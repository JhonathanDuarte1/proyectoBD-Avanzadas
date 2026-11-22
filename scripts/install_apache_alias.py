"""Añade solo el alias /proyectou al Apache XAMPP existente. Ejecutar con permiso para recargar Apache2.4."""
from datetime import datetime
from pathlib import Path
import hashlib
import json
import subprocess
import sys
import ctypes
import urllib.request

ROOT = Path(__file__).resolve().parent.parent
CONF = Path('C:/xampp/apache/conf/httpd.conf')
HTTPD = Path('C:/xampp/apache/bin/httpd.exe')
ALIAS = ROOT / 'deploy/apache-alias.conf'
WORK = ROOT / 'tmp/apache-publicacion'

def check(args):
    result = subprocess.run([str(HTTPD), *args], capture_output=True, text=True)
    if result.returncode:
        raise RuntimeError(result.stdout + result.stderr)
    return result

def main():
    WORK.mkdir(parents=True, exist_ok=True)
    original = CONF.read_bytes()
    directive = f'Include "{ALIAS.as_posix()}"'
    changed = directive not in original.decode('utf-8-sig')
    candidate = original + ((b'\r\n' + directive.encode('utf-8') + b'\r\n') if changed else b'')
    testfile = WORK / 'httpd-candidate.conf'
    testfile.write_bytes(candidate)
    check(['-t', '-f', str(testfile)])
    if CONF.read_bytes() != original:
        raise RuntimeError('La configuración cambió durante la revisión; no se escribió.')
    backup = WORK / ('httpd-before-' + datetime.now().strftime('%Y%m%d-%H%M%S-%f') + '.conf')
    backup.write_bytes(original)
    if changed:
        CONF.write_bytes(candidate)
        try:
            check(['-t', '-f', str(CONF)])
        except Exception:
            CONF.write_bytes(original)
            raise
    metadata = {'config':str(CONF), 'backup':str(backup), 'include':directive,
                'before_sha256':hashlib.sha256(original).hexdigest(), 'changed':changed}
    (WORK / 'installation.json').write_text(json.dumps(metadata, indent=2), encoding='utf-8')
    print('Configuración validada; respaldo:', backup)
    if not ctypes.windll.shell32.IsUserAnAdmin():
        print('El alias está guardado. Para activarlo, recarga Apache2.4 desde PowerShell como administrador.', file=sys.stderr)
        return 2
    result = subprocess.run([str(HTTPD), '-k', 'restart', '-n', 'Apache2.4'], capture_output=True, text=True)
    if result.returncode:
        print('El alias quedó configurado, pero no se pudo recargar el servicio.\n' + result.stdout + result.stderr, file=sys.stderr)
        return 2
    try:
        with urllib.request.urlopen('http://127.0.0.1/proyectou/', timeout=10) as response:
            if 'Bienvenido a Investiga U' not in response.read().decode('utf-8'):
                raise RuntimeError('No se identificó la página de la aplicación.')
    except Exception as error:
        print('No se pudo comprobar la activación del alias: '+str(error), file=sys.stderr)
        return 2
    print('Servicio Apache2.4 recargado y ruta /proyectou/ comprobada.')
    return 0

if __name__ == '__main__':
    try:
        sys.exit(main())
    except Exception as error:
        print(str(error), file=sys.stderr)
        sys.exit(1)
