<?php
require_once __DIR__ . '/includes/session_guard.php';

$auth = cb_get_auth();
$usuario = is_array($auth['usuario'] ?? null) ? $auth['usuario'] : [];
$servicio = is_array($auth['servicio'] ?? null) ? $auth['servicio'] : [];
$rol = is_array($auth['rol'] ?? null) ? $auth['rol'] : [];
$timeout = cb_get_timeout_minutes();

$nombreCompleto = trim((string) ($usuario['nombres'] ?? '') . ' ' . (string) ($usuario['apellidos'] ?? ''));
if ($nombreCompleto === '') {
    $nombreCompleto = 'Usuario externo';
}

$servicioNombre = trim((string) ($servicio['nombre'] ?? ''));
if ($servicioNombre === '') {
    $servicioNombre = CLIENTE_NOMBRE;
}

$servicioCodigo = trim((string) ($servicio['codigo_servicio'] ?? ''));
$documentoVisible = trim((string) ($usuario['documento_tipo'] ?? '') . ' ' . (string) ($usuario['documento_numero'] ?? ''));
if ($documentoVisible === '') {
    $documentoVisible = 'Sin documento';
}
$rolVisible = trim((string) ($rol['nombre'] ?? ''));
if ($rolVisible === '') {
    $rolVisible = 'Usuario externo';
}

$cbPageTitle = 'Inicio';
require_once __DIR__ . '/includes/layout_header.php';
require_once __DIR__ . '/includes/layout_sidebar.php';
?>
<div class="card card-primary card-outline">
  <div class="card-header">
    <h3 class="card-title">Panel inicial</h3>
  </div>
  <div class="card-body">
    <p class="mb-2">Bienvenido, <strong><?php echo cb_e($nombreCompleto); ?></strong>.</p>
    <p class="mb-2">Usuario: <strong><?php echo cb_e($documentoVisible); ?></strong></p>
    <p class="mb-2">Rol activo: <strong><?php echo cb_e($rolVisible); ?></strong></p>
    <p class="mb-2">Servicio: <strong><?php echo cb_e($servicioNombre); ?></strong><?php if ($servicioCodigo !== ''): ?> <small class="text-muted">(<?php echo cb_e($servicioCodigo); ?>)</small><?php endif; ?></p>
    <p class="mb-0">Timeout de sesión local: <strong><?php echo cb_e((string) $timeout); ?> minutos</strong></p>
  </div>
</div>
<?php require_once __DIR__ . '/includes/layout_footer.php'; ?>
