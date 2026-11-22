<?php
declare(strict_types=1);
require __DIR__.'/admin.php';require __DIR__.'/backup_lib.php';
$args=getopt('',['file:','target:']);
try{
 $file=$args['file']??'';$target=$args['target']??'';
 if(!is_file($file)||!is_file($file.'.json')||!preg_match('/^investiga_[a-zA-Z0-9_]+$/',$target))throw new RuntimeException('Indica --file=RESPALDO.sql y --target=investiga_NOMBRE_NUEVO.');
 $manifest=json_decode(file_get_contents($file.'.json'),true,512,JSON_THROW_ON_ERROR);
 if(!hash_equals($manifest['sql_sha256'],hash_file('sha256',$file)))throw new RuntimeException('El respaldo no coincide con su hash.');
 if($target===rows('SELECT DATABASE() n')[0]['n'])throw new RuntimeException('La restauracion nunca sobrescribe la base activa.');
 $q=db()->prepare('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?');$q->execute([$target]);if($q->fetch())throw new RuntimeException('El destino ya existe; no se sobrescribe.');
 run('CREATE DATABASE `'.$target.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
 $temporary=tempnam(sys_get_temp_dir(),'iu_restore_');
 try{
  $sql=file_get_contents($file);$sql=str_replace('`'.$manifest['database'].'`.','`'.$target.'`.',$sql);file_put_contents($temporary,$sql);
  mysqlTool('mysql',['--default-character-set=utf8mb4','--database='.$target],$temporary);
 }finally{unlink($temporary);}
 $pdo=new PDO('mysql:host='.(getenv('DB_HOST')?:'127.0.0.1').';port='.(getenv('DB_PORT')?:3306).';dbname='.$target.';charset=utf8mb4',getenv('DB_USER'),getenv('DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $pdo->exec("SET time_zone='-05:00'");
 if(databaseManifest($pdo)!==$manifest['tables'])throw new RuntimeException('Diferencias entre los datos restaurados y el manifiesto; revisar destino.');
 if(isset($manifest['objects'])&&databaseObjects($pdo)!==$manifest['objects'])throw new RuntimeException('No coinciden las vistas, procedimientos o triggers restaurados.');
 $q=$pdo->prepare('SELECT VIEW_NAME FROM information_schema.VIEW_TABLE_USAGE WHERE VIEW_SCHEMA=? AND TABLE_SCHEMA<>?');$q->execute([$target,$target]);if($q->fetch())throw new RuntimeException('Una vista restaurada apunta a una base externa.');
 echo "Restauracion verificada en $target: filas y hashes coinciden. La base activa no cambio.\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
