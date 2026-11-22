-- MySQL 8.0.16+; ejecutar sobre una base vacía seleccionada previamente.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE TABLE sedes (
 id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(100) NOT NULL UNIQUE,
 CONSTRAINT ck_sedes_nombre_vacio CHECK(CHAR_LENGTH(TRIM(nombre))>0)
) ENGINE=InnoDB;
CREATE TABLE facultades (
 id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(150) NOT NULL UNIQUE,
 sede_id INT NOT NULL, decano_id INT NULL,
 FOREIGN KEY(sede_id) REFERENCES sedes(id),
 CONSTRAINT ck_facultades_nombre_vacio CHECK(CHAR_LENGTH(TRIM(nombre))>0)
) ENGINE=InnoDB;
CREATE TABLE telefonos_facultad (
 id INT AUTO_INCREMENT UNIQUE, facultad_id INT NOT NULL,
 numero VARCHAR(30) NOT NULL, PRIMARY KEY(facultad_id,numero),
 FOREIGN KEY(facultad_id) REFERENCES facultades(id),
 CONSTRAINT ck_telefonos_facultad_numero_vacio CHECK(CHAR_LENGTH(TRIM(numero))>0)
) ENGINE=InnoDB;
CREATE TABLE areas (
 id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(120) NOT NULL UNIQUE,
 CONSTRAINT ck_areas_nombre_vacio CHECK(CHAR_LENGTH(TRIM(nombre))>0)
) ENGINE=InnoDB;
CREATE TABLE grupos (
 id INT AUTO_INCREMENT PRIMARY KEY, codigo VARCHAR(30) NOT NULL UNIQUE,
 nombre VARCHAR(150) NOT NULL, facultad_id INT NOT NULL, area_id INT NOT NULL,
 lider_id INT NULL, UNIQUE(facultad_id,nombre),
 FOREIGN KEY(facultad_id) REFERENCES facultades(id), FOREIGN KEY(area_id) REFERENCES areas(id),
 CONSTRAINT ck_grupos_codigo_vacio CHECK(CHAR_LENGTH(TRIM(codigo))>0),
 CONSTRAINT ck_grupos_nombre_vacio CHECK(CHAR_LENGTH(TRIM(nombre))>0)
) ENGINE=InnoDB;
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
CREATE TABLE titulos (
 id INT AUTO_INCREMENT UNIQUE, profesor_id INT NOT NULL,
 nombre VARCHAR(180) NOT NULL, PRIMARY KEY(profesor_id,nombre),
 FOREIGN KEY(profesor_id) REFERENCES profesores(id),
 CONSTRAINT ck_titulos_nombre_vacio CHECK(CHAR_LENGTH(TRIM(nombre))>0)
) ENGINE=InnoDB;
CREATE TABLE usuarios (
 id INT AUTO_INCREMENT PRIMARY KEY, profesor_id INT NOT NULL UNIQUE,
 correo VARCHAR(180) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL,
 rol ENUM('DECANO','DOCENTE') NOT NULL,
 FOREIGN KEY(profesor_id) REFERENCES profesores(id),
 CONSTRAINT ck_usuarios_correo_vacio CHECK(CHAR_LENGTH(TRIM(correo))>0),
 CONSTRAINT ck_usuarios_correo_formato CHECK(correo REGEXP '^[^[:space:]@]+@[^[:space:]@]+[.][^[:space:]@]+$')
) ENGINE=InnoDB;
CREATE TABLE proyectos (
 id INT AUTO_INCREMENT PRIMARY KEY, codigo VARCHAR(30) NOT NULL UNIQUE,
 nombre VARCHAR(180) NOT NULL UNIQUE, presupuesto DECIMAL(15,2) NOT NULL,
 inicio DATE NOT NULL, fin DATE NOT NULL, grupo_id INT NOT NULL,
 CHECK(presupuesto>=0), CHECK(inicio<fin),
 FOREIGN KEY(grupo_id) REFERENCES grupos(id),
 CONSTRAINT ck_proyectos_codigo_vacio CHECK(CHAR_LENGTH(TRIM(codigo))>0),
 CONSTRAINT ck_proyectos_nombre_vacio CHECK(CHAR_LENGTH(TRIM(nombre))>0)
) ENGINE=InnoDB;
CREATE TABLE participaciones (
 id INT AUTO_INCREMENT PRIMARY KEY, proyecto_id INT NOT NULL, profesor_id INT NOT NULL,
 incorporacion DATE NOT NULL, retiro DATE NULL, dedicacion DECIMAL(5,2) NOT NULL,
 responsable BOOLEAN NOT NULL DEFAULT 0,
 CONSTRAINT ck_responsable_booleano CHECK(responsable IN(0,1)), CHECK(retiro IS NULL OR incorporacion<=retiro), CHECK(dedicacion>0 AND dedicacion<=100),
 FOREIGN KEY(proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE, FOREIGN KEY(profesor_id) REFERENCES profesores(id),
 INDEX(profesor_id,proyecto_id,incorporacion)
) ENGINE=InnoDB;
CREATE TABLE estudiantes (
 id INT AUTO_INCREMENT PRIMARY KEY, codigo VARCHAR(30) NOT NULL UNIQUE,
 nombre VARCHAR(150) NOT NULL, correo VARCHAR(180) NOT NULL UNIQUE,
 registrado_por INT NOT NULL, FOREIGN KEY(registrado_por) REFERENCES profesores(id),
 CONSTRAINT ck_estudiantes_codigo_vacio CHECK(CHAR_LENGTH(TRIM(codigo))>0),
 CONSTRAINT ck_estudiantes_nombre_vacio CHECK(CHAR_LENGTH(TRIM(nombre))>0),
 CONSTRAINT ck_estudiantes_correo_vacio CHECK(CHAR_LENGTH(TRIM(correo))>0),
 CONSTRAINT ck_estudiantes_correo_formato CHECK(correo REGEXP '^[^[:space:]@]+@[^[:space:]@]+[.][^[:space:]@]+$')
) ENGINE=InnoDB;
CREATE TABLE estudiante_proyecto (
 id INT AUTO_INCREMENT UNIQUE, estudiante_id INT NOT NULL, proyecto_id INT NOT NULL,
 PRIMARY KEY(estudiante_id,proyecto_id),
 FOREIGN KEY(estudiante_id) REFERENCES estudiantes(id),
 FOREIGN KEY(proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE schema_migrations (
 version VARCHAR(64) PRIMARY KEY, aplicada_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
INSERT INTO schema_migrations(version) VALUES('20261117_correcciones_avance');
CREATE TABLE auditoria (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, proyecto_id INT NULL,
 accion VARCHAR(30) NOT NULL, detalle VARCHAR(255) NOT NULL,
 fecha TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE VIEW v_proyectos AS
 SELECT p.*,g.nombre grupo,f.nombre facultad,f.id facultad_id,
 CASE WHEN CURRENT_DATE<p.inicio THEN 'PROGRAMADO' WHEN CURRENT_DATE>p.fin THEN 'TERMINADO' ELSE 'ACTIVO' END COLLATE utf8mb4_unicode_ci estado
 FROM proyectos p JOIN grupos g ON g.id=p.grupo_id JOIN facultades f ON f.id=g.facultad_id;
CREATE VIEW v_profesores AS
 SELECT p.*,g.nombre grupo,g.facultad_id FROM profesores p JOIN grupos g ON g.id=p.grupo_id;
CREATE VIEW v_participaciones AS
 SELECT a.*,p.nombre profesor,y.nombre proyecto,
 (CURRENT_DATE>=a.incorporacion AND (a.retiro IS NULL OR CURRENT_DATE<=a.retiro)) vigente
 FROM participaciones a JOIN profesores p ON p.id=a.profesor_id JOIN proyectos y ON y.id=a.proyecto_id;
DELIMITER $$
CREATE PROCEDURE sp_presupuesto(IN facultad INT, IN desde DATE, IN hasta DATE)
BEGIN
 IF desde IS NULL OR hasta IS NULL OR desde>hasta THEN
 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Periodo invalido'; END IF;
 SELECT COUNT(*) proyectos,COALESCE(SUM(presupuesto),0) presupuesto
 FROM v_proyectos WHERE facultad_id=facultad AND inicio<=hasta AND fin>=desde;
END$$
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
CREATE TRIGGER tr_participacion_insert BEFORE INSERT ON participaciones FOR EACH ROW
BEGIN CALL sp_validar_participacion(0,NEW.proyecto_id,NEW.profesor_id,NEW.incorporacion,NEW.retiro); END$$
CREATE TRIGGER tr_participacion_update BEFORE UPDATE ON participaciones FOR EACH ROW
BEGIN CALL sp_validar_participacion(OLD.id,NEW.proyecto_id,NEW.profesor_id,NEW.incorporacion,NEW.retiro); END$$
CREATE TRIGGER tr_proyecto_fechas BEFORE UPDATE ON proyectos FOR EACH ROW
BEGIN
 IF EXISTS(SELECT 1 FROM participaciones WHERE proyecto_id=OLD.id AND
 (incorporacion<NEW.inicio OR incorporacion>NEW.fin OR retiro>NEW.fin)) THEN
 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Las fechas excluyen participaciones existentes'; END IF;
END$$
CREATE TRIGGER tr_proyecto_auditoria AFTER UPDATE ON proyectos FOR EACH ROW
BEGIN INSERT INTO auditoria(proyecto_id,accion,detalle) VALUES(NEW.id,'ACTUALIZAR',CONCAT('Proyecto ',NEW.codigo)); END$$
DELIMITER ;
