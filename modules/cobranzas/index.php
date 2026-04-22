<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';

demo_require_roles(['gerente', 'superadmin']);

$installments = demo_store('installments', []);
$policies = demo_store('policies', []);
$clients = demo_store('clients', []);
$users = demo_store('users', []);
$payments = demo_store('payments', []);

$executives = array_values(array_filter($users, fn($u) => ($u['role'] ?? '') === 'ejecutivo'));

$policyMap = [];
foreach ($policies as $policy) {
    $policyMap[$policy['id']] = $policy;
}

$clientMap = [];
foreach ($clients as $client) {
    $clientMap[$client['id']] = $client;
}

$executiveMap = [];
foreach ($executives as $exec) {
    $executiveMap[$exec['id']] = $exec;
}

$today = date('Y-m-d');
$currentMonth = date('Y-m');

$pendingCount = count(array_filter($installments, fn($i) => strtolower((string)($i['status'] ?? '')) === 'pendiente'));
$overdueCount = count(array_filter($installments, fn($i) => strtolower((string)($i['status'] ?? '')) === 'vencida'));
$paymentsThisMonth = count(array_filter($payments, fn($p) => !empty($p['date']) && str_starts_with((string)$p['date'], $currentMonth)));
$amountToCollect = array_reduce($installments, function ($carry, $item) {
    $status = strtolower((string)($item['status'] ?? ''));
    if (in_array($status, ['pendiente', 'vencida', 'en revisión'], true)) {
        return $carry + (float)($item['amount'] ?? 0);
    }
    return $carry;
}, 0.0);

$installmentsRows = [];
foreach ($installments as $installment) {
    $policy = $policyMap[$installment['policy_id']] ?? null;
    $client = $policy ? ($clientMap[$policy['client_id']] ?? null) : null;
    $executive = $policy ? ($executiveMap[$policy['assigned_executive_user_id']] ?? null) : null;

    $relatedPayment = null;
    foreach ($payments as $payment) {
        if (($payment['installment_id'] ?? '') === ($installment['id'] ?? '')) {
            $relatedPayment = $payment;
        }
    }

    $installmentsRows[] = [
        'id' => $installment['id'],
        'policy_id' => $installment['policy_id'],
        'policy_number' => $policy['policy_number'] ?? '—',
        'client_name' => $client['name'] ?? 'Cliente no encontrado',
        'executive_name' => $executive['full_name'] ?? 'Sin asignar',
        'executive_id' => $policy['assigned_executive_user_id'] ?? '',
        'number' => $installment['number'] ?? '—',
        'due_date' => $installment['due_date'] ?? '',
        'amount' => (float)($installment['amount'] ?? 0),
        'status' => $installment['status'] ?? 'pendiente',
        'receipt_uploaded' => (bool)($installment['receipt_uploaded'] ?? false),
        'receipt_name' => $installment['receipt_name'] ?? '',
        'payment_method' => $relatedPayment['method'] ?? '',
        'payment_date' => $relatedPayment['date'] ?? '',
        'payment_status' => $relatedPayment['status'] ?? '',
        'payment_note' => $relatedPayment['note'] ?? '',
    ];
}

usort($installmentsRows, fn($a, $b) => strtotime((string)($a['due_date'] ?? '')) <=> strtotime((string)($b['due_date'] ?? '')));

