<?php
declare(strict_types=1);

require_once __DIR__ . '/_requisitos_tipo_common.php';

$accion = strtolower(trim((string) ($_GET['accion'] ?? $_POST['accion'] ?? 'listar')));

try {
    switch ($accion) {
        case 'contexto':
            requisitos_contexto();
            break;
        case 'listar':
            requisitos_listar();
            break;
        case 'obtener':
            requisitos_obtener();
            break;
        case 'crear':
            requisitos_crear();
            break;
        case 'actualizar':
            requisitos_actualizar();
            break;
        case 'cambiar_estado':
            requisitos_cambiar_estado();
            break;
        default:
            req_json_error('Accion no valida.', 404);
    }
} catch (PDOException $e) {
    req_db_problem($e);
} catch (Throwable $e) {
    req_db_problem($e);
}

function requisitos_contexto(): void
{
    req_require_method('GET');
    req_require_perm('puede_ver');
    $pdo = req_db();
    $stmt = $pdo->query("SELECT t.id, t.codigo, t.nombre, r.nombre AS ramo_nombre
        FROM seg_tipos_seguro t
        INNER JOIN seg_ramos r ON r.id = t.ramo_id
        WHERE t.estado = 1
        ORDER BY r.nombre ASC, t.orden_visual ASC, t.nombre ASC, t.id ASC");

    req_json_success([
        'tipos_seguro' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'csrf' => cb_local_csrf_token('requisitos_tipo'),
    ]);
}

function requisitos_listar(): void
{
    req_require_method('GET');
    req_require_perm('puede_ver');
    $pdo = req_db();
    [$page, $perPage, $offset] = req_page_params();

    $where = [];
    $params = [];

    $tipoSeguroId = isset($_GET['tipo_seguro_id']) && is_numeric($_GET['tipo_seguro_id']) ? (int) $_GET['tipo_seguro_id'] : 0;
    if ($tipoSeguroId > 0) {
        $where[] = 'rt.tipo_seguro_id = :tipo_seguro_id';
        $params[':tipo_seguro_id'] = $tipoSeguroId;
    }

    $estado = strtolower(trim((string) ($_GET['estado'] ?? 'todos')));
    if ($estado === 'activo') {
        $where[] = 'rt.estado = 1';
    } elseif ($estado === 'inactivo') {
        $where[] = 'rt.estado = 0';
    }

    $q = trim((string) ($_GET['q'] ?? ''));
    $q = function_exists('mb_substr') ? mb_substr($q, 0, 120, 'UTF-8') : substr($q, 0, 120);
    if ($q !== '') {
        $where[] = "(rt.codigo LIKE :q ESCAPE '\\\\'
            OR rt.nombre LIKE :q ESCAPE '\\\\'
            OR rt.descripcion LIKE :q ESCAPE '\\\\'
            OR ts.nombre LIKE :q ESCAPE '\\\\'
            OR r.nombre LIKE :q ESCAPE '\\\\')";
        $params[':q'] = req_bind_like($q);
    }

    $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';
    $fromSql = ' FROM seg_requisitos_tipo_seguro rt
        INNER JOIN seg_tipos_seguro ts ON ts.id = rt.tipo_seguro_id
        INNER JOIN seg_ramos r ON r.id = ts.ramo_id';

    $stmtTotal = $pdo->prepare('SELECT COUNT(*)' . $fromSql . $whereSql);
    $stmtTotal->execute($params);
    $total = (int) $stmtTotal->fetchColumn();

    $stmt = $pdo->prepare('SELECT
            rt.id,
            rt.tipo_seguro_id,
            rt.codigo,
            rt.nombre,
            rt.descripcion,
            rt.es_obligatorio,
            rt.orden_visual,
            rt.estado,
            rt.actualizado_en,
            ts.nombre AS tipo_seguro_nombre,
            r.nombre AS ramo_nombre
        ' . $fromSql . $whereSql . '
        ORDER BY r.nombre ASC, ts.nombre ASC, rt.orden_visual ASC, rt.nombre ASC, rt.id DESC
        LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset);
    $stmt->execute($params);

    req_json_success([
        'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ],
    ]);
}

function requisitos_obtener(): void
{
    req_require_method('GET');
    req_require_perm('puede_ver');
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        req_json_error('Registro no valido.', 422);
    }
    $record = requisitos_fetch(req_db(), $id);
    if (!$record) {
        req_json_error('Requisito no encontrado.', 404);
    }
    req_json_success(['record' => $record]);
}

