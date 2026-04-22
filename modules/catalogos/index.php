<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';

demo_require_roles(['gerente', 'superadmin']);

$catalogs = [
    'insurers' => [
        'label' => 'Aseguradoras',
        'description' => 'Compañías aseguradoras disponibles para las pólizas del demo.',
        'items' => array_values(demo_store('insurers', [])),
    ],
    'insurance_types' => [
        'label' => 'Tipos de seguro',
        'description' => 'Clasificación comercial de productos de seguro.',
        'items' => array_values(demo_store('insurance_types', [])),
    ],
    'policy_statuses' => [
        'label' => 'Estados de póliza',
        'description' => 'Estados operativos permitidos para la cartera de pólizas.',
        'items' => array_values(demo_store('policy_statuses', [])),
    ],
    'internal_categories' => [
        'label' => 'Categorías internas',
        'description' => 'Etiquetas comerciales y operativas del demo.',
        'items' => array_values(demo_store('internal_categories', [])),
    ],
];

$totals = [
    'insurers' => count($catalogs['insurers']['items']),
    'insurance_types' => count($catalogs['insurance_types']['items']),
    'policy_statuses' => count($catalogs['policy_statuses']['items']),
    'internal_categories' => count($catalogs['internal_categories']['items']),
];

ob_start();
?>
<style>
    .catalog-kpis {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        margin-bottom: 1rem;
    }

    .catalog-toolbar {
        display: grid;
        gap: 1rem;
        grid-template-columns: 1fr auto;
        align-items: end;
        margin-bottom: 1rem;
    }

    .catalog-top-note {
        padding: .9rem 1rem;
        border-radius: 16px;
        border: 1px dashed rgba(100, 116, 139, .28);
        background: linear-gradient(180deg, #fbfdff 0%, #f7faff 100%);
        margin-bottom: 1rem;
    }

    .catalog-top-note strong {
        display: block;
        margin-bottom: .2rem;
    }

    .catalog-header-grid {
        display: grid;
        gap: 1rem;
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: center;
        margin-bottom: 1rem;
    }

    .catalog-table-actions {
        display: flex;
        flex-wrap: wrap;
        gap: .45rem;
    }

    .catalog-action-btn {
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

    .catalog-action-btn:hover {
        transform: translateY(-1px);
        border-color: rgba(79, 70, 229, .18);
        background: #f8fbff;
    }

    .catalog-action-btn--danger {
        color: var(--danger);
        border-color: rgba(239, 68, 68, .18);
        background: #fff7f7;
    }

    .catalog-empty {
        padding: 1.1rem;
        text-align: center;
        color: var(--text-soft);
    }

    .catalog-tab-panel[hidden] {
        display: none !important;
    }

    .catalog-description {
        margin: 0;
        color: var(--text-soft);
        line-height: 1.5;
    }

    .catalog-form-note {
        margin: 0 0 1rem;
        color: var(--text-soft);
        line-height: 1.5;
    }

    @media (max-width: 1100px) {
        .catalog-kpis {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .catalog-toolbar,
        .catalog-header-grid {
            grid-template-columns: 1fr;
        }

        .catalog-toolbar .btn,
        .catalog-header-grid .btn {
            width: 100%;
        }
    }

    @media (max-width: 680px) {
        .catalog-kpis {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="grid" style="gap: 1rem;">
    <section class="catalog-kpis">
        <article class="card kpi-card">
            <p class="kpi-card__label">Aseguradoras</p>
            <h3 class="kpi-card__value" id="kpi-insurers"><?= demo_e((string)$totals['insurers']) ?></h3>
            <p class="kpi-card__meta">Compañías disponibles para pólizas del demo.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Tipos de seguro</p>
            <h3 class="kpi-card__value" id="kpi-insurance_types"><?= demo_e((string)$totals['insurance_types']) ?></h3>
            <p class="kpi-card__meta">Líneas comerciales disponibles en cartera.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Estados de póliza</p>
            <h3 class="kpi-card__value" id="kpi-policy_statuses"><?= demo_e((string)$totals['policy_statuses']) ?></h3>
            <p class="kpi-card__meta">Estados operativos definidos para el sistema.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Categorías internas</p>
            <h3 class="kpi-card__value" id="kpi-internal_categories"><?= demo_e((string)$totals['internal_categories']) ?></h3>
            <p class="kpi-card__meta">Etiquetas internas para segmentación demo.</p>
        </article>
    </section>

    <section class="card">
        <div class="card__header">
            <div>
                <h2 class="card__title">Catálogos maestros</h2>
                <p class="card__subtitle">Administra listas base del demo con edición rápida, búsqueda y cambio de estado.</p>
            </div>
            <button type="button" class="btn btn-primary" id="btn-new-item">Nuevo ítem</button>
        </div>

        <div class="catalog-top-note">
            <strong>Gestión demo activa</strong>
            <span class="muted">Puedes crear, editar y activar o inactivar ítems sin recargar la página. Todo se guarda temporalmente en la sesión actual.</span>
        </div>

        <div class="catalog-toolbar">
            <div>
                <label class="form-label" for="catalog-search">Buscar por nombre</label>
                <input class="input" id="catalog-search" type="text" placeholder="Escribe para filtrar el catálogo activo">
            </div>
            <button type="button" class="btn btn-ghost" id="btn-reset-search">Limpiar búsqueda</button>
        </div>

        <div class="tab-nav" id="catalog-tabs">
            <?php foreach ($catalogs as $key => $catalog): ?>
                <button
                    type="button"
                    class="tab-btn <?= $key === 'insurers' ? 'is-active' : '' ?>"
                    data-catalog-tab="<?= demo_e($key) ?>"
                >
                    <?= demo_e($catalog['label']) ?>
                </button>
            <?php endforeach; ?>
        </div>

        <?php foreach ($catalogs as $key => $catalog): ?>
            <section class="catalog-tab-panel" data-catalog-panel="<?= demo_e($key) ?>" <?= $key !== 'insurers' ? 'hidden' : '' ?>>
                <div class="catalog-header-grid">
                    <div>
                        <h3 class="card__title"><?= demo_e($catalog['label']) ?></h3>
                        <p class="catalog-description"><?= demo_e($catalog['description']) ?></p>
                    </div>
                    <button type="button" class="btn btn-secondary" data-create-catalog="<?= demo_e($key) ?>">Nuevo en <?= demo_e($catalog['label']) ?></button>
                </div>

                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="catalog-table-<?= demo_e($key) ?>"></tbody>
                    </table>
                </div>

                <div class="catalog-empty" id="catalog-empty-<?= demo_e($key) ?>" hidden>
                    No hay registros que coincidan con la búsqueda actual.
                </div>
            </section>
        <?php endforeach; ?>
    </section>
</div>

<div class="modal" id="catalog-form-modal" hidden>
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__dialog modal__dialog--sm">
        <div class="modal__header">
            <h3 id="catalog-form-title">Nuevo ítem</h3>
            <button type="button" class="icon-btn" data-modal-close aria-label="Cerrar">✕</button>
        </div>
        <div class="modal__body">
            <p class="catalog-form-note" id="catalog-form-note">Completa los datos del ítem para el catálogo seleccionado.</p>

            <form id="catalog-form" class="form-grid">
                <input type="hidden" name="action" id="catalog-form-action" value="create">
                <input type="hidden" name="catalog_key" id="catalog-key" value="">
                <input type="hidden" name="item_id" id="catalog-item-id" value="">

                <div>
                    <label class="form-label" for="catalog-name">Nombre</label>
                    <input class="input" type="text" id="catalog-name" name="name" placeholder="Nombre del ítem">
                </div>

                <div>
                    <label class="form-label" for="catalog-status">Estado</label>
                    <select class="select" id="catalog-status" name="status">
                        <option value="activo">Activo</option>
                        <option value="inactivo">Inactivo</option>
                    </select>
                </div>
            </form>
        </div>
        <div class="modal__footer">
            <button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button>
            <button type="button" class="btn btn-primary" id="catalog-form-submit">Guardar ítem</button>
        </div>
    </div>
</div>

<script>
    (() => {
        const catalogsState = <?= json_encode(array_map(fn($catalog) => array_values($catalog['items']), $catalogs), JSON_UNESCAPED_UNICODE) ?>;
        const catalogLabels = <?= json_encode(array_map(fn($catalog) => $catalog['label'], $catalogs), JSON_UNESCAPED_UNICODE) ?>;
        const endpoint = <?= json_encode(demo_url('ajax/catalogos.php'), JSON_UNESCAPED_UNICODE) ?>;

        let activeTab = 'insurers';

        const searchInput = document.getElementById('catalog-search');
        const btnResetSearch = document.getElementById('btn-reset-search');
        const tabButtons = document.querySelectorAll('[data-catalog-tab]');
        const panels = document.querySelectorAll('[data-catalog-panel]');

        const formModalId = 'catalog-form-modal';
        const form = document.getElementById('catalog-form');
        const formTitle = document.getElementById('catalog-form-title');
        const formNote = document.getElementById('catalog-form-note');
        const formAction = document.getElementById('catalog-form-action');
        const catalogKeyInput = document.getElementById('catalog-key');
        const itemIdInput = document.getElementById('catalog-item-id');
        const nameInput = document.getElementById('catalog-name');
        const statusInput = document.getElementById('catalog-status');
        const submitButton = document.getElementById('catalog-form-submit');

        const escapeHtml = (value) => String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');

        const badgeTone = (status) => status === 'activo' ? 'success' : 'danger';

        const renderKpis = () => {
            Object.keys(catalogsState).forEach((key) => {
                const node = document.getElementById(`kpi-${key}`);
                if (node) {
                    node.textContent = String(catalogsState[key].length);
                }
            });
        };

        const getFilteredItems = (catalogKey) => {
            const term = searchInput.value.trim().toLowerCase();
            const items = catalogsState[catalogKey] || [];

            return items.filter((item) => {
                if (!term) return true;
                return String(item.name || '').toLowerCase().includes(term);
            });
        };

        const renderCatalog = (catalogKey) => {
            const tbody = document.getElementById(`catalog-table-${catalogKey}`);
            const empty = document.getElementById(`catalog-empty-${catalogKey}`);
            if (!tbody || !empty) return;

            const items = getFilteredItems(catalogKey)
                .slice()
                .sort((a, b) => String(a.name || '').localeCompare(String(b.name || '')));

            tbody.innerHTML = '';

            if (!items.length) {
                empty.hidden = false;
                return;
            }

            empty.hidden = true;

            items.forEach((item) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${escapeHtml(item.name || '—')}</td>
                    <td><span class="badge badge-${badgeTone(item.status)}">${escapeHtml((item.status || '—').charAt(0).toUpperCase() + (item.status || '—').slice(1))}</span></td>
                    <td>
                        <div class="catalog-table-actions">
                            <button type="button" class="catalog-action-btn" data-action="edit" data-catalog="${escapeHtml(catalogKey)}" data-id="${escapeHtml(item.id)}">Editar</button>
                            <button type="button" class="catalog-action-btn ${item.status === 'activo' ? 'catalog-action-btn--danger' : ''}" data-action="toggle" data-catalog="${escapeHtml(catalogKey)}" data-id="${escapeHtml(item.id)}">
                                ${item.status === 'activo' ? 'Inactivar' : 'Activar'}
                            </button>
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        };

        const renderAll = () => {
            Object.keys(catalogsState).forEach(renderCatalog);
            renderKpis();
        };

        const setActiveTab = (catalogKey) => {
            activeTab = catalogKey;

            tabButtons.forEach((button) => {
                button.classList.toggle('is-active', button.getAttribute('data-catalog-tab') === catalogKey);
            });

            panels.forEach((panel) => {
                panel.hidden = panel.getAttribute('data-catalog-panel') !== catalogKey;
            });
        };

        const resetForm = () => {
            form.reset();
            formAction.value = 'create';
            itemIdInput.value = '';
            statusInput.value = 'activo';
        };

        const openCreateModal = (catalogKey) => {
            resetForm();
            catalogKeyInput.value = catalogKey;
            formTitle.textContent = `Nuevo ítem · ${catalogLabels[catalogKey] || 'Catálogo'}`;
            formNote.textContent = `Completa los datos del nuevo registro para ${catalogLabels[catalogKey] || 'este catálogo'}.`;
            DemoApp.openModal(formModalId);
        };

        const openEditModal = (catalogKey, itemId) => {
            const item = (catalogsState[catalogKey] || []).find((row) => row.id === itemId);
            if (!item) return;

            resetForm();
            formAction.value = 'edit';
            catalogKeyInput.value = catalogKey;
            itemIdInput.value = item.id;
            nameInput.value = item.name || '';
            statusInput.value = item.status || 'activo';
            formTitle.textContent = `Editar ítem · ${catalogLabels[catalogKey] || 'Catálogo'}`;
            formNote.textContent = `Actualiza el nombre o el estado del registro dentro de ${catalogLabels[catalogKey] || 'este catálogo'}.`;
            DemoApp.openModal(formModalId);
        };

        const upsertItem = (catalogKey, item) => {
            const items = catalogsState[catalogKey] || [];
            const index = items.findIndex((row) => row.id === item.id);

            if (index >= 0) {
                items[index] = item;
            } else {
                items.unshift(item);
            }

            catalogsState[catalogKey] = items;
        };

        const replaceItem = (catalogKey, item) => {
            const items = catalogsState[catalogKey] || [];
            const index = items.findIndex((row) => row.id === item.id);
            if (index >= 0) {
                items[index] = item;
                catalogsState[catalogKey] = items;
            }
        };

        tabButtons.forEach((button) => {
            button.addEventListener('click', () => {
                setActiveTab(button.getAttribute('data-catalog-tab'));
            });
        });

        document.querySelectorAll('[data-create-catalog]').forEach((button) => {
            button.addEventListener('click', () => {
                openCreateModal(button.getAttribute('data-create-catalog'));
            });
        });

        document.getElementById('btn-new-item').addEventListener('click', () => {
            openCreateModal(activeTab);
        });

        searchInput.addEventListener('input', () => renderCatalog(activeTab));

        btnResetSearch.addEventListener('click', () => {
            searchInput.value = '';
            renderCatalog(activeTab);
        });

        document.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-action][data-catalog][data-id]');
            if (!button) return;

            const action = button.getAttribute('data-action');
            const catalogKey = button.getAttribute('data-catalog');
            const itemId = button.getAttribute('data-id');

            if (action === 'edit') {
                openEditModal(catalogKey, itemId);
                return;
            }

            if (action === 'toggle') {
                const item = (catalogsState[catalogKey] || []).find((row) => row.id === itemId);
                if (!item) return;

                const nextAction = item.status === 'activo' ? 'inactivar' : 'activar';

                DemoApp.confirm({
                    title: `${nextAction.charAt(0).toUpperCase() + nextAction.slice(1)} ítem`,
                    message: `¿Deseas ${nextAction} "${item.name}" dentro de ${catalogLabels[catalogKey] || 'este catálogo'}?`,
                    onAccept: async () => {
                        const formData = new FormData();
                        formData.append('action', 'toggle_status');
                        formData.append('catalog_key', catalogKey);
                        formData.append('item_id', itemId);

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

                        replaceItem(catalogKey, response.item);
                        renderAll();

                        DemoApp.toast({
                            title: response.title || 'Estado actualizado',
                            message: response.message || 'El registro fue actualizado correctamente.',
                            type: 'success'
                        });
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

            upsertItem(response.catalog_key, response.item);
            renderAll();
            DemoApp.closeModal(formModalId);

            DemoApp.toast({
                title: response.title || 'Ítem guardado',
                message: response.message || 'Los cambios se aplicaron correctamente.',
                type: 'success'
            });

            resetForm();
        });

        setActiveTab(activeTab);
        renderAll();
    })();
</script>
<?php
$content = ob_get_clean();

demo_render_internal_layout(
    'Catálogos',
    $content,
    [
        'breadcrumb' => ['Inicio', 'Catálogos'],
        'subtitle' => 'Administración de listas base del demo con edición rápida, control de estado y búsqueda.',
    ]
);