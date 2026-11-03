# API — endpoints de usuarios y gestión

Todas las respuestas son JSON con la forma `{"ok": true|false, ...}`.
La sesión viaja en una cookie `HttpOnly` que emite el login: en `curl` hay que guardarla
(`-c`) y reenviarla (`-b`). **El rol se valida en el backend**: ocultar un botón en el
frontend no es un control de seguridad.

**URLs.** Hay tres APIs: `api-usuarios`, `api-gestion` y `api-recoleccion` (esta última, en su sección al final).
En XAMPP, con el proyecto en `htdocs/cathartes`, cada API cuelga de su carpeta `public/`:
`http://localhost/cathartes/api-usuarios/public/...`, `.../api-gestion/public/...` y `.../api-recoleccion/public/...`.
Si cada app tiene su propio document root, la base es `/api-usuarios/...`, `/api-gestion/...` y `/api-recoleccion/...`.

**Códigos:** `200` ok · `201` creado · `400` datos inválidos · `401` sin sesión ·
`403` rol sin permiso · `404` inexistente · `409` conflicto con el estado actual
(ya está dado de baja, matrícula repetida, tiene dependencias…) · `419` token CSRF
inválido (solo pantallas del backoffice) · `500` error interno (sin detalle: va al log).

**Convenciones.** El id va en la query (`?id=N`). `PUT` es parcial: lo que no se envía se
conserva. Las bajas son **lógicas** (la fila queda, con fecha y motivo); no se usa `DELETE`
para borrar físicamente salvo donde se aclara. El cuerpo es JSON (`Content-Type: application/json`).

## api-usuarios

| Método | Ruta | Acceso | Descripción |
|---|---|---|---|
| POST | `/registro` | público | Registra un vecino. Queda **pendiente de aprobación** |
| POST | `/login` | público | Inicio de sesión (correo + contraseña) |
| POST | `/logout` | sesión | Cierra la sesión |
| POST | `/recuperar`, `/restablecer` | público | Recuperación de contraseña por token |
| GET | `/yo` | sesión | Datos de la sesión actual |
| GET / POST | `/perfil` | sesión | Consultar / actualizar el propio perfil |
| GET | `/usuarios` | administrador | Lista. Filtros: `?rol=` `?estado=` `?q=` |
| GET | `/usuarios/detalle?id=N` | administrador | Una cuenta con sus teléfonos |
| POST | `/usuarios` | administrador | **Alta** con rol |
| PUT | `/usuarios?id=N` | administrador | **Edición** parcial (el rol no cambia) |
| DELETE | `/usuarios?id=N` | administrador | **Baja** lógica (queda `no activo` y auditada) |
| POST | `/usuarios/aprobar?id=N` | administrador | Aprueba una solicitud pendiente |
| POST | `/usuarios/rechazar?id=N` | administrador | Rechaza una solicitud pendiente |
| GET | `/notificaciones` | sesión (cualquier rol) | Las notificaciones propias, las más nuevas primero. `?no_leidas=1` `?limite=N` (máx. 200) |
| GET | `/notificaciones/contador` | sesión | `{no_leidas: N}` para el globito de la campana |
| POST | `/notificaciones/leer?id=N` | sesión | Marca una como leída (`404` si no es tuya) |
| POST | `/notificaciones/leer-todas` | sesión | Marca todas las propias |

Nunca se devuelve la contraseña ni su hash.

**Alta (`POST /usuarios`)** — campos: `rol` (`administrador`, `operario`, `cuadrilla`, `vecino`),
`nombre`, `apellido` (opcional), `email`, `password` (mín. 8), `telefono` o `telefonos` (lista),
y según el rol: `id_instalacion` (operario, obligatorio), `id_camion` y `estado_cuadrilla`
(cuadrilla), `jefe` (`si`/`no`, administrador). Un campo de otro rol se **rechaza**, no se ignora.

**Reglas de negocio:**
- **RN3:** solo un administrador *jefe* crea, da de baja o cambia la jefatura de administradores.
  Un administrador común solo puede editar su propia cuenta.
- **RN4:** nunca se deja al sistema sin administrador jefe activo. Nadie se da de baja a sí mismo.
- Un camión no puede estar asignado a dos cuadrillas ni estar dado de baja.
- Una cuenta dada de baja pierde el acceso **de inmediato**, aunque su token siga vigente.

## api-gestion

