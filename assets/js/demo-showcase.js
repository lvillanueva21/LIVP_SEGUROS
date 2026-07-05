(() => {
  if (document.body.dataset.role !== 'gerente') return;

  const $ = (selector, root = document) => root.querySelector(selector);
  const now = () => window.BrokerDemo?.limaDateTime?.() || new Date().toISOString().slice(0, 19).replace('T', ' ');
  const key = (name, fallback) => window.BrokerDemo?.keys?.[name] || fallback;

  const entities = {
    companies: [
      { id: 'company-001', name: 'Constructora Norte S.A.C.', ruc: '20123456789', phone: '044 610 101', email: 'contacto@constructoranorte.demo', address: 'Av. Industrial 120, Trujillo', observations: 'Empresa demo activa.', active: true, created_at: '2026-06-20 09:00:00', updated_at: '2026-06-20 09:00:00' },
      { id: 'company-002', name: 'Ingeniería Andina S.A.C.', ruc: '20456789120', phone: '044 610 102', email: 'administracion@ingenieriaandina.demo', address: 'Calle Los Álamos 250, Trujillo', observations: '', active: true, created_at: '2026-06-20 09:10:00', updated_at: '2026-06-20 09:10:00' },
    ],
    consortia: [
      { id: 'consortium-001', name: 'Consorcio Vías del Norte', mode: 'ruc_propio', ruc: '20698765432', participants: [
        { company_id: 'company-001', active: true, role_label: 'Participante', is_tax_operator: false, added_at: '2026-06-21 10:00:00', removed_at: '', removal_reason: '' },
        { company_id: 'company-002', active: true, role_label: 'Participante', is_tax_operator: false, added_at: '2026-06-21 10:00:00', removed_at: '', removal_reason: '' },
      ], observations: 'Consorcio demo con RUC propio.', active: true, created_at: '2026-06-21 10:00:00', updated_at: '2026-06-21 10:00:00' },
    ],
  };

  const expedients = [
    {
      id: 'exp-demo-001', code: 'EXP-2026-0001', contact_id: 'contact-001', contact_name: 'Carla Valladares', contact_mobile: '949 510 201',
      client_id: 'company-001', client_name: 'Constructora Norte S.A.C.', client_document: '20123456789', entity_type: 'Empresa',
      title: 'Protección para proyecto vial', state: 'En seguimiento', opened_at: '2026-06-23', updated_at: '2026-06-27 09:30:00',
      description: 'Expediente demostrativo para consulta de póliza, pagos y reporte inicial de siniestro.', quotes: [],
      policies: [{
        id: 'policy-demo-001', code: 'POL-2026-0001', title: 'Seguro integral de obra vial', description: 'Póliza demostrativa para el proyecto vial.',
        insurance_type_id: 'type-demo-obra', insurance_type_name: 'Todo Riesgo Construcción', insurer_id: 'ins-demo-rimac', insurer_name: 'Rímac Seguros',
        client_snapshot: { id: 'company-001', name: 'Constructora Norte S.A.C.', document: '20123456789', entity_type: 'Empresa' },
        insured_amount: '850000.00', currency_id: 'currency-pen', currency_name: 'PEN', issued_at: '2026-06-20 10:00:00', starts_at: '2026-01-01 00:00:00', ends_at: '2026-12-31 23:59:00', status: 'Vigente', observations: 'Registro demostrativo.',
        file: { relative_path: 'almacen/polizas/demo/2026/06/27/POL-2026-0001_demo.pdf', original_name: 'Poliza_demo_Constructora_Norte.pdf', internal_name: 'POL-2026-0001_demo.pdf', mime: 'application/pdf', size_bytes: 0, uploaded_at: '2026-06-27 09:20:00', uploaded_by: 'Helmut Leiva' },
        payments: [
          { id: 'payment-demo-001', concept: 'Cuota 1 de prima', due_date: '2026-06-15', amount: '12500.00', currency_name: 'PEN', status: 'Pagado', receipt: null, created_at: '2026-06-01 09:00:00', updated_at: '2026-06-15 10:30:00' },
          { id: 'payment-demo-002', concept: 'Cuota 2 de prima', due_date: '2026-07-15', amount: '12500.00', currency_name: 'PEN', status: 'Pendiente', receipt: null, created_at: '2026-06-01 09:00:00', updated_at: '2026-06-01 09:00:00' },
        ],
        claims: [{ id: 'claim-demo-001', code: 'SIN-2026-0001', category: 'Daño material', event_date: '2026-06-24', location: 'Trujillo, La Libertad', description: 'Reporte demostrativo inicial registrado para mostrar el flujo de atención.', contact_phone: '949 579 579', status: 'En revisión', reported_at: '2026-06-24 15:20:00', updated_at: '2026-06-24 15:20:00' }],
        alerts: [], active: true, deactivation_reason: '', deactivated_at: '', created_at: '2026-06-20 10:00:00', updated_at: '2026-06-27 09:20:00',
      }],
      timeline: [
        { id: 'timeline-demo-001', kind: 'expedient', title: 'Expediente creado', detail: 'EXP-2026-0001', at: '2026-06-23 09:10:00' },
        { id: 'timeline-demo-002', kind: 'policy', title: 'Póliza registrada', detail: 'POL-2026-0001', at: '2026-06-20 10:00:00' },
        { id: 'timeline-demo-003', kind: 'payment', title: 'Cuota agregada al cronograma', detail: 'POL-2026-0001 · Cuota 2 de prima · PEN 12500.00', at: '2026-06-01 09:00:00' },
        { id: 'timeline-demo-004', kind: 'claim', title: 'Siniestro reportado por cliente', detail: 'SIN-2026-0001 · POL-2026-0001 · Daño material', at: '2026-06-24 15:20:00' },
      ], documents: [],
    },
    {
      id: 'exp-demo-003', code: 'EXP-2026-0003', contact_id: 'contact-003', contact_name: 'Mariela Castañeda', contact_mobile: '949 510 203',
      client_id: 'consortium-001', client_name: 'Consorcio Vías del Norte', client_document: '20698765432', entity_type: 'Consorcio con RUC propio',
      title: 'Proceso asegurador de consorcio', state: 'En seguimiento', opened_at: '2026-06-26', updated_at: '2026-06-27 11:20:00',
      description: 'Expediente demostrativo de consorcio con una póliza próxima a vencer.', quotes: [],
      policies: [{
        id: 'policy-demo-002', code: 'POL-2026-0002', title: 'Seguro de equipos y maquinaria', description: 'Póliza demostrativa para equipos de obra.',
        insurance_type_id: 'type-demo-equipos', insurance_type_name: 'Equipo y Maquinaria', insurer_id: 'ins-demo-qualitas', insurer_name: 'Qualitas Seguros',
        client_snapshot: { id: 'consortium-001', name: 'Consorcio Vías del Norte', document: '20698765432', entity_type: 'Consorcio con RUC propio' },
        insured_amount: '320000.00', currency_id: 'currency-usd', currency_name: 'USD', issued_at: '2026-06-01 10:00:00', starts_at: '2025-07-05 00:00:00', ends_at: '2026-07-05 23:59:00', status: 'Vigente', observations: 'Registro demostrativo.',
        file: { relative_path: 'almacen/polizas/demo/2026/06/27/POL-2026-0002_demo.pdf', original_name: 'Poliza_demo_Consorcio_Vias_del_Norte.pdf', internal_name: 'POL-2026-0002_demo.pdf', mime: 'application/pdf', size_bytes: 0, uploaded_at: '2026-06-27 09:20:00', uploaded_by: 'Helmut Leiva' },
        payments: [{ id: 'payment-demo-003', concept: 'Cuota final de prima', due_date: '2026-06-30', amount: '5800.00', currency_name: 'USD', status: 'Pagado', receipt: null, created_at: '2026-06-01 09:00:00', updated_at: '2026-06-20 10:30:00' }],
        claims: [], alerts: [], active: true, deactivation_reason: '', deactivated_at: '', created_at: '2026-06-01 10:00:00', updated_at: '2026-06-27 09:20:00',
      }],
      timeline: [
        { id: 'timeline-demo-101', kind: 'expedient', title: 'Expediente creado', detail: 'EXP-2026-0003', at: '2026-06-26 11:20:00' },
        { id: 'timeline-demo-102', kind: 'policy', title: 'Póliza registrada', detail: 'POL-2026-0002', at: '2026-06-01 10:00:00' },
      ], documents: [],
    },
  ];

  const welcome = $('.welcome-banner');
  if (!welcome || $('#demo-showcase-control')) return;

  const control = document.createElement('section');
  control.id = 'demo-showcase-control';
  control.className = 'demo-showcase-control';
  control.innerHTML = `<div><strong>Escenario listo para demostración</strong><p>Restaura dos expedientes con pólizas, pagos, PDF autorizado y un siniestro de muestra. Reemplaza solo la caché de esta maqueta.</p></div><button type="button" class="demo-showcase-button">Restablecer escenario demo</button>`;
  welcome.insertAdjacentElement('afterend', control);

  $('.demo-showcase-button', control)?.addEventListener('click', async (event) => {
    const confirmed = await window.BrokerNotify?.confirm?.(
      'Se reemplazarán los datos locales de Expedientes, Clientes y Cotizaciones por el escenario de demostración. Los archivos ya cargados en Hostinger no se eliminarán.',
      { title: 'Restablecer escenario demo', confirmLabel: 'Restablecer', cancelLabel: 'Cancelar' }
    );
    if (!confirmed) return;

    const button = event.currentTarget;
    button.disabled = true;
    button.textContent = 'Restableciendo…';

    try {
      localStorage.setItem(key('entities', 'broker_seguros_demo_entities_v1'), JSON.stringify(entities));
      localStorage.setItem(key('contacts', 'broker_seguros_demo_contacts_v1'), JSON.stringify([]));
      localStorage.setItem(key('expedients', 'broker_seguros_demo_expedients_v3'), JSON.stringify(expedients));
      localStorage.setItem('broker_seguros_demo_quotes_v1', JSON.stringify([]));
      localStorage.setItem('broker_seguros_demo_prepared_at', now());
      window.BrokerNotify?.success?.('El escenario de demostración quedó preparado. Puedes entrar como Empresa o Consorcio para revisar el portal cliente.', { title: 'Demo lista' });
      window.setTimeout(() => location.reload(), 450);
    } catch {
      window.BrokerNotify?.error?.('No se pudo preparar la caché local. Recarga la página e inténtalo nuevamente.', { title: 'No se pudo preparar la demo', duration: 0 });
      button.disabled = false;
      button.textContent = 'Restablecer escenario demo';
    }
  });
})();