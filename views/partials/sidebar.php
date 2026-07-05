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
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-top">
        <a class="sidebar-brand" href="<?= e(moduleUrl('inicio')) ?>" aria-label="<?= e(APP_NAME) ?>, Inicio">
            <span class="brand-mini">B</span>
            <span><?= e(APP_SHORT_NAME) ?> <b>SEGUROS</b></span>
        </a>
        <button class="sidebar-close" id="sidebar-close" type="button" aria-label="Cerrar menú">×</button>
    </div>

    <div class="profile-panel">
        <div class="profile-avatar" aria-hidden="true"><?= e($sidebarInitials ?: 'U') ?></div>
        <div>
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
            >
                <span class="nav-icon" aria-hidden="true"><?= e((string) ($item['icon'] ?? '•')) ?></span>
                <span><?= e((string) ($item['label'] ?? 'Módulo')) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <a href="<?= e(appRelativeUrl('logout.php')) ?>" class="logout-link">Cerrar sesión <span>→</span></a>
        <small>Maqueta sin base de datos</small>
    </div>
</aside>
