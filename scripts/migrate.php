<?php
declare(strict_types=1);
require __DIR__.'/admin.php';
if(PHP_SAPI!=='cli')exit;
$version='20261117_correcciones_avance';
try {
 if(rows("SHOW TABLES LIKE 'schema_migrations'")&&rows('SELECT version FROM schema_migrations WHERE version=?',[$version]))exit("La corrección ya está aplicada. Sin cambios.\n");
 $old=rows("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='estudiantes' AND COLUMN_NAME='proyecto_id'");
 if(!$old)throw new RuntimeException('Esquema no reconocido o migración parcial. Revisa el respaldo antes de continuar.');
 if(rows('SELECT profesor_id,nombre FROM titulos GROUP BY profesor_id,nombre HAVING COUNT(*)>1'))throw new RuntimeException('Existen títulos repetidos para el mismo profesor. Revísalos antes de migrar; no se borró nada.');
 if(rows('SELECT e.id FROM estudiantes e JOIN proyectos p ON p.id=e.proyecto_id JOIN grupos g ON g.id=p.grupo_id WHERE g.lider_id IS NULL AND NOT EXISTS(SELECT 1 FROM profesores pr WHERE pr.grupo_id=g.id)'))throw new RuntimeException('Hay estudiantes cuyo grupo no tiene profesor que gestione su registro.');
 $fk=rows("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='estudiantes' AND COLUMN_NAME='proyecto_id' AND REFERENCED_TABLE_NAME='proyectos'");
 if(count($fk)!==1)throw new RuntimeException('No se pudo identificar inequívocamente la FK anterior.');
 echo 'Base: '.rows('SELECT DATABASE() nombre')[0]['nombre']."\n";
 echo count(rows('SELECT id FROM estudiantes'))." identidades y vínculos existentes se conservarán.\n";
 if(!in_array('--apply',$argv,true))exit("Prevalidación correcta. Para aplicar: php scripts/migrate.php --apply --backup=RUTA_DEL_RESPALDO.sql\n");
 $backup=null;foreach($argv as $arg)if(strpos($arg,'--backup=')===0)$backup=substr($arg,9);
 if(!$backup||!is_file($backup)||filesize($backup)<100)throw new RuntimeException('Indica un respaldo SQL existente mediante --backup=.');
 $sql=file_get_contents(__DIR__.'/../database/migrations/'.$version.'.sql');
 $sql=str_replace('__FK_ESTUDIANTE_PROYECTO__','`'.str_replace('`','``',$fk[0]['CONSTRAINT_NAME']).'`',$sql);
 // MySQL realiza commits implícitos en DDL. Ejecutar en mantenimiento y restaurar el respaldo si falla.
 db()->exec($sql);
 echo "Migración completada. Los estudiantes conservan sus datos y vínculos previos.\n";
}catch(Throwable $e){fwrite(STDERR,"No se completó la migración: ".$e->getMessage()."\nEl DDL no es atómico. Si ya había iniciado, no reintentes sin revisar el respaldo.\n");exit(1);}
