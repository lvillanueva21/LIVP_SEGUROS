<?php
declare(strict_types=1);

require_once __DIR__ . '/_expedientes_common.php';

$accion = strtolower(trim((string) ($_GET['accion'] ?? $_POST['accion'] ?? 'listar')));

try {
    switch ($accion) {
        case 'tipos':
            doc_tipos();
            break;
        case 'listar':
            doc_listar();
            break;
        case 'cargar':
            doc_cargar();
            break;
        case 'archivar':
            doc_archivar();
            break;
        case 'descargar':
            doc_descargar();
            break;
        default:
            exp_json_error('Accion no valida.', 404);
    }
} catch (PDOException $e) {
    exp_db_error($e);
} catch (Throwable $e) {
    exp_db_error($e);
}

function doc_tipos(): void
{
    exp_require_method('GET');
    exp_require_perm('puede_ver');
    $rows = [];
    foreach (exp_tipo_documento_options() as $codigo => $nombre) {
        $rows[] = ['codigo' => $codigo, 'nombre' => $nombre];
    }
    exp_json_success(['rows' => $rows]);
}

function doc_listar(): void
{
    exp_require_method('GET');
    exp_require_perm('puede_ver');
    $expedienteId = (int) ($_GET['expediente_id'] ?? 0);
    if ($expedienteId <= 0) {
        exp_json_error('Expediente no valido.', 422);
    }
    $pdo = exp_db();
    doc_require_expediente($pdo, $expedienteId);

    $stmt = $pdo->prepare("SELECT
            v.id AS vinculo_id,
            v.archivo_id,
            v.slot,
            v.estado AS vinculo_estado,
            v.creado_por_usuario_externo_id AS cargado_por_usuario_externo_id,
            v.creado_en AS cargado_en,
            a.nombre_original,
            a.extension,
            a.mime_type,
            a.tamanio_bytes,
            a.descripcion,
            a.estado AS archivo_estado
        FROM seg_archivos_vinculos v
        INNER JOIN seg_archivos a ON a.id = v.archivo_id
        WHERE v.codigo_uso = 'expediente_documento'
          AND v.entidad_tipo = 'expediente'
          AND v.entidad_id = :expediente_id
        ORDER BY v.creado_en DESC, v.id DESC");
    $stmt->execute([':expediente_id' => $expedienteId]);
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row['tipo_documento_nombre'] = exp_tipo_documento_label((string) ($row['slot'] ?? ''));
        $rows[] = $row;
    }

    exp_json_success(['rows' => $rows]);
}

