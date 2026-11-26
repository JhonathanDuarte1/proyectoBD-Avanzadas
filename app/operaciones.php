<?php
require_once __DIR__ . '/authorization.php';
function contexto_usuario(int $actor): array
{
    $u = datos_usuario($actor);
    if (!$u) throw new RuntimeException('Cuenta sin facultad o decanato autorizado.');
    return $u;
}
// Solo se usan nombres constantes del código; los valores se envían como parámetros.
function escribir_fila(string $tabla, int $id, array $datos): int
{
    $columnas = array_keys($datos);
    if ($id) {
        run('UPDATE `' . $tabla . '` SET ' . implode(',', array_map(fn($c) => '`' . $c . '`=?', $columnas)) . ' WHERE id=?', array_merge(array_values($datos), [$id]));
        return $id;
    }
    run('INSERT INTO `' . $tabla . '` (`' . implode('`,`', $columnas) . '`) VALUES(' . implode(',', array_fill(0, count($columnas), '?')) . ')', array_values($datos));
    return (int)db()->lastInsertId();
}
function guardar_registro(int $actor, string $tabla, int $id, array $datos): int
{
    $u = contexto_usuario($actor);
    $entidades = require __DIR__ . '/entities.php';
    if (!isset($entidades[$tabla]) || !writable($tabla, $u)) throw new RuntimeException('No tienes permiso para esta operación.');
    db()->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
    db()->beginTransaction();
    try {
        // El bloqueo de la facultad serializa nombres de grupo y cambios de pertenencia del sitio.
        rows('SELECT id FROM Profesor WHERE cedula=(SELECT cedula FROM Es_decano WHERE id_facultad=?) FOR UPDATE', [$u['facultad_id']]);
        if ($id) rows('SELECT id FROM `' . tabla_entidad($tabla) . '` WHERE id=? FOR UPDATE', [$id]);
        $antes = $id ? registro_entidad($tabla, $id) : null;
        if ($id && (!$antes || !can_edit($tabla, $antes, $u))) throw new RuntimeException('Registro fuera de tu ámbito de edición.');
        $d = [];
        foreach ($entidades[$tabla]['campos'] as $col => $campo) {
            $valor = $datos[$col] ?? null;
            if ($valor === null && !($campo[3] ?? false)) throw new RuntimeException('Completa: ' . $campo[0]);
            $d[$col] = $valor;
        }
        // Se repiten las validaciones dentro de la transacción para cubrir cambios simultáneos.
        validar_registro($tabla, $id, $d, $u);
        if ($tabla === 'grupos') {
            if (!rows('SELECT id FROM v_profesores WHERE id=? AND facultad_id=?', [$d['lider_id'], $u['facultad_id']])) throw new RuntimeException('El líder debe ser de tu facultad.');
            $id = escribir_fila('Grupo_investigacion', $id, ['codigo_grupo' => $d['codigo'], 'nombre' => $d['nombre'], 'area_interes' => $d['area_interes']]);
            if (!$antes) run('INSERT INTO Tiene_grupo(codigo_grupo,id_facultad) VALUES(?,?)', [$d['codigo'], $u['facultad_id']]);
            run('INSERT INTO Lidera(cedula,codigo_grupo) VALUES(?,?) ON DUPLICATE KEY UPDATE cedula=VALUES(cedula)', [clave_entidad('profesores', (int)$d['lider_id']), $d['codigo']]);
        } elseif ($tabla === 'profesores') {
            if (!rows('SELECT id FROM ('.consulta_entidad('grupos').') datos_grupos WHERE id=? AND facultad_id=?', [$d['grupo_id'], $u['facultad_id']])) throw new RuntimeException('Grupo de otra facultad.');
            if (!$antes && trim($datos['titulo_inicial'] ?? '') === '') throw new RuntimeException('El profesor necesita su primer título.');
            $id = escribir_fila('Profesor', $id, ['cedula' => $d['cedula'], 'nombre' => $d['nombre'], 'tiempo_Experiencia' => $d['experiencia']]);
            run('INSERT INTO Pertenece(cedula,codigo_grupo) VALUES(?,?) ON DUPLICATE KEY UPDATE codigo_grupo=VALUES(codigo_grupo)', [$d['cedula'], clave_entidad('grupos', (int)$d['grupo_id'])]);
            if (!$antes) run('INSERT INTO profesor_titulo(Cedula,titulo) VALUES(?,?)', [$d['cedula'], trim($datos['titulo_inicial'])]);
        } elseif ($tabla === 'titulos') {
            if (!rows('SELECT id FROM v_profesores WHERE id=? AND facultad_id=?', [$d['profesor_id'], $u['facultad_id']])) throw new RuntimeException('Profesor de otra facultad.');
            if ($antes && (int)$antes['profesor_id'] !== (int)$d['profesor_id'] && (int)rows('SELECT COUNT(*) n FROM profesor_titulo WHERE Cedula=?', [clave_entidad('profesores', (int)$antes['profesor_id'])])[0]['n'] <= 1) throw new RuntimeException('No se puede trasladar el último título.');
            $id = escribir_fila('profesor_titulo', $id, ['Cedula' => clave_entidad('profesores', (int)$d['profesor_id']), 'titulo' => $d['nombre']]);
        } elseif ($tabla === 'proyectos') {
            if ((int)$d['grupo_id'] !== (int)$u['grupo_id']) throw new RuntimeException('Grupo no autorizado.');
            if (!$antes && (!isset($datos['dedicacion_inicial']) || $datos['dedicacion_inicial'] <= 0 || $datos['dedicacion_inicial'] > 100)) throw new RuntimeException('Indica una dedicación inicial entre 0 y 100.');
            $id = escribir_fila('Proyecto_investigacion', $id, ['codigo_proyecto' => $d['codigo'], 'nombre' => $d['nombre'], 'presupuesto' => $d['presupuesto'], 'fecha_inicio' => $d['inicio'], 'fecha_fin' => $d['fin']]);
            run('INSERT INTO Desarrolla(codigo_proyecto,codigo_grupo) VALUES(?,?) ON DUPLICATE KEY UPDATE codigo_grupo=VALUES(codigo_grupo)', [$d['codigo'], clave_entidad('grupos', (int)$d['grupo_id'])]);
            if (!$antes) run('INSERT INTO Participa(cedula,codigo_proyecto,fecha_incorporacion,dedicacion,es_responsable) VALUES(?,?,?,?,1)', [$u['cedula'], $d['codigo'], $d['inicio'], $datos['dedicacion_inicial']]);
        } elseif ($tabla === 'participaciones' || $tabla === 'estudiante_proyecto') {
            $origen = (int)($antes['proyecto_id'] ?? $d['proyecto_id']);
            $destino = (int)$d['proyecto_id'];
            rows('SELECT id FROM Proyecto_investigacion WHERE id IN (?,?) ORDER BY id FOR UPDATE', [$origen, $destino]);
            $codigo = clave_entidad('proyectos', $destino);
            if ($tabla === 'participaciones') {
                if (!rows('SELECT id FROM v_proyectos WHERE id=? AND grupo_id=?', [$destino, $u['grupo_id']])) throw new RuntimeException('Proyecto no autorizado.');
                if (!rows('SELECT id FROM v_profesores WHERE id=? AND facultad_id=?', [$d['profesor_id'], $u['facultad_id']])) throw new RuntimeException('Profesor de otra facultad.');
                $id = escribir_fila('Participa', $id, ['cedula' => clave_entidad('profesores', (int)$d['profesor_id']), 'codigo_proyecto' => $codigo, 'fecha_incorporacion' => $d['incorporacion'], 'fecha_retiro' => $d['retiro'], 'dedicacion' => $d['dedicacion'], 'es_responsable' => $d['responsable']]);
                foreach (array_unique([$origen, $destino]) as $p) {
                    if (!rows('SELECT id FROM v_participaciones WHERE proyecto_id=? AND responsable=1', [$p])) throw new RuntimeException('Cada proyecto debe conservar un profesor responsable.');
                }
            } else {
                rows('SELECT id FROM Estudiante WHERE id IN (?,?) ORDER BY id FOR UPDATE', [$antes['estudiante_id'] ?? $d['estudiante_id'], $d['estudiante_id']]);
                if (!rows('SELECT id FROM ('.consulta_entidad('estudiantes').') datos_estudiantes WHERE id=? AND facultad_id=?', [$d['estudiante_id'], $u['facultad_id']])) throw new RuntimeException('Estudiante de otra facultad.');
                foreach (array_unique([$origen, $destino]) as $p) {
                    if (!rows('SELECT a.id FROM v_participaciones a JOIN v_proyectos p ON p.id=a.proyecto_id WHERE a.proyecto_id=? AND a.profesor_id=? AND a.responsable=1 AND p.facultad_id=?', [$p, $u['profesor_id'], $u['facultad_id']])) throw new RuntimeException('Solo el responsable gestiona estudiantes de su facultad.');
                }
                $id = escribir_fila('Investiga', $id, ['codigo_estudiante' => clave_entidad('estudiantes', (int)$d['estudiante_id']), 'codigo_proyecto' => $codigo, 'fecha_inicio' => $d['fecha_incorporacion'], 'fecha_retiro' => $d['fecha_retiro']]);
            }
        } elseif ($tabla === 'estudiantes') {
            $fila = ['codigo_estudiante' => $d['codigo'], 'nombre' => $d['nombre'], 'correo' => $d['correo']];
            if (!$antes) $fila['registrado_por'] = $u['cedula'];
            $id = escribir_fila('Estudiante', $id, $fila);
        }
        db()->commit();
        return $id;
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }
}
function eliminar_registro(int $actor, string $tabla, int $id): void
{
    $u = contexto_usuario($actor);
    if (!in_array($tabla, ['proyectos', 'estudiante_proyecto'], true) || $u['rol'] !== 'DOCENTE') throw new RuntimeException('Eliminación no autorizada.');
    db()->beginTransaction();
    try {
        rows('SELECT id FROM Profesor WHERE cedula=(SELECT cedula FROM Es_decano WHERE id_facultad=?) FOR UPDATE', [$u['facultad_id']]);
        rows('SELECT id FROM `' . tabla_entidad($tabla) . '` WHERE id=? FOR UPDATE', [$id]);
        $r = registro_entidad($tabla, $id);
        if (!$r || !can_edit($tabla, $r, $u)) throw new RuntimeException('Registro fuera de tu ámbito de edición.');
        run('DELETE FROM `' . tabla_entidad($tabla) . '` WHERE id=?', [$id]);
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }
}
function actualizar_cuenta(int $actor, string $cedula, string $nombre, string $correo, string $hash): void
{
    $u = contexto_usuario($actor);
    db()->beginTransaction();
    try {
        run('UPDATE Profesor SET cedula=?,nombre=? WHERE id=?', [$cedula, $nombre, $u['profesor_id']]);
        run('UPDATE Usuario SET correo=?,password=? WHERE id_usuario=?', [$correo, $hash, $actor]);
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }
}
function crear_cuenta(int $actor, int $profesor, string $correo, string $hash): void
{
    $u = contexto_usuario($actor);
    if ($u['rol'] !== 'DECANO' || !rows('SELECT id FROM v_profesores WHERE id=? AND facultad_id=?', [$profesor, $u['facultad_id']])) throw new RuntimeException('Solo el decano crea cuentas de su facultad.');
    db()->beginTransaction();
    try {
        run('INSERT INTO Usuario(correo,password,rol) VALUES(?,?,\'DOCENTE\')', [$correo, $hash]);
        $id = (int)db()->lastInsertId();
        run('INSERT INTO Tiene(id_usuario,cedula) VALUES(?,?)', [$id, clave_entidad('profesores', $profesor)]);
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }
}
function importar_estudiantes(int $actor, array $estudiantes): void
{
    $u = contexto_usuario($actor);
    if ($u['rol'] !== 'DOCENTE' || !$estudiantes) throw new RuntimeException('Lote inválido.');
    db()->beginTransaction();
    try {
        foreach ($estudiantes as $e) {
            foreach (['codigo', 'nombre', 'correo'] as $c) if (!is_string($e[$c] ?? null) || trim($e[$c]) === '') throw new RuntimeException('Completa código, nombre y correo.');
            run('INSERT INTO Estudiante(codigo_estudiante,nombre,correo,registrado_por) VALUES(?,?,?,?)', [trim($e['codigo']), trim($e['nombre']), trim($e['correo']), $u['cedula']]);
        }
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }
}
function consultar_presupuesto(int $actor, string $desde, string $hasta): array
{
    $u = contexto_usuario($actor);
    if ($u['rol'] !== 'DECANO') throw new RuntimeException('Consulta reservada al decano.');
    return call_procedure('sp_presupuesto', [$u['facultad_id'], $desde, $hasta]);
}
