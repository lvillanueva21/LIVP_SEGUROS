<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';

demo_require_roles(['ejecutivo']);

$currentUser = demo_current_user();
$executiveId = $currentUser['id'] ?? '';

$policies = demo_filter_policies_by_executive(demo_store('policies', []), $executiveId);
$clients = demo_filter_clients_by_executive(demo_store('clients', []), $executiveId);
$claims = demo_store('claims', []);
$claimObservations = demo_store('claim_observations', []);
$claimTimeline = demo_store('claim_timeline', []);

$policyMap = [];
foreach ($policies as $policy) {
    $policyMap[$policy['id']] = $policy;
}

$clientMap = [];
foreach ($clients as $client) {
    $clientMap[$client['id']] = $client;
}

$policyIds = array_column($policies, 'id');

$claimsRows = array_values(array_filter($claims, function ($claim) use ($executiveId, $policyIds) {
    return (($claim['assigned_user_id'] ?? '') === $executiveId) || in_array(($claim['policy_id'] ?? ''), $policyIds, true);
}));

$totalReported = count(array_filter($claimsRows, fn($c) => ($c['status'] ?? '') === 'reportado'));
$totalInReview = count(array_filter($claimsRows, fn($c) => ($c['status'] ?? '') === 'en revisión'));
$totalPendingDocs = count(array_filter($claimsRows, fn($c) => ($c['status'] ?? '') === 'pendiente documentos'));
$totalClosed = count(array_filter($claimsRows, fn($c) => ($c['status'] ?? '') === 'cerrado'));

$claimTypes = [];
foreach ($claimsRows as $claim) {
    if (!empty($claim['type'])) {
        $claimTypes[] = $claim['type'];
    }
}
$claimTypes = array_values(array_unique(array_merge(
    ['Choque leve', 'Robo parcial', 'Daño por agua', 'Accidente personal', 'Incendio', 'Responsabilidad civil'],
    $claimTypes
)));
sort($claimTypes);

usort($claimsRows, fn($a, $b) => strtotime((string)($b['date'] ?? '')) <=> strtotime((string)($a['date'] ?? '')));

