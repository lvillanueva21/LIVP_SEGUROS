<?php

declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';
require __DIR__ . '/config/development_page.php';

renderDevelopmentPage('gestion-archivos', 'Aquí se configurarán rutas, tipos permitidos, almacenamiento y reglas para los archivos del sistema.');
