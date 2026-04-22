<?php
require_once __DIR__ . '/../includes/bootstrap.php';

demo_require_roles(['cliente']);

$currentUser = demo_current_user();
$clientId = (string)($currentUser['client_id'] ?? '');

function portal_finanzas_request_data(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    return $_POST ?: [];
}

function portal_finanzas_input(array $data, string $key, mixed $default = null): mixed
{
    return $data[$key] ?? $default;
}

function portal_finanzas_find_index(array $items, string $id): ?int
{
    foreach ($items as $index => $item) {
        if (($item['id'] ?? null) === $id) {
            return $index;
        }
    }
    return null;
}

function portal_finanzas_ensure_stores(array &$store): void
{
    if (!isset($store['documents']) || !is_array($store['documents'])) {
        $store['documents'] = [];
    }

    if (!isset($store['activity_log']) || !is_array($store['activity_log'])) {
        $store['activity_log'] = [];
    }
}

function portal_finanzas_add_activity(array &$store, string $title, string $description, ?string $userId): void
{
    portal_finanzas_ensure_stores($store);

    $store['activity_log'][] = [
        'id' => demo_generate_id('act'),
        'title' => $title,
        'description' => $description,
        'user_id' => $userId,
        'created_at' => date('Y-m-d H:i:s'),
    ];
}

function portal_finanzas_installment_belongs_to_client(array $store, string $installmentId, string $clientId): bool
{
    $installment = demo_find_by_id($store['installments'] ?? [], $installmentId);
    if (!$installment) {
        return false;
    }

    $policy = demo_find_by_id($store['policies'] ?? [], (string)($installment['policy_id'] ?? ''));
    if (!$policy) {
        return false;
    }

    return (string)($policy['client_id'] ?? '') === $clientId;
}

function portal_finanzas_format_installment(array $store, array $installment): array
{
    $policy = demo_find_by_id($store['policies'] ?? [], (string)($installment['policy_id'] ?? ''));
    $paymentData = null;

    foreach (($store['payments'] ?? []) as $payment) {
        if (($payment['installment_id'] ?? '') === ($installment['id'] ?? '')) {
            $paymentData = $payment;
        }
    }

    return [
        'id' => $installment['id'],
        'number' => $installment['number'] ?? '—',
        'policy_id' => $installment['policy_id'],
        'policy_number' => $policy['policy_number'] ?? '—',
        'due_date' => $installment['due_date'] ?? '',
        'amount' => (float)($installment['amount'] ?? 0),
        'status' => $installment['status'] ?? 'pendiente',
        'receipt_uploaded' => (bool)($installment['receipt_uploaded'] ?? false),
        'receipt_name' => $installment['receipt_name'] ?? '',
        'receipt_note' => $installment['receipt_note'] ?? '',
        'receipt_uploaded_at' => $installment['receipt_uploaded_at'] ?? '',
        'payment_date' => $paymentData['date'] ?? '',
        'payment_method' => $paymentData['method'] ?? '',
    ];
}

$data = portal_finanzas_request_data();
$action = trim((string) portal_finanzas_input($data, 'action', ''));

if ($action === '') {
    demo_json(false, [
        'title' => 'Solicitud inválida',
        'message' => 'No se recibió una acción válida.',
    ], 400);
}

$store = &demo_store_ref();
portal_finanzas_ensure_stores($store);

if ($action === 'upload_receipt') {
    $installmentId = trim((string) portal_finanzas_input($data, 'installment_id', ''));
    $receiptName = trim((string) portal_finanzas_input($data, 'receipt_name', ''));
    $receiptNote = trim((string) portal_finanzas_input($data, 'receipt_note', ''));

    if ($installmentId === '') {
        demo_json(false, [
            'title' => 'Cuota requerida',
            'message' => 'Selecciona una cuota válida.',
        ], 422);
    }

    if ($receiptName === '') {
        demo_json(false, [
            'title' => 'Comprobante requerido',
            'message' => 'Ingresa un nombre para el comprobante demo.',
        ], 422);
    }

    if (!portal_finanzas_installment_belongs_to_client($store, $installmentId, $clientId)) {
        demo_json(false, [
            'title' => 'Acceso denegado',
            'message' => 'No puedes modificar cuotas ajenas a tu portal.',
        ], 403);
    }

    $installmentIndex = portal_finanzas_find_index($store['installments'] ?? [], $installmentId);
    if ($installmentIndex === null) {
        demo_json(false, [
            'title' => 'Cuota no encontrada',
            'message' => 'No se encontró la cuota seleccionada.',
        ], 404);
    }

    $installment = $store['installments'][$installmentIndex];
    $installment['receipt_uploaded'] = true;
    $installment['receipt_name'] = $receiptName;
    $installment['receipt_note'] = $receiptNote;
    $installment['receipt_uploaded_at'] = date('Y-m-d H:i:s');
    $installment['status'] = 'en revisión';

    $store['installments'][$installmentIndex] = $installment;

    $policy = demo_find_by_id($store['policies'] ?? [], (string)($installment['policy_id'] ?? ''));
    $document = [
        'id' => demo_generate_id('doc'),
        'entity_type' => 'installment',
        'entity_id' => $installmentId,
        'name' => strtolower(str_replace(' ', '_', preg_replace('/[^A-Za-z0-9_\-\. ]/', '', $receiptName))),
        'original_name' => $receiptName,
        'type' => 'Comprobante de pago',
        'uploaded_by' => $currentUser['id'] ?? null,
        'uploaded_by_name' => $currentUser['full_name'] ?? 'Cliente portal',
        'created_at' => date('Y-m-d H:i:s'),
    ];

    $store['documents'][] = $document;

    portal_finanzas_add_activity(
        $store,
        'Comprobante enviado por cliente',
        'El cliente envió un comprobante para la póliza ' . ($policy['policy_number'] ?? '—') . ' y la cuota #' . ($installment['number'] ?? '—') . '.',
        $currentUser['id'] ?? null
    );

    demo_json(true, [
        'title' => 'Comprobante enviado',
        'message' => 'Tu comprobante fue recibido y la cuota quedó en revisión.',
        'installment' => portal_finanzas_format_installment($store, $installment),
        'document' => $document,
    ]);
}

demo_json(false, [
    'title' => 'Acción no soportada',
    'message' => 'La operación solicitada no está implementada.',
], 400);