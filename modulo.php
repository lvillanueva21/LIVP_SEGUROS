<?php
declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';

$moduleId = trim((string) ($_GET['modulo'] ?? ''));

if ($moduleId === 'inicio') {
    requireAuth();
    header('Location: ' . appRelativeUrl('dashboard.php'));
    exit;
}

$context = requireModuleAccess($moduleId);
$user = $context['user'];
$module = $context['module'];
$menu = modulesForRole((string) $user['role']);
$activeModule = (string) $module['id'];
$pageTitle = (string) $module['label'];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(APP_NAME) ?> | <?= e($pageTitle) ?></title>
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
            <article class="module-hero">
                <div class="module-hero-icon" aria-hidden="true"><?= e((string) ($module['icon'] ?? '•')) ?></div>
                <div>
                    <p class="eyebrow"><?= e((string) ($module['scope'] ?? 'MÓDULO')) ?></p>
                    <h2><?= e($pageTitle) ?></h2>
                    <p><?= e((string) ($module['description'] ?? 'Módulo habilitado para este perfil.')) ?></p>
                </div>
                <span class="module-access-badge">Acceso habilitado</span>
            </article>

            <section class="module-grid">
                <article class="module-card">
                    <p class="eyebrow">RUTA PROTEGIDA</p>
                    <h2>Permiso validado en servidor</h2>
                    <p>Esta pantalla se abrió porque tu rol <strong><?= e((string) $user['role_label']) ?></strong> tiene permiso para acceder al módulo <strong><?= e($pageTitle) ?></strong>.</p>
                    <dl class="module-details">
                        <div><dt>Perfil actual</dt><dd><?= e((string) $user['profile_title']) ?></dd></div>
                        <div><dt>Tipo de cuenta</dt><dd><?= e((string) ($user['account_type_label'] ?? 'No registrado')) ?></dd></div>
                        <div><dt>Ámbito del módulo</dt><dd><?= e((string) ($module['scope'] ?? 'Módulo')) ?></dd></div>
                    </dl>
                </article>

                <article class="module-card module-card-accent">
                    <p class="eyebrow">SIGUIENTE IMPLEMENTACIÓN</p>
                    <h2>Base preparada</h2>
                    <p>La ruta, el menú y el permiso ya existen. La próxima fase podrá agregar aquí la funcionalidad real sin volver a crear el control de acceso.</p>
                    <ul class="module-checklist">
                        <li>Ruta real del módulo.</li>
                        <li>Rol validado por PHP.</li>
                        <li>Menú alineado con el permiso.</li>
                        <li>Registro temporal de navegación.</li>
                    </ul>
                </article>
            </section>
        </section>
    </main>
</div>
<script src="assets/js/app.js"></script>
</body>
</html>
