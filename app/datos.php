<?php
// Las rutas del menú se conservan. La persistencia usa las tablas y las claves del diagrama.
function consulta_entidad(string $entidad): string {
 switch($entidad){
  case 'grupos':return <<<'SQL'
SELECT g.id,g.codigo_grupo codigo,g.nombre,g.area_interes,t.id_facultad facultad_id,p.id lider_id
 FROM Grupo_investigacion g JOIN Tiene_grupo t ON t.codigo_grupo=g.codigo_grupo
 LEFT JOIN Lidera l ON l.codigo_grupo=g.codigo_grupo LEFT JOIN Profesor p ON p.cedula=l.cedula
SQL;
  case 'profesores':return 'SELECT * FROM v_profesores';
  case 'proyectos':return 'SELECT * FROM v_proyectos';
  case 'participaciones':return 'SELECT * FROM v_participaciones';
  case 'estudiantes':return <<<'SQL'
SELECT e.id,e.codigo_estudiante codigo,e.nombre,e.correo,p.id registrado_por,r.facultad_id
 FROM Estudiante e JOIN Profesor p ON p.cedula=e.registrado_por JOIN v_profesores r ON r.id=p.id
SQL;
  case 'titulos':return 'SELECT t.id,p.id profesor_id,t.titulo nombre FROM profesor_titulo t JOIN Profesor p ON p.cedula=t.Cedula';
  case 'usuarios':return 'SELECT u.id_usuario id,p.id profesor_id,u.correo,u.password clave_hash,u.rol FROM Usuario u JOIN Tiene t ON t.id_usuario=u.id_usuario JOIN Profesor p ON p.cedula=t.cedula';
  case 'estudiante_proyecto':return 'SELECT i.id,e.id estudiante_id,p.id proyecto_id,i.fecha_inicio fecha_incorporacion,i.fecha_retiro FROM Investiga i JOIN Estudiante e ON e.codigo_estudiante=i.codigo_estudiante JOIN Proyecto_investigacion p ON p.codigo_proyecto=i.codigo_proyecto';
  case 'facultades':return "SELECT f.id_facultad id,f.nombre,f.sede,p.id decano_id,p.nombre decano,(SELECT GROUP_CONCAT(telefono SEPARATOR ', ') FROM Facultad_telefono t WHERE t.id_facultad=f.id_facultad) telefonos FROM Facultad f LEFT JOIN Es_decano d ON d.id_facultad=f.id_facultad LEFT JOIN Profesor p ON p.cedula=d.cedula";
 }
 throw new LogicException('Entidad no disponible.');
}
function registro_entidad(string $entidad,int $id): ?array {return rows('SELECT * FROM ('.consulta_entidad($entidad).') datos WHERE id=?',[$id])[0]??null;}
function clave_entidad(string $entidad,int $id): string {
 $mapa=['grupos'=>['Grupo_investigacion','codigo_grupo'],'profesores'=>['Profesor','cedula'],'proyectos'=>['Proyecto_investigacion','codigo_proyecto'],'estudiantes'=>['Estudiante','codigo_estudiante']];
 if(!isset($mapa[$entidad]))throw new LogicException('Clave no disponible.');
 [$tabla,$columna]=$mapa[$entidad];$r=rows("SELECT `$columna` clave FROM `$tabla` WHERE id=?",[$id])[0]??null;
 if(!$r)throw new RuntimeException('La selección ya no está disponible.');return $r['clave'];
}
function tabla_entidad(string $entidad): string {
 $mapa=['grupos'=>'Grupo_investigacion','profesores'=>'Profesor','titulos'=>'profesor_titulo','proyectos'=>'Proyecto_investigacion','participaciones'=>'Participa','estudiantes'=>'Estudiante','estudiante_proyecto'=>'Investiga'];
 if(!isset($mapa[$entidad]))throw new LogicException('Tabla no disponible.');return $mapa[$entidad];
}
function datos_usuario(int $id): ?array {
 return rows("SELECT u.id_usuario id,p.id profesor_id,u.correo,u.rol,p.cedula,p.nombre,p.grupo_id,p.facultad_id FROM Usuario u JOIN Tiene t ON t.id_usuario=u.id_usuario JOIN v_profesores p ON p.cedula=t.cedula WHERE u.id_usuario=? AND (u.rol<>'DECANO' OR EXISTS(SELECT 1 FROM Es_decano d WHERE d.cedula=p.cedula AND d.id_facultad=p.facultad_id))",[$id])[0]??null;
}

// Consulta normal, sin crear una vista adicional.
function consulta_resumen_facultad(): string {
 return <<<'SQL'
SELECT f.id_facultad facultad_id,f.nombre,COUNT(p.id) total,
 COALESCE(SUM(p.estado='ACTIVO'),0) activos,COALESCE(SUM(p.estado='TERMINADO'),0) terminados,
 COALESCE(SUM(p.presupuesto),0) presupuesto FROM Facultad f LEFT JOIN v_proyectos p ON p.facultad_id=f.id_facultad GROUP BY f.id_facultad,f.nombre
SQL;
}

// Consulta normal, sin crear una vista adicional.
function consulta_cartera_proyectos(): string {
 return <<<'SQL'
SELECT p.id,p.codigo,p.nombre,p.facultad_id,p.grupo_id,p.estado,p.presupuesto,
 (SELECT COUNT(DISTINCT a.cedula) FROM Participa a WHERE a.codigo_proyecto=p.codigo) profesores,
 (SELECT COUNT(DISTINCT e.codigo_estudiante) FROM Investiga e WHERE e.codigo_proyecto=p.codigo) estudiantes,
 (SELECT COUNT(DISTINCT o.presupuesto)+1 FROM v_proyectos o WHERE o.facultad_id=p.facultad_id AND o.presupuesto>p.presupuesto) prioridad_presupuesto FROM v_proyectos p
SQL;
}
