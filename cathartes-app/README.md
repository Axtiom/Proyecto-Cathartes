# cathartes-app

App unificada de Cathartes. Antes eran 4 aplicaciones PHP independientes
(`api-usuarios`, `api-gestion`, `api-recoleccion`, `backoffice`), cada una con
su propio `Model`/`View`/`Controller`/`Core`. Se fusionaron acá en una sola
app con **un solo** `app/Models`, `app/Views`, `app/Controllers` y `app/Core`.

`frontend-usuarios/` y `landing-page/` (HTML/JS estático) quedaron aparte,
como carpetas hermanas de esta — tal como estaban — y ahora le apuntan por
**ruta relativa** (`../cathartes-app/public/api`) en vez de una URL absoluta
tipo `http://localhost/...`.

## Qué cambió respecto del proyecto original

- **Sin doble factor de autenticación**: el login valida usuario y contraseña
  y abre sesión en un solo paso (antes había un paso intermedio con código
  enviado por correo). Se sacó `verificar.html`, el tipo de token `2fa` y el
  endpoint `/verificar`.
- **Rutas del backoffice** sin prefijo (`/usuarios`, `/incidencias`, ...) y
  **rutas de la ex-API** bajo `/api/*` (`/api/login`, `/api/vecino/...`,
  `/api/cuadrilla/ruta`, ...), todo en `routes/web.php`.
- **Nada hardcodeado**: el helper `hermana()` (en `app/Core/helpers.php`)
  arma la URL a `frontend-usuarios`/`landing-page` en base al propio
  `SCRIPT_NAME`, sin host ni carpeta fijos. El storage de fotos de
  incidencias pasó de un path absoluto (`D:/sigeru/...`) a uno relativo
  dentro de esta misma app (`storage/uploads/incidencias`).
- Controllers y Models que existían por duplicado en las 4 apps (por ejemplo,
  `Usuario`, `Contenedor`, `Incidencia`, `Recompensa`) se fusionaron en una
  sola clase por entidad. Donde dos controllers con el mismo nombre servían
  cosas distintas (admin vs. vecino/cuadrilla), el del lado admin conservó el
  nombre original y el otro se renombró: `ContenedorPublicoController`,
  `CuadrillaAppController`, `RecompensaVecinoController`,
  `AdminAccesoController` (entrada/logout del backoffice).

## Deploy

1. Copiá `.env.example` a `.env` y completá `DB_USER`, `DB_PASS` y
   `APP_TOKEN_SECRET` (cualquier cadena larga y aleatoria).
2. Apuntá el document root de esta app a `cathartes-app/public/`.
3. `frontend-usuarios/` y `landing-page/` se sirven aparte, como carpetas
   hermanas de `cathartes-app/` (mismo nivel), para que las rutas relativas
   funcionen.
