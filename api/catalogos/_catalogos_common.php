<?php
require_once __DIR__ . '/../../includes/session_guard.php';
require_once __DIR__ . '/../../includes/conexion_cliente.php';

function cat_db()
{
    try {
        return cb_cliente_db_required();
    } catch (Throwable $e) {
        cb_json_error('db_no_disponible', 'No se pudo conectar con la base de datos local.', 500);
    }
}

function cat_now_lima()
{
    return date('Y-m-d H:i:s');
}

function cat_payload()
{
    $payload = cb_request_payload();
    return is_array($payload) ? $payload : [];
}

function cat_trim($value)
{
    return trim((string) $value);
}

function cat_nullable($value)
{
    $value = cat_trim($value);
    return $value === '' ? null : $value;
}

function cat_codigo($value)
{
    $value = strtoupper(cat_trim($value));
    return preg_match('/^[A-Z0-9_-]{2,40}$/', $value) === 1 ? $value : '';
}

function cat_estado_value($value, $default = 1)
{
    if ($value === null || $value === '') {
        return (int) $default;
    }
    return (int) $value === 1 ? 1 : 0;
}

function cat_estado_filter()
{
    $estado = strtolower(cat_trim($_GET['estado'] ?? 'todos'));
    return in_array($estado, ['todos', 'activo', 'inactivo'], true) ? $estado : 'todos';
}

function cat_search()
{
    return substr(cat_trim($_GET['q'] ?? ''), 0, 120);
}

function cat_page()
{
    $page = (int) ($_GET['page'] ?? 1);
    return $page > 0 ? $page : 1;
}

function cat_per_page()
{
    $perPage = (int) ($_GET['per_page'] ?? 10);
    if ($perPage < 1) {
        return 10;
    }
    return min($perPage, 50);
}

function cat_response_page($rows, $total, $page, $perPage)
{
    $total = (int) $total;
    $perPage = (int) $perPage;
    $lastPage = $perPage > 0 ? max(1, (int) ceil($total / $perPage)) : 1;

    return [
        'rows' => $rows,
        'pagination' => [
            'page' => (int) $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => $lastPage,
        ],
    ];
}

function cat_require_catalogos($accion)
{
    cb_require_cliente_permission('catalogos', $accion);
}

function cat_require_post_change($accion)
{
    cb_require_method('POST');
    cat_require_catalogos($accion);
    cb_require_local_csrf('catalogos');
}

function cat_bind_like($value)
{
    return '%' . str_replace(['%', '_'], ['\\%', '\\_'], $value) . '%';
}

function cat_db_error()
{
    cb_json_error('error_bd', 'No se pudo completar la operacion solicitada.', 500);
}

function cat_duplicate_error($message = 'Ya existe un registro con los datos indicados.')
{
    cb_json_error('registro_duplicado', $message, 409);
}

function cat_user_id()
{
    $id = cb_cliente_usuario_externo_id();
    if ($id <= 0) {
        cb_json_error('usuario_no_identificado', 'No se pudo identificar al usuario actual.', 401);
    }
    return $id;
}

function cat_fetch_one(PDO $pdo, $sql, array $params)
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function cat_value_exists(PDO $pdo, $table, $column, $value, $excludeId = 0)
{
    if ($value === null || $value === '') {
        return false;
    }

    $sql = "SELECT id FROM {$table} WHERE {$column} = :value";
    $params = [':value' => $value];
    if ((int) $excludeId > 0) {
        $sql .= ' AND id <> :id';
        $params[':id'] = (int) $excludeId;
    }
    $sql .= ' LIMIT 1';

    return cat_fetch_one($pdo, $sql, $params) !== null;
}

function cat_require_record(PDO $pdo, $table, $id, $message = 'Registro no encontrado.')
{
    $id = (int) $id;
    if ($id <= 0) {
        cb_json_error('id_invalido', 'Identificador invalido.', 422);
    }

    $row = cat_fetch_one($pdo, "SELECT * FROM {$table} WHERE id = :id LIMIT 1", [':id' => $id]);
    if (!$row) {
        cb_json_error('no_encontrado', $message, 404);
    }

    return $row;
}

function cat_validate_email($value, $field, array &$errors)
{
    $value = cat_trim($value);
    if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
        $errors[$field] = 'Ingrese un correo valido.';
    }
}

function cat_validate_url($value, $field, array &$errors)
{
    $value = cat_trim($value);
    if ($value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
        $errors[$field] = 'Ingrese una URL valida.';
    }
}

function cat_abort_if_errors(array $errors)
{
    if ($errors) {
        cb_json_error('validacion', 'Revise los campos marcados.', 422, $errors);
    }
}
