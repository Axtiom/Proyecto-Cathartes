#!/usr/bin/env bash
# Corre toda la batería de tests. Requisitos: php 8.x con pdo_mysql y curl, phpunit 9+,
# y la base de prueba creada con ./preparar_bd.sh.
#
# Son tres corridas separadas porque api-usuarios y api-gestion tienen clases con el
# mismo nombre (Usuario, Auth...) y no pueden convivir en un mismo proceso PHP.
set -uo pipefail
cd "$(dirname "$0")"
PHPUNIT="${PHPUNIT:-phpunit}"
fallo=0
for cfg in phpunit.gestion.xml phpunit.usuarios.xml phpunit.http.xml; do
  echo; echo "=== $cfg ==="
  $PHPUNIT -c "$cfg" || fallo=1
done
exit $fallo
