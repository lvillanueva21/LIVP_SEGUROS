(() => {
  const quoteKey = 'broker_seguros_demo_quotes_v1';
  const templateKey = 'broker_seguros_demo_quote_templates_v1';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const esc = (v) => { const e = document.createElement('span'); e.textContent = String(v ?? ''); return e.innerHTML; };
  const note = (type, message, options = {}) => window.BrokerNotify?.[type]?.(message, options);
  const params = new URLSearchParams(location.search);
  const expedientId = params.get('expediente') || '';
  const action = params.get('accion') || '';
  const quoteRequested = params.get('cotizacion') || '';
  const copy = (v) => JSON.parse(JSON.stringify(v));
  const read = (key, fallback) => { try { return JSON.parse(localStorage.getItem(key) || 'null') || fallback; } catch { return fallback; } };
  const write = (key, value) => localStorage.setItem(key, JSON.stringify(value));
  const now = () => window.BrokerDemo?.limaDateTime?.() || new Date().toISOString().slice(0, 19).replace('T', ' ');

  function expedient() {
    try {
      const all = JSON.parse(localStorage.getItem(window.BrokerDemo?.keys?.expedients || 'broker_seguros_demo_expedients_v3') || '[]');
      return all.find((item) => String(item.id) === String(expedientId)) || null;
    } catch { return null; }
  }

  function nextQuoteCode(records) {
    const year = window.BrokerDemo?.limaYear?.() || new Date().getFullYear();
    const prefix = `COT-${year}-`;
    const values = records
      .filter((item) => String(item.code || '').startsWith(prefix))
      .map((item) => Number(String(item.code).slice(prefix.length)))
      .filter(Number.isFinite);

    return `${prefix}${String((values.length ? Math.max(...values) : 0) + 1).padStart(4, '0')}`;
  }

  function addContext() {
    if (!expedientId) return;

    const home = $('#quotes-home');
    const exp = expedient();
    if (!home || !exp) return;

    if (!$('#quote-expedient-context')) {
      const banner = document.createElement('div');
      banner.id = 'quote-expedient-context';
      banner.className = 'quote-context-banner';
      banner.innerHTML = `<strong>Expediente padre:</strong> ${esc(exp.code)} — ${esc(exp.title)}. Las cotizaciones mostradas o creadas aquí quedan vinculadas a este expediente y siguen siendo independientes de las pólizas reales.`;
      home.prepend(banner);
    }

    const search = $('#quote-search');
    if (search && search.value !== exp.code) {
      search.value = exp.code;
      search.dispatchEvent(new Event('input', { bubbles: true }));
    }
  }

  /*
   * Corrección HUBV2:
   * El formulario real de cotizaciones usa #e-exp.
   * Se conserva #q-expedient como compatibilidad con una maqueta anterior.
   */
  function presetParent() {
    if (!expedientId) return;

    const select = $('#e-exp') || $('#q-expedient');
    if (!select) return;

    const exists = [...select.options].some((option) => String(option.value) === String(expedientId));
    if (!exists) {
      note('warning', 'El expediente de origen ya no está disponible. Selecciónalo manualmente antes de guardar.', {
        title: 'Expediente no encontrado',
      });
      return;
    }

    select.value = expedientId;
    select.dispatchEvent(new Event('change', { bubbles: true }));

    if (!$('#quote-parent-hint')) {
      const hint = document.createElement('p');
      hint.id = 'quote-parent-hint';
      hint.className = 'quote-parent-hint';
      hint.textContent = 'Expediente padre preseleccionado desde la ficha. La cotización no se vinculará a pólizas.';
      select.closest('.quote-builder-section')?.appendChild(hint);
    }
  }

  function duplicateQuote(id) {
    const quotes = read(quoteKey, []);
    const source = quotes.find((item) => String(item.id) === String(id));
    if (!source) return;

    const clone = copy(source);
    clone.id = `quote-${Date.now()}-${Math.random().toString(16).slice(2, 7)}`;
    clone.code = nextQuoteCode(quotes);
    clone.title = `${source.title || 'Cotización'} (copia)`;
    clone.status = 'En preparación';
    clone.selected_alternative_id = '';

    (clone.groups || []).forEach((group) => {
      (group.alternatives || []).forEach((alternative) => {
        if (alternative.state === 'Elegida') alternative.state = 'Disponible';
      });
    });

    clone.created_at = now();
    clone.updated_at = now();
    clone.history = [{ at: now(), action: 'Duplicada desde', detail: source.code || '' }];
    quotes.push(clone);
    write(quoteKey, quotes);

    note('success', `${clone.code} fue creada como copia editable.`, { title: 'Cotización duplicada' });
    location.href = `cotizaciones.php?expediente=${encodeURIComponent(clone.expedient_id || '')}&cotizacion=${encodeURIComponent(clone.id)}`;
  }

  function duplicateTemplate(id) {
    const templates = read(templateKey, []);
    const source = templates.find((item) => String(item.id) === String(id));
    if (!source) return;

    const clone = copy(source);
    clone.id = `template-${Date.now()}-${Math.random().toString(16).slice(2, 7)}`;
    clone.name = `${source.name || 'Plantilla'} (copia)`;
    clone.active = true;
    clone.created_at = now();
    clone.updated_at = now();
    templates.push(clone);
    write(templateKey, templates);

    note('success', 'La plantilla fue duplicada para que puedas ajustarla sin alterar la original.', {
      title: 'Plantilla duplicada',
    });
    location.reload();
  }

  function decorate() {
    addContext();

    $$('.quote-list-card').forEach((card) => {
      if (card.dataset.v2Decorated === '1') return;

      const opener = $('[data-open]', card);
      if (!opener) return;

      card.dataset.v2Decorated = '1';

      const button = document.createElement('button');
      button.className = 'quote-mini-button';
      button.type = 'button';
      button.textContent = 'Duplicar';
      button.addEventListener('click', () => duplicateQuote(opener.dataset.open));
      $('.quote-list-actions', card)?.appendChild(button);

      if (quoteRequested && String(opener.dataset.open) === String(quoteRequested)) {
        window.setTimeout(() => opener.click(), 0);
      }
    });

    $$('.quote-template-card').forEach((card) => {
      if (card.dataset.v2Decorated === '1') return;

      const use = $('[data-use]', card);
      if (!use) return;

      card.dataset.v2Decorated = '1';

      const button = document.createElement('button');
      button.className = 'quote-mini-button';
      button.type = 'button';
      button.textContent = 'Duplicar';
      button.addEventListener('click', () => duplicateTemplate(use.dataset.use));
      $('.quote-template-actions', card)?.appendChild(button);
    });
  }

  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('#quote-new, [data-use]');
    if (!trigger || !expedientId) return;

    // El editor se crea por cotizaciones.js después del clic.
    window.setTimeout(presetParent, 80);
  }, true);

  new MutationObserver(() => window.setTimeout(decorate, 0))
    .observe(document.body, { childList: true, subtree: true });

  window.setTimeout(() => {
    decorate();

    if (action === 'nueva') {
      $('#quote-new')?.click();
    }
  }, 100);
})();
