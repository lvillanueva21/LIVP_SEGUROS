<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/session_guard.php';
require_once __DIR__ . '/../../includes/conexion_cliente.php';
require_once __DIR__ . '/../../includes/request_cliente.php';
require_once __DIR__ . '/../../includes/autorizacion_cliente.php';
require_once __DIR__ . '/../../includes/almacen_core.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

function fmt_db(): PDO
{
    try {
        return cb_cliente_db_required();
    } catch (Throwable $e) {
        fmt_json_error('No se pudo conectar con la base de datos local.', 500);
    }
}

function fmt_json_success(array $data = [], string $message = 'Operacion realizada correctamente.'): void
{
    echo json_encode([
        'ok' => true,
        'message' => $message,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fmt_json_error(string $message, int $status = 400, array $errors = []): void
{
    http_response_code($status);
    echo json_encode([
        'ok' => false,
        'message' => $message,
        'errors' => $errors,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fmt_require_method(string $method): void
{
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== strtoupper($method)) {
        fmt_json_error('Metodo no permitido.', 405);
    }
}

function fmt_payload(): array
{
    $payload = cb_request_payload();
    return is_array($payload) ? $payload : [];
}

function fmt_require_perm(string $permiso): void
{
    if (!cb_cliente_puede('formatos_tipo', $permiso)) {
        fmt_json_error('No tienes permisos para realizar esta accion.', 403);
    }
}

function fmt_require_change(string $permiso): array
{
    fmt_require_method('POST');
    fmt_require_perm($permiso);
    $payload = fmt_payload();
    $token = cb_extract_csrf_token($payload);
    if (!cb_validate_local_csrf('formatos_tipo', $token)) {
        fmt_json_error('La sesion expiro o el formulario no es valido. Vuelve a intentarlo.', 419);
    }
    return $payload;
}

function fmt_user_id(): ?int
{
    $id = cb_cliente_usuario_externo_id();
    return $id > 0 ? $id : null;
}

function fmt_now(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('America/Lima')))->format('Y-m-d H:i:s');
}

function fmt_str(array $data, string $key, int $max, bool $nullable = true): ?string
{
    $value = trim((string) ($data[$key] ?? ''));
    if ($value === '') {
        return $nullable ? null : '';
    }
    return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
}

function fmt_estado(array $data): int
{
    return ((string) ($data['estado'] ?? '1') === '0') ? 0 : 1;
}

function fmt_page_params(): array
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = 10;
    return [$page, $perPage, ($page - 1) * $perPage];
}

function fmt_bind_like(string $value): string
{
    return '%' . trim($value) . '%';
}

function fmt_require_active_tipo(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare('SELECT t.id, t.codigo, t.nombre, r.nombre AS ramo_nombre
        FROM seg_tipos_seguro t
        INNER JOIN seg_ramos r ON r.id = t.ramo_id
        WHERE t.id = :id AND t.estado = 1
        LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        fmt_json_error('Solo se pueden usar tipos de seguro activos.', 422, ['tipo_seguro_id' => 'Tipo de seguro no disponible.']);
    }
    return $row;
}

function fmt_require_requisito_mismo_tipo(PDO $pdo, int $requisitoId, int $tipoSeguroId): ?array
{
    if ($requisitoId <= 0) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT id, tipo_seguro_id, nombre, estado
        FROM seg_requisitos_tipo_seguro
        WHERE id = :id
        LIMIT 1');
    $stmt->execute([':id' => $requisitoId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || (int) $row['estado'] !== 1 || (int) $row['tipo_seguro_id'] !== $tipoSeguroId) {
        fmt_json_error('El requisito relacionado debe pertenecer al mismo tipo de seguro y estar activo.', 422, [
            'requisito_tipo_seguro_id' => 'Requisito no compatible.',
        ]);
    }
    return $row;
}

function fmt_validate(PDO $pdo, array $payload, bool $isUpdate = false): array
{
    $errors = [];
    $id = isset($payload['id']) && is_numeric($payload['id']) ? (int) $payload['id'] : 0;
    if ($isUpdate && $id <= 0) {
        $errors['id'] = 'Registro no valido.';
    }

    $tipoSeguroId = isset($payload['tipo_seguro_id']) && is_numeric($payload['tipo_seguro_id']) ? (int) $payload['tipo_seguro_id'] : 0;
    $requisitoId = isset($payload['requisito_tipo_seguro_id']) && is_numeric($payload['requisito_tipo_seguro_id']) ? (int) $payload['requisito_tipo_seguro_id'] : 0;
    $nombre = fmt_str($payload, 'nombre', 180, false);
    $descripcion = fmt_str($payload, 'descripcion', 1000, true);
    $ordenVisual = isset($payload['orden_visual']) && is_numeric($payload['orden_visual']) ? (int) $payload['orden_visual'] : 0;
    $estado = fmt_estado($payload);

    if ($tipoSeguroId <= 0) {
        $errors['tipo_seguro_id'] = 'Seleccione un tipo de seguro activo.';
    }
    if ($nombre === '') {
        $errors['nombre'] = 'Ingrese el nombre del formato.';
    }

    if ($errors !== []) {
        fmt_json_error('Revisa los campos marcados.', 422, $errors);
    }

    fmt_require_active_tipo($pdo, $tipoSeguroId);
    fmt_require_requisito_mismo_tipo($pdo, $requisitoId, $tipoSeguroId);

    return [
        'id' => $id,
        'tipo_seguro_id' => $tipoSeguroId,
        'requisito_tipo_seguro_id' => $requisitoId > 0 ? $requisitoId : null,
        'nombre' => $nombre,
        'descripcion' => $descripcion,
        'orden_visual' => $ordenVisual,
        'estado' => $estado,
    ];
}

function fmt_codigo(PDO $pdo): string
{
    for ($i = 0; $i < 12; $i++) {
        try {
            $random = strtoupper(bin2hex(random_bytes(3)));
        } catch (Throwable $e) {
            $random = strtoupper(substr(hash('sha256', uniqid((string) mt_rand(), true)), 0, 6));
        }
        $codigo = 'FTS-' . date('YmdHis') . '-' . $random;
        $stmt = $pdo->prepare('SELECT id FROM seg_formatos_tipo_seguro WHERE codigo = :codigo LIMIT 1');
        $stmt->execute([':codigo' => $codigo]);
        if (!$stmt->fetchColumn()) {
            return $codigo;
        }
    }
    fmt_json_error('No se pudo generar un codigo unico para el formato.', 409);
}

function fmt_fetch(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare("SELECT
            f.*,
            ts.nombre AS tipo_seguro_nombre,
            r.nombre AS ramo_nombre,
            rt.nombre AS requisito_nombre,
            (
                SELECT v.id
                FROM seg_archivos_vinculos v
                INNER JOIN seg_archivos a ON a.id = v.archivo_id
                WHERE v.codigo_uso = 'formato_tipo_seguro_archivo'
                  AND v.entidad_tipo = 'formato_tipo_seguro'
                  AND v.entidad_id = f.id
                  AND v.slot = 'archivo_principal'
                  AND v.estado = 1
                  AND a.estado = 1
                ORDER BY v.id DESC
                LIMIT 1
            ) AS archivo_vinculo_id
        FROM seg_formatos_tipo_seguro f
        INNER JOIN seg_tipos_seguro ts ON ts.id = f.tipo_seguro_id
        INNER JOIN seg_ramos r ON r.id = ts.ramo_id
        LEFT JOIN seg_requisitos_tipo_seguro rt ON rt.id = f.requisito_tipo_seguro_id
        WHERE f.id = :id
        LIMIT 1");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function fmt_archivo_activo(PDO $pdo, int $formatoId): ?array
{
    $stmt = $pdo->prepare("SELECT
            v.id AS vinculo_id,
            v.archivo_id,
            v.estado AS vinculo_estado,
            a.nombre_original,
            a.mime_type,
            a.tamanio_bytes,
            a.estado AS archivo_estado
        FROM seg_archivos_vinculos v
        INNER JOIN seg_archivos a ON a.id = v.archivo_id
        WHERE v.codigo_uso = 'formato_tipo_seguro_archivo'
          AND v.entidad_tipo = 'formato_tipo_seguro'
          AND v.entidad_id = :id
          AND v.slot = 'archivo_principal'
          AND v.estado = 1
          AND a.estado = 1
        ORDER BY v.id DESC
        LIMIT 1");
    $stmt->execute([':id' => $formatoId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function fmt_archivar_archivo_principal(PDO $pdo, int $formatoId, ?int $userId, string $now): void
{
    $stmt = $pdo->prepare("UPDATE seg_archivos_vinculos
        SET estado = 0,
            actualizado_por_usuario_externo_id = :usuario,
            actualizado_en = :fecha
        WHERE codigo_uso = 'formato_tipo_seguro_archivo'
          AND entidad_tipo = 'formato_tipo_seguro'
          AND entidad_id = :id
          AND slot = 'archivo_principal'
          AND estado = 1");
    $stmt->execute([':usuario' => $userId, ':fecha' => $now, ':id' => $formatoId]);
}

function fmt_fetch_vinculo(PDO $pdo, int $vinculoId, bool $soloActivo = true): ?array
{
    $sql = "SELECT
            v.id AS vinculo_id,
            v.archivo_id,
            v.entidad_id,
            v.estado AS vinculo_estado,
            a.estado AS archivo_estado
        FROM seg_archivos_vinculos v
        INNER JOIN seg_archivos a ON a.id = v.archivo_id
        WHERE v.id = :id
          AND v.codigo_uso = 'formato_tipo_seguro_archivo'
          AND v.entidad_tipo = 'formato_tipo_seguro'
          AND v.slot = 'archivo_principal'";
    if ($soloActivo) {
        $sql .= ' AND v.estado = 1 AND a.estado = 1';
    }
    $sql .= ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $vinculoId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function fmt_db_problem(Throwable $e): void
{
    error_log('[formatos_tipo] ' . $e->getMessage());
    fmt_json_error('No se pudo completar la operacion. Verifica que la tabla del modulo exista y vuelve a intentarlo.', 500);
}
