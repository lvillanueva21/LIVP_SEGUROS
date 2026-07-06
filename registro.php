<?php

declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';

if (isAuthenticated()) {
    header('Location: ' . appRelativeUrl('dashboard.php'));
    exit;
}

$assets = loginAssets();
$error = '';
$notice = '';
$form = [
    'nombres' => trim((string) ($_POST['nombres'] ?? '')),
    'apellidos' => trim((string) ($_POST['apellidos'] ?? '')),
    'document_type' => 'DNI',
    'document' => trim((string) ($_POST['document'] ?? '')),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValidate('register_development_form', (string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'No se pudo validar el formulario. Intenta nuevamente.';
    } else {
        $result = authRegisterDevelopment([
            'nombres' => $form['nombres'],
            'apellidos' => $form['apellidos'],
            'document_type' => 'DNI',
            'document' => $form['document'],
            'password' => (string) ($_POST['password'] ?? ''),
            'password_repeat' => (string) ($_POST['password_repeat'] ?? ''),
        ]);

        if (($result['ok'] ?? false) === true) {
            $notice = 'Usuario Desarrollo creado correctamente. Ya puedes iniciar sesión con su DNI y contraseña.';
            $form = ['nombres' => '', 'apellidos' => '', 'document_type' => 'DNI', 'document' => ''];
            csrfRotate('register_development_form');
        } else {
            $error = (string) ($result['error'] ?? 'No se pudo crear el usuario.');
        }
    }
}

$csrfRegisterToken = csrfToken('register_development_form');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(APP_NAME) ?> | Registro temporal Desarrollo</title>
    <?php if ($assets['favicon'] !== ''): ?>
        <link rel="icon" href="<?= e($assets['favicon']) ?>">
    <?php endif; ?>
    <link href="https://fonts.googleapis.com/css?family=Lato:300,400,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/login/css/ls-login-theme.css?v=AUTHDESARROLLOV1">
</head>
<body>
<section class="ftco-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-9 text-center mb-5"><h2 class="heading-section">Registro temporal de usuario Desarrollo</h2></div>
        </div>
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="wrap">
                    <?php if ($assets['carrusel'] !== []): ?>
                        <div id="loginCoverCarousel" class="carousel slide login-cover-carousel" data-ride="carousel" data-interval="5000">
                            <div class="carousel-inner">
                                <?php foreach ($assets['carrusel'] as $index => $image): ?>
                                    <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                                        <img src="<?= e($image['url']) ?>" class="d-block w-100 js-cover-image" alt="Portada de registro <?= $index + 1 ?>" draggable="false" data-toggle="modal" data-target="#coverImageModal" data-full-src="<?= e($image['url']) ?>">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="login-wrap p-4 p-md-5">
                        <div class="d-flex align-items-center mb-2">
                            <div class="w-100">
                                <?php if ($assets['logo'] !== ''): ?><img class="login-default-logo" src="<?= e($assets['logo']) ?>" alt="<?= e(APP_NAME) ?>"><?php endif; ?>
                                <h4 class="mb-0">Crear usuario Desarrollo</h4>
                                <p class="login-caption mb-0">Página temporal de pruebas. El usuario quedará guardado en MySQL.</p>
                            </div>
                            <div class="w-100"><p class="social-media d-flex justify-content-end m-0"><a href="<?= e(appRelativeUrl('index.php')) ?>" class="social-icon d-flex align-items-center justify-content-center" title="Ir al login" aria-label="Ir al login"><span class="fa fa-sign-in"></span></a></p></div>
                        </div>

                        <?php if ($notice !== ''): ?><div class="alert alert-success py-2 mb-3" role="alert"><?= e($notice) ?><br><a href="<?= e(appRelativeUrl('index.php')) ?>" class="alert-link">Ir al login</a></div><?php endif; ?>
                        <?php if ($error !== ''): ?><div class="alert alert-danger py-2 mb-3" role="alert"><?= e($error) ?></div><?php endif; ?>

                        <form method="post" class="signin-form" autocomplete="off" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= e($csrfRegisterToken) ?>">
                            <div class="form-group mt-3"><label for="nombres" class="form-label-fixed">Nombres</label><input id="nombres" type="text" name="nombres" class="form-control" maxlength="120" required value="<?= e($form['nombres']) ?>"></div>
                            <div class="form-group"><label for="apellidos" class="form-label-fixed">Apellidos</label><input id="apellidos" type="text" name="apellidos" class="form-control" maxlength="120" required value="<?= e($form['apellidos']) ?>"></div>
                            <div class="form-group"><label for="document_type" class="form-label-fixed">Tipo de documento</label><select id="document_type" name="document_type" class="form-control" required><option value="DNI">DNI</option></select></div>
                            <div class="form-group"><label for="document" class="form-label-fixed">Número de DNI</label><input id="document" type="text" name="document" class="form-control" inputmode="numeric" pattern="\d{8}" maxlength="8" required value="<?= e($form['document']) ?>"></div>
                            <div class="form-group"><label for="password-field" class="form-label-fixed">Contraseña</label><div class="position-relative"><input id="password-field" type="password" name="password" class="form-control" minlength="8" autocomplete="new-password" required><span toggle="#password-field" class="fa fa-fw fa-eye field-icon toggle-password" title="Mostrar u ocultar"></span></div></div>
                            <div class="form-group"><label for="password-repeat-field" class="form-label-fixed">Repetir contraseña</label><div class="position-relative"><input id="password-repeat-field" type="password" name="password_repeat" class="form-control" minlength="8" autocomplete="new-password" required><span toggle="#password-repeat-field" class="fa fa-fw fa-eye field-icon toggle-password" title="Mostrar u ocultar"></span></div></div>
                            <div class="form-group"><button class="form-control btn btn-primary rounded submit px-3" type="submit">Crear usuario Desarrollo</button></div>
                        </form>
                        <p class="text-center text-muted mt-3 mb-0 small">Puedes quedarte en esta página para registrar más usuarios o regresar al login cuando quieras.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<div class="modal fade" id="coverImageModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Vista de la imagen</h5><button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button></div><div class="modal-body text-center"><img id="coverModalImage" src="" class="img-fluid rounded" alt="Vista ampliada"></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button></div></div></div></div>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/login/js/login-ui.js?v=AUTHDESARROLLOV1"></script>
</body>
</html>
