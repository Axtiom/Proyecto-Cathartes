<?php
declare(strict_types=1);

/**
 * Model de Usuarios del Backoffice (PDO directo a `cathartes`).
 *
 * Recordá (BUG-003): la columna de contraseña es `contrasena`, sin ñ. En PDO va
 * con backticks. El rol NO es columna: se deduce de en qué tabla de subtipo está
 * el ID (administrador > operario > cuadrilla > vecino).
 *
 * Las pestañas de la pantalla se apoyan en la tabla `autorizacion` (auditoría de
 * aprobaciones/rechazos/bajas del admin):
 *   - Pendiente     = estado 'no activo' y SIN registro en autorizacion.
 *   - Dado de baja  = estado 'no activo' y CON registro en autorizacion
 *                     (rechazado o dado de baja alguna vez).
 *   - Activo        = estado 'activo'.
 */
class Usuario
{
    private const POR_PAGINA = 25;

    // ------------------------------------------------------------ básicos ----
    public static function porId(int $id): ?array
    {
        $st = Database::conexion()->prepare('SELECT * FROM usuario WHERE ID = ?');
        $st->execute([$id]);
        $u = $st->fetch();
        return $u ?: null;
    }

    public static function esAdmin(int $id): bool
    {
        $st = Database::conexion()->prepare('SELECT 1 FROM administrador WHERE ID_usuario = ? LIMIT 1');
        $st->execute([$id]);
        return (bool) $st->fetchColumn();
    }

    /** Rol deducido del subtipo (mismo orden que la API de Usuarios). */
    public static function rolDe(int $id): string
    {
        $pdo = Database::conexion();
        foreach (['administrador', 'operario', 'cuadrilla', 'vecino'] as $rol) {
            $st = $pdo->prepare("SELECT 1 FROM {$rol} WHERE ID_usuario = ? LIMIT 1");
            $st->execute([$id]);
            if ($st->fetchColumn()) {
                return $rol;
            }
        }
        return 'sin rol';
    }

