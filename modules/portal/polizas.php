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

function portal_demo_qr_svg(string $seed): string
{
    $size = 21;
    $cell = 8;
    $padding = 12;
    $svgSize = ($size * $cell) + ($padding * 2);
    $hash = sha1($seed);
    $bits = '';

    foreach (str_split($hash) as $char) {
        $bits .= str_pad(base_convert($char, 16, 2), 4, '0', STR_PAD_LEFT);
    }

    $finder = function (int $x, int $y, int $gridSize): bool {
        $zones = [
            [0, 0],
            [$gridSize - 7, 0],
            [0, $gridSize - 7],
        ];

        foreach ($zones as [$zx, $zy]) {
            if ($x >= $zx && $x < $zx + 7 && $y >= $zy && $y < $zy + 7) {
                $dx = $x - $zx;
                $dy = $y - $zy;
                return (
                    $dx === 0 || $dx === 6 || $dy === 0 || $dy === 6 ||
                    (($dx >= 2 && $dx <= 4) && ($dy >= 2 && $dy <= 4))
                );
            }
        }

        return false;
    };

    $rects = [];
    $bitIndex = 0;

    for ($y = 0; $y < $size; $y++) {
        for ($x = 0; $x < $size; $x++) {
            if ($finder($x, $y, $size)) {
                $fill = '#111827';
            } else {
                $bit = $bits[$bitIndex % strlen($bits)];
                $fill = $bit === '1' ? '#1f2937' : null;
                $bitIndex++;
            }

            if ($fill !== null) {
                $rects[] = '<rect x="' . ($padding + ($x * $cell)) . '" y="' . ($padding + ($y * $cell)) . '" width="' . ($cell - 1) . '" height="' . ($cell - 1) . '" rx="1" fill="' . $fill . '"></rect>';
            }
        }
    }

    return '<svg viewBox="0 0 ' . $svgSize . ' ' . $svgSize . '" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="QR demo">'
        . '<rect x="0" y="0" width="' . $svgSize . '" height="' . $svgSize . '" rx="20" fill="#ffffff"></rect>'
        . '<rect x="6" y="6" width="' . ($svgSize - 12) . '" height="' . ($svgSize - 12) . '" rx="16" fill="#f8fbff" stroke="#dbe3ef"></rect>'
        . implode('', $rects)
        . '</svg>';
}

$policies = demo_store('policies', []);
$documents = demo_store('documents', []);
$installments = demo_store('installments', []);
$insurers = demo_store('insurers', []);
$insuranceTypes = demo_store('insurance_types', []);

$portalPolicies = array_values(array_filter($policies, fn($policy) => (string)($policy['client_id'] ?? '') === $clientId));

$insurerMap = [];
foreach ($insurers as $insurer) {
    $insurerMap[$insurer['id']] = $insurer;
}

$typeMap = [];
foreach ($insuranceTypes as $type) {
    $typeMap[$type['id']] = $type;
}

$policyCards = [];
foreach ($portalPolicies as $policy) {
    $policyId = (string)($policy['id'] ?? '');
    $policyDocuments = array_values(array_filter($documents, fn($doc) => (string)($doc['entity_type'] ?? '') === 'policy' && (string)($doc['entity_id'] ?? '') === $policyId));
    usort($policyDocuments, fn($a, $b) => strtotime((string)($b['created_at'] ?? '')) <=> strtotime((string)($a['created_at'] ?? '')));

    $policyInstallments = array_values(array_filter($installments, fn($item) => (string)($item['policy_id'] ?? '') === $policyId));
    usort($policyInstallments, fn($a, $b) => strtotime((string)($a['due_date'] ?? '')) <=> strtotime((string)($b['due_date'] ?? '')));

    $nextInstallments = array_values(array_filter($policyInstallments, fn($item) => in_array(strtolower((string)($item['status'] ?? '')), ['pendiente', 'vencida', 'en revisión'], true)));
    $nextInstallments = array_slice($nextInstallments, 0, 4);

    $policyCards[] = [
        'id' => $policyId,
        'policy_number' => $policy['policy_number'] ?? '—',
        'insurance_type_id' => $policy['insurance_type_id'] ?? '',
        'insurance_type_name' => $typeMap[$policy['insurance_type_id']]['name'] ?? 'Tipo',
        'insurer_id' => $policy['insurer_id'] ?? '',
        'insurer_name' => $insurerMap[$policy['insurer_id']]['name'] ?? 'Aseguradora',
        'start_date' => $policy['start_date'] ?? '',
        'end_date' => $policy['end_date'] ?? '',
        'status' => $policy['status'] ?? 'activa',
        'premium' => (float)($policy['premium'] ?? 0),
        'currency' => $policy['currency'] ?? 'S/',
        'insured_item' => $policy['insured_item'] ?? 'Cobertura general',
        'notes' => $policy['notes'] ?? '',
        'documents' => array_map(fn($doc) => [
            'original_name' => $doc['original_name'] ?? 'Documento',
            'type' => $doc['type'] ?? 'Archivo',
            'created_at' => $doc['created_at'] ?? '',
        ], $policyDocuments),
        'next_installments' => array_map(fn($item) => [
            'number' => $item['number'] ?? '—',
            'due_date' => $item['due_date'] ?? '',
            'amount' => (float)($item['amount'] ?? 0),
            'status' => $item['status'] ?? 'pendiente',
        ], $nextInstallments),
        'qr_svg' => portal_demo_qr_svg(($policy['policy_number'] ?? 'policy') . '|' . $policyId),
    ];
}

