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
            grid-template-columns: 6fr 4fr;
            gap: 1.2rem;
            padding: 1.2rem;
            background: #eef4fb;
        }

        .hero-panel,
        .login-panel {
            background: rgba(255, 255, 255, 0.97);
            border: 1px solid rgba(219, 227, 239, 0.88);
            border-radius: 26px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
            min-width: 0;
        }

        .hero-panel {
            display: grid;
            grid-template-rows: auto 1fr auto;
            min-height: calc(100vh - 2.4rem);
            background:
                radial-gradient(circle at top right, rgba(79, 70, 229, 0.10), transparent 26%),
                radial-gradient(circle at bottom left, rgba(14, 165, 164, 0.08), transparent 26%),
                linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        }

        .hero-panel__top {
            padding: 1.1rem 1.4rem;
            border-bottom: 1px solid rgba(219, 227, 239, 0.85);
            text-align: center;
            background: rgba(255,255,255,.72);
        }

        .hero-panel__top h1 {
            margin: 0;
            font-size: clamp(1.8rem, 3vw, 2.8rem);
            line-height: 1.08;
            letter-spacing: -.03em;
        }

        .hero-panel__media {
            position: relative;
            min-height: 0;
            overflow: hidden;
            background: #eaf1f9;
        }

        .hero-panel__media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            display: block;
        }

        .hero-panel__bottom {
            display: grid;
            grid-template-columns: 1fr 1.35fr;
            border-top: 1px solid rgba(219, 227, 239, 0.85);
            min-height: 160px;
        }

        .hero-box {
            padding: 1.15rem 1.25rem;
            min-width: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .hero-box + .hero-box {
            border-left: 1px solid rgba(219, 227, 239, 0.85);
        }

        .hero-box h2 {
            margin: 0 0 .45rem;
            font-size: 1.02rem;
            line-height: 1.25;
        }

        .hero-box p {
            margin: 0;
            color: var(--text-soft);
            line-height: 1.62;
            font-size: .95rem;
        }

        .login-panel {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 1.45rem;
            min-height: calc(100vh - 2.4rem);
        }

        .login-panel__brand {
            display: flex;
            align-items: center;
            gap: .95rem;
            margin-bottom: 1.4rem;
        }

        .login-panel__logo {
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

        .login-panel__brand h2 {
            margin: 0;
            font-size: 1.45rem;
            line-height: 1.1;
        }

        .login-panel__brand p {
            margin: .35rem 0 0;
            color: var(--text-soft);
        }

        .auth-form {
            display: grid;
            gap: 1rem;
            margin-top: .4rem;
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
            .auth-shell {
                grid-template-columns: 1fr;
            }

            .login-panel {
                min-height: auto;
                order: 1;
            }

            .hero-panel {
                min-height: auto;
                order: 2;
            }
        }

        @media (max-width: 760px) {
            .hero-panel__bottom {
                grid-template-columns: 1fr;
            }

            .hero-box + .hero-box {
                border-left: 0;
                border-top: 1px solid rgba(219, 227, 239, 0.85);
            }
        }

        @media (max-width: 640px) {
            .auth-shell {
                padding: 1rem;
                gap: 1rem;
            }

            .hero-panel,
            .login-panel {
                border-radius: 22px;
            }

            .hero-panel__top,
            .hero-box,
            .login-panel {
                padding-left: 1rem;
                padding-right: 1rem;
            }

            .hero-panel__media {
                min-height: 220px;
            }

            .credentials-item {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="auth-shell">
        <section class="hero-panel">
            <div class="hero-panel__top">
                <h1>Sistema de Gestión de Seguros (SGS)</h1>
            </div>

            <div class="hero-panel__media">
                <img
                    src="<?= demo_e(demo_url('assets/img/familia.webp')) ?>"
                    alt="Familia protegida"
                >
            </div>

            <div class="hero-panel__bottom">
                <div class="hero-box">
                    <h2>Protección, confianza y tranquilidad</h2>
                    <p>
                        Los seguros ayudan a cuidar la estabilidad de las familias y de las empresas frente a eventos inesperados. Un sistema moderno de gestión permite brindar un servicio más ordenado, cercano y confiable, mejorando el seguimiento de pólizas, pagos y atenciones.
                    </p>
                </div>

                <div class="hero-box">
                    <h2>Panorama asegurador en el Perú</h2>
                    <p>
                        El sector asegurador peruano continúa fortaleciendo la atención digital y el acompañamiento al cliente, con mayor enfoque en prevención, seguimiento oportuno y respuesta ágil ante siniestros. Esta plataforma demo representa esa visión de servicio profesional, claro y bien organizado.
                    </p>
                </div>
            </div>
        </section>

        <section class="login-panel">
            <div class="login-panel__brand">
                <div class="login-panel__logo">BS</div>
                <div>
                    <h2>BrokerSeguros</h2>
                    <p>Inicia sesión con uno de los usuarios demo</p>
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

        form.addEventListener('ajax:success', () => {
            setError('');
        });
    });
</script>
</body>
</html>