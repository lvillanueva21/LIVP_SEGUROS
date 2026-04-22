<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';

demo_require_roles(['ejecutivo']);

$currentUser = demo_current_user();
$executiveId = $currentUser['id'] ?? '';

$policies = demo_filter_policies_by_executive(demo_store('policies', []), $executiveId);
$clients = demo_filter_clients_by_executive(demo_store('clients', []), $executiveId);
$insurers = array_values(demo_store('insurers', []));
$insuranceTypes = array_values(demo_store('insurance_types', []));

$clientMap = [];
foreach ($clients as $client) {
    $clientMap[$client['id']] = $client;
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
$totalActivePolicies = count(array_filter($policies, fn($p) => ($p['status'] ?? '') === 'activa'));

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
    $diff = (int)floor(($end - $today) / 86400);

    return $diff >= 0 && $diff <= 45;
}));

usort($policies, fn($a, $b) => strtotime((string)($b['created_at'] ?? '')) <=> strtotime((string)($a['created_at'] ?? '')));

ob_start();
?>
<style>
    .exec-policies-kpis {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        margin-bottom: 1rem;
    }

    .exec-policies-controls {
        display: grid;
        gap: 1rem;
        grid-template-columns: 1.2fr .85fr .85fr .85fr auto;
        align-items: end;
        margin-bottom: 1rem;
    }

    .exec-policies-actions {
        display: flex;
        flex-wrap: wrap;
        gap: .45rem;
    }

    .exec-policies-action-btn {
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

    .exec-policies-action-btn:hover {
        transform: translateY(-1px);
        border-color: rgba(79, 70, 229, .18);
        background: #f8fbff;
    }

    .exec-policies-action-btn--primary {
        background: rgba(79, 70, 229, .08);
        color: var(--primary);
        border-color: rgba(79, 70, 229, .12);
    }

    .exec-policies-empty {
        padding: 1.2rem;
        text-align: center;
        color: var(--text-soft);
    }

    .exec-policies-note {
        padding: .85rem 1rem;
        border-radius: 16px;
        border: 1px dashed rgba(100, 116, 139, .28);
        background: linear-gradient(180deg, #fbfdff 0%, #f7faff 100%);
        margin-bottom: 1rem;
    }

    .exec-policies-note strong {
        display: block;
        margin-bottom: .2rem;
    }

    .exec-policies-form-note {
        margin: 0 0 1rem;
        color: var(--text-soft);
        line-height: 1.5;
    }

    .exec-policies-helper {
        margin: .65rem 0 0;
        color: var(--text-soft);
        font-size: .88rem;
        line-height: 1.45;
    }

    @media (max-width: 1200px) {
        .exec-policies-kpis {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .exec-policies-controls {
            grid-template-columns: 1fr 1fr;
        }

        .exec-policies-controls .btn {
            width: 100%;
        }
    }

    @media (max-width: 760px) {
        .exec-policies-kpis,
        .exec-policies-controls {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="grid" style="gap: 1rem;">
    <section class="exec-policies-kpis">
        <article class="card kpi-card">
            <p class="kpi-card__label">Mis pólizas activas</p>
            <h3 class="kpi-card__value" id="kpi-active-policies"><?= demo_e((string)$totalActivePolicies) ?></h3>
            <p class="kpi-card__meta">Coberturas vigentes dentro de tu cartera comercial.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Vencen este mes</p>
            <h3 class="kpi-card__value" id="kpi-expiring-month"><?= demo_e((string)$expiringThisMonth) ?></h3>
            <p class="kpi-card__meta">Pólizas próximas a cierre dentro del mes actual.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Renovaciones próximas</p>
            <h3 class="kpi-card__value" id="kpi-renewals-soon"><?= demo_e((string)$renewalSoon) ?></h3>
            <p class="kpi-card__meta">Alertas tempranas para seguimiento comercial.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Primas demo</p>
            <h3 class="kpi-card__value" id="kpi-total-premium"><?= demo_e(demo_money($totalPremium)) ?></h3>
            <p class="kpi-card__meta">Monto total de primas en tu cartera actual.</p>
        </article>
    </section>

    <section class="card">
        <div class="card__header">
            <div>
                <h2 class="card__title">Mis pólizas</h2>
                <p class="card__subtitle">Gestión comercial de pólizas propias con alta manual y documento PDF simulado.</p>
            </div>

            <div class="exec-policies-actions">
                <button type="button" class="btn btn-secondary" id="btn-upload-pdf-global">Subir PDF</button>
                <button type="button" class="btn btn-primary" id="btn-new-policy">Nueva póliza</button>
            </div>
        </div>

        <div class="exec-policies-note">
            <strong>Solo cartera propia</strong>
            <span class="muted">Aquí solo podrás ver y operar pólizas asignadas a tu usuario ejecutivo. No puedes intervenir pólizas de otros ejecutivos ni modificar catálogos globales.</span>
        </div>

        <div class="exec-policies-controls">
            <div>
                <label class="form-label" for="policy-search">Buscar</label>
                <input class="input" id="policy-search" type="text" placeholder="Número, cliente, aseguradora o tipo">
            </div>

            <div>
                <label class="form-label" for="policy-insurer-filter">Aseguradora</label>
                <select class="select" id="policy-insurer-filter">
                    <option value="">Todas</option>
                    <?php foreach ($insurers as $insurer): ?>
                        <option value="<?= demo_e($insurer['id']) ?>"><?= demo_e($insurer['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="form-label" for="policy-type-filter">Tipo</label>
                <select class="select" id="policy-type-filter">
                    <option value="">Todos</option>
                    <?php foreach ($insuranceTypes as $type): ?>
                        <option value="<?= demo_e($type['id']) ?>"><?= demo_e($type['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="form-label" for="policy-status-filter">Estado</label>
                <select class="select" id="policy-status-filter">
                    <option value="">Todos</option>
                    <option value="activa">Activa</option>
                    <option value="pendiente">Pendiente</option>
                    <option value="vencida">Vencida</option>
                    <option value="anulada">Anulada</option>
                    <option value="renovada">Renovada</option>
                </select>
            </div>

            <div>
                <button type="button" class="btn btn-ghost" id="btn-reset-filters">Limpiar filtros</button>
            </div>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Número</th>
                        <th>Cliente</th>
                        <th>Aseguradora</th>
                        <th>Tipo</th>
                        <th>Vigencia</th>
                        <th>Prima</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="policies-table-body"></tbody>
            </table>
        </div>

        <div id="policies-empty-state" class="exec-policies-empty" hidden>
            No hay pólizas que coincidan con los filtros seleccionados.
        </div>
    </section>
</div>

<div class="modal" id="executive-policy-form-modal" hidden>
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__dialog">
        <div class="modal__header">
            <h3 id="executive-policy-form-title">Nueva póliza</h3>
            <button type="button" class="icon-btn" data-modal-close aria-label="Cerrar">✕</button>
        </div>
        <div class="modal__body">
            <p class="exec-policies-form-note" id="executive-policy-form-note">
                Registra una póliza manual para un cliente de tu cartera. Se generarán cuotas demo automáticamente.
            </p>

            <form id="executive-policy-form" class="form-grid form-grid--3">
                <input type="hidden" name="action" value="create">

                <div>
                    <label class="form-label" for="policy-number">Número de póliza</label>
                    <input class="input" type="text" id="policy-number" name="policy_number" placeholder="AU-2026-000811">
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
                    <label class="form-label" for="policy-premium">Prima total</label>
                    <input class="input" type="number" step="0.01" min="0" id="policy-premium" name="premium" placeholder="2500.00">
                </div>

                <div>
                    <label class="form-label" for="policy-start-date">Inicio de vigencia</label>
                    <input class="input" type="date" id="policy-start-date" name="start_date">
                </div>

                <div>
                    <label class="form-label" for="policy-end-date">Fin de vigencia</label>
                    <input class="input" type="date" id="policy-end-date" name="end_date">
                </div>

                <div style="grid-column: 1 / -1;">
                    <label class="form-label" for="policy-insured-item">Bien asegurado</label>
                    <input class="input" type="text" id="policy-insured-item" name="insured_item" placeholder="Descripción del bien o cobertura">
                </div>

                <div style="grid-column: 1 / -1;">
                    <label class="form-label" for="policy-notes">Observaciones</label>
                    <textarea class="textarea" id="policy-notes" name="notes" placeholder="Contexto comercial o nota operativa"></textarea>
                    <p class="exec-policies-helper">El ejecutivo no cambia catálogos globales desde aquí. Solo utiliza catálogos ya existentes.</p>
                </div>
            </form>
        </div>
        <div class="modal__footer">
            <button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button>
            <button type="button" class="btn btn-primary" id="executive-policy-form-submit">Guardar póliza</button>
        </div>
    </div>
</div>

<div class="modal" id="executive-policy-upload-modal" hidden>
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__dialog modal__dialog--sm">
        <div class="modal__header">
            <h3 id="executive-policy-upload-title">Subir PDF simulado</h3>
            <button type="button" class="icon-btn" data-modal-close aria-label="Cerrar">✕</button>
        </div>
        <div class="modal__body">
            <p class="exec-policies-form-note">
                Adjunta un documento demo a una póliza de tu cartera. No se sube un archivo real, solo se registra en la sesión.
            </p>

            <form id="executive-policy-upload-form" class="form-grid">
                <input type="hidden" name="action" value="upload_document">
                <input type="hidden" name="policy_id" id="upload-policy-id" value="">

                <div>
                    <label class="form-label" for="upload-policy-select">Póliza</label>
                    <select class="select" id="upload-policy-select" name="policy_id_select">
                        <option value="">Seleccionar</option>
                        <?php foreach ($policies as $policy): ?>
                            <option value="<?= demo_e($policy['id']) ?>">
                                <?= demo_e(($policy['policy_number'] ?? '—') . ' · ' . ($clientMap[$policy['client_id']]['name'] ?? 'Cliente')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label" for="upload-original-name">Nombre del archivo</label>
                    <input class="input" type="text" id="upload-original-name" name="original_name" placeholder="Poliza_Abril_2026.pdf">
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
            <button type="button" class="btn btn-primary" id="executive-policy-upload-submit">Adjuntar documento</button>
        </div>
    </div>
</div>

<script>
    (() => {
        let policiesState = <?= json_encode(array_values($policies), JSON_UNESCAPED_UNICODE) ?>;
        const clientsMap = <?= json_encode($clientMap, JSON_UNESCAPED_UNICODE) ?>;
        const insurerMap = <?= json_encode($insurerMap, JSON_UNESCAPED_UNICODE) ?>;
        const typeMap = <?= json_encode($typeMap, JSON_UNESCAPED_UNICODE) ?>;

        const endpoint = <?= json_encode(demo_url('ajax/ejecutivo-polizas.php'), JSON_UNESCAPED_UNICODE) ?>;
        const detailBase = <?= json_encode(demo_url('modules/ejecutivo/poliza-detalle.php?id='), JSON_UNESCAPED_UNICODE) ?>;

        const tableBody = document.getElementById('policies-table-body');
        const emptyState = document.getElementById('policies-empty-state');

        const searchInput = document.getElementById('policy-search');
        const insurerFilter = document.getElementById('policy-insurer-filter');
        const typeFilter = document.getElementById('policy-type-filter');
        const statusFilter = document.getElementById('policy-status-filter');
        const resetFiltersBtn = document.getElementById('btn-reset-filters');

        const formModalId = 'executive-policy-form-modal';
        const uploadModalId = 'executive-policy-upload-modal';
        const form = document.getElementById('executive-policy-form');
        const formSubmit = document.getElementById('executive-policy-form-submit');
        const uploadForm = document.getElementById('executive-policy-upload-form');
        const uploadSubmit = document.getElementById('executive-policy-upload-submit');
        const uploadPolicySelect = document.getElementById('upload-policy-select');
        const uploadPolicyId = document.getElementById('upload-policy-id');
        const uploadTitle = document.getElementById('executive-policy-upload-title');

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
            const date = new Date(`${value}T00:00:00`);
            if (Number.isNaN(date.getTime())) return value;
            return date.toLocaleDateString('es-PE');
        };

        const formatMoney = (value, currency = 'S/') => {
            const numeric = Number(value || 0);
            return `${currency} ${numeric.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        };

        const getClientName = (id) => clientsMap[id]?.name || 'Cliente';
        const getInsurerName = (id) => insurerMap[id]?.name || 'Aseguradora';
        const getTypeName = (id) => typeMap[id]?.name || 'Tipo';

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
                const end = new Date(`${p.end_date}T00:00:00`).getTime();
                const today = new Date(new Date().toISOString().slice(0, 10) + 'T00:00:00').getTime();
                const diff = Math.floor((end - today) / 86400000);
                return diff >= 0 && diff <= 45;
            }).length;
            const premium = policiesState.reduce((sum, p) => sum + Number(p.premium || 0), 0);

            kpiActive.textContent = String(activeCount);
            kpiExpiring.textContent = String(expiringMonth);
            kpiRenewals.textContent = String(renewalsSoon);
            kpiPremium.textContent = formatMoney(premium);
        };

        const getFilteredPolicies = () => {
            const term = searchInput.value.trim().toLowerCase();
            const insurer = insurerFilter.value;
            const type = typeFilter.value;
            const status = statusFilter.value;

            return policiesState.filter((policy) => {
                const haystack = [
                    policy.policy_number,
                    getClientName(policy.client_id),
                    getInsurerName(policy.insurer_id),
                    getTypeName(policy.insurance_type_id)
                ].join(' ').toLowerCase();

                return (!term || haystack.includes(term))
                    && (!insurer || policy.insurer_id === insurer)
                    && (!type || policy.insurance_type_id === type)
                    && (!status || policy.status === status);
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
                        <td>
                            <div class="exec-policies-actions">
                                <a class="exec-policies-action-btn exec-policies-action-btn--primary" href="${detailBase}${encodeURIComponent(policy.id)}">Ver ficha</a>
                                <button type="button" class="exec-policies-action-btn" data-action="upload" data-id="${escapeHtml(policy.id)}">Subir PDF</button>
                            </div>
                        </td>
                    `;
                    tableBody.appendChild(tr);
                });
        };

        const refreshUploadOptions = () => {
            const selected = uploadPolicySelect.value;
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

            if (selected) {
                uploadPolicySelect.value = selected;
            }
        };

        const openUploadModal = (policy = null) => {
            uploadForm.reset();
            uploadPolicyId.value = policy?.id || '';
            uploadPolicySelect.value = policy?.id || '';
            uploadTitle.textContent = policy ? `Subir PDF simulado · ${policy.policy_number}` : 'Subir PDF simulado';
            DemoApp.openModal(uploadModalId);
        };

        document.getElementById('btn-new-policy').addEventListener('click', () => {
            form.reset();
            document.getElementById('policy-status').value = 'activa';
            DemoApp.openModal(formModalId);
        });

        document.getElementById('btn-upload-pdf-global').addEventListener('click', () => {
            openUploadModal();
        });

        [searchInput, insurerFilter, typeFilter, statusFilter].forEach((element) => {
            element.addEventListener('input', renderTable);
            element.addEventListener('change', renderTable);
        });

        resetFiltersBtn.addEventListener('click', () => {
            searchInput.value = '';
            insurerFilter.value = '';
            typeFilter.value = '';
            statusFilter.value = '';
            renderTable();
        });

        tableBody.addEventListener('click', (event) => {
            const button = event.target.closest('[data-action]');
            if (!button) return;

            const action = button.getAttribute('data-action');
            const id = button.getAttribute('data-id');
            const policy = policiesState.find((item) => item.id === id);

            if (!policy) return;

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
                policiesState.unshift(response.policy);
            }

            renderKpis();
            renderTable();
            refreshUploadOptions();
            DemoApp.closeModal(formModalId);

            DemoApp.toast({
                title: response.title || 'Póliza creada',
                message: response.message || 'La póliza fue agregada correctamente a tu cartera.',
                type: 'success'
            });

            form.reset();
            document.getElementById('policy-status').value = 'activa';
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

            DemoApp.closeModal(uploadModalId);
            DemoApp.toast({
                title: response.title || 'Documento adjuntado',
                message: response.message || 'El PDF demo fue registrado correctamente.',
                type: 'success'
            });

            uploadForm.reset();
        });

        refreshUploadOptions();
        renderKpis();
        renderTable();
    })();
</script>
<?php
$content = ob_get_clean();

demo_render_internal_layout(
    'Mis pólizas',
    $content,
    [
        'breadcrumb' => ['Inicio', 'Pólizas'],
        'subtitle' => 'Gestión de pólizas propias con alta manual, vigencias y documentos simulados.',
    ]
);