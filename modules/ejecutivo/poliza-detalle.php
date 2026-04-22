<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';

demo_require_roles(['ejecutivo']);

$currentUser = demo_current_user();
$executiveId = $currentUser['id'] ?? '';
$policyId = trim((string)($_GET['id'] ?? ''));

$policies = demo_filter_policies_by_executive(demo_store('policies', []), $executiveId);
$policy = demo_find_by_id($policies, $policyId);

if (!$policy) {
    demo_push_toast('No puedes ver pólizas ajenas a tu cartera.', 'error', 'Acceso denegado');
    demo_redirect('modules/ejecutivo/polizas.php');
}

$clients = demo_filter_clients_by_executive(demo_store('clients', []), $executiveId);
$documents = demo_store('documents', []);
$installments = demo_store('installments', []);
$insurers = demo_store('insurers', []);
$insuranceTypes = demo_store('insurance_types', []);
$policyHistoryStore = demo_store('policy_history', []);

$client = demo_find_by_id($clients, (string)($policy['client_id'] ?? ''));
$insurer = demo_find_by_id($insurers, (string)($policy['insurer_id'] ?? ''));
$type = demo_find_by_id($insuranceTypes, (string)($policy['insurance_type_id'] ?? ''));

$policyInstallments = array_values(array_filter($installments, fn($i) => ($i['policy_id'] ?? '') === $policyId));
usort($policyInstallments, fn($a, $b) => ((int)($a['number'] ?? 0)) <=> ((int)($b['number'] ?? 0)));

$paidInstallments = array_values(array_filter($policyInstallments, fn($i) => strtolower((string)($i['status'] ?? '')) === 'pagada'));
$pendingInstallments = array_values(array_filter($policyInstallments, fn($i) => in_array(strtolower((string)($i['status'] ?? '')), ['pendiente', 'vencida', 'en revisión'], true)));

$policyDocuments = array_values(array_filter($documents, fn($doc) => ($doc['entity_type'] ?? '') === 'policy' && ($doc['entity_id'] ?? '') === $policyId));
usort($policyDocuments, fn($a, $b) => strtotime((string)($b['created_at'] ?? '')) <=> strtotime((string)($a['created_at'] ?? '')));

$historyItems = array_values(array_filter($policyHistoryStore, fn($item) => ($item['policy_id'] ?? '') === $policyId));
if (empty($historyItems)) {
    $historyItems[] = [
        'id' => 'seed-' . $policyId,
        'policy_id' => $policyId,
        'title' => 'Póliza registrada',
        'description' => 'Se registró la póliza ' . ($policy['policy_number'] ?? '—') . ' dentro de la cartera del ejecutivo.',
        'created_at' => $policy['created_at'] ?? date('Y-m-d H:i:s'),
    ];
}
usort($historyItems, fn($a, $b) => strtotime((string)($b['created_at'] ?? '')) <=> strtotime((string)($a['created_at'] ?? '')));
$historyItems = array_slice($historyItems, 0, 15);

$nextDue = !empty($pendingInstallments) ? ($pendingInstallments[0]['due_date'] ?? null) : null;

