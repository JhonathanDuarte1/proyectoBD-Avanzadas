<?php
// Credenciales de mantenimiento separadas de la cuenta utilizada por el sitio.
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
foreach(is_file(__DIR__.'/../.env.admin')?file(__DIR__.'/../.env.admin',FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES):[] as $line){
 if(strpos(trim($line),'#')===0||strpos($line,'=')===false)continue;
 [$key,$value]=explode('=',$line,2);if(getenv(trim($key))===false)putenv(trim($key).'='.trim($value));
}
require_once __DIR__.'/../app/bootstrap.php';
