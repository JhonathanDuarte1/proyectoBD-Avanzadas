<?php
require __DIR__.'/admin.php';
$database=rows('SELECT DATABASE() nombre')[0]['nombre'];
$service=parse_ini_file(__DIR__.'/../.env',false,INI_SCANNER_RAW);
$web=new PDO('mysql:host='.$service['DB_HOST'].';port='.$service['DB_PORT'].';dbname='.$service['DB_NAME'].';charset=utf8mb4',$service['DB_USER'],$service['DB_PASSWORD'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$evidence=[
 'fecha_bogota'=>date(DATE_ATOM),'database'=>$database,'mysql'=>rows('SELECT VERSION() version')[0]['version'],
 'tablas'=>rows("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME"),
 'procedimientos'=>rows("SELECT ROUTINE_NAME,SECURITY_TYPE FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() ORDER BY ROUTINE_NAME"),
 'vistas'=>rows("SELECT TABLE_NAME FROM information_schema.VIEWS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME"),
 'triggers'=>rows("SELECT TRIGGER_NAME,EVENT_MANIPULATION,EVENT_OBJECT_TABLE,ACTION_TIMING FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() ORDER BY TRIGGER_NAME"),
 'planes'=>rows('EXPLAIN SELECT * FROM v_proyectos WHERE facultad_id=1'),
 'cuenta_servicio'=>$web->query('SELECT CURRENT_USER() cuenta,CURRENT_ROLE() perfil')->fetch(),
 'grants_servicio'=>$web->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN)];
file_put_contents(__DIR__.'/../docs/evidencias-bd.json',json_encode($evidence,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
echo "Evidencia de motor, perfiles, objetos y plan generada sin credenciales.\n";
