<?php
declare(strict_types=1);

require_once __DIR__ . '/_expedientes_common.php';

$accion = strtolower(trim((string) ($_GET['accion'] ?? $_POST['accion'] ?? 'listar')));

try {
    switch ($accion) {
        case 'contexto':
            exp_contexto();
            break;
        case 'listar':
            exp_listar();
            break;
        case 'obtener':
            exp_obtener();
            break;
        case 'crear':
            exp_crear();
            break;
        case 'actualizar':
            exp_actualizar();
            break;
        case 'cambiar_estado':
            exp_cambiar_estado();
            break;
        default:
            exp_json_error('Accion no valida.', 404);
    }
} catch (PDOException $e) {
    exp_db_error($e);
} catch (Throwable $e) {
    exp_db_error($e);
}

function exp_contexto(): void
{
    exp_require_method('GET');
    exp_require_perm('puede_ver');
    $pdo = exp_db();

    $clientes = $pdo->query("SELECT id, codigo, tipo_cliente, ruc, razon_social, nombre_comercial
        FROM seg_clientes
        WHERE estado = 1
        ORDER BY razon_social ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

    $tipos = $pdo->query("SELECT t.id, t.codigo, t.nombre, r.nombre AS ramo_nombre
        FROM seg_tipos_seguro t
        INNER JOIN seg_ramos r ON r.id = t.ramo_id
        WHERE t.estado = 1
        ORDER BY r.nombre ASC, t.orden_visual ASC, t.nombre ASC, t.id ASC")->fetchAll(PDO::FETCH_ASSOC);

    $estados = $pdo->query("SELECT id, codigo, nombre, color_etiqueta, es_inicial
        FROM seg_estados_expediente
        WHERE estado = 1
        ORDER BY orden_visual ASC, nombre ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

    $iniciales = array_values(array_filter($estados, static function ($row) {
        return (int) ($row['es_inicial'] ?? 0) === 1;
    }));

    exp_json_success([
        'clientes' => $clientes,
        'tipos_seguro' => $tipos,
        'estados_expediente' => $estados,
        'estado_inicial' => count($iniciales) === 1 ? $iniciales[0] : null,
        'csrf' => cb_local_csrf_token('expedientes'),
    ]);
}

function exp_listar(): void
{
    exp_require_method('GET');
    exp_require_perm('puede_ver');
    $pdo = exp_db();
    [$page, $perPage, $offset] = exp_page_params();

    $where = [];
    $params = [];

    $q = trim((string) ($_GET['q'] ?? ''));
    $q = function_exists('mb_substr') ? mb_substr($q, 0, 120, 'UTF-8') : substr($q, 0, 120);
    if ($q !== '') {
        $where[] = "(e.codigo LIKE :q ESCAPE '\\\\'
            OR e.descripcion LIKE :q ESCAPE '\\\\'
            OR c.razon_social LIKE :q ESCAPE '\\\\'
            OR c.nombre_comercial LIKE :q ESCAPE '\\\\'
            OR c.ruc LIKE :q ESCAPE '\\\\'
            OR ts.nombre LIKE :q ESCAPE '\\\\')";
        $params[':q'] = exp_bind_like($q);
    }

    $filters = [
        'cliente_id' => 'e.cliente_id',
        'tipo_seguro_id' => 'e.tipo_seguro_id',
        'estado_expediente_id' => 'e.estado_expediente_id',
    ];
    foreach ($filters as $param => $column) {
        $value = isset($_GET[$param]) && is_numeric($_GET[$param]) ? (int) $_GET[$param] : 0;
        if ($value > 0) {
            $where[] = $column . ' = :' . $param;
            $params[':' . $param] = $value;
        }
    }

    $estado = strtolower(trim((string) ($_GET['estado'] ?? 'todos')));
    if ($estado === 'activo') {
        $where[] = 'e.estado = 1';
    } elseif ($estado === 'inactivo') {
        $where[] = 'e.estado = 0';
    }

    $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

    $fromSql = ' FROM seg_expedientes e
        INNER JOIN seg_clientes c ON c.id = e.cliente_id
        INNER JOIN seg_tipos_seguro ts ON ts.id = e.tipo_seguro_id
        INNER JOIN seg_estados_expediente ee ON ee.id = e.estado_expediente_id';

    $count = $pdo->prepare('SELECT COUNT(*)' . $fromSql . $whereSql);
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    $stmt = $pdo->prepare('SELECT
            e.id,
            e.codigo,
            e.cliente_id,
            e.tipo_seguro_id,
            e.estado_expediente_id,
            e.descripcion,
            e.observaciones,
            e.fecha_apertura,
            e.estado,
            c.ruc AS cliente_ruc,
            c.razon_social AS cliente_razon_social,
            c.nombre_comercial AS cliente_nombre_comercial,
            c.tipo_cliente,
            ts.nombre AS tipo_seguro_nombre,
            ee.nombre AS estado_expediente_nombre,
            ee.color_etiqueta
        ' . $fromSql . $whereSql . '
        ORDER BY e.fecha_apertura DESC, e.id DESC
        LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset);
    $stmt->execute($params);

    exp_json_success([
        'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ],
    ]);
}

function exp_obtener(): void
{
    exp_require_method('GET');
    exp_require_perm('puede_ver');
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        exp_json_error('Registro no valido.', 422);
    }

    $record = exp_fetch(exp_db(), $id);
    if (!$record) {
        exp_json_error('Expediente no encontrado.', 404);
    }
    exp_json_success(['record' => $record]);
}

function exp_crear(): void
{
    $payload = exp_require_change('puede_crear');
    $pdo = exp_db();
    $data = exp_validate($pdo, $payload, false);
    $userId = exp_user_id();
    $now = exp_now();
    $year = substr($data['fecha_apertura'], 0, 4);

    for ($attempt = 0; $attempt < 3; $attempt++) {
        try {
            $pdo->beginTransaction();
            $codigo = exp_next_codigo($pdo, $year);
            $stmt = $pdo->prepare('INSERT INTO seg_expedientes
                (codigo, cliente_id, tipo_seguro_id, estado_expediente_id, descripcion, observaciones, fecha_apertura, estado, creado_por_usuario_externo_id, actualizado_por_usuario_externo_id, creado_en, actualizado_en)
                VALUES
                (:codigo, :cliente_id, :tipo_seguro_id, :estado_expediente_id, :descripcion, :observaciones, :fecha_apertura, :estado, :creado_por, :actualizado_por, :creado_en, :actualizado_en)');
            $stmt->execute([
                ':codigo' => $codigo,
                ':cliente_id' => $data['cliente_id'],
                ':tipo_seguro_id' => $data['tipo_seguro_id'],
                ':estado_expediente_id' => $data['estado_expediente_id'],
                ':descripcion' => $data['descripcion'],
                ':observaciones' => $data['observaciones'],
                ':fecha_apertura' => $data['fecha_apertura'],
                ':estado' => $data['estado'],
                ':creado_por' => $userId,
                ':actualizado_por' => $userId,
                ':creado_en' => $now,
                ':actualizado_en' => $now,
            ]);
            $id = (int) $pdo->lastInsertId();
            $pdo->commit();
            exp_json_success(['id' => $id, 'codigo' => $codigo], 'Expediente registrado correctamente.');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e->getCode() === '23000') {
                continue;
            }
            throw $e;
        }
    }

    exp_json_error('No se pudo generar un codigo unico para el expediente. Intenta nuevamente.', 409);
}

function exp_actualizar(): void
{
    $payload = exp_require_change('puede_editar');
    $pdo = exp_db();
    $data = exp_validate($pdo, $payload, true);
    $record = exp_fetch($pdo, $data['id']);
    if (!$record) {
        exp_json_error('Expediente no encontrado.', 404);
    }

    $stmt = $pdo->prepare('UPDATE seg_expedientes SET
            cliente_id = :cliente_id,
            tipo_seguro_id = :tipo_seguro_id,
            estado_expediente_id = :estado_expediente_id,
            descripcion = :descripcion,
            observaciones = :observaciones,
            fecha_apertura = :fecha_apertura,
            estado = :estado,
            actualizado_por_usuario_externo_id = :actualizado_por,
            actualizado_en = :actualizado_en
        WHERE id = :id');
    $stmt->execute([
        ':id' => $data['id'],
        ':cliente_id' => $data['cliente_id'],
        ':tipo_seguro_id' => $data['tipo_seguro_id'],
        ':estado_expediente_id' => $data['estado_expediente_id'],
        ':descripcion' => $data['descripcion'],
        ':observaciones' => $data['observaciones'],
        ':fecha_apertura' => $data['fecha_apertura'],
        ':estado' => $data['estado'],
        ':actualizado_por' => exp_user_id(),
        ':actualizado_en' => exp_now(),
    ]);

    exp_json_success(['id' => $data['id']], 'Expediente actualizado correctamente.');
}

function exp_cambiar_estado(): void
{
    $payload = exp_require_change('puede_eliminar');
    $id = isset($payload['id']) && is_numeric($payload['id']) ? (int) $payload['id'] : 0;
    if ($id <= 0) {
        exp_json_error('Registro no valido.', 422);
    }

    $pdo = exp_db();
    $record = exp_fetch($pdo, $id);
    if (!$record) {
        exp_json_error('Expediente no encontrado.', 404);
    }

    $nuevoEstado = (int) $record['estado'] === 1 ? 0 : 1;
    $stmt = $pdo->prepare('UPDATE seg_expedientes
        SET estado = :estado,
            actualizado_por_usuario_externo_id = :usuario,
            actualizado_en = :fecha
        WHERE id = :id');
    $stmt->execute([
        ':estado' => $nuevoEstado,
        ':usuario' => exp_user_id(),
        ':fecha' => exp_now(),
        ':id' => $id,
    ]);

    exp_json_success(['id' => $id, 'estado' => $nuevoEstado], $nuevoEstado === 1 ? 'Expediente activado.' : 'Expediente desactivado.');
}

function exp_fetch(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT
            e.*,
            c.ruc AS cliente_ruc,
            c.razon_social AS cliente_razon_social,
            c.nombre_comercial AS cliente_nombre_comercial,
            c.tipo_cliente,
            ts.nombre AS tipo_seguro_nombre,
            ee.nombre AS estado_expediente_nombre,
            ee.color_etiqueta
        FROM seg_expedientes e
        INNER JOIN seg_clientes c ON c.id = e.cliente_id
        INNER JOIN seg_tipos_seguro ts ON ts.id = e.tipo_seguro_id
        INNER JOIN seg_estados_expediente ee ON ee.id = e.estado_expediente_id
        WHERE e.id = :id
        LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}
