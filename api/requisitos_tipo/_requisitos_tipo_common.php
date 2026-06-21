<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/session_guard.php';
require_once __DIR__ . '/../../includes/conexion_cliente.php';
require_once __DIR__ . '/../../includes/request_cliente.php';
require_once __DIR__ . '/../../includes/autorizacion_cliente.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

function req_db(): PDO
{
    try {
        return cb_cliente_db_required();
    } catch (Throwable $e) {
        req_json_error('No se pudo conectar con la base de datos local.', 500);
    }
}

function req_json_success(array $data = [], string $message = 'Operacion realizada correctamente.'): void
{
    echo json_encode([
        'ok' => true,
        'message' => $message,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function req_json_error(string $message, int $status = 400, array $errors = []): void
{
    http_response_code($status);
    echo json_encode([
        'ok' => false,
        'message' => $message,
        'errors' => $errors,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function req_require_method(string $method): void
{
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== strtoupper($method)) {
        req_json_error('Metodo no permitido.', 405);
    }
}

function req_payload(): array
{
    $payload = cb_request_payload();
    return is_array($payload) ? $payload : [];
}

function req_require_perm(string $permiso): void
{
    if (!cb_cliente_puede('requisitos_tipo', $permiso)) {
        req_json_error('No tienes permisos para realizar esta accion.', 403);
    }
}

function req_require_change(string $permiso): array
{
    req_require_method('POST');
    req_require_perm($permiso);
    $payload = req_payload();
    $token = cb_extract_csrf_token($payload);
    if (!cb_validate_local_csrf('requisitos_tipo', $token)) {
        req_json_error('La sesion expiro o el formulario no es valido. Vuelve a intentarlo.', 419);
    }
    return $payload;
}

function req_user_id(): ?int
{
    $id = cb_cliente_usuario_externo_id();
    return $id > 0 ? $id : null;
}

function req_now(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('America/Lima')))->format('Y-m-d H:i:s');
}

function req_str(array $data, string $key, int $max, bool $nullable = true): ?string
{
    $value = trim((string) ($data[$key] ?? ''));
    if ($value === '') {
        return $nullable ? null : '';
    }
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max, 'UTF-8');
    }
    return substr($value, 0, $max);
}

function req_bool(array $data, string $key, int $default = 0): int
{
    if (!array_key_exists($key, $data)) {
        return $default;
    }
    return in_array($data[$key], [1, '1', true, 'true', 'on', 'si'], true) ? 1 : 0;
}

function req_estado(array $data): int
{
    return ((string) ($data['estado'] ?? '1') === '0') ? 0 : 1;
}

function req_page_params(): array
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = 10;
    return [$page, $perPage, ($page - 1) * $perPage];
}

function req_bind_like(string $value): string
{
    return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value) . '%';
}

function req_validate(PDO $pdo, array $payload, bool $isUpdate = false): array
{
    $errors = [];
    $id = isset($payload['id']) && is_numeric($payload['id']) ? (int) $payload['id'] : 0;
    if ($isUpdate && $id <= 0) {
        $errors['id'] = 'Registro no valido.';
    }

    $tipoSeguroId = isset($payload['tipo_seguro_id']) && is_numeric($payload['tipo_seguro_id']) ? (int) $payload['tipo_seguro_id'] : 0;
    $nombre = req_str($payload, 'nombre', 180, false);
    $descripcion = req_str($payload, 'descripcion', 1000, true);
    $esObligatorio = req_bool($payload, 'es_obligatorio', 1);
    $ordenVisual = isset($payload['orden_visual']) && is_numeric($payload['orden_visual']) ? (int) $payload['orden_visual'] : 0;
    $estado = req_estado($payload);

    if ($tipoSeguroId <= 0) {
        $errors['tipo_seguro_id'] = 'Seleccione un tipo de seguro activo.';
    }
    if ($nombre === '') {
        $errors['nombre'] = 'Ingrese el nombre del requisito.';
    }

    if ($errors !== []) {
        req_json_error('Revisa los campos marcados.', 422, $errors);
    }

    req_require_active_tipo($pdo, $tipoSeguroId);
    if ($estado === 1) {
        req_require_unique_active_name($pdo, $tipoSeguroId, (string) $nombre, $id);
    }

    return [
        'id' => $id,
        'tipo_seguro_id' => $tipoSeguroId,
        'nombre' => $nombre,
        'descripcion' => $descripcion,
        'es_obligatorio' => $esObligatorio,
        'orden_visual' => $ordenVisual,
        'estado' => $estado,
    ];
}

function req_require_active_tipo(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare('SELECT id, estado FROM seg_tipos_seguro WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || (int) $row['estado'] !== 1) {
        req_json_error('Solo se pueden usar tipos de seguro activos.', 422, ['tipo_seguro_id' => 'Tipo de seguro no disponible.']);
    }
}

function req_require_unique_active_name(PDO $pdo, int $tipoSeguroId, string $nombre, int $ignoreId = 0): void
{
    $sql = 'SELECT id FROM seg_requisitos_tipo_seguro
        WHERE tipo_seguro_id = :tipo_seguro_id
          AND nombre = :nombre
          AND estado = 1';
    $params = [
        ':tipo_seguro_id' => $tipoSeguroId,
        ':nombre' => $nombre,
    ];
    if ($ignoreId > 0) {
        $sql .= ' AND id <> :id';
        $params[':id'] = $ignoreId;
    }
    $sql .= ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if ($stmt->fetchColumn()) {
        req_json_error('Ya existe un requisito activo con ese nombre para el tipo de seguro.', 409, ['nombre' => 'Requisito activo duplicado.']);
    }
}

function req_codigo(PDO $pdo): string
{
    for ($i = 0; $i < 12; $i++) {
        try {
            $random = strtoupper(bin2hex(random_bytes(3)));
        } catch (Throwable $e) {
            $random = strtoupper(substr(hash('sha256', uniqid((string) mt_rand(), true)), 0, 6));
        }
        $codigo = 'RTS-' . date('YmdHis') . '-' . $random;
        $stmt = $pdo->prepare('SELECT id FROM seg_requisitos_tipo_seguro WHERE codigo = :codigo LIMIT 1');
        $stmt->execute([':codigo' => $codigo]);
        if (!$stmt->fetchColumn()) {
            return $codigo;
        }
    }
    req_json_error('No se pudo generar un codigo unico para el requisito.', 409);
}

function req_db_problem(Throwable $e): void
{
    error_log('[requisitos_tipo] ' . $e->getMessage());
    req_json_error('No se pudo completar la operacion. Verifica que la tabla del modulo exista y vuelve a intentarlo.', 500);
}
