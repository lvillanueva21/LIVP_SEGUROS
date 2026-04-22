<?php
require_once __DIR__ . '/includes/bootstrap.php';

demo_logout_user();
demo_push_toast('Sesión cerrada correctamente.', 'info', 'Hasta luego');
demo_redirect('index.php');