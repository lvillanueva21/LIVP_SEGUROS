<?php
require_once __DIR__ . '/includes/session_guard.php';
require_once __DIR__ . '/includes/menu_cliente.php';

$menu = cb_cliente_menu();
$menuCodigos = cb_cliente_menu_codigos();
$modulo = strtolower(trim((string) ($_GET['m'] ?? 'inicio')));
$moduloValido = preg_match('/^[a-z0-9_]+$/', $modulo) === 1 && in_array($modulo, $menuCodigos, true);

$cbPageTitle = 'Módulo';
foreach ($menu as $item) {
    if ((string) ($item['codigo'] ?? '') === $modulo) {
        $cbPageTitle = (string) ($item['titulo'] ?? 'Módulo');
        break;
    }
}
require_once __DIR__ . '/includes/layout_header.php';
require_once __DIR__ . '/includes/layout_sidebar.php';

if (!$moduloValido) {
    ?>
    <div class="card card-danger card-outline">
      <div class="card-header">
        <h3 class="card-title">Módulo no permitido</h3>
      </div>
      <div class="card-body">
        <p class="mb-0">El módulo solicitado no está disponible.</p>
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
        <h3 class="card-title">Módulo no implementado</h3>
      </div>
      <div class="card-body">
        <p class="mb-0">El módulo existe en menú pero aún no tiene vista de inicio.</p>
      </div>
    </div>
    <?php
    require_once __DIR__ . '/includes/layout_footer.php';
    exit;
}

require $modulePath;
require_once __DIR__ . '/includes/layout_footer.php';
