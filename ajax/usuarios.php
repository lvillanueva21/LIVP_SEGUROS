<?php
require_once __DIR__ . '/../includes/bootstrap.php';

demo_require_roles(['gerente', 'superadmin']);

$editableRoles = ['ejecutivo', 'cliente'];
$currentUser = demo_current_user();

function usuarios_request_data(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    return $_POST ?: [];
}

function usuarios_input(array $data, string $key, mixed $default = null): mixed
{
    return $data[$key] ?? $default;
}

function usuarios_validate_document(string $document): bool
{
    return (bool) preg_match('/^\d{8}$/', $document);
}

function usuarios_find_index(array $items, string $id): ?int
{
    foreach ($items as $index => $item) {
        if (($item['id'] ?? null) === $id) {
            return $index;
        }
    }
    return null;
}

function usuarios_sync_client_access(array &$store, ?string $clientId): ?array
{
    if (!$clientId) {
        return null;
    }

    $clientIndex = usuarios_find_index($store['clients'] ?? [], $clientId);
    if ($clientIndex === null) {
        return null;
    }

    $hasActivePortal = false;
    foreach (($store['users'] ?? []) as $user) {
        if (
            ($user['role'] ?? '') === 'cliente'
            && ($user['status'] ?? '') === 'activo'
            && ($user['client_id'] ?? null) === $clientId
        ) {
            $hasActivePortal = true;
            break;
        }
    }

    $store['clients'][$clientIndex]['has_portal_access'] = $hasActivePortal;
    return $store['clients'][$clientIndex];
}

function usuarios_linked_client(array $store, ?string $clientId): ?array
{
    if (!$clientId) {
        return null;
    }

    return demo_find_by_id($store['clients'] ?? [], $clientId);
}

function usuarios_append_activity(array &$store, string $title, string $description, ?string $userId): void
{
    $store['activity_log'][] = [
        'id' => demo_generate_id('act'),
        'title' => $title,
        'description' => $description,
        'user_id' => $userId,
        'created_at' => date('Y-m-d H:i:s'),
    ];
}

$data = usuarios_request_data();
$action = trim((string) usuarios_input($data, 'action', ''));

if ($action === '') {
    demo_json(false, [
        'title' => 'Solicitud inválida',
        'message' => 'No se recibió ninguna acción válida.',
    ], 400);
}

$store = &demo_store_ref();

