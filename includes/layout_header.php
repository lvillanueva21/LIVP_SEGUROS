<?php
if (!isset($cbPageTitle)) {
    $cbPageTitle = 'Panel';
}
$cbVisual = cb_get_visual_config();
$cbPrimary = cb_e($cbVisual['color_primario']);
$cbSecondary = cb_e($cbVisual['color_secundario']);
$cbAssets = is_array($cbVisual['assets'] ?? null) ? $cbVisual['assets'] : [];
$cbFavicon = cb_asset_url((string) ($cbAssets['favicon_url'] ?? ''), 'assets/default/branding/favicon.svg');
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
      --cliente-primario: <?php echo $cbPrimary; ?>;
      --cliente-secundario: <?php echo $cbSecondary; ?>;
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
      --cliente-login-bg: <?php echo cb_e((string) ($cbVisual['color_login_bg'] ?? '#FFFFFF')); ?>;
      --cliente-login-saludo-text: <?php echo cb_e((string) ($cbVisual['color_login_saludo_text'] ?? '#212529')); ?>;
    }
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
<div class="wrapper">
  <nav class="main-header navbar navbar-expand navbar-white navbar-light cliente-main-header">
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="#" role="button" title="Alternar menú" aria-label="Alternar menú">
          <i class="fas fa-bars"></i>
        </a>
      </li>
      <li class="nav-item d-none d-sm-inline-block">
        <span class="nav-link cliente-header-title"><?php echo cb_e(CLIENTE_NOMBRE); ?></span>
      </li>
    </ul>
    <ul class="navbar-nav ml-auto">
      <li class="nav-item d-none d-sm-inline-block">
        <a href="<?php echo cb_e(cb_url('logout.php')); ?>" class="nav-link">Cerrar sesión</a>
      </li>
    </ul>
  </nav>
