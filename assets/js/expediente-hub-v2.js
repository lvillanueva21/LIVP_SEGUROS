(() => {
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
  const esc = (value) => { const node = document.createElement('span'); node.textContent = String(value ?? ''); return node.innerHTML; };
  const note = (type, message, options = {}) => window.BrokerNotify?.[type]?.(message, options);
  const now = () => window.BrokerDemo?.limaDateTime?.() || new Date().toISOString().slice(0, 19).replace('T', ' ');
  const dateLabel = (value, time = false) => window.BrokerDemo?.formatPeruDate?.(value, time) || String(value || 'No registrado');
  const documentsKey = 'documents';

  const api = () => window.BrokerExpedients || null;
  const getExpedient = (id) => api()?.getExpedients?.().find((item) => String(item.id) === String(id)) || null;
  const readQuotes = () => {
    try { return JSON.parse(localStorage.getItem('broker_seguros_demo_quotes_v1') || '[]'); } catch { return []; }
  };
  const saveAll = (expedient) => {
    expedient.updated_at = now();
    const ok = api()?.saveExpedients?.(api()?.getExpedients?.());
    if (ok !== false) api()?.render?.();
    return ok !== false;
  };
  const documentTypeOptions = ['Documento de identidad', 'RUC', 'Propuesta', 'Carta', 'Voucher', 'Contrato', 'Otro'];

  function fileSize(bytes) {
    const number = Number(bytes || 0);
    if (!number) return 'Tamaño no disponible';
    if (number < 1024) return `${number} B`;
    if (number < 1024 * 1024) return `${(number / 1024).toFixed(1)} KB`;
    return `${(number / (1024 * 1024)).toFixed(2)} MB`;
  }
  function tabCount(value) { return value ? `<span class="expedient-tab-count">${esc(value)}</span>` : ''; }
  function quoteState(quote) {
    const value = String(quote?.status || 'En preparación');
    if (value === 'Aceptada') return 'is-success';
    if (value === 'Vencida' || value === 'No aceptada' || value === 'Desactivada') return 'is-warning';
    return '';
  }
  function normalizeDocuments(expedient) {
    if (!Array.isArray(expedient.documents)) expedient.documents = [];
    return expedient.documents;
  }
  function hubQuotes(expedient) {
    return readQuotes()
      .filter((quote) => String(quote?.expedient_id || '') === String(expedient.id))
      .sort((a, b) => String(b.updated_at || '').localeCompare(String(a.updated_at || '')));
  }
  function hubAlerts(expedient) {
    const rows = [];
    (expedient.policies || []).forEach((policy) => {
      (policy.alerts || []).forEach((alert) => {
        const state = window.BrokerPolicyAlerts?.alertState?.(alert, policy) || { label: alert.active === false ? 'Pausada' : 'Programada', tone: alert.active === false ? 'muted' : 'primary', due: false };
        rows.push({ policy, alert, state });
      });
    });
    return rows;
  }
  function addTimeline(expedient, kind, title, detail) {
    window.BrokerTimeline?.addEvent?.(expedient, kind, title, detail);
  }

  function quotePanelMarkup(expedient) {
    const quotes = hubQuotes(expedient);
    const records = quotes.length
      ? quotes.map((quote) => `<article class="expedient-quote-hub-card">
          <div>
            <p class="expedient-hub-code">${esc(quote.code || 'COTIZACIÓN')}</p>
            <h4>${esc(quote.title || 'Cotización sin título')}</h4>
            <p>${esc(quote.recipient?.mode === 'informal' ? quote.recipient?.informal_client || 'Cliente informal' : quote.recipient?.client_name || 'Cliente no registrado')}</p>
            <div class="expedient-hub-badges">
              <span class="expedient-hub-badge ${quoteState(quote)}">${esc(quote.status || 'En preparación')}</span>
              ${quote.valid_until ? `<span class="expedient-hub-badge">Vence: ${esc(dateLabel(quote.valid_until))}</span>` : ''}
              <span class="expedient-hub-badge">Independiente de pólizas</span>
            </div>
          </div>
          <div class="expedient-hub-actions">
            <a class="expedient-secondary-button" href="cotizaciones.php?expediente=${encodeURIComponent(expedient.id)}&cotizacion=${encodeURIComponent(quote.id)}">Abrir</a>
          </div>
        </article>`).join('')
      : '<p class="expedient-hub-empty">Aún no hay cotizaciones en este expediente. Esto no impide registrar pólizas ni cerrar el expediente.</p>';

    return `<section class="expedient-hub-card">
      <div class="expedient-hub-heading">
        <div><p class="eyebrow">ANTECEDENTES COMERCIALES</p><h3>Cotizaciones independientes</h3><p>Una cotización siempre tiene este expediente como padre, pero no crea, modifica ni define pólizas reales.</p></div>
        <div class="expedient-hub-actions"><a class="expedient-secondary-button" href="cotizaciones.php?expediente=${encodeURIComponent(expedient.id)}">Abrir cotizaciones</a><a class="expedient-primary-button" href="cotizaciones.php?expediente=${encodeURIComponent(expedient.id)}&accion=nueva">+ Nueva cotización</a></div>
      </div>
      ${records}
    </section>`;
  }
  function alertsPanelMarkup(expedient) {
    const alerts = hubAlerts(expedient);
    const records = alerts.length
      ? alerts.map(({ policy, alert, state }) => `<article class="expedient-alert-hub-card" data-alert-tone="${esc(state.tone || 'primary')}" data-alert-label="${esc(state.label || '')}">
          <div>
            <p class="expedient-hub-code">${esc(policy.code || 'PÓLIZA')}</p>
            <h4>${esc(policy.title || 'Póliza sin título')}</h4>
            <p>${esc(alert.type === 'monthly' ? `Recordatorio mensual: día ${alert.day_of_month || 1} a las ${alert.hour || '09:00'}` : `Aviso: faltan ${alert.days_before || 0} días para el fin`)}</p>
            <div class="expedient-hub-badges"><span class="expedient-hub-badge ${state.tone === 'warning' ? 'is-warning' : state.tone === 'success' ? 'is-success' : 'is-muted'}">${esc(state.label || 'Programada')}</span>${state.next ? `<span class="expedient-hub-badge">Próxima: ${esc(dateLabel(state.next, true))}</span>` : ''}</div>
          </div>
          <div class="expedient-hub-actions"><button class="expedient-secondary-button" type="button" data-hub-open-alerts="${esc(policy.id)}">Gestionar</button></div>
        </article>`).join('')
      : '<p class="expedient-hub-empty">No hay alertas configuradas en las pólizas de este expediente.</p>';

    return `<section class="expedient-hub-card">
      <div class="expedient-hub-heading"><div><p class="eyebrow">SEGUIMIENTO DE VIGENCIAS</p><h3>Alertas de pólizas</h3><p>Las alertas pertenecen a las pólizas reales; aquí se muestran para consulta y acceso rápido.</p></div></div>
      <div class="expedient-alert-filter-row"><span class="expedient-hub-note">Puedes filtrar las alertas configuradas sin salir de la ficha.</span><select id="hub-alert-filter" class="expedient-hub-filter"><option value="">Todas</option><option value="Pendiente">Pendientes</option><option value="Programada">Programadas</option><option value="Gestionada">Gestionadas</option><option value="Pausada">Pausadas</option><option value="Finalizada">Finalizadas</option></select></div>
      <div id="hub-alert-list" class="expedient-alert-hub-list">${records}</div>
    </section>`;
  }
  function documentsPanelMarkup(expedient, showForm = false) {
    const docs = normalizeDocuments(expedient).filter((item) => item.active !== false).sort((a, b) => String(b.created_at || '').localeCompare(String(a.created_at || '')));
    const records = docs.length
      ? docs.map((document) => `<article class="expedient-document-card">
          <div>
            <p class="expedient-hub-code">${esc(document.category || 'Otro')}</p>
            <h4>${esc(document.title || 'Documento')}</h4>
            <p>${esc(document.observations || 'Sin observación')}</p>
            <div class="expedient-document-meta"><span class="expedient-hub-badge">${esc(document.file?.original_name || 'Archivo')}</span><span class="expedient-hub-badge">${esc(fileSize(document.file?.size_bytes))}</span><span class="expedient-hub-badge">${esc(dateLabel(document.created_at, true))}</span></div>
          </div>
          <div class="expedient-hub-actions">
            ${document.file?.relative_path ? `<a class="expedient-secondary-button" href="api/view_expedient_document.php?path=${encodeURIComponent(document.file.relative_path)}" target="_blank" rel="noopener">Abrir</a>` : ''}
            <button class="expedient-secondary-button" type="button" data-delete-document="${esc(document.id)}">Desactivar</button>
          </div>
        </article>`).join('')
      : '<p class="expedient-hub-empty">Aún no hay documentos generales registrados en este expediente.</p>';

    const form = showForm ? `<form id="hub-document-form" class="expedient-document-form" novalidate>
      <label><span>Tipo <b>*</b></span><select id="hub-doc-category">${documentTypeOptions.map((name) => `<option value="${esc(name)}">${esc(name)}</option>`).join('')}</select></label>
      <label><span>Título <b>*</b></span><input id="hub-doc-title" maxlength="140" placeholder="Ejemplo: Carta de presentación" required></label>
      <label class="wide"><span>Archivo <b>*</b></span><input id="hub-doc-file" type="file" accept="application/pdf,image/png,image/jpeg,image/webp,.pdf,.png,.jpg,.jpeg,.webp" required><small class="expedient-document-file">PDF, PNG, JPG o WEBP · máximo 7 MB.</small></label>
      <label class="wide"><span>Observación</span><textarea id="hub-doc-observations" rows="3" maxlength="500" placeholder="Detalle breve del documento"></textarea></label>
      <div class="expedient-document-form-actions"><button id="hub-doc-cancel" class="expedient-secondary-button" type="button">Cancelar</button><button id="hub-doc-save" class="expedient-primary-button" type="submit">Guardar documento</button></div>
    </form>` : '';

    return `<section class="expedient-hub-card">
      <div class="expedient-document-toolbar"><div><p class="eyebrow">ARCHIVO INTERNO</p><h3>Documentos generales</h3><p class="expedient-hub-note">Los archivos se guardan protegidos en Hostinger y su ficha queda registrada en la caché de este navegador.</p></div><button id="hub-add-document" class="expedient-primary-button" type="button">+ Adjuntar documento</button></div>
      ${form}<div class="expedient-document-list">${records}</div>
    </section>`;
  }
  function policyFilterMarkup() {
    return `<div class="expedient-policy-filter-row"><span class="expedient-hub-note">Las pólizas son registros reales e independientes de las cotizaciones.</span><select id="hub-policy-filter" class="expedient-hub-filter"><option value="">Todas</option><option value="active">Activas</option><option value="inactive">Desactivadas</option><option value="near">Próximas a vencer</option><option value="expired">Vencidas</option></select></div>`;
  }
  function daysTo(value) {
    const end = String(value || '').slice(0, 10);
    const today = (window.BrokerDemo?.limaDate?.() || now().slice(0, 10));
    const a = Date.parse(`${today}T00:00:00Z`), b = Date.parse(`${end}T00:00:00Z`);
    return Number.isFinite(a) && Number.isFinite(b) ? Math.ceil((b - a) / 86400000) : null;
  }

  function install(expedientId) {
    const content = $('#expedient-detail-content');
    const expedient = getExpedient(expedientId || content?.dataset?.expedientId);
    if (!content || !expedient || content.dataset.view !== 'detail') return;
    if (content.dataset.hubV2 === expedient.id) return;

    const header = $('.expedient-detail-header', content);
    const grid = $('.expedient-detail-grid', content);
    const description = $('.expedient-detail-description', content);
    const placeholder = $('.expedient-quotes-placeholder', content);
    const edit = $('.expedient-detail-edit', content);
    const policies = $('#policy-section', content);
    const timeline = $('#activity-timeline', content);

    if (!header || !grid || !edit) return;
    content.dataset.hubV2 = expedient.id;

    const quoteRows = hubQuotes(expedient);
    const alertRows = hubAlerts(expedient);
    const documentRows = normalizeDocuments(expedient);

    const tabs = document.createElement('nav');
    tabs.className = 'expedient-hub-tabs';
    tabs.setAttribute('aria-label', 'Secciones del expediente');
    tabs.innerHTML = `
      <button class="expedient-hub-tab is-active" type="button" data-hub-tab="summary">Resumen</button>
      <button class="expedient-hub-tab" type="button" data-hub-tab="quotes">Cotizaciones${tabCount(quoteRows.length)}</button>
      <button class="expedient-hub-tab" type="button" data-hub-tab="policies">Pólizas${tabCount((expedient.policies || []).length)}</button>
      <button class="expedient-hub-tab" type="button" data-hub-tab="alerts">Alertas${tabCount(alertRows.length)}</button>
      <button class="expedient-hub-tab" type="button" data-hub-tab="documents">Documentos${tabCount(documentRows.length)}</button>
      <button class="expedient-hub-tab" type="button" data-hub-tab="timeline">Historial${tabCount((expedient.timeline || []).length)}</button>`;
    header.insertAdjacentElement('afterend', tabs);

    const hub = document.createElement('div');
    hub.id = 'expedient-hub-v2';
    hub.innerHTML = `
      <section class="expedient-hub-panel is-active" data-hub-panel="summary"></section>
      <section class="expedient-hub-panel" data-hub-panel="quotes">${quotePanelMarkup(expedient)}</section>
      <section class="expedient-hub-panel" data-hub-panel="policies">${policyFilterMarkup()}</section>
      <section class="expedient-hub-panel" data-hub-panel="alerts">${alertsPanelMarkup(expedient)}</section>
      <section class="expedient-hub-panel" data-hub-panel="documents">${documentsPanelMarkup(expedient)}</section>
      <section class="expedient-hub-panel" data-hub-panel="timeline"></section>`;
    tabs.insertAdjacentElement('afterend', hub);

    const summary = $('[data-hub-panel="summary"]', hub);
    [grid, description, placeholder, edit].filter(Boolean).forEach((node) => summary.appendChild(node));
    const policyPanel = $('[data-hub-panel="policies"]', hub);
    if (policies) policyPanel.appendChild(policies);
    else policyPanel.insertAdjacentHTML('beforeend', '<p class="expedient-hub-empty">No se pudo cargar la sección de pólizas. Cierra y abre nuevamente la ficha.</p>');
    const timelinePanel = $('[data-hub-panel="timeline"]', hub);
    if (timeline) timelinePanel.appendChild(timeline);
    else timelinePanel.insertAdjacentHTML('beforeend', '<p class="expedient-hub-empty">Aún no hay eventos en la línea de tiempo.</p>');

    const activate = (target) => {
      $$('.expedient-hub-tab', tabs).forEach((button) => button.classList.toggle('is-active', button.dataset.hubTab === target));
      $$('[data-hub-panel]', hub).forEach((panel) => panel.classList.toggle('is-active', panel.dataset.hubPanel === target));
    };
    $$('.expedient-hub-tab', tabs).forEach((button) => button.addEventListener('click', () => activate(button.dataset.hubTab)));

    $('#hub-policy-filter', hub)?.addEventListener('change', (event) => {
      const mode = event.target.value;
      $$('.policy-card', policyPanel).forEach((card) => {
        const policyId = card.querySelector('[data-policy-id]')?.dataset.policyId || '';
        const policy = (expedient.policies || []).find((item) => String(item.id) === String(policyId));
        const days = daysTo(policy?.ends_at);
        const show = !mode
          || (mode === 'active' && policy?.active !== false)
          || (mode === 'inactive' && policy?.active === false)
          || (mode === 'near' && policy?.active !== false && days !== null && days >= 0 && days <= 30)
          || (mode === 'expired' && days !== null && days < 0);
        card.hidden = !show;
      });
    });

    $('#hub-alert-filter', hub)?.addEventListener('change', (event) => {
      const expected = event.target.value;
      $$('.expedient-alert-hub-card', hub).forEach((card) => { card.hidden = Boolean(expected && card.dataset.alertLabel !== expected); });
    });
    $$('[data-hub-open-alerts]', hub).forEach((button) => button.addEventListener('click', () => {
      const original = $(`[data-open-policy-alerts="${CSS.escape(button.dataset.hubOpenAlerts)}"]`, policyPanel);
      if (original) original.click();
      else note('warning', 'Abre la póliza y vuelve a intentar. La sección de alertas aún no terminó de cargar.', { title: 'Alertas no disponibles' });
    }));
    bindDocumentEvents(hub, expedient);
  }

  function bindDocumentEvents(hub, expedient) {
    $('#hub-add-document', hub)?.addEventListener('click', () => {
      const panel = $('[data-hub-panel="documents"]', hub);
      panel.innerHTML = documentsPanelMarkup(expedient, true);
      bindDocumentEvents(hub, expedient);
      $('#hub-doc-title', panel)?.focus();
    });
    $('#hub-doc-cancel', hub)?.addEventListener('click', () => {
      const panel = $('[data-hub-panel="documents"]', hub);
      panel.innerHTML = documentsPanelMarkup(expedient, false);
      bindDocumentEvents(hub, expedient);
    });
    $('#hub-document-form', hub)?.addEventListener('submit', async (event) => {
      event.preventDefault();
      const file = $('#hub-doc-file', hub)?.files?.[0];
      const category = $('#hub-doc-category', hub)?.value || 'Otro';
      const title = $('#hub-doc-title', hub)?.value.trim();
      const observations = $('#hub-doc-observations', hub)?.value.trim() || '';
      if (!file || !title) {
        note('warning', 'Completa título y selecciona un archivo antes de guardar.', { title: 'Documento incompleto' });
        return;
      }
      const button = $('#hub-doc-save', hub);
      button.disabled = true; button.textContent = 'Guardando archivo…';
      try {
        const form = new FormData();
        form.append('document', file);
        form.append('expedient_code', expedient.code || 'expediente');
        const response = await fetch('api/upload_expedient_document.php', { method: 'POST', body: form, headers: { Accept: 'application/json' } });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok || !payload.ok) throw new Error(payload.message || 'No se pudo guardar el documento.');
        normalizeDocuments(expedient).push({
          id: `document-local-${Date.now()}`,
          category, title, observations, file: payload.file, active: true, created_at: now(), updated_at: now(),
        });
        addTimeline(expedient, 'document', 'Documento adjuntado', `${category} · ${title}`);
        if (!saveAll(expedient)) return;
        note('success', 'El documento fue asociado al expediente en esta maqueta.', { title: 'Documento guardado' });
        api()?.openDetail?.(expedient.id);
      } catch (error) {
        note('error', error.message || 'No se pudo adjuntar el documento.', { title: 'Error al guardar', duration: 0 });
      } finally {
        button.disabled = false; button.textContent = 'Guardar documento';
      }
    });
    $$('[data-delete-document]', hub).forEach((button) => button.addEventListener('click', async () => {
      const documentId = button.dataset.deleteDocument;
      const documentRecord = normalizeDocuments(expedient).find((item) => String(item.id) === String(documentId));
      if (!documentRecord) return;
      const confirmed = await window.BrokerNotify?.confirm?.(
        `Se desactivará “${documentRecord.title}”. El antecedente se conservará en la caché del expediente.`,
        { title: 'Desactivar documento', confirmLabel: 'Desactivar', cancelLabel: 'Cancelar' }
      );
      if (!confirmed) return;
      try {
        if (documentRecord.file?.relative_path) {
          const response = await fetch('api/delete_expedient_document.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8', Accept: 'application/json' },
            body: new URLSearchParams({ path: documentRecord.file.relative_path }).toString(),
          });
          const payload = await response.json().catch(() => ({}));
          if (!response.ok || !payload.ok) throw new Error(payload.message || 'No se pudo retirar el archivo.');
        }
        documentRecord.active = false;
        documentRecord.updated_at = now();
        addTimeline(expedient, 'document', 'Documento desactivado', documentRecord.title);
        if (!saveAll(expedient)) return;
        note('success', 'El documento fue desactivado.', { title: 'Documento actualizado' });
        api()?.openDetail?.(expedient.id);
      } catch (error) {
        note('error', error.message || 'No se pudo desactivar el documento.', { title: 'Error al actualizar', duration: 0 });
      }
    }));
  }

  document.addEventListener('broker:expedient-detail-rendered', (event) => {
    window.setTimeout(() => install(event.detail?.expedientId), 350);
  });
  window.addEventListener('load', () => window.setTimeout(() => install($('#expedient-detail-content')?.dataset?.expedientId), 450));
})();