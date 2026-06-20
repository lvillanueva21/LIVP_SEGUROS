<?php
declare(strict_types=1);

require_once __DIR__ . '/_clientes_common.php';

$accion = strtolower(trim((string)($_GET['accion'] ?? $_POST['accion'] ?? 'listar')));

try {
    switch ($accion) {
        case 'listar':
            cli_require_method('GET');
            cli_require_perm('puede_ver');
            clientes_listar();
            break;

        case 'obtener':
            cli_require_method('GET');
            cli_require_perm('puede_ver');
            clientes_obtener();
            break;

        case 'crear':
            clientes_crear();
            break;

        case 'actualizar':
            clientes_actualizar();
            break;

        case 'cambiar_estado':
            clientes_cambiar_estado();
            break;

        default:
            cli_json_error('Accion no valida.', 404);
    }
} catch (PDOException $e) {
    cli_db_problem($e);
} catch (Throwable $e) {
    cli_db_problem($e);
}

function clientes_listar(): void
{
    $pdo = cli_db();
    [$page, $perPage, $offset] = cli_page_params();

    $estado = strtolower(trim((string)($_GET['estado'] ?? 'todos')));
    if (!in_array($estado, ['todos', 'activo', 'inactivo'], true)) {
        $estado = 'todos';
    }

    $q = trim((string)($_GET['q'] ?? ''));
    if (function_exists('mb_substr')) {
        $q = mb_substr($q, 0, 120, 'UTF-8');
    } else {
        $q = substr($q, 0, 120);
    }

    $where = ["c.tipo_cliente = 'empresa'"];
    $params = [];

    if ($estado === 'activo') {
        $where[] = 'c.estado = 1';
    } elseif ($estado === 'inactivo') {
        $where[] = 'c.estado = 0';
    }

    if ($q !== '') {
        $where[] = '(c.ruc LIKE :q OR c.razon_social LIKE :q OR c.nombre_comercial LIKE :q OR c.telefono_principal LIKE :q OR c.correo_principal LIKE :q OR EXISTS (
            SELECT 1 FROM seg_cliente_contactos cc
            WHERE cc.cliente_id = c.id
              AND (cc.nombre_completo LIKE :q OR cc.telefono LIKE :q OR cc.correo LIKE :q)
        ))';
        $params[':q'] = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM seg_clientes c {$whereSql}");
    foreach ($params as $key => $value) {
        $stmtTotal->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmtTotal->execute();
    $total = (int)$stmtTotal->fetchColumn();

    $sql = "SELECT
            c.id,
            c.codigo,
            c.tipo_cliente,
            c.ruc,
            c.razon_social,
            c.nombre_comercial,
            c.telefono_principal,
            c.correo_principal,
            c.estado,
            c.actualizado_en,
            cp.id AS contacto_id,
            cp.nombre_completo AS contacto_nombre_completo,
            cp.cargo_relacion AS contacto_cargo_relacion,
            cp.telefono AS contacto_telefono,
            cp.correo AS contacto_correo
        FROM seg_clientes c
        LEFT JOIN seg_cliente_contactos cp
            ON cp.id = (
                SELECT cc.id
                FROM seg_cliente_contactos cc
                WHERE cc.cliente_id = c.id
                  AND cc.es_principal = 1
                  AND cc.estado = 1
                ORDER BY cc.id DESC
                LIMIT 1
            )
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

    $lastPage = max(1, (int)ceil($total / $perPage));
    cli_json_success([
        'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => $lastPage,
        ],
    ]);
}

function clientes_obtener(): void
{
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        cli_json_error('Registro no valido.', 422);
    }

    $pdo = cli_db();
    $empresa = clientes_fetch_empresa($pdo, $id);
    if (!$empresa) {
        cli_json_error('Cliente no encontrado.', 404);
    }

    $contacto = clientes_fetch_contacto_principal($pdo, $id);
    cli_json_success([
        'empresa' => $empresa,
        'contacto' => $contacto,
    ]);
}

