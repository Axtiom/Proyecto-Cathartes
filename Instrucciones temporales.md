# Prompt: reestructuración total del monorepo SiGeRU

> Pegar en Claude Code, parado en `D:\sigeru`.

---

## Contexto

Monorepo `D:\sigeru`. Proyecto **SiGeRU** (marca de producto: **Cathartes**), sistema de gestión de residuos urbanos para Montevideo. Empresa: **Axtiom**. Base de datos MySQL 8 llamada `cathartes`.

Las aplicaciones de frontend replican un patrón MVC de PHP que la letra del proyecto **no pide para los frontends**. El resultado son directorios anidados, múltiples puntos de entrada y rutas de assets rotas.

Tu tarea es hacer una **reestructuración total** para que el monorepo cumpla la letra.

---

## Permisos y límites

**Tenés permiso total para borrar, mover, renombrar y crear archivos.** Todo el estado actual está respaldado en GitHub, así que no necesitás construir la estructura nueva al lado de la vieja: reestructurá en el lugar.

**Prohibido absolutamente: `git push`.** Commiteá localmente todo lo que quieras, pero no subas nada hasta que yo lo confirme explícitamente. Si creés que llegó el momento de pushear, pedímelo y esperá respuesta.

---

## Principio rector: reciclar, no reinventar

Ya hay trabajo hecho que sirve. **No escribas desde cero nada que ya exista.**

- El CSS existente se conserva. No lo reescribas ni lo "mejores".
- Las imágenes y los archivos de licencia se conservan.
- El HTML que hoy vive dentro de los `.php` de vistas y partials se recicla: le sacás las etiquetas PHP y queda HTML.
- Los queries PDO no se tiran: se mudan a la API que corresponda.

Creá archivos nuevos **solo** cuando no exista un equivalente que reciclar. El caso claro es `js/api.js`, que hoy no existe.

---

## Reglas que vienen de la letra del proyecto (NO negociables)

Documento oficial `Proyecto de Pasaje de Grado 2026`, sección 6 "Detalle de Implementación" y "Arquitectura del Sistema":

1. **Seis aplicaciones, seis bases de código independientes.** No fusionar ninguna:
   Landing Page · Frontend de Usuarios · API de Usuarios · API de Gestión · API de Recolección · Backoffice de Administración
2. **Los frontends usan SOLO HTML5, CSS y JavaScript.** Sin PHP, sin PDO, sin conexión a base de datos.
3. **La Landing Page no accede a ningún dato directamente.** Todo lo obtiene consumiendo su API.
4. **El Frontend de Usuarios no almacena datos ni accede a la base de datos.**
5. **Las APIs se desarrollan en PHP 8, estándar REST**, e implementan MVC.
6. **El Backoffice** sí puede tocar la base de datos directo para administración masiva.
7. Todo versionado con Git en GitHub.

**El patrón MVC de los frontends vive en JavaScript, no en directorios:** el HTML es la vista, los módulos JS son los controladores, y `api.js` (cliente `fetch`) cumple el rol de modelo. Así se documenta y así se defiende.

---

## Mapa de migración de `landing-page`

Este es el estado actual y qué hacer con cada cosa. Verificá el listado antes de aplicarlo, por si cambió.

### CONSERVAR — copiar tal cual, sin tocar el contenido

| Origen | Destino |
|---|---|
| `public/assets/css/estilos.css` | `css/estilos.css` |
| `public/assets/img/hero-camion.avif` | `assets/img/hero-camion.avif` |
| `public/assets/img/CREDITS.md` | `assets/img/CREDITS.md` |

`CREDITS.md` es la atribución de licencia de la foto de Unsplash. **Perderlo es un problema legal, no un detalle.**

### RECICLAR — convertir a HTML plano

| Origen | Destino |
|---|---|
| `app/Views/layouts/main.php` | esqueleto de `index.html` |
| `app/Views/landing/index.php` | cuerpo de `index.html` |
| `app/Views/partials/header.php` | `<header>` dentro de `index.html` |
| `app/Views/partials/footer.php` | `<footer>` dentro de `index.html` |
| `app/Views/partials/logo.php` | inline |
| `app/Views/partials/marca.php` | inline |
| `app/Views/partials/section-header.php` | inline |
| `app/Views/partials/fab.php` | inline |

Los seis partials se vuelven secciones del mismo archivo. Donde había `<?php echo $variable ?>`, dejá un contenedor con `id` o `data-*` que `js/landing.js` va a poblar con datos de la API.

### MIGRAR — mudar a las APIs

| Origen | Destino |
|---|---|
| `app/Models/Estadisticas.php` | `api-gestion`, como endpoint REST de estadísticas públicas |
| `app/Controllers/LandingController.php` | la lógica que tenga, al controlador correspondiente en la API |

