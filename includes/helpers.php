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
    if (isset($GLOBALS['CB_CLIENTE_VERSION_LABEL_OVERRIDE'])) {
        $override = trim((string) $GLOBALS['CB_CLIENTE_VERSION_LABEL_OVERRIDE']);
        if ($override !== '') {
            return $override;
        }
    }

    $label = trim((string) CLIENTE_VERSION_LABEL);
    if ($label === '') {
        $label = 'Cliente Base V1';
    }

    return $label;
}

function cb_normalize_login_button_text($value)
{
    $text = trim((string) $value);
    if ($text === '') {
        return '';
    }
    if (function_exists('mb_substr')) {
        $text = mb_substr($text, 0, 120, 'UTF-8');
    } else {
        $text = substr($text, 0, 120);
    }
    return trim($text);
}

function cb_normalize_icon_css($value)
{
    $icon = trim((string) $value);
    if ($icon === '') {
        return 'fas fa-link';
    }
    $icon = preg_replace('/[^A-Za-z0-9 _-]/', '', $icon);
    $icon = preg_replace('/\s+/', ' ', (string) $icon);
    $icon = trim((string) $icon);
    if ($icon === '') {
        return 'fas fa-link';
    }
    if (function_exists('mb_substr')) {
        $icon = mb_substr($icon, 0, 80, 'UTF-8');
    } else {
        $icon = substr($icon, 0, 80);
    }
    return trim((string) $icon);
}

function cb_default_login_botones()
{
    return [
        [
            'texto_boton' => 'Contactar a soporte',
            'icono_css' => 'fa fa-whatsapp',
            'url_destino' => 'https://wa.me/51964881841?text=Hola%2C%20necesito%20apoyo%20del%20%C3%A1rea%20de%20Soporte.',
            'orden' => 1,
        ],
        [
            'texto_boton' => 'Recuperar contraseÃ±a',
            'icono_css' => 'fa fa-unlock-alt',
            'url_destino' => 'https://wa.me/51964881841?text=Hola%2C%20quiero%20recuperar%20mi%20contrase%C3%B1a%2C%20mi%20DNI%20y%2Fo%20nombre%20completo%20es%3A',
            'orden' => 2,
        ],
    ];
}

function cb_normalize_login_botones($rawButtons, array $fallbackButtons = [])
{
    $safe = [];
    $source = is_array($rawButtons) ? $rawButtons : [];
    foreach ($source as $item) {
        if (!is_array($item)) {
            continue;
        }
        $url = trim((string) ($item['url_destino'] ?? ''));
        if (!cb_is_valid_remote_asset_url($url)) {
            continue;
        }
        $safe[] = [
            'texto_boton' => cb_normalize_login_button_text($item['texto_boton'] ?? ''),
            'icono_css' => cb_normalize_icon_css($item['icono_css'] ?? ''),
            'url_destino' => $url,
            'orden' => (int) ($item['orden'] ?? 0),
        ];
    }

    usort($safe, static function ($a, $b) {
        $oa = (int) ($a['orden'] ?? 0);
        $ob = (int) ($b['orden'] ?? 0);
        if ($oa === $ob) {
            return 0;
        }
        return ($oa < $ob) ? -1 : 1;
    });

    if ($safe) {
        return array_values($safe);
    }

    $fallback = [];
    foreach ($fallbackButtons as $item) {
        if (!is_array($item)) {
            continue;
        }
        $url = trim((string) ($item['url_destino'] ?? ''));
        if (!cb_is_valid_remote_asset_url($url)) {
            continue;
        }
        $fallback[] = [
            'texto_boton' => cb_normalize_login_button_text($item['texto_boton'] ?? ''),
            'icono_css' => cb_normalize_icon_css($item['icono_css'] ?? ''),
            'url_destino' => $url,
            'orden' => (int) ($item['orden'] ?? 0),
        ];
    }

    return array_values($fallback);
}

