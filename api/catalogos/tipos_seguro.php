<?php
require_once __DIR__ . '/_catalogos_common.php';

$pdo = cat_db();

function tipo_listar(PDO $pdo)
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
        $where[] = 't.estado = :estado';
        $params[':estado'] = $estado === 'activo' ? 1 : 0;
    }
    if ($q !== '') {
        $where[] = "(t.codigo LIKE :q_codigo OR t.nombre LIKE :q_nombre OR t.descripcion LIKE :q_descripcion OR t.ejemplo_uso LIKE :q_ejemplo_uso OR r.nombre LIKE :q_ramo)";
        $like = cat_bind_like($q);
        $params[':q_codigo'] = $like;
        $params[':q_nombre'] = $like;
        $params[':q_descripcion'] = $like;
        $params[':q_ejemplo_uso'] = $like;
        $params[':q_ramo'] = $like;
    }

    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $count = $pdo->prepare('SELECT COUNT(*)
        FROM seg_tipos_seguro t
        INNER JOIN seg_ramos r ON r.id = t.ramo_id' . $whereSql);
    $count->execute($params);

    $stmt = $pdo->prepare('SELECT t.id, t.ramo_id, t.codigo, t.nombre, t.descripcion, t.ejemplo_uso, t.orden_visual, t.estado, r.nombre AS ramo_nombre
        FROM seg_tipos_seguro t
        INNER JOIN seg_ramos r ON r.id = t.ramo_id' . $whereSql . '
        ORDER BY t.orden_visual ASC, t.nombre ASC, t.id ASC
        LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset);
    $stmt->execute($params);

    cb_json_success('Tipos de seguro cargados correctamente.', cat_response_page($stmt->fetchAll(PDO::FETCH_ASSOC), (int) $count->fetchColumn(), $page, $perPage));
}

function tipo_obtener(PDO $pdo)
{
    cat_require_catalogos('puede_ver');
    cb_json_success('Tipo de seguro cargado correctamente.', ['record' => cat_require_record($pdo, 'seg_tipos_seguro', (int) ($_GET['id'] ?? 0))]);
}

