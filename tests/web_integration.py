"""Pruebas de integración HTTP sobre la instalación de demostración local."""
import http.cookiejar
import os
import re
import urllib.parse
import urllib.request

BASE = os.environ.get('APP_BASE_URL', 'http://127.0.0.1:8080')
checks = 0

def client():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

def request(browser, path='/', data=None):
    encoded = urllib.parse.urlencode(data).encode() if data is not None else None
    with browser.open(BASE + path, encoded) as response:
        assert response.status == 200
        return response.read().decode('utf-8')

def token(html):
    return re.search(r'name="csrf" value="([a-f0-9]+)"', html)[1]

def check(condition, label):
    global checks
    assert condition, label
    checks += 1
    print('OK', label)

def login(email):
    browser = client()
    html = request(browser)
    html = request(browser, data={'action': 'login', 'csrf': token(html), 'correo': email, 'password': 'Universidad2026!'})
    check('Cerrar sesión' in html, 'Inicio de sesión: ' + email)
    return browser, html

anonymous = client()
html = request(anonymous)
check('Bienvenido a Investiga U' in html, 'Acceso anónimo muestra ingreso')
html = request(anonymous, data={'action': 'login', 'csrf': 'incorrecto'})
check('sesión del formulario venció' in html, 'Rechaza CSRF inválido')
dean, html = login('decano@example.test')
for page in ['inicio', 'facultades', 'grupos', 'profesores', 'titulos', 'proyectos', 'participaciones', 'estudiantes', 'estudiante_proyecto', 'reportes', 'usuarios', 'cuenta']:
    html = request(dean, '/?page=' + page)
    check('Fatal error' not in html and 'Warning:' not in html, 'Renderiza módulo ' + page)
html = request(dean, '/?page=reportes&desde=2026-01-01&hasta=2026-12-31')
check('45.000.000 COP' in html, 'Consulta de presupuesto correcta')
html = request(dean, '/?page=proyectos', {'action': 'save', 'csrf': token(html)})
check('No tienes permiso' in html, 'Decano no crea proyectos')
teacher, html = login('docente@example.test')
html = request(teacher, '/?page=usuarios', {'action': 'usuario', 'csrf': token(html)})
check('Acceso denegado' in html, 'Docente no crea cuentas')
html = request(teacher, '/?page=proyectos')
html = request(teacher, '/?page=proyectos', {'action': 'save', 'csrf': token(html), 'id': 3, 'codigo': 'INTRUSION'})
check('fuera de tu ámbito' in html, 'Rechaza edición en otra facultad')
html = request(teacher, '/?page=proyectos', {
    'action': 'save', 'csrf': token(html), 'id': 0, 'codigo': 'TEST-HTTP',
    'nombre': 'Proyecto temporal de integración', 'presupuesto': '123000',
    'inicio': '2026-01-01', 'fin': '2026-12-31', 'grupo_id': '1', 'dedicacion_inicial': '20'})
check('Cambios guardados' in html and 'TEST-HTTP' in html, 'Crea proyecto con responsable inicial')
match = re.search(r'<tr>(?:(?!</tr>).)*TEST-HTTP(?:(?!</tr>).)*name="id" value="(\d+)"', html, re.S)
project_id = match[1]
html = request(teacher, '/?page=proyectos', {'action': 'save', 'csrf': token(html), 'id': project_id,
    'codigo': 'TEST-HTTP', 'nombre': 'Proyecto temporal modificado', 'presupuesto': '124000',
    'inicio': '2026-01-01', 'fin': '2026-12-31', 'grupo_id': '1'})
check('Proyecto temporal modificado' in html, 'Actualiza proyecto')
enroll = '/?page=estudiante_proyecto'
html = request(teacher, enroll)
html = request(teacher, enroll, {'action': 'save', 'csrf': token(html), 'id': 0,
    'estudiante_id': 1, 'proyecto_id': project_id})
check('Cambios guardados' in html and 'TEST-HTTP' in html, 'Vincula estudiante existente a otro proyecto')
link_match = re.search(r'<tr>(?:(?!</tr>).)*TEST-HTTP(?:(?!</tr>).)*name="id" value="(\d+)"', html, re.S)
link_id = link_match[1]
html = request(teacher, enroll, {'action': 'save', 'csrf': token(html), 'id': 0,
    'estudiante_id': 1, 'proyecto_id': project_id})
check('No se pudo guardar' in html, 'Rechaza vínculo duplicado desde la aplicación')
html = request(teacher, enroll, {'action': 'save', 'csrf': token(html), 'id': link_id,
    'estudiante_id': 1, 'proyecto_id': 3})
check('Selección no permitida' in html, 'No traslada vínculo a proyecto de otra facultad')
html = request(teacher, enroll, {'action': 'delete', 'csrf': token(html), 'id': link_id})
check('Cambios guardados' in html and 'TEST-HTTP' not in html, 'Retira solo el vínculo seleccionado')
html = request(teacher, '/?page=estudiantes')
check('20260001' in html, 'Estudiante se conserva tras retirar un vínculo')
html = request(teacher, enroll)
html = request(teacher, enroll, {'action': 'save', 'csrf': token(html), 'id': 0,
    'estudiante_id': 1, 'proyecto_id': project_id})
check('Cambios guardados' in html, 'Vuelve a vincular estudiante para probar eliminación del proyecto')
html = request(teacher, '/?page=proyectos', {'action': 'delete', 'csrf': token(html), 'id': project_id})
check('Cambios guardados' in html and 'TEST-HTTP' not in html, 'Elimina proyecto temporal y participaciones')
html = request(teacher, '/?page=estudiantes')
check('20260001' in html, 'Eliminar proyecto no borra identidad del estudiante')
html = request(teacher, enroll)
check('20260001' in html and 'P-001' in html and 'TEST-HTTP' not in html, 'Eliminar proyecto conserva los vínculos del estudiante con otros proyectos')
html = request(teacher, data={'action': 'logout', 'csrf': token(html)})
check('Bienvenido a Investiga U' in html, 'Cierra sesión')
print(f'{checks} verificaciones HTTP correctas.')
