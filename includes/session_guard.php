<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/autorizacion_cliente.php';

cb_boot_session();

$isApiRequest = cb_is_api_request();
$auth = cb_get_auth();

if (!is_array($auth) || empty($auth['ok'])) {
    cb_guard_fail($isApiRequest, 'Sesión no válida.');
}

if (!cb_auth_has_authorization_payload($auth)) {
    cb_destroy_session();
    cb_guard_fail($isApiRequest, 'Sesion no valida.');
}

$now = time();
$lastActivity = isset($auth['last_activity_at']) ? (int) $auth['last_activity_at'] : 0;
$timeoutSeconds = cb_get_timeout_minutes() * 60;

if ($lastActivity <= 0 || ($now - $lastActivity) > $timeoutSeconds) {
    cb_destroy_session();
    cb_guard_fail($isApiRequest, 'Sesión expirada.');
}

$_SESSION['cliente_auth']['last_activity_at'] = $now;

function cb_guard_fail($isApiRequest, $message)
{
    if ($isApiRequest) {
        cb_json_response(401, [
            'ok' => false,
            'code' => 'sesion_requerida',
            'message' => (string) $message,
        ]);
    }

    cb_redirect('login.php?m=sesion');
}

function cb_auth_has_authorization_payload(array $auth)
{
    $rol = is_array($auth['rol'] ?? null) ? $auth['rol'] : [];
    $permisos = is_array($auth['permisos'] ?? null) ? $auth['permisos'] : [];
    $menu = is_array($auth['menu'] ?? null) ? $auth['menu'] : null;

    if (trim((string) ($rol['codigo_rol'] ?? '')) === '') {
        return false;
    }
    if (trim((string) ($rol['nombre'] ?? '')) === '') {
        return false;
    }
    if (!$permisos) {
        return false;
    }

    return is_array($menu);
}
