<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';

demo_require_roles(['gerente', 'superadmin']);

$store = demo_store();

$users = $store['users'] ?? [];
$clients = $store['clients'] ?? [];
$policies = $store['policies'] ?? [];
$installments = $store['installments'] ?? [];
$claims = $store['claims'] ?? [];
$documents = $store['documents'] ?? [];
$activityLog = $store['activity_log'] ?? [];

$activeClients = array_values(array_filter($clients, fn ($item) => ($item['status'] ?? '') === 'activo'));
$activePolicies = array_values(array_filter($policies, fn ($item) => ($item['status'] ?? '') === 'activa'));
$openClaims = array_values(array_filter($claims, fn ($item) => ($item['status'] ?? '') !== 'cerrado'));
$activeExecutives = array_values(array_filter($users, fn ($item) => ($item['role'] ?? '') === 'ejecutivo' && ($item['status'] ?? '') === 'activo'));
$loadedDocuments = count($documents);

$today = strtotime(date('Y-m-d'));
$threshold = strtotime('+10 days', $today);

$upcomingInstallments = array_values(array_filter($installments, function ($item) use ($today, $threshold) {
    $status = strtolower((string)($item['status'] ?? ''));
    $dueDate = strtotime((string)($item['due_date'] ?? ''));

    if (!$dueDate) {
        return false;
    }

    return in_array($status, ['pendiente', 'en revisión', 'vencida'], true) && $dueDate >= strtotime('-15 days', $today) && $dueDate <= $threshold;
}));

usort($upcomingInstallments, fn ($a, $b) => strtotime($a['due_date'] ?? '') <=> strtotime($b['due_date'] ?? ''));

$recentActivity = $activityLog;
usort($recentActivity, fn ($a, $b) => strtotime($b['created_at'] ?? '') <=> strtotime($a['created_at'] ?? ''));
$recentActivity = array_slice($recentActivity, 0, 6);

$executivePortfolio = [];
foreach ($activeExecutives as $executive) {
    $executiveId = $executive['id'];

    $execClients = demo_filter_clients_by_executive($clients, $executiveId);
    $execPolicies = demo_filter_policies_by_executive($policies, $executiveId);

    $policyIds = array_column($execPolicies, 'id');
    $openExecClaims = array_values(array_filter($claims, fn ($claim) => ($claim['assigned_user_id'] ?? null) === $executiveId && ($claim['status'] ?? '') !== 'cerrado'));
    $pendingExecInstallments = array_values(array_filter($installments, function ($installment) use ($policyIds) {
        return in_array($installment['policy_id'] ?? '', $policyIds, true)
            && in_array(strtolower((string)($installment['status'] ?? '')), ['pendiente', 'vencida', 'en revisión'], true);
    }));

    $executivePortfolio[] = [
        'id' => $executiveId,
        'name' => $executive['full_name'] ?? 'Ejecutivo',
        'avatar' => $executive['avatar'] ?? demo_avatar_initials($executive['full_name'] ?? ''),
        'clients' => count($execClients),
        'policies' => count($execPolicies),
        'pending_installments' => count($pendingExecInstallments),
        'open_claims' => count($openExecClaims),
    ];
}

$upcomingInstallmentsRows = [];
foreach ($upcomingInstallments as $item) {
    $policy = demo_find_by_id($policies, $item['policy_id'] ?? '');
    $clientName = demo_client_name($policy['client_id'] ?? '');
    $upcomingInstallmentsRows[] = [
        'client_name' => $clientName,
        'policy_number' => $policy['policy_number'] ?? '—',
        'due_date' => $item['due_date'] ?? null,
        'amount' => $item['amount'] ?? 0,
        'status' => $item['status'] ?? 'pendiente',
        'installment_number' => $item['number'] ?? '—',
    ];
}
$upcomingInstallmentsRows = array_slice($upcomingInstallmentsRows, 0, 8);

