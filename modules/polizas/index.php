<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';

demo_require_roles(['gerente', 'superadmin']);

$policies = demo_store('policies', []);
$clients = demo_store('clients', []);
$users = demo_store('users', []);
$insurers = demo_store('insurers', []);
$insuranceTypes = demo_store('insurance_types', []);
$documents = demo_store('documents', []);

$executives = array_values(array_filter($users, fn($u) => ($u['role'] ?? '') === 'ejecutivo'));

$clientMap = [];
foreach ($clients as $client) {
    $clientMap[$client['id']] = $client;
}

$executiveMap = [];
foreach ($executives as $exec) {
    $executiveMap[$exec['id']] = $exec;
}

$insurerMap = [];
foreach ($insurers as $insurer) {
    $insurerMap[$insurer['id']] = $insurer;
}

$typeMap = [];
foreach ($insuranceTypes as $type) {
    $typeMap[$type['id']] = $type;
}

$totalPremium = array_reduce($policies, fn($carry, $policy) => $carry + (float)($policy['premium'] ?? 0), 0.0);
$activePolicies = count(array_filter($policies, fn($p) => ($p['status'] ?? '') === 'activa'));

$currentMonth = date('Y-m');
$expiringThisMonth = count(array_filter($policies, function ($policy) use ($currentMonth) {
    return !empty($policy['end_date']) && str_starts_with((string)$policy['end_date'], $currentMonth);
}));

$renewalSoon = count(array_filter($policies, function ($policy) {
    if (empty($policy['end_date'])) {
        return false;
    }

    $end = strtotime((string)$policy['end_date']);
    $today = strtotime(date('Y-m-d'));
    $diff = (int) floor(($end - $today) / 86400);

    return $diff >= 0 && $diff <= 45;
}));

usort($policies, fn($a, $b) => strtotime((string)($b['created_at'] ?? '')) <=> strtotime((string)($a['created_at'] ?? '')));

