# BUGS.md — Registro de bugs del monorepo Cathartes

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
| BUG-006 | 2026-07-23 | Entorno / hosting| Apache servía una copia stale de `htdocs\cathartes`     | alta      | RESUELTO |
| BUG-007 | 2026-07-24 | BD / documentación| Doc de Fase 3.9 pedía crear `toma`, pero ya existía | media     | RESUELTO |
| BUG-008 | 2026-07-24 | Backoffice / PHP | `extract(EXTR_SKIP)` con clave `datos` no llegaba a la vista | media     | RESUELTO |
| BUG-009 | 2026-07-24 | Backoffice / seguridad | El admin escribe la contraseña del usuario que crea | baja      | ABIERTO |
| BUG-010 | 2026-07-26 | Backoffice / alta multirol | El alta dejaba asignarle un camión a un Vecino     | alta      | RESUELTO |
| BUG-011 | 2026-07-26 | Backoffice / marca | El logo del sidebar perdió el ala izquierda y las ruedas | baja      | RESUELTO |
| BUG-012 | 2026-07-26 | Backoffice / seguridad | Un error de PDO imprimía el stack trace en el navegador | alta      | RESUELTO |
| BUG-013 | 2026-07-26 | Backoffice / validación | Un número demasiado grande desbordaba la columna y daba 500 | media     | RESUELTO |
| BUG-014 | 2026-07-27 | Frontend / chrome por rol | Cuadrilla y operario veían el header y el footer del vecino | alta      | RESUELTO |
| BUG-015 | 2026-07-27 | Frontend / red | Un request colgado dejaba la pantalla en «Cargando…» para siempre | media     | RESUELTO |
| BUG-016 | 2026-07-27 | Frontend / chrome por rol | Con el partial en caché no se dibujaba ningún header y no se podía cerrar sesión | alta      | RESUELTO |
| BUG-017 | 2026-10-14 | Seguridad / config | Los `.env.example` traían una contraseña de base real | crítica   | RESUELTO |
| BUG-018 | 2026-10-14 | APIs / seguridad | Los 500 devolvían el mensaje de la excepción al cliente | alta      | RESUELTO |
| BUG-019 | 2026-10-14 | APIs / seguridad | `/health` (público) mostraba el error de MySQL con usuario y host | media     | RESUELTO |
| BUG-020 | 2026-10-14 | BD / auditoría | Dos acciones sobre la misma cuenta en el mismo segundo daban 500 (PK de `gestiona`/`autorizacion`) | media     | RESUELTO |
| BUG-021 | 2026-10-14 | BD / seed | Acentos rotos al cargar el seed y datos de prueba sin sentido | baja      | RESUELTO |

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

**Síntoma.** `GET http://localhost/cathartes/api-usuarios/public/health` devolvía el
404 JSON **de la propia API** (`{"error":"no encontrado"}`), aunque el front
controller `index.php` sí ejecutaba.

**Causa.** `dispatch()` comparaba el path exacto de las rutas (`/health`,
`/login`, ...) contra el `REQUEST_URI` **completo**. Bajo Apache en subcarpeta,
ese path incluye el prefijo del subdirectorio
(`/cathartes/api-usuarios/public/health`), que nunca es igual a `/health`. El Router
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

### BUG-006 — Apache servía una copia stale de `htdocs\cathartes`, no el working tree

