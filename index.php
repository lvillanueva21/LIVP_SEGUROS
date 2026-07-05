<?php
declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';
require __DIR__ . '/config/client_accounts.php';

if (isAuthenticated()) {
    header('Location: ' . appRelativeUrl('dashboard.php'));
    exit;
}

$users = loginUsers();
$error = '';
$documentType = $_POST['document_type'] ?? 'DNI';
$document = trim((string) ($_POST['document'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    $documentType = strtoupper(trim((string) $documentType));
    $document = $documentType === 'RUC' ? clientNormalizeDocument($document) : $document;

    $allowedTypes = ['DNI', 'CE', 'RUC'];
    if (!in_array($documentType, $allowedTypes, true) || $document === '' || $password === '') {
        $error = 'Completa el tipo de documento, número y contraseña.';
    } else {
        $matchedUser = null;
        foreach ($users as $user) {
            if (!is_array($user)) {
                continue;
            }

            $candidateType = strtoupper(trim((string) ($user['document_type'] ?? '')));
            $candidateDocument = $candidateType === 'RUC'
                ? clientNormalizeDocument((string) ($user['document'] ?? ''))
                : (string) ($user['document'] ?? '');

            if ($candidateType === $documentType && $candidateDocument === $document) {
                $matchedUser = $user;
                break;
            }
        }

        if ($matchedUser !== null && (($matchedUser['active'] ?? true) !== false) && password_verify($password, (string) ($matchedUser['password_hash'] ?? ''))) {
            createUserSession($matchedUser);
            header('Location: ' . appRelativeUrl('dashboard.php'));
            exit;
        }

        $error = 'Las credenciales no coinciden, la cuenta está inactiva o todavía no fue creada.';
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(APP_NAME) ?> | Maqueta funcional</title>
    <link rel="stylesheet" href="assets/css/app.css?v=BS-20260627-234916-CLIENTACCOUNTSV1">
</head>
<body class="login-body">
<main class="login-shell">
    <section class="login-branding">
        <div class="brand-mark">B</div>
        <p class="eyebrow">MAQUETA FUNCIONAL · ENTORNO DE DEMOSTRACIÓN</p>
        <h1>BROKER<br>SEGUROS</h1>
        <p class="brand-description">Prototipo para validar expedientes, pólizas, pagos, siniestros y el portal de consulta del cliente.</p>
        <div class="brand-notes">
            <span>PHP + sesiones</span>
            <span>Accesos Cliente protegidos</span>
            <span>Diseño responsive</span>
        </div>
    </section>

    <section class="login-panel" aria-labelledby="login-title">
        <div class="panel-header">
            <p class="eyebrow">ACCESO AL SISTEMA</p>
            <h2 id="login-title">Iniciar sesión</h2>
            <p>Las empresas y consorcios pueden ingresar con el acceso creado por Gerencia.</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" class="login-form" novalidate>
            <label for="document_type">Tipo de documento</label>
            <select id="document_type" name="document_type" required>
                <option value="DNI" <?= $documentType === 'DNI' ? 'selected' : '' ?>>DNI</option>
                <option value="CE" <?= $documentType === 'CE' ? 'selected' : '' ?>>Carné de extranjería</option>
                <option value="RUC" <?= $documentType === 'RUC' ? 'selected' : '' ?>>RUC</option>
            </select>

            <label id="document_label" for="document">Número de DNI</label>
            <input id="document" name="document" value="<?= e($document) ?>" inputmode="numeric" autocomplete="username" placeholder="Ingresa tu documento" required>

            <label for="password">Contraseña</label>
            <div class="password-field">
                <input id="password" type="password" name="password" autocomplete="current-password" placeholder="Ingresa tu contraseña" required>
                <button type="button" class="text-button" id="toggle-password" aria-label="Mostrar contraseña">Ver</button>
            </div>

            <button class="primary-button" type="submit">Ingresar al sistema <span>→</span></button>
        </form>

        <section class="demo-access" aria-labelledby="demo-title">
            <div class="demo-access-title">
                <h3 id="demo-title">Accesos de prueba</h3>
                <span>Haz clic para completar el formulario</span>
            </div>
            <div class="demo-grid">
                <button type="button" class="demo-card" data-doc-type="DNI" data-document="12345678" data-password="Gerente2026!">
                    <strong>Gerente</strong><span>DNI 12345678</span><small>Clave: Gerente2026!</small>
                </button>
                <button type="button" class="demo-card" data-doc-type="DNI" data-document="87654321" data-password="Ejecutivo2026!">
                    <strong>Ejecutivo</strong><span>DNI 87654321</span><small>Clave: Ejecutivo2026!</small>
                </button>
                <button type="button" class="demo-card" data-doc-type="RUC" data-document="20123456789" data-password="Empresa2026!">
                    <strong>Empresa demo</strong><span>RUC 20123456789</span><small>Clave: Empresa2026!</small>
                </button>
                <button type="button" class="demo-card" data-doc-type="RUC" data-document="20698765432" data-password="Consorcio2026!">
                    <strong>Consorcio demo</strong><span>RUC 20698765432</span><small>Clave: Consorcio2026!</small>
                </button>
            </div>
            <p class="demo-detail">Los accesos creados en Usuarios no aparecen aquí: se usan directamente con su RUC y contraseña asignada.</p>
        </section>
    </section>
</main>
<script src="assets/js/login.js?v=BS-20260627-234916-CLIENTACCOUNTSV1"></script>
</body>
</html>
