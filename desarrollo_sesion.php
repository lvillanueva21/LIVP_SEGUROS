<?php

declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';

$context = requireModuleAccess('desarrollo-sesion');
$user = $context['user'];
$module = $context['module'];
$menu = modulesForRole((string) $user['role']);
$activeModule = 'desarrollo-sesion';
$pageTitle = 'Sesión';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(APP_NAME) ?> | <?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="assets/css/app.css?v=BS-NAVFIXV2">
    <link rel="stylesheet" href="assets/css/modules.css?v=BS-NAVFIXV2">
</head>
<body class="app-body" data-role="<?= e((string) $user['role']) ?>" data-user="<?= e((string) $user['id']) ?>">
<div class="app-shell">
    <?php require __DIR__ . '/views/partials/sidebar.php'; ?>
    <div class="sidebar-overlay" id="sidebar-overlay"></div>
    <main class="workspace">
        <?php require __DIR__ . '/views/partials/topbar.php'; ?>
        <section class="workspace-content">
            <article class="module-hero">
                <div class="module-hero-icon" aria-hidden="true"><?= e((string) ($module['icon'] ?? '•')) ?></div>
                <div>
                    <p class="eyebrow"><?= e((string) ($module['scope'] ?? 'DESARROLLO')) ?></p>
                    <h2><?= e($pageTitle) ?></h2>
                    <p><?= e((string) ($module['description'] ?? 'Módulo en preparación.')) ?></p>
                </div>
                <span class="module-access-badge">Acceso habilitado</span>
            </article>
            <section class="development-placeholder">
                <div class="development-mark" aria-hidden="true">◌</div>
                <h2>Sin funcionalidad todavía</h2>
                <p>Aquí se implementará el control de sesiones, accesos, bloqueos y auditoría de seguridad.</p>
            </section>
        </section>
    </main>
</div>
<script src="assets/js/app.js?v=BS-NAVFIXV2"></script>
</body>
</html>
