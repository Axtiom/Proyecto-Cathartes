-- ==============================================================================
-- SCRIPT DE CARGA MASIVA DE DATOS REALES DE MONTEVIDEO - SiGeRU (UTU 2026)
-- Motor: MySQL 8
-- ==============================================================================

USE mef_sigeru; -- Ajustar al nombre exacto de tu base de datos si difiere

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE incidencias;
TRUNCATE TABLE recolecciones;
TRUNCATE TABLE contenedor_ruta;
TRUNCATE TABLE rutas;
TRUNCATE TABLE contenedores;
TRUNCATE TABLE maquinarias;
TRUNCATE TABLE camiones;
TRUNCATE TABLE instalaciones;
TRUNCATE TABLE usuarios;
SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------------------------
-- 1. USUARIOS (5 Admins, 10 Operarios, 30 Cuadrillas, 300 Vecinos = 345 Total)
-- ------------------------------------------------------------------------------

-- Administradores (5)
INSERT INTO usuarios (nombre, apellido, email, password, rol, estado, creado_en) VALUES
('Admin', 'General', 'admin@sigeru.gub.uy', '$2y$10$e8N8/u2K8Wn.ePzPq3K8UeX9V9/yHq3U2x7Z8w9Y0z1A2B3C4D5E6', 'administrador', 'activo', NOW()),
('Lucía', 'Fernández', 'l.fernandez@imm.gub.uy', '$2y$10$e8N8/u2K8Wn.ePzPq3K8UeX9V9/yHq3U2x7Z8w9Y0z1A2B3C4D5E6', 'administrador', 'activo', NOW()),
('Gonzalo', 'Rodríguez', 'g.rodriguez@imm.gub.uy', '$2y$10$e8N8/u2K8Wn.ePzPq3K8UeX9V9/yHq3U2x7Z8w9Y0z1A2B3C4D5E6', 'administrador', 'activo', NOW()),
('Mariana', 'Silva', 'm.silva@imm.gub.uy', '$2y$10$e8N8/u2K8Wn.ePzPq3K8UeX9V9/yHq3U2x7Z8w9Y0z1A2B3C4D5E6', 'administrador', 'activo', NOW()),
('Martín', 'Suárez', 'm.suarez@imm.gub.uy', '$2y$10$e8N8/u2K8Wn.ePzPq3K8UeX9V9/yHq3U2x7Z8w9Y0z1A2B3C4D5E6', 'administrador', 'activo', NOW());

-- Procedimiento almacenado para inserción masiva eficiente
DELIMITER //
CREATE PROCEDURE PobladoUsuariosMasivo()
BEGIN
    DECLARE i INT DEFAULT 1;

    -- Operarios (10)
    WHILE i <= 10 DO
        INSERT INTO usuarios (nombre, apellido, email, password, rol, estado, creado_en)
        VALUES (CONCAT('Operario_', i), 'Planta', CONCAT('operario', i, '@sigeru.gub.uy'), '$2y$10$e8N8/u2K8Wn.ePzPq3K8UeX9V9/yHq3U2x7Z8w9Y0z1A2B3C4D5E6', 'operario', 'activo', NOW());
        SET i = i + 1;
    END WHILE;

    -- Cuadrillas (30)
    SET i = 1;
    WHILE i <= 30 DO
        INSERT INTO usuarios (nombre, apellido, email, password, rol, estado, creado_en)
        VALUES (CONCAT('Cuadrilla_', i), 'Recoleccion', CONCAT('cuadrilla', i, '@sigeru.gub.uy'), '$2y$10$e8N8/u2K8Wn.ePzPq3K8UeX9V9/yHq3U2x7Z8w9Y0z1A2B3C4D5E6', 'cuadrilla', 'activo', NOW());
        SET i = i + 1;
    END WHILE;

    -- Vecinos (300)
    SET i = 1;
    WHILE i <= 300 DO
        INSERT INTO usuarios (nombre, apellido, email, password, rol, estado, creado_en)
        VALUES (CONCAT('Vecino_', i), CONCAT('Mvd_', i), CONCAT('vecino', i, '@gmail.com'), '$2y$10$e8N8/u2K8Wn.ePzPq3K8UeX9V9/yHq3U2x7Z8w9Y0z1A2B3C4D5E6', 'vecino', 'activo', NOW());
        SET i = i + 1;
    END WHILE;
END //
DELIMITER ;

