<?php

require_once __DIR__ . '/bootstrap.php';

if (!function_exists('demo_layout_header_actions')) {
    function demo_layout_header_actions(): string
    {
        $user = demo_current_user();
        $notifications = demo_user_notifications();
        $unreadCount = demo_unread_notifications_count();

        ob_start(); ?>
        <div class="topbar-actions">
            <label class="search-inline">
                <span class="search-inline__icon">⌕</span>
                <input type="text" placeholder="Buscar…" class="input input--sm" />
            </label>

            <div class="notification-chip" title="Notificaciones">
                <button type="button" class="icon-btn" data-dropdown-toggle="notifications-panel" aria-label="Notificaciones">
                    🔔
                    <?php if ($unreadCount > 0): ?>
                        <span class="notification-chip__count"><?= demo_e((string)$unreadCount) ?></span>
                    <?php endif; ?>
                </button>

                <div id="notifications-panel" class="dropdown-panel" hidden>
                    <div class="dropdown-panel__header">
                        <strong>Notificaciones</strong>
                        <span><?= demo_e((string)count($notifications)) ?></span>
                    </div>

                    <div class="dropdown-panel__content">
                        <?php if (empty($notifications)): ?>
                            <p class="muted">No tienes notificaciones nuevas.</p>
                        <?php else: ?>
                            <?php foreach (array_slice($notifications, 0, 4) as $notification): ?>
                                <article class="notification-item">
                                    <span class="badge badge-<?= demo_e(demo_badge_class($notification['type'] ?? 'info')) ?>">
                                        <?= demo_e(ucfirst($notification['type'] ?? 'info')) ?>
                                    </span>
                                    <h4><?= demo_e($notification['title']) ?></h4>
                                    <p><?= demo_e($notification['message']) ?></p>
                                    <small><?= demo_e(demo_date($notification['created_at'], 'd/m/Y H:i')) ?></small>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($user): ?>
                <div class="user-summary">
                    <div class="avatar"><?= demo_e($user['avatar'] ?? demo_avatar_initials($user['full_name'] ?? '')) ?></div>
                    <div class="user-summary__meta">
                        <strong><?= demo_e($user['full_name'] ?? 'Usuario') ?></strong>
                        <span><?= demo_e(demo_role_label($user['role'] ?? null)) ?></span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('demo_render_sidebar')) {
    function demo_render_sidebar(bool $clientPortal = false): string
    {
        $user = demo_current_user();
        $menu = demo_menu_items($user['role'] ?? null);
        $logoutHref = demo_url('logout.php');

        ob_start(); ?>
        <aside class="sidebar" id="app-sidebar">
            <div class="sidebar__brand">
                <button type="button" class="icon-btn sidebar__collapse" data-sidebar-toggle aria-label="Colapsar menú">☰</button>
                <div class="sidebar__brand-meta">
                    <span class="sidebar__eyebrow"><?= $clientPortal ? 'Portal cliente' : 'Broker demo' ?></span>
                    <strong><?= $clientPortal ? 'Área del cliente' : 'BrokerSeguros' ?></strong>
                </div>
            </div>

            <?php if ($user): ?>
                <div class="sidebar__profile">
                    <div class="avatar avatar--lg"><?= demo_e($user['avatar'] ?? demo_avatar_initials($user['full_name'] ?? '')) ?></div>
                    <div>
                        <strong class="sidebar__profile-name"><?= demo_e($user['full_name']) ?></strong>
                        <p class="sidebar__profile-role"><?= demo_e(demo_role_label($user['role'])) ?></p>
                        <small class="sidebar__profile-doc">DNI/Doc: <?= demo_e($user['document'] ?? '—') ?></small>
                    </div>
                </div>
            <?php endif; ?>

            <nav class="sidebar__nav">
                <p class="sidebar__nav-title"><?= $clientPortal ? 'Mi menú' : 'Menú principal' ?></p>

                <?php foreach ($menu as $item): ?>
                    <a href="<?= demo_e(demo_url($item['href'])) ?>"
                       class="sidebar__link <?= demo_active_menu($item['href']) ? 'is-active' : '' ?>">
                        <span class="sidebar__link-icon"><?= $item['icon'] ?></span>
                        <span class="sidebar__link-label"><?= demo_e($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="sidebar__footer">
                <a href="<?= demo_e($logoutHref) ?>" class="btn btn-danger btn-block">Cerrar sesión</a>
            </div>
        </aside>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('demo_render_breadcrumb')) {
    function demo_render_breadcrumb(array $items = []): string
    {
        if (empty($items)) {
            return '';
        }

        ob_start(); ?>
        <nav class="breadcrumb" aria-label="Ruta">
            <?php foreach ($items as $index => $item): ?>
                <span class="breadcrumb__item"><?= demo_e($item) ?></span>
                <?php if ($index < count($items) - 1): ?>
                    <span class="breadcrumb__sep">/</span>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('demo_render_base_modals')) {
    function demo_render_base_modals(): string
    {
        ob_start(); ?>
        <div class="modal" id="confirm-modal" hidden>
            <div class="modal__backdrop" data-modal-close></div>
            <div class="modal__dialog modal__dialog--sm">
                <div class="modal__header">
                    <h3 id="confirm-modal-title">Confirmar acción</h3>
                    <button type="button" class="icon-btn" data-modal-close aria-label="Cerrar">✕</button>
                </div>
                <div class="modal__body">
                    <p id="confirm-modal-message">¿Deseas continuar?</p>
                </div>
                <div class="modal__footer">
                    <button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button>
                    <button type="button" class="btn btn-primary" id="confirm-modal-accept">Aceptar</button>
                </div>
            </div>
        </div>

        <div class="modal" id="generic-modal" hidden>
            <div class="modal__backdrop" data-modal-close></div>
            <div class="modal__dialog">
                <div class="modal__header">
                    <h3 id="generic-modal-title">Detalle</h3>
                    <button type="button" class="icon-btn" data-modal-close aria-label="Cerrar">✕</button>
                </div>
                <div class="modal__body" id="generic-modal-body"></div>
                <div class="modal__footer">
                    <button type="button" class="btn btn-ghost" data-modal-close>Cerrar</button>
                </div>
            </div>
        </div>

        <div id="toast-container" class="toast-container" aria-live="polite" aria-atomic="true"></div>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('demo_render_internal_layout')) {
    function demo_render_internal_layout(string $title, string $content, array $options = []): void
    {
        demo_require_login();

        $user = demo_current_user();
        $breadcrumb = $options['breadcrumb'] ?? ['Inicio', $title];
        $pageClass = $options['page_class'] ?? '';
        $serverToasts = demo_consume_toasts();
        ?>
        <!doctype html>
        <html lang="es">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?= demo_e($title) ?> | BrokerSeguros</title>
            <link rel="stylesheet" href="<?= demo_e(demo_url('assets/css/theme.css')) ?>">
        </head>
        <body class="app-body <?= demo_e($pageClass) ?>">
            <div class="app-shell">
                <?= demo_render_sidebar(false) ?>

                <div class="app-main">
                    <header class="topbar">
                        <div class="topbar__left">
                            <button type="button" class="icon-btn topbar__menu" data-sidebar-toggle aria-label="Abrir menú">☰</button>
                            <div>
                                <?= demo_render_breadcrumb($breadcrumb) ?>
                                <h1 class="page-title"><?= demo_e($title) ?></h1>
                                <?php if (!empty($options['subtitle'])): ?>
                                    <p class="page-subtitle"><?= demo_e($options['subtitle']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?= demo_layout_header_actions() ?>
                    </header>

                    <main class="page-content">
                        <?= $content ?>
                    </main>

                    <footer class="app-footer">
                        <span>Demo funcional BrokerSeguros</span>
                        <span><?= demo_e($user['full_name'] ?? '') ?> · <?= demo_e(demo_role_label($user['role'] ?? null)) ?></span>
                    </footer>
                </div>
            </div>

            <?= demo_render_base_modals() ?>

            <script>
                window.DEMO_CONFIG = {
                    baseUrl: <?= json_encode(demo_root_prefix(), JSON_UNESCAPED_UNICODE) ?>,
                    currentRole: <?= json_encode($user['role'] ?? null, JSON_UNESCAPED_UNICODE) ?>,
                    currentUser: <?= json_encode($user, JSON_UNESCAPED_UNICODE) ?>,
                    serverToasts: <?= json_encode($serverToasts, JSON_UNESCAPED_UNICODE) ?>
                };
            </script>
            <script src="<?= demo_e(demo_url('assets/js/app.js')) ?>"></script>
        </body>
        </html>
        <?php
    }
}

if (!function_exists('demo_render_client_layout')) {
    function demo_render_client_layout(string $title, string $content, array $options = []): void
    {
        demo_require_login();
        demo_require_roles(['cliente']);

        $user = demo_current_user();
        $breadcrumb = $options['breadcrumb'] ?? ['Portal', $title];
        $pageClass = $options['page_class'] ?? 'portal-body';
        $serverToasts = demo_consume_toasts();
        ?>
        <!doctype html>
        <html lang="es">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?= demo_e($title) ?> | Portal Cliente</title>
            <link rel="stylesheet" href="<?= demo_e(demo_url('assets/css/theme.css')) ?>">
        </head>
        <body class="app-body <?= demo_e($pageClass) ?>">
            <div class="app-shell app-shell--portal">
                <?= demo_render_sidebar(true) ?>

                <div class="app-main">
                    <header class="topbar topbar--portal">
                        <div class="topbar__left">
                            <button type="button" class="icon-btn topbar__menu" data-sidebar-toggle aria-label="Abrir menú">☰</button>
                            <div>
                                <?= demo_render_breadcrumb($breadcrumb) ?>
                                <h1 class="page-title"><?= demo_e($title) ?></h1>
                                <?php if (!empty($options['subtitle'])): ?>
                                    <p class="page-subtitle"><?= demo_e($options['subtitle']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?= demo_layout_header_actions() ?>
                    </header>

                    <main class="page-content">
                        <?= $content ?>
                    </main>

                    <footer class="app-footer">
                        <span>Portal del cliente</span>
                        <span><?= demo_e($user['full_name'] ?? '') ?></span>
                    </footer>
                </div>
            </div>

            <?= demo_render_base_modals() ?>

            <script>
                window.DEMO_CONFIG = {
                    baseUrl: <?= json_encode(demo_root_prefix(), JSON_UNESCAPED_UNICODE) ?>,
                    currentRole: <?= json_encode($user['role'] ?? null, JSON_UNESCAPED_UNICODE) ?>,
                    currentUser: <?= json_encode($user, JSON_UNESCAPED_UNICODE) ?>,
                    serverToasts: <?= json_encode($serverToasts, JSON_UNESCAPED_UNICODE) ?>
                };
            </script>
            <script src="<?= demo_e(demo_url('assets/js/app.js')) ?>"></script>
        </body>
        </html>
        <?php
    }
}