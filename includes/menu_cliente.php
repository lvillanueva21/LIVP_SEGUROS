<?php

function cb_cliente_menu()
{
    return [
        [
            'codigo' => 'inicio',
            'titulo' => 'Inicio',
            'icono' => 'fas fa-home',
            'url' => 'modulo.php?m=inicio',
        ],
    ];
}

function cb_cliente_menu_codigos()
{
    $codigos = [];
    foreach (cb_cliente_menu() as $item) {
        $codigo = isset($item['codigo']) ? (string) $item['codigo'] : '';
        if ($codigo !== '') {
            $codigos[] = $codigo;
        }
    }

    return $codigos;
}

