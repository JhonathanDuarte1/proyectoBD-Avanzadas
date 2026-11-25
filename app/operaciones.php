<?php
// Operaciones de los formularios. Las reglas de fechas permanecen en MySQL.
require_once __DIR__.'/authorization.php';

function contexto_usuario(int $actor): array {
 $u=rows("SELECT u.id,u.profesor_id,u.rol,p.grupo_id,g.facultad_id
 FROM usuarios u JOIN profesores p ON p.id=u.profesor_id
 JOIN grupos g ON g.id=p.grupo_id WHERE u.id=?",[$actor])[0]??null;
 if(!$u)throw new RuntimeException('Cuenta no autorizada.');
 if($u['rol']==='DECANO'){
  $facultad=rows('SELECT id FROM facultades WHERE decano_id=? AND id=?',[$u['profesor_id'],$u['facultad_id']])[0]??null;
  if(!$facultad)throw new RuntimeException('La cuenta no tiene un decanato vigente.');
  $u['facultad_id']=$facultad['id'];
 }
 return $u;
}

function guardar_registro(int $actor,string $tabla,int $id,array $datos): int {
 $u=contexto_usuario($actor);
 $entidades=require __DIR__.'/entities.php';
 if(!isset($entidades[$tabla])||!writable($tabla,$u))throw new RuntimeException('No tienes permiso para esta operación.');
 // Las comprobaciones posteriores a un bloqueo deben ver los cambios ya confirmados.
 db()->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
 db()->beginTransaction();
 try {
  $anterior=$id?(rows("SELECT * FROM `$tabla` WHERE id=? FOR UPDATE",[$id])[0]??null):null;
  if($id&&(!$anterior||!can_edit($tabla,$anterior,$u)))throw new RuntimeException('Registro fuera de tu ámbito de edición.');
  // Solo se escriben las columnas declaradas en entities.php, nunca nombres enviados por el usuario.
  $valores=[];
  foreach($entidades[$tabla]['campos'] as $columna=>$campo){
   $valor=$datos[$columna]??null;
   if($valor===null&&!($campo[3]??false))throw new RuntimeException('Completa: '.$campo[0]);
   $valores[$columna]=$valor;
  }
  if($tabla==='proyectos'){
   if((int)$valores['grupo_id']!==(int)$u['grupo_id'])throw new RuntimeException('Grupo no autorizado.');
   if(!$id&&(!isset($datos['dedicacion_inicial'])||$datos['dedicacion_inicial']<=0||$datos['dedicacion_inicial']>100))throw new RuntimeException('Indica una dedicación inicial entre 0 y 100.');
  }elseif($tabla==='profesores'){
   if(!rows('SELECT id FROM grupos WHERE id=? AND facultad_id=?',[$valores['grupo_id'],$u['facultad_id']]))throw new RuntimeException('Grupo de otra facultad.');
   if(!$id&&trim($datos['titulo_inicial']??'')==='')throw new RuntimeException('El profesor necesita su primer título.');
  }elseif($tabla==='grupos'){
   if(!rows('SELECT id FROM v_profesores WHERE id=? AND facultad_id=?',[$valores['lider_id'],$u['facultad_id']]))throw new RuntimeException('El líder debe ser un profesor de tu facultad.');
   if(!$id)$valores['facultad_id']=$u['facultad_id'];
  }elseif($tabla==='titulos'){
   if(!rows('SELECT id FROM v_profesores WHERE id=? AND facultad_id=?',[$valores['profesor_id'],$u['facultad_id']]))throw new RuntimeException('Profesor de otra facultad.');
   if($id&&(int)$anterior['profesor_id']!==(int)$valores['profesor_id']){
    rows('SELECT id FROM profesores WHERE id=? FOR UPDATE',[$anterior['profesor_id']]);
    if((int)rows('SELECT COUNT(*) n FROM titulos WHERE profesor_id=?',[$anterior['profesor_id']])[0]['n']<=1)throw new RuntimeException('No se puede trasladar el último título.');
   }
  }elseif($tabla==='participaciones'||$tabla==='estudiante_proyecto'){
   $origen=(int)($anterior['proyecto_id']??$valores['proyecto_id']);
   $destino=(int)$valores['proyecto_id'];
   rows('SELECT id FROM proyectos WHERE id IN (?,?) ORDER BY id FOR UPDATE',[$origen,$destino]);
   if($tabla==='participaciones'){
    if(!rows('SELECT id FROM proyectos WHERE id=? AND grupo_id=?',[$destino,$u['grupo_id']]))throw new RuntimeException('Proyecto no autorizado.');
    if(!rows('SELECT id FROM v_profesores WHERE id=? AND facultad_id=?',[$valores['profesor_id'],$u['facultad_id']]))throw new RuntimeException('El profesor pertenece a otra facultad.');
   }else{
    // Serializa cambios del mismo estudiante, incluso entre proyectos diferentes.
    rows('SELECT id FROM estudiantes WHERE id IN (?,?) ORDER BY id FOR UPDATE',[$anterior['estudiante_id']??$valores['estudiante_id'],$valores['estudiante_id']]);
    if(!rows('SELECT e.id FROM estudiantes e JOIN v_profesores r ON r.id=e.registrado_por WHERE e.id=? AND r.facultad_id=?',[$valores['estudiante_id'],$u['facultad_id']]))throw new RuntimeException('El estudiante pertenece a otra facultad.');
    foreach(array_unique([$origen,$destino]) as $proyecto){
     if(!rows('SELECT a.id FROM participaciones a JOIN v_proyectos p ON p.id=a.proyecto_id WHERE a.proyecto_id=? AND a.profesor_id=? AND a.responsable=1 AND p.facultad_id=?',[$proyecto,$u['profesor_id'],$u['facultad_id']]))throw new RuntimeException('Solo el profesor responsable gestiona los estudiantes de proyectos de su facultad.');
    }
   }
  }elseif($tabla==='estudiantes'&&!$id){
   $valores['registrado_por']=$u['profesor_id'];
  }
  $columnas=array_keys($valores);
  if($id){
   $asignaciones=implode(',',array_map(fn($c)=>"`$c`=?",$columnas));
   run("UPDATE `$tabla` SET $asignaciones WHERE id=?",array_merge(array_values($valores),[$id]));
  }else{
   $nombres=implode(',',array_map(fn($c)=>"`$c`",$columnas));
   $marcas=implode(',',array_fill(0,count($columnas),'?'));
   run("INSERT INTO `$tabla` ($nombres) VALUES($marcas)",array_values($valores));
   $id=(int)db()->lastInsertId();
   if($tabla==='profesores')run('INSERT INTO titulos(profesor_id,nombre) VALUES(?,?)',[$id,trim($datos['titulo_inicial'])]);
   if($tabla==='proyectos')run('INSERT INTO participaciones(proyecto_id,profesor_id,incorporacion,dedicacion,responsable) VALUES(?,?,?,?,1)',[$id,$u['profesor_id'],$valores['inicio'],$datos['dedicacion_inicial']]);
  }
  if($tabla==='participaciones'){
   foreach(array_unique([$origen,$destino]) as $proyecto){
    if(!rows('SELECT id FROM participaciones WHERE proyecto_id=? AND responsable=1',[$proyecto]))throw new RuntimeException('Cada proyecto debe conservar un profesor responsable.');
   }
  }
  db()->commit();return $id;
 }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}

function eliminar_registro(int $actor,string $tabla,int $id): void {
 $u=contexto_usuario($actor);
 if(!in_array($tabla,['proyectos','estudiante_proyecto'],true)||$u['rol']!=='DOCENTE')throw new RuntimeException('Eliminación no autorizada.');
 $registro=rows("SELECT * FROM `$tabla` WHERE id=?",[$id])[0]??null;
 if(!$registro||!can_edit($tabla,$registro,$u))throw new RuntimeException('Registro fuera de tu ámbito de edición.');
 run("DELETE FROM `$tabla` WHERE id=?",[$id]);
}

function actualizar_cuenta(int $actor,string $cedula,string $nombre,string $correo,string $hash): void {
 $u=contexto_usuario($actor);
 db()->beginTransaction();
 try{
  run('UPDATE profesores SET cedula=?,nombre=? WHERE id=?',[$cedula,$nombre,$u['profesor_id']]);
  run('UPDATE usuarios SET correo=?,clave_hash=? WHERE id=?',[$correo,$hash,$actor]);
  db()->commit();
 }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}

function crear_cuenta(int $actor,int $profesor,string $correo,string $hash): void {
 $u=contexto_usuario($actor);
 if($u['rol']!=='DECANO'||!rows('SELECT id FROM v_profesores WHERE id=? AND facultad_id=?',[$profesor,$u['facultad_id']]))throw new RuntimeException('Solo el decano crea cuentas de su facultad.');
 run("INSERT INTO usuarios(profesor_id,correo,clave_hash,rol) VALUES(?,?,?,'DOCENTE')",[$profesor,$correo,$hash]);
}

function importar_estudiantes(int $actor,array $estudiantes): void {
 $u=contexto_usuario($actor);
 if($u['rol']!=='DOCENTE'||!$estudiantes)throw new RuntimeException('Lote de estudiantes inválido.');
 db()->beginTransaction();
 try{
  foreach($estudiantes as $estudiante){
   foreach(['codigo','nombre','correo'] as $campo)if(!is_string($estudiante[$campo]??null)||trim($estudiante[$campo])==='')throw new RuntimeException('Completa código, nombre y correo.');
   run('INSERT INTO estudiantes(codigo,nombre,correo,registrado_por) VALUES(?,?,?,?)',[trim($estudiante['codigo']),trim($estudiante['nombre']),trim($estudiante['correo']),$u['profesor_id']]);
  }
  db()->commit();
 }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}

function consultar_presupuesto(int $actor,string $desde,string $hasta): array {
 $u=contexto_usuario($actor);
 if($u['rol']!=='DECANO')throw new RuntimeException('Consulta reservada al decano.');
 return call_procedure('sp_presupuesto',[$u['facultad_id'],$desde,$hasta]);
}
