<?php

declare(strict_types=1);

/*
 * Configuración técnica del núcleo de correos.
 * No se edita desde la interfaz: la pantalla solo administra remitente, clave Zoho,
 * destinatarios de prueba, copia, asunto y mensaje de prueba.
 */

if (!defined('SEG_CORREO_SMTP_HOST')) {
    define('SEG_CORREO_SMTP_HOST', 'smtppro.zoho.com');
}

if (!defined('SEG_CORREO_SMTP_PORT')) {
    define('SEG_CORREO_SMTP_PORT', 587);
}

if (!defined('SEG_CORREO_SMTP_SECURE')) {
    define('SEG_CORREO_SMTP_SECURE', 'tls');
}

if (!defined('SEG_CORREO_SMTP_TIMEOUT')) {
    define('SEG_CORREO_SMTP_TIMEOUT', 25);
}

if (!defined('SEG_CORREO_DEFAULT_FROM_NAME')) {
    define('SEG_CORREO_DEFAULT_FROM_NAME', 'Broker Seguros - Helmut Leiva');
}

if (!defined('SEG_CORREO_DEFAULT_TEST_SUBJECT')) {
    define('SEG_CORREO_DEFAULT_TEST_SUBJECT', 'Broker Seguros — Prueba de notificaciones por correo');
}

if (!defined('SEG_CORREO_DEFAULT_TEST_MESSAGE')) {
    define('SEG_CORREO_DEFAULT_TEST_MESSAGE', 'Este es un correo de prueba enviado desde Broker Seguros para verificar que la configuración de notificaciones funciona correctamente.');
}

/*
 * Llave maestra para cifrar/descifrar la clave de aplicación Zoho guardada en MySQL.
 * Es una llave nueva del módulo; no modifica las claves actuales de base de datos.
 * Si la cambias después de guardar la clave Zoho, deberás volver a registrar la clave Zoho desde la interfaz.
 */
if (!defined('SEG_CORREO_ENCRYPTION_KEY')) {
    define('SEG_CORREO_ENCRYPTION_KEY', 'broker-seguros-correo-v1-7ca0f939f5e24cf28f3f10ef63a81f9c');
}
