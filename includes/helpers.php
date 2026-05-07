<?php
require_once __DIR__ . '/config_cliente.php';

function cb_e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function cb_detect_base_url()
{
    $scriptName = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '';
    if ($scriptName === '') {
        return '';
    }

    $dir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    if ($dir === '.' || $dir === '/') {
        return '';
    }

    return $dir;
}

function cb_base_url()
{
    $configured = trim((string) CLIENTE_BASE_URL);
    if ($configured !== '') {
        return rtrim($configured, '/');
    }

    return cb_detect_base_url();
}

function cb_url($path = '')
{
    $base = cb_base_url();
    $cleanPath = ltrim((string) $path, '/');

    if ($cleanPath === '') {
        return $base === '' ? '.' : $base . '/';
    }

    if ($base === '') {
        return $cleanPath;
    }

    return $base . '/' . $cleanPath;
}

function cb_local_domain()
{
    $configured = strtolower(trim((string) DOMINIO_LOCAL));
    if ($configured !== '') {
        return $configured;
    }

    return strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
}

function cb_is_api_request()
{
    $path = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (strpos($path, '/api/') !== false) {
        return true;
    }

    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    if (strpos($accept, 'application/json') !== false) {
        return true;
    }

    $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    return $requestedWith === 'xmlhttprequest';
}

function cb_json_response($httpCode, array $payload)
{
    http_response_code((int) $httpCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function cb_boot_session()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.cookie_samesite', 'Lax');

    $isHttps = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    if ($isHttps) {
        @ini_set('session.cookie_secure', '1');
    }

    session_name('LSIS_CLIENTE_SESS');
    session_start();
}

function cb_destroy_session()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        cb_boot_session();
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            isset($params['path']) ? $params['path'] : '/',
            isset($params['domain']) ? $params['domain'] : '',
            !empty($params['secure']),
            !empty($params['httponly'])
        );
    }

    session_destroy();
}

function cb_redirect($path)
{
    header('Location: ' . cb_url($path));
    exit;
}

function cb_get_auth()
{
    cb_boot_session();
    if (!isset($_SESSION['cliente_auth']) || !is_array($_SESSION['cliente_auth'])) {
        return null;
    }

    return $_SESSION['cliente_auth'];
}

function cb_is_logged_in()
{
    $auth = cb_get_auth();
    return is_array($auth) && !empty($auth['ok']);
}

function cb_get_visual_config()
{
    $auth = cb_get_auth();
    $visual = is_array($auth) && isset($auth['config_visual']) && is_array($auth['config_visual'])
        ? $auth['config_visual']
        : [];

    return [
        'titulo_login' => (string) ($visual['titulo_login'] ?? CLIENTE_LOGIN_TITULO),
        'subtitulo_login' => (string) ($visual['subtitulo_login'] ?? CLIENTE_LOGIN_SUBTITULO),
        'color_primario' => cb_normalize_hex_color((string) ($visual['color_primario'] ?? CLIENTE_COLOR_PRIMARIO), CLIENTE_COLOR_PRIMARIO),
        'color_secundario' => cb_normalize_hex_color((string) ($visual['color_secundario'] ?? CLIENTE_COLOR_SECUNDARIO), CLIENTE_COLOR_SECUNDARIO),
        'id_archivo_logo' => $visual['id_archivo_logo'] ?? null,
        'id_archivo_favicon' => $visual['id_archivo_favicon'] ?? null,
        'id_archivo_fondo' => $visual['id_archivo_fondo'] ?? null,
    ];
}

function cb_normalize_hex_color($value, $fallback)
{
    $v = strtoupper(trim((string) $value));
    if (preg_match('/^#[0-9A-F]{6}$/', $v) === 1) {
        return $v;
    }

    $f = strtoupper(trim((string) $fallback));
    if (preg_match('/^#[0-9A-F]{6}$/', $f) === 1) {
        return $f;
    }

    return '#007BFF';
}

function cb_get_timeout_minutes()
{
    $auth = cb_get_auth();
    $timeout = 30;
    if (is_array($auth) && isset($auth['config_login']) && is_array($auth['config_login'])) {
        $timeout = (int) ($auth['config_login']['timeout_sesion_minutos'] ?? $timeout);
    }
    if ($timeout < 5) {
        $timeout = 5;
    }
    if ($timeout > 1440) {
        $timeout = 1440;
    }

    return $timeout;
}

