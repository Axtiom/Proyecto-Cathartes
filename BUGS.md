# BUGS.md — Registro de bugs del monorepo SiGeRU / Cathartes

> Documento vivo. Cada bug que se encuentra (en código propio, del equipo, de
> configuración o de entorno) se anota acá **antes o al momento de arreglarlo**.
> No se borra nada: los bugs resueltos quedan con estado `RESUELTO` para dejar
> trazabilidad de qué pasó y cómo se arregló (útil para el PDF integrador).

## Cómo se registra un bug

1. Agregá una fila a la tabla de resumen con el próximo `ID` (`BUG-00N`).
2. Escribí el detalle completo abajo, en la sección "Detalle", con el mismo ID.
3. Estados posibles: `ABIERTO` · `EN PROGRESO` · `RESUELTO` · `NO ES BUG`.
4. Severidad: `crítica` · `alta` · `media` · `baja`.

## Resumen

| ID      | Fecha      | Área             | Descripción corta                                   | Severidad | Estado   |
|---------|------------|------------------|-----------------------------------------------------|-----------|----------|
| BUG-001 | 2026-07-20 | Git / config     | `.gitignore` ignoraba también los `.env.example`    | alta      | RESUELTO |
| BUG-002 | 2026-07-20 | Frontend / CSS   | Columnas desiguales en `.fila-2` (registro)         | baja      | RESUELTO |
| BUG-003 | 2026-07-20 | BD / import      | Columna `contraseña` importada como `contrase├▒a`   | crítica   | RESUELTO |
| BUG-004 | 2026-07-23 | Frontend / CSS   | Cajas `.aviso` con `hidden` se mostraban vacías      | media     | RESUELTO |
| BUG-005 | 2026-07-23 | APIs / Router    | El Router solo matchea en la raíz del dominio        | alta      | RESUELTO |
| BUG-006 | 2026-07-23 | Entorno / hosting| Apache servía una copia stale de `htdocs\sigeru`     | alta      | RESUELTO |

---

## Detalle

### BUG-001 — `.gitignore` ignoraba los `.env.example`

- **Fecha:** 2026-07-20
- **Área:** Git / configuración del monorepo (`.gitignore` de la raíz)
- **Severidad:** alta
- **Estado:** RESUELTO
- **Detectado en:** armado del esqueleto de la "parte de funcionamiento"
  (api-usuarios / frontend-usuarios).

**Síntoma.** Al agregar los `.env.example` de las apps nuevas, `git status` no
los mostraba como para commitear.

**Causa.** El bloque de credenciales del `.gitignore` tenía:

```gitignore
.env
.env.*
```

El patrón `.env.*` matchea **cualquier** archivo que empiece con `.env.`,
incluido `.env.example`. Resultado: los `.env.example` (que SÍ tienen que
commitearse, son la plantilla del `.env` real) quedaban ignorados. El `.env`
real, que NUNCA se commitea, sí estaba bien ignorado — pero se llevaba puesto al
ejemplo.

