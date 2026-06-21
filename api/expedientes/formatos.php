<?php
declare(strict_types=1);

require_once __DIR__ . '/_expedientes_common.php';

$accion = strtolower(trim((string) ($_GET['accion'] ?? $_POST['accion'] ?? 'listar')));

try {
    switch ($accion) {
        case 'listar':
            expfmt_listar();
            break;
        case 'descargar':
            expfmt_descargar();
            break;
        default:
            exp_json_error('Accion no valida.', 404);
    }
} catch (PDOException $e) {
    exp_db_error($e);
} catch (Throwable $e) {
    exp_db_error($e);
}

function expfmt_listar(): void
{
    exp_require_method('GET');
    exp_require_perm('puede_ver');
    $expedienteId = (int) ($_GET['expediente_id'] ?? 0);
    if ($expedienteId <= 0) {
        exp_json_error('Expediente no valido.', 422);
    }
    $pdo = exp_db();
    $expediente = exp_fetch($pdo, $expedienteId);
    if (!$expediente) {
        exp_json_error('Expediente no encontrado.', 404);
    }

    $stmt = $pdo->prepare("SELECT
            f.id,
            f.codigo,
            f.nombre,
            f.descripcion,
            f.requisito_tipo_seguro_id,
            rt.nombre AS requisito_nombre,
            v.id AS vinculo_id,
            a.nombre_original
        FROM seg_formatos_tipo_seguro f
        INNER JOIN seg_archivos_vinculos v ON v.codigo_uso = 'formato_tipo_seguro_archivo'
            AND v.entidad_tipo = 'formato_tipo_seguro'
            AND v.entidad_id = f.id
            AND v.slot = 'archivo_principal'
            AND v.estado = 1
        INNER JOIN seg_archivos a ON a.id = v.archivo_id AND a.estado = 1
        LEFT JOIN seg_requisitos_tipo_seguro rt ON rt.id = f.requisito_tipo_seguro_id
        WHERE f.tipo_seguro_id = :tipo_seguro_id
          AND f.estado = 1
        ORDER BY f.orden_visual ASC, f.nombre ASC, f.id ASC");
    $stmt->execute([':tipo_seguro_id' => (int) $expediente['tipo_seguro_id']]);
    exp_json_success(['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function expfmt_descargar(): void
{
    exp_require_method('GET');
    exp_require_perm('puede_ver');
    $expedienteId = (int) ($_GET['expediente_id'] ?? 0);
    $vinculoId = (int) ($_GET['vinculo_id'] ?? 0);
    if ($expedienteId <= 0 || $vinculoId <= 0) {
        http_response_code(422);
        echo 'Archivo no valido.';
        exit;
    }

    $pdo = exp_db();
    $expediente = exp_fetch($pdo, $expedienteId);
    if (!$expediente) {
        http_response_code(404);
        echo 'Expediente no encontrado.';
        exit;
    }

    $stmt = $pdo->prepare("SELECT
            v.archivo_id
        FROM seg_archivos_vinculos v
        INNER JOIN seg_formatos_tipo_seguro f ON f.id = v.entidad_id
        INNER JOIN seg_archivos a ON a.id = v.archivo_id
        WHERE v.id = :vinculo_id
          AND v.codigo_uso = 'formato_tipo_seguro_archivo'
          AND v.entidad_tipo = 'formato_tipo_seguro'
          AND v.slot = 'archivo_principal'
          AND v.estado = 1
          AND a.estado = 1
          AND f.estado = 1
          AND f.tipo_seguro_id = :tipo_seguro_id
        LIMIT 1");
    $stmt->execute([
        ':vinculo_id' => $vinculoId,
        ':tipo_seguro_id' => (int) $expediente['tipo_seguro_id'],
    ]);
    $archivoId = (int) ($stmt->fetchColumn() ?: 0);
    if ($archivoId <= 0) {
        http_response_code(404);
        echo 'Archivo no encontrado.';
        exit;
    }
    $archivo = cb_almacen_obtener_archivo($pdo, $archivoId, true);
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
