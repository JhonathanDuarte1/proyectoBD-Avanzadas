# Registro de validación

Fecha: 19 de noviembre de 2026, Bogotá. Revisión contra las reglas del primer avance, la corrección docente y los temas de BD Avanzadas.

## Entorno

- Windows, PHP 8.0.30 con PDO MySQL/mbstring y MySQL Community Server 9.6.0.
- Instancia aislada del proyecto: `tmp/mysql-real`, puerto local 33180.
- Base activa: `investiga_u_migrado`; servidor PHP local en 127.0.0.1:8080.
- Copia de pruebas: `investiga_auditoria_20261119`, con cuenta web propia y servidor 8081.
- Instalación nueva comprobada: `investiga_fresh_20261119`.
- Datos de demostración ficticios; las pruebas que crean profesores/cuentas o escenarios de carrera se ejecutan solo en copias identificadas, no sobre la base activa.

La versión completa actual se comprobó en MySQL 9.6.0. Las comprobaciones anteriores de MariaDB corresponden al estado previo a los procedimientos que usan JSON_TABLE y roles SQL; no acreditan compatibilidad de esta versión con MariaDB 10.4.

## Resultados finales

| Grupo de comprobaciones | Resultado | Evidencia ejecutable |
|---|---|---|
| Restricciones, vistas, FK, PK, intervalos y presupuesto | 22/22 | tests/database.php |
| HTTP: módulos, sesiones, permisos, proyectos y estudiantes | 32/32 | tests/web_integration.py |
| HTTP: entradas inválidas, consultas entre facultades, profesor/cuenta nuevos, contraseña, cédula y rollback | 22/22 | tests/web_acceptance.py |
| Reglas de negocio, mínimos obligatorios, operaciones atómicas y privilegios reales del motor | 48/48 | tests/business.php |
| Carreras entre dos procesos/conexiones | 7/7 | tests/concurrency.php |
| Importación CLI de CSV UTF-8 con BOM y nombre con coma entre comillas | 2/2 | tests/csv_import.php |

Total de comprobaciones de estos seis grupos: **133 aprobadas**. La repetición de una misma suite en otra base no se suma de nuevo a ese total.

Las pruebas SQL y de negocio se ejecutaron tanto sobre una instalación actualizada como sobre una instalación nueva durante el desarrollo. Las suites adicionales crean fixtures en copias. El recorrido HTTP base también se volvió a ejecutar con la base activa y su cuenta SQL restringida. Los cambios de la suite SQL base se revierten; los proyectos temporales HTTP se eliminan y su auditoría se conserva.

## Casos importantes cubiertos

- Códigos, correos y títulos duplicados; FK a proyectos inexistentes; nombres vacíos y formatos inválidos.
- Fechas reales y ordenadas, dedicación válida, presupuesto no negativo y reingresos sin solapamiento.
- Impedir retirar o trasladar el último título, teléfono o responsable.
- Profesor con título y proyecto con responsable creados de manera atómica; fallos intermedios revierten la operación.
- Facultad, grupo, profesor, teléfono, título, decano y líder creados en una sola transacción administrativa.
- Profesor responsable como requisito para gestionar estudiantes del proyecto.
- Vigencia exacta de la regla 34, incluso en un proyecto terminado sin retiro registrado.
- Decano verificado por la asignación real de la facultad; el rol anterior por sí solo no conserva permisos.
- Estudiante en varios proyectos y conservación de identidad y otros vínculos al eliminar uno.
- Recuento de profesores distintos pese a reingresos; agregaciones que no duplican presupuesto.
- DML directo y DDL denegados a la cuenta web; credenciales y ejecución de escrituras denegadas al perfil de lectura.
- Entrada con intento de inyección SQL tratada como dato; salida HTML escapada; CSRF y parámetros HTTP de tipo inválido.
- Cambio de cédula y contraseña propias; la clave anterior deja de funcionar; correo duplicado revierte también el cambio de nombre.

## Concurrencia

Se sincronizan dos procesos PHP para competir en tres escenarios:

1. Incorporar el mismo profesor en períodos superpuestos: confirma una sola operación.
2. Quitar simultáneamente la responsabilidad a los dos responsables existentes: permanece uno.
3. Acortar el proyecto mientras se incorpora una participación incompatible con el nuevo fin: se rechaza una operación y no quedan fechas inválidas.

Un interbloqueo de InnoDB es un error recuperable, no una operación exitosa. El procedimiento revierte y la interfaz pide reintentar. Las pruebas no son un ensayo de carga sostenida ni de disponibilidad.

## Respaldo y recuperación

Se creó una copia completa antes de modificar la base principal: `backups/pre_integridad_20261119.sql`, acompañada de su manifiesto JSON. Se conservaron además respaldos durante las fases de verificación; el respaldo de cierre es `backups/auditoria_final_20261119.sql`.

Se restauraron copias en bases nuevas, sin sobrescribir la activa. La herramienta verifica SHA-256 del archivo, conteos y hashes de filas por tabla, nombres de vistas/procedimientos/triggers y que las vistas no apunten a la base fuente. Los usuarios/roles del servidor no forman parte del dump de una base: se reprovisionan por separado.

La copia de trabajo inicial se restauró en investiga_auditoria_20261119; la copia con objetos de negocio se restauró en investiga_restore_20261119. La copia principal actualizada también se restauró en investiga_restore_final_20261119. El respaldo de cierre se verificó en investiga_restore_auditoria_final_20261119.

Las credenciales de la aplicación quedaron en .env, la cuenta administradora separada en .env.admin y el perfil de lectura en .env.report. Ninguna contraseña se incluye en evidencias-bd.json ni en este documento. Los respaldos y configuraciones privadas se excluyen del control de versiones.

## Límites de la validación y cierre académico

1. La revisión visual en escritorio/móvil sigue pendiente. En el intento anterior la herramienta de control de Chrome agotó su autorización; las pruebas HTTP no sustituyen revisión de apariencia o accesibilidad.
2. Los diagramas son editables en Mermaid. Falta renderizarlos y dar formato final al documento académico, con portada y revisión del equipo.
3. Deben exponerse las decisiones documentadas: presupuesto por intersección sin prorrateo, dedicación porcentual y perfiles según las reglas contradictorias resueltas por la corrección.
4. La escritura operativa está restringida a rutinas. El administrador de mantenimiento conserva poder de alterar o cargar datos: debe usar los procedimientos o completar/verificar la carga transaccional. No se atribuye a un CHECK la capacidad de validar mínimos entre tablas.
5. La limitación de intentos de inicio es por sesión. No hay recuperación de contraseña ni despliegue público en este alcance. Las pruebas no son certificación de seguridad ni garantía absoluta de ausencia de fallas.
6. Si el laboratorio exige otra versión del gestor, ejecutar allí las suites antes de presentar. La versión comprobada es MySQL 9.6.0.
