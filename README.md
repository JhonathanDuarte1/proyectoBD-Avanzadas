# Investiga U

Proyecto académico de Bases de Datos Avanzadas: gestión de investigación universitaria con PHP y MySQL.

## Trabajo en equipo

El repositorio comparte código, scripts SQL, pruebas y documentación. La aplicación y los datos compartidos se alojan por separado: subir archivos a GitHub no ejecuta PHP ni crea MySQL.

Ver [inicio para el equipo](docs/inicio-equipo.md), [reglas de negocio](docs/auditoria-reglas.md) y [temas de la materia](docs/temas-materia.md).

## Requisitos

- PHP con PDO MySQL y mbstring.
- MySQL compatible con CHECK, CTE, funciones de ventana, JSON_TABLE, procedimientos y triggers.
- La versión comprobada localmente usa PHP 8.0.30 y MySQL 9.6.0. Otros entornos necesitan validación antes de publicar. MariaDB 10.4 no ejecuta todos los objetos de esta versión.

## Instalación local en una base nueva

1. Crear una base MySQL vacía con utf8mb4/utf8mb4_unicode_ci.
2. Copiar `.env.admin.example` a `.env.admin` y configurar las credenciales de instalación para esa base.
3. Establecer DEMO_PASSWORD con una contraseña nueva de pruebas y ejecutar:

```powershell
$env:DEMO_PASSWORD='REEMPLAZAR_POR_UNA_CLAVE_PROPIA'
php scripts/install.php
Remove-Item Env:DEMO_PASSWORD
php scripts/provision_security.php
php -S 127.0.0.1:8080 -t public
```

El instalador no modifica bases con tablas existentes. El aprovisionamiento local requiere permisos de crear usuarios y roles; un alojamiento administrado puede requerir preparar cuentas desde su panel. El servidor integrado de PHP se usa para desarrollo.

Cuentas ficticias creadas por la instalación: `decano@example.test`, `docente@example.test` y `decano.ingenieria@example.test`. Su contraseña es la que se indicó con DEMO_PASSWORD; no se debe publicar una contraseña de la instalación compartida.

## Configuración privada

- `.env`: cuenta del backend.
- `.env.admin`: credenciales de mantenimiento usadas solo por CLI.
- `.env.report`: cuenta SQL de consulta.
- `.env.phpmyadmin`: acceso administrativo local cuando se configure expresamente.

Solo se versionan los archivos `.example`. Credenciales, respaldos, datos MySQL, claves y archivos de operación de cada computador quedan fuera del repositorio.

## Publicación web

El servidor debe servir exclusivamente `public/`, con HTTPS y una base persistente compatible. El Dockerfile prepara PHP/Apache y copia únicamente app/public; no contiene datos ni credenciales y no instala la BD. La imagen aún debe construirse y probarse en el proveedor elegido.

La cuenta MySQL del sitio se mantiene separada de mantenimiento. No se configura una base dentro del disco efímero de un servicio web gratuito. Consultar [los requisitos de alojamiento](docs/inicio-equipo.md#condiciones-que-debe-cumplir-el-alojamiento).

## Código y documentación

- `app/`: conexión PDO, autorización y acciones.
- `public/`: interfaz y recursos web.
- `database/01_schema.sql`: estructura y vistas base.
- `database/02_seed.sql`: datos ficticios.
- `database/03_business.sql`: procedimientos, vistas y triggers de negocio.
- `database/04_queries.sql`: consultas para la sustentación.
- `docs/modelos.md`, `docs/diccionario.md`, `docs/manual.md` y `docs/entrega.md`: documentación académica.
- `docs/validacion.md`: pruebas ejecutadas y límites pendientes.

## Verificación

```powershell
python scripts/check_repository_secrets.py
php tests/database.php
python tests/web_integration.py
```

La prueba de BD usa mantenimiento y revierte sus cambios. El recorrido HTTP usa una instalación de demostración y crea/elimina un proyecto temporal. Las otras suites crean fixtures: se ejecutan solo sobre las copias de pruebas descritas en validacion.md.

La revisión visual y el formato académico definitivo siguen pendientes. Las pruebas automatizadas no garantizan ausencia absoluta de defectos.
