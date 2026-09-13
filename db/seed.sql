-- =====================================================================
--  Cathartes — Siembra de datos de prueba
--  Proyecto de Pasaje de Grado 2026 — Axtiom
--
--  Regenerado el 2026-07-27 A PARTIR DE LA BASE VIVA. Incluye tanto la
--  siembra original de la Fase 0.5 como todo lo que se fue cargando desde
--  los ABM del backoffice durante las fases 4 a 4.2 (una segunda
--  instalación, un segundo camión, una tercera ruta, incidencias con foto,
--  intervenciones abiertas y una segunda toma).
--
--  Objetivo: dataset a escala mínima para verificar las pantallas con DATOS
--  REALES (no demo). Contenido ficticio, filas reales; ubicaciones de
--  Montevideo.
--
--  REGLAS DE ESTE SCRIPT
--    - Solo INSERT. No lleva DELETE / TRUNCATE / DROP: el borrado lo hace
--      db/BD.sql, que arranca con DROP DATABASE.
--    - IDs explícitos en todas las filas (FK legibles, reproducible). Se
--      respetan los huecos de numeración que tiene la base viva: no se
--      renumera, porque las FK apuntan a esos IDs.
--    - Orden de inserción respetando las FK.
--    - Charset utf8mb4; se asume la base `cathartes` ya creada (db/BD.sql).
--
--  FECHAS RELATIVAS (no literales), a propósito
--    Todas las fechas se siembran como DATE_SUB(NOW(), INTERVAL n MINUTE),
--    con el desfase exacto que tenían en la base viva. Así el dataset no
--    envejece: las métricas del Dashboard y el gráfico de los últimos 6
--    meses de Informes siguen dando números vivos cada vez que se resiembra.
--    Al lado de cada valor va el equivalente en días, que es lo que se lee
--    de un vistazo.
--
--  GOTCHAS DEL DDL RESPETADOS
--    - La columna de contraseña es `contrasena` (SIN ñ). El documento de
--      entrega la nombra con ñ: EL DDL MANDA (ver BUG-003).
--    - Las coordenadas van en columnas propias `latitud`/`longitud`
--      DECIMAL(9,6), no embebidas en el texto de `ubicacion` (Fase 2.5).
--
--  Los 5 usuarios comparten la contraseña `Cathartes2026`. El hash de abajo
--  es password_hash('Cathartes2026', PASSWORD_DEFAULT) de PHP (bcrypt): la API
--  valida con password_verify(). NO usar SHA2()/MD5() de MySQL.
--
--  TABLAS QUE QUEDAN VACÍAS (no es un olvido): `repuestos`, `maquinaria`,
--  `usa`, `autorizacion`, `gestiona`, `verificacion`. Las pantallas que las
--  leen muestran su estado vacío, que es el comportamiento correcto.
-- =====================================================================

USE cathartes;

-- ---------------------------------------------------------------------
-- 1) Instalaciones
--    La 1 está al 85% → dispara la alerta de umbral (80%) en el operario,
--    en el backoffice y en el Dashboard. La 2 se cargó desde el ABM y no
--    tiene coordenadas: sirve para probar el aviso de "sin coordenadas"
--    del mapa.
-- ---------------------------------------------------------------------
INSERT INTO instalacion (ID, nombre, ubicacion, latitud, longitud, capacidad, ocupacion_actual, tipo) VALUES
(1, 'Centro de Acopio Buceo', 'Av. Rivera 3900, Buceo, Montevideo', -34.907500, -56.138500,  1000,  850, 'centro de acopio'),
(2, 'vertedero don ramon',    'me lo acaco de inventar',                  NULL,       NULL, 10000, 6878, 'vertedero');

-- ---------------------------------------------------------------------
-- 2) Camiones (ninguno dado de baja)
--    El 1 lo tiene asignado la cuadrilla; el 2 queda libre, para poder
--    probar la reasignación desde la pantalla de Cuadrillas.
-- ---------------------------------------------------------------------
INSERT INTO camion (ID, matricula, modelo, capacidad, estado, fecha_baja, motivo_baja) VALUES
(1, 'EME 542', 'Mercedes-Benz Atego',    100, 'funcional', NULL, NULL),
(2, 'ARE STP', 'Mercedes-Benz Atego', 100000, 'funcional', NULL, NULL);

