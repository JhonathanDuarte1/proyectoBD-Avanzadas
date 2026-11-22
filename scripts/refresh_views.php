<?php
// Reaplica exclusivamente las tres vistas de la definición canónica, conservando los datos.
require __DIR__.'/admin.php';
if(PHP_SAPI!=='cli')exit;
$schema=file_get_contents(__DIR__.'/../database/01_schema.sql');
preg_match_all('/CREATE VIEW\s+v_[a-z_]+\s+AS\s+.*?;/s',$schema,$matches);
if(count($matches[0])!==3)throw new RuntimeException('No se identificaron las tres vistas.');
foreach($matches[0] as $sql)db()->exec(preg_replace('/^CREATE VIEW/','CREATE OR REPLACE VIEW',$sql));
echo "Vistas actualizadas; datos conservados.\n";
