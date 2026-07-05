(() => {
  const role = document.body?.dataset?.role || '';
  if (!['gerente', 'ejecutivo'].includes(role)) return;
  const $ = (selector, root = document) => root.querySelector(selector);

  const host = $('.workspace-content');
  if (!host || $('#published-state-sync')) return;

  const banner = document.createElement('section');
  banner.id = 'published-state-sync';
  banner.className = 'published-state-sync';
  banner.innerHTML = `<div><strong>Actualizar cartera publicada</strong><p>Trae a esta sesión los registros enviados por Clientes desde otros navegadores, incluidos comprobantes y siniestros.</p></div><button type="button">Actualizar ahora</button>`;
  host.prepend(banner);

  $('button', banner)?.addEventListener('click', async (event) => {
    const button = event.currentTarget;
    const confirmed = await window.BrokerNotify?.confirm?.(
      'Se reemplazará la caché local de Expedientes y Clientes por la cartera publicada más reciente. Las modificaciones locales que no hayan sido publicadas se perderán.',
      { title: 'Actualizar cartera', confirmLabel: 'Actualizar', cancelLabel: 'Cancelar' }
    );
    if (!confirmed) return;

    button.disabled = true;
    button.textContent = 'Actualizando…';
    try {
      const response = await fetch('api/published_portfolio_state.php', { headers: { Accept: 'application/json' } });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || !payload.ok) throw new Error(payload.message || 'No se pudo obtener la cartera publicada.');

      const entityKey = window.BrokerDemo?.keys?.entities || 'broker_seguros_demo_entities_v1';
      const expedientKey = window.BrokerDemo?.keys?.expedients || 'broker_seguros_demo_expedients_v3';
      localStorage.setItem(entityKey, JSON.stringify(payload.entities || { companies: [], consortia: [] }));
      localStorage.setItem(expedientKey, JSON.stringify(payload.expedients || []));
      window.BrokerNotify?.success?.('La cartera publicada fue cargada en esta sesión.', { title: 'Cartera actualizada' });
      window.setTimeout(() => location.reload(), 400);
    } catch (error) {
      window.BrokerNotify?.error?.(error.message || 'No se pudo actualizar la cartera.', { title: 'Actualización no realizada', duration: 0 });
      button.disabled = false;
      button.textContent = 'Actualizar ahora';
    }
  });
})();