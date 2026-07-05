(() => {
  const root = document.getElementById('workspace-content');
  if (!root || document.body.dataset.role === 'cliente') return;
  const esc = (value) => { const n = document.createElement('span'); n.textContent = String(value ?? ''); return n.innerHTML; };
  const dateLabel = (value, withTime = false) => window.BrokerDemo?.formatPeruDate?.(value, withTime) || String(value || 'No registrado');
  const now = () => window.BrokerDemo?.limaDateTime?.() || new Date().toISOString().slice(0, 19).replace('T', ' ');
  const read = (key, fallback = []) => { try { const raw = localStorage.getItem(key); return raw ? JSON.parse(raw) : fallback; } catch { return fallback; } };
  const expKey = window.BrokerDemo?.keys?.expedients || 'broker_seguros_demo_expedients_v3';
  const quotesKey = 'broker_seguros_demo_quotes_v1';
  const daysTo = (value) => {
    const end = String(value || '').slice(0, 10); const current = now().slice(0, 10);
    if (!/^\d{4}-\d{2}-\d{2}$/.test(end)) return null;
    return Math.ceil((Date.parse(`${end}T00:00:00Z`) - Date.parse(`${current}T00:00:00Z`)) / 86400000);
  };
  const eventList = (expedient) => (Array.isArray(expedient.timeline) ? expedient.timeline : []).map((item) => ({ ...item, expedient })).sort((a, b) => String(b.at).localeCompare(String(a.at)));
  const panel = document.createElement('section');
  panel.id = 'live-cache-dashboard';
  panel.className = 'live-cache-dashboard';

  function render() {
    const expedients = window.BrokerDemo?.loadExpedients?.({ items: [] }).items || read(expKey, []);
    const quotes = read(quotesKey, []);
    const policies = expedients.flatMap((expedient) => (expedient.policies || []).map((policy) => ({ expedient, policy })));
    const activePolicies = policies.filter(({ policy }) => policy.active !== false && (daysTo(policy.ends_at) === null || daysTo(policy.ends_at) >= 0));
    const due = window.BrokerPolicyAlerts?.dueItems?.(expedients) || [];
    const upcoming = policies.filter(({ policy }) => { const d = daysTo(policy.ends_at); return policy.active !== false && d !== null && d >= 0 && d <= 45; }).sort((a, b) => daysTo(a.policy.ends_at) - daysTo(b.policy.ends_at)).slice(0, 5);
    const activity = expedients.flatMap(eventList).slice(0, 7);
    panel.innerHTML = `<div class="live-dashboard-heading"><div><p class="eyebrow">INFORMACIÓN ACTUAL EN CACHÉ</p><h2>Resumen operativo</h2><p>Estos datos provienen de los expedientes, cotizaciones, pólizas y alertas guardados en este navegador.</p></div><div class="live-dashboard-actions"><a class="live-dashboard-button" href="expedientes.php">Expedientes</a><a class="live-dashboard-button is-primary" href="cotizaciones.php">Cotizaciones</a></div></div>
      <div class="live-metrics"><article><span>▧</span><p>Expedientes</p><strong>${expedients.length}</strong><small>${expedients.filter((item) => !item.client_id).length} con cliente pendiente</small></article><article><span>▤</span><p>Cotizaciones</p><strong>${quotes.length}</strong><small>Independientes de las pólizas</small></article><article><span>✓</span><p>Pólizas activas</p><strong>${activePolicies.length}</strong><small>${policies.length} registradas en total</small></article><article class="is-warning"><span>◷</span><p>Alertas pendientes</p><strong>${due.length}</strong><small>Requieren gestión manual</small></article></div>
      <div class="live-dashboard-grid"><section class="live-dashboard-card"><div class="live-card-heading"><div><p class="eyebrow">ACTIVIDAD RECIENTE</p><h3>Últimos movimientos</h3></div></div>${activity.length ? `<div class="live-activity-list">${activity.map((item) => `<a href="expedientes.php?expediente=${encodeURIComponent(item.expedient.id)}" class="live-activity"><span class="live-activity-dot ${esc(item.kind || 'info')}"></span><div><strong>${esc(item.title || 'Actividad registrada')}</strong><p>${esc(item.expedient.code)}${item.detail ? ` · ${esc(item.detail)}` : ''}</p><small>${esc(dateLabel(item.at, true))}</small></div></a>`).join('')}</div>` : '<p class="live-empty">Aún no hay movimientos guardados. Las próximas acciones en Expedientes, Pólizas, Alertas y Cotizaciones aparecerán aquí.</p>'}</section>
      <section class="live-dashboard-card"><div class="live-card-heading"><div><p class="eyebrow">PRÓXIMAS VIGENCIAS</p><h3>Pólizas por revisar</h3></div></div>${upcoming.length ? `<div class="live-expiry-list">${upcoming.map(({ expedient, policy }) => { const d = daysTo(policy.ends_at); return `<a href="expedientes.php?expediente=${encodeURIComponent(expedient.id)}" class="live-expiry"><div><strong>${esc(policy.code)} · ${esc(policy.title)}</strong><p>${esc(expedient.client_name || expedient.contact_name || expedient.code)}</p></div><span class="live-expiry-days ${d <= 7 ? 'is-danger' : d <= 30 ? 'is-warning' : ''}">${d === 0 ? 'Vence hoy' : `${d} día(s)`}</span></a>`; }).join('')}</div>` : '<p class="live-empty">No hay pólizas activas con vencimiento dentro de los próximos 45 días.</p>'}</section></div>`;
  }
  const anchor = root.querySelector('.welcome-banner');
  if (anchor) anchor.insertAdjacentElement('afterend', panel); else root.prepend(panel);
  render();
})();
