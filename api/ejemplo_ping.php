<?php
require_once __DIR__ . '/../includes/session_guard.php';

$auth = cb_get_auth();
$usuario = is_array($auth['usuario'] ?? null) ? $auth['usuario'] : [];
$servicio = is_array($auth['servicio'] ?? null) ? $auth['servicio'] : [];

cb_json_response(200, [
    'ok' => true,
    'code' => 'ok',
    'message' => 'Ping protegido con sesión local activa.',
    'data' => [
        'usuario_id' => (int) ($usuario['id'] ?? 0),
        'servicio_codigo' => (string) ($servicio['codigo_servicio'] ?? ''),
        'timestamp' => date('Y-m-d H:i:s'),
    ],
]);

