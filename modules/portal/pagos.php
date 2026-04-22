<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';

demo_require_roles(['cliente']);

$portalUser = demo_current_user();
$clientId = (string)($portalUser['client_id'] ?? '');

$clients = demo_store('clients', []);
$client = demo_find_by_id($clients, $clientId);

if (!$client) {
    demo_push_toast('No se encontró la ficha del cliente vinculada a este usuario.', 'error', 'Portal incompleto');
    demo_redirect('logout.php');
}

$policies = demo_store('policies', []);
$installments = demo_store('installments', []);
$payments = demo_store('payments', []);

$portalPolicies = array_values(array_filter($policies, fn($policy) => (string)($policy['client_id'] ?? '') === $clientId));
$policyMap = [];
foreach ($portalPolicies as $policy) {
    $policyMap[$policy['id']] = $policy;
}

$rows = [];
foreach ($installments as $installment) {
    $policy = $policyMap[$installment['policy_id'] ?? ''] ?? null;
    if (!$policy) {
        continue;
    }

    $paymentData = null;
    foreach ($payments as $payment) {
        if (($payment['installment_id'] ?? '') === ($installment['id'] ?? '')) {
            $paymentData = $payment;
        }
    }

    $rows[] = [
        'id' => $installment['id'],
        'policy_id' => $installment['policy_id'],
        'policy_number' => $policy['policy_number'] ?? '—',
        'due_date' => $installment['due_date'] ?? '',
        'amount' => (float)($installment['amount'] ?? 0),
        'status' => $installment['status'] ?? 'pendiente',
        'receipt_uploaded' => (bool)($installment['receipt_uploaded'] ?? false),
        'receipt_name' => $installment['receipt_name'] ?? '',
        'receipt_note' => $installment['receipt_note'] ?? '',
        'receipt_uploaded_at' => $installment['receipt_uploaded_at'] ?? '',
        'payment_date' => $paymentData['date'] ?? '',
        'payment_method' => $paymentData['method'] ?? '',
    ];
}

usort($rows, fn($a, $b) => strtotime((string)($a['due_date'] ?? '')) <=> strtotime((string)($b['due_date'] ?? '')));

$paidCount = count(array_filter($rows, fn($row) => strtolower((string)($row['status'] ?? '')) === 'pagada'));
$pendingRows = array_values(array_filter($rows, fn($row) => in_array(strtolower((string)($row['status'] ?? '')), ['pendiente', 'vencida', 'en revisión'], true)));
$pendingCount = count($pendingRows);
$nextDueRow = $pendingRows[0] ?? null;
$nextAmount = (float)($nextDueRow['amount'] ?? 0);

$portalActive = 'pagos';

