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
$claims = demo_store('claims', []);
$claimObservations = demo_store('claim_observations', []);
$claimTimeline = demo_store('claim_timeline', []);
$documents = demo_store('documents', []);

$portalPolicies = array_values(array_filter($policies, fn($policy) => (string)($policy['client_id'] ?? '') === $clientId));
$policyMap = [];
foreach ($portalPolicies as $policy) {
    $policyMap[$policy['id']] = $policy;
}
$policyIds = array_column($portalPolicies, 'id');

$portalClaims = array_values(array_filter($claims, function ($claim) use ($clientId, $policyIds) {
    return (string)($claim['client_id'] ?? '') === $clientId || in_array((string)($claim['policy_id'] ?? ''), $policyIds, true);
}));

usort($portalClaims, fn($a, $b) => strtotime((string)($b['date'] ?? '')) <=> strtotime((string)($a['date'] ?? '')));

$openClaims = count(array_filter($portalClaims, fn($claim) => ($claim['status'] ?? '') !== 'cerrado'));
$inReviewClaims = count(array_filter($portalClaims, fn($claim) => in_array(($claim['status'] ?? ''), ['en revisión', 'pendiente documentos'], true)));
$closedClaims = count(array_filter($portalClaims, fn($claim) => ($claim['status'] ?? '') === 'cerrado'));

$claimTypes = [];
foreach ($portalClaims as $claim) {
    if (!empty($claim['type'])) {
        $claimTypes[] = $claim['type'];
    }
}
$claimTypes = array_values(array_unique(array_merge(
    ['Choque leve', 'Robo parcial', 'Daño por agua', 'Accidente personal', 'Incendio', 'Responsabilidad civil'],
    $claimTypes
)));
sort($claimTypes);

$portalActive = 'siniestros';

