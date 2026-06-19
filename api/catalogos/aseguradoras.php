<?php
require_once __DIR__ . '/_catalogos_common.php';

$pdo = cat_db();

function aseg_listar(PDO $pdo)
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
        $where[] = "(codigo LIKE :q ESCAPE '\\\\' OR razon_social LIKE :q ESCAPE '\\\\' OR nombre_comercial LIKE :q ESCAPE '\\\\' OR ruc LIKE :q ESCAPE '\\\\')";
        $params[':q'] = cat_bind_like($q);
    }

    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $count = $pdo->prepare('SELECT COUNT(*) FROM seg_aseguradoras' . $whereSql);
    $count->execute($params);

    $sql = 'SELECT id, codigo, razon_social, nombre_comercial, ruc, contacto_nombre, contacto_email, contacto_telefono, sitio_web, observaciones, estado
            FROM seg_aseguradoras' . $whereSql . '
            ORDER BY razon_social ASC, id ASC
            LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    cb_json_success('Aseguradoras cargadas correctamente.', cat_response_page($stmt->fetchAll(PDO::FETCH_ASSOC), (int) $count->fetchColumn(), $page, $perPage));
}

function aseg_obtener(PDO $pdo)
{
    cat_require_catalogos('puede_ver');
    $id = (int) ($_GET['id'] ?? 0);
    cb_json_success('Aseguradora cargada correctamente.', ['record' => cat_require_record($pdo, 'seg_aseguradoras', $id)]);
}

