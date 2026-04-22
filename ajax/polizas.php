<?php
require_once __DIR__ . '/../includes/bootstrap.php';

demo_require_roles(['gerente', 'superadmin']);

$currentUser = demo_current_user();

function polizas_request_data(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    return $_POST ?: [];
}

function polizas_input(array $data, string $key, mixed $default = null): mixed
{
    return $data[$key] ?? $default;
}

function polizas_find_index(array $items, string $id): ?int
{
    foreach ($items as $index => $item) {
        if (($item['id'] ?? null) === $id) {
            return $index;
        }
    }
    return null;
}

function polizas_ensure_history_store(array &$store): void
{
    if (!isset($store['policy_history']) || !is_array($store['policy_history'])) {
        $store['policy_history'] = [];
    }
}

function polizas_add_history(array &$store, string $policyId, string $title, string $description, ?string $userId): array
{
    polizas_ensure_history_store($store);

    $history = [
        'id' => demo_generate_id('phist'),
        'policy_id' => $policyId,
        'title' => $title,
        'description' => $description,
        'user_id' => $userId,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    $store['policy_history'][] = $history;
    return $history;
}

function polizas_valid_statuses(): array
{
    return ['activa', 'pendiente', 'vencida', 'anulada', 'renovada'];
}

function polizas_validate_email_free_fields(array $policy): void
{
    // reservado
}

function polizas_generate_installments(array &$store, array $policy, int $count = 12): void
{
    $premium = (float)($policy['premium'] ?? 0);
    $amount = $count > 0 ? round($premium / $count, 2) : 0;
    $startDate = !empty($policy['start_date']) ? strtotime((string)$policy['start_date']) : strtotime(date('Y-m-d'));

    for ($i = 1; $i <= $count; $i++) {
        $dueDate = strtotime('+' . ($i - 1) . ' month', $startDate);
        $store['installments'][] = [
            'id' => demo_generate_id('cuo'),
            'policy_id' => $policy['id'],
            'number' => $i,
            'due_date' => date('Y-m-d', $dueDate),
            'amount' => $amount,
            'status' => $i === 1 && ($policy['status'] ?? '') === 'pendiente' ? 'pendiente' : ($i === 1 ? 'pagada' : 'pendiente'),
            'receipt_uploaded' => false,
        ];
    }
}

function polizas_find_document_uploader_name(array $store, ?string $userId): string
{
    $user = demo_find_by_id($store['users'] ?? [], (string)$userId);
    return $user['full_name'] ?? 'Sistema';
}

$data = polizas_request_data();
$action = trim((string) polizas_input($data, 'action', ''));

if ($action === '') {
    demo_json(false, [
        'title' => 'Solicitud inválida',
        'message' => 'No se recibió una acción válida.',
    ], 400);
}

$store = &demo_store_ref();
polizas_ensure_history_store($store);

if ($action === 'create') {
    $policyNumber = trim((string) polizas_input($data, 'policy_number', ''));
    $clientId = trim((string) polizas_input($data, 'client_id', ''));
    $executiveId = trim((string) polizas_input($data, 'assigned_executive_user_id', ''));
    $insurerId = trim((string) polizas_input($data, 'insurer_id', ''));
    $typeId = trim((string) polizas_input($data, 'insurance_type_id', ''));
    $status = trim((string) polizas_input($data, 'status', 'activa'));
    $startDate = trim((string) polizas_input($data, 'start_date', ''));
    $endDate = trim((string) polizas_input($data, 'end_date', ''));
    $premium = (float) polizas_input($data, 'premium', 0);
    $insuredItem = trim((string) polizas_input($data, 'insured_item', ''));
    $notes = trim((string) polizas_input($data, 'notes', ''));

    if ($policyNumber === '' || $clientId === '' || $insurerId === '' || $typeId === '' || $startDate === '' || $endDate === '') {
        demo_json(false, [
            'title' => 'Datos incompletos',
            'message' => 'Completa número, cliente, aseguradora, tipo y vigencia para crear la póliza.',
        ], 422);
    }

    if ($premium <= 0) {
        demo_json(false, [
            'title' => 'Prima inválida',
            'message' => 'La prima total debe ser mayor a cero.',
        ], 422);
    }

    if (!in_array($status, polizas_valid_statuses(), true)) {
        $status = 'activa';
    }

    foreach (($store['policies'] ?? []) as $existingPolicy) {
        if (($existingPolicy['policy_number'] ?? '') === $policyNumber) {
            demo_json(false, [
                'title' => 'Número duplicado',
                'message' => 'Ya existe una póliza con ese número en el demo.',
            ], 422);
        }
    }

    $client = demo_find_by_id($store['clients'] ?? [], $clientId);
    if (!$client) {
        demo_json(false, [
            'title' => 'Cliente no encontrado',
            'message' => 'Selecciona un cliente válido.',
        ], 422);
    }

    $policy = [
        'id' => demo_generate_id('pol'),
        'policy_number' => $policyNumber,
        'client_id' => $clientId,
        'insurer_id' => $insurerId,
        'insurance_type_id' => $typeId,
        'status' => $status,
        'premium' => $premium,
        'currency' => 'S/',
        'start_date' => $startDate,
        'end_date' => $endDate,
        'assigned_executive_user_id' => $executiveId !== '' ? $executiveId : ($client['assigned_executive_user_id'] ?? null),
        'insured_item' => $insuredItem,
        'notes' => $notes,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    $store['policies'][] = $policy;
    polizas_generate_installments($store, $policy, 12);

    $history = polizas_add_history(
        $store,
        $policy['id'],
        'Póliza creada',
        'Se registró la póliza ' . $policyNumber . ' para ' . ($client['name'] ?? 'cliente') . '.',
        $currentUser['id'] ?? null
    );

    demo_json(true, [
        'title' => 'Póliza creada',
        'message' => 'La póliza demo fue registrada y se generaron cuotas automáticas.',
        'policy' => $policy,
        'history' => $history,
    ]);
}

if ($action === 'edit') {
    $policyId = trim((string) polizas_input($data, 'policy_id', ''));
    $policyIndex = polizas_find_index($store['policies'] ?? [], $policyId);

    if ($policyIndex === null) {
        demo_json(false, [
            'title' => 'Póliza no encontrada',
            'message' => 'No se encontró la póliza que intentas editar.',
        ], 404);
    }

    $existing = $store['policies'][$policyIndex];

    $policyNumber = trim((string) polizas_input($data, 'policy_number', $existing['policy_number'] ?? ''));
    $clientId = trim((string) polizas_input($data, 'client_id', $existing['client_id'] ?? ''));
    $executiveId = trim((string) polizas_input($data, 'assigned_executive_user_id', (string)($existing['assigned_executive_user_id'] ?? '')));
    $insurerId = trim((string) polizas_input($data, 'insurer_id', $existing['insurer_id'] ?? ''));
    $typeId = trim((string) polizas_input($data, 'insurance_type_id', $existing['insurance_type_id'] ?? ''));
    $status = trim((string) polizas_input($data, 'status', $existing['status'] ?? 'activa'));
    $startDate = trim((string) polizas_input($data, 'start_date', $existing['start_date'] ?? ''));
    $endDate = trim((string) polizas_input($data, 'end_date', $existing['end_date'] ?? ''));
    $premium = (float) polizas_input($data, 'premium', $existing['premium'] ?? 0);
    $insuredItem = trim((string) polizas_input($data, 'insured_item', $existing['insured_item'] ?? ''));
    $notes = trim((string) polizas_input($data, 'notes', $existing['notes'] ?? ''));

    if ($policyNumber === '' || $clientId === '' || $insurerId === '' || $typeId === '' || $startDate === '' || $endDate === '') {
        demo_json(false, [
            'title' => 'Datos incompletos',
            'message' => 'Completa número, cliente, aseguradora, tipo y vigencia.',
        ], 422);
    }

    if ($premium <= 0) {
        demo_json(false, [
            'title' => 'Prima inválida',
            'message' => 'La prima total debe ser mayor a cero.',
        ], 422);
    }

    foreach (($store['policies'] ?? []) as $otherPolicy) {
        if (($otherPolicy['id'] ?? '') === $policyId) {
            continue;
        }
        if (($otherPolicy['policy_number'] ?? '') === $policyNumber) {
            demo_json(false, [
                'title' => 'Número duplicado',
                'message' => 'Ya existe otra póliza con ese número en el demo.',
            ], 422);
        }
    }

    if (!in_array($status, polizas_valid_statuses(), true)) {
        $status = 'activa';
    }

    $existing['policy_number'] = $policyNumber;
    $existing['client_id'] = $clientId;
    $existing['insurer_id'] = $insurerId;
    $existing['insurance_type_id'] = $typeId;
    $existing['status'] = $status;
    $existing['premium'] = $premium;
    $existing['start_date'] = $startDate;
    $existing['end_date'] = $endDate;
    $existing['assigned_executive_user_id'] = $executiveId !== '' ? $executiveId : null;
    $existing['insured_item'] = $insuredItem;
    $existing['notes'] = $notes;

    $store['policies'][$policyIndex] = $existing;

    $history = polizas_add_history(
        $store,
        $policyId,
        'Póliza actualizada',
        'Se actualizaron los datos principales de la póliza ' . $policyNumber . '.',
        $currentUser['id'] ?? null
    );

    demo_json(true, [
        'title' => 'Póliza actualizada',
        'message' => 'Los cambios se guardaron correctamente en la sesión actual.',
        'policy' => $existing,
        'history' => $history,
    ]);
}

if ($action === 'upload_document') {
    $policyId = trim((string) polizas_input($data, 'policy_id', ''));
    if ($policyId === '') {
        $policyId = trim((string) polizas_input($data, 'policy_id_select', ''));
    }

    $policy = demo_find_by_id($store['policies'] ?? [], $policyId);
    if (!$policy) {
        demo_json(false, [
            'title' => 'Póliza no encontrada',
            'message' => 'Selecciona una póliza válida para adjuntar el documento.',
        ], 422);
    }

    $originalName = trim((string) polizas_input($data, 'original_name', ''));
    $documentType = trim((string) polizas_input($data, 'document_type', 'Póliza PDF'));

    if ($originalName === '') {
        demo_json(false, [
            'title' => 'Nombre requerido',
            'message' => 'Debes ingresar un nombre de archivo demo.',
        ], 422);
    }

    $document = [
        'id' => demo_generate_id('doc'),
        'entity_type' => 'policy',
        'entity_id' => $policyId,
        'name' => strtolower(str_replace(' ', '_', preg_replace('/[^A-Za-z0-9_\-\. ]/', '', $originalName))),
        'original_name' => $originalName,
        'type' => $documentType,
        'uploaded_by' => $currentUser['id'] ?? null,
        'uploaded_by_name' => polizas_find_document_uploader_name($store, $currentUser['id'] ?? null),
        'created_at' => date('Y-m-d H:i:s'),
    ];

    $store['documents'][] = $document;

    $history = polizas_add_history(
        $store,
        $policyId,
        'Documento adjuntado',
        'Se adjuntó el documento ' . $originalName . ' a la póliza.',
        $currentUser['id'] ?? null
    );

    demo_json(true, [
        'title' => 'Documento adjuntado',
        'message' => 'El documento demo fue registrado correctamente.',
        'document' => $document,
        'history' => $history,
    ]);
}

if ($action === 'renew') {
    $policyId = trim((string) polizas_input($data, 'policy_id', ''));
    $policyIndex = polizas_find_index($store['policies'] ?? [], $policyId);

    if ($policyIndex === null) {
        demo_json(false, [
            'title' => 'Póliza no encontrada',
            'message' => 'No se encontró la póliza que intentas renovar.',
        ], 404);
    }

    $policy = $store['policies'][$policyIndex];
    $currentEnd = !empty($policy['end_date']) ? strtotime((string)$policy['end_date']) : strtotime(date('Y-m-d'));
    $newStart = date('Y-m-d', strtotime('+1 day', $currentEnd));
    $newEnd = date('Y-m-d', strtotime('+1 year', strtotime($newStart)) - 86400);

    $policy['status'] = 'activa';
    $policy['start_date'] = $newStart;
    $policy['end_date'] = $newEnd;
    $policy['notes'] = trim((string)($policy['notes'] ?? ''));
    $policy['notes'] .= ($policy['notes'] ? "\n" : '') . 'Renovación demo aplicada el ' . date('d/m/Y') . '.';

    $store['policies'][$policyIndex] = $policy;

    $history = polizas_add_history(
        $store,
        $policyId,
        'Póliza renovada',
        'Se renovó la póliza demo y la vigencia fue extendida hasta ' . demo_date($newEnd) . '.',
        $currentUser['id'] ?? null
    );

    demo_json(true, [
        'title' => 'Póliza renovada',
        'message' => 'La vigencia fue extendida un año más dentro del demo.',
        'policy' => $policy,
        'history' => $history,
    ]);
}

demo_json(false, [
    'title' => 'Acción no soportada',
    'message' => 'La operación solicitada no está implementada.',
], 400);