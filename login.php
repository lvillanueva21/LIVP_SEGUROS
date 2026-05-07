<?php
require_once __DIR__ . '/includes/api_client.php';

cb_boot_session();

if (cb_is_logged_in()) {
    cb_redirect('dashboard.php');
}

$visual = cb_get_visual_config();
$errorMsg = '';
$inputTipo = 'DNI';
$inputNumero = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $inputTipo = strtoupper(trim((string) ($_POST['documento_tipo'] ?? '')));
    $inputNumero = trim((string) ($_POST['documento_numero'] ?? ''));
    $clave = (string) ($_POST['clave'] ?? '');

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
                'config_visual' => [
                    'titulo_login' => (string) ($configVisual['titulo_login'] ?? CLIENTE_LOGIN_TITULO),
                    'subtitulo_login' => (string) ($configVisual['subtitulo_login'] ?? CLIENTE_LOGIN_SUBTITULO),
                    'color_primario' => cb_normalize_hex_color((string) ($configVisual['color_primario'] ?? CLIENTE_COLOR_PRIMARIO), CLIENTE_COLOR_PRIMARIO),
                    'color_secundario' => cb_normalize_hex_color((string) ($configVisual['color_secundario'] ?? CLIENTE_COLOR_SECUNDARIO), CLIENTE_COLOR_SECUNDARIO),
                    'id_archivo_logo' => $configVisual['id_archivo_logo'] ?? null,
                    'id_archivo_favicon' => $configVisual['id_archivo_favicon'] ?? null,
                    'id_archivo_fondo' => $configVisual['id_archivo_fondo'] ?? null,
                ],
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login - <?php echo cb_e(CLIENTE_NOMBRE); ?></title>
  <link rel="stylesheet" href="<?php echo cb_e(cb_url('plugins/fontawesome-free/css/all.min.css')); ?>">
  <link rel="stylesheet" href="<?php echo cb_e(cb_url('plugins/icheck-bootstrap/icheck-bootstrap.min.css')); ?>">
  <link rel="stylesheet" href="<?php echo cb_e(cb_url('dist/css/adminlte.min.css')); ?>">
  <link rel="stylesheet" href="<?php echo cb_e(cb_url('assets/css/cliente.css')); ?>">
  <style>
    :root {
      --cliente-primario: <?php echo cb_e($visual['color_primario']); ?>;
      --cliente-secundario: <?php echo cb_e($visual['color_secundario']); ?>;
    }
  </style>
</head>
<body class="hold-transition login-page cliente-login-page">
<div class="login-box">
  <div class="card card-outline card-primary">
    <div class="card-header text-center">
      <h1 class="h4 mb-1"><?php echo cb_e($visual['titulo_login']); ?></h1>
      <p class="text-muted mb-0"><?php echo cb_e($visual['subtitulo_login']); ?></p>
    </div>
    <div class="card-body">
      <?php if ($errorMsg !== ''): ?>
        <div class="alert alert-danger" role="alert"><?php echo cb_e($errorMsg); ?></div>
      <?php endif; ?>

      <form method="post" action="<?php echo cb_e(cb_url('login.php')); ?>" autocomplete="off" novalidate>
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
  </div>
</div>

<script src="<?php echo cb_e(cb_url('plugins/jquery/jquery.min.js')); ?>"></script>
<script src="<?php echo cb_e(cb_url('plugins/bootstrap/js/bootstrap.bundle.min.js')); ?>"></script>
<script src="<?php echo cb_e(cb_url('assets/js/cliente.js')); ?>"></script>
</body>
</html>

