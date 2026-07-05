(() => {
  const app = document.getElementById('client-portal-app');
  if (!app) return;

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
  const esc = (value) => { const node = document.createElement('span'); node.textContent = String(value ?? ''); return node.innerHTML; };
  const parse = (id, fallback) => { try { return JSON.parse(document.getElementById(id)?.textContent || '') || fallback; } catch { return fallback; } };
  const dateLabel = (value, time = false) => window.BrokerDemo?.formatPeruDate?.(value, time) || String(value || 'No registrado');
  const today = () => window.BrokerDemo?.limaDate?.() || new Date().toISOString().slice(0, 10);
  const normalized = (value) => String(value || '').trim().normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
  const same = (a, b) => Boolean(normalized(a) && normalized(b) && normalized(a) === normalized(b));
  const defaultExpedients = parse('client-default-expedients', { items: [] });
  const defaultClients = parse('client-default-clients', { companies: [], consortia: [] });
  const body = document.body;
  const account = {
    name: String(body.dataset.clientName || ''),
    entity: String(body.dataset.clientEntity || ''),
    document: String(body.dataset.clientDocument || ''),
    accountType: String(body.dataset.clientAccountType || ''),
  };

  function getExpedients() {
    const remote = window.BrokerClientPortalData?.state;
    if (Array.isArray(remote?.expedients)) return remote.expedients;
    const loaded = window.BrokerDemo?.loadExpedients?.(defaultExpedients);
    return Array.isArray(loaded?.items) ? loaded.items : Array.isArray(defaultExpedients?.items) ? defaultExpedients.items : [];
  }

  function getEntities() {
    const remote = window.BrokerClientPortalData?.state?.entities;
    const loaded = remote ? { entities: remote } : window.BrokerDemo?.loadEntities?.(defaultClients);
    const entities = loaded?.entities || defaultClients;
    const companies = Array.isArray(entities?.companies) ? entities.companies : [];
    const consortia = Array.isArray(entities?.consortia) ? entities.consortia : [];
    return [...companies, ...consortia];
  }

  function matchedEntityIds() {
    return new Set(getEntities()
      .filter((entity) => {
        const ruc = String(entity.ruc || entity.ruc_principal || '');
        return same(entity.name, account.entity)
          || same(entity.name, account.name)
          || Boolean(account.document && ruc && account.document === ruc);
      })
      .map((entity) => String(entity.id)));
  }

  function matchesClientRecord(record, entityIds) {
    if (!record || typeof record !== 'object') return false;
    const clientId = String(record.id || record.client_id || '');
    const clientName = String(record.name || record.client_name || '');
    const document = String(record.document || record.client_document || '');
    return entityIds.has(clientId)
      || same(clientName, account.entity)
      || same(clientName, account.name)
      || Boolean(account.document && document && account.document === document);
  }

  function matchesExpedient(expedient, entityIds) {
    return matchesClientRecord({
      client_id: expedient.client_id,
      client_name: expedient.client_name,
      client_document: expedient.client_document,
    }, entityIds);
  }

  function daysUntil(dateValue) {
    const end = String(dateValue || '').slice(0, 10);
    if (!/^\d{4}-\d{2}-\d{2}$/.test(end)) return null;
    const start = Date.parse(`${today()}T00:00:00Z`);
    const finish = Date.parse(`${end}T00:00:00Z`);
    return Number.isFinite(start) && Number.isFinite(finish) ? Math.ceil((finish - start) / 86400000) : null;
  }

  function policyStatus(policy) {
    const days = daysUntil(policy.ends_at);
    if (days === null) return { label: 'Sin vigencia definida', tone: 'muted', days };
    if (days < 0) return { label: 'Vencida', tone: 'muted', days };
    if (days <= 30) return { label: `Vence en ${days} día${days === 1 ? '' : 's'}`, tone: 'warning', days };
    return { label: 'Vigente', tone: 'good', days };
  }

  function publicCaseState(value) {
    const raw = normalized(value);
    if (raw.includes('cerrad') || raw.includes('finaliz') || raw.includes('complet')) return { label: 'Finalizada', tone: 'good' };
    if (raw.includes('observ') || raw.includes('pend')) return { label: 'En seguimiento', tone: 'warning' };
    return { label: 'En gestión', tone: 'muted' };
  }

  function collect() {
    const entityIds = matchedEntityIds();
    const allExpedients = getExpedients();
    const visibleCases = allExpedients.filter((expedient) => matchesExpedient(expedient, entityIds));

    const policies = [];
    allExpedients.forEach((expedient) => {
      (Array.isArray(expedient.policies) ? expedient.policies : []).forEach((policy) => {
        const snapshotMatch = matchesClientRecord(policy.client_snapshot || {}, entityIds);
        const parentMatch = matchesExpedient(expedient, entityIds);
        if (policy.active === false || (!snapshotMatch && !parentMatch)) return;
        policies.push({ ...policy, expedient_code: expedient.code, expedient_title: expedient.title, expedient_state: expedient.state });
      });
    });

    return {
      cases: visibleCases.sort((a, b) => String(b.updated_at || '').localeCompare(String(a.updated_at || ''))),
      policies: policies.sort((a, b) => String(a.ends_at || '').localeCompare(String(b.ends_at || ''))),
    };
  }

  function policyCard(record) {
    const status = policyStatus(record);
    const amount = record.insured_amount
      ? `${record.currency_name ? `${record.currency_name} ` : ''}${record.insured_amount}`
      : 'No registrada';

    return `<article class="client-policy-card" data-policy-id="${esc(record.id)}">
      <div>
        <p class="client-record-code">${esc(record.code || 'PÓLIZA')}</p>
        <h4>${esc(record.title || 'Póliza sin título')}</h4>
        <p>${esc([record.insurer_name || 'Aseguradora no registrada', record.insurance_type_name || 'Tipo no registrado'].join(' · '))}</p>
        <div class="client-record-meta">
          <span class="client-chip ${status.tone === 'good' ? 'is-good' : status.tone === 'warning' ? 'is-warning' : 'is-muted'}">${esc(status.label)}</span>
          <span class="client-chip">${esc(dateLabel(record.starts_at))} — ${esc(dateLabel(record.ends_at))}</span>
          <span class="client-chip">${esc(amount)}</span>
        </div>
      </div>
      <div class="client-record-actions"><button type="button" class="client-open-button" data-open-policy="${esc(record.id)}">Ver detalle</button></div>
    </article>`;
  }

  function caseCard(record, policies) {
    const state = publicCaseState(record.state);
    const linked = policies.filter((policy) => String(policy.expedient_code) === String(record.code));
    return `<article class="client-case-card" data-case-id="${esc(record.id)}">
      <div>
        <p class="client-record-code">${esc(record.code || 'GESTIÓN')}</p>
        <h4>${esc(record.title || 'Gestión sin título')}</h4>
        <p>Actualizada: ${esc(dateLabel(record.updated_at, true))}</p>
        <div class="client-record-meta">
          <span class="client-chip ${state.tone === 'good' ? 'is-good' : state.tone === 'warning' ? 'is-warning' : 'is-muted'}">${esc(state.label)}</span>
          <span class="client-chip">${linked.length} póliza${linked.length === 1 ? '' : 's'} disponible${linked.length === 1 ? '' : 's'}</span>
        </div>
      </div>
      <div class="client-record-actions"><button type="button" class="client-open-button" data-open-case="${esc(record.id)}">Ver gestión</button></div>
    </article>`;
  }

  function openDialog(eyebrow, title, bodyHtml) {
    const dialog = $('#client-detail-dialog');
    $('#client-detail-eyebrow').textContent = eyebrow;
    $('#client-detail-title').textContent = title;
    $('#client-detail-body').innerHTML = bodyHtml;
    if (!dialog.open) dialog.showModal();
  }

  function render() {
    const data = collect();
    const active = data.policies.filter((policy) => {
      const state = policyStatus(policy);
      return state.days !== null && state.days >= 0;
    });
    const near = active.filter((policy) => {
      const state = policyStatus(policy);
      return state.days !== null && state.days <= 30;
    });

    $('#client-policy-active').textContent = active.length;
    $('#client-policy-near').textContent = near.length;
    $('#client-expedient-total').textContent = data.cases.length;
    $('#client-policy-count').textContent = data.policies.length;
    $('#client-case-count').textContent = data.cases.length;

    const mode = $('#client-policy-filter').value;
    const filtered = data.policies.filter((policy) => {
      const state = policyStatus(policy);
      if (mode === 'active') return state.days !== null && state.days >= 0;
      if (mode === 'near') return state.days !== null && state.days >= 0 && state.days <= 30;
      if (mode === 'expired') return state.days !== null && state.days < 0;
      return true;
    });

    $('#client-policy-list').innerHTML = filtered.map(policyCard).join('');
    $('#client-policy-empty').hidden = filtered.length !== 0;
    $('#client-case-list').innerHTML = data.cases.map((item) => caseCard(item, data.policies)).join('');
    $('#client-case-empty').hidden = data.cases.length !== 0;

    $$('[data-open-policy]').forEach((button) => button.addEventListener('click', () => {
      const policy = data.policies.find((item) => String(item.id) === String(button.dataset.openPolicy));
      if (!policy) return;
      const state = policyStatus(policy);
      const amount = policy.insured_amount ? `${policy.currency_name ? `${policy.currency_name} ` : ''}${policy.insured_amount}` : 'No registrada';
      openDialog('PÓLIZA', policy.title || 'Póliza', `
        <div class="client-detail-grid">
          <div><dt>Código</dt><dd>${esc(policy.code || 'No registrado')}</dd></div>
          <div><dt>Estado de vigencia</dt><dd>${esc(state.label)}</dd></div>
          <div><dt>Aseguradora</dt><dd>${esc(policy.insurer_name || 'No registrada')}</dd></div>
          <div><dt>Tipo de seguro</dt><dd>${esc(policy.insurance_type_name || 'No registrado')}</dd></div>
          <div><dt>Inicio de vigencia</dt><dd>${esc(dateLabel(policy.starts_at, true))}</dd></div>
          <div><dt>Fin de vigencia</dt><dd>${esc(dateLabel(policy.ends_at, true))}</dd></div>
          <div><dt>Suma asegurada</dt><dd>${esc(amount)}</dd></div>
          <div><dt>Gestión vinculada</dt><dd>${esc(policy.expedient_code || 'No registrada')}</dd></div>
        </div>
        <div class="client-policy-document">
          ${policy.file?.relative_path
            ? `<a class="client-policy-pdf-button" target="_blank" rel="noopener" href="api/view_policy_pdf.php?path=${encodeURIComponent(policy.file.relative_path)}">Ver PDF autorizado</a>`
            : '<span class="client-policy-pdf-pending">PDF autorizado pendiente</span>'}
        </div>
        <p class="client-detail-note">Esta es una vista de consulta. Los documentos, observaciones, alertas y actividades internas son gestionados exclusivamente por la corredora.</p>`);
    }));

    $$('[data-open-case]').forEach((button) => button.addEventListener('click', () => {
      const record = data.cases.find((item) => String(item.id) === String(button.dataset.openCase));
      if (!record) return;
      const state = publicCaseState(record.state);
      const linked = data.policies.filter((policy) => String(policy.expedient_code) === String(record.code));
      openDialog('GESTIÓN', record.title || 'Gestión', `
        <div class="client-detail-grid">
          <div><dt>Código de gestión</dt><dd>${esc(record.code || 'No registrado')}</dd></div>
          <div><dt>Estado</dt><dd>${esc(state.label)}</dd></div>
          <div><dt>Fecha de inicio</dt><dd>${esc(dateLabel(record.opened_at))}</dd></div>
          <div><dt>Última actualización</dt><dd>${esc(dateLabel(record.updated_at, true))}</dd></div>
          <div><dt>Pólizas disponibles</dt><dd>${esc(String(linked.length))}</dd></div>
          <div><dt>Entidad</dt><dd>${esc(account.entity || account.name)}</dd></div>
        </div>
        <p class="client-detail-note">Esta pantalla muestra el seguimiento público de tu gestión. No incluye cotizaciones, información comercial, documentos internos ni observaciones del equipo.</p>`);
    }));
  }

  $$('.client-portal-tab').forEach((button) => button.addEventListener('click', () => {
    const tab = button.dataset.clientTab;
    $$('.client-portal-tab').forEach((item) => item.classList.toggle('is-active', item === button));
    $$('[data-client-panel]').forEach((panel) => panel.classList.toggle('is-active', panel.dataset.clientPanel === tab));
  }));

  $('#client-policy-filter').addEventListener('change', render);
  $('#client-detail-close').addEventListener('click', () => $('#client-detail-dialog').close());
  $('#client-detail-dialog').addEventListener('click', (event) => {
    if (event.target === $('#client-detail-dialog')) $('#client-detail-dialog').close();
  });

  render();
  window.addEventListener('broker:client-portal-data-ready', render);
})();
