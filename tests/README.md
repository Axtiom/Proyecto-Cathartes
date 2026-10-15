# Tests

Tres baterías, todas con PHPUnit 9 y contra una base **de prueba** (`cathartes_test`) que se
recarga desde `db/BD.sql` + `db/seed.sql` antes de cada test. Los tests se niegan a correr si
el nombre de la base no termina en `_test`: nunca tocan los datos reales.

| Batería | Archivo | Qué prueba |
|---|---|---|
| Unitarios `api-gestion` | `phpunit.gestion.xml` | Reglas y validaciones de contenedores, camiones, instalaciones y maquinaria |
| Unitarios `api-usuarios` | `phpunit.usuarios.xml` | Cuentas (alta, edición, aprobación, baja, RN3/RN4) y tokens |
| Por HTTP | `phpunit.http.xml` | Levanta las APIs y el backoffice y prueba sesión, **permisos por rol**, CRUD completos, CSRF y XSS |

Se corren por separado porque `api-usuarios` y `api-gestion` tienen clases con el mismo nombre
(`Usuario`, `Auth`…) y no pueden convivir en un mismo proceso PHP.

## Cómo correrlos

Con Docker (no hace falta instalar nada):

```bash
cp .env.example .env     # completar claves
docker compose --profile tests run --rm tests
```

Sin Docker (PHP 8.x con `pdo_mysql` y `curl`, y PHPUnit 9):

```bash
./tests/preparar_bd.sh      # una vez: crea cathartes_test y su usuario (pide la clave de root de MySQL)
./tests/run.sh              # corre las tres baterías
phpunit -c tests/phpunit.http.xml --filter SeguridadRolesTest   # o una sola
phpunit -c tests/phpunit.http.xml --filter SeguridadRolesTest
```

Variables opcionales: `DB_HOST`, `DB_NAME` (debe terminar en `_test`), `DB_USER`, `DB_PASS`.

## Qué garantizan
- Todo endpoint de gestión responde **401 sin sesión** y **403 a vecino, operario y cuadrilla**; solo el administrador pasa.
- Un usuario dado de baja pierde el acceso al instante, aunque conserve un token vigente.
- Ninguna respuesta de usuarios expone contraseñas ni hashes.
- Las escrituras del backoffice sin token CSRF se rechazan (419) y el texto libre se escapa contra XSS.
- Las validaciones del backend no dependen del frontend.
