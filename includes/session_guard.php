<?php
require_once __DIR__ . '/helpers.php';

cb_boot_session();

$isApiRequest = cb_is_api_request();
$auth = cb_get_auth();

if (!is_array($auth) || empty($auth['ok'])) {
    cb_guard_fail($isApiRequest, 'Sesión no válida.');
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
