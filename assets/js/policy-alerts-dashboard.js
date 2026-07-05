(() => {
  const root = document.getElementById('workspace-content');
  if (!root || document.body.dataset.role === 'cliente') return;
  const esc = (value) => { const n = document.createElement('span'); n.textContent = String(value ?? ''); return n.innerHTML; };
  const data = window.BrokerDemo?.loadExpedients?.({items:[]}).items || [];
  const due = window.BrokerPolicyAlerts?.dueItems?.(data) || [];
  const section = document.createElement('section');
  section.className = 'dashboard-panel policy-alert-dashboard';
  section.innerHTML = `<div class="section-heading"><div><p class="eyebrow">SEGUIMIENTO DE PÓLIZAS</p><h2>Alertas pendientes</h2></div><span class="policy-alert-dashboard-count">${due.length}</span></div><p class="dashboard-panel-description">Recordatorios configurados que requieren gestión manual hoy. El envío real por WhatsApp y correo aún no está conectado.</p>${due.length ? `<div class="policy-dashboard-alert-list">${due.map(({expedient,policy,alert,state})=>`<article class="policy-dashboard-alert"><div><strong>${esc(policy.code)} · ${esc(policy.title)}</strong><p>${esc(expedient.code)} · ${esc(expedient.client_name || expedient.contact_name || 'Cliente pendiente')}</p><small>${esc(alert.type==='monthly'?`Mensual: día ${alert.day_of_month} a las ${alert.hour}`:`Faltan ${alert.days_before} día(s) para el fin de vigencia`)} · Programada: ${esc(window.BrokerDemo?.formatPeruDate?.(state.scheduled,true)||state.scheduled)}</small></div><button type="button" class="policy-secondary-button" data-open-expedients>Ver póliza</button></article>`).join('')}</div>` : '<p class="dashboard-empty">No hay alertas de pólizas pendientes por gestionar en este momento.</p>'}`;
  const anchor = document.querySelector('.dashboard-main-grid');
  if (anchor) anchor.insertAdjacentElement('afterend',section); else root.appendChild(section);
  section.querySelectorAll('[data-open-expedients]').forEach((button)=>button.addEventListener('click',()=>{window.location.href='expedientes.php';}));
})();