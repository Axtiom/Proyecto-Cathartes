# API de Gestión — SiGeRU / Cathartes

> Documento de traspaso. Explica **qué es esta API, cómo está armada, cómo se
> corre y cómo se le agregan endpoints**, para que se pueda seguir sin depender
> de nadie. Si algo choca con esto, la fuente de verdad última es el DDL
> (`db/BD.sql`) y el documento oficial del proyecto (sección 6, "Detalle de
> Implementación").

---

## 1. Qué es y qué rol cumple

La **API de Gestión** es una de las tres APIs REST del sistema. Cubre todo lo que
es **administración y operación**: el Backoffice (incidencias, usuarios,
contenedores, camiones, instalaciones, rutas, cuadrillas, recompensas, informes)
y la pantalla del **Operario** (instalaciones). También sirve las **estadísticas
públicas** que consume la landing.

Las otras dos APIs:
- **API de Usuarios** (`api-usuarios`) — autenticación (registro, login, 2FA,
  recuperación). Es la **plantilla PHP-MVC de referencia** de todo el monorepo.
- **API de Recolección** (`api-recoleccion`) — universo Cuadrilla (rutas del día,
  incidencias asignadas, camión).

**Regla de oro del proyecto:** los frontends (landing, frontend-usuarios) son
HTML + CSS + JS puro y **nunca** tocan la base. Todo dato sale de un `fetch` a una
de estas APIs. El acceso a `cathartes` por PDO vive **solo acá, del lado
servidor.**

---

## 2. Estado actual (qué está hecho y qué falta)

**Hecho (Fase 0):**
- Esqueleto PHP-MVC completo, funcionando, con conexión real a `cathartes`.
- `GET /health` — chequeo de motor + BD.
- `GET /estadisticas/publicas` — incidencias por tipo, por mes y por zona (real,
  sin auth). Alimenta la landing y la pantalla de estadísticas públicas.

**Falta (fases siguientes):** todos los endpoints de Backoffice y Operario. **Ya
están especificados** — firma, parámetros y forma del JSON de respuesta — en
[`../docs/contratos-api.md`](../docs/contratos-api.md). Esa es tu lista de trabajo:
implementá contra ese contrato y las vistas van a enganchar sin cambios.

---

## 3. Arquitectura (por qué está así)

Es **PHP 8 + MVC + REST**, con PDO contra MySQL 8 (`cathartes`). El esqueleto es
una **copia de `api-usuarios`** (la plantilla de referencia): mismos `app/Core/`,
mismo front controller, mismo Router. Esto es a propósito: una sola forma de
hacer las APIs en todo el monorepo. **No reinventes el patrón**; copiá y agregá.

Diferencias respecto de `api-usuarios`:
- **No tiene Mailer** (esta API no manda correos).
- **No emite tokens, solo los valida** (ver sección 6).

### Estructura de directorios

```
api-gestion/
├── public/
│   ├── index.php        ← único punto de entrada (front controller)
│   └── .htaccess        ← manda todo el tráfico a index.php
├── app/
│   ├── Core/            ← infraestructura (idéntica a api-usuarios)
│   │   ├── Env.php         lee el .env (CLAVE=valor)
│   │   ├── Config.php      expone la config con Config::get('db.host')
│   │   ├── Router.php      matchea la URL → controlador (NO tocar, ver §7)
│   │   ├── Controller.php  base: json() y entrada() (body JSON del request)
│   │   ├── Database.php    conexión PDO singleton a cathartes
│   │   ├── Token.php       token firmado HMAC (acá solo se usa para validar)
│   │   └── Auth.php        guard: Auth::proteger() valida Bearer + kill-switch
│   ├── Controllers/
│   │   ├── HealthController.php
│   │   └── EstadisticasController.php
│   └── Models/
│       ├── Usuario.php      (lo usa Auth para el kill-switch y el rol)
│       └── Estadisticas.php (queries de las estadísticas públicas)
├── config/
│   └── config.php       ← arma la config leyendo del .env
├── routes/
│   └── web.php          ← acá se registran las rutas (método + path → controlador)
├── storage/
├── .env.example         ← plantilla (SÍ se commitea)
└── .env                 ← credenciales reales (NUNCA se commitea, gitignored)
```

El flujo de un request: `public/index.php` carga los Core y el `.env`, arma el
`Router`, incluye `routes/web.php` (donde están las rutas) y despacha. El Router
busca el controlador en `app/Controllers/`, lo instancia y llama al método.

---

## 4. Cómo correrla localmente

Hay dos formas; usá la que te sirva.

### Opción A — Apache (XAMPP), como en producción

`C:\Xampp\htdocs\sigeru` es un **junction** a `D:\sigeru` (una sola fuente de
verdad — no hay copias). Con Apache corriendo, la API queda en:

```
http://localhost/sigeru/api-gestion/public/health
http://localhost/sigeru/api-gestion/public/estadisticas/publicas
```

### Opción B — Servidor embebido de PHP (rápido para probar)

Desde `api-gestion/`. **El flag `-t public` es obligatorio** (el docroot es
`public/`, no la raíz de la app):

```sh
php -S 127.0.0.1:8091 -t public
# luego:
curl http://127.0.0.1:8091/health
curl http://127.0.0.1:8091/estadisticas/publicas
```

> El Router funciona igual en las dos: recorta solo el prefijo del subdirectorio
> (Apache) o no recorta nada (raíz del `php -S`). No hay que cambiar nada entre
> entornos. Ver §7.

> **Nota de entorno:** el `mysql.exe` de este XAMPP no conecta (no carga
> `caching_sha2_password.dll`). Para queries manuales usá PHP/PDO (el mismo
> cliente que la API), no el `mysql` de consola.

---

## 5. El `.env` (importante)

Nada hardcodeado: todo sale del `.env`. El `.env.example` está commiteado como
plantilla; el `.env` real es local y está gitignored. Variables:

```
DB_HOST=localhost
DB_NAME=cathartes
DB_USER=root
DB_PASS=********
DB_CHARSET=utf8mb4
APP_TOKEN_SECRET=<el MISMO secreto que api-usuarios>
APP_TOKEN_TTL=7776000
```

**Lo crítico:** `APP_TOKEN_SECRET` **tiene que ser idéntico al de
`api-usuarios`**. Esta API no emite tokens, solo los valida; si el secreto
difiere, todo token de sesión da 401. Al pasar a producción, solo cambia el
`.env` (credenciales y secreto reales) — el código no se toca.

---

## 6. Cómo funciona la autenticación

Modelo de todo el sistema: **token firmado (HMAC-SHA256), sin tabla de tokens.**

- El token de sesión lo **emite `api-usuarios`** cuando el usuario se loguea (y
  pasa el 2FA). Contiene `{id, rol, exp, typ:'sesion'}` firmado con el secreto.
- El frontend lo guarda en el navegador y lo manda en cada request como header
  `Authorization: Bearer <token>`.
- **Esta API solo lo valida.** En un endpoint protegido llamás:

  ```php
  $usuario = Auth::proteger();  // devuelve ['id','rol','nombre','email'] o corta con 401
  ```

  `Auth::proteger()` hace tres cosas: valida la firma del token (mismo secreto),
  re-chequea `usuario.estado='activo'` contra la base (**kill-switch**: si un
  admin desactiva la cuenta, el token muere en el próximo request), y deduce el
  **rol** consultando en qué tabla de subtipo está el ID (`administrador` /
  `operario` / `cuadrilla` / `vecino`). El rol **no** es una columna: se deduce.

- Para exigir un rol, chequealo después de proteger:

  ```php
  $usuario = Auth::proteger();
  if ($usuario['rol'] !== 'administrador') {
      $this->json(['ok' => false, 'error' => 'Requiere rol Administrador'], 403);
      return;
  }
  ```

Los endpoints **públicos** (como `/estadisticas/publicas`) simplemente **no
llaman** a `Auth::proteger()`.

---

## 7. El Router — no lo reescribas

`app/Core/Router.php` está copiado **verbatim** de `api-usuarios` y **no se
toca**. Recorta dinámicamente el prefijo del subdirectorio con
`dirname($_SERVER['SCRIPT_NAME'])`, así matchea igual servido en subcarpeta de
Apache (`/sigeru/api-gestion/public/...`) o en la raíz de un vhost (Rocky Linux).
Esto viene de un bug ya resuelto (BUG-005): si reescribís el matcheo con paths
absolutos, la API devuelve 404 a todo bajo Apache. Registrá las rutas en
`routes/web.php`, no toques el Router.

---

## 8. Cómo agregar un endpoint nuevo (paso a paso)

Tomemos de ejemplo lo pendiente `GET /camiones` (lista de la flota). Tres pasos:

**1. Registrar la ruta** en `routes/web.php`:

```php
$router->get('/camiones', 'CamionController', 'index');
```

**2. Crear el Model** en `app/Models/Camion.php` (todo el acceso a datos va acá,
nunca en el controlador):

```php
<?php
declare(strict_types=1);

class Camion
{
    /** @return list<array> */
    public static function todos(): array
    {
        $sql = 'SELECT ID AS id, matricula, modelo, capacidad, estado FROM camion ORDER BY matricula';
        return Database::conexion()->query($sql)->fetchAll();
    }
}
```

**3. Crear el Controller** en `app/Controllers/CamionController.php`:

```php
<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Models/Camion.php';

class CamionController extends Controller
{
    public function index(): void
    {
        $usuario = Auth::proteger();                       // protegido
        if ($usuario['rol'] !== 'administrador') {
            $this->json(['ok' => false, 'error' => 'Requiere rol Administrador'], 403);
            return;
        }
        try {
            $this->json(['ok' => true, 'items' => Camion::todos()]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
```

Listo. El contrato exacto de cada endpoint pendiente (parámetros de filtro,
paginación, forma del JSON) está en [`../docs/contratos-api.md`](../docs/contratos-api.md).

### El endpoint que ya está — de referencia

`GET /estadisticas/publicas` (público) usa el Model `Estadisticas` con tres
queries reales de agregación sobre `incidencia_historial`. Notá que **"zona" no
existe en el MER**: se deriva de `ruta.nombre` con un JOIN
`incidencia → contenedor → ruta`. Cuando una pantalla pide un concepto sin
columna, se deriva de lo que hay y se documenta — **no se inventa una columna**.

---

## 9. Reglas no negociables (te ahorran bugs)

- **La columna de contraseña es `contrasena`, SIN ñ**, y en toda query PDO va con
  backticks: `` `contrasena` ``. La ñ se eliminó a propósito (BUG-003: se corrompía
  al importar el dump). Ningún identificador de tabla/columna lleva ñ ni acentos.
- **El MER está congelado.** No inventes tablas ni columnas. Si una pantalla
  parece necesitar un campo que no está en `db/BD.sql`, **pará y avisá** — no
  alteres el esquema, derivá el dato o consultalo.
- **Cuatro roles exactos:** Administrador, Cuadrilla, Operario, Vecino. "Chofer"
  no existe.
- **Datos reales, nada de demo.** Toda respuesta sale de queries a `cathartes`.
  Sin filas → arreglo vacío (`[]`), nunca datos inventados.
- **El sistema no borra:** las bajas se marcan con campos `estado` /
  `fecha_baja` / `motivo_baja`. Nada de `DELETE` (salvo el CASCADE del esquema en
  subtipos de usuario).
- **PDO con prepared statements** siempre que entre un valor del request (evita
  inyección SQL). Mirá `Estadisticas.php` (queries fijas con `query()`) vs.
  `Usuario.php` (parámetros con `prepare()/execute()`).

---

## 10. Ojo: la base está casi vacía

Hoy `cathartes` tiene pocas filas sembradas: `/estadisticas/publicas` devuelve
arreglos vacíos porque todavía no hay incidencias cargadas. Eso es **correcto**
(estado vacío, no demo). Para ver datos al probar tus endpoints, sembrá filas
realistas con `INSERT` (contenido ficticio, pero filas reales en la base). No
metas arrays de ejemplo en el código.

---

## 11. Verificación rápida

```sh
# desde api-gestion/
php -S 127.0.0.1:8091 -t public &
curl http://127.0.0.1:8091/health                    # {"ok":true,"db":true}
curl http://127.0.0.1:8091/estadisticas/publicas     # {"ok":true,"por_tipo":[],...}
curl -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8091/nope   # 404
```

Si `health` da `"db":false`, revisá las credenciales del `.env` y que MySQL esté
levantado.
