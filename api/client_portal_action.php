<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../config/client_accounts.php';

$user = requireAuth();
header('Content-Type: application/json; charset=UTF-8');

function clientActionResponse(bool $ok, string $message, array $extra = [], int $status = 200): never
{
    http_response_code($status);
    echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function clientActionState(): array
{
    $state = clientReadJsonFile(portalStateStorePath(), []);
    if (!is_array($state['entities'] ?? null) || !is_array($state['expedients'] ?? null)) {
        $defaults = require __DIR__ . '/../config/demo_expedients.php';
        $state = [
            'published_at' => '',
            'entities' => require __DIR__ . '/../config/demo_clients.php',
            'expedients' => is_array($defaults['items'] ?? null) ? $defaults['items'] : [],
        ];
    }

    return $state;
}

function clientActionOwnsPolicy(array $expedient, array $policy, array $entity, array $user): bool
{
    $entityId = (string) ($entity['id'] ?? '');
    $entityDocument = clientNormalizeDocument((string) ($entity['ruc'] ?? $entity['ruc_principal'] ?? $user['document'] ?? ''));
    $parentId = (string) ($expedient['client_id'] ?? '');
    $parentDocument = clientNormalizeDocument((string) ($expedient['client_document'] ?? ''));
    $snapshot = is_array($policy['client_snapshot'] ?? null) ? $policy['client_snapshot'] : [];
    $snapshotId = (string) ($snapshot['id'] ?? '');
    $snapshotDocument = clientNormalizeDocument((string) ($snapshot['document'] ?? ''));

    return $parentId === $entityId
        || ($entityDocument !== '' && $parentDocument === $entityDocument)
        || $snapshotId === $entityId
        || ($entityDocument !== '' && $snapshotDocument === $entityDocument);
}

function clientActionFindPolicy(array &$state, array $entity, array $user, string $policyId): array
{
    foreach ($state['expedients'] as $expedientIndex => &$expedient) {
        if (!is_array($expedient)) {
            continue;
        }

        $policies = is_array($expedient['policies'] ?? null) ? $expedient['policies'] : [];
        foreach ($policies as $policyIndex => &$policy) {
            if (!is_array($policy) || (string) ($policy['id'] ?? '') !== $policyId || ($policy['active'] ?? true) === false) {
                continue;
            }

            if (clientActionOwnsPolicy($expedient, $policy, $entity, $user)) {
                return [$expedientIndex, $policyIndex];
            }
        }
        unset($policy);
    }
    unset($expedient);

    return [-1, -1];
}

function clientActionAddTimeline(array &$expedient, string $title, string $detail): void
{
    $timeline = is_array($expedient['timeline'] ?? null) ? $expedient['timeline'] : [];
    array_unshift($timeline, [
        'id' => 'timeline-' . bin2hex(random_bytes(8)),
        'kind' => 'client',
        'title' => $title,
        'detail' => $detail,
        'at' => (new DateTimeImmutable('now', new DateTimeZone('America/Lima')))->format('Y-m-d H:i:s'),
    ]);
    $expedient['timeline'] = array_slice($timeline, 0, 160);
}

function clientActionNextClaimCode(array $state): string
{
    $year = (new DateTimeImmutable('now', new DateTimeZone('America/Lima')))->format('Y');
    $prefix = 'SIN-' . $year . '-';
    $max = 0;

    foreach ((array) ($state['expedients'] ?? []) as $expedient) {
        foreach ((array) ($expedient['policies'] ?? []) as $policy) {
            foreach ((array) ($policy['claims'] ?? []) as $claim) {
                $code = (string) ($claim['code'] ?? '');
                if (str_starts_with($code, $prefix)) {
                    $number = (int) substr($code, strlen($prefix));
                    $max = max($max, $number);
                }
            }
        }
    }

    return $prefix . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
}

if ((string) ($user['role'] ?? '') !== 'cliente') {
    clientActionResponse(false, 'Este recurso está reservado para cuentas Cliente.', [], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    clientActionResponse(false, 'Método no permitido.', [], 405);
}

$state = clientActionState();
$entity = resolveClientEntity($user, $state);
if ($entity === null) {
    clientActionResponse(false, 'No se encontró una entidad publicada para esta cuenta.', [], 403);
}

$action = trim((string) ($_POST['action'] ?? ''));
$policyId = trim((string) ($_POST['policy_id'] ?? ''));
if ($policyId === '') {
    clientActionResponse(false, 'No se identificó la póliza.', [], 422);
}

[$expedientIndex, $policyIndex] = clientActionFindPolicy($state, $entity, $user, $policyId);
if ($expedientIndex < 0 || $policyIndex < 0) {
    clientActionResponse(false, 'La póliza no está disponible para esta cuenta.', [], 403);
}

$now = (new DateTimeImmutable('now', new DateTimeZone('America/Lima')))->format('Y-m-d H:i:s');
$expedient = &$state['expedients'][$expedientIndex];
$policy = &$expedient['policies'][$policyIndex];

if ($action === 'report_claim') {
    $eventDate = trim((string) ($_POST['event_date'] ?? ''));
    $category = trim((string) ($_POST['category'] ?? ''));
    $location = trim((string) ($_POST['location'] ?? ''));
    $phone = trim((string) ($_POST['contact_phone'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));

    $allowedCategories = ['Accidente', 'Daño material', 'Robo o pérdida', 'Atención médica', 'Incidente de viaje', 'Otro'];
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate) || $eventDate > substr($now, 0, 10) || !in_array($category, $allowedCategories, true) || $description === '') {
        clientActionResponse(false, 'Completa los datos obligatorios y usa una fecha de evento válida.', [], 422);
    }

    $claim = [
        'id' => 'claim-' . bin2hex(random_bytes(8)),
        'code' => clientActionNextClaimCode($state),
        'category' => $category,
        'event_date' => $eventDate,
        'location' => $location,
        'description' => $description,
        'contact_phone' => $phone,
        'status' => 'Reportado',
        'reported_at' => $now,
        'updated_at' => $now,
    ];

    $policy['claims'] = is_array($policy['claims'] ?? null) ? $policy['claims'] : [];
    $policy['claims'][] = $claim;
    $policy['updated_at'] = $now;
    $expedient['updated_at'] = $now;
    clientActionAddTimeline($expedient, 'Siniestro reportado por cliente', $claim['code'] . ' · ' . (string) ($policy['code'] ?? 'Póliza') . ' · ' . $category);

    $state['last_client_activity_at'] = $now;
    if (!clientWriteJsonFile(portalStateStorePath(), $state)) {
        clientActionResponse(false, 'No se pudo guardar el reporte de siniestro.', [], 500);
    }

    clientActionResponse(true, 'Reporte registrado correctamente.', ['claim' => $claim]);
}

if ($action === 'payment_receipt') {
    $paymentId = trim((string) ($_POST['payment_id'] ?? ''));
    $note = trim((string) ($_POST['note'] ?? ''));
    $receipt = json_decode((string) ($_POST['receipt'] ?? ''), true);

    if ($paymentId === '' || !is_array($receipt)) {
        clientActionResponse(false, 'No se identificó el pago o comprobante.', [], 422);
    }

    $relativePath = str_replace('\\', '/', trim((string) ($receipt['relative_path'] ?? '')));
    if (!preg_match('#^comprobantes/\d{4}/\d{2}/\d{2}/[A-Za-z0-9_-]+\.(pdf|png|jpg|webp)$#', $relativePath)) {
        clientActionResponse(false, 'La ruta del comprobante no es válida.', [], 422);
    }

    $projectRoot = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
    $filePath = realpath($projectRoot . DIRECTORY_SEPARATOR . 'almacen' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
    $meta = $filePath && is_file($filePath . '.meta') ? parse_ini_file($filePath . '.meta', false, INI_SCANNER_RAW) : false;
    $ownerDocument = is_array($meta) ? clientNormalizeDocument((string) ($meta['owner_document'] ?? '')) : '';
    $sessionDocument = clientNormalizeDocument((string) ($user['document'] ?? ''));

    if ($filePath === false || !is_file($filePath) || $ownerDocument === '' || $sessionDocument === '' || !hash_equals($ownerDocument, $sessionDocument)) {
        clientActionResponse(false, 'El comprobante no pertenece a esta cuenta.', [], 403);
    }

    $payments = is_array($policy['payments'] ?? null) ? $policy['payments'] : [];
    $found = false;

    foreach ($payments as &$payment) {
        if (is_array($payment) && (string) ($payment['id'] ?? '') === $paymentId) {
            $payment['receipt'] = [
                'relative_path' => $relativePath,
                'original_name' => (string) ($receipt['original_name'] ?? ''),
                'internal_name' => (string) ($receipt['internal_name'] ?? ''),
                'mime' => (string) ($receipt['mime'] ?? ''),
                'size_bytes' => (int) ($receipt['size_bytes'] ?? 0),
                'uploaded_at' => (string) ($receipt['uploaded_at'] ?? $now),
                'uploaded_by' => (string) ($receipt['uploaded_by'] ?? $user['name'] ?? ''),
                'status' => 'En revisión',
                'note' => $note,
            ];
            $payment['status'] = 'En revisión';
            $payment['updated_at'] = $now;
            $found = true;
            $paymentLabel = (string) ($payment['concept'] ?? 'Cuota de póliza');
            break;
        }
    }
    unset($payment);

    if (!$found) {
        clientActionResponse(false, 'No se encontró la cuota indicada en esta póliza.', [], 404);
    }

    $policy['payments'] = $payments;
    $policy['updated_at'] = $now;
    $expedient['updated_at'] = $now;
    clientActionAddTimeline($expedient, 'Comprobante de pago enviado por cliente', (string) ($policy['code'] ?? 'Póliza') . ' · ' . $paymentLabel);

    $state['last_client_activity_at'] = $now;
    if (!clientWriteJsonFile(portalStateStorePath(), $state)) {
        clientActionResponse(false, 'No se pudo registrar el comprobante en la cartera publicada.', [], 500);
    }

    clientActionResponse(true, 'Comprobante enviado para revisión.');
}

clientActionResponse(false, 'Acción no reconocida.', [], 422);
