<?php
require_once __DIR__ . '/../includes/bootstrap.php';

demo_require_roles(['ejecutivo']);

$currentUser = demo_current_user();
$executiveId = $currentUser['id'] ?? '';

function ejecutivo_operacion_request_data(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    return $_POST ?: [];
}

function ejecutivo_operacion_input(array $data, string $key, mixed $default = null): mixed
{
    return $data[$key] ?? $default;
}

function ejecutivo_operacion_find_index(array $items, string $id): ?int
{
    foreach ($items as $index => $item) {
        if (($item['id'] ?? null) === $id) {
            return $index;
        }
    }
    return null;
}

function ejecutivo_operacion_ensure_stores(array &$store): void
{
    if (!isset($store['activity_log']) || !is_array($store['activity_log'])) {
        $store['activity_log'] = [];
    }

    if (!isset($store['claim_observations']) || !is_array($store['claim_observations'])) {
        $store['claim_observations'] = [];
    }

    if (!isset($store['claim_timeline']) || !is_array($store['claim_timeline'])) {
        $store['claim_timeline'] = [];
    }

    if (!isset($store['payments']) || !is_array($store['payments'])) {
        $store['payments'] = [];
    }

    if (!isset($store['internal_categories']) || !is_array($store['internal_categories'])) {
        $store['internal_categories'] = [];
    }

    if (!isset($store['insurance_types']) || !is_array($store['insurance_types'])) {
        $store['insurance_types'] = [];
    }
}

function ejecutivo_operacion_add_activity(array &$store, string $title, string $description, ?string $userId): void
{
    ejecutivo_operacion_ensure_stores($store);

    $store['activity_log'][] = [
        'id' => demo_generate_id('act'),
        'title' => $title,
        'description' => $description,
        'user_id' => $userId,
        'created_at' => date('Y-m-d H:i:s'),
    ];
}

