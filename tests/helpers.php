<?php
function testConnection(string $file): PDO {
 $config=parse_ini_file($file,false,INI_SCANNER_RAW);
 $pdo=new PDO('mysql:host='.$config['DB_HOST'].';port='.$config['DB_PORT'].';dbname='.$config['DB_NAME'].';charset=utf8mb4',$config['DB_USER'],$config['DB_PASSWORD'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
 $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");$pdo->exec("SET time_zone='-05:00'");return $pdo;
}
function testCall(PDO $pdo,string $routine,array $args=[]): array {
 $q=$pdo->prepare('CALL '.$routine.'('.implode(',',array_fill(0,count($args),'?')).')');$q->execute($args);$out=$q->columnCount()?$q->fetchAll():[];while($q->nextRowset()){}$q->closeCursor();return $out;
}
function testCheck(bool $ok,string $label): void {global $testCount;if(!$ok)throw new RuntimeException($label);$testCount++;echo "OK $label\n";}
function testReject(callable $fn,string $label,array $codes=[1644,3819,1062,1452,1264,1292,1406,1048]): void {
 try{$fn();}catch(PDOException $e){if(!in_array((int)($e->errorInfo[1]??0),$codes,true))throw new RuntimeException($label.' produjo un error inesperado: '.$e->getMessage());testCheck(true,$label);return;}throw new RuntimeException('Se acepto una operacion invalida: '.$label);
}
