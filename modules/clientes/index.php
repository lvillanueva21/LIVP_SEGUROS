<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';

demo_require_roles(['gerente', 'superadmin']);

$clients = demo_store('clients', []);
$policies = demo_store('policies', []);
$users = demo_store('users', []);

$executives = array_values(array_filter($users, fn($u) => ($u['role'] ?? '') === 'ejecutivo'));

usort($clients, fn($a, $b) => strcmp(($a['name'] ?? ''), ($b['name'] ?? '')));
usort($executives, fn($a, $b) => strcmp(($a['full_name'] ?? ''), ($b['full_name'] ?? '')));

$policyCounts = [];
$activePolicyCounts = [];
foreach ($policies as $policy) {
    $clientId = $policy['client_id'] ?? '';
    if ($clientId === '') {
        continue;
    }

    $policyCounts[$clientId] = ($policyCounts[$clientId] ?? 0) + 1;
    if (($policy['status'] ?? '') === 'activa') {
        $activePolicyCounts[$clientId] = ($activePolicyCounts[$clientId] ?? 0) + 1;
    }
}

$totalClients = count($clients);
$totalActiveClients = count(array_filter($clients, fn($c) => ($c['status'] ?? '') === 'activo'));
$totalWithActivePolicy = count(array_filter($clients, fn($c) => ($activePolicyCounts[$c['id']] ?? 0) > 0));
$totalWithoutPortal = count(array_filter($clients, fn($c) => empty($c['has_portal_access'])));

$executiveMap = [];
foreach ($executives as $exec) {
    $executiveMap[$exec['id']] = $exec['full_name'];
}

