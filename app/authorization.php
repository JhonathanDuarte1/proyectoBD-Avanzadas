<?php
function options(string $sql,array $u): array {
 $args=[];foreach(['facultad'=>'facultad_id','grupo'=>'grupo_id','profesor'=>'profesor_id'] as $param=>$key)if(strpos($sql,':'.$param)!==false)$args[$param]=$u[$key];return rows($sql,$args);
}
function writable(string $entity,array $u): bool {
 if(in_array($entity,['profesores','titulos'],true))return $u['rol']==='DECANO';
 return in_array($entity,['proyectos','grupos','participaciones','estudiantes','estudiante_proyecto'],true)&&$u['rol']==='DOCENTE';
}
function can_edit(string $entity,array $record,array $u): bool {
 if(!writable($entity,$u))return false;
 if($entity==='grupos')return (int)$record['id']===(int)$u['grupo_id'];
 if($entity==='profesores')return (bool)rows('SELECT id FROM grupos WHERE id=? AND facultad_id=?',[$record['grupo_id'],$u['facultad_id']]);
 if($entity==='titulos')return (bool)rows('SELECT id FROM v_profesores WHERE id=? AND facultad_id=?',[$record['profesor_id'],$u['facultad_id']]);
 if($entity==='proyectos')return (int)$record['grupo_id']===(int)$u['grupo_id'];
 if($entity==='estudiantes')return (bool)rows('SELECT id FROM profesores WHERE id=? AND grupo_id=?',[$record['registrado_por'],$u['grupo_id']]);
 if($entity==='estudiante_proyecto')return (bool)rows('SELECT a.id FROM participaciones a JOIN v_proyectos p ON p.id=a.proyecto_id JOIN estudiantes e ON e.id=? JOIN v_profesores r ON r.id=e.registrado_por WHERE a.proyecto_id=? AND a.profesor_id=? AND a.responsable=1 AND p.facultad_id=? AND r.facultad_id=p.facultad_id',[$record['estudiante_id'],$record['proyecto_id'],$u['profesor_id'],$u['facultad_id']]);
 return (bool)rows('SELECT p.id FROM proyectos p JOIN v_profesores pr ON pr.id=? WHERE p.id=? AND p.grupo_id=? AND pr.facultad_id=?',[$record['profesor_id'],$record['proyecto_id'],$u['grupo_id'],$u['facultad_id']]);
}
