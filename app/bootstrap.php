<?php
declare(strict_types=1);
date_default_timezone_set('America/Bogota');
$envFile=getenv('APP_ENV_FILE')?:__DIR__.'/../.env';
foreach (is_file($envFile) ? file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [] as $line) {
    if (strpos(trim($line),'#')===0 || strpos($line,'=')===false) continue;
    [$key,$value]=explode('=',$line,2); if (getenv(trim($key))===false) putenv(trim($key).'='.trim($value));
}
function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $pdo=new PDO('mysql:host='.(getenv('DB_HOST')?:'127.0.0.1').';port='.(getenv('DB_PORT')?:'3306').';dbname='.(getenv('DB_NAME')?:'investiga_u').';charset=utf8mb4',getenv('DB_USER')?:'investiga_app',getenv('DB_PASSWORD')?:'', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
        $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdo->exec("SET time_zone = '-05:00'");
    } return $pdo;
}
function rows(string $sql,array $args=[]): array { $q=db()->prepare($sql);$q->execute($args);return $q->fetchAll(); }
function run(string $sql,array $args=[]): void { $q=db()->prepare($sql);$q->execute($args); }
function h($v): string { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function base_path(): string {
 $path=str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME']??'/index.php'));
 return $path==='/'||$path==='.'?'':rtrim($path,'/');
}
function app_url(string $path='/'): string {
 if($path===''||$path[0]!=='/'||substr($path,0,2)==='//')throw new LogicException('Se requiere una ruta local absoluta.');
 return base_path().$path;
}
function redirect(string $path): void { header('Location: '.app_url($path));exit; }
function user(): ?array {
 if(!isset($_SESSION['uid']))return null;
 $result=rows("SELECT u.id,u.profesor_id,u.correo,u.rol,p.cedula,p.nombre,p.grupo_id,CASE WHEN u.rol='DECANO' THEN (SELECT f.id FROM facultades f WHERE f.decano_id=p.id ORDER BY (f.id=g.facultad_id) DESC,f.id LIMIT 1) ELSE g.facultad_id END facultad_id FROM usuarios u JOIN profesores p ON p.id=u.profesor_id JOIN grupos g ON g.id=p.grupo_id WHERE u.id=?",[$_SESSION['uid']])[0]??null;
 return $result&&$result['facultad_id']!==null?$result:null;
}
function csrf(): string { return '<input type="hidden" name="csrf" value="'.h($_SESSION['csrf']).'">'; }
function assert_csrf(): void { if(!hash_equals($_SESSION['csrf'],(string)($_POST['csrf']??''))) throw new RuntimeException('La sesión del formulario venció. Intenta de nuevo.'); }
if (PHP_SAPI!=='cli') {
    session_name('INVESTIGAU_SESSION');
    session_set_cookie_params(['path'=>app_url('/'),'httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off']);
    session_start(); $_SESSION['csrf']=$_SESSION['csrf']??bin2hex(random_bytes(32));
    header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');
    header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'");
}