ob_start();
?>
<style>
    .exec-claims-kpis {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        margin-bottom: 1rem;
    }

    .exec-claims-controls {
        display: grid;
        gap: 1rem;
        grid-template-columns: 1.2fr .85fr .85fr auto;
        align-items: end;
        margin-bottom: 1rem;
    }

    .exec-claims-actions {
        display: flex;
        flex-wrap: wrap;
        gap: .45rem;
    }

    .exec-claims-action-btn {
        min-height: 34px;
        padding: .45rem .7rem;
        border-radius: 999px;
        border: 1px solid rgba(100, 116, 139, .16);
        background: #fff;
        color: var(--text);
        font-size: .82rem;
        font-weight: 700;
        transition: transform var(--transition), border-color var(--transition), background var(--transition);
    }

    .exec-claims-action-btn:hover {
        transform: translateY(-1px);
        border-color: rgba(79, 70, 229, .18);
        background: #f8fbff;
    }

    .exec-claims-action-btn--primary {
        background: rgba(79, 70, 229, .08);
        color: var(--primary);
        border-color: rgba(79, 70, 229, .12);
    }

    .exec-claims-note {
        padding: .85rem 1rem;
        border-radius: 16px;
        border: 1px dashed rgba(100, 116, 139, .28);
        background: linear-gradient(180deg, #fbfdff 0%, #f7faff 100%);
        margin-bottom: 1rem;
    }

    .exec-claims-note strong {
        display: block;
        margin-bottom: .2rem;
    }

    .exec-claims-empty {
        padding: 1.2rem;
        text-align: center;
        color: var(--text-soft);
    }

    .exec-claims-form-note {
        margin: 0 0 1rem;
        color: var(--text-soft);
        line-height: 1.5;
    }

    .exec-claim-detail-grid {
        display: grid;
        gap: 1rem;
    }

    .exec-claim-detail-header {
        display: grid;
        gap: 1rem;
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: start;
    }

    .exec-claim-detail-header h4 {
        margin: .15rem 0 .3rem;
        font-size: 1.2rem;
    }

    .exec-claim-detail-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: .55rem;
    }

    .exec-claim-meta-grid {
        display: grid;
        gap: .9rem;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .exec-claim-meta-item {
        padding: .9rem 1rem;
        border-radius: 16px;
        border: 1px solid rgba(219, 227, 239, .85);
        background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
    }

    .exec-claim-meta-item strong {
        display: block;
        margin-bottom: .35rem;
        color: var(--text-soft);
        font-size: .78rem;
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .exec-claim-meta-item span {
        display: block;
        line-height: 1.5;
        overflow-wrap: anywhere;
    }

    .exec-claim-detail-panels {
        display: grid;
        gap: 1rem;
        grid-template-columns: 1.1fr .9fr;
    }

    .exec-claim-list {
        display: grid;
        gap: .8rem;
    }

    .exec-claim-item {
        padding: .95rem 1rem;
        border-radius: 18px;
        border: 1px solid rgba(219, 227, 239, .85);
        background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
    }

    .exec-claim-item__top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: .75rem;
        margin-bottom: .35rem;
    }

    .exec-claim-item h5 {
        margin: 0;
        font-size: .95rem;
    }

    .exec-claim-item p,
    .exec-claim-item small {
        margin: 0;
        color: var(--text-soft);
        line-height: 1.5;
    }

    .exec-claim-note-box {
        padding: .85rem 1rem;
        border-radius: 16px;
        border: 1px dashed rgba(100, 116, 139, .28);
        background: linear-gradient(180deg, #fbfdff 0%, #f7faff 100%);
    }

    @media (max-width: 1180px) {
        .exec-claims-kpis {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .exec-claims-controls {
            grid-template-columns: 1fr 1fr;
        }

        .exec-claims-controls .btn {
            width: 100%;
        }
    }

    @media (max-width: 920px) {
        .exec-claim-detail-header,
        .exec-claim-detail-panels,
        .exec-claim-meta-grid {
            grid-template-columns: 1fr;
        }

        .exec-claim-detail-actions {
            justify-content: flex-start;
        }
    }

    @media (max-width: 760px) {
        .exec-claims-kpis,
        .exec-claims-controls {
            grid-template-columns: 1fr;
        }

        .exec-claim-item__top {
            flex-direction: column;
        }
    }
</style>

<div class="grid" style="gap: 1rem;">
    <section class="exec-claims-kpis">
        <article class="card kpi-card">
            <p class="kpi-card__label">Reportados</p>
            <h3 class="kpi-card__value" id="kpi-reportado"><?= demo_e((string)$totalReported) ?></h3>
            <p class="kpi-card__meta">Casos recién ingresados en tu cartera.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">En revisión</p>
            <h3 class="kpi-card__value" id="kpi-en-revision"><?= demo_e((string)$totalInReview) ?></h3>
            <p class="kpi-card__meta">Casos con análisis o seguimiento activo.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Pendiente documentos</p>
            <h3 class="kpi-card__value" id="kpi-pendiente-documentos"><?= demo_e((string)$totalPendingDocs) ?></h3>
            <p class="kpi-card__meta">Expedientes que requieren sustento adicional.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Cerrados</p>
            <h3 class="kpi-card__value" id="kpi-cerrado"><?= demo_e((string)$totalClosed) ?></h3>
            <p class="kpi-card__meta">Casos concluidos dentro de tu cartera.</p>
        </article>
    </section>

    <section class="card">
        <div class="card__header">
            <div>
                <h2 class="card__title">Siniestros de mi cartera</h2>
                <p class="card__subtitle">Seguimiento operativo de casos con detalle, timeline y observaciones.</p>
            </div>

            <button type="button" class="btn btn-primary" id="btn-new-claim">Nuevo siniestro</button>
        </div>

        <div class="exec-claims-note">
            <strong>Casos propios</strong>
            <span class="muted">Solo verás siniestros vinculados a tus clientes o pólizas. Los cambios de estado y observaciones se reflejan sin recargar.</span>
        </div>

        <div class="exec-claims-controls">
            <div>
                <label class="form-label" for="claim-search">Buscar</label>
                <input class="input" id="claim-search" type="text" placeholder="Código, cliente, póliza o tipo">
            </div>

            <div>
                <label class="form-label" for="claim-status-filter">Estado</label>
                <select class="select" id="claim-status-filter">
                    <option value="">Todos</option>
                    <option value="reportado">Reportado</option>
                    <option value="en revisión">En revisión</option>
                    <option value="pendiente documentos">Pendiente documentos</option>
                    <option value="cerrado">Cerrado</option>
                </select>
            </div>

            <div>
                <label class="form-label" for="claim-type-filter">Tipo</label>
                <select class="select" id="claim-type-filter">
                    <option value="">Todos</option>
                    <?php foreach ($claimTypes as $type): ?>
                        <option value="<?= demo_e($type) ?>"><?= demo_e($type) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <button type="button" class="btn btn-ghost" id="btn-reset-claims">Limpiar filtros</button>
            </div>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Cliente</th>
                        <th>Póliza</th>
                        <th>Tipo</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="claims-table-body"></tbody>
            </table>
        </div>

        <div id="claims-empty-state" class="exec-claims-empty" hidden>
            No hay siniestros que coincidan con los filtros seleccionados.
        </div>
    </section>
</div>

<div class="modal" id="claim-create-modal" hidden>
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__dialog">
        <div class="modal__header">
            <h3>Nuevo siniestro</h3>
            <button type="button" class="icon-btn" data-modal-close aria-label="Cerrar">✕</button>
        </div>
        <div class="modal__body">
            <p class="exec-claims-form-note">Registra un caso nuevo dentro de tu propia cartera operativa.</p>

            <form id="claim-create-form" class="form-grid form-grid--3">
                <input type="hidden" name="action" value="create_claim">

                <div>
                    <label class="form-label" for="claim-client">Cliente</label>
                    <select class="select" id="claim-client" name="client_id">
                        <option value="">Seleccionar</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= demo_e($client['id']) ?>"><?= demo_e($client['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label" for="claim-policy">Póliza</label>
                    <select class="select" id="claim-policy" name="policy_id">
                        <option value="">Seleccionar</option>
                        <?php foreach ($policies as $policy): ?>
                            <option value="<?= demo_e($policy['id']) ?>"><?= demo_e(($policy['policy_number'] ?? '—') . ' · ' . ($clientMap[$policy['client_id']]['name'] ?? 'Cliente')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label" for="claim-type">Tipo</label>
                    <select class="select" id="claim-type" name="type">
                        <?php foreach ($claimTypes as $type): ?>
                            <option value="<?= demo_e($type) ?>"><?= demo_e($type) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label" for="claim-date">Fecha del evento</label>
                    <input class="input" type="date" id="claim-date" name="date" value="<?= demo_e(date('Y-m-d')) ?>">
                </div>

                <div>
                    <label class="form-label" for="claim-status">Estado inicial</label>
                    <select class="select" id="claim-status" name="status">
                        <option value="reportado">Reportado</option>
                        <option value="en revisión">En revisión</option>
                        <option value="pendiente documentos">Pendiente documentos</option>
                    </select>
                </div>

                <div style="grid-column: 1 / -1;">
                    <label class="form-label" for="claim-description">Descripción</label>
                    <textarea class="textarea" id="claim-description" name="description" placeholder="Describe el siniestro con el contexto necesario"></textarea>
                </div>

                <div style="grid-column: 1 / -1;">
                    <label class="form-label" for="claim-note">Observación inicial</label>
                    <textarea class="textarea" id="claim-note" name="initial_note" placeholder="Comentario interno opcional"></textarea>
                </div>
            </form>
        </div>
        <div class="modal__footer">
            <button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button>
            <button type="button" class="btn btn-primary" id="claim-create-submit">Guardar siniestro</button>
        </div>
    </div>
</div>

<div class="modal" id="claim-detail-modal" hidden>
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__dialog">
        <div class="modal__header">
            <h3>Detalle del siniestro</h3>
            <button type="button" class="icon-btn" data-modal-close aria-label="Cerrar">✕</button>
        </div>
        <div class="modal__body">
            <div class="exec-claim-detail-grid">
                <div class="exec-claim-detail-header">
                    <div>
                        <span class="badge badge-info" id="detail-code-badge">SIN-0000</span>
                        <h4 id="detail-title">Siniestro</h4>
                        <p class="muted" id="detail-subtitle">Detalle del expediente</p>
                    </div>

                    <div class="exec-claim-detail-actions">
                        <button type="button" class="btn btn-ghost" id="detail-change-status-btn">Cambiar estado</button>
                        <button type="button" class="btn btn-primary" id="detail-add-observation-btn">Agregar observación</button>
                    </div>
                </div>

                <div class="exec-claim-meta-grid" id="claim-meta-grid"></div>

                <div class="exec-claim-note-box">
                    <strong>Descripción del caso</strong>
                    <p class="muted mt-1" id="detail-description">—</p>
                </div>

                <div class="exec-claim-detail-panels">
                    <div class="card">
                        <div class="card__header">
                            <div>
                                <h3 class="card__title">Timeline del caso</h3>
                                <p class="card__subtitle">Eventos principales del expediente.</p>
                            </div>
                        </div>
                        <div class="timeline" id="claim-timeline-list"></div>
                    </div>

                    <div class="card">
                        <div class="card__header">
                            <div>
                                <h3 class="card__title">Observaciones</h3>
                                <p class="card__subtitle">Notas internas del ejecutivo sobre el caso.</p>
                            </div>
                        </div>
                        <div class="exec-claim-list" id="claim-observations-list"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal__footer">
            <button type="button" class="btn btn-primary" data-modal-close>Cerrar</button>
        </div>
    </div>
</div>

<div class="modal" id="claim-status-modal" hidden>
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__dialog modal__dialog--sm">
        <div class="modal__header">
            <h3>Cambiar estado</h3>
            <button type="button" class="icon-btn" data-modal-close aria-label="Cerrar">✕</button>
        </div>
        <div class="modal__body">
            <p class="exec-claims-form-note">Actualiza el estado del caso y registra una nota opcional para el timeline.</p>

            <form id="claim-status-form" class="form-grid">
                <input type="hidden" name="action" value="claim_change_status">
                <input type="hidden" name="claim_id" id="status-claim-id" value="">

                <div>
                    <label class="form-label" for="status-select">Estado</label>
                    <select class="select" id="status-select" name="status">
                        <option value="reportado">Reportado</option>
                        <option value="en revisión">En revisión</option>
                        <option value="pendiente documentos">Pendiente documentos</option>
                        <option value="cerrado">Cerrado</option>
                    </select>
                </div>

                <div>
                    <label class="form-label" for="status-note">Observación</label>
                    <textarea class="textarea" id="status-note" name="note" placeholder="Detalle del cambio de estado"></textarea>
                </div>
            </form>
        </div>
        <div class="modal__footer">
            <button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button>
            <button type="button" class="btn btn-primary" id="claim-status-submit">Guardar cambio</button>
        </div>
    </div>
</div>

<div class="modal" id="claim-observation-modal" hidden>
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__dialog modal__dialog--sm">
        <div class="modal__header">
            <h3>Agregar observación</h3>
            <button type="button" class="icon-btn" data-modal-close aria-label="Cerrar">✕</button>
        </div>
        <div class="modal__body">
            <p class="exec-claims-form-note">Registra una observación rápida dentro del expediente del caso.</p>

            <form id="claim-observation-form" class="form-grid">
                <input type="hidden" name="action" value="claim_add_observation">
                <input type="hidden" name="claim_id" id="observation-claim-id" value="">

                <div>
                    <label class="form-label" for="observation-text">Observación</label>
                    <textarea class="textarea" id="observation-text" name="observation" placeholder="Escribe el detalle del seguimiento"></textarea>
                </div>
            </form>
        </div>
        <div class="modal__footer">
            <button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button>
            <button type="button" class="btn btn-primary" id="claim-observation-submit">Guardar observación</button>
        </div>
    </div>
</div>

<script>
    (() => {
        let claimsState = <?= json_encode(array_values($claimsRows), JSON_UNESCAPED_UNICODE) ?>;
        let observationsState = <?= json_encode(array_values($claimObservations), JSON_UNESCAPED_UNICODE) ?>;
        let timelineState = <?= json_encode(array_values($claimTimeline), JSON_UNESCAPED_UNICODE) ?>;
        const clientsMap = <?= json_encode($clientMap, JSON_UNESCAPED_UNICODE) ?>;
        const policiesMap = <?= json_encode($policyMap, JSON_UNESCAPED_UNICODE) ?>;
        const endpoint = <?= json_encode(demo_url('ajax/ejecutivo-operacion.php'), JSON_UNESCAPED_UNICODE) ?>;

        const tableBody = document.getElementById('claims-table-body');
        const emptyState = document.getElementById('claims-empty-state');

        const searchInput = document.getElementById('claim-search');
        const statusFilter = document.getElementById('claim-status-filter');
        const typeFilter = document.getElementById('claim-type-filter');
        const resetBtn = document.getElementById('btn-reset-claims');

        const kpiReportado = document.getElementById('kpi-reportado');
        const kpiRevision = document.getElementById('kpi-en-revision');
        const kpiPendienteDocs = document.getElementById('kpi-pendiente-documentos');
        const kpiCerrado = document.getElementById('kpi-cerrado');

        const detailCodeBadge = document.getElementById('detail-code-badge');
        const detailTitle = document.getElementById('detail-title');
        const detailSubtitle = document.getElementById('detail-subtitle');
        const detailDescription = document.getElementById('detail-description');
        const metaGrid = document.getElementById('claim-meta-grid');
        const timelineList = document.getElementById('claim-timeline-list');
        const observationsList = document.getElementById('claim-observations-list');

        let activeClaimId = null;

        const escapeHtml = (value) => String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');

        const formatDate = (value) => {
            if (!value) return '—';
            const date = new Date(`${value}T00:00:00`);
            if (Number.isNaN(date.getTime())) return value;
            return date.toLocaleDateString('es-PE');
        };

        const formatDateTime = (value) => {
            if (!value) return '—';
            const date = new Date(String(value).replace(' ', 'T'));
            if (Number.isNaN(date.getTime())) return value;
            return date.toLocaleDateString('es-PE') + ' ' + date.toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' });
        };

        const badgeTone = (status) => ({
            'reportado': 'warning',
            'en revisión': 'info',
            'pendiente documentos': 'danger',
            'cerrado': 'success'
        }[status] || 'neutral');

        const getClientName = (id) => clientsMap[id]?.name || 'Cliente';
        const getPolicyNumber = (id) => policiesMap[id]?.policy_number || 'Sin póliza';

        const renderKpis = () => {
            kpiReportado.textContent = claimsState.filter(c => c.status === 'reportado').length;
            kpiRevision.textContent = claimsState.filter(c => c.status === 'en revisión').length;
            kpiPendienteDocs.textContent = claimsState.filter(c => c.status === 'pendiente documentos').length;
            kpiCerrado.textContent = claimsState.filter(c => c.status === 'cerrado').length;
        };

        const getFilteredClaims = () => {
            const term = searchInput.value.trim().toLowerCase();
            const status = statusFilter.value;
            const type = typeFilter.value;

            return claimsState.filter((claim) => {
                const haystack = [
                    claim.code,
                    getClientName(claim.client_id),
                    getPolicyNumber(claim.policy_id),
                    claim.type
                ].join(' ').toLowerCase();

                return (!term || haystack.includes(term))
                    && (!status || claim.status === status)
                    && (!type || claim.type === type);
            });
        };

        const renderTable = () => {
            const rows = getFilteredClaims();
            tableBody.innerHTML = '';

            if (!rows.length) {
                emptyState.hidden = false;
                return;
            }

            emptyState.hidden = true;

            rows.forEach((claim) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${escapeHtml(claim.code || '—')}</td>
                    <td>${escapeHtml(getClientName(claim.client_id))}</td>
                    <td>${escapeHtml(getPolicyNumber(claim.policy_id))}</td>
                    <td>${escapeHtml(claim.type || '—')}</td>
                    <td>${escapeHtml(formatDate(claim.date))}</td>
                    <td><span class="badge badge-${badgeTone(claim.status)}">${escapeHtml((claim.status || '—').charAt(0).toUpperCase() + (claim.status || '—').slice(1))}</span></td>
                    <td>
                        <div class="exec-claims-actions">
                            <button type="button" class="exec-claims-action-btn exec-claims-action-btn--primary" data-action="detail" data-id="${escapeHtml(claim.id)}">Ver detalle</button>
                            <button type="button" class="exec-claims-action-btn" data-action="status" data-id="${escapeHtml(claim.id)}">Estado</button>
                        </div>
                    </td>
                `;
                tableBody.appendChild(tr);
            });
        };

        const findClaim = (id) => claimsState.find((item) => item.id === id) || null;

        const getClaimObservations = (claimId) => {
            const claim = findClaim(claimId);
            const rows = observationsState.filter((item) => item.claim_id === claimId)
                .slice()
                .sort((a, b) => new Date(b.created_at) - new Date(a.created_at));

            if (rows.length) {
                return rows;
            }

            if (claim && claim.notes) {
                return [{
                    id: `seed-note-${claimId}`,
                    claim_id: claimId,
                    observation: claim.notes,
                    author_name: 'Sistema demo',
                    created_at: claim.created_at || `${claim.date} 10:00:00`,
                }];
            }

            return [];
        };

        const getClaimTimeline = (claimId) => {
            const claim = findClaim(claimId);
            const rows = [];

            if (claim) {
                rows.push({
                    id: `seed-open-${claimId}`,
                    claim_id: claimId,
                    title: 'Siniestro reportado',
                    description: claim.description || 'Se abrió el expediente del siniestro.',
                    created_at: claim.created_at || `${claim.date} 09:00:00`,
                });
            }

            timelineState
                .filter((item) => item.claim_id === claimId)
                .forEach((item) => rows.push(item));

            return rows.slice().sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
        };

        const upsertClaim = (claim) => {
            const index = claimsState.findIndex((item) => item.id === claim.id);
            if (index >= 0) {
                claimsState[index] = claim;
            } else {
                claimsState.unshift(claim);
            }
        };

        const appendTimeline = (timeline) => {
            timelineState.unshift(timeline);
        };

        const appendObservation = (observation) => {
            observationsState.unshift(observation);
        };

        const renderDetail = (claimId) => {
            const claim = findClaim(claimId);
            if (!claim) return;

            activeClaimId = claimId;
            detailCodeBadge.textContent = claim.code || 'SIN-0000';
            detailTitle.textContent = claim.type || 'Siniestro';
            detailSubtitle.textContent = `${getClientName(claim.client_id)} · ${getPolicyNumber(claim.policy_id)}`;
            detailDescription.textContent = claim.description || 'Sin descripción registrada.';

            metaGrid.innerHTML = `
                <div class="exec-claim-meta-item">
                    <strong>Cliente</strong>
                    <span>${escapeHtml(getClientName(claim.client_id))}</span>
                </div>
                <div class="exec-claim-meta-item">
                    <strong>Póliza</strong>
                    <span>${escapeHtml(getPolicyNumber(claim.policy_id))}</span>
                </div>
                <div class="exec-claim-meta-item">
                    <strong>Tipo</strong>
                    <span>${escapeHtml(claim.type || '—')}</span>
                </div>
                <div class="exec-claim-meta-item">
                    <strong>Fecha</strong>
                    <span>${escapeHtml(formatDate(claim.date))}</span>
                </div>
                <div class="exec-claim-meta-item">
                    <strong>Estado</strong>
                    <span>${escapeHtml((claim.status || '—').charAt(0).toUpperCase() + (claim.status || '—').slice(1))}</span>
                </div>
                <div class="exec-claim-meta-item">
                    <strong>Código</strong>
                    <span>${escapeHtml(claim.code || '—')}</span>
                </div>
            `;

            const timelineItems = getClaimTimeline(claimId);
            timelineList.innerHTML = timelineItems.length
                ? timelineItems.map((item) => `
                    <article class="timeline__item">
                        <h4>${escapeHtml(item.title || 'Evento')}</h4>
                        <p>${escapeHtml(item.description || '')}</p>
                        <small class="muted">${escapeHtml(formatDateTime(item.created_at || ''))}</small>
                    </article>
                `).join('')
                : '<div class="exec-claims-empty">No hay eventos registrados para este caso.</div>';

            const observationItems = getClaimObservations(claimId);
            observationsList.innerHTML = observationItems.length
                ? observationItems.map((item) => `
                    <article class="exec-claim-item">
                        <div class="exec-claim-item__top">
                            <div>
                                <h5>${escapeHtml(item.author_name || 'Sistema')}</h5>
                                <p>${escapeHtml(item.observation || '')}</p>
                            </div>
                            <small>${escapeHtml(formatDateTime(item.created_at || ''))}</small>
                        </div>
                    </article>
                `).join('')
                : '<div class="exec-claims-empty">No hay observaciones registradas.</div>';
        };

        document.getElementById('btn-new-claim').addEventListener('click', () => {
            document.getElementById('claim-create-form').reset();
            document.getElementById('claim-date').value = '<?= demo_e(date('Y-m-d')) ?>';
            document.getElementById('claim-status').value = 'reportado';
            DemoApp.openModal('claim-create-modal');
        });

        [searchInput, statusFilter, typeFilter].forEach((element) => {
            element.addEventListener('input', renderTable);
            element.addEventListener('change', renderTable);
        });

        resetBtn.addEventListener('click', () => {
            searchInput.value = '';
            statusFilter.value = '';
            typeFilter.value = '';
            renderTable();
        });

        tableBody.addEventListener('click', (event) => {
            const button = event.target.closest('[data-action]');
            if (!button) return;

            const id = button.getAttribute('data-id');
            const action = button.getAttribute('data-action');

            if (action === 'detail') {
                renderDetail(id);
                DemoApp.openModal('claim-detail-modal');
                return;
            }

            if (action === 'status') {
                const claim = findClaim(id);
                if (!claim) return;
                document.getElementById('status-claim-id').value = claim.id;
                document.getElementById('status-select').value = claim.status || 'reportado';
                document.getElementById('status-note').value = '';
                DemoApp.openModal('claim-status-modal');
            }
        });

        document.getElementById('detail-change-status-btn').addEventListener('click', () => {
            if (!activeClaimId) return;
            const claim = findClaim(activeClaimId);
            if (!claim) return;

            document.getElementById('status-claim-id').value = claim.id;
            document.getElementById('status-select').value = claim.status || 'reportado';
            document.getElementById('status-note').value = '';
            DemoApp.openModal('claim-status-modal');
        });

        document.getElementById('detail-add-observation-btn').addEventListener('click', () => {
            if (!activeClaimId) return;
            document.getElementById('claim-observation-form').reset();
            document.getElementById('observation-claim-id').value = activeClaimId;
            DemoApp.openModal('claim-observation-modal');
        });

        document.getElementById('claim-policy').addEventListener('change', (event) => {
            const policy = policiesMap[event.target.value];
            if (!policy) return;
            document.getElementById('claim-client').value = policy.client_id || '';
        });

        document.getElementById('claim-create-submit').addEventListener('click', async () => {
            const formData = new FormData(document.getElementById('claim-create-form'));

            const response = await DemoApp.api(endpoint, {
                method: 'POST',
                body: formData
            });

            if (!response.success) {
                DemoApp.toast({
                    title: response.title || 'No se pudo crear',
                    message: response.message || 'Verifica la información ingresada.',
                    type: 'error'
                });
                return;
            }

            if (response.claim) upsertClaim(response.claim);
            if (response.observation) appendObservation(response.observation);
            if (response.timeline) appendTimeline(response.timeline);

            renderKpis();
            renderTable();
            DemoApp.closeModal('claim-create-modal');
            DemoApp.toast({
                title: response.title || 'Siniestro creado',
                message: response.message || 'El caso fue registrado correctamente.',
                type: 'success'
            });
        });

        document.getElementById('claim-status-submit').addEventListener('click', async () => {
            const formData = new FormData(document.getElementById('claim-status-form'));

            const response = await DemoApp.api(endpoint, {
                method: 'POST',
                body: formData
            });

            if (!response.success) {
                DemoApp.toast({
                    title: response.title || 'No se pudo actualizar',
                    message: response.message || 'Verifica la información ingresada.',
                    type: 'error'
                });
                return;
            }

            if (response.claim) upsertClaim(response.claim);
            if (response.timeline) appendTimeline(response.timeline);

            renderKpis();
            renderTable();

            if (activeClaimId === document.getElementById('status-claim-id').value) {
                renderDetail(activeClaimId);
            }

            DemoApp.closeModal('claim-status-modal');
            DemoApp.toast({
                title: response.title || 'Estado actualizado',
                message: response.message || 'El siniestro fue actualizado correctamente.',
                type: 'success'
            });
        });

        document.getElementById('claim-observation-submit').addEventListener('click', async () => {
            const formData = new FormData(document.getElementById('claim-observation-form'));

            const response = await DemoApp.api(endpoint, {
                method: 'POST',
                body: formData
            });

            if (!response.success) {
                DemoApp.toast({
                    title: response.title || 'No se pudo guardar',
                    message: response.message || 'Verifica la observación ingresada.',
                    type: 'error'
                });
                return;
            }

            if (response.observation) appendObservation(response.observation);
            if (response.timeline) appendTimeline(response.timeline);

            if (activeClaimId === document.getElementById('observation-claim-id').value) {
                renderDetail(activeClaimId);
            }

            DemoApp.closeModal('claim-observation-modal');
            DemoApp.toast({
                title: response.title || 'Observación agregada',
                message: response.message || 'La observación fue guardada correctamente.',
                type: 'success'
            });
        });

        renderKpis();
        renderTable();
    })();
</script>
<?php
$content = ob_get_clean();

demo_render_internal_layout(
    'Siniestros',
    $content,
    [
        'breadcrumb' => ['Inicio', 'Siniestros'],
        'subtitle' => 'Seguimiento de siniestros vinculados a tu cartera con timeline y observaciones.',
    ]
);