<?php
function valid_date(string $value): bool {
 $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);return $date!==false&&$date->format('Y-m-d')===$value;
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
  if(!$account||!password_verify($_POST['password']??'',$account['password_hash'])){
   $_SESSION['login_tries']=($_SESSION['login_tries']??0)+1;
   if($_SESSION['login_tries']>=5){$_SESSION['login_wait']=time()+60;$_SESSION['login_tries']=0;}
   throw new RuntimeException('Correo o contraseña incorrectos.');
  }
  if($account['rol']==='DECANO'&&!rows('SELECT id FROM facultades WHERE decano_id=?',[$account['profesor_id']]))throw new RuntimeException('La cuenta no tiene un decanato vigente.');
  session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(32));$_SESSION['uid']=$account['id'];$_SESSION['login_tries']=0;redirect('/');
 }
 if(!$u)throw new RuntimeException('Inicia sesión.');
 if($action==='logout'){$_SESSION=[];session_destroy();redirect('/');}
 if($action==='cuenta'){
  $name=trim($_POST['nombre']??'');$mail=trim($_POST['correo']??'');$cedula=trim($_POST['cedula']??'');
  if($cedula===''||mb_strlen($cedula)>20)throw new RuntimeException('Revisa la cédula (máximo 20 caracteres).');
  if(!$name||mb_strlen($name)>150||!filter_var($mail,FILTER_VALIDATE_EMAIL)||strlen($mail)>180)throw new RuntimeException('Revisa nombre y correo.');
  $old=rows('SELECT password_hash FROM usuarios WHERE id=?',[$u['id']])[0];
  if(!password_verify($_POST['actual']??'',$old['password_hash']))throw new RuntimeException('La contraseña actual no es correcta.');
  $password=$_POST['nueva']??'';if($password!==''&&(strlen($password)<10||strlen($password)>72))throw new RuntimeException('Usa entre 10 y 72 bytes para la contraseña.');
  call_procedure('sp_cuenta',[$u['id'],$cedula,$name,$mail,$password!==''?password_hash($password,PASSWORD_DEFAULT):$old['password_hash']]);
 }elseif($action==='usuario'){
  if($u['rol']!=='DECANO')throw new RuntimeException('Acceso denegado.');
  $pid=filter_var($_POST['profesor_id']??'',FILTER_VALIDATE_INT);$mail=trim($_POST['correo']??'');$password=$_POST['password']??'';
  if(!$pid||!filter_var($mail,FILTER_VALIDATE_EMAIL)||strlen($mail)>180||strlen($password)<10||strlen($password)>72)throw new RuntimeException('Verifica profesor, correo y contraseña (entre 10 y 72 bytes).');
  call_procedure('sp_usuario',[$u['id'],$pid,$mail,password_hash($password,PASSWORD_DEFAULT)]);
 }else{
  if(!isset($entities[$page])||!writable($page,$u))throw new RuntimeException('No tienes permiso para esta operación.');
  $id=filter_var($_POST['id']??0,FILTER_VALIDATE_INT);if($id===false||$id<0)throw new RuntimeException('Identificador inválido.');
  $existing=$id?(rows("SELECT * FROM `$page` WHERE id=?",[$id])[0]??null):null;
  if($id&&(!$existing||!can_edit($page,$existing,$u)))throw new RuntimeException('Registro fuera de tu ámbito de edición.');
  if($action==='delete'){
   if(!$id)throw new RuntimeException('Selecciona un registro.');
   call_procedure('sp_eliminar',[$u['id'],$page,$id]);
  }elseif($action==='save'){
   $fields=$entities[$page]['campos'];if(!$id)$fields=array_merge($fields,$entities[$page]['creacion']??[]);$data=[];
   foreach($fields as $key=>$field){
    $val=trim((string)($_POST[$key]??''));$optional=$field[3]??false;
    if($val===''&&!$optional)throw new RuntimeException('Completa: '.$field[0]);
    $max=['codigo'=>30,'cedula'=>20,'correo'=>180,'nombre'=>$page==='proyectos'||$page==='titulos'?180:150,'titulo_inicial'=>180][$key]??180;
    if(mb_strlen($val)>$max)throw new RuntimeException($field[0].': máximo '.$max.' caracteres.');
    if($field[1]==='select'&&!in_array($val,array_map('strval',array_column(options($field[2],$u),'id')),true))throw new RuntimeException('Selección no permitida: '.$field[0]);
    if($field[1]==='email'&&!filter_var($val,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Correo inválido.');
    if($field[1]==='number'&&(!preg_match('/^\d+(\.\d{1,2})?$/D',$val)||!is_finite((float)$val)))throw new RuntimeException('Valor numérico inválido.');
    if($key==='experiencia'&&(!ctype_digit($val)||(float)$val>2147483647))throw new RuntimeException('La experiencia se expresa en años enteros no negativos.');
    if($field[1]==='date'&&$val!==''&&!valid_date($val))throw new RuntimeException('Fecha inválida.');
    $data[$key]=$val===''?null:$val;
   }
   call_procedure('sp_guardar',[$u['id'],$page,$id,json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
  }else throw new RuntimeException('Acción inválida.');
 }
 $_SESSION['flash']='Cambios guardados.';redirect('/?page='.urlencode($page));
}
