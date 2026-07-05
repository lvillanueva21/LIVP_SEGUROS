(() => {
  const EXP_KEY = window.BrokerDemo?.keys?.expedients || 'broker_seguros_demo_expedients_v3';
  const QUOTE_KEY = 'broker_seguros_demo_quotes_v1';
  const $ = (selector, root = document) => root.querySelector(selector);
  const esc = (value) => { const node = document.createElement('span'); node.textContent = String(value ?? ''); return node.innerHTML; };
  const now = () => window.BrokerDemo?.limaDateTime?.() || new Date().toISOString().slice(0, 19).replace('T', ' ');
  const dateLabel = (value, withTime = true) => window.BrokerDemo?.formatPeruDate?.(value, withTime) || String(value || 'No registrado');
  const clone = (value) => JSON.parse(JSON.stringify(value));
  const read = (key, fallback = []) => { try { const raw = localStorage.getItem(key); return raw ? JSON.parse(raw) : fallback; } catch { return fallback; } };
  const nativeSet = localStorage.setItem.bind(localStorage);
  let interceptingQuotes = false;

  function normalizedEvent(value, index) {
    const item = value && typeof value === 'object' ? value : {};
    return {
      id: String(item.id || `timeline-${index + 1}`),
      kind: String(item.kind || 'info'),
      title: String(item.title || 'Actividad registrada'),
      detail: String(item.detail || ''),
      at: String(item.at || now()),
    };
  }
  function timelineOf(expedient) {
    const list = Array.isArray(expedient?.timeline) ? expedient.timeline.map(normalizedEvent) : [];
    return list.sort((a, b) => String(b.at).localeCompare(String(a.at)));
  }
  function addEvent(expedient, kind, title, detail = '') {
    const list = timelineOf(expedient);
    const latest = list[0];
    if (latest && latest.title === title && latest.detail === detail && String(latest.at).slice(0, 16) === String(now()).slice(0, 16)) {
      expedient.timeline = list;
      return;
    }
    list.unshift({ id: `timeline-${Date.now()}-${Math.random().toString(16).slice(2, 7)}`, kind, title, detail, at: now() });
    expedient.timeline = list.slice(0, 160);
  }
  function corePolicy(policy) {
    const copy = clone(policy || {});
    delete copy.alerts; delete copy.payments; delete copy.claims; delete copy.updated_at; delete copy.created_at; delete copy.file;
    return copy;
  }
  function simple(value) { return JSON.stringify(value ?? null); }
  function policyById(expedient, id) { return (expedient?.policies || []).find((item) => String(item.id) === String(id)); }
  function diffAlerts(expedient, oldPolicy, newPolicy) {
    const previous = Array.isArray(oldPolicy?.alerts) ? oldPolicy.alerts : [];
    const current = Array.isArray(newPolicy?.alerts) ? newPolicy.alerts : [];
    const oldMap = new Map(previous.map((item) => [String(item.id), item]));
    const newMap = new Map(current.map((item) => [String(item.id), item]));
    current.forEach((alert) => {
      const old = oldMap.get(String(alert.id));
      const label = newPolicy.code || newPolicy.title || 'Póliza';
      if (!old) { addEvent(expedient, 'alert', 'Alerta de póliza configurada', `${label} · ${alert.type === 'monthly' ? 'recordatorio mensual' : `faltan ${alert.days_before || 0} días`}`); return; }
      if (old.active !== alert.active) { addEvent(expedient, 'alert', alert.active === false ? 'Alerta de póliza pausada' : 'Alerta de póliza reactivada', label); return; }
      if (String(old.last_managed_at || '') !== String(alert.last_managed_at || '') && alert.last_managed_at) { addEvent(expedient, 'alert', 'Alerta marcada como gestionada', label); return; }
      const oldCopy = clone(old); const newCopy = clone(alert);
      delete oldCopy.updated_at; delete oldCopy.created_at; delete newCopy.updated_at; delete newCopy.created_at;
      if (simple(oldCopy) !== simple(newCopy)) addEvent(expedient, 'alert', 'Alerta de póliza actualizada', label);
    });
    previous.forEach((alert) => { if (!newMap.has(String(alert.id))) addEvent(expedient, 'alert', 'Alerta de póliza eliminada', newPolicy.code || newPolicy.title || 'Póliza'); });
  }
  function diffExpedient(before, after) {
    const changes = [];
    [['title', 'nombre'], ['description', 'descripción'], ['contact_name', 'contacto'], ['client_name', 'cliente o entidad'], ['state', 'situación']].forEach(([key, label]) => {
      if (String(before?.[key] || '') !== String(after?.[key] || '')) changes.push(label);
    });
    if (changes.length) addEvent(after, 'expedient', 'Información del expediente actualizada', `Cambios: ${changes.join(', ')}`);
    const oldPolicies = Array.isArray(before?.policies) ? before.policies : [];
    const newPolicies = Array.isArray(after?.policies) ? after.policies : [];
    const oldMap = new Map(oldPolicies.map((item) => [String(item.id), item]));
    const newMap = new Map(newPolicies.map((item) => [String(item.id), item]));
    newPolicies.forEach((policy) => {
      const old = oldMap.get(String(policy.id));
      const label = policy.code || policy.title || 'Póliza';
      if (!old) { addEvent(after, 'policy', 'Póliza registrada', label); return; }
      if (old.active !== policy.active) addEvent(after, 'policy', policy.active === false ? 'Póliza desactivada' : 'Póliza reactivada', label);
      if (simple(corePolicy(old)) !== simple(corePolicy(policy))) addEvent(after, 'policy', 'Póliza actualizada', label);
      diffAlerts(after, old, policy);
    });
    oldPolicies.forEach((policy) => { if (!newMap.has(String(policy.id))) addEvent(after, 'policy', 'Póliza retirada de la caché', policy.code || policy.title || 'Póliza'); });
    const oldQuotes = Array.isArray(before?.quotes) ? before.quotes : [];
    const newQuotes = Array.isArray(after?.quotes) ? after.quotes : [];
    if (simple(oldQuotes) !== simple(newQuotes)) addEvent(after, 'quote', 'Resumen de cotizaciones actualizado', 'Las cotizaciones conservan independencia frente a las pólizas.');
  }
  function prepareExpedientsSave(next) {
    const before = read(EXP_KEY, []);
    const previous = new Map((Array.isArray(before) ? before : []).map((item) => [String(item.id), item]));
    const prepared = clone(Array.isArray(next) ? next : []);
    prepared.forEach((expedient) => {
      const old = previous.get(String(expedient.id));
      if (!old) addEvent(expedient, 'expedient', 'Expediente creado', expedient.code || 'Nuevo expediente');
      else diffExpedient(old, expedient);
      expedient.timeline = timelineOf(expedient);
    });
    return prepared;
  }
  function appendQuoteTimeline(beforeQuotes, afterQuotes) {
    const before = Array.isArray(beforeQuotes) ? beforeQuotes : [];
    const after = Array.isArray(afterQuotes) ? afterQuotes : [];
    const oldMap = new Map(before.map((item) => [String(item.id), item]));
    const records = read(EXP_KEY, []);
    let changed = false;
    after.forEach((quote) => {
      const old = oldMap.get(String(quote.id));
      const exp = records.find((item) => String(item.id) === String(quote.expedient_id));
      if (!exp) return;
      const label = quote.code || quote.title || 'Cotización';
      if (!old) addEvent(exp, 'quote', 'Cotización registrada', label);
      else if (simple(old) !== simple(quote)) addEvent(exp, 'quote', 'Cotización actualizada', label);
      changed = true;
    });
    if (changed) nativeSet(EXP_KEY, JSON.stringify(records));
  }
  function decorateTimeline(expedientId, expanded = false) {
    const content = $('#expedient-detail-content');
    const exp = window.BrokerExpedients?.getExpedients?.().find((item) => String(item.id) === String(expedientId || content?.dataset?.expedientId));
    if (!content || !exp || content.dataset.view !== 'detail') return;
    content.querySelector('#activity-timeline')?.remove();
    let events = timelineOf(exp);
    if (!events.length) events = [{ id: 'baseline', kind: 'expedient', title: 'Expediente registrado', detail: exp.code || '', at: exp.opened_at || exp.updated_at || now() }];
    const visible = expanded ? events : events.slice(0, 6);
    const section = document.createElement('section');
    section.id = 'activity-timeline';
    section.className = 'activity-timeline';
    section.innerHTML = `<div class="activity-timeline-heading"><div><p class="eyebrow">TRAZABILIDAD</p><h3>Línea de tiempo</h3><p>Registra acciones de expediente, cotizaciones, pólizas y alertas guardadas en este navegador.</p></div><span class="activity-timeline-count">${events.length}</span></div><div class="activity-timeline-list">${visible.map((event) => `<article class="activity-event activity-${esc(event.kind)}"><span class="activity-event-dot" aria-hidden="true"></span><div><strong>${esc(event.title)}</strong>${event.detail ? `<p>${esc(event.detail)}</p>` : ''}<small>${esc(dateLabel(event.at, true))}</small></div></article>`).join('')}</div>${events.length > 6 ? `<button class="activity-toggle" type="button">${expanded ? 'Ver menos' : `Ver ${events.length - 6} evento(s) más`}</button>` : ''}`;
    const anchor = content.querySelector('.expedient-detail-edit');
    if (anchor) anchor.insertAdjacentElement('beforebegin', section); else content.appendChild(section);
    section.querySelector('.activity-toggle')?.addEventListener('click', () => decorateTimeline(exp.id, !expanded));
  }
  function installQuoteInterceptor() {
    try {
      localStorage.setItem = function(key, value) {
        if (String(key) === QUOTE_KEY && !interceptingQuotes) {
          const before = read(QUOTE_KEY, []);
          let after = [];
          try { after = JSON.parse(String(value)); } catch {}
          const result = nativeSet(key, value);
          interceptingQuotes = true;
          try { appendQuoteTimeline(before, after); } finally { interceptingQuotes = false; }
          return result;
        }
        return nativeSet(key, value);
      };
    } catch { /* La línea de tiempo del expediente continúa funcionando. */ }
  }
  installQuoteInterceptor();
  window.BrokerTimeline = { prepareExpedientsSave, timelineOf, addEvent, decorateTimeline };
  document.addEventListener('broker:expedient-detail-rendered', (event) => window.setTimeout(() => decorateTimeline(event.detail?.expedientId), 0));
})();
