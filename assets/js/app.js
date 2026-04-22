(() => {
    const App = {
        init() {
            this.cache();
            this.bindEvents();
            this.hydrateSidebarState();
            this.renderServerToasts();
            this.initTabs();
        },

        cache() {
            this.body = document.body;
            this.toastContainer = document.getElementById('toast-container');
            this.confirmModal = document.getElementById('confirm-modal');
            this.confirmTitle = document.getElementById('confirm-modal-title');
            this.confirmMessage = document.getElementById('confirm-modal-message');
            this.confirmAccept = document.getElementById('confirm-modal-accept');
            this.currentConfirmCallback = null;
        },

        bindEvents() {
            document.addEventListener('click', (event) => {
                const sidebarToggle = event.target.closest('[data-sidebar-toggle]');
                if (sidebarToggle) {
                    event.preventDefault();
                    this.toggleSidebar();
                    return;
                }

                const modalOpen = event.target.closest('[data-modal-open]');
                if (modalOpen) {
                    event.preventDefault();
                    this.openModal(modalOpen.getAttribute('data-modal-open'));
                    return;
                }

                const modalClose = event.target.closest('[data-modal-close]');
                if (modalClose) {
                    event.preventDefault();
                    this.closeClosestModal(modalClose);
                    return;
                }

                const dropdownToggle = event.target.closest('[data-dropdown-toggle]');
                if (dropdownToggle) {
                    event.preventDefault();
                    this.toggleDropdown(dropdownToggle.getAttribute('data-dropdown-toggle'));
                    return;
                }

                const toastClose = event.target.closest('[data-toast-close]');
                if (toastClose) {
                    event.preventDefault();
                    const toast = toastClose.closest('.toast');
                    this.dismissToast(toast);
                    return;
                }

                const tabButton = event.target.closest('[data-tab-button]');
                if (tabButton) {
                    event.preventDefault();
                    this.activateTab(tabButton);
                    return;
                }

                const printButton = event.target.closest('[data-print-page]');
                if (printButton) {
                    event.preventDefault();
                    window.print();
                    return;
                }
            });

            document.addEventListener('input', (event) => {
                const searchInput = event.target.closest('[data-table-search-input]');
                if (searchInput) {
                    this.filterTable(searchInput);
                }
            });

            document.addEventListener('submit', (event) => {
                const ajaxForm = event.target.closest('[data-ajax-form]');
                if (!ajaxForm) return;

                event.preventDefault();
                this.submitAjaxForm(ajaxForm);
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    this.closeAllModals();
                    this.closeAllDropdowns();
                }
            });

            document.addEventListener('click', (event) => {
                if (!event.target.closest('.notification-chip') && !event.target.closest('.dropdown-panel')) {
                    this.closeAllDropdowns();
                }
            });

            if (this.confirmAccept) {
                this.confirmAccept.addEventListener('click', () => {
                    if (typeof this.currentConfirmCallback === 'function') {
                        this.currentConfirmCallback();
                    }
                    this.closeModal('confirm-modal');
                    this.currentConfirmCallback = null;
                });
            }
        },

        rootPrefix() {
            return (window.DEMO_CONFIG && window.DEMO_CONFIG.baseUrl) || '';
        },

        hydrateSidebarState() {
            const appShell = document.querySelector('.app-shell');
            if (!appShell) return;

            if (window.innerWidth <= 980) {
                appShell.classList.remove('is-collapsed');
                return;
            }

            const collapsed = localStorage.getItem('demoSidebarCollapsed') === '1';
            if (collapsed) {
                appShell.classList.add('is-collapsed');
            }
        },

        toggleSidebar() {
            const appShell = document.querySelector('.app-shell');
            if (!appShell) return;

            if (window.innerWidth <= 980) {
                appShell.classList.toggle('is-sidebar-open');
                return;
            }

            appShell.classList.toggle('is-collapsed');
            localStorage.setItem('demoSidebarCollapsed', appShell.classList.contains('is-collapsed') ? '1' : '0');
        },

        openModal(id) {
            const modal = document.getElementById(id);
            if (!modal) return;
            modal.hidden = false;
            this.body.style.overflow = 'hidden';
        },

        closeModal(id) {
            const modal = document.getElementById(id);
            if (!modal) return;
            modal.hidden = true;

            if (!document.querySelector('.modal:not([hidden])')) {
                this.body.style.overflow = '';
            }
        },

        closeClosestModal(element) {
            const modal = element.closest('.modal');
            if (!modal) return;
            this.closeModal(modal.id);
        },

        closeAllModals() {
            document.querySelectorAll('.modal:not([hidden])').forEach((modal) => {
                modal.hidden = true;
            });
            this.body.style.overflow = '';
        },

        toggleDropdown(id) {
            const panel = document.getElementById(id);
            if (!panel) return;

            const willOpen = panel.hidden;
            this.closeAllDropdowns();

            if (willOpen) {
                panel.hidden = false;
            }
        },

        closeAllDropdowns() {
            document.querySelectorAll('.dropdown-panel').forEach((panel) => {
                panel.hidden = true;
            });
        },

        toast({ title = '', message = '', type = 'info', timeout = 5000 } = {}) {
            if (!this.toastContainer) return;

            const toast = document.createElement('article');
            toast.className = `toast toast--${type}`;
            toast.innerHTML = `
                <div class="toast__content">
                    ${title ? `<h4 class="toast__title">${this.escapeHtml(title)}</h4>` : ''}
                    <p class="toast__message">${this.escapeHtml(message)}</p>
                </div>
                <button type="button" class="icon-btn" data-toast-close aria-label="Cerrar notificación">✕</button>
            `;

            this.toastContainer.appendChild(toast);

            window.setTimeout(() => this.dismissToast(toast), timeout);
        },

        dismissToast(toast) {
            if (!toast || !toast.parentNode) return;
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-6px)';
            window.setTimeout(() => toast.remove(), 180);
        },

        renderServerToasts() {
            const serverToasts = (window.DEMO_CONFIG && window.DEMO_CONFIG.serverToasts) || [];
            serverToasts.forEach((item) => this.toast({
                title: item.title || '',
                message: item.message || '',
                type: item.type || 'info'
            }));
        },

        async api(url, options = {}) {
            const finalOptions = {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                ...options
            };

            if (finalOptions.body instanceof FormData) {
                // no tocar content-type
            } else if (finalOptions.body && typeof finalOptions.body === 'object') {
                finalOptions.headers['Content-Type'] = 'application/json';
                finalOptions.body = JSON.stringify(finalOptions.body);
            }

            const response = await fetch(url, finalOptions);
            const data = await response.json().catch(() => ({
                success: false,
                message: 'Respuesta no válida del servidor.'
            }));

            if (!response.ok && data.redirect) {
                window.location.href = data.redirect;
            }

            return data;
        },

        async submitAjaxForm(form) {
            const action = form.getAttribute('action') || window.location.href;
            const method = (form.getAttribute('method') || 'POST').toUpperCase();
            const formData = new FormData(form);

            const response = await this.api(action, {
                method,
                body: formData
            });

      if (response.success) {
    if (!response.suppressToast) {
        this.toast({
            title: response.title || 'Operación exitosa',
            message: response.message || 'Se guardaron los cambios.',
            type: response.type || 'success'
        });
    }

    if (response.closeModal) {
        this.closeModal(response.closeModal);
    }

    if (response.redirect) {
        window.setTimeout(() => {
            window.location.href = response.redirect;
        }, 400);
    }

    if (response.callback && typeof window[response.callback] === 'function') {
        window[response.callback](response);
    }

    form.dispatchEvent(new CustomEvent('ajax:success', { detail: response }));
} else {     
                this.toast({
                    title: response.title || 'No se pudo completar',
                    message: response.message || 'Verifica la información ingresada.',
                    type: response.type || 'error'
                });

                form.dispatchEvent(new CustomEvent('ajax:error', { detail: response }));
            }
        },

        confirm({ title = 'Confirmar acción', message = '¿Deseas continuar?', onAccept = null } = {}) {
            if (!this.confirmModal) return;

            this.confirmTitle.textContent = title;
            this.confirmMessage.textContent = message;
            this.currentConfirmCallback = onAccept;
            this.openModal('confirm-modal');
        },

        populateForm(form, data = {}) {
            if (!form) return;

            Object.entries(data).forEach(([key, value]) => {
                const field = form.querySelector(`[name="${key}"]`);
                if (!field) return;

                if (field.type === 'checkbox') {
                    field.checked = Boolean(value);
                } else if (field.type === 'radio') {
                    const radio = form.querySelector(`[name="${key}"][value="${value}"]`);
                    if (radio) radio.checked = true;
                } else {
                    field.value = value ?? '';
                }
            });
        },

        filterTable(input) {
            const target = input.getAttribute('data-table-search-input');
            const table = document.querySelector(target);
            if (!table) return;

            const term = input.value.trim().toLowerCase();
            const rows = table.querySelectorAll('tbody tr');

            rows.forEach((row) => {
                const text = row.textContent.toLowerCase();
                row.hidden = !text.includes(term);
            });
        },

        initTabs() {
            document.querySelectorAll('[data-tab-group]').forEach((group) => {
                const buttons = group.querySelectorAll('[data-tab-button]');
                const panels = group.querySelectorAll('[data-tab-panel]');
                if (!buttons.length || !panels.length) return;

                const activeButton = group.querySelector('[data-tab-button].is-active') || buttons[0];
                this.activateTab(activeButton, false);
            });
        },

        activateTab(button, userAction = true) {
            const group = button.closest('[data-tab-group]');
            if (!group) return;

            const target = button.getAttribute('data-tab-button');
            const buttons = group.querySelectorAll('[data-tab-button]');
            const panels = group.querySelectorAll('[data-tab-panel]');

            buttons.forEach((btn) => btn.classList.toggle('is-active', btn === button));
            panels.forEach((panel) => {
                panel.hidden = panel.getAttribute('data-tab-panel') !== target;
            });

            if (userAction) {
                button.blur();
            }
        },

        escapeHtml(value) {
            return String(value)
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }
    };

    window.DemoApp = App;
    document.addEventListener('DOMContentLoaded', () => App.init());
})();