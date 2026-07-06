<?php

declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';
require __DIR__ . '/config/development_page.php';

renderDevelopmentPage(
    'configuracion',
    'Elige una de las tres áreas técnicas disponibles. Por ahora solo se habilitan las rutas y permisos para Desarrollo.',
    [
        ['module' => 'gestion-archivos', 'description' => 'Rutas, tipos de archivos y almacenamiento futuro.'],
        ['module' => 'gestion-correos', 'description' => 'Cuentas, plantillas y automatizaciones futuras.'],
        ['module' => 'gestion-whatsapp', 'description' => 'Enlaces, plantillas y automatizaciones futuras.'],
    ]
);
