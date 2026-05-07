<?php
require_once __DIR__ . '/menu_cliente.php';
$cbAuth = cb_get_auth();
$cbUsuario = is_array($cbAuth) && isset($cbAuth['usuario']) && is_array($cbAuth['usuario']) ? $cbAuth['usuario'] : [];
$cbServicio = is_array($cbAuth) && isset($cbAuth['servicio']) && is_array($cbAuth['servicio']) ? $cbAuth['servicio'] : [];
$cbMenu = cb_cliente_menu();
?>
  <aside class="main-sidebar sidebar-dark-primary elevation-4">
    <a href="<?php echo cb_e(cb_url('dashboard.php')); ?>" class="brand-link">
      <span class="brand-text font-weight-light"><?php echo cb_e(CLIENTE_NOMBRE); ?></span>
    </a>

    <div class="sidebar">
      <div class="user-panel mt-3 pb-3 mb-3 d-flex">
        <div class="info">
          <a href="#" class="d-block"><?php echo cb_e(trim((string) ($cbUsuario['nombres'] ?? '') . ' ' . (string) ($cbUsuario['apellidos'] ?? ''))); ?></a>
          <small class="text-muted"><?php echo cb_e((string) ($cbServicio['nombre'] ?? '')); ?></small>
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

