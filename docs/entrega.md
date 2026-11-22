# Sistema de gestión de proyectos de investigación: Investiga U

Documento técnico de la implementación revisada. Curso: Bases de Datos Avanzadas. Universidad Distrital Francisco José de Caldas. Fecha de esta versión: 19 de noviembre de 2026. Revisar portada y formato antes de presentar. Integrantes y respuesta a la evaluación: correcciones-primer-avance.md.

## 1. Definición del problema

Una universidad requiere centralizar el seguimiento de los proyectos de investigación desarrollados por los grupos de sus facultades. La información dispersa dificulta conocer qué profesores participan, cuáles proyectos están activos o terminados y qué presupuesto institucional comprometen durante un período.

La solución consiste en una aplicación web con almacenamiento relacional que vincula facultades, grupos, profesores, proyectos, participaciones y estudiantes. El sistema permite consultar la actividad investigativa y gestionar sus registros mediante perfiles de acceso.

### Funcionalidades implementadas

| Función | Decano | Docente |
|---|---|---|
| Ingresar, cerrar sesión y actualizar nombre, correo y contraseña propios | Sí | Sí |
| Consultar facultades, sedes, teléfonos y decanos | Sí | Sí |
| Consultar grupos y buscar por área, nombre o código | De su facultad | De su facultad |
| Crear grupos / modificar el grupo propio | No | Sí |
| Consultar profesores y títulos | De su facultad | De su facultad |
| Registrar o modificar profesores y títulos | De su facultad | No |
| Crear cuentas de docente | Para profesores de su facultad | No |
| Consultar proyectos, estados y participaciones | De su facultad | De su facultad |
| Crear, modificar o eliminar proyectos | No | De su grupo |
| Registrar y modificar participación de profesores | No | Proyectos de su grupo |
| Gestionar estudiantes por proyecto | No | Proyectos donde sea responsable |
| Consultar proyectos por profesor y estado | De su facultad | De su facultad |
| Consultar presupuesto por período | De su facultad | No |

El buscador de cada tabla permite filtrar por su contenido visible, incluidos área, grupo y estado. La consulta por profesor devuelve participación histórica; para revisar vigencia se usa la tabla de participaciones. Crear un proyecto incorpora automáticamente al docente creador como responsable con el porcentaje de dedicación indicado expresamente en el formulario, editable después.

### Entorno de ejecución

Arquitectura navegador → PHP/PDO → servidor relacional. Presentación con HTML5, CSS y JavaScript sin frameworks. Desarrollo en Windows; Apache de XAMPP o servidor integrado de PHP para ejecución local. Motor usado y comprobado: MySQL Community Server 9.6.0. También se validó el esquema y la migración en MariaDB 10.4.32. Codificación UTF-8/utf8mb4 con cotejamiento utf8mb4_unicode_ci, InnoDB y zona horaria de Bogotá. Montos almacenados como DECIMAL en pesos colombianos.

### Reglas y delimitación

1. Facultad: nombre institucional único, sede obligatoria, teléfonos asociados y decano que referencia un profesor.
2. Grupo: código institucional único, nombre único por facultad, facultad y área obligatorias; líder profesor. La interfaz exige elegir un líder.
3. Profesor: cédula única almacenada como texto, experiencia no negativa y un único grupo actual. Los títulos se registran en una tabla separada.
4. Proyecto: código y nombre institucionales únicos, presupuesto no negativo, inicio estrictamente anterior al fin y un solo grupo.
5. Participación: relación muchos a muchos profesor–proyecto con incorporación, retiro opcional, dedicación porcentual y marca de responsable.
6. La incorporación debe estar dentro del proyecto; el retiro debe ser posterior o igual a la incorporación y no superar el fin del proyecto.
7. No se permiten intervalos superpuestos para el mismo profesor y proyecto. Ambos extremos se consideran inclusivos: retirarse e incorporarse el mismo día sería superposición. Puede reingresar al día siguiente.
8. Dedicación: mayor que 0 y menor o igual a 100 por registro. No se controla todavía la suma entre proyectos, pues el avance no fija esa regla.
9. Todo proyecto creado desde la aplicación tiene al menos un responsable. Las rutinas y triggers impiden quitar, trasladar o borrar al último responsable; la cuenta operativa SQL no puede saltarse las altas compuestas mediante DML directo.
10. Proyecto activo: fecha actual entre inicio y fin, inclusive. Terminado: fecha actual posterior al fin. Programado: fecha actual anterior al inicio. El estado no se almacena.
11. Vigencia: fecha actual posterior o igual a incorporación, anterior o igual al retiro cuando existe, según la regla 34; no se limita por el fin del proyecto. No se almacena como booleano permanente.
12. Estudiante: código y correo únicos; puede participar en varios proyectos mediante estudiante_proyecto, sin duplicar su identidad. Un proyecto puede incorporar varios estudiantes y una pareja estudiante–proyecto no se repite. Los estudiantes no tienen cuenta ni responsabilidad de dirección; se pueden registrar antes de vincularlos.
13. Cuenta: correo único, un profesor por cuenta y roles DECANO o DOCENTE. Contraseñas almacenadas mediante hash, nunca texto plano.
14. Los docentes escriben sobre proyectos de su grupo; los decanos administran profesores de su facultad. Estas autorizaciones se verifican en servidor, además de ocultar controles.
15. La consulta presupuestal suma una vez el presupuesto completo de cada proyecto que interseca el período inclusivo. No representa ejecución mensual ni gasto real.
16. Eliminar un proyecto borra, en una transacción, sus vinculaciones estudiante–proyecto y participaciones de profesores; conserva las identidades de estudiantes y sus otros vínculos, además de la auditoría de la eliminación. La interfaz solicita confirmación.
17. Los teléfonos de facultad se identifican por (facultad_id, numero), y los títulos por (profesor_id, nombre). Son atributos multivaluados transformados a tablas con PK compuesta.
18. El grupo del profesor registrado_por administra los datos de identidad del estudiante. Solo el profesor responsable administra las vinculaciones con sus proyectos, incluso si el estudiante fue registrado por otro grupo.

