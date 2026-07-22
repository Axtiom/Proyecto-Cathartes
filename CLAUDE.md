# CLAUDE.md — Constitución del monorepo SiGeRU / Cathartes

> Este archivo vive en la raíz del monorepo (`D:\sigeru\CLAUDE.md`).
> Claude Code lo lee automáticamente al iniciar cada sesión. Funciona como
> **contexto** (qué es el proyecto y dónde estamos parados) y como
> **instrucciones** (cómo se trabaja acá). Si algo choca con lo que traés de
> base, manda esto.

---

## 1. Qué es el proyecto

**SiGeRU** (Sistema de Gestión de Residuos Urbanos) es el proyecto; **Cathartes**
es el sistema/marca. Es una plataforma municipal de gestión de residuos urbanos
para la Intendencia de Montevideo. Proyecto de pasaje de grado 2026 del equipo
**Axtiom** (ESI Buceo, UTU/DGETP, 3°ME).

- **Equipo:** Juan Novoa ("J", PM, implementa la mayoría), Nahuel Badell
  (backend), Aarón Baez (frontend), Bruno Boggia (testing/QA).
- **Deadline:** 27 de julio de 2026 — un único PDF integrador.

---

## 2. Arquitectura del monorepo

Monorepo en `D:\sigeru` (host Windows). Cada aplicación es un MVC en PHP
independiente. La **Landing de SiGeRU (Cathartes)** ya está hecha y es la
**plantilla MVC de referencia** de todo el monorepo: cualquier app nueva se
estructura igual que ella.

Son **6 apps MVC** + **1 landing estática** (Axtiom). El número "7" que a veces
aparece sale de contar la landing de la empresa como propiedad a deployar: es
HTML plano (no MVC), pero igual necesita su dominio y hosting.

**6 apps MVC:**

- Landing pública SiGeRU / Cathartes — **hecha** · plantilla MVC del monorepo
- **Frontend de Usuarios** (Vecino · Cuadrilla · Operario)
- API de Usuarios
- API de Gestión
- API de Recolección
- Backoffice de Administración

**Landing estática (aparte):**

- Landing pública Axtiom (empresa) — HTML estático · necesita dominio + hosting

**Stack:** PHP 8, MySQL 8, XAMPP en `/opt/lampp/`, Docker (objetivo, hoy
bloqueado por conflicto VirtualBox / `hypervisorlaunchtype off`). Servidor de
destino: Rocky Linux 10.2 (`server1`, usuario `jn7_admin`, SSH en puertos 22 y
22445).

### Regla de acceso a datos (crítica)

- Las **apps públicas** (Frontend de Usuarios, landings) **van SIEMPRE por API**.
  Nunca tocan la base directo.
- El **Backoffice de Administración** sí puede tocar la DB directo (permitido por
  la consigna oficial para tareas masivas de admin).

---

## 3. Base de datos

Base `cathartes`, 22 tablas, `utf8mb4` / `utf8mb4_unicode_ci`, InnoDB. El DDL
(`db/BD.sql`) es la fuente de verdad y ya está commiteado. El **MER está
congelado**: no se inventan tablas ni columnas. Si un documento o código de un
compañero introduce tablas/columnas que no están en el DDL, es un error —
rechazar.

### Gotchas del DDL (no negociables)

- La columna de contraseña se llama **`contrasena`** (SIN ñ). En **toda** query
  PDO va con backticks: `` `contrasena` ``. La ñ se eliminó a propósito tras
  [BUG-003](BUGS.md): al importar el dump, la ñ (`C3 B1`) se corrompía a `├▒` y
  la columna quedaba inaccesible. Con un identificador ASCII eso no puede volver
  a pasar. **Regla derivada:** ningún identificador (tabla/columna) lleva ñ ni
  acentos; los dumps se importan/exportan siempre en UTF-8.
- `usuario.estado` es `ENUM('activo','no activo')`, `NOT NULL DEFAULT 'no activo'`.
  **No existe estado "pendiente".** El `'no activo'` ES el pendiente de aprobación.
- `usuario.email` es `UNIQUE`.
- `vecino.saldo INT DEFAULT 0` (puntos de gamificación).

### Política de borrado

- **CASCADE** sólo en subtipos de usuario (`vecino`, `cuadrilla`, `operario`,
  `administrador`) y en `usuario_telefono`.