function clientes_crear(): void
{
    $payload = cli_require_post_change('puede_crear');
    $data = cli_validate_empresa($payload);
    $pdo = cli_db();
    $userId = cli_user_id();
    $now = cli_now_lima();

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('INSERT INTO seg_clientes
            (codigo, tipo_cliente, ruc, razon_social, nombre_comercial, direccion, telefono_principal, correo_principal, observaciones, estado, creado_por_usuario_externo_id, actualizado_por_usuario_externo_id, creado_en, actualizado_en)
            VALUES
            (:codigo, :tipo_cliente, :ruc, :razon_social, :nombre_comercial, :direccion, :telefono_principal, :correo_principal, :observaciones, :estado, :creado_por, :actualizado_por, :creado_en, :actualizado_en)');
        $stmt->execute([
            ':codigo' => cli_codigo_cliente($pdo, 'empresa', $data['ruc']),
            ':tipo_cliente' => 'empresa',
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
        clientes_guardar_contacto_principal($pdo, $clienteId, $data['contacto'], $userId, $now);

        $pdo->commit();
        cli_json_success(['id' => $clienteId], 'Empresa cliente registrada correctamente.');
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e->getCode() === '23000') {
            cli_json_error('Ya existe una empresa cliente con ese RUC.', 409, ['ruc' => 'El RUC ya esta registrado.']);
        }
        throw $e;
    }
}

function clientes_actualizar(): void
{
    $payload = cli_require_post_change('puede_editar');
    $data = cli_validate_empresa($payload, true);
    $pdo = cli_db();
    $userId = cli_user_id();
    $now = cli_now_lima();

    try {
        $pdo->beginTransaction();

        if (!clientes_fetch_empresa($pdo, $data['id'])) {
            $pdo->rollBack();
            cli_json_error('Cliente no encontrado.', 404);
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
            WHERE id = :id');
        $stmt->execute([
            ':id' => $data['id'],
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

        clientes_guardar_contacto_principal($pdo, $data['id'], $data['contacto'], $userId, $now);

        $pdo->commit();
        cli_json_success(['id' => $data['id']], 'Empresa cliente actualizada correctamente.');
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e->getCode() === '23000') {
            cli_json_error('Ya existe una empresa cliente con ese RUC.', 409, ['ruc' => 'El RUC ya esta registrado.']);
        }
        throw $e;
    }
}

function clientes_cambiar_estado(): void
{
    $payload = cli_require_post_change('puede_eliminar');
    $id = (int)($payload['id'] ?? 0);
    if ($id <= 0) {
        cli_json_error('Registro no valido.', 422);
    }

    $pdo = cli_db();
    $empresa = clientes_fetch_empresa($pdo, $id);
    if (!$empresa) {
        cli_json_error('Cliente no encontrado.', 404);
    }

    $nuevoEstado = (int)$empresa['estado'] === 1 ? 0 : 1;
    $stmt = $pdo->prepare('UPDATE seg_clientes SET estado = :estado, actualizado_por_usuario_externo_id = :usuario, actualizado_en = :fecha WHERE id = :id');
    $stmt->execute([
        ':estado' => $nuevoEstado,
        ':usuario' => cli_user_id(),
        ':fecha' => cli_now_lima(),
        ':id' => $id,
    ]);

    cli_json_success(['id' => $id, 'estado' => $nuevoEstado], $nuevoEstado === 1 ? 'Empresa cliente activada.' : 'Empresa cliente desactivada.');
}

function clientes_fetch_empresa(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM seg_clientes WHERE id = :id AND tipo_cliente = 'empresa' LIMIT 1");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function clientes_fetch_contacto_principal(PDO $pdo, int $clienteId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM seg_cliente_contactos WHERE cliente_id = :cliente_id AND es_principal = 1 AND estado = 1 ORDER BY id DESC LIMIT 1');
    $stmt->execute([':cliente_id' => $clienteId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function clientes_guardar_contacto_principal(PDO $pdo, int $clienteId, array $contacto, ?int $userId, string $now): void
{
    if (empty($contacto['presente'])) {
        return;
    }

    $actual = clientes_fetch_contacto_principal($pdo, $clienteId);
    $actualId = is_array($actual) ? (int)$actual['id'] : 0;

    $stmtOtros = $pdo->prepare('UPDATE seg_cliente_contactos
        SET es_principal = 0, actualizado_por_usuario_externo_id = :usuario, actualizado_en = :fecha
        WHERE cliente_id = :cliente_id AND es_principal = 1 AND estado = 1 AND id <> :id');
    $stmtOtros->execute([
        ':usuario' => $userId,
        ':fecha' => $now,
        ':cliente_id' => $clienteId,
        ':id' => $actualId,
    ]);

    if ($actualId > 0) {
        $stmt = $pdo->prepare('UPDATE seg_cliente_contactos SET
                nombre_completo = :nombre_completo,
                cargo_relacion = :cargo_relacion,
                telefono = :telefono,
                correo = :correo,
                es_principal = 1,
                estado = :estado,
                actualizado_por_usuario_externo_id = :actualizado_por,
                actualizado_en = :actualizado_en
            WHERE id = :id');
        $stmt->execute([
            ':id' => $actualId,
            ':nombre_completo' => $contacto['nombre_completo'],
            ':cargo_relacion' => $contacto['cargo_relacion'],
            ':telefono' => $contacto['telefono'],
            ':correo' => $contacto['correo'],
            ':estado' => $contacto['estado'],
            ':actualizado_por' => $userId,
            ':actualizado_en' => $now,
        ]);
        return;
    }

    $stmt = $pdo->prepare('INSERT INTO seg_cliente_contactos
        (cliente_id, nombre_completo, cargo_relacion, telefono, correo, es_principal, estado, creado_por_usuario_externo_id, actualizado_por_usuario_externo_id, creado_en, actualizado_en)
        VALUES
        (:cliente_id, :nombre_completo, :cargo_relacion, :telefono, :correo, 1, :estado, :creado_por, :actualizado_por, :creado_en, :actualizado_en)');
    $stmt->execute([
        ':cliente_id' => $clienteId,
        ':nombre_completo' => $contacto['nombre_completo'],
        ':cargo_relacion' => $contacto['cargo_relacion'],
        ':telefono' => $contacto['telefono'],
        ':correo' => $contacto['correo'],
        ':estado' => $contacto['estado'],
        ':creado_por' => $userId,
        ':actualizado_por' => $userId,
        ':creado_en' => $now,
        ':actualizado_en' => $now,
    ]);
}
