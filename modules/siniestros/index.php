<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';

demo_require_roles(['gerente', 'superadmin']);

$claims = demo_store('claims', []);
$clients = demo_store('clients', []);
$policies = demo_store('policies', []);
$users = demo_store('users', []);
$documents = demo_store('documents', []);
$claimObservations = demo_store('claim_observations', []);
$claimTimeline = demo_store('claim_timeline', []);

$executives = array_values(array_filter($users, fn($u) => ($u['role'] ?? '') === 'ejecutivo'));

$clientMap = [];
foreach ($clients as $client) {
    $clientMap[$client['id']] = $client;
}

$policyMap = [];
foreach ($policies as $policy) {
    $policyMap[$policy['id']] = $policy;
}

$userMap = [];
foreach ($users as $user) {
    $userMap[$user['id']] = $user;
}

$claimDocuments = array_values(array_filter($documents, fn($doc) => ($doc['entity_type'] ?? '') === 'claim'));

$totalReported = count(array_filter($claims, fn($c) => ($c['status'] ?? '') === 'reportado'));
$totalInReview = count(array_filter($claims, fn($c) => ($c['status'] ?? '') === 'en revisión'));
$totalPendingDocs = count(array_filter($claims, fn($c) => ($c['status'] ?? '') === 'pendiente documentos'));
$totalClosed = count(array_filter($claims, fn($c) => ($c['status'] ?? '') === 'cerrado'));

$claimTypes = [];
foreach ($claims as $claim) {
    if (!empty($claim['type'])) {
        $claimTypes[] = $claim['type'];
    }
}
$claimTypes = array_values(array_unique(array_merge(
    ['Choque leve', 'Robo parcial', 'Daño por agua', 'Accidente personal', 'Incendio', 'Responsabilidad civil'],
    $claimTypes
)));
sort($claimTypes);

usort($claims, fn($a, $b) => strtotime((string)($b['date'] ?? '')) <=> strtotime((string)($a['date'] ?? '')));