function cb_default_visual_config()
{
    return [
        'titulo_login' => (string) CLIENTE_LOGIN_TITULO,
        'subtitulo_login' => (string) CLIENTE_LOGIN_SUBTITULO,
        'titulo_sistema_cliente' => (string) CLIENTE_NOMBRE,
        'footer_texto' => '',
        'footer_version_label' => cb_cliente_version_label(),
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
        'id_archivo_sidebar_cover' => null,
        'id_archivo_avatar_default' => null,
        'assets' => [
            'favicon_url' => (string) CLIENTE_FAVICON_PATH,
            'logo_url' => (string) CLIENTE_LOGO_PATH,
            'login_bg_url' => (string) CLIENTE_LOGIN_BG_PATH,
            'sidebar_cover_url' => (string) CLIENTE_LOGIN_BG_PATH,
            'carrusel' => ((bool) CLIENTE_LOGIN_CARRUSEL_ACTIVO) ? [
                (string) CLIENTE_LOGIN_CARRUSEL_1_PATH,
                (string) CLIENTE_LOGIN_CARRUSEL_2_PATH,
                (string) CLIENTE_LOGIN_CARRUSEL_3_PATH,
            ] : [],
            'avatar_default_url' => (string) CLIENTE_AVATAR_DEFAULT_PATH,
            'empty_state_url' => (string) CLIENTE_EMPTY_STATE_PATH,
        ],
        'login_botones' => cb_default_login_botones(),
        'preview' => [
            'login_preview_ready' => false,
            'shell_preview_ready' => false,
        ],
    ];
}

function cb_visual_cache_path()
{
    $fingerprint = hash('sha256', trim((string) API_BASE_URL) . '|' . trim((string) SERVICIO_CODIGO) . '|' . cb_local_domain());
    return __DIR__ . '/../storage/cache/config_visual_' . $fingerprint . '.json';
}

function cb_visual_asset_sync_enabled()
{
    return defined('CLIENTE_VISUAL_ASSET_SYNC_ACTIVO') && CLIENTE_VISUAL_ASSET_SYNC_ACTIVO;
}

function cb_visual_assets_root_dir()
{
    $configured = defined('CLIENTE_VISUAL_ASSET_SYNC_DIR')
        ? trim((string) CLIENTE_VISUAL_ASSET_SYNC_DIR)
        : 'storage/visual_assets';
    if (!cb_is_safe_asset_path($configured)) {
        $configured = 'storage/visual_assets';
    }

    return rtrim(__DIR__ . '/../' . $configured, '/\\');
}

function cb_visual_assets_root_rel()
{
    $configured = defined('CLIENTE_VISUAL_ASSET_SYNC_DIR')
        ? trim((string) CLIENTE_VISUAL_ASSET_SYNC_DIR)
        : 'storage/visual_assets';
    if (!cb_is_safe_asset_path($configured)) {
        $configured = 'storage/visual_assets';
    }

    return trim(str_replace('\\', '/', $configured), '/');
}

function cb_visual_assets_fingerprint()
{
    return hash('sha256', trim((string) API_BASE_URL) . '|' . trim((string) SERVICIO_CODIGO) . '|' . cb_local_domain());
}

function cb_visual_assets_manifest_path()
{
    return cb_visual_assets_root_dir() . '/' . cb_visual_assets_fingerprint() . '/manifest.json';
}

function cb_visual_assets_manifest_read()
{
    $path = cb_visual_assets_manifest_path();
    if (!is_file($path) || !is_readable($path)) {
        return [];
    }
    $raw = @file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function cb_visual_assets_manifest_write(array $manifest)
{
    $path = cb_visual_assets_manifest_path();
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return false;
    }
    if (!is_writable($dir)) {
        return false;
    }
    $json = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if (!is_string($json) || $json === '') {
        return false;
    }

    return @file_put_contents($path, $json, LOCK_EX) !== false;
}

function cb_visual_asset_extension_from_url($url, $contentType = '')
{
    $path = (string) (parse_url((string) $url, PHP_URL_PATH) ?: '');
    $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico'];
    if (in_array($ext, $allowed, true)) {
        return $ext;
    }

    $type = strtolower(trim((string) $contentType));
    if (strpos($type, 'image/jpeg') !== false) {
        return 'jpg';
    }
    if (strpos($type, 'image/png') !== false) {
        return 'png';
    }
    if (strpos($type, 'image/gif') !== false) {
        return 'gif';
    }
    if (strpos($type, 'image/webp') !== false) {
        return 'webp';
    }
    if (strpos($type, 'image/svg') !== false) {
        return 'svg';
    }
    if (strpos($type, 'image/x-icon') !== false || strpos($type, 'image/vnd.microsoft.icon') !== false) {
        return 'ico';
    }

    return '';
}

