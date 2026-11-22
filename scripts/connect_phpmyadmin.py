"""Agrega un perfil a phpMyAdmin; preserva perfiles existentes y no recarga Apache."""
from pathlib import Path
from datetime import datetime
import subprocess
import shutil

root = Path(__file__).resolve().parent.parent
config = Path('C:/xampp/phpMyAdmin/config.inc.php')
original = config.read_bytes()
text = original.decode('utf-8-sig')
marker = '// BEGIN INVESTIGA U PHPMYADMIN'
if marker in text:
    print('El perfil del proyecto ya está configurado. Sin cambios.')
    raise SystemExit(0)
credential_file = (root / '.env.phpmyadmin').as_posix()
if not Path(credential_file).is_file():
    raise RuntimeError('Ejecuta primero scripts/setup_phpmyadmin.php.')
block = '''
// BEGIN INVESTIGA U PHPMYADMIN
// Perfil disponible solo a peticiones locales; no se aceptan cabeceras de proxy como origen.
if (in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
$iuConfig = parse_ini_file('__CREDENTIALS__', false, INI_SCANNER_RAW);
$iuServer = max(array_merge([0], array_keys($cfg['Servers']))) + 1;
$cfg['Servers'][$iuServer] = [
    'verbose' => 'Investiga U - MySQL del proyecto',
    'host' => $iuConfig['DB_HOST'],
    'port' => $iuConfig['DB_PORT'],
    'connect_type' => 'tcp',
    'auth_type' => 'cookie',
    'AllowNoPassword' => false,
    'only_db' => [$iuConfig['DB_NAME']],
    'AllowDeny' => [
        'order' => 'explicit',
        'rules' => [
            'allow '.$iuConfig['DB_USER'].' from 127.0.0.1',
            'allow '.$iuConfig['DB_USER'].' from ::1',
        ],
    ],
];
unset($iuConfig, $iuServer);
}
// END INVESTIGA U PHPMYADMIN
'''.replace('__CREDENTIALS__', credential_file.replace("'", "\\'"))
position = text.rfind('?>') if text.rstrip().endswith('?>') else len(text)
candidate = (text[:position] + block + text[position:]).encode('utf-8')
folder = root / 'tmp/phpmyadmin'
folder.mkdir(parents=True, exist_ok=True)
testfile = folder / 'candidate.php'
testfile.write_bytes(candidate)
subprocess.run(['C:/xampp/php/php.exe', '-l', str(testfile)], check=True)
backup = folder / ('config-before-' + datetime.now().strftime('%Y%m%d-%H%M%S-%f') + '.php')
shutil.copyfile(config, backup)
if config.read_bytes() != original:
    raise RuntimeError('La configuración cambió durante la revisión. No se escribió.')
config.write_bytes(candidate)
print('Perfil agregado; conexiones anteriores conservadas. Respaldo:', backup)
