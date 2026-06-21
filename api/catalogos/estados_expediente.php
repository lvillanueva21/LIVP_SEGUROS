<?php
require_once __DIR__ . '/_catalogos_common.php';

$pdo = cat_db();

function estado_exp_listar(PDO $pdo)
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
        $where[] = "(codigo LIKE :q OR nombre LIKE :q OR descripcion LIKE :q OR ejemplo_uso LIKE :q)";
        $params[':q'] = cat_bind_like($q);
    }

    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $count = $pdo->prepare('SELECT COUNT(*) FROM seg_estados_expediente' . $whereSql);
    $count->execute($params);

    $stmt = $pdo->prepare('SELECT id, codigo, nombre, descripcion, ejemplo_uso, color_etiqueta, orden_visual, es_inicial, estado
        FROM seg_estados_expediente' . $whereSql . '
        ORDER BY orden_visual ASC, nombre ASC, id ASC
        LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset);
    $stmt->execute($params);

    cb_json_success('Estados de expediente cargados correctamente.', cat_response_page($stmt->fetchAll(PDO::FETCH_ASSOC), (int) $count->fetchColumn(), $page, $perPage));
}

function estado_exp_obtener(PDO $pdo)
{
    cat_require_catalogos('puede_ver');
    cb_json_success('Estado de expediente cargado correctamente.', ['record' => cat_require_record($pdo, 'seg_estados_expediente', (int) ($_GET['id'] ?? 0))]);
}

