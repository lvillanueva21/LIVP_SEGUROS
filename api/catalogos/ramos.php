<?php
require_once __DIR__ . '/_catalogos_common.php';

$pdo = cat_db();

function ramo_listar(PDO $pdo)
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
        $where[] = 'estado = :estado';
        $params[':estado'] = $estado === 'activo' ? 1 : 0;
    }
    if ($q !== '') {
        $where[] = "(codigo LIKE :q ESCAPE '\\\\' OR nombre LIKE :q ESCAPE '\\\\' OR descripcion LIKE :q ESCAPE '\\\\')";
        $params[':q'] = cat_bind_like($q);
    }

    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $count = $pdo->prepare('SELECT COUNT(*) FROM seg_ramos' . $whereSql);
    $count->execute($params);

    $stmt = $pdo->prepare('SELECT id, codigo, nombre, descripcion, estado
        FROM seg_ramos' . $whereSql . '
        ORDER BY nombre ASC, id ASC
        LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset);
    $stmt->execute($params);

    cb_json_success('Ramos cargados correctamente.', cat_response_page($stmt->fetchAll(PDO::FETCH_ASSOC), (int) $count->fetchColumn(), $page, $perPage));
}

function ramo_obtener(PDO $pdo)
{
    cat_require_catalogos('puede_ver');
    cb_json_success('Ramo cargado correctamente.', ['record' => cat_require_record($pdo, 'seg_ramos', (int) ($_GET['id'] ?? 0))]);
}

function ramo_opciones(PDO $pdo)
{
    cat_require_catalogos('puede_ver');
    $stmt = $pdo->query('SELECT id, codigo, nombre FROM seg_ramos WHERE estado = 1 ORDER BY nombre ASC, id ASC');
    cb_json_success('Opciones cargadas correctamente.', ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function ramo_validar(PDO $pdo, array $payload, $id = 0)
{
    $codigo = cat_codigo($payload['codigo'] ?? '');
    $nombre = strtoupper(cat_trim($payload['nombre'] ?? ''));
    $descripcion = cat_nullable($payload['descripcion'] ?? '');
    $estado = cat_estado_value($payload['estado'] ?? 1, 1);
    $errors = [];

    foreach ([
        'codigo' => $payload['codigo'] ?? '',
        'nombre' => $payload['nombre'] ?? '',
        'descripcion' => $payload['descripcion'] ?? '',
    ] as $field => $value) {
        cat_validate_utf8_value($value, $field, $errors);
    }
    cat_validate_max($codigo, 'codigo', 40, $errors);
    cat_validate_max($nombre, 'nombre', 120, $errors);

    if ($codigo === '') {
        $errors['codigo'] = 'Ingrese un codigo valido.';
    }
    if ($nombre === '') {
        $errors['nombre'] = 'Ingrese el nombre del ramo.';
    }
    cat_abort_if_errors($errors);

    if (cat_value_exists($pdo, 'seg_ramos', 'codigo', $codigo, $id)) {
        cat_duplicate_error('Ya existe un ramo con ese codigo.');
    }
    if (cat_value_exists($pdo, 'seg_ramos', 'nombre', $nombre, $id)) {
        cat_duplicate_error('Ya existe un ramo con ese nombre.');
    }

    return [
        'codigo' => $codigo,
        'nombre' => $nombre,
        'descripcion' => $descripcion,
        'estado' => $estado,
    ];
}

function ramo_crear(PDO $pdo)
{
    cat_require_post_change('puede_crear');
    $data = ramo_validar($pdo, cat_payload());
    $userId = cat_user_id();
    $now = cat_now_lima();

    try {
        $stmt = $pdo->prepare('INSERT INTO seg_ramos
            (codigo, nombre, descripcion, estado, creado_por_usuario_externo_id, actualizado_por_usuario_externo_id, creado_en, actualizado_en)
            VALUES
            (:codigo, :nombre, :descripcion, :estado, :creado_por, :actualizado_por, :creado_en, :actualizado_en)');
        $stmt->execute($data + [
            'creado_por' => $userId,
            'actualizado_por' => $userId,
            'creado_en' => $now,
            'actualizado_en' => $now,
        ]);
        cb_json_success('Ramo creado correctamente.', ['id' => (int) $pdo->lastInsertId()], 201);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            cat_duplicate_error();
        }
        cat_db_error();
    }
}

function ramo_actualizar(PDO $pdo)
{
    cat_require_post_change('puede_editar');
    $payload = cat_payload();
    $id = (int) ($payload['id'] ?? 0);
    cat_require_record($pdo, 'seg_ramos', $id);
    $data = ramo_validar($pdo, $payload, $id);

    try {
        $stmt = $pdo->prepare('UPDATE seg_ramos
            SET codigo = :codigo,
                nombre = :nombre,
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
        cb_json_success('Ramo actualizado correctamente.', ['id' => $id]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            cat_duplicate_error();
        }
        cat_db_error();
    }
}

function ramo_cambiar_estado(PDO $pdo)
{
    cat_require_post_change('puede_eliminar');
    $payload = cat_payload();
    $id = (int) ($payload['id'] ?? 0);
    $record = cat_require_record($pdo, 'seg_ramos', $id);
    $nuevoEstado = (int) ($record['estado'] ?? 0) === 1 ? 0 : 1;

    if ($nuevoEstado === 0) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM seg_productos WHERE ramo_id = :id AND estado = 1');
        $stmt->execute([':id' => $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            cb_json_error('dependencia_activa', 'No se puede desactivar un ramo con productos activos.', 409);
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM seg_tipos_seguro WHERE ramo_id = :id AND estado = 1');
        $stmt->execute([':id' => $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            cb_json_error('dependencia_activa', 'No se puede desactivar un ramo con tipos de seguro activos.', 409);
        }
    }

    $stmt = $pdo->prepare('UPDATE seg_ramos SET estado = :estado, actualizado_por_usuario_externo_id = :usuario, actualizado_en = :fecha WHERE id = :id');
    $stmt->execute([
        ':estado' => $nuevoEstado,
        ':usuario' => cat_user_id(),
        ':fecha' => cat_now_lima(),
        ':id' => $id,
    ]);
    cb_json_success($nuevoEstado === 1 ? 'Ramo activado correctamente.' : 'Ramo desactivado correctamente.', ['id' => $id, 'estado' => $nuevoEstado]);
}

try {
    if (cb_request_method() === 'GET') {
        $action = strtolower(cat_trim($_GET['action'] ?? 'list'));
        if ($action === 'get') {
            ramo_obtener($pdo);
        } elseif ($action === 'options') {
            ramo_opciones($pdo);
        } else {
            ramo_listar($pdo);
        }
    }

    $payload = cat_payload();
    $action = strtolower(cat_trim($payload['action'] ?? ''));
    if ($action === 'create') {
        ramo_crear($pdo);
    } elseif ($action === 'update') {
        ramo_actualizar($pdo);
    } elseif ($action === 'toggle') {
        ramo_cambiar_estado($pdo);
    }
    cb_json_error('accion_invalida', 'Accion no valida.', 400);
} catch (PDOException $e) {
    cat_db_error();
}
