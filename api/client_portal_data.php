<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../config/client_accounts.php';

$user = requireAuth();

header('Content-Type: application/json; charset=UTF-8');

function clientPortalResponse(bool $ok, string $message, array $extra = [], int $status = 200): never
{
    http_response_code($status);
    echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ((string) ($user['role'] ?? '') !== 'cliente') {
    clientPortalResponse(false, 'Este recurso está reservado para cuentas Cliente.', [], 403);
}

$state = clientReadJsonFile(portalStateStorePath(), []);
if (!is_array($state['entities'] ?? null) || !is_array($state['expedients'] ?? null)) {
    $state = [
        'published_at' => '',
        'entities' => require __DIR__ . '/../config/demo_clients.php',
        'expedients' => (require __DIR__ . '/../config/demo_expedients.php')['items'] ?? [],
    ];
}

$entity = resolveClientEntity($user, $state);
if ($entity === null) {
    clientPortalResponse(true, 'No se encontró una entidad publicada para esta cuenta.', [
        'published_at' => (string) ($state['published_at'] ?? ''),
        'state' => ['entities' => ['companies' => [], 'consortia' => []], 'expedients' => []],
    ]);
}

$entityId = (string) ($entity['id'] ?? '');
$entityDocument = clientNormalizeDocument((string) ($entity['ruc'] ?? $entity['ruc_principal'] ?? $user['document'] ?? ''));

$publicPolicies = static function (array $policies, bool $parentMatches, string $expectedEntityId) use ($entityDocument): array {
    $result = [];

    foreach ($policies as $policy) {
        if (!is_array($policy) || ($policy['active'] ?? true) === false) {
            continue;
        }

        $snapshot = is_array($policy['client_snapshot'] ?? null) ? $policy['client_snapshot'] : [];
        $snapshotId = (string) ($snapshot['id'] ?? '');
        $snapshotDocument = clientNormalizeDocument((string) ($snapshot['document'] ?? ''));
        $matches = $parentMatches || $snapshotId === $expectedEntityId || ($entityDocument !== '' && $snapshotDocument === $entityDocument);

        if (!$matches) {
            continue;
        }

        $file = is_array($policy['file'] ?? null) ? $policy['file'] : null;
        $payments = is_array($policy['payments'] ?? null) ? $policy['payments'] : [];
        $claims = is_array($policy['claims'] ?? null) ? $policy['claims'] : [];

        $result[] = [
            'id' => (string) ($policy['id'] ?? ''),
            'code' => (string) ($policy['code'] ?? ''),
            'title' => (string) ($policy['title'] ?? ''),
            'insurance_type_name' => (string) ($policy['insurance_type_name'] ?? ''),
            'insurer_name' => (string) ($policy['insurer_name'] ?? ''),
            'insured_amount' => (string) ($policy['insured_amount'] ?? ''),
            'currency_name' => (string) ($policy['currency_name'] ?? ''),
            'starts_at' => (string) ($policy['starts_at'] ?? ''),
            'ends_at' => (string) ($policy['ends_at'] ?? ''),
            'status' => (string) ($policy['status'] ?? ''),
            'active' => true,
            'client_snapshot' => [
                'id' => (string) ($snapshot['id'] ?? ''),
                'name' => (string) ($snapshot['name'] ?? ''),
                'document' => (string) ($snapshot['document'] ?? ''),
                'entity_type' => (string) ($snapshot['entity_type'] ?? ''),
            ],
            'file' => $file === null ? null : [
                'relative_path' => (string) ($file['relative_path'] ?? ''),
                'original_name' => (string) ($file['original_name'] ?? ''),
            ],
            'payments' => $payments,
            'claims' => $claims,
        ];
    }

    return $result;
};

$records = [];
foreach ((array) $state['expedients'] as $expedient) {
    if (!is_array($expedient)) {
        continue;
    }

    $parentId = (string) ($expedient['client_id'] ?? '');
    $parentDocument = clientNormalizeDocument((string) ($expedient['client_document'] ?? ''));
    $parentMatches = $parentId === $entityId || ($entityDocument !== '' && $parentDocument === $entityDocument);
    $policies = $publicPolicies((array) ($expedient['policies'] ?? []), $parentMatches, $entityId);

    if (!$parentMatches && $policies === []) {
        continue;
    }

    $records[] = [
        'id' => (string) ($expedient['id'] ?? ''),
        'code' => (string) ($expedient['code'] ?? ''),
        'client_id' => $entityId,
        'client_name' => (string) ($entity['name'] ?? ''),
        'client_document' => $entityDocument,
        'entity_type' => (string) ($entity['entity_type'] ?? $user['entity_type'] ?? ''),
        'title' => (string) ($expedient['title'] ?? ''),
        'state' => (string) ($expedient['state'] ?? ''),
        'opened_at' => (string) ($expedient['opened_at'] ?? ''),
        'updated_at' => (string) ($expedient['updated_at'] ?? ''),
        'policies' => $policies,
    ];
}

$companies = [];
$consortia = [];
$accountType = (string) ($user['account_type'] ?? 'empresa');
if ($accountType === 'consorcio') {
    $consortia[] = $entity;
} else {
    $companies[] = $entity;
}

clientPortalResponse(true, 'Datos Cliente disponibles.', [
    'published_at' => (string) ($state['published_at'] ?? ''),
    'state' => [
        'entities' => ['companies' => $companies, 'consortia' => $consortia],
        'expedients' => $records,
    ],
]);
