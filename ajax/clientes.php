<?php
require_once __DIR__ . '/../includes/bootstrap.php';

demo_require_roles(['gerente', 'superadmin']);

$currentUser = demo_current_user();

function clientes_request_data(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    return $_POST ?: [];
}

function clientes_input(array $data, string $key, mixed $default = null): mixed
{
    return $data[$key] ?? $default;
}

function clientes_find_index(array $items, string $id): ?int
{
    foreach ($items as $index => $item) {
        if (($item['id'] ?? null) === $id) {
            return $index;
        }
    }
    return null;
}

function clientes_validate_email(?string $email): bool
{
    $email = trim((string)$email);
    return $email === '' || (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

function clientes_ensure_aux_store(array &$store): void
{
    if (!isset($store['client_notes']) || !is_array($store['client_notes'])) {
        $store['client_notes'] = [];
    }

    if (!isset($store['client_activity']) || !is_array($store['client_activity'])) {
        $store['client_activity'] = [];
    }
}

function clientes_add_activity(array &$store, string $clientId, string $title, string $description, ?string $userId): array
{
    clientes_ensure_aux_store($store);

    $activity = [
        'id' => demo_generate_id('cact'),
        'client_id' => $clientId,
        'title' => $title,
        'description' => $description,
        'user_id' => $userId,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    $store['client_activity'][] = $activity;

    return $activity;
}

function clientes_find_user_by_client(array $users, string $clientId): ?array
{
    foreach ($users as $user) {
        if (($user['role'] ?? '') === 'cliente' && ($user['client_id'] ?? null) === $clientId) {
            return $user;
        }
    }
    return null;
}

$data = clientes_request_data();
$action = trim((string) clientes_input($data, 'action', ''));

if ($action === '') {
    demo_json(false, [
        'title' => 'Solicitud inválida',
        'message' => 'No se recibió una acción válida.',
    ], 400);
}

$store = &demo_store_ref();
clientes_ensure_aux_store($store);

if ($action === 'create') {
    $type = trim((string) clientes_input($data, 'type', 'persona'));
    $name = trim((string) clientes_input($data, 'name', ''));
    $documentType = trim((string) clientes_input($data, 'document_type', 'DNI'));
    $documentNumber = trim((string) clientes_input($data, 'document_number', ''));
    $email = trim((string) clientes_input($data, 'email', ''));
    $phone = trim((string) clientes_input($data, 'phone', ''));
    $address = trim((string) clientes_input($data, 'address', ''));
    $status = trim((string) clientes_input($data, 'status', 'activo'));
    $assignedExecutive = trim((string) clientes_input($data, 'assigned_executive_user_id', ''));
    $notes = trim((string) clientes_input($data, 'notes', ''));

    if (!in_array($type, ['persona', 'empresa'], true)) {
        demo_json(false, [
            'title' => 'Tipo inválido',
            'message' => 'El tipo de cliente debe ser persona o empresa.',
        ], 422);
    }

    if ($name === '') {
        demo_json(false, [
            'title' => 'Nombre requerido',
            'message' => 'Debes ingresar el nombre o razón social.',
        ], 422);
    }

    if ($documentNumber === '') {
        demo_json(false, [
            'title' => 'Documento requerido',
            'message' => 'Debes ingresar el número de documento.',
        ], 422);
    }

    foreach (($store['clients'] ?? []) as $existingClient) {
        if (($existingClient['document_number'] ?? '') === $documentNumber) {
            demo_json(false, [
                'title' => 'Documento duplicado',
                'message' => 'Ya existe un cliente con ese documento en el demo.',
            ], 422);
        }
    }

    if (!clientes_validate_email($email)) {
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
        'assigned_executive_user_id' => $assignedExecutive !== '' ? $assignedExecutive : null,
        'notes' => $notes,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    $store['clients'][] = $client;

    if ($notes !== '') {
        $store['client_notes'][] = [
            'id' => demo_generate_id('cnote'),
            'client_id' => $client['id'],
            'note' => $notes,
            'author_name' => $currentUser['full_name'] ?? 'Gerencia',
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    clientes_add_activity(
        $store,
        $client['id'],
        'Cliente creado',
        'Se registró el cliente ' . $name . ' en la sesión demo.',
        $currentUser['id'] ?? null
    );

    demo_json(true, [
        'title' => 'Cliente creado',
        'message' => 'El cliente fue registrado correctamente en el demo.',
        'client' => $client,
    ]);
}

if ($action === 'edit') {
    $clientId = trim((string) clientes_input($data, 'client_id', ''));
    $clientIndex = clientes_find_index($store['clients'] ?? [], $clientId);

    if ($clientIndex === null) {
        demo_json(false, [
            'title' => 'Cliente no encontrado',
            'message' => 'No se encontró el cliente que intentas editar.',
        ], 404);
    }

    $existing = $store['clients'][$clientIndex];

    $type = trim((string) clientes_input($data, 'type', $existing['type'] ?? 'persona'));
    $name = trim((string) clientes_input($data, 'name', $existing['name'] ?? ''));
    $documentType = trim((string) clientes_input($data, 'document_type', $existing['document_type'] ?? 'DNI'));
    $documentNumber = trim((string) clientes_input($data, 'document_number', $existing['document_number'] ?? ''));
    $email = trim((string) clientes_input($data, 'email', $existing['email'] ?? ''));
    $phone = trim((string) clientes_input($data, 'phone', $existing['phone'] ?? ''));
    $address = trim((string) clientes_input($data, 'address', $existing['address'] ?? ''));
    $status = trim((string) clientes_input($data, 'status', $existing['status'] ?? 'activo'));
    $assignedExecutive = trim((string) clientes_input($data, 'assigned_executive_user_id', (string)($existing['assigned_executive_user_id'] ?? '')));
    $notes = trim((string) clientes_input($data, 'notes', $existing['notes'] ?? ''));

    if (!in_array($type, ['persona', 'empresa'], true)) {
        demo_json(false, [
            'title' => 'Tipo inválido',
            'message' => 'El tipo de cliente debe ser persona o empresa.',
        ], 422);
    }

    if ($name === '') {
        demo_json(false, [
            'title' => 'Nombre requerido',
            'message' => 'Debes ingresar el nombre o razón social.',
        ], 422);
    }

    if ($documentNumber === '') {
        demo_json(false, [
            'title' => 'Documento requerido',
            'message' => 'Debes ingresar el número de documento.',
        ], 422);
    }

    foreach (($store['clients'] ?? []) as $otherClient) {
        if (($otherClient['id'] ?? '') === $clientId) {
            continue;
        }
        if (($otherClient['document_number'] ?? '') === $documentNumber) {
            demo_json(false, [
                'title' => 'Documento duplicado',
                'message' => 'Ya existe otro cliente con ese documento en el demo.',
            ], 422);
        }
    }

    if (!clientes_validate_email($email)) {
        demo_json(false, [
            'title' => 'Correo inválido',
            'message' => 'Ingresa un correo con formato válido.',
        ], 422);
    }

    if (!in_array($status, ['activo', 'inactivo'], true)) {
        $status = 'activo';
    }

    $existing['type'] = $type;
    $existing['name'] = $name;
    $existing['document_type'] = $documentType ?: 'DNI';
    $existing['document_number'] = $documentNumber;
    $existing['email'] = $email;
    $existing['phone'] = $phone;
    $existing['address'] = $address;
    $existing['status'] = $status;
    $existing['assigned_executive_user_id'] = $assignedExecutive !== '' ? $assignedExecutive : null;
    $existing['notes'] = $notes;

    $store['clients'][$clientIndex] = $existing;

    foreach (($store['users'] ?? []) as $userIndex => $user) {
        if (($user['role'] ?? '') === 'cliente' && ($user['client_id'] ?? null) === $clientId) {
            $store['users'][$userIndex]['full_name'] = $name;
            $store['users'][$userIndex]['email'] = $email;
            $store['users'][$userIndex]['phone'] = $phone;
            $store['users'][$userIndex]['assigned_executive_user_id'] = $assignedExecutive !== '' ? $assignedExecutive : null;
        }
    }

    clientes_add_activity(
        $store,
        $clientId,
        'Cliente actualizado',
        'Se actualizaron los datos principales del cliente ' . $name . '.',
        $currentUser['id'] ?? null
    );

    demo_json(true, [
        'title' => 'Cliente actualizado',
        'message' => 'Los datos del cliente se guardaron correctamente.',
        'client' => $existing,
    ]);
}

if ($action === 'create_portal_access') {
    $clientId = trim((string) clientes_input($data, 'client_id', ''));
    $clientIndex = clientes_find_index($store['clients'] ?? [], $clientId);

    if ($clientIndex === null) {
        demo_json(false, [
            'title' => 'Cliente no encontrado',
            'message' => 'No se encontró el cliente seleccionado.',
        ], 404);
    }

    $client = $store['clients'][$clientIndex];

    $existingPortalUser = clientes_find_user_by_client($store['users'] ?? [], $clientId);
    if ($existingPortalUser) {
        demo_json(false, [
            'title' => 'Portal ya creado',
            'message' => 'Este cliente ya tiene un acceso portal activo o registrado.',
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

    $newUser = [
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
        'assigned_executive_user_id' => $client['assigned_executive_user_id'] ?? null,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    $store['users'][] = $newUser;
    $client['has_portal_access'] = true;
    $store['clients'][$clientIndex] = $client;

    clientes_add_activity(
        $store,
        $clientId,
        'Acceso portal habilitado',
        'Se creó el usuario demo ' . $username . ' para el portal cliente.',
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
        'user' => $newUser,
    ]);
}

if ($action === 'add_note') {
    $clientId = trim((string) clientes_input($data, 'client_id', ''));
    $note = trim((string) clientes_input($data, 'note', ''));

    $clientIndex = clientes_find_index($store['clients'] ?? [], $clientId);
    if ($clientIndex === null) {
        demo_json(false, [
            'title' => 'Cliente no encontrado',
            'message' => 'No se encontró el cliente seleccionado.',
        ], 404);
    }

    if ($note === '') {
        demo_json(false, [
            'title' => 'Nota vacía',
            'message' => 'Debes escribir contenido para guardar la nota.',
        ], 422);
    }

    $noteRow = [
        'id' => demo_generate_id('cnote'),
        'client_id' => $clientId,
        'note' => $note,
        'author_name' => $currentUser['full_name'] ?? 'Gerencia',
        'created_at' => date('Y-m-d H:i:s'),
    ];

    $store['client_notes'][] = $noteRow;

    clientes_add_activity(
        $store,
        $clientId,
        'Nota interna agregada',
        $note,
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