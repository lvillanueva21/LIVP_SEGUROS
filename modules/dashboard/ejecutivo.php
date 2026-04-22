<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';

demo_require_roles(['ejecutivo']);

$currentUser = demo_current_user();
$executiveId = $currentUser['id'] ?? '';

$clients = demo_filter_clients_by_executive(demo_store('clients', []), $executiveId);
$policies = demo_filter_policies_by_executive(demo_store('policies', []), $executiveId);
$installments = demo_store('installments', []);
$claims = demo_store('claims', []);
$payments = demo_store('payments', []);
$activityLog = demo_store('activity_log', []);

$clientMap = [];
foreach ($clients as $client) {
    $clientMap[$client['id']] = $client;
}

$policyMap = [];
foreach ($policies as $policy) {
    $policyMap[$policy['id']] = $policy;
}

$policyIds = array_column($policies, 'id');
$clientIds = array_column($clients, 'id');

$activePolicies = array_values(array_filter($policies, fn($item) => ($item['status'] ?? '') === 'activa'));

$portfolioInstallments = array_values(array_filter($installments, function ($item) use ($policyIds) {
    return in_array(($item['policy_id'] ?? ''), $policyIds, true);
}));

$today = strtotime(date('Y-m-d'));
$nextWindow = strtotime('+10 days', $today);

$upcomingInstallments = array_values(array_filter($portfolioInstallments, function ($item) use ($today, $nextWindow) {
    $status = strtolower((string)($item['status'] ?? ''));
    $due = strtotime((string)($item['due_date'] ?? ''));

    if (!$due) {
        return false;
    }

    return in_array($status, ['pendiente', 'vencida', 'en revisión'], true)
        && $due >= strtotime('-10 days', $today)
        && $due <= $nextWindow;
}));

usort($upcomingInstallments, fn($a, $b) => strtotime((string)($a['due_date'] ?? '')) <=> strtotime((string)($b['due_date'] ?? '')));
$upcomingInstallments = array_slice($upcomingInstallments, 0, 8);

$openClaims = array_values(array_filter($claims, function ($item) use ($executiveId, $policyIds) {
    return (
        (($item['assigned_user_id'] ?? '') === $executiveId || in_array(($item['policy_id'] ?? ''), $policyIds, true))
        && ($item['status'] ?? '') !== 'cerrado'
    );
}));

$recentEvents = [];

foreach ($activityLog as $activity) {
    if (($activity['user_id'] ?? '') === $executiveId) {
        $recentEvents[] = [
            'title' => $activity['title'] ?? 'Actividad',
            'description' => $activity['description'] ?? '',
            'created_at' => $activity['created_at'] ?? date('Y-m-d H:i:s'),
            'type' => 'actividad',
        ];
    }
}

foreach ($policies as $policy) {
    $recentEvents[] = [
        'title' => 'Póliza en cartera',
        'description' => ($policy['policy_number'] ?? '—') . ' · ' . demo_client_name((string)($policy['client_id'] ?? '')),
        'created_at' => $policy['created_at'] ?? date('Y-m-d H:i:s'),
        'type' => 'poliza',
    ];
}

foreach ($openClaims as $claim) {
    $recentEvents[] = [
        'title' => 'Seguimiento de siniestro',
        'description' => ($claim['code'] ?? '—') . ' · ' . ($claim['type'] ?? 'Caso') . ' · ' . ($claim['status'] ?? '—'),
        'created_at' => !empty($claim['date']) ? ($claim['date'] . ' 10:00:00') : date('Y-m-d H:i:s'),
        'type' => 'siniestro',
    ];
}

foreach ($payments as $payment) {
    $policyId = $payment['policy_id'] ?? '';
    if (!in_array($policyId, $policyIds, true)) {
        continue;
    }

    $recentEvents[] = [
        'title' => 'Pago registrado en cartera',
        'description' => demo_policy_number((string)$policyId) . ' · ' . demo_money((float)($payment['amount'] ?? 0)),
        'created_at' => !empty($payment['date']) ? ($payment['date'] . ' 09:00:00') : date('Y-m-d H:i:s'),
        'type' => 'pago',
    ];
}