- **Fecha:** 2026-07-23
- **Área:** Entorno / hosting local (XAMPP `htdocs`)
- **Severidad:** alta (Apache servía código viejo; se presentaba como "la API no
  responde")
- **Estado:** RESUELTO
- **Detectado en:** diagnóstico de por qué el fix del Router y la reestructuración
  no aparecían por Apache.

**Síntoma.** Los cambios hechos en `D:\cathartes` no se reflejaban al servir por
Apache: la API y la landing devolvían versiones viejas; el frontend apuntaba a
`http://127.0.0.1:8081`, un puerto donde no escuchaba nada (Apache está en :80).

**Causa.** `C:\Xampp\htdocs\cathartes` **no era un link** a `D:\cathartes`: era una
**copia manual desincronizada** (carpeta común, con fechas mezcladas 20–22 jul).
Apache servía esa copia, ajena al working tree y a git.

**Impacto.** Horas de diagnóstico confuso: fixes correctos en el repo que "no
tenían efecto". Cualquier flujo por navegador usaba código stale.

**Solución aplicada.** Se conservó la copia como backup y se reemplazó por un
junction al working tree:

```
ren       C:\Xampp\htdocs\cathartes  cathartes-stale-20jul     (backup reversible)
mklink /J C:\Xampp\htdocs\cathartes  D:\cathartes              (sin admin)
```

Una sola fuente de verdad: lo que se edita en `D:\cathartes` se sirve al instante.
Además el frontend pasó a apuntar a la URL real de Apache
(`http://localhost/cathartes/api-usuarios/public`), mismo origen que el frontend, lo
que además elimina la necesidad de CORS. (El `ren` requirió cerrar antes una
ventana de Explorer y una pestaña de VS Code — `BD.sql` de la copia — que tenían
la carpeta tomada.)

**Verificación.** Junction confirmado (`LinkType: Junction`, `Target: D:\cathartes`);
Apache sirve el código nuevo (landing con `data-stat`, login con `data-page`,
Router con `normalizarPath`); cadena e2e login→2FA→`/yo` OK.

**Nota para producción.** En Rocky Linux el equivalente es un symlink
(`ln -s /ruta/al/repo /var/www/...`) o servir el repo directo como docroot del
vhost. No versionar copias manuales del árbol en el docroot.

---

### BUG-007 — La documentación pedía crear una tabla (`toma`) que ya existía

**Área:** BD / documentación. **Severidad:** media. **Estado:** RESUELTO.

**Qué pasó.** El prompt de la Fase 3.9 abría con una "reparación": crear la tabla
puente `toma` (Cuadrilla↔Ruta) porque, según el MER y el pasaje a tablas, "nunca
llegó al DDL". Al ejecutar el `CREATE TABLE toma`, MySQL cortó con
`1050 Table 'toma' already exists`.

**Causa.** La tabla **ya estaba en la base viva y en `db/BD.sql`**, pero con una
forma distinta a la que asumía el documento: PK `(ID_cuadrilla, ID_ruta, fecha)`
—incluye una columna `fecha`— en vez de la PK de dos columnas sin atributos que
proponía el MD. No es un descuido: es el mismo patrón temporal que usan las otras
relaciones M:N del esquema (`autorizacion`, `gestiona`, `verificacion`), todas con
`fecha` en la PK para guardar historial. La documentación (MER/pasaje/diccionario)
venía por detrás del DDL. Se destapó también que el relevamiento de la Fase 4 solo
listaba 15 de las 22 tablas reales (faltaban `toma`, `autorizacion`, `gestiona`,
`maquinaria`, `repuestos`, `usa`, `verificacion`).

**Solución.** Se aplicó la Regla cero ("el DDL gana"): no se tocó `toma`. Decisión
de Juan (2026-07-24): dejarla como está y solo sembrarla (incluyendo `fecha=NOW()`).
Se quitó el `CREATE TABLE toma` de `db/migracion-2026-07-24.sql` y se ajustó el
`INSERT` de seed para incluir `fecha`. El resto de la migración (ruta.zona/estado,
camion.fecha_baja/motivo_baja, cuadrilla.estado, rename de la cuadrilla, 2ª ruta)
se ejecutó sin problemas.

**Verificación.** `SHOW CREATE TABLE toma` confirma que quedó intacta; la fila de
seed `(3, 1, NOW())` entró bien. `db/BD.sql` ya tenía las 22 tablas: no hubo drift
de tablas, solo del *contenido* de la doc conceptual, que Juan actualizará el lunes.

**Insumo para la doc.** Al reconciliar MER/pasaje/diccionario: `toma` es una
relación **con atributo temporal** (`fecha` en la PK), no una simple asociación
binaria. Y sí existe el vínculo Cuadrilla↔Ruta en el modelo (vía `toma`), cosa que
el relevamiento parcial de la Fase 4 había reportado erróneamente como ausente.

---

### BUG-008 — `extract(EXTR_SKIP)` con una clave que colisiona con el parámetro

**Área:** Backoffice / PHP (capa de vistas). **Severidad:** media. **Estado:** RESUELTO.

**Qué pasó.** La pantalla de Usuarios del backoffice renderizaba el **estado vacío**
("Sin usuarios") aunque la base tenía filas y el Model las devolvía bien. En la vista
saltaban `Warning: Undefined array key "items"` y `"pagina"`.

**Causa.** El renderizador de vistas era:
`protected function vista(string $vista, array $datos = []) { extract($datos, EXTR_SKIP); require ...; }`.
El Controller pasaba, entre otras, una clave `'datos'` (el resultado paginado del
Model). Al hacer `extract($datos, EXTR_SKIP)`, PHP intenta crear una variable local
`$datos` a partir de esa clave, pero `$datos` YA EXISTE (es el propio parámetro con
todo el bolsón). Con `EXTR_SKIP`, ante la colisión **no la sobreescribe**: la salta.
Resultado: dentro de la vista, `$datos` seguía siendo el arreglo completo
(`admin`, `activo`, `datos`, `conteos`, …), sin claves `items`/`pagina`.

**Solución.** Renombrar los parámetros del método a algo que no pueda colisionar con
ninguna clave de datos: `vista(string $__vista, array $__ctx = [])` y
`extract($__ctx, EXTR_SKIP)`. Así la clave `'datos'` crea correctamente su `$datos`
local en la vista. Verificado: la tabla renderiza los 4 usuarios reales.

**Regla derivada.** Al usar `extract()` sobre un array de contexto, los nombres de
los parámetros/variables locales del renderizador deben ir con un prefijo reservado
(`$__`) para no chocar con las claves del contexto.

---

### BUG-009 — El administrador conoce la contraseña del usuario que da de alta

**Área:** Backoffice / seguridad. **Severidad:** baja. **Estado:** ABIERTO (decisión
consciente por tiempo, Fase 4.1).

**Qué pasa.** En el alta manual de Usuarios (backoffice), el administrador **escribe**
la contraseña inicial del usuario que crea. Es decir, un tercero conoce la credencial
de una cuenta ajena. Vale para los cuatro roles.

**Por qué queda así por ahora.** Se decidió (Juan, Fase 4.1) dejarlo simple por tiempo:
el alta funciona y el usuario puede usar la clave que le pasan.

**Lo correcto (a implementar).** Que el sistema **genere una contraseña temporal**
(no elegida por el admin), se la entregue al usuario por un canal aparte, y **fuerce
el cambio en el primer ingreso** (un flag/estado de "debe cambiar contraseña"). Eso
saca al admin del conocimiento de la credencial definitiva. Requiere una columna o
tabla nueva para el flag → coordinar con el MER antes de tocar el esquema.

---

### BUG-010 — El alta de usuarios dejaba asignarle un camión a un Vecino

- **Fecha:** 2026-07-26
- **Área:** Backoffice / Pantalla 3 (alta multirol, Fase 4.1) — CSS + Model
- **Severidad:** alta (permitía cruzar datos entre subtipos desde la UI)
- **Estado:** RESUELTO
- **Detectado en:** prueba manual de Juan sobre el panel de alta.

**Síntoma.** Dos cosas en el mismo panel:

1. El bloque de texto del modal se veía **cortado**.
2. Al elegir rol **Vecino**, seguían apareciendo los campos de otros subtipos
   (Instalación, Camión, ¿Jefe?) y se les podía elegir valor. Un vecino con camión
   no tiene sentido de dominio.

**Causa.** Es **el mismo patrón que [BUG-004](#bug-004--las-cajas-aviso-con-hidden-se-mostraban-vacías)**,
ahora en el Backoffice: los tres bloques por subtipo salen del servidor con el
atributo `hidden`, pero `admin.css` declara

```css
.bo-campo { display: flex; flex-direction: column; gap: 5px; }
```

y una regla de autor le gana a `[hidden] { display: none }` de la hoja del
navegador. Resultado: los tres bloques se veían **siempre**, sin importar el rol
elegido, y el JS que togglea `el.hidden` no tenía efecto visible. Con tres bloques
de más el formulario crecía y por eso además se veía cortado.

**Impacto real (medido).** El Model ya hacía `switch ($rol)` para el `INSERT` de
subtipo, así que **un vecino nunca llegó a tener fila en `cuadrilla`**: la base no
quedó inconsistente. El daño era de interfaz y de confianza — el panel prometía una
asignación que no ocurría, sin avisar. Igual se endureció el servidor: ocultar un
`<select>` es UX, no autorización (mismo criterio que la matriz de la Fase 3.95).

**Solución aplicada.** Tres capas:

1. **CSS** (`backoffice/public/css/admin.css`) — la misma normalización que ya
   existe en el frontend:
   ```css
   [hidden] { display: none !important; }
   ```
   Más `.bo-alta__modal form > * { flex-shrink: 0; }`, para que en el flex column
   del modal los hijos no se compriman y el texto no se corte.
2. **Vista + JS** (`app/Views/pantallas/usuarios.php`) — los tres `<select>` de
   subtipo salen del servidor con `disabled`; el JS habilita solo el del rol
   elegido. Un control deshabilitado **no se manda** en el POST, así el campo
   ajeno no viaja ni por accidente.
3. **Model** (`app/Models/Usuario.php::crear`) — límite duro: cada campo de
   subtipo tiene un rol dueño (`id_instalacion`→operario, `id_camion`→cuadrilla,
   `jefe`→administrador). Si llega con valor y el rol no es el dueño, el alta se
   **rechaza con mensaje**, no se ignora en silencio. Además los IDs se leen solo
   para el rol dueño (`$idInst`/`$idCam` quedan en 0 para el resto).

**De paso.** Los mensajes de fallo salían en verde: el flash no distinguía tipo y
todas las vistas lo pintaban con `bo-aviso--ok`. Se movieron `flash()`/`tomarFlash()`
al `Controller` base con un `$tipo` (`'ok'|'error'`) y las 7 pantallas ahora usan
`bo-aviso--<tipo>`.

**Verificación.** Con sesión de admin jefe, POST directo a
`/backoffice/public/usuarios/crear` (sin pasar por la pantalla, igual que la matriz
de la Fase 3.95):

| # | Envío | Esperado | Resultado |
|---|-------|----------|-----------|
| 1 | `rol=vecino` + `id_camion=1` | rechazo | «Un vecino no puede recibir el campo "id_camion" (es de cuadrilla)» |
| 2 | `rol=vecino` + `jefe=si` | rechazo | «…el campo "jefe" (es de administrador)» |
| 3 | `rol=operario` + `id_camion=1` | rechazo | «…el campo "id_camion" (es de cuadrilla)» |
| 4 | `rol=cuadrilla` + `id_instalacion=1` | rechazo | «…el campo "id_instalacion" (es de operario)» |
| 5 | `rol=vecino` limpio | alta | creado |
| 6 | `rol=operario` + `id_instalacion=1` | alta | creado |
| 7 | `rol=cuadrilla` sin camión | alta | creado |
| 8 | `rol=cuadrilla` + camión ya asignado | rechazo | «Ese camión ya está asignado a otra cuadrilla» |
| 9 | `rol=administrador` + `jefe=si` | alta | creado |

Los rechazos se muestran en rojo (`bo-aviso--error`) y las altas en verde. Las filas
de prueba se borraron después (base de vuelta en 5 usuarios; `autorizacion` y
`gestiona` en 0).

---

### BUG-011 — El logo del sidebar perdió el ala izquierda (y las ruedas)

- **Fecha:** 2026-07-26
- **Área:** Backoffice / `app/Views/partials/sidebar.php` (marca)
- **Severidad:** baja (visual, pero es la marca del sistema)
- **Estado:** RESUELTO
- **Detectado en:** revisión visual de Juan sobre el backoffice.

**Síntoma.** El isotipo Cathartes del sidebar aparecía con **una sola ala**, la
derecha. Además le faltaban las cuatro circunferencias inferiores (las ruedas del
camión).

**Causa.** Al transcribir el SVG al partial del backoffice se copió el `<path>` del
ala **una sola vez**. En el original (`landing-page/index.html` y las pantallas de
`frontend-usuarios`) el ala aparece **dos veces**: la segunda lleva
`transform="translate(96,0) scale(-1,1)"`, que la espeja sobre el eje vertical del
`viewBox` de 96 y produce el ala izquierda. No es un path distinto, es el mismo
espejado — por eso al copiar «el path del ala» se perdió la mitad del dibujo sin
que salte a la vista en el código. En la misma transcripción se cayeron los cuatro
`<circle>` de las ruedas.

**Solución aplicada.** Se reemplazó el SVG del sidebar por el canónico de la landing
(ala + ala espejada + cuerpo + camión + 4 ruedas), con un comentario que explica de
dónde sale el ala izquierda para que no se vuelva a caer en la próxima copia.

**Regla derivada.** El SVG de la marca **no se transcribe a mano**: se copia
completo desde `landing-page/index.html`, que es la fuente única. Cualquier app
nueva (landing Axtiom, etc.) copia de ahí.

**Verificación.** `curl` de una pantalla del backoffice: el marcado servido trae 2
ocurrencias del path del ala (una con el `transform` de espejado) y las 4
circunferencias de las ruedas.

---

### BUG-012 — Un error de PDO imprimía el stack trace completo en el navegador

- **Fecha:** 2026-07-26
- **Área:** Backoffice / `public/index.php` (front controller). Aplica a las 5 pantallas con escritura.
- **Severidad:** alta (divulgación de información)
- **Estado:** RESUELTO
- **Detectado en:** verificación del alta de la Pantalla 7 (Rutas), probando un
  `nombre` con bytes que no eran UTF-8 válido.

**Síntoma.** El POST devolvió `500` con esto impreso en la página:

```
Fatal error: Uncaught PDOException: SQLSTATE[HY000]: General error: 1366
Incorrect string value: '\x97 Cord...' for column 'nombre' at row 1
in D:\cathartes\backoffice\app\Models\Ruta.php:151
```

**Causa.** El front controller no tenía manejador de excepciones y PHP quedaba con
`display_errors` activo. Cualquier `PDOException` no capturada llegaba entera al
navegador. `Usuario::crear` era el único write envuelto en `try/catch` (por la
transacción); `Instalacion`, `Ruta`, `Contenedor` y `Camion` no.

**El rechazo en sí era correcto:** la conexión declara `utf8mb4` y MySQL hizo bien
en rechazar bytes inválidos (error 1366). El bug no es que fallara, es **cómo
falló**: expuso la ruta absoluta del servidor, el archivo y la línea del Model, el
nombre de la columna y un fragmento del valor.

**Impacto.** Un atacante autenticado (o cualquiera que provoque un 500) obtenía el
layout del filesystem del servidor y pistas del esquema. Contradice el criterio de
ciberseguridad del proyecto, que en el resto del sistema no revela detalle
(la 403 del backoffice, por ejemplo, es deliberadamente genérica).

**Solución aplicada.** Manejador central en `public/index.php` — una sola red que
cubre las 5 pantallas y las que falten, en vez de `try/catch` repetido en cada
Model:

```php
$depurar = (bool) Config::get('app.debug', false);
ini_set('display_errors', $depurar ? '1' : '0');

set_exception_handler(function (Throwable $e) use ($depurar) {
    error_log('[backoffice] ' . get_class($e) . ': ' . $e->getMessage()
        . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    require BASE_PATH . '/app/Views/pantallas/500.php';
});
```

Más una vista `500.php` autónoma (no incluye el chrome: el sidebar toca la sesión y
en un 500 no se puede asumir estado sano). El detalle solo se imprime con
`APP_DEBUG=true`, variable nueva del `.env` documentada en el `.env.example` como
**siempre `false` en el servidor**.

**Verificación.** Mismo POST con bytes inválidos: responde `500` con la página
genérica («Algo falló del lado del servidor»); `grep` del HTML servido no encuentra
`Fatal error`, ni `Uncaught`, ni `D:\cathartes`. El detalle queda en el log de PHP.

**Pendiente relacionado.** Las APIs (`api-usuarios`, `api-gestion`,
`api-recoleccion`) hay que revisarlas por el mismo motivo: son JSON, así que les
corresponde un handler que devuelva `{"error":"..."}` genérico con 500, no una
vista HTML.

---

### BUG-013 — Un número demasiado grande desbordaba la columna y daba 500

- **Fecha:** 2026-07-26
- **Área:** Backoffice / validación de los Models (Recompensa, Camión, Instalación, Contenedor)
- **Severidad:** media (no corrompe datos, pero corta la operación con un 500)
- **Estado:** RESUELTO
- **Detectado en:** prueba manual de Juan, cargando un costo en puntos absurdamente
  grande en el alta de una recompensa.

**Síntoma.** Con un número muy grande en un campo numérico, el alta devolvía **500**
en vez de un mensaje de validación. (Gracias a [BUG-012](#bug-012--un-error-de-pdo-imprimía-el-stack-trace-completo-en-el-navegador)
el 500 ya salía sin stack trace, pero seguía siendo un 500.)

**Causa.** Las validaciones usaban `(int) $valor > 0` y confiaban en el cast de PHP.
En PHP de 64 bits `(int) '99999999999'` **no desborda**: da `99999999999`. O sea, el
número pasa la validación intacto y explota recién en MySQL, cuyo `INT` con signo
llega hasta **2.147.483.647** (error 1264 «Out of range value»). El límite real es el
de la **columna**, no el del tipo de PHP — y nadie lo estaba mirando.

El mismo agujero estaba en las coordenadas: `latitud`/`longitud` son
`DECIMAL(9,6)`, que no admite más de `999.999999`. Una latitud de `99999`
desbordaba igual, y de paso era geográficamente imposible (la latitud va de -90 a
90) sin que nada lo dijera.

**Impacto.** Cuatro pantallas: costo en puntos (Recompensas), capacidad (Camiones),
capacidad y ocupación (Instalaciones), coordenadas (Contenedores e Instalaciones).

**Solución aplicada.** Dos helpers en `app/Core/helpers.php`, usados por los cuatro
Models:

```php
const MYSQL_INT_MAX = 2147483647;
function entero_en_rango(mixed $v, int $min = 0, int $max = MYSQL_INT_MAX): bool
function coordenada_valida(mixed $v, float $limite): bool
```

`entero_en_rango()` exige que el valor sea entero (regex, no cast) y lo compara
contra el límite de la columna; descarta de entrada los que tienen más dígitos que
el `int` de PHP. `coordenada_valida()` acepta vacío (la columna es NULL-able) y si
viene exige rango geográfico real: ±90 la latitud, ±180 la longitud — más chico que
el tope de `DECIMAL(9,6)`, así que nunca desborda.

Los mensajes dicen el rango, en vez de un «valor inválido» a ciegas.

**Verificación.** Ocho casos por POST directo, todos con mensaje de validación (ni
un 500):

| Envío | Resultado |
|---|---|
| `valor=99999999999` | «entre 1 y 2.147.483.647» |
| `valor=2147483648` (tope +1) | rechazo |
| `valor=2147483647` (el tope justo) | **alta OK** |
| `valor=abc` | rechazo |
| Camión `capacidad=999999999999` | rechazo |
| Instalación `capacidad=99999999999` | rechazo |
| Instalación `latitud=99999` | «entre -90 y 90» |
| Contenedor `longitud=99999` | «entre -180 y 180» |

**Pendiente relacionado.** Las tres APIs validan sus entradas por separado: hay que
pasarles el mismo criterio (hoy `api-gestion` y `api-recoleccion` casi no tienen
escrituras, pero `api-usuarios` sí).

---

### BUG-014 — Cuadrilla y operario veían el header y el footer del vecino

- **Fecha:** 2026-07-27
- **Área:** Frontend de Usuarios / chrome compartido (`partials/layout.html`, `js/layout.js`, `js/guardia.js`)
- **Severidad:** alta (le ofrecía a cada rol links que no le corresponden)
- **Estado:** RESUELTO
- **Detectado en:** prueba manual de Juan, navegando como cuadrilla y como operario.

**Síntoma.** El nav mostraba **Reportar / Mis incidencias / Recompensas** a roles que no
son vecino. Los links funcionaban en el sentido de que la API respondía **403**
correctamente — el límite duro estaba bien — pero nunca deberían haber estado ahí. Lo
mismo en la columna «Sistema» del footer.

**Causa.** El chrome tenía **un solo** `<template id="tpl-header">`, escrito con el nav
del vecino, y las 8 pantallas lo clonaban tal cual. No había noción de rol en el
layout: `layout.js` rellenaba nombre, iniciales y saldo, y nada más.

Un agravante que hace que el bug pueda volver de forma **silenciosa**: el partial se
baja por `fetch('partials/layout.html')`, o sea que el navegador lo cachea. Con un
partial viejo en caché, un `layout.js` nuevo que busque bloques por rol no encuentra
ninguno y **no oculta nada** — el resultado es exactamente este síntoma, sin ningún
error en consola. Es el mismo patrón de caché rancia que ya nos había costado un rato
con el 2FA.

**Solución aplicada.** Tres cosas:

1. **Un `<template>` de header por rol**: `tpl-header-vecino`, `tpl-header-cuadrilla`,
   `tpl-header-operario`. Cada uno trae su nav completa y su propia pantalla de inicio
   en la marca. `layout.js` clona `tpl-header-<rol>` con el rol que viene de
   `GET /yo`. Para que tres headers no signifiquen triplicar marcado, las dos piezas
   idénticas salen a su propio template y se insertan en un slot: `tpl-logo` (el SVG,
   que ya se rompió una vez al transcribirlo a mano — ver [BUG-011](#bug-011--el-logo-del-sidebar-perdió-el-ala-izquierda-y-las-ruedas))
   y `tpl-avatar` (badge de puntos + menú).
2. **Footer**: sigue siendo uno solo (no se duplica), pero la columna «Sistema» tiene
   un bloque por rol marcado con `data-rol`, y se muestra el que corresponde.
   «Mi perfil» del menú de usuario también quedó acotado a vecino: `perfil.html` es
   una pantalla de vecino y a los otros roles les daba 403.
3. **Se eliminó el rol por defecto.** `guardia.js` tenía
   `var rolEsperado = (opts && opts.rol) || 'vecino'`. Ese `|| 'vecino'` es la raíz
   conceptual del bug: el sistema asumía vecino cuando nadie decía lo contrario. Ahora
   **cada pantalla declara su rol** (las 15) y si no lo declara no se adivina: se
   avisa por consola y no se aplica guardia de rol.

**Beneficio lateral del diseño nuevo.** Con tres templates separados, un partial rancio
en caché ya **no falla en silencio**: no existe `tpl-header-cuadrilla`, así que
`armarHeader` devuelve `null`, loguea `[layout] no hay header para el rol "cuadrilla"`
y la página se dibuja sin header. Obviamente roto en vez de sutilmente roto. Y a
propósito **no** cae de vuelta al header del vecino, que es justo lo que había que
erradicar.

**Verificación.** Dos partes.

*Composición del chrome* — se simuló la lógica de `layout.js` sobre el partial servido
(sin navegador) y se comprobó, para los tres roles, qué links termina viendo cada uno:

| Rol | Nav | Marca | Footer «Sistema» |
|---|---|---|---|
| Vecino | Inicio · Reportar · Mis incidencias · Mapa · Recompensas | `inicio.html` | las 4 del vecino |
| Cuadrilla | Inicio · Mis rutas · Intervenciones · Mapa | `cuadrilla.html` | las 4 de cuadrilla |
| Operario | Inicio · Mi instalación · Informes | `operario.html` | las 3 de operario |

Con la comprobación explícita de que **ningún link de un rol apunta a una pantalla de
otro**. «Mi perfil» aparece solo para vecino.

*Límite duro por subtipo* — los cuatro usuarios contra `GET /cuadrilla/ruta`, que exige
estar en la tabla `cuadrilla`:

| Sesión | Resultado |
|---|---|
| cuadrilla | **200** |
| operario | **403** |
| administrador | **403** |
| vecino | **403** |
| sin cookie | **401** |

`GET /yo` devuelve el rol correcto para los cuatro, que es de donde el frontend lo lee.

**Nota honesta sobre el diagnóstico.** No pude reproducir el síntoma en el navegador
(la extensión de Chrome no está conectada en este entorno), así que la caché rancia del
partial queda como **la causa más probable, no como causa confirmada**. Lo que sí está
verificado es que el código actual compone el chrome correcto para los tres roles y que
el 403 por subtipo funciona. La verificación visual con los cuatro logins queda
pendiente de hacerse a mano.

---

### BUG-015 — Un request colgado dejaba la pantalla en «Cargando…» para siempre

- **Fecha:** 2026-07-27
- **Área:** Frontend de Usuarios / cliente HTTP (`js/api.js`) y bootstrap (`js/guardia.js`, `js/layout.js`)
- **Severidad:** media (la pantalla se lee como rota)
- **Estado:** RESUELTO
- **Detectado en:** prueba manual de Juan sobre las pantallas nuevas de la Fase 3.

**Síntoma.** Cuando la API no respondía, los estados de carga quedaban colgados
indefinidamente. Una pantalla congelada en «Cargando…» se lee como rota; una que dice
«no se pudo cargar» se lee como lo que es.

**Causa.** Tres agujeros distintos, uno arriba del otro:

1. **`fetch` no tiene timeout.** El `request()` de `api.js` cubría el fallo de red con
   un `.catch` que devuelve `status: 0`, pero eso solo dispara si la conexión falla. Si
   el servidor **acepta la conexión y nunca contesta**, la promesa no se resuelve nunca,
   el `.then` de la pantalla no corre y el estado de carga se queda ahí. Ningún `.catch`
   ayuda con una promesa que no se resuelve.
2. **`Layout.montar` podía rechazar.** `cargarPartial().then(...)` no tenía `.catch`, y
   `guardia.js` hacía `montar(...).then(render)`. Si el partial no bajaba, o si
   `armarHeader` tiraba una excepción, la promesa se rechazaba y **`render` nunca
   corría**: ninguna región de la pantalla llegaba a pedir sus datos y todas quedaban en
   su estado inicial de carga.
3. **Una excepción a mitad de `render`** dejaba en carga todas las regiones que
   faltaban pintar.

**Solución aplicada.**

1. **Timeout por request** (`api.js`), con `AbortController`:
   ```js
   var TIMEOUT_MS = 12000;
   var ctrl = new AbortController();
   opts.signal = ctrl.signal;
   reloj = setTimeout(function () { ctrl.abort(); }, TIMEOUT_MS);
   ```
   Al abortar, `fetch` rechaza y cae al `catch` que ya existía: termina como
   `status: 0`, el **mismo camino que un fallo de red**. Las pantallas no necesitaron un
   caso aparte para «colgado». Es el arreglo de raíz: vale para las tres APIs y para
   todas las pantallas, presentes y futuras.
2. **`Layout.montar` nunca rechaza**: se le agregó `.catch` y devuelve resuelto igual.
   Una pantalla sin header es fea; una pantalla congelada parece rota.
3. **`render` va envuelto en `try/catch`** en `guardia.js`, y si tira, `caerAError()`
   pasa a `error` toda región `[data-region]` que haya quedado en `cargando`.

**Verificación.** Cada región con estado de carga en las 7 pantallas nuevas tiene
transición a `error`, a `vacio` y a `ok` en su controlador (chequeado archivo por
archivo). Los 7 endpoints del contrato responden **404** hoy, así que el camino de
error es el que se ejercita de verdad al abrir las pantallas.

---

### BUG-016 — Con el partial en caché no se dibujaba ningún header y no se podía cerrar sesión

- **Fecha:** 2026-07-27
- **Área:** Frontend de Usuarios / chrome (`js/layout.js`, `partials/layout.html`)
- **Severidad:** alta (deja al usuario encerrado en la sesión)
- **Estado:** RESUELTO
- **Detectado en:** prueba de Juan inmediatamente después del arreglo de [BUG-014](#bug-014--cuadrilla-y-operario-veían-el-header-y-el-footer-del-vecino).

**Síntoma.** No aparecía **ningún** header, y sin header no hay menú de usuario: no se
podía cerrar sesión de la forma normal.

**Causa — y es una causa que me pertenece.** Al arreglar BUG-014 decidí que, si no
existía el header del rol, `armarHeader` devolviera `null` en vez de caer al header del
vecino. La intención era buena (que el fallo fuera **ruidoso** en vez de silencioso),
pero el efecto fue peor que el bug original: antes el usuario veía una nav equivocada
pero **funcional**; después no veía nada y quedaba sin salida.

Y el disparador fue justamente el escenario que yo mismo había anotado como probable en
BUG-014: **`partials/layout.html` viejo en la caché del navegador**. El partial se pide
por `fetch`, así que se cachea como cualquier recurso. Quedó `layout.js` nuevo (que
busca `tpl-header-<rol>`) contra un partial viejo (que solo tenía `tpl-header`), y el
resultado fue exactamente «ningún header». Eso además **confirma** la hipótesis de
BUG-014, que allá había quedado como probable y no como comprobada: el modo de falla
combinado JS-nuevo/partial-viejo es real y se reprodujo solo.

**Solución aplicada.** Dos puntas, una de raíz y una de contención:

1. **Que el partial no pueda venir rancio** (`js/layout.js`):
   ```js
   fetch('partials/layout.html', { cache: 'no-cache' })
   ```
   `no-cache` obliga al navegador a revalidar contra el servidor — un `304` barato
   cuando no cambió, el archivo fresco cuando sí. Es el arreglo de fondo: elimina la
   clase entera de problemas «JS nuevo + partial viejo». (Ojo, la misma trampa aplica a
   cualquier recurso que se baje por `fetch` en runtime, no solo a este.)

2. **Que nunca se pueda quedar sin poder salir**: se agregó `tpl-header-minimo` al
   partial — marca y avatar (o sea, «Cerrar sesión»), **sin nav**. Si falta el header
   del rol de la sesión, se usa ese. Resuelve la tensión entre los dos bugs sin
   traicionar a ninguno: la **nav** no cae nunca a la de otro rol (BUG-014), y el
   **logout** existe siempre (BUG-016). Una nav vacía es honesta; una nav ajena, no.

El respaldo se escribió como `<template>` en el partial y no con `createElement` en el
JS, para no romper dos reglas del proyecto de una: nada de construir estructura en JS, y
ningún texto fijo visible que exista solo en un `.js`.

**Verificación.** El partial servido trae los 7 templates (`tpl-logo`, `tpl-avatar`,
`tpl-header-minimo`, los tres por rol y `tpl-footer`); el mínimo trae sus slots de logo
y avatar; «Cerrar sesión» sigue viviendo en el HTML y no en `layout.js` (0 ocurrencias
allí); `createElement` no se usa en `layout.js` (la única mención es un comentario). Las
15 pantallas responden 200 y la composición del chrome por rol sigue dando correcto para
los tres roles.

**Lección para la próxima.** «Fallar ruidosamente» no puede significar «dejar la app
inusable». Cuando un componente compartido no se puede montar, el degradado tiene que
conservar las acciones de escape — salir de la sesión, volver al inicio — y perder solo
lo que no se puede resolver con seguridad.

---

### BUG-017 — Los `.env.example` traían una contraseña de base real
- **Síntoma:** los cuatro `.env.example` (versionados) tenían `DB_USER=root` y una `DB_PASS` con una contraseña que parecía real.
- **Causa:** se copió el `.env` de una máquina de desarrollo como plantilla.
- **Arreglo:** plantilla con usuario propio (`cathartes_app`) y valor de ejemplo. **Pendiente fuera del código:** si esa contraseña se usó en algún servidor, cambiarla, porque sigue en el historial de Git.

### BUG-018 — Los 500 devolvían el mensaje de la excepción
- **Síntoma:** ante un error interno, 11 controladores respondían `{"error": "<mensaje de PDO>"}`, con nombres de tablas, columnas y consultas.
- **Causa:** `catch (Throwable $e)` que pasaba `$e->getMessage()` al JSON.
- **Arreglo:** el detalle va a `error_log()` y el cliente recibe «Error interno del servidor». Los endpoints nuevos ni siquiera capturan: los cubre el manejador global.

### BUG-019 — `/health` público mostraba el error de MySQL
- **Síntoma:** con la base caída, `GET /health` (sin sesión) devolvía el texto del error, con el usuario y el host de la conexión.
- **Arreglo:** el detalle se loguea; la respuesta es `{"ok": false, "db": false}` con HTTP 503, que además permite que un healthcheck de Docker lo detecte (antes devolvía 200 aunque la base estuviera caída).

### BUG-020 — Choque de clave primaria en la auditoría
- **Síntoma:** crear y dar de baja a un administrador en el mismo segundo producía un error 500 (`Duplicate entry ... for key 'PRIMARY'`).
- **Causa:** `gestiona` y `autorizacion` tienen PK `(admin, cuenta, fecha)` con `fecha` al segundo; dos acciones iguales sobre la misma cuenta chocan.
- **Arreglo:** el alta del registro de auditoría reintenta corriendo la fecha un segundo. No se cambió el esquema. Cubierto por un test.

### BUG-021 — Acentos rotos y datos de prueba sin sentido
- **Síntoma:** al importar `seed.sql` con un cliente en latin1, `Pérez` quedaba como `PÃ©rez`. Además había un vertedero llamado «don ramon / me lo acaco de inventar», una ruta «helo» y un camión de 100 kg.
- **Arreglo:** `SET NAMES utf8mb4` al inicio de `BD.sql` y `seed.sql`; datos de prueba reemplazados por otros coherentes y ampliados.
