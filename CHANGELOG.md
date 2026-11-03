# Changelog

Formato: fecha · integrante responsable · cambio · módulo afectado.

## Tercera entrega
- **api-recoleccion:** 14 endpoints de cuadrilla y operario (resumen, rutas, mapa, intervenciones, repuestos, verificar contenedor,
  cambiar estado, usar repuestos, finalizar, ocupación de la instalación). Contrato en `docs/contrato-api-recoleccion.md`.
- **Incidencias:** ciclo completo vecino → administrador → cuadrilla. La cuadrilla finaliza desde territorio y el administrador
  puede finalizar desde el backoffice. Solo se asigna una incidencia **validada** (BUG-023).
- **Notificaciones y alertas:** modelo `Notificacion` (una copia por app), endpoints `/notificaciones` en api-usuarios, campana con
  contador en el frontend y pantalla en el backoffice. Avisos: nueva incidencia, validada, asignada, finalizada, intervención
  asignada e instalación al 80 % (solo al cruzar el umbral).
- **Mapa:** incidencias abiertas como halo sobre su contenedor (ámbar = sin validar, rojo = en curso), con filtro propio en el panel.
  `mapa.js` actualizado en sus tres copias (landing, frontend y backoffice).
- **Panel:** ranking de contenedores con más desbordes (este mes / últimos 30 días).
- **backoffice:** pantalla de notificaciones y panel de cierre de incidencias.
- **Frontend:** pantalla `notificaciones.html` (vecino, cuadrilla y operario) y mapa de la cuadrilla con incidencias.
  `guardia.js` acepta una lista de roles.
- **XAMPP:** instalación documentada paso a paso en el README. Los `.env` de las 4 apps vienen creados (mismo `APP_TOKEN_SECRET`)
  y `db/crear_usuario_app.sql` crea el usuario de base que usan. Los `.htaccess` cierran `app/`, `config/`, `routes/`, `db/`, `tests/`,
  `docs/` y `storage/`, bloquean `.env` y `.sql`, y redirigen `/backoffice/<pantalla>` a su entrada real.
- **Tests:** 295 (60 unitarios de api-gestion, 52 de api-usuarios y 183 por HTTP). Nuevos: recolección, flujo completo de incidencias,
  notificaciones, panel/informes y contrato con el frontend.
- **db/seed:** repuestos, verificaciones, notificaciones, una incidencia sin validar y textos de prueba con sentido.
- **Correcciones:** incidencia sin foto (BUG-022), asignar sin validar (BUG-023).

## Segunda entrega
- **api-usuarios:** CRUD completo de cuentas: `POST /usuarios` (alta con rol), `PUT /usuarios` (edición parcial),
  `DELETE /usuarios` (baja lógica), `POST /usuarios/aprobar` y `/rechazar`. Reglas RN3 y RN4 en el modelo.
- **api-gestion:** CRUD completo de contenedores (`PUT`, `DELETE` con motivo, `GET /contenedores/detalle`),
  camiones (`PUT`, `DELETE` con motivo), **instalaciones** y **maquinaria** (alta, lectura, edición, borrado).
- **Routers:** soportan `PUT` y `DELETE`. **Env:** las apps leen variables de entorno además del `.env` (los tests las usan para apuntar a la base de prueba).
- **backoffice:** pantalla de **edición de usuarios**, CRUD de **maquinaria**, y **eliminar instalación** (solo si no tiene dependencias).
- **Tests:** 212 tests con PHPUnit (unitarios de `api-gestion` y `api-usuarios`, y por HTTP: roles, CRUD, CSRF, XSS). Ver `tests/README.md`.
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
