-- INVESTIGA U. Modelo relacional entregado: nombres y claves del diagrama.
-- Importar en una BD nueva. Nunca sobre la instalación compartida existente.
CREATE DATABASE investiga_u CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE investiga_u;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
-- id auxiliar conserva los enlaces de edición de la interfaz; NO reemplaza la PK del diagrama.
CREATE TABLE Facultad (
 id_facultad INT AUTO_INCREMENT PRIMARY KEY,
 nombre VARCHAR(150) NOT NULL UNIQUE, sede VARCHAR(100) NOT NULL,
 CHECK(CHAR_LENGTH(TRIM(nombre))>0), CHECK(CHAR_LENGTH(TRIM(sede))>0)
) ENGINE=InnoDB;
CREATE TABLE Facultad_telefono (
 id_facultad INT NOT NULL, telefono VARCHAR(30) NOT NULL,
 id INT AUTO_INCREMENT UNIQUE, PRIMARY KEY(id_facultad,telefono),
 FOREIGN KEY(id_facultad) REFERENCES Facultad(id_facultad) ON UPDATE CASCADE,
 CHECK(CHAR_LENGTH(TRIM(telefono))>0)
) ENGINE=InnoDB;
CREATE TABLE Profesor (
 cedula VARCHAR(20) PRIMARY KEY, nombre VARCHAR(150) NOT NULL,
 tiempo_Experiencia INT NOT NULL DEFAULT 0, id INT AUTO_INCREMENT UNIQUE,
 CHECK(CHAR_LENGTH(TRIM(cedula))>0), CHECK(CHAR_LENGTH(TRIM(nombre))>0), CHECK(tiempo_Experiencia>=0)
) ENGINE=InnoDB;
CREATE TABLE profesor_titulo (
 Cedula VARCHAR(20) NOT NULL, titulo VARCHAR(180) NOT NULL,
 id INT AUTO_INCREMENT UNIQUE, PRIMARY KEY(Cedula,titulo),
 FOREIGN KEY(Cedula) REFERENCES Profesor(cedula) ON UPDATE CASCADE,
 CHECK(CHAR_LENGTH(TRIM(titulo))>0)
) ENGINE=InnoDB;
CREATE TABLE Grupo_investigacion (
 codigo_grupo VARCHAR(30) PRIMARY KEY, nombre VARCHAR(150) NOT NULL,
 area_interes VARCHAR(120) NOT NULL, id INT AUTO_INCREMENT UNIQUE,
 CHECK(CHAR_LENGTH(TRIM(codigo_grupo))>0), CHECK(CHAR_LENGTH(TRIM(nombre))>0),
 CHECK(CHAR_LENGTH(TRIM(area_interes))>0)
) ENGINE=InnoDB;
CREATE TABLE Es_decano (
 cedula VARCHAR(20) NOT NULL UNIQUE, id_facultad INT NOT NULL UNIQUE, PRIMARY KEY(cedula,id_facultad),
 FOREIGN KEY(cedula) REFERENCES Profesor(cedula) ON UPDATE CASCADE,
 FOREIGN KEY(id_facultad) REFERENCES Facultad(id_facultad) ON UPDATE CASCADE
) ENGINE=InnoDB;
CREATE TABLE Tiene_grupo (
 codigo_grupo VARCHAR(30) NOT NULL UNIQUE, id_facultad INT NOT NULL, PRIMARY KEY(codigo_grupo,id_facultad),
 FOREIGN KEY(codigo_grupo) REFERENCES Grupo_investigacion(codigo_grupo) ON UPDATE CASCADE,
 FOREIGN KEY(id_facultad) REFERENCES Facultad(id_facultad) ON UPDATE CASCADE
) ENGINE=InnoDB;
CREATE TABLE Lidera (
 cedula VARCHAR(20) NOT NULL, codigo_grupo VARCHAR(30) NOT NULL UNIQUE, PRIMARY KEY(cedula,codigo_grupo),
 FOREIGN KEY(cedula) REFERENCES Profesor(cedula) ON UPDATE CASCADE,
 FOREIGN KEY(codigo_grupo) REFERENCES Grupo_investigacion(codigo_grupo) ON UPDATE CASCADE
) ENGINE=InnoDB;
CREATE TABLE Pertenece (
 cedula VARCHAR(20) NOT NULL UNIQUE, codigo_grupo VARCHAR(30) NOT NULL, PRIMARY KEY(cedula,codigo_grupo),
 FOREIGN KEY(cedula) REFERENCES Profesor(cedula) ON UPDATE CASCADE,
 FOREIGN KEY(codigo_grupo) REFERENCES Grupo_investigacion(codigo_grupo) ON UPDATE CASCADE
) ENGINE=InnoDB;
CREATE TABLE Usuario (
 id_usuario INT AUTO_INCREMENT PRIMARY KEY, correo VARCHAR(180) NOT NULL UNIQUE,
 password VARCHAR(255) NOT NULL, rol ENUM('DECANO','DOCENTE') NOT NULL,
 CHECK(correo REGEXP '^[^[:space:]@]+@[^[:space:]@]+[.][^[:space:]@]+$')
) ENGINE=InnoDB;
-- password conserva el nombre del diagrama, pero su contenido siempre es un hash PHP.
CREATE TABLE Tiene (
 id_usuario INT NOT NULL UNIQUE, cedula VARCHAR(20) NOT NULL UNIQUE, PRIMARY KEY(id_usuario,cedula),
 FOREIGN KEY(id_usuario) REFERENCES Usuario(id_usuario) ON UPDATE CASCADE,
 FOREIGN KEY(cedula) REFERENCES Profesor(cedula) ON UPDATE CASCADE
) ENGINE=InnoDB;
CREATE TABLE Proyecto_investigacion (
 codigo_proyecto VARCHAR(30) PRIMARY KEY, nombre VARCHAR(180) NOT NULL UNIQUE,
 presupuesto DECIMAL(15,2) NOT NULL, fecha_inicio DATE NOT NULL, fecha_fin DATE NOT NULL,
 id INT AUTO_INCREMENT UNIQUE, CHECK(presupuesto>=0), CHECK(fecha_inicio<fecha_fin),
 CHECK(CHAR_LENGTH(TRIM(codigo_proyecto))>0), CHECK(CHAR_LENGTH(TRIM(nombre))>0)
) ENGINE=InnoDB;
CREATE TABLE Desarrolla (
 codigo_proyecto VARCHAR(30) NOT NULL UNIQUE, codigo_grupo VARCHAR(30) NOT NULL, PRIMARY KEY(codigo_proyecto,codigo_grupo),
 FOREIGN KEY(codigo_proyecto) REFERENCES Proyecto_investigacion(codigo_proyecto) ON UPDATE CASCADE ON DELETE CASCADE,
 FOREIGN KEY(codigo_grupo) REFERENCES Grupo_investigacion(codigo_grupo) ON UPDATE CASCADE
) ENGINE=InnoDB;
CREATE TABLE Participa (
 cedula VARCHAR(20) NOT NULL, codigo_proyecto VARCHAR(30) NOT NULL, fecha_incorporacion DATE NOT NULL,
 fecha_retiro DATE NULL, es_responsable BOOLEAN NOT NULL DEFAULT 0, dedicacion DECIMAL(5,2) NOT NULL,
 id INT AUTO_INCREMENT UNIQUE, PRIMARY KEY(cedula,codigo_proyecto,fecha_incorporacion),
 FOREIGN KEY(cedula) REFERENCES Profesor(cedula) ON UPDATE CASCADE,
 FOREIGN KEY(codigo_proyecto) REFERENCES Proyecto_investigacion(codigo_proyecto) ON UPDATE CASCADE ON DELETE CASCADE,
 CHECK(fecha_retiro IS NULL OR fecha_retiro>=fecha_incorporacion),
 CHECK(es_responsable IN(0,1)), CHECK(dedicacion>0 AND dedicacion<=100)
) ENGINE=InnoDB;
CREATE TABLE Estudiante (
 codigo_estudiante VARCHAR(30) PRIMARY KEY, nombre VARCHAR(150) NOT NULL, correo VARCHAR(180) NOT NULL UNIQUE,
 id INT AUTO_INCREMENT UNIQUE, registrado_por VARCHAR(20) NOT NULL,
 FOREIGN KEY(registrado_por) REFERENCES Profesor(cedula) ON UPDATE CASCADE,
 CHECK(CHAR_LENGTH(TRIM(codigo_estudiante))>0), CHECK(CHAR_LENGTH(TRIM(nombre))>0),
 CHECK(correo REGEXP '^[^[:space:]@]+@[^[:space:]@]+[.][^[:space:]@]+$')
) ENGINE=InnoDB;
-- registrado_por conserva la facultad de registro para los permisos, incluso antes de investigar.
CREATE TABLE Investiga (
 codigo_estudiante VARCHAR(30) NOT NULL, codigo_proyecto VARCHAR(30) NOT NULL, fecha_inicio DATE NOT NULL,
 fecha_retiro DATE NULL, id INT AUTO_INCREMENT UNIQUE,
 PRIMARY KEY(codigo_estudiante,codigo_proyecto,fecha_inicio),
 FOREIGN KEY(codigo_estudiante) REFERENCES Estudiante(codigo_estudiante) ON UPDATE CASCADE,
 FOREIGN KEY(codigo_proyecto) REFERENCES Proyecto_investigacion(codigo_proyecto) ON UPDATE CASCADE ON DELETE CASCADE,
 CHECK(fecha_retiro IS NULL OR fecha_retiro>=fecha_inicio)
) ENGINE=InnoDB;
-- fecha_retiro completa la regla 42 aclarada por la profesora. NULL deja abierto el período.

