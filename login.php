<?php
require_once __DIR__ . '/includes/api_client.php';

cb_boot_session();

if (cb_is_logged_in()) {
    cb_redirect('dashboard.php');
}

$visual = cb_get_effective_visual_config(true);
$errorMsg = '';
$infoMsg = '';
$inputTipo = 'DNI';
$inputNumero = '';

$csrfKey = 'cliente_login_csrf';
if (empty($_SESSION[$csrfKey]) || !is_string($_SESSION[$csrfKey])) {
    $_SESSION[$csrfKey] = cb_random_token(32);
}
$csrfToken = (string) $_SESSION[$csrfKey];

if ((string) ($_GET['m'] ?? '') === 'sesion') {
    $infoMsg = 'Tu sesión expiró o no es válida. Inicia sesión nuevamente.';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $inputTipo = strtoupper(trim((string) ($_POST['documento_tipo'] ?? '')));
    $inputNumero = trim((string) ($_POST['documento_numero'] ?? ''));
    $clave = (string) ($_POST['clave'] ?? '');
    $postedCsrf = (string) ($_POST['csrf_token'] ?? '');

    if ($csrfToken === '' || !hash_equals($csrfToken, $postedCsrf)) {
        $errorMsg = 'No se pudo procesar la solicitud. Intenta nuevamente.';
        $_SESSION[$csrfKey] = cb_random_token(32);
        $csrfToken = (string) $_SESSION[$csrfKey];
    } else {
        $_SESSION[$csrfKey] = cb_random_token(32);
        $csrfToken = (string) $_SESSION[$csrfKey];

        $validTipos = ['DNI', 'CE'];
        if (!in_array($inputTipo, $validTipos, true)) {
            $errorMsg = 'Credenciales inválidas o acceso no autorizado.';
        } elseif ($inputTipo === 'DNI' && preg_match('/^\d{8}$/', $inputNumero) !== 1) {
            $errorMsg = 'Credenciales inválidas o acceso no autorizado.';
        } elseif ($inputTipo === 'CE' && preg_match('/^[A-Za-z0-9]{6,15}$/', $inputNumero) !== 1) {
            $errorMsg = 'Credenciales inválidas o acceso no autorizado.';
        } elseif (trim($clave) === '') {
            $errorMsg = 'Credenciales inválidas o acceso no autorizado.';
        } else {
            $apiResult = cb_api_login($inputTipo, $inputNumero, $clave);
            if (!empty($apiResult['ok'])) {
                $data = is_array($apiResult['data'] ?? null) ? $apiResult['data'] : [];
                $usuario = is_array($data['usuario'] ?? null) ? $data['usuario'] : [];
                $servicio = is_array($data['servicio'] ?? null) ? $data['servicio'] : [];
                $configVisual = is_array($data['config_visual'] ?? null) ? $data['config_visual'] : [];
                $configLogin = is_array($data['config_login'] ?? null) ? $data['config_login'] : [];

                $timeout = (int) ($configLogin['timeout_sesion_minutos'] ?? 30);
                if ($timeout < 5) {
                    $timeout = 5;
                }
                if ($timeout > 1440) {
                    $timeout = 1440;
                }

                session_regenerate_id(true);
                $_SESSION[$csrfKey] = cb_random_token(32);

                $sessionVisual = $visual;
                $sessionVisual['titulo_login'] = trim((string) ($configVisual['titulo_login'] ?? '')) !== ''
                    ? trim((string) $configVisual['titulo_login'])
                    : (string) ($sessionVisual['titulo_login'] ?? CLIENTE_LOGIN_TITULO);
                $sessionVisual['subtitulo_login'] = isset($configVisual['subtitulo_login'])
                    ? (string) $configVisual['subtitulo_login']
                    : (string) ($sessionVisual['subtitulo_login'] ?? CLIENTE_LOGIN_SUBTITULO);
                $sessionVisual['color_primario'] = cb_normalize_hex_color((string) ($configVisual['color_primario'] ?? ''), (string) ($sessionVisual['color_primario'] ?? CLIENTE_COLOR_PRIMARIO));
                $sessionVisual['color_secundario'] = cb_normalize_hex_color((string) ($configVisual['color_secundario'] ?? ''), (string) ($sessionVisual['color_secundario'] ?? CLIENTE_COLOR_SECUNDARIO));

                $_SESSION['cliente_auth'] = [
                    'ok' => true,
                    'usuario' => [
                        'id' => (int) ($usuario['id'] ?? 0),
                        'documento_tipo' => (string) ($usuario['documento_tipo'] ?? ''),
                        'documento_numero' => (string) ($usuario['documento_numero'] ?? ''),
                        'nombres' => (string) ($usuario['nombres'] ?? ''),
                        'apellidos' => (string) ($usuario['apellidos'] ?? ''),
                    ],
                    'servicio' => [
                        'id' => (int) ($servicio['id'] ?? 0),
                        'codigo_servicio' => (string) ($servicio['codigo_servicio'] ?? ''),
                        'nombre' => (string) ($servicio['nombre'] ?? ''),
                    ],
                    'config_visual' => $sessionVisual,
                    'config_login' => [
                        'timeout_sesion_minutos' => $timeout,
                    ],
                    'login_at' => time(),
                    'last_activity_at' => time(),
                ];

                cb_redirect('dashboard.php');
            }

            $errorMsg = 'Credenciales inválidas o acceso no autorizado.';
        }
    }
}

