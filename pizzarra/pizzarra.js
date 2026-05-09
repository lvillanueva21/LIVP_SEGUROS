// pizzarra.js - PIZZARRA v1
(function () {
    'use strict';

    if (!Element.prototype.matches) {
        Element.prototype.matches = Element.prototype.msMatchesSelector || Element.prototype.webkitMatchesSelector;
    }
    if (!Element.prototype.closest) {
        Element.prototype.closest = function (selector) {
            var el = this;
            while (el && el.nodeType === 1) {
                if (el.matches(selector)) {
                    return el;
                }
                el = el.parentElement || el.parentNode;
            }
            return null;
        };
    }
    if (typeof Object.assign !== 'function') {
        Object.assign = function (target) {
            if (target == null) {
                throw new TypeError('Cannot convert undefined or null to object');
            }
            var output = Object(target);
            for (var index = 1; index < arguments.length; index++) {
                var source = arguments[index];
                if (source != null) {
                    for (var nextKey in source) {
                        if (Object.prototype.hasOwnProperty.call(source, nextKey)) {
                            output[nextKey] = source[nextKey];
                        }
                    }
                }
            }
            return output;
        };
    }

    var app = document.getElementById('pizzarraApp');
    if (!app) {
        return;
    }

    var CFG = window.PIZZARRA_CONFIG || {};
    CFG.uploadUrl = CFG.uploadUrl || app.getAttribute('data-upload-url') || 'upload_image.php';
    CFG.assetPrefix = CFG.assetPrefix || app.getAttribute('data-asset-prefix') || './';

    var STATUS = {
        pendiente: {
            key: 'pendiente',
            title: 'PENDIENTE',
            subtitle: 'Queso por fundir',
            icon: '🧀',
            color: '#F6C945',
            soft: '#FFF3BF',
            column: 'pzColPendiente',
            toast: 'La pieza quedó lista para fundirse.'
        },
        proceso: {
            key: 'proceso',
            title: 'EN PROCESO',
            subtitle: 'Bañando en salsa',
            icon: '🍅',
            color: '#F14937',
            soft: '#FFE1DB',
            column: 'pzColProceso',
            toast: 'La pieza entró en salsa.'
        },
        culminada: {
            key: 'culminada',
            title: 'CULMINADA',
            subtitle: 'Toque final de albahaca',
            icon: '🌿',
            color: '#6BAE45',
            soft: '#E6F5D9',
            column: 'pzColCulminada',
            toast: '¡Lista para servir! Pieza culminada.'
        },
        pausada: {
            key: 'pausada',
            title: 'PAUSADA',
            subtitle: 'Aceituna aparte',
            icon: '🫒',
            color: '#2B2B2F',
            soft: '#E8E8EB',
            column: 'pzColPausada',
            toast: 'La pieza quedó como aceituna aparte.'
        }
    };

    var STATUS_KEYS = ['pendiente', 'proceso', 'culminada', 'pausada'];

    var state = {
        projects: [],
        notes: [],
        favoriteColors: [],
        activeProjectId: '',
        currentDate: '',
        view: 'day',
        filters: {
            search: '',
            images: false,
            urgent: false,
            done: false,
            extras: false
        },
        selectedColor: '#F6C945',
        modalAttachments: [],
        editingId: null,
        summernoteReady: false
    };

    function byId(id) {
        return document.getElementById(id);
    }

    function qsa(selector, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(selector));
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function stripHtml(html) {
        var div = document.createElement('div');
        div.innerHTML = html || '';
        return (div.textContent || div.innerText || '').replace(/\s+/g, ' ').trim();
    }

    function sanitizeHtml(html) {
        var template = document.createElement('template');
        template.innerHTML = html || '';
        var blocked = template.content.querySelectorAll('script, style, iframe, object, embed, form, input, button');
        Array.prototype.forEach.call(blocked, function (node) {
            node.parentNode.removeChild(node);
        });
        var all = template.content.querySelectorAll('*');
        Array.prototype.forEach.call(all, function (node) {
            Array.prototype.slice.call(node.attributes).forEach(function (attr) {
                var name = attr.name.toLowerCase();
                var value = String(attr.value || '').trim().toLowerCase();
                if (name.indexOf('on') === 0) {
                    node.removeAttribute(attr.name);
                }
                if ((name === 'href' || name === 'src') && value.indexOf('javascript:') === 0) {
                    node.removeAttribute(attr.name);
                }
            });
        });
        return template.innerHTML;
    }

    function uid(prefix) {
        prefix = prefix || 'id';
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return prefix + '_' + window.crypto.randomUUID().replace(/-/g, '').slice(0, 18);
        }
        return prefix + '_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 9);
    }

    function pad(num) {
        return num < 10 ? '0' + num : String(num);
    }

    function toISO(date) {
        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
    }

    function todayISO() {
        return toISO(new Date());
    }

    function parseISO(iso) {
        var parts = String(iso || '').split('-');
        var y = parseInt(parts[0], 10);
        var m = parseInt(parts[1], 10) - 1;
        var d = parseInt(parts[2], 10);
        if (isNaN(y) || isNaN(m) || isNaN(d)) {
            return new Date();
        }
        return new Date(y, m, d);
    }

    function addDays(iso, amount) {
        var date = parseISO(iso);
        date.setDate(date.getDate() + amount);
        return toISO(date);
    }

    function startOfWeek(iso) {
        var date = parseISO(iso);
        var day = date.getDay();
        var diff = day === 0 ? -6 : 1 - day;
        date.setDate(date.getDate() + diff);
        return toISO(date);
    }

    function startOfMonth(iso) {
        var date = parseISO(iso);
        return new Date(date.getFullYear(), date.getMonth(), 1);
    }

    function endOfMonth(iso) {
        var date = parseISO(iso);
        return new Date(date.getFullYear(), date.getMonth() + 1, 0);
    }

    function formatLongDate(iso) {
        var days = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
        var months = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'setiembre', 'octubre', 'noviembre', 'diciembre'];
        var d = parseISO(iso);
        return capitalize(days[d.getDay()]) + ', ' + pad(d.getDate()) + ' de ' + months[d.getMonth()] + ' de ' + d.getFullYear();
    }

    function formatShortDate(iso) {
        var months = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'set', 'oct', 'nov', 'dic'];
        var d = parseISO(iso);
        return pad(d.getDate()) + ' ' + months[d.getMonth()];
    }

    function capitalize(value) {
        value = String(value || '');
        return value.charAt(0).toUpperCase() + value.slice(1);
    }

    function getProject(id) {
        return state.projects.filter(function (project) {
            return project.id === id;
        })[0] || state.projects[0] || null;
    }

    function getNote(id) {
        return state.notes.filter(function (note) {
            return note.id === id;
        })[0] || null;
    }

    function svgThumb(title, color, label) {
        var safeTitle = escapeHtml(title || 'PIZZARRA');
        var safeLabel = escapeHtml(label || 'Pieza');
        var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="320" height="180" viewBox="0 0 320 180">' +
            '<rect width="320" height="180" rx="26" fill="#FFF8EE"/>' +
            '<rect x="22" y="22" width="276" height="136" rx="20" fill="' + color + '" opacity="0.92"/>' +
            '<circle cx="258" cy="54" r="15" fill="#fff" opacity="0.32"/>' +
            '<circle cx="58" cy="132" r="22" fill="#fff" opacity="0.24"/>' +
            '<text x="36" y="78" font-family="Arial, sans-serif" font-size="25" font-weight="900" fill="#ffffff">' + safeLabel + '</text>' +
            '<text x="36" y="114" font-family="Arial, sans-serif" font-size="17" font-weight="700" fill="#ffffff" opacity="0.96">' + safeTitle + '</text>' +
            '</svg>';
        return 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg);
    }

    function seedData() {
        var today = todayISO();
        state.currentDate = today;
        state.view = 'day';
        state.projects = [
            { id: 'pizzarra-app', name: 'PIZZARRA APP', description: 'Diseño de la primera versión visual.', color: '#F58A1F' },
            { id: 'sabores-crm', name: 'SABORES CRM', description: 'Seguimiento comercial y clientes.', color: '#F14937' },
            { id: 'ingredientes-web', name: 'INGREDIENTES WEB', description: 'Mejoras para sitio web y landing.', color: '#6BAE45' },
            { id: 'recetas-mobile', name: 'RECETAS MOBILE', description: 'Ideas para app móvil.', color: '#F6C945' },
            { id: 'clientes-360', name: 'CLIENTES 360', description: 'Panel de clientes y soporte.', color: '#2B2B2F' }
        ];
        state.activeProjectId = state.projects[0].id;
        state.favoriteColors = [
            { name: 'Naranja marca', value: '#F58A1F' },
            { name: 'Queso', value: '#F6C945' },
            { name: 'Salsa', value: '#F14937' },
            { name: 'Albahaca', value: '#6BAE45' },
            { name: 'Aceituna', value: '#2B2B2F' },
            { name: 'Morado auxiliar', value: '#7C3AED' },
            { name: 'Azul suave', value: '#4DA3FF' }
        ];
        state.notes = [
            makeNote('Diseñar header del módulo', 'pizzarra-app', today, 'pendiente', '#F6C945', 'UI/UX', 'Alta', 'Crear cabecera con logo, selector de caja, navegación de fecha y botones rápidos.', [svgThumb('Header PIZZARRA', '#F58A1F', 'UI')], 'LM'),
            makeNote('Crear modal de nueva pieza', 'pizzarra-app', today, 'pendiente', '#F58A1F', 'Frontend', 'Doble queso', 'Formulario con título, estado, color, contenido, link e imágenes.', [svgThumb('Modal', '#7C3AED', 'Form')], 'AM'),
            makeNote('Definir colores favoritos', 'pizzarra-app', today, 'pendiente', '#7C3AED', 'Diseño', 'Normal', 'Permitir colores favoritos para personalizar piezas sin recargar la página.', [], 'CL'),
            makeNote('Maquetar vista semanal', 'pizzarra-app', today, 'proceso', '#F14937', 'Frontend', 'Alta', 'Crear resumen semanal con piezas por día y miniaturas de tareas.', [svgThumb('Semana', '#4DA3FF', 'Vista')], 'JR'),
            makeNote('Integrar Summernote', 'pizzarra-app', today, 'proceso', '#4DA3FF', 'Integración', 'Extra', 'Activar Summernote si existe en plugins/summernote/. Si no está disponible, usar editor nativo.', [svgThumb('Editor', '#2B2B2F', 'Text')], 'NT'),
            makeNote('Implementar filtros avanzados', 'pizzarra-app', today, 'proceso', '#F58A1F', 'Frontend', 'Normal', 'Filtrar por imágenes, urgentes, culminadas, extras y búsqueda de texto.', [], 'LM'),
            makeNote('Crear logo final', 'pizzarra-app', today, 'culminada', '#6BAE45', 'Branding', 'Extra', 'Logo cuadrado inspirado en pizza, notas y pizarra tipo Kanban.', [CFG.assetPrefix + 'logo_pizzarra.png'], 'DG'),
            makeNote('Exportación PDF', 'pizzarra-app', today, 'culminada', '#F14937', 'Backend', 'Doble queso', 'Idea futura para exportar pizarras. Por ahora queda como pieza terminada de concepto.', [svgThumb('PDF', '#F14937', 'PDF')], 'MA'),
            makeNote('Ajustes responsive', 'pizzarra-app', today, 'culminada', '#6BAE45', 'Frontend', 'Normal', 'La interfaz debe adaptarse a laptop, monitor grande y celular.', [], 'VS'),
            makeNote('Notificaciones por email', 'pizzarra-app', today, 'pausada', '#2B2B2F', 'Backend', 'Se enfrió', 'Se deja pausado hasta que exista base de datos y usuarios.', [svgThumb('Email', '#2B2B2F', 'Mail')], 'OP'),
            makeNote('Sincronización con calendario', 'pizzarra-app', today, 'pausada', '#4DA3FF', 'Integración', 'Se enfrió', 'Posible integración futura para agenda semanal o recordatorios.', [svgThumb('Calendar', '#4DA3FF', '31')], 'KV'),
            makeNote('Modo oscuro', 'pizzarra-app', today, 'pausada', '#7C3AED', 'UI/UX', 'Se enfrió', 'Versión oscura opcional para trabajar de noche.', [], 'NA'),
            makeNote('Preparar presentación comercial', 'sabores-crm', today, 'pendiente', '#F14937', 'Ventas', 'Alta', 'Crear piezas con beneficios, capturas y próximos pasos.', [svgThumb('CRM', '#F14937', 'CRM')], 'SM'),
            makeNote('Revisar copy de landing', 'ingredientes-web', today, 'proceso', '#6BAE45', 'Contenido', 'Normal', 'Pulir mensajes de la página principal.', [], 'WE'),
            makeNote('Probar flujo mobile', 'recetas-mobile', today, 'culminada', '#F6C945', 'Mobile', 'Extra', 'Revisión rápida del flujo principal en celular.', [svgThumb('Mobile', '#F6C945', 'App')], 'MB'),
            makeNote('Depurar clientes duplicados', 'clientes-360', today, 'pausada', '#2B2B2F', 'Datos', 'Se enfrió', 'Pendiente de definir estructura final de clientes.', [], 'DB'),
            makeNote('Revisar pizarra de ayer', 'pizzarra-app', addDays(today, -1), 'culminada', '#6BAE45', 'Historial', 'Normal', 'Ejemplo de pieza en una fecha anterior para probar semana y mes.', [], 'LM'),
            makeNote('Planificar mejoras de mañana', 'pizzarra-app', addDays(today, 1), 'pendiente', '#F6C945', 'Planificación', 'Normal', 'Ejemplo de pieza futura.', [], 'LM')
        ];
        state.filters = { search: '', images: false, urgent: false, done: false, extras: false };
        state.selectedColor = '#F6C945';
        state.modalAttachments = [];
        state.editingId = null;
    }

    function makeNote(title, projectId, date, status, color, tag, priority, content, images, avatar) {
        var attachments = (images || []).map(function (url, index) {
            return {
                id: uid('att'),
                type: 'image',
                name: 'imagen_' + (index + 1) + '.png',
                url: url,
                uploaded: true
            };
        });
        return {
            id: uid('piece'),
            title: title,
            projectId: projectId,
            date: date,
            status: status,
            color: color,
            tag: tag || '',
            priority: priority || 'Normal',
            content: '<p>' + escapeHtml(content || '') + '</p>',
            link: '',
            attachments: attachments,
            comments: Math.floor(Math.random() * 4),
            avatar: avatar || 'LM',
            createdAt: Date.now(),
            updatedAt: Date.now()
        };
    }

    function activeNotes() {
        return state.notes.filter(function (note) {
            return note.projectId === state.activeProjectId;
        });
    }

    function notesForDate(iso) {
        return activeNotes().filter(function (note) {
            return note.date === iso;
        });
    }

    function matchesFilters(note) {
        var text = (note.title + ' ' + note.tag + ' ' + note.priority + ' ' + stripHtml(note.content)).toLowerCase();
        var search = state.filters.search.toLowerCase().trim();
        var hasImages = note.attachments && note.attachments.length > 0;
        var hasExtras = hasImages || !!note.link || stripHtml(note.content).length > 0;
        var isUrgent = note.priority === 'Alta' || note.priority === 'Doble queso' || note.priority === 'Picante';
        if (search && text.indexOf(search) === -1) {
            return false;
        }
        if (state.filters.images && !hasImages) {
            return false;
        }
        if (state.filters.urgent && !isUrgent) {
            return false;
        }
        if (state.filters.done && note.status !== 'culminada') {
            return false;
        }
        if (state.filters.extras && !hasExtras) {
            return false;
        }
        return true;
    }

    function filteredNotesForDate(iso) {
        return notesForDate(iso).filter(matchesFilters);
    }

    function render() {
        renderProjectControls();
        renderFavorites();
        renderSummary();
        updateDateLabels();
        updateViewButtons();
        if (state.view === 'day') {
            renderDay();
        } else if (state.view === 'week') {
            renderWeek();
        } else {
            renderMonth();
        }
    }

    function renderProjectControls() {
        var select = byId('pzProjectSelect');
        var pieceProject = byId('pzPieceProject');
        var list = byId('pzProjectList');
        var options = state.projects.map(function (project) {
            return '<option value="' + escapeHtml(project.id) + '">' + escapeHtml(project.name) + '</option>';
        }).join('');
        if (select) {
            select.innerHTML = options;
            select.value = state.activeProjectId;
        }
        if (pieceProject) {
            pieceProject.innerHTML = options;
            pieceProject.value = state.activeProjectId;
        }
        if (list) {
            list.innerHTML = state.projects.map(function (project) {
                var active = project.id === state.activeProjectId ? ' active' : '';
                return '<button type="button" class="pz-project-item' + active + '" data-project-id="' + escapeHtml(project.id) + '">' +
                    '<span class="pz-project-dot" style="--dot:' + escapeHtml(project.color) + '"></span>' +
                    '<span>' + escapeHtml(project.name) + '</span>' +
                    '</button>';
            }).join('');
        }
    }

    function renderFavorites() {
        var html = state.favoriteColors.map(function (color) {
            return '<button type="button" class="pz-color-dot" style="--c:' + escapeHtml(color.value) + '" title="' + escapeHtml(color.name) + '" data-color="' + escapeHtml(color.value) + '"></button>';
        }).join('');
        ['pzFavoriteColors', 'pzColorsManager'].forEach(function (id) {
            var el = byId(id);
            if (el) {
                el.innerHTML = html;
            }
        });
        renderPieceColorOptions();
    }

    function renderPieceColorOptions() {
        var wrap = byId('pzPieceColorOptions');
        if (!wrap) {
            return;
        }
        wrap.innerHTML = state.favoriteColors.map(function (color) {
            var active = color.value.toLowerCase() === state.selectedColor.toLowerCase() ? ' active' : '';
            return '<button type="button" class="pz-color-option' + active + '" style="--c:' + escapeHtml(color.value) + '" data-color="' + escapeHtml(color.value) + '" title="' + escapeHtml(color.name) + '"></button>';
        }).join('');
        var custom = byId('pzPieceCustomColor');
        if (custom) {
            custom.value = state.selectedColor;
        }
    }

    function renderSummary() {
        var notes = notesForDate(state.currentDate);
        var summary = STATUS_KEYS.map(function (key) {
            return {
                key: key,
                label: STATUS[key].title.charAt(0) + STATUS[key].title.slice(1).toLowerCase(),
                count: notes.filter(function (note) { return note.status === key; }).length,
                color: STATUS[key].color
            };
        });
        var wrap = byId('pzSummaryList');
        if (wrap) {
            wrap.innerHTML = summary.map(function (row) {
                return '<div class="pz-summary-row"><span>' + escapeHtml(row.label) + '</span><strong class="pz-summary-badge" style="--c:' + escapeHtml(row.color) + '">' + row.count + '</strong></div>';
            }).join('');
        }
        var total = notes.length;
        var done = notes.filter(function (note) { return note.status === 'culminada'; }).length;
        var percent = total ? Math.round((done / total) * 100) : 0;
        byId('pzProgressText').textContent = percent + '%';
        byId('pzProgressBar').style.width = percent + '%';
    }

    function updateDateLabels() {
        var project = getProject(state.activeProjectId);
        var label = formatLongDate(state.currentDate);
        byId('pzDateLabel').textContent = label;
        byId('pzDateInput').value = state.currentDate;
        byId('pzBoardTitle').textContent = project ? 'Pizarra · ' + project.name : 'Pizarra';
        byId('pzBoardSubtitle').textContent = 'Fecha activa: ' + label + '. Las piezas persisten solo mientras no recargues la página.';
    }

    function updateViewButtons() {
        qsa('.pz-view-switch button').forEach(function (btn) {
            btn.classList.toggle('active', btn.getAttribute('data-view') === state.view);
        });
        byId('pzDayView').classList.toggle('pz-hidden', state.view !== 'day');
        byId('pzWeekView').classList.toggle('pz-hidden', state.view !== 'week');
        byId('pzMonthView').classList.toggle('pz-hidden', state.view !== 'month');
    }

    function renderDay() {
        STATUS_KEYS.forEach(function (key) {
            var col = byId(STATUS[key].column);
            if (!col) {
                return;
            }
            var notes = filteredNotesForDate(state.currentDate).filter(function (note) {
                return note.status === key;
            });
            col.innerHTML = notes.length ? notes.map(renderPieceCard).join('') : '<div class="pz-empty-column">Arrastra o agrega una pieza aquí.</div>';
        });
    }

    function renderPieceCard(note) {
        var tags = [];
        if (note.tag) {
            tags.push('<span class="pz-chip ' + chipClass(note.tag) + '">' + escapeHtml(note.tag) + '</span>');
        }
        if (note.priority && note.priority !== 'Normal') {
            tags.push('<span class="pz-chip ' + priorityClass(note.priority) + '">' + escapeHtml(note.priority) + '</span>');
        }
        if (note.link) {
            tags.push('<span class="pz-chip pz-chip-blue">Link</span>');
        }
        var preview = stripHtml(note.content).slice(0, 120);
        var thumbs = (note.attachments || []).slice(0, 3).map(function (att) {
            return '<img class="pz-thumb" src="' + escapeHtml(att.url) + '" alt="' + escapeHtml(att.name || 'Imagen') + '">';
        }).join('');
        var attachmentsCount = (note.attachments || []).length;
        return '<article class="pz-piece-card" draggable="true" data-note-id="' + escapeHtml(note.id) + '" style="--piece:' + escapeHtml(note.color || STATUS[note.status].color) + '">' +
            '<button type="button" class="pz-piece-menu" data-edit-note="' + escapeHtml(note.id) + '" title="Editar pieza">⋯</button>' +
            '<h4 class="pz-piece-title">' + escapeHtml(note.title) + '</h4>' +
            '<div class="pz-piece-tags">' + tags.join('') + '</div>' +
            (preview ? '<div class="pz-piece-preview">' + escapeHtml(preview) + '</div>' : '') +
            (thumbs ? '<div class="pz-piece-thumbs">' + thumbs + '</div>' : '') +
            '<div class="pz-piece-meta">' +
            '<span>📅 ' + escapeHtml(formatShortDate(note.date)) + '</span>' +
            '<span>💬 ' + (note.comments || 0) + '</span>' +
            '<span>📎 ' + attachmentsCount + '</span>' +
            '<span class="pz-avatar">' + escapeHtml(note.avatar || 'PZ') + '</span>' +
            '</div>' +
            '</article>';
    }

    function chipClass(tag) {
        tag = String(tag || '').toLowerCase();
        if (tag.indexOf('front') !== -1 || tag.indexOf('back') !== -1) {
            return 'pz-chip-blue';
        }
        if (tag.indexOf('dise') !== -1 || tag.indexOf('brand') !== -1 || tag.indexOf('ui') !== -1) {
            return '';
        }
        return 'pz-chip-blue';
    }

    function priorityClass(priority) {
        if (priority === 'Alta' || priority === 'Picante') {
            return 'pz-chip-high';
        }
        if (priority === 'Doble queso') {
            return 'pz-chip-cheese';
        }
        if (priority === 'Se enfrió') {
            return 'pz-chip-cold';
        }
        if (priority === 'Extra') {
            return '';
        }
        return '';
    }

    function renderWeek() {
        var start = startOfWeek(state.currentDate);
        var days = [];
        for (var i = 0; i < 7; i++) {
            days.push(addDays(start, i));
        }
        byId('pzWeekView').innerHTML = '<div class="pz-week-grid">' + days.map(function (iso) {
            var notes = filteredNotesForDate(iso);
            var mini = notes.slice(0, 4).map(function (note) {
                return '<div class="pz-mini-note" style="--piece:' + escapeHtml(note.color || STATUS[note.status].color) + '">' + escapeHtml(note.title) + '</div>';
            }).join('');
            return '<article class="pz-week-day ' + (iso === state.currentDate ? 'active' : '') + '" data-jump-date="' + escapeHtml(iso) + '">' +
                '<h4>' + escapeHtml(formatShortDate(iso)) + '</h4>' +
                '<span class="pz-count-pill">' + notes.length + ' piezas</span>' +
                mini +
                '</article>';
        }).join('') + '</div>';
    }

    function renderMonth() {
        var start = startOfMonth(state.currentDate);
        var end = endOfMonth(state.currentDate);
        var firstDay = start.getDay() === 0 ? 6 : start.getDay() - 1;
        var gridStart = new Date(start);
        gridStart.setDate(start.getDate() - firstDay);
        var cells = [];
        for (var i = 0; i < 42; i++) {
            var date = new Date(gridStart);
            date.setDate(gridStart.getDate() + i);
            cells.push(toISO(date));
        }
        var currentMonth = start.getMonth();
        byId('pzMonthView').innerHTML = '<div class="pz-month-grid">' + cells.map(function (iso) {
            var date = parseISO(iso);
            var notes = filteredNotesForDate(iso);
            var muted = date.getMonth() !== currentMonth ? ' is-muted' : '';
            var mini = notes.slice(0, 2).map(function (note) {
                return '<div class="pz-mini-note" style="--piece:' + escapeHtml(note.color || STATUS[note.status].color) + '">' + escapeHtml(note.title) + '</div>';
            }).join('');
            return '<article class="pz-month-day ' + (iso === state.currentDate ? 'active' : '') + muted + '" data-jump-date="' + escapeHtml(iso) + '">' +
                '<h4>' + pad(date.getDate()) + '</h4>' +
                '<span class="pz-count-pill">' + notes.length + '</span>' +
                mini +
                '</article>';
        }).join('') + '</div>';
    }

    function openModal(id) {
        byId('pzModalBackdrop').hidden = false;
        var modal = byId(id);
        if (modal) {
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
        }
    }

    function closeModal(id) {
        var modal = byId(id);
        if (modal) {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
        }
        var anyOpen = qsa('.pz-modal.is-open').length > 0;
        byId('pzModalBackdrop').hidden = anyOpen ? false : true;
    }

    function closeAllModals() {
        qsa('.pz-modal.is-open').forEach(function (modal) {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
        });
        byId('pzModalBackdrop').hidden = true;
    }

    function initSummernoteIfAvailable() {
        if (state.summernoteReady) {
            return;
        }
        if (window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.summernote === 'function') {
            window.jQuery('#pzPieceEditor').summernote({
                height: 170,
                placeholder: 'Escribe detalles, pega una tabla, agrega pendientes o texto enriquecido...',
                toolbar: [
                    ['style', ['bold', 'italic', 'underline', 'clear']],
                    ['font', ['strikethrough']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['insert', ['link', 'table']],
                    ['view', ['codeview']]
                ]
            });
            state.summernoteReady = true;
        }
    }

    function getEditorHtml() {
        if (state.summernoteReady && window.jQuery) {
            return sanitizeHtml(window.jQuery('#pzPieceEditor').summernote('code'));
        }
        return sanitizeHtml(byId('pzPieceEditor').innerHTML);
    }

    function setEditorHtml(html) {
        if (state.summernoteReady && window.jQuery) {
            window.jQuery('#pzPieceEditor').summernote('code', html || '');
            return;
        }
        byId('pzPieceEditor').innerHTML = html || '';
    }

    function openPieceModal(status, id) {
        initSummernoteIfAvailable();
        var note = id ? getNote(id) : null;
        state.editingId = note ? note.id : null;
        state.modalAttachments = note && note.attachments ? note.attachments.map(function (att) { return Object.assign({}, att); }) : [];
        byId('pzPieceModalTitle').textContent = note ? 'Editar pieza' : 'Nueva pieza';
        byId('pzPieceId').value = note ? note.id : '';
        byId('pzPieceTitle').value = note ? note.title : '';
        byId('pzPieceProject').value = note ? note.projectId : state.activeProjectId;
        byId('pzPieceDate').value = note ? note.date : state.currentDate;
        byId('pzPieceStatus').value = note ? note.status : (status || 'pendiente');
        byId('pzPiecePriority').value = note ? note.priority : 'Normal';
        byId('pzPieceTag').value = note ? note.tag : '';
        byId('pzPieceLink').value = note ? note.link : '';
        state.selectedColor = note ? (note.color || STATUS[note.status].color) : STATUS[status || 'pendiente'].color;
        byId('pzPieceCustomColor').value = state.selectedColor;
        byId('pzPieceImages').value = '';
        setEditorHtml(note ? note.content : '');
        renderPieceColorOptions();
        renderUploadPreview();
        byId('pzBtnDeletePiece').style.display = note ? 'inline-flex' : 'none';
        openModal('pzPieceModal');
        setTimeout(function () { byId('pzPieceTitle').focus(); }, 70);
    }

    function renderUploadPreview() {
        var wrap = byId('pzUploadPreview');
        if (!wrap) {
            return;
        }
        if (!state.modalAttachments.length) {
            wrap.innerHTML = '<span class="pz-muted">Sin imágenes todavía.</span>';
            return;
        }
        wrap.innerHTML = state.modalAttachments.map(function (att) {
            return '<div class="pz-upload-item" data-attachment-id="' + escapeHtml(att.id) + '">' +
                '<img src="' + escapeHtml(att.url) + '" alt="' + escapeHtml(att.name || 'Imagen') + '">' +
                '<button type="button" data-remove-attachment="' + escapeHtml(att.id) + '">×</button>' +
                '</div>';
        }).join('');
    }

    function savePiece(event) {
        event.preventDefault();
        var id = state.editingId;
        var title = byId('pzPieceTitle').value.trim();
        if (!title) {
            toast('Falta el título', 'Escribe un título para la pieza.', '⚠️');
            byId('pzPieceTitle').focus();
            return;
        }
        var data = {
            title: title,
            projectId: byId('pzPieceProject').value,
            date: byId('pzPieceDate').value || state.currentDate,
            status: byId('pzPieceStatus').value,
            color: state.selectedColor,
            priority: byId('pzPiecePriority').value,
            tag: byId('pzPieceTag').value.trim(),
            link: byId('pzPieceLink').value.trim(),
            content: getEditorHtml(),
            attachments: state.modalAttachments.map(function (att) { return Object.assign({}, att); }),
            updatedAt: Date.now()
        };
        if (id) {
            var note = getNote(id);
            if (note) {
                Object.keys(data).forEach(function (key) {
                    note[key] = data[key];
                });
            }
            toast('Pieza actualizada', 'Los cambios quedaron en memoria hasta recargar la página.', '✅');
        } else {
            data.id = uid('piece');
            data.createdAt = Date.now();
            data.comments = 0;
            data.avatar = 'LM';
            state.notes.push(data);
            toast('Pieza creada', 'Nueva pieza agregada a la pizarra.', '🍕');
        }
        state.activeProjectId = data.projectId;
        state.currentDate = data.date;
        closeModal('pzPieceModal');
        render();
    }

    function deleteCurrentPiece() {
        if (!state.editingId) {
            return;
        }
        if (!window.confirm('¿Eliminar esta pieza de la vista actual?')) {
            return;
        }
        state.notes = state.notes.filter(function (note) {
            return note.id !== state.editingId;
        });
        closeModal('pzPieceModal');
        toast('Pieza eliminada', 'Se retiró de la información temporal de esta sesión.', '🗑️');
        render();
    }

    function openBoxModal() {
        byId('pzBoxName').value = '';
        byId('pzBoxDescription').value = '';
        byId('pzBoxColor').value = '#F58A1F';
        openModal('pzBoxModal');
        setTimeout(function () { byId('pzBoxName').focus(); }, 70);
    }

    function saveBox(event) {
        event.preventDefault();
        var name = byId('pzBoxName').value.trim();
        if (!name) {
            toast('Falta el nombre', 'Escribe el nombre de la caja.', '⚠️');
            return;
        }
        var project = {
            id: uid('project'),
            name: name,
            description: byId('pzBoxDescription').value.trim(),
            color: byId('pzBoxColor').value || '#F58A1F'
        };
        state.projects.push(project);
        state.activeProjectId = project.id;
        closeModal('pzBoxModal');
        toast('Caja creada', 'Ya puedes agregar piezas a este proyecto.', '📦');
        render();
    }

    function addFavoriteColor() {
        var value = byId('pzNewFavoriteColor').value || '#7C3AED';
        var name = byId('pzNewFavoriteName').value.trim() || 'Color favorito';
        state.favoriteColors.push({ name: name, value: value });
        byId('pzNewFavoriteName').value = '';
        toast('Color agregado', 'Disponible para nuevas piezas en esta sesión.', '🎨');
        renderFavorites();
    }

    function handleImageFiles(files) {
        Array.prototype.forEach.call(files || [], function (file) {
            if (!file.type || file.type.indexOf('image/') !== 0) {
                toast('Archivo omitido', 'Solo se aceptan imágenes.', '⚠️');
                return;
            }
            var att = {
                id: uid('att'),
                type: 'image',
                name: file.name,
                url: '',
                uploaded: false
            };
            state.modalAttachments.push(att);
            var reader = new FileReader();
            reader.onload = function (e) {
                att.url = e.target.result;
                renderUploadPreview();
            };
            reader.readAsDataURL(file);
            uploadImage(file, att);
        });
    }

    function uploadImage(file, attachment) {
        if (!window.fetch || !window.FormData || !CFG.uploadUrl) {
            return;
        }
        var fd = new FormData();
        fd.append('imagen', file);
        fetch(CFG.uploadUrl, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        }).then(function (res) {
            return res.json();
        }).then(function (data) {
            if (data && data.ok && data.url) {
                attachment.url = data.url;
                attachment.uploaded = true;
                renderUploadPreview();
                toast('Extra guardado', 'Imagen subida a almacen/año/mes/día.', '📎');
            }
        }).catch(function () {
            // Si el servidor no permite subir todavía, se mantiene la vista temporal con FileReader.
        });
    }

    function moveNote(id, status) {
        var note = getNote(id);
        if (!note || !STATUS[status]) {
            return;
        }
        if (note.status === status) {
            return;
        }
        note.status = status;
        note.updatedAt = Date.now();
        toast(STATUS[status].title, STATUS[status].toast, STATUS[status].icon);
        render();
    }

    function changeView(view) {
        state.view = view;
        render();
    }

    function jumpDate(iso) {
        state.currentDate = iso;
        state.view = 'day';
        render();
    }

    function toast(title, message, icon) {
        var wrap = byId('pzToastWrap');
        if (!wrap) {
            return;
        }
        var node = document.createElement('div');
        node.className = 'pz-toast';
        node.innerHTML = '<div class="pz-toast-icon">' + escapeHtml(icon || '🍕') + '</div><div><strong>' + escapeHtml(title) + '</strong><p>' + escapeHtml(message) + '</p></div>';
        wrap.appendChild(node);
        setTimeout(function () {
            node.style.opacity = '0';
            node.style.transform = 'translateY(8px)';
            setTimeout(function () {
                if (node.parentNode) {
                    node.parentNode.removeChild(node);
                }
            }, 220);
        }, 3600);
    }

    function bindEvents() {
        byId('pzProjectSelect').addEventListener('change', function () {
            state.activeProjectId = this.value;
            render();
        });
        byId('pzPrevDay').addEventListener('click', function () {
            state.currentDate = addDays(state.currentDate, -1);
            render();
        });
        byId('pzNextDay').addEventListener('click', function () {
            state.currentDate = addDays(state.currentDate, 1);
            render();
        });
        byId('pzBtnToday').addEventListener('click', function () {
            state.currentDate = todayISO();
            state.view = 'day';
            render();
        });
        byId('pzDateLabel').addEventListener('click', function () {
            var input = byId('pzDateInput');
            if (typeof input.showPicker === 'function') {
                input.showPicker();
            } else {
                input.focus();
                input.click();
            }
        });
        byId('pzDateInput').addEventListener('change', function () {
            if (this.value) {
                state.currentDate = this.value;
                render();
            }
        });
        qsa('.pz-view-switch button').forEach(function (btn) {
            btn.addEventListener('click', function () {
                changeView(btn.getAttribute('data-view'));
            });
        });
        byId('pzBtnNewPiece').addEventListener('click', function () { openPieceModal('pendiente'); });
        byId('pzBtnNewBox').addEventListener('click', openBoxModal);
        byId('pzBtnNewBoxSide').addEventListener('click', openBoxModal);
        byId('pzBtnManageColors').addEventListener('click', function () { openModal('pzColorsModal'); });
        byId('pzBtnReset').addEventListener('click', function () {
            seedData();
            toast('Vista restaurada', 'Volvió la información genérica inicial.', '✨');
            render();
        });
        byId('pzPieceForm').addEventListener('submit', savePiece);
        byId('pzBoxForm').addEventListener('submit', saveBox);
        byId('pzBtnDeletePiece').addEventListener('click', deleteCurrentPiece);
        byId('pzBtnAddFavoriteColor').addEventListener('click', addFavoriteColor);
        byId('pzPieceImages').addEventListener('change', function () { handleImageFiles(this.files); });
        byId('pzPieceCustomColor').addEventListener('input', function () {
            state.selectedColor = this.value;
            renderPieceColorOptions();
        });
        byId('pzSearch').addEventListener('input', function () {
            state.filters.search = this.value;
            render();
        });
        byId('pzFilterImages').addEventListener('change', function () { state.filters.images = this.checked; render(); });
        byId('pzFilterUrgent').addEventListener('change', function () { state.filters.urgent = this.checked; render(); });
        byId('pzFilterDone').addEventListener('change', function () { state.filters.done = this.checked; render(); });
        byId('pzFilterExtras').addEventListener('change', function () { state.filters.extras = this.checked; render(); });

        document.addEventListener('click', function (event) {
            var target = event.target;
            var projectBtn = target.closest ? target.closest('[data-project-id]') : null;
            if (projectBtn) {
                state.activeProjectId = projectBtn.getAttribute('data-project-id');
                render();
                return;
            }
            var addBtn = target.closest ? target.closest('[data-status]') : null;
            if (addBtn && (addBtn.classList.contains('pz-column-add') || addBtn.classList.contains('pz-add-piece-link'))) {
                openPieceModal(addBtn.getAttribute('data-status'));
                return;
            }
            var editBtn = target.closest ? target.closest('[data-edit-note]') : null;
            if (editBtn) {
                event.stopPropagation();
                openPieceModal(null, editBtn.getAttribute('data-edit-note'));
                return;
            }
            var card = target.closest ? target.closest('.pz-piece-card') : null;
            if (card && !target.closest('button')) {
                openPieceModal(null, card.getAttribute('data-note-id'));
                return;
            }
            var colorBtn = target.closest ? target.closest('.pz-color-option') : null;
            if (colorBtn) {
                state.selectedColor = colorBtn.getAttribute('data-color');
                renderPieceColorOptions();
                return;
            }
            var favoriteBtn = target.closest ? target.closest('#pzFavoriteColors .pz-color-dot') : null;
            if (favoriteBtn) {
                state.selectedColor = favoriteBtn.getAttribute('data-color');
                openPieceModal('pendiente');
                return;
            }
            var removeAtt = target.closest ? target.closest('[data-remove-attachment]') : null;
            if (removeAtt) {
                var attId = removeAtt.getAttribute('data-remove-attachment');
                state.modalAttachments = state.modalAttachments.filter(function (att) { return att.id !== attId; });
                renderUploadPreview();
                return;
            }
            var closeBtn = target.closest ? target.closest('[data-close-modal]') : null;
            if (closeBtn) {
                closeModal(closeBtn.getAttribute('data-close-modal'));
                return;
            }
            var jump = target.closest ? target.closest('[data-jump-date]') : null;
            if (jump) {
                jumpDate(jump.getAttribute('data-jump-date'));
            }
        });

        document.addEventListener('dblclick', function (event) {
            var card = event.target.closest ? event.target.closest('.pz-piece-card') : null;
            if (card) {
                openPieceModal(null, card.getAttribute('data-note-id'));
            }
        });

        document.addEventListener('dragstart', function (event) {
            var card = event.target.closest ? event.target.closest('.pz-piece-card') : null;
            if (!card) {
                return;
            }
            event.dataTransfer.setData('text/plain', card.getAttribute('data-note-id'));
            event.dataTransfer.effectAllowed = 'move';
        });

        qsa('.pz-column-dropzone').forEach(function (zone) {
            zone.addEventListener('dragover', function (event) {
                event.preventDefault();
                zone.classList.add('is-over');
            });
            zone.addEventListener('dragleave', function () {
                zone.classList.remove('is-over');
            });
            zone.addEventListener('drop', function (event) {
                event.preventDefault();
                zone.classList.remove('is-over');
                var id = event.dataTransfer.getData('text/plain');
                var status = zone.getAttribute('data-status');
                moveNote(id, status);
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeAllModals();
            }
        });
    }

    function boot() {
        seedData();
        bindEvents();
        render();
        toast('PIZZARRA lista', 'Primera versión visual cargada con datos genéricos.', '🍕');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
