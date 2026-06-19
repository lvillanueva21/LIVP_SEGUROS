<?php

function cb_cliente_menu_fallback()
{
    return [
        [
            'codigo' => 'inicio',
            'titulo' => 'Inicio',
            'icono' => 'fas fa-home',
            'url' => 'modulo.php?m=inicio',
            'hijos' => [],
        ],
    ];
}

function cb_cliente_normalize_codigo_pagina($value)
{
    $value = strtolower(trim((string) $value));
    return preg_match('/^[a-z0-9_-]+$/', $value) === 1 ? $value : '';
}

function cb_cliente_normalize_menu_item(array $item)
{
    $codigo = cb_cliente_normalize_codigo_pagina($item['codigo_pagina'] ?? ($item['codigo'] ?? ''));
    if ($codigo === '') {
        return null;
    }

    $titulo = trim((string) ($item['titulo_menu'] ?? ($item['titulo'] ?? '')));
    if ($titulo === '') {
        $titulo = trim((string) ($item['titulo_pagina'] ?? $codigo));
    }

    $icono = cb_normalize_icon_css($item['icono'] ?? 'fas fa-circle');

    $normalized = [
        'codigo' => $codigo,
        'titulo' => $titulo,
        'icono' => $icono,
        'url' => 'modulo.php?m=' . rawurlencode($codigo),
        'hijos' => [],
    ];

    $children = is_array($item['hijos'] ?? null) ? $item['hijos'] : [];
    foreach ($children as $child) {
        if (!is_array($child)) {
            continue;
        }
        $normalizedChild = cb_cliente_normalize_menu_item($child);
        if (!$normalizedChild) {
            continue;
        }
        $normalizedChild['hijos'] = [];
        $normalized['hijos'][] = $normalizedChild;
    }

    return $normalized;
}

function cb_cliente_menu()
{
    $auth = cb_get_auth();
    if (!is_array($auth) || empty($auth['ok'])) {
        return cb_cliente_menu_fallback();
    }

    $remoteMenu = is_array($auth['menu'] ?? null) ? $auth['menu'] : [];
    if (!$remoteMenu) {
        return [];
    }

    $menu = [];
    foreach ($remoteMenu as $item) {
        if (!is_array($item)) {
            continue;
        }
        $normalized = cb_cliente_normalize_menu_item($item);
        if ($normalized) {
            $menu[] = $normalized;
        }
    }

    return $menu;
}

function cb_cliente_menu_codigos()
{
    $codigos = [];
    foreach (cb_cliente_menu() as $item) {
        $codigo = isset($item['codigo']) ? (string) $item['codigo'] : '';
        if ($codigo !== '') {
            $codigos[] = $codigo;
        }
        $children = is_array($item['hijos'] ?? null) ? $item['hijos'] : [];
        foreach ($children as $child) {
            $childCode = isset($child['codigo']) ? (string) $child['codigo'] : '';
            if ($childCode !== '') {
                $codigos[] = $childCode;
            }
        }
    }

    return array_values(array_unique($codigos));
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

function cb_cliente_puede_ver_pagina($codigoPagina)
{
    $permiso = cb_cliente_permiso_pagina($codigoPagina);
    return is_array($permiso) && (int) ($permiso['puede_ver'] ?? 0) === 1;
}

function cb_cliente_titulo_pagina($codigoPagina)
{
    $codigoPagina = cb_cliente_normalize_codigo_pagina($codigoPagina);
    foreach (cb_cliente_menu() as $item) {
        if ((string) ($item['codigo'] ?? '') === $codigoPagina) {
            return (string) ($item['titulo'] ?? 'Modulo');
        }
        $children = is_array($item['hijos'] ?? null) ? $item['hijos'] : [];
        foreach ($children as $child) {
            if ((string) ($child['codigo'] ?? '') === $codigoPagina) {
                return (string) ($child['titulo'] ?? 'Modulo');
            }
        }
    }

    return 'Modulo';
}
