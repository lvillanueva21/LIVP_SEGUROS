<?php
declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';

$context = requireModuleAccess('mis-seguros');
$user = $context['user'];
$module = $context['module'];
$menu = modulesForRole((string) $user['role']);
$activeModule = 'mis-seguros';
$pageTitle = 'Mis Seguros';

if ((string) $user['role'] !== 'cliente') {
    header('Location: ' . appRelativeUrl('acceso_denegado.php?modulo=mis-seguros&motivo=permiso'));
    exit;
}

$expedients = require __DIR__ . '/config/demo_expedients.php';
$clients = require __DIR__ . '/config/demo_clients.php';

function clientPortalJson(mixed $value): string {
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
    <link rel="stylesheet" href="assets/css/client-portal.css?v=BS-20260627-234916-CLIENTACCOUNTSV1">
</head>
<body
    class="app-body"
    data-role="<?= e((string) $user['role']) ?>"
    data-user="<?= e((string) $user['id']) ?>"
    data-client-name="<?= e((string) $user['name']) ?>"
    data-client-entity="<?= e((string) ($user['entity_name'] ?? $user['name'])) ?>"
    data-client-document="<?= e((string) $user['document']) ?>"
    data-client-account-type="<?= e((string) ($user['account_type'] ?? 'empresa')) ?>"
>
<div class="app-shell">
    <?php require __DIR__ . '/views/partials/sidebar.php'; ?>
    <div class="sidebar-overlay" id="sidebar-overlay"></div>

    <main class="workspace">
        <?php require __DIR__ . '/views/partials/topbar.php'; ?>

        <section class="workspace-content">
            <article class="module-hero client-portal-hero">
                <div class="module-hero-icon" aria-hidden="true">▣</div>
                <div>
                    <p class="eyebrow">PORTAL DE CLIENTE</p>
                    <h2>Mis seguros y gestiones</h2>
                    <p>Consulta únicamente la información pública asociada a tu empresa o consorcio.</p>
                </div>
                <span class="module-access-badge">Solo consulta</span>
            </article>

            <section id="client-portal-app" class="client-portal-app" aria-live="polite">
                <div class="client-portal-welcome">
                    <div>
                        <p class="eyebrow">CUENTA VINCULADA</p>
                        <h3><?= e((string) ($user['entity_name'] ?? $user['name'])) ?></h3>
                        <p><?= e((string) ($user['entity_type'] ?? $user['account_type_label'] ?? 'Cliente')) ?> · Documento <?= e((string) $user['document']) ?></p>
                    </div>
                    <span class="client-portal-local-badge">Ambiente de demostración</span>
                </div>

                <div class="client-metrics-grid">
                    <article class="client-metric-card">
                        <span class="client-metric-icon">▣</span>
                        <p>Pólizas vigentes</p>
                        <strong id="client-policy-active">0</strong>
                        <small>Activas a la fecha</small>
                    </article>
                    <article class="client-metric-card client-metric-warning">
                        <span class="client-metric-icon">◷</span>
                        <p>Próximas a vencer</p>
                        <strong id="client-policy-near">0</strong>
                        <small>Dentro de 30 días</small>
                    </article>
                    <article class="client-metric-card client-metric-primary">
                        <span class="client-metric-icon">▧</span>
                        <p>Mis gestiones</p>
                        <strong id="client-expedient-total">0</strong>
                        <small>Casos vinculados</small>
                    </article>
                </div>

                <div class="client-portal-tabs" role="tablist" aria-label="Información del cliente">
                    <button class="client-portal-tab is-active" type="button" data-client-tab="policies" role="tab">Mis pólizas <span id="client-policy-count">0</span></button>
                    <button class="client-portal-tab" type="button" data-client-tab="cases" role="tab">Mis gestiones <span id="client-case-count">0</span></button>
                </div>

                <section class="client-portal-panel is-active" data-client-panel="policies">
                    <div class="client-panel-heading">
                        <div>
                            <p class="eyebrow">PÓLIZAS PUBLICADAS</p>
                            <h3>Mis pólizas</h3>
                            <p>Se muestran pólizas activas e históricas asociadas a tu entidad. Las pólizas desactivadas no están disponibles en este portal.</p>
                        </div>
                        <label class="client-filter-label">
                            <span>Ver</span>
                            <select id="client-policy-filter">
                                <option value="all">Todas</option>
                                <option value="active">Vigentes</option>
                                <option value="near">Próximas a vencer</option>
                                <option value="expired">Vencidas</option>
                            </select>
                        </label>
                    </div>
                    <div id="client-policy-list" class="client-policy-list"></div>
                    <p id="client-policy-empty" class="client-empty" hidden>No hay pólizas disponibles para esta cuenta.</p>
                </section>

                <section class="client-portal-panel" data-client-panel="cases">
                    <div class="client-panel-heading">
                        <div>
                            <p class="eyebrow">SEGUIMIENTO</p>
                            <h3>Mis gestiones</h3>
                            <p>Vista simplificada de expedientes vinculados a tu empresa o consorcio. No muestra notas, cotizaciones ni actividades internas.</p>
                        </div>
                    </div>
                    <div id="client-case-list" class="client-case-list"></div>
                    <p id="client-case-empty" class="client-empty" hidden>No hay gestiones disponibles para esta cuenta.</p>
                </section>

                <div class="client-portal-info">
                    <strong>Sobre esta maqueta:</strong>
                    <span>La información corresponde a un entorno de demostración y está disponible solo para consulta. La gestión interna permanece restringida a la corredora.</span>
                </div>
            </section>
        </section>
    </main>
</div>

<dialog id="client-detail-dialog" class="client-detail-dialog" aria-labelledby="client-detail-title">
    <section class="client-detail-content">
        <div class="client-detail-heading">
            <div>
                <p class="eyebrow" id="client-detail-eyebrow">DETALLE</p>
                <h2 id="client-detail-title">Información</h2>
            </div>
            <button id="client-detail-close" type="button" class="client-detail-close" aria-label="Cerrar">×</button>
        </div>
        <div id="client-detail-body"></div>
    </section>
</dialog>

<script id="client-default-expedients" type="application/json"><?= clientPortalJson($expedients) ?></script>
<script id="client-default-clients" type="application/json"><?= clientPortalJson($clients) ?></script>
<script src="assets/js/cache-migrations.js?v=BS-20260627-234916-CLIENTACCOUNTSV1"></script>
<script src="assets/js/app.js?v=BS-20260627-234916-CLIENTACCOUNTSV1"></script>
<script src="assets/js/client-portal-data.js?v=BS-20260627-234916-CLIENTACCOUNTSV1"></script><script src="assets/js/client-portal.js?v=BS-20260627-234916-CLIENTACCOUNTSV1"></script>
</body>
</html>
