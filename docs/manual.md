# Manual de usuario — Investiga U

## Ingresar

Abre http://127.0.0.1:8080 con el servidor en ejecución. Escribe el correo y contraseña asignados. Los datos de demostración están en el README. Tras cinco intentos fallidos, esa sesión debe esperar un minuto.

El resumen muestra cantidades y presupuesto total de los proyectos de tu facultad y próximas terminaciones. El estado se calcula usando la fecha del servidor. Los ejemplos tienen fechas de 2025 a 2027; cambiará su estado con el paso del tiempo.

## Consultar registros

Usa el menú para abrir Facultades, Grupos, Profesores, Títulos, Proyectos, Participaciones, Estudiantes o Estudiantes por proyecto. La búsqueda de cada tabla filtra sus registros visibles por nombre, código, área o cualquier otro texto de las columnas. Borra el texto de búsqueda para verlos todos.

En Consultas y presupuesto puedes elegir facultad, profesor y estado, incluidas otras facultades. Seleccionar un profesor devuelve los proyectos donde tiene registros de participación, incluido el historial. En Participación de profesores se muestra la vigencia de cada período.

## Operaciones del decano

1. En Profesores, registra cédula, nombre, experiencia en años enteros, grupo y primer título. Profesor y título se guardan juntos. No se permiten cédulas duplicadas.
2. En Títulos, selecciona al profesor e ingresa cada título obtenido. Repite para registrar varios.
3. En Crear cuenta docente, selecciona un profesor registrado, escribe un correo único y una contraseña inicial de al menos diez caracteres. El rol asignado es DOCENTE.
4. En Consultas y presupuesto, ingresa Desde y Hasta y pulsa Calcular presupuesto. Se suma el presupuesto total de proyectos que se cruzan con ese período, sin prorrateo. Esta opción solo aparece al decano.

No hay registro público ni selección libre del rol desde un formulario.

## Operaciones del docente

1. Grupos: registra código, nombre, área y un líder que sea profesor registrado en la universidad. Puedes editar el grupo al que perteneces. Crear otro grupo no cambia tu pertenencia; el decano puede asignar profesores al nuevo grupo.
2. Proyectos: completa código, nombre, presupuesto COP, fechas, grupo propio y tu porcentaje de dedicación inicial. Al guardar quedas como primer responsable desde la fecha de inicio con el porcentaje que ingresaste; no se asigna un valor arbitrario.
3. Participación de profesores: elige un proyecto propio, profesor, fechas, porcentaje y marca responsable (1 sí, 0 no). El retiro es opcional. Para modificar el porcentaje inicial busca tu participación y pulsa Editar.
4. Puedes asignar otros profesores, incluidos los de otros grupos. No se permiten períodos superpuestos para el mismo profesor y proyecto.
5. Antes de quitar la responsabilidad al último responsable, asigna a otro. Si la operación dejara el proyecto sin responsable, se cancela.
6. Estudiantes: registra código, nombre y correo una sola vez. No tiene credenciales. El grupo que registra la identidad puede modificar sus datos. Los demás grupos pueden vincular al mismo estudiante sin duplicarlo.
7. Estudiantes por proyecto: selecciona un estudiante ya registrado y un proyecto del que seas profesor responsable. Repite para vincularlo a otros proyectos; no se permite duplicar la misma pareja. Puedes editar o retirar cada vínculo por separado. Retirar un vínculo conserva al estudiante y sus demás proyectos. Pertenecer al grupo del proyecto no basta para gestionar estudiantes.
8. Editar proyecto: pulsa Editar en su fila. No puedes acortar las fechas de modo que excluyan participaciones existentes.
9. Eliminar proyecto: pulsa Eliminar y acepta la confirmación. Se eliminan sus participaciones y vínculos con estudiantes, pero se conservan los datos de los estudiantes y sus otros proyectos. Se conserva una anotación de auditoría. No hay papelera.

## Mi cuenta y salida

En Mi cuenta cambia tu cédula, nombre o correo y confirma con la contraseña actual. Para cambiar la contraseña, completa también Nueva contraseña. Cierra sesión al terminar. Si el correo o la cédula ya existen, se revierte el cambio completo.

La columna vigente de una participación sigue exactamente la regla 34: fecha actual igual o posterior a incorporación y retiro no registrado o no superado. Un proyecto terminado puede tener una participación vigente si nunca se registró retiro. Estado del proyecto y vigencia de la participación son conceptos separados.

El decano dispone además de una cartera ordenada por presupuesto, con cantidades de profesores distintos y estudiantes. La clasificación admite empates y no representa una evaluación de calidad del proyecto.

## Mensajes frecuentes

- “No tienes permiso”: el rol no permite esa operación.
- “Registro fuera de tu ámbito”: el registro pertenece a otro grupo o facultad.
- “Regla de negocio”: revisa fechas y posibles participaciones superpuestas.
- “No se pudo guardar”: verifica códigos, correos o nombres únicos, valores y claves relacionadas. Los detalles técnicos se registran en el servidor.
- “Sesión del formulario venció”: recarga la página y vuelve a intentar.
- “No se pudo conectar”: revisa que el motor esté iniciado y `.env` corresponda con su puerto y credenciales.

## Guion de sustentación

1. Ingresar como decano y mostrar la información de Facultad Tecnológica.
2. Consultar profesores del grupo Software y sociedad y sus títulos.
3. Consultar proyectos de Laura Martínez y luego filtrar por ACTIVO.
4. Consultar presupuesto entre 2026-01-01 y 2026-12-31: los datos originales suman $45.000.000 COP para Tecnología.
5. Cerrar sesión e ingresar como docente. Crear un proyecto y mostrar su responsable inicial.
6. Intentar una participación superpuesta y explicar el rechazo del trigger.
7. Vincular un estudiante a dos proyectos, mostrar que existe una sola identidad y retirar uno de sus vínculos conservando el otro.
8. Modificar el presupuesto y consultar la tabla auditoria en el gestor de BD.
9. Mostrar las PK compuestas de telefonos_facultad, titulos y estudiante_proyecto, y la FK participaciones.proyecto_id → proyectos.id.
10. Mostrar las vistas y procedimientos del esquema; explicar que vigente se calcula con fechas y no es una columna persistente.

La ejecución del flujo visual debe revisarse en el navegador del equipo antes de presentar. Las comprobaciones automáticas HTTP no validan apariencia, tamaños de pantalla ni accesibilidad completa.
