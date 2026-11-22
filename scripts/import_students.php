<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';require __DIR__.'/../app/actions.php';
if(PHP_SAPI!=='cli')exit;
$args=getopt('',['file:','actor:']);
try{
 $file=$args['file']??'';$actor=filter_var($args['actor']??'',FILTER_VALIDATE_INT);
 if(!$actor||!is_file($file))throw new RuntimeException('Uso: php scripts/import_students.php --file=estudiantes.csv --actor=ID_USUARIO_DOCENTE');
 $handle=fopen($file,'rb');$header=fgetcsv($handle,0,',','"','');
 if(is_array($header)&&isset($header[0]))$header[0]=preg_replace('/^\xEF\xBB\xBF/','',$header[0]);
 if($header!==['codigo','nombre','correo'])throw new RuntimeException('Cabecera requerida: codigo,nombre,correo');
 $batch=[];$line=1;
 while(($fields=fgetcsv($handle,0,',','"',''))!==false){$line++;if($fields===[null])continue;
  if(count($fields)!==3)throw new RuntimeException('Columnas incorrectas en fila '.$line);
  $row=array_combine($header,array_map('trim',$fields));
  if(!$row['codigo']||!$row['nombre']||mb_strlen($row['codigo'])>30||mb_strlen($row['nombre'])>150||strlen($row['correo'])>180||!filter_var($row['correo'],FILTER_VALIDATE_EMAIL))throw new RuntimeException('Fila invalida: '.$line);
  $batch[]=$row;
 }fclose($handle);
 call_procedure('sp_importar_estudiantes',[$actor,json_encode($batch,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
 echo count($batch)." estudiantes cargados en una sola transaccion. Un error revierte el lote completo.\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
