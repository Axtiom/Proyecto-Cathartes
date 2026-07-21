-- =====================================================================
--  Cathartes - Sistema de Gestion de Residuos Urbanos (SiGeRU)
--  DDL de creacion de la base de datos y sus tablas
--
--  Proyecto de Pasaje de Grado 2026 - Axtiom
--  ESI Buceo - DGETP/UTU
--
--  Motor       : InnoDB (requerido para claves foraneas)
--  Charset     : utf8mb4 / utf8mb4_unicode_ci
--  Version     : MySQL 8.0
--
--  Politica de borrado:
--    CASCADE  -> solo subtipos de usuario y telefonos (no existen sin
--                el usuario padre)
--    RESTRICT -> todo el resto. El sistema no borra: marca de baja
--                mediante los campos 'estado'.
-- =====================================================================

DROP DATABASE IF EXISTS cathartes;
CREATE DATABASE cathartes
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE cathartes;


-- =====================================================================
--  BLOQUE 1 - Tablas sin dependencias
-- =====================================================================

CREATE TABLE usuario (
    ID              INT             AUTO_INCREMENT,
    nombre          VARCHAR(100)    NOT NULL,
    email           VARCHAR(150)    NOT NULL,
    `contrasena`    VARCHAR(255)    NOT NULL,
    estado          ENUM('activo','no activo') NOT NULL DEFAULT 'no activo',
    PRIMARY KEY (ID),
    UNIQUE KEY uq_usuario_email (email)
) ENGINE=InnoDB;

CREATE TABLE instalacion (
    ID                  INT             AUTO_INCREMENT,
    nombre              VARCHAR(100)    NOT NULL,
    ubicacion           VARCHAR(200)    NOT NULL,
    capacidad           INT             NOT NULL,
    ocupacion_actual    INT             NOT NULL DEFAULT 0,
    tipo                ENUM('vertedero','centro de acopio') NOT NULL,
    PRIMARY KEY (ID)
) ENGINE=InnoDB;

CREATE TABLE camion (
    ID          INT             AUTO_INCREMENT,
    matricula   VARCHAR(20)     NOT NULL,
    modelo      VARCHAR(100)    NOT NULL,
    capacidad   INT             NOT NULL,
    estado      ENUM('funcional','en mantenimiento','no funcional','dado de baja') NOT NULL,
    PRIMARY KEY (ID),
    UNIQUE KEY uq_camion_matricula (matricula)
) ENGINE=InnoDB;

CREATE TABLE recompensa (
    ID          INT             AUTO_INCREMENT,
    nombre      VARCHAR(100)    NOT NULL,
    descripcion VARCHAR(255)    NULL,
    valor       INT             NOT NULL,
    PRIMARY KEY (ID)
) ENGINE=InnoDB;

CREATE TABLE repuestos (
    ID          INT             AUTO_INCREMENT,
    nombre      VARCHAR(100)    NOT NULL,
    tipo        VARCHAR(50)     NOT NULL,
    cantidad    INT             NOT NULL,
    PRIMARY KEY (ID)
) ENGINE=InnoDB;


-- =====================================================================
--  BLOQUE 2 - Multivaluado y subtipos de usuario
--  (unicas relaciones con ON DELETE CASCADE)
-- =====================================================================

