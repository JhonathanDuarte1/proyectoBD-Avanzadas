<?php
// Crea una cuenta administrativa limitada a la base del proyecto para phpMyAdmin local.
require __DIR__.'/admin.php';
if(!in_array('--admin-local',$argv,true))exit("Esta herramienta requiere --admin-local y autorizacion del propietario.\n");
try {
 $database=rows('SELECT DATABASE() nombre')[0]['nombre'];
 $name='iu_pma_'.substr(hash('sha256',$database),0,8);$file=__DIR__.'/../.env.phpmyadmin';
 if(is_file($file)){
  $settings=parse_ini_file($file,false,INI_SCANNER_RAW);
  if(($settings['DB_NAME']??'')!==$database)throw new RuntimeException('La configuración existente pertenece a otra base.');
  $password=$settings['DB_PASSWORD'];
 }else{$password=bin2hex(random_bytes(12));}
 $account=db()->quote($name)."@'localhost'";
 run('CREATE USER IF NOT EXISTS '.$account.' IDENTIFIED BY '.db()->quote($password));
 run('GRANT ALL PRIVILEGES ON `'.str_replace('`','``',$database).'`.* TO '.$account);
 file_put_contents($file,'DB_HOST='.(getenv('DB_HOST')?:'127.0.0.1')."\nDB_PORT=".(getenv('DB_PORT')?:3306)."\nDB_NAME=$database\nDB_USER=$name\nDB_PASSWORD=$password\n");
 $pdo=new PDO('mysql:host='.(getenv('DB_HOST')?:'127.0.0.1').';port='.(getenv('DB_PORT')?:3306).';dbname='.$database.';charset=utf8mb4',$name,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 echo 'Cuenta comprobada para '.$database.'. Configuración privada: .env.phpmyadmin. No se muestran contraseñas.' ."\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
