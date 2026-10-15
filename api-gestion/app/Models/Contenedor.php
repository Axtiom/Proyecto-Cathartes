<?php
declare(strict_types=1);

/**
 * Model de Contenedores de la API de Gestión (PDO directo a `cathartes`).
 *
 * Esta API es de solo lectura pública/vecino (mapa, selector de /reportar,
 * estadísticas). El ABM completo (alta, baja, verificación de estado) vive en
 * el Contenedor del Backoffice — apps separadas, cada una con el modelo que
 * le corresponde a su tarea.
 */
class Contenedor
{
    public const TIPOS   = ['basura', 'reciclable'];
    public const ESTADOS = ['funcional', 'no funcional', 'desbordado']; // operativos (sin baja)

    /** Rutas disponibles (el alta de un contenedor exige elegir una: ID_ruta es NOT NULL). */
    public static function rutas(): array
    {
        return Database::conexion()->query('SELECT ID AS id, nombre, zona FROM ruta ORDER BY zona, nombre')->fetchAll();
    }

    /**
     * Registra un contenedor. Misma validación que el ABM del backoffice.
     * @return array{ok:bool,error?:string,id?:int}
     */
    public static function crear(array $d): array
    {
        $v = self::validar($d);
        if ($v !== null) return ['ok' => false, 'error' => $v];
        $pdo = Database::conexion();
        $pdo->prepare(
            'INSERT INTO contenedor (ubicacion, latitud, longitud, tipo, estado, ID_ruta) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            trim((string) $d['ubicacion']), self::coord($d['latitud'] ?? ''), self::coord($d['longitud'] ?? ''),
            $d['tipo'], $d['estado'] ?? 'funcional', (int) $d['id_ruta'],
        ]);
        return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
    }

    /** Un contenedor con todos sus campos (incluye baja y id de ruta). */
    public static function completo(int $id): ?array
    {
        $st = Database::conexion()->prepare(
            'SELECT c.ID, c.ubicacion, c.latitud, c.longitud, c.tipo, c.estado, c.fecha_baja, c.motivo_baja,
                    c.ID_ruta, r.nombre AS ruta
             FROM contenedor c JOIN ruta r ON r.ID = c.ID_ruta WHERE c.ID = ?'
        );
        $st->execute([$id]);
        $f = $st->fetch();
        if (!$f) return null;
        return [
            'id' => (int) $f['ID'], 'ubicacion' => (string) $f['ubicacion'],
            'latitud' => $f['latitud'] !== null ? (float) $f['latitud'] : null,
            'longitud' => $f['longitud'] !== null ? (float) $f['longitud'] : null,
            'tipo' => (string) $f['tipo'], 'estado' => (string) $f['estado'],
            'id_ruta' => (int) $f['ID_ruta'], 'ruta' => (string) $f['ruta'],
            'fecha_baja' => $f['fecha_baja'], 'motivo_baja' => $f['motivo_baja'],
        ];
    }

    /**
     * Edita ubicación, coordenadas, tipo y estado. NO cambia la ruta: eso se decide
     * desde la ruta (misma regla que el backoffice, para que dos pantallas no
     * peleen por el mismo campo). Un contenedor dado de baja no se edita.
     * @return array{ok:bool,error?:string,codigo?:int}
     */
    public static function actualizar(int $id, array $d): array
    {
        $c = self::completo($id);
        if ($c === null) return ['ok' => false, 'error' => 'Contenedor inexistente.', 'codigo' => 404];
        if ($c['estado'] === 'dado de baja') return ['ok' => false, 'error' => 'El contenedor está dado de baja y no se puede editar.', 'codigo' => 409];
        // PUT parcial: lo que no viene se conserva.
        $d = array_merge([
            'ubicacion' => $c['ubicacion'], 'latitud' => $c['latitud'], 'longitud' => $c['longitud'],
            'tipo' => $c['tipo'], 'estado' => $c['estado'], 'id_ruta' => $c['id_ruta'],
        ], $d);
        $v = self::validar($d);
        if ($v !== null) return ['ok' => false, 'error' => $v];
        Database::conexion()->prepare(
            'UPDATE contenedor SET ubicacion = ?, latitud = ?, longitud = ?, tipo = ?, estado = ? WHERE ID = ?'
        )->execute([
            trim((string) $d['ubicacion']), self::coord($d['latitud'] ?? ''), self::coord($d['longitud'] ?? ''),
            $d['tipo'], $d['estado'], $id,
        ]);
        return ['ok' => true];
    }

    /** Baja lógica: no borra; marca 'dado de baja' con fecha y motivo obligatorio. */
    public static function baja(int $id, string $motivo): array
    {
        $c = self::completo($id);
        if ($c === null) return ['ok' => false, 'error' => 'Contenedor inexistente.', 'codigo' => 404];
        if ($c['estado'] === 'dado de baja') return ['ok' => false, 'error' => 'El contenedor ya está dado de baja.', 'codigo' => 409];
        $motivo = trim($motivo);
        if ($motivo === '') return ['ok' => false, 'error' => 'El motivo de baja es obligatorio.'];
        if (mb_strlen($motivo) > 255) return ['ok' => false, 'error' => 'El motivo admite hasta 255 caracteres.'];
        Database::conexion()->prepare(
            "UPDATE contenedor SET estado='dado de baja', fecha_baja=NOW(), motivo_baja=? WHERE ID=?"
        )->execute([$motivo, $id]);
        return ['ok' => true];
    }

    private static function validar(array $d): ?string
    {
        if (trim((string) ($d['ubicacion'] ?? '')) === '') return 'La ubicación es obligatoria.';
        if (mb_strlen(trim((string) $d['ubicacion'])) > 200) return 'La ubicación admite hasta 200 caracteres.';
        if (!in_array($d['tipo'] ?? '', self::TIPOS, true)) return 'Tipo de residuo inválido (basura o reciclable).';
        if (!in_array($d['estado'] ?? 'funcional', self::ESTADOS, true)) return 'Estado inválido.';
        if (!coordenada_valida($d['latitud'] ?? '', 90))   return 'La latitud tiene que ser un número entre -90 y 90, o quedar vacía.';
        if (!coordenada_valida($d['longitud'] ?? '', 180)) return 'La longitud tiene que ser un número entre -180 y 180, o quedar vacía.';
        if (!entero_en_rango($d['id_ruta'] ?? '', 1)) return 'La ruta es obligatoria.';
        $st = Database::conexion()->prepare('SELECT 1 FROM ruta WHERE ID = ?');
        $st->execute([(int) $d['id_ruta']]);
        if (!$st->fetchColumn()) return 'La ruta no existe.';
        return null;
    }

    /** Coordenada opcional: '' -> null; si no, número. */
    private static function coord(mixed $v): ?float
    {
        $v = trim((string) $v);
        return ($v === '' || !is_numeric($v)) ? null : (float) $v;
    }

    /**
     * Contenedores activos (todo lo que no está dado de baja). Para el selector
     * de /reportar y la lista de /inicio del vecino. Incluye la ruta.
     * @return list<array>
     */
    public static function activos(): array
    {
        $sql = "SELECT c.ID AS id, c.ubicacion, c.latitud, c.longitud, c.tipo, c.estado, r.nombre AS ruta
                FROM contenedor c
                JOIN ruta r ON r.ID = c.ID_ruta
                WHERE c.estado <> 'dado de baja'
                ORDER BY c.ID";
        return self::mapear(Database::conexion()->query($sql)->fetchAll());
    }

    /** Todos los contenedores (para el mapa; incluye dados de baja). @return list<array> */
    public static function todos(): array
    {
        $sql = "SELECT c.ID AS id, c.ubicacion, c.latitud, c.longitud, c.tipo, c.estado, r.nombre AS ruta
                FROM contenedor c
                JOIN ruta r ON r.ID = c.ID_ruta
                ORDER BY c.ID";
        return self::mapear(Database::conexion()->query($sql)->fetchAll());
    }

    /** Un contenedor, en el formato liviano que usan el vecino y el mapa. */
    public static function porId(int $id): ?array
    {
        $st = Database::conexion()->prepare(
            "SELECT c.ID AS id, c.ubicacion, c.latitud, c.longitud, c.tipo, c.estado, r.nombre AS ruta
             FROM contenedor c JOIN ruta r ON r.ID = c.ID_ruta WHERE c.ID = ?"
        );
        $st->execute([$id]);
        $fila = $st->fetch();
        return $fila ? self::una($fila) : null;
    }

    /** @return list<array> */
    private static function mapear(array $filas): array
    {
        return array_map([self::class, 'una'], $filas);
    }

    private static function una(array $f): array
    {
        return [
            'id'        => (int) $f['id'],
            'ubicacion' => (string) $f['ubicacion'],
            'latitud'   => $f['latitud']  !== null ? (float) $f['latitud']  : null,
            'longitud'  => $f['longitud'] !== null ? (float) $f['longitud'] : null,
            'tipo'      => (string) $f['tipo'],
            'estado'    => (string) $f['estado'],
            'ruta'      => (string) $f['ruta'],
        ];
    }
}
