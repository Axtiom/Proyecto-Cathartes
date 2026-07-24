-- =====================================================================
--  Cathartes / SiGeRU — Siembra mínima de datos de prueba (Fase 0.5)
--  Proyecto de Pasaje de Grado 2026 — Axtiom
--
--  Objetivo: dataset a escala mínima para verificar las pantallas de las
--  fases siguientes con DATOS REALES (no demo). Contenido ficticio, filas
--  reales; ubicaciones de Montevideo.
--
--  REGLAS DE ESTE SCRIPT
--    - Solo INSERT. No lleva DELETE / TRUNCATE / DROP: estos datos se
--      reemplazan más adelante por siembra ficticia de mayor volumen, y el
--      borrado se hará en ese momento.
--    - IDs explícitos en todas las filas (FK legibles, reproducible).
--    - Orden de inserción respetando las FK.
--    - Charset utf8mb4; se asume la base `cathartes` ya creada (db/BD.sql).
--
--  GOTCHAS DEL DDL RESPETADOS
--    - La columna de contraseña es `contrasena` (SIN ñ). El documento de
--      entrega dice `contraseña` con ñ: EL DDL MANDA (ver BUG-003).
--    - El MER no tiene columnas de latitud/longitud: las coordenadas van
--      embebidas en el texto de `ubicacion`. (Ver reporte de discrepancias.)
--
--  Los 4 usuarios comparten la contraseña `Cathartes2026`. El hash de abajo
--  es password_hash('Cathartes2026', PASSWORD_DEFAULT) de PHP (bcrypt): la API
--  valida con password_verify(). NO usar SHA2()/MD5() de MySQL.
-- =====================================================================

USE cathartes;

-- ---------------------------------------------------------------------
-- 1) Instalación (centro de acopio al 85% → dispara alerta de umbral 80%)
-- ---------------------------------------------------------------------
INSERT INTO instalacion (ID, nombre, ubicacion, latitud, longitud, capacidad, ocupacion_actual, tipo) VALUES
(1, 'Centro de Acopio Buceo', 'Av. Rivera 3900, Buceo, Montevideo', -34.907500, -56.138500, 1000, 850, 'centro de acopio');

-- ---------------------------------------------------------------------
-- 2) Camión
-- ---------------------------------------------------------------------
INSERT INTO camion (ID, matricula, modelo, capacidad, estado) VALUES
(1, 'EME 542', 'Mercedes-Benz Atego', 8, 'funcional');

-- ---------------------------------------------------------------------
-- 3) Usuarios (todos activos; misma contraseña Cathartes2026)
-- ---------------------------------------------------------------------
INSERT INTO usuario (ID, nombre, email, `contrasena`, estado) VALUES
(1, 'Galvan de Saero', 'admin@cathartes.uy',     '$2y$10$z5mGXNm4VRBFHo/.c9.izev.4odeY.Kbjzmu8twLnhyLnA9lhDZUy', 'activo'),
(2, 'Amelia Garfan',   'operario@cathartes.uy',  '$2y$10$z5mGXNm4VRBFHo/.c9.izev.4odeY.Kbjzmu8twLnhyLnA9lhDZUy', 'activo'),
(3, 'Cuadrilla Alfa',  'cuadrilla@cathartes.uy', '$2y$10$z5mGXNm4VRBFHo/.c9.izev.4odeY.Kbjzmu8twLnhyLnA9lhDZUy', 'activo'),
(4, 'Juan Pérez',      'vecino@correo.com',      '$2y$10$z5mGXNm4VRBFHo/.c9.izev.4odeY.Kbjzmu8twLnhyLnA9lhDZUy', 'activo');

-- ---------------------------------------------------------------------
-- 4) Teléfonos (uno por usuario, formato uruguayo)
-- ---------------------------------------------------------------------
INSERT INTO usuario_telefono (id_tel, telefono, ID_usuario) VALUES
(1, '091 234 567', 1),
(2, '092 345 678', 2),
(3, '093 456 789', 3),
(4, '094 567 890', 4);

-- ---------------------------------------------------------------------
-- 5) Subtipos de usuario (el rol se deduce de acá, no es columna)
-- ---------------------------------------------------------------------
INSERT INTO administrador (ID_usuario, jefe) VALUES (1, 'si');
INSERT INTO operario (ID_usuario, ID_instalacion) VALUES (2, 1);
-- usuario.nombre ES el nombre del equipo (la cuadrilla es una cuenta compartida).
INSERT INTO cuadrilla (ID_usuario, ID_camion, estado) VALUES (3, 1, 'en servicio');
-- saldo = 150: cierra la aritmética de gamificación (ver bloque 11).
INSERT INTO vecino (ID_usuario, saldo) VALUES (4, 150);

