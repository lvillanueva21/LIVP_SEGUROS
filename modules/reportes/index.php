<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';

demo_require_roles(['gerente', 'superadmin']);

$clients = array_values(demo_store('clients', []));
$policies = array_values(demo_store('policies', []));
$installments = array_values(demo_store('installments', []));
$claims = array_values(demo_store('claims', []));
$users = array_values(demo_store('users', []));
$insuranceTypes = array_values(demo_store('insurance_types', []));

$executives = array_values(array_filter($users, fn($u) => ($u['role'] ?? '') === 'ejecutivo'));

$clientMap = [];
foreach ($clients as $client) {
    $clientMap[$client['id']] = $client;
}

$typeMap = [];
foreach ($insuranceTypes as $type) {
    $typeMap[$type['id']] = $type;
}

$executiveMap = [];
foreach ($executives as $exec) {
    $executiveMap[$exec['id']] = $exec;
}

$totalClients = count($clients);
$totalPolicies = count($policies);
$totalPremium = array_reduce($policies, fn($carry, $item) => $carry + (float)($item['premium'] ?? 0), 0.0);
$totalOverdue = count(array_filter($installments, fn($item) => strtolower((string)($item['status'] ?? '')) === 'vencida'));

$printBaseUrl = demo_url('modules/reportes/imprimir.php');

