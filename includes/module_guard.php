<?php
require_once __DIR__ . '/autorizacion_cliente.php';

function cb_module_forbidden()
{
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Acceso no permitido.';
    exit;
}

function cb_require_module_context($codigoEsperado)
{
    $codigoEsperado = cb_cliente_normalize_codigo_pagina($codigoEsperado);
    $codigoActual = defined('CB_MODULE_CODIGO')
        ? cb_cliente_normalize_codigo_pagina((string) CB_MODULE_CODIGO)
        : '';

    if ($codigoEsperado === '' || $codigoActual === '') {
        cb_module_forbidden();
    }

    if (!defined('CB_MODULE_CONTEXT') || CB_MODULE_CONTEXT !== true) {
        cb_module_forbidden();
    }

    if ($codigoActual !== $codigoEsperado) {
        cb_module_forbidden();
    }
}
