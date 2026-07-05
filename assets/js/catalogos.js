(() => {
  const app = document.getElementById('catalogs-app');
  const rawData = document.getElementById('catalog-default-data');

  if (!app || !rawData) {
    return;
  }

  const canManage = document.body.dataset.catalogManager === '1';
  const storageKey = 'broker_seguros_demo_catalogs_v1';
  const groupsContainer = document.getElementById('catalog-groups');
  const countLabel = document.getElementById('catalog-count');
  const currentTitle = document.getElementById('catalog-current-title');
  const currentDescription = document.getElementById('catalog-current-description');
  const currentEyebrow = document.getElementById('catalog-current-eyebrow');
  const tableBody = document.getElementById('catalog-table-body');
  const emptyMessage = document.getElementById('catalog-empty');
  const addButton = document.getElementById('add-catalog-item');
  const restoreButton = document.getElementById('restore-catalogs');

  const dialog = document.getElementById('catalog-item-dialog');
  const dialogForm = document.getElementById('catalog-item-form');
  const dialogTitle = document.getElementById('catalog-dialog-title');
  const dialogClose = document.getElementById('catalog-dialog-close');
  const dialogCancel = document.getElementById('catalog-dialog-cancel');
  const inputId = document.getElementById('catalog-item-id');
  const inputCode = document.getElementById('catalog-item-code');
  const inputName = document.getElementById('catalog-item-name');
  const inputDetail = document.getElementById('catalog-item-detail');
  const inputStatus = document.getElementById('catalog-item-status');

  let defaults = {};
  try {
    defaults = JSON.parse(rawData.textContent || '{}');
  } catch (error) {
    defaults = {};
  }

  const clone = (value) => JSON.parse(JSON.stringify(value));

  function notify(type, message, options = {}) {
    window.BrokerNotify?.[type]?.(message, options);
  }

  function getCatalogs() {
    if (window.BrokerDemo?.loadCatalogs) {
      return window.BrokerDemo.loadCatalogs(defaults).catalogs;
    }

    return clone(defaults);
  }

  let catalogs = getCatalogs();
  let currentCatalogId = Object.keys(catalogs)[0] || '';

  function escapeHtml(value) {
    const container = document.createElement('div');
    container.textContent = String(value ?? '');
    return container.innerHTML;
  }

  function saveCatalogs() {
    try {
      localStorage.setItem(storageKey, JSON.stringify(catalogs));
      return true;
    } catch (error) {
      notify('error', 'No se pudo guardar el cambio en este navegador. Libera espacio del sitio o intenta nuevamente.', {
        title: 'Error de almacenamiento',
        duration: 0,
      });
      return false;
    }
  }

  function currentCatalog() {
    return catalogs[currentCatalogId] || null;
  }

  function logCatalogAction(action, section = 'catalogos') {
    const data = new URLSearchParams({ action, section }).toString();

    try {
      if (navigator.sendBeacon) {
        navigator.sendBeacon(
          'api/cache_action.php',
          new Blob([data], { type: 'application/x-www-form-urlencoded;charset=UTF-8' })
        );
        return;
      }

      fetch('api/cache_action.php', {
        method: 'POST',
        keepalive: true,
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
          Accept: 'application/json',
        },
        body: data,
      });
    } catch (error) {
      // La funcionalidad de catálogos sigue disponible aunque falle el registro.
    }
  }

  function renderGroups() {
    const entries = Object.values(catalogs);
    countLabel.textContent = String(entries.length);

    groupsContainer.innerHTML = entries.map((catalog) => {
      const isActive = catalog.id === currentCatalogId;
      return `
        <button
          class="catalog-group-button ${isActive ? 'is-active' : ''}"
          type="button"
          role="tab"
          aria-selected="${isActive ? 'true' : 'false'}"
          data-catalog-id="${escapeHtml(catalog.id)}"
        >
          <span class="catalog-group-icon" aria-hidden="true">${escapeHtml(catalog.icon || '•')}</span>
          <span>${escapeHtml(catalog.label || 'Catálogo')}</span>
        </button>
      `;
    }).join('');

    groupsContainer.querySelectorAll('[data-catalog-id]').forEach((button) => {
      button.addEventListener('click', () => {
        currentCatalogId = button.dataset.catalogId || '';
        render();
      });
    });
  }

  function statusMarkup(status) {
    const isActive = String(status).toLowerCase() === 'activo';
    const className = isActive ? 'catalog-status-active' : 'catalog-status-inactive';
    return `<span class="catalog-status ${className}">${escapeHtml(status)}</span>`;
  }

  function renderTable() {
    const catalog = currentCatalog();
    if (!catalog) {
      tableBody.innerHTML = `<tr><td colspan="${canManage ? 5 : 4}">No se encontró el catálogo seleccionado.</td></tr>`;
      emptyMessage.hidden = true;
      return;
    }

    const items = Array.isArray(catalog.items) ? catalog.items : [];
    currentEyebrow.textContent = `${catalog.label || 'CATÁLOGO'}`.toUpperCase();
    currentTitle.textContent = catalog.label || 'Catálogo';
    currentDescription.textContent = catalog.description || '';

    emptyMessage.hidden = items.length !== 0;

    if (items.length === 0) {
      tableBody.innerHTML = '';
      return;
    }

    tableBody.innerHTML = items.map((item) => {
      const actions = canManage
        ? `
          <td>
            <div class="catalog-row-actions">
              <button type="button" class="catalog-row-action" data-action="edit" data-item-id="${escapeHtml(item.id)}">Editar</button>
              <button type="button" class="catalog-row-action is-danger" data-action="toggle" data-item-id="${escapeHtml(item.id)}">
                ${String(item.status).toLowerCase() === 'activo' ? 'Desactivar' : 'Activar'}
              </button>
            </div>
          </td>
        `
        : '';

      return `
        <tr>
          <td>${escapeHtml(item.code)}</td>
          <td>${escapeHtml(item.name)}</td>
          <td>${escapeHtml(item.detail)}</td>
          <td>${statusMarkup(item.status)}</td>
          ${actions}
        </tr>
      `;
    }).join('');

    if (canManage) {
      tableBody.querySelectorAll('[data-action="edit"]').forEach((button) => {
        button.addEventListener('click', () => openEditDialog(button.dataset.itemId || ''));
      });

      tableBody.querySelectorAll('[data-action="toggle"]').forEach((button) => {
        button.addEventListener('click', () => toggleItem(button.dataset.itemId || ''));
      });
    }
  }

  function render() {
    renderGroups();
    renderTable();
  }

  function openCreateDialog() {
    const catalog = currentCatalog();
    if (!dialog || !dialogForm || !catalog) return;

    dialogForm.reset();
    inputId.value = '';
    inputStatus.value = 'Activo';
    dialogTitle.textContent = `Agregar en ${catalog.label}`;
    dialog.showModal();
    inputCode.focus();
  }

  function openEditDialog(itemId) {
    const catalog = currentCatalog();
    const item = catalog?.items?.find((candidate) => candidate.id === itemId);
    if (!dialog || !dialogForm || !item || !catalog) return;

    inputId.value = item.id;
    inputCode.value = item.code || '';
    inputName.value = item.name || '';
    inputDetail.value = item.detail || '';
    inputStatus.value = String(item.status).toLowerCase() === 'inactivo' ? 'Inactivo' : 'Activo';
    dialogTitle.textContent = `Editar ${catalog.label}`;
    dialog.showModal();
    inputCode.focus();
  }

  function closeDialog() {
    if (dialog?.open) {
      dialog.close();
    }
  }

  function createDemoId(catalogId) {
    return `${catalogId}-${Date.now()}-${Math.random().toString(16).slice(2, 7)}`;
  }

  function handleDialogSubmit(event) {
    event.preventDefault();

    const catalog = currentCatalog();
    if (!catalog || !dialogForm) return;

    const code = inputCode.value.trim().toUpperCase();
    const name = inputName.value.trim();
    const detail = inputDetail.value.trim();
    const status = inputStatus.value === 'Inactivo' ? 'Inactivo' : 'Activo';
    const itemId = inputId.value.trim();

    if (!code || !name) {
      notify('warning', 'Completa como mínimo el código y el nombre antes de guardar.', {
        title: 'Faltan datos por completar',
      });
      return;
    }

    catalog.items = Array.isArray(catalog.items) ? catalog.items : [];
    const duplicate = catalog.items.some((item) => (
      item.code.toUpperCase() === code && item.id !== itemId
    ));

    if (duplicate) {
      notify('error', `El código ${code} ya existe dentro de ${catalog.label}. Usa otro código para continuar.`, {
        title: 'Código duplicado',
        duration: 9000,
      });
      return;
    }

    const action = itemId ? 'actualizó' : 'agregó';

    if (itemId) {
      const item = catalog.items.find((candidate) => candidate.id === itemId);
      if (!item) {
        notify('error', 'No se encontró el elemento que intentabas editar. Actualiza la página e inténtalo nuevamente.', {
          title: 'Elemento no disponible',
        });
        return;
      }

      item.code = code;
      item.name = name;
      item.detail = detail;
      item.status = status;
    } else {
      catalog.items.push({
        id: createDemoId(catalog.id),
        code,
        name,
        detail,
        status,
      });
    }

    if (!saveCatalogs()) {
      return;
    }

    logCatalogAction(`${itemId ? 'Editó' : 'Agregó'} ${catalog.label}: ${name}`);
    closeDialog();
    render();
    notify('success', `Se ${action} ${name}. El cambio quedó guardado temporalmente solo en este navegador mientras no exista MySQL.`, {
      title: itemId ? 'Catálogo actualizado' : 'Elemento agregado',
    });
  }

  function toggleItem(itemId) {
    const catalog = currentCatalog();
    const item = catalog?.items?.find((candidate) => candidate.id === itemId);
    if (!catalog || !item) return;

    item.status = String(item.status).toLowerCase() === 'activo' ? 'Inactivo' : 'Activo';

    if (!saveCatalogs()) {
      item.status = item.status === 'Activo' ? 'Inactivo' : 'Activo';
      return;
    }

    logCatalogAction(`${item.status === 'Activo' ? 'Activó' : 'Desactivó'} ${catalog.label}: ${item.name}`);
    render();
    notify('success', `Se ${item.status === 'Activo' ? 'activó' : 'desactivó'} ${item.name}. El cambio quedó guardado temporalmente solo en este navegador mientras no exista MySQL.`, {
      title: 'Estado actualizado',
    });
  }

  async function restoreDefaults() {
    const confirmed = await window.BrokerNotify?.confirm?.(
      'Se eliminarán todos los cambios demo guardados solo en este navegador. ¿Deseas restaurar los datos iniciales?',
      {
        title: 'Restaurar catálogos demo',
        confirmLabel: 'Restaurar',
        cancelLabel: 'Cancelar',
      }
    );

    if (!confirmed) return;

    try {
      localStorage.removeItem(storageKey);
      catalogs = clone(defaults);
      currentCatalogId = Object.keys(catalogs)[0] || '';
      logCatalogAction('Restauró los catálogos demo');
      render();
      notify('success', 'Los catálogos demo fueron restaurados correctamente. Se eliminaron únicamente los cambios guardados en este navegador.', {
        title: 'Datos demo restaurados',
      });
    } catch (error) {
      notify('error', 'No se pudieron restaurar los datos demo en este navegador.', {
        title: 'Error de almacenamiento',
        duration: 0,
      });
    }
  }

  addButton?.addEventListener('click', openCreateDialog);
  restoreButton?.addEventListener('click', restoreDefaults);
  dialogForm?.addEventListener('submit', handleDialogSubmit);
  dialogClose?.addEventListener('click', closeDialog);
  dialogCancel?.addEventListener('click', closeDialog);

  render();
})();
