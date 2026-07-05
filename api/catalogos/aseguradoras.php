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
        $where[] = 'a.estado = :estado';
        $params[':estado'] = $estado === 'activo' ? 1 : 0;
    }
    if ($q !== '') {
        $where[] = "(a.codigo LIKE :q ESCAPE '\\\\' OR a.razon_social LIKE :q ESCAPE '\\\\' OR a.nombre_comercial LIKE :q ESCAPE '\\\\' OR a.ruc LIKE :q ESCAPE '\\\\')";
        $params[':q'] = cat_bind_like($q);
    }

    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $hasLogoTable = cat_logo_table_exists($pdo);
    $logoJoin = $hasLogoTable ? ' LEFT JOIN seg_aseguradora_logo_archivos l ON l.aseguradora_id = a.id' : '';
    $logoSelect = $hasLogoTable
        ? ", l.id AS logo_id, l.mime_type AS logo_mime_type, l.tamanio_bytes AS logo_tamanio_bytes, l.ruta_relativa AS logo_ruta_relativa, DATE_FORMAT(l.actualizado_en, '%Y%m%d%H%i%s') AS logo_version"
        : ", NULL AS logo_id, NULL AS logo_mime_type, NULL AS logo_tamanio_bytes, NULL AS logo_ruta_relativa, NULL AS logo_version";

    $count = $pdo->prepare('SELECT COUNT(*) FROM seg_aseguradoras a' . $whereSql);
    $count->execute($params);

    $sql = 'SELECT a.id, a.codigo, a.razon_social, a.nombre_comercial, a.ruc, a.contacto_nombre, a.contacto_email, a.contacto_telefono, a.sitio_web, a.observaciones, a.estado' . $logoSelect . '
            FROM seg_aseguradoras a' . $logoJoin . $whereSql . '
            ORDER BY a.razon_social ASC, a.id ASC
            LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    cb_json_success('Aseguradoras cargadas correctamente.', cat_response_page($stmt->fetchAll(PDO::FETCH_ASSOC), (int) $count->fetchColumn(), $page, $perPage));
}

function aseg_obtener(PDO $pdo)
{
    cat_require_catalogos('puede_ver');
    $id = (int) ($_GET['id'] ?? 0);
    $record = cat_require_record($pdo, 'seg_aseguradoras', $id);
    $record['logo_id'] = null;
    $record['logo_mime_type'] = null;
    $record['logo_tamanio_bytes'] = null;
    $record['logo_ruta_relativa'] = null;
    $record['logo_version'] = null;
    if (cat_logo_table_exists($pdo)) {
        $logo = cat_fetch_one($pdo, "SELECT id, mime_type, tamanio_bytes, ruta_relativa, DATE_FORMAT(actualizado_en, '%Y%m%d%H%i%s') AS version FROM seg_aseguradora_logo_archivos WHERE aseguradora_id = :id LIMIT 1", [':id' => $id]);
        if ($logo) {
            $record['logo_id'] = (int) $logo['id'];
            $record['logo_mime_type'] = $logo['mime_type'];
            $record['logo_tamanio_bytes'] = (int) $logo['tamanio_bytes'];
            $record['logo_ruta_relativa'] = (string) $logo['ruta_relativa'];
            $record['logo_version'] = $logo['version'];
        }
    }
    cb_json_success('Aseguradora cargada correctamente.', ['record' => $record]);
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

    foreach ([
        'codigo' => $payload['codigo'] ?? '',
        'razon_social' => $payload['razon_social'] ?? '',
        'nombre_comercial' => $payload['nombre_comercial'] ?? '',
        'ruc' => $payload['ruc'] ?? '',
        'contacto_nombre' => $payload['contacto_nombre'] ?? '',
        'contacto_email' => $payload['contacto_email'] ?? '',
        'contacto_telefono' => $payload['contacto_telefono'] ?? '',
        'sitio_web' => $payload['sitio_web'] ?? '',
        'observaciones' => $payload['observaciones'] ?? '',
    ] as $field => $value) {
        cat_validate_utf8_value($value, $field, $errors);
    }
    cat_validate_max($codigo, 'codigo', 40, $errors);
    cat_validate_max($razonSocial, 'razon_social', 180, $errors);
    cat_validate_max((string) $nombreComercial, 'nombre_comercial', 180, $errors);
    cat_validate_max((string) $ruc, 'ruc', 20, $errors);
    cat_validate_max((string) $contactoNombre, 'contacto_nombre', 120, $errors);
    cat_validate_max((string) $contactoEmail, 'contacto_email', 160, $errors);
    cat_validate_max((string) $contactoTelefono, 'contacto_telefono', 40, $errors);
    cat_validate_max((string) $sitioWeb, 'sitio_web', 200, $errors);

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
    $payload = cat_payload();
    $data = aseg_validar($pdo, $payload);
    $logo = cat_validate_logo_upload('logo_archivo');
    $userId = cat_user_id();
    $now = cat_now_lima();
    $logoGuardado = null;

    try {
        $pdo->beginTransaction();
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
        $id = (int) $pdo->lastInsertId();
        if ($logo !== null) {
            $logoGuardado = cat_save_logo($pdo, $id, $logo, $userId, $now);
        }
        $pdo->commit();
        cb_json_success('Aseguradora creada correctamente.', ['id' => $id], 201);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (is_array($logoGuardado) && !empty($logoGuardado['absolute_path'])) {
            @unlink((string) $logoGuardado['absolute_path']);
        }
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
    $logo = cat_validate_logo_upload('logo_archivo');
    $quitarLogo = (int) ($payload['logo_quitar'] ?? 0) === 1;
    $logoGuardado = null;
    $logoAnteriorParaBorrar = '';

    try {
        $pdo->beginTransaction();
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
        if ($logo !== null) {
            $now = cat_now_lima();
            $logoGuardado = cat_save_logo($pdo, $id, $logo, cat_user_id(), $now);
            $logoAnteriorParaBorrar = (string) ($logoGuardado['previous_ruta_relativa'] ?? '');
        } elseif ($quitarLogo) {
            $logoAnteriorParaBorrar = cat_delete_logo($pdo, $id);
        }
        $pdo->commit();
        if ($logoAnteriorParaBorrar !== '') {
            cat_logo_delete_file($logoAnteriorParaBorrar);
        }
        cb_json_success('Aseguradora actualizada correctamente.', ['id' => $id]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (is_array($logoGuardado) && !empty($logoGuardado['absolute_path'])) {
            @unlink((string) $logoGuardado['absolute_path']);
        }
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
            cb_json_error('dependencia_activa', 'No se puede desactivar una aseguradora con productos activos.', 409);
        }
    }

    $stmt = $pdo->prepare('UPDATE seg_aseguradoras SET estado = :estado, actualizado_por_usuario_externo_id = :usuario, actualizado_en = :fecha WHERE id = :id');
    $stmt->execute([
        ':estado' => $nuevoEstado,
        ':usuario' => cat_user_id(),
        ':fecha' => cat_now_lima(),
        ':id' => $id,
    ]);
    cb_json_success($nuevoEstado === 1 ? 'Aseguradora activada correctamente.' : 'Aseguradora desactivada correctamente.', ['id' => $id, 'estado' => $nuevoEstado]);
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
