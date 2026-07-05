<?php
declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';

$user = requireAuth();
$requestedModuleId = trim((string) ($_GET['modulo'] ?? ''));
$reason = trim((string) ($_GET['motivo'] ?? 'permiso'));
$requestedModule = moduleForId($requestedModuleId);
$requestedLabel = (string) ($requestedModule['label'] ?? 'el módulo solicitado');

$menu = modulesForRole((string) $user['role']);
$activeModule = null;
$pageTitle = 'Acceso no autorizado';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(APP_NAME) ?> | Acceso no autorizado</title>
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="assets/css/modules.css">
</head>
<body class="app-body" data-role="<?= e($user['role']) ?>" data-user="<?= e((string) $user['id']) ?>">
<div class="app-shell">
    <?php require __DIR__ . '/views/partials/sidebar.php'; ?>

    <div class="sidebar-overlay" id="sidebar-overlay"></div>

    <main class="workspace">
        <?php require __DIR__ . '/views/partials/topbar.php'; ?>

        <section class="workspace-content">
            <article class="access-denied">
                <div class="access-denied-icon" aria-hidden="true">!</div>
                <p class="eyebrow"><?= $reason === 'modulo' ? 'RUTA NO DISPONIBLE' : 'PERMISO REQUERIDO' ?></p>
                <h2>No puedes acceder a <?= e($requestedLabel) ?></h2>
                <p>Tu perfil actual es <strong><?= e((string) $user['role_label']) ?></strong>. El sistema bloqueó la ruta desde el servidor, incluso si se intenta abrir la URL directamente.</p>
                <a class="module-primary-action" href="<?= e(moduleUrl('inicio')) ?>">Volver al Inicio</a>
            </article>

            <section class="module-card access-allowed-list">
                <p class="eyebrow">MÓDULOS DISPONIBLES</p>
                <h2>Opciones permitidas para tu perfil</h2>
                <div class="allowed-modules">
                    <?php foreach ($menu as $item): ?>
                        <a href="<?= e(moduleUrl((string) $item['id'])) ?>">
                            <span aria-hidden="true"><?= e((string) $item['icon']) ?></span>
                            <?= e((string) $item['label']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        </section>
    </main>
</div>
<script src="assets/js/app.js"></script>
</body>
</html>
