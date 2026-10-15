# Modelo físico de la base de datos `cathartes`

Generado desde la base real (`db/BD.sql`). Motor InnoDB, charset utf8mb4. El diagrama se visualiza directamente en GitHub (Mermaid).

Total: **20 tablas**.

```mermaid
erDiagram
    administrador ||--o{ autorizacion : "tiene"
    administrador ||--o{ gestiona : "tiene"
    camion ||--o{ cuadrilla : "tiene"
    contenedor ||--o{ incidencia_historial : "tiene"
    contenedor ||--o{ verificacion : "tiene"
    cuadrilla ||--o{ intervencion : "tiene"
    cuadrilla ||--o{ toma : "tiene"
    incidencia_historial ||--o{ intervencion : "tiene"
    incidencia_historial ||--o{ notificacion : "tiene"
    instalacion ||--o{ maquinaria : "tiene"
    instalacion ||--o{ operario : "tiene"
    instalacion ||--o{ ruta : "tiene"
    intervencion ||--o{ usa : "tiene"
    repuestos ||--o{ usa : "tiene"
    ruta ||--o{ contenedor : "tiene"
    ruta ||--o{ toma : "tiene"
    usuario ||--o{ administrador : "tiene"
    usuario ||--o{ autorizacion : "tiene"
    usuario ||--o{ cuadrilla : "tiene"
    usuario ||--o{ incidencia_historial : "tiene"
    usuario ||--o{ notificacion : "tiene"
    usuario ||--o{ operario : "tiene"
    usuario ||--o{ usuario_telefono : "tiene"
    usuario ||--o{ vecino : "tiene"
    usuario ||--o{ verificacion : "tiene"
    administrador {
        int_11 ID_usuario PK,FK
        enum_si_no jefe
    }
    autorizacion {
        int_11 ID_admin PK,FK
        int_11 ID_usuario PK,FK
        datetime fecha PK
        enum_aprobada_rechazada_baja accion
    }
    camion {
        int_11 ID PK
        varchar_20 matricula UK
        varchar_100 modelo
        int_11 capacidad
        enum_funcional_en_mantenimiento_no_funcional_dado_de_baja estado
        datetime fecha_baja
        varchar_255 motivo_baja
    }
    contenedor {
        int_11 ID PK
        varchar_200 ubicacion
        decimal_9_6 latitud
        decimal_9_6 longitud
        enum_basura_reciclable tipo
        enum_funcional_no_funcional_desbordado_dado_de_baja estado
        datetime fecha_baja
        varchar_255 motivo_baja
        int_11 ID_ruta FK
    }
    cuadrilla {
        int_11 ID_usuario PK,FK
        int_11 ID_camion FK,UK
        enum_en_servicio_fuera_de_servicio estado
    }
    gestiona {
        int_11 ID_ejecutor PK,FK
        int_11 ID_afectado PK,FK
        datetime fecha PK
        varchar_50 accion
    }
    incidencia_historial {
        int_11 ID_incidencia PK
        datetime fecha_ing
        varchar_255 descripcion
        varchar_255 foto
        enum_desbordado_roto_falta_de_recoleccion tipo
        enum_si_no validada
        enum_en_curso_finalizada estado
        int_11 ID_usuario FK
        int_11 ID_contenedor FK
    }
    instalacion {
        int_11 ID PK
        varchar_100 nombre
        varchar_200 ubicacion
        decimal_9_6 latitud
        decimal_9_6 longitud
        int_11 capacidad
        int_11 ocupacion_actual
        enum_vertedero_centro_de_acopio tipo
    }
    intervencion {
        int_11 ID_intervencion PK
        datetime fecha_inicio
        datetime fecha_fin
        enum_si_no tarea_finalizada
        int_11 ID_incidencia FK
        int_11 ID_cuadrilla FK
    }
    maquinaria {
        int_11 ID PK
        varchar_100 modelo
        varchar_50 tipo
        enum_funcional_no_funcional_en_mantenimiento estado
        int_11 ID_instalacion FK
    }
    notificacion {
        int_11 ID PK
        int_11 ID_usuario FK
        int_11 ID_incidencia FK
        varchar_50 tipo
        varchar_255 mensaje
        datetime fecha
        enum_si_no leida
    }
    operario {
        int_11 ID_usuario PK,FK
        int_11 ID_instalacion FK
    }
    repuestos {
        int_11 ID PK
        varchar_100 nombre
        varchar_50 tipo
        int_11 cantidad
    }
    ruta {
        int_11 ID PK
        varchar_100 nombre
        varchar_50 zona
        varchar_50 frecuencia
        enum_activa_inactiva estado
        int_11 ID_instalacion FK
    }
    toma {
        int_11 ID_cuadrilla PK,FK
        int_11 ID_ruta PK,FK
        datetime fecha PK
    }
    usa {
        int_11 ID_intervencion PK,FK
        int_11 ID_repuesto PK,FK
        int_11 cuantos
    }
    usuario {
        int_11 ID PK
        varchar_100 nombre
        varchar_150 email UK
        varchar_255 contrasena
        enum_activo_no_activo estado
    }
    usuario_telefono {
        int_11 id_tel PK
        varchar_20 telefono
        int_11 ID_usuario FK
    }
    vecino {
        int_11 ID_usuario PK,FK
    }
    verificacion {
        int_11 ID_usuario PK,FK
        int_11 ID_contenedor PK,FK
        datetime fecha PK
    }
```

