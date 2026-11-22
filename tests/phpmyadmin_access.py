"""Verifica el ingreso local al perfil autorizado de phpMyAdmin sin imprimir secretos."""
from pathlib import Path
import http.cookiejar
from html.parser import HTMLParser
import urllib.parse
import urllib.request

class LoginParser(HTMLParser):
    def __init__(self):
        super().__init__(); self.hidden = {}
    def handle_starttag(self, tag, attributes):
        attrs = dict(attributes)
        if tag == 'input' and attrs.get('type') == 'hidden' and attrs.get('name'):
            self.hidden[attrs['name']] = attrs.get('value', '')

root = Path(__file__).resolve().parent.parent
config = dict(line.split('=', 1) for line in (root/'.env.phpmyadmin').read_text().splitlines() if '=' in line)
browser = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
url = 'http://127.0.0.1/phpmyadmin/index.php?route=/&server=2'
with browser.open(url, timeout=10) as response:
    body = response.read().decode('utf-8')
assert 'Investiga U - MySQL del proyecto' in body, 'No aparece el perfil del proyecto'
assert 'pma_username' in body, 'No aparece el ingreso por cookie'
parser = LoginParser(); parser.feed(body)
fields = dict(parser.hidden, pma_username=config['DB_USER'], pma_password=config['DB_PASSWORD'], server='2')
with browser.open(url, urllib.parse.urlencode(fields).encode(), timeout=10) as response:
    body = response.read().decode('utf-8')
assert 'pma_username' not in body, 'No se completó el inicio de sesión'
assert config['DB_NAME'] in body, 'La base del proyecto no está visible'
print('OK perfil del proyecto visible y autenticación local completada')
structure = 'http://127.0.0.1/phpmyadmin/index.php?'+urllib.parse.urlencode({'route':'/database/structure','server':2,'db':config['DB_NAME']})
with browser.open(structure, timeout=10) as response:
    body = response.read().decode('utf-8')
for table in ['proyectos','profesores','estudiantes','participaciones','estudiante_proyecto']:
    assert table in body, 'No se muestra '+table
print('OK estructura y tablas del proyecto visibles en phpMyAdmin')
