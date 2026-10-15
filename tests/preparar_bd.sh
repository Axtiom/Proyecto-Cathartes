#!/usr/bin/env bash
# Prepara la base de PRUEBA (cathartes_test) y su usuario. Se corre una vez.
# Nunca toca la base real `cathartes`: los tests usan siempre cathartes_test.
#
# Uso:  ./preparar_bd.sh            (usa "mysql -uroot", pide contraseña si hace falta)
#       ADMIN_CMD="mysql -uroot -pXXXX -h127.0.0.1" ./preparar_bd.sh
set -euo pipefail
ADMIN_CMD="${ADMIN_CMD:-mysql -uroot}"
DB_TEST="${DB_NAME:-cathartes_test}"
USUARIO="${DB_USER:-cathartes_test}"
CLAVE="${DB_PASS:-cathartes_test_pass}"

# Desde qué hosts puede conectarse el usuario de PRUEBA (solo a la base *_test).
# En Docker se usa '%' porque los tests corren en otro contenedor.
HOSTS="${TEST_USER_HOSTS:-127.0.0.1 localhost}"

SQL="CREATE DATABASE IF NOT EXISTS \`${DB_TEST}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
for h in $HOSTS; do
  SQL+="CREATE USER IF NOT EXISTS '${USUARIO}'@'${h}' IDENTIFIED BY '${CLAVE}';"
  SQL+="GRANT ALL ON \`${DB_TEST}\`.* TO '${USUARIO}'@'${h}';"
done
SQL+="FLUSH PRIVILEGES;"
echo "$SQL" | $ADMIN_CMD
echo "Base de prueba lista: ${DB_TEST} (usuario ${USUARIO})."
