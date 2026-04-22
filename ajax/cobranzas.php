<?php
require_once __DIR__ . '/../includes/bootstrap.php';

demo_require_roles(['gerente', 'superadmin']);

$currentUser = demo_current_user();

function cobranzas_request_data(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    return $_POST ?: [];
}

function cobranzas_input(array $data, string $key, mixed $default = null): mixed
{
    return $data[$key] ?? $default;
}

function cobranzas_find_index(array $items, string $id): ?int
{
    foreach ($items as $index => $item) {
        if (($item['id'] ?? null) === $id) {
            return $index;
        }
    }
    return null;
}

function cobranzas_ensure_activity_store(array &$store): void
{
    if (!isset($store['activity_log']) || !is_array($store['activity_log'])) {
        $store['activity_log'] = [];
    }
}

function cobranzas_add_activity(array &$store, string $title, string $description, ?string $userId): void
{
    cobranzas_ensure_activity_store($store);

    $store['activity_log'][] = [
        'id' => demo_generate_id('act'),
        'title' => $title,
        'description' => $description,
        'user_id' => $userId,
        'created_at' => date('Y-m-d H:i:s'),
    ];
}

function cobranzas_format_installment(array $store, array $installment): array
{
    $policy = demo_find_by_id($store['policies'] ?? [], (string)($installment['policy_id'] ?? ''));
    $client = $policy ? demo_find_by_id($store['clients'] ?? [], (string)($policy['client_id'] ?? '')) : null;
    $executive = $policy ? demo_find_by_id($store['users'] ?? [], (string)($policy['assigned_executive_user_id'] ?? '')) : null;

    $relatedPayment = null;
    foreach (($store['payments'] ?? []) as $payment) {
        if (($payment['installment_id'] ?? '') === ($installment['id'] ?? '')) {
            $relatedPayment = $payment;
        }
    }

    return [
        'id' => $installment['id'],
        'policy_id' => $installment['policy_id'],
        'policy_number' => $policy['policy_number'] ?? '—',
        'client_name' => $client['name'] ?? 'Cliente no encontrado',
        'executive_name' => $executive['full_name'] ?? 'Sin asignar',
        'executive_id' => $policy['assigned_executive_user_id'] ?? '',
        'number' => $installment['number'] ?? '—',
        'due_date' => $installment['due_date'] ?? '',
        'amount' => (float)($installment['amount'] ?? 0),
        'status' => $installment['status'] ?? 'pendiente',
        'receipt_uploaded' => (bool)($installment['receipt_uploaded'] ?? false),
        'receipt_name' => $installment['receipt_name'] ?? '',
        'payment_method' => $relatedPayment['method'] ?? '',
        'payment_date' => $relatedPayment['date'] ?? '',
        'payment_status' => $relatedPayment['status'] ?? '',
        'payment_note' => $relatedPayment['note'] ?? '',
    ];
}

$data = cobranzas_request_data();
$action = trim((string) cobranzas_input($data, 'action', ''));

if ($action === '') {
    demo_json(false, [
        'title' => 'Solicitud inválida',
        'message' => 'No se recibió una acción válida.',
    ], 400);
}

$store = &demo_store_ref();

if ($action === 'register_payment') {
    $installmentId = trim((string) cobranzas_input($data, 'installment_id', ''));
    $paymentDate = trim((string) cobranzas_input($data, 'payment_date', date('Y-m-d')));
    $amount = (float) cobranzas_input($data, 'amount', 0);
    $method = trim((string) cobranzas_input($data, 'method', 'Transferencia'));
    $status = trim((string) cobranzas_input($data, 'status', 'pagada'));
    $note = trim((string) cobranzas_input($data, 'note', ''));

    if ($installmentId === '') {
        demo_json(false, [
            'title' => 'Cuota requerida',
            'message' => 'Selecciona una cuota válida para registrar el pago.',
        ], 422);
    }

    $installmentIndex = cobranzas_find_index($store['installments'] ?? [], $installmentId);
    if ($installmentIndex === null) {
        demo_json(false, [
            'title' => 'Cuota no encontrada',
            'message' => 'No se encontró la cuota seleccionada en el store demo.',
        ], 404);
    }

    if ($amount <= 0) {
        demo_json(false, [
            'title' => 'Monto inválido',
            'message' => 'Ingresa un monto mayor a cero para el pago.',
        ], 422);
    }

    if (!in_array($status, ['pagada', 'en revisión'], true)) {
        $status = 'pagada';
    }

    $installment = $store['installments'][$installmentIndex];
    $installment['status'] = $status;
    $installment['receipt_uploaded'] = true;
    $installment['receipt_name'] = $installment['receipt_name'] ?? ('voucher_cuota_' . ($installment['number'] ?? 'x') . '.jpg');
    $store['installments'][$installmentIndex] = $installment;

    $existingPaymentIndex = null;
    foreach (($store['payments'] ?? []) as $index => $payment) {
        if (($payment['installment_id'] ?? '') === $installmentId) {
            $existingPaymentIndex = $index;
            break;
        }
    }

    $paymentRow = [
        'id' => $existingPaymentIndex !== null ? ($store['payments'][$existingPaymentIndex]['id'] ?? demo_generate_id('pay')) : demo_generate_id('pay'),
        'installment_id' => $installmentId,
        'policy_id' => $installment['policy_id'] ?? '',
        'client_id' => '',
        'amount' => $amount,
        'date' => $paymentDate,
        'method' => $method,
        'status' => $status === 'pagada' ? 'confirmado' : 'en revisión',
        'note' => $note,
    ];

    $policy = demo_find_by_id($store['policies'] ?? [], (string)($installment['policy_id'] ?? ''));
    if ($policy) {
        $paymentRow['client_id'] = $policy['client_id'] ?? '';
    }

    if ($existingPaymentIndex !== null) {
        $store['payments'][$existingPaymentIndex] = $paymentRow;
    } else {
        $store['payments'][] = $paymentRow;
    }

    cobranzas_add_activity(
        $store,
        'Pago registrado',
        'Se registró el pago de la cuota #' . ($installment['number'] ?? '—') . ' de la póliza ' . ($policy['policy_number'] ?? '—') . '.',
        $currentUser['id'] ?? null
    );

    demo_json(true, [
        'title' => 'Pago registrado',
        'message' => 'La cuota fue actualizada correctamente en la sesión actual.',
        'installment' => cobranzas_format_installment($store, $installment),
        'payment' => $paymentRow,
    ]);
}