-- Tres vistas de lectura. Los demás listados son SELECT con JOIN desde app/datos.php.

CREATE VIEW v_profesores AS
 SELECT p.id,p.cedula,p.nombre,p.tiempo_Experiencia experiencia,g.id grupo_id,g.nombre grupo,t.id_facultad facultad_id
 FROM Profesor p JOIN Pertenece b ON b.cedula=p.cedula
 JOIN Grupo_investigacion g ON g.codigo_grupo=b.codigo_grupo JOIN Tiene_grupo t ON t.codigo_grupo=g.codigo_grupo;
CREATE VIEW v_proyectos AS
 SELECT p.id,p.codigo_proyecto codigo,p.nombre,p.presupuesto,p.fecha_inicio inicio,p.fecha_fin fin,
 g.id grupo_id,g.nombre grupo,f.nombre facultad,f.id_facultad facultad_id,
 CASE WHEN CURRENT_DATE<p.fecha_inicio THEN 'PROGRAMADO' WHEN CURRENT_DATE>p.fecha_fin THEN 'TERMINADO' ELSE 'ACTIVO' END COLLATE utf8mb4_unicode_ci estado
 FROM Proyecto_investigacion p JOIN Desarrolla d ON d.codigo_proyecto=p.codigo_proyecto
 JOIN Grupo_investigacion g ON g.codigo_grupo=d.codigo_grupo JOIN Tiene_grupo t ON t.codigo_grupo=g.codigo_grupo
 JOIN Facultad f ON f.id_facultad=t.id_facultad;
