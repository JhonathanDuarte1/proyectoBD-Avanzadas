# Objetos programables, integridad y permisos

Versión revisada el 19 de noviembre de 2026. Definiciones ejecutables: `database/01_schema.sql` y `database/03_business.sql`.

## Vistas

| Vista | Finalidad y resultado |
|---|---|
| v_proyectos | Proyecto, grupo, facultad y estado calculado PROGRAMADO/ACTIVO/TERMINADO. No duplica filas por investigador. |
| v_profesores | Profesor, grupo y facultad para aplicar el alcance de consulta. |
| v_participaciones | Participación, nombres y vigente calculado exactamente con incorporación y retiro (regla 34). No depende del estado del proyecto. |
| v_cartera_proyectos | CTE que agrega profesores distintos y estudiantes antes de unirlos al proyecto; DENSE_RANK por presupuesto dentro de la facultad. Se presenta al decano en Consultas y presupuesto. |
| v_resumen_facultad | Cantidades de proyectos, estados y presupuesto total, incluso para facultades sin proyectos. Alimenta el resumen de la aplicación. |

## Procedimientos

| Rutina | Entrada | Efecto / salida |
|---|---|---|
| sp_actor | actor; cuatro parámetros OUT | Resuelve profesor, grupo, facultad y rol. Para DECANO consulta la asignación real de facultades; una cuenta con rol antiguo no conserva permisos. Es auxiliar y no se concede a la cuenta web. |
| sp_guardar | actor, entidad, registro (0 para alta), datos JSON | Operaciones permitidas por entidad y rol; valida alcance en SQL y confirma o revierte la unidad completa. Devuelve el identificador. Un profesor nuevo exige titulo_inicial; un proyecto nuevo exige dedicacion_inicial y crea al responsable. |
| sp_eliminar | actor, entidad, registro | Borra proyectos del grupo o vínculos estudiantiles de un responsable. Las FK CASCADE eliminan participaciones/vínculos de un proyecto, sin borrar estudiantes. |
| sp_cuenta | actor, cédula, nombre, correo, hash | Actualiza identidad y cuenta propias en una transacción. PHP verifica antes la contraseña actual. SQL exige un hash y conserva restricciones de unicidad. |
| sp_usuario | actor, profesor, correo, hash | Solo el decano de la facultad crea una cuenta DOCENTE para un profesor de ella. No permite elegir un rol superior. |
| sp_presupuesto | facultad, desde, hasta | Agrega una sola vez el presupuesto de proyectos que intersectan el intervalo inclusivo. Rechaza período inválido. Rutina de mantenimiento/consulta interna. |
| sp_presupuesto_usuario | actor, desde, hasta | Comprueba que el actor sea decano y llama la agregación de su facultad. Es la rutina usada por el sitio. |
| sp_validar_participacion | registro, proyecto, profesor, incorporación, retiro | Verifica fechas y ausencia de superposición; bloquea el proyecto. Utilizada por triggers. |
| sp_importar_estudiantes | actor, arreglo JSON | Convierte un lote con JSON_TABLE e inserta en una sola transacción. Un error o duplicado revierte todo el lote. El importador CSV lo utiliza después de validar el archivo. |
| sp_crear_facultad | nombre, sede, teléfono, código/nombre de grupo, área, cédula/nombre de profesor y título | Alta administrativa atómica de facultad, teléfono, grupo, profesor, título, decano y líder; completa las referencias circulares antes de COMMIT. No se concede al servicio web. |

Las rutinas públicas de escritura usan SQL SECURITY DEFINER y una lista explícita de entidades; no construyen SQL dinámico a partir del JSON. Los parámetros de perfil/facultad se resuelven desde la cuenta, no se aceptan del formulario. El identificador actor proviene de la sesión PHP: la credencial de servicio pertenece al backend y no se distribuye a los usuarios finales.

## Triggers

