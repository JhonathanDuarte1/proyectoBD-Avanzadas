# Trabajo en equipo y publicación

## Dos servicios diferentes

GitHub conserva el código y las versiones. La aplicación se ejecuta en un alojamiento PHP con una base MySQL. Cada integrante debe tener su propia cuenta GitHub y, para administrar la base compartida, una cuenta SQL individual con permisos sobre esa base solamente.

## Repositorio privado

1. Crear un repositorio privado vacío y agregar a los otros tres integrantes como colaboradores.
2. Subir únicamente los archivos aprobados del proyecto. `.env`, `.env.admin`, `.env.phpmyadmin`, `.env.report`, respaldos, claves y archivos de operación locales se excluyen mediante `.gitignore`.
3. Cada integrante clona el repositorio y crea una rama para su trabajo. Comparte sus cambios mediante commits y una solicitud de incorporación.
4. Los cambios de estructura se guardan como scripts SQL versionados. Git no sincroniza los registros existentes de la base.

Las cuentas de ejemplo de los documentos son datos de demostración. En la instalación compartida se establece una contraseña nueva con DEMO_PASSWORD; no se utiliza una contraseña publicada en los ejemplos.

## Instalación independiente del computador de la empresa

La aplicación requiere PHP con PDO MySQL y mbstring. La versión completa se ha comprobado con PHP 8.0.30 y MySQL 9.6.0. Un despliegue nuevo debe repetir las pruebas en las versiones elegidas. XAMPP con MariaDB 10.4 no sustituye automáticamente a ese motor: el proyecto utiliza JSON_TABLE, CTE, ventanas y procedimientos.

1. Crear una base vacía MySQL con utf8mb4/utf8mb4_unicode_ci.
2. Crear `.env.admin` desde su ejemplo, indicando las credenciales de instalación de esa base.
3. Ejecutar `php scripts/install.php` con DEMO_PASSWORD configurado.
4. Provisionar una cuenta de ejecución con SELECT y EXECUTE sobre los procedimientos públicos, separada de la cuenta de mantenimiento. El script local provision_security.php presupone cuentas MySQL @localhost: un alojamiento con servicios separados necesita adaptar ese alcance a su red privada.
5. Configurar el servidor para servir exclusivamente `public/`.
6. Ejecutar las comprobaciones SQL y HTTP antes de compartir el enlace.

El Dockerfile prepara un servidor PHP/Apache con public como raíz y copia solo app/public. La base se suministra por separado mediante variables DB_HOST, DB_PORT, DB_NAME, DB_USER y DB_PASSWORD. No instala la base, no carga datos y no contiene archivos .env. Docker no está instalado en el equipo de preparación: la imagen todavía debe construirse y verificarse en el alojamiento seleccionado.

## Condiciones que debe cumplir el alojamiento

- PHP y MySQL con las funciones utilizadas por el proyecto.
- Permisos CREATE ROUTINE y TRIGGER para instalar la estructura programable.
- Posibilidad de ejecutar procedimientos con SQL SECURITY DEFINER y de dar EXECUTE a la cuenta de la app.
- Una cuenta de mantenimiento autorizada para preparar los perfiles SQL. Un plan compartido que impida CREATE USER/ROLE requiere gestionar esas cuentas desde el panel del proveedor y adaptar el aprovisionamiento.
- Conexión privada o TLS verificado entre aplicación y base; HTTPS para el navegador.
- Datos persistentes, respaldo y un procedimiento de restauración comprobable.

No se ha contratado ni desplegado ningún servicio. La elección depende del presupuesto y de los permisos reales del proveedor; publicar un repositorio por sí solo no crea un sitio PHP ni una base MySQL.

## Alternativa para demostraciones con cuota gratuita

GitHub Codespaces puede ejecutar un entorno de desarrollo y reenviar el puerto de la app a una URL HTTPS. Esto es un servicio de ejecución distinto del repositorio y de GitHub Pages. Requiere preparar MySQL y PHP dentro del entorno y verificar la instalación; todavía no se ha creado ese entorno para este proyecto.

Las cuentas personales incluyen una cuota mensual. En GitHub Free son 120 core-horas (60 horas de máquina de 2 núcleos) y 15 GB-mes. El entorno se detiene por inactividad y no sirve como alojamiento permanente 24/7. Un puerto privado solo lo puede abrir su propietario; compartir la app con los demás mediante un puerto público requiere conservar la autenticación de la aplicación. No se debe publicar el puerto de MySQL.

Fuentes: [uso incluido](https://docs.github.com/en/codespaces/troubleshooting/troubleshooting-included-usage), [límites por plan](https://docs.github.com/en/billing/reference/product-usage-included) y [seguridad de puertos](https://docs.github.com/en/codespaces/reference/security-in-github-codespaces).
