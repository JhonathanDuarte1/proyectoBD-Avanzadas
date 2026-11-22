-- MySQL 8.0.16+. Instalar con credenciales de mantenimiento después de estructura y datos.
-- Las escrituras de la aplicación usan SOLO los procedimientos públicos concedidos.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE OR REPLACE VIEW v_cartera_proyectos AS
 WITH docentes AS (SELECT proyecto_id,COUNT(DISTINCT profesor_id) profesores FROM participaciones GROUP BY proyecto_id),
 alumnos AS (SELECT proyecto_id,COUNT(*) estudiantes FROM estudiante_proyecto GROUP BY proyecto_id)
 SELECT p.id,p.codigo,p.nombre,p.facultad_id,p.grupo_id,p.estado,p.presupuesto,
 COALESCE(d.profesores,0) profesores,COALESCE(a.estudiantes,0) estudiantes,
 DENSE_RANK() OVER(PARTITION BY p.facultad_id ORDER BY p.presupuesto DESC) prioridad_presupuesto
 FROM v_proyectos p LEFT JOIN docentes d ON d.proyecto_id=p.id LEFT JOIN alumnos a ON a.proyecto_id=p.id;
CREATE OR REPLACE VIEW v_resumen_facultad AS
 SELECT f.id facultad_id,f.nombre,COUNT(p.id) total,
 COALESCE(SUM(p.estado='ACTIVO'),0) activos,COALESCE(SUM(p.estado='TERMINADO'),0) terminados,
 COALESCE(SUM(p.presupuesto),0) presupuesto FROM facultades f LEFT JOIN v_proyectos p ON p.facultad_id=f.id GROUP BY f.id,f.nombre;
DELIMITER $$
DROP PROCEDURE IF EXISTS sp_actor$$
CREATE PROCEDURE sp_actor(IN actor INT,OUT profesor INT,OUT grupo INT,OUT facultad INT,OUT perfil VARCHAR(10)) SQL SECURITY DEFINER
BEGIN
 DECLARE fac_grupo INT;
 SET profesor=NULL;
 SELECT u.profesor_id,p.grupo_id,g.facultad_id,u.rol INTO profesor,grupo,facultad,perfil
 FROM usuarios u JOIN profesores p ON p.id=u.profesor_id JOIN grupos g ON g.id=p.grupo_id WHERE u.id=actor;
 IF profesor IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Cuenta no autorizada'; END IF;
 IF perfil='DECANO' THEN
  SET fac_grupo=facultad;SET facultad=NULL;
  SELECT f.id INTO facultad FROM facultades f WHERE f.decano_id=profesor ORDER BY (f.id=fac_grupo) DESC,f.id LIMIT 1;
  IF facultad IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='La cuenta no tiene un decanato vigente';END IF;
 END IF;
