<?php

declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';
require __DIR__ . '/services/correo_sistema.php';

$context = requireModuleAccess('configuracion-correos');
$user = $context['user'];
$menu = modulesForRole((string) $user['role']);
$activeModule = 'configuracion';
$pageTitle = 'Configuración de Correos';

$flash = ['type' => '', 'message' => ''];
$setupError = '';
$activeTab = in_array((string) ($_GET['tab'] ?? ''), ['resumen', 'configuracion', 'historial'], true)
    ? (string) $_GET['tab']
    : 'resumen';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'guardar_configuracion') {
            $activeTab = 'configuracion';
            if (!csrfValidate('correo_config_form', (string) ($_POST['csrf_token'] ?? ''))) {
                $flash = ['type' => 'danger', 'message' => 'No se pudo validar el formulario. Recarga la página e intenta nuevamente.'];
            } else {
                $result = correo_save_config($_POST, $user);
                if (($result['ok'] ?? false) === true) {
                    $flash = ['type' => 'success', 'message' => (string) ($result['message'] ?? 'Configuración guardada correctamente.')];
                } else {
                    $flash = ['type' => 'danger', 'message' => implode(' ', (array) ($result['errors'] ?? ['No se pudo guardar la configuración.']))];
                }
            }
        } elseif ($action === 'probar_correo') {
            $activeTab = 'resumen';
            if (!csrfValidate('correo_test_form', (string) ($_POST['csrf_token'] ?? ''))) {
                $flash = ['type' => 'danger', 'message' => 'No se pudo validar la prueba. Recarga la página e intenta nuevamente.'];
            } else {
                $result = correo_run_test($user);
                $estado = (string) ($result['estado'] ?? 'fallido');
                if ($estado === 'enviado') {
                    $flash = ['type' => 'success', 'message' => 'Prueba enviada correctamente. Zoho aceptó todos los destinatarios configurados.'];
                } elseif ($estado === 'parcial') {
                    $flash = ['type' => 'warning', 'message' => 'Prueba enviada parcialmente. Revisa el historial para ver qué destinatario falló.'];
                } else {
                    $flash = ['type' => 'danger', 'message' => 'La prueba falló. Revisa el detalle técnico en el historial.'];
                }
            }
        }
    }

    $config = correo_ensure_config(correo_current_database_user_id($user));
    $recipients = correo_fetch_test_recipients((int) ($config['id'] ?? 0));
    $status = correo_compute_state($config, $recipients);
    $metrics = correo_metrics();
    $history = correo_history(25);
} catch (Throwable $exception) {
    $setupError = $exception->getMessage();
    $config = correo_default_config_values();
    $recipients = [];
    $status = 'incompleta';
    $metrics = [
        'envios_total' => 0,
        'envios_enviados' => 0,
        'envios_fallidos' => 0,
        'envios_parciales' => 0,
        'destinatarios_aceptados' => 0,
        'destinatarios_fallidos' => 0,
    ];
    $history = [];
}

$recipient1 = (string) ($recipients[0]['correo'] ?? '');
$recipient2 = (string) ($recipients[1]['correo'] ?? '');
$hasPassword = correo_config_has_password($config);
$configComplete = correo_config_is_complete($config, $recipients);
$configCsrf = csrfToken('correo_config_form');
$testCsrf = csrfToken('correo_test_form');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(APP_NAME) ?> | Configuración de Correos</title>
    <link rel="stylesheet" href="assets/css/app.css?v=BS-CORREOV1">
    <link rel="stylesheet" href="assets/css/modules.css?v=BS-CORREOV1">
    <link rel="stylesheet" href="assets/css/correo-configuracion.css?v=BS-CORREOV1">
