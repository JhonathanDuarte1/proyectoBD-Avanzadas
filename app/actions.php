<?php
require_once __DIR__.'/operaciones.php';
require_once __DIR__.'/validaciones.php';
function valid_date(string $value): bool {
 $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);return $date!==false&&$date->format('Y-m-d')===$value&&$value>='1000-01-01'&&$value<='9999-12-31';
}
function call_procedure(string $name,array $args): array {
 if(!preg_match('/^sp_[a-z_]+$/',$name))throw new LogicException('Procedimiento invalido');
 $q=db()->prepare('CALL '.$name.'('.implode(',',array_fill(0,count($args),'?')).')');$q->execute($args);
 $result=$q->columnCount()?$q->fetchAll():[];while($q->nextRowset()){}$q->closeCursor();return $result;
}
function process_post(?array $u,string $page,array $entities): void {
 foreach($_POST as $value)if(!is_string($value))throw new RuntimeException('Los campos enviados no tienen un formato válido.');
 assert_csrf();$action=$_POST['action']??'';
 if($action==='login'){
  if(($_SESSION['login_wait']??0)>time())throw new RuntimeException('Espera un minuto antes de volver a intentar.');
  $account=rows('SELECT * FROM usuarios WHERE correo=?',[trim($_POST['correo']??'')])[0]??null;
  if(!$account||!password_verify($_POST['password']??'',$account['clave_hash'])){
   $_SESSION['login_tries']=($_SESSION['login_tries']??0)+1;
   if($_SESSION['login_tries']>=5){$_SESSION['login_wait']=time()+60;$_SESSION['login_tries']=0;}
   throw new RuntimeException('Correo o contraseña incorrectos.');
  }
  if($account['rol']==='DECANO'&&!rows('SELECT f.id FROM facultades f JOIN profesores p ON p.id=f.decano_id JOIN grupos g ON g.id=p.grupo_id WHERE f.decano_id=? AND g.facultad_id=f.id',[$account['profesor_id']]))throw new RuntimeException('La cuenta no tiene un decanato vigente.');
  session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(32));$_SESSION['uid']=$account['id'];$_SESSION['login_tries']=0;redirect('/');
 }
 if(!$u)throw new RuntimeException('Inicia sesión.');
 if($action==='logout'){$_SESSION=[];session_destroy();redirect('/');}
 if($action==='cuenta'){
  $name=trim($_POST['nombre']??'');$mail=trim($_POST['correo']??'');$cedula=trim($_POST['cedula']??'');
  if($cedula===''||mb_strlen($cedula)>20)throw new ErrorFormulario('Ingresa tu cédula con máximo 20 caracteres.','cedula');
  if(!$name||mb_strlen($name)>150)throw new ErrorFormulario('Ingresa tu nombre con máximo 150 caracteres.','nombre');
  if(!filter_var($mail,FILTER_VALIDATE_EMAIL)||strlen($mail)>180)throw new ErrorFormulario('Ingresa un correo válido con máximo 180 caracteres.','correo');
  unico('usuarios','correo',$mail,(int)$u['id'],'El correo ya está registrado.','correo');
  unico('profesores','cedula',$cedula,(int)$u['profesor_id'],'La cédula ya está registrada.','cedula');
  $old=rows('SELECT clave_hash FROM usuarios WHERE id=?',[$u['id']])[0];
  if(!password_verify($_POST['actual']??'',$old['clave_hash']))throw new ErrorFormulario('La contraseña actual no es correcta.','actual');
  $password=$_POST['nueva']??'';if($password!==''&&(strlen($password)<10||strlen($password)>72))throw new ErrorFormulario('La nueva contraseña debe tener entre 10 y 72 bytes.','nueva');
  actualizar_cuenta((int)$u['id'],$cedula,$name,$mail,$password!==''?password_hash($password,PASSWORD_DEFAULT):$old['clave_hash']);
 }elseif($action==='usuario'){
  if($u['rol']!=='DECANO')throw new RuntimeException('Acceso denegado.');
  $pid=filter_var($_POST['profesor_id']??'',FILTER_VALIDATE_INT);$mail=trim($_POST['correo']??'');$password=$_POST['password']??'';
  if(!$pid)throw new ErrorFormulario('Selecciona un profesor.','profesor_id');
  if(!filter_var($mail,FILTER_VALIDATE_EMAIL)||strlen($mail)>180)throw new ErrorFormulario('Ingresa un correo válido con máximo 180 caracteres.','correo');
  if(strlen($password)<10||strlen($password)>72)throw new ErrorFormulario('La contraseña inicial debe tener entre 10 y 72 bytes.','password');
  if(!rows('SELECT id FROM v_profesores WHERE id=? AND facultad_id=?',[$pid,$u['facultad_id']]))throw new ErrorFormulario('Selecciona un profesor de tu facultad.','profesor_id');
  unico('usuarios','profesor_id',$pid,0,'El profesor ya tiene una cuenta de acceso.','profesor_id');
  unico('usuarios','correo',$mail,0,'El correo ya está registrado.','correo');
  crear_cuenta((int)$u['id'],(int)$pid,$mail,password_hash($password,PASSWORD_DEFAULT));
 }else{
  if(!isset($entities[$page])||!writable($page,$u))throw new RuntimeException('No tienes permiso para esta operación.');
  $id=filter_var($_POST['id']??0,FILTER_VALIDATE_INT);if($id===false||$id<0)throw new RuntimeException('Identificador inválido.');
  $existing=$id?(rows("SELECT * FROM `$page` WHERE id=?",[$id])[0]??null):null;
  if($id&&(!$existing||!can_edit($page,$existing,$u)))throw new RuntimeException('Registro fuera de tu ámbito de edición.');
  if($action==='delete'){
   if(!$id)throw new RuntimeException('Selecciona un registro.');
   eliminar_registro((int)$u['id'],$page,$id);
  }elseif($action==='save'){
   $fields=$entities[$page]['campos'];if(!$id)$fields=array_merge($fields,$entities[$page]['creacion']??[]);$data=[];
   foreach($fields as $key=>$field){
    $val=trim((string)($_POST[$key]??''));$optional=$field[3]??false;
    if($val===''&&!$optional)throw new ErrorFormulario('Completa: '.$field[0],$key);
    $max=['codigo'=>30,'cedula'=>20,'correo'=>180,'nombre'=>$page==='proyectos'||$page==='titulos'?180:150,'titulo_inicial'=>180][$key]??180;
    if(mb_strlen($val)>$max)throw new ErrorFormulario($field[0].': máximo '.$max.' caracteres.',$key);
    if($field[1]==='select'&&!in_array($val,array_map('strval',array_column(options($field[2],$u),'id')),true))throw new ErrorFormulario('Selección no permitida: '.$field[0].'. Elige una opción disponible de tu facultad.',$key);
    if($field[1]==='email'&&!filter_var($val,FILTER_VALIDATE_EMAIL))throw new ErrorFormulario('Ingresa un correo válido.',$key);
    if($field[1]==='number'&&is_numeric($val)&&(float)$val<0)throw new ErrorFormulario($field[0].' no puede ser negativo.',$key);
    if($field[1]==='number'&&(!preg_match('/^\d+(\.\d{1,2})?$/D',$val)||!is_finite((float)$val)))throw new ErrorFormulario($field[0].': ingresa un número válido con máximo dos decimales.',$key);
    if(in_array($key,['dedicacion','dedicacion_inicial'],true)&&((float)$val<=0||(float)$val>100))throw new ErrorFormulario('La dedicación debe ser mayor que 0 y menor o igual a 100.',$key);
    if($key==='presupuesto'&&(float)$val>=10000000000000)throw new ErrorFormulario('El presupuesto debe ser menor que 10 billones de pesos.',$key);
    if($key==='experiencia'&&(!ctype_digit($val)||(float)$val>2147483647))throw new ErrorFormulario('La experiencia se expresa en años enteros no negativos, hasta 2147483647.',$key);
    if($field[1]==='date'&&$val!==''&&!valid_date($val))throw new ErrorFormulario('Fecha inválida: '.$field[0].'.',$key);
    $data[$key]=$val===''?null:$val;
   }
   validar_registro($page,$id,$data,$u);
   guardar_registro((int)$u['id'],$page,$id,$data);
  }else throw new RuntimeException('Acción inválida.');
 }
 $_SESSION['flash']='Cambios guardados.';redirect('/?page='.urlencode($page));
}
