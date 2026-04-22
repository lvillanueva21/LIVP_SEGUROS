<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';

demo_require_roles(['ejecutivo']);

$currentUser = demo_current_user();
$executiveId = $currentUser['id'] ?? '';

$clients = demo_filter_clients_by_executive(demo_store('clients', []), $executiveId);
$policies = demo_filter_policies_by_executive(demo_store('policies', []), $executiveId);

$activePolicyCounts = [];
foreach ($policies as $policy) {
    if (($policy['status'] ?? '') !== 'activa') {
        continue;
    }

    $clientId = $policy['client_id'] ?? '';
    if ($clientId === '') {
        continue;
    }

    $activePolicyCounts[$clientId] = ($activePolicyCounts[$clientId] ?? 0) + 1;
}

usort($clients, fn($a, $b) => strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? '')));

ob_start();
?>
<style>
    .exec-clients-controls {
        display: grid;
        gap: 1rem;
        grid-template-columns: 1.2fr .8fr .8fr auto;
        align-items: end;
        margin-bottom: 1rem;
    }

    .exec-clients-actions {
        display: flex;
        flex-wrap: wrap;
        gap: .45rem;
    }

    .exec-clients-action-btn {
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

    .exec-clients-action-btn:hover {
        transform: translateY(-1px);
        border-color: rgba(79, 70, 229, .18);
        background: #f8fbff;
    }

    .exec-clients-action-btn--primary {
        background: rgba(79, 70, 229, .08);
        color: var(--primary);
        border-color: rgba(79, 70, 229, .12);
    }

    .exec-clients-empty {
        padding: 1.2rem;
        text-align: center;
        color: var(--text-soft);
    }

    .exec-clients-note {
        padding: .85rem 1rem;
        border-radius: 16px;
        border: 1px dashed rgba(100, 116, 139, .28);
        background: linear-gradient(180deg, #fbfdff 0%, #f7faff 100%);
        margin-bottom: 1rem;
    }

    .exec-clients-note strong {
        display: block;
        margin-bottom: .2rem;
    }

    .exec-clients-form-note {
        margin: 0 0 1rem;
        color: var(--text-soft);
        line-height: 1.5;
    }

    @media (max-width: 1120px) {
        .exec-clients-controls {
            grid-template-columns: 1fr 1fr;
        }

        .exec-clients-controls .btn {
            width: 100%;
        }
    }

    @media (max-width: 720px) {
        .exec-clients-controls {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="grid" style="gap: 1rem;">
    <section class="card">
        <div class="card__header">
            <div>
                <h2 class="card__title">Mis clientes</h2>
                <p class="card__subtitle">Vista comercial de tu cartera con creación rápida, edición y acceso portal.</p>
            </div>

            <button type="button" class="btn btn-primary" id="btn-new-client">Nuevo cliente</button>
        </div>

        <div class="exec-clients-note">
            <strong>Cartera propia</strong>
            <span class="muted">Solo verás y podrás gestionar clientes asignados a tu usuario ejecutivo. No es posible reasignarlos desde esta interfaz.</span>
        </div>

        <div class="exec-clients-controls">
            <div>
                <label class="form-label" for="client-search">Buscar</label>
                <input class="input" id="client-search" type="text" placeholder="Nombre, documento, correo o teléfono">
            </div>

            <div>
                <label class="form-label" for="client-status-filter">Estado</label>
                <select class="select" id="client-status-filter">
                    <option value="">Todos</option>
                    <option value="activo">Activo</option>
                    <option value="inactivo">Inactivo</option>
                </select>
            </div>

            <div>
                <label class="form-label" for="client-portal-filter">Portal</label>
                <select class="select" id="client-portal-filter">
                    <option value="">Todos</option>
                    <option value="con_portal">Con acceso portal</option>
                    <option value="sin_portal">Sin acceso portal</option>
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
                        <th>Nombre</th>
                        <th>Documento</th>
                        <th>Teléfono</th>
                        <th>Email</th>
                        <th>Pólizas activas</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="clients-table-body"></tbody>
            </table>
        </div>

        <div id="clients-empty-state" class="exec-clients-empty" hidden>
            No hay clientes que coincidan con los filtros seleccionados.
        </div>
    </section>
</div>

<div class="modal" id="executive-client-form-modal" hidden>
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__dialog">
        <div class="modal__header">
            <h3 id="executive-client-form-title">Nuevo cliente</h3>
            <button type="button" class="icon-btn" data-modal-close aria-label="Cerrar">✕</button>
        </div>
        <div class="modal__body">
            <p class="exec-clients-form-note" id="executive-client-form-note">
                Registra un cliente nuevo dentro de tu cartera comercial.
            </p>

            <form id="executive-client-form" class="form-grid form-grid--3">
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

                <div style="grid-column: 1 / -1;">
                    <label class="form-label" for="client-address">Dirección</label>
                    <input class="input" type="text" id="client-address" name="address" placeholder="Dirección completa">
                </div>

                <div style="grid-column: 1 / -1;">
                    <label class="form-label" for="client-notes">Notas</label>
                    <textarea class="textarea" id="client-notes" name="notes" placeholder="Comentario comercial o contexto relevante"></textarea>
                </div>
            </form>
        </div>
        <div class="modal__footer">
            <button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button>
            <button type="button" class="btn btn-primary" id="executive-client-form-submit">Guardar cliente</button>
        </div>
    </div>
</div>

<script>
    (() => {
        let clientsState = <?= json_encode(array_values($clients), JSON_UNESCAPED_UNICODE) ?>;
        const activePolicyCounts = <?= json_encode($activePolicyCounts, JSON_UNESCAPED_UNICODE) ?>;
        const endpoint = <?= json_encode(demo_url('ajax/ejecutivo-clientes.php'), JSON_UNESCAPED_UNICODE) ?>;
        const detailBase = <?= json_encode(demo_url('modules/ejecutivo/cliente-detalle.php?id='), JSON_UNESCAPED_UNICODE) ?>;

        const tableBody = document.getElementById('clients-table-body');
        const emptyState = document.getElementById('clients-empty-state');

        const searchInput = document.getElementById('client-search');
        const statusFilter = document.getElementById('client-status-filter');
        const portalFilter = document.getElementById('client-portal-filter');
        const resetFiltersBtn = document.getElementById('btn-reset-filters');

        const formModalId = 'executive-client-form-modal';
        const form = document.getElementById('executive-client-form');
        const formAction = document.getElementById('client-form-action');
        const formTitle = document.getElementById('executive-client-form-title');
        const formNote = document.getElementById('executive-client-form-note');
        const submitButton = document.getElementById('executive-client-form-submit');

        const escapeHtml = (value) => String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');

        const getActivePoliciesCount = (clientId) => activePolicyCounts[clientId] || 0;

        const getFilteredClients = () => {
            const term = searchInput.value.trim().toLowerCase();
            const status = statusFilter.value;
            const portal = portalFilter.value;

            return clientsState.filter((client) => {
                const haystack = [
                    client.name,
                    client.document_number,
                    client.email,
                    client.phone
                ].join(' ').toLowerCase();

                const matchesSearch = !term || haystack.includes(term);
                const matchesStatus = !status || (client.status || '') === status;
                const matchesPortal = !portal
                    || (portal === 'con_portal' && !!client.has_portal_access)
                    || (portal === 'sin_portal' && !client.has_portal_access);

                return matchesSearch && matchesStatus && matchesPortal;
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
                tr.innerHTML = `
                    <td>${escapeHtml(client.name || '—')}</td>
                    <td>${escapeHtml(client.document_type || '')} ${escapeHtml(client.document_number || '—')}</td>
                    <td>${escapeHtml(client.phone || '—')}</td>
                    <td>${escapeHtml(client.email || '—')}</td>
                    <td><span class="badge badge-${getActivePoliciesCount(client.id) > 0 ? 'success' : 'warning'}">${escapeHtml(String(getActivePoliciesCount(client.id)))}</span></td>
                    <td><span class="badge badge-${client.status === 'activo' ? 'success' : 'danger'}">${escapeHtml((client.status || '—').charAt(0).toUpperCase() + (client.status || '—').slice(1))}</span></td>
                    <td>
                        <div class="exec-clients-actions">
                            <a class="exec-clients-action-btn exec-clients-action-btn--primary" href="${detailBase}${encodeURIComponent(client.id)}">Ver ficha</a>
                            <button type="button" class="exec-clients-action-btn" data-action="edit" data-id="${escapeHtml(client.id)}">Editar</button>
                            <button type="button" class="exec-clients-action-btn" data-action="portal" data-id="${escapeHtml(client.id)}" ${client.has_portal_access ? 'disabled' : ''}>
                                ${client.has_portal_access ? 'Portal activo' : 'Crear acceso portal'}
                            </button>
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
            formNote.textContent = 'Registra un cliente nuevo dentro de tu cartera comercial.';
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
            document.getElementById('client-address').value = client.address || '';
            document.getElementById('client-notes').value = client.notes || '';
            formTitle.textContent = 'Editar cliente';
            formNote.textContent = 'Actualiza los datos principales del cliente sin salir de tu cartera.';
            DemoApp.openModal(formModalId);
        };

        const upsertClient = (client) => {
            const index = clientsState.findIndex((item) => item.id === client.id);
            if (index >= 0) {
                clientsState[index] = client;
            } else {
                clientsState.unshift(client);
            }
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
                        <strong>Detalle</strong>
                        <p class="mt-1">Este acceso demo se guarda de forma temporal en la sesión actual.</p>
                    </div>
                </div>
            `;

            DemoApp.openModal('generic-modal');
        };

        document.getElementById('btn-new-client').addEventListener('click', openCreateModal);

        [searchInput, statusFilter, portalFilter].forEach((element) => {
            element.addEventListener('input', renderTable);
            element.addEventListener('change', renderTable);
        });

        resetFiltersBtn.addEventListener('click', () => {
            searchInput.value = '';
            statusFilter.value = '';
            portalFilter.value = '';
            renderTable();
        });

        tableBody.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-action]');
            if (!button) return;

            const action = button.getAttribute('data-action');
            const id = button.getAttribute('data-id');
            const client = clientsState.find((item) => item.id === id);

            if (!client) return;

            if (action === 'edit') {
                openEditModal(client);
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

        submitButton.addEventListener('click', async () => {
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
            renderTable();
            DemoApp.closeModal(formModalId);

            DemoApp.toast({
                title: response.title || 'Cliente guardado',
                message: response.message || 'La información fue guardada correctamente.',
                type: 'success'
            });

            resetForm();
        });

        resetForm();
        renderTable();
    })();
</script>
<?php
$content = ob_get_clean();

demo_render_internal_layout(
    'Mis clientes',
    $content,
    [
        'breadcrumb' => ['Inicio', 'Clientes'],
        'subtitle' => 'Gestión comercial de clientes asignados a tu cartera como ejecutivo.',
    ]
);