function ejecutivo_operacion_add_claim_timeline(array &$store, string $claimId, string $title, string $description, ?string $userId): array
{
    ejecutivo_operacion_ensure_stores($store);

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

function ejecutivo_operacion_allowed_catalogs(): array
{
    return [
        'insurance_types' => ['label' => 'Tipos de seguro', 'prefix' => 'type'],
        'internal_categories' => ['label' => 'Categorías internas', 'prefix' => 'cat'],
    ];
}

function ejecutivo_operacion_claim_belongs_to_executive(array $store, array $claim, string $executiveId): bool
{
    if (($claim['assigned_user_id'] ?? '') === $executiveId) {
        return true;
    }

    $policy = demo_find_by_id($store['policies'] ?? [], (string)($claim['policy_id'] ?? ''));
    return (string)($policy['assigned_executive_user_id'] ?? '') === $executiveId;
}

function ejecutivo_operacion_policy_belongs_to_executive(array $store, string $policyId, string $executiveId): bool
{
    $policy = demo_find_by_id($store['policies'] ?? [], $policyId);
    return $policy && (string)($policy['assigned_executive_user_id'] ?? '') === $executiveId;
}

function ejecutivo_operacion_format_installment(array $store, array $installment): array
{
    $policy = demo_find_by_id($store['policies'] ?? [], (string)($installment['policy_id'] ?? ''));
    $client = $policy ? demo_find_by_id($store['clients'] ?? [], (string)($policy['client_id'] ?? '')) : null;

    $paymentData = null;
    foreach (($store['payments'] ?? []) as $payment) {
        if (($payment['installment_id'] ?? '') === ($installment['id'] ?? '')) {
            $paymentData = $payment;
        }
    }

    return [
        'id' => $installment['id'],
        'policy_id' => $installment['policy_id'],
        'policy_number' => $policy['policy_number'] ?? '—',
        'client_name' => $client['name'] ?? 'Cliente',
        'due_date' => $installment['due_date'] ?? '',
        'amount' => (float)($installment['amount'] ?? 0),
        'status' => $installment['status'] ?? 'pendiente',
        'receipt_uploaded' => (bool)($installment['receipt_uploaded'] ?? false),
        'receipt_name' => $installment['receipt_name'] ?? '',
        'payment_date' => $paymentData['date'] ?? '',
        'payment_method' => $paymentData['method'] ?? '',
        'payment_note' => $paymentData['note'] ?? '',
    ];
}

function ejecutivo_operacion_valid_claim_statuses(): array
{
    return ['reportado', 'en revisión', 'pendiente documentos', 'cerrado'];
}

function ejecutivo_operacion_next_claim_code(array $claims): string
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

$data = ejecutivo_operacion_request_data();
$action = trim((string)ejecutivo_operacion_input($data, 'action', ''));

if ($action === '') {
    demo_json(false, [
        'title' => 'Solicitud inválida',
        'message' => 'No se recibió una acción válida.',
    ], 400);
}

$store = &demo_store_ref();
ejecutivo_operacion_ensure_stores($store);

if ($action === 'register_payment') {
    $installmentId = trim((string)ejecutivo_operacion_input($data, 'installment_id', ''));
    $paymentDate = trim((string)ejecutivo_operacion_input($data, 'payment_date', date('Y-m-d')));
    $amount = (float)ejecutivo_operacion_input($data, 'amount', 0);
    $method = trim((string)ejecutivo_operacion_input($data, 'method', 'Transferencia'));
    $status = trim((string)ejecutivo_operacion_input($data, 'status', 'pagada'));
    $note = trim((string)ejecutivo_operacion_input($data, 'note', ''));

    if ($installmentId === '' || $amount <= 0) {
        demo_json(false, [
            'title' => 'Datos inválidos',
            'message' => 'Selecciona una cuota válida e ingresa un monto mayor a cero.',
        ], 422);
    }

    $installmentIndex = ejecutivo_operacion_find_index($store['installments'] ?? [], $installmentId);
    if ($installmentIndex === null) {
        demo_json(false, [
            'title' => 'Cuota no encontrada',
            'message' => 'No se encontró la cuota seleccionada.',
        ], 404);
    }

    $installment = $store['installments'][$installmentIndex];
    $policyId = (string)($installment['policy_id'] ?? '');

    if (!ejecutivo_operacion_policy_belongs_to_executive($store, $policyId, $executiveId)) {
        demo_json(false, [
            'title' => 'Acceso denegado',
            'message' => 'No puedes registrar pagos sobre pólizas ajenas a tu cartera.',
        ], 403);
    }

    if (!in_array($status, ['pagada', 'en revisión'], true)) {
        $status = 'pagada';
    }

    $installment['status'] = $status;
    $installment['receipt_uploaded'] = true;
    if (empty($installment['receipt_name'])) {
        $installment['receipt_name'] = 'voucher_cuota_' . ($installment['number'] ?? 'x') . '.jpg';
    }
    $store['installments'][$installmentIndex] = $installment;

    $paymentRow = [
        'id' => demo_generate_id('pay'),
        'installment_id' => $installmentId,
        'policy_id' => $policyId,
        'client_id' => '',
        'amount' => $amount,
        'date' => $paymentDate,
        'method' => $method,
        'status' => $status === 'pagada' ? 'confirmado' : 'en revisión',
        'note' => $note,
    ];

    $policy = demo_find_by_id($store['policies'] ?? [], $policyId);
    if ($policy) {
        $paymentRow['client_id'] = $policy['client_id'] ?? '';
    }

    $store['payments'][] = $paymentRow;

    ejecutivo_operacion_add_activity(
        $store,
        'Pago registrado',
        'Se registró el pago de la cuota #' . ($installment['number'] ?? '—') . ' de la póliza ' . ($policy['policy_number'] ?? '—') . '.',
        $currentUser['id'] ?? null
    );

    demo_json(true, [
        'title' => 'Pago registrado',
        'message' => 'La cuota fue actualizada correctamente.',
        'installment' => ejecutivo_operacion_format_installment($store, $installment),
        'payment' => $paymentRow,
    ]);
}

if ($action === 'create_claim') {
    $clientId = trim((string)ejecutivo_operacion_input($data, 'client_id', ''));
    $policyId = trim((string)ejecutivo_operacion_input($data, 'policy_id', ''));
    $type = trim((string)ejecutivo_operacion_input($data, 'type', ''));
    $date = trim((string)ejecutivo_operacion_input($data, 'date', date('Y-m-d')));
    $status = trim((string)ejecutivo_operacion_input($data, 'status', 'reportado'));
    $description = trim((string)ejecutivo_operacion_input($data, 'description', ''));
    $initialNote = trim((string)ejecutivo_operacion_input($data, 'initial_note', ''));

    if ($clientId === '' || $policyId === '' || $type === '' || $description === '') {
        demo_json(false, [
            'title' => 'Datos incompletos',
            'message' => 'Completa cliente, póliza, tipo y descripción del siniestro.',
        ], 422);
    }

    $client = demo_find_by_id($store['clients'] ?? [], $clientId);
    $policy = demo_find_by_id($store['policies'] ?? [], $policyId);

    if (!$client || !$policy) {
        demo_json(false, [
            'title' => 'Datos inválidos',
            'message' => 'El cliente o la póliza seleccionada no existen.',
        ], 422);
    }

    if ((string)($client['assigned_executive_user_id'] ?? '') !== $executiveId || (string)($policy['assigned_executive_user_id'] ?? '') !== $executiveId) {
        demo_json(false, [
            'title' => 'Acceso denegado',
            'message' => 'Solo puedes crear siniestros dentro de tu propia cartera.',
        ], 403);
    }

    if ((string)($policy['client_id'] ?? '') !== $clientId) {
        demo_json(false, [
            'title' => 'Relación inválida',
            'message' => 'La póliza seleccionada no pertenece al cliente indicado.',
        ], 422);
    }

    if (!in_array($status, ejecutivo_operacion_valid_claim_statuses(), true)) {
        $status = 'reportado';
    }

    $claim = [
        'id' => demo_generate_id('sin'),
        'code' => ejecutivo_operacion_next_claim_code($store['claims'] ?? []),
        'client_id' => $clientId,
        'policy_id' => $policyId,
        'type' => $type,
        'date' => $date,
        'status' => $status,
        'assigned_user_id' => $executiveId,
        'description' => $description,
        'notes' => $initialNote,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    $store['claims'][] = $claim;

    $timeline = ejecutivo_operacion_add_claim_timeline(
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
            'author_name' => $currentUser['full_name'] ?? 'Ejecutivo',
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $store['claim_observations'][] = $observation;
    }

    ejecutivo_operacion_add_activity(
        $store,
        'Siniestro creado',
        'Se registró el siniestro ' . ($claim['code'] ?? '—') . ' dentro de la cartera del ejecutivo.',
        $currentUser['id'] ?? null
    );

    demo_json(true, [
        'title' => 'Siniestro creado',
        'message' => 'El caso fue registrado correctamente.',
        'claim' => $claim,
        'timeline' => $timeline,
        'observation' => $observation,
    ]);
}

if ($action === 'claim_change_status') {
    $claimId = trim((string)ejecutivo_operacion_input($data, 'claim_id', ''));
    $status = trim((string)ejecutivo_operacion_input($data, 'status', ''));
    $note = trim((string)ejecutivo_operacion_input($data, 'note', ''));

    if ($claimId === '' || !in_array($status, ejecutivo_operacion_valid_claim_statuses(), true)) {
        demo_json(false, [
            'title' => 'Datos inválidos',
            'message' => 'Selecciona un siniestro válido y un estado permitido.',
        ], 422);
    }

    $claimIndex = ejecutivo_operacion_find_index($store['claims'] ?? [], $claimId);
    if ($claimIndex === null) {
        demo_json(false, [
            'title' => 'Siniestro no encontrado',
            'message' => 'No se encontró el expediente seleccionado.',
        ], 404);
    }

    $claim = $store['claims'][$claimIndex];
    if (!ejecutivo_operacion_claim_belongs_to_executive($store, $claim, $executiveId)) {
        demo_json(false, [
            'title' => 'Acceso denegado',
            'message' => 'No puedes cambiar el estado de siniestros ajenos a tu cartera.',
        ], 403);
    }

    $claim['status'] = $status;
    $store['claims'][$claimIndex] = $claim;

    $description = 'El estado del expediente ' . ($claim['code'] ?? '—') . ' cambió a ' . $status . '.';
    if ($note !== '') {
        $description .= ' Nota: ' . $note;
    }

    $timeline = ejecutivo_operacion_add_claim_timeline(
        $store,
        $claimId,
        'Estado actualizado',
        $description,
        $currentUser['id'] ?? null
    );

    ejecutivo_operacion_add_activity(
        $store,
        'Estado de siniestro actualizado',
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

if ($action === 'claim_add_observation') {
    $claimId = trim((string)ejecutivo_operacion_input($data, 'claim_id', ''));
    $observationText = trim((string)ejecutivo_operacion_input($data, 'observation', ''));

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

    if (!ejecutivo_operacion_claim_belongs_to_executive($store, $claim, $executiveId)) {
        demo_json(false, [
            'title' => 'Acceso denegado',
            'message' => 'No puedes agregar observaciones a casos ajenos.',
        ], 403);
    }

    $observation = [
        'id' => demo_generate_id('cobs'),
        'claim_id' => $claimId,
        'observation' => $observationText,
        'author_name' => $currentUser['full_name'] ?? 'Ejecutivo',
        'created_at' => date('Y-m-d H:i:s'),
    ];

    $store['claim_observations'][] = $observation;

    $timeline = ejecutivo_operacion_add_claim_timeline(
        $store,
        $claimId,
        'Observación agregada',
        $observationText,
        $currentUser['id'] ?? null
    );

    ejecutivo_operacion_add_activity(
        $store,
        'Observación en siniestro',
        'Se agregó una observación al expediente ' . ($claim['code'] ?? '—') . '.',
        $currentUser['id'] ?? null
    );

    demo_json(true, [
        'title' => 'Observación registrada',
        'message' => 'La observación fue agregada correctamente.',
        'observation' => $observation,
        'timeline' => $timeline,
    ]);
}

if (in_array($action, ['catalog_create', 'catalog_edit', 'catalog_toggle'], true)) {
    $allowedCatalogs = ejecutivo_operacion_allowed_catalogs();
    $catalogKey = trim((string)ejecutivo_operacion_input($data, 'catalog_key', ''));

    if (!isset($allowedCatalogs[$catalogKey])) {
        demo_json(false, [
            'title' => 'Catálogo inválido',
            'message' => 'Solo puedes gestionar catálogos simples permitidos.',
        ], 422);
    }

    $catalogLabel = $allowedCatalogs[$catalogKey]['label'];
    $catalogPrefix = $allowedCatalogs[$catalogKey]['prefix'];

    if (!isset($store[$catalogKey]) || !is_array($store[$catalogKey])) {
        $store[$catalogKey] = [];
    }

    if ($action === 'catalog_create') {
        $name = trim((string)ejecutivo_operacion_input($data, 'name', ''));
        $status = trim((string)ejecutivo_operacion_input($data, 'status', 'activo'));

        if ($name === '') {
            demo_json(false, [
                'title' => 'Nombre requerido',
                'message' => 'Debes ingresar un nombre para el nuevo ítem.',
            ], 422);
        }

        if (!in_array($status, ['activo', 'inactivo'], true)) {
            $status = 'activo';
        }

        foreach ($store[$catalogKey] as $existing) {
            if (mb_strtolower(trim((string)($existing['name'] ?? ''))) === mb_strtolower($name)) {
                demo_json(false, [
                    'title' => 'Nombre duplicado',
                    'message' => 'Ya existe un ítem con ese nombre dentro de ' . mb_strtolower($catalogLabel) . '.',
                ], 422);
            }
        }

        $item = [
            'id' => demo_generate_id($catalogPrefix),
            'name' => $name,
            'status' => $status,
        ];

        $store[$catalogKey][] = $item;

        ejecutivo_operacion_add_activity(
            $store,
            'Catálogo simple creado',
            'Se creó el ítem "' . $name . '" en ' . mb_strtolower($catalogLabel) . '.',
            $currentUser['id'] ?? null
        );

        demo_json(true, [
            'title' => 'Ítem creado',
            'message' => 'El nuevo registro fue agregado correctamente.',
            'catalog_key' => $catalogKey,
            'item' => $item,
        ]);
    }

    if ($action === 'catalog_edit') {
        $itemId = trim((string)ejecutivo_operacion_input($data, 'item_id', ''));
        $name = trim((string)ejecutivo_operacion_input($data, 'name', ''));
        $status = trim((string)ejecutivo_operacion_input($data, 'status', 'activo'));

        if ($itemId === '' || $name === '') {
            demo_json(false, [
                'title' => 'Datos incompletos',
                'message' => 'Debes indicar el ítem y el nuevo nombre.',
            ], 422);
        }

        $index = ejecutivo_operacion_find_index($store[$catalogKey], $itemId);
        if ($index === null) {
            demo_json(false, [
                'title' => 'Ítem no encontrado',
                'message' => 'No se encontró el registro solicitado.',
            ], 404);
        }

        if (!in_array($status, ['activo', 'inactivo'], true)) {
            $status = 'activo';
        }

        foreach ($store[$catalogKey] as $existing) {
            if (($existing['id'] ?? '') === $itemId) {
                continue;
            }

            if (mb_strtolower(trim((string)($existing['name'] ?? ''))) === mb_strtolower($name)) {
                demo_json(false, [
                    'title' => 'Nombre duplicado',
                    'message' => 'Ya existe otro ítem con ese nombre.',
                ], 422);
            }
        }

        $store[$catalogKey][$index]['name'] = $name;
        $store[$catalogKey][$index]['status'] = $status;

        ejecutivo_operacion_add_activity(
            $store,
            'Catálogo simple actualizado',
            'Se editó el ítem "' . $name . '" en ' . mb_strtolower($catalogLabel) . '.',
            $currentUser['id'] ?? null
        );

        demo_json(true, [
            'title' => 'Ítem actualizado',
            'message' => 'Los cambios fueron aplicados correctamente.',
            'catalog_key' => $catalogKey,
            'item' => $store[$catalogKey][$index],
        ]);
    }

    if ($action === 'catalog_toggle') {
        $itemId = trim((string)ejecutivo_operacion_input($data, 'item_id', ''));

        if ($itemId === '') {
            demo_json(false, [
                'title' => 'Ítem requerido',
                'message' => 'Debes indicar qué registro deseas actualizar.',
            ], 422);
        }

        $index = ejecutivo_operacion_find_index($store[$catalogKey], $itemId);
        if ($index === null) {
            demo_json(false, [
                'title' => 'Ítem no encontrado',
                'message' => 'No se encontró el registro solicitado.',
            ], 404);
        }

        $currentStatus = (string)($store[$catalogKey][$index]['status'] ?? 'activo');
        $store[$catalogKey][$index]['status'] = $currentStatus === 'activo' ? 'inactivo' : 'activo';

        ejecutivo_operacion_add_activity(
            $store,
            'Estado de catálogo actualizado',
            'Se cambió el estado de "' . ($store[$catalogKey][$index]['name'] ?? 'ítem') . '" en ' . mb_strtolower($catalogLabel) . '.',
            $currentUser['id'] ?? null
        );

        demo_json(true, [
            'title' => 'Estado actualizado',
            'message' => 'El registro fue actualizado correctamente.',
            'catalog_key' => $catalogKey,
            'item' => $store[$catalogKey][$index],
        ]);
    }
}

demo_json(false, [
    'title' => 'Acción no soportada',
    'message' => 'La operación solicitada no está implementada.',
], 400);