- **RESTRICT** en todo el resto. El sistema **no borra**: marca de baja vía
  campos `estado`.

---

## 4. Reglas de dominio y convenciones

- **Roles (exactamente 4):** Administrador, Cuadrilla, Operario, Vecino.
  "Operario de Cuadrilla" y "Chofer" son incorrectos — rechazar si aparecen.
- **El rol NO se guarda como atributo ni se elige en el registro.** Se deduce de
  en qué tabla de subtipo está el ID (`vecino` / `cuadrilla` / `operario` /
  `administrador`). El registro público siempre crea **Vecino**. Operario y
  Cuadrilla los crea el Administrador (por SQL o Backoffice).
- **Lenguaje:** sustantivos de dominio en español (`Vecino`, `Cuadrilla`,
  `Residuo`, `contraseña`); nombres técnicos de carpetas/identificadores en
  inglés.
- **Datos reales, nada de demo.** No se usan Models de datos estáticos ni
  `EstadisticasDemo`. Todo sale de queries reales a `cathartes`. Para probar se
  siembran filas realistas con `INSERT` (contenido ficticio, filas reales).
- **Gamificación:** 150 puntos fijos por incidencia validada (no al resolver).
  Los puntos no vencen; el costo de recompensas es configurable por el admin.
- **Foto obligatoria** al reportar incidencia (RF-18).

---

## 5. Trabajo actual — "Funcionamiento" (autenticación)

Estamos construyendo la **parte de funcionamiento** punta a punta:

- **Frontend de Usuarios:** páginas de login, registro, recuperar contraseña y
  verificación en dos pasos (según la maqueta Cathartes).
- **API de Usuarios (slice de auth):** endpoints de registro, login, logout, 2FA
  y recuperación, contra la tabla `usuario` con PDO.
- **DB:** conexión real + `INSERT`s de arranque para probar.

Después, si dan los tiempos: Vecino → Cuadrilla → Operario.

### Decisiones de arquitectura de auth (fijas)

- **Token firmado (HMAC), sin tabla de tokens.** La API emite un token que
  contiene `{id, rol, exp}` firmado con un secreto (`APP_TOKEN_SECRET` del
  `.env`). El Frontend lo guarda en su `$_SESSION` y lo manda en cada request
  como header `Authorization: Bearer <token>`. La API valida la firma y saca
  `id` y `rol`. **Cero cambios al esquema** (el MER está congelado).
- **Revocación por `estado` (kill-switch a nivel cuenta).** En cada request
  autenticado, además de validar la firma, la API **re-chequea
  `usuario.estado='activo'` contra la base**. Si el admin desactiva la cuenta
  (`UPDATE usuario SET estado='no activo'`), el próximo request de ese usuario se
  rechaza (401) y la sesión abierta se cae sola. Da un cierre forzado a nivel
  cuenta **sin agregar tabla ni columna** de tokens.
- **Duración según "Recordarme"** (checkbox de la maqueta):
  - **Tildado** → token largo, `APP_TOKEN_TTL = 90 días` (`7776000`), cookie
    persistente. La sesión dura hasta que el usuario cierra sesión (o hasta que
    el admin desactiva la cuenta).
  - **Destildado** → cookie de sesión pura: muere al cerrar el navegador. **Sin
    timeout por inactividad** — nunca se expulsa a un usuario que está en uso
    activo.
- **2FA efímero, sin columna en DB.** Código de 6 dígitos que vive en sesión
  server-side de la API (~5 min y se descarta). No hay columna para guardarlo y
  no se agrega.
- **Contraseñas:** `password_hash` / `password_verify` (bcrypt).
- **Registro:** siempre Vecino. `INSERT` en `usuario` (queda `estado='no activo'`
  por default) + `INSERT` en `vecino` (`saldo=0`). El rol no se elige.
- **Login:** sólo entra si `estado='activo'`. Un recién registrado queda
  `no activo` hasta que un Administrador lo aprueba con
  `UPDATE usuario SET estado='activo' WHERE ID=...`.
- **Mailer (2FA / recuperación):** PHPMailer, **config-driven** vía `.env`. Si no
  hay credencial cargada, cae a un **stub** que loguea el código en pantalla. El
  día que se pega la credencial en el `.env`, el envío real sale sin tocar código.
  El app password / cuenta SMTP lo genera Juan (Claude no crea cuentas ni carga
  credenciales).

