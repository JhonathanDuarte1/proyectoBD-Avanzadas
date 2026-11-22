<?php
declare(strict_types=1);
require __DIR__.'/admin.php';
require __DIR__.'/backup_lib.php';
$args=getopt('',['output:']);
$path=$args['output']??(__DIR__.'/../backups/'.date('Ymd_His').'.sql');
try{createBackup(db(),$path);echo "Respaldo y manifiesto creados: $path\n";}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
