<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/session_guard.php';
require_once __DIR__ . '/../../includes/conexion_cliente.php';
require_once __DIR__ . '/../../includes/request_cliente.php';
require_once __DIR__ . '/../../includes/autorizacion_cliente.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

function cli_db(): PDO
{
    try {
        return cb_cliente_db_required();
    } catch (Throwable $e) {
        cli_json_error('No se pudo conectar con la base de datos local.', 500);
    }
}

function cli_payload(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    return $_POST;
}

function cli_json_success(array $data = [], string $message = 'Operacion realizada correctamente.'): void
{
    echo json_encode([
        'ok' => true,
        'message' => $message,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function cli_json_error(string $message, int $status = 400, array $errors = []): void
{
    http_response_code($status);
    echo json_encode([
        'ok' => false,
        'message' => $message,
        'errors' => $errors,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function cli_require_method(string $method): void
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== strtoupper($method)) {
        cli_json_error('Metodo no permitido.', 405);
    }
}

function cli_require_perm(string $permiso): void
{
    if (!cb_cliente_puede('clientes', $permiso)) {
        cli_json_error('No tienes permisos para realizar esta accion.', 403);
    }
}

function cli_require_post_change(string $permiso): array
{
    cli_require_method('POST');
    cli_require_perm($permiso);
    $payload = cli_payload();

    $token = (string)($payload['_csrf'] ?? $payload['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if (!cb_validate_local_csrf('clientes', $token)) {
        cli_json_error('La sesion expiro o el formulario no es valido. Vuelve a intentarlo.', 419);
    }

    return $payload;
}

function cli_now_lima(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('America/Lima')))->format('Y-m-d H:i:s');
}

function cli_user_id(): ?int
{
    if (function_exists('cb_cliente_usuario_externo_id')) {
        $id = cb_cliente_usuario_externo_id();
        return $id > 0 ? $id : null;
    }

    $usuario = $_SESSION['usuario_externo'] ?? $_SESSION['cliente_usuario'] ?? $_SESSION['usuario'] ?? [];
    if (is_array($usuario)) {
        foreach (['id', 'usuario_externo_id', 'id_usuario_externo'] as $key) {
            if (isset($usuario[$key]) && is_numeric($usuario[$key])) {
                return (int)$usuario[$key];
            }
        }
    }

    foreach (['usuario_externo_id', 'id_usuario_externo'] as $key) {
        if (isset($_SESSION[$key]) && is_numeric($_SESSION[$key])) {
            return (int)$_SESSION[$key];
        }
    }

    return null;
}

function cli_str(array $data, string $key, int $max = 255, bool $nullable = true): ?string
{
    $value = trim((string)($data[$key] ?? ''));
    if ($value === '') {
        return $nullable ? null : '';
    }

    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max, 'UTF-8');
    }

    return substr($value, 0, $max);
}

function cli_required_str(array $data, string $key, string $label, int $max, array &$errors): string
{
    $value = cli_str($data, $key, $max, false);
    if ($value === '') {
        $errors[$key] = $label . ' es obligatorio.';
        return '';
    }

    return $value;
}

function cli_digits(?string $value, int $max = 20): ?string
{
    $digits = preg_replace('/\D+/', '', (string)$value);
    if ($digits === '') {
        return null;
    }

    return substr($digits, 0, $max);
}

function cli_estado(array $data, string $key = 'estado'): int
{
    return ((string)($data[$key] ?? '1') === '0') ? 0 : 1;
}

function cli_bool(array $data, string $key, int $default = 0): int
{
    if (!array_key_exists($key, $data)) {
        return $default;
    }

    $value = $data[$key];
    return in_array($value, [1, '1', true, 'true', 'on', 'si'], true) ? 1 : 0;
}

function cli_validate_email(?string $email, string $key, array &$errors): ?string
{
    if ($email === null) {
        return null;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[$key] = 'El correo no tiene un formato valido.';
        return null;
    }

    return $email;
}

function cli_validate_empresa(array $payload, bool $isUpdate = false): array
{
    $errors = [];

    $id = isset($payload['id']) && is_numeric($payload['id']) ? (int)$payload['id'] : 0;
    if ($isUpdate && $id <= 0) {
        $errors['id'] = 'Registro no valido.';
    }

    $ruc = cli_digits((string)($payload['ruc'] ?? ''), 11);
    if ($ruc === null || strlen($ruc) !== 11) {
        $errors['ruc'] = 'El RUC debe tener 11 digitos.';
    }

    $razonSocial = cli_required_str($payload, 'razon_social', 'La razon social', 180, $errors);
    $nombreComercial = cli_str($payload, 'nombre_comercial', 180);
    $direccion = cli_str($payload, 'direccion', 255);
    $telefonoPrincipal = cli_digits((string)($payload['telefono_principal'] ?? ''), 40);
    $correoPrincipal = cli_validate_email(cli_str($payload, 'correo_principal', 160), 'correo_principal', $errors);
    $observaciones = cli_str($payload, 'observaciones', 3000);
    $estado = cli_estado($payload);

    $contactoNombre = cli_str($payload, 'contacto_nombre_completo', 180);
    $contactoCargo = cli_str($payload, 'contacto_cargo_relacion', 120);
    $contactoTelefono = cli_digits((string)($payload['contacto_telefono'] ?? ''), 40);
    $contactoCorreo = cli_validate_email(cli_str($payload, 'contacto_correo', 160), 'contacto_correo', $errors);
    $contactoEstado = cli_estado($payload, 'contacto_estado');
    $contactoPrincipal = cli_bool($payload, 'contacto_es_principal', 1);

    $hayContacto = $contactoNombre !== null || $contactoCargo !== null || $contactoTelefono !== null || $contactoCorreo !== null;
    if ($hayContacto && $contactoNombre === null) {
        $errors['contacto_nombre_completo'] = 'El nombre del contacto es obligatorio si registras datos de contacto.';
    }

    if ($contactoPrincipal !== 1 && $hayContacto) {
        $errors['contacto_es_principal'] = 'En esta version el contacto registrado debe ser principal.';
    }

    if ($errors !== []) {
        cli_json_error('Revisa los campos marcados.', 422, $errors);
    }

    return [
        'id' => $id,
        'tipo_cliente' => 'empresa',
        'ruc' => $ruc,
        'razon_social' => $razonSocial,
        'nombre_comercial' => $nombreComercial,
        'direccion' => $direccion,
        'telefono_principal' => $telefonoPrincipal,
        'correo_principal' => $correoPrincipal,
        'observaciones' => $observaciones,
        'estado' => $estado,
        'contacto' => [
            'nombre_completo' => $contactoNombre,
            'cargo_relacion' => $contactoCargo,
            'telefono' => $contactoTelefono,
            'correo' => $contactoCorreo,
            'estado' => $contactoEstado,
            'es_principal' => $contactoPrincipal,
            'presente' => $hayContacto,
        ],
    ];
}

function cli_codigo_cliente(PDO $pdo, string $tipoCliente, ?string $ruc = null): string
{
    $prefix = $tipoCliente === 'consorcio' ? 'CON' : 'EMP';

    for ($i = 0; $i < 12; $i++) {
        try {
            $random = strtoupper(bin2hex(random_bytes(3)));
        } catch (Throwable $e) {
            $random = strtoupper(substr(md5(uniqid((string) mt_rand(), true)), 0, 6));
        }
        $codigo = $prefix . '-' . date('YmdHis') . '-' . $random;
        $stmt = $pdo->prepare('SELECT id FROM seg_clientes WHERE codigo = :codigo LIMIT 1');
        $stmt->execute([':codigo' => $codigo]);
        if (!$stmt->fetchColumn()) {
            return $codigo;
        }
    }

    cli_json_error('No se pudo generar un codigo unico para el cliente.', 409);
}

function cli_validate_consorcio(array $payload, bool $isUpdate = false): array
{
    $errors = [];

    $id = isset($payload['id']) && is_numeric($payload['id']) ? (int)$payload['id'] : 0;
    if ($isUpdate && $id <= 0) {
        $errors['id'] = 'Registro no valido.';
    }

    $modalidad = strtolower(trim((string)($payload['modalidad'] ?? '')));
    if (!in_array($modalidad, ['con_ruc_propio', 'con_operador_tributario'], true)) {
        $errors['modalidad'] = 'Seleccione una modalidad valida.';
    }

    $ruc = cli_digits((string)($payload['ruc'] ?? ''), 11);
    if ($modalidad === 'con_ruc_propio' && ($ruc === null || strlen($ruc) !== 11)) {
        $errors['ruc'] = 'El RUC es obligatorio para consorcios con RUC propio.';
    }
    if ($modalidad === 'con_operador_tributario' && $ruc !== null) {
        $errors['ruc'] = 'Deja el RUC vacio cuando el consorcio usa operador tributario.';
    }

    $razonSocial = cli_required_str($payload, 'razon_social', 'La razon social', 180, $errors);
    $nombreComercial = cli_str($payload, 'nombre_comercial', 180);
    $direccion = cli_str($payload, 'direccion', 255);
    $telefonoPrincipal = cli_digits((string)($payload['telefono_principal'] ?? ''), 40);
    $correoPrincipal = cli_validate_email(cli_str($payload, 'correo_principal', 160), 'correo_principal', $errors);
    $observaciones = cli_str($payload, 'observaciones', 3000);
    $estado = cli_estado($payload);

    $operadorId = isset($payload['operador_cliente_id']) && is_numeric($payload['operador_cliente_id'])
        ? (int)$payload['operador_cliente_id']
        : 0;
    if ($modalidad === 'con_operador_tributario' && $operadorId <= 0) {
        $errors['operador_cliente_id'] = 'Seleccione el operador tributario.';
    }
    if ($modalidad === 'con_ruc_propio') {
        $operadorId = 0;
    }

    $empresaIds = $payload['integrante_empresa_id'] ?? [];
    $participaciones = $payload['integrante_participacion'] ?? [];
    $roles = $payload['integrante_rol'] ?? [];
    if (!is_array($empresaIds)) {
        $empresaIds = [];
    }
    if (!is_array($participaciones)) {
        $participaciones = [];
    }
    if (!is_array($roles)) {
        $roles = [];
    }

    $integrantes = [];
    $seen = [];
    $suma = 0.0;
    foreach ($empresaIds as $idx => $empresaIdRaw) {
        $empresaId = is_numeric($empresaIdRaw) ? (int)$empresaIdRaw : 0;
        if ($empresaId <= 0) {
            continue;
        }
        if (isset($seen[$empresaId])) {
            $errors['integrantes'] = 'No repitas una empresa dentro del mismo consorcio.';
            continue;
        }
        $seen[$empresaId] = true;

        $participacionRaw = trim((string)($participaciones[$idx] ?? ''));
        $participacion = null;
        if ($participacionRaw !== '') {
            $participacion = (float)str_replace(',', '.', $participacionRaw);
            if ($participacion <= 0 || $participacion > 100) {
                $errors['integrante_participacion'] = 'La participacion debe ser mayor que 0 y maximo 100.';
            } else {
                $suma += $participacion;
            }
        }

        $integrantes[] = [
            'empresa_cliente_id' => $empresaId,
            'participacion_porcentaje' => $participacion,
            'rol_descripcion' => cli_str(['rol' => $roles[$idx] ?? ''], 'rol', 160),
        ];
    }

    if (count($integrantes) < 2) {
        $errors['integrantes'] = 'Agrega al menos dos empresas integrantes.';
    }
    if ($suma > 100.0) {
        $errors['integrante_participacion'] = 'La suma de participaciones no puede superar 100%.';
    }
    if ($modalidad === 'con_operador_tributario' && $operadorId > 0 && !isset($seen[$operadorId])) {
        $errors['operador_cliente_id'] = 'El operador tributario debe formar parte de los integrantes activos.';
    }

    if ($errors !== []) {
        cli_json_error('Revisa los campos marcados.', 422, $errors);
    }

    return [
        'id' => $id,
        'tipo_cliente' => 'consorcio',
        'modalidad' => $modalidad,
        'operador_cliente_id' => $operadorId > 0 ? $operadorId : null,
        'ruc' => $ruc,
        'razon_social' => $razonSocial,
        'nombre_comercial' => $nombreComercial,
        'direccion' => $direccion,
        'telefono_principal' => $telefonoPrincipal,
        'correo_principal' => $correoPrincipal,
        'observaciones' => $observaciones,
        'estado' => $estado,
        'integrantes' => $integrantes,
    ];
}

function cli_page_params(): array
{
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 10;
    $offset = ($page - 1) * $perPage;

    return [$page, $perPage, $offset];
}

function cli_db_problem(Throwable $e): void
{
    error_log('[clientes] ' . $e->getMessage());
    cli_json_error('No se pudo completar la operacion. Verifica que las tablas del modulo existan y vuelve a intentarlo.', 500);
}
