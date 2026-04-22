<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';

demo_require_roles(['gerente', 'superadmin']);

$policyId = trim((string)($_GET['id'] ?? ''));
$policies = demo_store('policies', []);
$clients = demo_store('clients', []);
$users = demo_store('users', []);
$documents = demo_store('documents', []);
$installments = demo_store('installments', []);
$insurers = demo_store('insurers', []);
$insuranceTypes = demo_store('insurance_types', []);
$policyHistoryStore = demo_store('policy_history', []);

$policy = demo_find_by_id($policies, $policyId);

if (!$policy) {
    ob_start();
    ?>
    <div class="empty-state">
        No se encontró la póliza solicitada en el store demo actual.
        <div class="mt-2">
            <a href="<?= demo_e(demo_url('modules/polizas/index.php')) ?>" class="btn btn-primary">Volver al listado</a>
        </div>
    </div>
    <?php
    $content = ob_get_clean();

    demo_render_internal_layout(
        'Ficha de póliza',
        $content,
        [
            'breadcrumb' => ['Inicio', 'Pólizas', 'Detalle'],
            'subtitle' => 'La póliza solicitada no existe en esta sesión.',
        ]
    );
    return;
}

$client = demo_find_by_id($clients, (string)($policy['client_id'] ?? ''));
$executive = demo_find_by_id($users, (string)($policy['assigned_executive_user_id'] ?? ''));
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
        'id' => 'seed-' . $policyId . '-1',
        'policy_id' => $policyId,
        'title' => 'Póliza registrada',
        'description' => 'Se registró la póliza ' . ($policy['policy_number'] ?? '—') . ' para ' . ($client['name'] ?? 'cliente') . '.',
        'created_at' => $policy['created_at'] ?? date('Y-m-d H:i:s'),
    ];
}

foreach ($policyDocuments as $document) {
    $historyItems[] = [
        'id' => 'doc-' . ($document['id'] ?? ''),
        'policy_id' => $policyId,
        'title' => 'Documento adjuntado',
        'description' => ($document['original_name'] ?? 'Documento') . ' fue agregado al expediente de la póliza.',
        'created_at' => $document['created_at'] ?? date('Y-m-d H:i:s'),
    ];
}

foreach ($policyInstallments as $installment) {
    if (strtolower((string)($installment['status'] ?? '')) === 'pagada') {
        $historyItems[] = [
            'id' => 'inst-' . ($installment['id'] ?? ''),
            'policy_id' => $policyId,
            'title' => 'Cuota pagada',
            'description' => 'La cuota #' . ($installment['number'] ?? '—') . ' fue registrada como pagada.',
            'created_at' => ($installment['due_date'] ?? date('Y-m-d')) . ' 09:00:00',
        ];
    }
}

usort($historyItems, fn($a, $b) => strtotime((string)($b['created_at'] ?? '')) <=> strtotime((string)($a['created_at'] ?? '')));
$historyItems = array_slice($historyItems, 0, 12);

$nextDue = !empty($pendingInstallments) ? $pendingInstallments[0]['due_date'] : null;
$paidCount = count($paidInstallments);
$pendingCount = count($pendingInstallments);

