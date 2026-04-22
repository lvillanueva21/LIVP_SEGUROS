<?php
require_once __DIR__ . '/../includes/bootstrap.php';

demo_require_roles(['ejecutivo']);

$currentUser = demo_current_user();
$executiveId = $currentUser['id'] ?? '';

function ejecutivo_polizas_request_data(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    return $_POST ?: [];
}

function ejecutivo_polizas_input(array $data, string $key, mixed $default = null): mixed
{
    return $data[$key] ?? $default;
}

function ejecutivo_polizas_find_index(array $items, string $id): ?int
{
    foreach ($items as $index => $item) {
        if (($item['id'] ?? null) === $id) {
            return $index;
        }
    }
    return null;
}

function ejecutivo_polizas_ensure_history_store(array &$store): void
{
    if (!isset($store['policy_history']) || !is_array($store['policy_history'])) {
        $store['policy_history'] = [];
    }

    if (!isset($store['activity_log']) || !is_array($store['activity_log'])) {
        $store['activity_log'] = [];
    }
}

function ejecutivo_polizas_add_history(array &$store, string $policyId, string $title, string $description, ?string $userId): array
{
    ejecutivo_polizas_ensure_history_store($store);

    $history = [
        'id' => demo_generate_id('phist'),
        'policy_id' => $policyId,
        'title' => $title,
        'description' => $description,
        'user_id' => $userId,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    $store['policy_history'][] = $history;

    $store['activity_log'][] = [
        'id' => demo_generate_id('act'),
        'title' => $title,
        'description' => $description,
        'user_id' => $userId,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    return $history;
}

function ejecutivo_polizas_generate_installments(array &$store, array $policy, int $count = 12): void
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

function ejecutivo_polizas_policy_belongs_to_executive(array $policy, string $executiveId): bool
{
    return (string)($policy['assigned_executive_user_id'] ?? '') === $executiveId;
}

function ejecutivo_polizas_client_belongs_to_executive(array $client, string $executiveId): bool
{
    return (string)($client['assigned_executive_user_id'] ?? '') === $executiveId;
}

function ejecutivo_polizas_uploader_name(array $store, ?string $userId): string
{
    $user = demo_find_by_id($store['users'] ?? [], (string)$userId);
    return $user['full_name'] ?? 'Sistema';
}

$data = ejecutivo_polizas_request_data();
$action = trim((string)ejecutivo_polizas_input($data, 'action', ''));

if ($action === '') {
    demo_json(false, [
        'title' => 'Solicitud inválida',
        'message' => 'No se recibió una acción válida.',
    ], 400);
}

$store = &demo_store_ref();
ejecutivo_polizas_ensure_history_store($store);

if ($action === 'create') {
    $policyNumber = trim((string)ejecutivo_polizas_input($data, 'policy_number', ''));
    $clientId = trim((string)ejecutivo_polizas_input($data, 'client_id', ''));
    $insurerId = trim((string)ejecutivo_polizas_input($data, 'insurer_id', ''));
    $typeId = trim((string)ejecutivo_polizas_input($data, 'insurance_type_id', ''));
    $status = trim((string)ejecutivo_polizas_input($data, 'status', 'activa'));
    $startDate = trim((string)ejecutivo_polizas_input($data, 'start_date', ''));
    $endDate = trim((string)ejecutivo_polizas_input($data, 'end_date', ''));
    $premium = (float)ejecutivo_polizas_input($data, 'premium', 0);
    $insuredItem = trim((string)ejecutivo_polizas_input($data, 'insured_item', ''));
    $notes = trim((string)ejecutivo_polizas_input($data, 'notes', ''));

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

    foreach (($store['policies'] ?? []) as $existingPolicy) {
        if (($existingPolicy['policy_number'] ?? '') === $policyNumber) {
            demo_json(false, [
                'title' => 'Número duplicado',
                'message' => 'Ya existe una póliza con ese número en el demo.',
            ], 422);
        }
    }

    $client = demo_find_by_id($store['clients'] ?? [], $clientId);
    if (!$client || !ejecutivo_polizas_client_belongs_to_executive($client, $executiveId)) {
        demo_json(false, [
            'title' => 'Cliente inválido',
            'message' => 'Solo puedes crear pólizas para clientes de tu propia cartera.',
        ], 403);
    }

    $policy = [
        'id' => demo_generate_id('pol'),
        'policy_number' => $policyNumber,
        'client_id' => $clientId,
        'insurer_id' => $insurerId,
        'insurance_type_id' => $typeId,
        'status' => in_array($status, ['activa', 'pendiente', 'vencida', 'anulada', 'renovada'], true) ? $status : 'activa',
        'premium' => $premium,
        'currency' => 'S/',
        'start_date' => $startDate,
        'end_date' => $endDate,
        'assigned_executive_user_id' => $executiveId,
        'insured_item' => $insuredItem,
        'notes' => $notes,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    $store['policies'][] = $policy;
    ejecutivo_polizas_generate_installments($store, $policy, 12);

    $history = ejecutivo_polizas_add_history(
        $store,
        $policy['id'],
        'Póliza creada',
        'Se creó la póliza ' . $policyNumber . ' para ' . ($client['name'] ?? 'cliente') . '.',
        $currentUser['id'] ?? null
    );

    demo_json(true, [
        'title' => 'Póliza creada',
        'message' => 'La póliza fue registrada correctamente en tu cartera.',
        'policy' => $policy,
        'history' => $history,
    ]);
}

if ($action === 'edit') {
    $policyId = trim((string)ejecutivo_polizas_input($data, 'policy_id', ''));
    $policyIndex = ejecutivo_polizas_find_index($store['policies'] ?? [], $policyId);

    if ($policyIndex === null) {
        demo_json(false, [
            'title' => 'Póliza no encontrada',
            'message' => 'No se encontró la póliza seleccionada.',
        ], 404);
    }

    $policy = $store['policies'][$policyIndex];
    if (!ejecutivo_polizas_policy_belongs_to_executive($policy, $executiveId)) {
        demo_json(false, [
            'title' => 'Acceso denegado',
            'message' => 'No puedes editar pólizas ajenas a tu cartera.',
        ], 403);
    }

    $policyNumber = trim((string)ejecutivo_polizas_input($data, 'policy_number', $policy['policy_number'] ?? ''));
    $clientId = trim((string)ejecutivo_polizas_input($data, 'client_id', $policy['client_id'] ?? ''));
    $insurerId = trim((string)ejecutivo_polizas_input($data, 'insurer_id', $policy['insurer_id'] ?? ''));
    $typeId = trim((string)ejecutivo_polizas_input($data, 'insurance_type_id', $policy['insurance_type_id'] ?? ''));
    $status = trim((string)ejecutivo_polizas_input($data, 'status', $policy['status'] ?? 'activa'));
    $startDate = trim((string)ejecutivo_polizas_input($data, 'start_date', $policy['start_date'] ?? ''));
    $endDate = trim((string)ejecutivo_polizas_input($data, 'end_date', $policy['end_date'] ?? ''));
    $premium = (float)ejecutivo_polizas_input($data, 'premium', $policy['premium'] ?? 0);
    $insuredItem = trim((string)ejecutivo_polizas_input($data, 'insured_item', $policy['insured_item'] ?? ''));
    $notes = trim((string)ejecutivo_polizas_input($data, 'notes', $policy['notes'] ?? ''));

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
                'message' => 'Ya existe otra póliza con ese número.',
            ], 422);
        }
    }

    $client = demo_find_by_id($store['clients'] ?? [], $clientId);
    if (!$client || !ejecutivo_polizas_client_belongs_to_executive($client, $executiveId)) {
        demo_json(false, [
            'title' => 'Cliente inválido',
            'message' => 'Solo puedes vincular pólizas a clientes de tu cartera.',
        ], 403);
    }

    $policy['policy_number'] = $policyNumber;
    $policy['client_id'] = $clientId;
    $policy['insurer_id'] = $insurerId;
    $policy['insurance_type_id'] = $typeId;
    $policy['status'] = in_array($status, ['activa', 'pendiente', 'vencida', 'anulada', 'renovada'], true) ? $status : 'activa';
    $policy['premium'] = $premium;
    $policy['start_date'] = $startDate;
    $policy['end_date'] = $endDate;
    $policy['insured_item'] = $insuredItem;
    $policy['notes'] = $notes;
    $policy['assigned_executive_user_id'] = $executiveId;

    $store['policies'][$policyIndex] = $policy;

    $history = ejecutivo_polizas_add_history(
        $store,
        $policyId,
        'Póliza actualizada',
        'Se actualizaron los datos principales de la póliza ' . $policyNumber . '.',
        $currentUser['id'] ?? null
    );

    demo_json(true, [
        'title' => 'Póliza actualizada',
        'message' => 'Los cambios fueron guardados correctamente.',
        'policy' => $policy,
        'history' => $history,
    ]);
}

