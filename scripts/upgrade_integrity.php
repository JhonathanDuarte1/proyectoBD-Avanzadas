<?php
declare(strict_types=1);
require __DIR__.'/admin.php';require __DIR__.'/sql_file.php';
try{
 $problems=rows("SELECT 'Facultad sin decano' problema,id FROM facultades WHERE decano_id IS NULL UNION ALL SELECT 'Grupo sin lider',id FROM grupos WHERE lider_id IS NULL UNION ALL SELECT 'Profesor sin titulo',p.id FROM profesores p WHERE NOT EXISTS(SELECT 1 FROM titulos t WHERE t.profesor_id=p.id) UNION ALL SELECT 'Facultad sin telefono',f.id FROM facultades f WHERE NOT EXISTS(SELECT 1 FROM telefonos_facultad t WHERE t.facultad_id=f.id) UNION ALL SELECT 'Proyecto sin responsable',p.id FROM proyectos p WHERE NOT EXISTS(SELECT 1 FROM participaciones a WHERE a.proyecto_id=p.id AND a.responsable=1)");
 if($problems)throw new RuntimeException('Corrige los datos antes de actualizar: '.json_encode($problems,JSON_UNESCAPED_UNICODE));
 if(!in_array('--apply',$argv,true))exit("Prevalidacion correcta. Ejecuta --apply despues de respaldar la base.\n");
 foreach(['participaciones','estudiante_proyecto'] as $table){
  $fk=rows("SELECT k.CONSTRAINT_NAME,r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.COLUMN_NAME='proyecto_id' AND k.REFERENCED_TABLE_NAME='proyectos'",[$table])[0];
  if($fk['DELETE_RULE']!=='CASCADE')run("ALTER TABLE `$table` DROP FOREIGN KEY `".str_replace('`','``',$fk['CONSTRAINT_NAME'])."`, ADD CONSTRAINT `fk_{$table}_proyecto` FOREIGN KEY(proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE");
 }
 $checks=[
 'sedes'=>['nombre'], 'facultades'=>['nombre'],'telefonos_facultad'=>['numero'],'areas'=>['nombre'],
 'grupos'=>['codigo','nombre'],'profesores'=>['cedula','nombre'],'titulos'=>['nombre'],'proyectos'=>['codigo','nombre'],
 'usuarios'=>['correo'],'estudiantes'=>['codigo','nombre','correo']];
 foreach($checks as $table=>$columns)foreach($columns as $column){
  $name='ck_'.$table.'_'.$column.'_vacio';
  if(!rows('SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=?',[$table,$name]))run("ALTER TABLE `$table` ADD CONSTRAINT `$name` CHECK(CHAR_LENGTH(TRIM(`$column`))>0)");
 }
 foreach(['usuarios','estudiantes'] as $table){
  $name='ck_'.$table.'_correo_formato';
  if(!rows('SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=?',[$table,$name]))run("ALTER TABLE `$table` ADD CONSTRAINT `$name` CHECK(correo REGEXP '^[^[:space:]@]+@[^[:space:]@]+[.][^[:space:]@]+$')");
 }
 if(!rows("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='ck_responsable_booleano'"))run('ALTER TABLE participaciones ADD CONSTRAINT ck_responsable_booleano CHECK(responsable IN(0,1))');
 executeSqlFile(db(),__DIR__.'/../database/03_business.sql');
 $schema=file_get_contents(__DIR__.'/../database/01_schema.sql');preg_match_all('/CREATE VIEW\s+v_[a-z_]+\s+AS\s+.*?;/s',$schema,$views);
 foreach($views[0] as $sql)db()->exec(preg_replace('/^CREATE VIEW/','CREATE OR REPLACE VIEW',$sql));
 run("INSERT IGNORE INTO schema_migrations(version) VALUES('20261119_integridad_seguridad')");
 $role='iu_web_'.substr(hash('sha256',rows('SELECT DATABASE() n')[0]['n']),0,8);
 // DROP PROCEDURE elimina sus GRANT. Restituir únicamente la lista pública si la cuenta ya existe.
 if(rows('SELECT User FROM mysql.user WHERE User=?',[$role]))foreach(['sp_guardar','sp_eliminar','sp_cuenta','sp_usuario','sp_presupuesto_usuario','sp_importar_estudiantes'] as $proc)run('GRANT EXECUTE ON PROCEDURE `'.rows('SELECT DATABASE() n')[0]['n'].'`.`'.$proc.'` TO '.db()->quote($role));
 echo "Reglas y procedimientos instalados; concesiones existentes restablecidas.\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\nDDL no atomico: revisar la base de destino si hubo un fallo.\n");exit(1);}
