<?php
require __DIR__.'/helpers.php';
try{
 [$script,$env,$payloadFile,$barrier,$ready]=$argv;
 $pdo=testConnection($env);$payload=json_decode(file_get_contents($payloadFile),true,512,JSON_THROW_ON_ERROR);
 file_put_contents($ready,'ready');$until=microtime(true)+10;
 while(!is_file($barrier)){if(microtime(true)>$until)throw new RuntimeException('Barrera vencida');usleep(10000);}
 try{testCall($pdo,'sp_guardar',$payload);echo json_encode(['ok'=>true]);}
 catch(PDOException $e){echo json_encode(['ok'=>false,'code'=>$e->errorInfo[1]??null,'message'=>$e->errorInfo[2]??'']);}
}catch(Throwable $e){fwrite(STDERR,$e->getMessage());exit(1);}
