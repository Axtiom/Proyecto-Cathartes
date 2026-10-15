# API — endpoints de usuarios y gestión

Todas las respuestas son JSON con la forma `{"ok": true|false, ...}`.
La sesión viaja en una cookie `HttpOnly` que emite el login: en `curl` hay que guardarla
(`-c`) y reenviarla (`-b`). **El rol se valida en el backend**: ocultar un botón en el
frontend no es un control de seguridad.

**URLs.** En local (proyecto servido desde la raíz, como en Docker) cada API cuelga de su
carpeta `public/`: `http://localhost:8080/api-usuarios/public/...` y
`http://localhost:8080/api-gestion/public/...`. Si cada app tiene su propio document root,
la base es `/api-usuarios/...` y `/api-gestion/...`.

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
API=http://localhost:8080

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
