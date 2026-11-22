-- Aplicar mediante scripts/migrate.php sobre la versión de octubre, con respaldo previo.
-- __FK_ESTUDIANTE_PROYECTO__ se resuelve desde information_schema, no se adivina el nombre.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE telefonos_facultad ADD UNIQUE KEY uq_telefono_id(id),
 DROP PRIMARY KEY, ADD PRIMARY KEY(facultad_id,numero);
ALTER TABLE titulos ADD UNIQUE KEY uq_titulo_id(id),
 DROP PRIMARY KEY, ADD PRIMARY KEY(profesor_id,nombre);
ALTER TABLE estudiantes ADD registrado_por INT NULL;
UPDATE estudiantes e JOIN proyectos p ON p.id=e.proyecto_id JOIN grupos g ON g.id=p.grupo_id
 SET e.registrado_por=COALESCE(g.lider_id,(SELECT MIN(pr.id) FROM profesores pr WHERE pr.grupo_id=g.id));
ALTER TABLE estudiantes MODIFY registrado_por INT NOT NULL,
 ADD FOREIGN KEY(registrado_por) REFERENCES profesores(id);
CREATE TABLE estudiante_proyecto (
 id INT AUTO_INCREMENT UNIQUE, estudiante_id INT NOT NULL, proyecto_id INT NOT NULL,
 PRIMARY KEY(estudiante_id,proyecto_id),
 FOREIGN KEY(estudiante_id) REFERENCES estudiantes(id),
 FOREIGN KEY(proyecto_id) REFERENCES proyectos(id)
) ENGINE=InnoDB;
INSERT INTO estudiante_proyecto(estudiante_id,proyecto_id) SELECT id,proyecto_id FROM estudiantes;
ALTER TABLE estudiantes DROP FOREIGN KEY __FK_ESTUDIANTE_PROYECTO__, DROP COLUMN proyecto_id;
CREATE OR REPLACE VIEW v_proyectos AS
 SELECT p.*,g.nombre grupo,f.nombre facultad,f.id facultad_id,
 CASE WHEN CURRENT_DATE<p.inicio THEN 'PROGRAMADO' WHEN CURRENT_DATE>p.fin THEN 'TERMINADO' ELSE 'ACTIVO' END COLLATE utf8mb4_unicode_ci estado
 FROM proyectos p JOIN grupos g ON g.id=p.grupo_id JOIN facultades f ON f.id=g.facultad_id;
CREATE TABLE schema_migrations (
 version VARCHAR(64) PRIMARY KEY, aplicada_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
INSERT INTO schema_migrations(version) VALUES('20261117_correcciones_avance');
