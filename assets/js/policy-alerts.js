(() => {
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
  const esc = (value) => { const n = document.createElement('span'); n.textContent = String(value ?? ''); return n.innerHTML; };
  const note = (type, message, options = {}) => window.BrokerNotify?.[type]?.(message, options);
  const now = () => window.BrokerDemo?.limaDateTime?.() || new Date().toISOString().slice(0, 19).replace('T', ' ');
  const dateLabel = (value, includeTime = false) => window.BrokerDemo?.formatPeruDate?.(value, includeTime) || String(value || 'No registrado');
  const ALERT_TYPES = { before_end: 'Antes del vencimiento', monthly: 'Recurrente mensual' };
  const vars = ['nombre_contacto','nombre_cliente','titulo_poliza','codigo_poliza','tipo_seguro','aseguradora','fecha_inicio','fecha_fin','monto','moneda','dias_para_vencimiento'];
  let lastFocusedMessage = 'alert-whatsapp-template';

  function api() { return window.BrokerExpedients || null; }
  function getExpedient(id) { return api()?.getExpedients?.().find((x) => String(x.id) === String(id)) || null; }
  function getPolicy(expedient, id) { return (expedient?.policies || []).find((x) => String(x.id) === String(id)) || null; }
  function showDetail(id) { api()?.openDetail?.(id); }
  function saveAll(expedient) {
    expedient.updated_at = now();
    const ok = api()?.saveExpedients?.(api()?.getExpedients?.());
    if (ok !== false) api()?.render?.();
    return ok !== false;
  }
  function parseParts(value) {
    const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?$/);
    if (!match) return null;
    return { y:Number(match[1]), m:Number(match[2]), d:Number(match[3]), h:Number(match[4] || 0), min:Number(match[5] || 0), s:Number(match[6] || 0) };
  }
  function pad(value) { return String(value).padStart(2, '0'); }
  function storage(parts) { return `${parts.y}-${pad(parts.m)}-${pad(parts.d)} ${pad(parts.h)}:${pad(parts.min)}:${pad(parts.s || 0)}`; }
  function maxDay(y, m) { return new Date(Date.UTC(y, m, 0)).getUTCDate(); }
  function addDays(value, days) {
    const p = parseParts(value); if (!p) return '';
    const date = new Date(Date.UTC(p.y, p.m - 1, p.d + Number(days || 0), p.h, p.min, p.s));
    return storage({ y:date.getUTCFullYear(), m:date.getUTCMonth() + 1, d:date.getUTCDate(), h:date.getUTCHours(), min:date.getUTCMinutes(), s:date.getUTCSeconds() });
  }
  function currentParts() { return parseParts(now()); }
  function oneMonthBack(parts) {
    let y = parts.y, m = parts.m - 1;
    if (m === 0) { y -= 1; m = 12; }
    return { ...parts, y, m, d: 1, h: 0, min: 0, s: 0 };
  }
  function dayDifference(end) {
    const a = parseParts(now()), b = parseParts(end);
    if (!a || !b) return '';
    const av = Date.UTC(a.y,a.m-1,a.d), bv = Date.UTC(b.y,b.m-1,b.d);
    return String(Math.ceil((bv - av) / 86400000));
  }
  function normalizedAlert(alert = {}) {
    return {
      id: String(alert.id || `policy-alert-${Date.now()}`),
      type: alert.type === 'monthly' ? 'monthly' : 'before_end',
      days_before: Math.max(1, Number(alert.days_before || 30)),
      day_of_month: Math.min(31, Math.max(1, Number(alert.day_of_month || 1))),
      hour: /^([01]\d|2[0-3]):[0-5]\d$/.test(String(alert.hour || '')) ? String(alert.hour) : '09:00',
      start_date: String(alert.start_date || ''), active: alert.active !== false,
      recipient: { mode: ['registered','manual','both'].includes(String(alert.recipient?.mode || '')) ? String(alert.recipient.mode) : 'registered', manual_name: String(alert.recipient?.manual_name || ''), manual_phone: String(alert.recipient?.manual_phone || ''), manual_email: String(alert.recipient?.manual_email || '') },
      whatsapp_template: String(alert.whatsapp_template || ''), email_subject_template: String(alert.email_subject_template || ''), email_body_template: String(alert.email_body_template || ''),
      last_managed_at: String(alert.last_managed_at || ''), created_at: String(alert.created_at || now()), updated_at: String(alert.updated_at || now()),
    };
  }
  function policyAlerts(policy) {
    if (!Array.isArray(policy.alerts)) policy.alerts = [];
    policy.alerts = policy.alerts.map(normalizedAlert);
    return policy.alerts;
  }
  function monthlySchedule(alert, policy) {
    const end = parseParts(policy.ends_at), current = currentParts();
    if (!end || !current) return { latest:'', next:'' };
    const startText = alert.start_date ? `${alert.start_date} 00:00:00` : (policy.starts_at || '');
    const start = parseParts(startText);
    const scheduleFor = (p) => {
      const [h,min] = alert.hour.split(':').map(Number);
      return storage({ y:p.y, m:p.m, d:Math.min(alert.day_of_month, maxDay(p.y,p.m)), h, min, s:0 });
    };
    const thisMonth = scheduleFor(current);
    let latest = thisMonth <= now() ? thisMonth : scheduleFor(oneMonthBack(current));
    let next;
    if (thisMonth > now()) next = thisMonth;
    else {
      let y=current.y,m=current.m+1; if(m===13){y+=1;m=1;}
      next = scheduleFor({y,m,d:1,h:0,min:0,s:0});
    }
    if (start && latest < storage(start)) latest = '';
    if (latest && latest > policy.ends_at) latest = '';
    if (next > policy.ends_at) next = '';
    return { latest, next };
  }
  function alertState(alert, policy) {
    const current = now();
    if (alert.active === false) return { label:'Pausada', tone:'muted', due:false, scheduled:'', next:'' };
    if (!policy.ends_at) return { label:'Sin fecha fin', tone:'danger', due:false, scheduled:'', next:'' };
    if (current > policy.ends_at) return { label:'Finalizada', tone:'muted', due:false, scheduled:'', next:'' };
    let scheduled = '', next = '';
    if (alert.type === 'before_end') scheduled = addDays(policy.ends_at, -Math.max(1, Number(alert.days_before || 1)));
    else ({ latest:scheduled, next } = monthlySchedule(alert, policy));
    if (!scheduled) return { label:'Programada', tone:'primary', due:false, scheduled:'', next };
    const managed = alert.last_managed_at && alert.last_managed_at >= scheduled;
    if (current >= scheduled && !managed) return { label:'Pendiente', tone:'warning', due:true, scheduled, next };
    if (managed) return { label:'Gestionada', tone:'success', due:false, scheduled, next };
    return { label:'Programada', tone:'primary', due:false, scheduled, next };
  }
  function readContacts() {
    try {
      const raw = localStorage.getItem(window.BrokerDemo?.keys?.contacts || 'broker_seguros_demo_contacts_v1');
      return raw ? JSON.parse(raw) : [];
    } catch { return []; }
  }
  function recipients(expedient, alert) {
    const stored = readContacts();
    const c = stored.find((item) => String(item.id) === String(expedient.contact_id)) || {};
    const registered = { name: c.full_name || expedient.contact_name || '', phone: c.mobile || expedient.contact_mobile || '', email: c.email || '' };
    const manual = { name: alert.recipient?.manual_name || '', phone: alert.recipient?.manual_phone || '', email: alert.recipient?.manual_email || '' };
    const mode = alert.recipient?.mode || 'registered';
    const selected = mode === 'manual' ? [manual] : mode === 'both' ? [registered, manual] : [registered];
    const clean = selected.filter((item) => item.name || item.phone || item.email);
    return { primary: clean[0] || registered, names: clean.map((item) => item.name).filter(Boolean), phones: [...new Set(clean.map((item) => item.phone).filter(Boolean))], emails: [...new Set(clean.map((item) => item.email).filter(Boolean))] };
  }
  function values(expedient, policy, alert) {
    const target = recipients(expedient, alert);
    const amount = policy.insured_amount ? `${policy.currency_name ? `${policy.currency_name} ` : ''}${policy.insured_amount}` : 'No registrado';
    return {
      nombre_contacto: target.primary.name || 'cliente', nombre_cliente: policy.client_snapshot?.name || expedient.client_name || 'cliente', titulo_poliza: policy.title || 'Póliza', codigo_poliza: policy.code || '', tipo_seguro: policy.insurance_type_name || 'No registrado', aseguradora: policy.insurer_name || 'No registrada', fecha_inicio: dateLabel(policy.starts_at), fecha_fin: dateLabel(policy.ends_at), monto: amount, moneda: policy.currency_name || 'No registrada', dias_para_vencimiento: dayDifference(policy.ends_at),
    };
  }
  function interpolate(text, map) { return String(text || '').replace(/\{\{([a-z_]+)\}\}/g, (_, key) => Object.prototype.hasOwnProperty.call(map, key) ? String(map[key] ?? '') : `{{${key}}}`); }
  function defaultWhatsApp() { return 'Hola {{nombre_contacto}}, te recordamos que la póliza {{titulo_poliza}} de {{nombre_cliente}} vence el {{fecha_fin}}. ¿Podemos coordinar la renovación?'; }
  function defaultSubject() { return 'Recordatorio de vigencia — {{titulo_poliza}}'; }
  function defaultEmail() { return 'Estimado(a) {{nombre_contacto}},\n\nLe recordamos que la póliza {{titulo_poliza}} ({{codigo_poliza}}) de {{nombre_cliente}} tiene vigencia hasta el {{fecha_fin}}.\n\nAseguradora: {{aseguradora}}\nTipo de seguro: {{tipo_seguro}}\nMonto: {{monto}}\n\nQuedamos atentos para coordinar la renovación.'; }

  function renderControls(expedientId) {
    const content = $('#expedient-detail-content');
    const section = $('#policy-section', content);
    const expedient = getExpedient(expedientId || content?.dataset.expedientId);
    if (!section || !expedient || content.dataset.view !== 'detail') return;
    const cards = $$('.policy-card', section);
    (expedient.policies || []).forEach((policy, index) => {
      const card = cards[index]; if (!card || card.querySelector('.policy-alert-control')) return;
      const alerts = policyAlerts(policy);
      const due = alerts.filter((alert) => alertState(alert, policy).due).length;
      const button = document.createElement('div');
      button.className = 'policy-alert-control';
      button.innerHTML = `<span class="policy-alert-count ${due ? 'is-due' : ''}">${due ? `${due} pendiente${due===1?'':'s'}` : `${alerts.length} alerta${alerts.length===1?'':'s'}`}</span><button class="policy-secondary-button" type="button" data-open-policy-alerts="${esc(policy.id)}">Alertas y mensajes</button>`;
      card.querySelector('.policy-card-actions')?.before(button);
      $('[data-open-policy-alerts]', button)?.addEventListener('click', () => openManager(expedient.id, policy.id));
    });
  }

  function managerMarkup(expedient, policy) {
    const alerts = policyAlerts(policy);
    return `<section class="policy-alert-manager" data-expedient-id="${esc(expedient.id)}" data-policy-id="${esc(policy.id)}"><div class="policy-editor-heading"><div><p class="eyebrow">ALERTAS DE PÓLIZA</p><h3>${esc(policy.code)} · ${esc(policy.title)}</h3><p>Las alertas se calculan al abrir el sistema. Esta maqueta aún no envía mensajes automáticamente.</p></div><button id="policy-alert-back" class="policy-secondary-button" type="button">← Volver a ficha</button></div><div class="policy-alert-info"><strong>Preparado para Twilio y Zoho Mail</strong><span>Por ahora puedes definir mensajes, ver datos reales, copiar contenido o abrir WhatsApp/correo manualmente.</span></div><div class="policy-alert-manager-actions"><button id="add-before-alert" class="policy-primary-button" type="button">+ Aviso antes de vencimiento</button><button id="add-monthly-alert" class="policy-secondary-button" type="button">+ Aviso mensual</button></div><div class="policy-alert-list">${alerts.length ? alerts.map((alert) => alertCard(alert, policy)).join('') : '<p class="policy-empty">Aún no hay alertas configuradas para esta póliza.</p>'}</div></section>`;
  }
  function alertCard(alert, policy) {
    const state = alertState(alert, policy);
    const description = alert.type === 'before_end' ? `Avisar ${alert.days_before} día(s) antes del fin de vigencia.` : `Avisar el día ${alert.day_of_month} a las ${alert.hour} hasta la fecha fin.`;
    return `<article class="policy-alert-card"><div><span class="policy-alert-type">${esc(ALERT_TYPES[alert.type])}</span><h4>${esc(description)}</h4><p>${state.scheduled ? `Fecha evaluada: ${esc(dateLabel(state.scheduled, true))}` : state.next ? `Próxima fecha: ${esc(dateLabel(state.next, true))}` : 'Se calculará según la vigencia de la póliza.'}</p></div><div class="policy-alert-card-right"><span class="policy-alert-status is-${esc(state.tone)}">${esc(state.label)}</span><div class="policy-card-actions"><button class="policy-secondary-button" type="button" data-alert-preview="${esc(alert.id)}">Vista previa</button><button class="policy-secondary-button" type="button" data-alert-edit="${esc(alert.id)}">Editar</button>${state.due ? `<button class="policy-secondary-button" type="button" data-alert-managed="${esc(alert.id)}">Marcar gestionada</button>` : ''}<button class="policy-danger-button" type="button" data-alert-delete="${esc(alert.id)}">Eliminar</button></div></div></article>`;
  }
  function openManager(expedientId, policyId) {
    const content = $('#expedient-detail-content'); const expedient = getExpedient(expedientId); const policy = getPolicy(expedient, policyId);
    if (!content || !expedient || !policy) return;
    content.dataset.view = 'policy-alerts'; content.dataset.expedientId = expedient.id; content.innerHTML = managerMarkup(expedient, policy);
    $('#policy-alert-back', content)?.addEventListener('click', () => showDetail(expedient.id));
    $('#add-before-alert', content)?.addEventListener('click', () => openEditor(expedient.id, policy.id, '', 'before_end'));
    $('#add-monthly-alert', content)?.addEventListener('click', () => openEditor(expedient.id, policy.id, '', 'monthly'));
    $$('[data-alert-edit]', content).forEach((button) => button.addEventListener('click', () => openEditor(expedient.id, policy.id, button.dataset.alertEdit)));
    $$('[data-alert-preview]', content).forEach((button) => button.addEventListener('click', () => preview(expedient.id, policy.id, button.dataset.alertPreview)));
    $$('[data-alert-managed]', content).forEach((button) => button.addEventListener('click', () => markManaged(expedient.id, policy.id, button.dataset.alertManaged)));
    $$('[data-alert-delete]', content).forEach((button) => button.addEventListener('click', async () => {
      const ok = await window.BrokerNotify?.confirm?.('Eliminarás esta configuración de alerta. No se enviará ningún mensaje desde la maqueta.', { title:'Eliminar alerta', confirmLabel:'Eliminar', cancelLabel:'Cancelar' });
      if (!ok) return; const item = policyAlerts(policy).find((alert) => alert.id === button.dataset.alertDelete); if (!item) return;
      policy.alerts = policy.alerts.filter((alert) => alert.id !== item.id); if (!saveAll(expedient)) return; note('success','La alerta fue eliminada.',{title:'Alerta eliminada'}); openManager(expedient.id, policy.id);
    }));
  }
  function variablesMarkup() { return `<div class="policy-variable-bar"><span>Insertar dato:</span>${vars.map((key) => `<button type="button" data-insert-variable="{{${key}}}">{{${key}}}</button>`).join('')}</div>`; }
  function recipientFields(alert, expedient) {
    const r = alert.recipient || {}; const registered = recipients(expedient, normalizedAlert(alert)).primary;
    return `<section class="policy-alert-editor-section"><h4>Destinatario</h4><div class="policy-form-grid"><label><span>Usar datos</span><select id="alert-recipient-mode"><option value="registered" ${r.mode==='registered'?'selected':''}>Contacto de gestión del expediente</option><option value="manual" ${r.mode==='manual'?'selected':''}>Datos manuales</option><option value="both" ${r.mode==='both'?'selected':''}>Ambos / usar el disponible</option></select></label><div class="policy-recipient-reference"><strong>Contacto actual</strong><span>${esc(registered.name || 'No registrado')}</span><small>${esc([registered.phone,registered.email].filter(Boolean).join(' · ') || 'Sin teléfono/correo registrado')}</small></div><label><span>Nombre manual</span><input id="alert-manual-name" maxlength="140" value="${esc(r.manual_name)}" placeholder="Opcional"></label><label><span>Celular manual</span><input id="alert-manual-phone" maxlength="40" value="${esc(r.manual_phone)}" placeholder="Opcional"></label><label class="policy-field-wide"><span>Correo manual</span><input id="alert-manual-email" type="email" maxlength="140" value="${esc(r.manual_email)}" placeholder="Opcional"></label></div></section>`;
  }
  function editorMarkup(expedient, policy, record) {
    const alert = normalizedAlert(record);
    return `<section class="policy-alert-manager" data-expedient-id="${esc(expedient.id)}" data-policy-id="${esc(policy.id)}" data-alert-id="${esc(alert.id)}"><div class="policy-editor-heading"><div><p class="eyebrow">${alert.id && (policyAlerts(policy).some((x)=>x.id===alert.id)) ? 'EDITAR ALERTA' : 'NUEVA ALERTA'}</p><h3>${esc(ALERT_TYPES[alert.type])}</h3><p>Los mensajes se guardan como preparación. No existe envío automático en esta etapa.</p></div><button id="alert-editor-back" class="policy-secondary-button" type="button">← Alertas</button></div><form id="policy-alert-form" novalidate><section class="policy-alert-editor-section"><h4>Programación</h4><div class="policy-form-grid"><label><span>Tipo</span><select id="alert-type"><option value="before_end" ${alert.type==='before_end'?'selected':''}>Faltan X días para vencimiento</option><option value="monthly" ${alert.type==='monthly'?'selected':''}>Cada día del mes hasta fecha fin</option></select></label><div></div><label class="alert-before-field"><span>Días antes del vencimiento <b>*</b></span><input id="alert-days-before" type="number" min="1" max="999" value="${esc(alert.days_before)}"></label><label class="alert-monthly-field"><span>Día del mes <b>*</b></span><input id="alert-day-month" type="number" min="1" max="31" value="${esc(alert.day_of_month)}"></label><label class="alert-monthly-field"><span>Hora Perú <b>*</b></span><input id="alert-hour" type="time" value="${esc(alert.hour)}"></label><label class="alert-monthly-field"><span>Iniciar desde</span><input id="alert-start-date" type="date" value="${esc(alert.start_date)}"><small>Vacío: desde inicio de vigencia.</small></label></div></section>${recipientFields(alert,expedient)}<section class="policy-alert-editor-section"><h4>Mensaje de WhatsApp</h4>${variablesMarkup()}<label class="policy-field"><span>Contenido</span><textarea id="alert-whatsapp-template" rows="5" maxlength="1600" placeholder="Mensaje WhatsApp">${esc(alert.whatsapp_template || defaultWhatsApp())}</textarea></label></section><section class="policy-alert-editor-section"><h4>Correo</h4>${variablesMarkup()}<label class="policy-field"><span>Asunto</span><input id="alert-email-subject" maxlength="200" value="${esc(alert.email_subject_template || defaultSubject())}"></label><label class="policy-field"><span>Cuerpo</span><textarea id="alert-email-body" rows="8" maxlength="4000">${esc(alert.email_body_template || defaultEmail())}</textarea></label></section><div class="policy-editor-actions"><button id="alert-cancel" class="policy-secondary-button" type="button">Cancelar</button><button id="alert-preview-from-editor" class="policy-secondary-button" type="button">Vista previa</button><button class="policy-primary-button" type="submit">Guardar alerta</button></div></form></section>`;
  }
  function openEditor(expedientId, policyId, alertId = '', forceType = '') {
    const content = $('#expedient-detail-content'); const exp = getExpedient(expedientId); const policy = getPolicy(exp, policyId); if (!content || !exp || !policy) return;
    const existing = policyAlerts(policy).find((alert) => String(alert.id) === String(alertId));
    const record = existing ? existing : normalizedAlert({ id:`policy-alert-${Date.now()}`, type:forceType || 'before_end', created_at:now(), updated_at:now(), recipient:{mode:'registered'} });
    content.dataset.view='policy-alert-editor'; content.innerHTML=editorMarkup(exp,policy,record);
    const toggleFields = () => { const monthly = $('#alert-type',content)?.value==='monthly'; $$('.alert-before-field',content).forEach((n)=>n.hidden=monthly); $$('.alert-monthly-field',content).forEach((n)=>n.hidden=!monthly); };
    toggleFields(); $('#alert-type',content)?.addEventListener('change',toggleFields);
    $('#alert-editor-back',content)?.addEventListener('click',()=>openManager(exp.id,policy.id)); $('#alert-cancel',content)?.addEventListener('click',()=>openManager(exp.id,policy.id));
    $$('textarea,input',content).forEach((input)=>input.addEventListener('focus',()=>{ if(input.id.includes('template') || input.id.includes('email')) lastFocusedMessage=input.id; }));
    $$('[data-insert-variable]',content).forEach((button)=>button.addEventListener('click',()=>{ const field=$(`#${lastFocusedMessage}`,content)||$('#alert-whatsapp-template',content); const value=button.dataset.insertVariable||''; const start=field.selectionStart??field.value.length,end=field.selectionEnd??field.value.length; field.value=`${field.value.slice(0,start)}${value}${field.value.slice(end)}`; field.focus(); field.setSelectionRange(start+value.length,start+value.length); }));
    $('#policy-alert-form',content)?.addEventListener('submit',(event)=>saveEditor(event,exp,policy,record));
    $('#alert-preview-from-editor',content)?.addEventListener('click',()=>{ const temp=readEditor(content,record); if(!validate(temp,policy,exp,false)) return; preview(exp.id,policy.id,'',temp); });
  }
  function readEditor(content, record) {
    const alert=normalizedAlert(record); alert.type=$('#alert-type',content)?.value==='monthly'?'monthly':'before_end'; alert.days_before=Math.max(1,Number($('#alert-days-before',content)?.value||1)); alert.day_of_month=Math.min(31,Math.max(1,Number($('#alert-day-month',content)?.value||1))); alert.hour=$('#alert-hour',content)?.value||'09:00'; alert.start_date=$('#alert-start-date',content)?.value||''; alert.recipient={mode:$('#alert-recipient-mode',content)?.value||'registered',manual_name:$('#alert-manual-name',content)?.value.trim()||'',manual_phone:$('#alert-manual-phone',content)?.value.trim()||'',manual_email:$('#alert-manual-email',content)?.value.trim()||''}; alert.whatsapp_template=$('#alert-whatsapp-template',content)?.value.trim()||''; alert.email_subject_template=$('#alert-email-subject',content)?.value.trim()||''; alert.email_body_template=$('#alert-email-body',content)?.value.trim()||''; alert.updated_at=now(); return alert;
  }
  function validate(alert, policy, exp, withNotice=true) {
    const warning=(message,title)=>{if(withNotice)note('warning',message,{title});};
    if(!policy.ends_at){warning('La póliza debe tener fecha de fin antes de configurar alertas.','Fecha fin pendiente');return false;}
    if(alert.type==='before_end' && (!Number.isFinite(alert.days_before)||alert.days_before<1)){warning('Indica cuántos días antes se debe avisar.','Programación incompleta');return false;}
    if(alert.type==='monthly' && (alert.day_of_month<1||alert.day_of_month>31||!/^([01]\d|2[0-3]):[0-5]\d$/.test(alert.hour))){warning('Completa día del mes y hora Perú.','Programación incompleta');return false;}
    if(alert.recipient.mode==='manual' && !alert.recipient.manual_name){warning('Indica al menos el nombre del destinatario manual.','Destinatario incompleto');return false;}
    if(!alert.whatsapp_template && !alert.email_body_template){warning('Escribe al menos un mensaje de WhatsApp o correo.','Mensaje pendiente');return false;}
    return true;
  }
  function saveEditor(event, exp, policy, record) {
    event.preventDefault(); const content=$('#expedient-detail-content'); const alert=readEditor(content,record); if(!validate(alert,policy,exp))return;
    const list=policyAlerts(policy); const i=list.findIndex((item)=>item.id===alert.id); if(i>=0)list[i]=alert;else list.push(alert); policy.alerts=list; if(!saveAll(exp))return; note('success','La alerta quedó guardada. Solo aparecerá pendiente cuando llegue su programación.',{title:'Alerta configurada'});openManager(exp.id,policy.id);
  }
  function markManaged(expId, policyId, alertId) { const exp=getExpedient(expId), policy=getPolicy(exp,policyId), alert=policyAlerts(policy).find((x)=>x.id===alertId); if(!exp||!policy||!alert)return; alert.last_managed_at=now();alert.updated_at=now();if(!saveAll(exp))return;note('success','Se registró la gestión manual de esta alerta para la programación actual.',{title:'Gestión registrada'});openManager(exp.id,policy.id); }
  function copy(text,label){ if(navigator.clipboard?.writeText){navigator.clipboard.writeText(text).then(()=>note('success',`${label} copiado al portapapeles.`,{title:'Contenido copiado'})).catch(()=>note('warning','No se pudo copiar automáticamente. Selecciona el texto manualmente.',{title:'Copia no disponible'}));}else note('warning','Tu navegador no permite copiar automáticamente. Selecciona el texto manualmente.',{title:'Copia no disponible'}); }
  function preview(expId, policyId, alertId='', draft=null) {
    const content=$('#expedient-detail-content'); const exp=getExpedient(expId); const policy=getPolicy(exp,policyId); const alert=draft||policyAlerts(policy).find((x)=>x.id===alertId); if(!content||!exp||!policy||!alert)return;
    const map=values(exp,policy,alert), target=recipients(exp,alert), whatsapp=interpolate(alert.whatsapp_template,map), subject=interpolate(alert.email_subject_template,map), email=interpolate(alert.email_body_template,map), phone=target.phones[0]||'', recipientName=target.names.join(' · ')||'Destinatario pendiente', emailTo=target.emails.join(', ')||'Correo pendiente';
    content.dataset.view='policy-alert-preview'; content.innerHTML=`<section class="policy-alert-preview"><div class="policy-editor-heading"><div><p class="eyebrow">VISTA PREVIA DE MENSAJES</p><h3>${esc(policy.code)} · ${esc(policy.title)}</h3><p>Los datos se reemplazan con la información real actual de la póliza.</p></div><button id="alert-preview-back" class="policy-secondary-button" type="button">← Volver</button></div><div class="policy-alert-info"><strong>Modo preparación</strong><span>La maqueta no envía mensajes. Twilio y Zoho Mail podrán conectarse después a esta misma configuración.</span></div><div class="policy-preview-grid"><article class="whatsapp-preview"><div class="whatsapp-preview-top"><span>◉</span><div><strong>${esc(recipientName)}</strong><small>${esc(phone || 'Sin celular disponible')}</small></div></div><div class="whatsapp-preview-body"><div class="whatsapp-bubble">${esc(whatsapp || 'Sin mensaje de WhatsApp configurado.').replace(/\n/g,'<br>')}<small>${esc(dateLabel(now(),true))}</small></div></div><div class="policy-card-actions"><button id="copy-whatsapp" class="policy-secondary-button" type="button">Copiar WhatsApp</button><button id="open-whatsapp" class="policy-primary-button" type="button" ${phone?'':'disabled'}>Abrir WhatsApp</button></div></article><article class="email-preview"><div class="email-preview-top"><span>✉</span><div><strong>Correo de renovación</strong><small>Vista de preparación</small></div></div><dl><div><dt>Para</dt><dd>${esc(emailTo)}</dd></div><div><dt>Asunto</dt><dd>${esc(subject || 'Sin asunto')}</dd></div></dl><div class="email-preview-body">${esc(email || 'Sin mensaje de correo configurado.').replace(/\n/g,'<br>')}</div><div class="policy-card-actions"><button id="copy-email" class="policy-secondary-button" type="button">Copiar correo</button><button id="open-email" class="policy-primary-button" type="button" ${target.emails.length?'':'disabled'}>Abrir correo</button></div></article></div></section>`;
    $('#alert-preview-back',content)?.addEventListener('click',()=>openManager(exp.id,policy.id)); $('#copy-whatsapp',content)?.addEventListener('click',()=>copy(whatsapp,'Mensaje de WhatsApp')); $('#copy-email',content)?.addEventListener('click',()=>copy(`Para: ${emailTo}\nAsunto: ${subject}\n\n${email}`,'Correo'));
    $('#open-whatsapp',content)?.addEventListener('click',()=>{const digits=phone.replace(/\D/g,'');if(digits)window.open(`https://wa.me/${digits}?text=${encodeURIComponent(whatsapp)}`,'_blank','noopener');}); $('#open-email',content)?.addEventListener('click',()=>{if(target.emails[0])window.location.href=`mailto:${encodeURIComponent(target.emails[0])}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(email)}`;});
  }
  function dueItems(expedients) { const list=[]; (expedients||[]).forEach((exp)=>{(exp.policies||[]).forEach((policy)=>{if(policy.active===false)return;policyAlerts(policy).forEach((alert)=>{const state=alertState(alert,policy);if(state.due)list.push({expedient:exp,policy,alert,state});});});});return list; }
  window.BrokerPolicyAlerts={dueItems,alertState,policyAlerts,interpolate,values,recipients};
  document.addEventListener('broker:expedient-detail-rendered',(event)=>window.setTimeout(()=>renderControls(event.detail?.expedientId),0));
  window.addEventListener('load',()=>window.setTimeout(()=>renderControls($('#expedient-detail-content')?.dataset?.expedientId),100));
})();