ob_start();
?>
<style>
    .clients-toolbar {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        margin-bottom: 1rem;
    }

    .clients-controls {
        display: grid;
        gap: 1rem;
        grid-template-columns: 1.1fr .8fr .8fr .8fr auto;
        align-items: end;
        margin-bottom: 1rem;
    }

    .clients-actions {
        display: flex;
        flex-wrap: wrap;
        gap: .45rem;
    }

    .clients-action-btn {
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

    .clients-action-btn:hover {
        transform: translateY(-1px);
        border-color: rgba(79, 70, 229, .18);
        background: #f8fbff;
    }

    .clients-action-btn--primary {
        background: rgba(79, 70, 229, .08);
        color: var(--primary);
        border-color: rgba(79, 70, 229, .12);
    }

    .clients-inline-note {
        padding: .85rem 1rem;
        border-radius: 16px;
        border: 1px dashed rgba(100, 116, 139, .28);
        background: linear-gradient(180deg, #fbfdff 0%, #f7faff 100%);
        margin-bottom: 1rem;
    }

    .clients-inline-note strong {
        display: block;
        margin-bottom: .2rem;
    }

    .clients-empty {
        padding: 1.2rem;
        text-align: center;
        color: var(--text-soft);
    }

    .portal-pill {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        min-height: 30px;
        padding: .35rem .7rem;
        border-radius: 999px;
        background: rgba(14, 165, 164, .1);
        color: var(--secondary);
        font-size: .8rem;
        font-weight: 700;
    }

    .clients-form-note {
        margin: 0 0 1rem;
        color: var(--text-soft);
        line-height: 1.5;
    }

    .clients-secondary-note {
        margin: .6rem 0 0;
        color: var(--text-soft);
        font-size: .88rem;
        line-height: 1.45;
    }

    @media (max-width: 1180px) {
        .clients-toolbar {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .clients-controls {
            grid-template-columns: 1fr 1fr;
        }

        .clients-controls .btn {
            width: 100%;
        }
    }

    @media (max-width: 720px) {
        .clients-toolbar,
        .clients-controls {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="grid" style="gap: 1rem;">
    <section class="clients-toolbar">
        <article class="card kpi-card">
            <p class="kpi-card__label">Total clientes</p>
            <h3 class="kpi-card__value" id="kpi-total-clients"><?= demo_e((string)$totalClients) ?></h3>
            <p class="kpi-card__meta">Registros comerciales dentro del store demo.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Activos</p>
            <h3 class="kpi-card__value" id="kpi-active-clients"><?= demo_e((string)$totalActiveClients) ?></h3>
            <p class="kpi-card__meta">Clientes operativos y visibles para gestión diaria.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Con póliza activa</p>
            <h3 class="kpi-card__value" id="kpi-with-policy"><?= demo_e((string)$totalWithActivePolicy) ?></h3>
            <p class="kpi-card__meta">Cartera con al menos una cobertura vigente.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Sin acceso portal</p>
            <h3 class="kpi-card__value" id="kpi-without-portal"><?= demo_e((string)$totalWithoutPortal) ?></h3>
            <p class="kpi-card__meta">Oportunidad para habilitar autogestión del cliente.</p>
        </article>
    </section>

    <section class="card">
        <div class="card__header">
            <div>
                <h2 class="card__title">Listado de clientes</h2>
                <p class="card__subtitle">Gestión comercial y operativa de personas y empresas dentro del demo.</p>
            </div>
            <button type="button" class="btn btn-primary" id="btn-new-client">Nuevo cliente</button>
        </div>

        <div class="clients-inline-note">
            <strong>Flujo demo activo</strong>
            <span class="muted">
                Desde esta pantalla puedes crear, editar, habilitar acceso portal y navegar a la ficha completa del cliente.
            </span>
        </div>

        <div class="clients-controls">
            <div>
                <label class="form-label" for="client-filter-search">Buscar</label>
                <input class="input" id="client-filter-search" type="text" placeholder="Nombre, documento, correo o teléfono">
            </div>

            <div>
                <label class="form-label" for="client-filter-executive">Ejecutivo</label>
                <select class="select" id="client-filter-executive">
                    <option value="">Todos</option>
                    <?php foreach ($executives as $exec): ?>
                        <option value="<?= demo_e($exec['id']) ?>"><?= demo_e($exec['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="form-label" for="client-filter-status">Estado</label>
                <select class="select" id="client-filter-status">
                    <option value="">Todos</option>
                    <option value="activo">Activo</option>
                    <option value="inactivo">Inactivo</option>
                </select>
            </div>

            <div>
                <label class="form-label" for="client-filter-type">Tipo</label>
                <select class="select" id="client-filter-type">
                    <option value="">Todos</option>
                    <option value="persona">Persona</option>
                    <option value="empresa">Empresa</option>
                </select>
            </div>

            <div>
                <button type="button" class="btn btn-ghost" id="btn-reset-client-filters">Limpiar filtros</button>
            </div>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nombre o razón social</th>
                        <th>Documento</th>
                        <th>Tipo</th>
                        <th>Teléfono</th>
                        <th>Email</th>
                        <th>Ejecutivo asignado</th>
                        <th>Pólizas activas</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="clients-table-body"></tbody>
            </table>
        </div>

        <div id="clients-empty-state" class="clients-empty" hidden>
            No hay clientes que coincidan con los filtros seleccionados.
        </div>
    </section>
</div>

<div class="modal" id="client-form-modal" hidden>
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__dialog">
        <div class="modal__header">
            <h3 id="client-form-title">Nuevo cliente</h3>
            <button type="button" class="icon-btn" data-modal-close aria-label="Cerrar">✕</button>
        </div>
        <div class="modal__body">
            <p class="clients-form-note" id="client-form-note">
                Registra una persona o empresa. La ficha se guardará de forma simulada dentro de la sesión actual.
            </p>

            <form id="client-form" class="form-grid form-grid--3">
                <input type="hidden" name="action" id="client-form-action" value="create">
                <input type="hidden" name="client_id" id="client-id" value="">

                <div>
                    <label class="form-label" for="client-type">Tipo</label>
                    <select class="select" id="client-type" name="type">
                        <option value="persona">Persona</option>
                        <option value="empresa">Empresa</option>
                    </select>
                </div>

                <div style="grid-column: span 2;">
                    <label class="form-label" for="client-name">Nombre o razón social</label>
                    <input class="input" type="text" id="client-name" name="name" placeholder="Nombre completo o razón social">
                </div>

                <div>
                    <label class="form-label" for="client-document-type">Tipo de documento</label>
                    <select class="select" id="client-document-type" name="document_type">
                        <option value="DNI">DNI</option>
                        <option value="RUC">RUC</option>
                        <option value="CE">CE</option>
                    </select>
                </div>

                <div>
                    <label class="form-label" for="client-document-number">Documento</label>
                    <input class="input" type="text" id="client-document-number" name="document_number" placeholder="Número de documento">
                </div>

                <div>
                    <label class="form-label" for="client-status">Estado</label>
                    <select class="select" id="client-status" name="status">
                        <option value="activo">Activo</option>
                        <option value="inactivo">Inactivo</option>
                    </select>
                </div>

                <div>
                    <label class="form-label" for="client-email">Correo</label>
                    <input class="input" type="email" id="client-email" name="email" placeholder="correo@dominio.com">
                </div>

                <div>
                    <label class="form-label" for="client-phone">Teléfono</label>
                    <input class="input" type="text" id="client-phone" name="phone" placeholder="999888777">
                </div>

                <div>
                    <label class="form-label" for="client-executive">Ejecutivo asignado</label>
                    <select class="select" id="client-executive" name="assigned_executive_user_id">
                        <option value="">Sin asignar</option>
                        <?php foreach ($executives as $exec): ?>
                            <option value="<?= demo_e($exec['id']) ?>"><?= demo_e($exec['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="grid-column: 1 / -1;">
                    <label class="form-label" for="client-address">Dirección</label>
                    <input class="input" type="text" id="client-address" name="address" placeholder="Dirección completa">
                </div>

                <div style="grid-column: 1 / -1;">
                    <label class="form-label" for="client-notes">Observaciones</label>
                    <textarea class="textarea" id="client-notes" name="notes" placeholder="Notas internas o contexto comercial"></textarea>
                    <p class="clients-secondary-note">Las observaciones iniciales también se mostrarán en la ficha detallada del cliente.</p>
                </div>
            </form>
        </div>
        <div class="modal__footer">
            <button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button>
            <button type="button" class="btn btn-primary" id="client-form-submit">Guardar cliente</button>
        </div>
    </div>
</div>

<script>
    (() => {
        const clientsState = <?= json_encode(array_values($clients), JSON_UNESCAPED_UNICODE) ?>;
        const executivesState = <?= json_encode(array_values($executives), JSON_UNESCAPED_UNICODE) ?>;
        const policyCountsState = <?= json_encode($activePolicyCounts, JSON_UNESCAPED_UNICODE) ?>;

        let currentClients = [...clientsState];
        let activePolicyCounts = { ...policyCountsState };

        const tableBody = document.getElementById('clients-table-body');
        const emptyState = document.getElementById('clients-empty-state');

        const filterSearch = document.getElementById('client-filter-search');
        const filterExecutive = document.getElementById('client-filter-executive');
        const filterStatus = document.getElementById('client-filter-status');
        const filterType = document.getElementById('client-filter-type');
        const btnResetFilters = document.getElementById('btn-reset-client-filters');

        const btnNewClient = document.getElementById('btn-new-client');
        const formModalId = 'client-form-modal';

        const form = document.getElementById('client-form');
        const formAction = document.getElementById('client-form-action');
        const formTitle = document.getElementById('client-form-title');
        const formNote = document.getElementById('client-form-note');
        const formSubmit = document.getElementById('client-form-submit');

        const kpiTotal = document.getElementById('kpi-total-clients');
        const kpiActive = document.getElementById('kpi-active-clients');
        const kpiWithPolicy = document.getElementById('kpi-with-policy');
        const kpiWithoutPortal = document.getElementById('kpi-without-portal');

        const endpoint = <?= json_encode(demo_url('ajax/clientes.php'), JSON_UNESCAPED_UNICODE) ?>;
        const detailBase = <?= json_encode(demo_url('modules/clientes/detalle.php?id='), JSON_UNESCAPED_UNICODE) ?>;

        const escapeHtml = (value) => String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');

        const roleName = (execId) => {
            const found = executivesState.find(item => item.id === execId);
            return found ? found.full_name : 'Sin asignar';
        };

        const typeLabel = (type) => type === 'empresa' ? 'Empresa' : 'Persona';

        const formatBadge = (label, tone) => `<span class="badge badge-${tone}">${escapeHtml(label)}</span>`;

        const renderKpis = () => {
            kpiTotal.textContent = currentClients.length;
            kpiActive.textContent = currentClients.filter(item => item.status === 'activo').length;
            kpiWithPolicy.textContent = currentClients.filter(item => (activePolicyCounts[item.id] || 0) > 0).length;
            kpiWithoutPortal.textContent = currentClients.filter(item => !item.has_portal_access).length;
        };

        const getFilteredClients = () => {
            const term = filterSearch.value.trim().toLowerCase();
            const executive = filterExecutive.value;
            const status = filterStatus.value;
            const type = filterType.value;

            return currentClients.filter((client) => {
                const haystack = [
                    client.name,
                    client.document_number,
                    client.email,
                    client.phone,
                    roleName(client.assigned_executive_user_id)
                ].join(' ').toLowerCase();

                const okSearch = !term || haystack.includes(term);
                const okExec = !executive || (client.assigned_executive_user_id || '') === executive;
                const okStatus = !status || (client.status || '') === status;
                const okType = !type || (client.type || '') === type;

                return okSearch && okExec && okStatus && okType;
            });
        };

        const renderTable = () => {
            const rows = getFilteredClients();
            tableBody.innerHTML = '';

            if (!rows.length) {
                emptyState.hidden = false;
                return;
            }

            emptyState.hidden = true;

            rows.forEach((client) => {
                const tr = document.createElement('tr');
                const hasPortal = !!client.has_portal_access;
                const activePolicies = activePolicyCounts[client.id] || 0;

                tr.innerHTML = `
                    <td>${escapeHtml(client.name || '—')}</td>
                    <td>${escapeHtml(client.document_type || '')} ${escapeHtml(client.document_number || '—')}</td>
                    <td>${formatBadge(typeLabel(client.type), client.type === 'empresa' ? 'info' : 'neutral')}</td>
                    <td>${escapeHtml(client.phone || '—')}</td>
                    <td>${escapeHtml(client.email || '—')}</td>
                    <td>${escapeHtml(roleName(client.assigned_executive_user_id))}</td>
                    <td>${formatBadge(String(activePolicies), activePolicies > 0 ? 'success' : 'warning')}</td>
                    <td>${formatBadge((client.status || '—').charAt(0).toUpperCase() + (client.status || '—').slice(1), client.status === 'activo' ? 'success' : 'danger')}</td>
                    <td>
                        <div class="clients-actions">
                            <a class="clients-action-btn clients-action-btn--primary" href="${detailBase}${encodeURIComponent(client.id)}">Ver ficha</a>
                            <button type="button" class="clients-action-btn" data-action="edit" data-id="${escapeHtml(client.id)}">Editar</button>
                            <button type="button" class="clients-action-btn" data-action="portal" data-id="${escapeHtml(client.id)}" ${hasPortal ? 'disabled' : ''}>
                                ${hasPortal ? 'Portal activo' : 'Crear acceso portal'}
                            </button>
                            <button type="button" class="clients-action-btn" data-action="policy" data-id="${escapeHtml(client.id)}">Nueva póliza</button>
                        </div>
                    </td>
                `;
                tableBody.appendChild(tr);
            });
        };

        const resetForm = () => {
            form.reset();
            formAction.value = 'create';
            document.getElementById('client-id').value = '';
            document.getElementById('client-type').value = 'persona';
            document.getElementById('client-document-type').value = 'DNI';
            document.getElementById('client-status').value = 'activo';
            formTitle.textContent = 'Nuevo cliente';
            formNote.textContent = 'Registra una persona o empresa. La ficha se guardará de forma simulada dentro de la sesión actual.';
        };

        const upsertClient = (client) => {
            const index = currentClients.findIndex(item => item.id === client.id);
            if (index >= 0) {
                currentClients[index] = client;
            } else {
                currentClients.unshift(client);
            }
        };

        const openCreateModal = () => {
            resetForm();
            DemoApp.openModal(formModalId);
        };

        const openEditModal = (client) => {
            resetForm();
            formAction.value = 'edit';
            document.getElementById('client-id').value = client.id || '';
            document.getElementById('client-type').value = client.type || 'persona';
            document.getElementById('client-name').value = client.name || '';
            document.getElementById('client-document-type').value = client.document_type || 'DNI';
            document.getElementById('client-document-number').value = client.document_number || '';
            document.getElementById('client-status').value = client.status || 'activo';
            document.getElementById('client-email').value = client.email || '';
            document.getElementById('client-phone').value = client.phone || '';
            document.getElementById('client-executive').value = client.assigned_executive_user_id || '';
            document.getElementById('client-address').value = client.address || '';
            document.getElementById('client-notes').value = client.notes || '';
            formTitle.textContent = 'Editar cliente';
            formNote.textContent = 'Actualiza los datos principales del cliente. Los cambios se verán reflejados al instante en la sesión actual.';
            DemoApp.openModal(formModalId);
        };

        const showPortalCredentials = (payload) => {
            const title = document.getElementById('generic-modal-title');
            const body = document.getElementById('generic-modal-body');

            title.textContent = 'Acceso portal creado';
            body.innerHTML = `
                <div class="grid" style="gap: .9rem;">
                    <div class="panel">
                        <strong>Cliente</strong>
                        <p class="mt-1">${escapeHtml(payload.client?.name || '—')}</p>
                    </div>
                    <div class="panel">
                        <strong>Usuario</strong>
                        <p class="mt-1"><code>${escapeHtml(payload.credentials?.username || '—')}</code></p>
                    </div>
                    <div class="panel">
                        <strong>Clave inicial</strong>
                        <p class="mt-1"><code>${escapeHtml(payload.credentials?.password || '—')}</code></p>
                    </div>
                    <div class="panel">
                        <strong>Observación</strong>
                        <p class="mt-1">Este acceso demo se guardó de forma temporal en la sesión actual.</p>
                    </div>
                </div>
            `;

            DemoApp.openModal('generic-modal');
        };

        btnNewClient.addEventListener('click', openCreateModal);

        [filterSearch, filterExecutive, filterStatus, filterType].forEach((el) => {
            el.addEventListener('input', renderTable);
            el.addEventListener('change', renderTable);
        });

        btnResetFilters.addEventListener('click', () => {
            filterSearch.value = '';
            filterExecutive.value = '';
            filterStatus.value = '';
            filterType.value = '';
            renderTable();
        });

        tableBody.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-action]');
            if (!button) return;

            const action = button.getAttribute('data-action');
            const id = button.getAttribute('data-id');
            const client = currentClients.find(item => item.id === id);

            if (!client) return;

            if (action === 'edit') {
                openEditModal(client);
                return;
            }

            if (action === 'policy') {
                DemoApp.toast({
                    title: 'Nueva póliza',
                    message: `Acción demo lista para ${client.name}. Se implementará en el módulo de pólizas.`,
                    type: 'info'
                });
                return;
            }

            if (action === 'portal') {
                DemoApp.confirm({
                    title: 'Crear acceso portal',
                    message: `¿Deseas crear el acceso portal para ${client.name}?`,
                    onAccept: async () => {
                        const formData = new FormData();
                        formData.append('action', 'create_portal_access');
                        formData.append('client_id', client.id);

                        const response = await DemoApp.api(endpoint, {
                            method: 'POST',
                            body: formData
                        });

                        if (!response.success) {
                            DemoApp.toast({
                                title: response.title || 'No se pudo crear el acceso',
                                message: response.message || 'Verifica los datos del cliente.',
                                type: 'error'
                            });
                            return;
                        }

                        upsertClient(response.client);
                        renderKpis();
                        renderTable();

                        DemoApp.toast({
                            title: response.title || 'Acceso creado',
                            message: response.message || 'El acceso portal fue generado correctamente.',
                            type: 'success'
                        });

                        showPortalCredentials(response);
                    }
                });
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

            upsertClient(response.client);
            renderKpis();
            renderTable();
            DemoApp.closeModal(formModalId);
            DemoApp.toast({
                title: response.title || 'Cliente guardado',
                message: response.message || 'La información se actualizó correctamente.',
                type: 'success'
            });

            resetForm();
        });

        resetForm();
        renderKpis();
        renderTable();
    })();
</script>
<?php
$content = ob_get_clean();

demo_render_internal_layout(
    'Clientes',
    $content,
    [
        'breadcrumb' => ['Inicio', 'Clientes'],
        'subtitle' => 'Vista gerencial de cartera, datos maestros y acceso portal para clientes.',
    ]
);