function tipo_opciones(PDO $pdo)
{
    cat_require_catalogos('puede_ver');
    $stmt = $pdo->query('SELECT id, codigo, nombre FROM seg_tipos_seguro WHERE estado = 1 ORDER BY orden_visual ASC, nombre ASC, id ASC');
    cb_json_success('Opciones cargadas correctamente.', ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function tipo_validar(PDO $pdo, array $payload, $id = 0)
{
    $ramoId = (int) ($payload['ramo_id'] ?? 0);
    $nombre = strtoupper(cat_trim($payload['nombre'] ?? ''));
    $descripcion = cat_nullable($payload['descripcion'] ?? '');
    $ejemploUso = cat_nullable($payload['ejemplo_uso'] ?? '');
    $ordenVisual = cat_int_range($payload['orden_visual'] ?? 0, 0, 0, 9999);
    $estado = cat_estado_value($payload['estado'] ?? 1, 1);
    $errors = [];

    foreach ([
        'nombre' => $payload['nombre'] ?? '',
        'descripcion' => $payload['descripcion'] ?? '',
        'ejemplo_uso' => $payload['ejemplo_uso'] ?? '',
    ] as $field => $value) {
        cat_validate_utf8_value($value, $field, $errors);
    }
    cat_validate_max($nombre, 'nombre', 160, $errors);
    cat_validate_max((string) $descripcion, 'descripcion', 65535, $errors);
    cat_validate_max((string) $ejemploUso, 'ejemplo_uso', 255, $errors);

    if ($ramoId <= 0) {
        $errors['ramo_id'] = 'Seleccione un ramo activo.';
    }
    if ($nombre === '') {
        $errors['nombre'] = 'Ingrese el nombre del tipo de seguro.';
    }
    cat_abort_if_errors($errors);

    $ramo = cat_fetch_one($pdo, 'SELECT id, estado FROM seg_ramos WHERE id = :id LIMIT 1', [':id' => $ramoId]);
    if (!$ramo) {
        cb_json_error('ramo_no_encontrado', 'Seleccione un ramo valido.', 422, ['ramo_id' => 'Ramo no encontrado.']);
    }
    if ((int) ($ramo['estado'] ?? 0) !== 1) {
        cb_json_error('ramo_desactivado', 'Solo se pueden usar ramos activos.', 409);
    }

    $sql = 'SELECT id FROM seg_tipos_seguro WHERE ramo_id = :ramo_id AND nombre = :nombre';
    $params = [':ramo_id' => $ramoId, ':nombre' => $nombre];
    if ((int) $id > 0) {
        $sql .= ' AND id <> :id';
        $params[':id'] = (int) $id;
    }
    $sql .= ' LIMIT 1';
    if (cat_fetch_one($pdo, $sql, $params)) {
        cat_duplicate_error('Ya existe un tipo de seguro con ese nombre en el ramo seleccionado.');
    }

    return [
        'ramo_id' => $ramoId,
        'nombre' => $nombre,
        'descripcion' => $descripcion,
        'ejemplo_uso' => $ejemploUso,
        'orden_visual' => $ordenVisual,
        'estado' => $estado,
    ];
}

function tipo_crear(PDO $pdo)
{
    cat_require_post_change('puede_crear');
    $data = tipo_validar($pdo, cat_payload());
    $data['codigo'] = cat_codigo_unico_desde_nombre($pdo, 'seg_tipos_seguro', $data['nombre']);
    $userId = cat_user_id();
    $now = cat_now_lima();

    try {
        $stmt = $pdo->prepare('INSERT INTO seg_tipos_seguro
            (ramo_id, codigo, nombre, descripcion, ejemplo_uso, orden_visual, estado, creado_por_usuario_externo_id, actualizado_por_usuario_externo_id, creado_en, actualizado_en)
            VALUES
            (:ramo_id, :codigo, :nombre, :descripcion, :ejemplo_uso, :orden_visual, :estado, :creado_por, :actualizado_por, :creado_en, :actualizado_en)');
        $stmt->execute($data + [
            'creado_por' => $userId,
            'actualizado_por' => $userId,
            'creado_en' => $now,
            'actualizado_en' => $now,
        ]);
        cb_json_success('Tipo de seguro creado correctamente.', ['id' => (int) $pdo->lastInsertId()], 201);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            cat_duplicate_error();
        }
        cat_db_error();
    }
}

function tipo_actualizar(PDO $pdo)
{
    cat_require_post_change('puede_editar');
    $payload = cat_payload();
    $id = (int) ($payload['id'] ?? 0);
    cat_require_record($pdo, 'seg_tipos_seguro', $id);
    $data = tipo_validar($pdo, $payload, $id);

    try {
        $stmt = $pdo->prepare('UPDATE seg_tipos_seguro
            SET ramo_id = :ramo_id,
                nombre = :nombre,
                descripcion = :descripcion,
                ejemplo_uso = :ejemplo_uso,
                orden_visual = :orden_visual,
                estado = :estado,
                actualizado_por_usuario_externo_id = :actualizado_por,
                actualizado_en = :actualizado_en
            WHERE id = :id');
        $stmt->execute($data + [
            'actualizado_por' => cat_user_id(),
            'actualizado_en' => cat_now_lima(),
            'id' => $id,
        ]);
        cb_json_success('Tipo de seguro actualizado correctamente.', ['id' => $id]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            cat_duplicate_error();
        }
        cat_db_error();
    }
}

function tipo_cambiar_estado(PDO $pdo)
{
    cat_require_post_change('puede_eliminar');
    $payload = cat_payload();
    $id = (int) ($payload['id'] ?? 0);
    $record = cat_require_record($pdo, 'seg_tipos_seguro', $id);
    $nuevoEstado = (int) ($record['estado'] ?? 0) === 1 ? 0 : 1;

    if ($nuevoEstado === 1) {
        $ramo = cat_fetch_one($pdo, 'SELECT estado FROM seg_ramos WHERE id = :id LIMIT 1', [':id' => (int) $record['ramo_id']]);
        if (!$ramo || (int) ($ramo['estado'] ?? 0) !== 1) {
            cb_json_error('ramo_desactivado', 'No se puede activar un tipo de seguro si su ramo no esta activo.', 409);
        }
    }

    $stmt = $pdo->prepare('UPDATE seg_tipos_seguro SET estado = :estado, actualizado_por_usuario_externo_id = :usuario, actualizado_en = :fecha WHERE id = :id');
    $stmt->execute([
        ':estado' => $nuevoEstado,
        ':usuario' => cat_user_id(),
        ':fecha' => cat_now_lima(),
        ':id' => $id,
    ]);
    cb_json_success($nuevoEstado === 1 ? 'Tipo de seguro activado correctamente.' : 'Tipo de seguro desactivado correctamente.', ['id' => $id, 'estado' => $nuevoEstado]);
}

try {
    if (cb_request_method() === 'GET') {
        $action = strtolower(cat_trim($_GET['action'] ?? 'list'));
        if ($action === 'get') {
            tipo_obtener($pdo);
        } elseif ($action === 'options') {
            tipo_opciones($pdo);
        } else {
            tipo_listar($pdo);
        }
    }

    $payload = cat_payload();
    $action = strtolower(cat_trim($payload['action'] ?? ''));
    if ($action === 'create') {
        tipo_crear($pdo);
    } elseif ($action === 'update') {
        tipo_actualizar($pdo);
    } elseif ($action === 'toggle') {
        tipo_cambiar_estado($pdo);
    }
    cb_json_error('accion_invalida', 'Accion no valida.', 400);
} catch (PDOException $e) {
    cat_db_error();
}
