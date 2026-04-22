<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';

demo_require_roles(['cliente']);

$portalUser = demo_current_user();
$clientId = (string)($portalUser['client_id'] ?? '');

$clients = demo_store('clients', []);
$client = demo_find_by_id($clients, $clientId);

if (!$client) {
    demo_push_toast('No se encontró la ficha del cliente vinculada a este usuario.', 'error', 'Portal incompleto');
    demo_redirect('logout.php');
}

$policies = demo_store('policies', []);
$installments = demo_store('installments', []);
$claims = demo_store('claims', []);
$documents = demo_store('documents', []);

$clientPolicies = array_values(array_filter($policies, fn($policy) => (string)($policy['client_id'] ?? '') === $clientId));
$policyIds = array_column($clientPolicies, 'id');

$activePolicies = array_values(array_filter($clientPolicies, fn($policy) => ($policy['status'] ?? '') === 'activa'));

$clientInstallments = array_values(array_filter($installments, fn($item) => in_array((string)($item['policy_id'] ?? ''), $policyIds, true)));
$upcomingPayments = array_values(array_filter($clientInstallments, function ($item) {
    $status = strtolower((string)($item['status'] ?? ''));
    return in_array($status, ['pendiente', 'vencida', 'en revisión'], true);
}));
usort($upcomingPayments, fn($a, $b) => strtotime((string)($a['due_date'] ?? '')) <=> strtotime((string)($b['due_date'] ?? '')));
$upcomingPaymentsPreview = array_slice($upcomingPayments, 0, 5);

$openClaims = array_values(array_filter($claims, function ($claim) use ($clientId, $policyIds) {
    return (
        ((string)($claim['client_id'] ?? '') === $clientId || in_array((string)($claim['policy_id'] ?? ''), $policyIds, true))
        && ($claim['status'] ?? '') !== 'cerrado'
    );
}));

$clientDocuments = array_values(array_filter($documents, function ($document) use ($clientId, $policyIds) {
    return (
        ((string)($document['entity_type'] ?? '') === 'client' && (string)($document['entity_id'] ?? '') === $clientId)
        || ((string)($document['entity_type'] ?? '') === 'policy' && in_array((string)($document['entity_id'] ?? ''), $policyIds, true))
    );
}));

$recentPolicies = $clientPolicies;
usort($recentPolicies, fn($a, $b) => strtotime((string)($b['created_at'] ?? '')) <=> strtotime((string)($a['created_at'] ?? '')));
$recentPolicies = array_slice($recentPolicies, 0, 4);

$portalActive = 'inicio';