usort($recentEvents, fn($a, $b) => strtotime((string)($b['created_at'] ?? '')) <=> strtotime((string)($a['created_at'] ?? '')));
$recentEvents = array_slice($recentEvents, 0, 6);

$attendedRatio = max(count($clients), 1);
$clientsWithPolicy = count(array_filter($clients, fn($client) => count(array_filter($activePolicies, fn($policy) => ($policy['client_id'] ?? '') === ($client['id'] ?? ''))) > 0));
$coveragePercent = (int) round(($clientsWithPolicy / $attendedRatio) * 100);

$totalPendingAmount = array_reduce($portfolioInstallments, function ($carry, $item) {
    $status = strtolower((string)($item['status'] ?? ''));
    if (in_array($status, ['pendiente', 'vencida', 'en revisión'], true)) {
        return $carry + (float)($item['amount'] ?? 0);
    }
    return $carry;
}, 0.0);

ob_start();
?>
<style>
    .exec-dashboard-grid {
        display: grid;
        gap: 1rem;
    }

    .exec-kpis {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .exec-main {
        display: grid;
        gap: 1rem;
        grid-template-columns: minmax(0, 1.45fr) minmax(320px, .95fr);
    }

    .exec-secondary {
        display: grid;
        gap: 1rem;
        grid-template-columns: minmax(0, 1.1fr) minmax(0, .9fr);
    }

    .exec-quick-actions {
        display: grid;
        gap: .85rem;
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .exec-quick-action {
        border: 1px solid rgba(219, 227, 239, .85);
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        border-radius: 20px;
        padding: 1rem;
        text-align: left;
        transition: transform var(--transition), box-shadow var(--transition), border-color var(--transition);
        box-shadow: var(--shadow-sm);
    }

    .exec-quick-action:hover {
        transform: translateY(-2px);
        border-color: rgba(79, 70, 229, .18);
        box-shadow: var(--shadow-md);
    }

    .exec-quick-action__icon {
        width: 48px;
        height: 48px;
        border-radius: 16px;
        display: grid;
        place-items: center;
        font-size: 1.15rem;
        margin-bottom: .8rem;
        background: linear-gradient(135deg, rgba(79, 70, 229, .14), rgba(14, 165, 164, .14));
        color: var(--primary);
    }

    .exec-quick-action__title {
        margin: 0 0 .25rem;
        font-size: .98rem;
    }

    .exec-quick-action__text {
        margin: 0;
        color: var(--text-soft);
        font-size: .88rem;
        line-height: 1.45;
    }

    .exec-section-title {
        margin: 0;
        font-size: 1.02rem;
    }

    .exec-section-subtitle {
        margin: .25rem 0 0;
        color: var(--text-soft);
        font-size: .9rem;
    }

    .exec-activity-list {
        display: grid;
        gap: .8rem;
    }

    .exec-activity-item {
        padding: .95rem 1rem;
        border-radius: 18px;
        border: 1px solid rgba(219, 227, 239, .85);
        background: linear-gradient(180deg, #ffffff 0%, #fafcff 100%);
    }

    .exec-activity-item__top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        margin-bottom: .35rem;
    }

    .exec-activity-item__title {
        margin: 0;
        font-size: .95rem;
    }

    .exec-activity-item__date {
        color: var(--text-soft);
        font-size: .8rem;
        white-space: nowrap;
    }

    .exec-activity-item__description {
        margin: 0;
        color: var(--text-soft);
        line-height: 1.5;
        font-size: .9rem;
    }

    .exec-goal-box {
        display: grid;
        gap: 1rem;
    }

    .exec-goal-highlight {
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 1rem;
        align-items: center;
        padding: 1rem;
        border-radius: 20px;
        background: linear-gradient(135deg, rgba(79, 70, 229, .08), rgba(14, 165, 164, .08));
        border: 1px solid rgba(79, 70, 229, .1);
    }

    .exec-goal-highlight__icon {
        width: 58px;
        height: 58px;
        border-radius: 18px;
        display: grid;
        place-items: center;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: #fff;
        font-size: 1.3rem;
        box-shadow: var(--shadow-sm);
    }

    .exec-goal-highlight__value {
        margin: .15rem 0 0;
        font-size: 1.8rem;
        font-weight: 800;
    }

    .exec-goal-stats {
        display: grid;
        gap: .8rem;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .exec-goal-stat {
        padding: .95rem 1rem;
        border-radius: 18px;
        border: 1px solid rgba(219, 227, 239, .85);
        background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
    }

    .exec-goal-stat strong {
        display: block;
        margin-bottom: .3rem;
        color: var(--text-soft);
        font-size: .82rem;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .exec-goal-stat span {
        display: block;
        font-size: 1.05rem;
        font-weight: 700;
        overflow-wrap: anywhere;
    }

    .exec-modal-note {
        margin: 0 0 1rem;
        color: var(--text-soft);
        line-height: 1.5;
    }

    @media (max-width: 1200px) {
        .exec-kpis,
        .exec-quick-actions {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .exec-main,
        .exec-secondary {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 720px) {
        .exec-kpis,
        .exec-quick-actions,
        .exec-goal-stats {
            grid-template-columns: 1fr;
        }

        .exec-activity-item__top {
            flex-direction: column;
            align-items: start;
        }

        .exec-goal-highlight {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="exec-dashboard-grid">
    <section class="exec-kpis">
        <article class="card kpi-card">
            <p class="kpi-card__label">Mis clientes</p>
            <h3 class="kpi-card__value"><?= demo_e((string)count($clients)) ?></h3>
            <p class="kpi-card__meta">Clientes asignados directamente a tu cartera comercial.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Mis pólizas activas</p>
            <h3 class="kpi-card__value"><?= demo_e((string)count($activePolicies)) ?></h3>
            <p class="kpi-card__meta">Coberturas vigentes que hoy requieren seguimiento.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Mis cuotas por vencer</p>
            <h3 class="kpi-card__value"><?= demo_e((string)count($upcomingInstallments)) ?></h3>
            <p class="kpi-card__meta">Pendientes y alertas cercanas dentro de tu cartera.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Mis siniestros abiertos</p>
            <h3 class="kpi-card__value"><?= demo_e((string)count($openClaims)) ?></h3>
            <p class="kpi-card__meta">Casos que aún demandan seguimiento operativo.</p>
        </article>
    </section>

    <section class="card">
        <div class="card__header">
            <div>
                <h2 class="exec-section-title">Accesos rápidos</h2>
                <p class="exec-section-subtitle">Atajos para tareas frecuentes sobre tu propia cartera.</p>
            </div>
            <?= demo_badge('Mi cartera', 'info') ?>
        </div>

        <div class="exec-quick-actions">
            <button type="button" class="exec-quick-action" data-exec-action="cliente">
                <span class="exec-quick-action__icon">👤</span>
                <h3 class="exec-quick-action__title">Nuevo cliente</h3>
                <p class="exec-quick-action__text">Registrar una nueva oportunidad comercial dentro de tu cartera.</p>
            </button>

            <button type="button" class="exec-quick-action" data-exec-action="poliza">
                <span class="exec-quick-action__icon">🛡</span>
                <h3 class="exec-quick-action__title">Nueva póliza</h3>
                <p class="exec-quick-action__text">Simular alta de póliza con vigencia, prima y producto.</p>
            </button>

            <button type="button" class="exec-quick-action" data-exec-action="pago">
                <span class="exec-quick-action__icon">💳</span>
                <h3 class="exec-quick-action__title">Registrar pago</h3>
                <p class="exec-quick-action__text">Confirmar una cobranza y dejar trazabilidad comercial.</p>
            </button>

            <button type="button" class="exec-quick-action" data-exec-action="siniestro">
                <span class="exec-quick-action__icon">⚠</span>
                <h3 class="exec-quick-action__title">Nuevo siniestro</h3>
                <p class="exec-quick-action__text">Abrir un caso demo para acompañamiento y seguimiento.</p>
            </button>
        </div>
    </section>

    <section class="exec-main">
        <article class="card">
            <div class="card__header">
                <div>
                    <h2 class="exec-section-title">Próximos vencimientos de mi cartera</h2>
                    <p class="exec-section-subtitle">Cuotas prioritarias dentro del rango operativo inmediato.</p>
                </div>
                <?= demo_badge((string)count($upcomingInstallments) . ' registros', 'warning') ?>
            </div>

            <?php if (empty($upcomingInstallments)): ?>
                <div class="empty-state">No hay vencimientos próximos relevantes en tu cartera.</div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Póliza</th>
                                <th>Cuota</th>
                                <th>Vencimiento</th>
                                <th>Monto</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcomingInstallments as $item): ?>
                                <?php $policy = $policyMap[$item['policy_id']] ?? null; ?>
                                <tr>
                                    <td><?= demo_e($policy ? demo_client_name((string)($policy['client_id'] ?? '')) : 'Cliente') ?></td>
                                    <td><?= demo_e($policy['policy_number'] ?? '—') ?></td>
                                    <td>#<?= demo_e((string)($item['number'] ?? '—')) ?></td>
                                    <td><?= demo_e(demo_date((string)($item['due_date'] ?? null))) ?></td>
                                    <td><?= demo_e(demo_money((float)($item['amount'] ?? 0))) ?></td>
                                    <td><?= demo_badge((string)($item['status'] ?? '—'), (string)($item['status'] ?? '—')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </article>

        <aside class="card">
            <div class="card__header">
                <div>
                    <h2 class="exec-section-title">Actividad reciente</h2>
                    <p class="exec-section-subtitle">Movimientos relacionados con tu cartera y gestión comercial.</p>
                </div>
            </div>

            <div class="exec-activity-list">
                <?php if (empty($recentEvents)): ?>
                    <div class="empty-state">Aún no hay actividad reciente registrada para tu cartera.</div>
                <?php else: ?>
                    <?php foreach ($recentEvents as $event): ?>
                        <article class="exec-activity-item">
                            <div class="exec-activity-item__top">
                                <h3 class="exec-activity-item__title"><?= demo_e($event['title'] ?? 'Actividad') ?></h3>
                                <span class="exec-activity-item__date"><?= demo_e(demo_date((string)($event['created_at'] ?? null), 'd/m/Y H:i')) ?></span>
                            </div>
                            <p class="exec-activity-item__description"><?= demo_e($event['description'] ?? '') ?></p>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </aside>
    </section>

    <section class="exec-secondary">
        <article class="card">
            <div class="card__header">
                <div>
                    <h2 class="exec-section-title">Mi cartera atendida</h2>
                    <p class="exec-section-subtitle">Lectura rápida del avance comercial sobre tus clientes asignados.</p>
                </div>
            </div>

            <div class="exec-goal-box">
                <div class="exec-goal-highlight">
                    <div class="exec-goal-highlight__icon">%</div>
                    <div>
                        <span class="muted">Cobertura comercial de cartera</span>
                        <p class="exec-goal-highlight__value"><?= demo_e((string)$coveragePercent) ?>%</p>
                        <p class="muted mt-1">Clientes de tu cartera que ya cuentan con al menos una póliza activa.</p>
                    </div>
                </div>

                <div class="exec-goal-stats">
                    <div class="exec-goal-stat">
                        <strong>Clientes con póliza activa</strong>
                        <span><?= demo_e((string)$clientsWithPolicy) ?></span>
                    </div>

                    <div class="exec-goal-stat">
                        <strong>Cuotas por regularizar</strong>
                        <span><?= demo_e(demo_money($totalPendingAmount)) ?></span>
                    </div>

                    <div class="exec-goal-stat">
                        <strong>Casos abiertos</strong>
                        <span><?= demo_e((string)count($openClaims)) ?></span>
                    </div>

                    <div class="exec-goal-stat">
                        <strong>Relación clientes / pólizas</strong>
                        <span><?= demo_e((string)count($clients)) ?> / <?= demo_e((string)count($policies)) ?></span>
                    </div>
                </div>
            </div>
        </article>

        <aside class="card">
            <div class="card__header">
                <div>
                    <h2 class="exec-section-title">Enfoque del día</h2>
                    <p class="exec-section-subtitle">Sugerencia operativa basada en la cartera actual.</p>
                </div>
            </div>

            <div class="panel">
                <strong>Prioridad comercial</strong>
                <p class="muted mt-1">
                    Revisa primero las cuotas próximas y luego los siniestros abiertos. Esa combinación suele impactar más rápido en seguimiento y percepción del cliente.
                </p>
                <div class="mt-2">
                    <?= demo_badge('Seguimiento recomendado', 'info') ?>
                </div>
            </div>
        </aside>
    </section>
</div>

<div class="modal" id="executive-action-modal" hidden>
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__dialog modal__dialog--sm">
        <div class="modal__header">
            <h3 id="executive-action-title">Acción rápida</h3>
            <button type="button" class="icon-btn" data-modal-close aria-label="Cerrar">✕</button>
        </div>
        <div class="modal__body">
            <p class="exec-modal-note" id="executive-action-note">
                Completa la acción demo. No se guardarán cambios permanentes fuera de la sesión actual.
            </p>

            <form id="executive-action-form" class="form-grid form-grid--2">
                <div>
                    <label class="form-label" for="exec-action-name">Referencia</label>
                    <input class="input" id="exec-action-name" name="name" type="text" placeholder="Ingresa un valor demo">
                </div>

                <div>
                    <label class="form-label" for="exec-action-date">Fecha</label>
                    <input class="input" id="exec-action-date" name="date" type="date" value="<?= demo_e(date('Y-m-d')) ?>">
                </div>

                <div class="form-grid" style="grid-column: 1 / -1;">
                    <label class="form-label" for="exec-action-description">Detalle</label>
                    <textarea class="textarea" id="exec-action-description" name="description" placeholder="Describe la acción comercial demo"></textarea>
                </div>
            </form>
        </div>
        <div class="modal__footer">
            <button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button>
            <button type="button" class="btn btn-primary" id="executive-action-submit">Guardar demo</button>
        </div>
    </div>
</div>

<script>
    (() => {
        const actionMap = {
            cliente: {
                title: 'Nuevo cliente',
                note: 'Simula el registro de un cliente nuevo dentro de tu cartera.',
                placeholder: 'Ej. Natalia Rojas'
            },
            poliza: {
                title: 'Nueva póliza',
                note: 'Simula el alta comercial de una nueva póliza para uno de tus clientes.',
                placeholder: 'Ej. AU-2026-000511'
            },
            pago: {
                title: 'Registrar pago',
                note: 'Simula la confirmación de un pago para una cuota de tu cartera.',
                placeholder: 'Ej. Cuota 2 / Lucía Torres'
            },
            siniestro: {
                title: 'Nuevo siniestro',
                note: 'Simula la apertura de un caso de siniestro para acompañamiento al cliente.',
                placeholder: 'Ej. Robo parcial / SIN-2026-0041'
            }
        };

        let activeActionKey = null;

        const titleNode = document.getElementById('executive-action-title');
        const noteNode = document.getElementById('executive-action-note');
        const submitButton = document.getElementById('executive-action-submit');
        const form = document.getElementById('executive-action-form');
        const nameInput = document.getElementById('exec-action-name');
        const descriptionInput = document.getElementById('exec-action-description');
        const dateInput = document.getElementById('exec-action-date');

        document.querySelectorAll('[data-exec-action]').forEach((button) => {
            button.addEventListener('click', () => {
                activeActionKey = button.getAttribute('data-exec-action');
                const config = actionMap[activeActionKey] || actionMap.cliente;

                titleNode.textContent = config.title;
                noteNode.textContent = config.note;
                nameInput.placeholder = config.placeholder;
                nameInput.value = '';
                descriptionInput.value = '';
                dateInput.value = '<?= demo_e(date('Y-m-d')) ?>';

                DemoApp.openModal('executive-action-modal');
            });
        });

        submitButton.addEventListener('click', () => {
            const config = actionMap[activeActionKey] || actionMap.cliente;
            const ref = (nameInput.value || config.title).trim();

            DemoApp.closeModal('executive-action-modal');
            DemoApp.toast({
                title: config.title,
                message: `${ref} fue registrado como acción demo de tu cartera.`,
                type: 'success'
            });

            form.reset();
            dateInput.value = '<?= demo_e(date('Y-m-d')) ?>';
        });
    })();
</script>
<?php
$content = ob_get_clean();

demo_render_internal_layout(
    'Mi dashboard comercial',
    $content,
    [
        'breadcrumb' => ['Inicio', 'Dashboard'],
        'subtitle' => 'Seguimiento rápido de clientes, pólizas, cobranzas y casos abiertos dentro de tu cartera.',
    ]
);