if ($action === 'upload_document') {
    $policyId = trim((string)ejecutivo_polizas_input($data, 'policy_id', ''));
    if ($policyId === '') {
        $policyId = trim((string)ejecutivo_polizas_input($data, 'policy_id_select', ''));
    }

    $policyIndex = ejecutivo_polizas_find_index($store['policies'] ?? [], $policyId);
    if ($policyIndex === null) {
        demo_json(false, [
            'title' => 'Póliza no encontrada',
            'message' => 'Selecciona una póliza válida de tu cartera.',
        ], 404);
    }

    $policy = $store['policies'][$policyIndex];
    if (!ejecutivo_polizas_policy_belongs_to_executive($policy, $executiveId)) {
        demo_json(false, [
            'title' => 'Acceso denegado',
            'message' => 'No puedes adjuntar documentos a pólizas ajenas.',
        ], 403);
    }

    $originalName = trim((string)ejecutivo_polizas_input($data, 'original_name', ''));
    $documentType = trim((string)ejecutivo_polizas_input($data, 'document_type', 'Póliza PDF'));

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
        'uploaded_by_name' => ejecutivo_polizas_uploader_name($store, $currentUser['id'] ?? null),
        'created_at' => date('Y-m-d H:i:s'),
    ];

    $store['documents'][] = $document;

    $history = ejecutivo_polizas_add_history(
        $store,
        $policyId,
        'Documento adjuntado',
        'Se adjuntó el documento ' . $originalName . ' a la póliza ' . ($policy['policy_number'] ?? '—') . '.',
        $currentUser['id'] ?? null
    );

    demo_json(true, [
        'title' => 'Documento adjuntado',
        'message' => 'El archivo demo fue agregado correctamente.',
        'document' => $document,
        'history' => $history,
    ]);
}

