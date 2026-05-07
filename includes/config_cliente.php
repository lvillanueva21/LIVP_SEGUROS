<?php
// Configuración base del cliente externo (V1).
// Usa placeholders: reemplaza estos valores en cada instalación real.

if (!defined('API_BASE_URL')) {
    define('API_BASE_URL', 'https://tu-dominio-lsistemas.pe');
}
if (!defined('API_LOGIN_ENDPOINT')) {
    define('API_LOGIN_ENDPOINT', 'sistema/api/serv/login.php');
}
if (!defined('API_KEY')) {
    define('API_KEY', 'LSIS_API_KEY_AQUI');
}
if (!defined('API_SECRET')) {
    define('API_SECRET', 'LSIS_API_SECRET_AQUI');
}
if (!defined('SERVICIO_CODIGO')) {
    define('SERVICIO_CODIGO', 'servicio_cliente_demo');
}
if (!defined('DOMINIO_LOCAL')) {
    define('DOMINIO_LOCAL', '');
}
if (!defined('CLIENTE_NOMBRE')) {
    define('CLIENTE_NOMBRE', 'Cliente Base V1');
}
if (!defined('CLIENTE_BASE_URL')) {
    define('CLIENTE_BASE_URL', '');
}
if (!defined('CLIENTE_TIMEZONE')) {
    define('CLIENTE_TIMEZONE', 'America/Lima');
}
if (!defined('CLIENTE_DEBUG')) {
    define('CLIENTE_DEBUG', false);
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

// Base de datos local opcional para módulos del cliente.
if (!defined('CLIENTE_DB_ACTIVA')) {
    define('CLIENTE_DB_ACTIVA', false);
}
if (!defined('CLIENTE_DB_HOST')) {
    define('CLIENTE_DB_HOST', '127.0.0.1');
}
if (!defined('CLIENTE_DB_NAME')) {
    define('CLIENTE_DB_NAME', 'cliente_db');
}
if (!defined('CLIENTE_DB_USER')) {
    define('CLIENTE_DB_USER', 'cliente_user');
}
if (!defined('CLIENTE_DB_PASS')) {
    define('CLIENTE_DB_PASS', 'cliente_pass');
}
if (!defined('CLIENTE_DB_CHARSET')) {
    define('CLIENTE_DB_CHARSET', 'utf8mb4');
}

if (function_exists('date_default_timezone_set')) {
    @date_default_timezone_set(CLIENTE_TIMEZONE);
}

