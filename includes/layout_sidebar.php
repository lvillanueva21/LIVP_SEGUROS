<?php
require_once __DIR__ . '/menu_cliente.php';
$cbAuth = cb_get_auth();
$cbUsuario = is_array($cbAuth) && isset($cbAuth['usuario']) && is_array($cbAuth['usuario']) ? $cbAuth['usuario'] : [];
$cbServicio = is_array($cbAuth) && isset($cbAuth['servicio']) && is_array($cbAuth['servicio']) ? $cbAuth['servicio'] : [];
$cbVisual = cb_get_visual_config();
$cbAssets = is_array($cbVisual['assets'] ?? null) ? $cbVisual['assets'] : [];
$cbMenu = cb_cliente_menu();
$cbLogo = cb_asset_url((string) ($cbAssets['logo_url'] ?? ''), 'assets/default/branding/logo_cliente.svg');
$cbAvatar = cb_asset_url((string) ($cbAssets['avatar_default_url'] ?? ''), 'assets/default/ui/avatar_default.svg');
?>
  <aside class="main-sidebar sidebar-dark-primary elevation-4 cliente-sidebar">
    <a href="<?php echo cb_e(cb_url('dashboard.php')); ?>" class="brand-link cliente-brand-link">
      <img src="<?php echo cb_e($cbLogo); ?>" alt="Logo cliente" class="brand-image cliente-brand-image elevation-2">
      <span class="brand-text font-weight-light"><?php echo cb_e(CLIENTE_NOMBRE); ?></span>
    </a>

    <div class="sidebar">
      <div class="user-panel mt-3 pb-3 mb-3 d-flex align-items-center cliente-user-panel">
        <div class="image">
          <img src="<?php echo cb_e($cbAvatar); ?>" class="img-circle elevation-1" alt="Avatar">
        </div>
        <div class="info">
          <a href="#" class="d-block text-truncate" title="<?php echo cb_e(trim((string) ($cbUsuario['nombres'] ?? '') . ' ' . (string) ($cbUsuario['apellidos'] ?? ''))); ?>">
            <?php echo cb_e(trim((string) ($cbUsuario['nombres'] ?? '') . ' ' . (string) ($cbUsuario['apellidos'] ?? ''))); ?>
          </a>
          <small class="text-muted d-block text-truncate" title="<?php echo cb_e((string) ($cbServicio['nombre'] ?? '')); ?>"><?php echo cb_e((string) ($cbServicio['nombre'] ?? '')); ?></small>
        </div>
      </div>

      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
          <li class="nav-item">
            <a href="<?php echo cb_e(cb_url('dashboard.php')); ?>" class="nav-link">
              <i class="nav-icon fas fa-tachometer-alt"></i>
              <p>Dashboard</p>
            </a>
          </li>

          <?php foreach ($cbMenu as $item): ?>
            <?php
              $icono = (string) ($item['icono'] ?? 'fas fa-circle');
              $titulo = (string) ($item['titulo'] ?? '');
              $url = (string) ($item['url'] ?? '#');
            ?>
            <li class="nav-item">
              <a href="<?php echo cb_e(cb_url($url)); ?>" class="nav-link">
                <i class="nav-icon <?php echo cb_e($icono); ?>"></i>
                <p><?php echo cb_e($titulo); ?></p>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </nav>
    </div>
  </aside>

  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1><?php echo cb_e($cbPageTitle); ?></h1>
          </div>
        </div>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