function cb_visual_asset_download($url, $targetPath, &$contentType = '')
{
    $url = trim((string) $url);
    if (!cb_is_valid_remote_asset_url($url)) {
        return false;
    }

    $maxBytes = defined('CLIENTE_VISUAL_ASSET_SYNC_MAX_BYTES') ? (int) CLIENTE_VISUAL_ASSET_SYNC_MAX_BYTES : 5242880;
    if ($maxBytes < 1024) {
        $maxBytes = 5242880;
    }

    $body = false;
    $contentType = '';
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => 'LIVP-SEGUROS VisualAssetSync',
            CURLOPT_HTTPHEADER => ['Accept: image/*,*/*;q=0.8'],
        ]);
        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        if ($httpCode < 200 || $httpCode >= 300) {
            return false;
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Accept: image/*,*/*;q=0.8\r\nUser-Agent: LIVP-SEGUROS VisualAssetSync\r\n",
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $headerLine) {
                if (stripos((string) $headerLine, 'content-type:') === 0) {
                    $contentType = trim(substr((string) $headerLine, 13));
                    break;
                }
            }
        }
    }

    if (!is_string($body) || $body === '' || strlen($body) > $maxBytes) {
        return false;
    }

    $dir = dirname($targetPath);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return false;
    }
    if (!is_writable($dir)) {
        return false;
    }

    return @file_put_contents($targetPath, $body, LOCK_EX) !== false;
}

function cb_visual_asset_sync_one($key, $url, $assetVersion, array &$manifest)
{
    $url = trim((string) $url);
    if (!cb_is_valid_remote_asset_url($url)) {
        return $url;
    }

    $assetVersion = trim((string) $assetVersion);
    if ($assetVersion === '') {
        $assetVersion = hash('sha256', $url);
    }

    $entries = is_array($manifest['assets'] ?? null) ? $manifest['assets'] : [];
    $entry = is_array($entries[$key] ?? null) ? $entries[$key] : [];
    $existingRel = trim((string) ($entry['local_rel'] ?? ''));
    $existingAbs = $existingRel !== '' && cb_is_safe_asset_path($existingRel) ? (__DIR__ . '/../' . $existingRel) : '';
    if (
        trim((string) ($manifest['asset_version'] ?? '')) === $assetVersion
        && trim((string) ($entry['remote_url'] ?? '')) === $url
        && $existingAbs !== ''
        && is_file($existingAbs)
    ) {
        return $existingRel;
    }

    $contentType = '';
    $baseDir = cb_visual_assets_root_dir() . '/' . cb_visual_assets_fingerprint() . '/assets';
    $baseRel = cb_visual_assets_root_rel() . '/' . cb_visual_assets_fingerprint() . '/assets';
    $safeKey = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $key);
    $tmpPath = $baseDir . '/' . $safeKey . '_' . substr(hash('sha256', $assetVersion . '|' . $url), 0, 16) . '.tmp';

    if (!cb_visual_asset_download($url, $tmpPath, $contentType)) {
        if ($existingAbs !== '' && is_file($existingAbs)) {
            return $existingRel;
        }
        return $url;
    }

    $ext = cb_visual_asset_extension_from_url($url, $contentType);
    if ($ext === '') {
        @unlink($tmpPath);
        return $url;
    }

    $finalName = $safeKey . '_' . substr(hash('sha256', $assetVersion . '|' . $url), 0, 16) . '.' . $ext;
    $finalAbs = $baseDir . '/' . $finalName;
    $finalRel = $baseRel . '/' . $finalName;
    if (!@rename($tmpPath, $finalAbs)) {
        @unlink($tmpPath);
        return $url;
    }

    $manifest['assets'][$key] = [
        'remote_url' => $url,
        'local_rel' => $finalRel,
        'content_type' => $contentType,
        'synced_at' => time(),
    ];

    return $finalRel;
}

