<?php
declare(strict_types=1);

require_once __DIR__ . '/_formatos_tipo_common.php';

$accion = strtolower(trim((string) ($_GET['accion'] ?? $_POST['accion'] ?? 'listar')));

try {
    switch ($accion) {
        case 'contexto':
            formatos_contexto();
            break;
        case 'listar':
            formatos_listar();
            break;
        case 'obtener':
            formatos_obtener();
            break;
        case 'crear':
            formatos_crear();
            break;
        case 'actualizar':
            formatos_actualizar();
            break;
        case 'reemplazar_archivo':
            formatos_reemplazar_archivo();
            break;
        case 'descargar':
            formatos_descargar();
            break;
        case 'cambiar_estado':
            formatos_cambiar_estado();
            break;
        default:
            fmt_json_error('Accion no valida.', 404);
    }
} catch (PDOException $e) {
    fmt_db_problem($e);
} catch (Throwable $e) {
    fmt_db_problem($e);
}

function formatos_contexto(): void
{
    fmt_require_method('GET');
    fmt_require_perm('puede_ver');
    $pdo = fmt_db();

    $tipos = $pdo->query("SELECT t.id, t.codigo, t.nombre, r.nombre AS ramo_nombre
        FROM seg_tipos_seguro t
        INNER JOIN seg_ramos r ON r.id = t.ramo_id
        WHERE t.estado = 1
        ORDER BY r.nombre ASC, t.orden_visual ASC, t.nombre ASC, t.id ASC")->fetchAll(PDO::FETCH_ASSOC);

    $requisitos = $pdo->query("SELECT id, tipo_seguro_id, codigo, nombre
        FROM seg_requisitos_tipo_seguro
        WHERE estado = 1
        ORDER BY tipo_seguro_id ASC, orden_visual ASC, nombre ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

    fmt_json_success([
        'tipos_seguro' => $tipos,
        'requisitos' => $requisitos,
        'csrf' => cb_local_csrf_token('formatos_tipo'),
    ]);
}

function formatos_listar(): void
{
    fmt_require_method('GET');
    fmt_require_perm('puede_ver');
    $pdo = fmt_db();
    [$page, $perPage, $offset] = fmt_page_params();

    $where = [];
    $params = [];

    $tipoSeguroId = isset($_GET['tipo_seguro_id']) && is_numeric($_GET['tipo_seguro_id']) ? (int) $_GET['tipo_seguro_id'] : 0;
    if ($tipoSeguroId > 0) {
        $where[] = 'f.tipo_seguro_id = :tipo_seguro_id';
        $params[':tipo_seguro_id'] = $tipoSeguroId;
    }

    $estado = strtolower(trim((string) ($_GET['estado'] ?? 'todos')));
    if ($estado === 'activo') {
        $where[] = 'f.estado = 1';
    } elseif ($estado === 'inactivo') {
        $where[] = 'f.estado = 0';
    }

    $q = trim((string) ($_GET['q'] ?? ''));
    $q = function_exists('mb_substr') ? mb_substr($q, 0, 120, 'UTF-8') : substr($q, 0, 120);
    if ($q !== '') {
        $where[] = "(f.codigo LIKE :q_codigo
            OR f.nombre LIKE :q_nombre
            OR f.descripcion LIKE :q_descripcion
            OR ts.nombre LIKE :q_tipo_seguro
            OR rt.nombre LIKE :q_requisito)";
        $like = fmt_bind_like($q);
        $params[':q_codigo'] = $like;
        $params[':q_nombre'] = $like;
        $params[':q_descripcion'] = $like;
        $params[':q_tipo_seguro'] = $like;
        $params[':q_requisito'] = $like;
    }

    $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';
    $fromSql = " FROM seg_formatos_tipo_seguro f
        INNER JOIN seg_tipos_seguro ts ON ts.id = f.tipo_seguro_id
        INNER JOIN seg_ramos r ON r.id = ts.ramo_id
        LEFT JOIN seg_requisitos_tipo_seguro rt ON rt.id = f.requisito_tipo_seguro_id";

    $stmtTotal = $pdo->prepare('SELECT COUNT(*)' . $fromSql . $whereSql);
    $stmtTotal->execute($params);
    $total = (int) $stmtTotal->fetchColumn();

    $stmt = $pdo->prepare("SELECT
            f.id,
            f.tipo_seguro_id,
            f.requisito_tipo_seguro_id,
            f.codigo,
            f.nombre,
            f.descripcion,
            f.orden_visual,
            f.estado,
            ts.nombre AS tipo_seguro_nombre,
            r.nombre AS ramo_nombre,
            rt.nombre AS requisito_nombre,
            (
                SELECT v.id
                FROM seg_archivos_vinculos v
                INNER JOIN seg_archivos a ON a.id = v.archivo_id
                WHERE v.codigo_uso = 'formato_tipo_seguro_archivo'
                  AND v.entidad_tipo = 'formato_tipo_seguro'
                  AND v.entidad_id = f.id
                  AND v.slot = 'archivo_principal'
                  AND v.estado = 1
                  AND a.estado = 1
                ORDER BY v.id DESC
                LIMIT 1
            ) AS archivo_vinculo_id,
            (
                SELECT a.nombre_original
                FROM seg_archivos_vinculos v
                INNER JOIN seg_archivos a ON a.id = v.archivo_id
                WHERE v.codigo_uso = 'formato_tipo_seguro_archivo'
                  AND v.entidad_tipo = 'formato_tipo_seguro'
                  AND v.entidad_id = f.id
                  AND v.slot = 'archivo_principal'
                  AND v.estado = 1
                  AND a.estado = 1
                ORDER BY v.id DESC
                LIMIT 1
            ) AS archivo_nombre
        " . $fromSql . $whereSql . '
        ORDER BY r.nombre ASC, ts.nombre ASC, f.orden_visual ASC, f.nombre ASC, f.id DESC
        LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset);
    $stmt->execute($params);

    fmt_json_success([
        'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ],
    ]);
}

function formatos_obtener(): void
{
    fmt_require_method('GET');
    fmt_require_perm('puede_ver');
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        fmt_json_error('Registro no valido.', 422);
    }
    $record = fmt_fetch(fmt_db(), $id);
    if (!$record) {
        fmt_json_error('Formato no encontrado.', 404);
    }
    $record['archivo'] = fmt_archivo_activo(fmt_db(), $id);
    fmt_json_success(['record' => $record]);
}