ob_start();
?>
<style>
    .policy-detail-grid {
        display: grid;
        gap: 1rem;
    }

    .policy-hero {
        display: grid;
        gap: 1rem;
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: center;
    }

    .policy-hero__meta h2 {
        margin: .25rem 0;
        font-size: clamp(1.45rem, 2vw, 2rem);
        line-height: 1.1;
    }

    .policy-hero__meta p {
        margin: 0;
        color: var(--text-soft);
        line-height: 1.5;
    }

    .policy-hero__actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: .65rem;
    }

    .policy-summary {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .policy-meta-grid {
        display: grid;
        gap: .9rem;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .policy-meta-item {
        padding: .95rem 1rem;
        border-radius: 18px;
        border: 1px solid rgba(219, 227, 239, .85);
        background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
    }

    .policy-meta-item strong {
        display: block;
        margin-bottom: .35rem;
        color: var(--text-soft);
        font-size: .8rem;
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .policy-meta-item span {
        display: block;
        line-height: 1.5;
        overflow-wrap: anywhere;
    }

    .policy-list {
        display: grid;
        gap: .8rem;
    }

    .policy-doc-item {
        padding: .95rem 1rem;
        border-radius: 18px;
        border: 1px solid rgba(219, 227, 239, .85);
        background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
    }

    .policy-doc-item__top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: .75rem;
        margin-bottom: .35rem;
    }

    .policy-doc-item h4 {
        margin: 0;
        font-size: .95rem;
    }

    .policy-doc-item p,
    .policy-doc-item small {
        margin: 0;
        color: var(--text-soft);
        line-height: 1.5;
    }

    .policy-note {
        padding: .85rem 1rem;
        border-radius: 16px;
        border: 1px dashed rgba(100, 116, 139, .28);
        background: linear-gradient(180deg, #fbfdff 0%, #f7faff 100%);
    }

    .policy-tab-panels > div[hidden] {
        display: none !important;
    }

    @media (max-width: 1100px) {
        .policy-summary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 920px) {
        .policy-hero {
            grid-template-columns: 1fr;
        }

        .policy-hero__actions {
            justify-content: flex-start;
        }

        .policy-meta-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 680px) {
        .policy-summary {
            grid-template-columns: 1fr;
        }

        .policy-doc-item__top {
            flex-direction: column;
        }
    }
</style>

<div class="policy-detail-grid">
    <section class="card">
        <div class="policy-hero">
            <div class="policy-hero__meta">
                <span class="badge badge-<?= demo_e(match (($policy['status'] ?? '')) {
                    'activa' => 'success',
                    'pendiente' => 'warning',
                    'vencida', 'anulada' => 'danger',
                    'renovada' => 'info',
                    default => 'neutral',
                }) ?>">
                    <?= demo_e(ucfirst((string)($policy['status'] ?? '—'))) ?>
                </span>
                <h2><?= demo_e($policy['policy_number'] ?? 'Póliza') ?></h2>
                <p><?= demo_e($client['name'] ?? 'Cliente') ?> · <?= demo_e($insurer['name'] ?? 'Aseguradora') ?> · <?= demo_e($type['name'] ?? 'Tipo de seguro') ?></p>
            </div>

            <div class="policy-hero__actions">
                <button type="button" class="btn btn-ghost" id="btn-edit-policy">Editar póliza</button>
                <button type="button" class="btn btn-secondary" id="btn-upload-document">Subir documento</button>
                <button type="button" class="btn btn-ghost" id="btn-register-payment">Registrar pago</button>
                <button type="button" class="btn btn-primary" id="btn-renew-policy">Renovar</button>
            </div>
        </div>
    </section>

    <section class="policy-summary">
        <article class="card kpi-card">
            <p class="kpi-card__label">Prima total</p>
            <h3 class="kpi-card__value" id="summary-premium"><?= demo_e(demo_money((float)($policy['premium'] ?? 0), (string)($policy['currency'] ?? 'S/'))) ?></h3>
            <p class="kpi-card__meta">Monto total demo asociado a esta póliza.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Cuotas pagadas</p>
            <h3 class="kpi-card__value" id="summary-paid-installments"><?= demo_e((string)$paidCount) ?></h3>
            <p class="kpi-card__meta">Cuotas marcadas como pagadas dentro del store actual.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Próximas cuotas</p>
            <h3 class="kpi-card__value" id="summary-pending-installments"><?= demo_e((string)$pendingCount) ?></h3>
            <p class="kpi-card__meta">Pendientes, vencidas y en revisión para seguimiento.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Fin de vigencia</p>
            <h3 class="kpi-card__value" id="summary-end-date" style="font-size: 1.45rem;"><?= demo_e(demo_date((string)($policy['end_date'] ?? null))) ?></h3>
            <p class="kpi-card__meta">Siguiente revisión comercial estimada.</p>
        </article>
    </section>

    <section class="card">
        <div class="tab-nav" data-tab-group>
            <button type="button" class="tab-btn is-active" data-tab-button="info">Información general</button>
            <button type="button" class="tab-btn" data-tab-button="cuotas">Cuotas</button>
            <button type="button" class="tab-btn" data-tab-button="documentos">Documentos</button>
            <button type="button" class="tab-btn" data-tab-button="historial">Historial</button>
        </div>

        <div class="policy-tab-panels" data-tab-group>
            <div data-tab-panel="info">
                <div class="policy-meta-grid">
                    <div class="policy-meta-item">
                        <strong>Cliente</strong>
                        <span id="info-client"><?= demo_e($client['name'] ?? '—') ?></span>
                    </div>
                    <div class="policy-meta-item">
                        <strong>Aseguradora</strong>
                        <span id="info-insurer"><?= demo_e($insurer['name'] ?? '—') ?></span>
                    </div>
                    <div class="policy-meta-item">
                        <strong>Tipo de seguro</strong>
                        <span id="info-type"><?= demo_e($type['name'] ?? '—') ?></span>
                    </div>
                    <div class="policy-meta-item">
                        <strong>Ejecutivo asignado</strong>
                        <span id="info-executive"><?= demo_e($executive['full_name'] ?? 'Sin asignar') ?></span>
                    </div>
                    <div class="policy-meta-item">
                        <strong>Inicio de vigencia</strong>
                        <span id="info-start-date"><?= demo_e(demo_date((string)($policy['start_date'] ?? null))) ?></span>
                    </div>
                    <div class="policy-meta-item">
                        <strong>Fin de vigencia</strong>
                        <span id="info-end-date"><?= demo_e(demo_date((string)($policy['end_date'] ?? null))) ?></span>
                    </div>
                    <div class="policy-meta-item">
                        <strong>Bien asegurado</strong>
                        <span id="info-insured-item"><?= demo_e($policy['insured_item'] ?? 'No especificado') ?></span>
                    </div>
                    <div class="policy-meta-item">
                        <strong>Estado</strong>
                        <span id="info-status"><?= demo_e(ucfirst((string)($policy['status'] ?? '—'))) ?></span>
                    </div>
                </div>

                <div class="policy-note mt-2">
                    <strong>Observaciones</strong>
                    <p class="muted mt-1" id="info-notes"><?= demo_e($policy['notes'] ?? 'Sin observaciones registradas.') ?></p>
                </div>
            </div>

            <div data-tab-panel="cuotas" hidden>
                <?php if (empty($policyInstallments)): ?>
                    <div class="empty-state">Esta póliza aún no tiene cuotas generadas en el demo.</div>
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
                            <tbody id="installments-table-body">
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
                    <?php if ($nextDue): ?>
                        <p class="muted mt-2">Próximo vencimiento estimado: <?= demo_e(demo_date((string)$nextDue)) ?></p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div data-tab-panel="documentos" hidden>
                <div class="policy-list" id="policy-documents-list">
                    <?php if (empty($policyDocuments)): ?>
                        <div class="empty-state">No hay documentos asociados a esta póliza.</div>
                    <?php else: ?>
                        <?php foreach ($policyDocuments as $document): ?>
                            <article class="policy-doc-item">
                                <div class="policy-doc-item__top">
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
                <div class="timeline" id="policy-history-timeline">
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

<div class="modal" id="detail-edit-modal" hidden>
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__dialog">
        <div class="modal__header">
            <h3>Editar póliza</h3>
            <button type="button" class="icon-btn" data-modal-close aria-label="Cerrar">✕</button>
        </div>
        <div class="modal__body">
            <p class="policies-form-note">Actualiza los datos principales de esta póliza. Los cambios se guardarán de forma simulada dentro de la sesión actual.</p>

            <form id="detail-edit-form" class="form-grid form-grid--3">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="policy_id" value="<?= demo_e($policyId) ?>">

                <div>
                    <label class="form-label" for="detail-policy-number">Número de póliza</label>
                    <input class="input" type="text" id="detail-policy-number" name="policy_number" value="<?= demo_e($policy['policy_number'] ?? '') ?>">
                </div>

                <div>
                    <label class="form-label" for="detail-client">Cliente</label>
                    <select class="select" id="detail-client" name="client_id">
                        <?php foreach ($clients as $clientItem): ?>
                            <option value="<?= demo_e($clientItem['id']) ?>" <?= ($clientItem['id'] ?? '') === ($policy['client_id'] ?? '') ? 'selected' : '' ?>>
                                <?= demo_e($clientItem['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label" for="detail-executive">Ejecutivo</label>
                    <select class="select" id="detail-executive" name="assigned_executive_user_id">
                        <option value="">Sin asignar</option>
                        <?php foreach (array_values(array_filter($users, fn($u) => ($u['role'] ?? '') === 'ejecutivo')) as $execItem): ?>
                            <option value="<?= demo_e($execItem['id']) ?>" <?= ($execItem['id'] ?? '') === ($policy['assigned_executive_user_id'] ?? '') ? 'selected' : '' ?>>
                                <?= demo_e($execItem['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label" for="detail-insurer">Aseguradora</label>
                    <select class="select" id="detail-insurer" name="insurer_id">
                        <?php foreach ($insurers as $insurerItem): ?>
                            <option value="<?= demo_e($insurerItem['id']) ?>" <?= ($insurerItem['id'] ?? '') === ($policy['insurer_id'] ?? '') ? 'selected' : '' ?>>
                                <?= demo_e($insurerItem['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label" for="detail-type">Tipo de seguro</label>
                    <select class="select" id="detail-type" name="insurance_type_id">
                        <?php foreach ($insuranceTypes as $typeItem): ?>
                            <option value="<?= demo_e($typeItem['id']) ?>" <?= ($typeItem['id'] ?? '') === ($policy['insurance_type_id'] ?? '') ? 'selected' : '' ?>>
                                <?= demo_e($typeItem['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label" for="detail-status">Estado</label>
                    <select class="select" id="detail-status" name="status">
                        <?php foreach (['activa', 'pendiente', 'vencida', 'anulada'] as $statusOption): ?>
                            <option value="<?= demo_e($statusOption) ?>" <?= ($statusOption === ($policy['status'] ?? '')) ? 'selected' : '' ?>>
                                <?= demo_e(ucfirst($statusOption)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label" for="detail-start-date">Inicio</label>
                    <input class="input" type="date" id="detail-start-date" name="start_date" value="<?= demo_e($policy['start_date'] ?? '') ?>">
                </div>

                <div>
                    <label class="form-label" for="detail-end-date">Fin</label>
                    <input class="input" type="date" id="detail-end-date" name="end_date" value="<?= demo_e($policy['end_date'] ?? '') ?>">
                </div>

                <div>
                    <label class="form-label" for="detail-premium">Prima</label>
                    <input class="input" type="number" step="0.01" min="0" id="detail-premium" name="premium" value="<?= demo_e((string)($policy['premium'] ?? '')) ?>">
                </div>

                <div style="grid-column: 1 / -1;">
                    <label class="form-label" for="detail-insured-item">Bien asegurado</label>
                    <input class="input" type="text" id="detail-insured-item" name="insured_item" value="<?= demo_e($policy['insured_item'] ?? '') ?>">
                </div>

                <div style="grid-column: 1 / -1;">
                    <label class="form-label" for="detail-notes">Observaciones</label>
                    <textarea class="textarea" id="detail-notes" name="notes"><?= demo_e($policy['notes'] ?? '') ?></textarea>
                </div>
            </form>
        </div>
        <div class="modal__footer">
            <button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button>
            <button type="button" class="btn btn-primary" id="detail-edit-submit">Guardar cambios</button>
        </div>
    </div>
</div>

<div class="modal" id="detail-upload-modal" hidden>
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__dialog modal__dialog--sm">
        <div class="modal__header">
            <h3>Subir documento</h3>
            <button type="button" class="icon-btn" data-modal-close aria-label="Cerrar">✕</button>
        </div>
        <div class="modal__body">
            <p class="policies-form-note">Adjunta un documento simulado a esta póliza para enriquecer el expediente demo.</p>

            <form id="detail-upload-form" class="form-grid">
                <input type="hidden" name="action" value="upload_document">
                <input type="hidden" name="policy_id" value="<?= demo_e($policyId) ?>">

                <div>
                    <label class="form-label" for="detail-original-name">Nombre del archivo</label>
                    <input class="input" type="text" id="detail-original-name" name="original_name" placeholder="Endoso Abril 2026.pdf">
                </div>

                <div>
                    <label class="form-label" for="detail-document-type">Tipo de documento</label>
                    <select class="select" id="detail-document-type" name="document_type">
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
            <button type="button" class="btn btn-primary" id="detail-upload-submit">Adjuntar documento</button>
        </div>
    </div>
</div>

<script>
    (() => {
        let policyState = <?= json_encode($policy, JSON_UNESCAPED_UNICODE) ?>;
        let documentsState = <?= json_encode(array_values($policyDocuments), JSON_UNESCAPED_UNICODE) ?>;
        let historyState = <?= json_encode(array_values($historyItems), JSON_UNESCAPED_UNICODE) ?>;

        const endpoint = <?= json_encode(demo_url('ajax/polizas.php'), JSON_UNESCAPED_UNICODE) ?>;
        const insurersMap = <?= json_encode(array_column($insurers, null, 'id'), JSON_UNESCAPED_UNICODE) ?>;
        const typesMap = <?= json_encode(array_column($insuranceTypes, null, 'id'), JSON_UNESCAPED_UNICODE) ?>;
        const executivesMap = <?= json_encode(array_column(array_values(array_filter($users, fn($u) => ($u['role'] ?? '') === 'ejecutivo')), null, 'id'), JSON_UNESCAPED_UNICODE) ?>;
        const clientsMap = <?= json_encode(array_column($clients, null, 'id'), JSON_UNESCAPED_UNICODE) ?>;

        const btnEdit = document.getElementById('btn-edit-policy');
        const btnUpload = document.getElementById('btn-upload-document');
        const btnPayment = document.getElementById('btn-register-payment');
        const btnRenew = document.getElementById('btn-renew-policy');

        const editForm = document.getElementById('detail-edit-form');
        const editSubmit = document.getElementById('detail-edit-submit');
        const uploadForm = document.getElementById('detail-upload-form');
        const uploadSubmit = document.getElementById('detail-upload-submit');

        const summaryPremium = document.getElementById('summary-premium');
        const summaryEndDate = document.getElementById('summary-end-date');

        const infoClient = document.getElementById('info-client');
        const infoInsurer = document.getElementById('info-insurer');
        const infoType = document.getElementById('info-type');
        const infoExecutive = document.getElementById('info-executive');
        const infoStartDate = document.getElementById('info-start-date');
        const infoEndDate = document.getElementById('info-end-date');
        const infoStatus = document.getElementById('info-status');
        const infoInsuredItem = document.getElementById('info-insured-item');
        const infoNotes = document.getElementById('info-notes');

        const docsList = document.getElementById('policy-documents-list');
        const historyTimeline = document.getElementById('policy-history-timeline');

        const escapeHtml = (value) => String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');

        const formatDate = (value) => {
            if (!value) return '—';
            const date = new Date(value.replace(' ', 'T'));
            if (Number.isNaN(date.getTime())) return value;
            return date.toLocaleDateString('es-PE');
        };

        const formatDateTime = (value) => {
            if (!value) return '—';
            const date = new Date(value.replace(' ', 'T'));
            if (Number.isNaN(date.getTime())) return value;
            return date.toLocaleDateString('es-PE') + ' ' + date.toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' });
        };

        const formatMoney = (value, currency = 'S/') => {
            const numeric = Number(value || 0);
            return `${currency} ${numeric.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        };

        const renderInfo = () => {
            summaryPremium.textContent = formatMoney(policyState.premium, policyState.currency || 'S/');
            summaryEndDate.textContent = formatDate(policyState.end_date);

            infoClient.textContent = clientsMap[policyState.client_id]?.name || '—';
            infoInsurer.textContent = insurersMap[policyState.insurer_id]?.name || '—';
            infoType.textContent = typesMap[policyState.insurance_type_id]?.name || '—';
            infoExecutive.textContent = executivesMap[policyState.assigned_executive_user_id]?.full_name || 'Sin asignar';
            infoStartDate.textContent = formatDate(policyState.start_date);
            infoEndDate.textContent = formatDate(policyState.end_date);
            infoStatus.textContent = (policyState.status || '—').charAt(0).toUpperCase() + (policyState.status || '—').slice(1);
            infoInsuredItem.textContent = policyState.insured_item || 'No especificado';
            infoNotes.textContent = policyState.notes || 'Sin observaciones registradas.';
        };

        const appendHistory = (item) => {
            const empty = historyTimeline.querySelector('.empty-state');
            if (empty) empty.remove();

            const article = document.createElement('article');
            article.className = 'timeline__item';
            article.innerHTML = `
                <h4>${escapeHtml(item.title || 'Historial')}</h4>
                <p>${escapeHtml(item.description || '')}</p>
                <small class="muted">${escapeHtml(formatDateTime(item.created_at || ''))}</small>
            `;
            historyTimeline.prepend(article);
        };

        const appendDocument = (documentItem) => {
            const empty = docsList.querySelector('.empty-state');
            if (empty) empty.remove();

            const article = document.createElement('article');
            article.className = 'policy-doc-item';
            article.innerHTML = `
                <div class="policy-doc-item__top">
                    <div>
                        <h4>${escapeHtml(documentItem.original_name || 'Documento')}</h4>
                        <p>${escapeHtml(documentItem.type || 'Archivo')}</p>
                    </div>
                    <small>${escapeHtml(formatDateTime(documentItem.created_at || ''))}</small>
                </div>
                <p>Subido por ${escapeHtml(documentItem.uploaded_by_name || 'Sistema')}</p>
            `;
            docsList.prepend(article);
        };

        btnEdit.addEventListener('click', () => {
            DemoApp.openModal('detail-edit-modal');
        });

        btnUpload.addEventListener('click', () => {
            uploadForm.reset();
            DemoApp.openModal('detail-upload-modal');
        });

        btnPayment.addEventListener('click', () => {
            DemoApp.toast({
                title: 'Registrar pago',
                message: 'La acción demo está lista para conectarse al módulo de cobranzas.',
                type: 'info'
            });
        });

        btnRenew.addEventListener('click', () => {
            DemoApp.confirm({
                title: 'Renovar póliza',
                message: '¿Deseas renovar esta póliza de forma demo y extender su vigencia un año más?',
                onAccept: async () => {
                    const formData = new FormData();
                    formData.append('action', 'renew');
                    formData.append('policy_id', <?= json_encode($policyId, JSON_UNESCAPED_UNICODE) ?>);

                    const response = await DemoApp.api(endpoint, {
                        method: 'POST',
                        body: formData
                    });

                    if (!response.success) {
                        DemoApp.toast({
                            title: response.title || 'No se pudo renovar',
                            message: response.message || 'La póliza no pudo renovarse.',
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

                    DemoApp.toast({
                        title: response.title || 'Póliza renovada',
                        message: response.message || 'La vigencia fue extendida correctamente en el demo.',
                        type: 'success'
                    });
                }
            });
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

            DemoApp.closeModal('detail-edit-modal');
            DemoApp.toast({
                title: response.title || 'Póliza actualizada',
                message: response.message || 'Los cambios fueron aplicados en la sesión actual.',
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
                documentsState.unshift(response.document);
                appendDocument(response.document);
            }

            if (response.history) {
                appendHistory(response.history);
            }

            DemoApp.closeModal('detail-upload-modal');
            DemoApp.toast({
                title: response.title || 'Documento adjuntado',
                message: response.message || 'El documento fue agregado correctamente.',
                type: 'success'
            });

            uploadForm.reset();
        });

        renderInfo();
    })();
</script>
<?php
$content = ob_get_clean();

demo_render_internal_layout(
    'Ficha de póliza',
    $content,
    [
        'breadcrumb' => ['Inicio', 'Pólizas', $policy['policy_number'] ?? 'Detalle'],
        'subtitle' => 'Detalle operativo de póliza, documentos, cuotas e historial de gestión.',
    ]
);