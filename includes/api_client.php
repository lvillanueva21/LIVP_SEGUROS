<?php
require_once __DIR__ . '/helpers.php';

function cb_api_build_url($base, $endpoint)
{
    $base = rtrim(trim((string) $base), '/');
    $endpoint = ltrim(trim((string) $endpoint), '/');
    if ($base === '') {
        return '';
    }

    return $endpoint === '' ? $base : ($base . '/' . $endpoint);
}

function cb_api_post($url, array $payload, $timeoutSeconds = 15)
{
    $url = trim((string) $url);
    $timeoutSeconds = (int) $timeoutSeconds;
    if ($timeoutSeconds < 5) {
        $timeoutSeconds = 5;
    }
    if ($timeoutSeconds > 30) {
        $timeoutSeconds = 30;
    }

    if ($url === '') {
        return [
            'ok' => false,
            'http_code' => 0,
            'raw' => '',
            'decoded' => null,
            'error' => 'url_invalida',
        ];
    }

    $rawResponse = '';
    $httpCode = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $rawResponse = curl_exec($ch);
        if (is_string($rawResponse)) {
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        }
        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
                'content' => http_build_query($payload),
                'timeout' => $timeoutSeconds,
                'ignore_errors' => true,
            ],
        ]);
        $rawResponse = @file_get_contents($url, false, $context);
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $headerLine) {
                if (preg_match('/^HTTP\/\S+\s+(\d{3})/i', (string) $headerLine, $m)) {
                    $httpCode = (int) $m[1];
                    break;
                }
            }
        }
    }

    $decoded = null;
    if (is_string($rawResponse) && $rawResponse !== '') {
        $tmp = json_decode($rawResponse, true);
        if (is_array($tmp)) {
            $decoded = $tmp;
        }
    }

    return [
        'ok' => is_string($rawResponse) && $rawResponse !== '',
        'http_code' => $httpCode,
        'raw' => is_string($rawResponse) ? $rawResponse : '',
        'decoded' => $decoded,
        'error' => '',
    ];
}

function cb_api_config_visual()
{
    $url = cb_api_build_url(API_BASE_URL, API_CONFIG_VISUAL_ENDPOINT);
    if ($url === '') {
        return [
            'ok' => false,
            'code' => 'config_incompleta',
            'message' => 'Configuración API incompleta.',
            'data' => [],
        ];
    }

    $payload = [
        'api_key' => (string) API_KEY,
        'api_secret' => (string) API_SECRET,
        'dominio' => cb_local_domain(),
        'servicio_codigo' => (string) SERVICIO_CODIGO,
    ];

    $postResult = cb_api_post($url, $payload, 12);
    $decoded = is_array($postResult['decoded']) ? $postResult['decoded'] : [];

    if (empty($decoded)) {
        return [
            'ok' => false,
            'code' => 'api_sin_respuesta',
            'message' => 'No se pudo obtener configuración remota.',
            'data' => [],
        ];
    }

    $ok = !empty($decoded['ok']);
    $code = (string) ($decoded['code'] ?? ($ok ? 'ok' : 'error'));
    $message = (string) ($decoded['message'] ?? ($ok ? 'ok' : 'No se pudo obtener configuración remota.'));
    $data = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];

    if (!$ok && $message === '') {
        $message = 'No se pudo obtener configuración remota.';
    }
    if (!$ok && (int) ($postResult['http_code'] ?? 0) >= 500) {
        $message = 'No se pudo obtener configuración remota.';
    }

    return [
        'ok' => $ok,
        'code' => $code,
        'message' => $message,
        'data' => $data,
    ];
}

function cb_api_login($documentoTipo, $documentoNumero, $clave)
{
    $documentoTipo = strtoupper(trim((string) $documentoTipo));
    $documentoNumero = trim((string) $documentoNumero);
    $clave = (string) $clave;

    $url = cb_api_build_url(API_BASE_URL, API_LOGIN_ENDPOINT);
    if ($url === '') {
        return [
            'ok' => false,
            'code' => 'config_incompleta',
            'message' => 'Configuración API incompleta.',
            'data' => [],
        ];
    }

    $payload = [
        'api_key' => (string) API_KEY,
        'api_secret' => (string) API_SECRET,
        'dominio' => cb_local_domain(),
        'documento_tipo' => $documentoTipo,
        'documento_numero' => $documentoNumero,
        'clave' => $clave,
    ];

    $postResult = cb_api_post($url, $payload, 15);
    if (empty($postResult['ok'])) {
        return [
            'ok' => false,
            'code' => 'api_sin_respuesta',
            'message' => 'No se pudo validar el acceso en este momento.',
            'data' => [],
        ];
    }

    $decoded = is_array($postResult['decoded']) ? $postResult['decoded'] : null;
    if (!is_array($decoded)) {
        return [
            'ok' => false,
            'code' => 'api_respuesta_invalida',
            'message' => 'Respuesta inválida del servidor central.',
            'data' => [],
        ];
    }

    $ok = !empty($decoded['ok']);
    $code = (string) ($decoded['code'] ?? ($ok ? 'ok' : 'error'));
    $message = (string) ($decoded['message'] ?? ($ok ? 'Acceso autorizado.' : 'Credenciales inválidas o acceso no autorizado.'));
    $data = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];

    if (!$ok && $message === '') {
        $message = 'Credenciales inválidas o acceso no autorizado.';
    }

    // Normaliza respuestas de error de red/HTTP sin exponer detalles sensibles.
    if (!$ok && (int) ($postResult['http_code'] ?? 0) >= 500) {
        $message = 'No se pudo validar el acceso en este momento.';
    }

    return [
        'ok' => $ok,
        'code' => $code,
        'message' => $message,
        'data' => $data,
    ];
}