ob_start();
?>
<style>
    .collections-toolbar {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        margin-bottom: 1rem;
    }

    .collections-controls {
        display: grid;
        gap: 1rem;
        grid-template-columns: 1.2fr .8fr .8fr .8fr .8fr auto;
        align-items: end;
        margin-bottom: 1rem;
    }

    .collections-actions {
        display: flex;
        flex-wrap: wrap;
        gap: .45rem;
    }

    .collections-action-btn {
        min-height: 34px;
        padding: .45rem .7rem;
        border-radius: 999px;
        border: 1px solid rgba(100, 116, 139, .16);
        background: #fff;
        color: var(--text);
        font-size: .82rem;
        font-weight: 700;
        transition: transform var(--transition), border-color var(--transition), background var(--transition);
    }

    .collections-action-btn:hover {
        transform: translateY(-1px);
        border-color: rgba(79, 70, 229, .18);
        background: #f8fbff;
    }

    .collections-action-btn--primary {
        background: rgba(79, 70, 229, .08);
        color: var(--primary);
        border-color: rgba(79, 70, 229, .12);
    }

    .collections-inline-note {
        padding: .85rem 1rem;
        border-radius: 16px;
        border: 1px dashed rgba(100, 116, 139, .28);
        background: linear-gradient(180deg, #fbfdff 0%, #f7faff 100%);
        margin-bottom: 1rem;
    }

    .collections-inline-note strong {
        display: block;
        margin-bottom: .2rem;
    }

    .collections-empty {
        padding: 1.2rem;
        text-align: center;
        color: var(--text-soft);
    }

    .collections-form-note {
        margin: 0 0 1rem;
        color: var(--text-soft);
        line-height: 1.5;
    }

    .receipt-chip {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        min-height: 30px;
        padding: .35rem .7rem;
        border-radius: 999px;
        background: rgba(14, 165, 164, .08);
        color: var(--secondary);
        font-size: .8rem;
        font-weight: 700;
    }

    .receipt-chip--missing {
        background: rgba(245, 158, 11, .12);
        color: #b45309;
    }

    .detail-grid {
        display: grid;
        gap: .9rem;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .detail-item {
        padding: .9rem 1rem;
        border-radius: 16px;
        border: 1px solid rgba(219, 227, 239, .85);
        background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
    }

    .detail-item strong {
        display: block;
        margin-bottom: .35rem;
        color: var(--text-soft);
        font-size: .78rem;
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .detail-item span {
        display: block;
        line-height: 1.5;
        overflow-wrap: anywhere;
    }

    @media (max-width: 1220px) {
        .collections-toolbar {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .collections-controls {
            grid-template-columns: 1fr 1fr;
        }

        .collections-controls .btn {
            width: 100%;
        }
    }

    @media (max-width: 760px) {
        .collections-toolbar,
        .collections-controls,
        .detail-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="grid" style="gap: 1rem;">
    <section class="collections-toolbar">
        <article class="card kpi-card">
            <p class="kpi-card__label">Cuotas pendientes</p>
            <h3 class="kpi-card__value" id="kpi-pending"><?= demo_e((string)$pendingCount) ?></h3>
            <p class="kpi-card__meta">Pendientes de pago y seguimiento inmediato.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Cuotas vencidas</p>
            <h3 class="kpi-card__value" id="kpi-overdue"><?= demo_e((string)$overdueCount) ?></h3>
            <p class="kpi-card__meta">Pagos fuera de fecha que requieren priorización.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Pagos del mes</p>
            <h3 class="kpi-card__value" id="kpi-payments-month"><?= demo_e((string)$paymentsThisMonth) ?></h3>
            <p class="kpi-card__meta">Movimientos confirmados dentro del mes actual.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Monto por cobrar</p>
            <h3 class="kpi-card__value" id="kpi-amount-collect"><?= demo_e(demo_money($amountToCollect)) ?></h3>
            <p class="kpi-card__meta">Suma demo de cuotas no regularizadas.</p>
        </article>
    </section>

    <section class="card">
        <div class="card__header">
            <div>
                <h2 class="card__title">Cobranzas y vencimientos</h2>
                <p class="card__subtitle">Control de cuotas, comprobantes y registro de pagos dentro del entorno demo.</p>
            </div>

            <button type="button" class="btn btn-primary" id="btn-register-payment-global">Registrar pago</button>
        </div>

        <div class="collections-inline-note">
            <strong>Flujo demo activo</strong>
            <span class="muted">Puedes registrar pagos, adjuntar comprobantes simulados, revisar el detalle de cada cuota y cambiar su estado sin recargar la página.</span>
        </div>

        <div class="collections-controls">
            <div>
                <label class="form-label" for="filter-search">Buscar</label>
                <input class="input" id="filter-search" type="text" placeholder="Cliente, póliza o ejecutivo">
            </div>

            <div>
                <label class="form-label" for="filter-status">Estado</label>
                <select class="select" id="filter-status">
                    <option value="">Todos</option>
                    <option value="pendiente">Pendiente</option>
                    <option value="pagada">Pagada</option>
                    <option value="vencida">Vencida</option>
                    <option value="en revisión">En revisión</option>
                </select>
            </div>

            <div>
                <label class="form-label" for="filter-from">Desde</label>
                <input class="input" id="filter-from" type="date">
            </div>

            <div>
                <label class="form-label" for="filter-to">Hasta</label>
                <input class="input" id="filter-to" type="date">
            </div>

            <div>
                <label class="form-label" for="filter-executive">Ejecutivo</label>
                <select class="select" id="filter-executive">
                    <option value="">Todos</option>
                    <?php foreach ($executives as $exec): ?>
                        <option value="<?= demo_e($exec['id']) ?>"><?= demo_e($exec['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <button type="button" class="btn btn-ghost" id="btn-reset-filters">Limpiar filtros</button>
            </div>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Póliza</th>
                        <th>N° cuota</th>
                        <th>Vencimiento</th>
                        <th>Monto</th>
                        <th>Estado</th>
                        <th>Comprobante</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="collections-table-body"></tbody>
            </table>
        </div>

        <div id="collections-empty-state" class="collections-empty" hidden>
            No hay cuotas que coincidan con los filtros seleccionados.
        </div>
    </section>
</div>

<div class="modal" id="payment-modal" hidden>
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__dialog">
        <div class="modal__header">
            <h3 id="payment-modal-title">Registrar pago</h3>
            <button type="button" class="icon-btn" data-modal-close aria-label="Cerrar">✕</button>
        </div>
        <div class="modal__body">
            <p class="collections-form-note">Confirma el pago de una cuota. El movimiento se guardará de forma simulada dentro de la sesión actual.</p>

            <form id="payment-form" class="form-grid form-grid--3">
                <input type="hidden" name="action" value="register_payment">
                <input type="hidden" name="installment_id" id="payment-installment-id" value="">

                <div style="grid-column: span 2;">
                    <label class="form-label" for="payment-installment-select">Cuota</label>
                    <select class="select" id="payment-installment-select" name="installment_select">
                        <option value="">Seleccionar</option>
                    </select>
                </div>

                <div>
                    <label class="form-label" for="payment-date">Fecha de pago</label>
                    <input class="input" type="date" id="payment-date" name="payment_date" value="<?= demo_e($today) ?>">
                </div>

                <div>
                    <label class="form-label" for="payment-amount">Monto pagado</label>
                    <input class="input" type="number" step="0.01" min="0" id="payment-amount" name="amount" placeholder="0.00">
                </div>

                <div>
                    <label class="form-label" for="payment-method">Método</label>
                    <select class="select" id="payment-method" name="method">
                        <option value="Transferencia">Transferencia</option>
                        <option value="Yape">Yape</option>
                        <option value="Depósito">Depósito</option>
                        <option value="Efectivo">Efectivo</option>
                    </select>
                </div>

                <div>
                    <label class="form-label" for="payment-status">Estado resultante</label>
                    <select class="select" id="payment-status" name="status">
                        <option value="pagada">Pagada</option>
                        <option value="en revisión">En revisión</option>
                    </select>
                </div>

                <div style="grid-column: 1 / -1;">
                    <label class="form-label" for="payment-note">Observación</label>
                    <textarea class="textarea" id="payment-note" name="note" placeholder="Comentario breve del registro demo"></textarea>
                </div>
            </form>
        </div>
        <div class="modal__footer">
            <button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button>
            <button type="button" class="btn btn-primary" id="payment-submit">Guardar pago</button>
        </div>
    </div>
</div>

<div class="modal" id="receipt-modal" hidden>
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__dialog modal__dialog--sm">
        <div class="modal__header">
            <h3 id="receipt-modal-title">Comprobante</h3>
            <button type="button" class="icon-btn" data-modal-close aria-label="Cerrar">✕</button>
        </div>
        <div class="modal__body">
            <p class="collections-form-note">Adjunta o revisa el comprobante simulado de la cuota seleccionada.</p>

            <form id="receipt-form" class="form-grid">
                <input type="hidden" name="action" value="attach_receipt">
                <input type="hidden" name="installment_id" id="receipt-installment-id" value="">

                <div>
                    <label class="form-label" for="receipt-name">Nombre del comprobante</label>
                    <input class="input" type="text" id="receipt-name" name="receipt_name" placeholder="voucher_transferencia_abril.jpg">
                </div>

                <div>
                    <label class="form-label" for="receipt-note">Detalle</label>
                    <textarea class="textarea" id="receipt-note" name="receipt_note" placeholder="Breve referencia del archivo o validación demo"></textarea>
                </div>
            </form>

            <div class="panel mt-2" id="receipt-preview-panel" hidden>
                <strong>Último comprobante registrado</strong>
                <p class="mt-1" id="receipt-preview-name">—</p>
                <p class="muted mt-1" id="receipt-preview-note">—</p>
            </div>
        </div>
        <div class="modal__footer">
            <button type="button" class="btn btn-ghost" data-modal-close>Cerrar</button>
            <button type="button" class="btn btn-primary" id="receipt-submit">Guardar comprobante</button>
        </div>
    </div>
</div>

<div class="modal" id="installment-detail-modal" hidden>
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__dialog">
        <div class="modal__header">
            <h3>Detalle de cuota</h3>
            <button type="button" class="icon-btn" data-modal-close aria-label="Cerrar">✕</button>
        </div>
        <div class="modal__body">
            <div class="detail-grid" id="detail-grid"></div>

            <div class="panel mt-2">
                <form id="detail-status-form" class="form-grid form-grid--2">
                    <input type="hidden" name="action" value="change_status">
                    <input type="hidden" name="installment_id" id="detail-installment-id" value="">

                    <div>
                        <label class="form-label" for="detail-status">Cambiar estado</label>
                        <select class="select" id="detail-status" name="status">
                            <option value="pendiente">Pendiente</option>
                            <option value="pagada">Pagada</option>
                            <option value="vencida">Vencida</option>
                            <option value="en revisión">En revisión</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label" for="detail-status-note">Observación</label>
                        <input class="input" type="text" id="detail-status-note" name="note" placeholder="Motivo del cambio demo">
                    </div>
                </form>
            </div>
        </div>
        <div class="modal__footer">
            <button type="button" class="btn btn-ghost" data-modal-close>Cerrar</button>
            <button type="button" class="btn btn-primary" id="detail-status-submit">Actualizar estado</button>
        </div>
    </div>
</div>

<script>
    (() => {
        let installmentsState = <?= json_encode(array_values($installmentsRows), JSON_UNESCAPED_UNICODE) ?>;
        let paymentsState = <?= json_encode(array_values($payments), JSON_UNESCAPED_UNICODE) ?>;

        const endpoint = <?= json_encode(demo_url('ajax/cobranzas.php'), JSON_UNESCAPED_UNICODE) ?>;

        const tableBody = document.getElementById('collections-table-body');
        const emptyState = document.getElementById('collections-empty-state');

        const filterSearch = document.getElementById('filter-search');
        const filterStatus = document.getElementById('filter-status');
        const filterFrom = document.getElementById('filter-from');
        const filterTo = document.getElementById('filter-to');
        const filterExecutive = document.getElementById('filter-executive');
        const btnResetFilters = document.getElementById('btn-reset-filters');

        const btnRegisterPaymentGlobal = document.getElementById('btn-register-payment-global');
        const paymentForm = document.getElementById('payment-form');
        const paymentInstallmentId = document.getElementById('payment-installment-id');
        const paymentInstallmentSelect = document.getElementById('payment-installment-select');
        const paymentAmountInput = document.getElementById('payment-amount');
        const paymentModalTitle = document.getElementById('payment-modal-title');
        const paymentSubmit = document.getElementById('payment-submit');

        const receiptForm = document.getElementById('receipt-form');
        const receiptInstallmentId = document.getElementById('receipt-installment-id');
        const receiptName = document.getElementById('receipt-name');
        const receiptNote = document.getElementById('receipt-note');
        const receiptPreviewPanel = document.getElementById('receipt-preview-panel');
        const receiptPreviewName = document.getElementById('receipt-preview-name');
        const receiptPreviewNote = document.getElementById('receipt-preview-note');
        const receiptSubmit = document.getElementById('receipt-submit');
        const receiptModalTitle = document.getElementById('receipt-modal-title');

        const detailGrid = document.getElementById('detail-grid');
        const detailInstallmentId = document.getElementById('detail-installment-id');
        const detailStatus = document.getElementById('detail-status');
        const detailStatusNote = document.getElementById('detail-status-note');
        const detailStatusSubmit = document.getElementById('detail-status-submit');

        const kpiPending = document.getElementById('kpi-pending');
        const kpiOverdue = document.getElementById('kpi-overdue');
        const kpiPaymentsMonth = document.getElementById('kpi-payments-month');
        const kpiAmountCollect = document.getElementById('kpi-amount-collect');

        const today = <?= json_encode($today, JSON_UNESCAPED_UNICODE) ?>;

        const escapeHtml = (value) => String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');

        const formatDate = (value) => {
            if (!value) return '—';
            const date = new Date(value + 'T00:00:00');
            if (Number.isNaN(date.getTime())) return value;
            return date.toLocaleDateString('es-PE');
        };

        const formatMoney = (value, currency = 'S/') => {
            const numeric = Number(value || 0);
            return `${currency} ${numeric.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        };

        const statusTone = (status) => ({
            'pendiente': 'warning',
            'pagada': 'success',
            'vencida': 'danger',
            'en revisión': 'info'
        }[status] || 'neutral');

        const renderKpis = () => {
            const pending = installmentsState.filter(i => i.status === 'pendiente').length;
            const overdue = installmentsState.filter(i => i.status === 'vencida').length;
            const month = new Date().toISOString().slice(0, 7);
            const paymentsMonth = paymentsState.filter(p => (p.date || '').startsWith(month)).length;
            const amountCollect = installmentsState.reduce((sum, item) => {
                return ['pendiente', 'vencida', 'en revisión'].includes(item.status) ? sum + Number(item.amount || 0) : sum;
            }, 0);

            kpiPending.textContent = String(pending);
            kpiOverdue.textContent = String(overdue);
            kpiPaymentsMonth.textContent = String(paymentsMonth);
            kpiAmountCollect.textContent = formatMoney(amountCollect);
        };

        const getFilteredRows = () => {
            const term = filterSearch.value.trim().toLowerCase();
            const status = filterStatus.value;
            const from = filterFrom.value;
            const to = filterTo.value;
            const executive = filterExecutive.value;

            return installmentsState.filter((item) => {
                const haystack = [
                    item.client_name,
                    item.policy_number,
                    item.executive_name
                ].join(' ').toLowerCase();

                const dateOkFrom = !from || (item.due_date && item.due_date >= from);
                const dateOkTo = !to || (item.due_date && item.due_date <= to);

                return (!term || haystack.includes(term))
                    && (!status || item.status === status)
                    && (!executive || item.executive_id === executive)
                    && dateOkFrom
                    && dateOkTo;
            });
        };

        const renderPaymentOptions = () => {
            const previous = paymentInstallmentSelect.value;
            paymentInstallmentSelect.innerHTML = '<option value="">Seleccionar</option>';

            installmentsState
                .slice()
                .sort((a, b) => new Date(a.due_date) - new Date(b.due_date))
                .forEach((item) => {
                    const option = document.createElement('option');
                    option.value = item.id;
                    option.textContent = `${item.policy_number} · ${item.client_name} · Cuota #${item.number}`;
                    paymentInstallmentSelect.appendChild(option);
                });

            if (previous) {
                paymentInstallmentSelect.value = previous;
            }
        };

        const renderTable = () => {
            const rows = getFilteredRows();
            tableBody.innerHTML = '';

            if (!rows.length) {
                emptyState.hidden = false;
                return;
            }

            emptyState.hidden = true;

            rows
                .slice()
                .sort((a, b) => new Date(a.due_date) - new Date(b.due_date))
                .forEach((item) => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${escapeHtml(item.client_name || '—')}</td>
                        <td>${escapeHtml(item.policy_number || '—')}</td>
                        <td>#${escapeHtml(String(item.number || '—'))}</td>
                        <td>${escapeHtml(formatDate(item.due_date))}</td>
                        <td>${escapeHtml(formatMoney(item.amount))}</td>
                        <td><span class="badge badge-${statusTone(item.status)}">${escapeHtml((item.status || '—').charAt(0).toUpperCase() + (item.status || '—').slice(1))}</span></td>
                        <td>
                            ${item.receipt_uploaded
                                ? `<span class="receipt-chip">${escapeHtml(item.receipt_name || 'Comprobante registrado')}</span>`
                                : `<span class="receipt-chip receipt-chip--missing">Sin comprobante</span>`
                            }
                        </td>
                        <td>
                            <div class="collections-actions">
                                <button type="button" class="collections-action-btn collections-action-btn--primary" data-action="pay" data-id="${escapeHtml(item.id)}">Registrar pago</button>
                                <button type="button" class="collections-action-btn" data-action="receipt" data-id="${escapeHtml(item.id)}">Ver comprobante</button>
                                <button type="button" class="collections-action-btn" data-action="detail" data-id="${escapeHtml(item.id)}">Detalle</button>
                            </div>
                        </td>
                    `;
                    tableBody.appendChild(tr);
                });
        };

        const findInstallment = (id) => installmentsState.find(item => item.id === id) || null;

        const upsertInstallment = (installment) => {
            const index = installmentsState.findIndex(item => item.id === installment.id);
            if (index >= 0) {
                installmentsState[index] = installment;
            } else {
                installmentsState.unshift(installment);
            }
        };

        const openPaymentModal = (installment = null) => {
            paymentForm.reset();
            paymentInstallmentId.value = installment?.id || '';
            paymentInstallmentSelect.value = installment?.id || '';
            paymentModalTitle.textContent = installment ? `Registrar pago · ${installment.policy_number} · Cuota #${installment.number}` : 'Registrar pago';
            paymentAmountInput.value = installment ? installment.amount : '';
            document.getElementById('payment-date').value = today;
            DemoApp.openModal('payment-modal');
        };

        const openReceiptModal = (installment) => {
            receiptForm.reset();
            receiptInstallmentId.value = installment.id;
            receiptModalTitle.textContent = `Comprobante · ${installment.policy_number} · Cuota #${installment.number}`;
            receiptName.value = installment.receipt_name || '';
            receiptNote.value = installment.payment_note || '';

            if (installment.receipt_uploaded) {
                receiptPreviewPanel.hidden = false;
                receiptPreviewName.textContent = installment.receipt_name || 'Comprobante registrado';
                receiptPreviewNote.textContent = installment.payment_note || 'Sin detalle adicional.';
            } else {
                receiptPreviewPanel.hidden = true;
                receiptPreviewName.textContent = '—';
                receiptPreviewNote.textContent = '—';
            }

            DemoApp.openModal('receipt-modal');
        };

        const openDetailModal = (installment) => {
            detailInstallmentId.value = installment.id;
            detailStatus.value = installment.status || 'pendiente';
            detailStatusNote.value = '';

            detailGrid.innerHTML = `
                <div class="detail-item">
                    <strong>Cliente</strong>
                    <span>${escapeHtml(installment.client_name || '—')}</span>
                </div>
                <div class="detail-item">
                    <strong>Póliza</strong>
                    <span>${escapeHtml(installment.policy_number || '—')}</span>
                </div>
                <div class="detail-item">
                    <strong>Número de cuota</strong>
                    <span>#${escapeHtml(String(installment.number || '—'))}</span>
                </div>
                <div class="detail-item">
                    <strong>Vencimiento</strong>
                    <span>${escapeHtml(formatDate(installment.due_date))}</span>
                </div>
                <div class="detail-item">
                    <strong>Monto</strong>
                    <span>${escapeHtml(formatMoney(installment.amount))}</span>
                </div>
                <div class="detail-item">
                    <strong>Estado actual</strong>
                    <span>${escapeHtml((installment.status || '—').charAt(0).toUpperCase() + (installment.status || '—').slice(1))}</span>
                </div>
                <div class="detail-item">
                    <strong>Ejecutivo</strong>
                    <span>${escapeHtml(installment.executive_name || 'Sin asignar')}</span>
                </div>
                <div class="detail-item">
                    <strong>Comprobante</strong>
                    <span>${escapeHtml(installment.receipt_uploaded ? (installment.receipt_name || 'Registrado') : 'No adjuntado')}</span>
                </div>
            `;

            DemoApp.openModal('installment-detail-modal');
        };

        btnRegisterPaymentGlobal.addEventListener('click', () => openPaymentModal());

        [filterSearch, filterStatus, filterFrom, filterTo, filterExecutive].forEach((element) => {
            element.addEventListener('input', renderTable);
            element.addEventListener('change', renderTable);
        });

        btnResetFilters.addEventListener('click', () => {
            filterSearch.value = '';
            filterStatus.value = '';
            filterFrom.value = '';
            filterTo.value = '';
            filterExecutive.value = '';
            renderTable();
        });

        tableBody.addEventListener('click', (event) => {
            const button = event.target.closest('[data-action]');
            if (!button) return;

            const id = button.getAttribute('data-id');
            const action = button.getAttribute('data-action');
            const installment = findInstallment(id);

            if (!installment) return;

            if (action === 'pay') {
                openPaymentModal(installment);
                return;
            }

            if (action === 'receipt') {
                openReceiptModal(installment);
                return;
            }

            if (action === 'detail') {
                openDetailModal(installment);
            }
        });

        paymentSubmit.addEventListener('click', async () => {
            const formData = new FormData(paymentForm);
            const selected = paymentInstallmentSelect.value || paymentInstallmentId.value;
            formData.set('installment_id', selected);

            const response = await DemoApp.api(endpoint, {
                method: 'POST',
                body: formData
            });

            if (!response.success) {
                DemoApp.toast({
                    title: response.title || 'No se pudo registrar',
                    message: response.message || 'Verifica la información ingresada.',
                    type: 'error'
                });
                return;
            }

            if (response.installment) {
                upsertInstallment(response.installment);
            }

            if (response.payment) {
                paymentsState.unshift(response.payment);
            }

            renderKpis();
            renderTable();
            DemoApp.closeModal('payment-modal');

            DemoApp.toast({
                title: response.title || 'Pago registrado',
                message: response.message || 'La cuota fue actualizada correctamente.',
                type: 'success'
            });

            paymentForm.reset();
            document.getElementById('payment-date').value = today;
        });

        receiptSubmit.addEventListener('click', async () => {
            const formData = new FormData(receiptForm);

            const response = await DemoApp.api(endpoint, {
                method: 'POST',
                body: formData
            });

            if (!response.success) {
                DemoApp.toast({
                    title: response.title || 'No se pudo adjuntar',
                    message: response.message || 'Verifica la información ingresada.',
                    type: 'error'
                });
                return;
            }

            if (response.installment) {
                upsertInstallment(response.installment);
            }

            renderKpis();
            renderTable();
            DemoApp.closeModal('receipt-modal');

            DemoApp.toast({
                title: response.title || 'Comprobante guardado',
                message: response.message || 'El comprobante demo fue registrado.',
                type: 'success'
            });

            receiptForm.reset();
        });

        detailStatusSubmit.addEventListener('click', async () => {
            const formData = new FormData();
            formData.append('action', 'change_status');
            formData.append('installment_id', detailInstallmentId.value);
            formData.append('status', detailStatus.value);
            formData.append('note', detailStatusNote.value);

            const response = await DemoApp.api(endpoint, {
                method: 'POST',
                body: formData
            });

            if (!response.success) {
                DemoApp.toast({
                    title: response.title || 'No se pudo actualizar',
                    message: response.message || 'Verifica la información ingresada.',
                    type: 'error'
                });
                return;
            }

            if (response.installment) {
                upsertInstallment(response.installment);
            }

            renderKpis();
            renderTable();
            DemoApp.closeModal('installment-detail-modal');

            DemoApp.toast({
                title: response.title || 'Estado actualizado',
                message: response.message || 'La cuota fue actualizada correctamente.',
                type: 'success'
            });

            detailStatusNote.value = '';
        });

        paymentInstallmentSelect.addEventListener('change', () => {
            const selected = findInstallment(paymentInstallmentSelect.value);
            paymentInstallmentId.value = paymentInstallmentSelect.value || '';
            paymentAmountInput.value = selected ? selected.amount : '';
        });

        renderPaymentOptions();
        renderKpis();
        renderTable();
    })();
</script>
<?php
$content = ob_get_clean();

demo_render_internal_layout(
    'Cobranzas',
    $content,
    [
        'breadcrumb' => ['Inicio', 'Cobranzas'],
        'subtitle' => 'Vista gerencial de vencimientos, registro de pagos y validación de comprobantes en la sesión actual.',
    ]
);