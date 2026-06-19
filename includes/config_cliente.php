<?php
// Configuración base del cliente externo (V1).
// Usa placeholders: reemplaza estos valores en cada instalación real.

if (!defined('API_BASE_URL')) {
    define('API_BASE_URL', 'https://luigisistemas.net.pe/');
}
if (!defined('API_LOGIN_ENDPOINT')) {
    define('API_LOGIN_ENDPOINT', 'sistema/api/serv/login.php');
}
if (!defined('API_CONFIG_VISUAL_ENDPOINT')) {
    define('API_CONFIG_VISUAL_ENDPOINT', 'sistema/api/serv/config_visual.php');
}
if (!defined('API_KEY')) {
    define('API_KEY', 'lsis_pk_537f6d10f50133b3baa5e6f8739913802a03');
}
if (!defined('API_SECRET')) {
    define('API_SECRET', 'lsis_sk_b87bd097897cc73537e8bce2b2b98f1ef1b6908d36f71e03');
}
if (!defined('SERVICIO_CODIGO')) {
    define('SERVICIO_CODIGO', 'broker_seguros');
}
if (!defined('DOMINIO_LOCAL')) {
    define('DOMINIO_LOCAL', 'brokerseguros.net.pe');
}
if (!defined('CLIENTE_NOMBRE')) {
    define('CLIENTE_NOMBRE', 'Broker Seguros');
}
if (!defined('CLIENTE_BASE_URL')) {
    define('CLIENTE_BASE_URL', '');
}
if (!defined('CLIENTE_TIMEZONE')) {
    define('CLIENTE_TIMEZONE', 'America/Lima');
}
if (!defined('CLIENTE_DB_TIMEZONE')) {
    define('CLIENTE_DB_TIMEZONE', '-05:00');
}
if (!defined('CLIENTE_DEBUG')) {
    define('CLIENTE_DEBUG', false);
}
if (!defined('CLIENTE_VERSION_LABEL')) {
    define('CLIENTE_VERSION_LABEL', 'Broker Seguros');
}
if (!defined('CLIENTE_VISUAL_REMOTO_ACTIVO')) {
    define('CLIENTE_VISUAL_REMOTO_ACTIVO', true);
}
if (!defined('CLIENTE_VISUAL_CACHE_ACTIVO')) {
    define('CLIENTE_VISUAL_CACHE_ACTIVO', true);
}
if (!defined('CLIENTE_VISUAL_CACHE_TTL_DEFAULT')) {
    define('CLIENTE_VISUAL_CACHE_TTL_DEFAULT', 600);
}
if (!defined('CLIENTE_VISUAL_CACHE_STALE_TTL')) {
    define('CLIENTE_VISUAL_CACHE_STALE_TTL', 86400);
}
if (!defined('CLIENTE_VISUAL_ASSET_SYNC_ACTIVO')) {
    define('CLIENTE_VISUAL_ASSET_SYNC_ACTIVO', true);
}
if (!defined('CLIENTE_VISUAL_ASSET_SYNC_DIR')) {
    define('CLIENTE_VISUAL_ASSET_SYNC_DIR', 'storage/visual_assets');
}
if (!defined('CLIENTE_VISUAL_ASSET_SYNC_MAX_BYTES')) {
    define('CLIENTE_VISUAL_ASSET_SYNC_MAX_BYTES', 5242880);
}

// Fallback visual local para login/dashboard.
if (!defined('CLIENTE_LOGIN_TITULO')) {
    define('CLIENTE_LOGIN_TITULO', 'Bienvenido');
}
if (!defined('CLIENTE_LOGIN_SUBTITULO')) {
    define('CLIENTE_LOGIN_SUBTITULO', 'Ingresa tus credenciales');
}
if (!defined('CLIENTE_COLOR_PRIMARIO')) {
    define('CLIENTE_COLOR_PRIMARIO', '#007BFF');
}
if (!defined('CLIENTE_COLOR_SECUNDARIO')) {
    define('CLIENTE_COLOR_SECUNDARIO', '#6C757D');
}

// Assets default locales (rutas relativas internas).
if (!defined('CLIENTE_FAVICON_PATH')) {
    define('CLIENTE_FAVICON_PATH', 'assets/default/branding/favicon.svg');
}
if (!defined('CLIENTE_LOGO_PATH')) {
    define('CLIENTE_LOGO_PATH', 'assets/default/branding/logo_cliente.svg');
}
if (!defined('CLIENTE_LOGIN_BG_PATH')) {
    define('CLIENTE_LOGIN_BG_PATH', 'assets/default/login/login_fondo.svg');
}
if (!defined('CLIENTE_LOGIN_CARRUSEL_ACTIVO')) {
    define('CLIENTE_LOGIN_CARRUSEL_ACTIVO', true);
}
if (!defined('CLIENTE_LOGIN_CARRUSEL_1_PATH')) {
    define('CLIENTE_LOGIN_CARRUSEL_1_PATH', 'assets/default/login/carrusel_1.svg');
}
if (!defined('CLIENTE_LOGIN_CARRUSEL_2_PATH')) {
    define('CLIENTE_LOGIN_CARRUSEL_2_PATH', 'assets/default/login/carrusel_2.svg');
}
if (!defined('CLIENTE_LOGIN_CARRUSEL_3_PATH')) {
    define('CLIENTE_LOGIN_CARRUSEL_3_PATH', 'assets/default/login/carrusel_3.svg');
}
if (!defined('CLIENTE_AVATAR_DEFAULT_PATH')) {
    define('CLIENTE_AVATAR_DEFAULT_PATH', 'assets/default/ui/avatar_default.svg');
}
if (!defined('CLIENTE_EMPTY_STATE_PATH')) {
    define('CLIENTE_EMPTY_STATE_PATH', 'assets/default/ui/empty_state.svg');
}

// Base de datos local de Broker Seguros.
// COMPLETAR MANUALMENTE host, nombre, usuario y clave antes de usar modulos con BD.
if (!defined('CLIENTE_DB_ACTIVA')) {
    define('CLIENTE_DB_ACTIVA', true);
}
if (!defined('CLIENTE_DB_HOST')) {
    define('CLIENTE_DB_HOST', 'root'); // COMPLETAR MANUALMENTE
}
if (!defined('CLIENTE_DB_NAME')) {
    define('CLIENTE_DB_NAME', 'u517204426_in5vRANce'); // COMPLETAR MANUALMENTE
}
if (!defined('CLIENTE_DB_USER')) {
    define('CLIENTE_DB_USER', 'u517204426_sal35MAN'); // COMPLETAR MANUALMENTE
}
if (!defined('CLIENTE_DB_PASS')) {
    define('CLIENTE_DB_PASS', 'E@0hWJCp!yY'); // COMPLETAR MANUALMENTE
}
if (!defined('CLIENTE_DB_CHARSET')) {
    define('CLIENTE_DB_CHARSET', 'utf8mb4');
}

if (function_exists('date_default_timezone_set')) {
    @date_default_timezone_set(CLIENTE_TIMEZONE);
}
