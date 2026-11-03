-- ============================================================================
--  Usuario de MySQL que usan las 4 apps (el mismo que traen los .env).
--  Se importa UNA vez, después de BD.sql y seed.sql (phpMyAdmin > Importar, o
--  /opt/lampp/bin/mysql -u root < db/crear_usuario_app.sql).
--
--  Tiene permisos SOLO sobre la base `cathartes`: la app nunca corre como root.
--  Si cambiás la clave acá, cambiala también en los 4 .env (DB_PASS).
-- ============================================================================
CREATE USER IF NOT EXISTS 'cathartes_app'@'localhost'  IDENTIFIED BY 'Y1yZaEULv5HxVJdWGI46Ko6o';
CREATE USER IF NOT EXISTS 'cathartes_app'@'127.0.0.1'  IDENTIFIED BY 'Y1yZaEULv5HxVJdWGI46Ko6o';
GRANT ALL PRIVILEGES ON cathartes.* TO 'cathartes_app'@'localhost';
GRANT ALL PRIVILEGES ON cathartes.* TO 'cathartes_app'@'127.0.0.1';
FLUSH PRIVILEGES;
