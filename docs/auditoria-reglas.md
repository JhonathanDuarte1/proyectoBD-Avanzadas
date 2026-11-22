# Trazabilidad de reglas de negocio

Revisión del 19 de noviembre de 2026. Fuente: primer avance suministrado por el equipo (`Proyecto final.pdf`), páginas 7 y 8, y corrección docente posterior. Se resume el requisito para contrastarlo con la implementación; el PDF original se conserva sin cambios.

Las expresiones “alta atómica” y “mínimo” deben interpretarse con el mecanismo de escritura controlada descrito en objetos-bd.md, no como restricciones diferidas de las tablas. El usuario web carece de escritura directa; el administrador no es el usuario operativo.

| Regla del avance | Requisito resumido | Implementación y comprobación |
|---|---|---|
| 1 | Nombre único de facultad | UNIQUE en facultades.nombre. |
| 2 | Facultad ubicada en una sede | sede_id NOT NULL y FK. |
| 3 | Teléfonos asociados a una facultad, al menos uno | Tabla multivaluada con PK (facultad_id,numero), FK; alta compuesta y protección del último valor. Cada registro tiene un propietario. |
| 4 | Decano debe ser profesor | FK, alta compuesta y trigger que impide dejar NULL; no se impone pertenencia al mismo grupo. |
| 5 | Grupo pertenece a una facultad | facultad_id NOT NULL y FK. |
| 6 | Nombre de grupo único dentro de la facultad | UNIQUE(facultad_id,nombre). |
| 7 | Código de grupo institucional único | UNIQUE(codigo). |
| 8 | Grupo asociado a un área | area_id NOT NULL y FK. |
| 9 | Grupo con profesor líder | FK, selección de profesor y trigger; alta compuesta para el primer grupo. No se inventa obligación de que ya pertenezca al grupo que lidera. |
| 10 | Profesor identificado por cédula única | cedula UNIQUE, texto de hasta 20 caracteres; id técnico estable. |
| 11 | Uno o varios títulos por profesor | PK (profesor_id,nombre), primer título en la misma transacción y protección de último título. |
| 12 | Años de experiencia | INT con CHECK no negativo; formulario admite años enteros. |
| 13 | Profesor pertenece a un grupo existente | grupo_id NOT NULL y FK; los formularios eligen grupos de la facultad del decano. |
| 14 | Grupo puede tener varios profesores | Relación 1:N, sin UNIQUE en profesores.grupo_id. |
| 15 | Identificación de proyecto por nombre y código | Código/nombre requeridos y únicos; misma PK id en modelos y FK. |
| 16 | Presupuesto no negativo | DECIMAL(15,2), CHECK y validación de formulario. |
| 17 | Fechas inicial/final obligatorias | DATE NOT NULL; validación de fechas reales. |
| 18 | Inicio estrictamente anterior al fin | CHECK(inicio < fin). |
| 19 | Grupo desarrolla varios proyectos; proyecto tiene un grupo | grupo_id NOT NULL y FK; relación 1:N. |
| 20 | Profesor puede participar en varios proyectos | Tabla participaciones N:M, con períodos de reingreso. |
| 21 | Proyecto con profesores participantes/responsables | Alta con el creador como responsable; dedicación explícita. No se permite perder el último responsable. |
| 22 | Incorporación obligatoria | DATE NOT NULL. |
| 23 | Retiro opcional | DATE NULL. |
| 24 | Incorporación <= retiro | CHECK para retiro no nulo. |
| 25 | Dedicación durante el período | Campo por participación, porcentaje >0 y <=100; unidad elegida y documentada. No se impone suma de porcentajes entre proyectos. |
| 26 | Sin participaciones simultáneas del mismo profesor/proyecto | Trigger, serialización por proyecto y prueba con dos conexiones. Los extremos son inclusivos; reingreso desde el día siguiente. |
| 27 | Activo según fecha actual e intervalo | CASE en v_proyectos, extremos inclusivos. |
| 28 | Terminado después de la fecha final | CASE, sin estado almacenado. |
| 29 | Facultad del proyecto a través de su grupo | JOIN proyectos → grupos → facultades. |
| 30 | Presupuesto de facultad durante un período | sp_presupuesto y envoltura por actor; intersección inclusiva, suma completa una vez por proyecto. El avance no define prorrateo. |
| 31 | Decano registra profesores de su facultad | sp_actor verifica asignación real del decanato; sp_guardar/sp_usuario limitan grupo y facultad. Se prueba pérdida del permiso al dejar de ser decano. |
| 32 | Incorporación no anterior al inicio del proyecto | Trigger de participación. |
| 33 | Retiro no posterior al fin del proyecto | Trigger de participación. |
| 34 | Vigencia obtenida desde incorporación y retiro | fecha actual >= incorporación AND (retiro NULL OR fecha actual <= retiro). No se añade un límite por fin de proyecto; puede existir participación vigente en un proyecto terminado si no tiene retiro. Prueba explícita. |
| 35 | Cuenta para acceder | Sesión autenticada; rutas de escritura rechazan acceso anónimo. |
| 36 | Correo de cuenta único | UNIQUE y validación; cambio duplicado revierte la transacción completa. |
| 37 | Contraseña de autenticación | Hash generado con password_hash y validado con password_verify; contraseña actual requerida al modificar cuenta. |
| 38 | Perfiles de usuario | DECANO/DOCENTE. La mención Estudiante se resuelve con la regla 45 que excluye cuentas estudiantiles. |
| 39 | Código estudiantil único | UNIQUE(estudiantes.codigo). |
| 40 | Docente registra nombre y correo del estudiante | sp_guardar y carga por lote verifican perfil DOCENTE; datos obligatorios y correo válido. |
| 41 | Correo estudiantil único | UNIQUE(estudiantes.correo). |
| 42 | Cardinalidad estudiante–proyecto | Sustituida por la corrección docente: N:M mediante estudiante_proyecto. No se aplica la antigua exclusividad. |
| 43 | Varios estudiantes por proyecto | Tabla asociativa sin unicidad de proyecto_id; puede registrarse un proyecto antes de incorporar estudiantes. |
| 44 | Estudiantes participantes, nunca responsables | No existe responsable en estudiante_proyecto ni cuentas de estudiante. Responsable solo en la relación con profesores. |
| 45 | Sin acceso estudiantil; seguimiento a cargo del responsable | Estudiantes sin cuenta; solo el profesor marcado responsable gestiona sus vínculos con el proyecto. Se prueban permisos en SQL y HTTP. |

