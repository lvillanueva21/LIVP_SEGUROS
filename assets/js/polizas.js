(() => {
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
  const esc = (value) => { const node = document.createElement('span'); node.textContent = String(value ?? ''); return node.innerHTML; };
  const note = (type, message, options = {}) => window.BrokerNotify?.[type]?.(message, options);
  const now = () => window.BrokerDemo?.limaDateTime?.() || new Date().toISOString().slice(0, 19).replace('T', ' ');
  const dateLabel = (value, includeTime = false) => window.BrokerDemo?.formatPeruDate?.(value, includeTime) || String(value || 'No registrado');
  const toInput = (value) => String(value || '').slice(0, 16).replace(' ', 'T');
  const toStorage = (value) => {
    const text = String(value || '').trim();
    return text ? `${text.replace('T', ' ')}:00` : '';
  };

  function api() {
    return window.BrokerExpedients || null;
  }

  function policies(expedient) {
    return Array.isArray(expedient?.policies) ? expedient.policies : [];
  }

  function activeCatalog(id) {
    const catalogs = api()?.getCatalogs?.() || {};
    const items = catalogs?.[id]?.items;
    return Array.isArray(items) ? items.filter((item) => item.status === 'Activo') : [];
  }

  function nextPolicyCode(records) {
    const year = window.BrokerDemo?.limaYear?.() || new Date().getFullYear();
    const prefix = `POL-${year}-`;
    const values = records.flatMap((item) => policies(item))
      .filter((policy) => String(policy.code || '').startsWith(prefix))
      .map((policy) => Number(String(policy.code).slice(prefix.length)))
      .filter(Number.isFinite);
    return `${prefix}${String((values.length ? Math.max(...values) : 0) + 1).padStart(4, '0')}`;
  }

  function sizeLabel(bytes) {
    const value = Number(bytes || 0);
    if (!value) return 'Tamaño no disponible';
    if (value < 1024) return `${value} B`;
    if (value < 1024 * 1024) return `${(value / 1024).toFixed(1)} KB`;
    return `${(value / (1024 * 1024)).toFixed(2)} MB`;
  }

  function option(items, selected = '', placeholder = 'Selecciona') {
    return [
      `<option value="">${esc(placeholder)}</option>`,
      ...items.map((item) => `<option value="${esc(item.id)}" ${String(item.id) === String(selected) ? 'selected' : ''}>${esc(item.name)}</option>`),
    ].join('');
  }

  function statusBadge(policy) {
    const label = policy.active === false ? 'Desactivada' : (policy.status || 'En emisión');
    const slug = label.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-');
    return `<span class="policy-status policy-status-${esc(slug)}">${esc(label)}</span>`;
  }

  function getExpedient(id) {
    return api()?.getExpedients?.().find((item) => String(item.id) === String(id)) || null;
  }

  function showDetail(id) {
    const module = api();
    if (!module?.openDetail) {
      note('error', 'No se pudo volver a abrir la ficha del expediente. Recarga la página.', { title: 'Ficha no disponible', duration: 0 });
      return;
    }
    module.openDetail(id);
  }

  function renderSection(expedientId) {
    const content = $('#expedient-detail-content');
    const expedient = getExpedient(expedientId || content?.dataset.expedientId);
    if (!content || !expedient || content.dataset.view !== 'detail') return;

    content.querySelector('#policy-section')?.remove();

    const canRegister = Boolean(expedient.client_id);
    const list = policies(expedient);
    const section = document.createElement('section');
    section.id = 'policy-section';
    section.className = 'policy-section';

    const cards = list.length
      ? list.map((policy) => {
          const pdf = policy.file?.relative_path
            ? `<a class="policy-file-link" href="api/view_policy_pdf.php?path=${encodeURIComponent(policy.file.relative_path)}" target="_blank" rel="noopener">PDF: ${esc(policy.file.original_name || 'Abrir documento')}</a>`
            : '<span class="policy-document-missing">Falta PDF de póliza</span>';

          return `<article class="policy-card ${policy.active === false ? 'is-inactive' : ''}">
            <div class="policy-card-heading">
              <div><p class="policy-code">${esc(policy.code)}</p><h4>${esc(policy.title)}</h4></div>
              ${statusBadge(policy)}
            </div>
            <dl class="policy-card-grid">
              <div><dt>Tipo de seguro</dt><dd>${esc(policy.insurance_type_name || 'No registrado')}</dd></div>
              <div><dt>Aseguradora</dt><dd>${esc(policy.insurer_name || 'No registrada')}</dd></div>
              <div><dt>Vigencia</dt><dd>${esc(dateLabel(policy.starts_at, true))} — ${esc(dateLabel(policy.ends_at, true))}</dd></div>
              <div><dt>Suma asegurada</dt><dd>${esc(policy.insured_amount ? `${policy.currency_name ? `${policy.currency_name} ` : ''}${policy.insured_amount}` : 'No registrada')}</dd></div>
            </dl>
            <div class="policy-card-file">${pdf}</div>
            ${policy.active === false && policy.deactivation_reason ? `<p class="policy-inactive-note">Motivo: ${esc(policy.deactivation_reason)}</p>` : ''}
            <div class="policy-card-actions">
              <button class="policy-secondary-button" type="button" data-policy-action="edit" data-policy-id="${esc(policy.id)}">Ver / editar</button>
              ${policy.active === false
                ? `<button class="policy-secondary-button" type="button" data-policy-action="activate" data-policy-id="${esc(policy.id)}">Reactivar</button>`
                : `<button class="policy-danger-button" type="button" data-policy-action="deactivate" data-policy-id="${esc(policy.id)}">Desactivar</button>`}
            </div>
          </article>`;
        }).join('')
      : '<p class="policy-empty">Aún no existen pólizas registradas. El expediente puede continuar sin póliza, pero ya tiene cliente definido para registrar una cuando corresponda.</p>';

    section.innerHTML = `
      <div class="policy-section-heading">
        <div>
          <p class="eyebrow">PÓLIZAS</p>
          <h3>Pólizas del expediente</h3>
          <p>Las pólizas se registran sin exigir cotización previa. El cliente queda guardado como referencia histórica.</p>
        </div>
        <button id="add-policy" class="policy-primary-button" type="button" ${canRegister ? '' : 'disabled'}>+ Registrar póliza</button>
      </div>
      ${canRegister ? '' : '<div class="policy-warning">Define primero el Cliente o entidad en el expediente. Sin cliente no se puede registrar una póliza.</div>'}
      <div class="policy-list">${cards}</div>
    `;

    const anchor = content.querySelector('.expedient-detail-edit');
    if (anchor) anchor.insertAdjacentElement('beforebegin', section);
    else content.appendChild(section);

    $('#add-policy', section)?.addEventListener('click', () => openEditor(expedient.id));
    $$('[data-policy-action]', section).forEach((button) => {
      button.addEventListener('click', () => {
        const action = button.dataset.policyAction;
        const policyId = button.dataset.policyId;
        if (action === 'edit') openEditor(expedient.id, policyId);
        if (action === 'deactivate') openDeactivate(expedient.id, policyId);
        if (action === 'activate') activate(expedient.id, policyId);
      });
    });
  }

  function editorMarkup(expedient, policy = null) {
    const client = policy?.client_snapshot || {
      id: expedient.client_id,
      name: expedient.client_name,
      document: expedient.client_document,
      entity_type: expedient.entity_type,
    };

    return `
      <section class="policy-editor" data-expedient-id="${esc(expedient.id)}" data-policy-id="${esc(policy?.id || '')}">
        <div class="policy-editor-heading">
          <div>
            <p class="eyebrow">PÓLIZA</p>
            <h3>${policy ? `Editar ${esc(policy.code)}` : 'Registrar póliza'}</h3>
            <p>Completa la información mínima. El PDF se puede adjuntar ahora o después.</p>
          </div>
          <button id="policy-back" class="policy-secondary-button" type="button">← Volver a ficha</button>
        </div>

        <div class="policy-info-box">
          <strong>Cliente histórico de esta póliza</strong>
          <span>${esc(client.name || 'No definido')}</span>
          <small>${esc([client.entity_type, client.document].filter(Boolean).join(' · ') || 'Se conserva desde el expediente')}</small>
        </div>

        <form id="policy-form" novalidate>
          <div class="policy-form-grid">
            <label class="policy-field-wide"><span>Título <b>*</b></span><input id="policy-title" maxlength="160" value="${esc(policy?.title || '')}" required></label>
            <label><span>Tipo de seguro <b>*</b></span><select id="policy-type" required>${option(activeCatalog('tipos_seguro'), policy?.insurance_type_id || '', 'Selecciona un tipo')}</select></label>
            <label><span>Aseguradora <b>*</b></span><select id="policy-insurer" required>${option(activeCatalog('aseguradoras'), policy?.insurer_id || '', 'Selecciona una aseguradora')}</select></label>
            <label><span>Estado</span><select id="policy-status">${option(activeCatalog('estados_poliza'), policy?.status || 'En emisión', 'En emisión')}</select></label>
            <label><span>Fecha y hora de emisión</span><input id="policy-issued" type="datetime-local" value="${esc(toInput(policy?.issued_at || now()))}"></label>
            <label><span>Inicio de vigencia <b>*</b></span><input id="policy-start" type="datetime-local" value="${esc(toInput(policy?.starts_at || ''))}" required></label>
            <label><span>Fin de vigencia <b>*</b></span><input id="policy-end" type="datetime-local" value="${esc(toInput(policy?.ends_at || ''))}" required></label>
            <label><span>Suma asegurada</span><input id="policy-amount" inputmode="decimal" maxlength="40" value="${esc(policy?.insured_amount || '')}" placeholder="Ejemplo: 250000.00"></label>
            <label><span>Moneda</span><select id="policy-currency">${option(activeCatalog('monedas'), policy?.currency_id || '', 'Sin moneda definida')}</select></label>
            <label class="policy-field-wide"><span>Descripción</span><textarea id="policy-description" rows="3" maxlength="800">${esc(policy?.description || '')}</textarea></label>
            <label class="policy-field-wide"><span>Observaciones</span><textarea id="policy-observations" rows="3" maxlength="800">${esc(policy?.observations || '')}</textarea></label>
          </div>

          <section class="policy-file-box">
            <div><h3>PDF principal de póliza</h3><p>Opcional. Si no lo adjuntas, la ficha mostrará que falta el documento.</p></div>
            ${policy?.file?.relative_path
              ? `<p class="policy-current-file">Actual: <a href="api/view_policy_pdf.php?path=${encodeURIComponent(policy.file.relative_path)}" target="_blank" rel="noopener">${esc(policy.file.original_name || 'Abrir PDF')}</a> · ${esc(sizeLabel(policy.file.size_bytes))}</p>`
              : '<p class="policy-document-missing">Falta el documento PDF de la póliza.</p>'}
            <label class="policy-file-input"><span>Seleccionar PDF ${policy?.file?.relative_path ? 'para reemplazar' : ''}</span><input id="policy-file" type="file" accept="application/pdf,.pdf"></label>
            <p id="policy-file-preview" class="policy-file-preview">No se seleccionó un archivo nuevo.</p>
            <div id="policy-progress-wrap" class="policy-upload-progress" hidden><progress id="policy-progress" max="100" value="0"></progress><span id="policy-upload-status">Preparando carga…</span></div>
          </section>

          <p class="policy-lima-note">Las fechas y horas se registran como America/Lima. El fin debe ser posterior al inicio.</p>
          <div class="policy-editor-actions">
            <button id="policy-cancel" class="policy-secondary-button" type="button">Cancelar</button>
            <button id="policy-save" class="policy-primary-button" type="submit">Guardar póliza</button>
          </div>
        </form>
      </section>`;
  }

  function openEditor(expedientId, policyId = '') {
    const content = $('#expedient-detail-content');
    const expedient = getExpedient(expedientId);
    if (!content || !expedient) {
      note('error', 'No se pudo identificar el expediente abierto. Recarga la página e intenta nuevamente.', { title: 'Expediente no disponible', duration: 0 });
      return;
    }
    if (!expedient.client_id) {
      note('warning', 'Define primero el Cliente o entidad del expediente antes de registrar una póliza.', { title: 'Cliente pendiente' });
      return;
    }

    const policy = policies(expedient).find((item) => String(item.id) === String(policyId)) || null;
    content.dataset.expedientId = expedient.id;
    content.dataset.view = 'policy';
    content.innerHTML = editorMarkup(expedient, policy);

    $('#policy-back', content)?.addEventListener('click', () => showDetail(expedient.id));
    $('#policy-cancel', content)?.addEventListener('click', () => showDetail(expedient.id));
    $('#policy-file', content)?.addEventListener('change', () => {
      const file = $('#policy-file', content).files?.[0];
      $('#policy-file-preview', content).textContent = file
        ? `Seleccionado: ${file.name} · ${sizeLabel(file.size)}`
        : 'No se seleccionó un archivo nuevo.';
    });
    $('#policy-form', content)?.addEventListener('submit', savePolicy);
    $('#policy-title', content)?.focus();
  }

  function uploadPdf(file, code, insuranceType, clientDocument = '') {
    return new Promise((resolve, reject) => {
      const content = $('#expedient-detail-content');
      const progressBox = $('#policy-progress-wrap', content);
      const progress = $('#policy-progress', content);
      const status = $('#policy-upload-status', content);
      const data = new FormData();
      const xhr = new XMLHttpRequest();

      data.append('pdf', file);
      data.append('policy_code', code);
      data.append('insurance_type', insuranceType);
      data.append('client_document', clientDocument);

      progressBox.hidden = false;
      progress.value = 0;
      status.textContent = 'Iniciando carga del PDF…';

      xhr.open('POST', 'api/upload_policy_pdf.php', true);
      xhr.responseType = 'json';
      xhr.timeout = 0;

      xhr.upload.addEventListener('progress', (event) => {
        if (!event.lengthComputable) {
          status.textContent = 'Subiendo PDF… el servidor aún no informó porcentaje.';
          return;
        }
        const percent = Math.min(100, Math.round((event.loaded / event.total) * 100));
        progress.value = percent;
        status.textContent = `Subiendo PDF: ${percent}%`;
      });

      xhr.addEventListener('load', () => {
        const payload = xhr.response || (() => { try { return JSON.parse(xhr.responseText || '{}'); } catch { return {}; } })();
        if (xhr.status >= 200 && xhr.status < 300 && payload.ok) {
          progress.value = 100;
          status.textContent = 'PDF guardado correctamente.';
          resolve(payload.file);
          return;
        }
        reject(new Error(payload.message || 'El servidor no pudo guardar el PDF.'));
      });
      xhr.addEventListener('error', () => reject(new Error('No se pudo conectar con el servidor durante la carga del PDF.')));
      xhr.addEventListener('timeout', () => reject(new Error('La carga tardó demasiado y fue cancelada por el navegador o servidor.')));
      xhr.send(data);
    });
  }

  async function deletePdf(path) {
    const response = await fetch('api/delete_policy_pdf.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8', Accept: 'application/json' },
      body: new URLSearchParams({ path }).toString(),
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok || !payload.ok) throw new Error(payload.message || 'No se pudo eliminar el PDF anterior.');
  }

  async function savePolicy(event) {
    event.preventDefault();

    const content = $('#expedient-detail-content');
    const editor = $('.policy-editor', content);
    const module = api();
    const all = module?.getExpedients?.() || [];
    const expedient = all.find((item) => String(item.id) === String(editor?.dataset.expedientId));
    const policyId = editor?.dataset.policyId || '';
    const existing = policies(expedient).find((item) => String(item.id) === String(policyId)) || null;

    if (!expedient || !expedient.client_id) {
      note('error', 'No se pudo identificar el expediente o su cliente. Recarga la página e intenta nuevamente.', { title: 'Expediente no disponible', duration: 0 });
      return;
    }

    const title = $('#policy-title', content).value.trim();
    const insuranceType = activeCatalog('tipos_seguro').find((item) => String(item.id) === String($('#policy-type', content).value));
    const insurer = activeCatalog('aseguradoras').find((item) => String(item.id) === String($('#policy-insurer', content).value));
    const startsAt = toStorage($('#policy-start', content).value);
    const endsAt = toStorage($('#policy-end', content).value);
    const issuedAt = toStorage($('#policy-issued', content).value);
    const file = $('#policy-file', content).files?.[0] || null;

    if (!title || !insuranceType || !insurer || !startsAt || !endsAt) {
      note('warning', 'Completa título, tipo de seguro, aseguradora, inicio y fin de vigencia.', { title: 'Faltan datos obligatorios' });
      return;
    }
    if (endsAt <= startsAt) {
      note('error', 'La fecha y hora de fin de vigencia debe ser posterior al inicio.', { title: 'Vigencia no válida' });
      return;
    }
    if (file && !/\.pdf$/i.test(file.name)) {
      note('error', 'Selecciona un archivo PDF válido para la póliza.', { title: 'Archivo no válido' });
      return;
    }

    const button = $('#policy-save', content);
    button.disabled = true;
    button.textContent = file ? 'Subiendo PDF…' : 'Guardando póliza…';

    let fileInfo = existing?.file || null;
    const code = existing?.code || nextPolicyCode(all);

    try {
      if (file) {
        const uploaded = await uploadPdf(file, code, insuranceType.name, String(expedient.client_document || ''));
        if (existing?.file?.relative_path) {
          try {
            await deletePdf(existing.file.relative_path);
          } catch {
            try { await deletePdf(uploaded.relative_path); } catch {}
            throw new Error('No se pudo reemplazar el PDF de forma segura. El archivo anterior se mantiene.');
          }
        }
        fileInfo = uploaded;
      }

      const currency = activeCatalog('monedas').find((item) => String(item.id) === String($('#policy-currency', content).value));
      const state = activeCatalog('estados_poliza').find((item) => String(item.id) === String($('#policy-status', content).value));

      const item = {
        id: existing?.id || `policy-local-${Date.now()}`,
        code,
        title,
        description: $('#policy-description', content).value.trim(),
        insurance_type_id: insuranceType.id,
        insurance_type_name: insuranceType.name,
        insurer_id: insurer.id,
        insurer_name: insurer.name,
        client_snapshot: {
          id: String(expedient.client_id || ''),
          name: String(expedient.client_name || ''),
          document: String(expedient.client_document || ''),
          entity_type: String(expedient.entity_type || ''),
        },
        insured_amount: $('#policy-amount', content).value.trim(),
        currency_id: currency?.id || '',
        currency_name: currency?.name || '',
        issued_at: issuedAt,
        starts_at: startsAt,
        ends_at: endsAt,
        status: state?.name || 'En emisión',
        observations: $('#policy-observations', content).value.trim(),
        alerts: Array.isArray(existing?.alerts) ? existing.alerts : [],
        payments: Array.isArray(existing?.payments) ? existing.payments : [],
        claims: Array.isArray(existing?.claims) ? existing.claims : [],
        file: fileInfo,
        active: existing?.active !== false,
        deactivation_reason: existing?.deactivation_reason || '',
        deactivated_at: existing?.deactivated_at || '',
        created_at: existing?.created_at || now(),
        updated_at: now(),
      };

      expedient.policies = policies(expedient);
      if (existing) Object.assign(existing, item);
      else expedient.policies.push(item);
      expedient.updated_at = now();

      if (!module.saveExpedients(all)) return;
      module.render();
      showDetail(expedient.id);
      note('success', `${item.code} fue guardada. ${item.file ? 'El PDF principal quedó asociado.' : 'Queda pendiente adjuntar el PDF de póliza.'}`, {
        title: existing ? 'Póliza actualizada' : 'Póliza registrada',
      });
    } catch (error) {
      note('error', error.message || 'No se pudo guardar la póliza.', { title: 'Error al registrar póliza', duration: 0 });
    } finally {
      button.disabled = false;
      button.textContent = 'Guardar póliza';
    }
  }

  function openDeactivate(expedientId, policyId) {
    const content = $('#expedient-detail-content');
    const expedient = getExpedient(expedientId);
    const policy = policies(expedient).find((item) => String(item.id) === String(policyId));
    if (!content || !expedient || !policy) return;

    content.dataset.view = 'policy-deactivate';
    content.innerHTML = `
      <section class="policy-editor policy-editor-danger">
        <div class="policy-editor-heading">
          <div>
            <p class="eyebrow">DESACTIVAR PÓLIZA</p>
            <h3>Conservar antecedente</h3>
            <p>La póliza seguirá visible para gerente y ejecutivo. Se ocultará para la futura vista cliente.</p>
          </div>
          <button id="policy-back" class="policy-secondary-button" type="button">← Volver a ficha</button>
        </div>
        <div class="policy-danger-message">Desactivarás <strong>${esc(policy.code)}</strong> — ${esc(policy.title)}. Esta acción no elimina la póliza ni su PDF.</div>
        <form id="policy-deactivate-form">
          <label class="policy-field"><span>Motivo <b>*</b></span><textarea id="policy-reason" rows="4" maxlength="400" placeholder="Explica el motivo de la desactivación." required></textarea></label>
          <div class="policy-editor-actions">
            <button id="policy-cancel" class="policy-secondary-button" type="button">Cancelar</button>
            <button class="policy-danger-button" type="submit">Desactivar póliza</button>
          </div>
        </form>
      </section>`;

    $('#policy-back', content)?.addEventListener('click', () => showDetail(expedient.id));
    $('#policy-cancel', content)?.addEventListener('click', () => showDetail(expedient.id));
    $('#policy-deactivate-form', content)?.addEventListener('submit', (event) => {
      event.preventDefault();
      const reason = $('#policy-reason', content).value.trim();
      if (!reason) {
        note('warning', 'Indica el motivo de la desactivación antes de continuar.', { title: 'Motivo obligatorio' });
        return;
      }

      policy.active = false;
      policy.deactivation_reason = reason;
      policy.deactivated_at = now();
      policy.updated_at = now();
      expedient.updated_at = now();

      const module = api();
      if (!module?.saveExpedients(module.getExpedients())) return;
      module.render();
      showDetail(expedient.id);
      note('success', `${policy.code} fue desactivada y se conserva como antecedente interno.`, { title: 'Póliza desactivada' });
    });
  }

  function activate(expedientId, policyId) {
    const expedient = getExpedient(expedientId);
    const policy = policies(expedient).find((item) => String(item.id) === String(policyId));
    const module = api();
    if (!expedient || !policy || !module) return;

    policy.active = true;
    policy.deactivation_reason = '';
    policy.deactivated_at = '';
    policy.updated_at = now();
    expedient.updated_at = now();

    if (!module.saveExpedients(module.getExpedients())) return;
    module.render();
    showDetail(expedient.id);
    note('success', `${policy.code} volvió a estar activa.`, { title: 'Póliza reactivada' });
  }

  document.addEventListener('broker:expedient-detail-rendered', (event) => {
    renderSection(event.detail?.expedientId);
  });

  window.addEventListener('load', () => {
    const detail = $('#expedient-detail-content');
    if (detail?.dataset?.expedientId) renderSection(detail.dataset.expedientId);
  });
})();