function doc_cargar(): void
{
    $payload = exp_require_change('puede_crear');
    $expedienteId = (int) ($payload['expediente_id'] ?? 0);
    if ($expedienteId <= 0) {
        exp_json_error('Expediente no valido.', 422, ['expediente_id' => 'Expediente no valido.']);
    }

    $tipo = strtolower(trim((string) ($payload['tipo_documento'] ?? '')));
    $tipos = exp_tipo_documento_options();
    if (!isset($tipos[$tipo])) {
        exp_json_error('Seleccione un tipo de documento valido.', 422, ['tipo_documento' => 'Tipo no valido.']);
    }
    if (!isset($_FILES['archivo']) || !is_array($_FILES['archivo'])) {
        exp_json_error('Seleccione un archivo para cargar.', 422, ['archivo' => 'Archivo requerido.']);
    }

    $descripcion = exp_str($payload, 'descripcion', 255, true);
    $pdo = exp_db();
    $expediente = doc_require_expediente($pdo, $expedienteId);
    $userId = exp_user_id();
    $now = exp_now();
    $stored = null;

    try {
        $pdo->beginTransaction();
        $errors = [];
        $stored = cb_almacen_guardar_upload($pdo, (array) $_FILES['archivo'], [
            'carpeta' => 'expedientes/documentos',
            'usuario_id' => $userId,
            'descripcion' => $descripcion,
            'vinculo' => [
                'codigo_uso' => 'expediente_documento',
                'entidad_tipo' => 'expediente',
                'entidad_id' => $expedienteId,
                'slot' => $tipo,
                'metadata' => [
                    'tipo_documento_nombre' => $tipos[$tipo],
                    'expediente_codigo' => $expediente['codigo'],
                ],
            ],
        ], $errors);

        if (!$stored) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            exp_json_error('No se pudo guardar el documento.', 422, $errors);
        }

        exp_timeline_add($pdo, 'expediente', $expedienteId, 'documento_cargado', 'Documento cargado: ' . $stored['nombre_original'], [
            'archivo_id' => (int) $stored['id'],
            'vinculo_id' => (int) (($stored['vinculo']['id'] ?? 0)),
            'tipo_documento' => $tipo,
            'tipo_documento_nombre' => $tipos[$tipo],
        ], $userId, $now);

        $pdo->commit();
        exp_json_success([
            'archivo_id' => (int) $stored['id'],
            'vinculo_id' => (int) (($stored['vinculo']['id'] ?? 0)),
        ], 'Documento cargado correctamente.');
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

function doc_archivar(): void
{
    $payload = exp_require_change('puede_editar');
    $vinculoId = (int) ($payload['vinculo_id'] ?? 0);
    if ($vinculoId <= 0) {
        exp_json_error('Documento no valido.', 422);
    }
    $pdo = exp_db();
    $doc = doc_fetch_vinculo($pdo, $vinculoId, false);
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
        $stmt = $pdo->prepare('UPDATE seg_archivos_vinculos
            SET estado = 0,
                actualizado_por_usuario_externo_id = :usuario,
                actualizado_en = :fecha
            WHERE id = :id');
        $stmt->execute([
            ':usuario' => $userId,
            ':fecha' => $now,
            ':id' => $vinculoId,
        ]);
        exp_timeline_add($pdo, 'expediente', (int) $doc['entidad_id'], 'documento_archivado', 'Documento archivado: ' . (string) $doc['nombre_original'], [
            'archivo_id' => (int) $doc['archivo_id'],
            'vinculo_id' => $vinculoId,
            'tipo_documento' => (string) $doc['slot'],
        ], $userId, $now);
        $pdo->commit();
        exp_json_success(['vinculo_id' => $vinculoId], 'Documento archivado correctamente.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function doc_descargar(): void
{
    exp_require_method('GET');
    exp_require_perm('puede_ver');
    $vinculoId = (int) ($_GET['vinculo_id'] ?? 0);
    if ($vinculoId <= 0) {
        http_response_code(422);
        echo 'Documento no valido.';
        exit;
    }
    $pdo = exp_db();
    $doc = doc_fetch_vinculo($pdo, $vinculoId, true);
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

function doc_require_expediente(PDO $pdo, int $expedienteId): array
{
    $stmt = $pdo->prepare('SELECT id, codigo, estado FROM seg_expedientes WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $expedienteId]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$record) {
        exp_json_error('Expediente no encontrado.', 404);
    }
    return $record;
}

function doc_fetch_vinculo(PDO $pdo, int $vinculoId, bool $soloActivo): ?array
{
    $sql = "SELECT
            v.id AS vinculo_id,
            v.archivo_id,
            v.codigo_uso,
            v.entidad_tipo,
            v.entidad_id,
            v.slot,
            v.estado AS vinculo_estado,
            a.nombre_original,
            a.estado AS archivo_estado
        FROM seg_archivos_vinculos v
        INNER JOIN seg_archivos a ON a.id = v.archivo_id
        WHERE v.id = :id
          AND v.codigo_uso = 'expediente_documento'
          AND v.entidad_tipo = 'expediente'";
    if ($soloActivo) {
        $sql .= ' AND v.estado = 1 AND a.estado = 1';
    }
    $sql .= ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $vinculoId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}