END$$
DROP PROCEDURE IF EXISTS sp_guardar$$
CREATE PROCEDURE sp_guardar(IN actor INT,IN entidad VARCHAR(40),IN registro INT,IN datos JSON) SQL SECURITY DEFINER
BEGIN
 DECLARE prof INT; DECLARE grupo INT; DECLARE fac INT; DECLARE perfil VARCHAR(10);
 DECLARE destino INT; DECLARE anterior INT; DECLARE relacionado INT; DECLARE resultado INT;
 DECLARE nombre_val VARCHAR(180); DECLARE codigo_val VARCHAR(30); DECLARE correo_val VARCHAR(180);
 DECLARE fecha1 DATE; DECLARE fecha2 DATE; DECLARE porcentaje DECIMAL(5,2); DECLARE responsable_val INT;
 DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
 SET TRANSACTION ISOLATION LEVEL READ COMMITTED;
 START TRANSACTION;
 CALL sp_actor(actor,prof,grupo,fac,perfil);
 IF registro<0 OR datos IS NULL OR JSON_TYPE(datos)<>'OBJECT' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Datos invalidos'; END IF;
 SET resultado=registro;
 SET nombre_val=TRIM(JSON_UNQUOTE(JSON_EXTRACT(datos,'$.nombre')));
 SET codigo_val=TRIM(JSON_UNQUOTE(JSON_EXTRACT(datos,'$.codigo')));
 SET correo_val=TRIM(JSON_UNQUOTE(JSON_EXTRACT(datos,'$.correo')));
 CASE entidad
 WHEN 'proyectos' THEN
  IF perfil<>'DOCENTE' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Solo docentes gestionan proyectos'; END IF;
  SET destino=JSON_UNQUOTE(JSON_EXTRACT(datos,'$.grupo_id'));
  IF destino IS NULL OR destino<>grupo THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Grupo no autorizado'; END IF;
  SET fecha1=JSON_UNQUOTE(JSON_EXTRACT(datos,'$.inicio')); SET fecha2=JSON_UNQUOTE(JSON_EXTRACT(datos,'$.fin'));
  IF nombre_val IS NULL OR nombre_val='' OR codigo_val IS NULL OR codigo_val='' OR fecha1 IS NULL OR fecha2 IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Completa los datos del proyecto'; END IF;
  IF registro=0 THEN
   SET porcentaje=JSON_UNQUOTE(JSON_EXTRACT(datos,'$.dedicacion_inicial'));
   IF porcentaje IS NULL OR porcentaje<=0 OR porcentaje>100 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Indica la dedicacion del responsable inicial entre 0 y 100'; END IF;
   INSERT INTO proyectos(codigo,nombre,presupuesto,inicio,fin,grupo_id) VALUES(codigo_val,nombre_val,JSON_UNQUOTE(JSON_EXTRACT(datos,'$.presupuesto')),fecha1,fecha2,grupo);
   SET resultado=LAST_INSERT_ID();
   INSERT INTO participaciones(proyecto_id,profesor_id,incorporacion,dedicacion,responsable) VALUES(resultado,prof,fecha1,porcentaje,1);
  ELSE
   SET anterior=NULL; SELECT grupo_id INTO anterior FROM proyectos WHERE id=registro FOR UPDATE;
   IF anterior IS NULL OR anterior<>grupo THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Proyecto no autorizado'; END IF;
   UPDATE proyectos SET codigo=codigo_val,nombre=nombre_val,presupuesto=JSON_UNQUOTE(JSON_EXTRACT(datos,'$.presupuesto')),inicio=fecha1,fin=fecha2 WHERE id=registro;
  END IF;
 WHEN 'profesores' THEN
  IF perfil<>'DECANO' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Solo el decano registra profesores'; END IF;
  SET destino=JSON_UNQUOTE(JSON_EXTRACT(datos,'$.grupo_id'));
  IF NOT EXISTS(SELECT 1 FROM grupos WHERE id=destino AND facultad_id=fac) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Grupo de otra facultad'; END IF;
  IF registro=0 THEN
   IF JSON_EXTRACT(datos,'$.titulo_inicial') IS NULL OR TRIM(JSON_UNQUOTE(JSON_EXTRACT(datos,'$.titulo_inicial')))='' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El profesor necesita su primer titulo'; END IF;
   INSERT INTO profesores(cedula,nombre,experiencia,grupo_id) VALUES(JSON_UNQUOTE(JSON_EXTRACT(datos,'$.cedula')),nombre_val,JSON_UNQUOTE(JSON_EXTRACT(datos,'$.experiencia')),destino);
   SET resultado=LAST_INSERT_ID();
   INSERT INTO titulos(profesor_id,nombre) VALUES(resultado,TRIM(JSON_UNQUOTE(JSON_EXTRACT(datos,'$.titulo_inicial'))));
  ELSE
   IF NOT EXISTS(SELECT 1 FROM profesores p JOIN grupos g ON g.id=p.grupo_id WHERE p.id=registro AND g.facultad_id=fac) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Profesor de otra facultad'; END IF;
   SELECT id INTO relacionado FROM profesores WHERE id=registro FOR UPDATE;
   UPDATE profesores SET cedula=JSON_UNQUOTE(JSON_EXTRACT(datos,'$.cedula')),nombre=nombre_val,experiencia=JSON_UNQUOTE(JSON_EXTRACT(datos,'$.experiencia')),grupo_id=destino WHERE id=registro;
  END IF;
 WHEN 'grupos' THEN
  IF perfil<>'DOCENTE' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Solo docentes gestionan grupos'; END IF;
  SET destino=JSON_UNQUOTE(JSON_EXTRACT(datos,'$.lider_id'));
  IF destino IS NULL OR NOT EXISTS(SELECT 1 FROM profesores WHERE id=destino) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Selecciona un profesor lider'; END IF;
  IF registro=0 THEN
   INSERT INTO grupos(codigo,nombre,facultad_id,area_id,lider_id) VALUES(codigo_val,nombre_val,fac,JSON_UNQUOTE(JSON_EXTRACT(datos,'$.area_id')),destino); SET resultado=LAST_INSERT_ID();
  ELSE
   IF registro<>grupo THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Grupo no autorizado'; END IF;
   UPDATE grupos SET codigo=codigo_val,nombre=nombre_val,area_id=JSON_UNQUOTE(JSON_EXTRACT(datos,'$.area_id')),lider_id=destino WHERE id=registro;
  END IF;
 WHEN 'titulos' THEN
  IF perfil<>'DECANO' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Solo el decano gestiona titulos'; END IF;
  SET destino=JSON_UNQUOTE(JSON_EXTRACT(datos,'$.profesor_id'));
  IF NOT EXISTS(SELECT 1 FROM v_profesores WHERE id=destino AND facultad_id=fac) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Profesor de otra facultad'; END IF;
  IF registro=0 THEN INSERT INTO titulos(profesor_id,nombre) VALUES(destino,nombre_val);SET resultado=LAST_INSERT_ID();
  ELSE
   IF NOT EXISTS(SELECT 1 FROM titulos t JOIN v_profesores p ON p.id=t.profesor_id WHERE t.id=registro AND p.facultad_id=fac) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Titulo no autorizado'; END IF;
   UPDATE titulos SET profesor_id=destino,nombre=nombre_val WHERE id=registro;
  END IF;
 WHEN 'participaciones' THEN
  IF perfil<>'DOCENTE' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Solo docentes gestionan participaciones'; END IF;
  SET destino=JSON_UNQUOTE(JSON_EXTRACT(datos,'$.proyecto_id'));
  SET anterior=destino;
  IF registro<>0 THEN SET anterior=NULL;SELECT proyecto_id INTO anterior FROM participaciones WHERE id=registro; END IF;
  IF anterior IS NULL OR NOT EXISTS(SELECT 1 FROM proyectos WHERE id=destino AND grupo_id=grupo) OR NOT EXISTS(SELECT 1 FROM proyectos WHERE id=anterior AND grupo_id=grupo) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Proyecto no autorizado'; END IF;
  -- Orden de bloqueo estable para traslados entre proyectos.
  SELECT id INTO relacionado FROM proyectos WHERE id=LEAST(anterior,destino) FOR UPDATE;
  SELECT id INTO relacionado FROM proyectos WHERE id=GREATEST(anterior,destino) FOR UPDATE;
  SET responsable_val=JSON_UNQUOTE(JSON_EXTRACT(datos,'$.responsable'));
  IF responsable_val IS NULL OR responsable_val NOT IN (0,1) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Responsable debe ser 0 o 1'; END IF;
  SET fecha1=JSON_UNQUOTE(JSON_EXTRACT(datos,'$.incorporacion'));
  SET fecha2=CASE WHEN JSON_EXTRACT(datos,'$.retiro') IS NULL OR JSON_TYPE(JSON_EXTRACT(datos,'$.retiro'))='NULL' THEN NULL ELSE JSON_UNQUOTE(JSON_EXTRACT(datos,'$.retiro')) END;
  SET porcentaje=JSON_UNQUOTE(JSON_EXTRACT(datos,'$.dedicacion'));
  SET relacionado=JSON_UNQUOTE(JSON_EXTRACT(datos,'$.profesor_id'));
  IF registro=0 THEN INSERT INTO participaciones(proyecto_id,profesor_id,incorporacion,retiro,dedicacion,responsable) VALUES(destino,relacionado,fecha1,fecha2,porcentaje,responsable_val);SET resultado=LAST_INSERT_ID();
  ELSE UPDATE participaciones SET proyecto_id=destino,profesor_id=relacionado,incorporacion=fecha1,retiro=fecha2,dedicacion=porcentaje,responsable=responsable_val WHERE id=registro; END IF;
  IF NOT EXISTS(SELECT 1 FROM participaciones WHERE proyecto_id=anterior AND responsable=1) OR NOT EXISTS(SELECT 1 FROM participaciones WHERE proyecto_id=destino AND responsable=1) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Cada proyecto debe conservar un profesor responsable'; END IF;
 WHEN 'estudiantes' THEN
  IF perfil<>'DOCENTE' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Solo docentes registran estudiantes'; END IF;
  IF registro=0 THEN INSERT INTO estudiantes(codigo,nombre,correo,registrado_por) VALUES(codigo_val,nombre_val,correo_val,prof);SET resultado=LAST_INSERT_ID();
  ELSE
   IF NOT EXISTS(SELECT 1 FROM estudiantes e JOIN profesores p ON p.id=e.registrado_por WHERE e.id=registro AND p.grupo_id=grupo) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Estudiante administrado por otro grupo'; END IF;
   UPDATE estudiantes SET codigo=codigo_val,nombre=nombre_val,correo=correo_val WHERE id=registro;
  END IF;
 WHEN 'estudiante_proyecto' THEN
  IF perfil<>'DOCENTE' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Solo docentes responsables gestionan estudiantes'; END IF;
  SET destino=JSON_UNQUOTE(JSON_EXTRACT(datos,'$.proyecto_id'));SET anterior=destino;
  IF registro<>0 THEN SET anterior=NULL;SELECT proyecto_id INTO anterior FROM estudiante_proyecto WHERE id=registro; END IF;
  IF anterior IS NULL OR NOT EXISTS(SELECT 1 FROM participaciones WHERE proyecto_id=destino AND profesor_id=prof AND responsable=1) OR NOT EXISTS(SELECT 1 FROM participaciones WHERE proyecto_id=anterior AND profesor_id=prof AND responsable=1) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Solo el profesor responsable gestiona los estudiantes del proyecto'; END IF;
  SELECT id INTO relacionado FROM proyectos WHERE id=LEAST(anterior,destino) FOR UPDATE;
  SELECT id INTO relacionado FROM proyectos WHERE id=GREATEST(anterior,destino) FOR UPDATE;
  SET relacionado=JSON_UNQUOTE(JSON_EXTRACT(datos,'$.estudiante_id'));
  IF registro=0 THEN INSERT INTO estudiante_proyecto(estudiante_id,proyecto_id) VALUES(relacionado,destino);SET resultado=LAST_INSERT_ID();
  ELSE UPDATE estudiante_proyecto SET estudiante_id=relacionado,proyecto_id=destino WHERE id=registro; END IF;
 ELSE SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Entidad no autorizada';
 END CASE;
 COMMIT;
 SELECT resultado id;
