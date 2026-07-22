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
