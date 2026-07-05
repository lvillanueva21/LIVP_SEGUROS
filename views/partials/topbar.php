<?php
declare(strict_types=1);

/**
 * Variable esperada:
 * - string $pageTitle
 */
?>
<header class="topbar">
    <button id="menu-toggle" class="menu-toggle" type="button" aria-label="Abrir menú">☰</button>
    <div>
        <p class="topbar-system"><?= e(APP_NAME) ?></p>
        <h1 id="page-title"><?= e($pageTitle) ?></h1>
    </div>
    <div class="topbar-actions">
        <div class="topbar-session">
            <span class="live-dot" aria-hidden="true"></span>
            <span>Sesión activa</span>
        </div>
        <a href="<?= e(appRelativeUrl('logout.php')) ?>" class="topbar-logout" aria-label="Cerrar sesión" title="Cerrar sesión">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M14 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"></path>
                <path d="M10 17l5-5-5-5"></path>
                <path d="M15 12H3"></path>
            </svg>
            <span class="topbar-logout-label">Cerrar sesión</span>
        </a>
    </div>
</header>
