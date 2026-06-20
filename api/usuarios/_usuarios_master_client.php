<?php
require_once __DIR__ . '/../../includes/session_guard.php';
require_once __DIR__ . '/../../includes/api_client.php';

function usu_scope()
{
    return 'usuarios';
}

function usu_payload()
{
    $payload = cb_request_payload();
    return is_array($payload) ? $payload : [];
}

function usu_auth_token()
{
    $auth = cb_get_auth();
    return trim((string) ($auth['token_sesion_servicio'] ?? ''));
}

function usu_master_endpoint($endpoint)
{
    return 'sistema/api/serv/usuarios/' . ltrim((string) $endpoint, '/');
}

function usu_master_call($endpoint, array $payload = [])
{
    $token = usu_auth_token();
    if ($token === '') {
        cb_destroy_session();
        cb_json_error('sesion_maestro_requerida', 'Sesion del sistema maestro no disponible. Inicia sesion nuevamente.', 401);
    }

    $payload = $payload + [
        'api_key' => (string) API_KEY,
        'api_secret' => (string) API_SECRET,
        'dominio' => cb_local_domain(),
        'token_sesion_servicio' => $token,
    ];

    $url = cb_api_build_url(API_BASE_URL, usu_master_endpoint($endpoint));
    $result = cb_api_post($url, $payload, 15);
    $decoded = is_array($result['decoded'] ?? null) ? $result['decoded'] : [];

    if (!$decoded) {
        cb_json_error('maestro_sin_respuesta', 'No se pudo comunicar con Luigi Sistemas.', 502);
    }

    $code = (string) ($decoded['code'] ?? '');
    if ($code === 'token_sesion_invalido' || $code === 'actor_no_autorizado' || $code === 'actor_sin_servicio') {
        cb_destroy_session();
        cb_json_error($code, 'Tu sesion central ya no es valida. Inicia sesion nuevamente.', 401);
    }

    $httpCode = (int) ($result['http_code'] ?? 0);
    if (empty($decoded['ok'])) {
        $status = $httpCode >= 400 ? $httpCode : 400;
        cb_json_error(
            $code !== '' ? $code : 'error_maestro',
            (string) ($decoded['message'] ?? 'No se pudo completar la operacion.'),
            $status,
            is_array($decoded['errors'] ?? null) ? $decoded['errors'] : []
        );
    }

    return is_array($decoded['data'] ?? null) ? $decoded['data'] : [];
}

function usu_require_action($accion, $method = 'POST', $csrf = true)
{
    cb_require_method($method);
    cb_require_cliente_permission('usuarios', $accion);
    if ($csrf) {
        cb_require_local_csrf(usu_scope());
    }
}