**Impacto.** Un clon limpio del repo se habría quedado sin la plantilla de
variables de entorno: nadie sabría qué claves cargar en su `.env`. Rompe la
regla de portabilidad de la sección 6 del CLAUDE.md ("se commitea un
`.env.example`").

**Solución aplicada.** Se agregó una excepción explícita después del patrón:

```gitignore
.env
.env.*
!.env.example
!**/.env.example
config/database.php
**/config.local.php
```

`!.env.example` desprotege el de la raíz; `!**/.env.example` desprotege los de
cada app (`api-usuarios/.env.example`, `frontend-usuarios/.env.example`, etc.).

**Verificación.**

```sh
git check-ignore api-usuarios/.env.example   # (sin salida = NO ignorado, correcto)
git check-ignore api-usuarios/.env           # api-usuarios/.env (ignorado, correcto)
```

Los `.env.example` aparecen como archivos a commitear; los `.env` reales siguen
ignorados.

---

### BUG-002 — Columnas desiguales en las filas de dos campos (registro)

- **Fecha:** 2026-07-20
- **Área:** Frontend de Usuarios / CSS (`public/assets/css/estilos.css`, `.fila-2`)
- **Severidad:** baja
- **Estado:** RESUELTO
- **Detectado en:** revisión visual de la pantalla de registro (captura de Juan).

**Síntoma.** En las filas de dos campos (Nombre/Apellido y Contraseña/Confirmar
contraseña) la columna derecha quedaba un poco más ancha que la izquierda, en
vez de partir el ancho por la mitad.

**Causa.** La grilla usaba `grid-template-columns: 1fr 1fr`. Por defecto, un ítem
de grid tiene `min-width: auto`, así que su tamaño mínimo es el `min-content` de
lo que tiene adentro. Un `<input>` trae un ancho intrínseco (según su atributo
`size`), y eso empuja el track: las dos columnas `1fr` dejan de repartirse
parejo y una queda más ancha.

**Impacto.** Puramente estético (desalineación leve). No afecta funcionalidad ni
datos.

**Solución aplicada.** Se cambió el reparto a `minmax(0, 1fr)` y se agregó
`min-width: 0` a los ítems, para que el track ignore el `min-content` del input
y las columnas queden exactamente iguales:

```css
.fila-2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.fila-2 > .campo { min-width: 0; }
```

**Nota de patrón.** `minmax(0, 1fr)` es el fix estándar para columnas de grid que
"no quedan parejas" cuando adentro hay inputs, texto largo o `<pre>`. Tenerlo
presente para el resto de las apps del monorepo.

---

### BUG-003 — La columna `contraseña` quedó corrupta en la BD importada

- **Fecha:** 2026-07-20
- **Área:** Base de datos / proceso de import (no es código del repo)
- **Severidad:** crítica (bloquea todo el login/registro)
- **Estado:** RESUELTO
- **Detectado en:** smoke test de la API tras cablear el auth (`POST /login`).

**Síntoma.** Cualquier endpoint que toca la contraseña tira
`SQLSTATE[42S22] 1054 Unknown column 'contraseña'`.

**Causa.** El DDL `db/BD.sql` define la columna con ñ UTF-8 sana
(bytes `63 6F 6E 74 72 61 73 65 C3 B1 61` = `contraseña`). Pero al **importar** el
dump a MySQL, el cliente interpretó los bytes `C3 B1` (la ñ) como **CP437**
(`├` + `▒`) y los guardó re-encodeados. La columna viva quedó nombrada
`contrase├▒a` (HEX `...65 E2949C E29692 61`). Verificado contra
`information_schema.COLUMNS`.

El código de la API es correcto: usa `` `contraseña` `` como manda el DDL y el
CLAUDE.md. **No se toca el código** para "matchear" el nombre corrupto — eso
violaría el MER y se rompería al reimportar bien.

**Impacto.** `registro`, `login`, `verificar`, `restablecer` fallan con 500. Nada
de auth funciona hasta arreglar la BD.

**Solución aplicada — erradicar la ñ del identificador (decisión de Juan).**
En vez de solo re-importar (que dejaría la ñ como riesgo latente en cada import
futuro), se renombró la columna a **`contrasena`** (ASCII, misma palabra sin
tilde). Un identificador ASCII no se puede corromper por codificación. Se tocó:

- `db/BD.sql` — DDL, fuente de verdad (ñ sana → `contrasena`).
- `db/dump_estructura.sql` — el dump estaba en UTF-16LE con la ñ doble-mangleada
  (`Ôö£ÔûÆ`); reemplazo por bytes.
- `api-usuarios/app/Models/Usuario.php` — las 3 queries (`SELECT/INSERT/UPDATE`).
- `api-usuarios/app/Core/Database.php` y los comentarios de los controllers.
- **BD viva:** `ALTER TABLE usuario CHANGE COLUMN` de la columna corrupta a
  `contrasena`. Escaneo posterior de `information_schema`: **cero** identificadores
  no-ASCII en toda la base.
- `CLAUDE.md` §3 — la regla ahora dice `contrasena` (sin ñ) y agrega la regla
  derivada: **ningún identificador (tabla/columna) lleva ñ ni acentos; los dumps
  se importan/exportan siempre en UTF-8**.

**Verificación.** E2E por la API (registro → login 403 → aprobar → 2FA → `/yo` →
kill-switch → recuperar → restablecer → login con clave nueva): 10/10 OK.
`HEX(COLUMN_NAME)` de la nueva columna = `636F6E74726173656E61` (ASCII puro).

---

### BUG-004 — Las cajas `.aviso` con `hidden` se mostraban vacías

- **Fecha:** 2026-07-23
- **Área:** Frontend de Usuarios / CSS (conversión PHP→HTML de la reestructuración)
- **Severidad:** media (rompe el visual de todas las pantallas de acceso)
- **Estado:** RESUELTO
- **Detectado en:** revisión visual de `login.html` (captura de Juan) tras pasar
  los frontends a HTML/CSS/JS.

**Síntoma.** Arriba de "Correo electrónico" aparecía una caja vacía (parecía un
input sin etiqueta) y otra con borde rojo, ícono y sin texto. Eran las cajas de
flash (`.aviso--dev`) y de error (`.aviso--error`), que en PHP estaban dentro de
un `<?php if (...) ?>` y ahora traían el atributo `hidden` — pero se mostraban
igual, vacías.

**Causa.** `.aviso { display: flex }` (hoja de autor) le gana a
`[hidden] { display: none }` (hoja del navegador): a igual especificidad, el
origen "autor" vence al "user-agent" en el cascade. El atributo `hidden`, por sí
solo, no oculta un elemento cuya clase declara `display`. Los `panel-ok`
(`.tarjeta`) y `.campo__error` no sufrían el bug porque sus clases no declaran
`display`, así que ahí `[hidden]` sí aplica.

**Impacto.** Puramente visual, pero en las 6 pantallas de acceso (7 cajas
`.aviso` en total). No afecta datos ni lógica.

**Solución aplicada.** Regla de normalización en `frontend-usuarios/css/estilos.css`
(un **agregado**, no un reescrito de estilos existentes):

```css
[hidden] { display: none !important; }
```

El JS ya togglea con `el.hidden = true/false`, así que no se tocó ni HTML ni JS.
Arregla las 7 cajas y cualquier futura.

**Verificación.** Todas las cajas ocultas por defecto; se muestran solo cuando el
controlador JS les inyecta texto por `textContent`.

---

### BUG-005 — El Router de las APIs solo matchea servido en la raíz del dominio

- **Fecha:** 2026-07-23
- **Área:** APIs / `app/Core/Router.php` (detectado en api-usuarios; **a replicar**
  en api-gestion, api-recoleccion y backoffice)
- **Severidad:** alta (la API responde 404 a todos sus endpoints bajo Apache)
- **Estado:** RESUELTO
- **Detectado en:** cablear el Frontend estático contra la API real por Apache.

**Síntoma.** `GET http://localhost/sigeru/api-usuarios/public/health` devolvía el
404 JSON **de la propia API** (`{"error":"no encontrado"}`), aunque el front
controller `index.php` sí ejecutaba.

**Causa.** `dispatch()` comparaba el path exacto de las rutas (`/health`,
`/login`, ...) contra el `REQUEST_URI` **completo**. Bajo Apache en subcarpeta,
ese path incluye el prefijo del subdirectorio
(`/sigeru/api-usuarios/public/health`), que nunca es igual a `/health`. El Router
asumía estar servido en la raíz del dominio, como pasa con
`php -S host:port -t public` (por eso ahí sí andaba).

**Impacto.** Con la app hosteada en subcarpeta de Apache (el caso local y
cualquier deploy que no sea vhost raíz), **ningún** endpoint matchea → auth
totalmente rota desde el frontend.

**Solución aplicada.** Se agregó `normalizarPath()`, que recorta dinámicamente el
prefijo deducido de `dirname($_SERVER['SCRIPT_NAME'])` — nada hardcodeado:

```php
$base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
if ($base !== '' && $base !== '/' && $base !== '.' && strpos($path, $base) === 0) {
    $path = substr($path, strlen($base));
}
```

Funciona igual en subcarpeta (Apache local) y en la raíz de un vhost (Rocky
Linux): el día del deploy no hay que cambiar nada.

**A replicar.** Este mismo Router se va a copiar a las otras tres APIs/backoffice
cuando se implementen. Arreglado una vez, se replica.

**Verificación.** `GET .../public/health` por Apache → `{"ok":true,"db":true}`
(HTTP 200); y servido en raíz con `php -S :8083` sigue dando 200. Cadena e2e
completa (registro → 403 → aprobar → login → 2FA → `/yo` → kill-switch) OK.

---

### BUG-006 — Apache servía una copia stale de `htdocs\sigeru`, no el working tree

- **Fecha:** 2026-07-23
- **Área:** Entorno / hosting local (XAMPP `htdocs`)
- **Severidad:** alta (Apache servía código viejo; se presentaba como "la API no
  responde")
- **Estado:** RESUELTO
- **Detectado en:** diagnóstico de por qué el fix del Router y la reestructuración
  no aparecían por Apache.

**Síntoma.** Los cambios hechos en `D:\sigeru` no se reflejaban al servir por
Apache: la API y la landing devolvían versiones viejas; el frontend apuntaba a
`http://127.0.0.1:8081`, un puerto donde no escuchaba nada (Apache está en :80).

**Causa.** `C:\Xampp\htdocs\sigeru` **no era un link** a `D:\sigeru`: era una
**copia manual desincronizada** (carpeta común, con fechas mezcladas 20–22 jul).
Apache servía esa copia, ajena al working tree y a git.

**Impacto.** Horas de diagnóstico confuso: fixes correctos en el repo que "no
tenían efecto". Cualquier flujo por navegador usaba código stale.

**Solución aplicada.** Se conservó la copia como backup y se reemplazó por un
junction al working tree:

```
ren       C:\Xampp\htdocs\sigeru  sigeru-stale-20jul     (backup reversible)
mklink /J C:\Xampp\htdocs\sigeru  D:\sigeru              (sin admin)
```

Una sola fuente de verdad: lo que se edita en `D:\sigeru` se sirve al instante.
Además el frontend pasó a apuntar a la URL real de Apache
(`http://localhost/sigeru/api-usuarios/public`), mismo origen que el frontend, lo
que además elimina la necesidad de CORS. (El `ren` requirió cerrar antes una
ventana de Explorer y una pestaña de VS Code — `BD.sql` de la copia — que tenían
la carpeta tomada.)

**Verificación.** Junction confirmado (`LinkType: Junction`, `Target: D:\sigeru`);
Apache sirve el código nuevo (landing con `data-stat`, login con `data-page`,
Router con `normalizarPath`); cadena e2e login→2FA→`/yo` OK.

**Nota para producción.** En Rocky Linux el equivalente es un symlink
(`ln -s /ruta/al/repo /var/www/...`) o servir el repo directo como docroot del
vhost. No versionar copias manuales del árbol en el docroot.
