<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

demo_require_roles(['gerente', 'superadmin']);

$clients = array_values(demo_store('clients', []));
$policies = array_values(demo_store('policies', []));
$installments = array_values(demo_store('installments', []));
$claims = array_values(demo_store('claims', []));
$users = array_values(demo_store('users', []));
$insuranceTypes = array_values(demo_store('insurance_types', []));
$insurers = array_values(demo_store('insurers', []));

$executives = array_values(array_filter($users, fn($u) => ($u['role'] ?? '') === 'ejecutivo'));

$executiveMap = [];
foreach ($executives as $exec) {
    $executiveMap[$exec['id']] = $exec;
}

$typeMap = [];
foreach ($insuranceTypes as $type) {
    $typeMap[$type['id']] = $type;
}

$clientMap = [];
foreach ($clients as $client) {
    $clientMap[$client['id']] = $client;
}

$insurerMap = [];
foreach ($insurers as $insurer) {
    $insurerMap[$insurer['id']] = $insurer;
}

$period = trim((string)($_GET['period'] ?? 'all'));
$executiveId = trim((string)($_GET['executive'] ?? ''));
$typeId = trim((string)($_GET['type'] ?? ''));
$autoPrint = ($_GET['autoprint'] ?? '') === '1';

$today = new DateTimeImmutable('today');
$reportGeneratedAt = new DateTimeImmutable();

$periodLabelMap = [
    'all' => 'Todo',
    '30d' => 'Últimos 30 días',
    '90d' => 'Últimos 90 días',
    '12m' => 'Últimos 12 meses',
];

$periodLabel = $periodLabelMap[$period] ?? 'Todo';
$executiveLabel = $executiveId !== '' ? ($executiveMap[$executiveId]['full_name'] ?? 'Ejecutivo') : 'Todos';
$typeLabel = $typeId !== '' ? ($typeMap[$typeId]['name'] ?? 'Tipo') : 'Todos';

$matchesPeriod = function (array $policy) use ($period, $today): bool {
    if ($period === 'all') {
        return true;
    }

    $source = $policy['created_at'] ?? $policy['start_date'] ?? null;
    if (!$source) {
        return true;
    }

    try {
        $date = new DateTimeImmutable(is_string($source) && str_contains($source, ' ') ? str_replace(' ', 'T', $source) : $source);
    } catch (Throwable $e) {
        return true;
    }

    $diff = (int)$today->diff($date)->format('%r%a');

    if ($period === '30d') {
        return $diff >= -30 && $diff <= 0;
    }

    if ($period === '90d') {
        return $diff >= -90 && $diff <= 0;
    }

    if ($period === '12m') {
        return $diff >= -365 && $diff <= 0;
    }

    return true;
};

$filteredPolicies = array_values(array_filter($policies, function (array $policy) use ($matchesPeriod, $executiveId, $typeId) {
    return $matchesPeriod($policy)
        && ($executiveId === '' || ($policy['assigned_executive_user_id'] ?? '') === $executiveId)
        && ($typeId === '' || ($policy['insurance_type_id'] ?? '') === $typeId);
}));

$filteredPolicyIds = array_column($filteredPolicies, 'id');
$filteredClientIds = array_values(array_unique(array_map(fn($policy) => $policy['client_id'] ?? '', $filteredPolicies)));
$filteredClients = array_values(array_filter($clients, fn($client) => in_array($client['id'], $filteredClientIds, true)));

$totalPremium = array_reduce($filteredPolicies, fn($carry, $item) => $carry + (float)($item['premium'] ?? 0), 0.0);
$totalOverdue = count(array_filter($installments, fn($item) => in_array($item['policy_id'] ?? '', $filteredPolicyIds, true) && strtolower((string)($item['status'] ?? '')) === 'vencida'));

