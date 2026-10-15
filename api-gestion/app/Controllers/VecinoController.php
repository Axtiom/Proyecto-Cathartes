<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Models/Incidencia.php';
require_once BASE_PATH . '/app/Models/Contenedor.php';

/**
 * Endpoints del universo Vecino (Fase 2). Todo protegido y SCOPEADO al vecino en
 * sesión: el ID sale del token, nunca del cliente, así un vecino solo ve/crea lo
 * suyo. Rol exigido: vecino.
 *
 *   GET  /vecino/metricas              -> {en_curso, finalizadas_anio}
 *   GET  /vecino/incidencias[?limite=N]-> lista de sus incidencias
 *   GET  /vecino/incidencia?id=N       -> detalle + intervención (línea de tiempo)
 *   POST /vecino/incidencias           -> alta (multipart, FOTO OBLIGATORIA)
 *   GET  /vecino/incidencia-foto?id=N  -> sirve el archivo de la foto
 */
class VecinoController extends Controller
{
    private const MAX_FOTO = 10 * 1024 * 1024; // 10 MB

    /**
     * Detecta el tipo de imagen por sus magic bytes (no por la extensión ni por
     * el Content-Type del cliente, que son falsificables). Se evita finfo a
     * propósito: en XAMPP Windows crashea el worker sobre uploads (HTTP 000).
     * @return 'png'|'jpg'|null
     */
    private static function detectarImagen(string $path): ?string
    {
        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            return null;
        }
        $bytes = (string) fread($fh, 8);
        fclose($fh);
        if (strncmp($bytes, "\x89PNG\r\n\x1a\n", 8) === 0) {
            return 'png';
        }
        if (strncmp($bytes, "\xFF\xD8\xFF", 3) === 0) {
            return 'jpg';
        }
        return null;
    }

    private function vecino(): array
    {
        $ctx = Auth::proteger();
        if ($ctx['rol'] !== 'vecino') {
            $this->json(['ok' => false, 'error' => 'Requiere rol Vecino'], 403);
            exit;
        }
        return $ctx;
    }

    public function metricas(): void
    {
        $v = $this->vecino();
        try {
            $this->json(['ok' => true, 'metricas' => Incidencia::metricas((int) $v['id'])]);
        } catch (Throwable $e) {
            error_log('[' . static::class . '] ' . $e->getMessage());
            $this->json(['ok' => false, 'error' => 'Error interno del servidor'], 500);
        }
    }

    public function incidencias(): void
    {
        $v = $this->vecino();
        $limite = isset($_GET['limite']) ? max(1, (int) $_GET['limite']) : null;
        try {
            $this->json(['ok' => true, 'items' => Incidencia::delVecino((int) $v['id'], $limite)]);
        } catch (Throwable $e) {
            error_log('[' . static::class . '] ' . $e->getMessage());
            $this->json(['ok' => false, 'error' => 'Error interno del servidor'], 500);
        }
    }

    public function incidencia(): void
    {
        $v = $this->vecino();
        $id = (int) ($_GET['id'] ?? 0);
        try {
            $inc = Incidencia::delVecinoPorId((int) $v['id'], $id);
            if ($inc === null) {
                $this->json(['ok' => false, 'error' => 'No encontrada'], 404);
                return;
            }
            $inc['intervencion'] = Incidencia::intervencionDe($id);
            $inc['tiene_foto']   = $inc['foto'] !== null;
            unset($inc['foto']); // el nombre de archivo no se expone; la foto va por su endpoint
            $this->json(['ok' => true, 'incidencia' => $inc]);
        } catch (Throwable $e) {
            error_log('[' . static::class . '] ' . $e->getMessage());
            $this->json(['ok' => false, 'error' => 'Error interno del servidor'], 500);
        }
    }

    // POST multipart: id_contenedor, tipo, descripcion (opcional), foto (obligatoria)
    public function reportar(): void
    {
        $v = $this->vecino();

        $idContenedor = (int) ($_POST['id_contenedor'] ?? 0);
        $tipo         = trim((string) ($_POST['tipo'] ?? ''));
        $descripcion  = trim((string) ($_POST['descripcion'] ?? ''));
        $descripcion  = $descripcion === '' ? null : $descripcion;

        if (!Incidencia::tipoValido($tipo)) {
            $this->json(['ok' => false, 'error' => 'Tipo de incidencia inválido.'], 400);
            return;
        }
        $cont = Contenedor::porId($idContenedor);
        if ($cont === null || $cont['estado'] === 'dado de baja') {
            $this->json(['ok' => false, 'error' => 'Contenedor inválido.'], 400);
            return;
        }

        // --- Foto obligatoria: validar ANTES de insertar ---
        $foto = $_FILES['foto'] ?? null;
        if (!is_array($foto) || ($foto['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $this->json(['ok' => false, 'error' => 'La foto es obligatoria.'], 400);
            return;
        }
        if (($foto['error'] ?? 1) !== UPLOAD_ERR_OK) {
            $this->json(['ok' => false, 'error' => 'No se pudo subir la foto.'], 400);
            return;
        }
        if ((int) $foto['size'] > self::MAX_FOTO) {
            $this->json(['ok' => false, 'error' => 'La foto supera los 10 MB.'], 400);
            return;
        }
        $ext = self::detectarImagen((string) $foto['tmp_name']);
        if ($ext === null) {
            $this->json(['ok' => false, 'error' => 'La foto debe ser PNG o JPG.'], 400);
            return;
        }

        try {
            $id = Incidencia::crear((int) $v['id'], $idContenedor, $tipo, $descripcion);

            $dir = (string) Config::get('uploads.incidencias', BASE_PATH . '/storage/uploads/incidencias');
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $nombre = $id . '.' . $ext;
            if (!move_uploaded_file($foto['tmp_name'], $dir . '/' . $nombre)) {
                throw new RuntimeException('No se pudo guardar la foto en disco.');
            }
            Incidencia::guardarFoto($id, $nombre);

            $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Incidencia reportada.'], 201);
        } catch (Throwable $e) {
            error_log('[' . static::class . '] ' . $e->getMessage());
            $this->json(['ok' => false, 'error' => 'Error interno del servidor'], 500);
        }
    }

    // Sirve el archivo de la foto (bytes crudos, no JSON). Scopeado al vecino.
    public function foto(): void
    {
        $v = $this->vecino();
        $id = (int) ($_GET['id'] ?? 0);
        $inc = Incidencia::delVecinoPorId((int) $v['id'], $id);
        if ($inc === null || $inc['foto'] === null) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Sin foto'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $dir  = (string) Config::get('uploads.incidencias', BASE_PATH . '/storage/uploads/incidencias');
        $ruta = $dir . '/' . $inc['foto'];
        if (!is_file($ruta)) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Archivo no encontrado'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
        header('Content-Type: ' . ($ext === 'png' ? 'image/png' : 'image/jpeg'));
        header('Content-Length: ' . filesize($ruta));
        header('Cache-Control: private, max-age=3600');
        readfile($ruta);
    }
}