function estado_exp_validar(PDO $pdo, array $payload, $id = 0)
{
    $nombre = strtoupper(cat_trim($payload['nombre'] ?? ''));
    $descripcion = cat_nullable($payload['descripcion'] ?? '');
    $ejemploUso = cat_nullable($payload['ejemplo_uso'] ?? '');
    $colorEtiqueta = cat_trim($payload['color_etiqueta'] ?? '#6c757d');
    $ordenVisual = cat_int_range($payload['orden_visual'] ?? 0, 0, 0, 9999);
    $esInicial = cat_bool_value($payload['es_inicial'] ?? 0, 0);
    $estado = cat_estado_value($payload['estado'] ?? 1, 1);
    $errors = [];

    foreach ([
        'nombre' => $payload['nombre'] ?? '',
        'descripcion' => $payload['descripcion'] ?? '',
        'ejemplo_uso' => $payload['ejemplo_uso'] ?? '',
    ] as $field => $value) {
        cat_validate_utf8_value($value, $field, $errors);
    }
    cat_validate_max($nombre, 'nombre', 120, $errors);
    cat_validate_max((string) $descripcion, 'descripcion', 65535, $errors);
    cat_validate_max((string) $ejemploUso, 'ejemplo_uso', 255, $errors);
    cat_validate_hex_color($colorEtiqueta, 'color_etiqueta', $errors);

    if ($nombre === '') {
        $errors['nombre'] = 'Ingrese el nombre del estado.';
    }
    cat_abort_if_errors($errors);

    if (cat_value_exists($pdo, 'seg_estados_expediente', 'nombre', $nombre, $id)) {
        cat_duplicate_error('Ya existe un estado de expediente con ese nombre.');
    }

    if ($estado === 0 && $esInicial === 1) {
        cb_json_error('estado_inicial_invalido', 'Un estado inicial debe quedar activo.', 422, ['estado' => 'El estado inicial debe estar activo.']);
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM seg_estados_expediente WHERE estado = 1' . ((int) $id > 0 ? ' AND id <> :id' : ''));
    if ((int) $id > 0) {
        $stmt->execute([':id' => (int) $id]);
    } else {
        $stmt->execute();
    }
    $activosDistintos = (int) $stmt->fetchColumn();
    if ((int) $id === 0 && $estado === 1 && $activosDistintos === 0 && $esInicial !== 1) {
        cb_json_error('primer_estado_inicial_requerido', 'El primer estado activo debe marcarse como estado inicial.', 422, ['es_inicial' => 'Marque este estado como inicial.']);
    }

    return [
        'nombre' => $nombre,
        'descripcion' => $descripcion,
        'ejemplo_uso' => $ejemploUso,
        'color_etiqueta' => $colorEtiqueta,
        'orden_visual' => $ordenVisual,
        'es_inicial' => $esInicial,
        'estado' => $estado,
    ];
}

function estado_exp_no_desactivar_unico_inicial(PDO $pdo, array $record, $nuevoEstado = null)
{
    if ((int) ($record['es_inicial'] ?? 0) !== 1 || (int) ($record['estado'] ?? 0) !== 1) {
        return;
    }
    if ($nuevoEstado === 1 || $nuevoEstado === null) {
        return;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM seg_estados_expediente WHERE estado = 1 AND es_inicial = 1 AND id <> :id');
    $stmt->execute([':id' => (int) $record['id']]);
    if ((int) $stmt->fetchColumn() === 0) {
        cb_json_error('estado_inicial_unico', 'No se puede desactivar el unico estado inicial activo.', 409);
    }
}

function estado_exp_no_quitar_unico_inicial(PDO $pdo, array $record, array $data)
{
    if ((int) ($record['es_inicial'] ?? 0) !== 1 || (int) ($record['estado'] ?? 0) !== 1) {
        return;
    }
    if ((int) ($data['estado'] ?? 0) !== 1 || (int) ($data['es_inicial'] ?? 0) === 1) {
        return;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM seg_estados_expediente WHERE estado = 1 AND es_inicial = 1 AND id <> :id');
    $stmt->execute([':id' => (int) $record['id']]);
    if ((int) $stmt->fetchColumn() === 0) {
        cb_json_error('estado_inicial_unico', 'Debe existir un estado inicial activo.', 409);
    }
}

function estado_exp_crear(PDO $pdo)
{
    cat_require_post_change('puede_crear');
    $data = estado_exp_validar($pdo, cat_payload());
    $data['codigo'] = cat_codigo_unico_desde_nombre($pdo, 'seg_estados_expediente', $data['nombre']);
    $userId = cat_user_id();
    $now = cat_now_lima();

    try {
        $pdo->beginTransaction();
        if ((int) $data['es_inicial'] === 1) {
            $pdo->prepare('UPDATE seg_estados_expediente SET es_inicial = 0, actualizado_por_usuario_externo_id = :usuario, actualizado_en = :fecha WHERE estado = 1 AND es_inicial = 1')
                ->execute([':usuario' => $userId, ':fecha' => $now]);
        }

        $stmt = $pdo->prepare('INSERT INTO seg_estados_expediente
            (codigo, nombre, descripcion, ejemplo_uso, color_etiqueta, orden_visual, es_inicial, estado, creado_por_usuario_externo_id, actualizado_por_usuario_externo_id, creado_en, actualizado_en)
            VALUES
            (:codigo, :nombre, :descripcion, :ejemplo_uso, :color_etiqueta, :orden_visual, :es_inicial, :estado, :creado_por, :actualizado_por, :creado_en, :actualizado_en)');
        $stmt->execute($data + [
            'creado_por' => $userId,
            'actualizado_por' => $userId,
            'creado_en' => $now,
            'actualizado_en' => $now,
        ]);
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();
        cb_json_success('Estado de expediente creado correctamente.', ['id' => $id], 201);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e->getCode() === '23000') {
            cat_duplicate_error();
        }
        cat_db_error();
    }
}

function estado_exp_actualizar(PDO $pdo)
{
    cat_require_post_change('puede_editar');
    $payload = cat_payload();
    $id = (int) ($payload['id'] ?? 0);
    $record = cat_require_record($pdo, 'seg_estados_expediente', $id);
    $data = estado_exp_validar($pdo, $payload, $id);
    estado_exp_no_desactivar_unico_inicial($pdo, $record, (int) $data['estado']);
    estado_exp_no_quitar_unico_inicial($pdo, $record, $data);
    $userId = cat_user_id();
    $now = cat_now_lima();

    try {
        $pdo->beginTransaction();
        if ((int) $data['es_inicial'] === 1 && (int) $data['estado'] === 1) {
            $pdo->prepare('UPDATE seg_estados_expediente SET es_inicial = 0, actualizado_por_usuario_externo_id = :usuario, actualizado_en = :fecha WHERE estado = 1 AND es_inicial = 1 AND id <> :id')
                ->execute([':usuario' => $userId, ':fecha' => $now, ':id' => $id]);
        }

        $stmt = $pdo->prepare('UPDATE seg_estados_expediente
            SET nombre = :nombre,
                descripcion = :descripcion,
                ejemplo_uso = :ejemplo_uso,
                color_etiqueta = :color_etiqueta,
                orden_visual = :orden_visual,
                es_inicial = :es_inicial,
                estado = :estado,
                actualizado_por_usuario_externo_id = :actualizado_por,
                actualizado_en = :actualizado_en
            WHERE id = :id');
        $stmt->execute($data + [
            'actualizado_por' => $userId,
            'actualizado_en' => $now,
            'id' => $id,
        ]);
        $pdo->commit();
        cb_json_success('Estado de expediente actualizado correctamente.', ['id' => $id]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e->getCode() === '23000') {
            cat_duplicate_error();
        }
        cat_db_error();
    }
}

function estado_exp_cambiar_estado(PDO $pdo)
{
    cat_require_post_change('puede_eliminar');
    $payload = cat_payload();
    $id = (int) ($payload['id'] ?? 0);
    $record = cat_require_record($pdo, 'seg_estados_expediente', $id);
    $nuevoEstado = (int) ($record['estado'] ?? 0) === 1 ? 0 : 1;
    estado_exp_no_desactivar_unico_inicial($pdo, $record, $nuevoEstado);

    $stmt = $pdo->prepare('UPDATE seg_estados_expediente SET estado = :estado, actualizado_por_usuario_externo_id = :usuario, actualizado_en = :fecha WHERE id = :id');
    $stmt->execute([
        ':estado' => $nuevoEstado,
        ':usuario' => cat_user_id(),
        ':fecha' => cat_now_lima(),
        ':id' => $id,
    ]);
    cb_json_success($nuevoEstado === 1 ? 'Estado de expediente activado correctamente.' : 'Estado de expediente desactivado correctamente.', ['id' => $id, 'estado' => $nuevoEstado]);
}

try {
    if (cb_request_method() === 'GET') {
        $action = strtolower(cat_trim($_GET['action'] ?? 'list'));
        if ($action === 'get') {
            estado_exp_obtener($pdo);
        } else {
            estado_exp_listar($pdo);
        }
    }

    $payload = cat_payload();
    $action = strtolower(cat_trim($payload['action'] ?? ''));
    if ($action === 'create') {
        estado_exp_crear($pdo);
    } elseif ($action === 'update') {
        estado_exp_actualizar($pdo);
    } elseif ($action === 'toggle') {
        estado_exp_cambiar_estado($pdo);
    }
    cb_json_error('accion_invalida', 'Accion no valida.', 400);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    cat_db_error();
}
