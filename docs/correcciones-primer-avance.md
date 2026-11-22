# Correcciones del primer avance — Grupo 4

Fecha de revisión e implementación: 17 de noviembre de 2026.

| Código | Integrante |
|---|---|
| 20231578140 | PAEZ GAVILAN JUAN SEBASTIAN |
| 20202578094 | ARCILA FORERO YAMID ALBEIRO |
| 20201578018 | GALINDO FAJARDO EDWARD STEVEN |
| 20262678083 | DUARTE BELLO JHONATHAN YESID |

Calificación comunicada: 3.6/5.0. La definición del problema, funcionalidades, entorno y reglas obtuvo 3.0/3.0; cada uno de los dos modelos obtuvo 0.3/1.0. Las observaciones se aplican al primer avance entregado; la versión corregida se encuentra en esta carpeta.

## Respuesta y evidencia

| Observación recibida | Corrección aplicada | Evidencia |
|---|---|---|
| Un estudiante puede investigar en más de un proyecto | Se elimina proyecto_id de estudiantes; se crea estudiante_proyecto con relación N:M y PK compuesta. | SQL, migración y módulo Estudiantes por proyecto; pruebas de múltiples proyectos y eliminación independiente. |
| No dirigir flechas hacia rombos | Los modelos revisados en modelos.md usan pata de cuervo, sin flechas direccionales ni rombos. Al presentar una versión Chen se deben usar conexiones sin puntas de flecha y cardinalidades explícitas. | Diagramas editables en modelos.md. |
| Vigente es deducible de las fechas | No hay columna vigente en participaciones ni atributo persistente en ER; solo se calcula en v_participaciones. | SQL y prueba de ausencia de la columna. |
| facultad_telefono no refleja adecuadamente el atributo multivaluado | telefonos_facultad tiene PK (facultad_id, numero); facultad_id es FK. | SQL y pruebas de unicidad por propietario. |
| Mismo problema en profesor_titulo | titulos tiene PK (profesor_id, nombre); profesor_id es FK. | SQL y pruebas: no duplicar un título por profesor, permitir el mismo título en profesores diferentes. |
| PK de algunas tablas no coincide con su entidad | Se explicita id como identificador en las entidades principales del modelo revisado y como PK en SQL; cédula/código son claves alternativas UNIQUE. | Tabla de correspondencia en modelos.md. |
| FK incorrecta de participa hacia proyecto | participaciones.proyecto_id INT referencia proyectos.id INT, igual que la PK revisada de Proyecto. | DDL y prueba de rechazo de proyecto inexistente. |

## Cambios de reglas y uso

La antigua regla 42 se reemplaza por: “Un estudiante puede participar como investigador en cero, uno o varios proyectos; un proyecto puede incorporar cero, uno o varios estudiantes”. El estado sin vínculos permite registrar primero al estudiante y asignarlo después. No se repite la misma pareja estudiante–proyecto.

El estudiante no tiene cuenta y no se convierte en responsable de proyecto. El docente registra su identidad una vez y gestiona las vinculaciones desde un módulo separado. Eliminar un proyecto solo elimina sus vínculos y participaciones; se conservan el estudiante y sus otros proyectos.

El campo registrado_por asigna al grupo del profesor registrante la edición de la identidad, mientras cada grupo gestiona los vínculos con sus proyectos. Al migrar datos antiguos, como no existía autor del registro, se asigna el líder del grupo del proyecto anterior, o el primer profesor de ese grupo si no tenía líder. Se documenta como asignación de gestión, no como reconstrucción de autoría histórica.

Los id auxiliares de teléfonos, títulos y vinculaciones son UNIQUE para conservar los formularios existentes. No sustituyen sus PK compuestas. La versión de noviembre incluye un migrador que conserva datos, comprueba títulos duplicados antes de cambiar la PK y exige un archivo de respaldo.

## Validación y cierre

Se ejecutan pruebas sobre la base anterior migrada y sobre una instalación nueva. Los resultados específicos se registran en validacion.md. La aprobación académica del modelo sigue correspondiendo a la docente; estas modificaciones resuelven las observaciones con decisiones explícitas y comprobables.

La aplicación se transfirió a MySQL Community Server 9.6.0 conservando los datos anteriores. En la revisión del 19 de noviembre se cerraron los mínimos operativos mediante procedimientos, triggers y privilegios, y se verificó la restauración de un respaldo. Ver auditoria-reglas.md y temas-materia.md. Para terminar la entrega aún se requiere revisar visualmente las pantallas y producir el documento final con diagramas renderizados y formato de entrega.
