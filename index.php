<?php

declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';
require __DIR__ . '/config/client_accounts.php';

if (isAuthenticated()) {
    header('Location: ' . appRelativeUrl('dashboard.php'));
    exit;
}

function loginInferDocumentType(string $rawDocument): string
{
    $compact = preg_replace('/\s+/', '', trim($rawDocument)) ?: '';

    if (preg_match('/^\d{8}$/', $compact) === 1) {
        return 'DNI';
    }

    if (preg_match('/^\d{11}$/', $compact) === 1) {
        return 'RUC';
    }

    return 'CE';
}

$assets = loginAssets();
$error = '';
$notice = '';
$document = trim((string) ($_POST['document'] ?? ''));
$documentType = loginInferDocumentType($document);

if (isset($_GET['m']) && $_GET['m'] === 'logout') {
    $notice = 'Sesión cerrada correctamente.';
} elseif (isset($_GET['m']) && $_GET['m'] === 'sesion') {
    $notice = 'Inicia sesión para continuar.';
} elseif (isset($_GET['m']) && $_GET['m'] === 'acceso_actualizado') {
    $notice = 'Tu sesión se cerró porque el acceso fue actualizado o desactivado.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');

    $documentType = loginInferDocumentType($document);
    $document = authNormalizeDocument($documentType, $document);

    if (!csrfValidate('login_form', $csrfToken)) {
        $error = 'No se pudo validar el formulario. Intenta nuevamente.';
        securityRecordLoginAttempt($documentType, $document, 'csrf', 'login', null, 'Token CSRF inválido');
    } elseif ($document === '' || $password === '') {
        $error = 'Completa tu documento y contraseña.';
    } else {
        $block = securityLoginBlockInfo($documentType, $document);

        if (($block['blocked'] ?? false) === true) {
            $minutes = max(1, (int) ($block['minutes'] ?? LIVP_LOGIN_BLOCK_MINUTES));
            $error = 'El acceso está bloqueado temporalmente por intentos fallidos. Intenta nuevamente en aproximadamente ' . $minutes . ' minuto(s).';
            securityRecordLoginAttempt($documentType, $document, 'bloqueado', 'login', null, 'Bloqueo temporal');
        } else {
            $developmentAttempt = authAttemptDevelopmentLogin($documentType, $document, $password);
            $developmentStatus = (string) ($developmentAttempt['status'] ?? 'not_found');

            if ($developmentStatus === 'success') {
                securityClearLoginFailures($documentType, $document);
                securityRecordLoginAttempt($documentType, $document, 'exitoso', 'database', (int) $developmentAttempt['database_user_id'], 'Usuario Desarrollo');
                createUserSession((array) $developmentAttempt['user']);
                header('Location: ' . appRelativeUrl('dashboard.php'));
                exit;
            }

            if ($developmentStatus === 'inactive') {
                securityRecordLoginAttempt($documentType, $document, 'fallido', 'database', (int) $developmentAttempt['database_user_id'], 'Usuario Desarrollo inactivo');
                $error = 'Las credenciales no coinciden, la cuenta está inactiva o todavía no fue creada.';
            } elseif ($developmentStatus === 'invalid_password') {
                securityRecordLoginAttempt($documentType, $document, 'fallido', 'database', (int) $developmentAttempt['database_user_id'], 'Contraseña incorrecta');
                $error = 'Las credenciales no coinciden, la cuenta está inactiva o todavía no fue creada.';
            } elseif ($developmentStatus === 'database_error') {
                $error = 'No se pudo validar el acceso real. Revisa la conexión y las tablas de autenticación.';
            } else {
                $matchedUser = null;

                foreach (loginUsers() as $user) {
                    if (!is_array($user)) {
                        continue;
                    }

                    $candidateType = strtoupper(trim((string) ($user['document_type'] ?? '')));
                    $candidateDocument = authNormalizeDocument($candidateType, (string) ($user['document'] ?? ''));

                    if ($candidateType === $documentType && $candidateDocument === $document) {
                        $matchedUser = $user;
                        break;
                    }
                }

                if ($matchedUser !== null
                    && (($matchedUser['active'] ?? true) !== false)
                    && password_verify($password, (string) ($matchedUser['password_hash'] ?? ''))
                ) {
                    securityClearLoginFailures($documentType, $document);
                    securityRecordLoginAttempt($documentType, $document, 'exitoso', 'demo', null, 'Acceso demo o cliente temporal');
                    createUserSession($matchedUser);
                    header('Location: ' . appRelativeUrl('dashboard.php'));
                    exit;
                }

                securityRecordLoginAttempt($documentType, $document, 'fallido', 'demo', null, 'Credenciales no válidas');
                $error = 'Las credenciales no coinciden, la cuenta está inactiva o todavía no fue creada.';
            }
        }
    }
}

$csrfLoginToken = csrfToken('login_form');
$hour = (int) date('G');
$greeting = $hour < 12 ? '¡Buenos días!' : ($hour < 19 ? '¡Buenas tardes!' : '¡Buenas noches!');
$greetingMessages = [
    $greeting . ' 👋 Bienvenido a Broker Seguros.',
    $greeting . ' ✨ Inicia sesión para continuar.',
    '🔐 La seguridad comienza con un inicio de sesión.',
    $greeting . ' 🚀 Sigamos avanzando con el sistema.',
];
$welcome = $greetingMessages[array_rand($greetingMessages)];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(APP_NAME) ?> | Iniciar sesión</title>
    <?php if ($assets['favicon'] !== ''): ?>
        <link rel="icon" href="<?= e($assets['favicon']) ?>">
    <?php endif; ?>
    <link href="https://fonts.googleapis.com/css?family=Lato:300,400,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/login/css/ls-login-theme.css?v=AUTHDESARROLLOV2">