    // ------------------------------------------------------------ listado ----
    /**
     * @param array{pestana?:string,rol?:string,q?:string,pagina?:int} $opts
     * @return array{items:array,total:int,pagina:int,paginas:int,porPagina:int}
     */
    public static function listar(array $opts): array
    {
        $pestana = $opts['pestana'] ?? 'pendientes';
        $rol     = $opts['rol'] ?? '';
        $q       = trim((string) ($opts['q'] ?? ''));
        $pagina  = max(1, (int) ($opts['pagina'] ?? 1));

        $where  = [];
        $params = [];

        // Pestaña
        if ($pestana === 'activos') {
            $where[] = "u.estado = 'activo'";
        } elseif ($pestana === 'pendientes') {
            $where[] = "u.estado = 'no activo' AND NOT EXISTS (SELECT 1 FROM autorizacion z WHERE z.ID_usuario = u.ID)";
        } elseif ($pestana === 'baja') {
            $where[] = "u.estado = 'no activo' AND EXISTS (SELECT 1 FROM autorizacion z WHERE z.ID_usuario = u.ID)";
        } // 'todos' => sin filtro de estado

        // Filtro por rol (vía existencia en la tabla de subtipo)
        $subtipos = ['administrador', 'operario', 'cuadrilla', 'vecino'];
        if (in_array($rol, $subtipos, true)) {
            $where[] = "EXISTS (SELECT 1 FROM {$rol} sr WHERE sr.ID_usuario = u.ID)";
        }

        // Buscador (nombre o email)
        if ($q !== '') {
            $where[] = '(u.nombre LIKE ? OR u.email LIKE ?)';
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $pdo = Database::conexion();

        // Total
        $stTot = $pdo->prepare("SELECT COUNT(*) FROM usuario u {$sqlWhere}");
        $stTot->execute($params);
        $total = (int) $stTot->fetchColumn();

        $paginas = max(1, (int) ceil($total / self::POR_PAGINA));
        if ($pagina > $paginas) {
            $pagina = $paginas;
        }
        $offset = ($pagina - 1) * self::POR_PAGINA;

        // Página (rol deducido con LEFT JOINs; estado de cuenta y operativo)
        $sql = "SELECT u.ID, u.nombre, u.email, u.estado,
                       CASE
                         WHEN a.ID_usuario IS NOT NULL THEN 'administrador'
                         WHEN o.ID_usuario IS NOT NULL THEN 'operario'
                         WHEN c.ID_usuario IS NOT NULL THEN 'cuadrilla'
                         WHEN v.ID_usuario IS NOT NULL THEN 'vecino'
                         ELSE 'sin rol'
                       END AS rol,
                       c.estado AS estado_operativo
                FROM usuario u
                LEFT JOIN administrador a ON a.ID_usuario = u.ID
                LEFT JOIN operario      o ON o.ID_usuario = u.ID
                LEFT JOIN cuadrilla     c ON c.ID_usuario = u.ID
                LEFT JOIN vecino        v ON v.ID_usuario = u.ID
                {$sqlWhere}
                ORDER BY u.ID ASC
                LIMIT {$offset}, " . self::POR_PAGINA;
        $st = $pdo->prepare($sql);
        $st->execute($params);

        return [
            'items'     => $st->fetchAll(),
            'total'     => $total,
            'pagina'    => $pagina,
            'paginas'   => $paginas,
            'porPagina' => self::POR_PAGINA,
        ];
    }

    /** Conteo por pestaña, para los badges de las solapas. */
    public static function conteos(): array
    {
        $pdo = Database::conexion();
        $pend = (int) $pdo->query("SELECT COUNT(*) FROM usuario u WHERE u.estado='no activo' AND NOT EXISTS (SELECT 1 FROM autorizacion z WHERE z.ID_usuario=u.ID)")->fetchColumn();
        $act  = (int) $pdo->query("SELECT COUNT(*) FROM usuario WHERE estado='activo'")->fetchColumn();
        $baja = (int) $pdo->query("SELECT COUNT(*) FROM usuario u WHERE u.estado='no activo' AND EXISTS (SELECT 1 FROM autorizacion z WHERE z.ID_usuario=u.ID)")->fetchColumn();
        $tot  = (int) $pdo->query("SELECT COUNT(*) FROM usuario")->fetchColumn();
        return ['pendientes' => $pend, 'activos' => $act, 'baja' => $baja, 'todos' => $tot];
    }

    // ------------------------------------------------------------ acciones ----
    /** Aprueba una solicitud: activa la cuenta y audita en `autorizacion`. */
    public static function aprobar(int $id, int $idAdmin): void
    {
        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare("UPDATE usuario SET estado='activo' WHERE ID = ? AND estado='no activo'");
            $st->execute([$id]);
            self::auditar($pdo, $idAdmin, $id, 'aprobada');
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Rechaza/da de baja: deja la cuenta 'no activo' y audita en `autorizacion`. */
    public static function rechazar(int $id, int $idAdmin): void
    {
        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare("UPDATE usuario SET estado='no activo' WHERE ID = ?");
            $st->execute([$id]);
            self::auditar($pdo, $idAdmin, $id, 'rechazada');
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Da de baja a un administrador: 'no activo' + audita en autorizacion Y gestiona.
     *  Las validaciones de permiso (jefe) y "último jefe" van en el Controller. */
    public static function bajaAdministrador(int $id, int $idAdmin): void
    {
        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE usuario SET estado='no activo' WHERE ID = ?")->execute([$id]);
            self::auditar($pdo, $idAdmin, $id, 'baja');
            self::gestionar($pdo, $idAdmin, $id, 'baja');
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // ------------------------------------------------------------ combos ----
    public static function esJefe(int $id): bool
    {
        $st = Database::conexion()->prepare("SELECT 1 FROM administrador WHERE ID_usuario = ? AND jefe = 'si' LIMIT 1");
        $st->execute([$id]);
        return (bool) $st->fetchColumn();
    }

    public static function instalaciones(): array
    {
        return Database::conexion()->query('SELECT ID, nombre FROM instalacion ORDER BY nombre')->fetchAll();
    }

    /** Camiones asignables: no dados de baja y sin cuadrilla (ID_camion es UNIQUE). */
    public static function camionesLibres(): array
    {
        return Database::conexion()->query(
            "SELECT ID, matricula, modelo FROM camion
             WHERE estado <> 'dado de baja'
               AND ID NOT IN (SELECT ID_camion FROM cuadrilla WHERE ID_camion IS NOT NULL)
             ORDER BY matricula"
        )->fetchAll();
    }

    /** ¿Es el único administrador con jefe='si' y estado='activo'? (tapiado). */
    public static function esUltimoJefe(int $id): bool
    {
        $pdo = Database::conexion();
        $n = (int) $pdo->query(
            "SELECT COUNT(*) FROM administrador a JOIN usuario u ON u.ID = a.ID_usuario
             WHERE a.jefe = 'si' AND u.estado = 'activo'"
        )->fetchColumn();
        $st = $pdo->prepare("SELECT 1 FROM administrador WHERE ID_usuario = ? AND jefe = 'si'");
        $st->execute([$id]);
        return $n <= 1 && (bool) $st->fetchColumn();
    }

    // ------------------------------------------------------------- alta ----
    /**
     * Alta multirol por el administrador. $datos: rol, nombre, apellido, telefono,
     * email, password, y según el rol id_instalacion (operario) / id_camion
     * (cuadrilla) / jefe (administrador). El usuario entra estado='activo'.
     * La autorización de crear administrador (jefe='si') se valida en el Controller.
     * @return array{ok:bool,error?:string,id?:int,rol?:string}
     */
    public static function crear(array $datos, int $idAdmin): array
    {
        $rol = (string) ($datos['rol'] ?? '');
        if (!in_array($rol, ['vecino', 'operario', 'cuadrilla', 'administrador'], true)) {
            return ['ok' => false, 'error' => 'Rol inválido.'];
        }

        $nombre = trim((string) ($datos['nombre'] ?? '') . ' ' . (string) ($datos['apellido'] ?? ''));
        $email  = trim((string) ($datos['email'] ?? ''));
        $tel    = trim((string) ($datos['telefono'] ?? ''));
        $pass   = (string) ($datos['password'] ?? '');

        if ($nombre === '' || $email === '' || $tel === '' || strlen($pass) < 8) {
            return ['ok' => false, 'error' => 'Completá nombre, teléfono, email y una contraseña de al menos 8 caracteres.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'El email no es válido.'];
        }
        if (self::porEmail($email) !== null) {
            return ['ok' => false, 'error' => 'Ya existe un usuario con ese email.'];
        }

        // Aislamiento por rol. Cada campo de subtipo pertenece a un solo rol; si
        // llega con valor y no es el rol elegido, es un error y se corta acá — no
        // se ignora en silencio. El panel no debería dejar mandarlo (el JS oculta
        // lo que no corresponde), pero el límite duro va en el servidor: ocultar
        // un <select> es UX, no autorización. Ver BUG-010.
        $duenoDe = ['id_instalacion' => 'operario', 'id_camion' => 'cuadrilla', 'jefe' => 'administrador'];
        $neutro  = ['id_instalacion' => ['', '0'], 'id_camion' => ['', '0'], 'jefe' => ['', 'no']];
        foreach ($duenoDe as $campo => $dueno) {
            $valor = trim((string) ($datos[$campo] ?? ''));
            if (in_array($valor, $neutro[$campo], true)) {
                continue; // vino vacío / en su valor por defecto: no asigna nada
            }
            if ($rol !== $dueno) {
                return ['ok' => false, 'error' => 'Un ' . $rol . ' no puede recibir el campo "' . $campo . '" (es de ' . $dueno . ').'];
            }
        }

        // Validaciones de subtipo ANTES de tocar la base (no reventar contra la FK).
        // Los IDs se leen solo para el rol dueño; para el resto quedan en 0/'no'.
        $idInst = $rol === 'operario'  ? (int) ($datos['id_instalacion'] ?? 0) : 0;
        $idCam  = $rol === 'cuadrilla' ? (int) ($datos['id_camion'] ?? 0)      : 0;
        if ($rol === 'operario' && $idInst <= 0) {
            return ['ok' => false, 'error' => 'El operario necesita una instalación.'];
        }
        if ($rol === 'cuadrilla' && $idCam > 0) {
            $chk = Database::conexion()->prepare('SELECT 1 FROM cuadrilla WHERE ID_camion = ? LIMIT 1');
            $chk->execute([$idCam]);
            if ($chk->fetchColumn()) {
                return ['ok' => false, 'error' => 'Ese camión ya está asignado a otra cuadrilla.'];
            }
        }
        $jefe = ($rol === 'administrador' && ($datos['jefe'] ?? '') === 'si') ? 'si' : 'no';

        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO usuario (nombre, email, `contrasena`, estado) VALUES (?, ?, ?, "activo")')
                ->execute([$nombre, $email, password_hash($pass, PASSWORD_DEFAULT)]);
            $id = (int) $pdo->lastInsertId();

            // Teléfono (obligatorio: sin él desaparece de listados con JOIN).
            $pdo->prepare('INSERT INTO usuario_telefono (telefono, ID_usuario) VALUES (?, ?)')->execute([$tel, $id]);

            switch ($rol) {
                case 'vecino':
                    $pdo->prepare('INSERT INTO vecino (ID_usuario) VALUES (?)')->execute([$id]);
                    break;
                case 'operario':
                    $pdo->prepare('INSERT INTO operario (ID_usuario, ID_instalacion) VALUES (?, ?)')->execute([$id, $idInst]);
                    break;
                case 'cuadrilla':
                    $pdo->prepare('INSERT INTO cuadrilla (ID_usuario, ID_camion, estado) VALUES (?, ?, "en servicio")')
                        ->execute([$id, $idCam > 0 ? $idCam : null]);
                    break;
                case 'administrador':
                    $pdo->prepare('INSERT INTO administrador (ID_usuario, jefe) VALUES (?, ?)')->execute([$id, $jefe]);
                    break;
            }

            // Auditoría: quién lo dio de alta (autorizacion). Los admins además en gestiona.
            self::auditar($pdo, $idAdmin, $id, 'aprobada');
            if ($rol === 'administrador') {
                self::gestionar($pdo, $idAdmin, $id, 'alta');
            }

            $pdo->commit();
            return ['ok' => true, 'id' => $id, 'rol' => $rol];
        } catch (Throwable $e) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'No se pudo crear el usuario.'];
        }
    }

    // --------------------------------------------------------- auditoría ----
    /**
     * `autorizacion` y `gestiona` tienen PK (admin, cuenta, fecha) con fecha al SEGUNDO: dos
     * acciones del mismo administrador sobre la misma cuenta dentro del mismo segundo chocaban
     * con la PK (error 1062) y daban un error 500. Se reintenta corriendo la fecha un segundo.
     */
    private static function insertarAuditoria(PDO $pdo, string $tabla, int $a, int $b, string $accion): void
    {
        [$colA, $colB] = $tabla === 'autorizacion' ? ['ID_admin', 'ID_usuario'] : ['ID_ejecutor', 'ID_afectado'];
        $sql = "INSERT INTO {$tabla} ({$colA}, {$colB}, fecha, accion) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), ?)";
        for ($desfase = 0; $desfase < 10; $desfase++) {
            try {
                $pdo->prepare($sql)->execute([$a, $b, $desfase, $accion]);
                return;
            } catch (PDOException $e) {
                if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
                    throw $e;
                }
            }
        }
        throw new RuntimeException("No se pudo registrar la auditoría en {$tabla}.");
    }

    private static function auditar(PDO $pdo, int $idAdmin, int $idUsuario, string $accion): void
    {
        self::insertarAuditoria($pdo, 'autorizacion', $idAdmin, $idUsuario, $accion);
    }

    /** Auditoría reflexiva admin↔admin (creación/baja/modificación de administradores). */
    private static function gestionar(PDO $pdo, int $ejecutor, int $afectado, string $accion): void
    {
        self::insertarAuditoria($pdo, 'gestiona', $ejecutor, $afectado, $accion);
    }

    // ------------------------------------------------------------ edición ----
    /** Una cuenta con sus teléfonos y los datos de su subtipo (para la pantalla de edición). */
    public static function detalle(int $id): ?array
    {
        $u = self::porId($id);
        if ($u === null) {
            return null;
        }
        $pdo = Database::conexion();
        $u['rol'] = self::rolDe($id);
        $st = $pdo->prepare('SELECT telefono FROM usuario_telefono WHERE ID_usuario = ? ORDER BY id_tel');
        $st->execute([$id]);
        $u['telefonos'] = $st->fetchAll(PDO::FETCH_COLUMN);
        $u['id_instalacion'] = null;
        $u['id_camion'] = null;
        $u['estado_cuadrilla'] = null;
        $u['jefe'] = null;
        if ($u['rol'] === 'operario') {
            $st = $pdo->prepare('SELECT ID_instalacion FROM operario WHERE ID_usuario = ?');
            $st->execute([$id]);
            $u['id_instalacion'] = (int) $st->fetchColumn();
        } elseif ($u['rol'] === 'cuadrilla') {
            $st = $pdo->prepare('SELECT ID_camion, estado FROM cuadrilla WHERE ID_usuario = ?');
            $st->execute([$id]);
            $f = $st->fetch();
            $u['id_camion'] = $f['ID_camion'] !== null ? (int) $f['ID_camion'] : null;
            $u['estado_cuadrilla'] = $f['estado'];
        } elseif ($u['rol'] === 'administrador') {
            $st = $pdo->prepare('SELECT jefe FROM administrador WHERE ID_usuario = ?');
            $st->execute([$id]);
            $u['jefe'] = $st->fetchColumn();
        }
        return $u;
    }

    /** Camiones que se le pueden asignar a una cuadrilla: los libres más el que ya tiene. */
    public static function camionesPara(int $idUsuario): array
    {
        $st = Database::conexion()->prepare(
            "SELECT ID, matricula, modelo FROM camion
             WHERE estado <> 'dado de baja'
               AND (ID NOT IN (SELECT ID_camion FROM cuadrilla WHERE ID_camion IS NOT NULL)
                    OR ID IN (SELECT ID_camion FROM cuadrilla WHERE ID_usuario = ?))
             ORDER BY matricula"
        );
        $st->execute([$idUsuario]);
        return $st->fetchAll();
    }

    private static function falla(string $mensaje, int $codigo = 400): array
    {
        return ['ok' => false, 'error' => $mensaje, 'codigo' => $codigo];
    }

    /**
     * Edita una cuenta (parcial: solo cambia lo que viene en $d). El rol no se cambia.
     * Mismas reglas que PUT /usuarios de api-usuarios: un administrador solo lo edita un
     * jefe o él mismo (RN3); la jefatura la cambia solo un jefe y nunca se deja sin jefe (RN4).
     * Claves de $d: nombre, email, password, telefonos (lista), id_instalacion, id_camion,
     * estado_cuadrilla, jefe.
     * @return array{ok:bool,error?:string,codigo?:int}
     */
    public static function actualizar(int $id, array $d, int $idAdmin): array
    {
        $u = self::detalle($id);
        if ($u === null) return self::falla('Usuario inexistente.', 404);
        $rol = $u['rol'];
        if ($rol === 'administrador' && $id !== $idAdmin && !self::esJefe($idAdmin)) {
            return self::falla('Solo un administrador jefe puede modificar a otros administradores.', 403);
        }
        $pdo = Database::conexion();

        $nombre = array_key_exists('nombre', $d) ? trim((string) $d['nombre']) : null;
        $email  = array_key_exists('email', $d) ? trim((string) $d['email']) : null;
        $pass   = array_key_exists('password', $d) ? (string) $d['password'] : null;
        if ($nombre !== null && ($nombre === '' || mb_strlen($nombre) > 100)) return self::falla('El nombre es obligatorio (hasta 100 caracteres).');
        if ($email !== null) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) return self::falla('El correo no es válido.');
            $st = $pdo->prepare('SELECT 1 FROM usuario WHERE email = ? AND ID <> ?');
            $st->execute([$email, $id]);
            if ($st->fetchColumn()) return self::falla('Ya existe un usuario con ese correo.', 409);
        }
        if ($pass !== null && strlen($pass) < 8) return self::falla('La contraseña debe tener al menos 8 caracteres.');

