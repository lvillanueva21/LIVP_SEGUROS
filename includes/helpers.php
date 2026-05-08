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

function cb_random_token($bytes = 32)
{
    $bytes = (int) $bytes;
    if ($bytes < 16) {
        $bytes = 16;
    }
    if ($bytes > 128) {
        $bytes = 128;
    }

    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes($bytes));
    }

    if (function_exists('openssl_random_pseudo_bytes')) {
        $secure = false;
        $raw = openssl_random_pseudo_bytes($bytes, $secure);
        if ($raw !== false && $secure) {
            return bin2hex($raw);
        }
    }

    return hash('sha256', uniqid((string) mt_rand(), true) . microtime(true));
}

function cb_boot_session()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.cookie_samesite', 'Lax');
    @ini_set('session.use_strict_mode', '1');

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

function cb_is_valid_remote_asset_url($url)
{
    $url = trim((string) $url);
    if ($url === '') {
        return false;
    }

    if (preg_match('/\s/', $url) === 1) {
        return false;
    }

    if (preg_match('/^https?:\/\/[^\s]+$/i', $url) !== 1) {
        return false;
    }

    $parsed = @parse_url($url);
    if (!is_array($parsed)) {
        return false;
    }

    $host = trim((string) ($parsed['host'] ?? ''));
    if ($host === '') {
        return false;
    }

    return true;
}

function cb_is_safe_asset_path($path)
{
    $path = trim((string) $path);
    if ($path === '') {
        return false;
    }
    if (preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*:\/\//', $path) === 1) {
        return false;
    }
    if (strpos($path, '..') !== false) {
        return false;
    }
    if (strpos($path, '\\') !== false) {
        return false;
    }
    if (strpos($path, "\0") !== false) {
        return false;
    }
    if (strpos($path, '/') === 0) {
        return false;
    }
    return preg_match('/^[A-Za-z0-9_\-\.\/]+$/', $path) === 1;
}

function cb_asset_url($path, $fallback = '')
{
    $path = trim((string) $path);
    if (cb_is_valid_remote_asset_url($path)) {
        return $path;
    }
    if (cb_is_safe_asset_path($path)) {
        return cb_url($path);
    }

    $fallback = trim((string) $fallback);
    if (cb_is_valid_remote_asset_url($fallback)) {
        return $fallback;
    }
    if ($fallback !== '' && cb_is_safe_asset_path($fallback)) {
        return cb_url($fallback);
    }

    return cb_url('assets/default/ui/empty_state.svg');
}

function cb_config_asset_url($constantValue, $fallback)
{
    return cb_asset_url((string) $constantValue, (string) $fallback);
}

function cb_cliente_version_label()
{
    $label = trim((string) CLIENTE_VERSION_LABEL);
    if ($label === '') {
        $label = 'Cliente Base V1';
    }

    return $label;
}

function cb_default_visual_config()
{
    return [
        'titulo_login' => (string) CLIENTE_LOGIN_TITULO,
        'subtitulo_login' => (string) CLIENTE_LOGIN_SUBTITULO,
        'color_primario' => cb_normalize_hex_color(CLIENTE_COLOR_PRIMARIO, '#007BFF'),
        'color_secundario' => cb_normalize_hex_color(CLIENTE_COLOR_SECUNDARIO, '#6C757D'),
        'color_header_bg' => '#343A40',
        'color_header_text' => '#FFFFFF',
        'color_sidebar_bg' => '#343A40',
        'color_sidebar_text' => '#FFFFFF',
        'color_sidebar_brand_bg' => '#343A40',
        'color_sidebar_brand_text' => '#FFFFFF',
        'color_sidebar_item_hover_bg' => '#1F2D3D',
        'color_sidebar_item_hover_text' => '#FFFFFF',
        'color_sidebar_item_active_bg' => cb_normalize_hex_color(CLIENTE_COLOR_PRIMARIO, '#007BFF'),
        'color_sidebar_item_active_text' => '#FFFFFF',
        'color_sidebar_group_active_bg' => '#0069D9',
        'color_sidebar_group_active_text' => '#FFFFFF',
        'color_login_bg' => '#FFFFFF',
        'color_login_saludo_text' => '#212529',
        'id_archivo_logo' => null,
        'id_archivo_favicon' => null,
        'id_archivo_fondo' => null,
        'assets' => [
            'favicon_url' => (string) CLIENTE_FAVICON_PATH,
            'logo_url' => (string) CLIENTE_LOGO_PATH,
            'login_bg_url' => (string) CLIENTE_LOGIN_BG_PATH,
            'carrusel' => ((bool) CLIENTE_LOGIN_CARRUSEL_ACTIVO) ? [
                (string) CLIENTE_LOGIN_CARRUSEL_1_PATH,
                (string) CLIENTE_LOGIN_CARRUSEL_2_PATH,
                (string) CLIENTE_LOGIN_CARRUSEL_3_PATH,
            ] : [],
            'avatar_default_url' => (string) CLIENTE_AVATAR_DEFAULT_PATH,
            'empty_state_url' => (string) CLIENTE_EMPTY_STATE_PATH,
        ],
    ];
}

