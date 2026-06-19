<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/request_cliente.php';

function cb_cliente_normalize_codigo_pagina($value)
{
    $value = strtolower(trim((string) $value));
    return preg_match('/^[a-z0-9_-]+$/', $value) === 1 ? $value : '';
}

function cb_cliente_acciones_permitidas()
{
    return ['puede_ver', 'puede_crear', 'puede_editar', 'puede_eliminar'];
}

function cb_cliente_normalize_accion($accion)
{
    $accion = strtolower(trim((string) $accion));
    return in_array($accion, cb_cliente_acciones_permitidas(), true) ? $accion : '';
}

function cb_cliente_permisos()
{
    $auth = cb_get_auth();
    return is_array($auth) && is_array($auth['permisos'] ?? null) ? $auth['permisos'] : [];
}

function cb_cliente_permiso_pagina($codigoPagina)
{
    $codigoPagina = cb_cliente_normalize_codigo_pagina($codigoPagina);
    if ($codigoPagina === '') {
        return null;
    }

    $permisos = cb_cliente_permisos();
    return is_array($permisos[$codigoPagina] ?? null) ? $permisos[$codigoPagina] : null;
}

function cb_cliente_puede($codigoPagina, $accion)
{
    $codigoPagina = cb_cliente_normalize_codigo_pagina($codigoPagina);
    $accion = cb_cliente_normalize_accion($accion);
    if ($codigoPagina === '' || $accion === '') {
        return false;
    }

    $permiso = cb_cliente_permiso_pagina($codigoPagina);
    return is_array($permiso) && (int) ($permiso[$accion] ?? 0) === 1;
}

function cb_cliente_puede_ver_pagina($codigoPagina)
{
    return cb_cliente_puede($codigoPagina, 'puede_ver');
}

function cb_cliente_usuario_externo_id()
{
    $auth = cb_get_auth();
    $usuario = is_array($auth['usuario'] ?? null) ? $auth['usuario'] : [];
    return (int) ($usuario['id'] ?? 0);
}

function cb_cliente_servicio_codigo()
{
    $auth = cb_get_auth();
    $servicio = is_array($auth['servicio'] ?? null) ? $auth['servicio'] : [];
    return trim((string) ($servicio['codigo_servicio'] ?? ''));
}

function cb_require_cliente_permission($codigoPagina, $accion)
{
    if (!cb_cliente_puede($codigoPagina, $accion)) {
        cb_json_error('permiso_denegado', 'No tienes permiso para realizar esta accion.', 403);
    }
}