ob_start();
?>
<style>
    .reports-kpis {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        margin-bottom: 1rem;
    }

    .reports-toolbar {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(3, minmax(0, 1fr)) auto auto;
        align-items: end;
        margin-bottom: 1rem;
    }

    .reports-inline-note {
        padding: .9rem 1rem;
        border-radius: 16px;
        border: 1px dashed rgba(100, 116, 139, .28);
        background: linear-gradient(180deg, #fbfdff 0%, #f7faff 100%);
        margin-bottom: 1rem;
    }

    .reports-inline-note strong {
        display: block;
        margin-bottom: .2rem;
    }

    .reports-grid {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        margin-bottom: 1rem;
    }

    .chart-card {
        min-height: 360px;
    }

    .chart-area {
        display: grid;
        gap: 1rem;
        align-items: end;
        height: 240px;
        grid-auto-flow: column;
        grid-auto-columns: minmax(70px, 1fr);
        padding-top: 1rem;
    }

    .chart-bar {
        display: flex;
        flex-direction: column;
        justify-content: end;
        gap: .55rem;
        min-width: 0;
    }

    .chart-bar__track {
        position: relative;
        display: flex;
        align-items: end;
        justify-content: center;
        height: 180px;
        border-radius: 18px 18px 12px 12px;
        background: linear-gradient(180deg, #f5f8ff 0%, #eef4fb 100%);
        border: 1px solid rgba(219, 227, 239, .85);
        overflow: hidden;
    }

    .chart-bar__fill {
        width: 100%;
        min-height: 6px;
        border-radius: 14px 14px 0 0;
        background: linear-gradient(180deg, rgba(79, 70, 229, .86) 0%, rgba(14, 165, 164, .88) 100%);
        box-shadow: inset 0 1px 0 rgba(255,255,255,.25);
    }

    .chart-bar__value {
        position: absolute;
        top: .55rem;
        left: 50%;
        transform: translateX(-50%);
        font-size: .82rem;
        font-weight: 800;
        color: var(--text);
        white-space: nowrap;
    }

    .chart-bar__label {
        font-size: .82rem;
        color: var(--text-soft);
        text-align: center;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }

    .chart-empty {
        display: grid;
        place-items: center;
        min-height: 240px;
        color: var(--text-soft);
        text-align: center;
        border: 1px dashed rgba(100, 116, 139, .28);
        border-radius: 18px;
        background: linear-gradient(180deg, #fbfdff 0%, #f7faff 100%);
    }

    .reports-summary-header {
        display: grid;
        gap: 1rem;
        grid-template-columns: 1fr auto;
        align-items: center;
        margin-bottom: 1rem;
    }

    .reports-filter-pill {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        min-height: 30px;
        padding: .35rem .75rem;
        border-radius: 999px;
        background: rgba(79, 70, 229, .08);
        color: var(--primary);
        font-size: .82rem;
        font-weight: 700;
    }

    .reports-empty {
        padding: 1.1rem;
        text-align: center;
        color: var(--text-soft);
    }

    @media (max-width: 1220px) {
        .reports-kpis {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .reports-toolbar,
        .reports-grid {
            grid-template-columns: 1fr 1fr;
        }

        .reports-toolbar .btn {
            width: 100%;
        }

        .reports-grid .chart-card:last-child {
            grid-column: 1 / -1;
        }
    }

    @media (max-width: 760px) {
        .reports-kpis,
        .reports-toolbar,
        .reports-grid,
        .reports-summary-header {
            grid-template-columns: 1fr;
        }

        .chart-area {
            grid-auto-columns: minmax(52px, 1fr);
        }
    }
</style>

<div class="grid" style="gap: 1rem;">
    <section class="reports-kpis">
        <article class="card kpi-card">
            <p class="kpi-card__label">Total clientes</p>
            <h3 class="kpi-card__value" id="kpi-total-clients"><?= demo_e((string)$totalClients) ?></h3>
            <p class="kpi-card__meta">Clientes relacionados con la cartera filtrada.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Total pólizas</p>
            <h3 class="kpi-card__value" id="kpi-total-policies"><?= demo_e((string)$totalPolicies) ?></h3>
            <p class="kpi-card__meta">Pólizas incluidas en el reporte actual.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Primas demo</p>
            <h3 class="kpi-card__value" id="kpi-total-premium"><?= demo_e(demo_money($totalPremium)) ?></h3>
            <p class="kpi-card__meta">Monto total de primas según filtros aplicados.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Cuotas vencidas</p>
            <h3 class="kpi-card__value" id="kpi-overdue-installments"><?= demo_e((string)$totalOverdue) ?></h3>
            <p class="kpi-card__meta">Cuotas con estado vencida dentro del alcance del reporte.</p>
        </article>
    </section>

    <section class="card">
        <div class="card__header">
            <div>
                <h2 class="card__title">Reportes gerenciales</h2>
                <p class="card__subtitle">Vista ejecutiva de cartera, vencimientos y distribución comercial del demo.</p>
            </div>
            <span class="reports-filter-pill" id="report-filter-pill">Filtro actual: Todo</span>
        </div>

        <div class="reports-inline-note">
            <strong>PDF sin librerías</strong>
            <span class="muted">La exportación usa una vista imprimible real del navegador. Puedes guardarla como PDF desde imprimir o usar el botón de exportación demo.</span>
        </div>

        <div class="reports-toolbar">
            <div>
                <label class="form-label" for="report-period">Periodo</label>
                <select class="select" id="report-period">
                    <option value="all">Todo</option>
                    <option value="30d">Últimos 30 días</option>
                    <option value="90d">Últimos 90 días</option>
                    <option value="12m">Últimos 12 meses</option>
                </select>
            </div>

            <div>
                <label class="form-label" for="report-executive">Ejecutivo</label>
                <select class="select" id="report-executive">
                    <option value="">Todos</option>
                    <?php foreach ($executives as $exec): ?>
                        <option value="<?= demo_e($exec['id']) ?>"><?= demo_e($exec['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="form-label" for="report-type">Tipo de seguro</label>
                <select class="select" id="report-type">
                    <option value="">Todos</option>
                    <?php foreach ($insuranceTypes as $type): ?>
                        <option value="<?= demo_e($type['id']) ?>"><?= demo_e($type['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="button" class="btn btn-ghost" id="btn-view-printable">Ver reporte imprimible</button>
            <button type="button" class="btn btn-primary" id="btn-export-pdf">Exportar PDF demo</button>
        </div>
    </section>

    <section class="reports-grid">
        <article class="card chart-card">
            <div class="card__header">
                <div>
                    <h3 class="card__title">Pólizas por tipo de seguro</h3>
                    <p class="card__subtitle">Distribución comercial del portafolio filtrado.</p>
                </div>
            </div>
            <div id="chart-types"></div>
        </article>

        <article class="card chart-card">
            <div class="card__header">
                <div>
                    <h3 class="card__title">Cartera por ejecutivo</h3>
                    <p class="card__subtitle">Pólizas asignadas por responsable comercial.</p>
                </div>
            </div>
            <div id="chart-executives"></div>
        </article>

        <article class="card chart-card">
            <div class="card__header">
                <div>
                    <h3 class="card__title">Vencimientos por mes</h3>
                    <p class="card__subtitle">Políticas agrupadas según fin de vigencia.</p>
                </div>
            </div>
            <div id="chart-expirations"></div>
        </article>
    </section>

    <section class="card">
        <div class="reports-summary-header">
            <div>
                <h3 class="card__title">Resumen por ejecutivo</h3>
                <p class="card__subtitle">Clientes, pólizas, primas y siniestros según el filtro aplicado.</p>
            </div>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Ejecutivo</th>
                        <th>Clientes</th>
                        <th>Pólizas</th>
                        <th>Primas</th>
                        <th>Siniestros</th>
                    </tr>
                </thead>
                <tbody id="executive-summary-body"></tbody>
            </table>
        </div>

        <div id="executive-summary-empty" class="reports-empty" hidden>
            No hay información suficiente para construir el resumen con los filtros seleccionados.
        </div>
    </section>
</div>

<script>
    (() => {
        const policies = <?= json_encode($policies, JSON_UNESCAPED_UNICODE) ?>;
        const clients = <?= json_encode($clients, JSON_UNESCAPED_UNICODE) ?>;
        const installments = <?= json_encode($installments, JSON_UNESCAPED_UNICODE) ?>;
        const claims = <?= json_encode($claims, JSON_UNESCAPED_UNICODE) ?>;
        const executives = <?= json_encode($executives, JSON_UNESCAPED_UNICODE) ?>;
        const insuranceTypes = <?= json_encode($insuranceTypes, JSON_UNESCAPED_UNICODE) ?>;
        const typeMap = <?= json_encode($typeMap, JSON_UNESCAPED_UNICODE) ?>;
        const executiveMap = <?= json_encode($executiveMap, JSON_UNESCAPED_UNICODE) ?>;
        const printBaseUrl = <?= json_encode($printBaseUrl, JSON_UNESCAPED_UNICODE) ?>;

        const periodSelect = document.getElementById('report-period');
        const executiveSelect = document.getElementById('report-executive');
        const typeSelect = document.getElementById('report-type');

        const filterPill = document.getElementById('report-filter-pill');

        const kpiClients = document.getElementById('kpi-total-clients');
        const kpiPolicies = document.getElementById('kpi-total-policies');
        const kpiPremium = document.getElementById('kpi-total-premium');
        const kpiOverdue = document.getElementById('kpi-overdue-installments');

        const chartTypes = document.getElementById('chart-types');
        const chartExecutives = document.getElementById('chart-executives');
        const chartExpirations = document.getElementById('chart-expirations');

        const summaryBody = document.getElementById('executive-summary-body');
        const summaryEmpty = document.getElementById('executive-summary-empty');

        const btnViewPrintable = document.getElementById('btn-view-printable');
        const btnExportPdf = document.getElementById('btn-export-pdf');

        const today = new Date();
        const todayTs = new Date(today.getFullYear(), today.getMonth(), today.getDate()).getTime();

        const escapeHtml = (value) => String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');

        const formatMoney = (value, currency = 'S/') => {
            const numeric = Number(value || 0);
            return `${currency} ${numeric.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        };

        const getClientName = (clientId) => clients.find((item) => item.id === clientId)?.name || 'Cliente';
        const getExecutiveName = (userId) => executiveMap[userId]?.full_name || 'Sin asignar';
        const getTypeName = (typeId) => typeMap[typeId]?.name || 'Tipo';

        const parseRelevantDate = (policy) => {
            const source = policy.created_at || policy.start_date || '';
            if (!source) return null;

            const normalized = String(source).includes(' ') ? String(source).replace(' ', 'T') : `${source}T00:00:00`;
            const date = new Date(normalized);
            return Number.isNaN(date.getTime()) ? null : date;
        };

        const matchesPeriod = (policy, period) => {
            if (period === 'all') return true;

            const date = parseRelevantDate(policy);
            if (!date) return true;

            const diffDays = Math.floor((todayTs - new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime()) / 86400000);

            if (period === '30d') return diffDays <= 30;
            if (period === '90d') return diffDays <= 90;
            if (period === '12m') return diffDays <= 365;

            return true;
        };

        const getFilteredPolicies = () => {
            const period = periodSelect.value;
            const executiveId = executiveSelect.value;
            const typeId = typeSelect.value;

            return policies.filter((policy) => {
                return matchesPeriod(policy, period)
                    && (!executiveId || (policy.assigned_executive_user_id || '') === executiveId)
                    && (!typeId || (policy.insurance_type_id || '') === typeId);
            });
        };

        const getPolicyIds = (rows) => rows.map((item) => item.id);

        const getClientsFromPolicies = (rows) => {
            const ids = Array.from(new Set(rows.map((item) => item.client_id).filter(Boolean)));
            return clients.filter((item) => ids.includes(item.id));
        };

        const getOverdueInstallments = (policyIds) => {
            return installments.filter((item) => policyIds.includes(item.policy_id) && String(item.status || '').toLowerCase() === 'vencida');
        };

        const getClaimsForPolicies = (policyIds) => {
            return claims.filter((item) => policyIds.includes(item.policy_id));
        };

        const buildTypeChart = (rows) => {
            const counters = {};

            rows.forEach((policy) => {
                const label = getTypeName(policy.insurance_type_id);
                counters[label] = (counters[label] || 0) + 1;
            });

            return Object.entries(counters).map(([label, value]) => ({ label, value }));
        };

        const buildExecutiveChart = (rows) => {
            const counters = {};

            rows.forEach((policy) => {
                const label = getExecutiveName(policy.assigned_executive_user_id);
                counters[label] = (counters[label] || 0) + 1;
            });

            return Object.entries(counters).map(([label, value]) => ({ label, value }));
        };

        const buildExpirationChart = (rows) => {
            const counters = {};

            rows.forEach((policy) => {
                const raw = String(policy.end_date || '');
                if (!raw) return;

                const date = new Date(`${raw}T00:00:00`);
                if (Number.isNaN(date.getTime())) return;

                const label = date.toLocaleDateString('es-PE', { month: 'short', year: '2-digit' });
                counters[label] = (counters[label] || 0) + 1;
            });

            return Object.entries(counters)
                .map(([label, value]) => ({ label, value }))
                .slice(0, 8);
        };

        const renderBars = (container, dataset) => {
            container.innerHTML = '';

            if (!dataset.length) {
                container.innerHTML = '<div class="chart-empty">No hay datos suficientes para construir este bloque con los filtros seleccionados.</div>';
                return;
            }

            const max = Math.max(...dataset.map((item) => item.value), 1);
            const wrap = document.createElement('div');
            wrap.className = 'chart-area';

            dataset.forEach((item) => {
                const bar = document.createElement('article');
                bar.className = 'chart-bar';
                bar.innerHTML = `
                    <div class="chart-bar__track">
                        <span class="chart-bar__value">${escapeHtml(String(item.value))}</span>
                        <div class="chart-bar__fill" style="height: ${Math.max((item.value / max) * 100, 6)}%;"></div>
                    </div>
                    <div class="chart-bar__label">${escapeHtml(item.label)}</div>
                `;
                wrap.appendChild(bar);
            });

            container.appendChild(wrap);
        };

        const renderExecutiveSummary = (rows) => {
            summaryBody.innerHTML = '';

            if (!rows.length) {
                summaryEmpty.hidden = false;
                return;
            }

            summaryEmpty.hidden = true;

            executives.forEach((exec) => {
                const execPolicies = rows.filter((policy) => (policy.assigned_executive_user_id || '') === exec.id);
                const execPolicyIds = getPolicyIds(execPolicies);
                const execClientIds = Array.from(new Set(execPolicies.map((policy) => policy.client_id).filter(Boolean)));
                const execClaims = claims.filter((item) => (item.assigned_user_id || '') === exec.id && execPolicyIds.includes(item.policy_id));

                if (!execPolicies.length && !execClaims.length) {
                    return;
                }

                const premium = execPolicies.reduce((sum, policy) => sum + Number(policy.premium || 0), 0);

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${escapeHtml(exec.full_name || 'Ejecutivo')}</td>
                    <td>${escapeHtml(String(execClientIds.length))}</td>
                    <td>${escapeHtml(String(execPolicies.length))}</td>
                    <td>${escapeHtml(formatMoney(premium))}</td>
                    <td>${escapeHtml(String(execClaims.length))}</td>
                `;
                summaryBody.appendChild(tr);
            });

            if (!summaryBody.children.length) {
                summaryEmpty.hidden = false;
            }
        };

        const buildPrintUrl = (autoprint = false) => {
            const params = new URLSearchParams();

            params.set('period', periodSelect.value);
            params.set('executive', executiveSelect.value);
            params.set('type', typeSelect.value);

            if (autoprint) {
                params.set('autoprint', '1');
            }

            return `${printBaseUrl}?${params.toString()}`;
        };

        const renderFilterPill = () => {
            const labels = [];

            const periodMap = {
                all: 'Todo',
                '30d': 'Últimos 30 días',
                '90d': 'Últimos 90 días',
                '12m': 'Últimos 12 meses',
            };

            labels.push(periodMap[periodSelect.value] || 'Todo');

            if (executiveSelect.value) {
                labels.push(getExecutiveName(executiveSelect.value));
            }

            if (typeSelect.value) {
                labels.push(getTypeName(typeSelect.value));
            }

            filterPill.textContent = `Filtro actual: ${labels.join(' · ')}`;
        };

        const render = () => {
            const filteredPolicies = getFilteredPolicies();
            const policyIds = getPolicyIds(filteredPolicies);
            const filteredClients = getClientsFromPolicies(filteredPolicies);
            const overdue = getOverdueInstallments(policyIds);
            const premium = filteredPolicies.reduce((sum, policy) => sum + Number(policy.premium || 0), 0);

            kpiClients.textContent = String(filteredPolicies.length ? filteredClients.length : clients.length && periodSelect.value === 'all' && !executiveSelect.value && !typeSelect.value ? clients.length : 0);
            kpiPolicies.textContent = String(filteredPolicies.length);
            kpiPremium.textContent = formatMoney(premium);
            kpiOverdue.textContent = String(overdue.length);

            renderBars(chartTypes, buildTypeChart(filteredPolicies));
            renderBars(chartExecutives, buildExecutiveChart(filteredPolicies));
            renderBars(chartExpirations, buildExpirationChart(filteredPolicies));
            renderExecutiveSummary(filteredPolicies);
            renderFilterPill();
        };

        [periodSelect, executiveSelect, typeSelect].forEach((element) => {
            element.addEventListener('change', render);
        });

        btnViewPrintable.addEventListener('click', () => {
            window.open(buildPrintUrl(false), '_blank', 'noopener');
        });

        btnExportPdf.addEventListener('click', () => {
            window.open(buildPrintUrl(true), '_blank', 'noopener');
        });

        render();
    })();
</script>
<?php
$content = ob_get_clean();

demo_render_internal_layout(
    'Reportes',
    $content,
    [
        'breadcrumb' => ['Inicio', 'Reportes'],
        'subtitle' => 'Vista gerencial de cartera, distribución comercial y salida imprimible a PDF desde el navegador.',
    ]
);