ob_start();
?>
<style>
    .claims-toolbar {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        margin-bottom: 1rem;
    }

    .claims-controls {
        display: grid;
        gap: 1rem;
        grid-template-columns: 1.2fr .85fr .85fr .85fr auto;
        align-items: end;
        margin-bottom: 1rem;
    }

    .claims-actions {
        display: flex;
        flex-wrap: wrap;
        gap: .45rem;
    }

    .claims-action-btn {
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

    .claims-action-btn:hover {
        transform: translateY(-1px);
        border-color: rgba(79, 70, 229, .18);
        background: #f8fbff;
    }

    .claims-action-btn--primary {
        background: rgba(79, 70, 229, .08);
        color: var(--primary);
        border-color: rgba(79, 70, 229, .12);
    }

    .claims-inline-note {
        padding: .85rem 1rem;
        border-radius: 16px;
        border: 1px dashed rgba(100, 116, 139, .28);
        background: linear-gradient(180deg, #fbfdff 0%, #f7faff 100%);
        margin-bottom: 1rem;
    }

    .claims-inline-note strong {
        display: block;
        margin-bottom: .2rem;
    }

    .claims-empty {
        padding: 1.2rem;
        text-align: center;
        color: var(--text-soft);
    }

    .claims-form-note {
        margin: 0 0 1rem;
        color: var(--text-soft);
        line-height: 1.5;
    }

    .claims-detail-grid {
        display: grid;
        gap: 1rem;
    }

    .claims-detail-header {
        display: grid;
        gap: 1rem;
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: start;
    }

    .claims-detail-header h4 {
        margin: .15rem 0 .3rem;
        font-size: 1.2rem;
    }

    .claims-detail-actions {
        display: flex;
        flex-wrap: wrap;
        gap: .55rem;
        justify-content: flex-end;
    }

    .claims-meta-grid {
        display: grid;
        gap: .9rem;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .claims-meta-item {
        padding: .9rem 1rem;
        border-radius: 16px;
        border: 1px solid rgba(219, 227, 239, .85);
        background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
    }

    .claims-meta-item strong {
        display: block;
        margin-bottom: .35rem;
        color: var(--text-soft);
        font-size: .78rem;
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .claims-meta-item span {
        display: block;
        line-height: 1.5;
        overflow-wrap: anywhere;
    }

    .claims-detail-panels {
        display: grid;
        gap: 1rem;
        grid-template-columns: 1.1fr .9fr;
    }

    .claims-list {
        display: grid;
        gap: .8rem;
    }

    .claims-list-item {
        padding: .95rem 1rem;
        border-radius: 18px;
        border: 1px solid rgba(219, 227, 239, .85);
        background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
    }

    .claims-list-item__top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: .75rem;
        margin-bottom: .35rem;
    }

    .claims-list-item h5 {
        margin: 0;
        font-size: .95rem;
    }

    .claims-list-item p,
    .claims-list-item small {
        margin: 0;
        color: var(--text-soft);
        line-height: 1.5;
    }

    .claims-small-note {
        padding: .85rem 1rem;
        border-radius: 16px;
        border: 1px dashed rgba(100, 116, 139, .28);
        background: linear-gradient(180deg, #fbfdff 0%, #f7faff 100%);
    }

    @media (max-width: 1220px) {
        .claims-toolbar {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .claims-controls {
            grid-template-columns: 1fr 1fr;
        }

        .claims-controls .btn {
            width: 100%;
        }
    }

    @media (max-width: 920px) {
        .claims-detail-header,
        .claims-detail-panels,
        .claims-meta-grid {
            grid-template-columns: 1fr;
        }

        .claims-detail-actions {
            justify-content: flex-start;
        }
    }

    @media (max-width: 760px) {
        .claims-toolbar,
        .claims-controls {
            grid-template-columns: 1fr;
        }

        .claims-list-item__top {
            flex-direction: column;
        }
    }
</style>

<div class="grid" style="gap: 1rem;">
    <section class="claims-toolbar">
        <article class="card kpi-card">
            <p class="kpi-card__label">Reportados</p>
            <h3 class="kpi-card__value" id="kpi-reportado"><?= demo_e((string)$totalReported) ?></h3>
            <p class="kpi-card__meta">Casos recién ingresados y pendientes de evaluación inicial.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">En revisión</p>
            <h3 class="kpi-card__value" id="kpi-en-revision"><?= demo_e((string)$totalInReview) ?></h3>
            <p class="kpi-card__meta">Expedientes con análisis operativo o técnico en curso.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Pendientes de documentos</p>
            <h3 class="kpi-card__value" id="kpi-pendiente-documentos"><?= demo_e((string)$totalPendingDocs) ?></h3>
            <p class="kpi-card__meta">Siniestros que requieren evidencias o sustento adicional.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Cerrados</p>
            <h3 class="kpi-card__value" id="kpi-cerrado"><?= demo_e((string)$totalClosed) ?></h3>
            <p class="kpi-card__meta">Casos concluidos dentro de la sesión actual del demo.</p>
        </article>
    </section>

    <section class="card">
        <div class="card__header">
            <div>
                <h2 class="card__title">Listado de siniestros</h2>
                <p class="card__subtitle">Control gerencial de casos, responsables, documentos y seguimiento cronológico.</p>
            </div>

            <button type="button" class="btn btn-primary" id="btn-new-claim">Nuevo siniestro</button>
        </div>

        <div class="claims-inline-note">
            <strong>Flujo demo activo</strong>
            <span class="muted">Puedes crear un siniestro, abrir su detalle, cambiar estado, agregar observaciones y adjuntar documentos simulados sin recargar la página.</span>
        </div>

        <div class="claims-controls">
            <div>
                <label class="form-label" for="claim-filter-search">Buscar</label>
                <input class="input" id="claim-filter-search" type="text" placeholder="Código, cliente, póliza o responsable">
            </div>

            <div>
                <label class="form-label" for="claim-filter-status">Estado</label>
                <select class="select" id="claim-filter-status">
                    <option value="">Todos</option>
                    <option value="reportado">Reportado</option>
                    <option value="en revisión">En revisión</option>
                    <option value="pendiente documentos">Pendiente documentos</option>
                    <option value="cerrado">Cerrado</option>
                </select>
            </div>

            <div>
                <label class="form-label" for="claim-filter-type">Tipo</label>
                <select class="select" id="claim-filter-type">
                    <option value="">Todos</option>
                    <?php foreach ($claimTypes as $type): ?>
                        <option value="<?= demo_e($type) ?>"><?= demo_e($type) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="form-label" for="claim-filter-responsible">Responsable</label>
                <select class="select" id="claim-filter-responsible">
                    <option value="">Todos</option>
                    <?php foreach ($executives as $exec): ?>
                        <option value="<?= demo_e($exec['id']) ?>"><?= demo_e($exec['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <button type="button" class="btn btn-ghost" id="btn-reset-claim-filters">Limpiar filtros</button>
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
                        <th>Responsable</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="claims-table-body"></tbody>
            </table>
        </div>

        <div id="claims-empty-state" class="claims-empty" hidden>
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
            <p class="claims-form-note">Registra un caso nuevo con cliente, póliza, tipo y descripción. El código del siniestro se generará automáticamente.</p>

            <form id="claim-create-form" class="form-grid form-grid--3">
                <input type="hidden" name="action" value="create">

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
                    <label class="form-label" for="claim-responsible">Responsable</label>
                    <select class="select" id="claim-responsible" name="assigned_user_id">
                        <option value="">Sin asignar</option>
                        <?php foreach ($executives as $exec): ?>
                            <option value="<?= demo_e($exec['id']) ?>"><?= demo_e($exec['full_name']) ?></option>
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
                    <textarea class="textarea" id="claim-description" name="description" placeholder="Describe el caso con el mayor contexto posible"></textarea>
                </div>

                <div style="grid-column: 1 / -1;">
                    <label class="form-label" for="claim-initial-note">Observación inicial</label>
                    <textarea class="textarea" id="claim-initial-note" name="initial_note" placeholder="Comentario interno opcional para abrir el caso"></textarea>
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
            <div class="claims-detail-grid">
                <div class="claims-detail-header">
                    <div>
                        <span class="badge badge-info" id="detail-code-badge">SIN-0000</span>
                        <h4 id="detail-title">Siniestro</h4>
                        <p class="muted" id="detail-subtitle">Detalle del expediente</p>
                    </div>

                    <div class="claims-detail-actions">
                        <button type="button" class="btn btn-ghost" id="detail-change-status-btn">Cambiar estado</button>
                        <button type="button" class="btn btn-secondary" id="detail-add-document-btn">Adjuntar documento</button>
                        <button type="button" class="btn btn-primary" id="detail-add-observation-btn">Agregar observación</button>
                    </div>
                </div>

                <div class="claims-meta-grid" id="claim-meta-grid"></div>

                <div class="claims-small-note">
                    <strong>Descripción del caso</strong>
                    <p class="muted mt-1" id="detail-description">—</p>
                </div>

                <div class="claims-detail-panels">
                    <div class="card">
                        <div class="card__header">
                            <div>
                                <h3 class="card__title">Timeline del caso</h3>
                                <p class="card__subtitle">Eventos clave del siniestro ordenados cronológicamente.</p>
                            </div>
                        </div>
                        <div class="timeline" id="claim-timeline-list"></div>
                    </div>

                    <div class="grid" style="gap: 1rem;">
                        <div class="card">
                            <div class="card__header">
                                <div>
                                    <h3 class="card__title">Documentos</h3>
                                    <p class="card__subtitle">Archivos asociados al expediente.</p>
                                </div>
                            </div>
                            <div class="claims-list" id="claim-documents-list"></div>
                        </div>

                        <div class="card">
                            <div class="card__header">
                                <div>
                                    <h3 class="card__title">Observaciones</h3>
                                    <p class="card__subtitle">Notas internas de seguimiento del caso.</p>
                                </div>
                            </div>
                            <div class="claims-list" id="claim-observations-list"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal__footer">
            <button type="button" class="btn btn-ghost" data-modal-close>Cerrar</button>
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
            <p class="claims-form-note">Actualiza el estado operativo del siniestro y agrega una nota opcional para el timeline.</p>

            <form id="claim-status-form" class="form-grid">
                <input type="hidden" name="action" value="change_status">
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
                    <textarea class="textarea" id="status-note" name="note" placeholder="Comentario del cambio de estado"></textarea>
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
            <p class="claims-form-note">Agrega una observación interna para enriquecer el expediente del caso.</p>

            <form id="claim-observation-form" class="form-grid">
                <input type="hidden" name="action" value="add_observation">
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

<div class="modal" id="claim-document-modal" hidden>
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__dialog modal__dialog--sm">
        <div class="modal__header">
            <h3>Adjuntar documento</h3>
            <button type="button" class="icon-btn" data-modal-close aria-label="Cerrar">✕</button>
        </div>
        <div class="modal__body">
            <p class="claims-form-note">Adjunta un documento simulado al expediente del siniestro.</p>

            <form id="claim-document-form" class="form-grid">
                <input type="hidden" name="action" value="attach_document">
                <input type="hidden" name="claim_id" id="document-claim-id" value="">

                <div>
                    <label class="form-label" for="document-name">Nombre del archivo</label>
                    <input class="input" type="text" id="document-name" name="original_name" placeholder="Fotos_choque_frontal.jpg">
                </div>

                <div>
                    <label class="form-label" for="document-type">Tipo de documento</label>
                    <select class="select" id="document-type" name="document_type">
                        <option value="Evidencia">Evidencia</option>
                        <option value="Informe">Informe</option>
                        <option value="Denuncia">Denuncia</option>
                        <option value="Carta">Carta</option>
                    </select>
                </div>

                <div>
                    <label class="form-label" for="document-note">Observación</label>
                    <textarea class="textarea" id="document-note" name="document_note" placeholder="Detalle adicional del archivo demo"></textarea>
                </div>
            </form>
        </div>
        <div class="modal__footer">
            <button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button>
            <button type="button" class="btn btn-primary" id="claim-document-submit">Adjuntar documento</button>
        </div>
    </div>
</div>

<script>
    (() => {
        let claimsState = <?= json_encode(array_values($claims), JSON_UNESCAPED_UNICODE) ?>;
        let documentsState = <?= json_encode(array_values($claimDocuments), JSON_UNESCAPED_UNICODE) ?>;
        let observationsState = <?= json_encode(array_values($claimObservations), JSON_UNESCAPED_UNICODE) ?>;
        let timelineState = <?= json_encode(array_values($claimTimeline), JSON_UNESCAPED_UNICODE) ?>;

        const clientsMap = <?= json_encode($clientMap, JSON_UNESCAPED_UNICODE) ?>;
        const policiesMap = <?= json_encode($policyMap, JSON_UNESCAPED_UNICODE) ?>;
        const usersMap = <?= json_encode($userMap, JSON_UNESCAPED_UNICODE) ?>;
        const endpoint = <?= json_encode(demo_url('ajax/siniestros.php'), JSON_UNESCAPED_UNICODE) ?>;

        const tableBody = document.getElementById('claims-table-body');
        const emptyState = document.getElementById('claims-empty-state');

        const filterSearch = document.getElementById('claim-filter-search');
        const filterStatus = document.getElementById('claim-filter-status');
        const filterType = document.getElementById('claim-filter-type');
        const filterResponsible = document.getElementById('claim-filter-responsible');
        const btnResetFilters = document.getElementById('btn-reset-claim-filters');

        const btnNewClaim = document.getElementById('btn-new-claim');
        const createForm = document.getElementById('claim-create-form');
        const createSubmit = document.getElementById('claim-create-submit');

        const detailCodeBadge = document.getElementById('detail-code-badge');
        const detailTitle = document.getElementById('detail-title');
        const detailSubtitle = document.getElementById('detail-subtitle');
        const detailDescription = document.getElementById('detail-description');
        const metaGrid = document.getElementById('claim-meta-grid');
        const timelineList = document.getElementById('claim-timeline-list');
        const documentsList = document.getElementById('claim-documents-list');
        const observationsList = document.getElementById('claim-observations-list');

        const btnDetailStatus = document.getElementById('detail-change-status-btn');
        const btnDetailDocument = document.getElementById('detail-add-document-btn');
        const btnDetailObservation = document.getElementById('detail-add-observation-btn');

        const statusForm = document.getElementById('claim-status-form');
        const statusClaimId = document.getElementById('status-claim-id');
        const statusSelect = document.getElementById('status-select');
        const statusNote = document.getElementById('status-note');
        const statusSubmit = document.getElementById('claim-status-submit');

        const observationForm = document.getElementById('claim-observation-form');
        const observationClaimId = document.getElementById('observation-claim-id');
        const observationSubmit = document.getElementById('claim-observation-submit');

        const documentForm = document.getElementById('claim-document-form');
        const documentClaimId = document.getElementById('document-claim-id');
        const documentSubmit = document.getElementById('claim-document-submit');

        const kpiReportado = document.getElementById('kpi-reportado');
        const kpiRevision = document.getElementById('kpi-en-revision');
        const kpiPendienteDocs = document.getElementById('kpi-pendiente-documentos');
        const kpiCerrado = document.getElementById('kpi-cerrado');

        let activeDetailClaimId = null;

        const escapeHtml = (value) => String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');

        const formatDate = (value) => {
            if (!value) return '—';
            const date = new Date(value.replace(' ', 'T'));
            if (Number.isNaN(date.getTime())) return value;
            return date.toLocaleDateString('es-PE');
        };

        const formatDateTime = (value) => {
            if (!value) return '—';
            const date = new Date(value.replace(' ', 'T'));
            if (Number.isNaN(date.getTime())) return value;
            return date.toLocaleDateString('es-PE') + ' ' + date.toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' });
        };

        const statusTone = (status) => ({
            'reportado': 'warning',
            'en revisión': 'info',
            'pendiente documentos': 'danger',
            'cerrado': 'success'
        }[status] || 'neutral');

        const getClientName = (id) => clientsMap[id]?.name || 'Cliente no encontrado';
        const getPolicyNumber = (id) => policiesMap[id]?.policy_number || 'Sin póliza';
        const getResponsibleName = (id) => usersMap[id]?.full_name || 'Sin asignar';

        const getFilteredClaims = () => {
            const term = filterSearch.value.trim().toLowerCase();
            const status = filterStatus.value;
            const type = filterType.value;
            const responsible = filterResponsible.value;

            return claimsState.filter((claim) => {
                const haystack = [
                    claim.code,
                    getClientName(claim.client_id),
                    getPolicyNumber(claim.policy_id),
                    getResponsibleName(claim.assigned_user_id),
                    claim.type
                ].join(' ').toLowerCase();

                return (!term || haystack.includes(term))
                    && (!status || claim.status === status)
                    && (!type || claim.type === type)
                    && (!responsible || (claim.assigned_user_id || '') === responsible);
            });
        };

        const renderKpis = () => {
            kpiReportado.textContent = claimsState.filter(c => c.status === 'reportado').length;
            kpiRevision.textContent = claimsState.filter(c => c.status === 'en revisión').length;
            kpiPendienteDocs.textContent = claimsState.filter(c => c.status === 'pendiente documentos').length;
            kpiCerrado.textContent = claimsState.filter(c => c.status === 'cerrado').length;
        };

        const renderTable = () => {
            const rows = getFilteredClaims();
            tableBody.innerHTML = '';

            if (!rows.length) {
                emptyState.hidden = false;
                return;
            }

            emptyState.hidden = true;

            rows
                .slice()
                .sort((a, b) => new Date(b.date) - new Date(a.date))
                .forEach((claim) => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${escapeHtml(claim.code || '—')}</td>
                        <td>${escapeHtml(getClientName(claim.client_id))}</td>
                        <td>${escapeHtml(getPolicyNumber(claim.policy_id))}</td>
                        <td>${escapeHtml(claim.type || '—')}</td>
                        <td>${escapeHtml(formatDate(claim.date))}</td>
                        <td><span class="badge badge-${statusTone(claim.status)}">${escapeHtml((claim.status || '—').charAt(0).toUpperCase() + (claim.status || '—').slice(1))}</span></td>
                        <td>${escapeHtml(getResponsibleName(claim.assigned_user_id))}</td>
                        <td>
                            <div class="claims-actions">
                                <button type="button" class="claims-action-btn claims-action-btn--primary" data-action="detail" data-id="${escapeHtml(claim.id)}">Ver detalle</button>
                                <button type="button" class="claims-action-btn" data-action="status" data-id="${escapeHtml(claim.id)}">Estado</button>
                            </div>
                        </td>
                    `;
                    tableBody.appendChild(tr);
                });
        };

        const findClaim = (id) => claimsState.find(item => item.id === id) || null;

        const getClaimObservations = (claimId) => {
            const claim = findClaim(claimId);
            const observationRows = observationsState.filter(item => item.claim_id === claimId);

            if (observationRows.length) {
                return observationRows
                    .slice()
                    .sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
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

        const getClaimDocuments = (claimId) => {
            return documentsState
                .filter(item => item.entity_id === claimId)
                .slice()
                .sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
        };

        const getClaimTimeline = (claimId) => {
            const claim = findClaim(claimId);
            const items = [];

            if (claim) {
                items.push({
                    id: `seed-open-${claimId}`,
                    claim_id: claimId,
                    title: 'Siniestro reportado',
                    description: claim.description || 'Se abrió el expediente del siniestro.',
                    created_at: claim.created_at || `${claim.date} 09:00:00`
                });
            }

            timelineState
                .filter(item => item.claim_id === claimId)
                .forEach(item => items.push(item));

            return items
                .slice()
                .sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
        };

        const upsertClaim = (claim) => {
            const index = claimsState.findIndex(item => item.id === claim.id);
            if (index >= 0) {
                claimsState[index] = claim;
            } else {
                claimsState.unshift(claim);
            }
        };

        const appendObservation = (observation) => {
            observationsState.unshift(observation);
        };

        const appendDocument = (document) => {
            documentsState.unshift(document);
        };

        const appendTimeline = (timeline) => {
            timelineState.unshift(timeline);
        };

        const resetCreateForm = () => {
            createForm.reset();
            document.getElementById('claim-date').value = new Date().toISOString().slice(0, 10);
            document.getElementById('claim-status').value = 'reportado';
        };

        const renderDetail = (claimId) => {
            const claim = findClaim(claimId);
            if (!claim) return;

            activeDetailClaimId = claimId;

            detailCodeBadge.textContent = claim.code || 'SIN-0000';
            detailTitle.textContent = claim.type || 'Siniestro';
            detailSubtitle.textContent = `${getClientName(claim.client_id)} · ${getPolicyNumber(claim.policy_id)}`;
            detailDescription.textContent = claim.description || 'Sin descripción registrada.';

            metaGrid.innerHTML = `
                <div class="claims-meta-item">
                    <strong>Cliente</strong>
                    <span>${escapeHtml(getClientName(claim.client_id))}</span>
                </div>
                <div class="claims-meta-item">
                    <strong>Póliza</strong>
                    <span>${escapeHtml(getPolicyNumber(claim.policy_id))}</span>
                </div>
                <div class="claims-meta-item">
                    <strong>Tipo</strong>
                    <span>${escapeHtml(claim.type || '—')}</span>
                </div>
                <div class="claims-meta-item">
                    <strong>Fecha</strong>
                    <span>${escapeHtml(formatDate(claim.date))}</span>
                </div>
                <div class="claims-meta-item">
                    <strong>Estado</strong>
                    <span>${escapeHtml((claim.status || '—').charAt(0).toUpperCase() + (claim.status || '—').slice(1))}</span>
                </div>
                <div class="claims-meta-item">
                    <strong>Responsable</strong>
                    <span>${escapeHtml(getResponsibleName(claim.assigned_user_id))}</span>
                </div>
            `;

            const timelineItems = getClaimTimeline(claimId);
            timelineList.innerHTML = '';
            if (!timelineItems.length) {
                timelineList.innerHTML = '<div class="claims-empty">No hay eventos registrados para este caso.</div>';
            } else {
                timelineItems.forEach((item) => {
                    const article = document.createElement('article');
                    article.className = 'timeline__item';
                    article.innerHTML = `
                        <h4>${escapeHtml(item.title || 'Evento')}</h4>
                        <p>${escapeHtml(item.description || '')}</p>
                        <small class="muted">${escapeHtml(formatDateTime(item.created_at || ''))}</small>
                    `;
                    timelineList.appendChild(article);
                });
            }

            const documentItems = getClaimDocuments(claimId);
            documentsList.innerHTML = '';
            if (!documentItems.length) {
                documentsList.innerHTML = '<div class="claims-empty">No hay documentos asociados.</div>';
            } else {
                documentItems.forEach((item) => {
                    const article = document.createElement('article');
                    article.className = 'claims-list-item';
                    article.innerHTML = `
                        <div class="claims-list-item__top">
                            <div>
                                <h5>${escapeHtml(item.original_name || 'Documento')}</h5>
                                <p>${escapeHtml(item.type || 'Archivo')}</p>
                            </div>
                            <small>${escapeHtml(formatDateTime(item.created_at || ''))}</small>
                        </div>
                        <p>Subido por ${escapeHtml(item.uploaded_by_name || 'Sistema')}</p>
                    `;
                    documentsList.appendChild(article);
                });
            }

            const observationItems = getClaimObservations(claimId);
            observationsList.innerHTML = '';
            if (!observationItems.length) {
                observationsList.innerHTML = '<div class="claims-empty">No hay observaciones registradas.</div>';
            } else {
                observationItems.forEach((item) => {
                    const article = document.createElement('article');
                    article.className = 'claims-list-item';
                    article.innerHTML = `
                        <div class="claims-list-item__top">
                            <div>
                                <h5>${escapeHtml(item.author_name || 'Sistema')}</h5>
                                <p>${escapeHtml(item.observation || '')}</p>
                            </div>
                            <small>${escapeHtml(formatDateTime(item.created_at || ''))}</small>
                        </div>
                    `;
                    observationsList.appendChild(article);
                });
            }
        };

        const openDetailModal = (claimId) => {
            renderDetail(claimId);
            DemoApp.openModal('claim-detail-modal');
        };

        const openStatusModal = (claimId) => {
            const claim = findClaim(claimId);
            if (!claim) return;

            statusClaimId.value = claimId;
            statusSelect.value = claim.status || 'reportado';
            statusNote.value = '';
            DemoApp.openModal('claim-status-modal');
        };

        const openObservationModal = (claimId) => {
            observationForm.reset();
            observationClaimId.value = claimId;
            DemoApp.openModal('claim-observation-modal');
        };

        const openDocumentModal = (claimId) => {
            documentForm.reset();
            documentClaimId.value = claimId;
            DemoApp.openModal('claim-document-modal');
        };

        btnNewClaim.addEventListener('click', () => {
            resetCreateForm();
            DemoApp.openModal('claim-create-modal');
        });

        [filterSearch, filterStatus, filterType, filterResponsible].forEach((element) => {
            element.addEventListener('input', renderTable);
            element.addEventListener('change', renderTable);
        });

        btnResetFilters.addEventListener('click', () => {
            filterSearch.value = '';
            filterStatus.value = '';
            filterType.value = '';
            filterResponsible.value = '';
            renderTable();
        });

        tableBody.addEventListener('click', (event) => {
            const button = event.target.closest('[data-action]');
            if (!button) return;

            const id = button.getAttribute('data-id');
            const action = button.getAttribute('data-action');

            if (action === 'detail') {
                openDetailModal(id);
                return;
            }

            if (action === 'status') {
                openStatusModal(id);
            }
        });

        btnDetailStatus.addEventListener('click', () => {
            if (!activeDetailClaimId) return;
            openStatusModal(activeDetailClaimId);
        });

        btnDetailObservation.addEventListener('click', () => {
            if (!activeDetailClaimId) return;
            openObservationModal(activeDetailClaimId);
        });

        btnDetailDocument.addEventListener('click', () => {
            if (!activeDetailClaimId) return;
            openDocumentModal(activeDetailClaimId);
        });

        createSubmit.addEventListener('click', async () => {
            const formData = new FormData(createForm);

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

            resetCreateForm();
        });

        statusSubmit.addEventListener('click', async () => {
            const formData = new FormData(statusForm);

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

            if (activeDetailClaimId === statusClaimId.value) {
                renderDetail(activeDetailClaimId);
            }

            DemoApp.closeModal('claim-status-modal');
            DemoApp.toast({
                title: response.title || 'Estado actualizado',
                message: response.message || 'El siniestro fue actualizado correctamente.',
                type: 'success'
            });

            statusNote.value = '';
        });

        observationSubmit.addEventListener('click', async () => {
            const formData = new FormData(observationForm);

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

            if (activeDetailClaimId === observationClaimId.value) {
                renderDetail(activeDetailClaimId);
            }

            DemoApp.closeModal('claim-observation-modal');
            DemoApp.toast({
                title: response.title || 'Observación agregada',
                message: response.message || 'La nota fue guardada correctamente.',
                type: 'success'
            });

            observationForm.reset();
        });

        documentSubmit.addEventListener('click', async () => {
            const formData = new FormData(documentForm);

            const response = await DemoApp.api(endpoint, {
                method: 'POST',
                body: formData
            });

            if (!response.success) {
                DemoApp.toast({
                    title: response.title || 'No se pudo adjuntar',
                    message: response.message || 'Verifica la información del documento.',
                    type: 'error'
                });
                return;
            }

            if (response.document) appendDocument(response.document);
            if (response.timeline) appendTimeline(response.timeline);

            if (activeDetailClaimId === documentClaimId.value) {
                renderDetail(activeDetailClaimId);
            }

            DemoApp.closeModal('claim-document-modal');
            DemoApp.toast({
                title: response.title || 'Documento adjuntado',
                message: response.message || 'El archivo demo fue agregado correctamente.',
                type: 'success'
            });

            documentForm.reset();
        });

        document.getElementById('claim-policy').addEventListener('change', (event) => {
            const policyId = event.target.value;
            const policy = policiesMap[policyId];
            if (!policy) return;
            document.getElementById('claim-client').value = policy.client_id || '';
            document.getElementById('claim-responsible').value = policy.assigned_executive_user_id || '';
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
        'subtitle' => 'Control gerencial de casos, responsables, documentos y evolución del expediente.',
    ]
);