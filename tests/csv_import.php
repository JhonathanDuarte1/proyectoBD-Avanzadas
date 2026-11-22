<?php
require __DIR__.'/../scripts/admin.php';require __DIR__.'/helpers.php';
$testCount=0;
try{
 if(!preg_match('/_(auditoria|test|fresh|restore)_/',rows('SELECT DATABASE() n')[0]['n']))throw new RuntimeException('Requiere base de pruebas.');
 $env=getenv('WEB_ENV_FILE')?:__DIR__.'/../tmp/auditoria_20261119/web.env';$web=testConnection($env);
 $tag=bin2hex(random_bytes(4));$file=__DIR__.'/../tmp/csv_'.$tag.'.csv';
 $handle=fopen($file,'wb');fwrite($handle,"\xEF\xBB\xBF");fputcsv($handle,['codigo','nombre','correo'],',','"','');fputcsv($handle,['IMP-'.$tag,'Nombre, con coma','imp'.$tag.'@example.test'],',','"','');fclose($handle);
 $childEnv=getenv();unset($childEnv['DB_NAME'],$childEnv['DB_USER'],$childEnv['DB_PASSWORD']);$childEnv['APP_ENV_FILE']=realpath($env);
 $pipes=[];$proc=proc_open([PHP_BINARY,__DIR__.'/../scripts/import_students.php','--file='.$file,'--actor=2'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$childEnv,['bypass_shell'=>true]);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($proc);
 testCheck($code===0,'Importador CLI acepta CSV valido: '.$error);
 testCheck((rows('SELECT nombre FROM estudiantes WHERE codigo=?',['IMP-'.$tag])[0]['nombre']??null)==='Nombre, con coma','Carga CSV respeta comillas y separadores en nombre');
 echo "$testCount comprobaciones del importador CSV correctas.\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
