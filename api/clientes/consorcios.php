<?php
declare(strict_types=1);

require_once __DIR__ . '/_clientes_common.php';

$accion = strtolower(trim((string)($_GET['accion'] ?? $_POST['accion'] ?? 'listar')));

try {
    switch ($accion) {
        case 'listar':
            cli_require_method('GET');
            cli_require_perm('puede_ver');
            consorcios_listar();
            break;
        case 'obtener':
            cli_require_method('GET');
            cli_require_perm('puede_ver');
            consorcios_obtener();
            break;
        case 'empresas_activas':
            cli_require_method('GET');
            cli_require_perm('puede_ver');
            consorcios_empresas_activas();
            break;
        case 'crear':
            consorcios_crear();
            break;
        case 'actualizar':
            consorcios_actualizar();
            break;
        case 'cambiar_estado':
            consorcios_cambiar_estado();
            break;
        default:
            cli_json_error('Accion no valida.', 404);
    }
} catch (PDOException $e) {
    cli_db_problem($e);
} catch (Throwable $e) {
    cli_db_problem($e);
}

function consorcios_listar(): void
{
    $pdo = cli_db();
    [$page, $perPage, $offset] = cli_page_params();

    $estado = strtolower(trim((string)($_GET['estado'] ?? 'todos')));
    if (!in_array($estado, ['todos', 'activo', 'inactivo'], true)) {
        $estado = 'todos';
    }

    $q = trim((string)($_GET['q'] ?? ''));
    $q = function_exists('mb_substr') ? mb_substr($q, 0, 120, 'UTF-8') : substr($q, 0, 120);

    $where = ["c.tipo_cliente = 'consorcio'"];
    $params = [];
    if ($estado === 'activo') {
        $where[] = 'c.estado = 1';
    } elseif ($estado === 'inactivo') {
        $where[] = 'c.estado = 0';
    }
    if ($q !== '') {
        $where[] = '(c.ruc LIKE :q OR c.razon_social LIKE :q OR c.nombre_comercial LIKE :q OR op.razon_social LIKE :q OR op.ruc LIKE :q)';
        $params[':q'] = '%' . $q . '%';
    }
    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $count = $pdo->prepare("SELECT COUNT(*)
        FROM seg_clientes c
        INNER JOIN seg_cliente_consorcios co ON co.cliente_id = c.id
        LEFT JOIN seg_clientes op ON op.id = co.operador_cliente_id
        {$whereSql}");
    foreach ($params as $key => $value) {
        $count->bindValue($key, $value, PDO::PARAM_STR);
    }
    $count->execute();
    $total = (int)$count->fetchColumn();

    $sql = "SELECT
            c.id,
            c.codigo,
            c.ruc,
            c.razon_social,
            c.nombre_comercial,
            c.estado,
            co.modalidad,
            co.operador_cliente_id,
            op.ruc AS operador_ruc,
            op.razon_social AS operador_razon_social,
            (
                SELECT COUNT(*)
                FROM seg_cliente_consorcio_integrantes ci
                WHERE ci.consorcio_cliente_id = c.id AND ci.estado = 1
            ) AS integrantes_activos
        FROM seg_clientes c
        INNER JOIN seg_cliente_consorcios co ON co.cliente_id = c.id
        LEFT JOIN seg_clientes op ON op.id = co.operador_cliente_id
        {$whereSql}
        ORDER BY c.razon_social ASC, c.id DESC
        LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    cli_json_success([
        'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => max(1, (int)ceil($total / $perPage)),
        ],
    ]);
}

function consorcios_obtener(): void
{
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        cli_json_error('Registro no valido.', 422);
    }

    $pdo = cli_db();
    $consorcio = consorcios_fetch($pdo, $id);
    if (!$consorcio) {
        cli_json_error('Consorcio no encontrado.', 404);
    }

    $stmt = $pdo->prepare('SELECT ci.*, e.ruc, e.razon_social
        FROM seg_cliente_consorcio_integrantes ci
        INNER JOIN seg_clientes e ON e.id = ci.empresa_cliente_id
        WHERE ci.consorcio_cliente_id = :id AND ci.estado = 1
        ORDER BY e.razon_social ASC');
    $stmt->execute([':id' => $id]);

    cli_json_success([
        'consorcio' => $consorcio,
        'integrantes' => $stmt->fetchAll(PDO::FETCH_ASSOC),
    ]);
}

