-- =====================================================================
--  Cathartes - Sistema de Gestion de Residuos Urbanos (Cathartes)
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
--
--  ESTE ARCHIVO ES LA FUENTE DE VERDAD DEL ESQUEMA.
--    Ya incluye TODAS las adiciones hechas durante el desarrollo: no hay
--    scripts de migracion aparte que haya que correr despues. Para levantar
--    la base desde cero alcanza con:
--        1) db/BD.sql    (estructura)   <- este archivo
--        2) db/seed.sql  (datos de prueba)
--    Verificado el 2026-07-27 contra la base viva: 22 tablas, mismas
--    columnas, mismo trigger. Se le dio de baja a la gamificacion despues
--    de esa fecha: quedaron 20 tablas y sin trigger (ver nota mas abajo).
-- =====================================================================
--  REGISTRO DE ADICIONES AL MER
--  ---------------------------------------------------------------------
--  El MER esta congelado: no se inventan tablas ni columnas. Lo que sigue
--  son las unicas excepciones, cada una autorizada explicitamente por Juan
--  y ya aplicada mas abajo en este mismo archivo. Se listan aca para que el
--  MER, el pasaje a tablas y el diccionario de datos se puedan actualizar
--  sin tener que reconstruir la historia desde los commits.
--
--  2026-07-23 (Fase 2)   incidencia_historial.foto VARCHAR(255) NULL
--      RF-18 exige foto obligatoria al reportar y el MER no tenia donde
--      guardarla. Guarda el nombre/ruta relativa; los bytes viven en el
--      disco de la API (storage/uploads/incidencias/), no en la base.
--
--  2026-07-23 (Fase 2)   [REVERTIDO] TRIGGER trg_incidencia_valida_puntos
--      Acreditaba 150 puntos fijos al pasar validada de 'no' a 'si'. Se sacó
--      el trigger junto con toda la gamificación (recompensa, canje,
--      vecino.saldo): el sistema de puntos/recompensas se dio de baja del
--      alcance del proyecto. Ver nota de "DECISIONES DE NO AGREGAR" más abajo.
--
--  2026-07-23 (Fase 2.5) contenedor.latitud/longitud  DECIMAL(9,6) NULL
--                        instalacion.latitud/longitud DECIMAL(9,6) NULL
--      Modulo de mapa (Leaflet/OSM). Antes las coordenadas vivian embebidas
--      en el texto de 'ubicacion'; se migraron a columnas propias.
--
--  2026-07-24 (Fase 3.9) ruta.zona     VARCHAR(50) NOT NULL
--                        ruta.estado   ENUM('activa','inactiva')
--      zona habilita el filtro por zona en Incidencias del backoffice;
--      estado permite sacar una ruta de operacion sin borrarla.
--
--  2026-07-24 (Fase 3.9) camion.fecha_baja  DATETIME     NULL
--                        camion.motivo_baja VARCHAR(255) NULL
--      Analogos exactos a los de 'contenedor': la baja no borra, registra.
--
--  2026-07-24 (Fase 3.9) cuadrilla.estado ENUM('en servicio','fuera de servicio')
--      Eje OPERATIVO, distinto de usuario.estado (activo/no activo), que es
--      el estado de CUENTA. Son dos cosas separadas a proposito.
--
--  NO es una adicion: la tabla 'toma' (cuadrilla toma ruta) YA EXISTIA en el
--  DDL con PK (ID_cuadrilla, ID_ruta, fecha). Un documento de la Fase 3.9
--  pedia crearla; el DDL gana. Ver BUG-007 en BUGS.md.
--
--  Nota de nomenclatura: la columna de contrasena se llama 'contrasena' (sin
--  ñ) en el DDL, mientras que el MER/diccionario la nombran con ñ. Es una
--  discrepancia deliberada tras BUG-003 (la ñ se corrompia al importar el
--  dump). Ningun identificador de la base lleva ñ ni acentos.
-- =====================================================================
--  DECISIONES DE "NO AGREGAR" (confirmadas por Juan - NO revertir)
--  ---------------------------------------------------------------------
--  Cosas que se evaluaron y se decidio NO poner en el esquema. Se dejan
--  escritas para que no vuelvan a proponerse en cada fase:
--
--    usuario.apellido      -> nombre y apellido van concatenados en
--                             usuario.nombre. El alta pide los dos campos y
--                             los une al insertar.
--    cuadrilla.nombre      -> usuario.nombre ES el nombre del equipo
--                             (ej. 'Cuadrilla Alfa'). La cuadrilla es un
--                             subtipo de Usuario y comparte UNA cuenta.
--    cuadrilla.integrantes -> la cuadrilla es una cuenta compartida, no un
--                             conjunto de personas.
--    recompensa.activo     -> [ya no aplica: se dio de baja toda la
--                             gamificacion, ver bloque de arriba]
--    2do tipo en instalacion -> el tipo (vertedero / centro de acopio) ya
--                             determina el residuo que maneja; el residuo es
--                             una etiqueta derivada en la vista, no columna.
--
--  Pendientes conocidos (requieren autorizacion para tocar el esquema):
--    - Cierre de una ruta recorrida: 'toma' registra cuando la cuadrilla toma
--      la ruta, no cuando la termina. Haria falta una columna de cierre o una
--      tabla aparte. El boton correspondiente esta deshabilitado en el front.
--    - Historico de ocupacion de instalaciones: 'instalacion.ocupacion_actual'
--      es un unico valor que se sobrescribe. Sin una tabla de mediciones
--      (fecha + ocupacion + instalacion) no hay serie mensual posible, y por
--      eso la pantalla de Informes del operario esta vacia a proposito.
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
    -- Coordenadas para el mapa interactivo (Leaflet/OSM). Columnas agregadas
    -- 2026-07-23 (Fase 2.5): excepcion puntual al MER congelado, ver CLAUDE.md.
    latitud             DECIMAL(9,6)    NULL,
    longitud            DECIMAL(9,6)    NULL,
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
    fecha_baja  DATETIME        NULL,
    motivo_baja VARCHAR(255)    NULL,
    PRIMARY KEY (ID),
    UNIQUE KEY uq_camion_matricula (matricula)
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
    estado      ENUM('en servicio','fuera de servicio') NOT NULL DEFAULT 'en servicio',
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
--  BLOQUE 5 - Infraestructura fisica
-- =====================================================================

CREATE TABLE ruta (
    ID              INT             AUTO_INCREMENT,
    nombre          VARCHAR(100)    NOT NULL,
    zona            VARCHAR(50)     NOT NULL,
    frecuencia      VARCHAR(50)     NOT NULL,
    estado          ENUM('activa','inactiva') NOT NULL DEFAULT 'activa',
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
    -- Coordenadas para el mapa interactivo (Leaflet/OSM). Columnas agregadas
    -- 2026-07-23 (Fase 2.5): excepcion puntual al MER congelado, ver CLAUDE.md.
    latitud     DECIMAL(9,6)    NULL,
    longitud    DECIMAL(9,6)    NULL,
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
    -- Foto de la incidencia (RF-18, obligatoria al reportar desde el frontend).
    -- Guarda el nombre/ruta relativa del archivo; los bytes viven en el disco de
    -- la API (storage/uploads/incidencias/). NULL para filas historicas sin foto.
    -- Columna agregada 2026-07-23: excepcion puntual al MER congelado, ver CLAUDE.md.
    foto            VARCHAR(255)    NULL,
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
--  Fin del DDL - 20 tablas
-- =====================================================================
