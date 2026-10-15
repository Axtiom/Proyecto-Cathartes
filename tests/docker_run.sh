#!/usr/bin/env bash
# Punto de entrada del servicio `tests` de docker-compose: prepara la base de prueba y corre todo.
set -euo pipefail
cd "$(dirname "$0")"
export ADMIN_CMD="mysql -uroot -p${DB_PASS_ADMIN} -h${DB_HOST}"
export TEST_USER_HOSTS="%"
./preparar_bd.sh
./run.sh
