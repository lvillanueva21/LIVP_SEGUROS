<?php
require_once __DIR__ . '/../includes/bootstrap.php';

demo_require_roles(['gerente', 'superadmin']);

$currentUser = demo_current_user();

function siniestros_request_data(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    return $_POST ?: [];
}

function siniestros_input(array $data, string $key, mixed $default = null): mixed
{
    return $data[$key] ?? $default;
}

function siniestros_find_index(array $items, string $id): ?int
{
    foreach ($items as $index => $item) {
        if (($item['id'] ?? null) === $id) {
            return $index;
        }
    }
    return null;
}

function siniestros_ensure_stores(array &$store): void
{
    if (!isset($store['claim_observations']) || !is_array($store['claim_observations'])) {
        $store['claim_observations'] = [];
    }

    if (!isset($store['claim_timeline']) || !is_array($store['claim_timeline'])) {
        $store['claim_timeline'] = [];
    }

    if (!isset($store['documents']) || !is_array($store['documents'])) {
        $store['documents'] = [];
    }
}

function siniestros_valid_statuses(): array
{
    return ['reportado', 'en revisión', 'pendiente documentos', 'cerrado'];
}

function siniestros_next_code(array $claims): string
{
    $year = date('Y');
    $max = 0;

    foreach ($claims as $claim) {
        if (preg_match('/SIN\-' . preg_quote($year, '/') . '\-(\d+)/', (string)($claim['code'] ?? ''), $matches)) {
            $max = max($max, (int)$matches[1]);
        }
    }

    return 'SIN-' . $year . '-' . str_pad((string)($max + 1), 4, '0', STR_PAD_LEFT);
}