ob_start();
?>
<style>
    .policies-toolbar {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        margin-bottom: 1rem;
    }

    .policies-controls {
        display: grid;
        gap: 1rem;
        grid-template-columns: 1.2fr repeat(4, minmax(0, .8fr)) auto;
        align-items: end;
        margin-bottom: 1rem;
    }

    .policies-actions {
        display: flex;
        flex-wrap: wrap;
        gap: .45rem;
    }

    .policies-action-btn {
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

    .policies-action-btn:hover {
        transform: translateY(-1px);
        border-color: rgba(79, 70, 229, .18);
        background: #f8fbff;
    }

    .policies-action-btn--primary {
        background: rgba(79, 70, 229, .08);
        color: var(--primary);
        border-color: rgba(79, 70, 229, .12);
    }

    .policies-inline-note {
        padding: .85rem 1rem;
        border-radius: 16px;
        border: 1px dashed rgba(100, 116, 139, .28);
        background: linear-gradient(180deg, #fbfdff 0%, #f7faff 100%);
        margin-bottom: 1rem;
    }

    .policies-inline-note strong {
        display: block;
        margin-bottom: .2rem;
    }

    .policies-empty {
        padding: 1.2rem;
        text-align: center;
        color: var(--text-soft);
    }

    .policies-form-note {
        margin: 0 0 1rem;
        color: var(--text-soft);
        line-height: 1.5;
    }

    .policies-helper {
        margin: .65rem 0 0;
        color: var(--text-soft);
        font-size: .88rem;
        line-height: 1.45;
    }

    .quick-view-grid {
        display: grid;
        gap: .9rem;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .quick-view-item {
        padding: .9rem 1rem;
        border-radius: 16px;
        border: 1px solid rgba(219, 227, 239, .85);
        background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
    }

    .quick-view-item strong {
        display: block;
        font-size: .78rem;
        color: var(--text-soft);
        text-transform: uppercase;
        letter-spacing: .05em;
        margin-bottom: .35rem;
    }

    .quick-view-item span {
        display: block;
        line-height: 1.45;
        overflow-wrap: anywhere;
    }

    @media (max-width: 1200px) {
        .policies-toolbar {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .policies-controls {
            grid-template-columns: 1fr 1fr;
        }

        .policies-controls .btn {
            width: 100%;
        }
    }

    @media (max-width: 760px) {
        .policies-toolbar,
        .policies-controls,
        .quick-view-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="grid" style="gap: 1rem;">
    <section class="policies-toolbar">
        <article class="card kpi-card">
            <p class="kpi-card__label">Pólizas activas</p>
            <h3 class="kpi-card__value" id="kpi-active-policies"><?= demo_e((string)$activePolicies) ?></h3>
            <p class="kpi-card__meta">Coberturas vigentes y listas para operación.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Vencen este mes</p>
            <h3 class="kpi-card__value" id="kpi-expiring-month"><?= demo_e((string)$expiringThisMonth) ?></h3>
            <p class="kpi-card__meta">Control de cartera con foco de renovación mensual.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Renovaciones próximas</p>
            <h3 class="kpi-card__value" id="kpi-renewals-soon"><?= demo_e((string)$renewalSoon) ?></h3>
            <p class="kpi-card__meta">Pólizas dentro del siguiente rango comercial de seguimiento.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Prima total demo</p>
            <h3 class="kpi-card__value" id="kpi-total-premium"><?= demo_e(demo_money($totalPremium)) ?></h3>
            <p class="kpi-card__meta">Suma estimada desde todas las pólizas cargadas en la sesión.</p>
        </article>
    </section>

    <section class="card">
        <div class="card__header">
            <div>
                <h2 class="card__title">Listado de pólizas</h2>
                <p class="card__subtitle">Control gerencial de pólizas, primas, vigencias y estado operativo.</p>
            </div>

            <div class="policies-actions">
                <button type="button" class="btn btn-secondary" id="btn-upload-pdf-global">Subir PDF</button>
                <button type="button" class="btn btn-primary" id="btn-new-policy">Nueva póliza</button>
            </div>
        </div>

        <div class="policies-inline-note">
            <strong>Flujo demo activo</strong>
            <span class="muted">Puedes crear pólizas manuales, adjuntar PDF simulado, ver ficha detallada, editar, renovar y revisar datos clave sin usar base de datos real.</span>
        </div>

        <div class="policies-controls">
            <div>
                <label class="form-label" for="policy-filter-search">Buscar</label>
                <input class="input" id="policy-filter-search" type="text" placeholder="Número, cliente, aseguradora o ejecutivo">
            </div>

            <div>
                <label class="form-label" for="policy-filter-insurer">Aseguradora</label>
                <select class="select" id="policy-filter-insurer">
                    <option value="">Todas</option>
                    <?php foreach ($insurers as $insurer): ?>
                        <option value="<?= demo_e($insurer['id']) ?>"><?= demo_e($insurer['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="form-label" for="policy-filter-type">Tipo de seguro</label>
                <select class="select" id="policy-filter-type">
                    <option value="">Todos</option>
                    <?php foreach ($insuranceTypes as $type): ?>
                        <option value="<?= demo_e($type['id']) ?>"><?= demo_e($type['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="form-label" for="policy-filter-status">Estado</label>
                <select class="select" id="policy-filter-status">
                    <option value="">Todos</option>
                    <option value="activa">Activa</option>
                    <option value="pendiente">Pendiente</option>
                    <option value="vencida">Vencida</option>
                    <option value="anulada">Anulada</option>
                    <option value="renovada">Renovada</option>
                </select>
            </div>

            <div>
                <label class="form-label" for="policy-filter-executive">Ejecutivo</label>
                <select class="select" id="policy-filter-executive">
                    <option value="">Todos</option>
                    <?php foreach ($executives as $exec): ?>
                        <option value="<?= demo_e($exec['id']) ?>"><?= demo_e($exec['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <button type="button" class="btn btn-ghost" id="btn-reset-policy-filters">Limpiar filtros</button>
            </div>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Número de póliza</th>
                        <th>Cliente</th>
                        <th>Aseguradora</th>
                        <th>Tipo de seguro</th>
                        <th>Vigencia</th>
                        <th>Prima</th>
                        <th>Estado</th>
                        <th>Ejecutivo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="policies-table-body"></tbody>
            </table>
        </div>

        <div id="policies-empty-state" class="policies-empty" hidden>
            No hay pólizas que coincidan con los filtros seleccionados.
        </div>
    </section>
</div>

<div class="modal" id="policy-form-modal" hidden>
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__dialog">
        <div class="modal__header">
            <h3 id="policy-form-title">Nueva póliza</h3>
            <button type="button" class="icon-btn" data-modal-close aria-label="Cerrar">✕</button>
        </div>
        <div class="modal__body">
            <p class="policies-form-note" id="policy-form-note">
                Registra una póliza de forma manual. El cronograma de cuotas demo se generará automáticamente.
            </p>

            <form id="policy-form" class="form-grid form-grid--3">
                <input type="hidden" name="action" id="policy-form-action" value="create">
                <input type="hidden" name="policy_id" id="policy-id" value="">

                <div>
                    <label class="form-label" for="policy-number">Número de póliza</label>
                    <input class="input" type="text" id="policy-number" name="policy_number" placeholder="AU-2026-000451">
                </div>

                <div>
                    <label class="form-label" for="policy-client">Cliente</label>
                    <select class="select" id="policy-client" name="client_id">
                        <option value="">Seleccionar</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= demo_e($client['id']) ?>"><?= demo_e($client['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label" for="policy-executive">Ejecutivo</label>
                    <select class="select" id="policy-executive" name="assigned_executive_user_id">
                        <option value="">Seleccionar</option>
                        <?php foreach ($executives as $exec): ?>
                            <option value="<?= demo_e($exec['id']) ?>"><?= demo_e($exec['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label" for="policy-insurer">Aseguradora</label>
                    <select class="select" id="policy-insurer" name="insurer_id">
                        <option value="">Seleccionar</option>
                        <?php foreach ($insurers as $insurer): ?>
                            <option value="<?= demo_e($insurer['id']) ?>"><?= demo_e($insurer['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label" for="policy-type">Tipo de seguro</label>
                    <select class="select" id="policy-type" name="insurance_type_id">
                        <option value="">Seleccionar</option>
                        <?php foreach ($insuranceTypes as $type): ?>
                            <option value="<?= demo_e($type['id']) ?>"><?= demo_e($type['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label" for="policy-status">Estado</label>
                    <select class="select" id="policy-status" name="status">
                        <option value="activa">Activa</option>
                        <option value="pendiente">Pendiente</option>
                        <option value="vencida">Vencida</option>
                        <option value="anulada">Anulada</option>
                    </select>
                </div>

                <div>
                    <label class="form-label" for="policy-start-date">Inicio de vigencia</label>
                    <input class="input" type="date" id="policy-start-date" name="start_date">
                </div>

                <div>
                    <label class="form-label" for="policy-end-date">Fin de vigencia</label>
                    <input class="input" type="date" id="policy-end-date" name="end_date">
                </div>

                <div>
                    <label class="form-label" for="policy-premium">Prima total</label>
                    <input class="input" type="number" step="0.01" min="0" id="policy-premium" name="premium" placeholder="2500.00">
                </div>

                <div style="grid-column: 1 / -1;">
                    <label class="form-label" for="policy-insured-item">Bien asegurado</label>
                    <input class="input" type="text" id="policy-insured-item" name="insured_item" placeholder="Descripción del vehículo, inmueble o cobertura">
                </div>

                <div style="grid-column: 1 / -1;">
                    <label class="form-label" for="policy-notes">Observaciones</label>
                    <textarea class="textarea" id="policy-notes" name="notes" placeholder="Notas comerciales u operativas de la póliza"></textarea>
                    <p class="policies-helper">Se simularán 12 cuotas iguales para esta póliza dentro del store temporal.</p>
                </div>
            </form>
        </div>
        <div class="modal__footer">
            <button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button>
            <button type="button" class="btn btn-primary" id="policy-form-submit">Guardar póliza</button>
        </div>
    </div>
</div>

<div class="modal" id="policy-upload-modal" hidden>
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__dialog modal__dialog--sm">
        <div class="modal__header">
            <h3 id="policy-upload-title">Subir PDF simulado</h3>
            <button type="button" class="icon-btn" data-modal-close aria-label="Cerrar">✕</button>
        </div>
        <div class="modal__body">
            <p class="policies-form-note">Adjunta un documento demo a una póliza existente. No se subirá un archivo real, pero se registrará el documento en la sesión.</p>

            <form id="policy-upload-form" class="form-grid">
                <input type="hidden" name="action" value="upload_document">
                <input type="hidden" name="policy_id" id="upload-policy-id" value="">

                <div>
                    <label class="form-label" for="upload-policy-select">Póliza</label>
                    <select class="select" id="upload-policy-select" name="policy_id_select">
                        <option value="">Seleccionar</option>
                        <?php foreach ($policies as $policy): ?>
                            <option value="<?= demo_e($policy['id']) ?>"><?= demo_e(($policy['policy_number'] ?? '—') . ' · ' . ($clientMap[$policy['client_id']]['name'] ?? 'Cliente')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label" for="upload-original-name">Nombre del archivo</label>
                    <input class="input" type="text" id="upload-original-name" name="original_name" placeholder="Poliza Renovada Abril 2026.pdf">
                </div>

                <div>
                    <label class="form-label" for="upload-type">Tipo de documento</label>
                    <select class="select" id="upload-type" name="document_type">
                        <option value="Póliza PDF">Póliza PDF</option>
                        <option value="Anexo">Anexo</option>
                        <option value="Endoso">Endoso</option>
                        <option value="Cotización">Cotización</option>
                    </select>
                </div>
            </form>
        </div>
        <div class="modal__footer">
            <button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button>
            <button type="button" class="btn btn-primary" id="policy-upload-submit">Adjuntar documento</button>
        </div>
    </div>
</div>

<script>
    (() => {
        let policiesState = <?= json_encode(array_values($policies), JSON_UNESCAPED_UNICODE) ?>;
        const clientsMap = <?= json_encode($clientMap, JSON_UNESCAPED_UNICODE) ?>;
        const executiveMap = <?= json_encode($executiveMap, JSON_UNESCAPED_UNICODE) ?>;
        const insurerMap = <?= json_encode($insurerMap, JSON_UNESCAPED_UNICODE) ?>;
        const typeMap = <?= json_encode($typeMap, JSON_UNESCAPED_UNICODE) ?>;
        let documentsState = <?= json_encode(array_values($documents), JSON_UNESCAPED_UNICODE) ?>;

        const endpoint = <?= json_encode(demo_url('ajax/polizas.php'), JSON_UNESCAPED_UNICODE) ?>;
        const detailBase = <?= json_encode(demo_url('modules/polizas/detalle.php?id='), JSON_UNESCAPED_UNICODE) ?>;

        const tableBody = document.getElementById('policies-table-body');
        const emptyState = document.getElementById('policies-empty-state');

        const filterSearch = document.getElementById('policy-filter-search');
        const filterInsurer = document.getElementById('policy-filter-insurer');
        const filterType = document.getElementById('policy-filter-type');
        const filterStatus = document.getElementById('policy-filter-status');
        const filterExecutive = document.getElementById('policy-filter-executive');
        const btnResetFilters = document.getElementById('btn-reset-policy-filters');

        const btnNewPolicy = document.getElementById('btn-new-policy');
        const btnUploadGlobal = document.getElementById('btn-upload-pdf-global');

        const formModalId = 'policy-form-modal';
        const uploadModalId = 'policy-upload-modal';

        const form = document.getElementById('policy-form');
        const formTitle = document.getElementById('policy-form-title');
        const formNote = document.getElementById('policy-form-note');
        const formAction = document.getElementById('policy-form-action');
        const formSubmit = document.getElementById('policy-form-submit');

        const uploadForm = document.getElementById('policy-upload-form');
        const uploadPolicyId = document.getElementById('upload-policy-id');
        const uploadPolicySelect = document.getElementById('upload-policy-select');
        const uploadTitle = document.getElementById('policy-upload-title');
        const uploadSubmit = document.getElementById('policy-upload-submit');

        const kpiActive = document.getElementById('kpi-active-policies');
        const kpiExpiring = document.getElementById('kpi-expiring-month');
        const kpiRenewals = document.getElementById('kpi-renewals-soon');
        const kpiPremium = document.getElementById('kpi-total-premium');

        const escapeHtml = (value) => String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');

        const formatDate = (value) => {
            if (!value) return '—';
            const date = new Date(value + 'T00:00:00');
            if (Number.isNaN(date.getTime())) return value;
            return date.toLocaleDateString('es-PE');
        };

        const formatMoney = (value, currency = 'S/') => {
            const numeric = Number(value || 0);
            return `${currency} ${numeric.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        };

        const getClientName = (clientId) => clientsMap[clientId]?.name || 'Cliente no encontrado';
        const getExecutiveName = (execId) => executiveMap[execId]?.full_name || 'Sin asignar';
        const getInsurerName = (insurerId) => insurerMap[insurerId]?.name || 'Aseguradora';
        const getTypeName = (typeId) => typeMap[typeId]?.name || 'Tipo';

        const badgeTone = (status) => ({
            activa: 'success',
            pendiente: 'warning',
            vencida: 'danger',
            anulada: 'danger',
            renovada: 'info'
        }[status] || 'neutral');

        const renderKpis = () => {
            const activeCount = policiesState.filter(p => p.status === 'activa').length;
            const month = new Date().toISOString().slice(0, 7);
            const expiringMonth = policiesState.filter(p => (p.end_date || '').startsWith(month)).length;
            const renewalsSoon = policiesState.filter((p) => {
                if (!p.end_date) return false;
                const end = new Date(p.end_date + 'T00:00:00').getTime();
                const today = new Date(new Date().toISOString().slice(0, 10) + 'T00:00:00').getTime();
                const diff = Math.floor((end - today) / 86400000);
                return diff >= 0 && diff <= 45;
            }).length;
            const totalPremium = policiesState.reduce((sum, p) => sum + Number(p.premium || 0), 0);

            kpiActive.textContent = String(activeCount);
            kpiExpiring.textContent = String(expiringMonth);
            kpiRenewals.textContent = String(renewalsSoon);
            kpiPremium.textContent = formatMoney(totalPremium);
        };

        const getFilteredPolicies = () => {
            const term = filterSearch.value.trim().toLowerCase();
            const insurer = filterInsurer.value;
            const type = filterType.value;
            const status = filterStatus.value;
            const executive = filterExecutive.value;

            return policiesState.filter((policy) => {
                const haystack = [
                    policy.policy_number,
                    getClientName(policy.client_id),
                    getInsurerName(policy.insurer_id),
                    getExecutiveName(policy.assigned_executive_user_id),
                    getTypeName(policy.insurance_type_id)
                ].join(' ').toLowerCase();

                return (!term || haystack.includes(term))
                    && (!insurer || policy.insurer_id === insurer)
                    && (!type || policy.insurance_type_id === type)
                    && (!status || policy.status === status)
                    && (!executive || (policy.assigned_executive_user_id || '') === executive);
            });
        };

        const renderTable = () => {
            const rows = getFilteredPolicies();
            tableBody.innerHTML = '';

            if (!rows.length) {
                emptyState.hidden = false;
                return;
            }

            emptyState.hidden = true;

            rows
                .slice()
                .sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0))
                .forEach((policy) => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${escapeHtml(policy.policy_number || '—')}</td>
                        <td>${escapeHtml(getClientName(policy.client_id))}</td>
                        <td>${escapeHtml(getInsurerName(policy.insurer_id))}</td>
                        <td><span class="badge badge-info">${escapeHtml(getTypeName(policy.insurance_type_id))}</span></td>
                        <td>${escapeHtml(formatDate(policy.start_date))} al ${escapeHtml(formatDate(policy.end_date))}</td>
                        <td>${escapeHtml(formatMoney(policy.premium, policy.currency || 'S/'))}</td>
                        <td><span class="badge badge-${badgeTone(policy.status)}">${escapeHtml((policy.status || '—').charAt(0).toUpperCase() + (policy.status || '—').slice(1))}</span></td>
                        <td>${escapeHtml(getExecutiveName(policy.assigned_executive_user_id))}</td>
                        <td>
                            <div class="policies-actions">
                                <a class="policies-action-btn policies-action-btn--primary" href="${detailBase}${encodeURIComponent(policy.id)}">Ver ficha</a>
                                <button type="button" class="policies-action-btn" data-action="quick-view" data-id="${escapeHtml(policy.id)}">Vista rápida</button>
                                <button type="button" class="policies-action-btn" data-action="edit" data-id="${escapeHtml(policy.id)}">Editar</button>
                                <button type="button" class="policies-action-btn" data-action="upload" data-id="${escapeHtml(policy.id)}">Subir PDF</button>
                            </div>
                        </td>
                    `;
                    tableBody.appendChild(tr);
                });
        };

        const refreshUploadOptions = () => {
            const current = uploadPolicySelect.value;
            uploadPolicySelect.innerHTML = '<option value="">Seleccionar</option>';

            policiesState
                .slice()
                .sort((a, b) => String(a.policy_number || '').localeCompare(String(b.policy_number || '')))
                .forEach((policy) => {
                    const option = document.createElement('option');
                    option.value = policy.id;
                    option.textContent = `${policy.policy_number || '—'} · ${getClientName(policy.client_id)}`;
                    uploadPolicySelect.appendChild(option);
                });

            if (current) {
                uploadPolicySelect.value = current;
            }
        };

        const resetForm = () => {
            form.reset();
            formAction.value = 'create';
            document.getElementById('policy-id').value = '';
            formTitle.textContent = 'Nueva póliza';
            formNote.textContent = 'Registra una póliza de forma manual. El cronograma de cuotas demo se generará automáticamente.';
            document.getElementById('policy-status').value = 'activa';
        };

        const upsertPolicy = (policy) => {
            const index = policiesState.findIndex(item => item.id === policy.id);
            if (index >= 0) {
                policiesState[index] = policy;
            } else {
                policiesState.unshift(policy);
            }
        };

        const openCreateModal = () => {
            resetForm();
            DemoApp.openModal(formModalId);
        };

        const openEditModal = (policy) => {
            resetForm();
            formAction.value = 'edit';
            document.getElementById('policy-id').value = policy.id || '';
            document.getElementById('policy-number').value = policy.policy_number || '';
            document.getElementById('policy-client').value = policy.client_id || '';
            document.getElementById('policy-executive').value = policy.assigned_executive_user_id || '';
            document.getElementById('policy-insurer').value = policy.insurer_id || '';
            document.getElementById('policy-type').value = policy.insurance_type_id || '';
            document.getElementById('policy-status').value = policy.status || 'activa';
            document.getElementById('policy-start-date').value = policy.start_date || '';
            document.getElementById('policy-end-date').value = policy.end_date || '';
            document.getElementById('policy-premium').value = policy.premium || '';
            document.getElementById('policy-insured-item').value = policy.insured_item || '';
            document.getElementById('policy-notes').value = policy.notes || '';
            formTitle.textContent = 'Editar póliza';
            formNote.textContent = 'Actualiza los datos principales de la póliza dentro de esta sesión demo.';
            DemoApp.openModal(formModalId);
        };

        const openUploadModal = (policy = null) => {
            uploadForm.reset();
            uploadPolicyId.value = policy?.id || '';
            uploadPolicySelect.value = policy?.id || '';
            uploadTitle.textContent = policy ? `Subir PDF simulado · ${policy.policy_number}` : 'Subir PDF simulado';
            DemoApp.openModal(uploadModalId);
        };

        const showQuickView = (policy) => {
            const title = document.getElementById('generic-modal-title');
            const body = document.getElementById('generic-modal-body');
            const attachedDocs = documentsState.filter(doc => doc.entity_type === 'policy' && doc.entity_id === policy.id);

            title.textContent = `Vista rápida · ${policy.policy_number || 'Póliza'}`;
            body.innerHTML = `
                <div class="quick-view-grid">
                    <div class="quick-view-item">
                        <strong>Cliente</strong>
                        <span>${escapeHtml(getClientName(policy.client_id))}</span>
                    </div>
                    <div class="quick-view-item">
                        <strong>Aseguradora</strong>
                        <span>${escapeHtml(getInsurerName(policy.insurer_id))}</span>
                    </div>
                    <div class="quick-view-item">
                        <strong>Tipo de seguro</strong>
                        <span>${escapeHtml(getTypeName(policy.insurance_type_id))}</span>
                    </div>
                    <div class="quick-view-item">
                        <strong>Estado</strong>
                        <span>${escapeHtml((policy.status || '—').charAt(0).toUpperCase() + (policy.status || '—').slice(1))}</span>
                    </div>
                    <div class="quick-view-item">
                        <strong>Vigencia</strong>
                        <span>${escapeHtml(formatDate(policy.start_date))} al ${escapeHtml(formatDate(policy.end_date))}</span>
                    </div>
                    <div class="quick-view-item">
                        <strong>Prima</strong>
                        <span>${escapeHtml(formatMoney(policy.premium, policy.currency || 'S/'))}</span>
                    </div>
                    <div class="quick-view-item">
                        <strong>Ejecutivo</strong>
                        <span>${escapeHtml(getExecutiveName(policy.assigned_executive_user_id))}</span>
                    </div>
                    <div class="quick-view-item">
                        <strong>Documentos</strong>
                        <span>${escapeHtml(String(attachedDocs.length))} documento(s)</span>
                    </div>
                </div>
                <div class="panel mt-2">
                    <strong>Bien asegurado</strong>
                    <p class="mt-1">${escapeHtml(policy.insured_item || 'No especificado')}</p>
                </div>
                <div class="panel mt-2">
                    <strong>Observaciones</strong>
                    <p class="mt-1">${escapeHtml(policy.notes || 'Sin observaciones registradas.')}</p>
                </div>
            `;

            DemoApp.openModal('generic-modal');
        };

        btnNewPolicy.addEventListener('click', openCreateModal);
        btnUploadGlobal.addEventListener('click', () => openUploadModal());

        [filterSearch, filterInsurer, filterType, filterStatus, filterExecutive].forEach((element) => {
            element.addEventListener('input', renderTable);
            element.addEventListener('change', renderTable);
        });

        btnResetFilters.addEventListener('click', () => {
            filterSearch.value = '';
            filterInsurer.value = '';
            filterType.value = '';
            filterStatus.value = '';
            filterExecutive.value = '';
            renderTable();
        });

        tableBody.addEventListener('click', (event) => {
            const button = event.target.closest('[data-action]');
            if (!button) return;

            const id = button.getAttribute('data-id');
            const action = button.getAttribute('data-action');
            const policy = policiesState.find(item => item.id === id);

            if (!policy) return;

            if (action === 'quick-view') {
                showQuickView(policy);
                return;
            }

            if (action === 'edit') {
                openEditModal(policy);
                return;
            }

            if (action === 'upload') {
                openUploadModal(policy);
            }
        });

        formSubmit.addEventListener('click', async () => {
            const formData = new FormData(form);

            const response = await DemoApp.api(endpoint, {
                method: 'POST',
                body: formData
            });

            if (!response.success) {
                DemoApp.toast({
                    title: response.title || 'No se pudo guardar',
                    message: response.message || 'Verifica la información ingresada.',
                    type: 'error'
                });
                return;
            }

            if (response.policy) {
                upsertPolicy(response.policy);
            }

            renderKpis();
            renderTable();
            refreshUploadOptions();
            DemoApp.closeModal(formModalId);

            DemoApp.toast({
                title: response.title || 'Póliza guardada',
                message: response.message || 'La póliza demo se guardó correctamente.',
                type: 'success'
            });

            resetForm();
        });

        uploadSubmit.addEventListener('click', async () => {
            const formData = new FormData(uploadForm);
            const selected = uploadPolicySelect.value || uploadPolicyId.value;

            formData.set('policy_id', selected);

            const response = await DemoApp.api(endpoint, {
                method: 'POST',
                body: formData
            });

            if (!response.success) {
                DemoApp.toast({
                    title: response.title || 'No se pudo adjuntar',
                    message: response.message || 'Verifica la información ingresada.',
                    type: 'error'
                });
                return;
            }

            if (response.document) {
                documentsState.unshift(response.document);
            }

            DemoApp.closeModal(uploadModalId);
            DemoApp.toast({
                title: response.title || 'Documento adjuntado',
                message: response.message || 'El documento demo se agregó correctamente.',
                type: 'success'
            });
            uploadForm.reset();
        });

        resetForm();
        renderKpis();
        renderTable();
        refreshUploadOptions();
    })();
</script>
<?php
$content = ob_get_clean();

demo_render_internal_layout(
    'Pólizas',
    $content,
    [
        'breadcrumb' => ['Inicio', 'Pólizas'],
        'subtitle' => 'Vista gerencial de cartera, primas, vigencias y renovaciones dentro del entorno demo.',
    ]
);