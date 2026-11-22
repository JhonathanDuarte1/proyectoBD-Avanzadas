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
 if($entity==='estudiante_proyecto')return (bool)rows('SELECT id FROM participaciones WHERE proyecto_id=? AND profesor_id=? AND responsable=1',[$record['proyecto_id'],$u['profesor_id']]);
 return (bool)rows('SELECT id FROM proyectos WHERE id=? AND grupo_id=?',[$record['proyecto_id'],$u['grupo_id']]);
}