| Trigger | Evento | Regla o evidencia |
|---|---|---|
| tr_participacion_insert | BEFORE INSERT | Fechas dentro del proyecto y sin períodos superpuestos para el mismo profesor/proyecto. |
| tr_participacion_update | BEFORE UPDATE | Revalida el período y excluye la propia fila al buscar conflicto. |
| tr_proyecto_fechas | BEFORE UPDATE | No permite acortar o mover fechas dejando participaciones fuera del proyecto. |
| tr_responsable_minimo_update | BEFORE UPDATE | No permite quitar o trasladar al último responsable. |
| tr_responsable_minimo_delete | BEFORE DELETE | No permite borrar la última participación responsable de un proyecto existente. |
| tr_facultad_decano | BEFORE UPDATE | Impide dejar NULL en el decano de una facultad establecida. |
| tr_grupo_lider | BEFORE UPDATE | Impide dejar NULL en el líder de un grupo establecido. |
| tr_titulo_minimo_delete / tr_titulo_minimo_update | BEFORE DELETE / UPDATE | No borra ni traslada el último título de un profesor. |
| tr_telefono_minimo_delete / tr_telefono_minimo_update | BEFORE DELETE / UPDATE | No borra ni traslada el último teléfono de una facultad. |
| tr_proyecto_insert_auditoria | AFTER INSERT | Registra creación y conexión SQL. Si falla la operación compuesta, la bitácora también se revierte. |
| tr_proyecto_auditoria | AFTER UPDATE | Registra código y presupuesto anterior/nuevo con la conexión SQL. |
| tr_proyecto_delete_auditoria | BEFORE DELETE | Conserva un registro histórico aunque desaparezca el proyecto. |

La auditoría identifica la conexión MySQL mediante USER(), no identifica por sí sola la persona de la sesión PHP. Es una bitácora de proyectos, no un historial completo de todas las entidades.

## Mínimos obligatorios y referencias circulares

Las altas compuestas completan los mínimos de título, teléfono, decano, líder y responsable antes de confirmar. Las bajas y modificaciones que puedan romperlos están protegidas por triggers y bloqueo de padres. La cuenta web no dispone de INSERT, UPDATE, DELETE ni DDL directo sobre las tablas: únicamente puede llamar las rutinas públicas concedidas.

`decano_id` y `lider_id` siguen admitiendo NULL físicamente para resolver el alta circular facultad → grupo → profesor → decano/líder. Ese estado intermedio solo pertenece a la transacción administrativa de alta. No se afirma que un CHECK entre tablas garantice el mínimo: la garantía de escritura es la combinación de procedimientos, transacciones, triggers y privilegios. La cuenta administradora conserva poder de mantenimiento y debe usar las rutinas o verificar el conjunto completo al cargar datos; puede alterar estructuras y no es una cuenta de operación.

El borrado de proyecto utiliza CASCADE en las dos FK dependientes. En el MySQL comprobado las acciones de cascada no ejecutan los triggers de las filas hijas; el padre se elimina como una operación completa. La prueba verifica que no se bloquea el borrado legítimo ni se pierden identidades estudiantiles.

## Perfiles SQL y perfiles de la aplicación

| Perfil SQL | Permisos efectivos | Uso |
|---|---|---|
| Servicio web | SELECT en la base; EXECUTE en sp_guardar, sp_eliminar, sp_cuenta, sp_usuario, sp_presupuesto_usuario y sp_importar_estudiantes | Backend con credencial privada. Sin DML directo, DDL, GRANT ni acceso a altas administrativas. |
| Consulta | SELECT sobre vistas y catálogos enumerados | Consultas académicas sin acceso a usuarios/password_hash y sin ejecución de escrituras. |
| Mantenimiento | Credencial separada en .env.admin, usada solo por herramientas CLI | Instalación, respaldo, restauración y mantenimiento. |

DECANO y DOCENTE son perfiles funcionales almacenados en usuarios; no son cuentas personales de conexión a MySQL. La separación de estos dos niveles evita confundir autorización de pantallas con privilegios del motor.

Las altas y cambios de participaciones se ejecutan con READ COMMITTED y bloqueos FOR UPDATE sobre los proyectos en orden estable. Las rutinas completas son unidades de transacción; no se anidan dentro de una transacción externa. Un interbloqueo produce rollback y puede reintentarse mediante un nuevo envío; no se oculta como éxito.

Referencias técnicas: [seguridad de rutinas](https://dev.mysql.com/doc/refman/8.4/en/create-procedure.html), [lecturas con bloqueo](https://dev.mysql.com/doc/refman/8.4/en/innodb-locking-reads.html) y [restricciones de programas almacenados](https://dev.mysql.com/doc/refman/8.4/en/stored-program-restrictions.html).