</head>
<body class="app-body" data-role="<?= e((string) $user['role']) ?>" data-user="<?= e((string) $user['id']) ?>">
<div class="app-shell">
    <?php require __DIR__ . '/views/partials/sidebar.php'; ?>
    <div class="sidebar-overlay" id="sidebar-overlay"></div>
    <main class="workspace">
        <?php require __DIR__ . '/views/partials/topbar.php'; ?>
        <section class="workspace-content">
            <article class="module-hero mail-hero">
                <div class="module-hero-icon" aria-hidden="true">✉</div>
                <div>
                    <p class="eyebrow">CONFIGURACIÓN TÉCNICA</p>
                    <h2>Gestión de Correos</h2>
                    <p>Configura el correo Zoho que usará Broker Seguros para enviar notificaciones y valida el envío con una prueba real.</p>
                </div>
                <span class="module-access-badge">Solo Desarrollo</span>
            </article>

            <div class="mail-back-row">
                <a class="mail-back-link" href="<?= e(appRelativeUrl('configuracion.php')) ?>">← Volver a Configuración</a>
            </div>

            <?php if ($setupError !== ''): ?>
                <div class="mail-alert mail-alert-danger">
                    <strong>No se pudo cargar el módulo de correos.</strong>
                    <span><?= e($setupError) ?></span>
                    <small>Si aún no creaste las tablas, ejecuta primero los CREATE TABLE indicados.</small>
                </div>
            <?php endif; ?>

            <?php if ($flash['message'] !== ''): ?>
                <div class="mail-alert mail-alert-<?= e($flash['type']) ?>">
                    <?= e($flash['message']) ?>
                </div>
            <?php endif; ?>

            <nav class="mail-tabs" aria-label="Secciones de configuración de correos">
                <button type="button" class="mail-tab <?= $activeTab === 'resumen' ? 'is-active' : '' ?>" data-mail-tab="resumen">Resumen y prueba</button>
                <button type="button" class="mail-tab <?= $activeTab === 'configuracion' ? 'is-active' : '' ?>" data-mail-tab="configuracion">Configuración</button>
                <button type="button" class="mail-tab <?= $activeTab === 'historial' ? 'is-active' : '' ?>" data-mail-tab="historial">Historial</button>
            </nav>

            <section class="mail-tab-panel <?= $activeTab === 'resumen' ? 'is-active' : '' ?>" data-mail-panel="resumen">
                <div class="mail-summary-grid">
                    <article class="mail-status-card <?= e(correo_status_class($status)) ?>">
                        <span>Estado actual</span>
                        <strong><?= e(correo_status_label($status)) ?></strong>
                        <p><?= $configComplete ? 'La configuración tiene los datos mínimos para intentar una prueba SMTP.' : 'Falta remitente, clave Zoho o al menos un destinatario de prueba.' ?></p>
                    </article>
                    <article class="mail-info-card">
                        <span>Remitente</span>
                        <strong><?= e((string) ($config['correo_remitente'] ?? 'No configurado')) ?></strong>
                        <p><?= e((string) ($config['nombre_remitente'] ?? SEG_CORREO_DEFAULT_FROM_NAME)) ?></p>
                    </article>
                    <article class="mail-info-card">
                        <span>Última prueba</span>
                        <strong><?= e(correo_format_datetime((string) ($config['ultima_prueba_en'] ?? ''))) ?></strong>
                        <p>Última exitosa: <?= e(correo_format_datetime((string) ($config['ultima_prueba_exitosa_en'] ?? ''))) ?></p>
                    </article>
                </div>

                <div class="mail-action-panel">
                    <div>
                        <p class="eyebrow">PRUEBA REAL SMTP</p>
                        <h3>Probar notificaciones por correo</h3>
                        <p>Usa la configuración guardada. Envía el asunto y mensaje de prueba a los destinatarios definidos y agrega CC si configuraste copia administrativa.</p>
                        <?php if (($config['ultimo_error'] ?? '') !== ''): ?>
                            <p class="mail-last-error"><strong>Último error:</strong> <?= e((string) $config['ultimo_error']) ?></p>
                        <?php endif; ?>
                    </div>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= e($testCsrf) ?>">
                        <input type="hidden" name="action" value="probar_correo">
                        <button class="mail-primary-button" type="submit" <?= $configComplete && $setupError === '' ? '' : 'disabled' ?>>Probar notificaciones por correo</button>
                    </form>
                </div>

                <div class="mail-metrics-grid">
                    <article><span>Correos generados</span><strong><?= e((string) $metrics['envios_total']) ?></strong></article>
                    <article><span>Enviados</span><strong><?= e((string) $metrics['envios_enviados']) ?></strong></article>
                    <article><span>Fallidos</span><strong><?= e((string) $metrics['envios_fallidos']) ?></strong></article>
                    <article><span>Parciales</span><strong><?= e((string) $metrics['envios_parciales']) ?></strong></article>
                    <article><span>Destinatarios aceptados</span><strong><?= e((string) $metrics['destinatarios_aceptados']) ?></strong></article>
                    <article><span>Destinatarios fallidos</span><strong><?= e((string) $metrics['destinatarios_fallidos']) ?></strong></article>
                </div>
            </section>

            <section class="mail-tab-panel <?= $activeTab === 'configuracion' ? 'is-active' : '' ?>" data-mail-panel="configuracion">
                <form method="post" class="mail-form" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= e($configCsrf) ?>">
                    <input type="hidden" name="action" value="guardar_configuracion">

                    <div class="mail-form-grid">
                        <label>
                            <span>Correo remitente Zoho</span>
                            <input type="email" name="correo_remitente" required value="<?= e((string) ($config['correo_remitente'] ?? '')) ?>" placeholder="notificaciones@tudominio.com">
                        </label>
                        <label>
                            <span>Nombre visible del remitente</span>
                            <input type="text" name="nombre_remitente" required maxlength="190" value="<?= e((string) ($config['nombre_remitente'] ?? SEG_CORREO_DEFAULT_FROM_NAME)) ?>">
                        </label>
                        <label class="mail-password-field">
                            <span>Clave de aplicación Zoho <?= $hasPassword ? '<small>Clave registrada. Déjalo vacío para conservarla.</small>' : '' ?></span>
                            <div class="mail-password-wrap">
                                <input id="clave-aplicacion" type="password" name="clave_aplicacion" data-clean-spaces="1" placeholder="Pega aquí la clave de aplicación">
                                <button type="button" class="mail-eye-button" data-toggle-password="#clave-aplicacion" aria-label="Mostrar u ocultar clave">👁</button>
                            </div>
                        </label>
                        <label>
                            <span>Correo con copia administrativa <small>Opcional, va como CC visible</small></span>
                            <input type="email" name="correo_copia_administrativa" value="<?= e((string) ($config['correo_copia_administrativa'] ?? '')) ?>" placeholder="gerencia@tudominio.com">
                        </label>
                        <label>
                            <span>Destinatario de prueba 1</span>
                            <input type="email" name="destinatario_prueba_1" required value="<?= e($recipient1) ?>" placeholder="tu.correo@dominio.com">
                        </label>
                        <label>
                            <span>Destinatario de prueba 2 <small>Opcional</small></span>
                            <input type="email" name="destinatario_prueba_2" value="<?= e($recipient2) ?>" placeholder="otro.correo@dominio.com">
                        </label>
                    </div>

                    <label class="mail-full-field">
                        <span>Asunto de prueba</span>
                        <input type="text" name="asunto_prueba" required maxlength="255" value="<?= e((string) ($config['asunto_prueba'] ?? SEG_CORREO_DEFAULT_TEST_SUBJECT)) ?>">
                    </label>
                    <label class="mail-full-field">
                        <span>Mensaje de prueba</span>
                        <textarea name="mensaje_prueba" rows="7" required><?= e((string) ($config['mensaje_prueba'] ?? SEG_CORREO_DEFAULT_TEST_MESSAGE)) ?></textarea>
                    </label>

                    <div class="mail-form-actions">
                        <button type="submit" class="mail-primary-button" <?= $setupError === '' ? '' : 'disabled' ?>>Guardar configuración</button>
                        <p>Host SMTP definido en sistema: <?= e((string) SEG_CORREO_SMTP_HOST) ?>:<?= e((string) SEG_CORREO_SMTP_PORT) ?> TLS.</p>
                    </div>
                </form>
            </section>

            <section class="mail-tab-panel <?= $activeTab === 'historial' ? 'is-active' : '' ?>" data-mail-panel="historial">
                <div class="mail-history-card">
                    <div class="section-heading">
                        <div>
                            <p class="eyebrow">AUDITORÍA SIMPLE</p>
                            <h2>Últimos correos generados</h2>
                        </div>
                    </div>

                    <?php if ($history === []): ?>
                        <p class="mail-empty">Todavía no hay pruebas ni correos registrados.</p>
                    <?php else: ?>
                        <div class="mail-table-wrap">
                            <table class="mail-table">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Tipo</th>
                                        <th>Origen</th>
                                        <th>Asunto</th>
                                        <th>Estado</th>
                                        <th>Destinatarios</th>
                                        <th>Detalle</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($history as $row): ?>
                                        <?php $details = correo_history_details((int) $row['id']); ?>
                                        <tr>
                                            <td><?= e(correo_format_datetime((string) ($row['created_at'] ?? ''))) ?></td>
                                            <td><?= e((string) ($row['tipo_envio'] ?? '')) ?></td>
                                            <td><?= e((string) ($row['modulo_origen'] ?? '')) ?></td>
                                            <td><?= e((string) ($row['asunto'] ?? '')) ?></td>
                                            <td><span class="mail-state-pill mail-state-<?= e((string) ($row['estado_general'] ?? 'pendiente')) ?>"><?= e((string) ($row['estado_general'] ?? 'pendiente')) ?></span></td>
                                            <td><?= e((string) ((int) ($row['aceptados'] ?? 0))) ?> ok / <?= e((string) ((int) ($row['fallidos'] ?? 0))) ?> fallo</td>
                                            <td>
                                                <details class="mail-details">
                                                    <summary>Ver</summary>
                                                    <div class="mail-detail-box">
                                                        <p><strong>Remitente:</strong> <?= e((string) ($row['nombre_remitente'] ?? '')) ?> &lt;<?= e((string) ($row['correo_remitente'] ?? '')) ?>&gt;</p>
                                                        <p><strong>Mensaje enviado:</strong></p>
                                                        <pre><?= e((string) ($row['mensaje'] ?? '')) ?></pre>
                                                        <?php if (($row['error_general'] ?? '') !== ''): ?>
                                                            <p class="mail-last-error"><strong>Error general:</strong> <?= e((string) $row['error_general']) ?></p>
                                                        <?php endif; ?>
                                                        <table class="mail-mini-table">
                                                            <thead><tr><th>Tipo</th><th>Correo</th><th>Estado</th><th>Respuesta/Error</th></tr></thead>
                                                            <tbody>
                                                                <?php foreach ($details as $detail): ?>
                                                                    <tr>
                                                                        <td><?= e((string) ($detail['tipo_destinatario'] ?? '')) ?></td>
                                                                        <td><?= e((string) ($detail['correo'] ?? '')) ?></td>
                                                                        <td><?= e((string) ($detail['estado'] ?? '')) ?></td>
                                                                        <td><?= e((string) (($detail['detalle_error'] ?? '') !== '' ? $detail['detalle_error'] : ($detail['respuesta_smtp'] ?? ''))) ?></td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </details>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </section>
    </main>
</div>
<script src="assets/js/app.js?v=BS-CORREOV1"></script>
<script src="assets/js/correo-configuracion.js?v=BS-CORREOV1"></script>
</body>
</html>