ob_start();
?>
<style>
    .portal-payments-grid {
        display: grid;
        gap: 1rem;
    }

    .portal-payments-kpis {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .portal-payments-controls {
        display: grid;
        gap: 1rem;
        grid-template-columns: 1fr .85fr auto;
        align-items: end;
        margin-bottom: 1rem;
    }

    .portal-payments-note {
        padding: .9rem 1rem;
        border-radius: 18px;
        border: 1px dashed rgba(100, 116, 139, .26);
        background: linear-gradient(180deg, #fbfdff 0%, #f7faff 100%);
        color: var(--text-soft);
        line-height: 1.55;
        margin-bottom: 1rem;
    }

    .portal-payments-empty {
        padding: 1.2rem;
        text-align: center;
        color: var(--text-soft);
    }

    .portal-payments-form-note {
        margin: 0 0 1rem;
        color: var(--text-soft);
        line-height: 1.5;
    }

    .portal-payment-detail-grid {
        display: grid;
        gap: .9rem;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .portal-payment-detail-item {
        padding: .9rem 1rem;
        border-radius: 18px;
        border: 1px solid rgba(219, 227, 239, .85);
        background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
    }

    .portal-payment-detail-item strong {
        display: block;
        margin-bottom: .35rem;
        color: var(--text-soft);
        font-size: .78rem;
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    @media (max-width: 1100px) {
        .portal-payments-kpis {
            grid-template-columns: 1fr;
        }

        .portal-payments-controls {
            grid-template-columns: 1fr 1fr;
        }

        .portal-payments-controls .btn {
            width: 100%;
        }
    }

    @media (max-width: 760px) {
        .portal-payments-controls,
        .portal-payment-detail-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="portal-shell">
    <?php require __DIR__ . '/_nav.php'; ?>

    <div class="portal-main">
        <div class="portal-payments-grid">
            <section class="portal-payments-kpis">
                <article class="card kpi-card">
                    <p class="kpi-card__label">Pagos al día</p>
                    <h3 class="kpi-card__value" id="kpi-paid"><?= demo_e((string)$paidCount) ?></h3>
                    <p class="kpi-card__meta">Cuotas marcadas como pagadas en tu historial actual.</p>
                </article>

                <article class="card kpi-card">
                    <p class="kpi-card__label">Cuotas pendientes</p>
                    <h3 class="kpi-card__value" id="kpi-pending"><?= demo_e((string)$pendingCount) ?></h3>
                    <p class="kpi-card__meta">Pagos aún por regularizar o en revisión.</p>
                </article>

                <article class="card kpi-card">
                    <p class="kpi-card__label">Monto próximo a vencer</p>
                    <h3 class="kpi-card__value" id="kpi-next-amount"><?= demo_e(demo_money($nextAmount)) ?></h3>
                    <p class="kpi-card__meta">Referencia del siguiente pago importante en tu portal.</p>
                </article>
            </section>

            <section class="card">
                <div class="card__header">
                    <div>
                        <h2 class="card__title">Mis pagos</h2>
                        <p class="card__subtitle">Consulta tus cuotas y comparte comprobantes de manera simulada pero realista.</p>
                    </div>
                    <?= demo_badge((string)count($rows) . ' cuotas', 'info') ?>
                </div>

                <div class="portal-payments-note">
                    Puedes subir un comprobante demo para una cuota pendiente. El estado cambiará visualmente a <strong style="display:inline; font-size:inherit; text-transform:none; letter-spacing:normal;">en revisión</strong> sin recargar la página.
                </div>

                <div class="portal-payments-controls">
                    <div>
                        <label class="form-label" for="payment-search">Buscar</label>
                        <input class="input" id="payment-search" type="text" placeholder="Número de póliza">
                    </div>

                    <div>
                        <label class="form-label" for="payment-status-filter">Estado</label>
                        <select class="select" id="payment-status-filter">
                            <option value="">Todos</option>
                            <option value="pendiente">Pendiente</option>
                            <option value="pagada">Pagada</option>
                            <option value="vencida">Vencida</option>
                            <option value="en revisión">En revisión</option>
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
                                <th>Póliza</th>
                                <th>Cuota</th>
                                <th>Vencimiento</th>
                                <th>Monto</th>
                                <th>Estado</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody id="payments-table-body"></tbody>
                    </table>
                </div>

                <div id="payments-empty-state" class="portal-payments-empty" hidden>
                    No hay cuotas que coincidan con los filtros seleccionados.
                </div>
            </section>
        </div>
    </div>
</div>

<div class="modal" id="upload-receipt-modal" hidden>
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__dialog modal__dialog--sm">
        <div class="modal__header">
            <h3 id="upload-modal-title">Subir comprobante</h3>
            <button type="button" class="icon-btn" data-modal-close aria-label="Cerrar">✕</button>
        </div>
        <div class="modal__body">
            <p class="portal-payments-form-note">Comparte un comprobante demo para esta cuota. El cambio se reflejará en tu portal de inmediato.</p>

            <form id="upload-form" class="form-grid">
                <input type="hidden" name="action" value="upload_receipt">
                <input type="hidden" name="installment_id" id="upload-installment-id" value="">

                <div>
                    <label class="form-label" for="receipt-name">Nombre del comprobante</label>
                    <input class="input" type="text" id="receipt-name" name="receipt_name" placeholder="voucher_transferencia_mayo.jpg">
                </div>

                <div>
                    <label class="form-label" for="receipt-note">Detalle</label>
                    <textarea class="textarea" id="receipt-note" name="receipt_note" placeholder="Comentario breve para el equipo"></textarea>
                </div>
            </form>
        </div>
        <div class="modal__footer">
            <button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button>
            <button type="button" class="btn btn-primary" id="upload-submit">Enviar comprobante</button>
        </div>
    </div>
</div>

<div class="modal" id="payment-detail-modal" hidden>
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__dialog">
        <div class="modal__header">
            <h3 id="payment-detail-title">Detalle de pago</h3>
            <button type="button" class="icon-btn" data-modal-close aria-label="Cerrar">✕</button>
        </div>
        <div class="modal__body">
            <div class="portal-payment-detail-grid" id="payment-detail-grid"></div>
        </div>
        <div class="modal__footer">
            <button type="button" class="btn btn-primary" data-modal-close>Cerrar</button>
        </div>
    </div>
</div>

<script>
    (() => {
        let rowsState = <?= json_encode(array_values($rows), JSON_UNESCAPED_UNICODE) ?>;
        const endpoint = <?= json_encode(demo_url('ajax/portal-finanzas.php'), JSON_UNESCAPED_UNICODE) ?>;

        const tableBody = document.getElementById('payments-table-body');
        const emptyState = document.getElementById('payments-empty-state');

        const searchInput = document.getElementById('payment-search');
        const statusFilter = document.getElementById('payment-status-filter');
        const resetBtn = document.getElementById('btn-reset-filters');

        const uploadForm = document.getElementById('upload-form');
        const uploadInstallmentId = document.getElementById('upload-installment-id');
        const uploadModalTitle = document.getElementById('upload-modal-title');
        const uploadSubmit = document.getElementById('upload-submit');

        const detailTitle = document.getElementById('payment-detail-title');
        const detailGrid = document.getElementById('payment-detail-grid');

        const kpiPaid = document.getElementById('kpi-paid');
        const kpiPending = document.getElementById('kpi-pending');
        const kpiNextAmount = document.getElementById('kpi-next-amount');

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

        const formatDateTime = (value) => {
            if (!value) return '—';
            const date = new Date(String(value).replace(' ', 'T'));
            if (Number.isNaN(date.getTime())) return value;
            return date.toLocaleDateString('es-PE') + ' ' + date.toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' });
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
        }[String(status || '').toLowerCase()] || 'neutral');

        const renderKpis = () => {
            const paid = rowsState.filter(row => row.status === 'pagada').length;
            const pendingRows = rowsState.filter(row => ['pendiente', 'vencida', 'en revisión'].includes(row.status));
            const nextAmount = pendingRows.length ? Number(pendingRows[0].amount || 0) : 0;

            kpiPaid.textContent = String(paid);
            kpiPending.textContent = String(pendingRows.length);
            kpiNextAmount.textContent = formatMoney(nextAmount);
        };

        const getFilteredRows = () => {
            const term = searchInput.value.trim().toLowerCase();
            const status = statusFilter.value;

            return rowsState.filter((row) => {
                return (!term || String(row.policy_number || '').toLowerCase().includes(term))
                    && (!status || row.status === status);
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
                    <td>${escapeHtml(row.policy_number || '—')}</td>
                    <td>#${escapeHtml(String(row.number || row.id || '—'))}</td>
                    <td>${escapeHtml(formatDate(row.due_date))}</td>
                    <td>${escapeHtml(formatMoney(row.amount))}</td>
                    <td><span class="badge badge-${badgeTone(row.status)}">${escapeHtml((row.status || '—').charAt(0).toUpperCase() + (row.status || '—').slice(1))}</span></td>
                    <td>
                        <div style="display:flex; flex-wrap:wrap; gap:.45rem;">
                            <button type="button" class="btn btn-secondary" data-action="detail" data-id="${escapeHtml(row.id)}">Detalle</button>
                            <button type="button" class="btn btn-primary" data-action="upload" data-id="${escapeHtml(row.id)}" ${row.status === 'pagada' ? 'disabled' : ''}>
                                ${row.status === 'pagada' ? 'Pagada' : 'Subir comprobante'}
                            </button>
                        </div>
                    </td>
                `;
                tableBody.appendChild(tr);
            });
        };

        const findRow = (id) => rowsState.find((row) => row.id === id) || null;

        const upsertRow = (row) => {
            const index = rowsState.findIndex((item) => item.id === row.id);
            if (index >= 0) {
                rowsState[index] = row;
            } else {
                rowsState.unshift(row);
            }
        };

        const openUploadModal = (row) => {
            uploadForm.reset();
            uploadInstallmentId.value = row.id;
            uploadModalTitle.textContent = `Subir comprobante · ${row.policy_number} · Cuota #${row.number || '—'}`;
            DemoApp.openModal('upload-receipt-modal');
        };

        const openDetailModal = (row) => {
            detailTitle.textContent = `Detalle de pago · ${row.policy_number} · Cuota #${row.number || '—'}`;
            detailGrid.innerHTML = `
                <div class="portal-payment-detail-item">
                    <strong>Póliza</strong>
                    <span>${escapeHtml(row.policy_number || '—')}</span>
                </div>
                <div class="portal-payment-detail-item">
                    <strong>Cuota</strong>
                    <span>#${escapeHtml(String(row.number || '—'))}</span>
                </div>
                <div class="portal-payment-detail-item">
                    <strong>Vencimiento</strong>
                    <span>${escapeHtml(formatDate(row.due_date))}</span>
                </div>
                <div class="portal-payment-detail-item">
                    <strong>Monto</strong>
                    <span>${escapeHtml(formatMoney(row.amount))}</span>
                </div>
                <div class="portal-payment-detail-item">
                    <strong>Estado</strong>
                    <span>${escapeHtml((row.status || '—').charAt(0).toUpperCase() + (row.status || '—').slice(1))}</span>
                </div>
                <div class="portal-payment-detail-item">
                    <strong>Fecha de pago registrada</strong>
                    <span>${escapeHtml(formatDate(row.payment_date || ''))}</span>
                </div>
                <div class="portal-payment-detail-item">
                    <strong>Comprobante</strong>
                    <span>${escapeHtml(row.receipt_uploaded ? (row.receipt_name || 'Adjunto') : 'No enviado')}</span>
                </div>
                <div class="portal-payment-detail-item">
                    <strong>Fecha de envío</strong>
                    <span>${escapeHtml(formatDateTime(row.receipt_uploaded_at || ''))}</span>
                </div>
                <div class="portal-payment-detail-item" style="grid-column: 1 / -1;">
                    <strong>Detalle</strong>
                    <span>${escapeHtml(row.receipt_note || 'Sin observaciones registradas.')}</span>
                </div>
            `;
            DemoApp.openModal('payment-detail-modal');
        };

        [searchInput, statusFilter].forEach((element) => {
            element.addEventListener('input', renderTable);
            element.addEventListener('change', renderTable);
        });

        resetBtn.addEventListener('click', () => {
            searchInput.value = '';
            statusFilter.value = '';
            renderTable();
        });

        tableBody.addEventListener('click', (event) => {
            const button = event.target.closest('[data-action]');
            if (!button) return;

            const row = findRow(button.getAttribute('data-id'));
            if (!row) return;

            if (button.getAttribute('data-action') === 'upload') {
                openUploadModal(row);
                return;
            }

            if (button.getAttribute('data-action') === 'detail') {
                openDetailModal(row);
            }
        });

        uploadSubmit.addEventListener('click', async () => {
            const formData = new FormData(uploadForm);

            const response = await DemoApp.api(endpoint, {
                method: 'POST',
                body: formData
            });

            if (!response.success) {
                DemoApp.toast({
                    title: response.title || 'No se pudo enviar',
                    message: response.message || 'Verifica la información ingresada.',
                    type: 'error'
                });
                return;
            }

            if (response.installment) {
                upsertRow(response.installment);
            }

            renderKpis();
            renderTable();
            DemoApp.closeModal('upload-receipt-modal');
            DemoApp.toast({
                title: response.title || 'Comprobante enviado',
                message: response.message || 'Tu comprobante fue registrado correctamente.',
                type: 'success'
            });

            uploadForm.reset();
        });

        renderKpis();
        renderTable();
    })();
</script>
<?php
$content = ob_get_clean();

demo_render_internal_layout(
    'Mis pagos',
    $content,
    [
        'breadcrumb' => ['Portal', 'Mis pagos'],
        'subtitle' => 'Consulta de cuotas y envío de comprobantes desde el portal cliente.',
    ]
);