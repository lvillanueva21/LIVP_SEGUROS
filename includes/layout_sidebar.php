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
$cbCoverRaw = trim((string) ($cbAssets['sidebar_cover_url'] ?? ''));
if ($cbCoverRaw === '') {
    $cbCoverRaw = trim((string) ($cbAssets['login_bg_url'] ?? ''));
}
$cbCover = cb_asset_url($cbCoverRaw, 'assets/default/login/login_fondo.svg');
$cbLogoFallback = cb_asset_url('', 'assets/default/branding/logo_cliente.svg');
$cbAvatarFallback = cb_asset_url('', 'assets/default/ui/avatar_default.svg');
$cbCoverFallback = cb_asset_url('', 'assets/default/login/login_fondo.svg');

$cbNombreCompleto = trim((string) ($cbUsuario['nombres'] ?? '') . ' ' . (string) ($cbUsuario['apellidos'] ?? ''));
if ($cbNombreCompleto === '') {
    $cbNombreCompleto = 'Usuario externo';
}

$cbServicioNombre = trim((string) ($cbServicio['nombre'] ?? ''));
if ($cbServicioNombre === '') {
    $cbServicioNombre = CLIENTE_NOMBRE;
}
$cbTituloSistema = trim((string) ($cbVisual['titulo_sistema_cliente'] ?? ''));
if ($cbTituloSistema === '') {
    $cbTituloSistema = $cbServicioNombre !== '' ? $cbServicioNombre : CLIENTE_NOMBRE;
}

$cbDocumentoTipo = trim((string) ($cbUsuario['documento_tipo'] ?? ''));
$cbDocumentoNumero = trim((string) ($cbUsuario['documento_numero'] ?? ''));
$cbDocumentoVisible = trim($cbDocumentoTipo . ' ' . $cbDocumentoNumero);
if ($cbDocumentoVisible === '') {
    $cbDocumentoVisible = 'Usuario externo';
}

$cbUbicacionVisible = trim((string) DOMINIO_LOCAL);
if ($cbUbicacionVisible === '') {
    $cbUbicacionVisible = 'Sin ubicación';
}

$cbCurrentScript = strtolower((string) basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
$cbCurrentModule = strtolower(trim((string) ($_GET['m'] ?? '')));
$cbInicioDashboardActivo = $cbCurrentScript === 'dashboard.php';
?>
  <aside id="lsis-main-sidebar" class="main-sidebar sidebar-dark-primary elevation-4 cliente-sidebar">
    <a href="<?php echo cb_e(cb_url('dashboard.php')); ?>" class="brand-link cliente-brand-link">
      <img src="<?php echo cb_e($cbLogo); ?>" alt="Logo cliente" class="brand-image img-circle elevation-3 cliente-brand-image" style="opacity:.8" onerror="this.onerror=null;this.src='<?php echo cb_e($cbLogoFallback); ?>';">
      <span id="lsis-brand-text" class="brand-text font-weight-light"><?php echo cb_e($cbTituloSistema); ?></span>
    </a>

    <div class="sidebar">
      <div
        id="lsis-sidebar-user-card"
        class="user-card text-center p-3 mb-3 lsis-user-card"
        style="background:url('<?php echo cb_e($cbCover); ?>') no-repeat center center; background-size:cover;"
      >
        <img id="lsis-sidebar-user-cover-img" class="d-none" src="<?php echo cb_e($cbCover); ?>" alt="Portada" onerror="var c=document.getElementById('lsis-sidebar-user-card');if(c){c.style.backgroundImage='url(<?php echo cb_e($cbCoverFallback); ?>)';}this.onerror=null;this.src='<?php echo cb_e($cbCoverFallback); ?>';">

        <div class="mb-2 uc-avatar" title="<?php echo cb_e($cbNombreCompleto); ?>">
          <img id="lsis-sidebar-user-photo" src="<?php echo cb_e($cbAvatar); ?>" alt="Avatar" class="img-circle elevation-3" onerror="this.onerror=null;this.src='<?php echo cb_e($cbAvatarFallback); ?>';">
          <span class="emp-mini-logo" title="Logo servicio">
            <img id="lsis-sidebar-company-logo" src="<?php echo cb_e($cbLogo); ?>" alt="Logo servicio" onerror="this.onerror=null;this.src='<?php echo cb_e($cbLogoFallback); ?>';">
          </span>
        </div>

        <div id="lsis-sidebar-user-name" class="font-weight-bold mb-1 uc-name" title="<?php echo cb_e($cbNombreCompleto); ?>">
          <?php echo cb_e($cbNombreCompleto); ?>
        </div>
        <div class="mb-2 uc-company">
          <span class="badge badge-primary px-3 py-2">
            <span id="lsis-sidebar-user-company"><?php echo cb_e($cbServicioNombre); ?></span>
          </span>
        </div>

        <div class="d-flex justify-content-between mt-2 uc-meta">
          <span class="badge bg-light text-dark mr-1" title="<?php echo cb_e($cbUbicacionVisible); ?>">
            <span aria-hidden="true">&#x1F4CD;</span>
            <span id="lsis-sidebar-user-location"><?php echo cb_e($cbUbicacionVisible); ?></span>
          </span>
          <span class="badge bg-primary" id="lsis-sidebar-user-role-wrap" title="<?php echo cb_e($cbDocumentoVisible); ?>">
            <i class="fas fa-id-badge mr-1" aria-hidden="true"></i>
            <span id="lsis-sidebar-user-role"><?php echo cb_e($cbDocumentoVisible); ?></span>
          </span>
        </div>
      </div>

      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
          <li class="nav-item">
            <a href="<?php echo cb_e(cb_url('dashboard.php')); ?>" class="nav-link<?php echo $cbInicioDashboardActivo ? ' active' : ''; ?>">
              <i class="nav-icon fas fa-home"></i>
              <p>Inicio</p>
            </a>
          </li>

          <?php foreach ($cbMenu as $item): ?>
            <?php
              $icono = (string) ($item['icono'] ?? 'fas fa-circle');
              $titulo = (string) ($item['titulo'] ?? '');
              $url = (string) ($item['url'] ?? '#');
              $codigo = strtolower(trim((string) ($item['codigo'] ?? '')));
              $esActivo = false;
              if ($cbCurrentScript === 'modulo.php' && $codigo !== '' && $codigo === $cbCurrentModule) {
                  $esActivo = true;
              }
            ?>
            <li class="nav-item">
              <a href="<?php echo cb_e(cb_url($url)); ?>" class="nav-link<?php echo $esActivo ? ' active' : ''; ?>">
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