$portalActive = 'polizas';

ob_start();
?>
<style>
    .portal-page-grid {
        display: grid;
        gap: 1rem;
    }

    .portal-filters {
        display: grid;
        gap: 1rem;
        grid-template-columns: 1fr .8fr .8fr auto;
        align-items: end;
        margin-bottom: 1rem;
    }

    .portal-policy-grid {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .portal-policy-card {
        border: 1px solid rgba(219, 227, 239, .9);
        border-radius: 24px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        box-shadow: var(--shadow-sm);
        padding: 1rem;
    }

    .portal-policy-card__top {
        display: flex;
        align-items: start;
        justify-content: space-between;
        gap: .85rem;
        margin-bottom: .75rem;
    }

    .portal-policy-card__title {
        margin: 0;
        font-size: 1rem;
        line-height: 1.2;
    }

    .portal-policy-card__meta {
        margin: .2rem 0 0;
        color: var(--text-soft);
        font-size: .9rem;
        line-height: 1.5;
    }

    .portal-policy-card__stats {
        display: grid;
        gap: .75rem;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        margin: .9rem 0;
    }

    .portal-policy-card__stat {
        padding: .8rem .9rem;
        border-radius: 16px;
        border: 1px solid rgba(219, 227, 239, .85);
        background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
    }

    .portal-policy-card__stat strong {
        display: block;
        margin-bottom: .25rem;
        color: var(--text-soft);
        font-size: .78rem;
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .portal-policy-card__stat span {
        display: block;
        font-size: .98rem;
        font-weight: 700;
        overflow-wrap: anywhere;
    }

    .portal-policy-card__footer {
        display: flex;
        justify-content: flex-end;
    }

    .portal-empty {
        padding: 1.2rem;
        text-align: center;
        color: var(--text-soft);
    }

    .portal-detail-grid {
        display: grid;
        gap: 1rem;
    }

    .portal-detail-head {
        display: grid;
        gap: 1rem;
        grid-template-columns: minmax(0, 1fr) 220px;
        align-items: start;
    }

    .portal-detail-head h3 {
        margin: .2rem 0;
        font-size: 1.25rem;
    }

    .portal-detail-head p {
        margin: 0;
        color: var(--text-soft);
        line-height: 1.55;
    }

    .portal-detail-qr {
        padding: .9rem;
        border-radius: 22px;
        border: 1px solid rgba(219, 227, 239, .9);
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        box-shadow: var(--shadow-sm);
    }

    .portal-detail-qr svg {
        width: 100%;
        height: auto;
        display: block;
    }

    .portal-detail-meta {
        display: grid;
        gap: .9rem;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .portal-detail-meta__item {
        padding: .9rem 1rem;
        border-radius: 18px;
        border: 1px solid rgba(219, 227, 239, .85);
        background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
    }

    .portal-detail-meta__item strong {
        display: block;
        margin-bottom: .35rem;
        color: var(--text-soft);
        font-size: .78rem;
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .portal-detail-meta__item span {
        display: block;
        line-height: 1.5;
        overflow-wrap: anywhere;
    }

    .portal-detail-panels {
        display: grid;
        gap: 1rem;
        grid-template-columns: 1fr 1fr;
    }

    .portal-detail-list {
        display: grid;
        gap: .8rem;
    }

    .portal-detail-item {
        padding: .95rem 1rem;
        border-radius: 18px;
        border: 1px solid rgba(219, 227, 239, .85);
        background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
    }

    .portal-detail-item__top {
        display: flex;
        align-items: start;
        justify-content: space-between;
        gap: .8rem;
        margin-bottom: .35rem;
    }

    .portal-detail-item__top h4 {
        margin: 0;
        font-size: .95rem;
    }

    .portal-detail-item p,
    .portal-detail-item small {
        margin: 0;
        color: var(--text-soft);
        line-height: 1.5;
    }

    .portal-inline-note {
        padding: .9rem 1rem;
        border-radius: 18px;
        border: 1px dashed rgba(100, 116, 139, .26);
        background: linear-gradient(180deg, #fbfdff 0%, #f7faff 100%);
        color: var(--text-soft);
        line-height: 1.55;
    }

    @media (max-width: 1180px) {
        .portal-filters {
            grid-template-columns: 1fr 1fr;
        }

        .portal-policy-grid,
        .portal-detail-panels {
            grid-template-columns: 1fr;
        }

        .portal-filters .btn {
            width: 100%;
        }
    }

    @media (max-width: 900px) {
        .portal-detail-head,
        .portal-detail-meta {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 620px) {
        .portal-filters {
            grid-template-columns: 1fr;
        }

        .portal-policy-card__stats {
            grid-template-columns: 1fr;
        }

        .portal-detail-item__top {
            flex-direction: column;
        }
    }
</style>

<div class="portal-shell">
    <?php require __DIR__ . '/_nav.php'; ?>

    <div class="portal-main">
        <div class="portal-page-grid">
            <section class="card">
                <div class="card__header">
                    <div>
                        <h2 class="card__title">Mis pólizas</h2>
                        <p class="card__subtitle">Revisa tus pólizas activas, vigencias, documentos y próximas cuotas.</p>
                    </div>
                    <?= demo_badge((string)count($policyCards) . ' pólizas', 'info') ?>
                </div>

                <div class="portal-filters">
                    <div>
                        <label class="form-label" for="policy-search">Buscar</label>
                        <input class="input" id="policy-search" type="text" placeholder="Número, aseguradora o tipo">
                    </div>

                    <div>
                        <label class="form-label" for="policy-status-filter">Estado</label>
                        <select class="select" id="policy-status-filter">
                            <option value="">Todos</option>
                            <option value="activa">Activa</option>
                            <option value="pendiente">Pendiente</option>
                            <option value="vencida">Vencida</option>
                            <option value="anulada">Anulada</option>
                            <option value="renovada">Renovada</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label" for="policy-type-filter">Tipo</label>
                        <select class="select" id="policy-type-filter">
                            <option value="">Todos</option>
                            <?php foreach ($insuranceTypes as $type): ?>
                                <option value="<?= demo_e($type['id']) ?>"><?= demo_e($type['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <button type="button" class="btn btn-ghost" id="btn-reset-filters">Limpiar filtros</button>
                    </div>
                </div>

                <div class="portal-policy-grid" id="policy-grid"></div>
                <div id="policy-empty-state" class="portal-empty" hidden>No hay pólizas que coincidan con los filtros seleccionados.</div>
            </section>
        </div>
    </div>
</div>

<div class="modal" id="policy-detail-modal" hidden>
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__dialog">
        <div class="modal__header">
            <h3>Detalle de póliza</h3>
            <button type="button" class="icon-btn" data-modal-close aria-label="Cerrar">✕</button>
        </div>
        <div class="modal__body">
            <div class="portal-detail-grid">
                <div class="portal-detail-head">
                    <div>
                        <span class="badge badge-info" id="detail-status-badge">Activa</span>
                        <h3 id="detail-title">Póliza</h3>
                        <p id="detail-subtitle">Detalle general</p>
                    </div>
                    <div class="portal-detail-qr">
                        <div id="detail-qr"></div>
                    </div>
                </div>

                <div class="portal-detail-meta" id="detail-meta-grid"></div>

                <div class="portal-inline-note">
                    <strong>Coberturas demo</strong>
                    <p class="mt-1" id="detail-coverage-text">Información general de la póliza.</p>
                </div>

                <div class="portal-detail-panels">
                    <div class="card">
                        <div class="card__header">
                            <div>
                                <h3 class="card__title">Documentos</h3>
                                <p class="card__subtitle">Archivos disponibles vinculados a esta póliza.</p>
                            </div>
                        </div>
                        <div class="portal-detail-list" id="detail-documents-list"></div>
                    </div>

                    <div class="card">
                        <div class="card__header">
                            <div>
                                <h3 class="card__title">Próximas cuotas</h3>
                                <p class="card__subtitle">Pagos pendientes o próximos a revisión.</p>
                            </div>
                        </div>
                        <div class="portal-detail-list" id="detail-installments-list"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal__footer">
            <button type="button" class="btn btn-primary" data-modal-close>Cerrar</button>
        </div>
    </div>
</div>

<script>
    (() => {
        const policiesState = <?= json_encode(array_values($policyCards), JSON_UNESCAPED_UNICODE) ?>;
        const grid = document.getElementById('policy-grid');
        const emptyState = document.getElementById('policy-empty-state');

        const searchInput = document.getElementById('policy-search');
        const statusFilter = document.getElementById('policy-status-filter');
        const typeFilter = document.getElementById('policy-type-filter');
        const resetBtn = document.getElementById('btn-reset-filters');

        const detailStatusBadge = document.getElementById('detail-status-badge');
        const detailTitle = document.getElementById('detail-title');
        const detailSubtitle = document.getElementById('detail-subtitle');
        const detailQr = document.getElementById('detail-qr');
        const detailMetaGrid = document.getElementById('detail-meta-grid');
        const detailCoverageText = document.getElementById('detail-coverage-text');
        const detailDocumentsList = document.getElementById('detail-documents-list');
        const detailInstallmentsList = document.getElementById('detail-installments-list');

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
            activa: 'success',
            pendiente: 'warning',
            vencida: 'danger',
            anulada: 'danger',
            renovada: 'info'
        }[String(status || '').toLowerCase()] || 'neutral');

        const getFilteredPolicies = () => {
            const term = searchInput.value.trim().toLowerCase();
            const status = statusFilter.value;
            const type = typeFilter.value;

            return policiesState.filter((policy) => {
                const haystack = [
                    policy.policy_number,
                    policy.insurer_name,
                    policy.insurance_type_name
                ].join(' ').toLowerCase();

                return (!term || haystack.includes(term))
                    && (!status || policy.status === status)
                    && (!type || policy.insurance_type_id === type);
            });
        };

        const renderCards = () => {
            const rows = getFilteredPolicies();
            grid.innerHTML = '';

            if (!rows.length) {
                emptyState.hidden = false;
                return;
            }

            emptyState.hidden = true;

            rows.forEach((policy) => {
                const article = document.createElement('article');
                article.className = 'portal-policy-card';
                article.innerHTML = `
                    <div class="portal-policy-card__top">
                        <div>
                            <h3 class="portal-policy-card__title">${escapeHtml(policy.policy_number || 'Póliza')}</h3>
                            <p class="portal-policy-card__meta">${escapeHtml(policy.insurance_type_name || 'Tipo')} · ${escapeHtml(policy.insurer_name || 'Aseguradora')}</p>
                        </div>
                        <span class="badge badge-${badgeTone(policy.status)}">${escapeHtml((policy.status || '—').charAt(0).toUpperCase() + (policy.status || '—').slice(1))}</span>
                    </div>

                    <div class="portal-policy-card__stats">
                        <div class="portal-policy-card__stat">
                            <strong>Vigencia</strong>
                            <span>${escapeHtml(formatDate(policy.start_date))} al ${escapeHtml(formatDate(policy.end_date))}</span>
                        </div>
                        <div class="portal-policy-card__stat">
                            <strong>Prima</strong>
                            <span>${escapeHtml(formatMoney(policy.premium, policy.currency || 'S/'))}</span>
                        </div>
                    </div>

                    <div class="portal-policy-card__footer">
                        <button type="button" class="btn btn-primary" data-action="detail" data-id="${escapeHtml(policy.id)}">Ver detalle</button>
                    </div>
                `;
                grid.appendChild(article);
            });
        };

        const openPolicyDetail = (policyId) => {
            const policy = policiesState.find((item) => item.id === policyId);
            if (!policy) return;

            detailStatusBadge.className = `badge badge-${badgeTone(policy.status)}`;
            detailStatusBadge.textContent = (policy.status || '—').charAt(0).toUpperCase() + (policy.status || '—').slice(1);
            detailTitle.textContent = policy.policy_number || 'Póliza';
            detailSubtitle.textContent = `${policy.insurance_type_name || 'Tipo'} · ${policy.insurer_name || 'Aseguradora'}`;
            detailQr.innerHTML = policy.qr_svg || '';
            detailCoverageText.textContent = policy.insured_item || policy.notes || 'Cobertura general de la póliza.';

            detailMetaGrid.innerHTML = `
                <div class="portal-detail-meta__item">
                    <strong>Tipo</strong>
                    <span>${escapeHtml(policy.insurance_type_name || '—')}</span>
                </div>
                <div class="portal-detail-meta__item">
                    <strong>Aseguradora</strong>
                    <span>${escapeHtml(policy.insurer_name || '—')}</span>
                </div>
                <div class="portal-detail-meta__item">
                    <strong>Inicio de vigencia</strong>
                    <span>${escapeHtml(formatDate(policy.start_date))}</span>
                </div>
                <div class="portal-detail-meta__item">
                    <strong>Fin de vigencia</strong>
                    <span>${escapeHtml(formatDate(policy.end_date))}</span>
                </div>
                <div class="portal-detail-meta__item">
                    <strong>Estado</strong>
                    <span>${escapeHtml((policy.status || '—').charAt(0).toUpperCase() + (policy.status || '—').slice(1))}</span>
                </div>
                <div class="portal-detail-meta__item">
                    <strong>Prima</strong>
                    <span>${escapeHtml(formatMoney(policy.premium, policy.currency || 'S/'))}</span>
                </div>
            `;

            detailDocumentsList.innerHTML = policy.documents.length
                ? policy.documents.map((doc) => `
                    <article class="portal-detail-item">
                        <div class="portal-detail-item__top">
                            <div>
                                <h4>${escapeHtml(doc.original_name || 'Documento')}</h4>
                                <p>${escapeHtml(doc.type || 'Archivo')}</p>
                            </div>
                            <small>${escapeHtml(formatDate(doc.created_at || ''))}</small>
                        </div>
                    </article>
                `).join('')
                : '<div class="portal-empty">No hay documentos disponibles para esta póliza.</div>';

            detailInstallmentsList.innerHTML = policy.next_installments.length
                ? policy.next_installments.map((item) => `
                    <article class="portal-detail-item">
                        <div class="portal-detail-item__top">
                            <div>
                                <h4>Cuota #${escapeHtml(String(item.number || '—'))}</h4>
                                <p>Vence ${escapeHtml(formatDate(item.due_date || ''))}</p>
                            </div>
                            <small><span class="badge badge-${badgeTone(item.status)}">${escapeHtml((item.status || '—').charAt(0).toUpperCase() + (item.status || '—').slice(1))}</span></small>
                        </div>
                        <p>${escapeHtml(formatMoney(item.amount, policy.currency || 'S/'))}</p>
                    </article>
                `).join('')
                : '<div class="portal-empty">No hay próximas cuotas pendientes para esta póliza.</div>';

            DemoApp.openModal('policy-detail-modal');
        };

        grid.addEventListener('click', (event) => {
            const button = event.target.closest('[data-action="detail"]');
            if (!button) return;
            openPolicyDetail(button.getAttribute('data-id'));
        });

        [searchInput, statusFilter, typeFilter].forEach((element) => {
            element.addEventListener('input', renderCards);
            element.addEventListener('change', renderCards);
        });

        resetBtn.addEventListener('click', () => {
            searchInput.value = '';
            statusFilter.value = '';
            typeFilter.value = '';
            renderCards();
        });

        renderCards();
    })();
</script>
<?php
$content = ob_get_clean();

demo_render_internal_layout(
    'Mis pólizas',
    $content,
    [
        'breadcrumb' => ['Portal', 'Mis pólizas'],
        'subtitle' => 'Consulta de pólizas, documentos, vigencias y próximas cuotas del cliente.',
    ]
);