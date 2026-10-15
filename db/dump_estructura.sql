-- =====================================================================
--  Cathartes - DUMP DE ESTRUCTURA de la base de datos (sin datos)
--  Generado con: mysqldump --no-data --routines --triggers --skip-comments cathartes
--  Sirve como copia de respaldo del esquema. La fuente de verdad sigue
--  siendo db/BD.sql; los datos de prueba estan en db/seed.sql.
-- =====================================================================

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `administrador` (
  `ID_usuario` int(11) NOT NULL,
  `jefe` enum('si','no') NOT NULL DEFAULT 'no',
  PRIMARY KEY (`ID_usuario`),
  CONSTRAINT `fk_admin_usuario` FOREIGN KEY (`ID_usuario`) REFERENCES `usuario` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `autorizacion` (
  `ID_admin` int(11) NOT NULL,
  `ID_usuario` int(11) NOT NULL,
  `fecha` datetime NOT NULL,
  `accion` enum('aprobada','rechazada','baja') NOT NULL,
  PRIMARY KEY (`ID_admin`,`ID_usuario`,`fecha`),
  KEY `idx_autorizacion_usuario` (`ID_usuario`),
  CONSTRAINT `fk_autorizacion_admin` FOREIGN KEY (`ID_admin`) REFERENCES `administrador` (`ID_usuario`) ON UPDATE CASCADE,
  CONSTRAINT `fk_autorizacion_usuario` FOREIGN KEY (`ID_usuario`) REFERENCES `usuario` (`ID`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `camion` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `matricula` varchar(20) NOT NULL,
  `modelo` varchar(100) NOT NULL,
  `capacidad` int(11) NOT NULL,
  `estado` enum('funcional','en mantenimiento','no funcional','dado de baja') NOT NULL,
  `fecha_baja` datetime DEFAULT NULL,
  `motivo_baja` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `uq_camion_matricula` (`matricula`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contenedor` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `ubicacion` varchar(200) NOT NULL,
  `latitud` decimal(9,6) DEFAULT NULL,
  `longitud` decimal(9,6) DEFAULT NULL,
  `tipo` enum('basura','reciclable') NOT NULL,
  `estado` enum('funcional','no funcional','desbordado','dado de baja') NOT NULL,
  `fecha_baja` datetime DEFAULT NULL,
  `motivo_baja` varchar(255) DEFAULT NULL,
  `ID_ruta` int(11) NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `idx_contenedor_ruta` (`ID_ruta`),
  CONSTRAINT `fk_contenedor_ruta` FOREIGN KEY (`ID_ruta`) REFERENCES `ruta` (`ID`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cuadrilla` (
  `ID_usuario` int(11) NOT NULL,
  `ID_camion` int(11) DEFAULT NULL,
  `estado` enum('en servicio','fuera de servicio') NOT NULL DEFAULT 'en servicio',
  PRIMARY KEY (`ID_usuario`),
  UNIQUE KEY `uq_cuadrilla_camion` (`ID_camion`),
  CONSTRAINT `fk_cuadrilla_camion` FOREIGN KEY (`ID_camion`) REFERENCES `camion` (`ID`) ON UPDATE CASCADE,
  CONSTRAINT `fk_cuadrilla_usuario` FOREIGN KEY (`ID_usuario`) REFERENCES `usuario` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gestiona` (
  `ID_ejecutor` int(11) NOT NULL,
  `ID_afectado` int(11) NOT NULL,
  `fecha` datetime NOT NULL,
  `accion` varchar(50) NOT NULL,
  PRIMARY KEY (`ID_ejecutor`,`ID_afectado`,`fecha`),
  KEY `idx_gestiona_afectado` (`ID_afectado`),
  CONSTRAINT `fk_gestiona_afectado` FOREIGN KEY (`ID_afectado`) REFERENCES `administrador` (`ID_usuario`) ON UPDATE CASCADE,
  CONSTRAINT `fk_gestiona_ejecutor` FOREIGN KEY (`ID_ejecutor`) REFERENCES `administrador` (`ID_usuario`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `incidencia_historial` (
  `ID_incidencia` int(11) NOT NULL AUTO_INCREMENT,
  `fecha_ing` datetime NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `tipo` enum('desbordado','roto','falta de recoleccion') NOT NULL,
  `validada` enum('si','no') NOT NULL DEFAULT 'no',
  `estado` enum('en curso','finalizada') NOT NULL DEFAULT 'en curso',
  `ID_usuario` int(11) NOT NULL,
  `ID_contenedor` int(11) NOT NULL,
  PRIMARY KEY (`ID_incidencia`),
  KEY `idx_incidencia_usuario` (`ID_usuario`),
  KEY `idx_incidencia_contenedor` (`ID_contenedor`),
  CONSTRAINT `fk_incidencia_contenedor` FOREIGN KEY (`ID_contenedor`) REFERENCES `contenedor` (`ID`) ON UPDATE CASCADE,
  CONSTRAINT `fk_incidencia_usuario` FOREIGN KEY (`ID_usuario`) REFERENCES `usuario` (`ID`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `instalacion` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `ubicacion` varchar(200) NOT NULL,
  `latitud` decimal(9,6) DEFAULT NULL,
  `longitud` decimal(9,6) DEFAULT NULL,
  `capacidad` int(11) NOT NULL,
  `ocupacion_actual` int(11) NOT NULL DEFAULT 0,
  `tipo` enum('vertedero','centro de acopio') NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `intervencion` (
  `ID_intervencion` int(11) NOT NULL AUTO_INCREMENT,
  `fecha_inicio` datetime NOT NULL,
  `fecha_fin` datetime DEFAULT NULL,
  `tarea_finalizada` enum('si','no') NOT NULL DEFAULT 'no',
  `ID_incidencia` int(11) NOT NULL,
  `ID_cuadrilla` int(11) NOT NULL,
  PRIMARY KEY (`ID_intervencion`),
  KEY `idx_intervencion_incidencia` (`ID_incidencia`),
  KEY `idx_intervencion_cuadrilla` (`ID_cuadrilla`),
  CONSTRAINT `fk_intervencion_cuadrilla` FOREIGN KEY (`ID_cuadrilla`) REFERENCES `cuadrilla` (`ID_usuario`) ON UPDATE CASCADE,
  CONSTRAINT `fk_intervencion_incidencia` FOREIGN KEY (`ID_incidencia`) REFERENCES `incidencia_historial` (`ID_incidencia`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `maquinaria` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `modelo` varchar(100) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `estado` enum('funcional','no funcional','en mantenimiento') NOT NULL,
  `ID_instalacion` int(11) NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `idx_maquinaria_instalacion` (`ID_instalacion`),
  CONSTRAINT `fk_maquinaria_instalacion` FOREIGN KEY (`ID_instalacion`) REFERENCES `instalacion` (`ID`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notificacion` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `ID_usuario` int(11) NOT NULL,
  `ID_incidencia` int(11) DEFAULT NULL,
  `tipo` varchar(50) NOT NULL,
  `mensaje` varchar(255) NOT NULL,
  `fecha` datetime NOT NULL,
  `leida` enum('si','no') NOT NULL DEFAULT 'no',
  PRIMARY KEY (`ID`),
  KEY `idx_notificacion_usuario` (`ID_usuario`),
  KEY `idx_notificacion_incidencia` (`ID_incidencia`),
  CONSTRAINT `fk_notificacion_incidencia` FOREIGN KEY (`ID_incidencia`) REFERENCES `incidencia_historial` (`ID_incidencia`) ON UPDATE CASCADE,
  CONSTRAINT `fk_notificacion_usuario` FOREIGN KEY (`ID_usuario`) REFERENCES `usuario` (`ID`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `operario` (
  `ID_usuario` int(11) NOT NULL,
  `ID_instalacion` int(11) NOT NULL,
  PRIMARY KEY (`ID_usuario`),
  KEY `idx_operario_instalacion` (`ID_instalacion`),
  CONSTRAINT `fk_operario_instalacion` FOREIGN KEY (`ID_instalacion`) REFERENCES `instalacion` (`ID`) ON UPDATE CASCADE,
  CONSTRAINT `fk_operario_usuario` FOREIGN KEY (`ID_usuario`) REFERENCES `usuario` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `repuestos` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `cantidad` int(11) NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ruta` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `zona` varchar(50) NOT NULL,
  `frecuencia` varchar(50) NOT NULL,
  `estado` enum('activa','inactiva') NOT NULL DEFAULT 'activa',
  `ID_instalacion` int(11) NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `idx_ruta_instalacion` (`ID_instalacion`),
  CONSTRAINT `fk_ruta_instalacion` FOREIGN KEY (`ID_instalacion`) REFERENCES `instalacion` (`ID`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `toma` (
  `ID_cuadrilla` int(11) NOT NULL,
  `ID_ruta` int(11) NOT NULL,
  `fecha` datetime NOT NULL,
  PRIMARY KEY (`ID_cuadrilla`,`ID_ruta`,`fecha`),
  KEY `idx_toma_ruta` (`ID_ruta`),
  CONSTRAINT `fk_toma_cuadrilla` FOREIGN KEY (`ID_cuadrilla`) REFERENCES `cuadrilla` (`ID_usuario`) ON UPDATE CASCADE,
  CONSTRAINT `fk_toma_ruta` FOREIGN KEY (`ID_ruta`) REFERENCES `ruta` (`ID`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `usa` (
  `ID_intervencion` int(11) NOT NULL,
  `ID_repuesto` int(11) NOT NULL,
  `cuantos` int(11) NOT NULL,
  PRIMARY KEY (`ID_intervencion`,`ID_repuesto`),
  KEY `idx_usa_repuesto` (`ID_repuesto`),
  CONSTRAINT `fk_usa_intervencion` FOREIGN KEY (`ID_intervencion`) REFERENCES `intervencion` (`ID_intervencion`) ON UPDATE CASCADE,
  CONSTRAINT `fk_usa_repuesto` FOREIGN KEY (`ID_repuesto`) REFERENCES `repuestos` (`ID`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuario` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `contrasena` varchar(255) NOT NULL,
  `estado` enum('activo','no activo') NOT NULL DEFAULT 'no activo',
  PRIMARY KEY (`ID`),
  UNIQUE KEY `uq_usuario_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuario_telefono` (
  `id_tel` int(11) NOT NULL AUTO_INCREMENT,
  `telefono` varchar(20) NOT NULL,
  `ID_usuario` int(11) NOT NULL,
  PRIMARY KEY (`id_tel`),
  KEY `idx_tel_usuario` (`ID_usuario`),
  CONSTRAINT `fk_tel_usuario` FOREIGN KEY (`ID_usuario`) REFERENCES `usuario` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vecino` (
  `ID_usuario` int(11) NOT NULL,
  PRIMARY KEY (`ID_usuario`),
  CONSTRAINT `fk_vecino_usuario` FOREIGN KEY (`ID_usuario`) REFERENCES `usuario` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `verificacion` (
  `ID_usuario` int(11) NOT NULL,
  `ID_contenedor` int(11) NOT NULL,
  `fecha` datetime NOT NULL,
  PRIMARY KEY (`ID_usuario`,`ID_contenedor`,`fecha`),
  KEY `idx_verificacion_contenedor` (`ID_contenedor`),
  CONSTRAINT `fk_verificacion_contenedor` FOREIGN KEY (`ID_contenedor`) REFERENCES `contenedor` (`ID`) ON UPDATE CASCADE,
  CONSTRAINT `fk_verificacion_usuario` FOREIGN KEY (`ID_usuario`) REFERENCES `usuario` (`ID`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