---

## 6. Portabilidad / hosting

Todo tiene que poder hostearse en el servidor. Por eso:

- **Nada hardcodeado.** Ni paths, ni credenciales, ni hosts. Todo por `.env`.
- El salto local → producción debe ser sólo cambiar el `.env`.

Variables esperadas (base):

```
DB_HOST=  DB_NAME=cathartes  DB_USER=  DB_PASS=  DB_CHARSET=utf8mb4
APP_TOKEN_SECRET=  APP_TOKEN_TTL=7776000   # 90 días (Recordarme); sin Recordarme = sesión de navegador
MAIL_DRIVER=stub          # stub | smtp
SMTP_HOST=  SMTP_PORT=  SMTP_USER=  SMTP_PASS=  SMTP_FROM=  SMTP_SECURE=
```

El `.env` **nunca** se commitea; sí se commitea un `.env.example`.

> **Gotcha del `.gitignore` (ver [BUG-001](BUGS.md)):** el patrón `.env.*`
> también matchea `.env.example`. Por eso el `.gitignore` de la raíz lleva las
> excepciones `!.env.example` y `!**/.env.example` — no las borres o los
> `.env.example` de las apps dejan de commitearse.

---

## 7. Convenciones de entorno y comandos

- Server PHP de dev: `php -S 127.0.0.1:PUERTO -t public` — el **`-t public` es
  obligatorio**.
- El shell de las tools de Code es `sh` (no `bash`): la brace expansion en
  `mkdir` falla silenciosa. Usar paths explícitos o `bash -c '...'`.
- Máquina AMD: `bcdedit /set hypervisorlaunchtype off` debe seguir activo
  (Hyper-V/WSL2 rompe VirtualBox). Code corre como binario nativo de Windows
  (`C:\Users\juan1\.local\bin`), no WSL2.
- XAMPP no está bajo systemd: estado con `sudo /opt/lampp/lampp status`.
- Git / GitHub (cuenta de organización), monorepo en `D:\sigeru`.

---

## 8. Documentación

Todos los entregables siguen el **formato ESI anexo** (no el genérico), con la
**guía estructural de la plantilla UTULab**, y respetan como base los
**requisitos del documento de Ingeniería**. Arial, interlineado 1.15,
justificado, A4, márgenes 3/3/3/2.5 cm, H1 14pt negrita con borde, H2 12pt
negrita-cursiva, cuerpo 12pt, registro impersonal. Carátula = página 1.
Introducción única antes del contenido numerado (no Abstract/Objetivo/Alcance
por separado).

---

## 9. Cómo trabajar con Juan

- **Estilo colaborativo e iterativo.** Alinear antes de entregar. Confirmar cada
  decisión grande antes de pasar a la siguiente. No producir entregables
  terminados de forma unilateral sin alineación previa.
- **Evaluaciones honestas, sin endulzar** — incluido el trabajo de los
  compañeros.
- **Registro:** rioplatense casual, mezcla español/inglés. Juan dicta por
  voz-a-texto: hay errores de transcripción (Java→JavaScript, "Boggia"→"Bojia",
  términos en inglés fonéticos). Interpretar por contexto sin marcar cada error.
- **Secuenciación paso a paso**, con avisos explícitos antes de operaciones
  riesgosas (servidor / VM).
- **Imágenes:** cuando Juan sube capturas, están en `/mnt/user-data/uploads/` y
  **sí** se pueden incrustar en `.docx`. Verificar siempre con:
  `find /mnt/user-data/uploads/ -type f \( -iname "*.png" -o -iname "*.jpg" -o -iname "*.jpeg" \)`
  antes de afirmar nada sobre disponibilidad de imágenes.

---

## 10. Registro de bugs

Todo bug que se encuentra —de código propio, del equipo, de configuración o de
entorno— se anota en **[`BUGS.md`](BUGS.md)** (raíz del monorepo), al momento de
detectarlo o arreglarlo. No se borran: los resueltos quedan con estado
`RESUELTO` para dejar trazabilidad (qué pasó, causa, solución, verificación).
Sirve además de insumo para el PDF integrador.

- Formato: tabla de resumen + sección de detalle por `BUG-00N` (ver el propio
  archivo, que arranca con la convención escrita).
- Al arreglar un bug, se actualiza su fila y su detalle; no se abre uno nuevo
  para el mismo problema.
