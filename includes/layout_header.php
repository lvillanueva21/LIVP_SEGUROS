<?php
if (!isset($cbPageTitle)) {
    $cbPageTitle = 'Panel';
}

$cbVisual = cb_get_visual_config();
$cbAuth = cb_get_auth();
$cbUsuario = is_array($cbAuth['usuario'] ?? null) ? $cbAuth['usuario'] : [];
$cbServicio = is_array($cbAuth['servicio'] ?? null) ? $cbAuth['servicio'] : [];

$cbNombreCompleto = trim((string) ($cbUsuario['nombres'] ?? '') . ' ' . (string) ($cbUsuario['apellidos'] ?? ''));
if ($cbNombreCompleto === '') {
    $cbNombreCompleto = 'Usuario externo';
}

$cbRolVisual = 'Usuario externo';
$cbServicioNombre = trim((string) ($cbServicio['nombre'] ?? ''));
if ($cbServicioNombre === '') {
    $cbServicioNombre = CLIENTE_NOMBRE;
}

$cbAssets = is_array($cbVisual['assets'] ?? null) ? $cbVisual['assets'] : [];
$cbFavicon = cb_asset_url((string) ($cbAssets['favicon_url'] ?? ''), 'assets/default/branding/favicon.svg');
$cbAvatar = cb_asset_url((string) ($cbAssets['avatar_default_url'] ?? ''), 'assets/default/ui/avatar_default.svg');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo cb_e($cbPageTitle); ?> - <?php echo cb_e(CLIENTE_NOMBRE); ?></title>
  <link rel="icon" href="<?php echo cb_e($cbFavicon); ?>">
  <link rel="stylesheet" href="<?php echo cb_e(cb_url('plugins/fontawesome-free/css/all.min.css')); ?>">
  <link rel="stylesheet" href="<?php echo cb_e(cb_url('dist/css/adminlte.min.css')); ?>">
  <link rel="stylesheet" href="<?php echo cb_e(cb_url('assets/css/cliente.css')); ?>">
  <style>
    :root {
      --cliente-primario: <?php echo cb_e((string) ($cbVisual['color_primario'] ?? '#007BFF')); ?>;
      --cliente-secundario: <?php echo cb_e((string) ($cbVisual['color_secundario'] ?? '#6C757D')); ?>;
      --cliente-header-bg: <?php echo cb_e((string) ($cbVisual['color_header_bg'] ?? '#343A40')); ?>;
      --cliente-header-text: <?php echo cb_e((string) ($cbVisual['color_header_text'] ?? '#FFFFFF')); ?>;
      --cliente-sidebar-bg: <?php echo cb_e((string) ($cbVisual['color_sidebar_bg'] ?? '#343A40')); ?>;
      --cliente-sidebar-text: <?php echo cb_e((string) ($cbVisual['color_sidebar_text'] ?? '#FFFFFF')); ?>;
      --cliente-sidebar-brand-bg: <?php echo cb_e((string) ($cbVisual['color_sidebar_brand_bg'] ?? '#343A40')); ?>;
      --cliente-sidebar-brand-text: <?php echo cb_e((string) ($cbVisual['color_sidebar_brand_text'] ?? '#FFFFFF')); ?>;
      --cliente-sidebar-hover-bg: <?php echo cb_e((string) ($cbVisual['color_sidebar_item_hover_bg'] ?? '#1F2D3D')); ?>;
      --cliente-sidebar-hover-text: <?php echo cb_e((string) ($cbVisual['color_sidebar_item_hover_text'] ?? '#FFFFFF')); ?>;
      --cliente-sidebar-active-bg: <?php echo cb_e((string) ($cbVisual['color_sidebar_item_active_bg'] ?? '#007BFF')); ?>;
      --cliente-sidebar-active-text: <?php echo cb_e((string) ($cbVisual['color_sidebar_item_active_text'] ?? '#FFFFFF')); ?>;
      --cliente-sidebar-group-active-bg: <?php echo cb_e((string) ($cbVisual['color_sidebar_group_active_bg'] ?? '#0069D9')); ?>;
      --cliente-sidebar-group-active-text: <?php echo cb_e((string) ($cbVisual['color_sidebar_group_active_text'] ?? '#FFFFFF')); ?>;
    }
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed layout-footer-fixed">
<div class="wrapper">
  <nav id="lsis-main-header" class="main-header navbar navbar-expand navbar-dark">
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="#" role="button" title="Alternar menú" aria-label="Alternar menú">
          <i class="fas fa-bars"></i>
        </a>
      </li>
      <li class="nav-item d-none d-sm-inline-block">
        <a href="<?php echo cb_e(cb_url('dashboard.php')); ?>" class="nav-link">Inicio</a>
      </li>
      <li class="nav-item d-none d-lg-inline-block">
        <span class="nav-link cliente-header-page-title"><?php echo cb_e($cbPageTitle); ?></span>
      </li>
    </ul>

    <ul class="navbar-nav ml-auto">
      <li class="nav-item d-none d-md-block">
        <span class="badge badge-primary mr-2"><?php echo cb_e($cbRolVisual); ?></span>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="#" title="Usuario">
          <img
            src="<?php echo cb_e($cbAvatar); ?>"
            alt="Foto"
            class="img-circle elevation-1 mr-1"
            style="width:24px;height:24px;object-fit:cover;"
          >
          <i class="far fa-user"></i>
          <span class="d-none d-sm-inline"><?php echo cb_e($cbNombreCompleto); ?></span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-danger" href="<?php echo cb_e(cb_url('logout.php')); ?>" title="Salir" aria-label="Salir">
          <i class="fas fa-sign-out-alt"></i>
        </a>
      </li>
    </ul>
  </nav>