$visualAssets = is_array($visual['assets'] ?? null) ? $visual['assets'] : [];
$faviconUrl = cb_asset_url((string) ($visualAssets['favicon_url'] ?? ''), 'assets/default/branding/favicon.svg');
$logoUrl = cb_asset_url((string) ($visualAssets['logo_url'] ?? ''), 'assets/default/branding/logo_cliente.svg');
$bgUrl = cb_asset_url((string) ($visualAssets['login_bg_url'] ?? ''), 'assets/default/login/login_fondo.svg');
$carouselEnabled = (bool) CLIENTE_LOGIN_CARRUSEL_ACTIVO;
$carouselItemsRaw = isset($visualAssets['carrusel']) && is_array($visualAssets['carrusel'])
    ? $visualAssets['carrusel']
    : [];
$carouselItems = [];
foreach ($carouselItemsRaw as $itemUrl) {
    $safeUrl = cb_asset_url((string) $itemUrl, '');
    if ($safeUrl !== '' && !in_array($safeUrl, $carouselItems, true)) {
        $carouselItems[] = $safeUrl;
    }
}
if (!$carouselItems) {
    $carouselItems = [
        cb_config_asset_url(CLIENTE_LOGIN_CARRUSEL_1_PATH, 'assets/default/login/carrusel_1.svg'),
        cb_config_asset_url(CLIENTE_LOGIN_CARRUSEL_2_PATH, 'assets/default/login/carrusel_2.svg'),
        cb_config_asset_url(CLIENTE_LOGIN_CARRUSEL_3_PATH, 'assets/default/login/carrusel_3.svg'),
    ];
}
if (count($carouselItems) < 2) {
    $carouselEnabled = false;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login - <?php echo cb_e(CLIENTE_NOMBRE); ?></title>
  <link rel="icon" href="<?php echo cb_e($faviconUrl); ?>">
  <link rel="stylesheet" href="<?php echo cb_e(cb_url('plugins/fontawesome-free/css/all.min.css')); ?>">
  <link rel="stylesheet" href="<?php echo cb_e(cb_url('plugins/icheck-bootstrap/icheck-bootstrap.min.css')); ?>">
  <link rel="stylesheet" href="<?php echo cb_e(cb_url('dist/css/adminlte.min.css')); ?>">
  <link rel="stylesheet" href="<?php echo cb_e(cb_url('assets/css/cliente.css')); ?>">
  <style>
    :root {
      --cliente-primario: <?php echo cb_e($visual['color_primario']); ?>;
      --cliente-secundario: <?php echo cb_e($visual['color_secundario']); ?>;
      --cliente-login-bg: <?php echo cb_e((string) ($visual['color_login_bg'] ?? '#FFFFFF')); ?>;
      --cliente-login-saludo-text: <?php echo cb_e((string) ($visual['color_login_saludo_text'] ?? '#212529')); ?>;
      --cliente-header-bg: <?php echo cb_e((string) ($visual['color_header_bg'] ?? '#343A40')); ?>;
      --cliente-header-text: <?php echo cb_e((string) ($visual['color_header_text'] ?? '#FFFFFF')); ?>;
      --cliente-sidebar-bg: <?php echo cb_e((string) ($visual['color_sidebar_bg'] ?? '#343A40')); ?>;
      --cliente-sidebar-text: <?php echo cb_e((string) ($visual['color_sidebar_text'] ?? '#FFFFFF')); ?>;
      --cliente-sidebar-brand-bg: <?php echo cb_e((string) ($visual['color_sidebar_brand_bg'] ?? '#343A40')); ?>;
      --cliente-sidebar-brand-text: <?php echo cb_e((string) ($visual['color_sidebar_brand_text'] ?? '#FFFFFF')); ?>;
      --cliente-sidebar-hover-bg: <?php echo cb_e((string) ($visual['color_sidebar_item_hover_bg'] ?? '#1F2D3D')); ?>;
      --cliente-sidebar-hover-text: <?php echo cb_e((string) ($visual['color_sidebar_item_hover_text'] ?? '#FFFFFF')); ?>;
      --cliente-sidebar-active-bg: <?php echo cb_e((string) ($visual['color_sidebar_item_active_bg'] ?? '#007BFF')); ?>;
      --cliente-sidebar-active-text: <?php echo cb_e((string) ($visual['color_sidebar_item_active_text'] ?? '#FFFFFF')); ?>;
      --cliente-sidebar-group-active-bg: <?php echo cb_e((string) ($visual['color_sidebar_group_active_bg'] ?? '#0069D9')); ?>;
      --cliente-sidebar-group-active-text: <?php echo cb_e((string) ($visual['color_sidebar_group_active_text'] ?? '#FFFFFF')); ?>;
    }
  </style>
</head>
<body class="hold-transition cliente-login-page">
<div class="cliente-login-shell" style="background-color: <?php echo cb_e((string) ($visual['color_login_bg'] ?? '#FFFFFF')); ?>; background-image: linear-gradient(140deg, rgba(11,33,61,0.86), rgba(6,22,41,0.78)), url('<?php echo cb_e($bgUrl); ?>');">
  <div class="container-fluid h-100">
    <div class="row h-100 align-items-center justify-content-center">
      <div class="col-12 col-lg-10 col-xl-9">
        <div class="cliente-login-card card shadow-sm">
          <div class="card-body p-0">
            <div class="row no-gutters">
              <div class="col-12 col-md-6 cliente-login-panel">
                <div class="cliente-login-brand text-center mb-3">
                  <img src="<?php echo cb_e($logoUrl); ?>" alt="Logo cliente" class="cliente-login-logo">
                  <h1 class="h5 mb-1"><?php echo cb_e($visual['titulo_login']); ?></h1>
                  <p class="text-muted mb-0"><?php echo cb_e($visual['subtitulo_login']); ?></p>
                </div>

                <?php if ($infoMsg !== ''): ?>
                  <div class="alert alert-warning" role="alert"><?php echo cb_e($infoMsg); ?></div>
                <?php endif; ?>
                <?php if ($errorMsg !== ''): ?>
                  <div class="alert alert-danger" role="alert"><?php echo cb_e($errorMsg); ?></div>
                <?php endif; ?>

                <form method="post" action="<?php echo cb_e(cb_url('login.php')); ?>" autocomplete="off" novalidate>
                  <input type="hidden" name="csrf_token" value="<?php echo cb_e($csrfToken); ?>">

                  <div class="form-group">
                    <label for="documento_tipo">Tipo de documento</label>
                    <select class="form-control" id="documento_tipo" name="documento_tipo" required>
                      <option value="DNI" <?php echo $inputTipo === 'DNI' ? 'selected' : ''; ?>>DNI</option>
                      <option value="CE" <?php echo $inputTipo === 'CE' ? 'selected' : ''; ?>>CE</option>
                    </select>
                  </div>

                  <div class="form-group">
                    <label for="documento_numero">Número de documento</label>
                    <input type="text" class="form-control" id="documento_numero" name="documento_numero" maxlength="20" value="<?php echo cb_e($inputNumero); ?>" required>
                  </div>

                  <div class="form-group">
                    <label for="clave">Clave</label>
                    <div class="input-group">
                      <input type="password" class="form-control" id="clave" name="clave" required>
                      <div class="input-group-append">
                        <button
                          type="button"
                          class="btn btn-outline-secondary js-toggle-password"
                          data-target="#clave"
                          title="Mostrar u ocultar clave"
                          aria-label="Mostrar u ocultar clave"
                        >
                          <i class="fas fa-eye" aria-hidden="true"></i>
                        </button>
                      </div>
                    </div>
                  </div>

                  <button type="submit" class="btn btn-primary btn-block">Ingresar</button>
                </form>
              </div>

              <div class="col-12 col-md-6 cliente-login-hero d-none d-md-flex">
                <?php if ($carouselEnabled): ?>
                  <div id="cliente-login-carousel" class="carousel slide w-100" data-ride="carousel" data-interval="4500">
                    <ol class="carousel-indicators">
                      <?php foreach ($carouselItems as $idx => $itemUrl): ?>
                        <li data-target="#cliente-login-carousel" data-slide-to="<?php echo cb_e((string) $idx); ?>" class="<?php echo $idx === 0 ? 'active' : ''; ?>"></li>
                      <?php endforeach; ?>
                    </ol>
                    <div class="carousel-inner">
                      <?php foreach ($carouselItems as $idx => $itemUrl): ?>
                        <div class="carousel-item <?php echo $idx === 0 ? 'active' : ''; ?>">
                          <img src="<?php echo cb_e($itemUrl); ?>" class="d-block w-100" alt="Imagen informativa <?php echo cb_e((string) ($idx + 1)); ?>">
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php else: ?>
                  <img src="<?php echo cb_e($carouselItems[0]); ?>" alt="Imagen de acceso" class="img-fluid">
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="<?php echo cb_e(cb_url('plugins/jquery/jquery.min.js')); ?>"></script>
<script src="<?php echo cb_e(cb_url('plugins/bootstrap/js/bootstrap.bundle.min.js')); ?>"></script>
<script src="<?php echo cb_e(cb_url('assets/js/cliente.js')); ?>"></script>
</body>
</html>