END$$
DROP PROCEDURE IF EXISTS sp_eliminar$$
CREATE PROCEDURE sp_eliminar(IN actor INT,IN entidad VARCHAR(40),IN registro INT) SQL SECURITY DEFINER
BEGIN
 DECLARE prof INT; DECLARE grupo INT; DECLARE fac INT; DECLARE perfil VARCHAR(10); DECLARE destino INT;
 DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK;RESIGNAL;END;
 START TRANSACTION;CALL sp_actor(actor,prof,grupo,fac,perfil);
 IF perfil<>'DOCENTE' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Solo docentes autorizados eliminan registros'; END IF;
 IF entidad='proyectos' THEN
  SET destino=NULL;SELECT id INTO destino FROM proyectos WHERE id=registro AND grupo_id=grupo FOR UPDATE;
  IF destino IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Proyecto no autorizado'; END IF;
  DELETE FROM proyectos WHERE id=registro;
 ELSEIF entidad='estudiante_proyecto' THEN
  SET destino=NULL;SELECT proyecto_id INTO destino FROM estudiante_proyecto WHERE id=registro;
  IF destino IS NULL OR NOT EXISTS(SELECT 1 FROM participaciones WHERE proyecto_id=destino AND profesor_id=prof AND responsable=1) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Solo el responsable puede retirar estudiantes'; END IF;
  SELECT id INTO destino FROM proyectos WHERE id=destino FOR UPDATE;
  DELETE FROM estudiante_proyecto WHERE id=registro;
 ELSE SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Eliminacion no autorizada';END IF;
 COMMIT;
