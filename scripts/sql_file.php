<?php
function executeSqlFile(PDO $pdo,string $file): void {
 $delimiter=';';$buffer='';
 foreach(file($file) as $line){
  if(preg_match('/^DELIMITER\s+(\S+)/',trim($line),$m)){$delimiter=$m[1];continue;}
  if(preg_match('/^\s*--/',$line))continue;
  $buffer.=$line;
  if(substr(rtrim($buffer),-strlen($delimiter))===$delimiter){$pdo->exec(substr(rtrim($buffer),0,-strlen($delimiter)));$buffer='';}
 }
 if(trim($buffer)!=='')throw new RuntimeException('Sentencia SQL incompleta en '.$file);
}