function siniestros_add_timeline(array &$store, string $claimId, string $title, string $description, ?string $userId): array
{
    siniestros_ensure_stores($store);

    $event = [
        'id' => demo_generate_id('ctimeline'),
        'claim_id' => $claimId,
        'title' => $title,
        'description' => $description,
        'user_id' => $userId,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    $store['claim_timeline'][] = $event;

    return $event;
}

function siniestros_uploader_name(array $store, ?string $userId): string
{
    $user = demo_find_by_id($store['users'] ?? [], (string)$userId);
    return $user['full_name'] ?? 'Sistema';
}

$data = siniestros_request_data();
$action = trim((string)siniestros_input($data, 'action', ''));

if ($action === '') {
    demo_json(false, [
        'title' => 'Solicitud inválida',
        'message' => 'No se recibió una acción válida.',
    ], 400);
}

$store = &demo_store_ref();
siniestros_ensure_stores($store);

if ($action === 'create') {
    $clientId = trim((string)siniestros_input($data, 'client_id', ''));
    $policyId = trim((string)siniestros_input($data, 'policy_id', ''));
    $assignedUserId = trim((string)siniestros_input($data, 'assigned_user_id', ''));
    $type = trim((string)siniestros_input($data, 'type', ''));
    $date = trim((string)siniestros_input($data, 'date', date('Y-m-d')));
    $status = trim((string)siniestros_input($data, 'status', 'reportado'));
    $description = trim((string)siniestros_input($data, 'description', ''));
    $initialNote = trim((string)siniestros_input($data, 'initial_note', ''));

    if ($clientId === '' || $policyId === '' || $type === '' || $description === '') {
        demo_json(false, [
            'title' => 'Datos incompletos',
            'message' => 'Debes completar cliente, póliza, tipo y descripción del siniestro.',
        ], 422);
    }

    if (!in_array($status, siniestros_valid_statuses(), true)) {
        $status = 'reportado';
    }

    $client = demo_find_by_id($store['clients'] ?? [], $clientId);
    $policy = demo_find_by_id($store['policies'] ?? [], $policyId);

    if (!$client || !$policy) {
        demo_json(false, [
            'title' => 'Datos inválidos',
            'message' => 'El cliente o la póliza seleccionada no existen en el store demo.',
        ], 422);
    }

    if (($policy['client_id'] ?? '') !== $clientId) {
        demo_json(false, [
            'title' => 'Relación inválida',
            'message' => 'La póliza seleccionada no pertenece al cliente elegido.',
        ], 422);
    }

    $claim = [
        'id' => demo_generate_id('sin'),
        'code' => siniestros_next_code($store['claims'] ?? []),
        'client_id' => $clientId,
        'policy_id' => $policyId,
        'type' => $type,
        'date' => $date,
        'status' => $status,
        'assigned_user_id' => $assignedUserId !== '' ? $assignedUserId : ($policy['assigned_executive_user_id'] ?? null),
        'description' => $description,
        'notes' => $initialNote,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    $store['claims'][] = $claim;

    $timeline = siniestros_add_timeline(
        $store,
        $claim['id'],
        'Siniestro creado',
        'Se abrió el expediente ' . $claim['code'] . ' para ' . ($client['name'] ?? 'cliente') . '.',
        $currentUser['id'] ?? null
    );

    $observation = null;
    if ($initialNote !== '') {
        $observation = [
            'id' => demo_generate_id('cobs'),
            'claim_id' => $claim['id'],
            'observation' => $initialNote,
            'author_name' => $currentUser['full_name'] ?? 'Gerencia',
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $store['claim_observations'][] = $observation;

        siniestros_add_timeline(
            $store,
            $claim['id'],
            'Observación inicial',
            $initialNote,
            $currentUser['id'] ?? null
        );
    }

    demo_json(true, [
        'title' => 'Siniestro creado',
        'message' => 'El caso fue registrado correctamente en la sesión actual.',
        'claim' => $claim,
        'timeline' => $timeline,
        'observation' => $observation,
    ]);
}

if ($action === 'change_status') {
    $claimId = trim((string)siniestros_input($data, 'claim_id', ''));
    $status = trim((string)siniestros_input($data, 'status', ''));
    $note = trim((string)siniestros_input($data, 'note', ''));

    if ($claimId === '' || !in_array($status, siniestros_valid_statuses(), true)) {
        demo_json(false, [
            'title' => 'Datos inválidos',
            'message' => 'Selecciona un siniestro válido y un estado permitido.',
        ], 422);
    }

    $claimIndex = siniestros_find_index($store['claims'] ?? [], $claimId);
    if ($claimIndex === null) {
        demo_json(false, [
            'title' => 'Siniestro no encontrado',
            'message' => 'No se encontró el expediente seleccionado.',
        ], 404);
    }

    $claim = $store['claims'][$claimIndex];
    $claim['status'] = $status;
    $store['claims'][$claimIndex] = $claim;

    $description = 'El estado del expediente ' . ($claim['code'] ?? '—') . ' cambió a ' . $status . '.';
    if ($note !== '') {
        $description .= ' Nota: ' . $note;
    }

    $timeline = siniestros_add_timeline(
        $store,
        $claimId,
        'Estado actualizado',
        $description,
        $currentUser['id'] ?? null
    );

    demo_json(true, [
        'title' => 'Estado actualizado',
        'message' => 'El estado del siniestro fue actualizado correctamente.',
        'claim' => $claim,
        'timeline' => $timeline,
    ]);
}

if ($action === 'add_observation') {
    $claimId = trim((string)siniestros_input($data, 'claim_id', ''));
    $observationText = trim((string)siniestros_input($data, 'observation', ''));

    if ($claimId === '' || $observationText === '') {
        demo_json(false, [
            'title' => 'Observación requerida',
            'message' => 'Debes seleccionar el siniestro y escribir una observación.',
        ], 422);
    }

    $claim = demo_find_by_id($store['claims'] ?? [], $claimId);
    if (!$claim) {
        demo_json(false, [
            'title' => 'Siniestro no encontrado',
            'message' => 'No se encontró el expediente seleccionado.',
        ], 404);
    }

    $observation = [
        'id' => demo_generate_id('cobs'),
        'claim_id' => $claimId,
        'observation' => $observationText,
        'author_name' => $currentUser['full_name'] ?? 'Gerencia',
        'created_at' => date('Y-m-d H:i:s'),
    ];

    $store['claim_observations'][] = $observation;

    $timeline = siniestros_add_timeline(
        $store,
        $claimId,
        'Observación agregada',
        $observationText,
        $currentUser['id'] ?? null
    );

    demo_json(true, [
        'title' => 'Observación registrada',
        'message' => 'La observación fue agregada correctamente.',
        'observation' => $observation,
        'timeline' => $timeline,
    ]);
}

if ($action === 'attach_document') {
    $claimId = trim((string)siniestros_input($data, 'claim_id', ''));
    $originalName = trim((string)siniestros_input($data, 'original_name', ''));
    $documentType = trim((string)siniestros_input($data, 'document_type', 'Evidencia'));
    $documentNote = trim((string)siniestros_input($data, 'document_note', ''));

    if ($claimId === '' || $originalName === '') {
        demo_json(false, [
            'title' => 'Documento requerido',
            'message' => 'Selecciona un siniestro y escribe un nombre de archivo demo.',
        ], 422);
    }

    $claim = demo_find_by_id($store['claims'] ?? [], $claimId);
    if (!$claim) {
        demo_json(false, [
            'title' => 'Siniestro no encontrado',
            'message' => 'No se encontró el expediente seleccionado.',
        ], 404);
    }

    $document = [
        'id' => demo_generate_id('doc'),
        'entity_type' => 'claim',
        'entity_id' => $claimId,
        'name' => strtolower(str_replace(' ', '_', preg_replace('/[^A-Za-z0-9_\-\. ]/', '', $originalName))),
        'original_name' => $originalName,
        'type' => $documentType,
        'uploaded_by' => $currentUser['id'] ?? null,
        'uploaded_by_name' => siniestros_uploader_name($store, $currentUser['id'] ?? null),
        'note' => $documentNote,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    $store['documents'][] = $document;

    $description = 'Se adjuntó el documento ' . $originalName . ' al expediente.';
    if ($documentNote !== '') {
        $description .= ' Detalle: ' . $documentNote;
    }

    $timeline = siniestros_add_timeline(
        $store,
        $claimId,
        'Documento adjuntado',
        $description,
        $currentUser['id'] ?? null
    );

    demo_json(true, [
        'title' => 'Documento adjuntado',
        'message' => 'El archivo demo fue agregado correctamente al expediente.',
        'document' => $document,
        'timeline' => $timeline,
    ]);
}

demo_json(false, [
    'title' => 'Acción no soportada',
    'message' => 'La operación solicitada no está implementada.',
], 400);