END$$
DROP PROCEDURE IF EXISTS sp_cuenta$$
CREATE PROCEDURE sp_cuenta(IN actor INT,IN cedula_val VARCHAR(20),IN nombre_val VARCHAR(150),IN correo_val VARCHAR(180),IN hash_val VARCHAR(255)) SQL SECURITY DEFINER
BEGIN
 DECLARE prof INT;DECLARE grupo INT;DECLARE fac INT;DECLARE perfil VARCHAR(10);
 DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK;RESIGNAL;END;
 IF hash_val IS NULL OR LEFT(hash_val,1)<>'$' OR CHAR_LENGTH(hash_val)<20 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Se requiere un hash de clave valido';END IF;
 START TRANSACTION;CALL sp_actor(actor,prof,grupo,fac,perfil);
 UPDATE profesores SET cedula=cedula_val,nombre=nombre_val WHERE id=prof;
 UPDATE usuarios SET correo=correo_val,password_hash=hash_val WHERE id=actor;
 COMMIT;
END$$
DROP PROCEDURE IF EXISTS sp_usuario$$
CREATE PROCEDURE sp_usuario(IN actor INT,IN docente INT,IN correo_val VARCHAR(180),IN hash_val VARCHAR(255)) SQL SECURITY DEFINER
BEGIN
 DECLARE prof INT;DECLARE grupo INT;DECLARE fac INT;DECLARE perfil VARCHAR(10);
 IF hash_val IS NULL OR LEFT(hash_val,1)<>'$' OR CHAR_LENGTH(hash_val)<20 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Se requiere un hash de clave valido';END IF;
 CALL sp_actor(actor,prof,grupo,fac,perfil);
 IF perfil<>'DECANO' OR NOT EXISTS(SELECT 1 FROM v_profesores WHERE id=docente AND facultad_id=fac) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Solo el decano crea cuentas de su facultad'; END IF;
 INSERT INTO usuarios(profesor_id,correo,password_hash,rol) VALUES(docente,correo_val,hash_val,'DOCENTE');
