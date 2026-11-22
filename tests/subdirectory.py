"""Comprueba URL, recursos, cookie y aislamiento del alias de Apache sin modificar datos."""
import os
import urllib.error
import urllib.request

BASE = os.environ.get('APP_BASE_URL', 'http://127.0.0.1/proyectou').rstrip('/')
with urllib.request.urlopen(BASE, timeout=10) as response:
    assert response.geturl().endswith('/proyectou/'), response.geturl()
    body = response.read().decode('utf-8')
    assert 'href="/proyectou/assets/style.css"' in body
    assert 'src="/proyectou/assets/app.js"' in body
    cookie = response.headers.get('Set-Cookie', '')
    assert 'INVESTIGAU_SESSION=' in cookie and 'path=/proyectou/' in cookie and 'HttpOnly' in cookie, cookie
    print('OK redirección a barra final, recursos con prefijo y cookie aislada')
for asset, mime in [('style.css', 'text/css'), ('app.js', 'javascript')]:
    with urllib.request.urlopen(BASE+'/assets/'+asset, timeout=10) as response:
        assert response.status == 200 and mime in response.headers.get('Content-Type', '')
        print('OK recurso', asset)
for path in ['/.env', '/app/bootstrap.php', '/database/01_schema.sql', '/assets/']:
    try:
        urllib.request.urlopen(BASE+path, timeout=10)
    except urllib.error.HTTPError as error:
        assert error.code in [403, 404], (path, error.code)
        print('OK no se expone', path)
    else:
        raise AssertionError('Se expone una ruta que no corresponde: '+path)
