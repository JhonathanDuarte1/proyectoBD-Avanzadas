<?php
// Solo CLI. No crea ni elimina bases de datos. Instala únicamente sobre una base vacía.
require __DIR__.'/admin.php';
require __DIR__.'/sql_file.php';
if(PHP_SAPI!=='cli')exit;
if(rows('SHOW TABLES'))exit("La base no está vacía. No se modificó nada.\n");
if(strlen(getenv('DEMO_PASSWORD')?:'')<10)exit("Define DEMO_PASSWORD (mínimo 10 caracteres) para crear las cuentas de demostración.\n");
try{
 executeSqlFile(db(),__DIR__.'/../database/01_schema.sql');executeSqlFile(db(),__DIR__.'/../database/02_seed.sql');
 $hash=password_hash(getenv('DEMO_PASSWORD'),PASSWORD_DEFAULT);
 foreach([[1,'decano@example.test','DECANO'],[2,'docente@example.test','DOCENTE'],[3,'decano.ingenieria@example.test','DECANO']] as $a)
 run('INSERT INTO usuarios(profesor_id,correo,rol,password_hash) VALUES(?,?,?,?)',array_merge($a,[$hash]));
 executeSqlFile(db(),__DIR__.'/../database/03_business.sql');
 echo "Instalación completada. Cuentas: decano@example.test, docente@example.test, decano.ingenieria@example.test\n";
}catch(Throwable $e){fwrite(STDERR,"Instalación incompleta: ".$e->getMessage()."\nEl DDL no es transaccional. Revisa la base de prueba antes de reintentar.\n");exit(1);}
