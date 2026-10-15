<?php
declare(strict_types=1);

/**
 * Model de Usuarios de la API de Usuarios (PDO directo a `cathartes`).
 *
 * Recordá (BUG-003): la columna de contraseña es `contrasena`, sin ñ. En PDO va
 * con backticks.
 *
 * Esta es la versión liviana: solo lo que necesitan el registro, el login y la
 * recuperación de contraseña. El ABM completo de cuentas (aprobar, rechazar,
 * dar de baja, listar, alta multirol) vive en el Usuario del Backoffice — son
 * apps separadas, cada una con el modelo que le corresponde a su tarea.
 */
class Usuario
{
    /** La usa Auth::proteger() (guard de sesión) en cada request protegido. */
    public static function porId(int $id): ?array
    {
        $st = Database::conexion()->prepare('SELECT ID, nombre, email, estado FROM usuario WHERE ID = ?');
        $st->execute([$id]);
        $u = $st->fetch();
        return $u ?: null;
    }

    /** SELECT * más el alias `hash` sobre `contrasena` (lo usa el login). */
    public static function porEmail(string $email): ?array
    {
        $st = Database::conexion()->prepare(
            'SELECT *, `contrasena` AS hash FROM usuario WHERE email = ?'
        );
        $st->execute([$email]);
        $u = $st->fetch();
        return $u ?: null;
    }

    public static function existeEmail(string $email): bool
    {
        $st = Database::conexion()->prepare('SELECT 1 FROM usuario WHERE email = ? LIMIT 1');
        $st->execute([$email]);
        return (bool) $st->fetchColumn();
    }