$summaryByExecutive = [];
foreach ($executives as $exec) {
    $execPolicies = array_values(array_filter($filteredPolicies, fn($policy) => ($policy['assigned_executive_user_id'] ?? '') === $exec['id']));
    $execPolicyIds = array_column($execPolicies, 'id');
    $execClientIds = array_values(array_unique(array_filter(array_map(fn($policy) => $policy['client_id'] ?? '', $execPolicies))));
    $execClaims = array_values(array_filter($claims, fn($claim) => ($claim['assigned_user_id'] ?? '') === $exec['id'] && in_array($claim['policy_id'] ?? '', $execPolicyIds, true)));

    if (empty($execPolicies) && empty($execClaims)) {
        continue;
    }

    $summaryByExecutive[] = [
        'executive' => $exec['full_name'] ?? 'Ejecutivo',
        'clients' => count($execClientIds),
        'policies' => count($execPolicies),
        'premium' => array_reduce($execPolicies, fn($carry, $item) => $carry + (float)($item['premium'] ?? 0), 0.0),
        'claims' => count($execClaims),
    ];
}

$policiesByType = [];
foreach ($filteredPolicies as $policy) {
    $label = $typeMap[$policy['insurance_type_id']]['name'] ?? 'Tipo';
    $policiesByType[$label] = ($policiesByType[$label] ?? 0) + 1;
}

$vencimientosPorMes = [];
foreach ($filteredPolicies as $policy) {
    if (empty($policy['end_date'])) {
        continue;
    }

    try {
        $date = new DateTimeImmutable($policy['end_date']);
        $label = ucfirst((string)$date->format('M Y'));
        $vencimientosPorMes[$label] = ($vencimientosPorMes[$label] ?? 0) + 1;
    } catch (Throwable $e) {
        continue;
    }
}

$topPolicies = array_slice($filteredPolicies, 0, 12);