function formatos_crear(): void
{
    $payload = fmt_require_change('puede_crear');
    if (!isset($_FILES['archivo']) || !is_array($_FILES['archivo'])) {
        fmt_json_error('Seleccione el archivo principal del formato.', 422, ['archivo' => 'Archivo requerido.']);
    }

    $pdo = fmt_db();
    $data = fmt_validate($pdo, $payload, false);
    $userId = fmt_user_id();
    $now = fmt_now();
    $stored = null;

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO seg_formatos_tipo_seguro
            (tipo_seguro_id, requisito_tipo_seguro_id, codigo, nombre, descripcion, orden_visual, estado, creado_por_usuario_externo_id, actualizado_por_usuario_externo_id, creado_en, actualizado_en)
            VALUES
            (:tipo_seguro_id, :requisito_tipo_seguro_id, :codigo, :nombre, :descripcion, :orden_visual, :estado, :creado_por, :actualizado_por, :creado_en, :actualizado_en)');
        $stmt->execute([
            ':tipo_seguro_id' => $data['tipo_seguro_id'],
            ':requisito_tipo_seguro_id' => $data['requisito_tipo_seguro_id'],
            ':codigo' => fmt_codigo($pdo),
            ':nombre' => $data['nombre'],
            ':descripcion' => $data['descripcion'],
            ':orden_visual' => $data['orden_visual'],
            ':estado' => $data['estado'],
            ':creado_por' => $userId,
            ':actualizado_por' => $userId,
            ':creado_en' => $now,
            ':actualizado_en' => $now,
        ]);
        $id = (int) $pdo->lastInsertId();
        $uploadErrors = [];
        $stored = formatos_guardar_archivo_principal($pdo, $id, $data['nombre'], $userId, $uploadErrors);
        if (!$stored) {
            $pdo->rollBack();
            fmt_json_error('No se pudo guardar el archivo principal.', 422, $uploadErrors);
        }
        $pdo->commit();
        fmt_json_success(['id' => $id, 'archivo_id' => (int) $stored['id']], 'Formato registrado correctamente.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (is_array($stored ?? null) && !empty($stored['absolute_path']) && is_file((string) $stored['absolute_path'])) {
            @unlink((string) $stored['absolute_path']);
        }
        throw $e;
    }
}