| Método | Ruta | Acceso | Descripción |
|---|---|---|---|
| GET | `/health` | público | Estado del servicio (`503` si la base no responde) |
| GET | `/estadisticas/publicas` | público | Estadísticas de incidencias |
| GET | `/contenedores` | sesión | Contenedores activos |
| GET | `/contenedores/todos` | sesión | Todos, incluso dados de baja |
| GET | `/contenedores/publicos` | público | Activos, para el mapa de la landing |
| GET | `/contenedores/detalle?id=N` | administrador | Un contenedor completo |
| POST | `/contenedores` | administrador | **Alta** |
| PUT | `/contenedores?id=N` | administrador | **Edición** (la ruta no se cambia desde acá) |
| DELETE | `/contenedores?id=N` | administrador | **Baja** lógica; body `{"motivo": "..."}` obligatorio |
| GET | `/rutas` | administrador | Rutas disponibles (el alta exige una) |
| GET | `/camiones` | administrador | Flota. Filtros: `?estado=` `?q=` |
| GET | `/camiones/detalle?id=N` | administrador | Un camión |
| POST | `/camiones` | administrador | **Alta** |
| PUT | `/camiones?id=N` | administrador | **Edición** |
| DELETE | `/camiones?id=N` | administrador | **Baja** lógica; body `{"motivo": "..."}` obligatorio |
| GET | `/instalaciones` | administrador | Vertederos y centros de acopio. Filtros: `?tipo=` `?q=` |
| GET | `/instalaciones/detalle?id=N` | administrador | Una instalación con sus dependencias |
| POST | `/instalaciones` | administrador | **Alta** |
| PUT | `/instalaciones?id=N` | administrador | **Edición** |
| DELETE | `/instalaciones?id=N` | administrador | **Borrado**, solo si no tiene rutas, operarios ni maquinaria (si no, `409`) |
| GET | `/maquinaria` | administrador | Filtros: `?instalacion=ID` `?estado=` |
| GET | `/maquinaria/detalle?id=N` | administrador | Una máquina |
| POST | `/maquinaria` | administrador | **Alta** |
| PUT | `/maquinaria?id=N` | administrador | **Edición** (puede cambiarla de instalación) |
| DELETE | `/maquinaria?id=N` | administrador | **Borrado** (nada la referencia) |

### Reglas de validación

- **Camión:** matrícula obligatoria, única, hasta 20 caracteres; modelo obligatorio; capacidad
  entera entre 1 y 2.147.483.647; estado `funcional`, `en mantenimiento` o `no funcional`
  (por defecto `funcional`). `dado de baja` solo se alcanza con la baja.
- **Contenedor:** ubicación obligatoria (hasta 200); tipo `basura` o `reciclable`; estado
  `funcional`, `no funcional` o `desbordado`; latitud -90..90 y longitud -180..180 (opcionales);
  `id_ruta` debe existir (un contenedor siempre pertenece a una ruta).
- **Instalación:** nombre y ubicación obligatorios; tipo `vertedero` o `centro de acopio`;
  capacidad y ocupación en m³ (enteros); la ocupación no puede superar la capacidad. La
  respuesta incluye `porcentaje` y `alerta` (verdadera desde el 80 %).
- **Maquinaria:** modelo y tipo obligatorios; estado `funcional`, `no funcional` o
  `en mantenimiento`; la instalación debe existir.
- Un camión o contenedor **dado de baja** no se edita ni se vuelve a dar de baja (`409`).

### Ejemplos

```bash
API=http://localhost/cathartes

# Login (guarda la cookie de sesión)
curl -c cj -H 'Content-Type: application/json' \
  -d '{"email":"admin@cathartes.uy","password":"Cathartes2026"}' $API/api-usuarios/public/login

# Listar vecinos
curl -b cj "$API/api-usuarios/public/usuarios?rol=vecino"

# Alta de un operario
curl -b cj -H 'Content-Type: application/json' -d '{"rol":"operario","nombre":"Rosa","apellido":"Pérez",
  "email":"rosa@cathartes.uy","password":"Clave12345","telefono":"099 555 444","id_instalacion":2}' \
  $API/api-usuarios/public/usuarios

# Registrar un camión
curl -b cj -H 'Content-Type: application/json' \
  -d '{"matricula":"SBC 1234","modelo":"Volvo FM","capacidad":12000}' $API/api-gestion/public/camiones

# Pasarlo a mantenimiento (PUT parcial)
curl -b cj -X PUT -H 'Content-Type: application/json' -d '{"estado":"en mantenimiento"}' \
  "$API/api-gestion/public/camiones?id=3"

# Darlo de baja
curl -b cj -X DELETE -H 'Content-Type: application/json' -d '{"motivo":"Fin de vida útil"}' \
  "$API/api-gestion/public/camiones?id=3"
```

