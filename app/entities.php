<?php
// Etiqueta, tipo y consulta de opciones (cuando corresponda).
return [
 'proyectos'=>['titulo'=>'Proyectos','columnas'=>['codigo','nombre','presupuesto','inicio','fin','grupo','facultad','estado'],'campos'=>[
 'codigo'=>['Código','text'],'nombre'=>['Nombre','text'],'presupuesto'=>['Presupuesto (COP)','number'],
 'inicio'=>['Fecha de inicio','date'],'fin'=>['Fecha de terminación','date'],
 'grupo_id'=>['Grupo','select','SELECT id,nombre FROM ('.consulta_entidad('grupos').') datos_grupos WHERE id=:grupo']],
 'creacion'=>['dedicacion_inicial'=>['Tu dedicación como responsable inicial (%)','number']],
 'lista'=>'SELECT p.id,p.codigo,p.nombre,p.presupuesto,p.inicio,p.fin,p.grupo,p.facultad,p.estado FROM v_proyectos p WHERE p.facultad_id=:facultad', 'scope'=>'grupo'],
 'grupos'=>['titulo'=>'Grupos de investigación','columnas'=>['codigo','nombre','facultad','area','lider'],'campos'=>[
 'codigo'=>['Código','text'],'nombre'=>['Nombre','text'],
 'area_interes'=>['Área de interés','text'],
 'lider_id'=>['Líder','select','SELECT id,nombre FROM v_profesores WHERE facultad_id=:facultad']],
 'lista'=>'SELECT g.id,g.codigo,g.nombre,f.nombre facultad,g.area_interes area,p.nombre lider FROM ('.consulta_entidad('grupos').') g JOIN Facultad f ON f.id_facultad=g.facultad_id LEFT JOIN v_profesores p ON p.id=g.lider_id AND p.grupo_id IN(SELECT id FROM ('.consulta_entidad('grupos').') datos_grupos WHERE facultad_id=g.facultad_id) WHERE g.facultad_id=:facultad','scope'=>'facultad'],
 'profesores'=>['titulo'=>'Profesores','campos'=>[
 'cedula'=>['Cédula','text'],'nombre'=>['Nombre completo','text'],'experiencia'=>['Años de experiencia','number'],
 'grupo_id'=>['Grupo','select','SELECT id,nombre FROM ('.consulta_entidad('grupos').') datos_grupos WHERE facultad_id=:facultad']],
 'creacion'=>['titulo_inicial'=>['Primer título académico','text']],
 'lista'=>'SELECT id,cedula,nombre,experiencia,grupo FROM v_profesores WHERE facultad_id=:facultad','scope'=>'profesor'],
 'titulos'=>['titulo'=>'Títulos académicos','campos'=>[
 'profesor_id'=>['Profesor','select','SELECT id,nombre FROM v_profesores WHERE facultad_id=:facultad'],'nombre'=>['Título obtenido','text']],
 'lista'=>'SELECT t.id,p.nombre profesor,t.titulo titulo FROM profesor_titulo t JOIN v_profesores p ON p.cedula=t.Cedula WHERE p.facultad_id=:facultad','scope'=>'titulo'],
 'participaciones'=>['titulo'=>'Participación de profesores','campos'=>[
 'proyecto_id'=>['Proyecto','select','SELECT id,nombre FROM v_proyectos WHERE grupo_id=:grupo'],
 'profesor_id'=>['Profesor','select','SELECT id,nombre FROM v_profesores WHERE facultad_id=:facultad'],
 'incorporacion'=>['Incorporación','date'],'retiro'=>['Retiro (opcional)','date',null,true],
 'dedicacion'=>['Dedicación (%)','number'],'responsable'=>['¿Es responsable?','select',"SELECT 1 id,'Sí' nombre UNION ALL SELECT 0,'No'"]],
 'lista'=>'SELECT a.id,a.proyecto,a.profesor,a.incorporacion,a.retiro,a.dedicacion,a.responsable,a.vigente FROM v_participaciones a JOIN v_proyectos p ON p.id=a.proyecto_id JOIN v_profesores pr ON pr.id=a.profesor_id WHERE p.facultad_id=:facultad AND pr.facultad_id=p.facultad_id','scope'=>'proyecto'],
 'estudiantes'=>['titulo'=>'Estudiantes investigadores','campos'=>[
 'codigo'=>['Código estudiantil','text'],'nombre'=>['Nombre completo','text'],'correo'=>['Correo','email']],
 'lista'=>'SELECT e.id,e.codigo,e.nombre,e.correo FROM ('.consulta_entidad('estudiantes').') e JOIN v_profesores r ON r.id=e.registrado_por WHERE r.facultad_id=:facultad','scope'=>'estudiante'],
 'estudiante_proyecto'=>['titulo'=>'Estudiantes por proyecto','campos'=>[
 'estudiante_id'=>['Estudiante registrado','select',"SELECT e.id,CONCAT(e.codigo,' · ',e.nombre) nombre FROM (".consulta_entidad('estudiantes').") e JOIN v_profesores r ON r.id=e.registrado_por WHERE r.facultad_id=:facultad ORDER BY e.nombre"],
 'proyecto_id'=>['Proyecto del que eres responsable','select','SELECT p.id,p.nombre FROM v_proyectos p JOIN v_participaciones a ON a.proyecto_id=p.id WHERE p.facultad_id=:facultad AND a.profesor_id=:profesor AND a.responsable=1 GROUP BY p.id,p.nombre'],
 'fecha_incorporacion'=>['Fecha de incorporación','date'],'fecha_retiro'=>['Fecha de retiro (opcional)','date',null,true]],
 'lista'=>"SELECT ep.id,e.codigo,e.nombre estudiante,p.codigo codigo_proyecto,p.nombre proyecto,ep.fecha_incorporacion,ep.fecha_retiro FROM (".consulta_entidad('estudiante_proyecto').") ep JOIN (".consulta_entidad('estudiantes').") e ON e.id=ep.estudiante_id JOIN v_profesores r ON r.id=e.registrado_por JOIN v_proyectos p ON p.id=ep.proyecto_id WHERE p.facultad_id=:facultad AND r.facultad_id=p.facultad_id",'scope'=>'proyecto'],
 'facultades'=>['titulo'=>'Facultades','campos'=>[], 'lista'=>'SELECT nombre,sede,decano,telefonos FROM ('.consulta_entidad('facultades').') f WHERE id=:facultad','scope'=>'lectura'],
];