function formatos_actualizar(): void
{
    $payload = fmt_require_change('puede_editar');
    $pdo = fmt_db();
    $data = fmt_validate($pdo, $payload, true);
    $record = fmt_fetch($pdo, $data['id']);
    if (!$record) {
        fmt_json_error('Formato no encontrado.', 404);
    }
    if ($data['estado'] === 1 && !fmt_archivo_activo($pdo, $data['id'])) {
        fmt_json_error('No se puede activar un formato sin archivo principal activo.', 409, ['estado' => 'Cargue primero el archivo principal.']);
    }

    $stmt = $pdo->prepare('UPDATE seg_formatos_tipo_seguro SET
            tipo_seguro_id = :tipo_seguro_id,
            requisito_tipo_seguro_id = :requisito_tipo_seguro_id,
            nombre = :nombre,
            descripcion = :descripcion,
            orden_visual = :orden_visual,
            estado = :estado,
            actualizado_por_usuario_externo_id = :actualizado_por,
            actualizado_en = :actualizado_en
        WHERE id = :id');
    $stmt->execute([
        ':id' => $data['id'],
        ':tipo_seguro_id' => $data['tipo_seguro_id'],
        ':requisito_tipo_seguro_id' => $data['requisito_tipo_seguro_id'],
        ':nombre' => $data['nombre'],
        ':descripcion' => $data['descripcion'],
        ':orden_visual' => $data['orden_visual'],
        ':estado' => $data['estado'],
        ':actualizado_por' => fmt_user_id(),
        ':actualizado_en' => fmt_now(),
    ]);

    fmt_json_success(['id' => $data['id']], 'Formato actualizado correctamente.');
}

function formatos_reemplazar_archivo(): void
{
    $payload = fmt_require_change('puede_editar');
    $id = isset($payload['id']) && is_numeric($payload['id']) ? (int) $payload['id'] : 0;
    if ($id <= 0) {
        fmt_json_error('Formato no valido.', 422);
    }
    if (!isset($_FILES['archivo']) || !is_array($_FILES['archivo'])) {
        fmt_json_error('Seleccione el archivo principal del formato.', 422, ['archivo' => 'Archivo requerido.']);
    }

    $pdo = fmt_db();
    $record = fmt_fetch($pdo, $id);
    if (!$record) {
        fmt_json_error('Formato no encontrado.', 404);
    }

    $userId = fmt_user_id();
    $now = fmt_now();
    $stored = null;

    try {
        $pdo->beginTransaction();
        fmt_archivar_archivo_principal($pdo, $id, $userId, $now);
        $uploadErrors = [];
        $stored = formatos_guardar_archivo_principal($pdo, $id, (string) $record['nombre'], $userId, $uploadErrors);
        if (!$stored) {
            $pdo->rollBack();
            fmt_json_error('No se pudo guardar el archivo principal.', 422, $uploadErrors);
        }
        $pdo->commit();
        fmt_json_success(['id' => $id, 'archivo_id' => (int) $stored['id']], 'Archivo principal reemplazado correctamente.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (is_array($stored ?? null) && !empty($stored['absolute_path']) && is_file((string) $stored['absolute_path'])) {
            @unlink((string) $stored['absolute_path']);
        }
        throw $e;
    }
}

function formatos_descargar(): void
{
    fmt_require_method('GET');
    fmt_require_perm('puede_ver');
    $vinculoId = (int) ($_GET['vinculo_id'] ?? 0);
    if ($vinculoId <= 0) {
        http_response_code(422);
        echo 'Archivo no valido.';
        exit;
    }
    $pdo = fmt_db();
    $link = fmt_fetch_vinculo($pdo, $vinculoId, true);
    if (!$link) {
        http_response_code(404);
        echo 'Archivo no encontrado.';
        exit;
    }
    $archivo = cb_almacen_obtener_archivo($pdo, (int) $link['archivo_id'], true);
    if (!$archivo) {
        http_response_code(404);
        echo 'Archivo no disponible.';
        exit;
    }
    $payload = cb_almacen_payload_archivo($archivo);
    if (!$payload) {
        http_response_code(404);
        echo 'Archivo no disponible.';
        exit;
    }
    cb_almacen_servir_archivo($payload, false);
}

function formatos_cambiar_estado(): void
{
    $payload = fmt_require_change('puede_eliminar');
    $id = isset($payload['id']) && is_numeric($payload['id']) ? (int) $payload['id'] : 0;
    if ($id <= 0) {
        fmt_json_error('Registro no valido.', 422);
    }
    $pdo = fmt_db();
    $record = fmt_fetch($pdo, $id);
    if (!$record) {
        fmt_json_error('Formato no encontrado.', 404);
    }
    $nuevoEstado = (int) $record['estado'] === 1 ? 0 : 1;
    if ($nuevoEstado === 1) {
        fmt_require_active_tipo($pdo, (int) $record['tipo_seguro_id']);
        if ((int) ($record['archivo_vinculo_id'] ?? 0) <= 0) {
            fmt_json_error('No se puede activar un formato sin archivo principal activo.', 409);
        }
    }

    $stmt = $pdo->prepare('UPDATE seg_formatos_tipo_seguro
        SET estado = :estado,
            actualizado_por_usuario_externo_id = :usuario,
            actualizado_en = :fecha
        WHERE id = :id');
    $stmt->execute([
        ':estado' => $nuevoEstado,
        ':usuario' => fmt_user_id(),
        ':fecha' => fmt_now(),
        ':id' => $id,
    ]);

    fmt_json_success(['id' => $id, 'estado' => $nuevoEstado], $nuevoEstado === 1 ? 'Formato activado.' : 'Formato desactivado.');
}

function formatos_guardar_archivo_principal(PDO $pdo, int $formatoId, string $nombre, ?int $userId, array &$errors = []): ?array
{
    $errors = [];
    $stored = cb_almacen_guardar_upload($pdo, (array) $_FILES['archivo'], [
        'carpeta' => 'formatos_tipo/archivos',
        'usuario_id' => $userId,
        'descripcion' => 'Archivo principal de formato: ' . $nombre,
        'vinculo' => [
            'codigo_uso' => 'formato_tipo_seguro_archivo',
            'entidad_tipo' => 'formato_tipo_seguro',
            'entidad_id' => $formatoId,
            'slot' => 'archivo_principal',
            'metadata' => [
                'formato_id' => $formatoId,
                'nombre' => $nombre,
            ],
        ],
    ], $errors);

    return $stored ?: null;
}
