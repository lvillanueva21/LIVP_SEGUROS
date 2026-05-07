<?php
if (!isset($cbPageTitle)) {
    $cbPageTitle = 'Panel';
}
$cbVisual = cb_get_visual_config();
$cbPrimary = cb_e($cbVisual['color_primario']);
$cbSecondary = cb_e($cbVisual['color_secundario']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo cb_e($cbPageTitle); ?> - <?php echo cb_e(CLIENTE_NOMBRE); ?></title>
  <link rel="stylesheet" href="<?php echo cb_e(cb_url('plugins/fontawesome-free/css/all.min.css')); ?>">
  <link rel="stylesheet" href="<?php echo cb_e(cb_url('dist/css/adminlte.min.css')); ?>">
  <link rel="stylesheet" href="<?php echo cb_e(cb_url('assets/css/cliente.css')); ?>">
  <style>
    :root {
      --cliente-primario: <?php echo $cbPrimary; ?>;
      --cliente-secundario: <?php echo $cbSecondary; ?>;
    }
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
  <nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="#" role="button" title="Alternar menú" aria-label="Alternar menú">
          <i class="fas fa-bars"></i>
        </a>
      </li>
      <li class="nav-item d-none d-sm-inline-block">
        <span class="nav-link"><?php echo cb_e(CLIENTE_NOMBRE); ?></span>
      </li>
    </ul>
    <ul class="navbar-nav ml-auto">
      <li class="nav-item d-none d-sm-inline-block">
        <a href="<?php echo cb_e(cb_url('logout.php')); ?>" class="nav-link">Cerrar sesión</a>
      </li>
    </ul>
  </nav>