CALL PobladoUsuariosMasivo();
DROP PROCEDURE PobladoUsuariosMasivo;


-- ------------------------------------------------------------------------------
-- 2. INSTALACIONES REALES DE MONTEVIDEO
-- Regla: Cada instalación tiene asignado obligatoriamente un operario (IDs 6 al 12).
-- ------------------------------------------------------------------------------
INSERT INTO instalaciones (nombre, tipo, capacidad_toneladas, direccion, latitud, longitud, estado, id_operario_cargo) VALUES
('Sitio de Disposición Final Felipe Cardoso', 'vertedero', 150000.00, 'Felipe Cardoso 3210, Bañados de Carrasco', -34.845812, -56.082145, 'operativo', 6),
('Planta de Clasificación Durán', 'centro_acopio', 5000.00, 'Camino Durán 5120, Colón', -34.801234, -56.214567, 'operativo', 7),
('Planta de Clasificación Géminis', 'centro_acopio', 4500.00, 'Géminis 2840, Bella Italia', -34.839120, -56.109843, 'operativo', 8),
('Planta de Clasificación Burgos', 'centro_acopio', 6000.00, 'Burgos 3150, Prado', -34.862145, -56.192834, 'operativo', 9),
('Centro de Acopio Ecopunto Buceo', 'centro_acopio', 2000.00, 'Av. Rivera 4200, Buceo', -34.895123, -56.128475, 'operativo', 10),
('Centro de Transferencia Chacarita de los Padres', 'centro_acopio', 8000.00, 'Camino Chacarita 2210', -34.821940, -56.115430, 'operativo', 11),
('Planta de Compostaje Tresor', 'vertedero', 25000.00, 'Ruta 5 Km 12.500', -34.781200, -56.241500, 'operativo', 12);


-- ------------------------------------------------------------------------------
-- 3. MAQUINARIAS (40 Unidades distribuidas en las instalaciones)
-- ------------------------------------------------------------------------------
DELIMITER //
CREATE PROCEDURE PobladoMaquinaria()
BEGIN
    DECLARE i INT DEFAULT 1;
    DECLARE inst_id INT;
    WHILE i <= 40 DO
        SET inst_id = ((i - 1) MOD 7) + 1;
        INSERT INTO maquinarias (nombre, tipo, estado, id_instalacion)
        VALUES (
            CONCAT('Maquinaria Asignada #', i),
            IF(i % 2 = 0, 'Pala Mecánica', 'Compactadora de Rodillo'),
            'operativo',
            inst_id
        );
        SET i = i + 1;
    END WHILE;
END //
DELIMITER ;

CALL PobladoMaquinaria();
DROP PROCEDURE PobladoMaquinaria;


-- ------------------------------------------------------------------------------
-- 4. CAMIONES RECOLECTORES (30 Unidades)
-- ------------------------------------------------------------------------------
DELIMITER //
CREATE PROCEDURE PobladoCamiones()
BEGIN
    DECLARE i INT DEFAULT 1;
    WHILE i <= 30 DO
        INSERT INTO camiones (matricula, modelo, capacidad_m3, estado)
        VALUES (
            CONCAT('SIM-', 1000 + i),
            'Mercedes-Benz Atego 1729',
            15.00,
            IF(i % 5 = 0, 'en_mantenimiento', 'operativo')
        );
        SET i = i + 1;
    END WHILE;
END //
DELIMITER ;

CALL PobladoCamiones();
DROP PROCEDURE PobladoCamiones;


