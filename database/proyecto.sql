-- INVESTIGA U - BASE DE DATOS DEL PROYECTO
-- Archivo unico: estructura, datos ficticios, vistas, procedimientos y triggers.
-- Importar en MySQL sobre un destino NUEVO. No ejecutar sobre la base compartida actual.
-- Si investiga_u ya existe, cambiar el nombre en CREATE DATABASE y USE.
-- La base web existente puede conservar su nombre investiga_u_migrado.
-- PK identifica filas; FK relaciona tablas; UNIQUE evita repetidos; CHECK valida una fila.
-- InnoDB permite transacciones. Las operaciones de formularios se realizan desde PHP/PDO.
-- Los minimos de titulo, telefono y responsable se completan al crear cada conjunto.
-- El administrador debe respetar las reglas al editar directamente en phpMyAdmin.
CREATE DATABASE investiga_u CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE investiga_u;

-- 1. TABLAS Y RELACIONES
-- MySQL 8.0.16+; ejecutar sobre una base vacía seleccionada previamente.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
-- Catálogo de ubicaciones. La regla 2 exige que cada facultad tenga una sede existente. Columnas: id:
-- identificador interno. nombre: sede, obligatorio y único.
CREATE TABLE sedes (
 id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(100) NOT NULL UNIQUE,
 CONSTRAINT ck_sedes_nombre_vacio CHECK(CHAR_LENGTH(TRIM(nombre))>0)
) ENGINE=InnoDB;
-- Reglas 1, 2 y 4: nombre único, sede obligatoria y referencia al profesor decano. decano_id permite
-- NULL solo al iniciar la referencia circular; el alta administrativa completa el conjunto en una
-- transacción. Columnas: id: identificador. nombre: nombre institucional único. sede_id: FK de
-- ubicación. decano_id: FK al profesor que ejerce como decano.
CREATE TABLE facultades (
 id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(150) NOT NULL UNIQUE,
 sede_id INT NOT NULL, decano_id INT NULL,
 CONSTRAINT uq_facultades_decano UNIQUE(decano_id),
 FOREIGN KEY(sede_id) REFERENCES sedes(id),
 CONSTRAINT ck_facultades_nombre_vacio CHECK(CHAR_LENGTH(TRIM(nombre))>0)
) ENGINE=InnoDB;
-- Regla 3 y corrección docente: atributo multivaluado. PK(facultad_id,numero) evita repetir el mismo
-- teléfono para la misma facultad. id es una clave alternativa para la interfaz, no sustituye la PK
-- compuesta. Columnas: id: identificador auxiliar UNIQUE. facultad_id: propietario y parte de la PK.
-- numero: texto para conservar prefijos y ceros; completa la PK.
CREATE TABLE telefonos_facultad (
 id INT AUTO_INCREMENT UNIQUE, facultad_id INT NOT NULL,
 numero VARCHAR(30) NOT NULL, PRIMARY KEY(facultad_id,numero),
 FOREIGN KEY(facultad_id) REFERENCES facultades(id),
 CONSTRAINT ck_telefonos_facultad_numero_vacio CHECK(CHAR_LENGTH(TRIM(numero))>0)
) ENGINE=InnoDB;
-- Catálogo de áreas de interés; evita repetir nombres de área en cada grupo (regla 8). Columnas: id:
-- identificador. nombre: descripción única del área.
CREATE TABLE areas (
 id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(120) NOT NULL UNIQUE,
 CONSTRAINT ck_areas_nombre_vacio CHECK(CHAR_LENGTH(TRIM(nombre))>0)
) ENGINE=InnoDB;
-- Reglas 5 a 9: facultad y área existentes, código institucional único y nombre único DENTRO de la
-- facultad. lider_id se completa después de crear el primer profesor. Columnas: id: identificador.
-- codigo: código único institucional. nombre: se combina con facultad_id para su unicidad. facultad_id
-- y area_id: FK. lider_id: profesor que lidera.
CREATE TABLE grupos (
 id INT AUTO_INCREMENT PRIMARY KEY, codigo VARCHAR(30) NOT NULL UNIQUE,
 nombre VARCHAR(150) NOT NULL, facultad_id INT NOT NULL, area_id INT NOT NULL,
 lider_id INT NULL, UNIQUE(facultad_id,nombre),
 FOREIGN KEY(facultad_id) REFERENCES facultades(id), FOREIGN KEY(area_id) REFERENCES areas(id),
 CONSTRAINT ck_grupos_codigo_vacio CHECK(CHAR_LENGTH(TRIM(codigo))>0),
 CONSTRAINT ck_grupos_nombre_vacio CHECK(CHAR_LENGTH(TRIM(nombre))>0)
) ENGINE=InnoDB;
-- Reglas 10 a 14: cédula única, experiencia entera no negativa y pertenencia obligatoria a un grupo.
-- El id técnico permite corregir la cédula sin modificar todas las FK. Columnas: id: identificador
-- interno. cedula: identificación institucional única en texto. nombre: nombre completo. experiencia:
-- años enteros, mínimo cero. grupo_id: FK al único grupo actual.
CREATE TABLE profesores (
 id INT AUTO_INCREMENT PRIMARY KEY, cedula VARCHAR(20) NOT NULL UNIQUE,
 nombre VARCHAR(150) NOT NULL, experiencia INT NOT NULL DEFAULT 0,
 grupo_id INT NOT NULL, CHECK(experiencia>=0),
 FOREIGN KEY(grupo_id) REFERENCES grupos(id),
 CONSTRAINT ck_profesores_cedula_vacio CHECK(CHAR_LENGTH(TRIM(cedula))>0),
 CONSTRAINT ck_profesores_nombre_vacio CHECK(CHAR_LENGTH(TRIM(nombre))>0)
) ENGINE=InnoDB;
ALTER TABLE facultades ADD FOREIGN KEY(decano_id) REFERENCES profesores(id);
ALTER TABLE grupos ADD FOREIGN KEY(lider_id) REFERENCES profesores(id);
-- Regla 11 y corrección docente: PK(profesor_id,nombre) representa los títulos multivaluados. Dos
-- profesores pueden tener el mismo título; uno no puede repetirlo. Columnas: id: identificador
-- auxiliar UNIQUE. profesor_id: propietario y parte de la PK. nombre: título obtenido; completa la PK.
CREATE TABLE titulos (
 id INT AUTO_INCREMENT UNIQUE, profesor_id INT NOT NULL,
 nombre VARCHAR(180) NOT NULL, PRIMARY KEY(profesor_id,nombre),
 FOREIGN KEY(profesor_id) REFERENCES profesores(id),
 CONSTRAINT ck_titulos_nombre_vacio CHECK(CHAR_LENGTH(TRIM(nombre))>0)
) ENGINE=InnoDB;
-- Reglas 35 a 38 y 45: cuenta de un profesor, correo único y perfil DECANO o DOCENTE. clave_hash
-- guarda el resultado de password_hash de PHP, nunca la contraseña en texto. profesor_id UNIQUE
-- establece como máximo una cuenta por profesor. La regla 45 excluye cuentas de estudiantes. Columnas:
-- id: cuenta. profesor_id: FK UNIQUE, una cuenta como máximo por profesor. correo: acceso único.
-- clave_hash: resultado de la función hash de PHP. rol: DECANO o DOCENTE.
CREATE TABLE usuarios (
 id INT AUTO_INCREMENT PRIMARY KEY, profesor_id INT NOT NULL UNIQUE,
 correo VARCHAR(180) NOT NULL UNIQUE, clave_hash VARCHAR(255) NOT NULL,
 rol ENUM('DECANO','DOCENTE') NOT NULL,
 FOREIGN KEY(profesor_id) REFERENCES profesores(id),
 CONSTRAINT ck_usuarios_correo_vacio CHECK(CHAR_LENGTH(TRIM(correo))>0),
 CONSTRAINT ck_usuarios_correo_formato CHECK(correo REGEXP '^[^[:space:]@]+@[^[:space:]@]+[.][^[:space:]@]+$')
) ENGINE=InnoDB;
-- Reglas 15 a 19: código y nombre únicos, presupuesto decimal no negativo, inicio anterior a fin y un
-- solo grupo. La PK id coincide en ambos modelos revisados; las tablas hijas referencian este id.
-- Columnas: id: identificador estable. codigo y nombre: claves alternativas únicas. presupuesto: monto
-- exacto con dos decimales. inicio y fin: límites del proyecto. grupo_id: FK al grupo desarrollador.
CREATE TABLE proyectos (
 id INT AUTO_INCREMENT PRIMARY KEY, codigo VARCHAR(30) NOT NULL UNIQUE,
 nombre VARCHAR(180) NOT NULL UNIQUE, presupuesto DECIMAL(15,2) NOT NULL,
 inicio DATE NOT NULL, fin DATE NOT NULL, grupo_id INT NOT NULL,
 CHECK(presupuesto>=0), CHECK(inicio<fin),
 FOREIGN KEY(grupo_id) REFERENCES grupos(id),
 CONSTRAINT ck_proyectos_codigo_vacio CHECK(CHAR_LENGTH(TRIM(codigo))>0),
 CONSTRAINT ck_proyectos_nombre_vacio CHECK(CHAR_LENGTH(TRIM(nombre))>0)
) ENGINE=InnoDB;
-- Reglas 20 a 26, 32 y 33: relación N:M profesor-proyecto con períodos de incorporación y retiro. Se
-- admiten reingresos sin solapamiento. No se declara UNIQUE(proyecto_id,profesor_id), porque impediría
-- el historial. Dedicación se interpreta como porcentaje; responsable solo corresponde a profesores.
-- Columnas: id: episodio de participación. proyecto_id y profesor_id: FK de la relación.
-- incorporacion: primer día incluido. retiro: último día incluido, opcional. dedicacion: porcentaje
-- del episodio. responsable: 0 o 1 para indicar quién gestiona estudiantes.
CREATE TABLE participaciones (
 id INT AUTO_INCREMENT PRIMARY KEY, proyecto_id INT NOT NULL, profesor_id INT NOT NULL,
 incorporacion DATE NOT NULL, retiro DATE NULL, dedicacion DECIMAL(5,2) NOT NULL,
 responsable BOOLEAN NOT NULL DEFAULT 0,
 CONSTRAINT ck_responsable_booleano CHECK(responsable IN(0,1)), CHECK(retiro IS NULL OR incorporacion<=retiro), CHECK(dedicacion>0 AND dedicacion<=100),
 FOREIGN KEY(proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE, FOREIGN KEY(profesor_id) REFERENCES profesores(id),
 INDEX(profesor_id,proyecto_id,incorporacion)
) ENGINE=InnoDB;
-- Reglas 39 a 41 y 45: identidad independiente con código y correo únicos. registrado_por identifica
-- al profesor que creó el registro. No tiene proyecto_id: un estudiante puede investigar en varios
-- proyectos según la corrección docente. Columnas: id: identidad interna. codigo: identificación
-- estudiantil única. nombre: nombre completo. correo: contacto único. registrado_por: FK del profesor
-- que registra; no es una FK al usuario ni al proyecto.
CREATE TABLE estudiantes (
 id INT AUTO_INCREMENT PRIMARY KEY, codigo VARCHAR(30) NOT NULL UNIQUE,
 nombre VARCHAR(150) NOT NULL, correo VARCHAR(180) NOT NULL UNIQUE,
 registrado_por INT NOT NULL, FOREIGN KEY(registrado_por) REFERENCES profesores(id),
 CONSTRAINT ck_estudiantes_codigo_vacio CHECK(CHAR_LENGTH(TRIM(codigo))>0),
 CONSTRAINT ck_estudiantes_nombre_vacio CHECK(CHAR_LENGTH(TRIM(nombre))>0),
 CONSTRAINT ck_estudiantes_correo_vacio CHECK(CHAR_LENGTH(TRIM(correo))>0),
 CONSTRAINT ck_estudiantes_correo_formato CHECK(correo REGEXP '^[^[:space:]@]+@[^[:space:]@]+[.][^[:space:]@]+$')
) ENGINE=InnoDB;
-- Corrección de la regla 42 y reglas 43 a 45: tabla N:M con PK(estudiante_id,proyecto_id). Impide
-- repetir un vínculo, permite varios proyectos NO simultáneos por estudiante y no contiene un indicador de
-- responsable. Columnas: id: identificador auxiliar UNIQUE. estudiante_id y proyecto_id: ambas FK
-- forman la PK compuesta.
CREATE TABLE estudiante_proyecto (
 id INT AUTO_INCREMENT UNIQUE, estudiante_id INT NOT NULL, proyecto_id INT NOT NULL,
 fecha_incorporacion DATE NOT NULL, fecha_retiro DATE NULL,
 CONSTRAINT ck_estudiante_periodo CHECK(fecha_retiro IS NULL OR fecha_retiro>=fecha_incorporacion),
 PRIMARY KEY(estudiante_id,proyecto_id),
 FOREIGN KEY(estudiante_id) REFERENCES estudiantes(id),
 FOREIGN KEY(proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 2. DATOS FICTICIOS EN ORDEN DE DEPENDENCIA
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
INSERT INTO estudiante_proyecto(estudiante_id,proyecto_id,fecha_incorporacion,fecha_retiro)
 VALUES(1,1,'2026-01-01','2026-12-31'),(1,2,'2025-01-01','2025-12-31');

-- Cuentas ficticias para una instalacion nueva. Clave de prueba: Universidad2026!
-- Cambiarla desde Mi cuenta antes de compartir una instalacion con datos reales.
INSERT INTO usuarios(profesor_id,correo,clave_hash,rol) VALUES
(1,'decano@example.test','$2y$10$tErMlG5ZBRsTRHsemexAu.KfZFlvhqlG7XRds2cvGhXErqIe9GUci','DECANO'),
(2,'docente@example.test','$2y$10$tErMlG5ZBRsTRHsemexAu.KfZFlvhqlG7XRds2cvGhXErqIe9GUci','DOCENTE'),
(3,'decano.ingenieria@example.test','$2y$10$tErMlG5ZBRsTRHsemexAu.KfZFlvhqlG7XRds2cvGhXErqIe9GUci','DECANO');

-- 3. VISTAS DE CONSULTA
-- Reglas 27 a 29: JOIN con grupo y facultad, y CASE para obtener PROGRAMADO, ACTIVO o TERMINADO con la
-- fecha actual. No se almacena un estado que quedaría desactualizado.
CREATE VIEW v_proyectos AS
 SELECT p.*,g.nombre grupo,f.nombre facultad,f.id facultad_id,
 CASE WHEN CURRENT_DATE<p.inicio THEN 'PROGRAMADO' WHEN CURRENT_DATE>p.fin THEN 'TERMINADO' ELSE 'ACTIVO' END COLLATE utf8mb4_unicode_ci estado
 FROM proyectos p JOIN grupos g ON g.id=p.grupo_id JOIN facultades f ON f.id=g.facultad_id;

-- Reutiliza el vínculo profesor-grupo-facultad para consultar profesores y comprobar el ámbito del
-- decano (regla 31).
CREATE VIEW v_profesores AS
 SELECT p.*,g.nombre grupo,g.facultad_id FROM profesores p JOIN grupos g ON g.id=p.grupo_id;

-- Regla 34 y corrección docente: calcula vigente desde incorporación y retiro. El valor se obtiene al
-- consultar, no es una columna de la tabla. Sin retiro, la regla permite que siga vigente aunque el
-- proyecto ya haya terminado.
CREATE VIEW v_participaciones AS
 SELECT a.*,p.nombre profesor,y.nombre proyecto,
 (CURRENT_DATE>=a.incorporacion AND (a.retiro IS NULL OR CURRENT_DATE<=a.retiro)) vigente
 FROM participaciones a JOIN profesores p ON p.id=a.profesor_id JOIN proyectos y ON y.id=a.proyecto_id;

-- Informe para el decano. Cada subconsulta cuenta profesores distintos o estudiantes por proyecto sin
-- multiplicar filas entre dos relaciones N:M. prioridad_presupuesto vale 1 más la cantidad de
-- presupuestos DIFERENTES mayores en la facultad; los empates comparten posición. Es una ayuda de
-- presentación, no una regla de aprobación presupuestal.
CREATE OR REPLACE VIEW v_cartera_proyectos AS
 SELECT p.id,p.codigo,p.nombre,p.facultad_id,p.grupo_id,p.estado,p.presupuesto,
 (SELECT COUNT(DISTINCT a.profesor_id) FROM participaciones a WHERE a.proyecto_id=p.id) profesores,
 (SELECT COUNT(*) FROM estudiante_proyecto e WHERE e.proyecto_id=p.id) estudiantes,
 -- Una posición por cada presupuesto diferente mayor, dentro de la misma facultad.
 (SELECT COUNT(DISTINCT otro.presupuesto)+1 FROM v_proyectos otro
  WHERE otro.facultad_id=p.facultad_id AND otro.presupuesto>p.presupuesto) prioridad_presupuesto
 FROM v_proyectos p;

-- Reglas 27 a 30: LEFT JOIN incluye facultades sin proyectos, COUNT/SUM agrupan y COALESCE devuelve
-- cero donde no hay datos.
CREATE OR REPLACE VIEW v_resumen_facultad AS
 SELECT f.id facultad_id,f.nombre,COUNT(p.id) total,
 COALESCE(SUM(p.estado='ACTIVO'),0) activos,COALESCE(SUM(p.estado='TERMINADO'),0) terminados,
 COALESCE(SUM(p.presupuesto),0) presupuesto FROM facultades f LEFT JOIN v_proyectos p ON p.facultad_id=f.id GROUP BY f.id,f.nombre;

-- 4. CUATRO PROCEDIMIENTOS CON FUNCIONES DIFERENTES
-- DELIMITER permite usar ; dentro de BEGIN y END. SIGNAL devuelve una regla incumplida.
DELIMITER $$
-- Regla 30: comprueba desde<=hasta y suma una sola vez los proyectos que intersectan el período.
-- inicio<=hasta AND fin>=desde expresa el cruce de intervalos. No calcula gastos ni prorratea días: el
-- avance no lo pide.
CREATE PROCEDURE sp_presupuesto(IN facultad INT, IN desde DATE, IN hasta DATE)
BEGIN
 IF desde IS NULL OR hasta IS NULL OR desde>hasta THEN
 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Periodo invalido'; END IF;
 SELECT COUNT(*) proyectos,COALESCE(SUM(presupuesto),0) presupuesto
 FROM v_proyectos WHERE facultad_id=facultad AND inicio<=hasta AND fin>=desde;
END$$

-- Reglas 24, 26, 32 y 33: obtiene las fechas del proyecto, bloquea su fila mientras se valida y
-- rechaza períodos fuera del proyecto o solapados. Dos intervalos se cruzan si inicio1<=fin2 y
-- fin1>=inicio2; COALESCE sustituye retiro ausente por el fin del proyecto para esa comparación.
-- id<>registro excluye la propia fila al editar.
CREATE PROCEDURE sp_validar_participacion(IN registro INT,IN proyecto INT,IN profesor INT,IN desde DATE,IN hasta DATE)
BEGIN
 DECLARE fecha_inicio DATE; DECLARE fecha_fin DATE; DECLARE bloqueo INT;
 -- Serializa las asignaciones concurrentes del mismo proyecto.
 SELECT id,inicio,fin INTO bloqueo,fecha_inicio,fecha_fin FROM proyectos WHERE id=proyecto FOR UPDATE;
 IF desde<fecha_inicio OR desde>fecha_fin OR (hasta IS NOT NULL AND (hasta<desde OR hasta>fecha_fin)) THEN
 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Participacion fuera de las fechas del proyecto'; END IF;
 IF EXISTS(SELECT 1 FROM participaciones WHERE proyecto_id=proyecto AND profesor_id=profesor
 AND id<>registro AND incorporacion<=COALESCE(hasta,fecha_fin) AND COALESCE(retiro,fecha_fin)>=desde) THEN
 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El profesor ya participa durante ese periodo'; END IF;
END$$

-- Alta administrativa para resolver el ciclo facultad-grupo-profesor: crea facultad, teléfono, grupo,
-- profesor y título; después asigna líder y decano y confirma. Si algo falla se revierte el conjunto.
-- La operacion de facultad se reserva a administracion SQL. Los mínimos de existencia se garantizan por esta operación y permisos,
-- no por un CHECK con subconsultas.
CREATE PROCEDURE sp_crear_facultad(IN nombre_fac VARCHAR(150),IN sede INT,IN telefono VARCHAR(30),IN codigo_grupo VARCHAR(30),IN nombre_grupo VARCHAR(150),IN area INT,IN cedula VARCHAR(20),IN nombre_prof VARCHAR(150),IN titulo VARCHAR(180)) SQL SECURITY DEFINER
BEGIN
 DECLARE fac INT;DECLARE grupo INT;DECLARE prof INT;
 DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK;RESIGNAL;END;
 IF telefono IS NULL OR TRIM(telefono)='' OR titulo IS NULL OR TRIM(titulo)='' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Se requieren telefono y titulo inicial';END IF;
 START TRANSACTION;
 INSERT INTO facultades(nombre,sede_id) VALUES(nombre_fac,sede);SET fac=LAST_INSERT_ID();
 INSERT INTO telefonos_facultad(facultad_id,numero) VALUES(fac,telefono);
 INSERT INTO grupos(codigo,nombre,facultad_id,area_id) VALUES(codigo_grupo,nombre_grupo,fac,area);SET grupo=LAST_INSERT_ID();
 INSERT INTO profesores(cedula,nombre,experiencia,grupo_id) VALUES(cedula,nombre_prof,0,grupo);SET prof=LAST_INSERT_ID();
 INSERT INTO titulos(profesor_id,nombre) VALUES(prof,titulo);
 UPDATE grupos SET lider_id=prof WHERE id=grupo;
 UPDATE facultades SET decano_id=prof WHERE id=fac;
 COMMIT;SELECT fac facultad_id,grupo grupo_id,prof profesor_id;
END$$

-- Regla 42: un estudiante puede investigar en varios proyectos históricos, solo uno a la vez.
-- Los extremos se incluyen. NULL significa período abierto, no cierre automático al finalizar el proyecto.
CREATE PROCEDURE sp_validar_estudiante(IN registro INT,IN proyecto INT,IN estudiante INT,IN desde DATE,IN hasta DATE)
BEGIN
 DECLARE ini DATE; DECLARE fin_proyecto DATE; DECLARE bloqueo INT;
 SELECT id,inicio,fin INTO bloqueo,ini,fin_proyecto FROM proyectos WHERE id=proyecto FOR UPDATE;
 SELECT id INTO bloqueo FROM estudiantes WHERE id=estudiante FOR UPDATE;
 IF desde IS NULL OR desde<ini OR desde>fin_proyecto OR (hasta IS NOT NULL AND (hasta<desde OR hasta>fin_proyecto)) THEN
 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Las fechas del estudiante deben estar dentro del proyecto y retiro no puede ser anterior a incorporacion'; END IF;
 IF EXISTS(SELECT 1 FROM estudiante_proyecto WHERE estudiante_id=estudiante AND id<>registro
 AND fecha_incorporacion<=COALESCE(hasta,'9999-12-31') AND COALESCE(fecha_retiro,'9999-12-31')>=desde) THEN
 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El estudiante ya participa durante ese periodo; cierre su participacion anterior'; END IF;
END$$

-- 5. CINCO TRIGGERS PARA LAS REGLAS TEMPORALES
CREATE TRIGGER tr_estudiante_insert BEFORE INSERT ON estudiante_proyecto FOR EACH ROW
BEGIN CALL sp_validar_estudiante(0,NEW.proyecto_id,NEW.estudiante_id,NEW.fecha_incorporacion,NEW.fecha_retiro); END$$
CREATE TRIGGER tr_estudiante_update BEFORE UPDATE ON estudiante_proyecto FOR EACH ROW
BEGIN CALL sp_validar_estudiante(OLD.id,NEW.proyecto_id,NEW.estudiante_id,NEW.fecha_incorporacion,NEW.fecha_retiro); END$$
-- Antes de INSERT llama a sp_validar_participacion: aplica los intervalos y evita duplicidad temporal
-- también cuando se escribe desde SQL.
CREATE TRIGGER tr_participacion_insert BEFORE INSERT ON participaciones FOR EACH ROW
BEGIN CALL sp_validar_participacion(0,NEW.proyecto_id,NEW.profesor_id,NEW.incorporacion,NEW.retiro); END$$

-- Antes de UPDATE repite la validación temporal excluyendo OLD.id, para permitir editar el mismo
-- registro sin compararlo contra sí mismo.
CREATE TRIGGER tr_participacion_update BEFORE UPDATE ON participaciones FOR EACH ROW
BEGIN CALL sp_validar_participacion(OLD.id,NEW.proyecto_id,NEW.profesor_id,NEW.incorporacion,NEW.retiro); END$$

-- Antes de cambiar un proyecto verifica que sus nuevas fechas no dejen participaciones fuera del
-- intervalo (reglas 32 y 33).
CREATE TRIGGER tr_proyecto_fechas BEFORE UPDATE ON proyectos FOR EACH ROW
BEGIN
 IF EXISTS(SELECT 1 FROM participaciones WHERE proyecto_id=OLD.id AND
 (incorporacion<NEW.inicio OR incorporacion>NEW.fin OR retiro>NEW.fin)) THEN
 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Las fechas excluyen participaciones existentes'; END IF;
 IF EXISTS(SELECT 1 FROM estudiante_proyecto WHERE proyecto_id=OLD.id AND
 (fecha_incorporacion<NEW.inicio OR fecha_incorporacion>NEW.fin OR fecha_retiro>NEW.fin)) THEN
 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Las fechas del proyecto excluyen participaciones de estudiantes'; END IF;
END$$
DELIMITER ;

-- 6. CONSULTAS PARA SUSTENTAR (opcionales; no cambian datos)
-- Ver proyectos y su estado calculado:
-- SELECT codigo,nombre,estado FROM v_proyectos;
-- Ver a una estudiante en distintos proyectos:
-- SELECT e.nombre,p.nombre FROM estudiante_proyecto ep
-- JOIN estudiantes e ON e.id=ep.estudiante_id JOIN proyectos p ON p.id=ep.proyecto_id;
-- Consultar el presupuesto de una facultad en un periodo:
-- CALL sp_presupuesto(1,'2026-01-01','2026-12-31');
-- Revisar los indices elegidos por el motor:
-- EXPLAIN SELECT * FROM v_proyectos WHERE facultad_id=1;
-- Mostrar permisos de la conexion actual:
-- SHOW GRANTS;
-- Las credenciales de conexion se escriben de forma privada en .env, fuera de public/.
-- scripts/permisos.php crea las cuentas SQL de servicio y consulta para la base seleccionada.
-- Para crear una copia nueva, importar este archivo y configurar la conexion de PHP.

-- 7. DOS FACULTADES ADICIONALES PARA PRACTICAR CON OTROS PERFILES
-- Datos ficticios. El procedimiento crea juntos facultad, telefono, grupo,
-- primer profesor con titulo, decano y lider. Asi se resuelve su dependencia circular.
-- Despues se registra un segundo profesor y las cuentas de acceso de ambos.
SET @sede_central=(SELECT id FROM sedes WHERE nombre='Sede Central');

INSERT INTO areas(nombre) VALUES('Educación y pedagogía');
SET @area_educacion=LAST_INSERT_ID();
CALL sp_crear_facultad(
 'Facultad de Ciencias y Educación',@sede_central,'6015550103',
 'G-EDU','Educación y sociedad',@area_educacion,
 '10000004','Marta Rojas','Doctorado en Educación'
);
SET @grupo_educacion=(SELECT id FROM grupos WHERE codigo='G-EDU');
SET @decano_educacion=(SELECT id FROM profesores WHERE cedula='10000004');
START TRANSACTION;
INSERT INTO profesores(cedula,nombre,experiencia,grupo_id)
 VALUES('10000005','Diego Castro',5,@grupo_educacion);
SET @profesor_educacion=LAST_INSERT_ID();
INSERT INTO titulos(profesor_id,nombre) VALUES(@profesor_educacion,'Maestría en Educación');
-- La misma clave publica de los ejemplos iniciales: Universidad2026!
INSERT INTO usuarios(profesor_id,correo,clave_hash,rol) VALUES
 (@decano_educacion,'decano.ciencias@example.test','$2y$10$tErMlG5ZBRsTRHsemexAu.KfZFlvhqlG7XRds2cvGhXErqIe9GUci','DECANO'),
 (@profesor_educacion,'profesor.ciencias@example.test','$2y$10$tErMlG5ZBRsTRHsemexAu.KfZFlvhqlG7XRds2cvGhXErqIe9GUci','DOCENTE');
COMMIT;

INSERT INTO areas(nombre) VALUES('Gestión ambiental');
SET @area_ambiente=LAST_INSERT_ID();
CALL sp_crear_facultad(
 'Facultad del Medio Ambiente y Recursos Naturales',@sede_central,'6015550104',
 'G-AMB','Ambiente y sostenibilidad',@area_ambiente,
 '10000006','Jorge Medina','Doctorado en Ciencias Ambientales'
);
SET @grupo_ambiente=(SELECT id FROM grupos WHERE codigo='G-AMB');
SET @decano_ambiente=(SELECT id FROM profesores WHERE cedula='10000006');
START TRANSACTION;
INSERT INTO profesores(cedula,nombre,experiencia,grupo_id)
 VALUES('10000007','Paula Gómez',5,@grupo_ambiente);
SET @profesor_ambiente=LAST_INSERT_ID();
INSERT INTO titulos(profesor_id,nombre) VALUES(@profesor_ambiente,'Maestría en Gestión Ambiental');
INSERT INTO usuarios(profesor_id,correo,clave_hash,rol) VALUES
 (@decano_ambiente,'decano.ambiente@example.test','$2y$10$tErMlG5ZBRsTRHsemexAu.KfZFlvhqlG7XRds2cvGhXErqIe9GUci','DECANO'),
 (@profesor_ambiente,'profesor.ambiente@example.test','$2y$10$tErMlG5ZBRsTRHsemexAu.KfZFlvhqlG7XRds2cvGhXErqIe9GUci','DOCENTE');
COMMIT;
