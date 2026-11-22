<?php
require __DIR__.'/../scripts/admin.php';
if(PHP_SAPI!=='cli')exit;
$checks=0;
function check(bool $ok,string $message): void {global $checks;if(!$ok)throw new RuntimeException($message);$checks++;echo "OK $message\n";}
function rejected(callable $fn,string $message): void {try{$fn();}catch(PDOException $e){check(true,$message);return;}throw new RuntimeException('No se rechazó: '.$message);}
try {
db()->beginTransaction();
check(count(rows('SELECT * FROM v_proyectos'))>=3,'Vista de proyectos disponible');
check(isset(rows("SELECT COUNT(*) n FROM v_proyectos WHERE estado='ACTIVO'")[0]['n']),'Comparación de estados compatible con el cotejamiento del motor');
$p=rows("SELECT * FROM proyectos WHERE codigo='P-001'")[0];
$teacher=rows("SELECT * FROM profesores WHERE cedula='10000001'")[0];
rejected(fn()=>run('INSERT INTO participaciones(proyecto_id,profesor_id,incorporacion,dedicacion) VALUES(?,?,?,?)',[$p['id'],$teacher['id'],'2026-03-01',10]),'Rechaza participación superpuesta');
rejected(fn()=>run('INSERT INTO participaciones(proyecto_id,profesor_id,incorporacion,dedicacion) VALUES(?,?,?,?)',[$p['id'],$teacher['id'],'2024-03-01',10]),'Rechaza participación fuera del proyecto');
rejected(fn()=>run('UPDATE proyectos SET presupuesto=-1 WHERE id=?',[$p['id']]),'Rechaza presupuesto negativo');
rejected(fn()=>run("UPDATE proyectos SET inicio='2027-01-01' WHERE id=?",[$p['id']]),'Rechaza fechas incompatibles');
rejected(fn()=>run('INSERT INTO grupos(codigo,nombre,facultad_id,area_id) VALUES(?,?,?,?)',['G-SOFT','Duplicado',1,1]),'Rechaza código de grupo duplicado');
run('UPDATE proyectos SET presupuesto=presupuesto+1 WHERE id=?',[$p['id']]);
check((int)rows('SELECT COUNT(*) n FROM auditoria WHERE proyecto_id=?',[$p['id']])[0]['n']>0,'Trigger de auditoría registra modificación');
$q=db()->prepare('CALL sp_presupuesto(?,?,?)');$q->execute([1,'2026-01-01','2026-12-31']);$budget=$q->fetch();$q->closeCursor();
check((float)$budget['presupuesto']===45000001.0,'Presupuesto suma proyectos que cruzan el período');
$student=rows("SELECT id FROM estudiantes WHERE codigo='20260001'")[0]['id'];
$other=rows("SELECT id FROM proyectos WHERE codigo='P-002'")[0]['id'];
run('INSERT IGNORE INTO estudiante_proyecto(estudiante_id,proyecto_id) VALUES(?,?)',[$student,$other]);
check((int)rows('SELECT COUNT(*) n FROM estudiante_proyecto WHERE estudiante_id=?',[$student])[0]['n']>=2,'Un estudiante participa en varios proyectos sin duplicar su identidad');
rejected(fn()=>run('INSERT INTO estudiante_proyecto(estudiante_id,proyecto_id) VALUES(?,?)',[$student,$other]),'Rechaza vínculo estudiante-proyecto duplicado');
rejected(fn()=>run('INSERT INTO estudiante_proyecto(estudiante_id,proyecto_id) VALUES(?,?)',[$student,2147483647]),'FK de vinculación exige proyecto existente');
rejected(fn()=>run('INSERT INTO participaciones(proyecto_id,profesor_id,incorporacion,dedicacion) VALUES(?,?,?,?)',[2147483647,$teacher['id'],'2026-03-01',10]),'FK de participación exige proyecto existente');
rejected(fn()=>run('INSERT INTO titulos(profesor_id,nombre) SELECT profesor_id,nombre FROM titulos LIMIT 1'),'Clave compuesta impide título repetido por profesor');
rejected(fn()=>run('INSERT INTO telefonos_facultad(facultad_id,numero) SELECT facultad_id,numero FROM telefonos_facultad LIMIT 1'),'Clave compuesta impide teléfono repetido por facultad');
run("INSERT INTO titulos(profesor_id,nombre) VALUES(?,'Título de prueba compartido'),(?,'Título de prueba compartido')",[$teacher['id'],2]);
check((int)rows("SELECT COUNT(*) n FROM titulos WHERE nombre='Título de prueba compartido'")[0]['n']===2,'Distintos profesores pueden tener el mismo título');
run("INSERT INTO telefonos_facultad(facultad_id,numero) VALUES(1,'PRUEBA-001'),(2,'PRUEBA-001')");
check((int)rows("SELECT COUNT(*) n FROM telefonos_facultad WHERE numero='PRUEBA-001'")[0]['n']===2,'El teléfono se identifica junto con su facultad');
check(!rows("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='participaciones' AND COLUMN_NAME='vigente'"),'Vigente no se almacena en participaciones');
foreach(['telefonos_facultad'=>['facultad_id','numero'],'titulos'=>['profesor_id','nombre'],'estudiante_proyecto'=>['estudiante_id','proyecto_id'],'proyectos'=>['id']] as $table=>$expected){
 $pk=rows('SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=? ORDER BY ORDINAL_POSITION',[$table,'PRIMARY']);
 check(array_column($pk,'COLUMN_NAME')===$expected,'PK documentada coincide con SQL: '.$table);
}
db()->rollBack();echo "$checks verificaciones correctas; cambios revertidos.\n";
}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();fwrite(STDERR,$e->getMessage()."\n");exit(1);}