function consorcios_empresas_activas(): void
{
    $pdo = cli_db();
    $stmt = $pdo->query("SELECT id, ruc, razon_social, nombre_comercial
        FROM seg_clientes
        WHERE tipo_cliente = 'empresa' AND estado = 1
        ORDER BY razon_social ASC, id ASC");
    cli_json_success(['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function consorcios_crear(): void
{
    $payload = cli_require_post_change('puede_crear');
    $data = cli_validate_consorcio($payload);
    $pdo = cli_db();
    consorcios_validar_empresas($pdo, $data);

    $userId = cli_user_id();
    $now = cli_now_lima();

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO seg_clientes
            (codigo, tipo_cliente, ruc, razon_social, nombre_comercial, direccion, telefono_principal, correo_principal, observaciones, estado, creado_por_usuario_externo_id, actualizado_por_usuario_externo_id, creado_en, actualizado_en)
            VALUES
            (:codigo, :tipo_cliente, :ruc, :razon_social, :nombre_comercial, :direccion, :telefono_principal, :correo_principal, :observaciones, :estado, :creado_por, :actualizado_por, :creado_en, :actualizado_en)');
        $stmt->execute([
            ':codigo' => cli_codigo_cliente($pdo, 'consorcio', $data['ruc']),
            ':tipo_cliente' => 'consorcio',
            ':ruc' => $data['ruc'],
            ':razon_social' => $data['razon_social'],
            ':nombre_comercial' => $data['nombre_comercial'],
            ':direccion' => $data['direccion'],
            ':telefono_principal' => $data['telefono_principal'],
            ':correo_principal' => $data['correo_principal'],
            ':observaciones' => $data['observaciones'],
            ':estado' => $data['estado'],
            ':creado_por' => $userId,
            ':actualizado_por' => $userId,
            ':creado_en' => $now,
            ':actualizado_en' => $now,
        ]);
        $clienteId = (int)$pdo->lastInsertId();
        consorcios_guardar_config($pdo, $clienteId, $data, $userId, $now, true);
        consorcios_guardar_integrantes($pdo, $clienteId, $data['integrantes'], $userId, $now);
        $pdo->commit();
        cli_json_success(['id' => $clienteId], 'Consorcio registrado correctamente.');
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e->getCode() === '23000') {
            cli_json_error('Ya existe un cliente con ese RUC o codigo.', 409, ['ruc' => 'El RUC ya esta registrado.']);
        }
        throw $e;
    }
}

function consorcios_actualizar(): void
{
    $payload = cli_require_post_change('puede_editar');
    $data = cli_validate_consorcio($payload, true);
    $pdo = cli_db();
    consorcios_validar_empresas($pdo, $data);

    $userId = cli_user_id();
    $now = cli_now_lima();

    try {
        $pdo->beginTransaction();
        if (!consorcios_fetch($pdo, $data['id'])) {
            $pdo->rollBack();
            cli_json_error('Consorcio no encontrado.', 404);
        }
        $stmt = $pdo->prepare('UPDATE seg_clientes SET
                ruc = :ruc,
                razon_social = :razon_social,
                nombre_comercial = :nombre_comercial,
                direccion = :direccion,
                telefono_principal = :telefono_principal,
                correo_principal = :correo_principal,
                observaciones = :observaciones,
                estado = :estado,
                actualizado_por_usuario_externo_id = :actualizado_por,
                actualizado_en = :actualizado_en
            WHERE id = :id AND tipo_cliente = :tipo_cliente');
        $stmt->execute([
            ':id' => $data['id'],
            ':tipo_cliente' => 'consorcio',
            ':ruc' => $data['ruc'],
            ':razon_social' => $data['razon_social'],
            ':nombre_comercial' => $data['nombre_comercial'],
            ':direccion' => $data['direccion'],
            ':telefono_principal' => $data['telefono_principal'],
            ':correo_principal' => $data['correo_principal'],
            ':observaciones' => $data['observaciones'],
            ':estado' => $data['estado'],
            ':actualizado_por' => $userId,
            ':actualizado_en' => $now,
        ]);
        consorcios_guardar_config($pdo, $data['id'], $data, $userId, $now, false);
        consorcios_guardar_integrantes($pdo, $data['id'], $data['integrantes'], $userId, $now);
        $pdo->commit();
        cli_json_success(['id' => $data['id']], 'Consorcio actualizado correctamente.');
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e->getCode() === '23000') {
            cli_json_error('Ya existe un cliente con ese RUC o codigo.', 409, ['ruc' => 'El RUC ya esta registrado.']);
        }
        throw $e;
    }
}

function consorcios_cambiar_estado(): void
{
    $payload = cli_require_post_change('puede_eliminar');
    $id = (int)($payload['id'] ?? 0);
    if ($id <= 0) {
        cli_json_error('Registro no valido.', 422);
    }

    $pdo = cli_db();
    $row = consorcios_fetch($pdo, $id);
    if (!$row) {
        cli_json_error('Consorcio no encontrado.', 404);
    }

    $estado = (int)$row['estado'] === 1 ? 0 : 1;
    $userId = cli_user_id();
    $now = cli_now_lima();
    $pdo->beginTransaction();
    $pdo->prepare('UPDATE seg_clientes SET estado = :estado, actualizado_por_usuario_externo_id = :usuario, actualizado_en = :fecha WHERE id = :id AND tipo_cliente = :tipo')
        ->execute([':estado' => $estado, ':usuario' => $userId, ':fecha' => $now, ':id' => $id, ':tipo' => 'consorcio']);
    $pdo->prepare('UPDATE seg_cliente_consorcios SET estado = :estado, actualizado_por_usuario_externo_id = :usuario, actualizado_en = :fecha WHERE cliente_id = :id')
        ->execute([':estado' => $estado, ':usuario' => $userId, ':fecha' => $now, ':id' => $id]);
    $pdo->commit();

    cli_json_success(['id' => $id, 'estado' => $estado], $estado === 1 ? 'Consorcio activado.' : 'Consorcio desactivado.');
}

function consorcios_fetch(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT c.*, co.modalidad, co.operador_cliente_id
        FROM seg_clientes c
        INNER JOIN seg_cliente_consorcios co ON co.cliente_id = c.id
        WHERE c.id = :id AND c.tipo_cliente = :tipo
        LIMIT 1');
    $stmt->execute([':id' => $id, ':tipo' => 'consorcio']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function consorcios_validar_empresas(PDO $pdo, array $data): void
{
    $ids = array_column($data['integrantes'], 'empresa_cliente_id');
    if ($data['operador_cliente_id']) {
        $ids[] = (int)$data['operador_cliente_id'];
    }
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) {
        cli_json_error('Agrega al menos dos empresas integrantes.', 422, ['integrantes' => 'Agrega al menos dos empresas integrantes.']);
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id FROM seg_clientes WHERE tipo_cliente = 'empresa' AND estado = 1 AND id IN ({$placeholders})");
    $stmt->execute($ids);
    $validos = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    foreach ($ids as $id) {
        if (!in_array($id, $validos, true)) {
            cli_json_error('Solo empresas activas pueden ser integrantes u operador tributario.', 422, ['integrantes' => 'Selecciona empresas activas.']);
        }
    }
}

function consorcios_guardar_config(PDO $pdo, int $clienteId, array $data, ?int $userId, string $now, bool $creating): void
{
    if ($creating) {
        $stmt = $pdo->prepare('INSERT INTO seg_cliente_consorcios
            (cliente_id, modalidad, operador_cliente_id, estado, creado_por_usuario_externo_id, actualizado_por_usuario_externo_id, creado_en, actualizado_en)
            VALUES
            (:cliente_id, :modalidad, :operador_cliente_id, :estado, :creado_por, :actualizado_por, :creado_en, :actualizado_en)');
        $stmt->execute([
            ':cliente_id' => $clienteId,
            ':modalidad' => $data['modalidad'],
            ':operador_cliente_id' => $data['operador_cliente_id'],
            ':estado' => $data['estado'],
            ':creado_por' => $userId,
            ':actualizado_por' => $userId,
            ':creado_en' => $now,
            ':actualizado_en' => $now,
        ]);
        return;
    }

    $stmt = $pdo->prepare('UPDATE seg_cliente_consorcios SET
            modalidad = :modalidad,
            operador_cliente_id = :operador_cliente_id,
            estado = :estado,
            actualizado_por_usuario_externo_id = :actualizado_por,
            actualizado_en = :actualizado_en
        WHERE cliente_id = :cliente_id');
    $stmt->execute([
        ':cliente_id' => $clienteId,
        ':modalidad' => $data['modalidad'],
        ':operador_cliente_id' => $data['operador_cliente_id'],
        ':estado' => $data['estado'],
        ':actualizado_por' => $userId,
        ':actualizado_en' => $now,
    ]);
}

function consorcios_guardar_integrantes(PDO $pdo, int $clienteId, array $integrantes, ?int $userId, string $now): void
{
    $pdo->prepare('UPDATE seg_cliente_consorcio_integrantes
        SET estado = 0, actualizado_por_usuario_externo_id = :usuario, actualizado_en = :fecha
        WHERE consorcio_cliente_id = :id')
        ->execute([':usuario' => $userId, ':fecha' => $now, ':id' => $clienteId]);

    foreach ($integrantes as $item) {
        $stmt = $pdo->prepare('INSERT INTO seg_cliente_consorcio_integrantes
            (consorcio_cliente_id, empresa_cliente_id, participacion_porcentaje, rol_descripcion, estado, creado_por_usuario_externo_id, actualizado_por_usuario_externo_id, creado_en, actualizado_en)
            VALUES
            (:consorcio_id, :empresa_id, :participacion, :rol, 1, :creado_por, :actualizado_por, :creado_en, :actualizado_en)
            ON DUPLICATE KEY UPDATE
                participacion_porcentaje = VALUES(participacion_porcentaje),
                rol_descripcion = VALUES(rol_descripcion),
                estado = 1,
                actualizado_por_usuario_externo_id = VALUES(actualizado_por_usuario_externo_id),
                actualizado_en = VALUES(actualizado_en)');
        $stmt->execute([
            ':consorcio_id' => $clienteId,
            ':empresa_id' => $item['empresa_cliente_id'],
            ':participacion' => $item['participacion_porcentaje'],
            ':rol' => $item['rol_descripcion'],
            ':creado_por' => $userId,
            ':actualizado_por' => $userId,
            ':creado_en' => $now,
            ':actualizado_en' => $now,
        ]);
    }
}
