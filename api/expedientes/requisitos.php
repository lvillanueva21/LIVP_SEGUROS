<?php
declare(strict_types=1);

require_once __DIR__ . '/_expedientes_common.php';

$accion = strtolower(trim((string) ($_GET['accion'] ?? $_POST['accion'] ?? 'listar')));

try {
    switch ($accion) {
        case 'estados':
            reqexp_estados();
            break;
        case 'listar':
            reqexp_listar();
            break;
        case 'generar':
            reqexp_generar();
            break;
        case 'cambiar_estado':
            reqexp_cambiar_estado();
            break;
        case 'cargar_documento':
            reqexp_cargar_documento();
            break;
        case 'archivar_documento':
            reqexp_archivar_documento();
            break;
        case 'descargar':
            reqexp_descargar();
            break;
        default:
            exp_json_error('Accion no valida.', 404);
    }
} catch (PDOException $e) {
    exp_db_error($e);
} catch (Throwable $e) {
    exp_db_error($e);
}

function reqexp_estados(): void
{
    exp_require_method('GET');
    exp_require_perm('puede_ver');
    $rows = [];
    foreach (exp_requisito_estados() as $codigo => $nombre) {
        $rows[] = ['codigo' => $codigo, 'nombre' => $nombre];
    }
    exp_json_success(['rows' => $rows]);
}

