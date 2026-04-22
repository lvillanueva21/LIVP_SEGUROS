<?php
require_once __DIR__ . '/../includes/bootstrap.php';

demo_require_roles(['gerente', 'superadmin']);

$currentUser = demo_current_user();

function catalogos_request_data(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    return $_POST ?: [];
}

function catalogos_input(array $data, string $key, mixed $default = null): mixed
{
    return $data[$key] ?? $default;
}

function catalogos_allowed(): array
{
    return [
        'insurers' => ['label' => 'Aseguradoras', 'prefix' => 'ins'],
        'insurance_types' => ['label' => 'Tipos de seguro', 'prefix' => 'type'],
        'policy_statuses' => ['label' => 'Estados de póliza', 'prefix' => 'pst'],
        'internal_categories' => ['label' => 'Categorías internas', 'prefix' => 'cat'],
    ];
}

function catalogos_find_index(array $items, string $id): ?int
{
    foreach ($items as $index => $item) {
        if (($item['id'] ?? null) === $id) {
            return $index;
        }
    }
    return null;
}

function catalogos_ensure_activity(array &$store): void
{
    if (!isset($store['activity_log']) || !is_array($store['activity_log'])) {
        $store['activity_log'] = [];
    }
}

function catalogos_add_activity(array &$store, string $title, string $description, ?string $userId): void
{
    catalogos_ensure_activity($store);

    $store['activity_log'][] = [
        'id' => demo_generate_id('act'),
        'title' => $title,
        'description' => $description,
        'user_id' => $userId,
        'created_at' => date('Y-m-d H:i:s'),
    ];
}

$data = catalogos_request_data();
$action = trim((string) catalogos_input($data, 'action', ''));

if ($action === '') {
    demo_json(false, [
        'title' => 'Solicitud inválida',
        'message' => 'No se recibió una acción válida.',
    ], 400);
}

$allowed = catalogos_allowed();
$catalogKey = trim((string) catalogos_input($data, 'catalog_key', ''));

if (!isset($allowed[$catalogKey])) {
    demo_json(false, [
        'title' => 'Catálogo inválido',
        'message' => 'El catálogo seleccionado no está permitido.',
    ], 422);
}

$store = &demo_store_ref();
$catalogLabel = $allowed[$catalogKey]['label'];
$catalogPrefix = $allowed[$catalogKey]['prefix'];

if (!isset($store[$catalogKey]) || !is_array($store[$catalogKey])) {
    $store[$catalogKey] = [];
}

if ($action === 'create') {
    $name = trim((string) catalogos_input($data, 'name', ''));
    $status = trim((string) catalogos_input($data, 'status', 'activo'));

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

    catalogos_add_activity(
        $store,
        'Catálogo actualizado',
        'Se creó el ítem "' . $name . '" en ' . mb_strtolower($catalogLabel) . '.',
        $currentUser['id'] ?? null
    );

    demo_json(true, [
        'title' => 'Ítem creado',
        'message' => 'El nuevo registro fue agregado correctamente a ' . mb_strtolower($catalogLabel) . '.',
        'catalog_key' => $catalogKey,
        'item' => $item,
    ]);
}

if ($action === 'edit') {
    $itemId = trim((string) catalogos_input($data, 'item_id', ''));
    $name = trim((string) catalogos_input($data, 'name', ''));
    $status = trim((string) catalogos_input($data, 'status', 'activo'));

    if ($itemId === '' || $name === '') {
        demo_json(false, [
            'title' => 'Datos incompletos',
            'message' => 'Debes indicar el ítem y el nuevo nombre.',
        ], 422);
    }

    $index = catalogos_find_index($store[$catalogKey], $itemId);
    if ($index === null) {
        demo_json(false, [
            'title' => 'Ítem no encontrado',
            'message' => 'No se encontró el registro que intentas editar.',
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
                'message' => 'Ya existe otro ítem con ese nombre dentro de ' . mb_strtolower($catalogLabel) . '.',
            ], 422);
        }
    }

    $store[$catalogKey][$index]['name'] = $name;
    $store[$catalogKey][$index]['status'] = $status;

    catalogos_add_activity(
        $store,
        'Catálogo actualizado',
        'Se editó el ítem "' . $name . '" en ' . mb_strtolower($catalogLabel) . '.',
        $currentUser['id'] ?? null
    );

    demo_json(true, [
        'title' => 'Ítem actualizado',
        'message' => 'Los cambios fueron aplicados correctamente en ' . mb_strtolower($catalogLabel) . '.',
        'catalog_key' => $catalogKey,
        'item' => $store[$catalogKey][$index],
    ]);
}

if ($action === 'toggle_status') {
    $itemId = trim((string) catalogos_input($data, 'item_id', ''));

    if ($itemId === '') {
        demo_json(false, [
            'title' => 'Ítem requerido',
            'message' => 'Debes indicar qué registro deseas actualizar.',
        ], 422);
    }

    $index = catalogos_find_index($store[$catalogKey], $itemId);
    if ($index === null) {
        demo_json(false, [
            'title' => 'Ítem no encontrado',
            'message' => 'No se encontró el registro solicitado.',
        ], 404);
    }

    $currentStatus = (string)($store[$catalogKey][$index]['status'] ?? 'activo');
    $store[$catalogKey][$index]['status'] = $currentStatus === 'activo' ? 'inactivo' : 'activo';

    catalogos_add_activity(
        $store,
        'Estado de catálogo actualizado',
        'Se cambió el estado de "' . ($store[$catalogKey][$index]['name'] ?? 'ítem') . '" en ' . mb_strtolower($catalogLabel) . '.',
        $currentUser['id'] ?? null
    );

    demo_json(true, [
        'title' => 'Estado actualizado',
        'message' => 'El registro fue actualizado correctamente en ' . mb_strtolower($catalogLabel) . '.',
        'catalog_key' => $catalogKey,
        'item' => $store[$catalogKey][$index],
    ]);
}

demo_json(false, [
    'title' => 'Acción no soportada',
    'message' => 'La operación solicitada no está implementada.',
], 400);