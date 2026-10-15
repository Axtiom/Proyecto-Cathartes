# Changelog

Formato: fecha · integrante responsable · cambio · módulo afectado.

## Segunda entrega
- **api-usuarios:** CRUD completo de cuentas: `POST /usuarios` (alta con rol), `PUT /usuarios` (edición parcial),
  `DELETE /usuarios` (baja lógica), `POST /usuarios/aprobar` y `/rechazar`. Reglas RN3 y RN4 en el modelo.
- **api-gestion:** CRUD completo de contenedores (`PUT`, `DELETE` con motivo, `GET /contenedores/detalle`),
  camiones (`PUT`, `DELETE` con motivo), **instalaciones** y **maquinaria** (alta, lectura, edición, borrado).
- **Routers:** soportan `PUT` y `DELETE`. **Env:** las apps leen variables de entorno además del `.env` (necesario para Docker).
- **backoffice:** pantalla de **edición de usuarios**, CRUD de **maquinaria**, y **eliminar instalación** (solo si no tiene dependencias).
- **Tests:** 212 tests con PHPUnit (unitarios de `api-gestion` y `api-usuarios`, y por HTTP: roles, CRUD, CSRF, XSS). Ver `tests/README.md`.
- **Docker:** `Dockerfile`, `docker-compose.yml` (web + MySQL 8 + servicio de tests) y configuración de Apache que cierra
  `app/`, `config/`, `db/`, `.env` y `storage/`.
- **db/seed:** datos de prueba ampliados (4 camiones en distintos estados, 8 contenedores, maquinaria, 2 solicitudes pendientes).
- **Correcciones de la primera entrega:** 500 sin detalle interno, `/health` sin filtrar errores y con 503, colisión de
  auditoría en el mismo segundo, acentos del seed (BUG-017 a BUG-021).

## Primera entrega
- **api-usuarios:** `GET /usuarios` y `GET /usuarios/detalle` (solo administrador, sin datos de acceso).
- **api-gestion:** `POST /contenedores`, `GET /rutas`, `GET /camiones`, `GET /camiones/detalle`
  y `POST /camiones` (solo administrador).
- **Auth (api-usuarios, api-gestion):** nuevo `Auth::exigirRol()`, control de rol en el backend con 403 JSON.
- **db:** `db/dump_estructura.sql` (dump de estructura) y `docs/modelo_fisico.md`.
- **db:** `BD.sql` y `seed.sql` fijan `SET NAMES utf8mb4` (los acentos se guardaban rotos con el cliente en latin1).
- **db/seed:** se reemplazaron datos de prueba que no tenían sentido (vertedero y ruta de relleno).
- **seguridad:** los `.env.example` de las 4 apps traían una contraseña de base real; se reemplazó por un valor de ejemplo
  y un usuario propio (`cathartes_app`). **Si esa contraseña se usó en algún servidor, hay que cambiarla**,
  porque quedó en el historial de Git.
- **docs:** `docs/API.md`, `docs/GITHUB.md`, plantilla de Pull Request.
