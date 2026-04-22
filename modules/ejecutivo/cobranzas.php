<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';

demo_require_roles(['ejecutivo']);

$currentUser = demo_current_user();
$executiveId = $currentUser['id'] ?? '';

$policies = demo_filter_policies_by_executive(demo_store('policies', []), $executiveId);
$clients = demo_filter_clients_by_executive(demo_store('clients', []), $executiveId);
$installments = demo_store('installments', []);
$payments = demo_store('payments', []);

$policyMap = [];
foreach ($policies as $policy) {
    $policyMap[$policy['id']] = $policy;
}

$clientMap = [];
foreach ($clients as $client) {
    $clientMap[$client['id']] = $client;
}

$rows = [];
foreach ($installments as $installment) {
    $policyId = $installment['policy_id'] ?? '';
    $policy = $policyMap[$policyId] ?? null;

    if (!$policy) {
        continue;
    }

    $client = $clientMap[$policy['client_id'] ?? ''] ?? null;
    $paymentData = null;

    foreach ($payments as $payment) {
        if (($payment['installment_id'] ?? '') === ($installment['id'] ?? '')) {
            $paymentData = $payment;
        }
    }

    $rows[] = [
        'id' => $installment['id'],
        'policy_id' => $policyId,
        'policy_number' => $policy['policy_number'] ?? '—',
        'client_name' => $client['name'] ?? 'Cliente',
        'due_date' => $installment['due_date'] ?? '',
        'amount' => (float)($installment['amount'] ?? 0),
        'status' => $installment['status'] ?? 'pendiente',
        'receipt_uploaded' => (bool)($installment['receipt_uploaded'] ?? false),
        'receipt_name' => $installment['receipt_name'] ?? '',
        'payment_date' => $paymentData['date'] ?? '',
        'payment_method' => $paymentData['method'] ?? '',
        'payment_note' => $paymentData['note'] ?? '',
    ];
}

usort($rows, fn($a, $b) => strtotime((string)($a['due_date'] ?? '')) <=> strtotime((string)($b['due_date'] ?? '')));

$totalPending = count(array_filter($rows, fn($r) => ($r['status'] ?? '') === 'pendiente'));
$totalOverdue = count(array_filter($rows, fn($r) => ($r['status'] ?? '') === 'vencida'));
$totalReview = count(array_filter($rows, fn($r) => ($r['status'] ?? '') === 'en revisión'));
$totalAmount = array_reduce($rows, function ($carry, $row) {
    return in_array((string)($row['status'] ?? ''), ['pendiente', 'vencida', 'en revisión'], true)
        ? $carry + (float)($row['amount'] ?? 0)
        : $carry;
}, 0.0);