END$$
DROP PROCEDURE IF EXISTS sp_presupuesto_usuario$$
CREATE PROCEDURE sp_presupuesto_usuario(IN actor INT,IN desde DATE,IN hasta DATE) SQL SECURITY DEFINER
BEGIN
 DECLARE prof INT;DECLARE grupo INT;DECLARE fac INT;DECLARE perfil VARCHAR(10);
 CALL sp_actor(actor,prof,grupo,fac,perfil);
 IF perfil<>'DECANO' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Consulta presupuestal reservada al decano'; END IF;
 CALL sp_presupuesto(fac,desde,hasta);
END$$
DROP PROCEDURE IF EXISTS sp_importar_estudiantes$$
CREATE PROCEDURE sp_importar_estudiantes(IN actor INT,IN datos JSON) SQL SECURITY DEFINER
BEGIN
 DECLARE prof INT;DECLARE grupo INT;DECLARE fac INT;DECLARE perfil VARCHAR(10);
 DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK;RESIGNAL;END;
 START TRANSACTION;CALL sp_actor(actor,prof,grupo,fac,perfil);
 IF perfil<>'DOCENTE' OR JSON_TYPE(datos)<>'ARRAY' OR JSON_LENGTH(datos)=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Lote de estudiantes invalido'; END IF;
 INSERT INTO estudiantes(codigo,nombre,correo,registrado_por)
 SELECT TRIM(j.codigo),TRIM(j.nombre),TRIM(j.correo),prof FROM JSON_TABLE(datos,'$[*]' COLUMNS(codigo VARCHAR(30) PATH '$.codigo' ERROR ON EMPTY,nombre VARCHAR(150) PATH '$.nombre' ERROR ON EMPTY,correo VARCHAR(180) PATH '$.correo' ERROR ON EMPTY)) j;
 COMMIT;
END$$
DROP PROCEDURE IF EXISTS sp_crear_facultad$$
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
DROP TRIGGER IF EXISTS tr_facultad_decano$$
CREATE TRIGGER tr_facultad_decano BEFORE UPDATE ON facultades FOR EACH ROW
BEGIN IF NEW.decano_id IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='La facultad requiere decano';END IF;END$$
DROP TRIGGER IF EXISTS tr_grupo_lider$$
CREATE TRIGGER tr_grupo_lider BEFORE UPDATE ON grupos FOR EACH ROW
BEGIN IF NEW.lider_id IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El grupo requiere lider';END IF;END$$
DROP TRIGGER IF EXISTS tr_titulo_minimo_delete$$
CREATE TRIGGER tr_titulo_minimo_delete BEFORE DELETE ON titulos FOR EACH ROW
BEGIN
 DECLARE bloqueo INT;SELECT id INTO bloqueo FROM profesores WHERE id=OLD.profesor_id FOR UPDATE;
 IF (SELECT COUNT(*) FROM titulos WHERE profesor_id=OLD.profesor_id)<=1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='No se puede retirar el ultimo titulo';END IF;
