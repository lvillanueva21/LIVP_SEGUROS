<?php
require_once __DIR__ . '/includes/session_guard.php';
require_once __DIR__ . '/includes/menu_cliente.php';

$auth = cb_get_auth();
$usuario = is_array($auth['usuario'] ?? null) ? $auth['usuario'] : [];
$servicio = is_array($auth['servicio'] ?? null) ? $auth['servicio'] : [];
$timeout = cb_get_timeout_minutes();

$cbPageTitle = 'Dashboard';
require_once __DIR__ . '/includes/layout_header.php';
require_once __DIR__ . '/includes/layout_sidebar.php';
?>
<div class="card card-primary card-outline">
  <div class="card-header">
    <h3 class="card-title">Bienvenido</h3>
  </div>
  <div class="card-body">
    <p class="mb-2">
      Usuario: <strong><?php echo cb_e(trim((string) ($usuario['nombres'] ?? '') . ' ' . (string) ($usuario['apellidos'] ?? ''))); ?></strong>
    </p>
    <p class="mb-2">
      Documento: <strong><?php echo cb_e((string) ($usuario['documento_tipo'] ?? '')); ?> <?php echo cb_e((string) ($usuario['documento_numero'] ?? '')); ?></strong>
    </p>
    <p class="mb-2">
      Servicio: <strong><?php echo cb_e((string) ($servicio['nombre'] ?? '')); ?></strong>
      <small class="text-muted">(<?php echo cb_e((string) ($servicio['codigo_servicio'] ?? '')); ?>)</small>
    </p>
    <p class="mb-0">
      Timeout de sesión local: <strong><?php echo cb_e((string) $timeout); ?> minutos</strong>
    </p>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <p class="mb-0">Este cliente base está listo para que agregues módulos propios en <code>modules/</code>.</p>
  </div>
</div>
<?php require_once __DIR__ . '/includes/layout_footer.php'; ?>

