<?php
require_once __DIR__ . '/includes/session_guard.php';
require_once __DIR__ . '/includes/menu_cliente.php';

$modulo = strtolower(trim((string) ($_GET['m'] ?? 'inicio')));
$moduloValido = cb_cliente_normalize_codigo_pagina($modulo) === $modulo;
$puedeVer = $moduloValido && cb_cliente_puede_ver_pagina($modulo);

$cbPageTitle = $moduloValido ? cb_cliente_titulo_pagina($modulo) : 'Modulo';
require_once __DIR__ . '/includes/layout_header.php';
require_once __DIR__ . '/includes/layout_sidebar.php';

if (!$moduloValido || !$puedeVer) {
    ?>
    <div class="card card-danger card-outline">
      <div class="card-header">
        <h3 class="card-title">Acceso no permitido</h3>
      </div>
      <div class="card-body">
        <p class="mb-0">No tienes permiso para acceder a esta pagina.</p>
      </div>
    </div>
    <?php
    require_once __DIR__ . '/includes/layout_footer.php';
    exit;
}

$modulePath = __DIR__ . '/modules/' . $modulo . '/index.php';
if (!is_file($modulePath)) {
    ?>
    <div class="card card-warning card-outline">
      <div class="card-header">
        <h3 class="card-title">Pagina en construccion</h3>
      </div>
      <div class="card-body">
        <p class="mb-0">Esta pagina esta permitida para tu rol, pero todavia no tiene un modulo publicado.</p>
      </div>
    </div>
    <?php
    require_once __DIR__ . '/includes/layout_footer.php';
    exit;
}

require $modulePath;
require_once __DIR__ . '/includes/layout_footer.php';