function cb_visual_cache_path()
{
    $fingerprint = hash('sha256', trim((string) API_BASE_URL) . '|' . trim((string) SERVICIO_CODIGO) . '|' . cb_local_domain());
    return __DIR__ . '/../storage/cache/config_visual_' . $fingerprint . '.json';
}

function cb_write_visual_cache(array $normalizedVisual, $ttlSeconds)
{
    if (!defined('CLIENTE_VISUAL_CACHE_ACTIVO') || !CLIENTE_VISUAL_CACHE_ACTIVO) {
        return false;
    }

    $ttlSeconds = (int) $ttlSeconds;
    if ($ttlSeconds < 60) {
        $ttlSeconds = (int) CLIENTE_VISUAL_CACHE_TTL_DEFAULT;
    }
    if ($ttlSeconds < 60) {
        $ttlSeconds = 600;
    }

    $staleTtl = (int) CLIENTE_VISUAL_CACHE_STALE_TTL;
    if ($staleTtl < 60) {
        $staleTtl = 86400;
    }

    $cachePath = cb_visual_cache_path();
    $cacheDir = dirname($cachePath);
    if (!is_dir($cacheDir) && !@mkdir($cacheDir, 0755, true)) {
        return false;
    }
    if (!is_writable($cacheDir)) {
        return false;
    }

    $now = time();
    $payload = [
        'created_at' => $now,
        'expires_at' => $now + $ttlSeconds,
        'stale_until' => $now + $staleTtl,
        'visual' => $normalizedVisual,
    ];

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json) || $json === '') {
        return false;
    }

    return @file_put_contents($cachePath, $json, LOCK_EX) !== false;
}

function cb_read_visual_cache()
{
    if (!defined('CLIENTE_VISUAL_CACHE_ACTIVO') || !CLIENTE_VISUAL_CACHE_ACTIVO) {
        return [
            'ok' => false,
            'is_fresh' => false,
            'is_stale' => false,
            'visual' => null,
        ];
    }

    $cachePath = cb_visual_cache_path();
    if (!is_file($cachePath) || !is_readable($cachePath)) {
        return [
            'ok' => false,
            'is_fresh' => false,
            'is_stale' => false,
            'visual' => null,
        ];
    }

    $raw = @file_get_contents($cachePath);
    if (!is_string($raw) || trim($raw) === '') {
        return [
            'ok' => false,
            'is_fresh' => false,
            'is_stale' => false,
            'visual' => null,
        ];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [
            'ok' => false,
            'is_fresh' => false,
            'is_stale' => false,
            'visual' => null,
        ];
    }

    $visual = is_array($decoded['visual'] ?? null) ? $decoded['visual'] : null;
    if (!$visual) {
        return [
            'ok' => false,
            'is_fresh' => false,
            'is_stale' => false,
            'visual' => null,
        ];
    }

    $now = time();
    $expiresAt = (int) ($decoded['expires_at'] ?? 0);
    $staleUntil = (int) ($decoded['stale_until'] ?? 0);

    return [
        'ok' => true,
        'is_fresh' => $expiresAt > $now,
        'is_stale' => $staleUntil > $now,
        'visual' => $visual,
    ];
}