## Decisiones de alcance expresas

- Presupuesto por período: suma completa de proyectos con intersección, sin prorrateo ni ejecución real de gastos.
- Dedicación: porcentaje por período, sin imponer una suma máxima entre proyectos que no aparece en el avance.
- Los docentes gestionan proyectos de su grupo; en estudiantes se exige además la responsabilidad del proyecto, según la regla 45.
- Se puede corregir la cédula propia desde Mi cuenta, sin modificar las FK internas. El decano también la puede corregir desde Profesores.
- Si un profesor es decano de varias facultades, el contexto actual toma la de su grupo si corresponde y en otro caso la de menor id. No hay un selector para administrar varios decanatos en una sesión; el caso de demostración tiene uno por cuenta.
- Las sedes, áreas, teléfonos y altas iniciales de facultad se mantienen por administración SQL. El avance exige su consulta, no módulos públicos de alta de estos catálogos.

## Evidencias ejecutables

`tests/database.php` cubre restricciones y estructura; `tests/business.php` cubre permisos y operaciones compuestas en una copia; `tests/concurrency.php` usa dos procesos/conexiones; `tests/web_integration.py` y `tests/web_acceptance.py` cubren rutas HTTP y formularios. Las restauraciones con manifiesto se describen en validacion.md. Estas pruebas no garantizan ausencia absoluta de defectos ni reemplazan la revisión visual.
