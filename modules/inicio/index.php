<?php
require_once __DIR__ . '/../../includes/module_guard.php';
cb_require_module_context('inicio');

$cbPageTitle = 'Inicio';
$cbAuthInicio = cb_get_auth();
$cbUsuarioInicio = is_array($cbAuthInicio['usuario'] ?? null) ? $cbAuthInicio['usuario'] : [];
$cbServicioInicio = is_array($cbAuthInicio['servicio'] ?? null) ? $cbAuthInicio['servicio'] : [];
$cbRolInicio = is_array($cbAuthInicio['rol'] ?? null) ? $cbAuthInicio['rol'] : [];

$cbNombreInicio = trim((string) ($cbUsuarioInicio['nombres'] ?? '') . ' ' . (string) ($cbUsuarioInicio['apellidos'] ?? ''));
if ($cbNombreInicio === '') {
    $cbNombreInicio = 'Usuario externo';
}

$cbDocumentoInicio = trim((string) ($cbUsuarioInicio['documento_tipo'] ?? '') . ' ' . (string) ($cbUsuarioInicio['documento_numero'] ?? ''));
if ($cbDocumentoInicio === '') {
    $cbDocumentoInicio = 'Sin documento';
}

$cbServicioNombreInicio = trim((string) ($cbServicioInicio['nombre'] ?? ''));
if ($cbServicioNombreInicio === '') {
    $cbServicioNombreInicio = CLIENTE_NOMBRE;
}

$cbServicioCodigoInicio = trim((string) ($cbServicioInicio['codigo_servicio'] ?? ''));
$cbRolVisibleInicio = trim((string) ($cbRolInicio['nombre'] ?? ''));
if ($cbRolVisibleInicio === '') {
    $cbRolVisibleInicio = 'Usuario externo';
}
?>
<div class="card card-primary card-outline">
  <div class="card-header">
    <h3 class="card-title">Panel inicial</h3>
  </div>
  <div class="card-body">
    <p class="mb-2">Bienvenido, <strong><?php echo cb_e($cbNombreInicio); ?></strong>.</p>
    <p class="mb-2">Usuario: <strong><?php echo cb_e($cbDocumentoInicio); ?></strong></p>
    <p class="mb-2">Rol activo: <strong><?php echo cb_e($cbRolVisibleInicio); ?></strong></p>
    <p class="mb-2">
      Servicio: <strong><?php echo cb_e($cbServicioNombreInicio); ?></strong>
      <?php if ($cbServicioCodigoInicio !== ''): ?>
        <small class="text-muted">(<?php echo cb_e($cbServicioCodigoInicio); ?>)</small>
      <?php endif; ?>
    </p>
    <p class="mb-0">Módulo base del cliente externo.</p>
  </div>
</div>
