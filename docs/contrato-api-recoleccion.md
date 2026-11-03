# Contrato de la API de Recolección

Es lo que `frontend-usuarios` (pantallas `cuadrilla-*.js` y `operario-*.js`) lee y envía. Está fijado por
`tests/Http/ContratoFrontendTest.php`: si un endpoint cambia un nombre de campo, el test falla antes de que la
pantalla se rompa sin avisar.

Todas las respuestas: `{"ok": true|false, ...}`. Errores: `{"ok": false, "error": "mensaje"}` con `400` (datos
inválidos), `401` (sin sesión), `403` (rol o contenedor ajeno), `404` (no existe **o no es tuyo**), `409` (conflicto
con el estado actual). Fechas: `AAAA-MM-DD hh:mm:ss`.

## Cuadrilla (rol `cuadrilla`)

### `GET /cuadrilla/resumen`
```json
{ "metricas": { "rutas_hoy": 2, "intervenciones_pendientes": 2, "contenedores_a_verificar": 1 },
  "ruta_activa": { "id": 2, "nombre": "…", "zona": "…", "frecuencia": "…", "estado": "activa",
                   "fecha_toma": "…", "verificados": 1, "total_contenedores": 2 },
  "urgentes": [ { "id_intervencion": 3, "id_incidencia": 8, "tipo": "roto", "fecha_inicio": "…",
                  "ubicacion": "…", "estado_contenedor": "desbordado" } ] }
```
`ruta_activa` es `null` si todavía no tomó ninguna. `urgentes`: hasta 5 abiertas, primero las de contenedor
`desbordado`, después `no funcional`, y dentro de cada grupo las más antiguas. `rutas_hoy` = rutas **activas** que tomó.

### `GET /cuadrilla/ruta`
`{"rutas": [{"id","nombre","frecuencia","instalacion","fecha"}]}` — historial de tomas, la más nueva primero.

### `GET /cuadrilla/rutas`
`{"total": N, "items": [{"id","nombre","zona","frecuencia","estado","fecha_toma","verificados",
"contenedores": [{"id","ubicacion","tipo","estado","latitud","longitud","verificado_hoy"}]}]}`.
Una fila por ruta (con su toma más reciente). Los contenedores dados de baja no figuran.

### `GET /cuadrilla/mapa` · `GET /cuadrilla/mapa?ruta=ID`
```json
{ "ruta": {"id","nombre","zona"},
  "contenedores": [ {"id","ubicacion","tipo","estado","latitud","longitud","verificado_hoy"} ],
  "incidencias":  [ {"id","tipo":"incidencia","estado":"en curso","latitud","longitud","titulo","ubicacion"} ] }
```
Sin `ruta` usa la vigente; sin ninguna, `{"ruta": null, "contenedores": [], "incidencias": []}`. `?ruta=` de una ruta que
no tomó → `404`. Los elementos de `incidencias` ya vienen con la forma de marcador que dibuja `js/mapa.js`.

### `GET /cuadrilla/intervenciones`
`{"total": N, "items": [{"id_intervencion","id_incidencia","tipo","fecha_inicio","fecha_fin","tarea_finalizada":"si|no",
"ubicacion","estado_contenedor"}]}` — las abiertas primero.

### `GET /cuadrilla/intervencion?id=N`
```json
{ "intervencion": { "id_intervencion","id_incidencia","tipo","descripcion","vecino","id_contenedor","ubicacion",
                    "estado_contenedor","estado_incidencia","fecha_inicio","fecha_fin","tarea_finalizada" },
  "repuestos": [ {"nombre","tipo","cuantos"} ] }
```

### `GET /cuadrilla/repuestos`
`{"total": N, "items": [{"id","nombre","tipo","cantidad"}]}` — solo los que tienen stock.

### `POST /cuadrilla/contenedor/verificar` — `{"id_contenedor": N}`
`200 {"ya_verificado": false}` la primera vez del día, `{"ya_verificado": true}` si ya estaba. `403` si el contenedor no
está en una ruta que tomó; `409` si está dado de baja.

### `POST /cuadrilla/contenedor/estado` — `{"id_intervencion": N, "estado": "funcional|no funcional|desbordado"}`
Cambia el estado del contenedor de esa incidencia. `400` con otro estado (incluido `dado de baja`); `409` si la
intervención ya está finalizada o el contenedor está dado de baja.

### `POST /cuadrilla/intervencion/repuestos` — `{"id_intervencion": N, "id_repuesto": N, "cuantos": N}`
`cuantos`: entero ≥ 1. Descuenta del inventario dentro de una transacción. `409` si no alcanza el stock (el mensaje dice
cuántos quedan) o la intervención está cerrada; `404` si el repuesto no existe.

### `POST /cuadrilla/intervencion/finalizar` — `{"id_intervencion": N}`
La intervención queda `tarea_finalizada = "si"` con `fecha_fin`, la incidencia pasa a `finalizada` y el vecino recibe el
aviso `incidencia finalizada`. `409` si ya estaba finalizada.

## Operario (rol `operario`)

### `GET /operario/resumen`
`{"instalacion": {"id","nombre","tipo","ubicacion","capacidad","ocupacion_actual","porcentaje","alerta"},
"maquinaria": {"funcional": N, "total": N}}`

### `GET /operario/instalacion`
`{"instalacion": { …igual… }, "maquinaria": [{"id","modelo","tipo","estado"}]}`

### `POST /operario/ocupacion` — `{"ocupacion_actual": N}`
Entero en m³ entre 0 y la capacidad (`400` si no). `200 {"alerta": true|false}`; `alerta` es `true` solo si con este valor
la instalación cruzó el 80 % hacia arriba.

## Notificaciones (api-usuarios, cualquier rol)
`GET /notificaciones` → `{"total","no_leidas","items":[{"id","tipo","mensaje","fecha","leida":true|false,"id_incidencia"}]}`.
`GET /notificaciones/contador` → `{"no_leidas": N}`. `POST /notificaciones/leer?id=N` → `{"no_leidas": N}` (`404` si no es
tuya). `POST /notificaciones/leer-todas` → `{"marcadas": N, "no_leidas": 0}`.