ob_start();
?>
<style>
    .portal-claims-grid {
        display: grid;
        gap: 1rem;
    }

    .portal-claims-kpis {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .portal-claims-note {
        padding: .9rem 1rem;
        border-radius: 18px;
        border: 1px dashed rgba(100, 116, 139, .26);
        background: linear-gradient(180deg, #fbfdff 0%, #f7faff 100%);
        color: var(--text-soft);
        line-height: 1.55;
        margin-bottom: 1rem;
    }

    .portal-claims-empty {
        padding: 1.2rem;
        text-align: center;
        color: var(--text-soft);
    }

    .portal-claims-form-note {
        margin: 0 0 1rem;
        color: var(--text-soft);
        line-height: 1.5;
    }

    .portal-claim-detail-grid {
        display: grid;
        gap: 1rem;
    }

    .portal-claim-detail-head {
        display: grid;
        gap: 1rem;
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: start;
    }

    .portal-claim-detail-head h3 {
        margin: .2rem 0;
        font-size: 1.25rem;
    }

    .portal-claim-detail-head p {
        margin: 0;
        color: var(--text-soft);
        line-height: 1.55;
    }

    .portal-claim-detail-meta {
        display: grid;
        gap: .9rem;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .portal-claim-detail-meta__item {
        padding: .9rem 1rem;
        border-radius: 18px;
        border: 1px solid rgba(219, 227, 239, .85);
        background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
    }

    .portal-claim-detail-meta__item strong {
        display: block;
        margin-bottom: .35rem;
        color: var(--text-soft);
        font-size: .78rem;
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .portal-claim-detail-meta__item span {
        display: block;
        line-height: 1.5;
        overflow-wrap: anywhere;
    }

    .portal-claim-detail-panels {
        display: grid;
        gap: 1rem;
        grid-template-columns: 1fr 1fr;
    }

    .portal-claim-list {
        display: grid;
        gap: .8rem;
    }

    .portal-claim-item {
        padding: .95rem 1rem;
        border-radius: 18px;
        border: 1px solid rgba(219, 227, 239, .85);
        background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
    }

    .portal-claim-item__top {
        display: flex;
        align-items: start;
        justify-content: space-between;
        gap: .8rem;
        margin-bottom: .35rem;
    }

    .portal-claim-item__top h4 {
        margin: 0;
        font-size: .95rem;
    }

    .portal-claim-item p,
    .portal-claim-item small {
        margin: 0;
        color: var(--text-soft);
        line-height: 1.5;
    }

    .portal-inline-note {
        padding: .9rem 1rem;
        border-radius: 18px;
        border: 1px dashed rgba(100, 116, 139, .26);
        background: linear-gradient(180deg, #fbfdff 0%, #f7faff 100%);
        color: var(--text-soft);
        line-height: 1.55;
    }

    @media (max-width: 1100px) {
        .portal-claims-kpis,
        .portal-claim-detail-panels {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 860px) {
        .portal-claim-detail-head,
        .portal-claim-detail-meta {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 620px) {
        .portal-claim-item__top {
            flex-direction: column;
        }
    }
</style>

<div class="portal-shell">
    <?php require __DIR__ . '/_nav.php'; ?>

    <div class="portal-main">
        <div class="portal-claims-grid">
            <section class="portal-claims-kpis">
                <article class="card kpi-card">
                    <p class="kpi-card__label">Abiertos</p>
                    <h3 class="kpi-card__value" id="kpi-open"><?= demo_e((string)$openClaims) ?></h3>
                    <p class="kpi-card__meta">Casos aún en gestión dentro de tu portal.</p>
                </article>

                <article class="card kpi-card">
                    <p class="kpi-card__label">En revisión</p>
                    <h3 class="kpi-card__value" id="kpi-review"><?= demo_e((string)$inReviewClaims) ?></h3>
                    <p class="kpi-card__meta">Casos pendientes de validación o documentación.</p>
                </article>

                <article class="card kpi-card">
                    <p class="kpi-card__label">Cerrados</p>
                    <h3 class="kpi-card__value" id="kpi-closed"><?= demo_e((string)$closedClaims) ?></h3>
                    <p class="kpi-card__meta">Casos concluidos visibles en tu historial.</p>
                </article>
            </section>

            <section class="card">
                <div class="card__header">
                    <div>
                        <h2 class="card__title">Mis siniestros</h2>
                        <p class="card__subtitle">Reporta un siniestro nuevo y revisa el seguimiento de tus casos actuales.</p>
                    </div>
                    <button type="button" class="btn btn-primary" id="btn-new-claim">Reportar siniestro</button>
                </div>

                <div class="portal-claims-note">
                    Desde aquí puedes registrar un siniestro de forma simple. El adjunto es demo y se mostrará como referencia dentro del caso.
                </div>

                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Póliza</th>
                                <th>Fecha</th>
                                <th>Estado</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody id="claims-table-body"></tbody>
                    </table>
                </div>

                <div id="claims-empty-state" class="portal-claims-empty" hidden>
                    Todavía no tienes siniestros registrados en este portal.
                </div>
            </section>
        </div>
    </div>
</div>

<div class="modal" id="claim-create-modal" hidden>
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__dialog">
        <div class="modal__header">
            <h3>Reportar siniestro</h3>
            <button type="button" class="icon-btn" data-modal-close aria-label="Cerrar">✕</button>
        </div>
        <div class="modal__body">
            <p class="portal-claims-form-note">Completa la información principal del incidente. El adjunto será una referencia visual demo.</p>

            <form id="claim-create-form" class="form-grid form-grid--2">
                <input type="hidden" name="action" value="create_claim">

                <div>
                    <label class="form-label" for="claim-policy">Póliza</label>
                    <select class="select" id="claim-policy" name="policy_id">
                        <option value="">Seleccionar</option>
                        <?php foreach ($portalPolicies as $policy): ?>
                            <option value="<?= demo_e($policy['id']) ?>"><?= demo_e(($policy['policy_number'] ?? '—') . ' · ' . demo_insurance_type_name((string)($policy['insurance_type_id'] ?? ''))) ?></option>
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
                    <label class="form-label" for="claim-date">Fecha</label>
                    <input class="input" type="date" id="claim-date" name="date" value="<?= demo_e(date('Y-m-d')) ?>">
                </div>

                <div>
                    <label class="form-label" for="claim-attachment">Adjunto simulado</label>
                    <input class="input" type="text" id="claim-attachment" name="attachment_name" placeholder="foto_incidente.jpg">
                </div>

                <div style="grid-column: 1 / -1;">
                    <label class="form-label" for="claim-description">Descripción</label>
                    <textarea class="textarea" id="claim-description" name="description" placeholder="Describe el incidente con el mayor detalle posible"></textarea>
                </div>
            </form>
        </div>
        <div class="modal__footer">
            <button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button>
            <button type="button" class="btn btn-primary" id="claim-create-submit">Enviar reporte</button>
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
            <div class="portal-claim-detail-grid">
                <div class="portal-claim-detail-head">
                    <div>
                        <span class="badge badge-info" id="detail-status-badge">Reportado</span>
                        <h3 id="detail-title">Siniestro</h3>
                        <p id="detail-subtitle">Detalle del caso</p>
                    </div>

                    <div class="portal-inline-note">
                        Este detalle muestra el avance visible para ti dentro del portal cliente.
                    </div>
                </div>

                <div class="portal-claim-detail-meta" id="detail-meta-grid"></div>

                <div class="portal-inline-note">
                    <strong>Descripción</strong>
                    <p class="mt-1" id="detail-description">—</p>
                </div>

                <div class="portal-claim-detail-panels">
                    <div class="card">
                        <div class="card__header">
                            <div>
                                <h3 class="card__title">Timeline</h3>
                                <p class="card__subtitle">Eventos principales del caso visibles en tu portal.</p>
                            </div>
                        </div>
                        <div class="timeline" id="detail-timeline-list"></div>
                    </div>

                    <div class="card">
                        <div class="card__header">
                            <div>
                                <h3 class="card__title">Observaciones</h3>
                                <p class="card__subtitle">Notas visibles relacionadas con el expediente.</p>
                            </div>
                        </div>
                        <div class="portal-claim-list" id="detail-observations-list"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal__footer">
            <button type="button" class="btn btn-primary" data-modal-close>Cerrar</button>
        </div>
    </div>
</div>

<script>
    (() => {
        let claimsState = <?= json_encode(array_values($portalClaims), JSON_UNESCAPED_UNICODE) ?>;
        let observationsState = <?= json_encode(array_values($claimObservations), JSON_UNESCAPED_UNICODE) ?>;
        let timelineState = <?= json_encode(array_values($claimTimeline), JSON_UNESCAPED_UNICODE) ?>;
        let documentsState = <?= json_encode(array_values($documents), JSON_UNESCAPED_UNICODE) ?>;
        const policiesMap = <?= json_encode($policyMap, JSON_UNESCAPED_UNICODE) ?>;
        const endpoint = <?= json_encode(demo_url('ajax/portal-cliente.php'), JSON_UNESCAPED_UNICODE) ?>;

        const tableBody = document.getElementById('claims-table-body');
        const emptyState = document.getElementById('claims-empty-state');

        const kpiOpen = document.getElementById('kpi-open');
        const kpiReview = document.getElementById('kpi-review');
        const kpiClosed = document.getElementById('kpi-closed');

        const detailStatusBadge = document.getElementById('detail-status-badge');
        const detailTitle = document.getElementById('detail-title');
        const detailSubtitle = document.getElementById('detail-subtitle');
        const detailMetaGrid = document.getElementById('detail-meta-grid');
        const detailDescription = document.getElementById('detail-description');
        const detailTimelineList = document.getElementById('detail-timeline-list');
        const detailObservationsList = document.getElementById('detail-observations-list');

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
        }[String(status || '').toLowerCase()] || 'neutral');

        const getPolicyNumber = (policyId) => policiesMap[policyId]?.policy_number || 'Sin póliza';

        const renderKpis = () => {
            kpiOpen.textContent = String(claimsState.filter(c => c.status !== 'cerrado').length);
            kpiReview.textContent = String(claimsState.filter(c => ['en revisión', 'pendiente documentos'].includes(c.status)).length);
            kpiClosed.textContent = String(claimsState.filter(c => c.status === 'cerrado').length);
        };

        const renderTable = () => {
            tableBody.innerHTML = '';

            if (!claimsState.length) {
                emptyState.hidden = false;
                return;
            }

            emptyState.hidden = true;

            claimsState
                .slice()
                .sort((a, b) => new Date(b.date) - new Date(a.date))
                .forEach((claim) => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${escapeHtml(claim.code || '—')}</td>
                        <td>${escapeHtml(getPolicyNumber(claim.policy_id))}</td>
                        <td>${escapeHtml(formatDate(claim.date))}</td>
                        <td><span class="badge badge-${badgeTone(claim.status)}">${escapeHtml((claim.status || '—').charAt(0).toUpperCase() + (claim.status || '—').slice(1))}</span></td>
                        <td><button type="button" class="btn btn-primary" data-action="detail" data-id="${escapeHtml(claim.id)}">Ver detalle</button></td>
                    `;
                    tableBody.appendChild(tr);
                });
        };

        const findClaim = (id) => claimsState.find((claim) => claim.id === id) || null;

        const appendClaim = (claim) => {
            claimsState.unshift(claim);
        };

        const appendTimeline = (event) => {
            timelineState.unshift(event);
        };

        const appendObservation = (observation) => {
            observationsState.unshift(observation);
        };

        const appendDocument = (document) => {
            documentsState.unshift(document);
        };

        const getClaimTimeline = (claimId) => {
            const claim = findClaim(claimId);
            const rows = [];

            if (claim) {
                rows.push({
                    id: `seed-open-${claimId}`,
                    claim_id: claimId,
                    title: 'Siniestro reportado',
                    description: claim.description || 'Se registró el siniestro desde el portal cliente.',
                    created_at: claim.created_at || `${claim.date} 09:00:00`
                });
            }

            timelineState
                .filter((item) => item.claim_id === claimId)
                .forEach((item) => rows.push(item));

            return rows.slice().sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
        };

        const getClaimObservations = (claimId) => {
            const claim = findClaim(claimId);
            const rows = observationsState
                .filter((item) => item.claim_id === claimId)
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
                    created_at: claim.created_at || `${claim.date} 10:00:00`
                }];
            }

            return [];
        };

        const getAttachmentHint = (claimId) => {
            const doc = documentsState.find((item) => item.entity_type === 'claim' && item.entity_id === claimId);
            return doc ? doc.original_name || 'Adjunto demo' : 'Sin adjunto demo';
        };

        const openDetailModal = (claimId) => {
            const claim = findClaim(claimId);
            if (!claim) return;

            detailStatusBadge.className = `badge badge-${badgeTone(claim.status)}`;
            detailStatusBadge.textContent = (claim.status || '—').charAt(0).toUpperCase() + (claim.status || '—').slice(1);
            detailTitle.textContent = claim.code || 'Siniestro';
            detailSubtitle.textContent = `${getPolicyNumber(claim.policy_id)} · ${claim.type || 'Caso'}`;
            detailDescription.textContent = claim.description || 'Sin descripción registrada.';

            detailMetaGrid.innerHTML = `
                <div class="portal-claim-detail-meta__item">
                    <strong>Póliza</strong>
                    <span>${escapeHtml(getPolicyNumber(claim.policy_id))}</span>
                </div>
                <div class="portal-claim-detail-meta__item">
                    <strong>Tipo</strong>
                    <span>${escapeHtml(claim.type || '—')}</span>
                </div>
                <div class="portal-claim-detail-meta__item">
                    <strong>Fecha</strong>
                    <span>${escapeHtml(formatDate(claim.date))}</span>
                </div>
                <div class="portal-claim-detail-meta__item">
                    <strong>Estado</strong>
                    <span>${escapeHtml((claim.status || '—').charAt(0).toUpperCase() + (claim.status || '—').slice(1))}</span>
                </div>
                <div class="portal-claim-detail-meta__item">
                    <strong>Código</strong>
                    <span>${escapeHtml(claim.code || '—')}</span>
                </div>
                <div class="portal-claim-detail-meta__item">
                    <strong>Adjunto</strong>
                    <span>${escapeHtml(getAttachmentHint(claim.id))}</span>
                </div>
            `;

            const timelineRows = getClaimTimeline(claimId);
            detailTimelineList.innerHTML = timelineRows.length
                ? timelineRows.map((item) => `
                    <article class="timeline__item">
                        <h4>${escapeHtml(item.title || 'Evento')}</h4>
                        <p>${escapeHtml(item.description || '')}</p>
                        <small class="muted">${escapeHtml(formatDateTime(item.created_at || ''))}</small>
                    </article>
                `).join('')
                : '<div class="portal-claims-empty">No hay eventos visibles para este caso.</div>';

            const observationRows = getClaimObservations(claimId);
            detailObservationsList.innerHTML = observationRows.length
                ? observationRows.map((item) => `
                    <article class="portal-claim-item">
                        <div class="portal-claim-item__top">
                            <div>
                                <h4>${escapeHtml(item.author_name || 'Sistema')}</h4>
                                <p>${escapeHtml(item.observation || '')}</p>
                            </div>
                            <small>${escapeHtml(formatDateTime(item.created_at || ''))}</small>
                        </div>
                    </article>
                `).join('')
                : '<div class="portal-claims-empty">No hay observaciones visibles para este caso.</div>';

            DemoApp.openModal('claim-detail-modal');
        };

        document.getElementById('btn-new-claim').addEventListener('click', () => {
            document.getElementById('claim-create-form').reset();
            document.getElementById('claim-date').value = '<?= demo_e(date('Y-m-d')) ?>';
            DemoApp.openModal('claim-create-modal');
        });

        tableBody.addEventListener('click', (event) => {
            const button = event.target.closest('[data-action="detail"]');
            if (!button) return;
            openDetailModal(button.getAttribute('data-id'));
        });

        document.getElementById('claim-create-submit').addEventListener('click', async () => {
            const formData = new FormData(document.getElementById('claim-create-form'));

            const response = await DemoApp.api(endpoint, {
                method: 'POST',
                body: formData
            });

            if (!response.success) {
                DemoApp.toast({
                    title: response.title || 'No se pudo registrar',
                    message: response.message || 'Verifica la información ingresada.',
                    type: 'error'
                });
                return;
            }

            if (response.claim) appendClaim(response.claim);
            if (response.timeline) appendTimeline(response.timeline);
            if (response.observation) appendObservation(response.observation);
            if (response.document) appendDocument(response.document);

            renderKpis();
            renderTable();
            DemoApp.closeModal('claim-create-modal');
            DemoApp.toast({
                title: response.title || 'Siniestro enviado',
                message: response.message || 'Tu reporte fue registrado correctamente.',
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
    'Mis siniestros',
    $content,
    [
        'breadcrumb' => ['Portal', 'Mis siniestros'],
        'subtitle' => 'Reporte y seguimiento de siniestros visibles para el cliente.',
    ]
);