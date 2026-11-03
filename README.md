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
| `api-recoleccion/`  | Cuadrilla en territorio (rutas, intervenciones, repuestos, mapa) y operario de instalación | JSON |
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

## Instalación en XAMPP

Pensado para **XAMPP** (Apache 2.4 + MySQL/MariaDB + PHP 8). No hace falta instalar nada más.

**1. Copiar el proyecto.** Poné la carpeta completa dentro de `htdocs`, por ejemplo
`C:\xampp\htdocs\cathartes` (Windows) o `/opt/lampp/htdocs/cathartes` (Linux). Las seis carpetas de las
aplicaciones y `storage/` tienen que quedar **hermanas**, como vienen: las rutas entre ellas son relativas.

**2. Arrancar Apache y MySQL** desde el panel de XAMPP (o `sudo /opt/lampp/lampp start`).

**3. Crear la base.** En phpMyAdmin (`http://localhost/phpmyadmin`), pestaña *Importar*, en este orden:
`db/BD.sql` (estructura), `db/seed.sql` (datos de prueba) y `db/crear_usuario_app.sql` (el usuario
`cathartes_app`, con permisos solo sobre la base `cathartes`: las apps nunca corren como `root`). O por línea de
comandos:

```bash
/opt/lampp/bin/mysql -u root < db/BD.sql
/opt/lampp/bin/mysql -u root < db/seed.sql
/opt/lampp/bin/mysql -u root < db/crear_usuario_app.sql
```

**4. Los `.env` ya vienen creados** en las 4 apps PHP (`api-usuarios`, `api-gestion`, `api-recoleccion` y
`backoffice`), con la clave del usuario `cathartes_app` y un `APP_TOKEN_SECRET` aleatorio que es **el mismo en las
4** (así la sesión iniciada en una vale en las demás). No hay que tocar nada. Si cambiás la clave de la base,
cambiala en `db/crear_usuario_app.sql` y en los 4 `.env` (`DB_PASS`).

> **Cuidado con los `.env`:** tienen claves. El `.gitignore` los excluye, así que `git push` no los sube; no los
> subas a ningún lado ni los compartas. Antes de publicar el sistema, generá una clave y un secreto nuevos
> (`openssl rand -hex 32`), y poné `COOKIE_SECURE=true` si usás HTTPS. Si perdés los `.env`, se recrean
> copiando `.env.example` y completando esos tres valores.

**5. Verificar Apache.** Las protecciones del proyecto son archivos `.htaccess`, y necesitan `mod_rewrite` y
`AllowOverride All` en `htdocs` (XAMPP los trae así por defecto). Si en tu instalación no es así, en
`conf/httpd.conf` dejá:

```apache
<Directory "C:/xampp/htdocs">
    AllowOverride All
</Directory>
```

**6. Fotos de incidencias.** La carpeta `storage/uploads/incidencias` tiene que poder escribirla Apache. En
Linux: `sudo chown -R daemon:daemon storage` (el usuario de Apache en XAMPP). En Windows no hace falta.

**7. Probar.**

| Qué | URL |
|---|---|
| Página pública | `http://localhost/cathartes/landing-page/` |
| Vecino, cuadrilla y operario | `http://localhost/cathartes/frontend-usuarios/login.html` |
| Administrador (backoffice) | se entra desde el mismo login, o `http://localhost/cathartes/backoffice/` |
| Estado de las APIs | `http://localhost/cathartes/api-gestion/public/health` → `{"ok":true,"db":true}` |

Cuentas de prueba (clave `Cathartes2026`): `admin@cathartes.uy`, `admin2@cathartes.uy` (administrador común),
`operario@cathartes.uy`, `cuadrilla@cathartes.uy` y `vecino@correo.com`. **Cambiarlas antes de exponer el sistema.**

### Qué queda protegido
Como todo el proyecto cuelga de `htdocs`, se cierran con `.htaccess` (`Require all denied`) las carpetas
internas: `app/`, `config/` y `routes/` de cada app, `db/`, `tests/`, `docs/`, `storage/` y `.github/`. El
`.htaccess` de la raíz bloquea los archivos `.env`, `.sql`, `.md` y `.sh`, desactiva el listado de carpetas y
redirige `/backoffice/<pantalla>` a `/backoffice/public/<pantalla>` (así funciona el destino del login del administrador, con cualquier nombre de carpeta). Se puede comprobar: abrir
`http://localhost/cathartes/api-usuarios/.env` o `.../db/BD.sql` tiene que dar **403**.

### Si cada app tiene su propio dominio o carpeta pública
También se puede apuntar el document root de cada app a su `public/` (como en un servidor de producción). En ese
caso las apps siguen las URLs hermanas por sí mismas y los `.htaccess` de arriba no hacen falta.

## Documentación

- `docs/API.md` — endpoints de las tres APIs, con ejemplos, el ciclo de una incidencia y quién recibe cada aviso.
- `docs/contrato-api-recoleccion.md` — respuestas exactas de la API de recolección (lo que leen las pantallas).
- `tests/README.md` — cómo correr los tests y qué cubren.
- `docs/modelo_fisico.md` — modelo físico de la base (diagrama + tablas).
- `docs/GITHUB.md` — organización, ramas, Pull Requests y convención de commits.
- `CHANGELOG.md` — registro de cambios.

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