function aseg_opciones(PDO $pdo)
{
    cat_require_catalogos('puede_ver');
    $stmt = $pdo->query('SELECT id, codigo, razon_social, nombre_comercial FROM seg_aseguradoras WHERE estado = 1 ORDER BY razon_social ASC, id ASC');
    cb_json_success('Opciones cargadas correctamente.', ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function aseg_validar(PDO $pdo, array $payload, $id = 0)
{
    $codigo = cat_codigo($payload['codigo'] ?? '');
    $razonSocial = strtoupper(cat_trim($payload['razon_social'] ?? ''));
    $nombreComercial = cat_nullable($payload['nombre_comercial'] ?? '');
    $ruc = cat_nullable($payload['ruc'] ?? '');
    $contactoNombre = cat_nullable($payload['contacto_nombre'] ?? '');
    $contactoEmail = cat_nullable($payload['contacto_email'] ?? '');
    $contactoTelefono = cat_nullable($payload['contacto_telefono'] ?? '');
    $sitioWeb = cat_nullable($payload['sitio_web'] ?? '');
    $observaciones = cat_nullable($payload['observaciones'] ?? '');
    $estado = cat_estado_value($payload['estado'] ?? 1, 1);
    $errors = [];

    if ($codigo === '') {
        $errors['codigo'] = 'Ingrese un codigo valido.';
    }
    if ($razonSocial === '') {
        $errors['razon_social'] = 'Ingrese la razon social.';
    }
    cat_validate_email($contactoEmail, 'contacto_email', $errors);
    cat_validate_url($sitioWeb, 'sitio_web', $errors);
    cat_abort_if_errors($errors);

    if (cat_value_exists($pdo, 'seg_aseguradoras', 'codigo', $codigo, $id)) {
        cat_duplicate_error('Ya existe una aseguradora con ese codigo.');
    }
    if (cat_value_exists($pdo, 'seg_aseguradoras', 'razon_social', $razonSocial, $id)) {
        cat_duplicate_error('Ya existe una aseguradora con esa razon social.');
    }
    if ($ruc !== null && cat_value_exists($pdo, 'seg_aseguradoras', 'ruc', $ruc, $id)) {
        cat_duplicate_error('Ya existe una aseguradora con ese RUC.');
    }

    return [
        'codigo' => $codigo,
        'razon_social' => $razonSocial,
        'nombre_comercial' => $nombreComercial,
        'ruc' => $ruc,
        'contacto_nombre' => $contactoNombre,
        'contacto_email' => $contactoEmail,
        'contacto_telefono' => $contactoTelefono,
        'sitio_web' => $sitioWeb,
        'observaciones' => $observaciones,
        'estado' => $estado,
    ];
}

function aseg_crear(PDO $pdo)
{
    cat_require_post_change('puede_crear');
    $data = aseg_validar($pdo, cat_payload());
    $userId = cat_user_id();
    $now = cat_now_lima();

    try {
        $stmt = $pdo->prepare('INSERT INTO seg_aseguradoras
            (codigo, razon_social, nombre_comercial, ruc, contacto_nombre, contacto_email, contacto_telefono, sitio_web, observaciones, estado, creado_por_usuario_externo_id, actualizado_por_usuario_externo_id, creado_en, actualizado_en)
            VALUES
            (:codigo, :razon_social, :nombre_comercial, :ruc, :contacto_nombre, :contacto_email, :contacto_telefono, :sitio_web, :observaciones, :estado, :creado_por, :actualizado_por, :creado_en, :actualizado_en)');
        $stmt->execute($data + [
            'creado_por' => $userId,
            'actualizado_por' => $userId,
            'creado_en' => $now,
            'actualizado_en' => $now,
        ]);
        cb_json_success('Aseguradora creada correctamente.', ['id' => (int) $pdo->lastInsertId()], 201);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            cat_duplicate_error();
        }
        cat_db_error();
    }
}

function aseg_actualizar(PDO $pdo)
{
    cat_require_post_change('puede_editar');
    $payload = cat_payload();
    $id = (int) ($payload['id'] ?? 0);
    cat_require_record($pdo, 'seg_aseguradoras', $id);
    $data = aseg_validar($pdo, $payload, $id);

    try {
        $stmt = $pdo->prepare('UPDATE seg_aseguradoras
            SET codigo = :codigo,
                razon_social = :razon_social,
                nombre_comercial = :nombre_comercial,
                ruc = :ruc,
                contacto_nombre = :contacto_nombre,
                contacto_email = :contacto_email,
                contacto_telefono = :contacto_telefono,
                sitio_web = :sitio_web,
                observaciones = :observaciones,
                estado = :estado,
                actualizado_por_usuario_externo_id = :actualizado_por,
                actualizado_en = :actualizado_en
            WHERE id = :id');
        $stmt->execute($data + [
            'actualizado_por' => cat_user_id(),
            'actualizado_en' => cat_now_lima(),
            'id' => $id,
        ]);
        cb_json_success('Aseguradora actualizada correctamente.', ['id' => $id]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            cat_duplicate_error();
        }
        cat_db_error();
    }
}

function aseg_cambiar_estado(PDO $pdo)
{
    cat_require_post_change('puede_eliminar');
    $payload = cat_payload();
    $id = (int) ($payload['id'] ?? 0);
    $record = cat_require_record($pdo, 'seg_aseguradoras', $id);
    $nuevoEstado = (int) ($record['estado'] ?? 0) === 1 ? 0 : 1;

    if ($nuevoEstado === 0) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM seg_productos WHERE aseguradora_id = :id AND estado = 1');
        $stmt->execute([':id' => $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            cb_json_error('dependencia_activa', 'No se puede inactivar una aseguradora con productos activos.', 409);
        }
    }

    $stmt = $pdo->prepare('UPDATE seg_aseguradoras SET estado = :estado, actualizado_por_usuario_externo_id = :usuario, actualizado_en = :fecha WHERE id = :id');
    $stmt->execute([
        ':estado' => $nuevoEstado,
        ':usuario' => cat_user_id(),
        ':fecha' => cat_now_lima(),
        ':id' => $id,
    ]);
    cb_json_success($nuevoEstado === 1 ? 'Aseguradora activada correctamente.' : 'Aseguradora inactivada correctamente.', ['id' => $id, 'estado' => $nuevoEstado]);
}

try {
    if (cb_request_method() === 'GET') {
        $action = strtolower(cat_trim($_GET['action'] ?? 'list'));
        if ($action === 'get') {
            aseg_obtener($pdo);
        } elseif ($action === 'options') {
            aseg_opciones($pdo);
        } else {
            aseg_listar($pdo);
        }
    }

    $payload = cat_payload();
    $action = strtolower(cat_trim($payload['action'] ?? ''));
    if ($action === 'create') {
        aseg_crear($pdo);
    } elseif ($action === 'update') {
        aseg_actualizar($pdo);
    } elseif ($action === 'toggle') {
        aseg_cambiar_estado($pdo);
    }
    cb_json_error('accion_invalida', 'Accion no valida.', 400);
} catch (PDOException $e) {
    cat_db_error();
}
