<?php
function mysqlTool(string $tool,array $extra,?string $input=null,?string $output=null): void {
 $bin=getenv('MYSQL_BIN')?:'C:/Program Files/MySQL/MySQL Server 9.6/bin';
 $cnf=tempnam(sys_get_temp_dir(),'iu_');
 $quote=fn($s)=>'"'.str_replace(['\\','"',"\n","\r"],['\\\\','\\"','\\n','\\r'],(string)$s).'"';
 file_put_contents($cnf,"[client]\nhost=".$quote(getenv('DB_HOST')?:'127.0.0.1')."\nport=".(getenv('DB_PORT')?:'3306')."\nuser=".$quote(getenv('DB_USER')?:'root')."\npassword=".$quote(getenv('DB_PASSWORD')?:'')."\n");
 try{
  $cmd=array_merge([$bin.'/'.$tool.'.exe','--defaults-extra-file='.$cnf],$extra);
  $pipes=[];$descriptors=[0=>$input?['file',$input,'rb']:['pipe','r'],1=>$output?['file',$output,'wb']:['pipe','w'],2=>['pipe','w']];
  $process=proc_open($cmd,$descriptors,$pipes,null,null,['bypass_shell'=>true]);
  if(!is_resource($process))throw new RuntimeException('No se pudo iniciar '.$tool);
  if(isset($pipes[0]))fclose($pipes[0]);
  $stdout=isset($pipes[1])?stream_get_contents($pipes[1]):'';if(isset($pipes[1]))fclose($pipes[1]);
  $stderr=stream_get_contents($pipes[2]);fclose($pipes[2]);$status=proc_close($process);
  if($status!==0)throw new RuntimeException($tool.' fallo: '.$stderr);
 }finally{unlink($cnf);}
}
function databaseManifest(PDO $pdo): array {
 $manifest=[];$pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES,true);
 $tables=$pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
 foreach($tables as $table){
  $columns=$pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll();
  $order=array_column(array_filter($columns,fn($c)=>$c['Key']==='PRI'),'Field');
  $sql="SELECT * FROM `$table`".($order?' ORDER BY '.implode(',',array_map(fn($c)=>'`'.$c.'`',$order)):'');
  $data=$pdo->query($sql)->fetchAll();
  $manifest[$table]=['rows'=>count($data),'sha256'=>hash('sha256',json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION|JSON_THROW_ON_ERROR))];
 }
 return $manifest;
}
function databaseObjects(PDO $pdo): array {
 return [
 'views'=>$pdo->query("SELECT TABLE_NAME FROM information_schema.VIEWS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN),
 'procedures'=>$pdo->query("SELECT ROUTINE_NAME FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() AND ROUTINE_TYPE='PROCEDURE' ORDER BY ROUTINE_NAME")->fetchAll(PDO::FETCH_COLUMN),
 'triggers'=>$pdo->query("SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() ORDER BY TRIGGER_NAME")->fetchAll(PDO::FETCH_COLUMN)];
}
function createBackup(PDO $pdo,string $path): void {
 if(is_file($path)||is_file($path.'.json'))throw new RuntimeException('No se sobrescriben respaldos existentes.');
 if(!is_dir(dirname($path)))mkdir(dirname($path),0700,true);
 $database=$pdo->query('SELECT DATABASE()')->fetchColumn();
 // Solo la cuenta administradora toma un breve bloqueo global de lectura para sincronizar dump y manifiesto.
 // El bloqueo se libera siempre. En esta instancia aislada no existen bases de otras aplicaciones.
 $pdo->exec('FLUSH TABLES WITH READ LOCK');
 try{
  $manifest=databaseManifest($pdo);$objects=databaseObjects($pdo);
  mysqlTool('mysqldump',['--single-transaction','--set-gtid-purged=OFF','--routines','--triggers','--events','--no-tablespaces','--hex-blob','--skip-add-locks',$database],null,$path);
 }finally{$pdo->exec('UNLOCK TABLES');}
 file_put_contents($path.'.json',json_encode(['database'=>$database,'created_at'=>date(DATE_ATOM),'sql_sha256'=>hash_file('sha256',$path),'tables'=>$manifest,'objects'=>$objects],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
}
