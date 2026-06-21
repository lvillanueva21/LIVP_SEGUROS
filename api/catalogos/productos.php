<?php
require_once __DIR__ . '/_catalogos_common.php';

$pdo = cat_db();

function prod_listar(PDO $pdo)
{
    cat_require_catalogos('puede_ver');

    $page = cat_page();
    $perPage = cat_per_page();
    $offset = ($page - 1) * $perPage;
    $estado = cat_estado_filter();
    $q = cat_search();
    $where = [];
    $params = [];

    if ($estado !== 'todos') {
        $where[] = 'p.estado = :estado';
        $params[':estado'] = $estado === 'activo' ? 1 : 0;
    }
    if ($q !== '') {
        $where[] = "(p.codigo LIKE :q OR p.nombre_producto LIKE :q OR p.nombre_plan LIKE :q OR a.razon_social LIKE :q OR r.nombre LIKE :q)";
        $params[':q'] = cat_bind_like($q);
    }

    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $count = $pdo->prepare('SELECT COUNT(*)
        FROM seg_productos p
        INNER JOIN seg_aseguradoras a ON a.id = p.aseguradora_id
        INNER JOIN seg_ramos r ON r.id = p.ramo_id' . $whereSql);
    $count->execute($params);

    $stmt = $pdo->prepare('SELECT p.id, p.aseguradora_id, p.ramo_id, p.codigo, p.nombre_producto, p.nombre_plan, p.descripcion, p.estado,
            a.razon_social AS aseguradora_nombre, r.nombre AS ramo_nombre
        FROM seg_productos p
        INNER JOIN seg_aseguradoras a ON a.id = p.aseguradora_id
        INNER JOIN seg_ramos r ON r.id = p.ramo_id' . $whereSql . '
        ORDER BY p.nombre_producto ASC, p.nombre_plan ASC, p.id ASC
        LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset);
    $stmt->execute($params);

    cb_json_success('Productos cargados correctamente.', cat_response_page($stmt->fetchAll(PDO::FETCH_ASSOC), (int) $count->fetchColumn(), $page, $perPage));
}

function prod_obtener(PDO $pdo)
{
    cat_require_catalogos('puede_ver');
    cb_json_success('Producto cargado correctamente.', ['record' => cat_require_record($pdo, 'seg_productos', (int) ($_GET['id'] ?? 0))]);
}

function prod_dependencias_activas(PDO $pdo, $aseguradoraId, $ramoId)
{
    $aseguradora = cat_fetch_one($pdo, 'SELECT id, estado FROM seg_aseguradoras WHERE id = :id LIMIT 1', [':id' => (int) $aseguradoraId]);
    if (!$aseguradora) {
        cb_json_error('aseguradora_no_encontrada', 'Seleccione una aseguradora valida.', 422, ['aseguradora_id' => 'Aseguradora no encontrada.']);
    }
    if ((int) ($aseguradora['estado'] ?? 0) !== 1) {
        cb_json_error('aseguradora_desactivada', 'La aseguradora seleccionada debe estar activa.', 409);
    }

    $ramo = cat_fetch_one($pdo, 'SELECT id, estado FROM seg_ramos WHERE id = :id LIMIT 1', [':id' => (int) $ramoId]);
    if (!$ramo) {
        cb_json_error('ramo_no_encontrado', 'Seleccione un ramo valido.', 422, ['ramo_id' => 'Ramo no encontrado.']);
    }
    if ((int) ($ramo['estado'] ?? 0) !== 1) {
        cb_json_error('ramo_desactivado', 'El ramo seleccionado debe estar activo.', 409);
    }
}

function prod_combo_exists(PDO $pdo, $aseguradoraId, $ramoId, $nombreProducto, $nombrePlan, $excludeId = 0)
{
    $sql = "SELECT id FROM seg_productos
            WHERE aseguradora_id = :aseguradora_id
              AND ramo_id = :ramo_id
              AND nombre_producto = :nombre_producto
              AND COALESCE(nombre_plan, '') = :nombre_plan";
    $params = [
        ':aseguradora_id' => (int) $aseguradoraId,
        ':ramo_id' => (int) $ramoId,
        ':nombre_producto' => $nombreProducto,
        ':nombre_plan' => (string) $nombrePlan,
    ];
    if ((int) $excludeId > 0) {
        $sql .= ' AND id <> :id';
        $params[':id'] = (int) $excludeId;
    }
    $sql .= ' LIMIT 1';

    return cat_fetch_one($pdo, $sql, $params) !== null;
}

function prod_validar(PDO $pdo, array $payload, $id = 0)
{
    $aseguradoraId = (int) ($payload['aseguradora_id'] ?? 0);
    $ramoId = (int) ($payload['ramo_id'] ?? 0);
    $codigo = cat_codigo($payload['codigo'] ?? '');
    $nombreProducto = strtoupper(cat_trim($payload['nombre_producto'] ?? ''));
    $nombrePlan = cat_trim($payload['nombre_plan'] ?? '');
    $descripcion = cat_nullable($payload['descripcion'] ?? '');
    $estado = cat_estado_value($payload['estado'] ?? 1, 1);
    $errors = [];

    foreach ([
        'codigo' => $payload['codigo'] ?? '',
        'nombre_producto' => $payload['nombre_producto'] ?? '',
        'nombre_plan' => $payload['nombre_plan'] ?? '',
        'descripcion' => $payload['descripcion'] ?? '',
    ] as $field => $value) {
        cat_validate_utf8_value($value, $field, $errors);
    }
    cat_validate_max($codigo, 'codigo', 40, $errors);
    cat_validate_max($nombreProducto, 'nombre_producto', 160, $errors);
    cat_validate_max($nombrePlan, 'nombre_plan', 160, $errors);

    if ($aseguradoraId <= 0) {
        $errors['aseguradora_id'] = 'Seleccione una aseguradora.';
    }
    if ($ramoId <= 0) {
        $errors['ramo_id'] = 'Seleccione un ramo.';
    }
    if ($codigo === '') {
        $errors['codigo'] = 'Ingrese un codigo valido.';
    }
    if ($nombreProducto === '') {
        $errors['nombre_producto'] = 'Ingrese el producto.';
    }
    cat_abort_if_errors($errors);

    prod_dependencias_activas($pdo, $aseguradoraId, $ramoId);

    if (cat_value_exists($pdo, 'seg_productos', 'codigo', $codigo, $id)) {
        cat_duplicate_error('Ya existe un producto o plan con ese codigo.');
    }
    if (prod_combo_exists($pdo, $aseguradoraId, $ramoId, $nombreProducto, $nombrePlan, $id)) {
        cat_duplicate_error('Ya existe ese producto o plan para la aseguradora y ramo seleccionados.');
    }

    return [
        'aseguradora_id' => $aseguradoraId,
        'ramo_id' => $ramoId,
        'codigo' => $codigo,
        'nombre_producto' => $nombreProducto,
        'nombre_plan' => $nombrePlan,
        'descripcion' => $descripcion,
        'estado' => $estado,
    ];
}

function prod_crear(PDO $pdo)
{
    cat_require_post_change('puede_crear');
    $data = prod_validar($pdo, cat_payload());
    $userId = cat_user_id();
    $now = cat_now_lima();

    try {
        $stmt = $pdo->prepare('INSERT INTO seg_productos
            (aseguradora_id, ramo_id, codigo, nombre_producto, nombre_plan, descripcion, estado, creado_por_usuario_externo_id, actualizado_por_usuario_externo_id, creado_en, actualizado_en)
            VALUES
            (:aseguradora_id, :ramo_id, :codigo, :nombre_producto, :nombre_plan, :descripcion, :estado, :creado_por, :actualizado_por, :creado_en, :actualizado_en)');
        $stmt->execute($data + [
            'creado_por' => $userId,
            'actualizado_por' => $userId,
            'creado_en' => $now,
            'actualizado_en' => $now,
        ]);
        cb_json_success('Producto o plan creado correctamente.', ['id' => (int) $pdo->lastInsertId()], 201);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            cat_duplicate_error();
        }
        cat_db_error();
    }
}

function prod_actualizar(PDO $pdo)
{
    cat_require_post_change('puede_editar');
    $payload = cat_payload();
    $id = (int) ($payload['id'] ?? 0);
    cat_require_record($pdo, 'seg_productos', $id);
    $data = prod_validar($pdo, $payload, $id);

    try {
        $stmt = $pdo->prepare('UPDATE seg_productos
            SET aseguradora_id = :aseguradora_id,
                ramo_id = :ramo_id,
                codigo = :codigo,
                nombre_producto = :nombre_producto,
                nombre_plan = :nombre_plan,
                descripcion = :descripcion,
                estado = :estado,
                actualizado_por_usuario_externo_id = :actualizado_por,
                actualizado_en = :actualizado_en
            WHERE id = :id');
        $stmt->execute($data + [
            'actualizado_por' => cat_user_id(),
            'actualizado_en' => cat_now_lima(),
            'id' => $id,
        ]);
        cb_json_success('Producto o plan actualizado correctamente.', ['id' => $id]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            cat_duplicate_error();
        }
        cat_db_error();
    }
}

function prod_cambiar_estado(PDO $pdo)
{
    cat_require_post_change('puede_eliminar');
    $payload = cat_payload();
    $id = (int) ($payload['id'] ?? 0);
    $record = cat_require_record($pdo, 'seg_productos', $id);
    $nuevoEstado = (int) ($record['estado'] ?? 0) === 1 ? 0 : 1;

    if ($nuevoEstado === 1) {
        prod_dependencias_activas($pdo, (int) $record['aseguradora_id'], (int) $record['ramo_id']);
    }

    $stmt = $pdo->prepare('UPDATE seg_productos SET estado = :estado, actualizado_por_usuario_externo_id = :usuario, actualizado_en = :fecha WHERE id = :id');
    $stmt->execute([
        ':estado' => $nuevoEstado,
        ':usuario' => cat_user_id(),
        ':fecha' => cat_now_lima(),
        ':id' => $id,
    ]);
    cb_json_success($nuevoEstado === 1 ? 'Producto o plan activado correctamente.' : 'Producto o plan desactivado correctamente.', ['id' => $id, 'estado' => $nuevoEstado]);
}

try {
    if (cb_request_method() === 'GET') {
        $action = strtolower(cat_trim($_GET['action'] ?? 'list'));
        if ($action === 'get') {
            prod_obtener($pdo);
        } else {
            prod_listar($pdo);
        }
    }

    $payload = cat_payload();
    $action = strtolower(cat_trim($payload['action'] ?? ''));
    if ($action === 'create') {
        prod_crear($pdo);
    } elseif ($action === 'update') {
        prod_actualizar($pdo);
    } elseif ($action === 'toggle') {
        prod_cambiar_estado($pdo);
    }
    cb_json_error('accion_invalida', 'Accion no valida.', 400);
} catch (PDOException $e) {
    cat_db_error();
}
