-- ============================================================================
-- db/migracion-2026-07-24.sql
-- Fase 3.9 — Reparación y adiciones al DDL de `cathartes`.
-- Autorizado explícitamente por Juan. Se documentará en MER, pasaje a tablas y
-- diccionario de datos. Ejecutable de una sola pasada. NO push.
--
-- Recordatorio: la columna de contraseña es `contrasena` (sin ñ) en el DDL; en el
-- MER/diccionario figura con ñ (modelo conceptual). Discrepancia deliberada, no se toca.
-- ============================================================================
USE cathartes;

-- ----------------------------------------------------------------------------
-- 1) REPARACIÓN "Cuadrilla toma Ruta": SIN EFECTO.
--    El MD daba por hecho que `toma` nunca llegó al DDL. FALSO: la tabla YA EXISTE
--    en la base viva, con PK (ID_cuadrilla, ID_ruta, `fecha`) — el mismo patrón
--    temporal que autorizacion/gestiona/verificacion. Regla cero: el DDL gana.
--    Decisión de Juan (2026-07-24): NO se toca `toma`; solo se siembra (ver §7).
--    Por eso acá NO hay CREATE TABLE.
-- ----------------------------------------------------------------------------

-- ----------------------------------------------------------------------------
-- 2) ADICIÓN 8ª: ruta.zona (NOT NULL) + ruta.estado (ENUM).
--    zona habilita el filtro por zona en Incidencias del backoffice.
--    estado: enum('activa','inactiva') (confirmado por Juan).
--    zona se agrega con DEFAULT '' temporal para no romper la fila existente;
--    se rellena y luego se le quita el default (queda NOT NULL sin default).
-- ----------------------------------------------------------------------------
ALTER TABLE `ruta`
  ADD COLUMN `zona`   varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' AFTER `nombre`,
  ADD COLUMN `estado` enum('activa','inactiva') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'activa' AFTER `frecuencia`;
  
UPDATE `ruta` SET `zona` = 'Buceo' WHERE `ID` = 1;
ALTER TABLE `ruta` ALTER COLUMN `zona` DROP DEFAULT;

-- ----------------------------------------------------------------------------
-- 3) ADICIÓN 9ª: camion.fecha_baja + camion.motivo_baja.
--    Análogos exactos a los de `contenedor` (mismo tipo, misma nulabilidad).
--    Quedan NULL: el camión sembrado está funcional.
-- ----------------------------------------------------------------------------
ALTER TABLE `camion`
  ADD COLUMN `fecha_baja`  datetime DEFAULT NULL AFTER `estado`,
  ADD COLUMN `motivo_baja` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `fecha_baja`;

-- ----------------------------------------------------------------------------
-- 4) ADICIÓN 10ª: cuadrilla.estado (eje OPERATIVO).
--    Distinto de usuario.estado (activo/no activo = estado de CUENTA).
--    enum('en servicio','fuera de servicio') (confirmado por Juan).
-- ----------------------------------------------------------------------------
ALTER TABLE `cuadrilla`
  ADD COLUMN `estado` enum('en servicio','fuera de servicio') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en servicio' AFTER `ID_camion`;

-- ----------------------------------------------------------------------------
-- 6) AJUSTE DE DATOS: el nombre de la cuadrilla es el nombre de EQUIPO.
--    La cuadrilla es subtipo de Usuario y comparte una sola cuenta:
--    usuario.nombre ES el nombre del equipo. Se renombra la fila sembrada.
-- ----------------------------------------------------------------------------
UPDATE `usuario` SET `nombre` = 'Cuadrilla Alfa' WHERE `ID` IN (SELECT `ID_usuario` FROM `cuadrilla`);

-- ----------------------------------------------------------------------------
-- 7) SEMBRADO MÍNIMO (a propósito): una 2ª ruta para distinguir dos zonas en
--    filtros/joins, y una fila en `toma`. NO se amplía el resto del seed.
--    (cuadrilla.estado quedó 'en servicio' por el DEFAULT; ruta.estado 'activa'.)
-- ----------------------------------------------------------------------------
INSERT INTO `ruta` (`nombre`, `zona`, `frecuencia`, `estado`, `ID_instalacion`)
VALUES ('Pocitos — Punta Carretas', 'Pocitos', 'lunes, miércoles y viernes', 'activa', 1);

-- `toma` ya existe con `fecha` en la PK: el seed la incluye (NOW()).
-- Vincula la cuadrilla sembrada con la ruta 1 (Buceo).
INSERT INTO `toma` (`ID_cuadrilla`, `ID_ruta`, `fecha`)
SELECT c.`ID_usuario`, 1, NOW() FROM `cuadrilla` c LIMIT 1;
