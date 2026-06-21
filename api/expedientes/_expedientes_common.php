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

function exp_db(): PDO
{
    try {
        return cb_cliente_db_required();
    } catch (Throwable $e) {
        exp_json_error('No se pudo conectar con la base de datos local.', 500);
    }
}

function exp_json_success(array $data = [], string $message = 'Operacion realizada correctamente.'): void
{
    echo json_encode([
        'ok' => true,
        'message' => $message,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function exp_json_error(string $message, int $status = 400, array $errors = []): void
{
    http_response_code($status);
    echo json_encode([
        'ok' => false,
        'message' => $message,
        'errors' => $errors,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function exp_require_method(string $method): void
{
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== strtoupper($method)) {
        exp_json_error('Metodo no permitido.', 405);
    }
}

function exp_payload(): array
{
    $payload = cb_request_payload();
    return is_array($payload) ? $payload : [];
}

function exp_require_perm(string $permiso): void
{
    if (!cb_cliente_puede('expedientes', $permiso)) {
        exp_json_error('No tienes permisos para realizar esta accion.', 403);
    }
}

function exp_require_change(string $permiso): array
{
    exp_require_method('POST');
    exp_require_perm($permiso);
    $payload = exp_payload();
    $token = cb_extract_csrf_token($payload);
    if (!cb_validate_local_csrf('expedientes', $token)) {
        exp_json_error('La sesion expiro o el formulario no es valido. Vuelve a intentarlo.', 419);
    }
    return $payload;
}

function exp_user_id(): ?int
{
    $id = cb_cliente_usuario_externo_id();
    return $id > 0 ? $id : null;
}

function exp_now(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('America/Lima')))->format('Y-m-d H:i:s');
}

function exp_today(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('America/Lima')))->format('Y-m-d');
}

function exp_str(array $data, string $key, int $max, bool $nullable = true): ?string
{
    $value = trim((string) ($data[$key] ?? ''));
    if ($value === '') {
        return $nullable ? null : '';
    }
    return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
}

function exp_date(array $data, string $key): string
{
    $value = trim((string) ($data[$key] ?? ''));
    if ($value === '') {
        return exp_today();
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $value, new DateTimeZone('America/Lima'));
    if (!$dt || $dt->format('Y-m-d') !== $value) {
        exp_json_error('Revisa los campos marcados.', 422, [$key => 'Fecha no valida.']);
    }
    return $value;
}

function exp_estado_value(array $data, string $key = 'estado'): int
{
    return ((string) ($data[$key] ?? '1') === '0') ? 0 : 1;
}

function exp_page_params(): array
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = 10;
    return [$page, $perPage, ($page - 1) * $perPage];
}

function exp_bind_like(string $value): string
{
    return '%' . str_replace(['%', '_'], ['\\%', '\\_'], $value) . '%';
}

function exp_initial_estado(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id, nombre, color_etiqueta FROM seg_estados_expediente WHERE estado = 1 AND es_inicial = 1 ORDER BY id ASC');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) !== 1) {
        exp_json_error('Debe existir exactamente un estado inicial activo antes de registrar expedientes.', 409, [
            'estado_expediente_id' => 'Configura un unico estado inicial activo en Catalogos.',
        ]);
    }
    return $rows[0];
}

function exp_validate(PDO $pdo, array $payload, bool $isUpdate = false): array
{
    $errors = [];
    $id = isset($payload['id']) && is_numeric($payload['id']) ? (int) $payload['id'] : 0;
    if ($isUpdate && $id <= 0) {
        $errors['id'] = 'Registro no valido.';
    }

    $clienteId = isset($payload['cliente_id']) && is_numeric($payload['cliente_id']) ? (int) $payload['cliente_id'] : 0;
    $tipoSeguroId = isset($payload['tipo_seguro_id']) && is_numeric($payload['tipo_seguro_id']) ? (int) $payload['tipo_seguro_id'] : 0;
    $estadoExpedienteId = isset($payload['estado_expediente_id']) && is_numeric($payload['estado_expediente_id']) ? (int) $payload['estado_expediente_id'] : 0;
    $descripcion = exp_str($payload, 'descripcion', 255, false);
    $observaciones = exp_str($payload, 'observaciones', 3000, true);
    $fechaApertura = exp_date($payload, 'fecha_apertura');
    $estado = exp_estado_value($payload);

    if ($clienteId <= 0) {
        $errors['cliente_id'] = 'Seleccione un cliente activo.';
    }
    if ($tipoSeguroId <= 0) {
        $errors['tipo_seguro_id'] = 'Seleccione un tipo de seguro activo.';
    }
    if ($isUpdate && $estadoExpedienteId <= 0) {
        $errors['estado_expediente_id'] = 'Seleccione un estado de expediente.';
    }
    if ($descripcion === '') {
        $errors['descripcion'] = 'Ingrese una descripcion breve u objeto del seguro.';
    }

    if ($errors) {
        exp_json_error('Revisa los campos marcados.', 422, $errors);
    }

    exp_require_active_cliente($pdo, $clienteId);
    exp_require_active_tipo($pdo, $tipoSeguroId);

    if ($isUpdate) {
        exp_require_active_estado_expediente($pdo, $estadoExpedienteId);
    } else {
        $estadoInicial = exp_initial_estado($pdo);
        $estadoExpedienteId = (int) $estadoInicial['id'];
    }

    return [
        'id' => $id,
        'cliente_id' => $clienteId,
        'tipo_seguro_id' => $tipoSeguroId,
        'estado_expediente_id' => $estadoExpedienteId,
        'descripcion' => $descripcion,
        'observaciones' => $observaciones,
        'fecha_apertura' => $fechaApertura,
        'estado' => $estado,
    ];
}

