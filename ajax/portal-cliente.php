<?php
require_once __DIR__ . '/../includes/bootstrap.php';

demo_require_roles(['cliente']);

$currentUser = demo_current_user();
$clientId = (string)($currentUser['client_id'] ?? '');

function portal_cliente_request_data(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    return $_POST ?: [];
}

function portal_cliente_input(array $data, string $key, mixed $default = null): mixed
{
    return $data[$key] ?? $default;
}

function portal_cliente_find_index(array $items, string $id): ?int
{
    foreach ($items as $index => $item) {
        if (($item['id'] ?? null) === $id) {
            return $index;
        }
    }
    return null;
}

function portal_cliente_ensure_stores(array &$store): void
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

    if (!isset($store['documents']) || !is_array($store['documents'])) {
        $store['documents'] = [];
    }
}

function portal_cliente_add_activity(array &$store, string $title, string $description, ?string $userId): void
{
    portal_cliente_ensure_stores($store);

    $store['activity_log'][] = [
        'id' => demo_generate_id('act'),
        'title' => $title,
        'description' => $description,
        'user_id' => $userId,
        'created_at' => date('Y-m-d H:i:s'),
    ];
}

function portal_cliente_add_timeline(array &$store, string $claimId, string $title, string $description, ?string $userId): array
{
    portal_cliente_ensure_stores($store);

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

function portal_cliente_next_claim_code(array $claims): string
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

function portal_cliente_policy_belongs_to_client(array $store, string $policyId, string $clientId): bool
{
    $policy = demo_find_by_id($store['policies'] ?? [], $policyId);
    return $policy && (string)($policy['client_id'] ?? '') === $clientId;
}

function portal_cliente_find_portal_user_index(array $users, string $clientId): ?int
{
    foreach ($users as $index => $user) {
        if (($user['role'] ?? '') === 'cliente' && (string)($user['client_id'] ?? '') === $clientId) {
            return $index;
        }
    }
    return null;
}

$data = portal_cliente_request_data();
$action = trim((string) portal_cliente_input($data, 'action', ''));

if ($action === '') {
    demo_json(false, [
        'title' => 'Solicitud inválida',
        'message' => 'No se recibió una acción válida.',
    ], 400);
}

$store = &demo_store_ref();
portal_cliente_ensure_stores($store);

if ($action === 'create_claim') {
    $policyId = trim((string) portal_cliente_input($data, 'policy_id', ''));
    $type = trim((string) portal_cliente_input($data, 'type', ''));
    $date = trim((string) portal_cliente_input($data, 'date', date('Y-m-d')));
    $description = trim((string) portal_cliente_input($data, 'description', ''));
    $attachmentName = trim((string) portal_cliente_input($data, 'attachment_name', ''));

    if ($policyId === '' || $type === '' || $description === '') {
        demo_json(false, [
            'title' => 'Datos incompletos',
            'message' => 'Debes completar póliza, tipo y descripción del siniestro.',
        ], 422);
    }

    if (!portal_cliente_policy_belongs_to_client($store, $policyId, $clientId)) {
        demo_json(false, [
            'title' => 'Acceso denegado',
            'message' => 'Solo puedes reportar siniestros sobre tus propias pólizas.',
        ], 403);
    }

    $policy = demo_find_by_id($store['policies'] ?? [], $policyId);

    $claim = [
        'id' => demo_generate_id('sin'),
        'code' => portal_cliente_next_claim_code($store['claims'] ?? []),
        'client_id' => $clientId,
        'policy_id' => $policyId,
        'type' => $type,
        'date' => $date,
        'status' => 'reportado',
        'assigned_user_id' => $policy['assigned_executive_user_id'] ?? null,
        'description' => $description,
        'notes' => '',
        'created_at' => date('Y-m-d H:i:s'),
    ];

    $store['claims'][] = $claim;

    $timeline = portal_cliente_add_timeline(
        $store,
        $claim['id'],
        'Siniestro reportado',
        'El cliente reportó el caso ' . $claim['code'] . ' desde el portal.',
        $currentUser['id'] ?? null
    );

    $observation = [
        'id' => demo_generate_id('cobs'),
        'claim_id' => $claim['id'],
        'observation' => 'Caso recibido por el portal cliente y pendiente de revisión inicial.',
        'author_name' => 'Sistema portal',
        'created_at' => date('Y-m-d H:i:s'),
    ];
    $store['claim_observations'][] = $observation;

    $document = null;
    if ($attachmentName !== '') {
        $document = [
            'id' => demo_generate_id('doc'),
            'entity_type' => 'claim',
            'entity_id' => $claim['id'],
            'name' => strtolower(str_replace(' ', '_', preg_replace('/[^A-Za-z0-9_\-\. ]/', '', $attachmentName))),
            'original_name' => $attachmentName,
            'type' => 'Adjunto de siniestro',
            'uploaded_by' => $currentUser['id'] ?? null,
            'uploaded_by_name' => $currentUser['full_name'] ?? 'Cliente portal',
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $store['documents'][] = $document;

        portal_cliente_add_timeline(
            $store,
            $claim['id'],
            'Adjunto demo recibido',
            'Se registró el adjunto demo ' . $attachmentName . ' en el expediente.',
            $currentUser['id'] ?? null
        );
    }

    portal_cliente_add_activity(
        $store,
        'Siniestro reportado por cliente',
        'El cliente registró el siniestro ' . $claim['code'] . ' desde el portal.',
        $currentUser['id'] ?? null
    );

    demo_json(true, [
        'title' => 'Siniestro reportado',
        'message' => 'Tu caso fue registrado correctamente y quedó pendiente de revisión.',
        'claim' => $claim,
        'timeline' => $timeline,
        'observation' => $observation,
        'document' => $document,
    ]);
}

if ($action === 'update_profile') {
    $name = trim((string) portal_cliente_input($data, 'name', ''));
    $email = trim((string) portal_cliente_input($data, 'email', ''));
    $phone = trim((string) portal_cliente_input($data, 'phone', ''));
    $address = trim((string) portal_cliente_input($data, 'address', ''));

    if ($name === '') {
        demo_json(false, [
            'title' => 'Nombre requerido',
            'message' => 'Debes ingresar tu nombre o razón social.',
        ], 422);
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        demo_json(false, [
            'title' => 'Correo inválido',
            'message' => 'Ingresa un correo con formato válido.',
        ], 422);
    }

    $clientIndex = portal_cliente_find_index($store['clients'] ?? [], $clientId);
    if ($clientIndex === null) {
        demo_json(false, [
            'title' => 'Cliente no encontrado',
            'message' => 'No se encontró la ficha del cliente vinculada a tu usuario.',
        ], 404);
    }

    $preferences = [
        'email' => portal_cliente_input($data, 'pref_email') === '1',
        'whatsapp' => portal_cliente_input($data, 'pref_whatsapp') === '1',
        'phone' => portal_cliente_input($data, 'pref_phone') === '1',
    ];

    $store['clients'][$clientIndex]['name'] = $name;
    $store['clients'][$clientIndex]['email'] = $email;
    $store['clients'][$clientIndex]['phone'] = $phone;
    $store['clients'][$clientIndex]['address'] = $address;
    $store['clients'][$clientIndex]['contact_preferences'] = $preferences;

    $userIndex = portal_cliente_find_portal_user_index($store['users'] ?? [], $clientId);
    $updatedUser = null;

    if ($userIndex !== null) {
        $store['users'][$userIndex]['full_name'] = $name;
        $store['users'][$userIndex]['email'] = $email;
        $store['users'][$userIndex]['phone'] = $phone;
        $updatedUser = $store['users'][$userIndex];
    }

    portal_cliente_add_activity(
        $store,
        'Perfil actualizado',
        'El cliente actualizó sus datos básicos y preferencias de contacto desde el portal.',
        $currentUser['id'] ?? null
    );

    demo_json(true, [
        'title' => 'Perfil actualizado',
        'message' => 'Tus datos fueron actualizados correctamente.',
        'client' => $store['clients'][$clientIndex],
        'user' => $updatedUser,
    ]);
}

if ($action === 'change_password') {
    $currentPassword = trim((string) portal_cliente_input($data, 'current_password', ''));
    $newPassword = trim((string) portal_cliente_input($data, 'new_password', ''));
    $confirmPassword = trim((string) portal_cliente_input($data, 'confirm_password', ''));

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        demo_json(false, [
            'title' => 'Datos incompletos',
            'message' => 'Completa la contraseña actual, la nueva y su confirmación.',
        ], 422);
    }

    if ($newPassword !== $confirmPassword) {
        demo_json(false, [
            'title' => 'Confirmación incorrecta',
            'message' => 'La nueva contraseña y la confirmación no coinciden.',
        ], 422);
    }

    if (strlen($newPassword) < 4) {
        demo_json(false, [
            'title' => 'Clave muy corta',
            'message' => 'La nueva contraseña debe tener al menos 4 caracteres.',
        ], 422);
    }

    $userIndex = portal_cliente_find_portal_user_index($store['users'] ?? [], $clientId);
    if ($userIndex === null) {
        demo_json(false, [
            'title' => 'Usuario no encontrado',
            'message' => 'No se encontró el usuario del portal asociado a este cliente.',
        ], 404);
    }

    $storedPassword = (string)($store['users'][$userIndex]['password'] ?? '');
    if ($currentPassword !== $storedPassword) {
        demo_json(false, [
            'title' => 'Clave actual incorrecta',
            'message' => 'La contraseña actual ingresada no coincide con tu acceso vigente.',
        ], 422);
    }

    $store['users'][$userIndex]['password'] = $newPassword;

    portal_cliente_add_activity(
        $store,
        'Contraseña actualizada',
        'El cliente cambió su contraseña demo desde el portal.',
        $currentUser['id'] ?? null
    );

    demo_json(true, [
        'title' => 'Contraseña actualizada',
        'message' => 'Tu clave demo fue actualizada correctamente.',
    ]);
}

demo_json(false, [
    'title' => 'Acción no soportada',
    'message' => 'La operación solicitada no está implementada.',
], 400);