function cb_normalize_remote_visual_config($data)
{
    if (!is_array($data)) {
        return null;
    }

    $defaults = cb_default_visual_config();
    $visualRaw = is_array($data['visual'] ?? null) ? $data['visual'] : [];
    $assetsRaw = is_array($data['assets'] ?? null) ? $data['assets'] : [];
    $cacheRaw = is_array($data['cache'] ?? null) ? $data['cache'] : [];

    $normalized = $defaults;
    $normalized['titulo_login'] = trim((string) ($visualRaw['titulo_login'] ?? '')) !== ''
        ? trim((string) $visualRaw['titulo_login'])
        : $defaults['titulo_login'];
    $normalized['subtitulo_login'] = (string) ($visualRaw['subtitulo_login'] ?? $defaults['subtitulo_login']);

    $colorFields = [
        'color_primario',
        'color_secundario',
        'color_header_bg',
        'color_header_text',
        'color_sidebar_bg',
        'color_sidebar_text',
        'color_sidebar_brand_bg',
        'color_sidebar_brand_text',
        'color_sidebar_item_hover_bg',
        'color_sidebar_item_hover_text',
        'color_sidebar_item_active_bg',
        'color_sidebar_item_active_text',
        'color_sidebar_group_active_bg',
        'color_sidebar_group_active_text',
        'color_login_bg',
        'color_login_saludo_text',
    ];
    foreach ($colorFields as $field) {
        $normalized[$field] = cb_normalize_hex_color((string) ($visualRaw[$field] ?? ''), $defaults[$field]);
    }

    $faviconUrl = trim((string) ($assetsRaw['favicon_url'] ?? ''));
    $logoUrl = trim((string) ($assetsRaw['logo_url'] ?? ''));
    $loginBgUrl = trim((string) ($assetsRaw['login_bg_url'] ?? ''));
    $remoteCarrusel = isset($assetsRaw['carrusel']) && is_array($assetsRaw['carrusel']) ? $assetsRaw['carrusel'] : [];
    $safeCarrusel = [];
    foreach ($remoteCarrusel as $itemUrl) {
        $itemUrl = trim((string) $itemUrl);
        if ($itemUrl !== '' && cb_is_valid_remote_asset_url($itemUrl)) {
            $safeCarrusel[] = $itemUrl;
        }
    }

    $normalized['assets'] = [
        'favicon_url' => cb_is_valid_remote_asset_url($faviconUrl) ? $faviconUrl : $defaults['assets']['favicon_url'],
        'logo_url' => cb_is_valid_remote_asset_url($logoUrl) ? $logoUrl : $defaults['assets']['logo_url'],
        'login_bg_url' => cb_is_valid_remote_asset_url($loginBgUrl) ? $loginBgUrl : $defaults['assets']['login_bg_url'],
        'carrusel' => $safeCarrusel ?: $defaults['assets']['carrusel'],
        'avatar_default_url' => $defaults['assets']['avatar_default_url'],
        'empty_state_url' => $defaults['assets']['empty_state_url'],
    ];

    $ttlSeconds = (int) ($cacheRaw['ttl_seconds'] ?? 0);
    if ($ttlSeconds < 60) {
        $ttlSeconds = (int) CLIENTE_VISUAL_CACHE_TTL_DEFAULT;
    }
    if ($ttlSeconds < 60) {
        $ttlSeconds = 600;
    }
    $normalized['_cache_ttl_seconds'] = $ttlSeconds;

    return $normalized;
}

function cb_get_remote_visual_config()
{
    if (!defined('CLIENTE_VISUAL_REMOTO_ACTIVO') || !CLIENTE_VISUAL_REMOTO_ACTIVO) {
        return ['ok' => false, 'code' => 'remoto_inactivo', 'visual' => null];
    }
    if (!function_exists('cb_api_config_visual')) {
        return ['ok' => false, 'code' => 'cliente_api_no_disponible', 'visual' => null];
    }

    $apiResult = cb_api_config_visual();
    if (empty($apiResult['ok'])) {
        return ['ok' => false, 'code' => (string) ($apiResult['code'] ?? 'error'), 'visual' => null];
    }

    $normalized = cb_normalize_remote_visual_config(is_array($apiResult['data'] ?? null) ? $apiResult['data'] : []);
    if (!is_array($normalized)) {
        return ['ok' => false, 'code' => 'respuesta_invalida', 'visual' => null];
    }

    return ['ok' => true, 'code' => 'ok', 'visual' => $normalized];
}

