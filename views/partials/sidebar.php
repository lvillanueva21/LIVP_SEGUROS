<?php

declare(strict_types=1);

/**
 * Variables esperadas:
 * - array $user
 * - array $menu
 * - string|null $activeModule
 */
$sidebarInitials = implode('', array_map(
    static fn (string $part): string => strtoupper(firstChar($part)),
    array_slice(preg_split('/\s+/', (string) ($user['name'] ?? '')) ?: [], 0, 2)
));
$isDatabaseUser = ($user['auth_source'] ?? '') === 'database';
?>
<link rel="stylesheet" href="<?= e(appRelativeUrl('assets/css/navigation-shell.css?v=NAVFIXV2')) ?>">
<aside class="sidebar" id="sidebar">
    <div class="sidebar-top">
        <a class="sidebar-brand" href="<?= e(moduleUrl('inicio')) ?>" aria-label="<?= e(APP_NAME) ?>, Inicio">
            <span class="brand-mini">B</span>
            <span class="sidebar-brand-label"><?= e(APP_SHORT_NAME) ?> <b>SEGUROS</b></span>
        </a>

        <div class="sidebar-top-actions">
            <button
                class="sidebar-collapse-toggle"
                id="sidebar-collapse-toggle"
                type="button"
                aria-label="Comprimir navegación lateral"
                aria-pressed="false"
                title="Comprimir navegación lateral"
            >
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <rect x="3.5" y="4" width="17" height="16" rx="2"></rect>
                    <path d="M9 4v16"></path>
                    <path d="M13 9l-2.5 3 2.5 3"></path>
                </svg>
            </button>
            <button class="sidebar-close" id="sidebar-close" type="button" aria-label="Cerrar menú">×</button>
        </div>
    </div>

    <div class="profile-panel">
        <div class="profile-avatar" aria-hidden="true"><?= e($sidebarInitials ?: 'U') ?></div>
        <div class="sidebar-profile-copy">
            <p class="profile-name"><?= e((string) ($user['name'] ?? 'Usuario')) ?></p>
            <p class="profile-role"><?= e((string) ($user['profile_title'] ?? 'Perfil')) ?></p>
        </div>
        <div class="profile-meta">
            <span class="role-badge role-<?= e((string) ($user['role'] ?? '')) ?>"><?= e((string) ($user['role_label'] ?? '')) ?></span>
            <span><?= e((string) ($user['document_type'] ?? '')) ?> <?= e((string) ($user['document'] ?? '')) ?></span>
        </div>
    </div>

    <nav class="main-nav" aria-label="Navegación principal">
        <?php foreach ($menu as $item): ?>
            <?php $itemId = (string) ($item['id'] ?? ''); ?>
            <a
                href="<?= e(moduleUrl($itemId)) ?>"
                class="nav-item <?= $itemId === $activeModule ? 'is-active' : '' ?>"
                data-module-id="<?= e($itemId) ?>"
                data-module-label="<?= e((string) ($item['label'] ?? 'Módulo')) ?>"
                title="<?= e((string) ($item['label'] ?? 'Módulo')) ?>"
            >
                <span class="nav-icon" aria-hidden="true"><?= e((string) ($item['icon'] ?? '•')) ?></span>
                <span class="nav-label"><?= e((string) ($item['label'] ?? 'Módulo')) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <form method="post" action="<?= e(appRelativeUrl('logout.php')) ?>" class="sidebar-logout-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken('logout_form')) ?>">
            <button type="submit" class="logout-link sidebar-logout-button">
                <span class="logout-label">Cerrar sesión</span>
                <span aria-hidden="true">→</span>
            </button>
        </form>
        <small><?= $isDatabaseUser ? 'Acceso real con MySQL' : 'Acceso demo / caché temporal' ?></small>
    </div>
</aside>
<script defer src="<?= e(appRelativeUrl('assets/js/navigation-shell.js?v=NAVFIXV2')) ?>"></script>
