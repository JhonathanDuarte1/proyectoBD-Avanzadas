<?php
class ErrorFormulario extends RuntimeException {
 public string $campo;
 public function __construct(string $mensaje,string $campo='') {parent::__construct($mensaje);$this->campo=$campo;}
}
function unico(string $tabla,string $columna,$valor,int $id,string $mensaje,string $campo): void {
 // Los nombres son constantes del código; el contenido se envía como parámetro.
 if(rows("SELECT id FROM `$tabla` WHERE `$columna`=? AND id<>?",[$valor,$id]))throw new ErrorFormulario($mensaje,$campo);
}
function validar_registro(string $tabla,int $id,array $d,array $u): void {
 if(isset($d['codigo']))unico($tabla,'codigo',$d['codigo'],$id,'El código ya existe. Ingresa uno diferente.','codigo');
 if(isset($d['cedula']))unico($tabla,'cedula',$d['cedula'],$id,'La cédula ya está registrada.','cedula');
 if(isset($d['correo']))unico($tabla,'correo',$d['correo'],$id,'El correo ya está registrado.','correo');
 if($tabla==='proyectos'){
  unico($tabla,'nombre',$d['nombre'],$id,'Ya existe un proyecto con ese nombre.','nombre');
  if($d['inicio']>=$d['fin'])throw new ErrorFormulario('La fecha de inicio debe ser anterior a la fecha de fin.','inicio');
  if($id&&rows('SELECT id FROM participaciones WHERE proyecto_id=? AND (incorporacion<? OR incorporacion>? OR retiro>?)',[$id,$d['inicio'],$d['fin'],$d['fin']]))throw new ErrorFormulario('Las fechas del proyecto deben incluir todas sus participaciones registradas.','inicio');
  if($id&&rows('SELECT id FROM estudiante_proyecto WHERE proyecto_id=? AND (fecha_incorporacion<? OR fecha_incorporacion>? OR fecha_retiro>?)',[$id,$d['inicio'],$d['fin'],$d['fin']]))throw new ErrorFormulario('Las fechas del proyecto deben incluir las participaciones de sus estudiantes.','inicio');
 }
 if($tabla==='grupos'&&rows('SELECT id FROM grupos WHERE facultad_id=? AND nombre=? AND id<>?',[$u['facultad_id'],$d['nombre'],$id]))throw new ErrorFormulario('Ya existe un grupo con ese nombre en tu facultad.','nombre');
 if($tabla==='titulos'&&rows('SELECT id FROM titulos WHERE profesor_id=? AND nombre=? AND id<>?',[$d['profesor_id'],$d['nombre'],$id]))throw new ErrorFormulario('Este profesor ya tiene registrado ese título.','nombre');
 if($tabla==='estudiante_proyecto'&&rows('SELECT id FROM estudiante_proyecto WHERE estudiante_id=? AND proyecto_id=? AND id<>?',[$d['estudiante_id'],$d['proyecto_id'],$id]))throw new ErrorFormulario('El estudiante ya está vinculado a ese proyecto.','estudiante_id');
 if($tabla==='estudiante_proyecto')validar_periodo_estudiante($id,$d);
 if($tabla==='participaciones'){
  $p=rows('SELECT inicio,fin FROM proyectos WHERE id=? AND grupo_id=?',[$d['proyecto_id'],$u['grupo_id']])[0]??null;
  if(!$p)throw new ErrorFormulario('Selecciona un proyecto de tu grupo.','proyecto_id');
  if($d['retiro']!==null&&$d['incorporacion']>$d['retiro'])throw new ErrorFormulario('La incorporación no puede ser posterior a la fecha de retiro.','retiro');
  if($d['incorporacion']<$p['inicio']||$d['incorporacion']>$p['fin'])throw new ErrorFormulario('La incorporación debe estar entre '.$p['inicio'].' y '.$p['fin'].', las fechas del proyecto.','incorporacion');
  if($d['retiro']!==null&&$d['retiro']>$p['fin'])throw new ErrorFormulario('El retiro no puede ser posterior al fin del proyecto: '.$p['fin'].'.','retiro');
  if(rows('SELECT id FROM participaciones WHERE proyecto_id=? AND profesor_id=? AND id<>? AND incorporacion<=? AND COALESCE(retiro,?)>=?',[$d['proyecto_id'],$d['profesor_id'],$id,$d['retiro']??$p['fin'],$p['fin'],$d['incorporacion']]))throw new ErrorFormulario('El profesor ya tiene una participación que se cruza con esas fechas.','incorporacion');
 }
}
function validar_periodo_estudiante(int $id,array $d): void {
 $p=rows('SELECT inicio,fin FROM proyectos WHERE id=?',[$d['proyecto_id']])[0]??null;
 if(!$p)throw new ErrorFormulario('Selecciona un proyecto disponible.','proyecto_id');
 $inicio=$d['fecha_incorporacion']??'';$fin=$d['fecha_retiro']??null;
 if(!valid_date($inicio)||($fin!==null&&!valid_date($fin)))throw new ErrorFormulario('Ingresa fechas de participación válidas.','fecha_incorporacion');
 if($inicio<$p['inicio']||$inicio>$p['fin'])throw new ErrorFormulario('La incorporación del estudiante debe estar entre '.$p['inicio'].' y '.$p['fin'].'.','fecha_incorporacion');
 if($fin!==null&&$fin<$inicio)throw new ErrorFormulario('El retiro del estudiante no puede ser anterior a su incorporación.','fecha_retiro');
 if($fin!==null&&$fin>$p['fin'])throw new ErrorFormulario('El retiro del estudiante no puede superar el fin del proyecto: '.$p['fin'].'.','fecha_retiro');
 // Sin retiro, el período permanece abierto: debe cerrarse antes de pasar a otro proyecto.
 if(rows("SELECT id FROM estudiante_proyecto WHERE estudiante_id=? AND id<>? AND fecha_incorporacion<=COALESCE(?,'9999-12-31') AND COALESCE(fecha_retiro,'9999-12-31')>=?",[$d['estudiante_id'],$id,$fin,$inicio]))throw new ErrorFormulario('El estudiante ya participa en otro proyecto durante esas fechas. Registra su retiro o elige un período sin cruces.','fecha_incorporacion');
}
function detalle_error(Throwable $e): array {
 if($e instanceof ErrorFormulario)return ['message'=>$e->getMessage(),'field'=>$e->campo];
 if(!($e instanceof PDOException))return ['message'=>$e->getMessage(),'field'=>''];
 $numero=(int)($e->errorInfo[1]??0);$interno=$e->errorInfo[2]??'';
 if($e->getCode()==='45000')return ['message'=>$interno,'field'=>''];
 if(in_array($numero,[1213,1205],true))return ['message'=>'Coincidieron cambios simultáneos. Vuelve a guardar; tus datos se conservan.','field'=>''];
 if($numero===1062){
  // También cubre un duplicado que aparezca entre la comprobación y el INSERT.
  foreach(['codigo'=>['El código ya existe. Ingresa uno diferente.','codigo'],'correo'=>['El correo ya está registrado.','correo'],'cedula'=>['La cédula ya está registrada.','cedula'],'profesor_id'=>['El profesor ya tiene una cuenta de acceso.','profesor_id'],'nombre'=>['Ese nombre ya está registrado para esta relación.','nombre'],'estudiante_id'=>['El estudiante ya está vinculado a ese proyecto.','estudiante_id']] as $clave=>$dato){
   if(preg_match("/for key '[^']*\\b".preg_quote($clave,'/')."[^']*'/i",$interno))return ['message'=>$dato[0],'field'=>$dato[1]];
  }
  return ['message'=>'El registro ya existe. Revisa los datos que deben ser únicos.','field'=>''];
 }
 if($numero===1452)return ['message'=>'Una selección ya no está disponible. Revisa el grupo, profesor, proyecto o estudiante.','field'=>''];
 if($numero===1451)return ['message'=>'No se puede eliminar este registro porque tiene datos relacionados.','field'=>''];
 if(in_array($numero,[1406,1264],true))return ['message'=>'Uno de los valores supera el tamaño permitido. Revisa la longitud de los textos y los montos.','field'=>''];
 if(in_array($numero,[1292,1366,1048],true))return ['message'=>'Revisa que las fechas sean válidas y que todos los campos obligatorios tengan el tipo de dato indicado.','field'=>''];
 if($numero===3819)return ['message'=>'Revisa las fechas, los valores no negativos y la dedicación entre 0 y 100.','field'=>''];
 return ['message'=>'No se pudo guardar por un error del servidor. Tus datos se conservan; vuelve a intentarlo.','field'=>''];
}