CREATE TABLE usuario_telefono (
    id_tel      INT             AUTO_INCREMENT,
    telefono    VARCHAR(20)     NOT NULL,
    ID_usuario  INT             NOT NULL,
    PRIMARY KEY (id_tel),
    KEY idx_tel_usuario (ID_usuario),
    CONSTRAINT fk_tel_usuario FOREIGN KEY (ID_usuario)
        REFERENCES usuario (ID)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE administrador (
    ID_usuario  INT             NOT NULL,
    jefe        ENUM('si','no') NOT NULL DEFAULT 'no',
    PRIMARY KEY (ID_usuario),
    CONSTRAINT fk_admin_usuario FOREIGN KEY (ID_usuario)
        REFERENCES usuario (ID)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE operario (
    ID_usuario      INT NOT NULL,
    ID_instalacion  INT NOT NULL,
    PRIMARY KEY (ID_usuario),
    KEY idx_operario_instalacion (ID_instalacion),
    CONSTRAINT fk_operario_usuario FOREIGN KEY (ID_usuario)
        REFERENCES usuario (ID)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_operario_instalacion FOREIGN KEY (ID_instalacion)
        REFERENCES instalacion (ID)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE cuadrilla (
    ID_usuario  INT NOT NULL,
    ID_camion   INT NULL,
    PRIMARY KEY (ID_usuario),
    UNIQUE KEY uq_cuadrilla_camion (ID_camion),
    CONSTRAINT fk_cuadrilla_usuario FOREIGN KEY (ID_usuario)
        REFERENCES usuario (ID)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_cuadrilla_camion FOREIGN KEY (ID_camion)
        REFERENCES camion (ID)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE vecino (
    ID_usuario  INT NOT NULL,
    saldo       INT NOT NULL DEFAULT 0,
    PRIMARY KEY (ID_usuario),
    CONSTRAINT fk_vecino_usuario FOREIGN KEY (ID_usuario)
        REFERENCES usuario (ID)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;


-- =====================================================================
--  BLOQUE 3 - Relaciones entre administradores y cuentas
-- =====================================================================

CREATE TABLE gestiona (
    ID_ejecutor INT             NOT NULL,
    ID_afectado INT             NOT NULL,
    fecha       DATETIME        NOT NULL,
    accion      VARCHAR(50)     NOT NULL,
    PRIMARY KEY (ID_ejecutor, ID_afectado, fecha),
    KEY idx_gestiona_afectado (ID_afectado),
    CONSTRAINT fk_gestiona_ejecutor FOREIGN KEY (ID_ejecutor)
        REFERENCES administrador (ID_usuario)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_gestiona_afectado FOREIGN KEY (ID_afectado)
        REFERENCES administrador (ID_usuario)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE autorizacion (
    ID_admin    INT         NOT NULL,
    ID_usuario  INT         NOT NULL,
    fecha       DATETIME    NOT NULL,
    accion      ENUM('aprobada','rechazada','baja') NOT NULL,
    PRIMARY KEY (ID_admin, ID_usuario, fecha),
    KEY idx_autorizacion_usuario (ID_usuario),
    CONSTRAINT fk_autorizacion_admin FOREIGN KEY (ID_admin)
        REFERENCES administrador (ID_usuario)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_autorizacion_usuario FOREIGN KEY (ID_usuario)
        REFERENCES usuario (ID)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;


-- =====================================================================
--  BLOQUE 4 - Gamificacion
-- =====================================================================

CREATE TABLE canje (
    ID_vecino       INT         NOT NULL,
    ID_recompensa   INT         NOT NULL,
    fecha           DATETIME    NOT NULL,
    puntos_gastados INT         NOT NULL,
    PRIMARY KEY (ID_vecino, ID_recompensa, fecha),
    KEY idx_canje_recompensa (ID_recompensa),
    CONSTRAINT fk_canje_vecino FOREIGN KEY (ID_vecino)
        REFERENCES vecino (ID_usuario)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_canje_recompensa FOREIGN KEY (ID_recompensa)
        REFERENCES recompensa (ID)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;


-- =====================================================================
--  BLOQUE 5 - Infraestructura fisica
-- =====================================================================

CREATE TABLE ruta (
    ID              INT             AUTO_INCREMENT,
    nombre          VARCHAR(100)    NOT NULL,
    frecuencia      VARCHAR(50)     NOT NULL,
    ID_instalacion  INT             NOT NULL,
    PRIMARY KEY (ID),
    KEY idx_ruta_instalacion (ID_instalacion),
    CONSTRAINT fk_ruta_instalacion FOREIGN KEY (ID_instalacion)
        REFERENCES instalacion (ID)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE maquinaria (
    ID              INT             AUTO_INCREMENT,
    modelo          VARCHAR(100)    NOT NULL,
    tipo            VARCHAR(50)     NOT NULL,
    estado          ENUM('funcional','no funcional','en mantenimiento') NOT NULL,
    ID_instalacion  INT             NOT NULL,
    PRIMARY KEY (ID),
    KEY idx_maquinaria_instalacion (ID_instalacion),
    CONSTRAINT fk_maquinaria_instalacion FOREIGN KEY (ID_instalacion)
        REFERENCES instalacion (ID)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE contenedor (
    ID          INT             AUTO_INCREMENT,
    ubicacion   VARCHAR(200)    NOT NULL,
    tipo        ENUM('basura','reciclable') NOT NULL,
    estado      ENUM('funcional','no funcional','desbordado','dado de baja') NOT NULL,
    fecha_baja  DATETIME        NULL,
    motivo_baja VARCHAR(255)    NULL,
    ID_ruta     INT             NOT NULL,
    PRIMARY KEY (ID),
    KEY idx_contenedor_ruta (ID_ruta),
    CONSTRAINT fk_contenedor_ruta FOREIGN KEY (ID_ruta)
        REFERENCES ruta (ID)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE verificacion (
    ID_usuario      INT         NOT NULL,
    ID_contenedor   INT         NOT NULL,
    fecha           DATETIME    NOT NULL,
    PRIMARY KEY (ID_usuario, ID_contenedor, fecha),
    KEY idx_verificacion_contenedor (ID_contenedor),
    CONSTRAINT fk_verificacion_usuario FOREIGN KEY (ID_usuario)
        REFERENCES usuario (ID)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_verificacion_contenedor FOREIGN KEY (ID_contenedor)
        REFERENCES contenedor (ID)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;


-- =====================================================================
--  BLOQUE 6 - Ciclo de incidencias
-- =====================================================================

CREATE TABLE incidencia_historial (
    ID_incidencia   INT             AUTO_INCREMENT,
    fecha_ing       DATETIME        NOT NULL,
    descripcion     VARCHAR(255)    NULL,
    tipo            ENUM('desbordado','roto','falta de recoleccion') NOT NULL,
    validada        ENUM('si','no') NOT NULL DEFAULT 'no',
    estado          ENUM('en curso','finalizada') NOT NULL DEFAULT 'en curso',
    ID_usuario      INT             NOT NULL,
    ID_contenedor   INT             NOT NULL,
    PRIMARY KEY (ID_incidencia),
    KEY idx_incidencia_usuario (ID_usuario),
    KEY idx_incidencia_contenedor (ID_contenedor),
    CONSTRAINT fk_incidencia_usuario FOREIGN KEY (ID_usuario)
        REFERENCES usuario (ID)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_incidencia_contenedor FOREIGN KEY (ID_contenedor)
        REFERENCES contenedor (ID)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE notificacion (
    ID              INT             AUTO_INCREMENT,
    ID_usuario      INT             NOT NULL,
    ID_incidencia   INT             NULL,
    tipo            VARCHAR(50)     NOT NULL,
    mensaje         VARCHAR(255)    NOT NULL,
    fecha           DATETIME        NOT NULL,
    leida           ENUM('si','no') NOT NULL DEFAULT 'no',
    PRIMARY KEY (ID),
    KEY idx_notificacion_usuario (ID_usuario),
    KEY idx_notificacion_incidencia (ID_incidencia),
    CONSTRAINT fk_notificacion_usuario FOREIGN KEY (ID_usuario)
        REFERENCES usuario (ID)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_notificacion_incidencia FOREIGN KEY (ID_incidencia)
        REFERENCES incidencia_historial (ID_incidencia)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE intervencion (
    ID_intervencion     INT         AUTO_INCREMENT,
    fecha_inicio        DATETIME    NOT NULL,
    fecha_fin           DATETIME    NULL,
    tarea_finalizada    ENUM('si','no') NOT NULL DEFAULT 'no',
    ID_incidencia       INT         NOT NULL,
    ID_cuadrilla        INT         NOT NULL,
    PRIMARY KEY (ID_intervencion),
    KEY idx_intervencion_incidencia (ID_incidencia),
    KEY idx_intervencion_cuadrilla (ID_cuadrilla),
    CONSTRAINT fk_intervencion_incidencia FOREIGN KEY (ID_incidencia)
        REFERENCES incidencia_historial (ID_incidencia)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_intervencion_cuadrilla FOREIGN KEY (ID_cuadrilla)
        REFERENCES cuadrilla (ID_usuario)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;


-- =====================================================================
--  BLOQUE 7 - Logistica de recoleccion
-- =====================================================================

CREATE TABLE usa (
    ID_intervencion INT NOT NULL,
    ID_repuesto     INT NOT NULL,
    cuantos         INT NOT NULL,
    PRIMARY KEY (ID_intervencion, ID_repuesto),
    KEY idx_usa_repuesto (ID_repuesto),
    CONSTRAINT fk_usa_intervencion FOREIGN KEY (ID_intervencion)
        REFERENCES intervencion (ID_intervencion)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_usa_repuesto FOREIGN KEY (ID_repuesto)
        REFERENCES repuestos (ID)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE toma (
    ID_cuadrilla    INT         NOT NULL,
    ID_ruta         INT         NOT NULL,
    fecha           DATETIME    NOT NULL,
    PRIMARY KEY (ID_cuadrilla, ID_ruta, fecha),
    KEY idx_toma_ruta (ID_ruta),
    CONSTRAINT fk_toma_cuadrilla FOREIGN KEY (ID_cuadrilla)
        REFERENCES cuadrilla (ID_usuario)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_toma_ruta FOREIGN KEY (ID_ruta)
        REFERENCES ruta (ID)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;


-- =====================================================================
--  Fin del DDL - 22 tablas
-- =====================================================================
