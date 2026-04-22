<?php
require_once __DIR__ . '/../includes/bootstrap.php';

demo_require_roles(['ejecutivo']);

$currentUser = demo_current_user();
$executiveId = $currentUser['id'] ?? '';

function ejecutivo_clientes_request_data(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    return $_POST ?: [];
}

function ejecutivo_clientes_input(array $data, string $key, mixed $default = null): mixed
{
    return $data[$key] ?? $default;
}

function ejecutivo_clientes_find_index(array $items, string $id): ?int
{
    foreach ($items as $index => $item) {
        if (($item['id'] ?? null) === $id) {
            return $index;
        }
    }
    return null;
}

function ejecutivo_clientes_validate_email(?string $email): bool
{
    $email = trim((string)$email);
    return $email === '' || (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

function ejecutivo_clientes_ensure_aux_store(array &$store): void
{
    if (!isset($store['client_notes']) || !is_array($store['client_notes'])) {
        $store['client_notes'] = [];
    }

    if (!isset($store['activity_log']) || !is_array($store['activity_log'])) {
        $store['activity_log'] = [];
    }
}

function ejecutivo_clientes_add_activity(array &$store, string $title, string $description, ?string $userId): void
{
    ejecutivo_clientes_ensure_aux_store($store);

    $store['activity_log'][] = [
        'id' => demo_generate_id('act'),
        'title' => $title,
        'description' => $description,
        'user_id' => $userId,
        'created_at' => date('Y-m-d H:i:s'),
    ];
}

function ejecutivo_clientes_find_portal_user(array $users, string $clientId): ?array
{
    foreach ($users as $user) {
        if (($user['role'] ?? '') === 'cliente' && ($user['client_id'] ?? null) === $clientId) {
            return $user;
        }
    }
    return null;
}

$data = ejecutivo_clientes_request_data();
$action = trim((string) ejecutivo_clientes_input($data, 'action', ''));

if ($action === '') {
    demo_json(false, [
        'title' => 'Solicitud inválida',
        'message' => 'No se recibió una acción válida.',
    ], 400);
}

$store = &demo_store_ref();
ejecutivo_clientes_ensure_aux_store($store);

if ($action === 'create') {
    $type = trim((string) ejecutivo_clientes_input($data, 'type', 'persona'));
    $name = trim((string) ejecutivo_clientes_input($data, 'name', ''));
    $documentType = trim((string) ejecutivo_clientes_input($data, 'document_type', 'DNI'));
    $documentNumber = trim((string) ejecutivo_clientes_input($data, 'document_number', ''));
    $email = trim((string) ejecutivo_clientes_input($data, 'email', ''));
    $phone = trim((string) ejecutivo_clientes_input($data, 'phone', ''));
    $address = trim((string) ejecutivo_clientes_input($data, 'address', ''));
    $status = trim((string) ejecutivo_clientes_input($data, 'status', 'activo'));
    $notes = trim((string) ejecutivo_clientes_input($data, 'notes', ''));

    if (!in_array($type, ['persona', 'empresa'], true)) {
        demo_json(false, [
            'title' => 'Tipo inválido',
            'message' => 'El tipo de cliente debe ser persona o empresa.',
        ], 422);
    }

    if ($name === '' || $documentNumber === '') {
        demo_json(false, [
            'title' => 'Datos incompletos',
            'message' => 'Debes completar nombre y documento del cliente.',
        ], 422);
    }

    foreach (($store['clients'] ?? []) as $existingClient) {
        if (($existingClient['document_number'] ?? '') === $documentNumber) {
            demo_json(false, [
                'title' => 'Documento duplicado',
                'message' => 'Ya existe un cliente con ese documento.',
            ], 422);
        }
    }

    if (!ejecutivo_clientes_validate_email($email)) {
        demo_json(false, [
            'title' => 'Correo inválido',
            'message' => 'Ingresa un correo con formato válido.',
        ], 422);
    }

    if (!in_array($status, ['activo', 'inactivo'], true)) {
        $status = 'activo';
    }

    $client = [
        'id' => demo_generate_id('cli'),
        'type' => $type,
        'name' => $name,
        'document_type' => $documentType ?: 'DNI',
        'document_number' => $documentNumber,
        'email' => $email,
        'phone' => $phone,
        'address' => $address,
        'status' => $status,
        'has_portal_access' => false,
        'assigned_executive_user_id' => $executiveId,
        'notes' => $notes,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    $store['clients'][] = $client;

    if ($notes !== '') {
        $store['client_notes'][] = [
            'id' => demo_generate_id('cnote'),
            'client_id' => $client['id'],
            'note' => $notes,
            'author_name' => $currentUser['full_name'] ?? 'Ejecutivo',
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    ejecutivo_clientes_add_activity(
        $store,
        'Cliente creado',
        'Se registró el cliente ' . $name . ' dentro de la cartera del ejecutivo.',
        $currentUser['id'] ?? null
    );

    demo_json(true, [
        'title' => 'Cliente creado',
        'message' => 'El cliente fue agregado correctamente a tu cartera.',
        'client' => $client,
    ]);
}

if ($action === 'edit') {
    $clientId = trim((string) ejecutivo_clientes_input($data, 'client_id', ''));
    $clientIndex = ejecutivo_clientes_find_index($store['clients'] ?? [], $clientId);

    if ($clientIndex === null) {
        demo_json(false, [
            'title' => 'Cliente no encontrado',
            'message' => 'No se encontró el cliente seleccionado.',
        ], 404);
    }

    $client = $store['clients'][$clientIndex];

    if (($client['assigned_executive_user_id'] ?? '') !== $executiveId) {
        demo_json(false, [
            'title' => 'Acceso denegado',
            'message' => 'No puedes editar clientes ajenos a tu cartera.',
        ], 403);
    }

    $type = trim((string) ejecutivo_clientes_input($data, 'type', $client['type'] ?? 'persona'));
    $name = trim((string) ejecutivo_clientes_input($data, 'name', $client['name'] ?? ''));
    $documentType = trim((string) ejecutivo_clientes_input($data, 'document_type', $client['document_type'] ?? 'DNI'));
    $documentNumber = trim((string) ejecutivo_clientes_input($data, 'document_number', $client['document_number'] ?? ''));
    $email = trim((string) ejecutivo_clientes_input($data, 'email', $client['email'] ?? ''));
    $phone = trim((string) ejecutivo_clientes_input($data, 'phone', $client['phone'] ?? ''));
    $address = trim((string) ejecutivo_clientes_input($data, 'address', $client['address'] ?? ''));
    $status = trim((string) ejecutivo_clientes_input($data, 'status', $client['status'] ?? 'activo'));
    $notes = trim((string) ejecutivo_clientes_input($data, 'notes', $client['notes'] ?? ''));

    if (!in_array($type, ['persona', 'empresa'], true)) {
        demo_json(false, [
            'title' => 'Tipo inválido',
            'message' => 'El tipo de cliente debe ser persona o empresa.',
        ], 422);
    }

    if ($name === '' || $documentNumber === '') {
        demo_json(false, [
            'title' => 'Datos incompletos',
            'message' => 'Debes completar nombre y documento del cliente.',
        ], 422);
    }

    foreach (($store['clients'] ?? []) as $otherClient) {
        if (($otherClient['id'] ?? '') === $clientId) {
            continue;
        }
        if (($otherClient['document_number'] ?? '') === $documentNumber) {
            demo_json(false, [
                'title' => 'Documento duplicado',
                'message' => 'Ya existe otro cliente con ese documento.',
            ], 422);
        }
    }

    if (!ejecutivo_clientes_validate_email($email)) {
        demo_json(false, [
            'title' => 'Correo inválido',
            'message' => 'Ingresa un correo con formato válido.',
        ], 422);
    }

    if (!in_array($status, ['activo', 'inactivo'], true)) {
        $status = 'activo';
    }

    $client['type'] = $type;
    $client['name'] = $name;
    $client['document_type'] = $documentType ?: 'DNI';
    $client['document_number'] = $documentNumber;
    $client['email'] = $email;
    $client['phone'] = $phone;
    $client['address'] = $address;
    $client['status'] = $status;
    $client['notes'] = $notes;
    $client['assigned_executive_user_id'] = $executiveId;

    $store['clients'][$clientIndex] = $client;

    foreach (($store['users'] ?? []) as $index => $user) {
        if (($user['role'] ?? '') === 'cliente' && ($user['client_id'] ?? null) === $clientId) {
            $store['users'][$index]['full_name'] = $name;
            $store['users'][$index]['email'] = $email;
            $store['users'][$index]['phone'] = $phone;
            $store['users'][$index]['assigned_executive_user_id'] = $executiveId;
        }
    }

    ejecutivo_clientes_add_activity(
        $store,
        'Cliente actualizado',
        'Se actualizaron los datos del cliente ' . $name . ' dentro de tu cartera.',
        $currentUser['id'] ?? null
    );

    demo_json(true, [
        'title' => 'Cliente actualizado',
        'message' => 'Los cambios del cliente fueron guardados correctamente.',
        'client' => $client,
    ]);
}

if ($action === 'create_portal_access') {
    $clientId = trim((string) ejecutivo_clientes_input($data, 'client_id', ''));
    $clientIndex = ejecutivo_clientes_find_index($store['clients'] ?? [], $clientId);

    if ($clientIndex === null) {
        demo_json(false, [
            'title' => 'Cliente no encontrado',
            'message' => 'No se encontró el cliente seleccionado.',
        ], 404);
    }

    $client = $store['clients'][$clientIndex];

    if (($client['assigned_executive_user_id'] ?? '') !== $executiveId) {
        demo_json(false, [
            'title' => 'Acceso denegado',
            'message' => 'No puedes crear acceso portal para clientes ajenos a tu cartera.',
        ], 403);
    }

    $existingPortalUser = ejecutivo_clientes_find_portal_user($store['users'] ?? [], $clientId);
    if ($existingPortalUser) {
        demo_json(false, [
            'title' => 'Portal ya creado',
            'message' => 'Este cliente ya tiene un acceso portal registrado.',
        ], 422);
    }

    $username = preg_match('/^\d{8}$/', (string)($client['document_number'] ?? ''))
        ? (string)$client['document_number']
        : str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT);

    foreach (($store['users'] ?? []) as $user) {
        if (($user['username'] ?? '') === $username) {
            $username = str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT);
            break;
        }
    }

    $portalUser = [
        'id' => demo_generate_id('usr'),
        'username' => $username,
        'password' => $username,
        'full_name' => $client['name'] ?? 'Cliente',
        'role' => 'cliente',
        'document' => $username,
        'email' => $client['email'] ?? '',
        'phone' => $client['phone'] ?? '',
        'status' => 'activo',
        'avatar' => demo_avatar_initials($client['name'] ?? 'Cliente'),
        'client_id' => $clientId,
        'assigned_executive_user_id' => $executiveId,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    $store['users'][] = $portalUser;

    $client['has_portal_access'] = true;
    $store['clients'][$clientIndex] = $client;

    ejecutivo_clientes_add_activity(
        $store,
        'Acceso portal creado',
        'Se creó el acceso portal para ' . ($client['name'] ?? 'cliente') . '.',
        $currentUser['id'] ?? null
    );

    demo_json(true, [
        'title' => 'Acceso portal creado',
        'message' => 'El acceso portal del cliente fue generado correctamente.',
        'client' => $client,
        'credentials' => [
            'username' => $username,
            'password' => $username,
        ],
        'user' => $portalUser,
    ]);
}

if ($action === 'add_note') {
    $clientId = trim((string) ejecutivo_clientes_input($data, 'client_id', ''));
    $note = trim((string) ejecutivo_clientes_input($data, 'note', ''));

    $clientIndex = ejecutivo_clientes_find_index($store['clients'] ?? [], $clientId);

    if ($clientIndex === null) {
        demo_json(false, [
            'title' => 'Cliente no encontrado',
            'message' => 'No se encontró el cliente seleccionado.',
        ], 404);
    }

    $client = $store['clients'][$clientIndex];

    if (($client['assigned_executive_user_id'] ?? '') !== $executiveId) {
        demo_json(false, [
            'title' => 'Acceso denegado',
            'message' => 'No puedes agregar notas a clientes ajenos a tu cartera.',
        ], 403);
    }

    if ($note === '') {
        demo_json(false, [
            'title' => 'Nota vacía',
            'message' => 'Debes escribir una nota antes de guardarla.',
        ], 422);
    }

    $noteRow = [
        'id' => demo_generate_id('cnote'),
        'client_id' => $clientId,
        'note' => $note,
        'author_name' => $currentUser['full_name'] ?? 'Ejecutivo',
        'created_at' => date('Y-m-d H:i:s'),
    ];

    $store['client_notes'][] = $noteRow;
    $store['clients'][$clientIndex]['notes'] = $note;

    ejecutivo_clientes_add_activity(
        $store,
        'Nota agregada',
        'Se registró una nota interna para el cliente ' . ($client['name'] ?? 'cliente') . '.',
        $currentUser['id'] ?? null
    );

    demo_json(true, [
        'title' => 'Nota guardada',
        'message' => 'La nota fue registrada correctamente.',
        'note' => array_merge($noteRow, [
            'created_at_label' => demo_date($noteRow['created_at'], 'd/m/Y H:i'),
        ]),
    ]);
}

demo_json(false, [
    'title' => 'Acción no soportada',
    'message' => 'La operación solicitada no está implementada.',
], 400);