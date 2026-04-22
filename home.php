<?php
require_once __DIR__ . '/includes/bootstrap.php';

demo_require_login();

$role = demo_current_role() ?? 'cliente';
$target = demo_default_route($role);
$targetAbsolute = demo_project_root() . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $target);

if (is_file($targetAbsolute)) {
    demo_redirect($target);
}

$user = demo_current_user();
$serverToasts = demo_consume_toasts();
$title = 'Módulo en construcción';
$subtitle = 'La base de autenticación ya está operativa. El siguiente módulo todavía no existe en el servidor.';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= demo_e($title) ?> | BrokerSeguros</title>
    <link rel="stylesheet" href="<?= demo_e(demo_url('assets/css/theme.css')) ?>">
    <style>
        .fallback-shell {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 1.25rem;
        }

        .fallback-card {
            width: min(880px, 100%);
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            border: 1px solid rgba(219, 227, 239, 0.85);
            border-radius: 28px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }

        .fallback-header {
            padding: 1.4rem 1.4rem 1rem;
            background:
                radial-gradient(circle at top right, rgba(79, 70, 229, 0.18), transparent 28%),
                radial-gradient(circle at bottom left, rgba(14, 165, 164, 0.16), transparent 28%),
                linear-gradient(135deg, #ffffff 0%, #f7fbff 100%);
            border-bottom: 1px solid rgba(219, 227, 239, 0.8);
        }

        .fallback-body {
            padding: 1.4rem;
        }

        .fallback-grid {
            display: grid;
            grid-template-columns: 1.2fr .8fr;
            gap: 1rem;
        }

        .fallback-user {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .fallback-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
            margin-top: 1.25rem;
        }

        .fallback-code {
            background: #0f172a;
            color: #e8eefc;
            border-radius: 18px;
            padding: 1rem;
            font-size: .95rem;
            overflow: auto;
        }

        @media (max-width: 820px) {
            .fallback-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="fallback-shell">
        <section class="fallback-card">
            <header class="fallback-header">
                <div class="fallback-user">
                    <div class="avatar avatar--lg"><?= demo_e($user['avatar'] ?? demo_avatar_initials($user['full_name'] ?? '')) ?></div>
                    <div>
                        <span class="badge badge-info"><?= demo_e(demo_role_label($role)) ?></span>
                        <h1 class="page-title"><?= demo_e($title) ?></h1>
                        <p class="page-subtitle"><?= demo_e($subtitle) ?></p>
                    </div>
                </div>
            </header>

            <div class="fallback-body">
                <div class="fallback-grid">
                    <article class="card">
                        <div class="card__header">
                            <div>
                                <h2 class="card__title">Sesión iniciada correctamente</h2>
                                <p class="card__subtitle">El usuario actual ya fue autenticado y redirigido según su rol.</p>
                            </div>
                        </div>

                        <div class="grid grid--2">
                            <div class="panel">
                                <strong>Usuario</strong>
                                <p class="muted mt-1"><?= demo_e($user['full_name'] ?? '—') ?></p>
                            </div>
                            <div class="panel">
                                <strong>Rol</strong>
                                <p class="muted mt-1"><?= demo_e(demo_role_label($role)) ?></p>
                            </div>
                            <div class="panel">
                                <strong>Ruta esperada</strong>
                                <p class="muted mt-1"><?= demo_e($target) ?></p>
                            </div>
                            <div class="panel">
                                <strong>Estado</strong>
                                <p class="mt-1"><?= demo_badge('activo', 'activo') ?></p>
                            </div>
                        </div>

                        <div class="fallback-actions">
                            <a href="<?= demo_e(demo_url('index.php')) ?>" class="btn btn-ghost">Volver al login</a>
                            <a href="<?= demo_e(demo_url('logout.php')) ?>" class="btn btn-danger">Cerrar sesión</a>
                        </div>
                    </article>

                    <aside class="card">
                        <div class="card__header">
                            <div>
                                <h2 class="card__title">Siguiente paso</h2>
                                <p class="card__subtitle">Sube el módulo que corresponde al rol para que la redirección funcione sola.</p>
                            </div>
                        </div>

                        <div class="fallback-code"><?= demo_e($target) ?></div>

                        <p class="muted mt-2">
                            En cuanto exista ese archivo, <code>/home.php</code> redirigirá automáticamente sin mostrar esta pantalla.
                        </p>
                    </aside>
                </div>
            </div>
        </section>
    </div>

    <div id="toast-container" class="toast-container" aria-live="polite" aria-atomic="true"></div>

    <script>
        window.DEMO_CONFIG = {
            baseUrl: <?= json_encode(demo_root_prefix(), JSON_UNESCAPED_UNICODE) ?>,
            currentRole: <?= json_encode($role, JSON_UNESCAPED_UNICODE) ?>,
            currentUser: <?= json_encode($user, JSON_UNESCAPED_UNICODE) ?>,
            serverToasts: <?= json_encode($serverToasts, JSON_UNESCAPED_UNICODE) ?>
        };
    </script>
    <script src="<?= demo_e(demo_url('assets/js/app.js')) ?>"></script>
</body>
</html>