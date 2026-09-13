# Proyecto Cathartes

Sistema de gestión de residuos urbanos (Cathartes / SiGeRU) para el
departamento de Montevideo. Seis aplicaciones independientes, cada una con
su propio `Model`/`View`/`Controller`/`Core`, que comparten una única base
de datos MySQL.

## Aplicaciones

| Carpeta            | Qué es                          | Sirve             |
|---------------------|----------------------------------|--------------------|
| `api-usuarios/`     | Autenticación, registro, perfil  | JSON               |
| `api-gestion/`      | Contenedores, estadísticas, incidencias del vecino | JSON |
| `api-recoleccion/`  | Ruta del día de la cuadrilla     | JSON               |
| `backoffice/`       | Panel de administración municipal | HTML server-rendered |
| `frontend-usuarios/`| Interfaz de vecino, cuadrilla y operario | HTML/JS estático |
| `landing-page/`     | Página pública informativa       | HTML/JS estático   |

Las 4 primeras son apps PHP (MVC); las 2 últimas son estáticas y consumen las
APIs por `fetch`. Las 6 cuelgan del mismo nivel en el repo (son carpetas
hermanas), más una carpeta `storage/` para las fotos de incidencias,
compartida entre `api-gestion` (que las escribe) y `backoffice` (que las lee).

## Sesión compartida

Las 4 apps PHP validan la misma cookie de sesión (`cathartes_sesion`, HMAC
firmado), emitida por `api-usuarios` en el login. Por eso **el
`APP_TOKEN_SECRET` del `.env` tiene que ser idéntico en las 4** — si no
coincide, una app no puede validar la sesión que abrió otra.

No hay verificación en dos pasos: el login es de un solo paso (usuario +
contraseña).

## Deploy

1. En cada una de las 4 apps PHP (`api-usuarios`, `api-gestion`,
   `api-recoleccion`, `backoffice`), copiá `.env.example` a `.env` y
   completá `DB_USER`, `DB_PASS` y `APP_TOKEN_SECRET` (el mismo valor en las
   4).
2. Apuntá el document root de cada app a su propia carpeta `public/`.
3. Las 6 carpetas (`api-usuarios`, `api-gestion`, `api-recoleccion`,
   `backoffice`, `frontend-usuarios`, `landing-page`) tienen que quedar como
   hermanas en el servidor (mismo nivel), para que las rutas relativas entre
   ellas funcionen. Lo mismo la carpeta `storage/`.
4. Importá `db/BD.sql` (estructura) y `db/seed.sql` (datos de prueba) en ese
   orden.

## Qué cambió respecto del proyecto original

- **Sin doble factor de autenticación**: el login valida usuario y
  contraseña y abre sesión en un solo paso.
- **Sin sistema de puntos/recompensas**: se dio de baja toda la
  gamificación (catálogo de recompensas, canjes, saldo de puntos). El vecino
  reporta y sigue sus incidencias igual, sin ningún incentivo asociado.
- **Nada hardcodeado**: el helper `hermana()` (en `app/Core/helpers.php` de
  cada app) arma la URL a una carpeta hermana (`frontend-usuarios`,
  `backoffice`, etc.) en base al propio `SCRIPT_NAME`, sin host ni carpeta
  fijos. El storage de fotos de incidencias es una ruta relativa
  (`storage/uploads/incidencias`, hermana de las 4 apps), no un path
  absoluto de una máquina en particular.

Ver `BUGS.md` para el detalle de bugs encontrados y corregidos durante el
desarrollo.
