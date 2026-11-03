-- Prepara la base de PRUEBA y su usuario (para Windows/XAMPP, donde no hay bash).
-- Uso (desde la carpeta del proyecto):  C:\xampp\mysql\bin\mysql -uroot -e "source tests/preparar_bd.sql"
-- Nunca toca la base real `cathartes`.
CREATE DATABASE IF NOT EXISTS `cathartes_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'cathartes_test'@'127.0.0.1' IDENTIFIED BY 'cathartes_test_pass';
CREATE USER IF NOT EXISTS 'cathartes_test'@'localhost' IDENTIFIED BY 'cathartes_test_pass';
GRANT ALL ON `cathartes_test`.* TO 'cathartes_test'@'127.0.0.1';
GRANT ALL ON `cathartes_test`.* TO 'cathartes_test'@'localhost';
FLUSH PRIVILEGES;