function cb_apply_local_visual_assets(array $visual)
{
    if (!cb_visual_asset_sync_enabled() || !is_array($visual['assets'] ?? null)) {
        return $visual;
    }

    $assets = $visual['assets'];
    $assetVersion = trim((string) ($visual['_asset_version'] ?? ''));
    if ($assetVersion === '') {
        $assetsJson = json_encode($assets, JSON_UNESCAPED_SLASHES);
        $assetVersion = hash('sha256', is_string($assetsJson) ? $assetsJson : '');
    }

    $manifest = cb_visual_assets_manifest_read();
    if (!is_array($manifest['assets'] ?? null)) {
        $manifest['assets'] = [];
    }
    $manifest['asset_version'] = $assetVersion;
    $manifest['servicio_codigo'] = (string) SERVICIO_CODIGO;
    $manifest['dominio'] = cb_local_domain();

    $scalarKeys = ['favicon_url', 'logo_url', 'login_bg_url', 'sidebar_cover_url', 'avatar_default_url'];
    foreach ($scalarKeys as $assetKey) {
        if (isset($assets[$assetKey]) && cb_is_valid_remote_asset_url((string) $assets[$assetKey])) {
            $assets[$assetKey] = cb_visual_asset_sync_one($assetKey, (string) $assets[$assetKey], $assetVersion, $manifest);
        }
    }

    if (isset($assets['carrusel']) && is_array($assets['carrusel'])) {
        $localCarrusel = [];
        foreach ($assets['carrusel'] as $idx => $url) {
            $key = 'carrusel_' . ((int) $idx + 1);
            $localCarrusel[] = cb_is_valid_remote_asset_url((string) $url)
                ? cb_visual_asset_sync_one($key, (string) $url, $assetVersion, $manifest)
                : (string) $url;
        }
        $assets['carrusel'] = $localCarrusel;
    }

    $manifest['updated_at'] = time();
    cb_visual_assets_manifest_write($manifest);
    $visual['assets'] = $assets;

    return $visual;
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
    $servicioRaw = is_array($data['servicio'] ?? null) ? $data['servicio'] : [];
    $visualRaw = is_array($data['visual'] ?? null) ? $data['visual'] : [];
    $assetsRaw = is_array($data['assets'] ?? null) ? $data['assets'] : [];
    $loginBotonesRaw = is_array($data['login_botones'] ?? null) ? $data['login_botones'] : [];
    $previewRaw = is_array($data['preview'] ?? null) ? $data['preview'] : [];
    $cacheRaw = is_array($data['cache'] ?? null) ? $data['cache'] : [];

    $normalized = $defaults;
    $normalized['titulo_login'] = trim((string) ($visualRaw['titulo_login'] ?? '')) !== ''
        ? trim((string) $visualRaw['titulo_login'])
        : $defaults['titulo_login'];
    $normalized['subtitulo_login'] = (string) ($visualRaw['subtitulo_login'] ?? $defaults['subtitulo_login']);
    $footerTexto = trim((string) ($visualRaw['footer_texto'] ?? ''));
    $footerVersion = trim((string) ($visualRaw['footer_version_label'] ?? ''));
    $tituloSistema = trim((string) ($visualRaw['titulo_sistema_cliente'] ?? ''));
    if ($tituloSistema === '') {
        $tituloSistema = trim((string) ($servicioRaw['nombre'] ?? ''));
    }
    if ($tituloSistema === '') {
        $tituloSistema = trim((string) CLIENTE_NOMBRE);
    }
    $normalized['footer_texto'] = $footerTexto;
    $normalized['footer_version_label'] = $footerVersion !== '' ? $footerVersion : cb_cliente_version_label();
    $normalized['titulo_sistema_cliente'] = $tituloSistema;

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
    $sidebarCoverUrl = trim((string) ($assetsRaw['sidebar_cover_url'] ?? ''));
    $avatarDefaultUrl = trim((string) ($assetsRaw['avatar_default_url'] ?? ''));
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
        'sidebar_cover_url' => cb_is_valid_remote_asset_url($sidebarCoverUrl)
            ? $sidebarCoverUrl
            : (cb_is_valid_remote_asset_url($loginBgUrl) ? $loginBgUrl : $defaults['assets']['sidebar_cover_url']),
        'carrusel' => $safeCarrusel ?: $defaults['assets']['carrusel'],
        'avatar_default_url' => cb_is_valid_remote_asset_url($avatarDefaultUrl)
            ? $avatarDefaultUrl
            : $defaults['assets']['avatar_default_url'],
        'empty_state_url' => $defaults['assets']['empty_state_url'],
    ];
    $normalized['login_botones'] = cb_normalize_login_botones($loginBotonesRaw, $defaults['login_botones']);
    $normalized['preview'] = [
        'login_preview_ready' => !empty($previewRaw['login_preview_ready']),
        'shell_preview_ready' => !empty($previewRaw['shell_preview_ready']),
    ];

    $ttlSeconds = (int) ($cacheRaw['ttl_seconds'] ?? 0);
    if ($ttlSeconds < 60) {
        $ttlSeconds = (int) CLIENTE_VISUAL_CACHE_TTL_DEFAULT;
    }
    if ($ttlSeconds < 60) {
        $ttlSeconds = 600;
    }
    $normalized['_cache_ttl_seconds'] = $ttlSeconds;
    $normalized['_asset_version'] = trim((string) ($cacheRaw['asset_version'] ?? ''));

    return $normalized;
}