$totalPremium = array_reduce($policies, fn ($carry, $policy) => $carry + (float)($policy['premium'] ?? 0), 0.0);

ob_start();
?>
<style>
    .dashboard-grid {
        display: grid;
        gap: 1rem;
    }

    .dashboard-kpis {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(6, minmax(0, 1fr));
    }

    .dashboard-main {
        display: grid;
        gap: 1rem;
        grid-template-columns: minmax(0, 1.6fr) minmax(320px, .9fr);
    }

    .dashboard-secondary {
        display: grid;
        gap: 1rem;
        grid-template-columns: minmax(0, 1.1fr) minmax(0, .9fr);
    }

    .quick-actions {
        display: grid;
        gap: .85rem;
        grid-template-columns: repeat(5, minmax(0, 1fr));
    }

    .quick-action {
        border: 1px solid rgba(219, 227, 239, .85);
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        border-radius: 20px;
        padding: 1rem;
        text-align: left;
        transition: transform var(--transition), box-shadow var(--transition), border-color var(--transition);
        box-shadow: var(--shadow-sm);
    }

    .quick-action:hover {
        transform: translateY(-2px);
        border-color: rgba(79, 70, 229, .18);
        box-shadow: var(--shadow-md);
    }

    .quick-action__icon {
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

    .quick-action__title {
        margin: 0 0 .25rem;
        font-size: .98rem;
    }

    .quick-action__text {
        margin: 0;
        color: var(--text-soft);
        font-size: .88rem;
        line-height: 1.45;
    }

    .dashboard-section-title {
        margin: 0;
        font-size: 1.02rem;
    }

    .dashboard-section-subtitle {
        margin: .25rem 0 0;
        color: var(--text-soft);
        font-size: .9rem;
    }

    .activity-list {
        display: grid;
        gap: .8rem;
    }

    .activity-item {
        padding: .95rem 1rem;
        border-radius: 18px;
        border: 1px solid rgba(219, 227, 239, .85);
        background: linear-gradient(180deg, #ffffff 0%, #fafcff 100%);
    }

    .activity-item__top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        margin-bottom: .35rem;
    }

    .activity-item__title {
        margin: 0;
        font-size: .95rem;
    }

    .activity-item__date {
        color: var(--text-soft);
        font-size: .8rem;
        white-space: nowrap;
    }

    .activity-item__description {
        margin: 0;
        color: var(--text-soft);
        line-height: 1.5;
        font-size: .9rem;
    }

    .portfolio-list {
        display: grid;
        gap: .8rem;
    }

    .portfolio-item {
        display: grid;
        grid-template-columns: auto 1fr auto;
        gap: .9rem;
        align-items: center;
        padding: .95rem 1rem;
        border-radius: 18px;
        border: 1px solid rgba(219, 227, 239, .85);
        background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
    }

    .portfolio-item__meta strong {
        display: block;
        margin-bottom: .15rem;
        font-size: .95rem;
    }

    .portfolio-item__meta span {
        color: var(--text-soft);
        font-size: .86rem;
    }

    .portfolio-item__stats {
        display: flex;
        flex-wrap: wrap;
        gap: .4rem;
        justify-content: flex-end;
    }

    .metric-chip {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        min-height: 30px;
        padding: .3rem .65rem;
        border-radius: 999px;
        background: rgba(79, 70, 229, .08);
        color: var(--primary);
        font-size: .8rem;
        font-weight: 700;
    }

    .dashboard-highlight {
        display: grid;
        gap: 1rem;
        grid-template-columns: minmax(0, 1fr) minmax(0, .9fr);
    }

    .premium-highlight {
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 1rem;
        align-items: center;
        padding: 1rem;
        border-radius: 20px;
        background: linear-gradient(135deg, rgba(79, 70, 229, .08), rgba(14, 165, 164, .08));
        border: 1px solid rgba(79, 70, 229, .1);
    }

    .premium-highlight__icon {
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

    .premium-highlight__value {
        margin: .15rem 0 0;
        font-size: 1.8rem;
        font-weight: 800;
    }

    .mini-note {
        padding: 1rem;
        border-radius: 20px;
        border: 1px dashed rgba(100, 116, 139, .28);
        background: linear-gradient(180deg, #fbfdff 0%, #f7faff 100%);
    }

    .modal-form-note {
        margin: 0 0 1rem;
        color: var(--text-soft);
        line-height: 1.5;
    }

    @media (max-width: 1280px) {
        .dashboard-kpis {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .quick-actions {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 980px) {
        .dashboard-main,
        .dashboard-secondary,
        .dashboard-highlight {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 720px) {
        .dashboard-kpis,
        .quick-actions {
            grid-template-columns: 1fr;
        }

        .portfolio-item {
            grid-template-columns: 1fr;
            align-items: start;
        }

        .portfolio-item__stats {
            justify-content: start;
        }

        .activity-item__top {
            flex-direction: column;
            align-items: start;
        }
    }
</style>

<div class="dashboard-grid">
    <section class="dashboard-kpis">
        <article class="card kpi-card">
            <p class="kpi-card__label">Clientes activos</p>
            <h3 class="kpi-card__value"><?= demo_e((string)count($activeClients)) ?></h3>
            <p class="kpi-card__meta">Base comercial vigente y con seguimiento activo.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Pólizas activas</p>
            <h3 class="kpi-card__value"><?= demo_e((string)count($activePolicies)) ?></h3>
            <p class="kpi-card__meta">Coberturas vigentes dentro de la cartera actual.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Cuotas por vencer</p>
            <h3 class="kpi-card__value"><?= demo_e((string)count($upcomingInstallmentsRows)) ?></h3>
            <p class="kpi-card__meta">Incluye pendientes, vencidas y en revisión próximas.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Siniestros abiertos</p>
            <h3 class="kpi-card__value"><?= demo_e((string)count($openClaims)) ?></h3>
            <p class="kpi-card__meta">Casos que aún requieren atención operativa.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Ejecutivos activos</p>
            <h3 class="kpi-card__value"><?= demo_e((string)count($activeExecutives)) ?></h3>
            <p class="kpi-card__meta">Usuarios con cartera y actividad comercial asignada.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Documentos cargados</p>
            <h3 class="kpi-card__value"><?= demo_e((string)$loadedDocuments) ?></h3>
            <p class="kpi-card__meta">Archivos disponibles entre clientes, pólizas y siniestros.</p>
        </article>
    </section>

    <section class="card">
        <div class="card__header">
            <div>
                <h2 class="dashboard-section-title">Accesos rápidos</h2>
                <p class="dashboard-section-subtitle">Acciones frecuentes para gerencia y operación diaria.</p>
            </div>
            <?= demo_badge('Demo funcional', 'info') ?>
        </div>

        <div class="quick-actions">
            <button type="button" class="quick-action" data-dashboard-action="cliente">
                <span class="quick-action__icon">👤</span>
                <h3 class="quick-action__title">Nuevo cliente</h3>
                <p class="quick-action__text">Abrir alta rápida y registrar un nuevo contacto comercial.</p>
            </button>

            <button type="button" class="quick-action" data-dashboard-action="poliza">
                <span class="quick-action__icon">🛡</span>
                <h3 class="quick-action__title">Nueva póliza</h3>
                <p class="quick-action__text">Crear una póliza manual con fechas, aseguradora y prima.</p>
            </button>

            <button type="button" class="quick-action" data-dashboard-action="pago">
                <span class="quick-action__icon">💳</span>
                <h3 class="quick-action__title">Registrar pago</h3>
                <p class="quick-action__text">Confirmar una cuota y dejar trazabilidad visual en el dashboard.</p>
            </button>

            <button type="button" class="quick-action" data-dashboard-action="siniestro">
                <span class="quick-action__icon">⚠</span>
                <h3 class="quick-action__title">Nuevo siniestro</h3>
                <p class="quick-action__text">Simular la apertura de un caso con póliza y descripción breve.</p>
            </button>

            <button type="button" class="quick-action" data-dashboard-action="ejecutivo">
                <span class="quick-action__icon">👥</span>
                <h3 class="quick-action__title">Crear ejecutivo</h3>
                <p class="quick-action__text">Agregar un usuario comercial con documento demo y acceso inicial.</p>
            </button>
        </div>
    </section>

    <section class="dashboard-highlight">
        <article class="card">
            <div class="premium-highlight">
                <div class="premium-highlight__icon">S/</div>
                <div>
                    <span class="muted">Prima total estimada de cartera</span>
                    <p class="premium-highlight__value"><?= demo_e(demo_money($totalPremium)) ?></p>
                    <p class="muted mt-1">Resumen financiero demo calculado desde las pólizas cargadas actualmente.</p>
                </div>
            </div>
        </article>

        <aside class="mini-note">
            <strong>Lectura rápida del día</strong>
            <p class="muted mt-1">
                La cartera muestra pólizas activas estables, una concentración operativa en un ejecutivo y algunos eventos clave en cobranzas y siniestros que conviene priorizar.
            </p>
            <div class="mt-2">
                <?= demo_badge('Seguimiento prioritario', 'warning') ?>
            </div>
        </aside>
    </section>

    <section class="dashboard-main">
        <article class="card">
            <div class="card__header">
                <div>
                    <h2 class="dashboard-section-title">Próximos vencimientos</h2>
                    <p class="dashboard-section-subtitle">Cuotas relevantes dentro del rango operativo inmediato.</p>
                </div>
                <?= demo_badge((string)count($upcomingInstallmentsRows) . ' registros', 'info') ?>
            </div>

            <?php if (empty($upcomingInstallmentsRows)): ?>
                <div class="empty-state">No hay vencimientos próximos en el rango definido.</div>
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
                        <tbody id="dashboard-upcoming-table">
                            <?php foreach ($upcomingInstallmentsRows as $row): ?>
                                <tr>
                                    <td><?= demo_e($row['client_name']) ?></td>
                                    <td><?= demo_e($row['policy_number']) ?></td>
                                    <td>#<?= demo_e((string)$row['installment_number']) ?></td>
                                    <td><?= demo_e(demo_date($row['due_date'])) ?></td>
                                    <td><?= demo_e(demo_money((float)$row['amount'])) ?></td>
                                    <td><?= demo_badge((string)$row['status'], (string)$row['status']) ?></td>
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
                    <h2 class="dashboard-section-title">Actividad reciente</h2>
                    <p class="dashboard-section-subtitle">Eventos demo registrados en la operación diaria.</p>
                </div>
            </div>

            <div class="activity-list" id="dashboard-activity-list">
                <?php if (empty($recentActivity)): ?>
                    <div class="empty-state">Aún no hay actividad registrada.</div>
                <?php else: ?>
                    <?php foreach ($recentActivity as $activity): ?>
                        <article class="activity-item">
                            <div class="activity-item__top">
                                <h3 class="activity-item__title"><?= demo_e($activity['title'] ?? 'Actividad') ?></h3>
                                <span class="activity-item__date"><?= demo_e(demo_date($activity['created_at'] ?? null, 'd/m/Y H:i')) ?></span>
                            </div>
                            <p class="activity-item__description"><?= demo_e($activity['description'] ?? '') ?></p>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </aside>
    </section>

    <section class="dashboard-secondary">
        <article class="card">
            <div class="card__header">
                <div>
                    <h2 class="dashboard-section-title">Cartera por ejecutivo</h2>
                    <p class="dashboard-section-subtitle">Vista compacta de carga comercial y operativa por responsable.</p>
                </div>
            </div>

            <div class="portfolio-list">
                <?php if (empty($executivePortfolio)): ?>
                    <div class="empty-state">No hay ejecutivos activos en el store demo.</div>
                <?php else: ?>
                    <?php foreach ($executivePortfolio as $item): ?>
                        <article class="portfolio-item">
                            <div class="avatar"><?= demo_e($item['avatar']) ?></div>

                            <div class="portfolio-item__meta">
                                <strong><?= demo_e($item['name']) ?></strong>
                                <span>Cartera operativa asignada en la sesión actual.</span>
                            </div>

                            <div class="portfolio-item__stats">
                                <span class="metric-chip"><?= demo_e((string)$item['clients']) ?> clientes</span>
                                <span class="metric-chip"><?= demo_e((string)$item['policies']) ?> pólizas</span>
                                <span class="metric-chip"><?= demo_e((string)$item['pending_installments']) ?> cuotas</span>
                                <span class="metric-chip"><?= demo_e((string)$item['open_claims']) ?> siniestros</span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </article>

        <aside class="card">
            <div class="card__header">
                <div>
                    <h2 class="dashboard-section-title">Indicadores rápidos</h2>
                    <p class="dashboard-section-subtitle">Resumen ejecutivo para lectura inmediata.</p>
                </div>
            </div>

            <div class="grid grid--2">
                <div class="panel">
                    <strong>Clientes con acceso portal</strong>
                    <p class="mt-1"><?= demo_e((string)count(array_filter($clients, fn ($item) => !empty($item['has_portal_access'])))) ?></p>
                </div>

                <div class="panel">
                    <strong>Pólizas pendientes</strong>
                    <p class="mt-1"><?= demo_e((string)count(array_filter($policies, fn ($item) => ($item['status'] ?? '') === 'pendiente'))) ?></p>
                </div>

                <div class="panel">
                    <strong>Cuotas vencidas</strong>
                    <p class="mt-1"><?= demo_e((string)count(array_filter($installments, fn ($item) => strtolower((string)($item['status'] ?? '')) === 'vencida'))) ?></p>
                </div>

                <div class="panel">
                    <strong>Siniestros pendientes docs</strong>
                    <p class="mt-1"><?= demo_e((string)count(array_filter($claims, fn ($item) => ($item['status'] ?? '') === 'pendiente documentos'))) ?></p>
                </div>
            </div>
        </aside>
    </section>
</div>

<div class="modal" id="dashboard-action-modal" hidden>
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__dialog modal__dialog--sm">
        <div class="modal__header">
            <h3 id="dashboard-action-title">Acción rápida</h3>
            <button type="button" class="icon-btn" data-modal-close aria-label="Cerrar">✕</button>
        </div>
        <div class="modal__body">
            <p class="modal-form-note" id="dashboard-action-note">
                Completa la acción demo. No se guardarán cambios permanentes.
            </p>

            <form id="dashboard-action-form" class="form-grid form-grid--2">
                <div>
                    <label class="form-label" for="dashboard-action-name">Nombre o referencia</label>
                    <input class="input" id="dashboard-action-name" name="name" type="text" placeholder="Ingresa un valor demo">
                </div>

                <div>
                    <label class="form-label" for="dashboard-action-date">Fecha</label>
                    <input class="input" id="dashboard-action-date" name="date" type="date" value="<?= demo_e(date('Y-m-d')) ?>">
                </div>

                <div class="form-grid" style="grid-column: 1 / -1;">
                    <label class="form-label" for="dashboard-action-description">Detalle rápido</label>
                    <textarea class="textarea" id="dashboard-action-description" name="description" placeholder="Escribe una descripción corta para la demo"></textarea>
                </div>
            </form>
        </div>
        <div class="modal__footer">
            <button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button>
            <button type="button" class="btn btn-primary" id="dashboard-action-submit">Guardar demo</button>
        </div>
    </div>
</div>

<script>
    (() => {
        const actionMap = {
            cliente: {
                title: 'Nuevo cliente',
                note: 'Simula el alta rápida de un cliente y genera una actividad visual en el dashboard.',
                placeholder: 'Ej. Pedro Gutiérrez'
            },
            poliza: {
                title: 'Nueva póliza',
                note: 'Simula el registro de una póliza manual con datos básicos de cartera.',
                placeholder: 'Ej. AU-2026-000299'
            },
            pago: {
                title: 'Registrar pago',
                note: 'Simula la confirmación de una cuota y agrega trazabilidad a la actividad reciente.',
                placeholder: 'Ej. Cuota 4 / Javier Salas'
            },
            siniestro: {
                title: 'Nuevo siniestro',
                note: 'Simula la apertura de un caso operativo asociado a una póliza.',
                placeholder: 'Ej. Choque leve / SIN-2026-0031'
            },
            ejecutivo: {
                title: 'Crear ejecutivo',
                note: 'Simula la creación de un nuevo usuario comercial con acceso al sistema.',
                placeholder: 'Ej. Andrea Quispe'
            }
        };

        let activeActionKey = null;

        const modalId = 'dashboard-action-modal';
        const titleNode = document.getElementById('dashboard-action-title');
        const noteNode = document.getElementById('dashboard-action-note');
        const submitButton = document.getElementById('dashboard-action-submit');
        const form = document.getElementById('dashboard-action-form');
        const nameInput = document.getElementById('dashboard-action-name');
        const descriptionInput = document.getElementById('dashboard-action-description');
        const activityList = document.getElementById('dashboard-activity-list');

        document.querySelectorAll('[data-dashboard-action]').forEach((button) => {
            button.addEventListener('click', () => {
                activeActionKey = button.getAttribute('data-dashboard-action');
                const config = actionMap[activeActionKey] || actionMap.cliente;

                titleNode.textContent = config.title;
                noteNode.textContent = config.note;
                nameInput.placeholder = config.placeholder;
                nameInput.value = '';
                descriptionInput.value = '';

                DemoApp.openModal(modalId);
            });
        });

        submitButton.addEventListener('click', () => {
            const config = actionMap[activeActionKey] || actionMap.cliente;
            const name = (nameInput.value || config.title).trim();
            const description = (descriptionInput.value || `Se ejecutó la acción demo "${config.title}" desde el dashboard gerencial.`).trim();
            const date = document.getElementById('dashboard-action-date').value || '';

            DemoApp.closeModal(modalId);
            DemoApp.toast({
                title: config.title,
                message: 'La acción demo se ejecutó correctamente.',
                type: 'success'
            });

            const item = document.createElement('article');
            item.className = 'activity-item';
            item.innerHTML = `
                <div class="activity-item__top">
                    <h3 class="activity-item__title">${config.title}</h3>
                    <span class="activity-item__date">${date ? date.split('-').reverse().join('/') : ''}</span>
                </div>
                <p class="activity-item__description"><strong>${name}</strong> · ${description}</p>
            `;

            if (activityList) {
                const emptyState = activityList.querySelector('.empty-state');
                if (emptyState) emptyState.remove();
                activityList.prepend(item);

                const items = activityList.querySelectorAll('.activity-item');
                if (items.length > 6) {
                    items[items.length - 1].remove();
                }
            }

            form.reset();
            document.getElementById('dashboard-action-date').value = '<?= demo_e(date('Y-m-d')) ?>';
        });
    })();
</script>
<?php
$content = ob_get_clean();

demo_render_internal_layout(
    'Dashboard gerencial',
    $content,
    [
        'breadcrumb' => ['Inicio', 'Dashboard'],
        'subtitle' => 'Vista ejecutiva de cartera, operación comercial y alertas clave del día.',
    ]
);