## Tablas y columnas

### `administrador`

| Columna | Tipo | Nulo | Clave |
|---|---|---|---|
| ID_usuario | int(11) | No | PK, FK → usuario |
| jefe | enum('si','no') | No |  |

### `autorizacion`

| Columna | Tipo | Nulo | Clave |
|---|---|---|---|
| ID_admin | int(11) | No | PK, FK → administrador |
| ID_usuario | int(11) | No | PK, FK → usuario |
| fecha | datetime | No | PK |
| accion | enum('aprobada','rechazada','baja') | No |  |

### `camion`

| Columna | Tipo | Nulo | Clave |
|---|---|---|---|
| ID | int(11) | No | PK |
| matricula | varchar(20) | No | UNIQUE |
| modelo | varchar(100) | No |  |
| capacidad | int(11) | No |  |
| estado | enum('funcional','en mantenimiento','no funcional','dado de baja') | No |  |
| fecha_baja | datetime | Sí |  |
| motivo_baja | varchar(255) | Sí |  |

### `contenedor`

| Columna | Tipo | Nulo | Clave |
|---|---|---|---|
| ID | int(11) | No | PK |
| ubicacion | varchar(200) | No |  |
| latitud | decimal(9,6) | Sí |  |
| longitud | decimal(9,6) | Sí |  |
| tipo | enum('basura','reciclable') | No |  |
| estado | enum('funcional','no funcional','desbordado','dado de baja') | No |  |
| fecha_baja | datetime | Sí |  |
| motivo_baja | varchar(255) | Sí |  |
| ID_ruta | int(11) | No | FK → ruta |

### `cuadrilla`

| Columna | Tipo | Nulo | Clave |
|---|---|---|---|
| ID_usuario | int(11) | No | PK, FK → usuario |
| ID_camion | int(11) | Sí | FK → camion, UNIQUE |
| estado | enum('en servicio','fuera de servicio') | No |  |

### `gestiona`

| Columna | Tipo | Nulo | Clave |
|---|---|---|---|
| ID_ejecutor | int(11) | No | PK, FK → administrador |
| ID_afectado | int(11) | No | PK, FK → administrador |
| fecha | datetime | No | PK |
| accion | varchar(50) | No |  |

### `incidencia_historial`

| Columna | Tipo | Nulo | Clave |
|---|---|---|---|
| ID_incidencia | int(11) | No | PK |
| fecha_ing | datetime | No |  |
| descripcion | varchar(255) | Sí |  |
| foto | varchar(255) | Sí |  |
| tipo | enum('desbordado','roto','falta de recoleccion') | No |  |
| validada | enum('si','no') | No |  |
| estado | enum('en curso','finalizada') | No |  |
| ID_usuario | int(11) | No | FK → usuario |
| ID_contenedor | int(11) | No | FK → contenedor |