function exp_require_active_cliente(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare('SELECT id, estado FROM seg_clientes WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || (int) $row['estado'] !== 1) {
        exp_json_error('Solo se pueden usar clientes activos.', 422, ['cliente_id' => 'Cliente no disponible.']);
    }
}

function exp_require_active_tipo(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare('SELECT id, estado FROM seg_tipos_seguro WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || (int) $row['estado'] !== 1) {
        exp_json_error('Solo se pueden usar tipos de seguro activos.', 422, ['tipo_seguro_id' => 'Tipo de seguro no disponible.']);
    }
}

function exp_require_active_estado_expediente(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare('SELECT id, estado FROM seg_estados_expediente WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || (int) $row['estado'] !== 1) {
        exp_json_error('Solo se pueden usar estados de expediente activos.', 422, ['estado_expediente_id' => 'Estado no disponible.']);
    }
}

function exp_next_codigo(PDO $pdo, string $year): string
{
    $prefix = 'EXP-' . $year . '-';
    $stmt = $pdo->prepare("SELECT codigo FROM seg_expedientes WHERE codigo LIKE :prefix ORDER BY codigo DESC LIMIT 1 FOR UPDATE");
    $stmt->execute([':prefix' => $prefix . '%']);
    $last = (string) ($stmt->fetchColumn() ?: '');
    $next = 1;
    if (preg_match('/^EXP-' . preg_quote($year, '/') . '-([0-9]{6})$/', $last, $m) === 1) {
        $next = (int) $m[1] + 1;
    }
    return $prefix . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
}

function exp_db_error(Throwable $e): void
{
    error_log('[expedientes] ' . $e->getMessage());
    exp_json_error('No se pudo completar la operacion solicitada.', 500);
}

function exp_timeline_add(PDO $pdo, string $entidadTipo, int $entidadId, string $codigoEvento, string $descripcion, ?array $metadata = null, ?int $actorId = null, ?string $fecha = null): void
{
    $entidadTipo = trim($entidadTipo);
    $codigoEvento = trim($codigoEvento);
    $descripcion = trim($descripcion);
    if ($entidadTipo === '' || $entidadId <= 0 || $codigoEvento === '' || $descripcion === '') {
        throw new RuntimeException('Evento de timeline invalido.');
    }

    $metadataJson = null;
    if (is_array($metadata) && $metadata !== []) {
        $encoded = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $metadataJson = is_string($encoded) && $encoded !== '' ? $encoded : null;
    }

    $stmt = $pdo->prepare('INSERT INTO seg_timeline_eventos
        (entidad_tipo, entidad_id, codigo_evento, descripcion, actor_usuario_externo_id, fecha_evento, metadata_json)
        VALUES
        (:entidad_tipo, :entidad_id, :codigo_evento, :descripcion, :actor_usuario, :fecha_evento, :metadata_json)');
    $stmt->execute([
        ':entidad_tipo' => $entidadTipo,
        ':entidad_id' => $entidadId,
        ':codigo_evento' => $codigoEvento,
        ':descripcion' => $descripcion,
        ':actor_usuario' => $actorId ?? exp_user_id(),
        ':fecha_evento' => $fecha ?? exp_now(),
        ':metadata_json' => $metadataJson,
    ]);
}

function exp_tipo_documento_options(): array
{
    return [
        'documento_general' => 'Documento general',
        'cotizacion' => 'Cotizacion',
        'poliza' => 'Poliza',
        'constancia' => 'Constancia',
        'endoso' => 'Endoso',
        'carta_fianza' => 'Carta fianza',
        'voucher' => 'Voucher',
        'garantia' => 'Garantia',
    ];
}

function exp_tipo_documento_label(string $codigo): string
{
    $options = exp_tipo_documento_options();
    return $options[$codigo] ?? $codigo;
}
