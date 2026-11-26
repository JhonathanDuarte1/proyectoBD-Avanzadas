<?php
declare(strict_types=1);
require __DIR__.'/admin.php';
$args=getopt('',['write-env:']);
try{
 $name=rows('SELECT DATABASE() n')[0]['n'];$suffix=substr(hash('sha256',$name),0,8);
 $webRole='iu_web_'.$suffix;$readRole='iu_lectura_'.$suffix;$webUser='iu_app_'.$suffix;$readUser='iu_report_'.$suffix;
 foreach([$webRole,$readRole] as $role)run('CREATE ROLE IF NOT EXISTS '.db()->quote($role));
 run('GRANT SELECT ON `'.$name.'`.* TO '.db()->quote($webRole));
 foreach(['Grupo_investigacion','Profesor','profesor_titulo','Usuario','Proyecto_investigacion','Participa','Estudiante','Investiga','Tiene','Lidera','Pertenece','Desarrolla','Tiene_grupo'] as $table)run('GRANT INSERT,UPDATE ON `'.$name.'`.`'.$table.'` TO '.db()->quote($webRole));
 foreach(['Proyecto_investigacion','Investiga'] as $table)run('GRANT DELETE ON `'.$name.'`.`'.$table.'` TO '.db()->quote($webRole));
 run('GRANT EXECUTE ON PROCEDURE `'.$name.'`.`sp_presupuesto` TO '.db()->quote($webRole));
 foreach(['v_proyectos','v_profesores','v_participaciones','Facultad','Facultad_telefono','Profesor','profesor_titulo','Grupo_investigacion','Es_decano','Tiene_grupo','Lidera','Pertenece','Proyecto_investigacion','Desarrolla','Participa','Estudiante','Investiga'] as $view)run('GRANT SELECT ON `'.$name.'`.`'.$view.'` TO '.db()->quote($readRole));
 $configs=[];
 foreach([[$webUser,$webRole],[$readUser,$readRole]] as [$user,$role]){
  $password=bin2hex(random_bytes(24));$account=db()->quote($user)."@'localhost'";
  // Las cuentas son exclusivas de esta base. No se modifican usuarios ajenos al proyecto.
  run('CREATE USER IF NOT EXISTS '.$account.' IDENTIFIED BY '.db()->quote($password));
  run('ALTER USER '.$account.' IDENTIFIED BY '.db()->quote($password));
  run('GRANT '.db()->quote($role).' TO '.$account);
  run('SET DEFAULT ROLE '.db()->quote($role).' TO '.$account);
  $configs[]='DB_HOST='.(getenv('DB_HOST')?:'127.0.0.1')."\nDB_PORT=".(getenv('DB_PORT')?:3306)."\nDB_NAME=$name\nDB_USER=$user\nDB_PASSWORD=$password\n";
 }
 $path=$args['write-env']??(__DIR__.'/../.env');
 file_put_contents($path,$configs[0]);file_put_contents($path.'.report',$configs[1]);
 echo "Roles de servicio y lectura configurados. Credenciales guardadas en $path y $path.report; no se muestran.\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