if ($action === 'create') {
    $role = trim((string) usuarios_input($data, 'role', ''));
    $fullName = trim((string) usuarios_input($data, 'full_name', ''));
    $document = trim((string) usuarios_input($data, 'document', ''));
    $email = trim((string) usuarios_input($data, 'email', ''));
    $phone = trim((string) usuarios_input($data, 'phone', ''));
    $status = trim((string) usuarios_input($data, 'status', 'activo'));
    $clientId = trim((string) usuarios_input($data, 'client_id', ''));

    if (!in_array($role, $editableRoles, true)) {
        demo_json(false, [
            'title' => 'Rol no permitido',
            'message' => 'Gerencia solo puede crear ejecutivos y clientes en esta fase.',
        ], 422);
    }

    if ($fullName === '') {
        demo_json(false, [
            'title' => 'Nombre requerido',
            'message' => 'Debes ingresar el nombre completo del usuario.',
        ], 422);
    }

    if (!usuarios_validate_document($document)) {
        demo_json(false, [
            'title' => 'Documento inválido',
            'message' => 'El documento debe tener exactamente 8 dígitos.',
        ], 422);
    }

    foreach (($store['users'] ?? []) as $existingUser) {
        if (($existingUser['document'] ?? '') === $document || ($existingUser['username'] ?? '') === $document) {
            demo_json(false, [
                'title' => 'Documento duplicado',
                'message' => 'Ya existe un usuario con ese documento/usuario en la demo.',
            ], 422);
        }
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        demo_json(false, [
            'title' => 'Correo inválido',
            'message' => 'Ingresa un correo con formato válido.',
        ], 422);
    }

    if (!in_array($status, ['activo', 'inactivo'], true)) {
        $status = 'activo';
    }

    $linkedClient = null;
    if ($role === 'cliente' && $clientId !== '') {
        $linkedClient = usuarios_linked_client($store, $clientId);
        if (!$linkedClient) {
            demo_json(false, [
                'title' => 'Cliente no encontrado',
                'message' => 'El cliente vinculado no existe en el store demo.',
            ], 422);
        }
    }

    $newUser = [
        'id' => demo_generate_id('usr'),
        'username' => $document,
        'password' => $document,
        'full_name' => $fullName,
        'role' => $role,
        'document' => $document,
        'email' => $email,
        'phone' => $phone,
        'status' => $status,
        'avatar' => demo_avatar_initials($fullName),
        'client_id' => $role === 'cliente' ? ($clientId !== '' ? $clientId : null) : null,
        'assigned_executive_user_id' => $role === 'cliente' ? ($linkedClient['assigned_executive_user_id'] ?? null) : null,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    $store['users'][] = $newUser;
    $updatedClients = [];

    if ($role === 'cliente' && $clientId !== '') {
        $updatedClient = usuarios_sync_client_access($store, $clientId);
        if ($updatedClient) {
            $updatedClients[] = $updatedClient;
        }
    }

    usuarios_append_activity(
        $store,
        'Usuario creado',
        'Se creó el usuario ' . $fullName . ' con rol ' . $role . '.',
        $currentUser['id'] ?? null
    );

    demo_json(true, [
        'title' => 'Usuario creado',
        'message' => 'Se registró el nuevo usuario demo correctamente.',
        'user' => $newUser,
        'updated_clients' => $updatedClients,
    ]);
}

if ($action === 'update') {
    $userId = trim((string) usuarios_input($data, 'user_id', ''));
    $userIndex = usuarios_find_index($store['users'] ?? [], $userId);

    if ($userIndex === null) {
        demo_json(false, [
            'title' => 'Usuario no encontrado',
            'message' => 'No se encontró el usuario que intentas editar.',
        ], 404);
    }

    $existingUser = $store['users'][$userIndex];

    if (($existingUser['id'] ?? null) === ($currentUser['id'] ?? null)) {
        demo_json(false, [
            'title' => 'Acción no permitida',
            'message' => 'No puedes editar tu propio usuario desde esta pantalla.',
        ], 403);
    }

    if (!in_array(($existingUser['role'] ?? ''), $editableRoles, true)) {
        demo_json(false, [
            'title' => 'Usuario protegido',
            'message' => 'Este usuario no puede editarse desde gerencia en esta fase.',
        ], 403);
    }

    $role = trim((string) usuarios_input($data, 'role', $existingUser['role'] ?? ''));
    $fullName = trim((string) usuarios_input($data, 'full_name', $existingUser['full_name'] ?? ''));
    $document = trim((string) usuarios_input($data, 'document', $existingUser['document'] ?? ''));
    $email = trim((string) usuarios_input($data, 'email', $existingUser['email'] ?? ''));
    $phone = trim((string) usuarios_input($data, 'phone', $existingUser['phone'] ?? ''));
    $status = trim((string) usuarios_input($data, 'status', $existingUser['status'] ?? 'activo'));
    $clientId = trim((string) usuarios_input($data, 'client_id', ''));

    if (!in_array($role, $editableRoles, true)) {
        demo_json(false, [
            'title' => 'Rol no permitido',
            'message' => 'Solo puedes mantener al usuario como ejecutivo o cliente.',
        ], 422);
    }

    if ($fullName === '') {
        demo_json(false, [
            'title' => 'Nombre requerido',
            'message' => 'Debes ingresar el nombre completo del usuario.',
        ], 422);
    }

    if (!usuarios_validate_document($document)) {
        demo_json(false, [
            'title' => 'Documento inválido',
            'message' => 'El documento debe tener exactamente 8 dígitos.',
        ], 422);
    }

    foreach (($store['users'] ?? []) as $existing) {
        if (($existing['id'] ?? '') === $userId) {
            continue;
        }
        if (($existing['document'] ?? '') === $document || ($existing['username'] ?? '') === $document) {
            demo_json(false, [
                'title' => 'Documento duplicado',
                'message' => 'Ya existe otro usuario con ese documento/usuario.',
            ], 422);
        }
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        demo_json(false, [
            'title' => 'Correo inválido',
            'message' => 'Ingresa un correo con formato válido.',
        ], 422);
    }

    if (!in_array($status, ['activo', 'inactivo'], true)) {
        $status = 'activo';
    }

    $oldClientId = $existingUser['client_id'] ?? null;
    $linkedClient = null;

    if ($role === 'cliente' && $clientId !== '') {
        $linkedClient = usuarios_linked_client($store, $clientId);
        if (!$linkedClient) {
            demo_json(false, [
                'title' => 'Cliente no encontrado',
                'message' => 'El cliente vinculado no existe en el store demo.',
            ], 422);
        }
    }

    $updatedUser = $existingUser;
    $updatedUser['full_name'] = $fullName;
    $updatedUser['role'] = $role;
    $updatedUser['document'] = $document;
    $updatedUser['username'] = $document;
    $updatedUser['password'] = $document;
    $updatedUser['email'] = $email;
    $updatedUser['phone'] = $phone;
    $updatedUser['status'] = $status;
    $updatedUser['avatar'] = demo_avatar_initials($fullName);
    $updatedUser['client_id'] = $role === 'cliente' ? ($clientId !== '' ? $clientId : null) : null;
    $updatedUser['assigned_executive_user_id'] = $role === 'cliente' ? ($linkedClient['assigned_executive_user_id'] ?? null) : null;

    $store['users'][$userIndex] = $updatedUser;

    $updatedClients = [];
    $syncIds = array_unique(array_filter([$oldClientId, $updatedUser['client_id'] ?? null]));
    foreach ($syncIds as $syncId) {
        $updatedClient = usuarios_sync_client_access($store, $syncId);
        if ($updatedClient) {
            $updatedClients[] = $updatedClient;
        }
    }

    usuarios_append_activity(
        $store,
        'Usuario actualizado',
        'Se actualizó el usuario ' . $fullName . '.',
        $currentUser['id'] ?? null
    );

    demo_json(true, [
        'title' => 'Usuario actualizado',
        'message' => 'Los datos del usuario demo se guardaron correctamente.',
        'user' => $updatedUser,
        'updated_clients' => $updatedClients,
    ]);
}

if ($action === 'toggle_status') {
    $userId = trim((string) usuarios_input($data, 'user_id', ''));
    $userIndex = usuarios_find_index($store['users'] ?? [], $userId);

    if ($userIndex === null) {
        demo_json(false, [
            'title' => 'Usuario no encontrado',
            'message' => 'No se encontró el usuario seleccionado.',
        ], 404);
    }

    $existingUser = $store['users'][$userIndex];

    if (($existingUser['id'] ?? null) === ($currentUser['id'] ?? null)) {
        demo_json(false, [
            'title' => 'Acción no permitida',
            'message' => 'No puedes cambiar el estado de tu propio usuario desde esta pantalla.',
        ], 403);
    }

    if (!in_array(($existingUser['role'] ?? ''), $editableRoles, true)) {
        demo_json(false, [
            'title' => 'Usuario protegido',
            'message' => 'Este usuario no puede activarse o inactivarse desde aquí.',
        ], 403);
    }

    $existingUser['status'] = ($existingUser['status'] ?? 'activo') === 'activo' ? 'inactivo' : 'activo';
    $store['users'][$userIndex] = $existingUser;

    $updatedClients = [];
    if (!empty($existingUser['client_id'])) {
        $updatedClient = usuarios_sync_client_access($store, $existingUser['client_id']);
        if ($updatedClient) {
            $updatedClients[] = $updatedClient;
        }
    }

    usuarios_append_activity(
        $store,
        'Estado de usuario actualizado',
        'Se cambió el estado del usuario ' . ($existingUser['full_name'] ?? 'demo') . ' a ' . ($existingUser['status'] ?? '—') . '.',
        $currentUser['id'] ?? null
    );

    demo_json(true, [
        'title' => 'Estado actualizado',
        'message' => 'El usuario quedó ' . ($existingUser['status'] ?? 'actualizado') . ' correctamente.',
        'user' => $existingUser,
        'updated_clients' => $updatedClients,
    ]);
}

demo_json(false, [
    'title' => 'Acción no soportada',
    'message' => 'La operación solicitada no está implementada.',
], 400);