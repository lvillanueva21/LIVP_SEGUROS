<?php
declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';

$context = requireModuleAccess('expedientes');
$user = $context['user'];
$module = $context['module'];
$menu = modulesForRole((string) $user['role']);
$activeModule = 'expedientes';
$pageTitle = 'Expedientes';

$expedientData = require __DIR__ . '/config/demo_expedients.php';
$catalogData = require __DIR__ . '/config/demo_catalogs.php';

$expedientData = is_array($expedientData) ? $expedientData : [];
$catalogData = is_array($catalogData) ? $catalogData : [];

$expedientDataJson = json_encode(
    $expedientData,
    JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
);

$catalogDataJson = json_encode(
    $catalogData,
    JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
);

if ($expedientDataJson === false) {
    $expedientDataJson = '{}';
}

if ($catalogDataJson === false) {
    $catalogDataJson = '{}';
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
    <link rel="stylesheet" href="assets/css/expedientes.css?v=BS-20260627-234916-CLIENTACCOUNTSV1">
    <link rel="stylesheet" href="assets/css/modal-ui.css?v=BS-20260627-234916-CLIENTACCOUNTSV1" data-broker-modal-ui-styles="true">
    <link rel="stylesheet" href="assets/css/notifications.css?v=BS-20260627-234916-CLIENTACCOUNTSV1" data-broker-notification-styles="true">
    <link rel="stylesheet" href="assets/css/polizas.css?v=BS-20260627-234916-CLIENTACCOUNTSV1">
    <link rel="stylesheet" href="assets/css/policy-alerts.css?v=BS-20260627-234916-CLIENTACCOUNTSV1">
    <link rel="stylesheet" href="assets/css/activity-timeline.css?v=BS-20260627-234916-CLIENTACCOUNTSV1">
    <link rel="stylesheet" href="assets/css/expedientes-recovery.css?v=BS-20260627-234916-CLIENTACCOUNTSV1">
    <link rel="stylesheet" href="assets/css/expediente-hub-v2.css?v=BS-20260627-234916-CLIENTACCOUNTSV1">
    <link rel="stylesheet" href="assets/css/policy-payments.css?v=BS-20260627-234916-CLIENTACCOUNTSV1">
    <link rel="stylesheet" href="assets/css/published-state-sync.css?v=BS-20260627-234916-CLIENTACCOUNTSV1">
</head>
<body class="app-body" data-role="<?= e((string) $user['role']) ?>" data-user="<?= e((string) $user['id']) ?>">
<div class="app-shell">
    <?php require __DIR__ . '/views/partials/sidebar.php'; ?>
    <div class="sidebar-overlay" id="sidebar-overlay"></div>

    <main class="workspace">
        <?php require __DIR__ . '/views/partials/topbar.php'; ?>

        <section class="workspace-content">
            <article class="module-hero expedients-hero">
                <div class="module-hero-icon" aria-hidden="true"><?= e((string) ($module['icon'] ?? '▧')) ?></div>
                <div>
                    <p class="eyebrow">NÚCLEO OPERATIVO</p>
                    <h2>Expedientes demo</h2>
                    <p>Un expediente inicia con contacto de gestión, nombre y descripción. El cliente, cotización y póliza se definen cuando corresponda.</p>
                </div>
                <span class="module-access-badge">Gestión interna compartida</span>
            </article>

            <section id="expedients-app" class="expedients-app">
                <div class="expedients-summary-grid">
                    <article class="expedient-summary-card">
                        <span class="expedient-summary-icon">▧</span>
                        <p>Expedientes visibles</p>
                        <strong id="summary-total">0</strong>
                        <small>Gestión de gerente y ejecutivo</small>
                    </article>
                    <article class="expedient-summary-card summary-warning">
                        <span class="expedient-summary-icon">○</span>
                        <p>Cliente pendiente</p>
                        <strong id="summary-pending-client">0</strong>
                        <small>Sin empresa o consorcio definido</small>
                    </article>
                    <article class="expedient-summary-card summary-primary">
                        <span class="expedient-summary-icon">◈</span>
                        <p>Sin cotización</p>
                        <strong id="summary-without-quotes">0</strong>
                        <small>Casos que pueden continuar así</small>
                    </article>
                    <article class="expedient-summary-card summary-success">
                        <span class="expedient-summary-icon">✓</span>
                        <p>Expedientes cerrados</p>
                        <strong id="summary-closed">0</strong>
                        <small>Con o sin contratación</small>
                    </article>
                </div>

                <section class="expedients-panel">
                    <div class="expedients-panel-heading">
                        <div>
                            <p class="eyebrow">CONTROL DE EXPEDIENTES</p>
                            <h2>Listado de expedientes</h2>
                            <p id="expedients-context-text">Gerentes y ejecutivos pueden trabajar sobre todos los expedientes. Los datos demo se guardan únicamente en este navegador.</p>
                        </div>
                        <button id="add-expedient" class="expedient-primary-button" type="button">+ Crear expediente</button>
                    </div>

                    <div class="expedient-filters" aria-label="Filtros de expedientes">
                        <label class="filter-search">
                            <span>Buscar</span>
                            <input id="filter-search" type="search" placeholder="Código, contacto, cliente, nombre o detalle">
                        </label>
                        <label>
                            <span>Situación</span>
                            <select id="filter-state"><option value="">Todas</option></select>
                        </label>
                        <label>
                            <span>Cliente o entidad</span>
                            <select id="filter-client"><option value="">Todos</option></select>
                        </label>
                        <button id="clear-expedient-filters" class="expedient-secondary-button" type="button">Limpiar filtros</button>
                    </div>

                    <div class="expedient-table-wrap">
                        <table class="expedient-table">
                            <thead>
                            <tr>
                                <th scope="col">Código</th>
                                <th scope="col">Contacto de gestión</th>
                                <th scope="col">Cliente o entidad</th>
                                <th scope="col">Expediente</th>
                                <th scope="col">Situación</th>
                                <th scope="col">Cotizaciones</th>
                                <th scope="col">Actualización</th>
                                <th scope="col">Acción</th>
                            </tr>
                            </thead>
                            <tbody id="expedient-table-body">
                            <tr><td colspan="8">Cargando expedientes…</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <p id="expedient-empty" class="expedient-empty" hidden>No existen expedientes que coincidan con los filtros seleccionados.</p>
                </section>
            </section>
        </section>
    </main>
</div>

<dialog id="expedient-form-dialog" class="expedient-dialog" aria-labelledby="expedient-form-title">
    <form id="expedient-form" method="dialog">
        <div class="expedient-dialog-heading">
            <div>
                <p class="eyebrow">NUEVO EXPEDIENTE</p>
                <h2 id="expedient-form-title">Crear expediente</h2>
            </div>
            <button id="expedient-form-close" class="expedient-dialog-close" type="button" aria-label="Cerrar">×</button>
        </div>
        <p class="expedient-form-help">Registra el contacto de gestión, nombre y detalle del proceso. El cliente o entidad puede quedar pendiente; será obligatorio antes de registrar una póliza.</p>

        <div class="expedient-form-grid">
            <label>
                <span>Contacto de gestión <b aria-hidden="true">*</b></span>
                <select id="expedient-contact" required></select>
            </label>
            <div class="expedient-contact-create">
                <span>¿No está registrado?</span>
                <button id="show-quick-contact" class="expedient-link-button" type="button">+ Registrar contacto rápido</button>
            </div>
            <label>
                <span>Cliente o entidad</span>
                <select id="expedient-client"></select>
            </label>
            <label>
                <span>Situación inicial</span>
                <select id="expedient-state"></select>
            </label>
            <label class="expedient-field-wide">
                <span>Nombre del expediente <b aria-hidden="true">*</b></span>
                <input id="expedient-title" type="text" maxlength="140" placeholder="Ejemplo: Protección para obra vial" required>
            </label>
        </div>

        <section id="quick-contact-panel" class="quick-contact-panel" hidden aria-label="Registro rápido de contacto">
            <div class="quick-contact-heading">
                <div>
                    <p class="eyebrow">CONTACTO DE GESTIÓN</p>
                    <h3>Registrar contacto rápido</h3>
                </div>
                <button id="cancel-quick-contact" class="expedient-dialog-close" type="button" aria-label="Cerrar registro de contacto">×</button>
            </div>
            <p>Este contacto es una persona natural relacionada con el proceso. No es usuario interno ni cliente por sí mismo.</p>
            <div class="expedient-form-grid">
                <label><span>Nombre completo <b aria-hidden="true">*</b></span><input id="quick-contact-name" type="text" maxlength="140" placeholder="Nombres y apellidos"></label>
                <label><span>Celular <b aria-hidden="true">*</b></span><input id="quick-contact-mobile" type="text" maxlength="30" placeholder="Ejemplo: 999 999 999"></label>
                <label><span>Correo</span><input id="quick-contact-email" type="email" maxlength="140" placeholder="Opcional"></label>
                <label><span>Etiqueta o cargo</span><input id="quick-contact-label" type="text" maxlength="80" placeholder="Ejemplo: secretaria, gerente, solicitante"></label>
                <label><span>Entidad vinculada</span><select id="quick-contact-entity"></select></label>
            </div>
            <div class="quick-contact-actions">
                <button id="save-quick-contact" class="expedient-secondary-button" type="button">Guardar y seleccionar contacto</button>
                <button id="cancel-quick-contact-secondary" class="expedient-secondary-button" type="button">Cancelar</button>
            </div>
        </section>

        <label class="expedient-description-field">
            <span>Descripción o detalle <b aria-hidden="true">*</b></span>
            <textarea id="expedient-description" rows="4" maxlength="800" placeholder="Registra el contexto inicial disponible." required></textarea>
        </label>
        <p class="expedient-code-note">El código y la fecha/hora Perú se generan automáticamente. No se exigirá cliente, cotización, seguro, póliza, pago ni documento para crear el expediente.</p>
        <div class="expedient-dialog-actions">
            <button id="expedient-form-cancel" class="expedient-secondary-button" type="button">Cancelar</button>
            <button class="expedient-primary-button" type="submit">Crear expediente demo</button>
        </div>
    </form>
</dialog>

<dialog id="expedient-detail-dialog" class="expedient-dialog expedient-detail-dialog" aria-labelledby="expedient-detail-title">
    <div class="expedient-dialog-content">
        <div class="expedient-dialog-heading">
            <div>
                <p class="eyebrow">FICHA DE EXPEDIENTE</p>
                <h2 id="expedient-detail-title">Expediente</h2>
            </div>
            <button id="expedient-detail-close" class="expedient-dialog-close" type="button" aria-label="Cerrar">×</button>
        </div>
        <div id="expedient-detail-content"></div>
    </div>
</dialog>

<script id="expedient-default-data" type="application/json"><?= $expedientDataJson ?></script>
<script id="expedient-catalog-data" type="application/json"><?= $catalogDataJson ?></script>
<script src="assets/js/cache-migrations.js?v=BS-20260627-234916-CLIENTACCOUNTSV1"></script>
<script src="assets/js/app.js?v=BS-20260627-234916-CLIENTACCOUNTSV1"></script>
<script src="assets/js/expedientes.js?v=BS-20260627-234916-CLIENTACCOUNTSV1"></script>
<script src="assets/js/polizas.js?v=BS-20260627-234916-CLIENTACCOUNTSV1"></script>
<script src="assets/js/policy-alerts.js?v=BS-20260627-234916-CLIENTACCOUNTSV1"></script>
<script src="assets/js/activity-timeline.js?v=BS-20260627-234916-CLIENTACCOUNTSV1"></script>
<script src="assets/js/expediente-hub-v2.js?v=BS-20260627-234916-CLIENTACCOUNTSV1"></script>
<script src="assets/js/policy-payments.js?v=BS-20260627-234916-CLIENTACCOUNTSV1"></script>
<script src="assets/js/published-state-sync.js?v=BS-20260627-234916-CLIENTACCOUNTSV1"></script>
</body>
</html>