END$$
DROP TRIGGER IF EXISTS tr_titulo_minimo_update$$
CREATE TRIGGER tr_titulo_minimo_update BEFORE UPDATE ON titulos FOR EACH ROW
BEGIN
 DECLARE bloqueo INT;SELECT id INTO bloqueo FROM profesores WHERE id=OLD.profesor_id FOR UPDATE;
 IF NEW.profesor_id<>OLD.profesor_id AND (SELECT COUNT(*) FROM titulos WHERE profesor_id=OLD.profesor_id)<=1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='No se puede trasladar el ultimo titulo';END IF;
END$$
DROP TRIGGER IF EXISTS tr_telefono_minimo_delete$$
CREATE TRIGGER tr_telefono_minimo_delete BEFORE DELETE ON telefonos_facultad FOR EACH ROW
BEGIN
 DECLARE bloqueo INT;SELECT id INTO bloqueo FROM facultades WHERE id=OLD.facultad_id FOR UPDATE;
 IF (SELECT COUNT(*) FROM telefonos_facultad WHERE facultad_id=OLD.facultad_id)<=1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='No se puede retirar el ultimo telefono';END IF;
END$$
DROP TRIGGER IF EXISTS tr_telefono_minimo_update$$
CREATE TRIGGER tr_telefono_minimo_update BEFORE UPDATE ON telefonos_facultad FOR EACH ROW
BEGIN
 DECLARE bloqueo INT;SELECT id INTO bloqueo FROM facultades WHERE id=OLD.facultad_id FOR UPDATE;
 IF NEW.facultad_id<>OLD.facultad_id AND (SELECT COUNT(*) FROM telefonos_facultad WHERE facultad_id=OLD.facultad_id)<=1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='No se puede trasladar el ultimo telefono';END IF;
END$$
DROP TRIGGER IF EXISTS tr_responsable_minimo_update$$
CREATE TRIGGER tr_responsable_minimo_update BEFORE UPDATE ON participaciones FOR EACH ROW
BEGIN
 DECLARE bloqueo INT;SELECT id INTO bloqueo FROM proyectos WHERE id=OLD.proyecto_id FOR UPDATE;
 IF OLD.responsable=1 AND (NEW.responsable=0 OR NEW.proyecto_id<>OLD.proyecto_id) AND NOT EXISTS(SELECT 1 FROM participaciones WHERE proyecto_id=OLD.proyecto_id AND responsable=1 AND id<>OLD.id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='No se puede quitar el ultimo responsable';END IF;
END$$
DROP TRIGGER IF EXISTS tr_responsable_minimo_delete$$
CREATE TRIGGER tr_responsable_minimo_delete BEFORE DELETE ON participaciones FOR EACH ROW
BEGIN
 DECLARE bloqueo INT;SELECT id INTO bloqueo FROM proyectos WHERE id=OLD.proyecto_id FOR UPDATE;
 IF OLD.responsable=1 AND NOT EXISTS(SELECT 1 FROM participaciones WHERE proyecto_id=OLD.proyecto_id AND responsable=1 AND id<>OLD.id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='No se puede eliminar el ultimo responsable';END IF;
END$$
DROP TRIGGER IF EXISTS tr_proyecto_insert_auditoria$$
CREATE TRIGGER tr_proyecto_insert_auditoria AFTER INSERT ON proyectos FOR EACH ROW
BEGIN INSERT INTO auditoria(proyecto_id,accion,detalle) VALUES(NEW.id,'CREAR',CONCAT(USER(),': ',NEW.codigo));END$$
DROP TRIGGER IF EXISTS tr_proyecto_delete_auditoria$$
CREATE TRIGGER tr_proyecto_delete_auditoria BEFORE DELETE ON proyectos FOR EACH ROW
BEGIN INSERT INTO auditoria(proyecto_id,accion,detalle) VALUES(OLD.id,'ELIMINAR',CONCAT(USER(),': ',OLD.codigo));END$$
DROP TRIGGER IF EXISTS tr_proyecto_auditoria$$
CREATE TRIGGER tr_proyecto_auditoria AFTER UPDATE ON proyectos FOR EACH ROW
BEGIN INSERT INTO auditoria(proyecto_id,accion,detalle) VALUES(NEW.id,'ACTUALIZAR',CONCAT(USER(),': ',NEW.codigo,'; presupuesto ',OLD.presupuesto,' -> ',NEW.presupuesto));END$$
DELIMITER ;
INSERT IGNORE INTO schema_migrations(version) VALUES('20261119_integridad_seguridad');
