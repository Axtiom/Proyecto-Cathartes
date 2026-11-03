# Tests

Tres baterías, todas con PHPUnit 9 y contra una base **de prueba** (`cathartes_test`) que se
recarga desde `db/BD.sql` + `db/seed.sql` antes de cada test. Los tests se niegan a correr si
el nombre de la base no termina en `_test`: nunca tocan los datos reales.

| Batería | Archivo | Qué prueba |
|---|---|---|
| Unitarios `api-gestion` | `phpunit.gestion.xml` | Reglas y validaciones de contenedores, camiones, instalaciones, maquinaria e incidencias |
| Unitarios `api-usuarios` | `phpunit.usuarios.xml` | Cuentas (alta, edición, aprobación, baja, RN3/RN4), tokens y notificaciones |
| Por HTTP | `phpunit.http.xml` | Levanta las tres APIs y el backoffice y prueba sesión, **permisos por rol**, CRUD completos, el ciclo de incidencias, notificaciones, panel, CSRF y XSS |

Se corren por separado porque `api-usuarios` y `api-gestion` tienen clases con el mismo nombre
(`Usuario`, `Auth`…) y no pueden convivir en un mismo proceso PHP.

## Cómo correrlos (con XAMPP)

XAMPP trae PHP y MySQL; falta PHPUnit. Se descarga el `phpunit-9.6.phar` desde
<https://phpunit.de/getting-started/phpunit-9.html> y se deja en la carpeta del proyecto.

```bash
# Linux (XAMPP en /opt/lampp)
ADMIN_CMD="/opt/lampp/bin/mysql -uroot" ./tests/preparar_bd.sh      # una vez: crea cathartes_test y su usuario
PHPUNIT="/opt/lampp/bin/php phpunit-9.6.phar" ./tests/run.sh        # corre las tres baterías
```

```bat
:: Windows (XAMPP en C:\xampp), desde la carpeta del proyecto
C:\xampp\mysql\bin\mysql -uroot -e "source tests/preparar_bd.sql"
C:\xampp\php\php phpunit-9.6.phar -c tests\phpunit.gestion.xml
C:\xampp\php\php phpunit-9.6.phar -c tests\phpunit.usuarios.xml
C:\xampp\php\php phpunit-9.6.phar -c tests\phpunit.http.xml
```

Para correr una sola clase: `PHPUNIT=... ./tests/run.sh` corre todo; una clase suelta se corre con
`php phpunit-9.6.phar -c tests/phpunit.http.xml --filter SeguridadRolesTest`.

Requisitos de PHP: extensiones `pdo_mysql`, `mbstring` y `curl` (XAMPP las trae activas).
Variables opcionales: `DB_HOST`, `DB_NAME` (debe terminar en `_test`), `DB_USER`, `DB_PASS`.

## Qué garantizan
- Todo endpoint de gestión responde **401 sin sesión** y **403 a vecino, operario y cuadrilla**; solo el administrador pasa.
- Un usuario dado de baja pierde el acceso al instante, aunque conserve un token vigente.
- Ninguna respuesta de usuarios expone contraseñas ni hashes.
- Las escrituras del backoffice sin token CSRF se rechazan (419) y el texto libre se escapa contra XSS.
- Las validaciones del backend no dependen del frontend.
- Una cuadrilla no ve ni toca las intervenciones de otra; el stock de repuestos nunca queda negativo ni se descuenta a medias.
- El ciclo de una incidencia (reporte con foto → validación → asignación → cierre) genera cada aviso para quien corresponde, una sola vez.
- La alerta de instalación llena avisa al cruzar el 80 %, no en cada actualización.
- `ContratoFrontendTest` fija los nombres de campos que leen las pantallas: un cambio en la API que las rompería falla acá.

## Totales
295 tests: 60 (api-gestion) + 52 (api-usuarios) + 183 (HTTP).
