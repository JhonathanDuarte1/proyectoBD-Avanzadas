<?php
// Transferencia local conservadora: fuente configurada en .env; destino MySQL vacío.
declare(strict_types=1);
require __DIR__.'/admin.php';
require __DIR__.'/sql_file.php';
if(PHP_SAPI!=='cli')exit;
$targetName=getenv('MYSQL_TARGET_DATABASE')?:'investiga_u_migrado';
$targetPort=getenv('MYSQL_TARGET_PORT')?:'33180';
try {
 $source=db();
 $target=new PDO('mysql:host=127.0.0.1;port='.$targetPort.';dbname='.$targetName.';charset=utf8mb4',getenv('MYSQL_TARGET_USER')?:'root',getenv('MYSQL_TARGET_PASSWORD')?:'',[
 PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
 if($target->query('SHOW TABLES')->fetchAll())throw new RuntimeException('El destino no está vacío. No se copió ni eliminó nada.');
 if(!rows("SELECT version FROM schema_migrations WHERE version='20261117_correcciones_avance'"))throw new RuntimeException('Primero aplica la corrección de noviembre a la fuente.');
 $tables=['sedes','facultades','telefonos_facultad','areas','grupos','profesores','titulos','usuarios','proyectos','participaciones','estudiantes','estudiante_proyecto','auditoria'];
 $snapshot=[];
 // Una instantánea consistente; la fuente no se modifica.
 $source->beginTransaction();
 foreach($tables as $table)$snapshot[$table]=rows("SELECT * FROM `$table` ORDER BY id");
 $source->commit();
 $delimiter=';';$buffer='';
 foreach(file(__DIR__.'/../database/01_schema.sql') as $line){
  if(preg_match('/^DELIMITER\s+(\S+)/',trim($line),$m)){$delimiter=$m[1];continue;}
  if(preg_match('/^\s*--/',$line))continue;
  $buffer.=$line;
  if(substr(rtrim($buffer),-strlen($delimiter))===$delimiter){$target->exec(substr(rtrim($buffer),0,-strlen($delimiter)));$buffer='';}
 }
 // Las referencias circulares facultad/grupo/profesor requieren cargar el conjunto completo.
 // Los triggers se suspenden solo en este destino nuevo durante la importación y se recrean después.
 $triggers=$target->query("SHOW TRIGGERS")->fetchAll();$definitions=[];
 foreach($triggers as $trigger){$name=$trigger['Trigger'];$definition=$target->query('SHOW CREATE TRIGGER `'.$name.'`')->fetch();$definitions[]=$definition['SQL Original Statement'];$target->exec('DROP TRIGGER `'.$name.'`');}
 $target->exec('SET FOREIGN_KEY_CHECKS=0');$target->beginTransaction();
 foreach($snapshot as $table=>$records){
  foreach($records as $record){$columns=array_keys($record);$marks=implode(',',array_fill(0,count($columns),'?'));$names=implode(',',array_map(fn($c)=>'`'.$c.'`',$columns));$stmt=$target->prepare("INSERT INTO `$table` ($names) VALUES($marks)");$stmt->execute(array_values($record));}
  $loaded=$target->query("SELECT * FROM `$table` ORDER BY id")->fetchAll();
  if($loaded!=$records)throw new RuntimeException('La comprobación de datos falló en '.$table);
  echo $table.': '.count($records)." registros verificados\n";
 }
 $fks=$target->query('SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL')->fetchAll();
 foreach($fks as $fk){
  $t=$fk['TABLE_NAME'];$c=$fk['COLUMN_NAME'];$rt=$fk['REFERENCED_TABLE_NAME'];$rc=$fk['REFERENCED_COLUMN_NAME'];
  if($target->query("SELECT 1 FROM `$t` s LEFT JOIN `$rt` r ON s.`$c`=r.`$rc` WHERE s.`$c` IS NOT NULL AND r.`$rc` IS NULL LIMIT 1")->fetch())throw new RuntimeException('Referencia huérfana en '.$t.'.'.$c);
 }
 $target->commit();$target->exec('SET FOREIGN_KEY_CHECKS=1');
 foreach($definitions as $definition)$target->exec($definition);
 executeSqlFile($target,__DIR__.'/../database/03_business.sql');
 echo "Copia completada y verificada. La fuente y .env no se modificaron.\n";
}catch(Throwable $e){if(isset($target)&&$target->inTransaction())$target->rollBack();if(isset($source)&&$source->inTransaction())$source->rollBack();fwrite(STDERR,$e->getMessage()."\nSi se creó estructura en el destino, revisa esa base antes de reintentar.\n");exit(1);}