ob_start();
?>
<style>
    .exec-op-kpis {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        margin-bottom: 1rem;
    }

    .exec-op-controls {
        display: grid;
        gap: 1rem;
        grid-template-columns: 1.2fr .85fr .85fr .85fr auto;
        align-items: end;
        margin-bottom: 1rem;
    }

    .exec-op-actions {
        display: flex;
        flex-wrap: wrap;
        gap: .45rem;
    }

    .exec-op-action-btn {
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

    .exec-op-action-btn:hover {
        transform: translateY(-1px);
        border-color: rgba(79, 70, 229, .18);
        background: #f8fbff;
    }

    .exec-op-action-btn--primary {
        background: rgba(79, 70, 229, .08);
        color: var(--primary);
        border-color: rgba(79, 70, 229, .12);
    }

    .exec-op-note {
        padding: .85rem 1rem;
        border-radius: 16px;
        border: 1px dashed rgba(100, 116, 139, .28);
        background: linear-gradient(180deg, #fbfdff 0%, #f7faff 100%);
        margin-bottom: 1rem;
    }

    .exec-op-note strong {
        display: block;
        margin-bottom: .2rem;
    }

    .exec-op-empty {
        padding: 1.2rem;
        text-align: center;
        color: var(--text-soft);
    }

    .exec-op-form-note {
        margin: 0 0 1rem;
        color: var(--text-soft);
        line-height: 1.5;
    }

    .exec-op-receipt-grid {
        display: grid;
        gap: .9rem;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .exec-op-receipt-item {
        padding: .9rem 1rem;
        border-radius: 16px;
        border: 1px solid rgba(219, 227, 239, .85);
        background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
    }

    .exec-op-receipt-item strong {
        display: block;
        margin-bottom: .35rem;
        color: var(--text-soft);
        font-size: .78rem;
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    @media (max-width: 1180px) {
        .exec-op-kpis {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .exec-op-controls {
            grid-template-columns: 1fr 1fr;
        }

        .exec-op-controls .btn {
            width: 100%;
        }
    }

    @media (max-width: 760px) {
        .exec-op-kpis,
        .exec-op-controls,
        .exec-op-receipt-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="grid" style="gap: 1rem;">
    <section class="exec-op-kpis">
        <article class="card kpi-card">
            <p class="kpi-card__label">Pendientes</p>
            <h3 class="kpi-card__value" id="kpi-pending"><?= demo_e((string)$totalPending) ?></h3>
            <p class="kpi-card__meta">Cuotas pendientes dentro de tu cartera.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Vencidas</p>
            <h3 class="kpi-card__value" id="kpi-overdue"><?= demo_e((string)$totalOverdue) ?></h3>
            <p class="kpi-card__meta">Cobros fuera de fecha que requieren seguimiento.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">En revisión</p>
            <h3 class="kpi-card__value" id="kpi-review"><?= demo_e((string)$totalReview) ?></h3>
            <p class="kpi-card__meta">Pagos observados o pendientes de validar.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Monto por regularizar</p>
            <h3 class="kpi-card__value" id="kpi-amount"><?= demo_e(demo_money($totalAmount)) ?></h3>
            <p class="kpi-card__meta">Total estimado de cuotas no regularizadas.</p>
        </article>
    </section>

    <section class="card">
        <div class="card__header">
            <div>
                <h2 class="card__title">Cobranzas de mi cartera</h2>
                <p class="card__subtitle">Registro de pagos demo, seguimiento de cuotas y revisión de comprobantes.</p>
            </div>

            <button type="button" class="btn btn-primary" id="btn-register-payment-global">Registrar pago</button>
        </div>

        <div class="exec-op-note">
            <strong>Operación propia</strong>
            <span class="muted">Solo se muestran cuotas vinculadas a tus pólizas. Los cambios se aplican sin recargar la página.</span>
        </div>

        <div class="exec-op-controls">
            <div>
                <label class="form-label" for="installment-search">Buscar</label>
                <input class="input" id="installment-search" type="text" placeholder="Cliente o número de póliza">
            </div>

            <div>
                <label class="form-label" for="installment-status-filter">Estado</label>
                <select class="select" id="installment-status-filter">
                    <option value="">Todos</option>
                    <option value="pendiente">Pendiente</option>
                    <option value="pagada">Pagada</option>
                    <option value="vencida">Vencida</option>
                    <option value="en revisión">En revisión</option>
                </select>
            </div>

            <div>
                <label class="form-label" for="installment-from">Desde</label>
                <input class="input" id="installment-from" type="date">
            </div>

            <div>
                <label class="form-label" for="installment-to">Hasta</label>
                <input class="input" id="installment-to" type="date">
            </div>

            <div>
                <button type="button" class="btn btn-ghost" id="btn-reset-installments">Limpiar filtros</button>
            </div>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Póliza</th>
                        <th>Cuota</th>
                        <th>Vencimiento</th>
                        <th>Monto</th>
                        <th>Estado</th>
                        <th>Comprobante</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="installments-table-body"></tbody>
            </table>
        </div>

        <div id="installments-empty-state" class="exec-op-empty" hidden>
            No hay cuotas que coincidan con los filtros seleccionados.
        </div>
    </section>
</div>

<div class="modal" id="payment-modal" hidden>
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__dialog">
        <div class="modal__header">
            <h3 id="payment-modal-title">Registrar pago demo</h3>
            <button type="button" class="icon-btn" data-modal-close aria-label="Cerrar">✕</button>
        </div>
        <div class="modal__body">
            <p class="exec-op-form-note">Confirma un pago para una cuota de tu cartera. Se guardará de forma simulada en la sesión actual.</p>

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
                    <input class="input" type="date" id="payment-date" name="payment_date" value="<?= demo_e(date('Y-m-d')) ?>">
                </div>

                <div>
                    <label class="form-label" for="payment-amount">Monto</label>
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
                    <textarea class="textarea" id="payment-note" name="note" placeholder="Comentario breve del pago demo"></textarea>
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
            <div class="exec-op-receipt-grid" id="receipt-detail-grid"></div>
        </div>
        <div class="modal__footer">
            <button type="button" class="btn btn-primary" data-modal-close>Cerrar</button>
        </div>
    </div>
</div>

<script>
    (() => {
        let installmentsState = <?= json_encode(array_values($rows), JSON_UNESCAPED_UNICODE) ?>;
        let paymentsState = <?= json_encode(array_values($payments), JSON_UNESCAPED_UNICODE) ?>;
        const endpoint = <?= json_encode(demo_url('ajax/ejecutivo-operacion.php'), JSON_UNESCAPED_UNICODE) ?>;

        const tableBody = document.getElementById('installments-table-body');
        const emptyState = document.getElementById('installments-empty-state');

        const searchInput = document.getElementById('installment-search');
        const statusFilter = document.getElementById('installment-status-filter');
        const fromFilter = document.getElementById('installment-from');
        const toFilter = document.getElementById('installment-to');
        const resetBtn = document.getElementById('btn-reset-installments');

        const paymentForm = document.getElementById('payment-form');
        const paymentSelect = document.getElementById('payment-installment-select');
        const paymentInstallmentId = document.getElementById('payment-installment-id');
        const paymentAmount = document.getElementById('payment-amount');
        const paymentTitle = document.getElementById('payment-modal-title');
        const paymentSubmit = document.getElementById('payment-submit');

        const receiptTitle = document.getElementById('receipt-modal-title');
        const receiptGrid = document.getElementById('receipt-detail-grid');

        const kpiPending = document.getElementById('kpi-pending');
        const kpiOverdue = document.getElementById('kpi-overdue');
        const kpiReview = document.getElementById('kpi-review');
        const kpiAmount = document.getElementById('kpi-amount');

        const escapeHtml = (value) => String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');

        const formatDate = (value) => {
            if (!value) return '—';
            const date = new Date(`${value}T00:00:00`);
            if (Number.isNaN(date.getTime())) return value;
            return date.toLocaleDateString('es-PE');
        };

        const formatMoney = (value, currency = 'S/') => {
            const numeric = Number(value || 0);
            return `${currency} ${numeric.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        };

        const badgeTone = (status) => ({
            'pendiente': 'warning',
            'pagada': 'success',
            'vencida': 'danger',
            'en revisión': 'info'
        }[status] || 'neutral');

        const renderKpis = () => {
            const pending = installmentsState.filter(r => r.status === 'pendiente').length;
            const overdue = installmentsState.filter(r => r.status === 'vencida').length;
            const review = installmentsState.filter(r => r.status === 'en revisión').length;
            const amount = installmentsState.reduce((sum, row) => {
                return ['pendiente', 'vencida', 'en revisión'].includes(row.status) ? sum + Number(row.amount || 0) : sum;
            }, 0);

            kpiPending.textContent = String(pending);
            kpiOverdue.textContent = String(overdue);
            kpiReview.textContent = String(review);
            kpiAmount.textContent = formatMoney(amount);
        };

        const getFilteredRows = () => {
            const term = searchInput.value.trim().toLowerCase();
            const status = statusFilter.value;
            const from = fromFilter.value;
            const to = toFilter.value;

            return installmentsState.filter((row) => {
                const haystack = [row.client_name, row.policy_number].join(' ').toLowerCase();
                const okSearch = !term || haystack.includes(term);
                const okStatus = !status || row.status === status;
                const okFrom = !from || (row.due_date && row.due_date >= from);
                const okTo = !to || (row.due_date && row.due_date <= to);

                return okSearch && okStatus && okFrom && okTo;
            });
        };

        const renderTable = () => {
            const rows = getFilteredRows();
            tableBody.innerHTML = '';

            if (!rows.length) {
                emptyState.hidden = false;
                return;
            }

            emptyState.hidden = true;

            rows.forEach((row) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${escapeHtml(row.client_name || '—')}</td>
                    <td>${escapeHtml(row.policy_number || '—')}</td>
                    <td>#${escapeHtml(String(row.number || '—'))}</td>
                    <td>${escapeHtml(formatDate(row.due_date))}</td>
                    <td>${escapeHtml(formatMoney(row.amount))}</td>
                    <td><span class="badge badge-${badgeTone(row.status)}">${escapeHtml((row.status || '—').charAt(0).toUpperCase() + (row.status || '—').slice(1))}</span></td>
                    <td>${row.receipt_uploaded ? `<span class="badge badge-success">${escapeHtml(row.receipt_name || 'Adjunto')}</span>` : `<span class="badge badge-warning">Sin comprobante</span>`}</td>
                    <td>
                        <div class="exec-op-actions">
                            <button type="button" class="exec-op-action-btn exec-op-action-btn--primary" data-action="pay" data-id="${escapeHtml(row.id)}">Registrar pago</button>
                            <button type="button" class="exec-op-action-btn" data-action="receipt" data-id="${escapeHtml(row.id)}">Ver comprobante</button>
                        </div>
                    </td>
                `;
                tableBody.appendChild(tr);
            });
        };

        const renderPaymentOptions = () => {
            const previous = paymentSelect.value;
            paymentSelect.innerHTML = '<option value="">Seleccionar</option>';

            installmentsState
                .slice()
                .sort((a, b) => new Date(a.due_date) - new Date(b.due_date))
                .forEach((row) => {
                    const option = document.createElement('option');
                    option.value = row.id;
                    option.textContent = `${row.policy_number} · ${row.client_name} · Cuota #${row.number}`;
                    paymentSelect.appendChild(option);
                });

            if (previous) {
                paymentSelect.value = previous;
            }
        };

        const findInstallment = (id) => installmentsState.find((row) => row.id === id) || null;

        const upsertInstallment = (installment) => {
            const index = installmentsState.findIndex((row) => row.id === installment.id);
            if (index >= 0) {
                installmentsState[index] = installment;
            } else {
                installmentsState.unshift(installment);
            }
        };

        const openPaymentModal = (installment = null) => {
            paymentForm.reset();
            paymentInstallmentId.value = installment?.id || '';
            paymentSelect.value = installment?.id || '';
            paymentAmount.value = installment?.amount || '';
            document.getElementById('payment-date').value = '<?= demo_e(date('Y-m-d')) ?>';
            paymentTitle.textContent = installment ? `Registrar pago · ${installment.policy_number} · Cuota #${installment.number}` : 'Registrar pago demo';
            DemoApp.openModal('payment-modal');
        };

        const openReceiptModal = (installment) => {
            receiptTitle.textContent = `Comprobante · ${installment.policy_number} · Cuota #${installment.number}`;
            receiptGrid.innerHTML = `
                <div class="exec-op-receipt-item">
                    <strong>Cliente</strong>
                    <span>${escapeHtml(installment.client_name || '—')}</span>
                </div>
                <div class="exec-op-receipt-item">
                    <strong>Póliza</strong>
                    <span>${escapeHtml(installment.policy_number || '—')}</span>
                </div>
                <div class="exec-op-receipt-item">
                    <strong>Comprobante</strong>
                    <span>${escapeHtml(installment.receipt_uploaded ? (installment.receipt_name || 'Registrado') : 'No adjuntado')}</span>
                </div>
                <div class="exec-op-receipt-item">
                    <strong>Estado</strong>
                    <span>${escapeHtml((installment.status || '—').charAt(0).toUpperCase() + (installment.status || '—').slice(1))}</span>
                </div>
                <div class="exec-op-receipt-item">
                    <strong>Fecha de pago</strong>
                    <span>${escapeHtml(formatDate(installment.payment_date || ''))}</span>
                </div>
                <div class="exec-op-receipt-item">
                    <strong>Método</strong>
                    <span>${escapeHtml(installment.payment_method || '—')}</span>
                </div>
                <div class="exec-op-receipt-item" style="grid-column: 1 / -1;">
                    <strong>Detalle</strong>
                    <span>${escapeHtml(installment.payment_note || 'Sin observaciones registradas.')}</span>
                </div>
            `;
            DemoApp.openModal('receipt-modal');
        };

        document.getElementById('btn-register-payment-global').addEventListener('click', () => openPaymentModal());

        [searchInput, statusFilter, fromFilter, toFilter].forEach((element) => {
            element.addEventListener('input', renderTable);
            element.addEventListener('change', renderTable);
        });

        resetBtn.addEventListener('click', () => {
            searchInput.value = '';
            statusFilter.value = '';
            fromFilter.value = '';
            toFilter.value = '';
            renderTable();
        });

        tableBody.addEventListener('click', (event) => {
            const button = event.target.closest('[data-action]');
            if (!button) return;

            const action = button.getAttribute('data-action');
            const id = button.getAttribute('data-id');
            const installment = findInstallment(id);

            if (!installment) return;

            if (action === 'pay') {
                openPaymentModal(installment);
                return;
            }

            if (action === 'receipt') {
                openReceiptModal(installment);
            }
        });

        paymentSelect.addEventListener('change', () => {
            const installment = findInstallment(paymentSelect.value);
            paymentInstallmentId.value = paymentSelect.value || '';
            paymentAmount.value = installment ? installment.amount : '';
        });

        paymentSubmit.addEventListener('click', async () => {
            const formData = new FormData(paymentForm);
            const selected = paymentSelect.value || paymentInstallmentId.value;
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
            document.getElementById('payment-date').value = '<?= demo_e(date('Y-m-d')) ?>';
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
        'subtitle' => 'Seguimiento de cuotas, pagos demo y comprobantes dentro de tu propia cartera.',
    ]
);