if ($action === 'register_action') {
    $policyId = trim((string)ejecutivo_polizas_input($data, 'policy_id', ''));
    $actionType = trim((string)ejecutivo_polizas_input($data, 'action_type', 'seguimiento'));
    $note = trim((string)ejecutivo_polizas_input($data, 'note', ''));
    $amount = (float)ejecutivo_polizas_input($data, 'amount', 0);

    $policyIndex = ejecutivo_polizas_find_index($store['policies'] ?? [], $policyId);
    if ($policyIndex === null) {
        demo_json(false, [
            'title' => 'Póliza no encontrada',
            'message' => 'No se encontró la póliza seleccionada.',
        ], 404);
    }

    $policy = $store['policies'][$policyIndex];
    if (!ejecutivo_polizas_policy_belongs_to_executive($policy, $executiveId)) {
        demo_json(false, [
            'title' => 'Acceso denegado',
            'message' => 'No puedes registrar acciones sobre pólizas ajenas.',
        ], 403);
    }

    $title = 'Acción demo registrada';
    $description = 'Se registró una acción demo sobre la póliza ' . ($policy['policy_number'] ?? '—') . '.';

    if ($actionType === 'payment') {
        $title = 'Pago demo registrado';
        $description = 'Se registró un pago demo';
        if ($amount > 0) {
            $description .= ' por ' . demo_money($amount);
        }
        $description .= ' en la póliza ' . ($policy['policy_number'] ?? '—') . '.';
        if ($note !== '') {
            $description .= ' Detalle: ' . $note;
        }
    } elseif ($note !== '') {
        $description = $note;
    }

    $history = ejecutivo_polizas_add_history(
        $store,
        $policyId,
        $title,
        $description,
        $currentUser['id'] ?? null
    );

    demo_json(true, [
        'title' => 'Acción registrada',
        'message' => 'La trazabilidad demo fue guardada correctamente.',
        'history' => $history,
    ]);
}

demo_json(false, [
    'title' => 'Acción no soportada',
    'message' => 'La operación solicitada no está implementada.',
], 400);