## api-recoleccion

La usan la **cuadrilla en territorio** y el **operario de instalación**. Cada endpoint exige su rol y trabaja
sobre lo que es de esa persona: el id sale de la **sesión**, nunca de un parámetro. Una intervención o ruta ajena
responde `404`, como si no existiera. El detalle de cada respuesta está en
[`contrato-api-recoleccion.md`](contrato-api-recoleccion.md).

| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| GET | `/health` | público | Estado del servicio (`503` si la base no responde) |
| GET | `/cuadrilla/resumen` | cuadrilla | Métricas, ruta vigente y las 5 intervenciones más urgentes |
| GET | `/cuadrilla/ruta` | cuadrilla | Rutas que tomó, con la instalación de destino |
| GET | `/cuadrilla/rutas` | cuadrilla | Rutas con sus contenedores y el avance de hoy |
| GET | `/cuadrilla/mapa[?ruta=ID]` | cuadrilla | Contenedores e incidencias abiertas de una ruta (por defecto la vigente) |
| GET | `/cuadrilla/intervenciones` | cuadrilla | Intervenciones asignadas (abiertas primero) |
| GET | `/cuadrilla/intervencion?id=N` | cuadrilla | Detalle y repuestos usados |
| GET | `/cuadrilla/repuestos` | cuadrilla | Inventario con stock |
| POST | `/cuadrilla/contenedor/verificar` | cuadrilla | `{id_contenedor}` lo marca recorrido hoy (idempotente) |
| POST | `/cuadrilla/contenedor/estado` | cuadrilla | `{id_intervencion, estado}` (`funcional`, `no funcional`, `desbordado`) |
| POST | `/cuadrilla/intervencion/repuestos` | cuadrilla | `{id_intervencion, id_repuesto, cuantos}`: descuenta del stock |
| POST | `/cuadrilla/intervencion/finalizar` | cuadrilla | `{id_intervencion}`: cierra la tarea y la incidencia y avisa al vecino |
| GET | `/operario/resumen` | operario | Su instalación y la maquinaria funcional |
| GET | `/operario/instalacion` | operario | Su instalación y su maquinaria |
| POST | `/operario/ocupacion` | operario | `{ocupacion_actual}` (m³, entero, 0..capacidad) |

**Reglas de negocio:**
- La «ruta vigente» de una cuadrilla es su toma **más reciente** (RN10: `toma` es un historial).
- Verificar un contenedor exige que esté en una ruta que la cuadrilla tomó (`403` si no) y que no esté dado de baja (`409`).
- Una intervención **finalizada** no admite más cambios de estado ni repuestos (`409`).
- Usar repuestos es **transaccional**: si no alcanza el stock responde `409` («quedan N») y no descuenta nada; usar el
  mismo repuesto dos veces en una intervención acumula la cantidad.
- La cuadrilla no puede dar de baja un contenedor (eso es del administrador).
- Un operario solo ve y modifica **su** instalación. Si su nueva ocupación cruza el 80 %, se avisa a los administradores.

## Ciclo de una incidencia y quién recibe cada aviso

| Paso | Quién | Dónde | Aviso (tipo) | Para |
|---|---|---|---|---|
| 1. Reporta con foto obligatoria | vecino | `POST /vecino/incidencias` (api-gestion) | `nueva incidencia` | todos los administradores activos |
| 2. Valida el reporte | administrador | backoffice | `incidencia validada` | el vecino |
| 3. Asigna una cuadrilla (solo si está validada) | administrador | backoffice | `incidencia asignada` · `intervencion asignada` | el vecino · la cuadrilla |
| 4. Trabaja y finaliza | cuadrilla | `POST /cuadrilla/intervencion/finalizar` | `incidencia finalizada` | el vecino |
| 4b. (alternativa) Finaliza | administrador | backoffice | `incidencia finalizada` | el vecino |
| Ocupación al 80 % o más | operario o administrador | `/operario/ocupacion`, `PUT /instalaciones`, backoffice | `instalacion llena` | todos los administradores activos |

- La alerta de instalación llena avisa **solo al cruzar** el 80 % hacia arriba, no en cada actualización posterior.
- Una notificación es un aviso: si no se pudiera escribir, se registra en el log y la acción principal sigue adelante.
- Si falla el guardado de la foto, la incidencia **no queda registrada** (la foto es obligatoria).