        $tels = null;
        if (array_key_exists('telefonos', $d)) {
            $tels = array_values(array_filter(array_map(fn ($x) => trim((string) $x), (array) $d['telefonos']), fn ($x) => $x !== ''));
            if (!$tels) return self::falla('Ingresá al menos un teléfono.');
            foreach ($tels as $t) {
                if (!preg_match('/^[0-9 +()\-]{6,20}$/', $t)) return self::falla('El teléfono "' . $t . '" no es válido (solo números, espacios, + ( ) -).');
            }
        }

        $camion = $estadoCuad = $instalacion = $jefeNuevo = null;
        $cambiaCamion = false;
        if ($rol === 'operario' && array_key_exists('id_instalacion', $d)) {
            $instalacion = (int) $d['id_instalacion'];
            $st = $pdo->prepare('SELECT 1 FROM instalacion WHERE ID = ?');
            $st->execute([$instalacion]);
            if (!$st->fetchColumn()) return self::falla('La instalación no existe.');
        }
        if ($rol === 'cuadrilla') {
            if (array_key_exists('id_camion', $d)) {
                $cambiaCamion = true;
                $camion = (int) $d['id_camion'] > 0 ? (int) $d['id_camion'] : null;
                if ($camion !== null) {
                    $st = $pdo->prepare('SELECT estado FROM camion WHERE ID = ?');
                    $st->execute([$camion]);
                    $est = $st->fetchColumn();
                    if ($est === false) return self::falla('El camión no existe.');
                    if ($est === 'dado de baja') return self::falla('El camión está dado de baja.', 409);
                    $st = $pdo->prepare('SELECT 1 FROM cuadrilla WHERE ID_camion = ? AND ID_usuario <> ?');
                    $st->execute([$camion, $id]);
                    if ($st->fetchColumn()) return self::falla('Ese camión ya está asignado a otra cuadrilla.', 409);
                }
            }
            if (array_key_exists('estado_cuadrilla', $d)) {
                if (!in_array($d['estado_cuadrilla'], ['en servicio', 'fuera de servicio'], true)) return self::falla('Estado de cuadrilla inválido.');
                $estadoCuad = $d['estado_cuadrilla'];
            }
        }
        if ($rol === 'administrador' && array_key_exists('jefe', $d)) {
            if (!in_array($d['jefe'], ['si', 'no'], true)) return self::falla('El campo jefe es "si" o "no".');
            if ($d['jefe'] !== $u['jefe']) {
                if (!self::esJefe($idAdmin)) return self::falla('Solo un administrador jefe puede cambiar la jefatura.', 403);
                if ($d['jefe'] === 'no' && self::esUltimoJefe($id)) return self::falla('No se puede quitar la jefatura al último administrador jefe activo.', 409);
                $jefeNuevo = $d['jefe'];
            }
        }

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
            if ($instalacion !== null) $pdo->prepare('UPDATE operario SET ID_instalacion = ? WHERE ID_usuario = ?')->execute([$instalacion, $id]);
            if ($cambiaCamion)         $pdo->prepare('UPDATE cuadrilla SET ID_camion = ? WHERE ID_usuario = ?')->execute([$camion, $id]);
            if ($estadoCuad !== null)  $pdo->prepare('UPDATE cuadrilla SET estado = ? WHERE ID_usuario = ?')->execute([$estadoCuad, $id]);
            if ($jefeNuevo !== null) {
                $pdo->prepare('UPDATE administrador SET jefe = ? WHERE ID_usuario = ?')->execute([$jefeNuevo, $id]);
                self::gestionar($pdo, $idAdmin, $id, 'modificacion');
            }
            $pdo->commit();
            return ['ok' => true];
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('[backoffice] Usuario::actualizar: ' . $e->getMessage());
            return self::falla('No se pudo guardar los cambios.', 500);
        }
    }
}
