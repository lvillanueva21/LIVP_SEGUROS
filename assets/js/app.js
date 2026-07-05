(() => {
  const body = document.body;
  const userId = body.dataset.user || 'anonymous';
  const actionKey = `broker_seguros_demo_actions_${userId}`;
  const styleVersion = 'BS-20260627-193319-PET';

  function ensureStyles(path, attribute) {
    if (document.querySelector(`link[${attribute}]`)) return;
    const script = document.currentScript?.src;
    const url = script ? new URL(path, script) : new URL(`assets/${path.replace('../', '')}`, window.location.href);
    url.searchParams.set('v', styleVersion);
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = url.href;
    link.setAttribute(attribute, 'true');
    document.head.appendChild(link);
  }

  ensureStyles('../css/notifications.css', 'data-broker-notification-styles');
  ensureStyles('../css/modal-ui.css', 'data-broker-modal-ui-styles');

  let globalHost = null;
  let lastSignature = '';
  let lastAt = 0;

  const escapeHtml = (value) => {
    const node = document.createElement('span');
    node.textContent = String(value ?? '');
    return node.innerHTML;
  };

  function getActiveDialog() {
    const dialogs = [...document.querySelectorAll('dialog')].filter((dialog) => dialog.open);
    return dialogs[dialogs.length - 1] || null;
  }

  function getGlobalHost() {
    if (globalHost && document.body.contains(globalHost)) return globalHost;
    globalHost = document.createElement('section');
    globalHost.className = 'broker-notification-host broker-notification-host-global';
    globalHost.setAttribute('aria-live', 'polite');
    globalHost.setAttribute('aria-label', 'Mensajes del sistema');
    document.body.appendChild(globalHost);
    return globalHost;
  }

  function getDialogHost(dialog) {
    const root = dialog.querySelector(':scope > form')
      || dialog.querySelector(':scope > .expedient-dialog-content')
      || dialog;

    const current = [...root.children].find((child) => child.classList?.contains('broker-dialog-notification-host'));
    if (current) return current;

    const host = document.createElement('section');
    host.className = 'broker-notification-host broker-dialog-notification-host';
    host.setAttribute('aria-live', 'polite');
    host.setAttribute('aria-label', 'Mensajes del formulario');

    const head = root.querySelector('.policy-editor-heading, .expedient-dialog-heading, .catalog-dialog-heading, .dialog-head');
    if (head) head.insertAdjacentElement('afterend', host);
    else root.prepend(host);

    dialog.addEventListener('close', () => host.remove(), { once: true });
    return host;
  }

  function getHost(options = {}) {
    if (options.placement === 'global') return getGlobalHost();
    const dialog = getActiveDialog();
    return dialog ? getDialogHost(dialog) : getGlobalHost();
  }

  function dismiss(card) {
    if (!card || card.dataset.leaving === '1') return;
    card.dataset.leaving = '1';
    card.classList.add('is-leaving');
    window.setTimeout(() => card.remove(), 180);
  }

  function show(type, message, options = {}) {
    const allowed = ['success', 'error', 'warning', 'info'];
    const kind = allowed.includes(type) ? type : 'info';
    const defaults = {
      success: { title: 'Acción realizada', icon: '✓', duration: 5000 },
      error: { title: 'No se pudo completar', icon: '!', duration: 9000 },
      warning: { title: 'Revisa esta información', icon: '!', duration: 7200 },
      info: { title: 'Información', icon: 'i', duration: 5000 },
    };
    const settings = { ...defaults[kind], ...options };
    const signature = `${kind}|${settings.title}|${message}`;
    const now = Date.now();

    if (signature === lastSignature && now - lastAt < 700) return null;
    lastSignature = signature;
    lastAt = now;

    const card = document.createElement('article');
    card.className = `broker-notification broker-notification-${kind}`;
    card.setAttribute('role', kind === 'error' ? 'alert' : 'status');

    const actions = Array.isArray(settings.actions) && settings.actions.length
      ? `<div class="broker-notification-actions">${settings.actions.map((action, index) => `<button type="button" class="broker-notification-action ${action.kind === 'danger' ? 'is-danger' : ''}" data-notify-action="${index}">${escapeHtml(action.label)}</button>`).join('')}</div>`
      : '';

    card.innerHTML = `
      <span class="broker-notification-icon" aria-hidden="true">${escapeHtml(settings.icon)}</span>
      <div class="broker-notification-copy">
        <p class="broker-notification-title">${escapeHtml(settings.title)}</p>
        <p class="broker-notification-message">${escapeHtml(message)}</p>
        ${actions}
      </div>
      <button type="button" class="broker-notification-close" aria-label="Cerrar mensaje">×</button>
    `;

    card.querySelector('.broker-notification-close')?.addEventListener('click', () => dismiss(card));
    card.querySelectorAll('[data-notify-action]').forEach((button) => {
      button.addEventListener('click', () => {
        const action = settings.actions?.[Number(button.dataset.notifyAction)];
        action?.callback?.();
        dismiss(card);
      });
    });

    getHost(settings).appendChild(card);
    if (settings.duration > 0) window.setTimeout(() => dismiss(card), settings.duration);
    return card;
  }

  window.BrokerNotify = {
    show,
    success: (message, options = {}) => show('success', message, options),
    error: (message, options = {}) => show('error', message, options),
    warning: (message, options = {}) => show('warning', message, options),
    info: (message, options = {}) => show('info', message, options),
    confirm: (message, options = {}) => new Promise((resolve) => {
      let completed = false;
      const finish = (result) => {
        if (completed) return;
        completed = true;
        resolve(result);
      };

      const card = show('warning', message, {
        title: options.title || 'Confirma la acción',
        duration: 0,
        placement: options.placement,
        actions: [
          { label: options.confirmLabel || 'Confirmar', kind: options.kind || 'danger', callback: () => finish(true) },
          { label: options.cancelLabel || 'Cancelar', callback: () => finish(false) },
        ],
      });

      if (!card) {
        finish(false);
        return;
      }

      card.querySelector('.broker-notification-close')?.addEventListener('click', () => finish(false), { once: true });
    }),
  };

  let lastInvalid = 0;
  document.addEventListener('invalid', (event) => {
    const element = event.target;
    if (!(element instanceof HTMLInputElement || element instanceof HTMLSelectElement || element instanceof HTMLTextAreaElement)) return;
    const current = Date.now();
    if (current - lastInvalid < 700) return;
    lastInvalid = current;
    window.BrokerNotify.warning('Completa los campos obligatorios marcados antes de guardar.', { title: 'Faltan datos por completar' });
  }, true);

  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebar-overlay');
  const toggle = document.getElementById('menu-toggle');
  const close = document.getElementById('sidebar-close');

  const closeMenu = () => {
    sidebar?.classList.remove('is-open');
    overlay?.classList.remove('is-visible');
  };

  toggle?.addEventListener('click', () => {
    sidebar?.classList.add('is-open');
    overlay?.classList.add('is-visible');
  });
  close?.addEventListener('click', closeMenu);
  overlay?.addEventListener('click', closeMenu);

  document.querySelectorAll('[data-module-id]').forEach((link) => {
    link.addEventListener('click', closeMenu);
  });

  // Keep an optional local action trail without failing any page when API endpoints are absent.
  const list = document.getElementById('cache-list');
  if (list) {
    try {
      const actions = JSON.parse(localStorage.getItem(actionKey) || '[]');
      list.innerHTML = Array.isArray(actions) && actions.length
        ? actions.slice(0, 5).map((item) => `<li><span>${escapeHtml(item.action || 'Acción registrada')}</span></li>`).join('')
        : '<li>Sin acciones registradas todavía.</li>';
    } catch {}
  }
})();