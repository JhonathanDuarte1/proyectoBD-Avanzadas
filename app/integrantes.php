<?php
// Consulta de lectura dentro de Grupos. No modifica pertenencias ni crea otro módulo.
$grupoSolicitado=filter_var($_GET['grupo']??'',FILTER_VALIDATE_INT);
$grupoConsultado=$grupoSolicitado?rows('SELECT id,nombre,lider_id FROM grupos WHERE id=? AND facultad_id=?',[$grupoSolicitado,$u['facultad_id']])[0]??null:null;
?>
<section class="panel" id="integrantes">
<?php if(!$grupoConsultado):?>
<p class="error">Selecciona un grupo de tu facultad para consultar sus integrantes.</p>
<?php else:
$profesoresGrupo=rows("SELECT p.cedula,p.nombre,
 CASE WHEN p.grupo_id=g.id THEN 'Pertenece al grupo' WHEN p.id=g.lider_id THEN 'Lidera el grupo' ELSE 'Participa en sus proyectos' END vinculacion,
 CASE WHEN p.id=g.lider_id THEN 'Sí' ELSE 'No' END lider
 FROM profesores p JOIN grupos propio ON propio.id=p.grupo_id
 JOIN grupos g ON g.id=?
 WHERE g.facultad_id=? AND propio.facultad_id=g.facultad_id
 AND (p.grupo_id=g.id OR p.id=g.lider_id OR EXISTS(
  SELECT 1 FROM participaciones a JOIN proyectos pr ON pr.id=a.proyecto_id
  WHERE a.profesor_id=p.id AND pr.grupo_id=g.id)) ORDER BY p.nombre",[$grupoConsultado['id'],$u['facultad_id']]);
$estudiantesGrupo=rows("SELECT e.codigo,e.nombre,
 GROUP_CONCAT(DISTINCT CONCAT(p.codigo,' · ',p.nombre) ORDER BY p.codigo SEPARATOR '; ') proyectos
 FROM estudiantes e JOIN estudiante_proyecto ep ON ep.estudiante_id=e.id
 JOIN proyectos p ON p.id=ep.proyecto_id JOIN grupos g ON g.id=p.grupo_id
 JOIN v_profesores r ON r.id=e.registrado_por
 WHERE g.id=? AND g.facultad_id=? AND r.facultad_id=g.facultad_id
 GROUP BY e.id,e.codigo,e.nombre ORDER BY e.nombre",[$grupoConsultado['id'],$u['facultad_id']]);
?>
<h2>Integrantes de <?=h($grupoConsultado['nombre'])?></h2>
<h3>Profesores</h3>
<p>Incluye al líder, los profesores que pertenecen al grupo y los profesores de la facultad con participaciones registradas en sus proyectos, aunque su grupo de pertenencia sea otro.</p>
<?php if($profesoresGrupo):?><div class="table-wrap"><table><thead><tr><th>Cédula</th><th>Nombre</th><th>Vinculación</th><th>Líder</th></tr></thead><tbody>
<?php foreach($profesoresGrupo as $profesor):?><tr><td><?=h($profesor['cedula'])?></td><td><?=h($profesor['nombre'])?></td><td><?=h($profesor['vinculacion'])?></td><td><?=h($profesor['lider'])?></td></tr><?php endforeach;?>
</tbody></table></div><?php else:?><p>No hay profesores vinculados a este grupo.</p><?php endif;?>
<h3>Estudiantes en proyectos del grupo</h3>
<p>Su vínculo con el grupo se obtiene a través de los proyectos. Cada estudiante aparece una sola vez, con los proyectos en los que participa.</p>
<?php if($estudiantesGrupo):?><div class="table-wrap"><table><thead><tr><th>Código</th><th>Nombre</th><th>Proyectos</th></tr></thead><tbody>
<?php foreach($estudiantesGrupo as $estudiante):?><tr><td><?=h($estudiante['codigo'])?></td><td><?=h($estudiante['nombre'])?></td><td><?=h($estudiante['proyectos'])?></td></tr><?php endforeach;?>
</tbody></table></div><?php else:?><p>No hay estudiantes vinculados a proyectos de este grupo.</p><?php endif;?>
<?php endif;?>
</section>