if ($action === 'attach_receipt') {
    $installmentId = trim((string) cobranzas_input($data, 'installment_id', ''));
    $receiptName = trim((string) cobranzas_input($data, 'receipt_name', ''));
    $receiptNote = trim((string) cobranzas_input($data, 'receipt_note', ''));

    if ($installmentId === '') {
        demo_json(false, [
            'title' => 'Cuota requerida',
            'message' => 'Selecciona una cuota válida para adjuntar el comprobante.',
        ], 422);
    }

    $installmentIndex = cobranzas_find_index($store['installments'] ?? [], $installmentId);
    if ($installmentIndex === null) {
        demo_json(false, [
            'title' => 'Cuota no encontrada',
            'message' => 'No se encontró la cuota seleccionada en el store demo.',
        ], 404);
    }

    if ($receiptName === '') {
        demo_json(false, [
            'title' => 'Nombre requerido',
            'message' => 'Debes ingresar un nombre de comprobante demo.',
        ], 422);
    }

    $installment = $store['installments'][$installmentIndex];
    $installment['receipt_uploaded'] = true;
    $installment['receipt_name'] = $receiptName;
    $store['installments'][$installmentIndex] = $installment;

    $paymentIndex = null;
    foreach (($store['payments'] ?? []) as $index => $payment) {
        if (($payment['installment_id'] ?? '') === $installmentId) {
            $paymentIndex = $index;
            break;
        }
    }

    if ($paymentIndex !== null) {
        $store['payments'][$paymentIndex]['note'] = $receiptNote;
    }

    $policy = demo_find_by_id($store['policies'] ?? [], (string)($installment['policy_id'] ?? ''));
    cobranzas_add_activity(
        $store,
        'Comprobante adjuntado',
        'Se adjuntó el comprobante ' . $receiptName . ' a la cuota #' . ($installment['number'] ?? '—') . ' de ' . ($policy['policy_number'] ?? '—') . '.',
        $currentUser['id'] ?? null
    );

    $formatted = cobranzas_format_installment($store, $installment);
    $formatted['payment_note'] = $receiptNote;

    demo_json(true, [
        'title' => 'Comprobante guardado',
        'message' => 'El comprobante demo fue registrado correctamente.',
        'installment' => $formatted,
    ]);
}

if ($action === 'change_status') {
    $installmentId = trim((string) cobranzas_input($data, 'installment_id', ''));
    $status = trim((string) cobranzas_input($data, 'status', 'pendiente'));
    $note = trim((string) cobranzas_input($data, 'note', ''));

    if ($installmentId === '') {
        demo_json(false, [
            'title' => 'Cuota requerida',
            'message' => 'Selecciona una cuota válida.',
        ], 422);
    }

    if (!in_array($status, ['pendiente', 'pagada', 'vencida', 'en revisión'], true)) {
        demo_json(false, [
            'title' => 'Estado inválido',
            'message' => 'Selecciona un estado válido para la cuota.',
        ], 422);
    }

    $installmentIndex = cobranzas_find_index($store['installments'] ?? [], $installmentId);
    if ($installmentIndex === null) {
        demo_json(false, [
            'title' => 'Cuota no encontrada',
            'message' => 'No se encontró la cuota seleccionada en el store demo.',
        ], 404);
    }

    $installment = $store['installments'][$installmentIndex];
    $installment['status'] = $status;
    $store['installments'][$installmentIndex] = $installment;

    $paymentIndex = null;
    foreach (($store['payments'] ?? []) as $index => $payment) {
        if (($payment['installment_id'] ?? '') === $installmentId) {
            $paymentIndex = $index;
            break;
        }
    }

    if ($paymentIndex !== null) {
        $store['payments'][$paymentIndex]['status'] = $status === 'pagada' ? 'confirmado' : $status;
        if ($note !== '') {
            $store['payments'][$paymentIndex]['note'] = $note;
        }
    }

    $policy = demo_find_by_id($store['policies'] ?? [], (string)($installment['policy_id'] ?? ''));
    cobranzas_add_activity(
        $store,
        'Estado de cuota actualizado',
        'La cuota #' . ($installment['number'] ?? '—') . ' de la póliza ' . ($policy['policy_number'] ?? '—') . ' cambió a ' . $status . '.',
        $currentUser['id'] ?? null
    );

    demo_json(true, [
        'title' => 'Estado actualizado',
        'message' => 'La cuota fue actualizada correctamente.',
        'installment' => cobranzas_format_installment($store, $installment),
    ]);
}

demo_json(false, [
    'title' => 'Acción no soportada',
    'message' => 'La operación solicitada no está implementada.',
], 400);