-- ---------------------------------------------------------------------
-- 3) Usuarios (todos activos; misma contraseña Cathartes2026)
-- ---------------------------------------------------------------------
INSERT INTO usuario (ID, nombre, email, `contrasena`, estado) VALUES
(1, 'Galvan de Saero', 'admin@cathartes.uy',     '$2y$10$z5mGXNm4VRBFHo/.c9.izev.4odeY.Kbjzmu8twLnhyLnA9lhDZUy', 'activo'),
(2, 'Amelia Garfan',   'operario@cathartes.uy',  '$2y$10$z5mGXNm4VRBFHo/.c9.izev.4odeY.Kbjzmu8twLnhyLnA9lhDZUy', 'activo'),
(3, 'Cuadrilla Alfa',  'cuadrilla@cathartes.uy', '$2y$10$z5mGXNm4VRBFHo/.c9.izev.4odeY.Kbjzmu8twLnhyLnA9lhDZUy', 'activo'),
(4, 'Juan Pérez',      'vecino@correo.com',      '$2y$10$z5mGXNm4VRBFHo/.c9.izev.4odeY.Kbjzmu8twLnhyLnA9lhDZUy', 'activo'),
-- Segundo administrador con jefe='no' (Fase 4.1): necesario para demostrar RN4
-- (un admin común NO puede crear ni dar de baja administradores).
(5, 'Bruno Contralor', 'admin2@cathartes.uy',    '$2y$10$z5mGXNm4VRBFHo/.c9.izev.4odeY.Kbjzmu8twLnhyLnA9lhDZUy', 'activo');

-- ---------------------------------------------------------------------
-- 4) Teléfonos (uno por usuario, formato uruguayo)
--    Los id_tel 8 y 9 no son un error: son los que quedaron en la base viva.
--    Se preservan porque el id es la PK de la tabla.
-- ---------------------------------------------------------------------
INSERT INTO usuario_telefono (id_tel, telefono, ID_usuario) VALUES
(1, '091 234 567', 1),
(2, '092 345 678', 2),
(3, '093 456 789', 3),
(8, '094 567 890', 4),
(9, '095 111 222', 5);

-- ---------------------------------------------------------------------
-- 5) Subtipos de usuario (el rol se deduce de acá, no es una columna)
-- ---------------------------------------------------------------------
INSERT INTO administrador (ID_usuario, jefe) VALUES (1, 'si');
INSERT INTO administrador (ID_usuario, jefe) VALUES (5, 'no');   -- admin común (RN4)
INSERT INTO operario (ID_usuario, ID_instalacion) VALUES (2, 1);
-- usuario.nombre ES el nombre del equipo (la cuadrilla es una cuenta compartida).
INSERT INTO cuadrilla (ID_usuario, ID_camion, estado) VALUES (3, 1, 'en servicio');
INSERT INTO vecino (ID_usuario) VALUES (4);

-- ---------------------------------------------------------------------
-- 6) Rutas
--    Dos zonas distintas, para ver que los filtros y los joins las
--    distinguen, más una ruta inactiva (la 3) para probar que la pantalla
--    de Rutas no le deja asignar cuadrilla y que el filtro por estado anda.
-- ---------------------------------------------------------------------
INSERT INTO ruta (ID, nombre, zona, frecuencia, estado, ID_instalacion) VALUES
(1, 'Buceo — Pocitos',          'Buceo',   'diaria',                     'activa',   1),
(2, 'Pocitos — Punta Carretas', 'Pocitos', 'lunes, miércoles y viernes', 'activa',   1),
(3, 'helo',                     'Pocitos', 'miercoles',                  'inactiva', 1);

-- `toma` es un HISTORIAL, no una asignación viva: su PK incluye `fecha`, y
-- "la ruta actual de la cuadrilla" es la toma más reciente. Con dos filas se
-- ve el historial funcionando: la vigente es la ruta 2.
INSERT INTO toma (ID_cuadrilla, ID_ruta, fecha) VALUES
(3, 1, DATE_SUB(NOW(), INTERVAL 3046 MINUTE)),   -- ~2,1 días (la anterior)
(3, 2, DATE_SUB(NOW(), INTERVAL  287 MINUTE));   -- ~4,8 horas (la vigente)

-- ---------------------------------------------------------------------
-- 7) Contenedores (ninguno dado de baja)
--    Repartidos en las tres rutas, como quedaron tras usar el panel
--    "por qué contenedores pasa" de la pantalla de Rutas. Los tres estados
--    operativos están representados, para los badges y el color del mapa.
-- ---------------------------------------------------------------------
INSERT INTO contenedor (ID, ubicacion, latitud, longitud, tipo, estado, fecha_baja, motivo_baja, ID_ruta) VALUES
(1, 'Av. Rivera 3245, Buceo',   -34.904800, -56.142000, 'basura',     'funcional',    NULL, NULL, 3),
(2, 'Br. España 2650, Pocitos', -34.911000, -56.150000, 'basura',     'desbordado',   NULL, NULL, 1),
(3, 'Av. Brasil 3100, Pocitos', -34.909500, -56.147000, 'reciclable', 'no funcional', NULL, NULL, 2);

