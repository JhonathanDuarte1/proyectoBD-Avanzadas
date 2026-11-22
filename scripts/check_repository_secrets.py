"""Comprueba archivos candidatos a Git sin mostrar contraseñas en la salida."""
from pathlib import Path
import subprocess
import sys
import re

root = Path(__file__).resolve().parent.parent
result = subprocess.run(['git','ls-files','--cached','--others','--exclude-standard','-z'],cwd=root,capture_output=True,check=True)
paths = sorted(set(p for p in result.stdout.decode('utf-8').split('\0') if p))
cached = set(p for p in subprocess.run(['git','ls-files','--cached','-z'],cwd=root,capture_output=True,check=True).stdout.decode('utf-8').split('\0') if p)
secrets = []
for file in root.glob('.env*'):
    if file.name.endswith('.example'):
        continue
    for line in file.read_text(encoding='utf-8-sig').splitlines():
        if '=' in line:
            key,value = line.split('=',1)
            if any(part in key.upper() for part in ['PASSWORD','SECRET','TOKEN','PRIVATE_KEY']) and len(value.strip())>=8:
                secrets.append(value.strip())
blocked = []
for name in paths:
    path = root/name
    if name.startswith(('.env', 'backups/', 'tmp/')) and not name.endswith('.example'):
        blocked.append((name,'archivo privado'))
        continue
    if path.is_file():
        content = subprocess.run(['git','show',':'+name],cwd=root,capture_output=True,check=True).stdout if name in cached else path.read_bytes()
        if any(secret.encode() in content for secret in secrets):
            blocked.append((name,'contiene una credencial de configuración local'))
        if re.search(rb'-----BEGIN (?:OPENSSH |RSA |EC |DSA |ENCRYPTED )?PRIVATE KEY-----', content):
            blocked.append((name,'contiene una clave privada'))
if blocked:
    for name,reason in blocked:
        print('BLOQUEADO:',name,'-',reason)
    sys.exit(1)
print(f'OK: {len(paths)} archivos candidatos revisados; sin coincidencias con credenciales locales ni claves privadas detectadas.')
print('La comprobación no sustituye revisar el listado y el diff antes de publicar.')
