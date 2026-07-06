<?php

declare(strict_types=1);

/**
 * Render común para páginas temporales del rol Desarrollo.
 * Mantiene el mismo shell de Broker sin duplicar el control de permisos.
 */
function renderDevelopmentPage(string $moduleId, string $intro, array $links = []): void
{
    $context = requireModuleAccess($moduleId);
    $user = $context['user'];
    $module = $context['module'];
    $menu = modulesForRole((string) $user['role']);
    $activeModule = $moduleId;
    $pageTitle = (string) ($module['label'] ?? 'Desarrollo');
    ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(APP_NAME) ?> | <?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="assets/css/app.css?v=BS-20260705-NAVDESARROLLOV11">
    <link rel="stylesheet" href="assets/css/modules.css?v=BS-20260705-NAVDESARROLLOV11">
    <link rel="stylesheet" href="assets/css/development.css?v=BS-20260705-NAVDESARROLLOV11">
</head>
<body class="app-body" data-role="<?= e((string) $user['role']) ?>" data-user="<?= e((string) $user['id']) ?>">
<div class="app-shell">
    <?php require __DIR__ . '/../views/partials/sidebar.php'; ?>
    <div class="sidebar-overlay" id="sidebar-overlay"></div>
    <main class="workspace">
        <?php require __DIR__ . '/../views/partials/topbar.php'; ?>
        <section class="workspace-content">
            <article class="module-hero development-hero">
                <div class="module-hero-icon" aria-hidden="true"><?= e((string) ($module['icon'] ?? '⚙')) ?></div>
                <div>
                    <p class="eyebrow"><?= e((string) ($module['scope'] ?? 'DESARROLLO')) ?></p>
                    <h2><?= e($pageTitle) ?></h2>
                    <p><?= e($intro) ?></p>
                </div>
                <span class="module-access-badge">Acceso Desarrollo</span>
            </article>

            <?php if ($links !== []): ?>
                <section class="development-submenu-grid" aria-label="Submenús de <?= e($pageTitle) ?>">
                    <?php foreach ($links as $link): ?>
                        <?php
                        $targetId = (string) ($link['module'] ?? '');
                        $target = moduleForId($targetId);
                        if ($target === null || !canAccessModule((string) $user['role'], $targetId)) {
                            continue;
                        }
                        ?>
                        <a class="development-submenu-card" href="<?= e(moduleUrl($targetId)) ?>">
                            <span class="development-submenu-icon" aria-hidden="true"><?= e((string) ($target['icon'] ?? '•')) ?></span>
                            <span><strong><?= e((string) ($target['label'] ?? 'Configuración')) ?></strong><small><?= e((string) ($link['description'] ?? $target['description'] ?? 'Módulo en preparación.')) ?></small></span>
                            <span class="development-submenu-arrow" aria-hidden="true">→</span>
                        </a>
                    <?php endforeach; ?>
                </section>
            <?php else: ?>
                <section class="development-empty-card">
                    <span class="development-empty-mark" aria-hidden="true">⌘</span>
                    <p class="eyebrow">MÓDULO EN PREPARACIÓN</p>
                    <h2>Base creada y acceso validado</h2>
                    <p>Esta página ya está protegida para el rol Desarrollo. La funcionalidad real se implementará en una siguiente fase sin cambiar la ruta ni el menú.</p>
                </section>
            <?php endif; ?>
        </section>
    </main>
</div>
<script src="assets/js/app.js?v=BS-20260705-NAVDESARROLLOV11"></script>
</body>
</html>
    <?php
}