### BORRAR — andamiaje que ya no aplica

```
app/Core/Config.php
app/Core/Controller.php
app/Core/Env.php
app/Core/Router.php
app/Core/View.php
app/Services/          (solo tiene un README vacío)
config/config.php
routes/web.php
public/index.php
public/.htaccess
.env                   (el frontend ya no se conecta a la base)
.env.example
```

### Para `frontend-usuarios`

No tengo su listado. **Inventarialo vos primero** y aplicá exactamente el mismo criterio: conservar CSS/imágenes, reciclar vistas a HTML, migrar PDO a las APIs, borrar el andamiaje.

---

## Estructura destino

```
D:\sigeru\
├── landing-page\
│   ├── index.html              ← único punto de entrada, en la raíz
│   ├── css\estilos.css
│   ├── js\
│   │   ├── api.js              ← NUEVO: cliente fetch
│   │   └── landing.js          ← NUEVO: poblado del DOM
│   └── assets\img\
│
├── frontend-usuarios\
│   ├── index.html
│   ├── login.html
│   ├── registro.html
│   ├── (una página por pantalla de la maqueta)
│   └── css\  js\  assets\
│
├── api-usuarios\
│   ├── public\index.php  public\.htaccess
│   ├── app\Controllers\  app\Models\  app\Core\
│   ├── config\
│   └── .env                    ← acá SÍ va, nunca se commitea
│
├── api-gestion\
├── api-recoleccion\
└── backoffice\
```

Las rutas de assets van siempre relativas a la raíz de la app: `css/estilos.css`, `assets/img/hero-camion.avif`. Nunca `../../`.

---

## Procedimiento

### Paso 0 — Checkpoint local

```
git add -A
git commit -m "checkpoint: estado previo a la reestructuración"
```

Sin push. Es solo para poder volver atrás localmente.

### Paso 1 — Inventario y plan

Recorré `landing-page\` y `frontend-usuarios\`. Devolveme una tabla:

| Archivo | Categoría | Destino |
|---|---|---|

Categorías: CONSERVAR · RECICLAR · MIGRAR · BORRAR

**Pará acá y mostrame la tabla. No ejecutes nada hasta que yo la apruebe.**

### Paso 2 — Ejecutar la reestructuración

Con la tabla aprobada, aplicala. Borrado incluido.

### Paso 3 — Mover la lógica de datos a las APIs

Todo query PDO que hoy viva en un frontend pasa al controlador de la API correspondiente, expuesto como endpoint REST.

**Alerta de bug conocido:** la columna de contraseña en la tabla `usuario` se llama literalmente `` `contraseña` ``, con eñe. **Toda** consulta PDO que la toque necesita backticks. Ya causó bugs antes. Revisá cada query.

### Paso 4 — Verificación

Levantá cada app y comprobá:

- [ ] `localhost/sigeru/landing-page/` abre el landing sin ninguna ruta adicional
- [ ] El CSS carga (la página se ve estilada, no texto pelado)
- [ ] Cargan Barlow Condensed, DM Sans y JetBrains Mono
- [ ] Se muestra `hero-camion.avif` con su overlay oscuro
- [ ] La consola del navegador no tiene ni un 404
- [ ] Los datos mostrados vienen de un `fetch` a la API, nunca hardcodeados
- [ ] No queda ni un `.php` dentro de `landing-page\` ni de `frontend-usuarios\`
- [ ] `CREDITS.md` sigue presente

Reportame cada punto.

### Paso 5 — Higiene del repositorio

- `.gitignore` debe incluir: `.env`, `**/.env`, `*.log`, `vendor/`
- Verificá con `git log --all -- "**/.env"` si alguna vez se commiteó un `.env` con credenciales reales. **Si aparece algo, avisame y no hagas nada más al respecto.**
- Un commit descriptivo por aplicación, no uno solo gigante
- **Sin push.**

---

## Restricciones de estilo

- Fuentes: **Barlow Condensed**, **DM Sans**, **JetBrains Mono** (Google Fonts)
- Los tokens CSS mantienen sus nombres **en español**
- El hero conserva el desenfoque ya aplicado en la imagen y el oscurecimiento por CSS
- Los cuatro roles del sistema son **Administrador, Cuadrilla, Operario y Vecino**. "Chofer" no existe, nunca uses ese término.

---

## Entorno

- El shell es `sh`, no `bash`: la expansión de llaves en `mkdir` falla en silencio. Usá rutas explícitas separadas o `bash -c '...'`.
- El servidor embebido de PHP requiere el flag `-t public`.

---

## Qué NO hacer

- No `git push` bajo ninguna circunstancia sin confirmación
- No fusionar aplicaciones
- No dejar PHP en los frontends
- No reescribir el CSS existente
- No usar datos estáticos ni de demostración
- No hacer un único commit con todo
