(() => {
  const app = document.getElementById('expedients-app');
  const rawData = document.getElementById('expedient-default-data');
  const rawCatalogs = document.getElementById('expedient-catalog-data');
  if (!app || !rawData || !rawCatalogs) return;

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
  const parse = (node, fallback) => { try { return JSON.parse(node?.textContent || ''); } catch { return fallback; } };
  const esc = (value) => { const node = document.createElement('span'); node.textContent = String(value ?? ''); return node.innerHTML; };
  const note = (type, message, options = {}) => window.BrokerNotify?.[type]?.(message, options);

  const data = parse(rawData, {});
  const catalogs = window.BrokerDemo?.loadCatalogs
    ? window.BrokerDemo.loadCatalogs(parse(rawCatalogs, {})).catalogs
    : parse(rawCatalogs, {});

  let entities = (window.BrokerDemo?.loadEntities?.(data.entity_defaults || {}).entities) || data.entity_defaults || { companies: [], consortia: [] };
  let contacts = (window.BrokerDemo?.loadContacts?.(data.contacts || data.entity_defaults?.contacts || []).items) || data.contacts || data.entity_defaults?.contacts || [];
  let expedients = (window.BrokerDemo?.loadExpedients?.(data).items) || data.items || [];

  const summary = {
    total: $('#summary-total'),
    pending: $('#summary-pending-client'),
    withoutQuotes: $('#summary-without-quotes'),
    closed: $('#summary-closed'),
  };

  const search = $('#filter-search');
  const filterState = $('#filter-state');
  const filterClient = $('#filter-client');
  const clear = $('#clear-expedient-filters');
  const table = $('#expedient-table-body');
  const empty = $('#expedient-empty');
  const add = $('#add-expedient');

  const formDialog = $('#expedient-form-dialog');
  const createForm = $('#expedient-form');
  const formClose = $('#expedient-form-close');
  const formCancel = $('#expedient-form-cancel');
  const contactSelect = $('#expedient-contact');
  const clientSelect = $('#expedient-client');
  const stateSelect = $('#expedient-state');
  const titleInput = $('#expedient-title');
  const descriptionInput = $('#expedient-description');

  const quickPanel = $('#quick-contact-panel');
  const showQuick = $('#show-quick-contact');
  const cancelQuick = $('#cancel-quick-contact');
  const cancelQuickSecondary = $('#cancel-quick-contact-secondary');
  const saveQuick = $('#save-quick-contact');
  const quickName = $('#quick-contact-name');
  const quickMobile = $('#quick-contact-mobile');
  const quickEmail = $('#quick-contact-email');
  const quickLabel = $('#quick-contact-label');
  const quickEntity = $('#quick-contact-entity');

  const detailDialog = $('#expedient-detail-dialog');
  const detail = $('#expedient-detail-content');
  const closeDetail = $('#expedient-detail-close');

  const now = () => window.BrokerDemo?.limaDateTime?.() || new Date().toISOString().slice(0, 19).replace('T', ' ');
  const today = () => window.BrokerDemo?.limaDate?.() || now().slice(0, 10);
  const dateLabel = (value, time = false) => window.BrokerDemo?.formatPeruDate?.(value, time) || String(value || 'No registrado');
  const active = (items) => (items || []).filter((item) => item?.active !== false);
  const states = () => active(catalogs?.estados_expediente?.items).map((item) => item.name);
  const entityRecords = () => window.BrokerDemo?.entityRecords ? window.BrokerDemo.entityRecords(entities) : [...(entities.companies || []), ...(entities.consortia || [])];
  const entity = (id) => entityRecords().find((item) => String(item.id) === String(id)) || null;
  const contact = (id) => contacts.find((item) => String(item.id) === String(id)) || null;
  const principalRuc = (item) => item?.ruc_principal || item?.principal_ruc || item?.ruc || '';
  const selectedEntityName = (item) => item?.client_name || 'Pendiente de definir';
  const policyCount = (item) => Array.isArray(item?.policies) ? item.policies.length : 0;
  const quoteCount = (item) => Array.isArray(item?.quotes) ? item.quotes.length : 0;
  const slug = (value) => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
  const stateBadge = (value) => `<span class="expedient-state state-${esc(slug(value))}">${esc(value)}</span>`;

  function saveEntities() {
    try {
      window.BrokerDemo?.writeStorage?.(window.BrokerDemo.keys.entities, entities)
        || localStorage.setItem('broker_seguros_demo_entities_v1', JSON.stringify(entities));
      return true;
    } catch {
      note('error', 'No se pudieron guardar las entidades actualizadas en este navegador.', { title: 'Error de almacenamiento', duration: 0 });
      return false;
    }
  }

  function saveContacts() {
    try {
      localStorage.setItem(window.BrokerDemo?.keys?.contacts || 'broker_seguros_demo_contacts_v1', JSON.stringify(contacts));
      return true;
    } catch {
      note('error', 'No se pudo guardar el contacto en este navegador.', { title: 'Error de almacenamiento', duration: 0 });
      return false;
    }
  }

  function saveExpedients(next = expedients) {
    try {
      const prepared = window.BrokerTimeline?.prepareExpedientsSave?.(next) || next;
      localStorage.setItem(window.BrokerDemo?.keys?.expedients || 'broker_seguros_demo_expedients_v3', JSON.stringify(prepared));
      expedients = prepared;
      return true;
    } catch {
      note('error', 'No se pudo guardar el expediente en este navegador.', { title: 'Error de almacenamiento', duration: 0 });
      return false;
    }
  }

  function option(items, placeholder, valueKey = 'id', textKey = 'name', selected = '') {
    return [
      `<option value="">${esc(placeholder)}</option>`,
      ...items.map((item) => `<option value="${esc(item[valueKey])}" ${String(item[valueKey]) === String(selected) ? 'selected' : ''}>${esc(item[textKey])}</option>`),
    ].join('');
  }

  function activeEntities() {
    return active(entityRecords());
  }

  function contactLabel(item) {
    const links = active(item?.relationships || []);
    const primary = links.find((link) => link.is_primary) || links[0];
    return [
      item?.full_name,
      item?.mobile ? `Cel. ${item.mobile}` : '',
      primary ? entity(primary.entity_id)?.name : '',
      item?.label,
    ].filter(Boolean).join(' · ');
  }

  function contactSuggestedEntity(contactId) {
    const selected = contact(contactId);
    const links = active(selected?.relationships || []).filter((link) => entity(link.entity_id)?.active !== false);
    return links.length === 1 ? links[0].entity_id : '';
  }

  function entityInfo(item) {
    if (!item?.client_id) return 'Se solicitará antes de registrar una póliza.';
    const found = entity(item.client_id);
    return [found?.entity_type || item.entity_type, principalRuc(found) ? `RUC ${principalRuc(found)}` : item.client_document].filter(Boolean).join(' · ');
  }

  function renderFilters() {
    const previousState = filterState.value;
    const previousClient = filterClient.value;
    filterState.innerHTML = option(states().map((name) => ({ id: name, name })), 'Todas');
    filterClient.innerHTML = option([{ id: '__pending__', name: 'Pendiente de definir' }, ...activeEntities()], 'Todos');
    filterState.value = states().includes(previousState) ? previousState : '';
    filterClient.value = activeEntities().some((item) => item.id === previousClient) || previousClient === '__pending__' ? previousClient : '';
  }

  function filtered() {
    const query = String(search.value || '').trim().toLocaleLowerCase('es-PE');
    return [...expedients]
      .filter((item) => {
        const values = [item.code, item.title, item.description, item.contact_name, item.contact_mobile, item.client_name, item.client_document].join(' ').toLocaleLowerCase('es-PE');
        return !query || values.includes(query);
      })
      .filter((item) => !filterState.value || item.state === filterState.value)
      .filter((item) => !filterClient.value || (filterClient.value === '__pending__' ? !item.client_id : String(item.client_id) === filterClient.value))
      .sort((a, b) => String(b.updated_at).localeCompare(String(a.updated_at)));
  }

  function renderTable() {
    const items = filtered();
    empty.hidden = items.length !== 0;
    table.innerHTML = items.map((item) => {
      const c = contact(item.contact_id);
      const person = item.contact_name || c?.full_name || 'Contacto pendiente de regularizar';
      const mobile = item.contact_mobile || c?.mobile || 'Sin celular registrado';
      const quotes = quoteCount(item);
      const policies = policyCount(item);
      const operationCount = policies ? `${quotes} cot. · ${policies} pól.` : (quotes === 1 ? '1 cotización' : `${quotes} cotizaciones`);
      return `<tr>
        <td><span class="expedient-code">${esc(item.code)}</span></td>
        <td><span class="expedient-contact-name">${esc(person)}</span><span class="expedient-contact-meta">${esc(mobile)}</span></td>
        <td><span class="expedient-client-name ${item.client_id ? '' : 'is-pending'}">${esc(selectedEntityName(item))}</span><span class="expedient-client-document">${esc(entityInfo(item))}</span></td>
        <td><span class="expedient-title">${esc(item.title)}</span></td>
        <td>${stateBadge(item.state)}</td>
        <td><span class="expedient-quote-count ${quotes || policies ? 'has-quotes' : ''}">${esc(operationCount)}</span></td>
        <td>${esc(dateLabel(item.updated_at, true))}</td>
        <td><button class="expedient-row-button" type="button" data-id="${esc(item.id)}">Ver ficha</button></td>
      </tr>`;
    }).join('');

    $$('[data-id]', table).forEach((button) => {
      button.addEventListener('click', () => openDetail(button.dataset.id));
    });
  }

  function render() {
    summary.total.textContent = expedients.length;
    summary.pending.textContent = expedients.filter((item) => !item.client_id).length;
    summary.withoutQuotes.textContent = expedients.filter((item) => !quoteCount(item)).length;
    summary.closed.textContent = expedients.filter((item) => item.state === 'Cerrado').length;
    renderFilters();
    renderTable();
  }

  function renderCreateChoices(selectedContact = '', selectedClient = '') {
    contactSelect.innerHTML = option(active(contacts).map((item) => ({ id: item.id, name: contactLabel(item) })), 'Selecciona un contacto de gestión', 'id', 'name', selectedContact);
    clientSelect.innerHTML = option(activeEntities(), 'Pendiente de definir', 'id', 'name', selectedClient);
    stateSelect.innerHTML = option(states().map((name) => ({ id: name, name })), 'Abierto', 'id', 'name', 'Abierto');
    quickEntity.innerHTML = option(activeEntities(), 'Sin entidad vinculada');
  }

  function resetQuick() {
    [quickName, quickMobile, quickEmail, quickLabel].forEach((input) => { input.value = ''; });
    quickEntity.value = '';
  }

  function toggleQuick(show) {
    quickPanel.hidden = !show;
    if (show) quickName.focus();
    else resetQuick();
  }

  function openCreate() {
    createForm.reset();
    renderCreateChoices();
    stateSelect.value = 'Abierto';
    toggleQuick(false);
    formDialog.showModal();
    contactSelect.focus();
  }

  function closeCreate() {
    toggleQuick(false);
    formDialog.close();
  }

  function suggestedClient() {
    const id = contactSuggestedEntity(contactSelect.value);
    if (id && !clientSelect.value) clientSelect.value = id;
  }

  function createQuickContact() {
    const fullName = quickName.value.trim();
    const mobile = quickMobile.value.trim();
    if (!fullName || !mobile) {
      note('warning', 'Para crear el contacto rápido completa nombre y celular.', { title: 'Faltan datos obligatorios' });
      return;
    }

    const related = entity(quickEntity.value);
    const label = quickLabel.value.trim();
    const item = {
      id: `contact-local-${Date.now()}`,
      full_name: fullName,
      mobile,
      email: quickEmail.value.trim(),
      document_type: '',
      document: '',
      label,
      observations: '',
      active: true,
      relationships: related ? [{ id: `rel-${Date.now()}`, entity_id: related.id, label, is_primary: true, active: true }] : [],
    };

    contacts.push(item);
    if (!saveContacts()) {
      contacts.pop();
      return;
    }

    renderCreateChoices(item.id);
    contactSelect.value = item.id;
    if (related) clientSelect.value = related.id;
    toggleQuick(false);
    note('success', `${item.full_name} quedó registrado y seleccionado.`, { title: 'Contacto registrado' });
  }

  function nextExpedientCode() {
    const year = window.BrokerDemo?.limaYear?.() || new Date().getFullYear();
    const prefix = `EXP-${year}-`;
    const numbers = expedients
      .filter((item) => String(item.code).startsWith(prefix))
      .map((item) => Number(String(item.code).slice(prefix.length)))
      .filter(Number.isFinite);
    return `${prefix}${String((numbers.length ? Math.max(...numbers) : 0) + 1).padStart(4, '0')}`;
  }

  function submitCreate(event) {
    event.preventDefault();
    const c = contact(contactSelect.value);
    const e = entity(clientSelect.value);
    const title = titleInput.value.trim();
    const description = descriptionInput.value.trim();

    if (!c || !title || !description) {
      note('warning', 'Completa contacto de gestión, nombre y descripción.', { title: 'Faltan datos esenciales' });
      return;
    }

    const item = {
      id: `exp-local-${Date.now()}`,
      code: nextExpedientCode(),
      title,
      description,
      contact_id: c.id,
      contact_name: c.full_name,
      contact_mobile: c.mobile,
      client_id: e?.id || '',
      client_name: e?.name || '',
      client_document: principalRuc(e) ? `RUC ${principalRuc(e)}` : '',
      entity_type: e?.entity_type || '',
      state: stateSelect.value || 'Abierto',
      opened_at: today(),
      updated_at: now(),
      quotes: [],
      policies: [],
      legacy_assigned_executive_user_id: '',
      legacy_assigned_executive_name: '',
    };

    expedients.push(item);
    if (!saveExpedients()) {
      expedients.pop();
      return;
    }

    closeCreate();
    render();
    openDetail(item.id);
    note('success', `${item.code} fue creado. Puede continuar sin cliente, cotización o póliza.`, { title: 'Expediente creado' });
  }

  function detailOptions(selected, items, placeholder, isContact = false) {
    return option(items, placeholder, 'id', isContact ? 'label' : 'name', selected);
  }

  function dispatchDetailRendered(item) {
    document.dispatchEvent(new CustomEvent('broker:expedient-detail-rendered', {
      detail: { expedientId: item.id },
    }));
  }

  function openDetail(id) {
    const item = expedients.find((record) => String(record.id) === String(id));
    if (!item) {
      note('error', 'No se encontró el expediente solicitado. Recarga la página e intenta nuevamente.', { title: 'Expediente no disponible', duration: 0 });
      return;
    }

    const c = contact(item.contact_id);
    const quotes = quoteCount(item);
    const policyTotal = policyCount(item);
    const contactsForSelect = active(contacts).map((record) => ({ id: record.id, label: contactLabel(record) }));

    detail.dataset.expedientId = item.id;
    detail.dataset.view = 'detail';
    detail.innerHTML = `
      <div class="expedient-detail-header">
        <div>
          <p class="expedient-detail-code">${esc(item.code)}</p>
          <h3 class="expedient-detail-title">${esc(item.title)}</h3>
        </div>
        ${stateBadge(item.state)}
      </div>

      <div class="expedient-detail-grid">
        <div><dt>Contacto de gestión</dt><dd>${esc(item.contact_name || c?.full_name || 'Pendiente')}</dd></div>
        <div><dt>Celular</dt><dd>${esc(item.contact_mobile || c?.mobile || 'No registrado')}</dd></div>
        <div><dt>Cliente o entidad</dt><dd>${esc(selectedEntityName(item))}</dd></div>
        <div><dt>Referencia</dt><dd>${esc(entityInfo(item))}</dd></div>
        <div><dt>Apertura</dt><dd>${esc(dateLabel(item.opened_at))}</dd></div>
        <div><dt>Última actualización</dt><dd>${esc(dateLabel(item.updated_at, true))}</dd></div>
      </div>

      <p class="expedient-detail-description">${esc(item.description)}</p>

      <section class="expedient-quotes-placeholder">
        <h3>Cotizaciones y pólizas</h3>
        <p>${quotes ? `Tiene ${quotes} cotización(es) vinculada(s).` : 'No tiene cotizaciones. Es válido: puede continuar o cerrarse sin cotización, seguro, póliza, pago o documento.'}</p>
        ${policyTotal ? `<p class="expedient-quote-placeholder-status">También tiene ${policyTotal} póliza(s) registrada(s).</p>` : ''}
      </section>

      <section class="expedient-detail-edit">
        <h3>Actualizar información</h3>
        <p>El contacto, nombre y descripción se mantienen como datos esenciales. El cliente puede quedar pendiente.</p>
        <div class="expedient-form-grid">
          <label><span>Contacto de gestión <b aria-hidden="true">*</b></span><select id="d-contact">${detailOptions(item.contact_id, contactsForSelect, 'Selecciona un contacto', true)}</select></label>
          <label><span>Cliente o entidad</span><select id="d-client">${detailOptions(item.client_id, activeEntities(), 'Pendiente de definir')}</select></label>
          <label><span>Situación</span><select id="d-state">${detailOptions(item.state, states().map((name) => ({ id: name, name })), 'Abierto')}</select></label>
          <label><span>Nombre del expediente <b aria-hidden="true">*</b></span><input id="d-title" maxlength="140" value="${esc(item.title)}"></label>
        </div>
        <label class="expedient-description-field"><span>Descripción o detalle <b aria-hidden="true">*</b></span><textarea id="d-description" rows="4" maxlength="800">${esc(item.description)}</textarea></label>
        <div class="expedient-detail-state-actions">
          <button id="cancel-detail" class="expedient-secondary-button" type="button">Cancelar</button>
          <button id="save-detail" class="expedient-primary-button" type="button">Guardar cambios</button>
        </div>
      </section>
    `;

    $('#save-detail', detail).addEventListener('click', () => updateDetail(item.id));
    $('#cancel-detail', detail).addEventListener('click', () => openDetail(item.id));

    if (!detailDialog.open) detailDialog.showModal();
    dispatchDetailRendered(item);
  }

  function updateDetail(id) {
    const item = expedients.find((record) => String(record.id) === String(id));
    const c = contact($('#d-contact', detail)?.value);
    const e = entity($('#d-client', detail)?.value);
    const title = $('#d-title', detail)?.value.trim();
    const description = $('#d-description', detail)?.value.trim();

    if (!item || !c || !title || !description) {
      note('warning', 'Completa contacto de gestión, nombre y descripción antes de guardar.', { title: 'Faltan datos esenciales' });
      return;
    }

    Object.assign(item, {
      contact_id: c.id,
      contact_name: c.full_name,
      contact_mobile: c.mobile,
      client_id: e?.id || '',
      client_name: e?.name || '',
      client_document: principalRuc(e) ? `RUC ${principalRuc(e)}` : '',
      entity_type: e?.entity_type || '',
      state: $('#d-state', detail)?.value || 'Abierto',
      title,
      description,
      updated_at: now(),
    });

    if (!saveExpedients()) return;
    render();
    openDetail(item.id);
    note('success', `${item.code} fue actualizado temporalmente en este navegador.`, { title: 'Expediente actualizado' });
  }

  search.addEventListener('input', renderTable);
  filterState.addEventListener('change', renderTable);
  filterClient.addEventListener('change', renderTable);
  clear.addEventListener('click', () => {
    search.value = '';
    filterState.value = '';
    filterClient.value = '';
    renderTable();
    note('info', 'Se limpiaron los filtros.', { title: 'Filtros restablecidos' });
  });

  add.addEventListener('click', openCreate);
  createForm.addEventListener('submit', submitCreate);
  formClose.addEventListener('click', closeCreate);
  formCancel.addEventListener('click', closeCreate);
  showQuick.addEventListener('click', () => toggleQuick(true));
  cancelQuick.addEventListener('click', () => toggleQuick(false));
  cancelQuickSecondary.addEventListener('click', () => toggleQuick(false));
  saveQuick.addEventListener('click', createQuickContact);
  contactSelect.addEventListener('change', suggestedClient);
  closeDetail.addEventListener('click', () => detailDialog.close());

  window.BrokerExpedients = {
    openDetail,
    render,
    getExpedients: () => expedients,
    saveExpedients,
    getCatalogs: () => catalogs,
  };

  render();

  const requestedExpedient = new URLSearchParams(window.location.search).get('expediente');
  if (requestedExpedient) {
    window.setTimeout(() => openDetail(requestedExpedient), 0);
  }
})();
