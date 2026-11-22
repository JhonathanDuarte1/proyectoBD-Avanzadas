"""Flujos adicionales sobre la copia de auditoría; crea fixtures que no se usan en la base activa."""
import http.cookiejar
import os
import re
import secrets
import urllib.parse
import urllib.request

BASE = os.environ.get('APP_BASE_URL', 'http://127.0.0.1:8081')
if BASE.endswith(':8080'):
    raise RuntimeError('Usa la copia de auditoría, no el servidor activo.')
checks = 0

def client():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

def request(browser, path='/', data=None):
    with browser.open(BASE+path, None if data is None else urllib.parse.urlencode(data).encode()) as response:
        body = response.read().decode('utf-8')
        assert response.status == 200 and 'Fatal error' not in body and 'Warning:' not in body, body[:1000]
        return body

def token(html):
    return re.search(r'name="csrf" value="([a-f0-9]+)"', html)[1]

def check(condition, label):
    global checks
    assert condition, label
    checks += 1
    print('OK', label)

def login(email, password='Universidad2026!'):
    browser = client(); html = request(browser)
    html = request(browser, data={'action':'login','csrf':token(html),'correo':email,'password':password})
    return browser, html

bad, html = login("' OR 1=1 --", 'incorrecta')
check('Correo o contraseña incorrectos' in html and 'Cerrar sesión' not in html, 'Ingreso no admite inyección SQL')
dean, html = login('decano@example.test')
html = request(dean, '/?page=reportes&desde=2026-02-30&hasta=2026-12-31')
check('Revisa el período ingresado' in html, 'Fecha imposible en reporte no causa error SQL')
html = request(dean, '/?page=reportes&desde=2026-12-31&hasta=2026-01-01')
check('Revisa el período ingresado' in html, 'Reporte rechaza intervalo invertido')
html = request(dean, '/?page=reportes&facultad=2&estado=ACTIVO')
check('P-003' in html, 'Consulta proyectos activos de una facultad distinta de la propia')
html = request(dean, '/?page=reportes&profesor=3')
check('P-003' in html, 'Consulta proyectos de un profesor de otra facultad')
html = request(dean, '/?page[]=reportes&desde[]=2026-01-01')
check('Fatal error' not in html, 'Parámetros GET con tipo inválido no rompen la página')
html = request(dean, '/?page=profesores', {'action':'save','csrf[]':'invalido'})
check('formato válido' in html, 'Parámetros POST con tipo inválido se rechazan sin advertencias')
tag = secrets.token_hex(4)
prof = {'action':'save','id':0,'cedula':'HT'+tag,'nombre':'Profesor HTTP '+tag,'experiencia':'2','grupo_id':'1'}
html = request(dean, '/?page=profesores', dict(prof, csrf=token(html)))
check('Completa: Primer título' in html, 'Formulario exige título inicial')
prof['titulo_inicial'] = 'Maestría HTTP'
html = request(dean, '/?page=profesores', dict(prof, csrf=token(html)))
check('Cambios guardados' in html and 'HT'+tag in html, 'Registra profesor y título desde la interfaz')
user_page = request(dean, '/?page=usuarios')
pid = re.search(r'<option value="(\d+)">Profesor HTTP '+tag, user_page)[1]
mail = 'http'+tag+'@example.test'
html = request(dean, '/?page=usuarios', {'action':'usuario','csrf':token(user_page),'profesor_id':pid,'correo':mail,'password':'Universidad2026!'})
check('Cambios guardados' in html, 'Decano registra cuenta del profesor')
teacher, html = login(mail)
check('Cerrar sesión' in html and 'Profesor HTTP '+tag in html, 'Profesor nuevo ingresa con su cuenta')
project = {'action':'save','id':0,'codigo':'HT-'+tag,'nombre':'Proyecto HTTP '+tag,'presupuesto':'1','inicio':'2026-01-01','fin':'2026-12-31','grupo_id':1}
html = request(teacher, '/?page=proyectos', dict(project,csrf=token(html)))
check('Completa: Tu dedicación' in html, 'Exige dedicación inicial explícita')
project['dedicacion_inicial'] = '10'
invalid = dict(project, inicio='2026-02-30', csrf=token(html))
html = request(teacher, '/?page=proyectos', invalid)
check('Fecha inválida' in html, 'Formulario rechaza fecha inexistente')
html = request(teacher, '/?page=proyectos', dict(project,csrf=token(html),nombre='x'*181))
check('máximo 180 caracteres' in html, 'Rechaza longitud excesiva antes de enviar a MySQL')
html = request(teacher, '/?page=proyectos', dict(project,csrf=token(html),presupuesto='-1'))
check('Valor numérico inválido' in html, 'Rechaza presupuesto negativo desde el formulario')
html = request(teacher, '/?page=proyectos', dict(project,csrf=token(html)))
check('Cambios guardados' in html, 'Docente nuevo crea proyecto propio')
project_id = re.search(r'<tr>(?:(?!</tr>).)*HT-'+tag+r'(?:(?!</tr>).)*name="id" value="(\d+)"', html, re.S)[1]
html = request(teacher, '/?page=estudiantes')
html = request(teacher, '/?page=estudiantes', {'action':'save','id':0,'csrf':token(html),'codigo':'HS-'+tag,'nombre':'<script>alert(1)</script>','correo':'hs'+tag+'@example.test'})
check('&lt;script&gt;alert(1)&lt;/script&gt;' in html and '<script>alert(1)</script>' not in html, 'La salida escapa contenido HTML de los datos')
html = request(teacher, '/?page=proyectos')
html = request(teacher, '/?page=proyectos', {'action':'delete','id':project_id,'csrf':token(html)})
check('Cambios guardados' in html, 'Elimina proyecto de aceptación sin afectar otras identidades')
html = request(teacher, '/?page=cuenta')
html = request(teacher, '/?page=cuenta', {'action':'cuenta','csrf':token(html),'cedula':'HX'+tag,'nombre':'Profesor HTTP '+tag,'correo':mail,'actual':'Universidad2026!','nueva':'Universidad2026!Nueva'})
check('Cambios guardados' in html and 'HX'+tag in html, 'Docente modifica cédula y contraseña propias')
old_client, html = login(mail)
check('Correo o contraseña incorrectos' in html, 'La contraseña anterior deja de funcionar')
teacher, html = login(mail, 'Universidad2026!Nueva')
check('Cerrar sesión' in html, 'La contraseña nueva permite ingresar')
html = request(teacher, '/?page=cuenta')
html = request(teacher, '/?page=cuenta', {'action':'cuenta','csrf':token(html),'cedula':'HX'+tag,'nombre':'No debe persistir','correo':'decano@example.test','actual':'Universidad2026!Nueva','nueva':''})
check('No se pudo guardar' in html and 'value="No debe persistir"' not in html, 'Correo duplicado revierte también el cambio de nombre')
print(f'{checks} comprobaciones HTTP adicionales correctas.')
