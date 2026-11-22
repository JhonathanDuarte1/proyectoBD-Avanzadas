-- Consultas de sustentación: parámetros de ejemplo; modificar en el gestor.
SET @profesor=1; SET @facultad=1;
SET @desde='2026-01-01'; SET @hasta='2026-12-31';
-- 1. Proyectos de un profesor, sin duplicar proyectos cuando hay reingresos.
SELECT p.codigo,p.nombre,p.estado FROM v_proyectos p
WHERE EXISTS(SELECT 1 FROM participaciones a WHERE a.proyecto_id=p.id AND a.profesor_id=@profesor);
-- 2. Activos y terminados de una facultad; también diferencia programados.
SELECT estado,COUNT(*) proyectos,SUM(presupuesto) presupuesto
FROM v_proyectos WHERE facultad_id=@facultad GROUP BY estado;
-- 3. Profesores de un grupo y sus títulos (atributo multivaluado normalizado).
SELECT p.cedula,p.nombre,GROUP_CONCAT(t.nombre ORDER BY t.nombre SEPARATOR ' / ') titulos
FROM profesores p LEFT JOIN titulos t ON t.profesor_id=p.id WHERE p.grupo_id=1
GROUP BY p.id,p.cedula,p.nombre;
-- 4. Presupuesto por intersección de intervalos: no unir participaciones y duplicar montos.
CALL sp_presupuesto(@facultad,@desde,@hasta);
-- 5. Cartera priorizada: CTE + agregaciones + función de ventana, usada por la aplicación.
SELECT * FROM v_cartera_proyectos WHERE facultad_id=@facultad ORDER BY prioridad_presupuesto,codigo;
-- 6. Eficiencia del acceso por facultad: la FK grupos.facultad_id tiene índice.
EXPLAIN SELECT * FROM v_proyectos WHERE facultad_id=@facultad;
-- DCL: evidencia del perfil efectivo de la conexión actual.
SELECT CURRENT_USER(),CURRENT_ROLE(); SHOW GRANTS;
