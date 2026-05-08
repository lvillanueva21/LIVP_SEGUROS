<?php
require_once __DIR__ . '/includes/api_client.php';

cb_boot_session();

if (cb_is_logged_in()) {
    cb_redirect('dashboard.php');
}

$visual = cb_get_effective_visual_config(true);
$errorMsg = '';
$infoMsg = '';
$inputUsuario = '';

$csrfKey = 'cliente_login_csrf';
if (empty($_SESSION[$csrfKey]) || !is_string($_SESSION[$csrfKey])) {
    $_SESSION[$csrfKey] = cb_random_token(32);
}
$csrfToken = (string) $_SESSION[$csrfKey];

if ((string) ($_GET['m'] ?? '') === 'sesion') {
    $infoMsg = 'Tu sesión expiró o no es válida. Inicia sesión nuevamente.';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $inputUsuario = strtoupper(trim((string) ($_POST['usuario'] ?? '')));
    $inputUsuario = preg_replace('/\s+/', '', $inputUsuario);
    $clave = (string) ($_POST['clave'] ?? '');
    $postedCsrf = (string) ($_POST['csrf_token'] ?? '');

    if ($csrfToken === '' || !hash_equals($csrfToken, $postedCsrf)) {
        $errorMsg = 'No se pudo procesar la solicitud. Intenta nuevamente.';
        $_SESSION[$csrfKey] = cb_random_token(32);
        $csrfToken = (string) $_SESSION[$csrfKey];
    } else {
        $_SESSION[$csrfKey] = cb_random_token(32);
        $csrfToken = (string) $_SESSION[$csrfKey];

        $documentoTipo = '';
        $documentoNumero = $inputUsuario;

        if (preg_match('/^\d{8}$/', $documentoNumero) === 1) {
            $documentoTipo = 'DNI';
        } elseif (preg_match('/^[A-Z0-9]{6,15}$/', $documentoNumero) === 1) {
            $documentoTipo = 'CE';
        }

        if ($documentoTipo === '' || trim($clave) === '') {
            $errorMsg = 'Credenciales inválidas o acceso no autorizado.';
        } else {
            $apiResult = cb_api_login($documentoTipo, $documentoNumero, $clave);
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

$hour = (int) date('G');
if ($hour < 12) {
    $saludo = '¡Buenos días!';
} elseif ($hour < 19) {
    $saludo = '¡Buenas tardes!';
} else {
    $saludo = '¡Buenas noches!';
}

$emojis = ['🌟', '🚀', '💡', '✨', '👋', '🧠', '⚡', '🔐', '📌', '✅'];
$mensajes = [
    '{saludo} {emoji} Tu compromiso hace la diferencia cada día.',
    '{saludo} {emoji} Sigamos trabajando por un mejor servicio.',
    'Bienvenid@ {emoji} Gracias por contribuir con la calidad y eficiencia del sistema.',
    '{saludo} {emoji} Cada acción cuenta para lograr resultados.',
    '¡Excelente jornada por delante! {emoji}',
];
$plantilla = $mensajes[array_rand($mensajes)];
$mensajeBienvenida = str_replace(
    ['{saludo}', '{emoji}'],
    [$saludo, $emojis[array_rand($emojis)]],
    $plantilla
);

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
$coverImageUrl = $carouselItems[0] ?? $bgUrl;
if (count($carouselItems) < 2) {
    $carouselEnabled = false;
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login - <?php echo cb_e(CLIENTE_NOMBRE); ?></title>
  <link rel="icon" href="<?php echo cb_e($faviconUrl); ?>">

  <link href="https://fonts.googleapis.com/css?family=Lato:300,400,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo cb_e(cb_url('plugins/fontawesome-free/css/all.min.css')); ?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo cb_e(cb_url('assets/css/cliente.css')); ?>">

  <style>
    :root {
      --cliente-primario: <?php echo cb_e((string) ($visual['color_primario'] ?? '#007BFF')); ?>;
      --cliente-secundario: <?php echo cb_e((string) ($visual['color_secundario'] ?? '#6C757D')); ?>;
      --cliente-login-bg: <?php echo cb_e((string) ($visual['color_login_bg'] ?? '#FFFFFF')); ?>;
      --cliente-login-saludo-text: <?php echo cb_e((string) ($visual['color_login_saludo_text'] ?? '#212529')); ?>;
    }
  </style>
</head>
<body>
<section class="ftco-section cliente-login-page" style="background-color: <?php echo cb_e((string) ($visual['color_login_bg'] ?? '#FFFFFF')); ?>;">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-md-7 text-center mb-5">
        <h2 class="heading-section" style="color: <?php echo cb_e((string) ($visual['color_login_saludo_text'] ?? '#212529')); ?>;"><?php echo cb_e($mensajeBienvenida); ?></h2>
      </div>
    </div>

    <div class="row justify-content-center">
      <div class="col-md-7 col-lg-5">
        <div class="wrap cliente-login-wrap">
          <div id="cliente-login-carousel" class="carousel slide cliente-login-cover-carousel" data-ride="carousel" data-interval="5000">
            <?php if ($carouselEnabled): ?>
              <ol class="carousel-indicators">
                <?php foreach ($carouselItems as $idx => $itemUrl): ?>
                  <li data-target="#cliente-login-carousel" data-slide-to="<?php echo cb_e((string) $idx); ?>" class="<?php echo $idx === 0 ? 'active' : ''; ?>"></li>
                <?php endforeach; ?>
              </ol>
            <?php endif; ?>
            <div class="carousel-inner">
              <?php if ($carouselEnabled): ?>
                <?php foreach ($carouselItems as $idx => $itemUrl): ?>
                  <div class="carousel-item<?php echo $idx === 0 ? ' active' : ''; ?>">
                    <img src="<?php echo cb_e($itemUrl); ?>" class="d-block w-100" alt="Portada login <?php echo cb_e((string) ($idx + 1)); ?>" draggable="false">
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <div class="carousel-item active">
                  <img src="<?php echo cb_e($coverImageUrl); ?>" class="d-block w-100" alt="Portada login" draggable="false">
                </div>
              <?php endif; ?>
            </div>
          </div>

          <div class="login-wrap p-4 p-md-5">
            <div class="d-flex align-items-center mb-2">
              <div class="w-100">
                <h4 class="mb-0">Iniciar sesión</h4>
              </div>
              <div class="w-100 text-right">
                <img src="<?php echo cb_e($logoUrl); ?>" alt="Logo cliente" class="cliente-login-logo-inline">
              </div>
            </div>

            <?php if ($infoMsg !== ''): ?>
              <div class="alert alert-info py-2 mb-3" role="alert"><?php echo cb_e($infoMsg); ?></div>
            <?php endif; ?>
            <?php if ($errorMsg !== ''): ?>
              <div class="alert alert-danger py-2 mb-3" role="alert"><?php echo cb_e($errorMsg); ?></div>
            <?php endif; ?>

            <form id="form-login" method="post" action="<?php echo cb_e(cb_url('login.php')); ?>" class="signin-form" autocomplete="off" novalidate>
              <input type="hidden" name="csrf_token" value="<?php echo cb_e($csrfToken); ?>">

              <div class="form-group mt-3">
                <label for="usuario" class="form-label-fixed">Usuario (DNI/CE)</label>
                <input
                  id="usuario"
                  type="text"
                  name="usuario"
                  class="form-control"
                  maxlength="15"
                  autocomplete="username"
                  required
                  autofocus
                  value="<?php echo cb_e($inputUsuario); ?>"
                >
              </div>

              <div class="form-group">
                <label for="password-field" class="form-label-fixed">Contraseña</label>
                <div class="position-relative">
                  <input
                    id="password-field"
                    type="password"
                    name="clave"
                    class="form-control"
                    autocomplete="current-password"
                    required
                  >
                  <span
                    toggle="#password-field"
                    class="fa fa-fw fa-eye field-icon toggle-password"
                    title="Mostrar u ocultar clave"
                    aria-label="Mostrar u ocultar clave"
                  ></span>
                </div>
              </div>

              <div class="form-group">
                <button id="btn-login" class="form-control btn btn-primary rounded submit px-3" type="submit">
                  Ingresar
                </button>
              </div>
            </form>

            <p class="text-center text-muted mt-3 mb-0 small">
              © <?php echo date('Y'); ?> - LuigiSistemas - Todos los derechos reservados.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<script src="<?php echo cb_e(cb_url('plugins/jquery/jquery.min.js')); ?>"></script>
<script src="<?php echo cb_e(cb_url('plugins/bootstrap/js/bootstrap.bundle.min.js')); ?>"></script>
<script src="<?php echo cb_e(cb_url('assets/js/cliente.js')); ?>"></script>
</body>
</html>