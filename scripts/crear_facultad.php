<?php
// Alta administrativa mediante SQL normal y una transacción, sin procedimiento almacenado.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require __DIR__ . '/admin.php';
$args = getopt('', ['file:']);
try {
    $file = $args['file'] ?? '';
    if (!is_file($file)) throw new RuntimeException('Uso: php scripts/crear_facultad.php --file=facultad.json');
    $d = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    $limites = ['nombre' => 150, 'sede' => 100, 'telefono' => 30, 'codigo_grupo' => 30, 'nombre_grupo' => 150, 'area_interes' => 120, 'cedula' => 20, 'nombre_profesor' => 150, 'titulo' => 180];
    foreach ($limites as $campo => $max) {
        if (!is_string($d[$campo] ?? null) || trim($d[$campo]) === '' || mb_strlen(trim($d[$campo])) > $max) throw new RuntimeException('Completa ' . $campo . ' con máximo ' . $max . ' caracteres.');
        $d[$campo] = trim($d[$campo]);
    }
    db()->beginTransaction();
    run('INSERT INTO Facultad(nombre,sede) VALUES(?,?)', [$d['nombre'], $d['sede']]);
    $fac = (int)db()->lastInsertId();
    run('INSERT INTO Facultad_telefono(id_facultad,telefono) VALUES(?,?)', [$fac, $d['telefono']]);
    run('INSERT INTO Grupo_investigacion(codigo_grupo,nombre,area_interes) VALUES(?,?,?)', [$d['codigo_grupo'], $d['nombre_grupo'], $d['area_interes']]);
    run('INSERT INTO Tiene_grupo(codigo_grupo,id_facultad) VALUES(?,?)', [$d['codigo_grupo'], $fac]);
    run('INSERT INTO Profesor(cedula,nombre) VALUES(?,?)', [$d['cedula'], $d['nombre_profesor']]);
    run('INSERT INTO Pertenece(cedula,codigo_grupo) VALUES(?,?)', [$d['cedula'], $d['codigo_grupo']]);
    run('INSERT INTO Lidera(cedula,codigo_grupo) VALUES(?,?)', [$d['cedula'], $d['codigo_grupo']]);
    run('INSERT INTO profesor_titulo(Cedula,titulo) VALUES(?,?)', [$d['cedula'], $d['titulo']]);
    run('INSERT INTO Es_decano(cedula,id_facultad) VALUES(?,?)', [$d['cedula'], $fac]);
    db()->commit();
    echo "Facultad y conjunto inicial creados. id_facultad: $fac. La cuenta inicial del decano se prepara por separado.\n";
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