function cb_get_remote_visual_config()
{
    if (!defined('CLIENTE_VISUAL_REMOTO_ACTIVO') || !CLIENTE_VISUAL_REMOTO_ACTIVO) {
        return ['ok' => false, 'code' => 'remoto_desactivado', 'visual' => null];
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

function cb_normalize_cached_visual_config(array $cachedVisual)
{
    return cb_normalize_remote_visual_config([
        'visual' => $cachedVisual,
        'assets' => (array) ($cachedVisual['assets'] ?? []),
        'login_botones' => (array) ($cachedVisual['login_botones'] ?? []),
        'preview' => (array) ($cachedVisual['preview'] ?? []),
        'cache' => [
            'ttl_seconds' => (int) CLIENTE_VISUAL_CACHE_TTL_DEFAULT,
            'asset_version' => (string) ($cachedVisual['_asset_version'] ?? ''),
        ],
    ]);
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
            $cachedVisual = cb_normalize_cached_visual_config($cache['visual']);
            return is_array($cachedVisual) ? cb_apply_local_visual_assets($cachedVisual) : null;
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
        return cb_apply_local_visual_assets($visualToCache);
    }

    if ($cacheEnabled && !empty($cache['ok']) && !empty($cache['is_stale']) && is_array($cache['visual'] ?? null)) {
        $cachedVisual = cb_normalize_cached_visual_config($cache['visual']);
        return is_array($cachedVisual) ? cb_apply_local_visual_assets($cachedVisual) : null;
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
        if ($key === '_cache_ttl_seconds' || $key === '_asset_version') {
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
            'login_botones' => (array) ($sessionVisual['login_botones'] ?? []),
            'preview' => (array) ($sessionVisual['preview'] ?? []),
            'cache' => [
                'ttl_seconds' => (int) CLIENTE_VISUAL_CACHE_TTL_DEFAULT,
                'asset_version' => (string) ($sessionVisual['_asset_version'] ?? ''),
            ],
        ]);
        if (is_array($normalizedSession)) {
            $effective = cb_merge_visual_config($effective, $normalizedSession);
        }
    }

    if ($preferRemote) {
        $remoteVisualResult = cb_get_remote_visual_config();
        if (!empty($remoteVisualResult['ok']) && is_array($remoteVisualResult['visual'] ?? null)) {
            $remoteVisual = $remoteVisualResult['visual'];
            $ttl = (int) ($remoteVisual['_cache_ttl_seconds'] ?? CLIENTE_VISUAL_CACHE_TTL_DEFAULT);
            $visualToCache = $remoteVisual;
            unset($visualToCache['_cache_ttl_seconds']);
            if (defined('CLIENTE_VISUAL_CACHE_ACTIVO') && CLIENTE_VISUAL_CACHE_ACTIVO) {
                cb_write_visual_cache($visualToCache, $ttl);
            }

            $remoteVisual = cb_apply_local_visual_assets($remoteVisual);
            if (is_array($remoteVisual)) {
                $effective = cb_merge_visual_config($effective, $remoteVisual);
            }
        } else {
            $remoteVisual = cb_get_remote_visual_config_cached();
            if (is_array($remoteVisual)) {
                $effective = cb_merge_visual_config($effective, $remoteVisual);
            }
        }
    }

    return $effective;
}

function cb_get_visual_config()
{
    return cb_get_effective_visual_config(true);
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
