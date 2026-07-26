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

    public static function porEmail(string $email): ?array
    {
        $st = Database::conexion()->prepare('SELECT * FROM usuario WHERE email = ?');
        $st->execute([$email]);
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
                    $pdo->prepare('INSERT INTO vecino (ID_usuario, saldo) VALUES (?, 0)')->execute([$id]);
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
    private static function auditar(PDO $pdo, int $idAdmin, int $idUsuario, string $accion): void
    {
        $st = $pdo->prepare('INSERT INTO autorizacion (ID_admin, ID_usuario, fecha, accion) VALUES (?, ?, NOW(), ?)');
        $st->execute([$idAdmin, $idUsuario, $accion]);
    }

    /** Auditoría reflexiva admin↔admin (creación/baja de administradores). */
    private static function gestionar(PDO $pdo, int $ejecutor, int $afectado, string $accion): void
    {
        $st = $pdo->prepare('INSERT INTO gestiona (ID_ejecutor, ID_afectado, fecha, accion) VALUES (?, ?, NOW(), ?)');
        $st->execute([$ejecutor, $afectado, $accion]);
    }
}
