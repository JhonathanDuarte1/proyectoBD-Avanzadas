-- Datos ficticios para demostración académica.
INSERT INTO sedes(nombre) VALUES ('Sede Tecnológica'),('Sede Central');
INSERT INTO facultades(nombre,sede_id) VALUES ('Facultad Tecnológica',1),('Facultad de Ingeniería',2);
INSERT INTO telefonos_facultad(facultad_id,numero) VALUES(1,'6015550101'),(2,'6015550102');
INSERT INTO areas(nombre) VALUES('Ingeniería de software'),('Inteligencia artificial'),('Sistemas de información');
INSERT INTO grupos(codigo,nombre,facultad_id,area_id) VALUES('G-SOFT','Software y sociedad',1,1),('G-IA','Inteligencia aplicada',2,2);
INSERT INTO profesores(cedula,nombre,experiencia,grupo_id) VALUES('10000001','Laura Martínez',12,1),('10000002','Carlos Ramírez',6,1),('10000003','Ana Torres',10,2);
UPDATE grupos SET lider_id=1 WHERE id=1;
UPDATE grupos SET lider_id=3 WHERE id=2;
UPDATE facultades SET decano_id=1 WHERE id=1;
UPDATE facultades SET decano_id=3 WHERE id=2;
INSERT INTO titulos(profesor_id,nombre) VALUES(1,'Doctorado en Ingeniería'),(2,'Maestría en Sistemas'),(3,'Doctorado en Computación');
INSERT INTO proyectos(codigo,nombre,presupuesto,inicio,fin,grupo_id) VALUES
 ('P-001','Observatorio de investigación',45000000,'2026-01-01','2026-12-31',1),
 ('P-002','Archivo digital universitario',18000000,'2025-01-01','2025-12-31',1),
 ('P-003','Analítica para la permanencia',62000000,'2026-06-01','2027-06-30',2);
INSERT INTO participaciones(proyecto_id,profesor_id,incorporacion,retiro,dedicacion,responsable) VALUES
 (1,1,'2026-01-01',NULL,40,1),(1,2,'2026-02-01',NULL,25,0),
 (2,1,'2025-01-01','2025-12-31',20,1),(3,3,'2026-06-01',NULL,50,1);
INSERT INTO estudiantes(codigo,nombre,correo,registrado_por) VALUES('20260001','Sofía Pérez','sofia@example.test',2);
-- El mismo estudiante investiga en varios proyectos, sin duplicar su identidad.
INSERT INTO estudiante_proyecto(estudiante_id,proyecto_id) VALUES(1,1),(1,2);