ob_start();
?>
<style>
    .portal-home-grid {
        display: grid;
        gap: 1rem;
    }

    .portal-hero {
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(219, 227, 239, .9);
        border-radius: 26px;
        padding: 1.2rem 1.2rem 1.15rem;
        background:
            radial-gradient(circle at top right, rgba(79, 70, 229, .16), transparent 30%),
            radial-gradient(circle at bottom left, rgba(14, 165, 164, .12), transparent 32%),
            linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        box-shadow: var(--shadow-sm);
    }

    .portal-hero__row {
        display: grid;
        gap: 1rem;
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: center;
    }

    .portal-hero__title {
        margin: 0;
        font-size: clamp(1.55rem, 2.3vw, 2.2rem);
        line-height: 1.08;
    }

    .portal-hero__text {
        margin: .35rem 0 0;
        color: var(--text-soft);
        line-height: 1.6;
        max-width: 60ch;
    }

    .portal-hero__badge {
        padding: .75rem 1rem;
        border-radius: 18px;
        background: rgba(255,255,255,.72);
        border: 1px solid rgba(219, 227, 239, .9);
        text-align: right;
        min-width: 220px;
        box-shadow: var(--shadow-sm);
    }

    .portal-hero__badge strong {
        display: block;
        margin-bottom: .2rem;
        font-size: .82rem;
        color: var(--text-soft);
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .portal-hero__badge span {
        display: block;
        font-size: 1.1rem;
        font-weight: 800;
        color: var(--primary);
    }

    .portal-kpis {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .portal-main {
        display: grid;
        gap: 1rem;
        grid-template-columns: minmax(0, 1.25fr) minmax(320px, .95fr);
    }

    .portal-section-title {
        margin: 0;
        font-size: 1.04rem;
    }

    .portal-section-subtitle {
        margin: .25rem 0 0;
        color: var(--text-soft);
        font-size: .9rem;
    }

    .portal-list {
        display: grid;
        gap: .85rem;
    }

    .portal-list-item {
        padding: .95rem 1rem;
        border-radius: 18px;
        border: 1px solid rgba(219, 227, 239, .85);
        background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
    }

    .portal-list-item__top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: .8rem;
        margin-bottom: .35rem;
    }

    .portal-list-item__title {
        margin: 0;
        font-size: .96rem;
    }

    .portal-list-item__meta,
    .portal-list-item__text,
    .portal-list-item__small {
        margin: 0;
        color: var(--text-soft);
        line-height: 1.55;
        font-size: .9rem;
    }

    .portal-quick-actions {
        display: grid;
        gap: .85rem;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .portal-quick-action {
        border: 1px solid rgba(219, 227, 239, .9);
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        border-radius: 22px;
        padding: 1rem;
        text-align: left;
        transition: transform var(--transition), border-color var(--transition), box-shadow var(--transition);
        box-shadow: var(--shadow-sm);
    }

    .portal-quick-action:hover {
        transform: translateY(-2px);
        border-color: rgba(79, 70, 229, .16);
        box-shadow: var(--shadow-md);
    }

    .portal-quick-action__icon {
        width: 46px;
        height: 46px;
        border-radius: 16px;
        display: grid;
        place-items: center;
        margin-bottom: .8rem;
        background: linear-gradient(135deg, rgba(79, 70, 229, .14), rgba(14, 165, 164, .14));
        color: var(--primary);
        font-size: 1.1rem;
        font-weight: 800;
    }

    .portal-quick-action__title {
        margin: 0 0 .2rem;
        font-size: .98rem;
    }

    .portal-quick-action__text {
        margin: 0;
        color: var(--text-soft);
        font-size: .88rem;
        line-height: 1.45;
    }

    .portal-inline-note {
        padding: .9rem 1rem;
        border-radius: 18px;
        border: 1px dashed rgba(100, 116, 139, .26);
        background: linear-gradient(180deg, #fbfdff 0%, #f7faff 100%);
        color: var(--text-soft);
        line-height: 1.55;
    }

    @media (max-width: 1200px) {
        .portal-kpis {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .portal-main {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 860px) {
        .portal-hero__row {
            grid-template-columns: 1fr;
        }

        .portal-hero__badge {
            text-align: left;
            min-width: 0;
        }
    }

    @media (max-width: 620px) {
        .portal-kpis,
        .portal-quick-actions {
            grid-template-columns: 1fr;
        }

        .portal-list-item__top {
            flex-direction: column;
        }
    }
</style>

<div class="portal-shell">
    <?php require __DIR__ . '/_nav.php'; ?>

    <div class="portal-main">
        <div class="portal-home-grid">
            <section class="portal-hero">
                <div class="portal-hero__row">
                    <div>
                        <h2 class="portal-hero__title">Hola, <?= demo_e($portalUser['full_name'] ?? 'cliente') ?></h2>
                        <p class="portal-hero__text">
                            Aquí puedes revisar el estado general de tus pólizas, próximos pagos, siniestros abiertos y documentos disponibles en tu portal.
                        </p>
                    </div>

                    <div class="portal-hero__badge">
                        <strong>Cliente portal</strong>
                        <span><?= demo_e($client['document_type'] ?? 'Doc') ?> <?= demo_e($client['document_number'] ?? '—') ?></span>
                    </div>
                </div>
            </section>

            <section class="portal-kpis">
                <article class="card kpi-card">
                    <p class="kpi-card__label">Pólizas activas</p>
                    <h3 class="kpi-card__value"><?= demo_e((string)count($activePolicies)) ?></h3>
                    <p class="kpi-card__meta">Coberturas actualmente vigentes en tu cartera.</p>
                </article>

                <article class="card kpi-card">
                    <p class="kpi-card__label">Próximos pagos</p>
                    <h3 class="kpi-card__value"><?= demo_e((string)count($upcomingPayments)) ?></h3>
                    <p class="kpi-card__meta">Cuotas pendientes, vencidas o en revisión.</p>
                </article>

                <article class="card kpi-card">
                    <p class="kpi-card__label">Siniestros abiertos</p>
                    <h3 class="kpi-card__value"><?= demo_e((string)count($openClaims)) ?></h3>
                    <p class="kpi-card__meta">Casos aún en curso o pendientes de cierre.</p>
                </article>

                <article class="card kpi-card">
                    <p class="kpi-card__label">Documentos disponibles</p>
                    <h3 class="kpi-card__value"><?= demo_e((string)count($clientDocuments)) ?></h3>
                    <p class="kpi-card__meta">Archivos vinculados a tu perfil y pólizas.</p>
                </article>
            </section>

            <section class="card">
                <div class="card__header">
                    <div>
                        <h3 class="portal-section-title">Próximos vencimientos</h3>
                        <p class="portal-section-subtitle">Pagos que conviene revisar primero en tu portal.</p>
                    </div>
                    <?= demo_badge((string)count($upcomingPaymentsPreview) . ' registros', 'warning') ?>
                </div>

                <div class="portal-list">
                    <?php if (empty($upcomingPaymentsPreview)): ?>
                        <div class="empty-state">No tienes pagos próximos pendientes en este momento.</div>
                    <?php else: ?>
                        <?php foreach ($upcomingPaymentsPreview as $payment): ?>
                            <?php
                            $relatedPolicy = demo_find_by_id($clientPolicies, (string)($payment['policy_id'] ?? ''));
                            $statusText = ucfirst((string)($payment['status'] ?? '—'));
                            ?>
                            <article class="portal-list-item">
                                <div class="portal-list-item__top">
                                    <div>
                                        <h4 class="portal-list-item__title"><?= demo_e($relatedPolicy['policy_number'] ?? 'Póliza') ?> · Cuota #<?= demo_e((string)($payment['number'] ?? '—')) ?></h4>
                                        <p class="portal-list-item__meta"><?= demo_e(demo_insurance_type_name((string)($relatedPolicy['insurance_type_id'] ?? ''))) ?> · Vence <?= demo_e(demo_date((string)($payment['due_date'] ?? null))) ?></p>
                                    </div>
                                    <?= demo_badge($statusText, (string)($payment['status'] ?? '—')) ?>
                                </div>
                                <p class="portal-list-item__text">Monto estimado: <?= demo_e(demo_money((float)($payment['amount'] ?? 0), (string)($relatedPolicy['currency'] ?? 'S/'))) ?></p>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <section class="card">
                <div class="card__header">
                    <div>
                        <h3 class="portal-section-title">Mis pólizas recientes</h3>
                        <p class="portal-section-subtitle">Resumen rápido de tus pólizas más recientes dentro del portal.</p>
                    </div>
                    <?= demo_badge((string)count($recentPolicies) . ' pólizas', 'info') ?>
                </div>

                <div class="portal-list">
                    <?php if (empty($recentPolicies)): ?>
                        <div class="empty-state">Aún no tienes pólizas visibles en este portal.</div>
                    <?php else: ?>
                        <?php foreach ($recentPolicies as $policy): ?>
                            <article class="portal-list-item">
                                <div class="portal-list-item__top">
                                    <div>
                                        <h4 class="portal-list-item__title"><?= demo_e($policy['policy_number'] ?? 'Póliza') ?></h4>
                                        <p class="portal-list-item__meta">
                                            <?= demo_e(demo_insurance_type_name((string)($policy['insurance_type_id'] ?? ''))) ?>
                                            · <?= demo_e(demo_insurer_name((string)($policy['insurer_id'] ?? ''))) ?>
                                        </p>
                                    </div>
                                    <?= demo_badge(ucfirst((string)($policy['status'] ?? '—')), (string)($policy['status'] ?? '—')) ?>
                                </div>
                                <p class="portal-list-item__text">
                                    Vigencia: <?= demo_e(demo_date((string)($policy['start_date'] ?? null))) ?>
                                    al <?= demo_e(demo_date((string)($policy['end_date'] ?? null))) ?>
                                    · Prima: <?= demo_e(demo_money((float)($policy['premium'] ?? 0), (string)($policy['currency'] ?? 'S/'))) ?>
                                </p>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <div class="portal-home-grid">
            <section class="card">
                <div class="card__header">
                    <div>
                        <h3 class="portal-section-title">Accesos rápidos</h3>
                        <p class="portal-section-subtitle">Funciones frecuentes dentro del portal cliente.</p>
                    </div>
                </div>

                <div class="portal-quick-actions">
                    <button type="button" class="portal-quick-action" data-portal-action="polizas">
                        <span class="portal-quick-action__icon">🛡</span>
                        <h4 class="portal-quick-action__title">Ver mis pólizas</h4>
                        <p class="portal-quick-action__text">Consulta el detalle general de tus pólizas y vigencias.</p>
                    </button>

                    <button type="button" class="portal-quick-action" data-portal-action="comprobante">
                        <span class="portal-quick-action__icon">📄</span>
                        <h4 class="portal-quick-action__title">Subir comprobante</h4>
                        <p class="portal-quick-action__text">Inicia el flujo para compartir un comprobante de pago.</p>
                    </button>

                    <button type="button" class="portal-quick-action" data-portal-action="siniestro">
                        <span class="portal-quick-action__icon">⚠</span>
                        <h4 class="portal-quick-action__title">Reportar siniestro</h4>
                        <p class="portal-quick-action__text">Solicita acompañamiento para un nuevo caso o incidente.</p>
                    </button>

                    <button type="button" class="portal-quick-action" data-portal-action="perfil">
                        <span class="portal-quick-action__icon">👤</span>
                        <h4 class="portal-quick-action__title">Actualizar perfil</h4>
                        <p class="portal-quick-action__text">Revisa tus datos de contacto y la información visible en el portal.</p>
                    </button>
                </div>
            </section>

            <section class="card">
                <div class="card__header">
                    <div>
                        <h3 class="portal-section-title">Confianza y seguimiento</h3>
                        <p class="portal-section-subtitle">Estado general de tu espacio como cliente.</p>
                    </div>
                </div>

                <div class="portal-inline-note">
                    Tu portal está pensado para darte una vista simple, clara y segura de tus pólizas, pagos y solicitudes. Algunos accesos rápidos quedarán listos para conectarse a los siguientes módulos del cliente.
                </div>
            </section>
        </div>
    </div>
</div>

<script>
    (() => {
        const actionLinks = {
            polizas: <?= json_encode(demo_url('modules/portal/polizas.php'), JSON_UNESCAPED_UNICODE) ?>,
            comprobante: <?= json_encode(demo_url('modules/portal/pagos.php'), JSON_UNESCAPED_UNICODE) ?>,
            siniestro: <?= json_encode(demo_url('modules/portal/siniestros.php'), JSON_UNESCAPED_UNICODE) ?>,
            perfil: <?= json_encode(demo_url('modules/portal/perfil.php'), JSON_UNESCAPED_UNICODE) ?>,
        };

        document.querySelectorAll('[data-portal-action]').forEach((button) => {
            button.addEventListener('click', () => {
                const action = button.getAttribute('data-portal-action');
                const url = actionLinks[action] || null;

                if (url) {
                    window.location.href = url;
                    return;
                }

                DemoApp.toast({
                    title: 'Módulo en preparación',
                    message: 'La acción seleccionada estará disponible en el siguiente paso del portal cliente.',
                    type: 'info'
                });
            });
        });
    })();
</script>
<?php
$content = ob_get_clean();

demo_render_internal_layout(
    'Inicio del portal',
    $content,
    [
        'breadcrumb' => ['Portal', 'Inicio'],
        'subtitle' => 'Resumen general de pólizas, pagos y gestiones visibles para el cliente.',
    ]
);