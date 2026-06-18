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
    $infoMsg = 'Inicia sesión para continuar.';
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

date_default_timezone_set('America/Lima');
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
    '{emoji} Recuerda: la precisión también es parte del progreso.',
    '{saludo} {emoji} Qué gusto verte de nuevo.',
    '¡Hola! {emoji} Esperamos que hoy tengas un gran día.',
    '{saludo} {emoji} Siempre es bueno verte por aquí.',
    'Bienvenid@ de nuevo {emoji} ¡Vamos con todo hoy!',
];
$plantilla = $mensajes[array_rand($mensajes)];
$mensajeBienvenida = str_replace(
    ['{saludo}', '{emoji}'],
    [$saludo, $emojis[array_rand($emojis)]],
    $plantilla
);

$visualAssets = is_array($visual['assets'] ?? null) ? $visual['assets'] : [];
$tituloLoginPagina = trim((string) ($visual['titulo_login'] ?? 'Login | Sistema'));
if ($tituloLoginPagina === '') {
    $tituloLoginPagina = 'Login | Sistema';
}

$faviconUrl = cb_asset_url((string) ($visualAssets['favicon_url'] ?? ''), 'assets/default/branding/favicon.svg');
$bgUrl = cb_asset_url((string) ($visualAssets['login_bg_url'] ?? ''), 'assets/default/login/login_fondo.svg');
$carouselItemsRaw = isset($visualAssets['carrusel']) && is_array($visualAssets['carrusel']) ? $visualAssets['carrusel'] : [];
$carouselItems = [];
foreach ($carouselItemsRaw as $itemUrl) {
    $safeUrl = cb_asset_url((string) $itemUrl, '');
    if ($safeUrl !== '' && !in_array($safeUrl, $carouselItems, true)) {
        $carouselItems[] = $safeUrl;
    }
}
if (!$carouselItems) {
    $carouselItems = [$bgUrl];
}
$carouselItems = array_values(array_filter($carouselItems));
if (!$carouselItems) {
    $carouselItems = [$bgUrl];
}