-- ------------------------------------------------------------------------------
-- 5. RUTAS Y ASIGNACIÓN A CUADRILLAS (15 Rutas distribuidas por zonas de Montevideo)
-- ------------------------------------------------------------------------------
INSERT INTO rutas (nombre_zona, descripcion, id_cuadrilla, id_camion) VALUES
('Zona 1 - Centro / Cordón', 'Recorrido por Av. 18 de Julio, Constituyente y Ejido', 16, 1),
('Zona 2 - Ciudad Vieja', 'Recorrido por Sarandí, Pérez Castellano y Rambla 25 de Agosto', 17, 2),
('Zona 3 - Pocitos / Buceo', 'Recorrido por Av. Brasil, Benito Blanco y Rivera', 18, 3),
('Zona 4 - Punta Carretas', 'Recorrido por Ellauri, 21 de Setiembre y Zorrilla', 19, 4),
('Zona 5 - Unión / Malvín', 'Recorrido por Av. Italia, Comercio y Asunción', 20, 5),
('Zona 6 - Carrasco', 'Recorrido por Arocena, Blanes Viale y Av. Bolivia', 21, 6),
('Zona 7 - Prado / Bella Vista', 'Recorrido por Av. Agraciada, Lucas Obes y Suárez', 22, 7),
('Zona 8 - Cerro / La Teja', 'Recorrido por Carlos María Ramírez y Grecia', 23, 8),
('Zona 9 - Colón / Lezica', 'Recorrido por Av. Garzón y Camino Lezica', 24, 9),
('Zona 10 - Maroñas / Flor de Maroñas', 'Recorrido por Caminos Belloni y 8 de Octubre', 25, 10),
('Zona 11 - Sayago / Peñarol', 'Recorrido por Propios y Camino Edison', 26, 11),
('Zona 12 - Parque Rodó / Palermo', 'Recorrido por Gonzalo Ramírez y San Salvador', 27, 12),
('Zona 13 - La Blanqueada', 'Recorrido por Av. Luis Alberto de Herrera y Monte Caseros', 28, 13),
('Zona 14 - Aguada / Reducto', 'Recorrido por San Martín y Av. General Flores', 29, 14),
('Zona 15 - Manga / Toledo Chico', 'Recorrido por Av. Mendoza y Av. Belloni Norte', 30, 15);


-- ------------------------------------------------------------------------------
-- 6. CONTENEDORES MASIVOS (3,000 Contenedores geolocalizados en Montevideo)
-- ------------------------------------------------------------------------------
DELIMITER //
CREATE PROCEDURE PobladoContenedoresMasivos()
BEGIN
    DECLARE i INT DEFAULT 1;
    DECLARE lat DOUBLE;
    DECLARE lng DOUBLE;
    DECLARE estado_val VARCHAR(20);
    
    WHILE i <= 3000 DO
        -- Coordenadas acotadas a la zona urbana de Montevideo
        SET lat = -34.800000 - (RAND() * 0.130000);
        SET lng = -56.050000 - (RAND() * 0.180000);

        SET estado_val = CASE 
            WHEN i % 15 = 0 THEN 'desbordado'
            WHEN i % 25 = 0 THEN 'roto'
            ELSE 'funcional'
        END;

        INSERT INTO contenedores (codigo_identificador, calle_numero, esquina, latitud, longitud, estado, tipo_residuo)
        VALUES (
            CONCAT('MVD-CONT-', LPAD(i, 4, '0')),
            CONCAT('Calle Mvd Subzona ', (i MOD 50) + 1, ' #', 1000 + i),
            CONCAT('Esq. Av. Principal ', (i MOD 20) + 1),
            lat,
            lng,
            estado_val,
            IF(i % 3 = 0, 'reciclable', 'orgánico')
        );
        SET i = i + 1;
    END WHILE;
END //
DELIMITER ;

CALL PobladoContenedoresMasivos();
DROP PROCEDURE PobladoContenedoresMasivos;


-- ------------------------------------------------------------------------------
-- 7. HISTORIAL DE INCIDENCIAS (50 Registros vinculados a contenedores y vecinos)
-- ------------------------------------------------------------------------------
DELIMITER //
CREATE PROCEDURE PobladoIncidencias()
BEGIN
    DECLARE i INT DEFAULT 1;
    DECLARE cont_id INT;
    DECLARE vec_id INT;

    WHILE i <= 50 DO
        SET cont_id = FLOOR(1 + RAND() * 2999);
        SET vec_id = FLOOR(46 + RAND() * 290); -- Rango correspondiente a los IDs de vecinos creados

        INSERT INTO incidencias (id_contenedor, id_usuario_reporta, tipo_incidencia, descripcion, estado, fecha_reporte)
        VALUES (
            cont_id,
            vec_id,
            ELT(FLOOR(1 + RAND() * 3), 'desborde', 'rotura', 'falta_recoleccion'),
            CONCAT('Incidencia reportada en contenedor MVD-CONT-', LPAD(cont_id, 4, '0'), ' por acumulación o desperfecto técnico.'),
            ELT(FLOOR(1 + RAND() * 3), 'abierta', 'en_curso', 'resuelta'),
            NOW() - INTERVAL FLOOR(RAND() * 30) DAY
        );
        SET i = i + 1;
    END WHILE;
END //
DELIMITER ;

CALL PobladoIncidencias();
DROP PROCEDURE PobladoIncidencias;

COMMIT;