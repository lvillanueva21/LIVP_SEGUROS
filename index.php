<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (demo_is_logged_in()) {
    demo_redirect('home.php');
}

$serverToasts = demo_consume_toasts();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión | BrokerSeguros</title>
    <link rel="stylesheet" href="<?= demo_e(demo_url('assets/css/theme.css')) ?>">
    <style>
        :root {
            --auth-bg-1: #eef4fb;
            --auth-bg-2: #f7fbff;
            --auth-line: rgba(219, 227, 239, 0.92);
            --auth-soft: #5f6f86;
            --auth-strong: #0f172a;
            --auth-surface: rgba(255, 255, 255, 0.97);
            --auth-surface-2: rgba(248, 251, 255, 0.98);
        }

        body {
            min-height: 100vh;
            margin: 0;
            background:
                radial-gradient(circle at top left, rgba(79, 70, 229, 0.10), transparent 22%),
                radial-gradient(circle at bottom right, rgba(14, 165, 164, 0.10), transparent 22%),
                linear-gradient(180deg, var(--auth-bg-1) 0%, var(--auth-bg-2) 100%);
        }

        .auth-shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: minmax(0, 1.45fr) minmax(380px, 460px);
            gap: 1rem;
            padding: 1rem;
            align-items: stretch;
        }

        .hero-panel,
        .login-panel {
            min-width: 0;
            min-height: calc(100vh - 2rem);
            background: var(--auth-surface);
            border: 1px solid var(--auth-line);
            border-radius: 28px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }

        .hero-panel {
            padding: 1.35rem;
            display: grid;
            grid-template-rows: auto 1fr auto;
            gap: 1rem;
            background:
                radial-gradient(circle at top right, rgba(79, 70, 229, 0.12), transparent 26%),
                radial-gradient(circle at bottom left, rgba(14, 165, 164, 0.10), transparent 26%),
                linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        }

        .hero-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .hero-brand {
            display: flex;
            align-items: center;
            gap: .8rem;
        }

        .hero-brand__logo {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            display: grid;
            place-items: center;
            color: #fff;
            font-weight: 900;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            box-shadow: var(--shadow-sm);
            flex-shrink: 0;
        }

        .hero-brand__eyebrow {
            margin: 0 0 .15rem;
            color: var(--auth-soft);
            font-size: .82rem;
            letter-spacing: .03em;
            text-transform: uppercase;
            font-weight: 700;
        }

        .hero-brand__title {
            margin: 0;
            color: var(--auth-strong);
            font-size: 1.08rem;
            line-height: 1.2;
        }

        .hero-chip {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .62rem .92rem;
            border-radius: 999px;
            border: 1px solid rgba(79, 70, 229, 0.12);
            background: rgba(79, 70, 229, 0.06);
            color: var(--primary);
            font-weight: 700;
            font-size: .9rem;
        }

        .hero-main {
            display: grid;
            grid-template-columns: minmax(0, 1.02fr) minmax(320px, .98fr);
            gap: 1rem;
            min-height: 0;
            align-items: stretch;
        }

        .hero-copy {
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-width: 0;
        }

        .hero-copy h1 {
            margin: 0 0 .75rem;
            font-size: clamp(2rem, 4vw, 3.4rem);
            line-height: 1.02;
            letter-spacing: -.04em;
            color: var(--auth-strong);
            max-width: 10ch;
        }

        .hero-copy p {
            margin: 0;
            color: var(--auth-soft);
            line-height: 1.68;
            font-size: .98rem;
            max-width: 52ch;
        }

        .hero-feature-grid {
            margin-top: 1rem;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .8rem;
        }

        .hero-feature {
            padding: .95rem 1rem;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.82);
            border: 1px solid var(--auth-line);
            box-shadow: var(--shadow-sm);
        }

        .hero-feature strong {
            display: block;
            color: var(--auth-strong);
            font-size: .95rem;
            line-height: 1.3;
        }

        .hero-feature span {
            display: block;
            margin-top: .22rem;
            color: var(--auth-soft);
            font-size: .88rem;
            line-height: 1.45;
        }

        .hero-media {
            position: relative;
            min-height: 100%;
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid var(--auth-line);
            box-shadow: var(--shadow-sm);
            background: #eaf1f8;
        }

        .hero-media img {
            width: 100%;
            height: 100%;
            min-height: 360px;
            object-fit: cover;
            object-position: center;
            display: block;
        }

        .hero-media__card {
            position: absolute;
            left: 1rem;
            right: 1rem;
            bottom: 1rem;
            padding: .95rem 1rem;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.65);
            box-shadow: var(--shadow-sm);
        }

        .hero-media__card strong {
            display: block;
            margin-bottom: .2rem;
            font-size: .96rem;
            color: var(--auth-strong);
        }

        .hero-media__card p {
            margin: 0;
            color: var(--auth-soft);
            font-size: .88rem;
            line-height: 1.45;
        }

        .hero-bottom {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .85rem;
        }

        .hero-note {
            padding: .95rem 1rem;
            border-radius: 20px;
            border: 1px solid var(--auth-line);
            background: var(--auth-surface-2);
            box-shadow: var(--shadow-sm);
        }

        .hero-note__label {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            font-size: .8rem;
            font-weight: 800;
            letter-spacing: .03em;
            text-transform: uppercase;
            color: var(--primary);
            margin-bottom: .35rem;
        }

        .hero-note h2 {
            margin: 0 0 .25rem;
            font-size: .98rem;
            line-height: 1.3;
            color: var(--auth-strong);
        }

        .hero-note p {
            margin: 0;
            color: var(--auth-soft);
            font-size: .88rem;
            line-height: 1.45;
        }

        .login-panel {
            padding: 1.45rem;
            display: grid;
            grid-template-rows: auto auto 1fr auto;
            gap: 1rem;
            background: linear-gradient(180deg, rgba(255,255,255,0.98) 0%, rgba(249,252,255,0.98) 100%);
        }

        .login-header {
            display: flex;
            align-items: center;
            gap: .9rem;
        }

        .login-header__logo {
            width: 58px;
            height: 58px;
            border-radius: 18px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #fff;
            display: grid;
            place-items: center;
            font-weight: 900;
            font-size: 1.05rem;
            box-shadow: var(--shadow-sm);
            flex-shrink: 0;
        }

        .login-header__title {
            margin: 0;
            font-size: 1.45rem;
            line-height: 1.1;
            color: var(--auth-strong);
        }

        .login-header__text {
            margin: .3rem 0 0;
            color: var(--auth-soft);
            line-height: 1.45;
            font-size: .92rem;
        }

        .login-card {
            padding: 1rem;
            border-radius: 22px;
            border: 1px solid var(--auth-line);
            background: rgba(255, 255, 255, 0.88);
        }

        .login-card__title {
            margin: 0 0 .2rem;
            font-size: 1rem;
            color: var(--auth-strong);
        }

        .login-card__subtitle {
            margin: 0;
            color: var(--auth-soft);
            font-size: .9rem;
            line-height: 1.45;
        }

        .auth-form {
            display: grid;
            gap: .95rem;
            margin-top: 1rem;
        }

        .auth-form__actions {
            display: grid;
            gap: .7rem;
        }

        .auth-help {
            color: var(--auth-soft);
            font-size: .88rem;
            line-height: 1.45;
        }

        .auth-submit {
            width: 100%;
            justify-content: center;
        }

        .auth-error {
            border: 1px solid rgba(239, 68, 68, 0.16);
            background: linear-gradient(180deg, #fff 0%, #fff7f7 100%);
            color: #b91c1c;
            padding: .9rem 1rem;
            border-radius: 16px;
            display: none;
        }

        .auth-error.is-visible {
            display: block;
        }

        .credentials-card {
            padding: 1rem;
            border-radius: 22px;
            border: 1px solid rgba(100, 116, 139, 0.18);
            background: linear-gradient(180deg, #fbfdff 0%, #f6faff 100%);
        }

        .credentials-card h3 {
            margin: 0 0 .15rem;
            font-size: 1rem;
            color: var(--auth-strong);
        }

        .credentials-card p {
            margin: 0 0 .75rem;
            color: var(--auth-soft);
            font-size: .88rem;
            line-height: 1.4;
        }

        .credentials-list {
            display: grid;
            gap: .65rem;
        }

        .credentials-item {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: center;
            gap: .75rem;
            padding: .8rem .9rem;
            border-radius: 16px;
            background: #fff;
            border: 1px solid var(--auth-line);
        }

        .credentials-item strong {
            display: block;
            font-size: .94rem;
            color: var(--auth-strong);
            line-height: 1.25;
        }

        .credentials-item small {
            display: block;
            margin-top: .12rem;
            color: var(--auth-soft);
            font-size: .82rem;
            line-height: 1.35;
        }

        .credentials-item code {
            background: #f2f6fb;
            border-radius: 10px;
            padding: .32rem .55rem;
            white-space: nowrap;
            color: #0f172a;
            font-size: .84rem;
            border: 1px solid rgba(219, 227, 239, 0.95);
        }

        .login-footer {
            text-align: center;
            color: var(--auth-soft);
            font-size: .86rem;
            line-height: 1.4;
        }

        @media (max-width: 1200px) {
            .hero-main {
                grid-template-columns: 1fr;
            }

            .hero-copy h1 {
                max-width: none;
            }

            .hero-media img {
                min-height: 280px;
            }
        }

        @media (max-width: 1100px) {
            .auth-shell {
                grid-template-columns: 1fr;
            }

            .login-panel {
                order: 1;
                min-height: auto;
            }

            .hero-panel {
                order: 2;
                min-height: auto;
            }
        }

        @media (max-width: 720px) {
            .hero-feature-grid,
            .hero-bottom {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .auth-shell {
                padding: .85rem;
                gap: .85rem;
            }

            .hero-panel,
            .login-panel {
                min-height: auto;
                border-radius: 22px;
            }

            .hero-panel {
                padding: 1rem;
            }

            .login-panel {
                padding: 1rem;
            }

            .hero-top {
                align-items: flex-start;
            }

            .hero-media img {
                min-height: 220px;
            }

            .credentials-item {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="auth-shell">
        <section class="hero-panel">
            <div class="hero-top">
                <div class="hero-brand">
                    <div class="hero-brand__logo">SGS</div>
                    <div>
                        <p class="hero-brand__eyebrow">Demo sistema broker</p>
                        <h2 class="hero-brand__title">Sistema de Gestión de Seguros</h2>
                    </div>
                </div>

                <div class="hero-chip">Operación clara y profesional</div>
            </div>

            <div class="hero-main">
                <div class="hero-copy">
                    <h1>Sistema de Gestión de Seguros (SGS)</h1>

                    <p>
                        Plataforma demo para administrar clientes, pólizas, pagos y siniestros en un entorno moderno, ordenado y confiable.
                    </p>

                    <div class="hero-feature-grid">
                        <div class="hero-feature">
                            <strong>Pólizas</strong>
                            <span>Control y seguimiento</span>
                        </div>

                        <div class="hero-feature">
                            <strong>Cobranzas</strong>
                            <span>Pagos y vencimientos</span>
                        </div>

                        <div class="hero-feature">
                            <strong>Siniestros</strong>
                            <span>Registro y atención</span>
                        </div>

                        <div class="hero-feature">
                            <strong>Portal cliente</strong>
                            <span>Consulta y autoservicio</span>
                        </div>
                    </div>
                </div>

                <div class="hero-media">
                    <img
                        src="<?= demo_e(demo_url('assets/img/familia.webp')) ?>"
                        alt="Familia protegida"
                    >

                    <div class="hero-media__card">
                        <strong>Protección y confianza</strong>
                        <p>Un sistema sólido mejora la experiencia del cliente y el control del broker.</p>
                    </div>
                </div>
            </div>

            <div class="hero-bottom">
                <article class="hero-note">
                    <div class="hero-note__label">Seguros</div>
                    <h2>Respaldo ante imprevistos</h2>
                    <p>Orden, seguimiento y atención profesional en un solo lugar.</p>
                </article>

                <article class="hero-note">
                    <div class="hero-note__label">Perú</div>
                    <h2>Gestión cada vez más digital</h2>
                    <p>Procesos más ágiles y mejor acompañamiento al cliente.</p>
                </article>
            </div>
        </section>

        <section class="login-panel">
            <div class="login-header">
                <div class="login-header__logo">BS</div>
                <div>
                    <h2 class="login-header__title">BrokerSeguros</h2>
                    <p class="login-header__text">Accede con uno de los usuarios demo.</p>
                </div>
            </div>

            <div class="login-card">
                <h3 class="login-card__title">Ingreso al sistema</h3>
                <p class="login-card__subtitle">Usa tus credenciales para continuar.</p>

                <form id="login-form" class="auth-form" action="<?= demo_e(demo_url('ajax/auth.php')) ?>" method="post" data-ajax-form>
                    <input type="hidden" name="action" value="login">

                    <div>
                        <label class="form-label" for="username">Usuario</label>
                        <input class="input" id="username" name="username" type="text" inputmode="numeric" autocomplete="username" placeholder="Ingresa tu DNI demo" required>
                    </div>

                    <div>
                        <label class="form-label" for="password">Contraseña</label>
                        <input class="input" id="password" name="password" type="password" autocomplete="current-password" placeholder="Ingresa tu contraseña" required>
                    </div>

                    <div id="login-error" class="auth-error" role="alert" aria-live="assertive"></div>

                    <div class="auth-form__actions">
                        <span class="auth-help">La contraseña inicial es igual al usuario demo.</span>
                        <button type="submit" class="btn btn-primary auth-submit">Ingresar</button>
                    </div>
                </form>
            </div>

            <div class="credentials-card">
                <h3>Credenciales demo</h3>
                <p>Perfiles disponibles para probar el sistema.</p>

                <div class="credentials-list">
                    <div class="credentials-item">
                        <div>
                            <strong>Gerente</strong>
                            <small>Gestión general</small>
                        </div>
                        <code>45871234 / 45871234</code>
                    </div>

                    <div class="credentials-item">
                        <div>
                            <strong>Ejecutivo</strong>
                            <small>Cartera y operación</small>
                        </div>
                        <code>48652137 / 48652137</code>
                    </div>

                    <div class="credentials-item">
                        <div>
                            <strong>Cliente</strong>
                            <small>Portal de consulta</small>
                        </div>
                        <code>70123456 / 70123456</code>
                    </div>
                </div>
            </div>

            <div class="login-footer">
                Demo funcional · Persistencia temporal por sesión
            </div>
        </section>
    </div>

    <div id="toast-container" class="toast-container" aria-live="polite" aria-atomic="true"></div>

    <script>
        window.DEMO_CONFIG = {
            baseUrl: <?= json_encode(demo_root_prefix(), JSON_UNESCAPED_UNICODE) ?>,
            currentRole: null,
            currentUser: null,
            serverToasts: <?= json_encode($serverToasts, JSON_UNESCAPED_UNICODE) ?>
        };
    </script>
    <script src="<?= demo_e(demo_url('assets/js/app.js?v=20260422_03')) ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('login-form');
            const errorBox = document.getElementById('login-error');

            const setError = (message = '') => {
                errorBox.textContent = message;
                errorBox.classList.toggle('is-visible', Boolean(message));
            };

            form.addEventListener('ajax:error', (event) => {
                setError(event.detail?.message || 'No se pudo iniciar sesión.');
            });

            form.addEventListener('ajax:success', () => {
                setError('');
            });
        });
    </script>
</body>
</html>