ob_start();
?>
<style>
    .exec-policy-detail-grid {
        display: grid;
        gap: 1rem;
    }

    .exec-policy-hero {
        display: grid;
        gap: 1rem;
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: center;
    }

    .exec-policy-hero__meta h2 {
        margin: .25rem 0;
        font-size: clamp(1.45rem, 2vw, 2rem);
        line-height: 1.1;
    }

    .exec-policy-hero__meta p {
        margin: 0;
        color: var(--text-soft);
        line-height: 1.5;
    }

    .exec-policy-hero__actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: .65rem;
    }

    .exec-policy-summary {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .exec-policy-meta-grid {
        display: grid;
        gap: .9rem;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .exec-policy-meta-item {
        padding: .95rem 1rem;
        border-radius: 18px;
        border: 1px solid rgba(219, 227, 239, .85);
        background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
    }

    .exec-policy-meta-item strong {
        display: block;
        margin-bottom: .35rem;
        color: var(--text-soft);
        font-size: .8rem;
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .exec-policy-meta-item span {
        display: block;
        line-height: 1.5;
        overflow-wrap: anywhere;
    }

    .exec-policy-tab-panels > div[hidden] {
        display: none !important;
    }

    .exec-policy-list {
        display: grid;
        gap: .8rem;
    }

    .exec-policy-item {
        padding: .95rem 1rem;
        border-radius: 18px;
        border: 1px solid rgba(219, 227, 239, .85);
        background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
    }

    .exec-policy-item__top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: .75rem;
        margin-bottom: .35rem;
    }

    .exec-policy-item h4 {
        margin: 0;
        font-size: .96rem;
    }

    .exec-policy-item p,
    .exec-policy-item small {
        margin: 0;
        color: var(--text-soft);
        line-height: 1.55;
    }

    .exec-policy-note {
        padding: .85rem 1rem;
        border-radius: 16px;
        border: 1px dashed rgba(100, 116, 139, .28);
        background: linear-gradient(180deg, #fbfdff 0%, #f7faff 100%);
    }

    .exec-policy-form-note {
        margin: 0 0 1rem;
        color: var(--text-soft);
        line-height: 1.5;
    }

    @media (max-width: 1100px) {
        .exec-policy-summary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 920px) {
        .exec-policy-hero,
        .exec-policy-meta-grid {
            grid-template-columns: 1fr;
        }

        .exec-policy-hero__actions {
            justify-content: flex-start;
        }
    }

    @media (max-width: 680px) {
        .exec-policy-summary {
            grid-template-columns: 1fr;
        }

        .exec-policy-item__top {
            flex-direction: column;
        }
    }
</style>

<div class="exec-policy-detail-grid">
    <section class="card">
        <div class="exec-policy-hero">
            <div class="exec-policy-hero__meta">
                <span class="badge badge-<?= demo_e(match (($policy['status'] ?? '')) {
                    'activa' => 'success',
                    'pendiente' => 'warning',
                    'vencida', 'anulada' => 'danger',
                    'renovada' => 'info',
                    default => 'neutral',
                }) ?>" id="policy-status-badge">
                    <?= demo_e(ucfirst((string)($policy['status'] ?? '—'))) ?>
                </span>
                <h2 id="policy-title"><?= demo_e($policy['policy_number'] ?? 'Póliza') ?></h2>
                <p id="policy-subtitle"><?= demo_e(($client['name'] ?? 'Cliente') . ' · ' . ($insurer['name'] ?? 'Aseguradora') . ' · ' . ($type['name'] ?? 'Tipo')) ?></p>
            </div>

            <div class="exec-policy-hero__actions">
                <button type="button" class="btn btn-ghost" id="btn-edit-policy">Editar</button>
                <button type="button" class="btn btn-secondary" id="btn-upload-document">Subir documento</button>
                <button type="button" class="btn btn-primary" id="btn-register-payment">Registrar pago</button>
            </div>
        </div>
    </section>

    <section class="exec-policy-summary">
        <article class="card kpi-card">
            <p class="kpi-card__label">Prima total</p>
            <h3 class="kpi-card__value" id="summary-premium"><?= demo_e(demo_money((float)($policy['premium'] ?? 0), (string)($policy['currency'] ?? 'S/'))) ?></h3>
            <p class="kpi-card__meta">Monto total de la póliza dentro de tu cartera.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Cuotas pagadas</p>
            <h3 class="kpi-card__value"><?= demo_e((string)count($paidInstallments)) ?></h3>
            <p class="kpi-card__meta">Cuotas confirmadas dentro del store actual.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Próximas cuotas</p>
            <h3 class="kpi-card__value"><?= demo_e((string)count($pendingInstallments)) ?></h3>
            <p class="kpi-card__meta">Pendientes, vencidas o en revisión.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Fin de vigencia</p>
            <h3 class="kpi-card__value" style="font-size: 1.45rem;" id="summary-end-date"><?= demo_e(demo_date((string)($policy['end_date'] ?? null))) ?></h3>
            <p class="kpi-card__meta">Próxima fecha clave para tu seguimiento comercial.</p>
        </article>
    </section>

    <section class="card">
        <div class="tab-nav" data-tab-group>
            <button type="button" class="tab-btn is-active" data-tab-button="info">Info general</button>
            <button type="button" class="tab-btn" data-tab-button="cuotas">Cuotas</button>
            <button type="button" class="tab-btn" data-tab-button="documentos">Documentos</button>
            <button type="button" class="tab-btn" data-tab-button="historial">Historial</button>
        </div>

        <div class="exec-policy-tab-panels" data-tab-group>
            <div data-tab-panel="info">
                <div class="exec-policy-meta-grid">
                    <div class="exec-policy-meta-item">
                        <strong>Cliente</strong>
                        <span id="info-client"><?= demo_e($client['name'] ?? '—') ?></span>
                    </div>
                    <div class="exec-policy-meta-item">
                        <strong>Aseguradora</strong>
                        <span id="info-insurer"><?= demo_e($insurer['name'] ?? '—') ?></span>
                    </div>
                    <div class="exec-policy-meta-item">
                        <strong>Tipo de seguro</strong>
                        <span id="info-type"><?= demo_e($type['name'] ?? '—') ?></span>
                    </div>
                    <div class="exec-policy-meta-item">
                        <strong>Estado</strong>
                        <span id="info-status"><?= demo_e(ucfirst((string)($policy['status'] ?? '—'))) ?></span>
                    </div>
                    <div class="exec-policy-meta-item">
                        <strong>Inicio de vigencia</strong>
                        <span id="info-start-date"><?= demo_e(demo_date((string)($policy['start_date'] ?? null))) ?></span>
                    </div>
                    <div class="exec-policy-meta-item">
                        <strong>Fin de vigencia</strong>
                        <span id="info-end-date"><?= demo_e(demo_date((string)($policy['end_date'] ?? null))) ?></span>
                    </div>
                    <div class="exec-policy-meta-item">
                        <strong>Bien asegurado</strong>
                        <span id="info-insured-item"><?= demo_e($policy['insured_item'] ?? 'No especificado') ?></span>
                    </div>
                    <div class="exec-policy-meta-item">
                        <strong>Próximo vencimiento</strong>
                        <span><?= demo_e(demo_date((string)$nextDue)) ?></span>
                    </div>
                </div>

                <div class="exec-policy-note mt-2">
                    <strong>Observaciones</strong>
                    <p class="muted mt-1" id="info-notes"><?= demo_e($policy['notes'] ?? 'Sin observaciones registradas.') ?></p>
                </div>
            </div>

            <div data-tab-panel="cuotas" hidden>
                <?php if (empty($policyInstallments)): ?>
                    <div class="empty-state">Esta póliza aún no tiene cuotas generadas.</div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Cuota</th>
                                    <th>Vencimiento</th>
                                    <th>Monto</th>
                                    <th>Estado</th>
                                    <th>Comprobante</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($policyInstallments as $installment): ?>
                                    <tr>
                                        <td>#<?= demo_e((string)($installment['number'] ?? '—')) ?></td>
                                        <td><?= demo_e(demo_date((string)($installment['due_date'] ?? null))) ?></td>
                                        <td><?= demo_e(demo_money((float)($installment['amount'] ?? 0), (string)($policy['currency'] ?? 'S/'))) ?></td>
                                        <td><?= demo_badge((string)($installment['status'] ?? '—'), (string)($installment['status'] ?? '—')) ?></td>
                                        <td><?= !empty($installment['receipt_uploaded']) ? demo_badge('Sí', 'success') : demo_badge('No', 'warning') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div data-tab-panel="documentos" hidden>
                <div class="exec-policy-list" id="documents-list">
                    <?php if (empty($policyDocuments)): ?>
                        <div class="empty-state">No hay documentos asociados a esta póliza.</div>
                    <?php else: ?>
                        <?php foreach ($policyDocuments as $document): ?>
                            <article class="exec-policy-item">
                                <div class="exec-policy-item__top">
                                    <div>
                                        <h4><?= demo_e($document['original_name'] ?? 'Documento') ?></h4>
                                        <p><?= demo_e($document['type'] ?? 'Archivo') ?></p>
                                    </div>
                                    <small><?= demo_e(demo_date((string)($document['created_at'] ?? null), 'd/m/Y H:i')) ?></small>
                                </div>
                                <p>Subido por <?= demo_e(demo_find_user_by_id((string)($document['uploaded_by'] ?? ''))['full_name'] ?? 'Sistema') ?></p>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div data-tab-panel="historial" hidden>
                <div class="timeline" id="history-list">
                    <?php if (empty($historyItems)): ?>
                        <div class="empty-state">No hay historial disponible para esta póliza.</div>
                    <?php else: ?>
                        <?php foreach ($historyItems as $item): ?>
                            <article class="timeline__item">
                                <h4><?= demo_e($item['title'] ?? 'Historial') ?></h4>
                                <p><?= demo_e($item['description'] ?? '') ?></p>
                                <small class="muted"><?= demo_e(demo_date((string)($item['created_at'] ?? null), 'd/m/Y H:i')) ?></small>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</div>

<div class="modal" id="exec-policy-edit-modal" hidden>
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__dialog">
        <div class="modal__header">
            <h3>Editar póliza</h3>
            <button type="button" class="icon-btn" data-modal-close aria-label="Cerrar">✕</button>
        </div>
        <div class="modal__body">
            <p class="exec-policy-form-note">Actualiza los datos principales de esta póliza dentro de tu cartera.</p>

            <form id="exec-policy-edit-form" class="form-grid form-grid--3">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="policy_id" value="<?= demo_e($policyId) ?>">

                <div>
                    <label class="form-label" for="edit-policy-number">Número de póliza</label>
                    <input class="input" type="text" id="edit-policy-number" name="policy_number" value="<?= demo_e($policy['policy_number'] ?? '') ?>">
                </div>

                <div>
                    <label class="form-label" for="edit-client">Cliente</label>
                    <select class="select" id="edit-client" name="client_id">
                        <?php foreach ($clients as $clientItem): ?>
                            <option value="<?= demo_e($clientItem['id']) ?>" <?= ($clientItem['id'] ?? '') === ($policy['client_id'] ?? '') ? 'selected' : '' ?>>
                                <?= demo_e($clientItem['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label" for="edit-insurer">Aseguradora</label>
                    <select class="select" id="edit-insurer" name="insurer_id">
                        <?php foreach ($insurers as $insurerItem): ?>
                            <option value="<?= demo_e($insurerItem['id']) ?>" <?= ($insurerItem['id'] ?? '') === ($policy['insurer_id'] ?? '') ? 'selected' : '' ?>>
                                <?= demo_e($insurerItem['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label" for="edit-type">Tipo de seguro</label>
                    <select class="select" id="edit-type" name="insurance_type_id">
                        <?php foreach ($insuranceTypes as $typeItem): ?>
                            <option value="<?= demo_e($typeItem['id']) ?>" <?= ($typeItem['id'] ?? '') === ($policy['insurance_type_id'] ?? '') ? 'selected' : '' ?>>
                                <?= demo_e($typeItem['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label" for="edit-status">Estado</label>
                    <select class="select" id="edit-status" name="status">
                        <?php foreach (['activa', 'pendiente', 'vencida', 'anulada'] as $statusOption): ?>
                            <option value="<?= demo_e($statusOption) ?>" <?= ($statusOption === ($policy['status'] ?? '')) ? 'selected' : '' ?>>
                                <?= demo_e(ucfirst($statusOption)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label" for="edit-premium">Prima</label>
                    <input class="input" type="number" step="0.01" min="0" id="edit-premium" name="premium" value="<?= demo_e((string)($policy['premium'] ?? '')) ?>">
                </div>

                <div>
                    <label class="form-label" for="edit-start-date">Inicio</label>
                    <input class="input" type="date" id="edit-start-date" name="start_date" value="<?= demo_e($policy['start_date'] ?? '') ?>">
                </div>

                <div>
                    <label class="form-label" for="edit-end-date">Fin</label>
                    <input class="input" type="date" id="edit-end-date" name="end_date" value="<?= demo_e($policy['end_date'] ?? '') ?>">
                </div>

                <div style="grid-column: 1 / -1;">
                    <label class="form-label" for="edit-insured-item">Bien asegurado</label>
                    <input class="input" type="text" id="edit-insured-item" name="insured_item" value="<?= demo_e($policy['insured_item'] ?? '') ?>">
                </div>

                <div style="grid-column: 1 / -1;">
                    <label class="form-label" for="edit-notes">Observaciones</label>
                    <textarea class="textarea" id="edit-notes" name="notes"><?= demo_e($policy['notes'] ?? '') ?></textarea>
                </div>
            </form>
        </div>
        <div class="modal__footer">
            <button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button>
            <button type="button" class="btn btn-primary" id="exec-policy-edit-submit">Guardar cambios</button>
        </div>
    </div>
</div>

<div class="modal" id="exec-policy-upload-modal" hidden>
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__dialog modal__dialog--sm">
        <div class="modal__header">
            <h3>Subir documento</h3>
            <button type="button" class="icon-btn" data-modal-close aria-label="Cerrar">✕</button>
        </div>
        <div class="modal__body">
            <p class="exec-policy-form-note">Adjunta un documento demo a esta póliza de tu cartera.</p>

            <form id="exec-policy-upload-form" class="form-grid">
                <input type="hidden" name="action" value="upload_document">
                <input type="hidden" name="policy_id" value="<?= demo_e($policyId) ?>">

                <div>
                    <label class="form-label" for="document-name">Nombre del archivo</label>
                    <input class="input" type="text" id="document-name" name="original_name" placeholder="Endoso_2026.pdf">
                </div>

                <div>
                    <label class="form-label" for="document-type">Tipo de documento</label>
                    <select class="select" id="document-type" name="document_type">
                        <option value="Póliza PDF">Póliza PDF</option>
                        <option value="Anexo">Anexo</option>
                        <option value="Endoso">Endoso</option>
                        <option value="Cotización">Cotización</option>
                    </select>
                </div>
            </form>
        </div>
        <div class="modal__footer">
            <button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button>
            <button type="button" class="btn btn-primary" id="exec-policy-upload-submit">Adjuntar documento</button>
        </div>
    </div>
</div>

<div class="modal" id="exec-policy-action-modal" hidden>
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__dialog modal__dialog--sm">
        <div class="modal__header">
            <h3>Registrar pago demo</h3>
            <button type="button" class="icon-btn" data-modal-close aria-label="Cerrar">✕</button>
        </div>
        <div class="modal__body">
            <p class="exec-policy-form-note">Esta acción solo registrará trazabilidad demo en el historial de la póliza.</p>

            <form id="exec-policy-action-form" class="form-grid">
                <input type="hidden" name="action" value="register_action">
                <input type="hidden" name="policy_id" value="<?= demo_e($policyId) ?>">
                <input type="hidden" name="action_type" value="payment">

                <div>
                    <label class="form-label" for="action-amount">Monto</label>
                    <input class="input" type="number" step="0.01" min="0" id="action-amount" name="amount" placeholder="0.00">
                </div>

                <div>
                    <label class="form-label" for="action-note">Detalle</label>
                    <textarea class="textarea" id="action-note" name="note" placeholder="Comentario breve del pago demo"></textarea>
                </div>
            </form>
        </div>
        <div class="modal__footer">
            <button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button>
            <button type="button" class="btn btn-primary" id="exec-policy-action-submit">Registrar acción</button>
        </div>
    </div>
</div>

<script>
    (() => {
        let policyState = <?= json_encode($policy, JSON_UNESCAPED_UNICODE) ?>;
        const clientsMap = <?= json_encode(array_column($clients, null, 'id'), JSON_UNESCAPED_UNICODE) ?>;
        const insurersMap = <?= json_encode(array_column($insurers, null, 'id'), JSON_UNESCAPED_UNICODE) ?>;
        const typesMap = <?= json_encode(array_column($insuranceTypes, null, 'id'), JSON_UNESCAPED_UNICODE) ?>;

        const endpoint = <?= json_encode(demo_url('ajax/ejecutivo-polizas.php'), JSON_UNESCAPED_UNICODE) ?>;

        const titleNode = document.getElementById('policy-title');
        const subtitleNode = document.getElementById('policy-subtitle');
        const statusBadge = document.getElementById('policy-status-badge');
        const premiumNode = document.getElementById('summary-premium');
        const endDateNode = document.getElementById('summary-end-date');

        const infoClient = document.getElementById('info-client');
        const infoInsurer = document.getElementById('info-insurer');
        const infoType = document.getElementById('info-type');
        const infoStatus = document.getElementById('info-status');
        const infoStartDate = document.getElementById('info-start-date');
        const infoEndDate = document.getElementById('info-end-date');
        const infoInsuredItem = document.getElementById('info-insured-item');
        const infoNotes = document.getElementById('info-notes');

        const documentsList = document.getElementById('documents-list');
        const historyList = document.getElementById('history-list');

        const editForm = document.getElementById('exec-policy-edit-form');
        const editSubmit = document.getElementById('exec-policy-edit-submit');
        const uploadForm = document.getElementById('exec-policy-upload-form');
        const uploadSubmit = document.getElementById('exec-policy-upload-submit');
        const actionForm = document.getElementById('exec-policy-action-form');
        const actionSubmit = document.getElementById('exec-policy-action-submit');

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
            activa: 'success',
            pendiente: 'warning',
            vencida: 'danger',
            anulada: 'danger',
            renovada: 'info'
        }[status] || 'neutral');

        const renderInfo = () => {
            const clientName = clientsMap[policyState.client_id]?.name || 'Cliente';
            const insurerName = insurersMap[policyState.insurer_id]?.name || 'Aseguradora';
            const typeName = typesMap[policyState.insurance_type_id]?.name || 'Tipo';

            titleNode.textContent = policyState.policy_number || 'Póliza';
            subtitleNode.textContent = `${clientName} · ${insurerName} · ${typeName}`;
            statusBadge.className = `badge badge-${badgeTone(policyState.status)}`;
            statusBadge.textContent = (policyState.status || '—').charAt(0).toUpperCase() + (policyState.status || '—').slice(1);

            premiumNode.textContent = formatMoney(policyState.premium, policyState.currency || 'S/');
            endDateNode.textContent = formatDate(policyState.end_date);

            infoClient.textContent = clientName;
            infoInsurer.textContent = insurerName;
            infoType.textContent = typeName;
            infoStatus.textContent = (policyState.status || '—').charAt(0).toUpperCase() + (policyState.status || '—').slice(1);
            infoStartDate.textContent = formatDate(policyState.start_date);
            infoEndDate.textContent = formatDate(policyState.end_date);
            infoInsuredItem.textContent = policyState.insured_item || 'No especificado';
            infoNotes.textContent = policyState.notes || 'Sin observaciones registradas.';
        };

        const appendDocument = (document) => {
            const empty = documentsList.querySelector('.empty-state');
            if (empty) empty.remove();

            const article = document.createElement('article');
            article.className = 'exec-policy-item';
            article.innerHTML = `
                <div class="exec-policy-item__top">
                    <div>
                        <h4>${escapeHtml(document.original_name || 'Documento')}</h4>
                        <p>${escapeHtml(document.type || 'Archivo')}</p>
                    </div>
                    <small>${escapeHtml(formatDateTime(document.created_at || ''))}</small>
                </div>
                <p>Subido por ${escapeHtml(document.uploaded_by_name || 'Sistema')}</p>
            `;
            documentsList.prepend(article);
        };

        const appendHistory = (history) => {
            const empty = historyList.querySelector('.empty-state');
            if (empty) empty.remove();

            const article = document.createElement('article');
            article.className = 'timeline__item';
            article.innerHTML = `
                <h4>${escapeHtml(history.title || 'Historial')}</h4>
                <p>${escapeHtml(history.description || '')}</p>
                <small class="muted">${escapeHtml(formatDateTime(history.created_at || ''))}</small>
            `;
            historyList.prepend(article);
        };

        document.getElementById('btn-edit-policy').addEventListener('click', () => {
            DemoApp.openModal('exec-policy-edit-modal');
        });

        document.getElementById('btn-upload-document').addEventListener('click', () => {
            uploadForm.reset();
            DemoApp.openModal('exec-policy-upload-modal');
        });

        document.getElementById('btn-register-payment').addEventListener('click', () => {
            actionForm.reset();
            DemoApp.openModal('exec-policy-action-modal');
        });

        editSubmit.addEventListener('click', async () => {
            const formData = new FormData(editForm);

            const response = await DemoApp.api(endpoint, {
                method: 'POST',
                body: formData
            });

            if (!response.success) {
                DemoApp.toast({
                    title: response.title || 'No se pudo guardar',
                    message: response.message || 'Verifica la información ingresada.',
                    type: 'error'
                });
                return;
            }

            if (response.policy) {
                policyState = response.policy;
                renderInfo();
            }

            if (response.history) {
                appendHistory(response.history);
            }

            DemoApp.closeModal('exec-policy-edit-modal');
            DemoApp.toast({
                title: response.title || 'Póliza actualizada',
                message: response.message || 'Los cambios fueron guardados correctamente.',
                type: 'success'
            });
        });

        uploadSubmit.addEventListener('click', async () => {
            const formData = new FormData(uploadForm);

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

            if (response.document) {
                appendDocument(response.document);
            }

            if (response.history) {
                appendHistory(response.history);
            }

            DemoApp.closeModal('exec-policy-upload-modal');
            DemoApp.toast({
                title: response.title || 'Documento adjuntado',
                message: response.message || 'El archivo demo fue agregado correctamente.',
                type: 'success'
            });

            uploadForm.reset();
        });

        actionSubmit.addEventListener('click', async () => {
            const formData = new FormData(actionForm);

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

            if (response.history) {
                appendHistory(response.history);
            }

            DemoApp.closeModal('exec-policy-action-modal');
            DemoApp.toast({
                title: response.title || 'Acción registrada',
                message: response.message || 'La trazabilidad demo fue registrada correctamente.',
                type: 'success'
            });

            actionForm.reset();
        });

        renderInfo();
    })();
</script>
<?php
$content = ob_get_clean();

demo_render_internal_layout(
    'Detalle de póliza',
    $content,
    [
        'breadcrumb' => ['Inicio', 'Pólizas', $policy['policy_number'] ?? 'Detalle'],
        'subtitle' => 'Ficha de póliza dentro de tu cartera con cuotas, documentos e historial.',
    ]
);