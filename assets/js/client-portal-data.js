(() => {
  const body = document.body;
  if (body?.dataset?.role !== 'cliente') return;

  const data = { state: null, publishedAt: '', ready: null, refresh: null };
  window.BrokerClientPortalData = data;

  const load = () => fetch('api/client_portal_data.php', { headers: { Accept: 'application/json' } })
    .then(async (response) => {
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || !payload.ok) throw new Error(payload.message || 'No se pudo cargar la cartera publicada.');
      data.state = payload.state && typeof payload.state === 'object'
        ? payload.state
        : { entities: { companies: [], consortia: [] }, expedients: [] };
      data.publishedAt = String(payload.published_at || '');
      window.dispatchEvent(new CustomEvent('broker:client-portal-data-ready', { detail: { publishedAt: data.publishedAt } }));
      return data;
    })
    .catch((error) => {
      window.dispatchEvent(new CustomEvent('broker:client-portal-data-fallback', { detail: { message: error.message || '' } }));
      return data;
    });

  data.refresh = load;
  data.ready = load();
})();