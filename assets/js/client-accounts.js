(() => {
  const app = document.getElementById('client-accounts-app');
  if (!app) return;

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
  const esc = (value) => { const node = document.createElement('span'); node.textContent = String(value ?? ''); return node.innerHTML; };
  const parse = (id, fallback) => { try { return JSON.parse(document.getElementById(id)?.textContent || '') || fallback; } catch { return fallback; } };
  const dateLabel = (value) => window.BrokerDemo?.formatPeruDate?.(value, true) || String(value || 'No registrado');
  const now = () => window.BrokerDemo?.limaDateTime?.() || new Date().toISOString().slice(0, 19).replace('T', ' ');
  const defaults = {
    entities: parse('account-default-entities', { companies: [], consortia: [] }),
    expedients: parse('account-default-expedients', { items: [] }),
  };
  const EXP_KEY = window.BrokerDemo?.keys?.expedients || 'broker_seguros_demo_expedients_v3';
  let accounts = [];
  let entities = [];

  function getEntities() {
    const loaded = window.BrokerDemo?.loadEntities?.(defaults.entities);
    const source = loaded?.entities || defaults.entities;
    return [
      ...(Array.isArray(source?.companies) ? source.companies.map((item) => ({ ...item, entity_type: 'Empresa', account_type: 'empresa' })) : []),
      ...(Array.isArray(source?.consortia) ? source.consortia.map((item) => ({ ...item, entity_type: 'Consorcio', account_type: 'consorcio' })) : []),
    ];
  }

  function getExpedients() {
    const loaded = window.BrokerDemo?.loadExpedients?.(defaults.expedients);
    return Array.isArray(loaded?.items) ? loaded.items : Array.isArray(defaults.expedients?.items) ? defaults.expedients.items : [];
  }

  function usableEntities() {
    return getEntities()
      .filter((item) => item.active !== false)
      .map((item) => ({ ...item, ruc: String(item.ruc || item.ruc_principal || '').replace(/\D/g, '') }))
      .sort((a, b) => String(a.name || '').localeCompare(String(b.name || '')));
  }

  function setEntityDetails() {
    const entity = entities.find((item) => String(item.id) === String($('#client-account-entity').value));
    $('#client-account-document').value = entity?.ruc || '';
    $('#client-account-type').value = entity ? `${entity.entity_type}${entity.ruc.length === 11 ? '' : ' · Sin RUC propio'}` : '';

    const button = $('#client-account-submit');
    if (entity && entity.ruc.length !== 11) {
      button.disabled = true;
      button.title = 'La entidad necesita un RUC propio de 11 dígitos para tener portal Cliente.';
      window.BrokerNotify?.warning?.('Esta entidad no tiene RUC propio de 11 dígitos. Primero define su RUC antes de crearle una cuenta.', { title: 'RUC requerido' });
    } else {
      button.disabled = false;
      button.removeAttribute('title');
    }
  }

  function renderOptions() {
    entities = usableEntities();
    const select = $('#client-account-entity');
    const selected = select.value;
    select.innerHTML = `<option value="">Selecciona una entidad</option>${entities.map((item) => {
      const enabled = item.ruc.length === 11;
      const suffix = enabled ? `RUC ${item.ruc}` : 'Sin RUC propio';
      return `<option value="${esc(item.id)}" ${enabled ? '' : 'disabled'}>${esc(item.name)} · ${esc(item.entity_type)} · ${esc(suffix)}</option>`;
    }).join('')}`;
    if ([...select.options].some((option) => option.value === selected)) select.value = selected;
    setEntityDetails();
  }

  function accountRow(account) {
    const isActive = account.active !== false;
    return `<article class="client-account-row">
      <div>
        <h4>${esc(account.entity_name || 'Entidad')}</h4>
        <p>${esc(account.entity_type || account.account_type || 'Cliente')} · RUC ${esc(account.document || '')}</p>
        <div class="client-account-chips">
          <span class="client-account-chip ${isActive ? 'active' : 'inactive'}">${isActive ? 'Activa' : 'Desactivada'}</span>
          <span class="client-account-chip">Actualizada: ${esc(dateLabel(account.updated_at))}</span>
        </div>
      </div>
      <div class="client-account-row-actions">
        <button class="client-account-secondary" type="button" data-toggle-account="${esc(account.id)}">${isActive ? 'Desactivar' : 'Activar'}</button>
      </div>
    </article>`;
  }

  function renderAccounts() {
    const list = $('#client-account-list');
    if (!accounts.length) {
      list.innerHTML = '<p class="client-account-empty">Aún no se crearon accesos Cliente adicionales.</p>';
      return;
    }

    list.innerHTML = accounts
      .sort((a, b) => String(a.entity_name || '').localeCompare(String(b.entity_name || '')))
      .map(accountRow)
      .join('');

    $$('[data-toggle-account]').forEach((button) => button.addEventListener('click', async () => {
      const account = accounts.find((item) => String(item.id) === String(button.dataset.toggleAccount));
      if (!account) return;

      const action = account.active !== false ? 'desactivar' : 'activar';
      const confirmed = await window.BrokerNotify?.confirm?.(
        `${action === 'desactivar' ? 'La cuenta no podrá iniciar sesión hasta que la reactives.' : 'La cuenta podrá iniciar sesión nuevamente.'}`,
        { title: `${action === 'desactivar' ? 'Desactivar' : 'Activar'} cuenta`, confirmLabel: action === 'desactivar' ? 'Desactivar' : 'Activar', cancelLabel: 'Cancelar' }
      );
      if (!confirmed) return;

      button.disabled = true;
      try {
        const data = new URLSearchParams({ action: 'toggle', id: account.id, active: account.active !== false ? '0' : '1' });
        const response = await fetch('api/client_accounts.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8', Accept: 'application/json' }, body: data.toString() });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok || !payload.ok) throw new Error(payload.message || 'No se pudo actualizar la cuenta.');
        accounts = Array.isArray(payload.accounts) ? payload.accounts : accounts;
        renderAccounts();
        window.BrokerNotify?.success?.(payload.message || 'Cuenta actualizada.', { title: 'Acceso actualizado' });
      } catch (error) {
        window.BrokerNotify?.error?.(error.message || 'No se pudo actualizar la cuenta.', { title: 'Error de acceso', duration: 0 });
        button.disabled = false;
      }
    }));
  }

  async function loadAccounts() {
    try {
      const response = await fetch('api/client_accounts.php', { headers: { Accept: 'application/json' } });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || !payload.ok) throw new Error(payload.message || 'No se pudo cargar la lista de accesos.');
      accounts = Array.isArray(payload.accounts) ? payload.accounts : [];
      renderAccounts();
      $('#client-sync-status').textContent = payload.published_at
        ? `Última cartera publicada: ${dateLabel(payload.published_at)}.`
        : 'Aún no hay una cartera publicada en servidor.';
    } catch (error) {
      $('#client-account-list').innerHTML = '<p class="client-account-empty">No se pudo cargar las cuentas Cliente.</p>';
      window.BrokerNotify?.error?.(error.message || 'No se pudo cargar la información.', { title: 'Usuarios no disponibles', duration: 0 });
    }
  }

  $('#client-account-entity').addEventListener('change', setEntityDetails);

  $('#client-account-form').addEventListener('submit', async (event) => {
    event.preventDefault();

    const entity = entities.find((item) => String(item.id) === String($('#client-account-entity').value));
    const password = $('#client-account-password').value;
    const confirm = $('#client-account-password-confirm').value;

    if (!entity || entity.ruc.length !== 11) {
      window.BrokerNotify?.warning?.('Selecciona una entidad activa con RUC propio de 11 dígitos.', { title: 'Entidad no válida' });
      return;
    }
    if (password.length < 8 || password !== confirm) {
      window.BrokerNotify?.warning?.('La contraseña debe tener al menos 8 caracteres y coincidir con la confirmación.', { title: 'Contraseña no válida' });
      return;
    }

    const button = $('#client-account-submit');
    button.disabled = true;
    button.textContent = 'Guardando…';

    try {
      const data = new URLSearchParams({
        action: 'save',
        entity_id: String(entity.id),
        entity_name: String(entity.name || ''),
        entity_type: String(entity.entity_type || ''),
        account_type: String(entity.account_type || 'empresa'),
        document: entity.ruc,
        contact_email: String(entity.email || ''),
        contact_phone: String(entity.phone || ''),
        password,
      });

      const response = await fetch('api/client_accounts.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8', Accept: 'application/json' }, body: data.toString() });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || !payload.ok) throw new Error(payload.message || 'No se pudo guardar la cuenta.');

      accounts = Array.isArray(payload.accounts) ? payload.accounts : accounts;
      $('#client-account-password').value = '';
      $('#client-account-password-confirm').value = '';
      renderAccounts();
      window.BrokerNotify?.success?.(payload.message || 'Acceso Cliente guardado.', { title: 'Cuenta lista' });
    } catch (error) {
      window.BrokerNotify?.error?.(error.message || 'No se pudo guardar la cuenta.', { title: 'Error al crear acceso', duration: 0 });
    } finally {
      button.disabled = false;
      button.textContent = 'Guardar acceso';
    }
  });

  $('#client-sync-button').addEventListener('click', async (event) => {
    const button = event.currentTarget;
    const currentEntities = window.BrokerDemo?.loadEntities?.(defaults.entities)?.entities || defaults.entities;
    const currentExpedients = getExpedients();

    const confirmed = await window.BrokerNotify?.confirm?.(
      `Se publicarán ${currentExpedients.length} expediente(s) y las entidades actuales para que los Clientes con cuenta puedan consultarlos desde otro navegador.`,
      { title: 'Publicar cartera', confirmLabel: 'Publicar', cancelLabel: 'Cancelar' }
    );
    if (!confirmed) return;

    button.disabled = true;
    button.textContent = 'Publicando…';
    try {
      const data = new URLSearchParams({
        state: JSON.stringify({
          entities: currentEntities,
          expedients: currentExpedients,
          published_at: now(),
        }),
      });

      const response = await fetch('api/publish_client_portal_state.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8', Accept: 'application/json' }, body: data.toString() });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || !payload.ok) throw new Error(payload.message || 'No se pudo publicar la cartera.');

      $('#client-sync-status').textContent = `Última cartera publicada: ${dateLabel(payload.published_at || now())}.`;
      window.BrokerNotify?.success?.('La cartera quedó disponible para los portales Cliente vinculados.', { title: 'Cartera publicada' });
    } catch (error) {
      window.BrokerNotify?.error?.(error.message || 'No se pudo publicar la cartera.', { title: 'Publicación no realizada', duration: 0 });
    } finally {
      button.disabled = false;
      button.textContent = 'Publicar cartera actual';
    }
  });

  renderOptions();
  loadAccounts();
})();