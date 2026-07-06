<?php

declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';
require __DIR__ . '/config/development_page.php';

renderDevelopmentPage('gestion-correos', 'Aquí se configurarán cuentas, plantillas y automatizaciones de correo del sistema.');
