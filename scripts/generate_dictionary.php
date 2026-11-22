<?php
require __DIR__.'/admin.php';
if(PHP_SAPI!=='cli')exit;
$descriptions=[
 'sedes'=>'Ubicaciones de la universidad.', 'facultades'=>'Unidades académicas; el decano referencia un profesor.',
 'telefonos_facultad'=>'Teléfonos multivaluados separados de la facultad.', 'areas'=>'Catálogo de intereses de investigación.',
 'grupos'=>'Grupos adscritos a una facultad y área.', 'profesores'=>'Identidad y pertenencia actual de los docentes.',
 'titulos'=>'Títulos obtenidos por cada profesor.', 'usuarios'=>'Credenciales y rol de acceso de un profesor.',
 'proyectos'=>'Investigaciones, presupuesto total y fechas.', 'participaciones'=>'Períodos de vinculación de profesores a proyectos.',
 'estudiantes'=>'Identidad única de investigadores estudiantes sin cuenta; independiente de sus proyectos.',
 'estudiante_proyecto'=>'Relación muchos a muchos; PK (estudiante_id, proyecto_id). id es una clave alternativa UNIQUE para los formularios.',
 'schema_migrations'=>'Versiones del esquema aplicadas por el instalador o migrador.',
 'auditoria'=>'Registro de actualizaciones y eliminaciones de proyectos, conservado al borrar el proyecto.'
];
$meaning=[
 'id'=>'Identificador interno autoincremental; si hay PK compuesta es una clave alternativa UNIQUE para la interfaz.', 'nombre'=>'Nombre descriptivo.', 'codigo'=>'Código institucional de negocio.',
 'cedula'=>'Identificación del profesor; texto para conservar formato.', 'sede_id'=>'Sede de ubicación.',
 'facultad_id'=>'Facultad propietaria.', 'decano_id'=>'Profesor que ejerce como decano; admite NULL durante la inicialización.',
 'numero'=>'Teléfono de contacto.', 'area_id'=>'Área de interés.', 'lider_id'=>'Profesor líder; admite NULL durante la inicialización.',
 'experiencia'=>'Años de experiencia investigativa no negativos.', 'grupo_id'=>'Grupo al que pertenece el registro.',
 'profesor_id'=>'Profesor relacionado.', 'correo'=>'Correo electrónico único en su tabla.', 'password_hash'=>'Hash generado con password_hash de PHP.',
 'estudiante_id'=>'Identidad del estudiante vinculado.', 'registrado_por'=>'Profesor gestor del registro; su grupo administra la identidad.',
 'version'=>'Identificador de migración aplicada.', 'aplicada_en'=>'Momento de aplicación de la migración.',
 'rol'=>'Perfil DECANO o DOCENTE.', 'presupuesto'=>'Presupuesto total en COP; decimal exacto, no negativo.',
 'inicio'=>'Fecha inicial del proyecto.', 'fin'=>'Fecha final; estrictamente posterior al inicio.',
 'proyecto_id'=>'Proyecto relacionado; en auditoría es referencia histórica sin FK.',
 'incorporacion'=>'Primer día del período de participación.', 'retiro'=>'Último día inclusivo; NULL significa sin retiro explícito.',
 'dedicacion'=>'Porcentaje de dedicación, mayor a 0 y hasta 100.', 'responsable'=>'1 si es profesor responsable; 0 si solo participa.',
 'accion'=>'Operación auditada.', 'detalle'=>'Descripción breve sin contraseñas.', 'fecha'=>'Momento de registro de la auditoría.'
];
$engine=rows('SELECT VERSION() version')[0]['version'];
$text="# Diccionario de datos\n\nGenerado a partir del esquema instalado. Versión del motor inspeccionado: $engine. MySQL y MariaDB pueden mostrar diferencias de representación de tipos.\n\nPK: clave primaria (SHOW COLUMNS usa PRI). UNI: columna con índice único individual. MUL: primer componente de índice no único o compuesto; revisar los índices y FK completos debajo. Todas las tablas usan InnoDB.\n\n";
foreach($descriptions as $table=>$description){
 $text.="## $table\n\n$description\n\n| Columna | Tipo | NULL | Clave | Predeterminado | Descripción |\n|---|---|---|---|---|---|\n";
 foreach(rows("SHOW COLUMNS FROM `$table`") as $c)$text.='| '.$c['Field'].' | '.$c['Type'].' | '.$c['Null'].' | '.$c['Key'].' | '.($c['Default']??'NULL / sin valor'). ' | '.($meaning[$c['Field']]??'Ver definición SQL.')." |\n";
 $text.="\nÍndices (columna y posición):\n\n";
 foreach(rows("SHOW INDEX FROM `$table`") as $i)$text.='- '.$i['Key_name'].': '.$i['Column_name'].' (posición '.$i['Seq_in_index'].', '.($i['Non_unique']?'no único':'único').").\n";
 $fks=rows('SELECT COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL',[$table]);
 if($fks){$text.="\nClaves foráneas (acciones de borrado consultables en 01_schema.sql):\n\n";foreach($fks as $fk)$text.='- '.$fk['COLUMN_NAME'].' → '.$fk['REFERENCED_TABLE_NAME'].'.'.$fk['REFERENCED_COLUMN_NAME'].".\n";}
 $text.="\n";
}
$text.="\n## Restricciones CHECK instaladas\n\n";
foreach(rows("SELECT tc.TABLE_NAME,cc.CHECK_CLAUSE FROM information_schema.TABLE_CONSTRAINTS tc JOIN information_schema.CHECK_CONSTRAINTS cc ON cc.CONSTRAINT_SCHEMA=tc.CONSTRAINT_SCHEMA AND cc.CONSTRAINT_NAME=tc.CONSTRAINT_NAME WHERE tc.CONSTRAINT_SCHEMA=DATABASE() AND tc.CONSTRAINT_TYPE='CHECK' ORDER BY tc.TABLE_NAME,tc.CONSTRAINT_NAME") as $check)$text.='- `'.$check['TABLE_NAME'].'`: `'.$check['CHECK_CLAUSE']."`.\n";
$text.="\n".file_get_contents(__DIR__.'/../docs/objetos-bd.md');

file_put_contents(__DIR__.'/../docs/diccionario.md',$text);
echo "Diccionario generado.\n";