function reqexp_listar(): void
{
    exp_require_method('GET');
    exp_require_perm('puede_ver');
    $expedienteId = (int) ($_GET['expediente_id'] ?? 0);
    if ($expedienteId <= 0) {
        exp_json_error('Expediente no valido.', 422);
    }
    $estado = strtolower(trim((string) ($_GET['estado'] ?? 'todos')));
    $pdo = exp_db();
    reqexp_require_expediente($pdo, $expedienteId);
    $stmtAll = $pdo->prepare('SELECT COUNT(*) FROM seg_expediente_requisitos WHERE expediente_id = :id');
    $stmtAll->execute([':id' => $expedienteId]);
    $totalExpediente = (int) $stmtAll->fetchColumn();

    $where = ['er.expediente_id = :expediente_id'];
    $params = [':expediente_id' => $expedienteId];
    if ($estado !== 'todos' && exp_requisito_estado_valido($estado)) {
        $where[] = 'er.estado_requisito = :estado';
        $params[':estado'] = $estado;
    }

    $stmt = $pdo->prepare('SELECT
            er.*,
            (
                SELECT COUNT(*)
                FROM seg_archivos_vinculos v
                INNER JOIN seg_archivos a ON a.id = v.archivo_id
                WHERE v.codigo_uso = \'expediente_requisito_documento\'
                  AND v.entidad_tipo = \'expediente_requisito\'
                  AND v.entidad_id = er.id
                  AND v.estado = 1
                  AND a.estado = 1
            ) AS documentos_activos
        FROM seg_expediente_requisitos er
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY er.orden_visual_snapshot ASC, er.nombre_snapshot ASC, er.id ASC');
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $docs = reqexp_documentos_por_requisitos($pdo, array_map(static function ($row) {
        return (int) $row['id'];
    }, $rows));

    foreach ($rows as &$row) {
        $row['estado_requisito_nombre'] = exp_requisito_estado_label((string) $row['estado_requisito']);
        $row['documentos'] = $docs[(int) $row['id']] ?? [];
    }
    unset($row);

    exp_json_success([
        'rows' => $rows,
        'total' => count($rows),
        'tiene_requisitos' => $totalExpediente > 0,
    ]);
}

function reqexp_generar(): void
{
    $payload = exp_require_change('puede_crear');
    $expedienteId = (int) ($payload['expediente_id'] ?? 0);
    if ($expedienteId <= 0) {
        exp_json_error('Expediente no valido.', 422);
    }

    $pdo = exp_db();
    $expediente = reqexp_require_expediente($pdo, $expedienteId);
    $userId = exp_user_id();
    $now = exp_now();

    try {
        $pdo->beginTransaction();
        $count = exp_generar_requisitos_expediente($pdo, $expedienteId, (int) $expediente['tipo_seguro_id'], $userId, $now);
        if ($count <= 0) {
            $pdo->rollBack();
            exp_json_error('El expediente ya tiene requisitos o no existen requisitos activos para su tipo de seguro.', 409);
        }
        exp_timeline_add($pdo, 'expediente', $expedienteId, 'requisitos_generados', 'Requisitos generados para el expediente.', [
            'cantidad' => $count,
            'tipo_seguro_id' => (int) $expediente['tipo_seguro_id'],
        ], $userId, $now);
        $pdo->commit();
        exp_json_success(['cantidad' => $count], 'Requisitos generados correctamente.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function reqexp_cambiar_estado(): void
{
    $payload = exp_require_change('puede_editar');
    $id = (int) ($payload['id'] ?? 0);
    $expedienteId = (int) ($payload['expediente_id'] ?? 0);
    $estadoNuevo = strtolower(trim((string) ($payload['estado_requisito'] ?? '')));
    $observacion = exp_str($payload, 'observacion_actual', 1000, true);

    if ($id <= 0 || $expedienteId <= 0) {
        exp_json_error('Requisito no valido.', 422);
    }
    if (!exp_requisito_estado_valido($estadoNuevo)) {
        exp_json_error('Seleccione un estado valido.', 422, ['estado_requisito' => 'Estado no valido.']);
    }
    if (in_array($estadoNuevo, ['observado', 'rechazado', 'no_aplica'], true) && $observacion === null) {
        exp_json_error('Ingrese una observacion o motivo para ese estado.', 422, ['observacion_actual' => 'Observacion requerida.']);
    }

    $pdo = exp_db();
    $req = reqexp_fetch_requisito($pdo, $id, $expedienteId);
    if (!$req) {
        exp_json_error('Requisito no encontrado.', 404);
    }

    $estadoAnterior = (string) $req['estado_requisito'];
    $observacionAnterior = $req['observacion_actual'] === null ? null : (string) $req['observacion_actual'];
    if ($estadoAnterior === $estadoNuevo && $observacionAnterior === $observacion) {
        exp_json_success(['id' => $id], 'No hubo cambios para guardar.');
    }

    $userId = exp_user_id();
    $now = exp_now();
    $setEntrega = $estadoNuevo === 'entregado';
    $setEvaluacion = in_array($estadoNuevo, ['observado', 'aprobado', 'rechazado', 'no_aplica'], true);

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('UPDATE seg_expediente_requisitos SET
                estado_requisito = :estado,
                observacion_actual = :observacion,
                fecha_entrega = CASE WHEN :set_entrega = 1 THEN :fecha_entrega ELSE fecha_entrega END,
                entregado_por_usuario_externo_id = CASE WHEN :set_entrega2 = 1 THEN :usuario_entrega ELSE entregado_por_usuario_externo_id END,
                fecha_evaluacion = CASE WHEN :set_eval = 1 THEN :fecha_eval ELSE fecha_evaluacion END,
                evaluado_por_usuario_externo_id = CASE WHEN :set_eval2 = 1 THEN :usuario_eval ELSE evaluado_por_usuario_externo_id END,
                actualizado_por_usuario_externo_id = :actualizado_por,
                actualizado_en = :actualizado_en
            WHERE id = :id AND expediente_id = :expediente_id');
        $stmt->execute([
            ':estado' => $estadoNuevo,
            ':observacion' => $observacion,
            ':set_entrega' => $setEntrega ? 1 : 0,
            ':set_entrega2' => $setEntrega ? 1 : 0,
            ':fecha_entrega' => $now,
            ':usuario_entrega' => $userId,
            ':set_eval' => $setEvaluacion ? 1 : 0,
            ':set_eval2' => $setEvaluacion ? 1 : 0,
            ':fecha_eval' => $now,
            ':usuario_eval' => $userId,
            ':actualizado_por' => $userId,
            ':actualizado_en' => $now,
            ':id' => $id,
            ':expediente_id' => $expedienteId,
        ]);
        if ($estadoAnterior !== $estadoNuevo) {
            reqexp_evento_estado($pdo, $expedienteId, $req, $estadoAnterior, $estadoNuevo, $observacion, $userId, $now);
        }
        $pdo->commit();
        exp_json_success(['id' => $id], 'Estado del requisito actualizado.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function reqexp_cargar_documento(): void
{
    $payload = exp_require_change('puede_crear');
    $id = (int) ($payload['id'] ?? 0);
    $expedienteId = (int) ($payload['expediente_id'] ?? 0);
    if ($id <= 0 || $expedienteId <= 0) {
        exp_json_error('Requisito no valido.', 422);
    }
    if (!isset($_FILES['archivo']) || !is_array($_FILES['archivo'])) {
        exp_json_error('Seleccione un archivo para cargar.', 422, ['archivo' => 'Archivo requerido.']);
    }

    $pdo = exp_db();
    $req = reqexp_fetch_requisito($pdo, $id, $expedienteId);
    if (!$req) {
        exp_json_error('Requisito no encontrado.', 404);
    }
    if (!in_array((string) $req['estado_requisito'], ['pendiente', 'entregado', 'observado', 'rechazado'], true)) {
        exp_json_error('No se pueden cargar documentos cuando el requisito esta aprobado o no aplica.', 409);
    }

    $userId = exp_user_id();
    $now = exp_now();
    $stored = null;
    $estadoAnterior = (string) $req['estado_requisito'];

    try {
        $pdo->beginTransaction();
        $errors = [];
        $stored = cb_almacen_guardar_upload($pdo, (array) $_FILES['archivo'], [
            'carpeta' => 'expedientes/requisitos',
            'usuario_id' => $userId,
            'descripcion' => 'Respuesta de requisito: ' . (string) $req['nombre_snapshot'],
            'vinculo' => [
                'codigo_uso' => 'expediente_requisito_documento',
                'entidad_tipo' => 'expediente_requisito',
                'entidad_id' => $id,
                'slot' => 'respuesta_requisito',
                'metadata' => [
                    'expediente_id' => $expedienteId,
                    'requisito' => (string) $req['nombre_snapshot'],
                ],
            ],
        ], $errors);
        if (!$stored) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            exp_json_error('No se pudo guardar el documento.', 422, $errors);
        }

        $stmt = $pdo->prepare('UPDATE seg_expediente_requisitos SET
                estado_requisito = :estado,
                fecha_entrega = :fecha_entrega,
                entregado_por_usuario_externo_id = :usuario_entrega,
                actualizado_por_usuario_externo_id = :actualizado_por,
                actualizado_en = :actualizado_en
            WHERE id = :id AND expediente_id = :expediente_id');
        $stmt->execute([
            ':estado' => 'entregado',
            ':fecha_entrega' => $now,
            ':usuario_entrega' => $userId,
            ':actualizado_por' => $userId,
            ':actualizado_en' => $now,
            ':id' => $id,
            ':expediente_id' => $expedienteId,
        ]);

        exp_timeline_add($pdo, 'expediente', $expedienteId, 'requisito_documento_cargado', 'Documento de requisito cargado: ' . $stored['nombre_original'], [
            'requisito_id' => $id,
            'requisito' => (string) $req['nombre_snapshot'],
            'archivo_id' => (int) $stored['id'],
            'vinculo_id' => (int) (($stored['vinculo']['id'] ?? 0)),
        ], $userId, $now);

        if ($estadoAnterior !== 'entregado') {
            reqexp_evento_estado($pdo, $expedienteId, $req, $estadoAnterior, 'entregado', $req['observacion_actual'] ?? null, $userId, $now);
        }

        $pdo->commit();
        exp_json_success([
            'archivo_id' => (int) $stored['id'],
            'vinculo_id' => (int) (($stored['vinculo']['id'] ?? 0)),
        ], 'Documento de requisito cargado correctamente.');
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

function reqexp_archivar_documento(): void
{
    $payload = exp_require_change('puede_editar');
    $vinculoId = (int) ($payload['vinculo_id'] ?? 0);
    $expedienteId = (int) ($payload['expediente_id'] ?? 0);
    if ($vinculoId <= 0 || $expedienteId <= 0) {
        exp_json_error('Documento no valido.', 422);
    }

    $pdo = exp_db();
    $doc = reqexp_fetch_documento($pdo, $vinculoId, $expedienteId, false);
    if (!$doc) {
        exp_json_error('Documento no encontrado.', 404);
    }
    if ((int) $doc['vinculo_estado'] !== 1) {
        exp_json_success(['vinculo_id' => $vinculoId], 'El documento ya estaba archivado.');
    }

    $userId = exp_user_id();
    $now = exp_now();
    try {
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE seg_archivos_vinculos
            SET estado = 0,
                actualizado_por_usuario_externo_id = :usuario,
                actualizado_en = :fecha
            WHERE id = :id')
            ->execute([':usuario' => $userId, ':fecha' => $now, ':id' => $vinculoId]);

        exp_timeline_add($pdo, 'expediente', $expedienteId, 'requisito_documento_archivado', 'Documento de requisito archivado: ' . (string) $doc['nombre_original'], [
            'requisito_id' => (int) $doc['requisito_id'],
            'requisito' => (string) $doc['nombre_snapshot'],
            'archivo_id' => (int) $doc['archivo_id'],
            'vinculo_id' => $vinculoId,
        ], $userId, $now);

        $activos = reqexp_count_documentos_activos($pdo, (int) $doc['requisito_id']);
        if ($activos === 0 && (string) $doc['estado_requisito'] === 'entregado') {
            $pdo->prepare('UPDATE seg_expediente_requisitos
                SET estado_requisito = :estado,
                    fecha_entrega = NULL,
                    entregado_por_usuario_externo_id = NULL,
                    actualizado_por_usuario_externo_id = :usuario,
                    actualizado_en = :fecha
                WHERE id = :id')
                ->execute([':estado' => 'pendiente', ':usuario' => $userId, ':fecha' => $now, ':id' => (int) $doc['requisito_id']]);
            reqexp_evento_estado($pdo, $expedienteId, [
                'id' => (int) $doc['requisito_id'],
                'nombre_snapshot' => (string) $doc['nombre_snapshot'],
            ], 'entregado', 'pendiente', null, $userId, $now);
        }

        $pdo->commit();
        exp_json_success(['vinculo_id' => $vinculoId], 'Documento archivado correctamente.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function reqexp_descargar(): void
{
    exp_require_method('GET');
    exp_require_perm('puede_ver');
    $vinculoId = (int) ($_GET['vinculo_id'] ?? 0);
    $expedienteId = (int) ($_GET['expediente_id'] ?? 0);
    if ($vinculoId <= 0 || $expedienteId <= 0) {
        http_response_code(422);
        echo 'Documento no valido.';
        exit;
    }

    $pdo = exp_db();
    $doc = reqexp_fetch_documento($pdo, $vinculoId, $expedienteId, true);
    if (!$doc) {
        http_response_code(404);
        echo 'Documento no encontrado.';
        exit;
    }
    $archivo = cb_almacen_obtener_archivo($pdo, (int) $doc['archivo_id'], true);
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

function reqexp_require_expediente(PDO $pdo, int $expedienteId): array
{
    $stmt = $pdo->prepare('SELECT id, codigo, tipo_seguro_id, estado FROM seg_expedientes WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $expedienteId]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$record) {
        exp_json_error('Expediente no encontrado.', 404);
    }
    return $record;
}

function reqexp_fetch_requisito(PDO $pdo, int $id, int $expedienteId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM seg_expediente_requisitos WHERE id = :id AND expediente_id = :expediente_id LIMIT 1');
    $stmt->execute([':id' => $id, ':expediente_id' => $expedienteId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function reqexp_documentos_por_requisitos(PDO $pdo, array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT
            v.id AS vinculo_id,
            v.entidad_id AS requisito_id,
            v.archivo_id,
            v.estado AS vinculo_estado,
            v.creado_por_usuario_externo_id AS cargado_por_usuario_externo_id,
            v.creado_en AS cargado_en,
            a.nombre_original,
            a.mime_type,
            a.tamanio_bytes,
            a.estado AS archivo_estado
        FROM seg_archivos_vinculos v
        INNER JOIN seg_archivos a ON a.id = v.archivo_id
        WHERE v.codigo_uso = 'expediente_requisito_documento'
          AND v.entidad_tipo = 'expediente_requisito'
          AND v.entidad_id IN ({$placeholders})
        ORDER BY v.creado_en DESC, v.id DESC");
    $stmt->execute($ids);
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $out[(int) $row['requisito_id']][] = $row;
    }
    return $out;
}

function reqexp_fetch_documento(PDO $pdo, int $vinculoId, int $expedienteId, bool $soloActivo): ?array
{
    $sql = "SELECT
            v.id AS vinculo_id,
            v.archivo_id,
            v.estado AS vinculo_estado,
            a.nombre_original,
            a.estado AS archivo_estado,
            er.id AS requisito_id,
            er.expediente_id,
            er.nombre_snapshot,
            er.estado_requisito
        FROM seg_archivos_vinculos v
        INNER JOIN seg_archivos a ON a.id = v.archivo_id
        INNER JOIN seg_expediente_requisitos er ON er.id = v.entidad_id
        WHERE v.id = :id
          AND v.codigo_uso = 'expediente_requisito_documento'
          AND v.entidad_tipo = 'expediente_requisito'
          AND er.expediente_id = :expediente_id";
    if ($soloActivo) {
        $sql .= ' AND v.estado = 1 AND a.estado = 1';
    }
    $sql .= ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $vinculoId, ':expediente_id' => $expedienteId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function reqexp_count_documentos_activos(PDO $pdo, int $requisitoId): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*)
        FROM seg_archivos_vinculos v
        INNER JOIN seg_archivos a ON a.id = v.archivo_id
        WHERE v.codigo_uso = 'expediente_requisito_documento'
          AND v.entidad_tipo = 'expediente_requisito'
          AND v.entidad_id = :id
          AND v.estado = 1
          AND a.estado = 1");
    $stmt->execute([':id' => $requisitoId]);
    return (int) $stmt->fetchColumn();
}

function reqexp_evento_estado(PDO $pdo, int $expedienteId, array $req, string $estadoAnterior, string $estadoNuevo, ?string $observacion, ?int $userId, string $now): void
{
    exp_timeline_add($pdo, 'expediente', $expedienteId, 'requisito_estado_modificado', 'Estado de requisito modificado: ' . (string) $req['nombre_snapshot'], [
        'requisito_id' => (int) $req['id'],
        'requisito' => (string) $req['nombre_snapshot'],
        'estado_anterior' => $estadoAnterior,
        'estado_anterior_nombre' => exp_requisito_estado_label($estadoAnterior),
        'estado_nuevo' => $estadoNuevo,
        'estado_nuevo_nombre' => exp_requisito_estado_label($estadoNuevo),
        'observacion' => $observacion,
    ], $userId, $now);
}