### Decisiones que debe revisar el equipo con la docente

- El avance enumera un rol Estudiante y luego excluye su acceso: se adoptó la regla final de estudiantes sin cuenta.
- El cálculo por período no define prorrateo: se eligió sumar presupuestos completos con intersección de fechas.
- El modelo original separa varias relaciones uno a muchos en tablas intermedias. Se simplificaron a FK; se mantiene la tabla asociativa de participaciones porque sí es muchos a muchos y tiene atributos propios.
- La pertenencia de profesor es al grupo actual, sin historial. La participación puede ser en proyectos de otros grupos, porque el enunciado no lo prohíbe.
- El docente del mismo grupo puede administrar sus proyectos; falta confirmar si solo el líder o responsable debe poder hacerlo.
- La facultad y el grupo se crean antes del profesor para resolver el ciclo de referencias. `decano_id` y `lider_id` admiten NULL durante inicialización SQL; los datos de demostración los completan. El procedimiento administrativo de alta completa esas referencias y los mínimos de teléfono/título antes de COMMIT; los usuarios operativos no tienen DML directo. Los triggers impiden destruir los mínimos establecidos. Ver objetos-bd.md para el alcance de la cuenta administradora.
- Un decano es también profesor, pero la cuenta actual tiene un solo rol. Si necesita operar como docente, hay que acordar un modelo de permisos múltiples.
- La cédula propia se modifica desde Mi cuenta con confirmación de contraseña; el decano también puede corregirla en Profesores.
- La observación docente sobre estudiantes modifica la antigua regla 42 del avance: ahora se permite participación en varios proyectos. Ver correcciones-primer-avance.md.

## 2 y 3. Modelos

Consultar `modelos.md`. Son modelos actualizados con las correcciones del primer avance. Las entidades principales declaran id como identificador tanto en ER como en el relacional; sus códigos y cédulas mantienen restricciones UNIQUE. Las FK apuntan a las PK documentadas con tipos coincidentes. Teléfonos, títulos y vinculaciones estudiante–proyecto usan PK compuestas; sus id auxiliares son claves alternativas UNIQUE.

## 4. Diccionario de datos

Consultar `diccionario.md`, que describe las columnas, vistas, procedimientos y triggers. La estructura ejecutable es la fuente de referencia para los tipos y restricciones.

## 5. Código fuente

Estructura: `database/01_schema.sql` y `database/03_business.sql`. Inserción: `database/02_seed.sql` y `scripts/install.php` para hashes de las cuentas. Conexión: `app/bootstrap.php`, mediante PDO, consultas preparadas y configuración externa `.env`. Interfaz y operaciones: `public/index.php`.

Las altas de proyecto y responsable se realizan atómicamente; los cambios de participaciones se revierten si dejan un proyecto sin responsable. Los triggers protegen fechas y superposiciones incluso fuera de la aplicación. La rutina de validación bloquea el proyecto para serializar las asignaciones concurrentes sobre él. DDL e instalación requieren una cuenta con permisos adicionales; la aplicación ya utiliza una cuenta separada sin escrituras directas ni DDL. La configuración de mantenimiento está en .env.admin, accesible solo a herramientas CLI.

## 6. Manual de usuario

Consultar `manual.md`. Incluye un recorrido para la sustentación usando datos ficticios.

## Matriz de entrega y validación

| Elemento evaluado | Evidencia | Estado |
|---|---|---|
| Problema, funciones, entorno y reglas | Este documento | Borrador para revisión académica |
| Diagrama entidad–relación | `modelos.md`, diagrama conceptual Mermaid | Elaborado; revisar cardinalidades mínimas |
| Diagrama relacional | `modelos.md`, diagrama físico Mermaid | Elaborado |
| Diccionario de datos y objetos programables | `diccionario.md` | Elaborado |
| Estructura e inserciones SQL | `database/` | Versión completa ejecutada en MySQL 9.6; la prueba anterior en MariaDB no incluye los nuevos procedimientos |
| Conexión aplicación–BD | `app/bootstrap.php` | Probada localmente |
| Manual | `manual.md` | Elaborado |
| Motor permitido: MySQL, PostgreSQL o SQL Server | MySQL Community Server 9.6.0 | Aplicación conectada y pruebas SQL/HTTP correctas |
| Aplicación funcional | `public/` y pruebas | Flujos HTTP y BD comprobados; revisión visual pendiente |

No se afirma conformidad final mientras falten el cierre de decisiones del alcance, la revisión visual y el formato definitivo del documento.


## Auditoría de reglas y temas de la materia

Consultar auditoria-reglas.md (45 reglas y correcciones), temas-materia.md (uso justificado de los temas), objetos-bd.md y evidencias-bd.json. Respaldo y recuperación se comprobaron con restauración en una base distinta. Los scripts operativos son backup.php, restore.php e import_students.php.