function requisitos_crear(): void
{
    $payload = req_require_change('puede_crear');
    $pdo = req_db();
    $data = req_validate($pdo, $payload, false);
    $userId = req_user_id();
    $now = req_now();

    try {
        $stmt = $pdo->prepare('INSERT INTO seg_requisitos_tipo_seguro
            (tipo_seguro_id, codigo, nombre, descripcion, es_obligatorio, orden_visual, estado, creado_por_usuario_externo_id, actualizado_por_usuario_externo_id, creado_en, actualizado_en)
            VALUES
            (:tipo_seguro_id, :codigo, :nombre, :descripcion, :es_obligatorio, :orden_visual, :estado, :creado_por, :actualizado_por, :creado_en, :actualizado_en)');
        $stmt->execute([
            ':tipo_seguro_id' => $data['tipo_seguro_id'],
            ':codigo' => req_codigo($pdo),
            ':nombre' => $data['nombre'],
            ':descripcion' => $data['descripcion'],
            ':es_obligatorio' => $data['es_obligatorio'],
            ':orden_visual' => $data['orden_visual'],
            ':estado' => $data['estado'],
            ':creado_por' => $userId,
            ':actualizado_por' => $userId,
            ':creado_en' => $now,
            ':actualizado_en' => $now,
        ]);
        req_json_success(['id' => (int) $pdo->lastInsertId()], 'Requisito registrado correctamente.');
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            req_json_error('Ya existe un requisito con datos duplicados.', 409);
        }
        throw $e;
    }
}

function requisitos_actualizar(): void
{
    $payload = req_require_change('puede_editar');
    $pdo = req_db();
    $data = req_validate($pdo, $payload, true);
    if (!requisitos_fetch($pdo, $data['id'])) {
        req_json_error('Requisito no encontrado.', 404);
    }

    $stmt = $pdo->prepare('UPDATE seg_requisitos_tipo_seguro SET
            tipo_seguro_id = :tipo_seguro_id,
            nombre = :nombre,
            descripcion = :descripcion,
            es_obligatorio = :es_obligatorio,
            orden_visual = :orden_visual,
            estado = :estado,
            actualizado_por_usuario_externo_id = :actualizado_por,
            actualizado_en = :actualizado_en
        WHERE id = :id');
    $stmt->execute([
        ':id' => $data['id'],
        ':tipo_seguro_id' => $data['tipo_seguro_id'],
        ':nombre' => $data['nombre'],
        ':descripcion' => $data['descripcion'],
        ':es_obligatorio' => $data['es_obligatorio'],
        ':orden_visual' => $data['orden_visual'],
        ':estado' => $data['estado'],
        ':actualizado_por' => req_user_id(),
        ':actualizado_en' => req_now(),
    ]);

    req_json_success(['id' => $data['id']], 'Requisito actualizado correctamente.');
}

function requisitos_cambiar_estado(): void
{
    $payload = req_require_change('puede_eliminar');
    $id = isset($payload['id']) && is_numeric($payload['id']) ? (int) $payload['id'] : 0;
    if ($id <= 0) {
        req_json_error('Registro no valido.', 422);
    }

    $pdo = req_db();
    $record = requisitos_fetch($pdo, $id);
    if (!$record) {
        req_json_error('Requisito no encontrado.', 404);
    }

    $nuevoEstado = (int) $record['estado'] === 1 ? 0 : 1;
    if ($nuevoEstado === 1) {
        req_require_active_tipo($pdo, (int) $record['tipo_seguro_id']);
        req_require_unique_active_name($pdo, (int) $record['tipo_seguro_id'], (string) $record['nombre'], $id);
    }

    $stmt = $pdo->prepare('UPDATE seg_requisitos_tipo_seguro
        SET estado = :estado,
            actualizado_por_usuario_externo_id = :usuario,
            actualizado_en = :fecha
        WHERE id = :id');
    $stmt->execute([
        ':estado' => $nuevoEstado,
        ':usuario' => req_user_id(),
        ':fecha' => req_now(),
        ':id' => $id,
    ]);

    req_json_success(['id' => $id, 'estado' => $nuevoEstado], $nuevoEstado === 1 ? 'Requisito activado.' : 'Requisito desactivado.');
}

function requisitos_fetch(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT
            rt.*,
            ts.nombre AS tipo_seguro_nombre,
            r.nombre AS ramo_nombre
        FROM seg_requisitos_tipo_seguro rt
        INNER JOIN seg_tipos_seguro ts ON ts.id = rt.tipo_seguro_id
        INNER JOIN seg_ramos r ON r.id = ts.ramo_id
        WHERE rt.id = :id
        LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}
