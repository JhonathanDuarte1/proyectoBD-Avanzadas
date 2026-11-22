# Aplicación de los temas de Bases de Datos Avanzadas

Versión del 19 de noviembre de 2026. La selección responde a necesidades del sistema; no se introducen motores, eventos o funciones sin un caso de uso.

| Tema del curso | Uso real en el proyecto | Evidencia para sustentar |
|---|---|---|
| DDL | Tablas normalizadas, PK/FK, UNIQUE, CHECK, vistas, procedimientos, triggers y perfiles SQL. | 01_schema.sql, 03_business.sql y provision_security.php. |
| DML | Inserción de ejemplos y operaciones INSERT/UPDATE/DELETE dentro de unidades de negocio controladas. | 02_seed.sql, sp_guardar, sp_eliminar. |
| Consultas para problemas específicos | Proyectos de profesor, estados por facultad, profesores por grupo, presupuesto por intervalo. | 04_queries.sql, reportes de la aplicación. |
| Estrategias de almacenamiento | Elección de InnoDB para datos persistentes con FK, transacciones, índices y bloqueo por filas. Comparación razonada con MyISAM/MEMORY. | Sección siguiente y ENGINE de las tablas. |
| Respaldo y recuperación | Dump lógico con datos, vistas, rutinas, triggers y eventos; hash del archivo y manifiesto de filas/hashes. Restauración a base nueva, verificación de datos, objetos y dependencias de vistas. | backup.php, restore.php y pruebas documentadas. |
| Mecanismos de carga | Semilla SQL, formularios y CSV validado convertido a lote JSON_TABLE. El lote se confirma completo o se revierte. | 02_seed.sql, import_students.php, csv_import.php y sp_importar_estudiantes. |
| Consultas complejas y niveles | Subconsultas EXISTS, agrupaciones, CTE que preagrega relaciones N:M y ventana DENSE_RANK. Se evita duplicar presupuesto o profesores por reingresos. | v_cartera_proyectos, 04_queries.sql y pruebas de conteo. |
| Vistas y procesamiento | Vistas reutilizables para ámbito, estado, vigencia, resumen y cartera. La interfaz consulta estas vistas. | Inicio y Consultas y presupuesto. |
| Reglas de integridad | NOT NULL, PK/FK, UNIQUE y CHECK para restricciones locales; triggers/procedimientos para condiciones entre tablas. | auditoria-reglas.md y tests/business.php. |
| Procedimientos y triggers | Altas compuestas, control de permisos, carga por lote, presupuesto, mínimos de relación y auditoría. | objetos-bd.md. |
| Seguridad SQL | Cuenta del backend sin escrituras directas/DDL y cuenta de lectura sin acceso a credenciales; EXECUTE concedido solo a rutinas públicas. | SHOW GRANTS y pruebas con denegación real del motor. |
| Usuarios, perfiles y privilegios | Roles SQL de servicio y consulta; administrador separado. Roles funcionales DECANO/DOCENTE verificados por sesión y procedimiento. | provision_security.php, sp_actor y authorization.php. |
| Transacciones | START TRANSACTION/COMMIT, handler de errores con ROLLBACK; READ COMMITTED y FOR UPDATE para cambios concurrentes. | tests/business.php y tests/concurrency.php. |
| Lenguaje de programación y persistencia | PHP con PDO, parámetros preparados, resultados, excepciones, sesiones y configuración separada. | app/bootstrap.php, app/actions.php, formularios y pruebas HTTP. |

## Por qué usar InnoDB

| Alternativa | Características relevantes | Decisión para este sistema |
|---|---|---|
| InnoDB | Almacenamiento persistente, transacciones ACID, recuperación ante fallos, FK y bloqueos de filas. | Elegido para todas las tablas. Es necesario para revertir altas compuestas y preservar relaciones. |
| MyISAM | No proporciona las transacciones y FK de InnoDB; usa bloqueo de tablas. | No se usa para datos institucionales que requieren rollback e integridad referencial. |
| MEMORY | Conserva filas en memoria y pierde sus datos al reiniciar el servidor. | No se usa como almacenamiento de proyectos, identidades o cuentas. |

No se cambia una tabla a otro motor para demostrar un tema: rompería las garantías de las operaciones. Los índices primarios y secundarios existentes se inspeccionan con SHOW INDEX y EXPLAIN. Con los pocos datos de demostración el optimizador puede preferir un recorrido completo; no se afirma una mejora de tiempo sin una medición con volumen representativo.

## Reglas en MySQL

Se aplican restricciones CHECK/FK/UNIQUE y lógica de procedimientos/triggers. No se simula una sentencia CREATE RULE de otro gestor ni se añade un objeto sin soporte en MySQL. Las condiciones de mínimo entre entidades se cumplen mediante una interfaz SQL de escritura restringida y transacciones, no mediante un CHECK ficticio con subconsultas.

## Respaldo y recuperación: alcance

El respaldo es lógico. Permite recuperar una instantánea verificada y no se presenta como recuperación a un instante arbitrario: no se implementó una cadena administrada de copias y binary logs. Los usuarios/roles del servidor no se incluyen en el dump de una base; al restaurar en un servidor nuevo se reprovisionan con el script de seguridad. Un hash detecta cambios respecto del manifiesto, pero no es una firma contra un atacante que pudiera cambiar ambos archivos.

## Fuentes técnicas consultadas

- [Motores de almacenamiento de MySQL](https://dev.mysql.com/doc/refman/8.4/en/storage-engines.html).
- [JSON_TABLE](https://dev.mysql.com/doc/refman/8.4/en/json-table-functions.html).
- [mysqldump](https://dev.mysql.com/doc/refman/8.4/en/mysqldump.html).
- [Lecturas con bloqueo](https://dev.mysql.com/doc/refman/8.4/en/innodb-locking-reads.html).
- [Seguridad de procedimientos](https://dev.mysql.com/doc/refman/8.4/en/create-procedure.html).

La implementación y las pruebas se ejecutaron en MySQL Community Server 9.6.0; las referencias de 8.4 explican los mecanismos usados, no acreditan una prueba ejecutada en esa versión.