### `instalacion`

| Columna | Tipo | Nulo | Clave |
|---|---|---|---|
| ID | int(11) | No | PK |
| nombre | varchar(100) | No |  |
| ubicacion | varchar(200) | No |  |
| latitud | decimal(9,6) | Sí |  |
| longitud | decimal(9,6) | Sí |  |
| capacidad | int(11) | No |  |
| ocupacion_actual | int(11) | No |  |
| tipo | enum('vertedero','centro de acopio') | No |  |

### `intervencion`

| Columna | Tipo | Nulo | Clave |
|---|---|---|---|
| ID_intervencion | int(11) | No | PK |
| fecha_inicio | datetime | No |  |
| fecha_fin | datetime | Sí |  |
| tarea_finalizada | enum('si','no') | No |  |
| ID_incidencia | int(11) | No | FK → incidencia_historial |
| ID_cuadrilla | int(11) | No | FK → cuadrilla |

### `maquinaria`

| Columna | Tipo | Nulo | Clave |
|---|---|---|---|
| ID | int(11) | No | PK |
| modelo | varchar(100) | No |  |
| tipo | varchar(50) | No |  |
| estado | enum('funcional','no funcional','en mantenimiento') | No |  |
| ID_instalacion | int(11) | No | FK → instalacion |

### `notificacion`

| Columna | Tipo | Nulo | Clave |
|---|---|---|---|
| ID | int(11) | No | PK |
| ID_usuario | int(11) | No | FK → usuario |
| ID_incidencia | int(11) | Sí | FK → incidencia_historial |
| tipo | varchar(50) | No |  |
| mensaje | varchar(255) | No |  |
| fecha | datetime | No |  |
| leida | enum('si','no') | No |  |

### `operario`

| Columna | Tipo | Nulo | Clave |
|---|---|---|---|
| ID_usuario | int(11) | No | PK, FK → usuario |
| ID_instalacion | int(11) | No | FK → instalacion |

### `repuestos`

| Columna | Tipo | Nulo | Clave |
|---|---|---|---|
| ID | int(11) | No | PK |
| nombre | varchar(100) | No |  |
| tipo | varchar(50) | No |  |
| cantidad | int(11) | No |  |

### `ruta`

| Columna | Tipo | Nulo | Clave |
|---|---|---|---|
| ID | int(11) | No | PK |
| nombre | varchar(100) | No |  |
| zona | varchar(50) | No |  |
| frecuencia | varchar(50) | No |  |
| estado | enum('activa','inactiva') | No |  |
| ID_instalacion | int(11) | No | FK → instalacion |

### `toma`

| Columna | Tipo | Nulo | Clave |
|---|---|---|---|
| ID_cuadrilla | int(11) | No | PK, FK → cuadrilla |
| ID_ruta | int(11) | No | PK, FK → ruta |
| fecha | datetime | No | PK |

### `usa`

| Columna | Tipo | Nulo | Clave |
|---|---|---|---|
| ID_intervencion | int(11) | No | PK, FK → intervencion |
| ID_repuesto | int(11) | No | PK, FK → repuestos |
| cuantos | int(11) | No |  |

### `usuario`

| Columna | Tipo | Nulo | Clave |
|---|---|---|---|
| ID | int(11) | No | PK |
| nombre | varchar(100) | No |  |
| email | varchar(150) | No | UNIQUE |
| contrasena | varchar(255) | No |  |
| estado | enum('activo','no activo') | No |  |

### `usuario_telefono`

| Columna | Tipo | Nulo | Clave |
|---|---|---|---|
| id_tel | int(11) | No | PK |
| telefono | varchar(20) | No |  |
| ID_usuario | int(11) | No | FK → usuario |

### `vecino`

| Columna | Tipo | Nulo | Clave |
|---|---|---|---|
| ID_usuario | int(11) | No | PK, FK → usuario |

### `verificacion`

| Columna | Tipo | Nulo | Clave |
|---|---|---|---|
| ID_usuario | int(11) | No | PK, FK → usuario |
| ID_contenedor | int(11) | No | PK, FK → contenedor |
| fecha | datetime | No | PK |