?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reporte gerencial imprimible | BrokerSeguros</title>
    <style>
        :root {
            --ink: #172033;
            --muted: #667085;
            --line: #d9e2ef;
            --soft: #f4f7fb;
            --primary: #4f46e5;
            --secondary: #0ea5a4;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Inter, Arial, Helvetica, sans-serif;
            color: var(--ink);
            background: #eef3f9;
            line-height: 1.45;
        }

        .print-shell {
            width: min(1100px, calc(100vw - 2rem));
            margin: 1rem auto;
            background: #fff;
            border: 1px solid var(--line);
            box-shadow: 0 12px 30px rgba(15, 23, 42, .08);
        }

        .print-header {
            padding: 1.25rem 1.4rem 1rem;
            border-bottom: 2px solid var(--line);
            background:
                radial-gradient(circle at top right, rgba(79, 70, 229, .12), transparent 30%),
                radial-gradient(circle at bottom left, rgba(14, 165, 164, .10), transparent 30%),
                linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        }

        .brand-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .brand-mark {
            display: flex;
            align-items: center;
            gap: .9rem;
        }

        .brand-badge {
            width: 54px;
            height: 54px;
            border-radius: 18px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #fff;
            font-weight: 900;
            font-size: 1rem;
        }

        .brand-title {
            margin: 0;
            font-size: 1.45rem;
        }

        .brand-subtitle {
            margin: .25rem 0 0;
            color: var(--muted);
            font-size: .92rem;
        }

        .report-title {
            margin: 0;
            font-size: 1.7rem;
            line-height: 1.1;
        }

        .report-meta {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .75rem;
            margin-top: 1rem;
        }

        .meta-box {
            padding: .8rem .9rem;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: #fff;
        }

        .meta-box strong {
            display: block;
            margin-bottom: .25rem;
            font-size: .74rem;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--muted);
        }

        .meta-box span {
            display: block;
            font-size: .95rem;
            overflow-wrap: anywhere;
        }

        .print-body {
            padding: 1.25rem 1.4rem 1.5rem;
        }

        .summary-grid {
            display: grid;
            gap: .85rem;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            margin-bottom: 1.15rem;
        }

        .summary-box {
            padding: .95rem 1rem;
            border-radius: 16px;
            border: 1px solid var(--line);
            background: linear-gradient(180deg, #ffffff 0%, #fafcff 100%);
        }

        .summary-box strong {
            display: block;
            font-size: .8rem;
            color: var(--muted);
            margin-bottom: .35rem;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .summary-box span {
            display: block;
            font-size: 1.3rem;
            font-weight: 800;
        }

        .section {
            margin-top: 1.15rem;
        }

        .section h2 {
            margin: 0 0 .65rem;
            font-size: 1.05rem;
        }

        .section p.note {
            margin: 0 0 .8rem;
            color: var(--muted);
            font-size: .92rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid var(--line);
            padding: .7rem .75rem;
            text-align: left;
            vertical-align: top;
            font-size: .9rem;
            overflow-wrap: anywhere;
        }

        th {
            background: var(--soft);
            color: var(--muted);
            font-size: .76rem;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .mini-grid {
            display: grid;
            gap: .85rem;
            grid-template-columns: 1fr 1fr;
        }

        .mini-card {
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: .95rem 1rem;
            background: #fff;
        }

        .mini-card ul {
            margin: .6rem 0 0;
            padding-left: 1rem;
        }

        .mini-card li {
            margin-bottom: .35rem;
        }

        .print-footer {
            margin-top: 1.35rem;
            border-top: 2px solid var(--line);
            padding-top: 1rem;
            display: grid;
            gap: .9rem;
            grid-template-columns: 1fr auto;
            align-items: end;
        }

        .signature {
            min-width: 220px;
            text-align: center;
        }

        .signature-line {
            height: 1px;
            background: #9aa8bd;
            margin-bottom: .4rem;
        }

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 5;
            display: flex;
            justify-content: end;
            gap: .7rem;
            padding: 1rem;
            background: rgba(238, 243, 249, .95);
            backdrop-filter: blur(10px);
        }

        .btn {
            min-height: 42px;
            padding: .7rem 1rem;
            border-radius: 12px;
            border: 0;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-print {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #fff;
        }

        .btn-back {
            background: #fff;
            color: var(--ink);
            border: 1px solid var(--line);
        }

        @media (max-width: 900px) {
            .report-meta,
            .summary-grid,
            .mini-grid,
            .print-footer {
                grid-template-columns: 1fr;
            }
        }

        @media print {
            body {
                background: #fff;
            }

            .toolbar {
                display: none !important;
            }

            .print-shell {
                width: 100%;
                margin: 0;
                border: 0;
                box-shadow: none;
            }

            @page {
                size: A4 portrait;
                margin: 14mm;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" class="btn btn-back" onclick="window.close()">Cerrar</button>
        <button type="button" class="btn btn-print" onclick="window.print()">Imprimir / Guardar PDF</button>
    </div>

    <main class="print-shell">
        <header class="print-header">
            <div class="brand-row">
                <div class="brand-mark">
                    <div class="brand-badge">BS</div>
                    <div>
                        <h1 class="brand-title">BrokerSeguros</h1>
                        <p class="brand-subtitle">Reporte gerencial imprimible del entorno demo</p>
                    </div>
                </div>

                <div>
                    <h2 class="report-title">Reporte de cartera y gestión</h2>
                </div>
            </div>

            <div class="report-meta">
                <div class="meta-box">
                    <strong>Periodo</strong>
                    <span><?= demo_e($periodLabel) ?></span>
                </div>

                <div class="meta-box">
                    <strong>Ejecutivo</strong>
                    <span><?= demo_e($executiveLabel) ?></span>
                </div>

                <div class="meta-box">
                    <strong>Tipo de seguro</strong>
                    <span><?= demo_e($typeLabel) ?></span>
                </div>

                <div class="meta-box">
                    <strong>Fecha de emisión</strong>
                    <span><?= demo_e($reportGeneratedAt->format('d/m/Y H:i')) ?></span>
                </div>
            </div>
        </header>

        <section class="print-body">
            <div class="summary-grid">
                <div class="summary-box">
                    <strong>Total clientes</strong>
                    <span><?= demo_e((string)count($filteredClients)) ?></span>
                </div>

                <div class="summary-box">
                    <strong>Total pólizas</strong>
                    <span><?= demo_e((string)count($filteredPolicies)) ?></span>
                </div>

                <div class="summary-box">
                    <strong>Primas demo</strong>
                    <span><?= demo_e(demo_money($totalPremium)) ?></span>
                </div>

                <div class="summary-box">
                    <strong>Cuotas vencidas</strong>
                    <span><?= demo_e((string)$totalOverdue) ?></span>
                </div>
            </div>

            <section class="section">
                <h2>Resumen por ejecutivo</h2>
                <p class="note">Consolidado de cartera, clientes, primas y siniestros dentro del filtro seleccionado.</p>

                <table>
                    <thead>
                        <tr>
                            <th>Ejecutivo</th>
                            <th>Clientes</th>
                            <th>Pólizas</th>
                            <th>Primas</th>
                            <th>Siniestros</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($summaryByExecutive)): ?>
                            <tr>
                                <td colspan="5">No hay datos suficientes para construir el resumen con los filtros aplicados.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($summaryByExecutive as $row): ?>
                                <tr>
                                    <td><?= demo_e($row['executive']) ?></td>
                                    <td><?= demo_e((string)$row['clients']) ?></td>
                                    <td><?= demo_e((string)$row['policies']) ?></td>
                                    <td><?= demo_e(demo_money($row['premium'])) ?></td>
                                    <td><?= demo_e((string)$row['claims']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </section>

            <section class="section">
                <h2>Detalle de pólizas incluidas</h2>
                <p class="note">Relación base usada para el reporte actual.</p>

                <table>
                    <thead>
                        <tr>
                            <th>Número de póliza</th>
                            <th>Cliente</th>
                            <th>Aseguradora</th>
                            <th>Tipo</th>
                            <th>Vigencia</th>
                            <th>Prima</th>
                            <th>Estado</th>
                            <th>Ejecutivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($topPolicies)): ?>
                            <tr>
                                <td colspan="8">No existen pólizas para los filtros seleccionados.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($topPolicies as $policy): ?>
                                <tr>
                                    <td><?= demo_e($policy['policy_number'] ?? '—') ?></td>
                                    <td><?= demo_e($clientMap[$policy['client_id']]['name'] ?? 'Cliente') ?></td>
                                    <td><?= demo_e($insurerMap[$policy['insurer_id']]['name'] ?? 'Aseguradora') ?></td>
                                    <td><?= demo_e($typeMap[$policy['insurance_type_id']]['name'] ?? 'Tipo') ?></td>
                                    <td><?= demo_e(demo_date($policy['start_date'] ?? null)) ?> al <?= demo_e(demo_date($policy['end_date'] ?? null)) ?></td>
                                    <td><?= demo_e(demo_money((float)($policy['premium'] ?? 0), (string)($policy['currency'] ?? 'S/'))) ?></td>
                                    <td><?= demo_e(ucfirst((string)($policy['status'] ?? '—'))) ?></td>
                                    <td><?= demo_e($executiveMap[$policy['assigned_executive_user_id']]['full_name'] ?? 'Sin asignar') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </section>

            <section class="section">
                <h2>Indicadores complementarios</h2>
                <p class="note">Resumen textual de la distribución actual del reporte.</p>

                <div class="mini-grid">
                    <article class="mini-card">
                        <h3 style="margin:0;">Pólizas por tipo</h3>
                        <ul>
                            <?php if (empty($policiesByType)): ?>
                                <li>Sin información disponible.</li>
                            <?php else: ?>
                                <?php foreach ($policiesByType as $label => $value): ?>
                                    <li><?= demo_e($label) ?>: <?= demo_e((string)$value) ?></li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </article>

                    <article class="mini-card">
                        <h3 style="margin:0;">Vencimientos por mes</h3>
                        <ul>
                            <?php if (empty($vencimientosPorMes)): ?>
                                <li>Sin información disponible.</li>
                            <?php else: ?>
                                <?php foreach ($vencimientosPorMes as $label => $value): ?>
                                    <li><?= demo_e($label) ?>: <?= demo_e((string)$value) ?></li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </article>
                </div>
            </section>

            <footer class="print-footer">
                <div>
                    <strong>Leyenda demo</strong>
                    <p style="margin:.35rem 0 0; color:#667085;">
                        Este documento pertenece al entorno demo de BrokerSeguros. Los datos son simulados y se muestran con fines de validación visual, operativa y comercial.
                    </p>
                </div>

                <div class="signature">
                    <div class="signature-line"></div>
                    <small>Gerencia · BrokerSeguros Demo</small>
                </div>
            </footer>
        </section>
    </main>

    <?php if ($autoPrint): ?>
        <script>
            window.addEventListener('load', () => {
                window.print();
            });
        </script>
    <?php endif; ?>
</body>
</html>