    /**
     * Alta de Vecino en una transacción: usuario (queda 'no activo' por default)
     * + vecino + usuario_telefono. Devuelve el ID nuevo. La usa el
     * registro público (AccesoController::registro).
     */
    public static function crearVecino(string $nombreCompleto, string $email, string $hash, string $telefono): int
    {
        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('INSERT INTO usuario (nombre, email, `contrasena`) VALUES (?, ?, ?)');
            $st->execute([$nombreCompleto, $email, $hash]);
            $id = (int) $pdo->lastInsertId();

            $pdo->prepare('INSERT INTO vecino (ID_usuario) VALUES (?)')->execute([$id]);
            $pdo->prepare('INSERT INTO usuario_telefono (telefono, ID_usuario) VALUES (?, ?)')->execute([$telefono, $id]);

            $pdo->commit();
            return $id;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function actualizarPassword(int $id, string $hash): void
    {
        $st = Database::conexion()->prepare('UPDATE usuario SET `contrasena` = ? WHERE ID = ?');
        $st->execute([$hash, $id]);
    }

    private const ROLES = ['administrador', 'operario', 'cuadrilla', 'vecino'];

    /**
     * Listado de cuentas para el administrador. NUNCA devuelve la contraseña ni su hash.
     * El rol se deduce de la tabla de subtipo en la que está el ID (no es una columna).
     * @return list<array>
     */
    public static function listar(?string $rol = null, ?string $estado = null, ?string $q = null): array
    {
        $sql = "SELECT u.ID, u.nombre, u.email, u.estado,
                       CASE WHEN a.ID_usuario IS NOT NULL THEN 'administrador'
                            WHEN o.ID_usuario IS NOT NULL THEN 'operario'
                            WHEN c.ID_usuario IS NOT NULL THEN 'cuadrilla'
                            WHEN v.ID_usuario IS NOT NULL THEN 'vecino'
                       END AS rol
                FROM usuario u
                LEFT JOIN administrador a ON a.ID_usuario = u.ID
                LEFT JOIN operario o      ON o.ID_usuario = u.ID
                LEFT JOIN cuadrilla c     ON c.ID_usuario = u.ID
                LEFT JOIN vecino v        ON v.ID_usuario = u.ID";
        $where = []; $p = [];
        if ($estado !== null && in_array($estado, ['activo', 'no activo'], true)) { $where[] = 'u.estado = ?'; $p[] = $estado; }
        $q = trim((string) $q);
        if ($q !== '') { $where[] = '(u.nombre LIKE ? OR u.email LIKE ?)'; $p[] = "%$q%"; $p[] = "%$q%"; }
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY u.ID';

        $st = Database::conexion()->prepare($sql);
        $st->execute($p);
        $filas = $st->fetchAll();
        if ($rol !== null && in_array($rol, self::ROLES, true)) {
            $filas = array_values(array_filter($filas, fn ($f) => $f['rol'] === $rol));
        }
        return array_map(fn ($f) => [
            'id' => (int) $f['ID'], 'nombre' => (string) $f['nombre'], 'email' => (string) $f['email'],
            'estado' => (string) $f['estado'], 'rol' => $f['rol'],
        ], $filas);
    }

    /** Un usuario con sus teléfonos (sin datos de acceso). */
    public static function detalle(int $id): ?array
    {
        $fila = array_values(array_filter(self::listar(), fn ($u) => $u['id'] === $id))[0] ?? null;
        if ($fila === null) return null;
        $st = Database::conexion()->prepare('SELECT telefono FROM usuario_telefono WHERE ID_usuario = ? ORDER BY id_tel');
        $st->execute([$id]);
        $fila['telefonos'] = $st->fetchAll(PDO::FETCH_COLUMN);
        return $fila;
    }

    // =====================================================================
    //  Gestión de cuentas (CRUD) — lo usa el administrador
    //  Reglas de negocio: RN3 (solo un administrador jefe crea o da de baja
    //  administradores) y RN4 (no se puede dar de baja al último jefe activo).
    //  Cada método devuelve ['ok'=>bool, 'error'?, 'codigo'?] y el
    //  Controller lo traduce a HTTP (400 validación, 403 permiso, 404, 409).
    // =====================================================================

    private static function falla(string $mensaje, int $codigo = 400): array
    {
        return ['ok' => false, 'error' => $mensaje, 'codigo' => $codigo];
    }

    /** Rol deducido de la tabla de subtipo en la que está el ID. */
    public static function rolDe(int $id): ?string
    {
        $pdo = Database::conexion();
        foreach (self::ROLES as $rol) {
            $st = $pdo->prepare("SELECT 1 FROM {$rol} WHERE ID_usuario = ? LIMIT 1");
            $st->execute([$id]);
            if ($st->fetchColumn()) return $rol;
        }
        return null;
    }

    public static function esJefe(int $id): bool
    {
        $st = Database::conexion()->prepare("SELECT 1 FROM administrador WHERE ID_usuario = ? AND jefe = 'si' LIMIT 1");
        $st->execute([$id]);
        return (bool) $st->fetchColumn();
    }

    /** ¿Es el único administrador jefe activo? (RN4) */
    public static function esUltimoJefe(int $id): bool
    {
        $pdo = Database::conexion();
        $n = (int) $pdo->query(
            "SELECT COUNT(*) FROM administrador a JOIN usuario u ON u.ID = a.ID_usuario WHERE a.jefe = 'si' AND u.estado = 'activo'"
        )->fetchColumn();
        return $n <= 1 && self::esJefe($id);
    }

    /** Teléfonos válidos del payload: acepta `telefonos` (lista) o `telefono` (uno). */
    private static function telefonos(array $d): ?array
    {
        if (!array_key_exists('telefonos', $d) && !array_key_exists('telefono', $d)) return null;
        $t = $d['telefonos'] ?? $d['telefono'];
        if (!is_array($t)) $t = [$t];
        return array_values(array_filter(array_map(fn ($x) => trim((string) $x), $t), fn ($x) => $x !== ''));
    }

    private static function telefonosInvalidos(array $tels): ?string
    {
        if (!$tels) return 'Ingresá al menos un teléfono.';
        foreach ($tels as $t) {
            if (!preg_match('/^[0-9 +()\-]{6,20}$/', $t)) return 'El teléfono "' . $t . '" no es válido (solo números, espacios, + ( ) -).';
        }
        return null;
    }

    /**
     * `autorizacion` y `gestiona` tienen PK (admin, cuenta, fecha) con fecha al SEGUNDO:
     * dos acciones del mismo administrador sobre la misma cuenta dentro del mismo segundo
     * chocan con la PK (error 1062). En vez de perder la auditoría o devolver un 500, se
     * reintenta corriendo la fecha un segundo hacia adelante.
     */
    private static function insertarAuditoria(PDO $pdo, string $tabla, array $valores): void
    {
        [$colA, $colB, $colAccion] = $tabla === 'autorizacion'
            ? ['ID_admin', 'ID_usuario', 'accion'] : ['ID_ejecutor', 'ID_afectado', 'accion'];
        $sql = "INSERT INTO {$tabla} ({$colA}, {$colB}, fecha, {$colAccion}) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), ?)";
        for ($desfase = 0; $desfase < 10; $desfase++) {
            try {
                $pdo->prepare($sql)->execute([$valores[0], $valores[1], $desfase, $valores[2]]);
                return;
            } catch (PDOException $e) {
                if ((int) ($e->errorInfo[1] ?? 0) !== 1062) throw $e;
            }
        }
        throw new RuntimeException("No se pudo registrar la auditoría en {$tabla}.");
    }

    private static function auditar(PDO $pdo, int $idAdmin, int $idUsuario, string $accion): void
    {
        self::insertarAuditoria($pdo, 'autorizacion', [$idAdmin, $idUsuario, $accion]);
    }

    private static function gestionar(PDO $pdo, int $ejecutor, int $afectado, string $accion): void
    {
        self::insertarAuditoria($pdo, 'gestiona', [$ejecutor, $afectado, $accion]);
    }

    /** ¿El camión está libre (sin cuadrilla)? Se ignora la cuadrilla $exceptoUsuario. */
    private static function camionDisponible(int $idCamion, int $exceptoUsuario = 0): ?string
    {
        $pdo = Database::conexion();
        $st = $pdo->prepare('SELECT estado FROM camion WHERE ID = ?');
        $st->execute([$idCamion]);
        $estado = $st->fetchColumn();
        if ($estado === false) return 'El camión no existe.';
        if ($estado === 'dado de baja') return 'El camión está dado de baja.';
        $st = $pdo->prepare('SELECT 1 FROM cuadrilla WHERE ID_camion = ? AND ID_usuario <> ? LIMIT 1');
        $st->execute([$idCamion, $exceptoUsuario]);
        if ($st->fetchColumn()) return 'Ese camión ya está asignado a otra cuadrilla.';
        return null;
    }

    private static function instalacionExiste(int $id): bool
    {
        $st = Database::conexion()->prepare('SELECT 1 FROM instalacion WHERE ID = ?');
        $st->execute([$id]);
        return (bool) $st->fetchColumn();
    }

    /** Cada campo de subtipo es de un solo rol: si llega con valor para otro rol, se rechaza. */
    private static function campoAjeno(string $rol, array $d): ?string
    {
        $dueno  = ['id_instalacion' => 'operario', 'id_camion' => 'cuadrilla', 'estado_cuadrilla' => 'cuadrilla', 'jefe' => 'administrador'];
        $neutro = ['id_instalacion' => ['', '0', null, 0], 'id_camion' => ['', '0', null, 0], 'estado_cuadrilla' => ['', null], 'jefe' => ['', null]];
        foreach ($dueno as $campo => $rolDueno) {
            if (!array_key_exists($campo, $d)) continue;
            if (in_array($d[$campo], $neutro[$campo], true)) continue;
            if ($campo === 'jefe' && $d[$campo] === 'no' && $rol !== 'administrador') continue;
            if ($rol !== $rolDueno) return 'Un ' . $rol . ' no puede recibir el campo "' . $campo . '" (es de ' . $rolDueno . ').';
        }
        return null;
    }

    /**
     * Alta de una cuenta con rol. Entra 'activo' (la crea un administrador).
     * @return array{ok:bool,error?:string,codigo?:int,id?:int,rol?:string}
     */
    public static function crear(array $d, int $idAdmin): array
    {
        $rol = (string) ($d['rol'] ?? '');
        if (!in_array($rol, self::ROLES, true)) return self::falla('Rol inválido (administrador, operario, cuadrilla o vecino).');
        if ($rol === 'administrador' && !self::esJefe($idAdmin)) {
            return self::falla('Solo un administrador jefe puede crear administradores.', 403);
        }

        $nombrePila = trim((string) ($d['nombre'] ?? ''));
        $nombre = trim($nombrePila . ' ' . trim((string) ($d['apellido'] ?? '')));
        $email  = trim((string) ($d['email'] ?? ''));
        $pass   = (string) ($d['password'] ?? '');
        if ($nombrePila === '') return self::falla('El nombre es obligatorio.');
        if (mb_strlen($nombre) > 100) return self::falla('El nombre admite hasta 100 caracteres.');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) return self::falla('El correo no es válido.');
        if (strlen($pass) < 8) return self::falla('La contraseña debe tener al menos 8 caracteres.');
        $tels = self::telefonos($d) ?? [];
        if ($e = self::telefonosInvalidos($tels)) return self::falla($e);
        if (self::existeEmail($email)) return self::falla('Ya existe un usuario con ese correo.', 409);
        if ($e = self::campoAjeno($rol, $d)) return self::falla($e);

        $idInst = $rol === 'operario'  ? (int) ($d['id_instalacion'] ?? 0) : 0;
        $idCam  = $rol === 'cuadrilla' ? (int) ($d['id_camion'] ?? 0) : 0;
        if ($rol === 'operario') {
            if ($idInst <= 0) return self::falla('El operario necesita una instalación.');
            if (!self::instalacionExiste($idInst)) return self::falla('La instalación no existe.');
        }
        if ($rol === 'cuadrilla' && $idCam > 0 && ($e = self::camionDisponible($idCam))) return self::falla($e, 409);
        $estadoCuad = (string) ($d['estado_cuadrilla'] ?? 'en servicio') ?: 'en servicio';
        if ($rol === 'cuadrilla' && !in_array($estadoCuad, ['en servicio', 'fuera de servicio'], true)) return self::falla('Estado de cuadrilla inválido.');
        $jefe = ($rol === 'administrador' && ($d['jefe'] ?? '') === 'si') ? 'si' : 'no';

        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO usuario (nombre, email, `contrasena`, estado) VALUES (?, ?, ?, "activo")')
                ->execute([$nombre, $email, password_hash($pass, PASSWORD_DEFAULT)]);
            $id = (int) $pdo->lastInsertId();
            foreach ($tels as $t) {
                $pdo->prepare('INSERT INTO usuario_telefono (telefono, ID_usuario) VALUES (?, ?)')->execute([$t, $id]);
            }
            switch ($rol) {
                case 'vecino':
                    $pdo->prepare('INSERT INTO vecino (ID_usuario) VALUES (?)')->execute([$id]); break;
                case 'operario':
                    $pdo->prepare('INSERT INTO operario (ID_usuario, ID_instalacion) VALUES (?, ?)')->execute([$id, $idInst]); break;
                case 'cuadrilla':
                    $pdo->prepare('INSERT INTO cuadrilla (ID_usuario, ID_camion, estado) VALUES (?, ?, ?)')
                        ->execute([$id, $idCam > 0 ? $idCam : null, $estadoCuad]); break;
                case 'administrador':
                    $pdo->prepare('INSERT INTO administrador (ID_usuario, jefe) VALUES (?, ?)')->execute([$id, $jefe]); break;
            }
            self::auditar($pdo, $idAdmin, $id, 'aprobada');
            if ($rol === 'administrador') self::gestionar($pdo, $idAdmin, $id, 'alta');
            $pdo->commit();
            return ['ok' => true, 'id' => $id, 'rol' => $rol];
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e; // lo contiene el manejador global: 500 genérico y detalle al log
        }
    }

    /**
     * Edita una cuenta (PUT parcial: solo cambia lo que se envía). El rol no se cambia.
     * Un administrador solo puede ser editado por un jefe o por él mismo.
     * @return array{ok:bool,error?:string,codigo?:int}
     */
    public static function actualizar(int $id, array $d, int $idAdmin): array
    {
        $u = self::porId($id);
        if ($u === null) return self::falla('Usuario inexistente.', 404);
        $rol = self::rolDe($id);
        if ($rol === null) return self::falla('La cuenta no tiene un rol asignado.', 409);
        if (isset($d['rol']) && $d['rol'] !== $rol) return self::falla('El rol de una cuenta no se puede cambiar.');
        if ($rol === 'administrador' && $id !== $idAdmin && !self::esJefe($idAdmin)) {
            return self::falla('Solo un administrador jefe puede modificar a otros administradores.', 403);
        }
        if ($e = self::campoAjeno($rol, $d)) return self::falla($e);

        $nombre = array_key_exists('nombre', $d) ? trim((string) $d['nombre']) : null;
        $email  = array_key_exists('email', $d) ? trim((string) $d['email']) : null;
        $pass   = array_key_exists('password', $d) ? (string) $d['password'] : null;
        if ($nombre !== null && ($nombre === '' || mb_strlen($nombre) > 100)) return self::falla('El nombre es obligatorio (hasta 100 caracteres).');
        if ($email !== null) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) return self::falla('El correo no es válido.');
            $st = Database::conexion()->prepare('SELECT 1 FROM usuario WHERE email = ? AND ID <> ?');
            $st->execute([$email, $id]);
            if ($st->fetchColumn()) return self::falla('Ya existe un usuario con ese correo.', 409);
        }
        if ($pass !== null && strlen($pass) < 8) return self::falla('La contraseña debe tener al menos 8 caracteres.');
        $tels = self::telefonos($d);
        if ($tels !== null && ($e = self::telefonosInvalidos($tels))) return self::falla($e);

        // Subtipo
        $sub = [];
        if ($rol === 'operario' && array_key_exists('id_instalacion', $d)) {
            $inst = (int) $d['id_instalacion'];
            if ($inst <= 0 || !self::instalacionExiste($inst)) return self::falla('La instalación no existe.');
            $sub = ['operario' => ['ID_instalacion', $inst]];
        }
        if ($rol === 'cuadrilla') {
            if (array_key_exists('id_camion', $d)) {
                $cam = (int) $d['id_camion'];   // 0 / null = quitar el camión
                if ($cam > 0 && ($e = self::camionDisponible($cam, $id))) return self::falla($e, 409);
                $sub['cuadrilla_camion'] = ['ID_camion', $cam > 0 ? $cam : null];
            }
            if (array_key_exists('estado_cuadrilla', $d)) {
                if (!in_array($d['estado_cuadrilla'], ['en servicio', 'fuera de servicio'], true)) return self::falla('Estado de cuadrilla inválido.');
                $sub['cuadrilla_estado'] = ['estado', $d['estado_cuadrilla']];
            }
        }
        $jefeNuevo = null;
        if ($rol === 'administrador' && array_key_exists('jefe', $d)) {
            if (!in_array($d['jefe'], ['si', 'no'], true)) return self::falla('El campo jefe es "si" o "no".');
            if ($d['jefe'] !== (self::esJefe($id) ? 'si' : 'no')) {
                if (!self::esJefe($idAdmin)) return self::falla('Solo un administrador jefe puede cambiar la jefatura.', 403);
                if ($d['jefe'] === 'no' && self::esUltimoJefe($id)) return self::falla('No se puede quitar la jefatura al último administrador jefe activo.', 409);
                $jefeNuevo = $d['jefe'];
            }
        }

        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            if ($nombre !== null) $pdo->prepare('UPDATE usuario SET nombre = ? WHERE ID = ?')->execute([$nombre, $id]);
            if ($email !== null)  $pdo->prepare('UPDATE usuario SET email = ? WHERE ID = ?')->execute([$email, $id]);
            if ($pass !== null)   $pdo->prepare('UPDATE usuario SET `contrasena` = ? WHERE ID = ?')->execute([password_hash($pass, PASSWORD_DEFAULT), $id]);
            if ($tels !== null) {
                $pdo->prepare('DELETE FROM usuario_telefono WHERE ID_usuario = ?')->execute([$id]);
                foreach ($tels as $t) {
                    $pdo->prepare('INSERT INTO usuario_telefono (telefono, ID_usuario) VALUES (?, ?)')->execute([$t, $id]);
                }
            }
            if (isset($sub['operario'])) $pdo->prepare('UPDATE operario SET ID_instalacion = ? WHERE ID_usuario = ?')->execute([$sub['operario'][1], $id]);
            if (isset($sub['cuadrilla_camion'])) $pdo->prepare('UPDATE cuadrilla SET ID_camion = ? WHERE ID_usuario = ?')->execute([$sub['cuadrilla_camion'][1], $id]);
            if (isset($sub['cuadrilla_estado'])) $pdo->prepare('UPDATE cuadrilla SET estado = ? WHERE ID_usuario = ?')->execute([$sub['cuadrilla_estado'][1], $id]);
            if ($jefeNuevo !== null) {
                $pdo->prepare('UPDATE administrador SET jefe = ? WHERE ID_usuario = ?')->execute([$jefeNuevo, $id]);
                self::gestionar($pdo, $idAdmin, $id, 'modificacion');
            }
            $pdo->commit();
            return ['ok' => true];
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Aprueba una solicitud pendiente: activa la cuenta y deja constancia en `autorizacion`. */
    public static function aprobar(int $id, int $idAdmin): array
    {
        $u = self::porId($id);
        if ($u === null) return self::falla('Usuario inexistente.', 404);
        if ($u['estado'] === 'activo') return self::falla('La cuenta ya está activa.', 409);
        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE usuario SET estado='activo' WHERE ID = ?")->execute([$id]);
            self::auditar($pdo, $idAdmin, $id, 'aprobada');
            $pdo->commit();
            return ['ok' => true];
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Rechaza una solicitud pendiente (la cuenta queda 'no activo'). Para cuentas activas se usa baja(). */
    public static function rechazar(int $id, int $idAdmin): array
    {
        $u = self::porId($id);
        if ($u === null) return self::falla('Usuario inexistente.', 404);
        if ($u['estado'] === 'activo') return self::falla('La cuenta está activa: para desactivarla usá la baja.', 409);
        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            self::auditar($pdo, $idAdmin, $id, 'rechazada');
            $pdo->commit();
            return ['ok' => true];
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Baja lógica de una cuenta activa (no se borra: queda 'no activo' y auditada).
     * RN3: dar de baja a un administrador exige ser jefe. RN4: no se da de baja al último jefe activo.
     */
    public static function baja(int $id, int $idAdmin): array
    {
        $u = self::porId($id);
        if ($u === null) return self::falla('Usuario inexistente.', 404);
        if ($u['estado'] !== 'activo') return self::falla('La cuenta ya está dada de baja o pendiente.', 409);
        if ($id === $idAdmin) return self::falla('No podés darte de baja a vos mismo.', 409);
        $esAdmin = self::rolDe($id) === 'administrador';
        if ($esAdmin) {
            if (!self::esJefe($idAdmin)) return self::falla('Solo un administrador jefe puede dar de baja administradores.', 403);
            if (self::esUltimoJefe($id)) return self::falla('No se puede dar de baja al último administrador jefe activo.', 409);
        }
        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE usuario SET estado='no activo' WHERE ID = ?")->execute([$id]);
            self::auditar($pdo, $idAdmin, $id, 'baja');
            if ($esAdmin) self::gestionar($pdo, $idAdmin, $id, 'baja');
            $pdo->commit();
            return ['ok' => true];
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