$loginBotones = [
    [
        'texto_boton' => 'Contactar a soporte',
        'icono_css' => 'fa fa-whatsapp',
        'url_destino' => 'https://wa.me/51964881841?text=Hola%2C%20necesito%20apoyo%20del%20%C3%A1rea%20de%20Soporte.',
    ],
    [
        'texto_boton' => 'Recuperar contraseña',
        'icono_css' => 'fa fa-unlock-alt',
        'url_destino' => 'https://wa.me/51964881841?text=Hola%2C%20quiero%20recuperar%20mi%20contrase%C3%B1a%2C%20mi%20DNI%20y%2Fo%20nombre%20completo%20es%3A',
    ],
];
$remoteLoginBotones = is_array($visual['login_botones'] ?? null) ? $visual['login_botones'] : [];
$loginBotones = cb_normalize_login_botones($remoteLoginBotones, $loginBotones);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo cb_e($tituloLoginPagina); ?></title>
  <link rel="icon" type="image/x-icon" href="<?php echo cb_e($faviconUrl); ?>">

  <link href="https://fonts.googleapis.com/css?family=Lato:300,400,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo cb_e(cb_url('plugins/fontawesome-free/css/all.min.css')); ?>">
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo cb_e(cb_url('assets/css/cliente.css')); ?>">

  <style>
    .form-group { position: relative; margin-bottom: 1.25rem; }
    .form-control-placeholder { display: none !important; }

    .form-label-fixed {
      display: flex;
      align-items: center;
      gap: .4rem;
      font-weight: 600;
      margin-bottom: .4rem;
    }

    .info-icon {
      cursor: help;
      font-size: .95rem;
      line-height: 1;
      color: #6c757d;
    }

    .info-icon:hover,
    .info-icon:focus { color: #495057; }

    .field-icon {
      position: absolute;
      top: 50%;
      right: .75rem;
      transform: translateY(-50%);
      z-index: 2;
      cursor: pointer;
      user-select: none;
      color: #6c757d;
    }

    #password-field { padding-right: 2.25rem; }

    .text-decoration-none:hover { text-decoration: underline !important; }

    body,
    .ftco-section {
      background-color: <?php echo cb_e((string) ($visual['color_login_bg'] ?? '#FFFFFF')); ?> !important;
    }

    .heading-section {
      color: <?php echo cb_e((string) ($visual['color_login_saludo_text'] ?? '#212529')); ?> !important;
    }
  </style>
</head>
<body>
<section class="ftco-section">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-md-7 text-center mb-5">
        <h2 class="heading-section"><?php echo cb_e($mensajeBienvenida); ?></h2>
      </div>
    </div>

    <div class="row justify-content-center">
      <div class="col-md-7 col-lg-5">
        <div class="wrap">
          <div id="loginCoverCarousel" class="carousel slide login-cover-carousel" data-ride="carousel" data-interval="5000">
            <div class="carousel-inner">
              <?php foreach ($carouselItems as $idx => $rutaImg): ?>
                <?php
                $rutaImg = trim((string) $rutaImg);
                if ($rutaImg === '') {
                    continue;
                }
                $downloadName = basename(parse_url($rutaImg, PHP_URL_PATH) ?: $rutaImg);
                if ($downloadName === '') {
                    $downloadName = 'imagen_login.webp';
                }
                ?>
                <div class="carousel-item<?php echo ((int) $idx === 0) ? ' active' : ''; ?>">
                  <img
                    src="<?php echo cb_e($rutaImg); ?>"
                    class="d-block w-100 js-cover-image"
                    alt="Portada login <?php echo (int) $idx + 1; ?>"
                    draggable="false"
                    data-toggle="modal"
                    data-target="#coverImageModal"
                    data-full-src="<?php echo cb_e($rutaImg); ?>"
                    data-download-name="<?php echo cb_e($downloadName); ?>"
                  >
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="login-wrap p-4 p-md-5">
            <div class="d-flex align-items-center mb-2">
              <div class="w-100">
                <h4 class="mb-0">Iniciar sesión</h4>
              </div>
              <div class="w-100">
                <p class="social-media d-flex justify-content-end m-0">
                  <?php foreach ($loginBotones as $btnTop): ?>
                    <?php
                    $topUrl = trim((string) ($btnTop['url_destino'] ?? ''));
                    if ($topUrl === '') {
                        continue;
                    }
                    $topIcon = trim((string) ($btnTop['icono_css'] ?? ''));
                    if ($topIcon === '') {
                        $topIcon = 'fas fa-link';
                    }
                    $topText = trim((string) ($btnTop['texto_boton'] ?? 'Enlace'));
                    $topAbsUrl = (preg_match('/^https?:\/\//i', $topUrl) === 1);
                    ?>
                    <a
                      href="<?php echo cb_e($topUrl); ?>"
                      class="social-icon d-flex align-items-center justify-content-center"
                      title="<?php echo cb_e($topText); ?>"
                      style="margin-left:.35rem;"
                      <?php if ($topAbsUrl): ?>target="_blank" rel="noopener noreferrer"<?php endif; ?>
                    ><span class="<?php echo cb_e($topIcon); ?>"></span></a>
                  <?php endforeach; ?>
                </p>
              </div>
            </div>

            <?php if ($infoMsg !== ''): ?>
              <div class="alert alert-info py-2 mb-3" role="alert"><?php echo cb_e($infoMsg); ?></div>
            <?php endif; ?>

            <?php if ($errorMsg !== ''): ?>
              <div class="alert alert-danger py-2 mb-3" role="alert"><?php echo cb_e($errorMsg); ?></div>
            <?php endif; ?>

            <form id="form-login" action="<?php echo cb_e(cb_url('login.php')); ?>" method="post" class="signin-form" autocomplete="off" novalidate>
              <input type="hidden" name="csrf_token" value="<?php echo cb_e($csrfToken); ?>">

              <div class="form-group mt-3">
                <label for="usuario" class="form-label-fixed">
                  Usuario (DNI/CE)
                  <span
                    class="fa fa-info-circle info-icon"
                    tabindex="0"
                    role="button"
                    data-toggle="tooltip"
                    data-placement="right"
                    title="Tu usuario es tu documento de identidad."
                    aria-label="Más información sobre el campo Usuario"
                  ></span>
                </label>
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
                <label for="password-field" class="form-label-fixed">
                  Contraseña
                  <span
                    class="fa fa-info-circle info-icon"
                    tabindex="0"
                    role="button"
                    data-toggle="tooltip"
                    data-placement="right"
                    title="No compartas tu contraseña."
                    aria-label="Más información sobre el campo Contraseña"
                  ></span>
                </label>

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
                    title="Mostrar u ocultar"
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

<div class="modal fade" id="coverImageModal" tabindex="-1" aria-labelledby="coverImageModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="coverImageModalLabel">Vista de la imagen</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body text-center">
        <img id="coverModalImage" src="" class="img-fluid rounded" alt="Vista ampliada">
      </div>
      <div class="modal-footer">
        <a id="coverModalDownload" class="btn btn-primary" href="#" download>Descargar imagen</a>
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script src="<?php echo cb_e(cb_url('plugins/jquery/jquery.min.js')); ?>"></script>
<script src="<?php echo cb_e(cb_url('plugins/bootstrap/js/bootstrap.bundle.min.js')); ?>"></script>
<script src="<?php echo cb_e(cb_url('assets/js/cliente.js')); ?>"></script>
</body>
</html>
