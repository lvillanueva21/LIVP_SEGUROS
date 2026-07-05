
(() => {
  const app = document.getElementById('client-claims-app');
  if (!app) return;

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
  const esc = (value) => { const node = document.createElement('span'); node.textContent = String(value ?? ''); return node.innerHTML; };
  const parse = (id, fallback) => { try { return JSON.parse(document.getElementById(id)?.textContent || '') || fallback; } catch { return fallback; } };
  const now = () => window.BrokerDemo?.limaDateTime?.() || new Date().toISOString().slice(0, 19).replace('T', ' ');
  const today = () => window.BrokerDemo?.limaDate?.() || now().slice(0, 10);
  const dateLabel = (value, time = false) => window.BrokerDemo?.formatPeruDate?.(value, time) || String(value || 'No registrado');
  const normalized = (value) => String(value || '').trim().normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
  const same = (a, b) => Boolean(normalized(a) && normalized(b) && normalized(a) === normalized(b));
  const defaults = {
    expedients: parse('claim-default-expedients', { items: [] }),
    clients: parse('claim-default-clients', { companies: [], consortia: [] }),
  };
  const body = document.body;
  const account = {
    name: String(body.dataset.clientName || ''),
    entity: String(body.dataset.clientEntity || ''),
    document: String(body.dataset.clientDocument || ''),
    phone: String(body.dataset.clientPhone || ''),
  };
  const key = window.BrokerDemo?.keys?.expedients || 'broker_seguros_demo_expedients_v3';

  function getExpedients() {
    const remote = window.BrokerClientPortalData?.state;
    if (Array.isArray(remote?.expedients)) return remote.expedients;
    const loaded = window.BrokerDemo?.loadExpedients?.(defaults.expedients);
    return Array.isArray(loaded?.items) ? loaded.items : Array.isArray(defaults.expedients?.items) ? defaults.expedients.items : [];
  }

  function getEntities() {
    const remote = window.BrokerClientPortalData?.state?.entities;
    const loaded = remote ? { entities: remote } : window.BrokerDemo?.loadEntities?.(defaults.clients);
    const records = loaded?.entities || defaults.clients;
    return [...(records.companies || []), ...(records.consortia || [])];
  }

  function entityIds() {
    return new Set(getEntities().filter((item) => {
      const ruc = String(item.ruc || item.ruc_principal || '');
      return same(item.name, account.entity)
        || same(item.name, account.name)
        || Boolean(account.document && ruc && account.document === ruc);
    }).map((item) => String(item.id)));
  }

  function matchesClient(record, ids) {
    if (!record || typeof record !== 'object') return false;
    const id = String(record.id || record.client_id || '');
    const name = String(record.name || record.client_name || '');
    const document = String(record.document || record.client_document || '');
    return ids.has(id)
      || same(name, account.entity)
      || same(name, account.name)
      || Boolean(account.document && document && account.document === document);
  }

  function normalizeClaim(value, index) {
    const record = value && typeof value === 'object' ? value : {};
    return {
      id: String(record.id || `claim-${index + 1}`),
      code: String(record.code || ''),
      category: String(record.category || 'Otro'),
      event_date: String(record.event_date || ''),
      location: String(record.location || ''),
      description: String(record.description || ''),
      contact_phone: String(record.contact_phone || ''),
      status: ['Reportado', 'En revisión', 'En gestión', 'Cerrado'].includes(String(record.status || '')) ? String(record.status) : 'Reportado',
      reported_at: String(record.reported_at || now()),
      updated_at: String(record.updated_at || now()),
    };
  }

  function collectPolicies() {
    const ids = entityIds();
    const rows = [];

    getExpedients().forEach((expedient) => {
      const parentMatch = matchesClient({
        client_id: expedient.client_id,
        client_name: expedient.client_name,
        client_document: expedient.client_document,
      }, ids);

      (Array.isArray(expedient.policies) ? expedient.policies : []).forEach((policy) => {
        const policyMatch = matchesClient(policy.client_snapshot || {}, ids);
        if (policy.active === false || (!parentMatch && !policyMatch)) return;
        rows.push({ expedient, policy });
      });
    });

    return rows;
  }

  function collectClaims() {
    const rows = [];
    collectPolicies().forEach(({ expedient, policy }) => {
      (Array.isArray(policy.claims) ? policy.claims : []).map(normalizeClaim).forEach((claim) => {
        rows.push({ expedient, policy, claim });
      });
    });
    return rows.sort((a, b) => String(b.claim.reported_at || '').localeCompare(String(a.claim.reported_at || '')));
  }

  function nextCode(claims) {
    const year = (window.BrokerDemo?.limaYear?.() || new Date().getFullYear());
    const prefix = `SIN-${year}-`;
    const values = claims
      .filter((row) => String(row.claim.code || '').startsWith(prefix))
      .map((row) => Number(String(row.claim.code).slice(prefix.length)))
      .filter(Number.isFinite);
    return `${prefix}${String((values.length ? Math.max(...values) : 0) + 1).padStart(4, '0')}`;
  }

  function statusClass(status) {
    if (status === 'Cerrado') return 'is-good';
    if (status === 'En revisión' || status === 'En gestión') return 'is-primary';
    return '';
  }

  function claimCard(row) {
    return `<article class="client-claim-card">
      <div>
        <p class="client-claim-code">${esc(row.claim.code || 'SINIESTRO')}</p>
        <h4>${esc(row.claim.category)} · ${esc(row.policy.code || 'Póliza')}</h4>
        <p>Fecha del evento: ${esc(dateLabel(row.claim.event_date))} · Reportado: ${esc(dateLabel(row.claim.reported_at, true))}</p>
        <div class="client-claim-meta">
          <span class="client-claim-chip ${statusClass(row.claim.status)}">${esc(row.claim.status)}</span>
          ${row.claim.location ? `<span class="client-claim-chip">${esc(row.claim.location)}</span>` : ''}
        </div>
      </div>
      <div><button class="client-claim-open" type="button" data-open-claim="${esc(row.claim.id)}" data-open-policy="${esc(row.policy.id)}">Ver detalle</button></div>
    </article>`;
  }

  function populatePolicies() {
    const select = $('#claim-policy');
    const current = select.value;
    const rows = collectPolicies();
    select.innerHTML = `<option value="">Selecciona una póliza</option>${rows.map(({ expedient, policy }) => `<option value="${esc(policy.id)}" data-expedient="${esc(expedient.id)}">${esc(policy.code || 'Póliza')} — ${esc(policy.title || 'Sin título')} · ${esc(policy.insurer_name || 'Aseguradora')}</option>`).join('')}`;
    if ([...select.options].some((option) => option.value === current)) select.value = current;
  }

  function render() {
    const claims = collectClaims();
    $('#claims-total').textContent = claims.length;
    $('#claims-open').textContent = claims.filter((row) => row.claim.status !== 'Cerrado').length;
    $('#client-claim-list').innerHTML = claims.map(claimCard).join('');
    $('#client-claim-empty').hidden = claims.length !== 0;
    populatePolicies();

    $$('[data-open-claim]').forEach((button) => button.addEventListener('click', () => {
      const row = claims.find((item) => String(item.claim.id) === String(button.dataset.openClaim) && String(item.policy.id) === String(button.dataset.openPolicy));
      if (!row) return;
      $('#client-claim-detail-title').textContent = `${row.claim.code || 'Reporte'} · ${row.claim.category}`;
      $('#client-claim-detail-body').innerHTML = `
        <div class="client-claim-detail-grid">
          <div><dt>Póliza</dt><dd>${esc(row.policy.code || 'No registrada')}</dd></div>
          <div><dt>Estado</dt><dd>${esc(row.claim.status)}</dd></div>
          <div><dt>Fecha del evento</dt><dd>${esc(dateLabel(row.claim.event_date))}</dd></div>
          <div><dt>Reportado el</dt><dd>${esc(dateLabel(row.claim.reported_at, true))}</dd></div>
          <div><dt>Lugar</dt><dd>${esc(row.claim.location || 'No registrado')}</dd></div>
          <div><dt>Teléfono de contacto</dt><dd>${esc(row.claim.contact_phone || 'No registrado')}</dd></div>
        </div>
        <p class="client-claim-description">${esc(row.claim.description || 'Sin descripción inicial registrada.')}</p>`;
      $('#client-claim-detail-dialog').showModal();
    }));
  }

  function saveExpedients(items) {
    if (window.BrokerDemo?.writeStorage) {
      window.BrokerDemo.writeStorage(key, items);
      return;
    }
    localStorage.setItem(key, JSON.stringify(items));
  }

  function addTimeline(expedient, claim, policy) {
    const timeline = Array.isArray(expedient.timeline) ? expedient.timeline : [];
    timeline.unshift({
      id: `timeline-${Date.now()}-${Math.random().toString(16).slice(2, 7)}`,
      kind: 'claim',
      title: 'Siniestro reportado por cliente',
      detail: `${claim.code} · ${policy.code || 'Póliza'} · ${claim.category}`,
      at: now(),
    });
    expedient.timeline = timeline.slice(0, 160);
  }

  $('#client-claim-form').addEventListener('submit', async (event) => {
    event.preventDefault();

    const policyId = $('#claim-policy').value;
    const eventDate = $('#claim-event-date').value;
    const category = $('#claim-category').value;
    const location = $('#claim-location').value.trim();
    const contactPhone = $('#claim-phone').value.trim() || account.phone;
    const description = $('#claim-description').value.trim();

    if (!policyId || !eventDate || !category || !description) {
      window.BrokerNotify?.warning?.('Completa póliza, fecha, tipo de reporte y descripción inicial.', { title: 'Reporte incompleto' });
      return;
    }
    if (eventDate > today()) {
      window.BrokerNotify?.warning?.('La fecha del evento no puede ser futura.', { title: 'Fecha no válida' });
      return;
    }

    const button = $('#client-claim-form button[type="submit"]');
    button.disabled = true;
    button.textContent = 'Registrando…';

    try {
      const data = new URLSearchParams({
        action: 'report_claim',
        policy_id: policyId,
        event_date: eventDate,
        category,
        location,
        contact_phone: contactPhone,
        description,
      });

      const response = await fetch('api/client_portal_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8', Accept: 'application/json' },
        body: data.toString(),
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || !payload.ok) throw new Error(payload.message || 'No se pudo registrar el reporte.');

      await window.BrokerClientPortalData?.refresh?.();
      $('#client-claim-form').reset();
      $('#claim-event-date').value = today();
      $('#claim-phone').value = account.phone;
      render();
      window.BrokerNotify?.success?.(`${payload.claim?.code || 'El reporte'} fue registrado y será revisado por la corredora.`, { title: 'Reporte enviado' });
    } catch (error) {
      window.BrokerNotify?.error?.(error.message || 'No se pudo registrar el reporte.', { title: 'Reporte no guardado', duration: 0 });
    } finally {
      button.disabled = false;
      button.textContent = 'Registrar reporte';
    }
  });

  $('#client-claim-detail-close').addEventListener('click', () => $('#client-claim-detail-dialog').close());
  $('#client-claim-detail-dialog').addEventListener('click', (event) => {
    if (event.target === $('#client-claim-detail-dialog')) $('#client-claim-detail-dialog').close();
  });

  $('#claim-event-date').value = today();
  $('#claim-phone').value = account.phone;
  render();
  window.addEventListener('broker:client-portal-data-ready', render);
})();