function cb_get_remote_visual_config_cached()
{
    $cacheEnabled = (defined('CLIENTE_VISUAL_CACHE_ACTIVO') && CLIENTE_VISUAL_CACHE_ACTIVO);
    $remoteEnabled = (defined('CLIENTE_VISUAL_REMOTO_ACTIVO') && CLIENTE_VISUAL_REMOTO_ACTIVO);

    $cache = [
        'ok' => false,
        'is_fresh' => false,
        'is_stale' => false,
        'visual' => null,
    ];

    if ($cacheEnabled) {
        $cache = cb_read_visual_cache();
        if (!empty($cache['ok']) && !empty($cache['is_fresh']) && is_array($cache['visual'] ?? null)) {
            return cb_normalize_remote_visual_config([
                'visual' => $cache['visual'],
                'assets' => (array) ($cache['visual']['assets'] ?? []),
                'cache' => ['ttl_seconds' => (int) CLIENTE_VISUAL_CACHE_TTL_DEFAULT],
            ]);
        }
    }

    if (!$remoteEnabled) {
        return null;
    }

    $remote = cb_get_remote_visual_config();
    if (!empty($remote['ok']) && is_array($remote['visual'] ?? null)) {
        $ttl = (int) ($remote['visual']['_cache_ttl_seconds'] ?? CLIENTE_VISUAL_CACHE_TTL_DEFAULT);
        $visualToCache = $remote['visual'];
        unset($visualToCache['_cache_ttl_seconds']);
        if ($cacheEnabled) {
            cb_write_visual_cache($visualToCache, $ttl);
        }
        return $visualToCache;
    }

    if ($cacheEnabled && !empty($cache['ok']) && !empty($cache['is_stale']) && is_array($cache['visual'] ?? null)) {
        return cb_normalize_remote_visual_config([
            'visual' => $cache['visual'],
            'assets' => (array) ($cache['visual']['assets'] ?? []),
            'cache' => ['ttl_seconds' => (int) CLIENTE_VISUAL_CACHE_TTL_DEFAULT],
        ]);
    }

    return null;
}

function cb_merge_visual_config(array $base, array $override)
{
    $result = $base;
    foreach ($override as $key => $value) {
        if ($key === 'assets' && is_array($value) && is_array($result['assets'] ?? null)) {
            $result['assets'] = array_merge($result['assets'], $value);
            continue;
        }
        if ($key === '_cache_ttl_seconds') {
            continue;
        }
        $result[$key] = $value;
    }
    return $result;
}

function cb_get_effective_visual_config($preferRemote = false)
{
    $effective = cb_default_visual_config();

    $auth = cb_get_auth();
    $sessionVisual = is_array($auth) && isset($auth['config_visual']) && is_array($auth['config_visual'])
        ? $auth['config_visual']
        : null;
    if (is_array($sessionVisual)) {
        $normalizedSession = cb_normalize_remote_visual_config([
            'visual' => $sessionVisual,
            'assets' => (array) ($sessionVisual['assets'] ?? []),
            'cache' => ['ttl_seconds' => (int) CLIENTE_VISUAL_CACHE_TTL_DEFAULT],
        ]);
        if (is_array($normalizedSession)) {
            $effective = cb_merge_visual_config($effective, $normalizedSession);
        }
    }

    if ($preferRemote) {
        $remoteVisual = cb_get_remote_visual_config_cached();
        if (is_array($remoteVisual)) {
            $effective = cb_merge_visual_config($effective, $remoteVisual);
        }
    }

    return $effective;
}

function cb_get_visual_config()
{
    return cb_get_effective_visual_config(false);
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
