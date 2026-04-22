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
        .auth-shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(360px, 430px);
            gap: 1.25rem;
            padding: 1.25rem;
            align-items: stretch;
        }

        .auth-hero {
            background:
                radial-gradient(circle at top right, rgba(79, 70, 229, 0.16), transparent 28%),
                radial-gradient(circle at bottom left, rgba(14, 165, 164, 0.12), transparent 26%),
                linear-gradient(135deg, #ffffff 0%, #f7fbff 100%);
            border: 1px solid rgba(219, 227, 239, 0.88);
            border-radius: 28px;
            box-shadow: var(--shadow-md);
            padding: 1.25rem;
            display: grid;
            grid-template-rows: auto 1fr;
            gap: 1.15rem;
            overflow: hidden;
            min-width: 0;
        }

        .auth-hero__media {
            position: relative;
            min-height: 320px;
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid rgba(219, 227, 239, 0.88);
            background: #eef4fb;
            box-shadow: var(--shadow-sm);
        }

        .auth-hero__media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            display: block;
        }

        .auth-hero__overlay {
            position: absolute;
            inset: auto 1.1rem 1.1rem 1.1rem;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            pointer-events: none;
        }

        .auth-hero__badge {
            display: inline-flex;
            align-items: center;
            gap: .55rem;
            padding: .65rem 1rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.88);
            color: var(--primary);
            font-weight: 700;
            box-shadow: var(--shadow-sm);
            backdrop-filter: blur(8px);
            max-width: 100%;
        }

        .auth-hero__body {
            display: grid;
            grid-template-columns: minmax(0, 1.15fr) minmax(280px, .85fr);
            gap: 1.15rem;
            align-items: start;
            min-width: 0;
        }

        .auth-hero__content {
            min-width: 0;
        }

        .auth-hero__title {
            margin: 0 0 .85rem;
            font-size: clamp(2rem, 4vw, 3.4rem);
            line-height: 1.02;
            letter-spacing: -.035em;
            max-width: 12ch;
        }

        .auth-hero__text {
            margin: 0;
            color: var(--text-soft);
            font-size: 1rem;
            line-height: 1.72;
            max-width: 62ch;
        }

        .auth-hero__news {
            padding: 1.1rem 1.1rem;
            border-radius: 22px;
            background: rgba(255, 255, 255, 0.86);
            border: 1px solid rgba(219, 227, 239, 0.86);
            box-shadow: var(--shadow-sm);
        }

        .auth-hero__news strong {
            display: block;
            margin-bottom: .45rem;
            font-size: 1rem;
            line-height: 1.3;
        }

        .auth-hero__news p {
            margin: 0;
            color: var(--text-soft);
            font-size: .95rem;
            line-height: 1.62;
        }

        .auth-card {
            background: rgba(255, 255, 255, 0.97);
            border: 1px solid rgba(219, 227, 239, 0.88);
            border-radius: 28px;
            box-shadow: var(--shadow-md);
            padding: 1.45rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-width: 0;
        }

        .auth-card__brand {
            display: flex;
            align-items: center;
            gap: .95rem;
            margin-bottom: 1.2rem;
        }

        .auth-card__logo {
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

        .auth-card__title {
            margin: 0;
            font-size: 1.45rem;
            line-height: 1.1;
        }

        .auth-card__subtitle {
            margin: .35rem 0 0;
            color: var(--text-soft);
        }

        .auth-form {
            display: grid;
            gap: 1rem;
            margin-top: .9rem;
        }

        .auth-form__actions {
            display: flex;
            gap: .75rem;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .auth-help {
            color: var(--text-soft);
            font-size: .92rem;
        }

        .auth-error {
            border: 1px solid rgba(239, 68, 68, 0.18);
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
            margin-top: 1.15rem;
            background: linear-gradient(180deg, #fbfdff 0%, #f7faff 100%);
            border: 1px dashed rgba(100, 116, 139, 0.28);
            border-radius: 18px;
            padding: 1rem;
        }

        .credentials-card h3 {
            margin: 0 0 .8rem;
            font-size: 1rem;
        }

        .credentials-list {
            display: grid;
            gap: .7rem;
        }

        .credentials-item {
            display: flex;
            align-items: start;
            justify-content: space-between;
            gap: .75rem;
            padding: .75rem .85rem;
            border-radius: 14px;
            background: #fff;
            border: 1px solid rgba(219, 227, 239, 0.85);
        }

        .credentials-item__role {
            font-weight: 700;
        }

        .credentials-item code {
            background: #f2f6fb;
            border-radius: 8px;
            padding: .2rem .45rem;
            white-space: nowrap;
        }

        .auth-footer-note {
            margin-top: 1rem;
            color: var(--text-soft);
            font-size: .9rem;
            text-align: center;
        }

        @media (max-width: 1180px) {
            .auth-hero__body {
                grid-template-columns: 1fr;
            }

            .auth-hero__title {
                max-width: none;
            }
        }

        @media (max-width: 980px) {
            .auth-shell {
                grid-template-columns: 1fr;
            }

            .auth-card {
                order: 1;
            }

            .auth-hero {
                order: 2;
            }
        }

        @media (max-width: 640px) {
            .auth-shell {
                padding: 1rem;
            }

            .auth-hero,
            .auth-card {
                padding: 1rem;
                border-radius: 22px;
            }

            .auth-hero__media {
                min-height: 220px;
            }

            .auth-hero__overlay {
                inset: auto .75rem .75rem .75rem;
            }

            .auth-hero__title {
                font-size: clamp(1.85rem, 9vw, 2.6rem);
            }

            .credentials-item {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="auth-shell">
        <section class="auth-hero">
            <div class="auth-hero__media">
                <img
                    src="<?= demo_e(demo_url('assets/img/familia.webp')) ?>"
                    alt="Familia protegida"
                >
                <div class="auth-hero__overlay">
                    <span class="auth-hero__badge">● Demo funcional para broker de seguros</span>
                </div>
            </div>

            <div class="auth-hero__body">
                <div class="auth-hero__content">
                    <h1 class="auth-hero__title">Sistema de Gestión de Seguros (SGS)</h1>

                    <p class="auth-hero__text">
                        Los seguros brindan estabilidad, respaldo y tranquilidad frente a imprevistos que pueden afectar a una familia o a una empresa. Un sistema de gestión bien organizado permite controlar pólizas, pagos y seguimientos con mayor claridad, ofreciendo una atención oportuna, cercana y confiable en cada etapa del servicio.
                    </p>
                </div>

                <div class="auth-hero__news">
                    <strong>Panorama asegurador en Perú</strong>
                    <p>
                        El sector asegurador peruano viene reforzando una atención más cercana, digital y preventiva, con mayor seguimiento de pólizas, pagos y acompañamiento al cliente. Esta portada busca transmitir esa idea de confianza, orden y protección que hoy esperan las familias y empresas.
                    </p>
                </div>
            </div>
        </section>

        <section class="auth-card">
            <div class="auth-card__brand">
                <div class="auth-card__logo">BS</div>
                <div>
                    <h2 class="auth-card__title">BrokerSeguros</h2>
                    <p class="auth-card__subtitle">Inicia sesión con uno de los usuarios demo</p>
                </div>
            </div>

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
                    <span class="auth-help">La clave inicial es igual al usuario demo.</span>
                    <button type="submit" class="btn btn-primary">Ingresar al sistema</button>
                </div>
            </form>

            <div class="credentials-card">
                <h3>Credenciales demo</h3>
                <div class="credentials-list">
                    <div class="credentials-item">
                        <div>
                            <div class="credentials-item__role">Gerente</div>
                            <small class="muted">Acceso completo de gestión</small>
                        </div>
                        <div><code>45871234 / 45871234</code></div>
                    </div>

                    <div class="credentials-item">
                        <div>
                            <div class="credentials-item__role">Ejecutivo</div>
                            <small class="muted">Acceso a su cartera y operación</small>
                        </div>
                        <div><code>48652137 / 48652137</code></div>
                    </div>

                    <div class="credentials-item">
                        <div>
                            <div class="credentials-item__role">Cliente</div>
                            <small class="muted">Acceso al portal de autoservicio</small>
                        </div>
                        <div><code>70123456 / 70123456</code></div>
                    </div>
                </div>
            </div>

            <p class="auth-footer-note">
                Demo sin base de datos real · Persistencia temporal por sesión
            </p>
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
    <script src="<?= demo_e(demo_url('assets/js/app.js')) ?>"></script>
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

            form.addEventListener('ajax:success', (event) => {
                setError('');
                const detail = event.detail || {};
                DemoApp.toast({
                    title: detail.title || 'Acceso concedido',
                    message: detail.message || 'Redirigiendo al sistema…',
                    type: 'success',
                    timeout: 1200
                });

                setTimeout(() => {
                    window.location.href = detail.redirect || 'home.php';
                }, 850);
            });
        });
    </script>
</body>
</html>