-- ---------------------------------------------------------------------
-- 8) Incidencias (todas reportadas por el vecino, ID 4)
--    El hueco en el ID 5 es de la base viva y se preserva.
--
--    Las 6, 7 y 8 tienen foto: el nombre apunta a un archivo que vive en el
--    disco de esta misma app, en storage/uploads/incidencias/. Si esos
--    .png no están, la ficha muestra "Sin foto" — el dato está igual.
-- ---------------------------------------------------------------------
INSERT INTO incidencia_historial (ID_incidencia, fecha_ing, descripcion, foto, tipo, validada, estado, ID_usuario, ID_contenedor) VALUES
(1, DATE_SUB(NOW(), INTERVAL 33393 MINUTE), 'Contenedor desbordado sobre la vereda.', NULL,    'desbordado',           'si', 'finalizada', 4, 2),  -- ~23,2 d
(2, DATE_SUB(NOW(), INTERVAL 18993 MINUTE), 'Tapa rota, no cierra.',                  NULL,    'roto',                 'si', 'en curso',   4, 3),  -- ~13,2 d
(3, DATE_SUB(NOW(), INTERVAL 11793 MINUTE), 'Hace días que no pasa la recolección.',  NULL,    'falta de recoleccion', 'si', 'en curso',   4, 1),  -- ~8,2 d
(4, DATE_SUB(NOW(), INTERVAL  7473 MINUTE), 'Volvió a desbordarse.',                  NULL,    'desbordado',           'si', 'en curso',   4, 2),  -- ~5,2 d
(6, DATE_SUB(NOW(), INTERVAL  4304 MINUTE), 'xd',                                     '6.png', 'desbordado',           'si', 'en curso',   4, 1),  -- ~3,0 d
(7, DATE_SUB(NOW(), INTERVAL  4284 MINUTE), 'xd2',                                    '7.png', 'roto',                 'si', 'en curso',   4, 2),  -- ~3,0 d
(8, DATE_SUB(NOW(), INTERVAL  3008 MINUTE), 'puto',                                   '8.png', 'roto',                 'si', 'en curso',   4, 2);  -- ~2,1 d

-- ---------------------------------------------------------------------
-- 9) Intervenciones de la cuadrilla 3
--    El hueco en el ID 2 es de la base viva y se preserva.
--    Una finalizada (duró 1440 min = 24 h exactas) y dos abiertas: es lo que
--    hace que el informe de rendimiento del backoffice tenga algo que decir
--    (asignadas 3, finalizadas 1, abiertas 2, tasa 33%, promedio 1 d con n=1).
--    Coherencia temporal de la #1: reporte (~23,2 d) → inicio (~21,2 d) → fin (~20,2 d).
-- ---------------------------------------------------------------------
INSERT INTO intervencion (ID_intervencion, fecha_inicio, fecha_fin, tarea_finalizada, ID_incidencia, ID_cuadrilla) VALUES
(1, DATE_SUB(NOW(), INTERVAL 30513 MINUTE), DATE_SUB(NOW(), INTERVAL 29073 MINUTE), 'si', 1, 3),  -- ~21,2 d → ~20,2 d
(3, DATE_SUB(NOW(), INTERVAL   288 MINUTE), NULL,                                   'no', 8, 3),  -- ~4,8 h, abierta
(4, DATE_SUB(NOW(), INTERVAL   287 MINUTE), NULL,                                   'no', 7, 3);  -- ~4,8 h, abierta

-- ---------------------------------------------------------------------
-- 10) Notificaciones para el vecino 4
--     Las fechas coinciden a propósito con los hechos que las originan:
--     la de "validada" con el ingreso de la incidencia 3, y la de
--     "finalizada" con el cierre de la intervención 1.
-- ---------------------------------------------------------------------
INSERT INTO notificacion (ID, ID_usuario, ID_incidencia, tipo, mensaje, fecha, leida) VALUES
(1, 4, 3, 'incidencia validada',   'Tu incidencia fue validada.',                  DATE_SUB(NOW(), INTERVAL 11793 MINUTE), 'si'),  -- ~8,2 d
(2, 4, 1, 'incidencia finalizada', 'Tu incidencia fue resuelta por la cuadrilla.', DATE_SUB(NOW(), INTERVAL 29073 MINUTE), 'no');  -- ~20,2 d

-- =====================================================================
--  Fin del seed. 45 filas en 16 tablas.
--  Las 6 tablas restantes quedan vacías a propósito (ver el encabezado).
-- =====================================================================