</head>
<body>
<section class="ftco-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-9 text-center mb-5">
                <h2 class="heading-section"><?= e($welcome) ?></h2>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="wrap">
                    <?php if ($assets['carrusel'] !== []): ?>
                        <div id="loginCoverCarousel" class="carousel slide login-cover-carousel" data-ride="carousel" data-interval="5000">
                            <div class="carousel-inner">
                                <?php foreach ($assets['carrusel'] as $index => $image): ?>
                                    <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                                        <img
                                            src="<?= e($image['url']) ?>"
                                            class="d-block w-100 js-cover-image"
                                            alt="Portada de acceso <?= $index + 1 ?>"
                                            draggable="false"
                                        >
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="login-wrap p-4 p-md-5">
                        <div class="d-flex align-items-center mb-2">
                            <div class="w-100">
                                <?php if ($assets['logo'] !== ''): ?>
                                    <img class="login-default-logo" src="<?= e($assets['logo']) ?>" alt="<?= e(APP_NAME) ?>">
                                <?php endif; ?>
                                <h4 class="mb-0">Iniciar sesión</h4>
                                <p class="login-caption mb-0">Acceso a la maqueta funcional de Broker Seguros.</p>
                            </div>
                            <div class="w-100">
                                <p class="social-media d-flex justify-content-end m-0">
                                    <button type="button" class="social-icon d-flex align-items-center justify-content-center" data-toggle="modal" data-target="#demoAccessModal" title="Accesos de prueba" aria-label="Abrir accesos de prueba">
                                        <span class="fa fa-flask"></span>
                                    </button>
                                </p>
                            </div>
                        </div>

                        <?php if ($notice !== ''): ?>
                            <div class="alert alert-info py-2 mb-3" role="alert"><?= e($notice) ?></div>
                        <?php endif; ?>
                        <?php if ($error !== ''): ?>
                            <div class="alert alert-danger py-2 mb-3" role="alert"><?= e($error) ?></div>
                        <?php endif; ?>

                        <form id="form-login" method="post" class="signin-form" autocomplete="off" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= e($csrfLoginToken) ?>">

                            <div class="form-group mt-3">
                                <label for="document" class="form-label-fixed">
                                    Documento
                                    <span class="fa fa-info-circle info-icon" tabindex="0" role="button" data-toggle="tooltip" data-placement="right" title="Usa DNI, CE o RUC. El sistema identifica el tipo por el formato ingresado." aria-label="Información sobre documento"></span>
                                </label>
                                <input id="document" type="text" name="document" class="form-control" maxlength="30" autocomplete="username" required value="<?= e($document) ?>">
                            </div>

                            <div class="form-group">
                                <label for="password-field" class="form-label-fixed">
                                    Contraseña
                                    <span class="fa fa-info-circle info-icon" tabindex="0" role="button" data-toggle="tooltip" data-placement="right" title="No compartas tu contraseña." aria-label="Información sobre contraseña"></span>
                                </label>
                                <div class="position-relative">
                                    <input id="password-field" type="password" name="password" class="form-control" autocomplete="current-password" required>
                                    <span toggle="#password-field" class="fa fa-fw fa-eye field-icon toggle-password" title="Mostrar u ocultar"></span>
                                </div>
                            </div>

                            <div class="form-group">
                                <button id="btn-login" class="form-control btn btn-primary rounded submit px-3" type="submit">Ingresar</button>
                            </div>
                        </form>

                        <p class="text-center text-muted mt-3 mb-0 small">© <?= date('Y') ?> - Broker Seguros - Entorno de pruebas.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="modal fade" id="demoAccessModal" tabindex="-1" aria-labelledby="demoAccessModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="demoAccessModalLabel">Accesos de prueba</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">Selecciona un acceso. El formulario se completará, pero tú decidirás cuándo presionar Ingresar.</p>
                <div class="demo-access-list">
                    <button type="button" class="demo-access-option" data-document="12345678" data-password="Gerente2026!">
                        <strong>Gerente demo</strong><span>DNI 12345678</span><small>Clave: Gerente2026!</small>
                    </button>
                    <button type="button" class="demo-access-option" data-document="87654321" data-password="Ejecutivo2026!">
                        <strong>Ejecutivo demo</strong><span>DNI 87654321</span><small>Clave: Ejecutivo2026!</small>
                    </button>
                    <button type="button" class="demo-access-option" data-document="20123456789" data-password="Empresa2026!">
                        <strong>Empresa demo</strong><span>RUC 20123456789</span><small>Clave: Empresa2026!</small>
                    </button>
                    <button type="button" class="demo-access-option" data-document="20698765432" data-password="Consorcio2026!">
                        <strong>Consorcio demo</strong><span>RUC 20698765432</span><small>Clave: Consorcio2026!</small>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/login/js/login-ui.js?v=AUTHDESARROLLOV2"></script>
</body>
</html>