CREATE VIEW v_participaciones AS
 SELECT a.id,p.id proyecto_id,r.id profesor_id,a.fecha_incorporacion incorporacion,a.fecha_retiro retiro,
 a.es_responsable responsable,a.dedicacion,r.nombre profesor,p.nombre proyecto,
 (CURRENT_DATE>=a.fecha_incorporacion AND (a.fecha_retiro IS NULL OR CURRENT_DATE<=a.fecha_retiro)) vigente
 FROM Participa a JOIN Profesor r ON r.cedula=a.cedula JOIN Proyecto_investigacion p ON p.codigo_proyecto=a.codigo_proyecto;

DELIMITER $$
CREATE PROCEDURE sp_presupuesto(IN facultad INT,IN desde DATE,IN hasta DATE)
BEGIN
 IF desde IS NULL OR hasta IS NULL OR desde>hasta THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Periodo invalido'; END IF;
 SELECT COUNT(*) proyectos,COALESCE(SUM(presupuesto),0) presupuesto FROM v_proyectos WHERE facultad_id=facultad AND inicio<=hasta AND fin>=desde;
END$$
CREATE PROCEDURE sp_validar_participacion(IN registro INT,IN proyecto VARCHAR(30),IN profesor VARCHAR(20),IN desde DATE,IN hasta DATE)
BEGIN
 DECLARE ini DATE;DECLARE fin DATE;DECLARE bloqueo VARCHAR(30);
 SELECT codigo_proyecto,fecha_inicio,fecha_fin INTO bloqueo,ini,fin FROM Proyecto_investigacion WHERE codigo_proyecto=proyecto FOR UPDATE;
 IF desde IS NULL OR desde<ini OR desde>fin OR (hasta IS NOT NULL AND (hasta<desde OR hasta>fin)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Participacion fuera de las fechas del proyecto'; END IF;
 IF EXISTS(SELECT 1 FROM Participa WHERE codigo_proyecto=proyecto AND cedula=profesor AND id<>registro AND fecha_incorporacion<=COALESCE(hasta,fin) AND COALESCE(fecha_retiro,fin)>=desde) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El profesor ya participa durante ese periodo'; END IF;
END$$

-- El alta administrativa de facultad se realiza con scripts/crear_facultad.php y una transacción PHP.

CREATE TRIGGER tr_participacion_insert BEFORE INSERT ON Participa FOR EACH ROW
BEGIN CALL sp_validar_participacion(0,NEW.codigo_proyecto,NEW.cedula,NEW.fecha_incorporacion,NEW.fecha_retiro);END$$
CREATE TRIGGER tr_participacion_update BEFORE UPDATE ON Participa FOR EACH ROW
BEGIN CALL sp_validar_participacion(OLD.id,NEW.codigo_proyecto,NEW.cedula,NEW.fecha_incorporacion,NEW.fecha_retiro);END$$

-- PHP valida nombre por facultad, períodos estudiantiles y fechas de proyecto al guardar.
-- PK, FK y CHECK permanecen en MySQL. La edición SQL directa debe respetar los controles PHP.

DELIMITER ;

-- Datos ficticios de instalación. Clave pública de las cuentas: Universidad2026!
INSERT INTO `Facultad` (`id_facultad`,`nombre`,`sede`) VALUES('1','Facultad Tecnológica','Sede Tecnológica');
INSERT INTO `Facultad` (`id_facultad`,`nombre`,`sede`) VALUES('2','Facultad de Ingeniería','Sede Central');
INSERT INTO `Facultad` (`id_facultad`,`nombre`,`sede`) VALUES('3','Facultad de Ciencias y Educación','Sede Central');
INSERT INTO `Facultad` (`id_facultad`,`nombre`,`sede`) VALUES('4','Facultad del Medio Ambiente y Recursos Naturales','Sede Central');
INSERT INTO `Profesor` (`id`,`cedula`,`nombre`,`tiempo_Experiencia`) VALUES('1','10000001','Laura Martínez','12');
INSERT INTO `Profesor` (`id`,`cedula`,`nombre`,`tiempo_Experiencia`) VALUES('2','10000002','Carlos Ramírez','6');
INSERT INTO `Profesor` (`id`,`cedula`,`nombre`,`tiempo_Experiencia`) VALUES('3','10000003','Ana Torres','10');
INSERT INTO `Profesor` (`id`,`cedula`,`nombre`,`tiempo_Experiencia`) VALUES('4','10000004','Marta Rojas','0');
INSERT INTO `Profesor` (`id`,`cedula`,`nombre`,`tiempo_Experiencia`) VALUES('5','10000005','Diego Castro','5');
INSERT INTO `Profesor` (`id`,`cedula`,`nombre`,`tiempo_Experiencia`) VALUES('6','10000006','Jorge Medina','0');
INSERT INTO `Profesor` (`id`,`cedula`,`nombre`,`tiempo_Experiencia`) VALUES('7','10000007','Paula Gómez','5');
INSERT INTO `Grupo_investigacion` (`id`,`codigo_grupo`,`nombre`,`area_interes`) VALUES('1','G-SOFT','Software y sociedad','Ingeniería de software');
INSERT INTO `Grupo_investigacion` (`id`,`codigo_grupo`,`nombre`,`area_interes`) VALUES('2','G-IA','Inteligencia aplicada','Inteligencia artificial');
INSERT INTO `Grupo_investigacion` (`id`,`codigo_grupo`,`nombre`,`area_interes`) VALUES('3','G-EDU','Educación y sociedad','Educación y pedagogía');
INSERT INTO `Grupo_investigacion` (`id`,`codigo_grupo`,`nombre`,`area_interes`) VALUES('4','G-AMB','Ambiente y sostenibilidad','Gestión ambiental');
INSERT INTO `Facultad_telefono` (`id`,`id_facultad`,`telefono`) VALUES('1','1','6015550101');
INSERT INTO `Facultad_telefono` (`id`,`id_facultad`,`telefono`) VALUES('2','2','6015550102');
INSERT INTO `Facultad_telefono` (`id`,`id_facultad`,`telefono`) VALUES('3','3','6015550103');
INSERT INTO `Facultad_telefono` (`id`,`id_facultad`,`telefono`) VALUES('4','4','6015550104');
INSERT INTO `profesor_titulo` (`id`,`Cedula`,`titulo`) VALUES('1','10000001','Doctorado en Ingeniería');
INSERT INTO `profesor_titulo` (`id`,`Cedula`,`titulo`) VALUES('2','10000002','Maestría en Sistemas');
INSERT INTO `profesor_titulo` (`id`,`Cedula`,`titulo`) VALUES('3','10000003','Doctorado en Computación');
INSERT INTO `profesor_titulo` (`id`,`Cedula`,`titulo`) VALUES('4','10000004','Doctorado en Educación');
INSERT INTO `profesor_titulo` (`id`,`Cedula`,`titulo`) VALUES('5','10000005','Maestría en Educación');
INSERT INTO `profesor_titulo` (`id`,`Cedula`,`titulo`) VALUES('6','10000006','Doctorado en Ciencias Ambientales');
INSERT INTO `profesor_titulo` (`id`,`Cedula`,`titulo`) VALUES('7','10000007','Maestría en Gestión Ambiental');
INSERT INTO `Es_decano` (`cedula`,`id_facultad`) VALUES('10000001','1');
INSERT INTO `Es_decano` (`cedula`,`id_facultad`) VALUES('10000003','2');
INSERT INTO `Es_decano` (`cedula`,`id_facultad`) VALUES('10000004','3');
INSERT INTO `Es_decano` (`cedula`,`id_facultad`) VALUES('10000006','4');
INSERT INTO `Tiene_grupo` (`codigo_grupo`,`id_facultad`) VALUES('G-SOFT','1');
INSERT INTO `Tiene_grupo` (`codigo_grupo`,`id_facultad`) VALUES('G-IA','2');
INSERT INTO `Tiene_grupo` (`codigo_grupo`,`id_facultad`) VALUES('G-EDU','3');
INSERT INTO `Tiene_grupo` (`codigo_grupo`,`id_facultad`) VALUES('G-AMB','4');
INSERT INTO `Lidera` (`cedula`,`codigo_grupo`) VALUES('10000001','G-SOFT');
INSERT INTO `Lidera` (`cedula`,`codigo_grupo`) VALUES('10000003','G-IA');
INSERT INTO `Lidera` (`cedula`,`codigo_grupo`) VALUES('10000004','G-EDU');
INSERT INTO `Lidera` (`cedula`,`codigo_grupo`) VALUES('10000006','G-AMB');
INSERT INTO `Pertenece` (`cedula`,`codigo_grupo`) VALUES('10000001','G-SOFT');
INSERT INTO `Pertenece` (`cedula`,`codigo_grupo`) VALUES('10000002','G-SOFT');
INSERT INTO `Pertenece` (`cedula`,`codigo_grupo`) VALUES('10000003','G-IA');
INSERT INTO `Pertenece` (`cedula`,`codigo_grupo`) VALUES('10000004','G-EDU');
INSERT INTO `Pertenece` (`cedula`,`codigo_grupo`) VALUES('10000005','G-EDU');
INSERT INTO `Pertenece` (`cedula`,`codigo_grupo`) VALUES('10000006','G-AMB');
INSERT INTO `Pertenece` (`cedula`,`codigo_grupo`) VALUES('10000007','G-AMB');
INSERT INTO `Usuario` (`id_usuario`,`correo`,`password`,`rol`) VALUES('1','decano@example.test','$2y$10$tErMlG5ZBRsTRHsemexAu.KfZFlvhqlG7XRds2cvGhXErqIe9GUci','DECANO');
INSERT INTO `Usuario` (`id_usuario`,`correo`,`password`,`rol`) VALUES('2','docente@example.test','$2y$10$tErMlG5ZBRsTRHsemexAu.KfZFlvhqlG7XRds2cvGhXErqIe9GUci','DOCENTE');
INSERT INTO `Usuario` (`id_usuario`,`correo`,`password`,`rol`) VALUES('3','decano.ingenieria@example.test','$2y$10$tErMlG5ZBRsTRHsemexAu.KfZFlvhqlG7XRds2cvGhXErqIe9GUci','DECANO');
INSERT INTO `Usuario` (`id_usuario`,`correo`,`password`,`rol`) VALUES('4','decano.ciencias@example.test','$2y$10$tErMlG5ZBRsTRHsemexAu.KfZFlvhqlG7XRds2cvGhXErqIe9GUci','DECANO');
INSERT INTO `Usuario` (`id_usuario`,`correo`,`password`,`rol`) VALUES('5','profesor.ciencias@example.test','$2y$10$tErMlG5ZBRsTRHsemexAu.KfZFlvhqlG7XRds2cvGhXErqIe9GUci','DOCENTE');
INSERT INTO `Usuario` (`id_usuario`,`correo`,`password`,`rol`) VALUES('6','decano.ambiente@example.test','$2y$10$tErMlG5ZBRsTRHsemexAu.KfZFlvhqlG7XRds2cvGhXErqIe9GUci','DECANO');
INSERT INTO `Usuario` (`id_usuario`,`correo`,`password`,`rol`) VALUES('7','profesor.ambiente@example.test','$2y$10$tErMlG5ZBRsTRHsemexAu.KfZFlvhqlG7XRds2cvGhXErqIe9GUci','DOCENTE');
INSERT INTO `Tiene` (`id_usuario`,`cedula`) VALUES('1','10000001');
INSERT INTO `Tiene` (`id_usuario`,`cedula`) VALUES('2','10000002');
INSERT INTO `Tiene` (`id_usuario`,`cedula`) VALUES('3','10000003');
INSERT INTO `Tiene` (`id_usuario`,`cedula`) VALUES('4','10000004');
INSERT INTO `Tiene` (`id_usuario`,`cedula`) VALUES('5','10000005');
INSERT INTO `Tiene` (`id_usuario`,`cedula`) VALUES('6','10000006');
INSERT INTO `Tiene` (`id_usuario`,`cedula`) VALUES('7','10000007');
INSERT INTO `Proyecto_investigacion` (`id`,`codigo_proyecto`,`nombre`,`presupuesto`,`fecha_inicio`,`fecha_fin`) VALUES('1','P-001','Observatorio de investigación','45000000.00','2026-01-01','2026-12-31');
INSERT INTO `Proyecto_investigacion` (`id`,`codigo_proyecto`,`nombre`,`presupuesto`,`fecha_inicio`,`fecha_fin`) VALUES('2','P-002','Archivo digital universitario','18000000.00','2025-01-01','2025-12-31');
INSERT INTO `Proyecto_investigacion` (`id`,`codigo_proyecto`,`nombre`,`presupuesto`,`fecha_inicio`,`fecha_fin`) VALUES('3','P-003','Analítica para la permanencia','62000000.00','2026-06-01','2027-06-30');
INSERT INTO `Desarrolla` (`codigo_proyecto`,`codigo_grupo`) VALUES('P-001','G-SOFT');
INSERT INTO `Desarrolla` (`codigo_proyecto`,`codigo_grupo`) VALUES('P-002','G-SOFT');
INSERT INTO `Desarrolla` (`codigo_proyecto`,`codigo_grupo`) VALUES('P-003','G-IA');
INSERT INTO `Participa` (`id`,`cedula`,`codigo_proyecto`,`fecha_incorporacion`,`fecha_retiro`,`es_responsable`,`dedicacion`) VALUES('1','10000001','P-001','2026-01-01',NULL,'1','40.00');
INSERT INTO `Participa` (`id`,`cedula`,`codigo_proyecto`,`fecha_incorporacion`,`fecha_retiro`,`es_responsable`,`dedicacion`) VALUES('2','10000002','P-001','2026-02-01',NULL,'0','25.00');
INSERT INTO `Participa` (`id`,`cedula`,`codigo_proyecto`,`fecha_incorporacion`,`fecha_retiro`,`es_responsable`,`dedicacion`) VALUES('3','10000001','P-002','2025-01-01','2025-12-31','1','20.00');
INSERT INTO `Participa` (`id`,`cedula`,`codigo_proyecto`,`fecha_incorporacion`,`fecha_retiro`,`es_responsable`,`dedicacion`) VALUES('4','10000003','P-003','2026-06-01',NULL,'1','50.00');
INSERT INTO `Estudiante` (`id`,`codigo_estudiante`,`nombre`,`correo`,`registrado_por`) VALUES('1','20260001','Sofía Pérez','sofia@example.test','10000002');
INSERT INTO `Investiga` (`id`,`codigo_estudiante`,`codigo_proyecto`,`fecha_inicio`,`fecha_retiro`) VALUES('1','20260001','P-001','2026-01-01','2026-12-31');
INSERT INTO `Investiga` (`id`,`codigo_estudiante`,`codigo_proyecto`,`fecha_inicio`,`fecha_retiro`) VALUES('2','20260001','P-002','2025-01-01','2025-12-31');
