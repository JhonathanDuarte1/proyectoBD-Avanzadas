<?php
require __DIR__.'/../scripts/admin.php';require __DIR__.'/helpers.php';
$testCount=0;
function race(string $env,array $payloads): array {
 $folder=__DIR__.'/../tmp/race_'.bin2hex(random_bytes(5));mkdir($folder);$barrier=$folder.'/go';$workers=[];
 foreach($payloads as $i=>$payload){
  $file=$folder.'/payload'.$i.'.json';file_put_contents($file,json_encode($payload,JSON_THROW_ON_ERROR));
  $pipes=[];$process=proc_open([PHP_BINARY,__DIR__.'/concurrent_worker.php',realpath($env),$file,$barrier,$folder.'/ready'.$i],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,null,['bypass_shell'=>true]);fclose($pipes[0]);$workers[]=[$process,$pipes];
 }
 $until=microtime(true)+10;
 while(count(glob($folder.'/ready*'))<count($workers)){if(microtime(true)>$until)throw new RuntimeException('Los procesos no llegaron a la barrera.');usleep(20000);}
 file_put_contents($barrier,'go');$results=[];
 foreach($workers as [$process,$pipes]){$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($process)!==0)throw new RuntimeException($error);$results[]=json_decode($out,true,512,JSON_THROW_ON_ERROR);}
 return $results;
}
try{
 if(!preg_match('/_(auditoria|test|fresh|restore)_/',rows('SELECT DATABASE() n')[0]['n']))throw new RuntimeException('Requiere base de pruebas.');
 $env=getenv('WEB_ENV_FILE')?:__DIR__.'/../tmp/auditoria_20261119/web.env';$web=testConnection($env);
 $save=fn($entity,$id,$data)=>testCall($web,'sp_guardar',[2,$entity,$id,json_encode($data)]);
 $tag=bin2hex(random_bytes(4));$project=['codigo'=>'RACE-'.$tag,'nombre'=>'Concurrencia '.$tag,'presupuesto'=>10,'inicio'=>'2026-01-01','fin'=>'2026-12-31','grupo_id'=>1,'dedicacion_inicial'=>10];
 $id=$save('proyectos',0,$project)[0]['id'];
 $part=['proyecto_id'=>$id,'profesor_id'=>3,'incorporacion'=>'2026-03-01','retiro'=>null,'dedicacion'=>10,'responsable'=>0];
 $payload=[2,'participaciones',0,json_encode($part)];$results=race($env,[$payload,$payload]);
 testCheck(count(array_filter($results,fn($r)=>$r['ok']))===1,'Carrera de participaciones: solo una superpuesta confirma');
 testCheck((int)rows('SELECT COUNT(*) n FROM participaciones WHERE proyecto_id=? AND profesor_id=3',[$id])[0]['n']===1,'No quedan periodos superpuestos tras la carrera');
 foreach($results as $r)if(!$r['ok'])testCheck(in_array($r['code'],[1644,1213],true),'La otra transaccion se rechaza por regla o interbloqueo recuperable');
 testCall($web,'sp_eliminar',[2,'proyectos',$id]);
 $project['codigo'].='B';$project['nombre'].=' B';$id=$save('proyectos',0,$project)[0]['id'];
 $second=['proyecto_id'=>$id,'profesor_id'=>3,'incorporacion'=>'2026-01-01','retiro'=>null,'dedicacion'=>10,'responsable'=>1];$secondId=$save('participaciones',0,$second)[0]['id'];
 $firstId=rows('SELECT id FROM participaciones WHERE proyecto_id=? AND profesor_id=2',[$id])[0]['id'];
 $first=$second;$first['profesor_id']=2;$first['responsable']=0;$second['responsable']=0;
 $results=race($env,[[2,'participaciones',$firstId,json_encode($first)],[2,'participaciones',$secondId,json_encode($second)]]);
 testCheck(count(array_filter($results,fn($r)=>$r['ok']))===1,'Carrera al retirar responsables: solo una modificacion confirma');
 testCheck((int)rows('SELECT COUNT(*) n FROM participaciones WHERE proyecto_id=? AND responsable=1',[$id])[0]['n']===1,'Permanece al menos un responsable bajo concurrencia');
 testCall($web,'sp_eliminar',[2,'proyectos',$id]);
 $project['codigo'].='C';$project['nombre'].=' C';$id=$save('proyectos',0,$project)[0]['id'];
 $shrunk=$project;$shrunk['fin']='2026-02-01';$part['proyecto_id']=$id;$part['incorporacion']='2026-06-01';
 $results=race($env,[[2,'proyectos',$id,json_encode($shrunk)],[2,'participaciones',0,json_encode($part)]]);
 testCheck(count(array_filter($results,fn($r)=>$r['ok']))===1,'Carrera de fechas y asignacion: una operacion incompatible se rechaza');
 testCheck(!rows('SELECT a.id FROM participaciones a JOIN proyectos p ON p.id=a.proyecto_id WHERE p.id=? AND (a.incorporacion<p.inicio OR a.incorporacion>p.fin OR a.retiro>p.fin)',[$id]),'Todas las participaciones quedan dentro del proyecto');
 testCall($web,'sp_eliminar',[2,'proyectos',$id]);
 echo "$testCount comprobaciones de concurrencia correctas.\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
