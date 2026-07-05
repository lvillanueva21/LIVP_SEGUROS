<?php
declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';

$context = requireModuleAccess('mis-siniestros');
$user = $context['user'];
$module = $context['module'];
$menu = modulesForRole((string) $user['role']);
$activeModule = 'mis-siniestros';
$pageTitle = 'Mis Siniestros';

if ((string) $user['role'] !== 'cliente') {
    header('Location: ' . appRelativeUrl('acceso_denegado.php?modulo=mis-siniestros&motivo=permiso'));
    exit;
}

$expedients = require __DIR__ . '/config/demo_expedients.php';
$clients = require __DIR__ . '/config/demo_clients.php';

function claimPortalJson(mixed $value): string {
    $json = json_encode(
        $value,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
    );
    return $json === false ? '{}' : $json;
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(APP_NAME) ?> | <?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="assets/css/app.css?v=BS-20260627-234916-CLIENTACCOUNTSV1">
    <link rel="stylesheet" href="assets/css/modules.css?v=BS-20260627-234916-CLIENTACCOUNTSV1">
    <link rel="stylesheet" href="assets/css/client-claims.css?v=BS-20260627-234916-CLIENTACCOUNTSV1">
</head>
<body
    class="app-body"
    data-role="<?= e((string) $user['role']) ?>"
    data-user="<?= e((string) $user['id']) ?>"
    data-client-name="<?= e((string) $user['name']) ?>"
    data-client-entity="<?= e((string) ($user['entity_name'] ?? $user['name'])) ?>"
    data-client-document="<?= e((string) $user['document']) ?>"
    data-client-phone="<?= e((string) ($user['contact_phone'] ?? '')) ?>"
>
<div class="app-shell">
    <?php require __DIR__ . '/views/partials/sidebar.php'; ?>
    <div class="sidebar-overlay" id="sidebar-overlay"></div>

    <main class="workspace">
        <?php require __DIR__ . '/views/partials/topbar.php'; ?>

        <section class="workspace-content">
            <article class="module-hero">
                <div class="module-hero-icon" aria-hidden="true">⚑</div>
                <div>
                    <p class="eyebrow">PORTAL DE CLIENTE</p>
                    <h2>Mis siniestros</h2>
                    <p>Registra una solicitud vinculada a una póliza disponible y consulta los reportes enviados.</p>
                </div>
                <span class="module-access-badge">Reporte inicial</span>
            </article>

            <section id="client-claims-app" class="client-claims-app" aria-live="polite">
                <div class="client-claims-summary">
                    <article>
                        <p>Reportados</p>
                        <strong id="claims-total">0</strong>
                        <small>Solicitudes registradas</small>
                    </article>
                    <article class="is-primary">
                        <p>En gestión</p>
                        <strong id="claims-open">0</strong>
                        <small>Reportados o en revisión</small>
                    </article>
                </div>

                <section class="client-claim-form-card">
                    <div class="client-claim-heading">
                        <div>
                            <p class="eyebrow">NUEVO REPORTE</p>
                            <h3>Reportar siniestro</h3>
                            <p>Describe brevemente lo ocurrido. La corredora revisará el reporte y te contactará por el canal registrado.</p>
                        </div>
                    </div>

                    <form id="client-claim-form" class="client-claim-form" novalidate>
                        <label class="wide">
                            <span>Póliza vinculada <b>*</b></span>
                            <select id="claim-policy" required>
                                <option value="">Selecciona una póliza</option>
                            </select>
                        </label>

                        <label>
                            <span>Fecha del evento <b>*</b></span>
                            <input id="claim-event-date" type="date" required>
                        </label>

                        <label>
                            <span>Tipo de reporte <b>*</b></span>
                            <select id="claim-category" required>
                                <option value="">Selecciona</option>
                                <option value="Accidente">Accidente</option>
                                <option value="Daño material">Daño material</option>
                                <option value="Robo o pérdida">Robo o pérdida</option>
                                <option value="Atención médica">Atención médica</option>
                                <option value="Incidente de viaje">Incidente de viaje</option>
                                <option value="Otro">Otro</option>
                            </select>
                        </label>

                        <label>
                            <span>Distrito / lugar</span>
                            <input id="claim-location" maxlength="160" placeholder="Ejemplo: Trujillo, La Libertad">
                        </label>

                        <label>
                            <span>Teléfono de contacto</span>
                            <input id="claim-phone" maxlength="40" placeholder="Teléfono para coordinación">
                        </label>

                        <label class="wide">
                            <span>Descripción inicial <b>*</b></span>
                            <textarea id="claim-description" rows="5" maxlength="1200" required placeholder="Indica de forma breve qué ocurrió y cualquier dato útil para que la corredora se comunique contigo."></textarea>
                            <small>No incluyas contraseñas, datos bancarios ni información sensible que no sea necesaria para el reporte inicial.</small>
                        </label>

                        <div class="client-claim-actions">
                            <button class="client-claim-primary" type="submit">Registrar reporte</button>
                        </div>
                    </form>
                </section>

                <section class="client-claim-list-card">
                    <div class="client-claim-heading">
                        <div>
                            <p class="eyebrow">SEGUIMIENTO</p>
                            <h3>Reportes enviados</h3>
                            <p>Este portal muestra la etapa inicial del reporte. La gestión posterior se realiza con la corredora.</p>
                        </div>
                    </div>
                    <div id="client-claim-list" class="client-claim-list"></div>
                    <p id="client-claim-empty" class="client-claim-empty" hidden>Aún no hay reportes registrados para esta cuenta.</p>
                </section>

                <div class="client-claims-info">
                    <strong>Importante:</strong>
                    <span>El reporte se registra en la ficha interna de la póliza y genera una actividad para Gerente y Ejecutivo. No representa aceptación ni cobertura automática.</span>
                </div>
            </section>
        </section>
    </main>
</div>

<dialog id="client-claim-detail-dialog" class="client-claim-detail-dialog">
    <section class="client-claim-detail-content">
        <div class="client-claim-detail-heading">
            <div>
                <p class="eyebrow">REPORTE DE SINIESTRO</p>
                <h2 id="client-claim-detail-title">Detalle</h2>
            </div>
            <button id="client-claim-detail-close" type="button" class="client-claim-detail-close" aria-label="Cerrar">×</button>
        </div>
        <div id="client-claim-detail-body"></div>
    </section>
</dialog>

<script id="claim-default-expedients" type="application/json"><?= claimPortalJson($expedients) ?></script>
<script id="claim-default-clients" type="application/json"><?= claimPortalJson($clients) ?></script>
<script src="assets/js/cache-migrations.js?v=BS-20260627-234916-CLIENTACCOUNTSV1"></script>
<script src="assets/js/app.js?v=BS-20260627-234916-CLIENTACCOUNTSV1"></script>
<script src="assets/js/client-portal-data.js?v=BS-20260627-234916-CLIENTACCOUNTSV1"></script><script src="assets/js/client-claims.js?v=BS-20260627-234916-CLIENTACCOUNTSV1"></script>
</body>
</html>