-- ---------------------------------------------------------------------
-- 6) Rutas (dos zonas distintas, para ver que filtros y joins las distinguen)
-- ---------------------------------------------------------------------
INSERT INTO ruta (ID, nombre, zona, frecuencia, estado, ID_instalacion) VALUES
(1, 'Buceo — Pocitos',          'Buceo',   'diaria',                     'activa', 1),
(2, 'Pocitos — Punta Carretas', 'Pocitos', 'lunes, miércoles y viernes', 'activa', 1);

-- Cuadrilla Alfa toma la ruta 1 (Buceo). `toma` es temporal: la PK incluye `fecha`.
INSERT INTO toma (ID_cuadrilla, ID_ruta, fecha) VALUES (3, 1, NOW());

-- ---------------------------------------------------------------------
-- 7) Contenedores (todos en la ruta 1; ninguno dado de baja)
-- ---------------------------------------------------------------------
INSERT INTO contenedor (ID, ubicacion, latitud, longitud, tipo, estado, fecha_baja, motivo_baja, ID_ruta) VALUES
(1, 'Av. Rivera 3245, Buceo',   -34.904800, -56.142000, 'basura',     'funcional',    NULL, NULL, 1),
(2, 'Br. España 2650, Pocitos', -34.911000, -56.150000, 'basura',     'desbordado',   NULL, NULL, 1),
(3, 'Av. Brasil 3100, Pocitos', -34.909500, -56.147000, 'reciclable', 'no funcional', NULL, NULL, 1);

-- ---------------------------------------------------------------------
-- 8) Recompensas (catálogo de gamificación)
-- ---------------------------------------------------------------------
INSERT INTO recompensa (ID, nombre, descripcion, valor) VALUES
(1, 'Marco «Reciclador»',            'Marco de perfil para vecinos recicladores.', 300),
(2, 'Insignia «Vecino comprometido»','Insignia visible en el perfil.',             250),
(3, 'Mural de vecinos',              'Lugar en el mural público de vecinos.',      800);

-- ---------------------------------------------------------------------
-- 9) Incidencias (todas reportadas por el vecino, ID 4)
--    Las 4 combinaciones útiles de validada+estado, para los filtros del front.
--    fecha_ing relativa a hoy (DATE_SUB) para que siempre luzca "reciente".
-- ---------------------------------------------------------------------
INSERT INTO incidencia_historial (ID_incidencia, fecha_ing, descripcion, tipo, validada, estado, ID_usuario, ID_contenedor) VALUES
(1, DATE_SUB(NOW(), INTERVAL 20 DAY), 'Contenedor desbordado sobre la vereda.',   'desbordado',           'si', 'finalizada', 4, 2),
(2, DATE_SUB(NOW(), INTERVAL 10 DAY), 'Tapa rota, no cierra.',                     'roto',                 'si', 'en curso',   4, 3),
(3, DATE_SUB(NOW(), INTERVAL  5 DAY), 'Hace días que no pasa la recolección.',     'falta de recoleccion', 'si', 'en curso',   4, 1),
(4, DATE_SUB(NOW(), INTERVAL  2 DAY), 'Volvió a desbordarse.',                     'desbordado',           'no', 'en curso',   4, 2);

-- ---------------------------------------------------------------------
-- 10) Intervención sobre la incidencia 1 (cuadrilla 3), ya finalizada.
--     Coherencia temporal: reporte (-20d) → inicio (-18d) → fin (-17d).
-- ---------------------------------------------------------------------
INSERT INTO intervencion (ID_intervencion, fecha_inicio, fecha_fin, tarea_finalizada, ID_incidencia, ID_cuadrilla) VALUES
(1, DATE_SUB(NOW(), INTERVAL 18 DAY), DATE_SUB(NOW(), INTERVAL 17 DAY), 'si', 1, 3);

-- ---------------------------------------------------------------------
-- 11) Canje (gamificación). La aritmética debe cerrar EXACTO:
--       3 incidencias validadas × 150 = 450  →  450 − 300 (canje) = 150 = vecino.saldo
--     (El saldo lo mantiene la API de Usuarios; acá se siembra consistente.)
-- ---------------------------------------------------------------------
INSERT INTO canje (ID_vecino, ID_recompensa, fecha, puntos_gastados) VALUES
(4, 1, DATE_SUB(NOW(), INTERVAL 1 DAY), 300);

-- ---------------------------------------------------------------------
-- 12) Notificaciones para el vecino 4
-- ---------------------------------------------------------------------
INSERT INTO notificacion (ID, ID_usuario, ID_incidencia, tipo, mensaje, fecha, leida) VALUES
(1, 4, 3, 'incidencia validada',  'Tu incidencia fue validada. +150 puntos.',   DATE_SUB(NOW(), INTERVAL 5 DAY), 'si'),
(2, 4, 1, 'incidencia finalizada','Tu incidencia fue resuelta por la cuadrilla.', DATE_SUB(NOW(), INTERVAL 17 DAY), 'no');

-- =====================================================================
--  Fin del seed. Verificación en la "compuerta" de la Fase 0.5.
-- =====================================================================
