<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';

demo_require_roles(['gerente', 'superadmin']);

$currentUser = demo_current_user();
$users = demo_store('users', []);
$clients = demo_store('clients', []);

usort($users, fn ($a, $b) => strtotime($b['created_at'] ?? '') <=> strtotime($a['created_at'] ?? ''));
usort($clients, fn ($a, $b) => strcmp(($a['name'] ?? ''), ($b['name'] ?? '')));

$totalUsers = count($users);
$totalExecutives = count(array_filter($users, fn ($u) => ($u['role'] ?? '') === 'ejecutivo'));
$totalClients = count(array_filter($users, fn ($u) => ($u['role'] ?? '') === 'cliente'));
$totalInactive = count(array_filter($users, fn ($u) => ($u['status'] ?? '') === 'inactivo'));

$clientsMap = [];
foreach ($clients as $client) {
    $clientsMap[$client['id']] = $client;
}

ob_start();
?>
<style>
    .users-toolbar {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        margin-bottom: 1rem;
    }

    .users-controls {
        display: grid;
        gap: 1rem;
        grid-template-columns: 1.15fr .75fr .75fr auto;
        align-items: end;
        margin-bottom: 1rem;
    }

    .users-table-actions {
        display: flex;
        flex-wrap: wrap;
        gap: .45rem;
    }

    .table-action-btn {
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

    .table-action-btn:hover {
        transform: translateY(-1px);
        border-color: rgba(79, 70, 229, .18);
        background: #f8fbff;
    }

    .table-action-btn--danger {
        color: var(--danger);
        border-color: rgba(239, 68, 68, .18);
        background: #fff7f7;
    }

    .table-action-btn[disabled] {
        opacity: .6;
        cursor: not-allowed;
        transform: none;
    }

    .users-role-badge {
        min-width: 94px;
        justify-content: center;
    }

    .users-empty {
        padding: 1.25rem;
        text-align: center;
        color: var(--text-soft);
    }

    .users-meta-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .9rem;
    }

    .users-meta-item {
        padding: .85rem .95rem;
        border-radius: 16px;
        border: 1px solid rgba(219, 227, 239, .85);
        background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
    }

    .users-meta-item strong {
        display: block;
        font-size: .8rem;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: var(--text-soft);
        margin-bottom: .35rem;
    }

    .users-meta-item span {
        display: block;
        font-size: .98rem;
        line-height: 1.45;
        overflow-wrap: anywhere;
    }

    .users-form-helper {
        margin: 0;
        color: var(--text-soft);
        font-size: .88rem;
        line-height: 1.45;
    }

    .users-inline-note {
        padding: .85rem 1rem;
        border-radius: 16px;
        border: 1px dashed rgba(100, 116, 139, .28);
        background: linear-gradient(180deg, #fbfdff 0%, #f7faff 100%);
        margin-bottom: 1rem;
    }

    .users-inline-note strong {
        display: block;
        margin-bottom: .2rem;
    }

    .users-pill {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        min-height: 30px;
        padding: .35rem .7rem;
        border-radius: 999px;
        background: rgba(79, 70, 229, .08);
        color: var(--primary);
        font-size: .82rem;
        font-weight: 700;
    }

    @media (max-width: 1100px) {
        .users-toolbar {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .users-controls {
            grid-template-columns: 1fr 1fr;
        }

        .users-controls .btn {
            width: 100%;
        }
    }

    @media (max-width: 720px) {
        .users-toolbar,
        .users-controls,
        .users-meta-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="grid" style="gap: 1rem;">
    <section class="users-toolbar">
        <article class="card kpi-card">
            <p class="kpi-card__label">Usuarios registrados</p>
            <h3 class="kpi-card__value" id="kpi-total-users"><?= demo_e((string)$totalUsers) ?></h3>
            <p class="kpi-card__meta">Incluye gerencia, ejecutivos y clientes con acceso.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Ejecutivos</p>
            <h3 class="kpi-card__value" id="kpi-executives"><?= demo_e((string)$totalExecutives) ?></h3>
            <p class="kpi-card__meta">Usuarios comerciales disponibles para cartera.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Clientes con acceso</p>
            <h3 class="kpi-card__value" id="kpi-clients"><?= demo_e((string)$totalClients) ?></h3>
            <p class="kpi-card__meta">Cuentas habilitadas para el portal cliente.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Inactivos</p>
            <h3 class="kpi-card__value" id="kpi-inactive"><?= demo_e((string)$totalInactive) ?></h3>
            <p class="kpi-card__meta">Accesos deshabilitados temporalmente en la demo.</p>
        </article>
    </section>

    <section class="card">
        <div class="card__header">
            <div>
                <h2 class="card__title">Gestión de usuarios</h2>
                <p class="card__subtitle">El gerente puede crear y administrar ejecutivos y clientes con acceso al sistema.</p>
            </div>

            <button type="button" class="btn btn-primary" id="btn-new-user">Nuevo usuario</button>
        </div>

        <div class="users-inline-note">
            <strong>Regla demo activa</strong>
            <span class="muted">
                El documento de 8 dígitos será el usuario y también la clave inicial. En esta fase, gerencia no puede crear superadmin ni otros gerentes.
            </span>
        </div>

        <div class="users-controls">
            <div>
                <label class="form-label" for="filter-search">Buscar</label>
                <input class="input" id="filter-search" type="text" placeholder="Nombre, documento, correo o teléfono">
            </div>

            <div>
                <label class="form-label" for="filter-role">Rol</label>
                <select class="select" id="filter-role">
                    <option value="">Todos</option>
                    <option value="gerente">Gerente</option>
                    <option value="ejecutivo">Ejecutivo</option>
                    <option value="cliente">Cliente</option>
                </select>
            </div>

            <div>
                <label class="form-label" for="filter-status">Estado</label>
                <select class="select" id="filter-status">
                    <option value="">Todos</option>
                    <option value="activo">Activo</option>
                    <option value="inactivo">Inactivo</option>
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
                        <th>Rol</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th>Estado</th>
                        <th>Fecha de alta</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="users-table-body"></tbody>
            </table>
        </div>

        <div id="users-empty-state" class="users-empty" hidden>
            No hay usuarios que coincidan con los filtros seleccionados.
        </div>
    </section>
</div>

<div class="modal" id="user-form-modal" hidden>
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__dialog">
        <div class="modal__header">
            <h3 id="user-form-title">Nuevo usuario</h3>
            <button type="button" class="icon-btn" data-modal-close aria-label="Cerrar">✕</button>
        </div>
        <div class="modal__body">
            <p class="users-form-helper" id="user-form-helper">
                Completa los datos. El usuario y la clave inicial se generarán con el documento.
            </p>

            <form id="user-form" class="form-grid form-grid--3">
                <input type="hidden" name="action" id="form-action" value="create">
                <input type="hidden" name="user_id" id="form-user-id" value="">

                <div style="grid-column: span 2;">
                    <label class="form-label" for="full_name">Nombre completo</label>
                    <input class="input" type="text" id="full_name" name="full_name" placeholder="Nombre completo del usuario">
                </div>

                <div>
                    <label class="form-label" for="role">Rol</label>
                    <select class="select" id="role" name="role">
                        <option value="ejecutivo">Ejecutivo</option>
                        <option value="cliente">Cliente</option>
                    </select>
                </div>

                <div>
                    <label class="form-label" for="document">Documento / usuario</label>
                    <input class="input" type="text" id="document" name="document" inputmode="numeric" maxlength="8" placeholder="8 dígitos">
                </div>

                <div>
                    <label class="form-label" for="status">Estado</label>
                    <select class="select" id="status" name="status">
                        <option value="activo">Activo</option>
                        <option value="inactivo">Inactivo</option>
                    </select>
                </div>

                <div>
                    <label class="form-label" for="email">Correo</label>
                    <input class="input" type="email" id="email" name="email" placeholder="correo@dominio.com">
                </div>

                <div>
                    <label class="form-label" for="phone">Teléfono</label>
                    <input class="input" type="text" id="phone" name="phone" placeholder="999888777">
                </div>

                <div id="client-link-wrapper" style="grid-column: 1 / -1;" hidden>
                    <label class="form-label" for="client_id">Vincular a cliente existente</label>
                    <select class="select" id="client_id" name="client_id">
                        <option value="">Sin vincular por ahora</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= demo_e($client['id']) ?>">
                                <?= demo_e($client['name']) ?>
                                <?= !empty($client['has_portal_access']) ? ' · ya tiene acceso' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="users-form-helper mt-1">
                        Úsalo cuando el usuario cliente corresponda a una ficha ya creada en el módulo de clientes.
                    </p>
                </div>
            </form>
        </div>
        <div class="modal__footer">
            <button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button>
            <button type="button" class="btn btn-primary" id="user-form-submit">Guardar usuario</button>
        </div>
    </div>
</div>

<div class="modal" id="user-detail-modal" hidden>
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__dialog modal__dialog--sm">
        <div class="modal__header">
            <h3>Detalle de usuario</h3>
            <button type="button" class="icon-btn" data-modal-close aria-label="Cerrar">✕</button>
        </div>
        <div class="modal__body">
            <div class="users-meta-grid" id="user-detail-grid"></div>
        </div>
        <div class="modal__footer">
            <button type="button" class="btn btn-ghost" data-modal-close>Cerrar</button>
        </div>
    </div>
</div>

<script>
    (() => {
        const currentUserId = <?= json_encode($currentUser['id'] ?? '', JSON_UNESCAPED_UNICODE) ?>;
        let usersState = <?= json_encode(array_values($users), JSON_UNESCAPED_UNICODE) ?>;
        let clientsState = <?= json_encode(array_values($clients), JSON_UNESCAPED_UNICODE) ?>;

        const tableBody = document.getElementById('users-table-body');
        const emptyState = document.getElementById('users-empty-state');
        const filterSearch = document.getElementById('filter-search');
        const filterRole = document.getElementById('filter-role');
        const filterStatus = document.getElementById('filter-status');
        const btnResetFilters = document.getElementById('btn-reset-filters');
        const btnNewUser = document.getElementById('btn-new-user');

        const formModalId = 'user-form-modal';
        const detailModalId = 'user-detail-modal';

        const form = document.getElementById('user-form');
        const formTitle = document.getElementById('user-form-title');
        const formHelper = document.getElementById('user-form-helper');
        const formAction = document.getElementById('form-action');
        const formUserId = document.getElementById('form-user-id');
        const roleSelect = document.getElementById('role');
        const clientLinkWrapper = document.getElementById('client-link-wrapper');
        const clientSelect = document.getElementById('client_id');
        const submitBtn = document.getElementById('user-form-submit');

        const detailGrid = document.getElementById('user-detail-grid');

        const kpiTotal = document.getElementById('kpi-total-users');
        const kpiExec = document.getElementById('kpi-executives');
        const kpiClients = document.getElementById('kpi-clients');
        const kpiInactive = document.getElementById('kpi-inactive');

        const endpoint = <?= json_encode(demo_url('ajax/usuarios.php'), JSON_UNESCAPED_UNICODE) ?>;

        const roleLabel = (role) => ({
            gerente: 'Gerente',
            ejecutivo: 'Ejecutivo',
            cliente: 'Cliente',
            superadmin: 'Superadmin'
        }[role] || role || '—');

        const roleTone = (role) => ({
            gerente: 'info',
            ejecutivo: 'warning',
            cliente: 'success',
            superadmin: 'danger'
        }[role] || 'neutral');

        const statusTone = (status) => ({
            activo: 'success',
            inactivo: 'danger'
        }[status] || 'neutral');

        const formatDate = (value) => {
            if (!value) return '—';
            const date = new Date(value.replace(' ', 'T'));
            if (Number.isNaN(date.getTime())) return value;
            return date.toLocaleDateString('es-PE');
        };

        const escapeHtml = (value) => String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');

        const findClient = (clientId) => clientsState.find((item) => item.id === clientId) || null;

        const isProtectedRow = (user) => {
            return ['gerente', 'superadmin'].includes(user.role) || user.id === currentUserId;
        };

        const renderKpis = () => {
            kpiTotal.textContent = usersState.length;
            kpiExec.textContent = usersState.filter((u) => u.role === 'ejecutivo').length;
            kpiClients.textContent = usersState.filter((u) => u.role === 'cliente').length;
            kpiInactive.textContent = usersState.filter((u) => u.status === 'inactivo').length;
        };

        const getFilteredUsers = () => {
            const term = filterSearch.value.trim().toLowerCase();
            const role = filterRole.value;
            const status = filterStatus.value;

            return usersState.filter((user) => {
                const haystack = [
                    user.full_name,
                    user.document,
                    user.email,
                    user.phone,
                    roleLabel(user.role)
                ].join(' ').toLowerCase();

                const roleOk = !role || user.role === role;
                const statusOk = !status || user.status === status;
                const searchOk = !term || haystack.includes(term);

                return roleOk && statusOk && searchOk;
            });
        };

        const renderTable = () => {
            const rows = getFilteredUsers();
            tableBody.innerHTML = '';

            if (!rows.length) {
                emptyState.hidden = false;
                return;
            }

            emptyState.hidden = true;

            rows.forEach((user) => {
                const protectedRow = isProtectedRow(user);

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${escapeHtml(user.full_name || '—')}</td>
                    <td>${escapeHtml(user.document || '—')}</td>
                    <td><span class="badge badge-${roleTone(user.role)} users-role-badge">${escapeHtml(roleLabel(user.role))}</span></td>
                    <td>${escapeHtml(user.email || '—')}</td>
                    <td>${escapeHtml(user.phone || '—')}</td>
                    <td><span class="badge badge-${statusTone(user.status)}">${escapeHtml((user.status || '—').charAt(0).toUpperCase() + (user.status || '—').slice(1))}</span></td>
                    <td>${escapeHtml(formatDate(user.created_at))}</td>
                    <td>
                        <div class="users-table-actions">
                            <button type="button" class="table-action-btn" data-action="detail" data-id="${escapeHtml(user.id)}">Ver</button>
                            ${protectedRow ? `
                                <button type="button" class="table-action-btn" disabled>Protegido</button>
                            ` : `
                                <button type="button" class="table-action-btn" data-action="edit" data-id="${escapeHtml(user.id)}">Editar</button>
                                <button type="button" class="table-action-btn ${user.status === 'activo' ? 'table-action-btn--danger' : ''}" data-action="toggle" data-id="${escapeHtml(user.id)}">
                                    ${user.status === 'activo' ? 'Inactivar' : 'Activar'}
                                </button>
                            `}
                        </div>
                    </td>
                `;
                tableBody.appendChild(tr);
            });
        };

        const toggleClientLink = () => {
            const isClient = roleSelect.value === 'cliente';
            clientLinkWrapper.hidden = !isClient;
            if (!isClient) {
                clientSelect.value = '';
            }
        };

        const resetForm = () => {
            form.reset();
            formAction.value = 'create';
            formUserId.value = '';
            formTitle.textContent = 'Nuevo usuario';
            formHelper.textContent = 'Completa los datos. El usuario y la clave inicial se generarán con el documento.';
            roleSelect.value = 'ejecutivo';
            document.getElementById('status').value = 'activo';
            toggleClientLink();
        };

        const openCreateModal = () => {
            resetForm();
            DemoApp.openModal(formModalId);
        };

        const openEditModal = (user) => {
            resetForm();
            formAction.value = 'update';
            formUserId.value = user.id;
            formTitle.textContent = 'Editar usuario';
            formHelper.textContent = 'Puedes ajustar los datos del acceso. Si cambias el documento, también cambiarán usuario y clave demo.';
            document.getElementById('full_name').value = user.full_name || '';
            document.getElementById('document').value = user.document || '';
            document.getElementById('email').value = user.email || '';
            document.getElementById('phone').value = user.phone || '';
            document.getElementById('status').value = user.status || 'activo';
            roleSelect.value = user.role || 'ejecutivo';
            toggleClientLink();
            clientSelect.value = user.client_id || '';
            DemoApp.openModal(formModalId);
        };

        const openDetailModal = (user) => {
            const linkedClient = user.client_id ? findClient(user.client_id) : null;
            detailGrid.innerHTML = `
                <div class="users-meta-item">
                    <strong>Nombre</strong>
                    <span>${escapeHtml(user.full_name || '—')}</span>
                </div>
                <div class="users-meta-item">
                    <strong>Rol</strong>
                    <span>${escapeHtml(roleLabel(user.role))}</span>
                </div>
                <div class="users-meta-item">
                    <strong>Documento / usuario</strong>
                    <span>${escapeHtml(user.document || '—')}</span>
                </div>
                <div class="users-meta-item">
                    <strong>Estado</strong>
                    <span>${escapeHtml(user.status || '—')}</span>
                </div>
                <div class="users-meta-item">
                    <strong>Correo</strong>
                    <span>${escapeHtml(user.email || '—')}</span>
                </div>
                <div class="users-meta-item">
                    <strong>Teléfono</strong>
                    <span>${escapeHtml(user.phone || '—')}</span>
                </div>
                <div class="users-meta-item">
                    <strong>Fecha de alta</strong>
                    <span>${escapeHtml(formatDate(user.created_at))}</span>
                </div>
                <div class="users-meta-item">
                    <strong>Cliente vinculado</strong>
                    <span>${escapeHtml(linkedClient ? linkedClient.name : 'Sin vincular')}</span>
                </div>
            `;
            DemoApp.openModal(detailModalId);
        };

        const replaceUserInState = (user) => {
            const index = usersState.findIndex((item) => item.id === user.id);
            if (index >= 0) {
                usersState[index] = user;
            } else {
                usersState.unshift(user);
            }
        };

        const applyClientUpdates = (updatedClients = []) => {
            if (!Array.isArray(updatedClients)) return;

            updatedClients.forEach((updatedClient) => {
                const index = clientsState.findIndex((item) => item.id === updatedClient.id);
                if (index >= 0) {
                    clientsState[index] = updatedClient;
                }
            });
        };

        btnNewUser.addEventListener('click', openCreateModal);

        roleSelect.addEventListener('change', toggleClientLink);

        btnResetFilters.addEventListener('click', () => {
            filterSearch.value = '';
            filterRole.value = '';
            filterStatus.value = '';
            renderTable();
        });

        [filterSearch, filterRole, filterStatus].forEach((element) => {
            element.addEventListener('input', renderTable);
            element.addEventListener('change', renderTable);
        });

        tableBody.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-action]');
            if (!button) return;

            const action = button.getAttribute('data-action');
            const id = button.getAttribute('data-id');
            const user = usersState.find((item) => item.id === id);

            if (!user) return;

            if (action === 'detail') {
                openDetailModal(user);
                return;
            }

            if (action === 'edit') {
                openEditModal(user);
                return;
            }

            if (action === 'toggle') {
                const nextLabel = user.status === 'activo' ? 'inactivar' : 'activar';

                DemoApp.confirm({
                    title: `${nextLabel.charAt(0).toUpperCase() + nextLabel.slice(1)} usuario`,
                    message: `¿Deseas ${nextLabel} a ${user.full_name}?`,
                    onAccept: async () => {
                        const formData = new FormData();
                        formData.append('action', 'toggle_status');
                        formData.append('user_id', user.id);

                        const response = await DemoApp.api(endpoint, {
                            method: 'POST',
                            body: formData
                        });

                        if (!response.success) {
                            DemoApp.toast({
                                title: response.title || 'No se pudo actualizar',
                                message: response.message || 'La acción no pudo completarse.',
                                type: 'error'
                            });
                            return;
                        }

                        replaceUserInState(response.user);
                        applyClientUpdates(response.updated_clients || []);
                        renderKpis();
                        renderTable();

                        DemoApp.toast({
                            title: response.title || 'Estado actualizado',
                            message: response.message || 'Se actualizó el estado del usuario.',
                            type: 'success'
                        });
                    }
                });
            }
        });

        submitBtn.addEventListener('click', async () => {
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

            replaceUserInState(response.user);
            applyClientUpdates(response.updated_clients || []);
            renderKpis();
            renderTable();
            DemoApp.closeModal(formModalId);

            DemoApp.toast({
                title: response.title || 'Usuario guardado',
                message: response.message || 'Los cambios se guardaron correctamente.',
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
    'Usuarios',
    $content,
    [
        'breadcrumb' => ['Inicio', 'Usuarios'],
        'subtitle' => 'Administración simulada de accesos para ejecutivos y clientes desde gerencia.',
    ]
);