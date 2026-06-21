<?php
declare(strict_types=1);

require_once __DIR__ . '/_expedientes_common.php';

$accion = strtolower(trim((string) ($_GET['accion'] ?? $_POST['accion'] ?? 'listar')));

try {
    switch ($accion) {
        case 'contexto':
            pol_contexto();
            break;
        case 'listar':
            pol_listar();
            break;
        case 'obtener':
            pol_obtener();
            break;
        case 'crear':
            pol_crear();
            break;
        case 'actualizar':
            pol_actualizar();
            break;
        case 'cambiar_estado':
            pol_cambiar_estado();
            break;
        case 'cargar_pdf':
            pol_cargar_pdf();
            break;
        case 'archivar_pdf':
            pol_archivar_pdf();
            break;
        case 'descargar_pdf':
            pol_descargar_pdf();
            break;
        default:
            exp_json_error('Accion no valida.', 404);
    }
} catch (PDOException $e) {
    exp_db_error($e);
} catch (Throwable $e) {
    exp_db_error($e);
}

function pol_contexto(): void
{
    exp_require_method('GET');
    exp_require_perm('puede_ver');
    $pdo = exp_db();
    $aseguradoras = $pdo->query("SELECT id, codigo, razon_social, nombre_comercial
        FROM seg_aseguradoras
        WHERE estado = 1
        ORDER BY nombre_comercial ASC, razon_social ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

    $estados = [];
    foreach (exp_poliza_estados() as $codigo => $nombre) {
        $estados[] = ['codigo' => $codigo, 'nombre' => $nombre];
    }
    $tipos = [];
    foreach (exp_poliza_documentos() as $codigo => $nombre) {
        $tipos[] = ['codigo' => $codigo, 'nombre' => $nombre];
    }

    exp_json_success([
        'aseguradoras' => $aseguradoras,
        'estados' => $estados,
        'tipos_documento' => $tipos,
        'csrf' => cb_local_csrf_token('expedientes'),
    ]);
}

function pol_listar(): void
{
    exp_require_method('GET');
    exp_require_perm('puede_ver');
    $expedienteId = (int) ($_GET['expediente_id'] ?? 0);
    if ($expedienteId <= 0) {
        exp_json_error('Expediente no valido.', 422);
    }
    $pdo = exp_db();
    pol_require_expediente($pdo, $expedienteId, false);
    [$page, $perPage, $offset] = exp_page_params();

    $where = ['p.expediente_id = :expediente_id'];
    $params = [':expediente_id' => $expedienteId];

    $aseguradoraId = isset($_GET['aseguradora_id']) && is_numeric($_GET['aseguradora_id']) ? (int) $_GET['aseguradora_id'] : 0;
    if ($aseguradoraId > 0) {
        $where[] = 'p.aseguradora_id = :aseguradora_id';
        $params[':aseguradora_id'] = $aseguradoraId;
    }
    $estadoPoliza = strtolower(trim((string) ($_GET['estado_poliza'] ?? 'todos')));
    if ($estadoPoliza !== 'todos' && exp_poliza_estado_valido($estadoPoliza)) {
        $where[] = 'p.estado_poliza = :estado_poliza';
        $params[':estado_poliza'] = $estadoPoliza;
    }
    $estado = strtolower(trim((string) ($_GET['estado'] ?? 'todos')));
    if ($estado === 'activo') {
        $where[] = 'p.estado = 1';
    } elseif ($estado === 'inactivo') {
        $where[] = 'p.estado = 0';
    }
    $q = trim((string) ($_GET['q'] ?? ''));
    $q = function_exists('mb_substr') ? mb_substr($q, 0, 120, 'UTF-8') : substr($q, 0, 120);
    if ($q !== '') {
        $where[] = "(p.codigo LIKE :q ESCAPE '\\\\'
            OR p.numero_documento LIKE :q ESCAPE '\\\\'
            OR p.contratante_nombre_snapshot LIKE :q ESCAPE '\\\\'
            OR a.razon_social LIKE :q ESCAPE '\\\\'
            OR a.nombre_comercial LIKE :q ESCAPE '\\\\')";
        $params[':q'] = exp_bind_like($q);
    }

    $whereSql = ' WHERE ' . implode(' AND ', $where);
    $fromSql = ' FROM seg_polizas p
        INNER JOIN seg_aseguradoras a ON a.id = p.aseguradora_id';

    $stmtTotal = $pdo->prepare('SELECT COUNT(*)' . $fromSql . $whereSql);
    $stmtTotal->execute($params);
    $total = (int) $stmtTotal->fetchColumn();

    $stmt = $pdo->prepare("SELECT
            p.*,
            a.razon_social AS aseguradora_razon_social,
            a.nombre_comercial AS aseguradora_nombre_comercial,
            (
                SELECT v.id
                FROM seg_archivos_vinculos v
                INNER JOIN seg_archivos ar ON ar.id = v.archivo_id
                WHERE v.codigo_uso = 'poliza_documento_principal'
                  AND v.entidad_tipo = 'poliza'
                  AND v.entidad_id = p.id
                  AND v.slot = 'documento_principal'
                  AND v.estado = 1
                  AND ar.estado = 1
                ORDER BY v.id DESC
                LIMIT 1
            ) AS pdf_vinculo_id
        " . $fromSql . $whereSql . '
        ORDER BY p.fecha_emision DESC, p.id DESC
        LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['estado_poliza_nombre'] = exp_poliza_estado_label((string) $row['estado_poliza']);
        $row['tipo_documento_emitido_nombre'] = exp_poliza_documento_label((string) $row['tipo_documento_emitido']);
    }
    unset($row);

    exp_json_success([
        'rows' => $rows,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ],
    ]);
}

function pol_obtener(): void
{
    exp_require_method('GET');
    exp_require_perm('puede_ver');
    $id = (int) ($_GET['id'] ?? 0);
    $expedienteId = (int) ($_GET['expediente_id'] ?? 0);
    if ($id <= 0 || $expedienteId <= 0) {
        exp_json_error('Poliza no valida.', 422);
    }
    $record = pol_fetch(exp_db(), $id, $expedienteId);
    if (!$record) {
        exp_json_error('Poliza no encontrada.', 404);
    }
    $record['pdf'] = pol_pdf_activo(exp_db(), $id);
    exp_json_success(['record' => $record]);
}

function pol_crear(): void
{
    $payload = exp_require_change('puede_crear');
    $pdo = exp_db();
    $expedienteId = (int) ($payload['expediente_id'] ?? 0);
    $expediente = pol_require_expediente($pdo, $expedienteId, true);
    $data = pol_validate($pdo, $payload, $expediente, false);
    $pdfFilePresent = isset($_FILES['archivo_pdf'])
        && is_array($_FILES['archivo_pdf'])
        && (int) ($_FILES['archivo_pdf']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    if (in_array($data['estado_poliza'], ['emitida', 'vigente'], true) && !$pdfFilePresent) {
        exp_json_error('Para registrar una poliza emitida o vigente debes cargar el PDF principal.', 422, ['archivo_pdf' => 'PDF requerido.']);
    }
    if ($pdfFilePresent) {
        pol_validate_pdf_upload((array) $_FILES['archivo_pdf']);
    }

    $userId = exp_user_id();
    $now = exp_now();
    $stored = null;

    try {
        $pdo->beginTransaction();
        $codigo = pol_next_codigo($pdo, substr((string) $data['fecha_emision'], 0, 4));
        $stmt = $pdo->prepare('INSERT INTO seg_polizas
            (codigo, expediente_id, cliente_id, tipo_seguro_id, aseguradora_id, tipo_documento_emitido, numero_documento, contratante_nombre_snapshot, contratante_ruc_snapshot, beneficiario_nombre, fecha_emision, vigencia_inicio, vigencia_fin, vigencia_dias, suma_asegurada, moneda, prima_comercial, igv, prima_total, estado_poliza, observaciones, estado, creado_por_usuario_externo_id, actualizado_por_usuario_externo_id, creado_en, actualizado_en)
            VALUES
            (:codigo, :expediente_id, :cliente_id, :tipo_seguro_id, :aseguradora_id, :tipo_documento_emitido, :numero_documento, :contratante_nombre_snapshot, :contratante_ruc_snapshot, :beneficiario_nombre, :fecha_emision, :vigencia_inicio, :vigencia_fin, :vigencia_dias, :suma_asegurada, :moneda, :prima_comercial, :igv, :prima_total, :estado_poliza, :observaciones, :estado, :creado_por, :actualizado_por, :creado_en, :actualizado_en)');
        $stmt->execute(pol_params($data, [
            ':codigo' => $codigo,
            ':creado_por' => $userId,
            ':actualizado_por' => $userId,
            ':creado_en' => $now,
            ':actualizado_en' => $now,
        ]));
        $id = (int) $pdo->lastInsertId();
        if ($pdfFilePresent) {
            $uploadErrors = [];
            $stored = pol_guardar_pdf($pdo, $id, $codigo, $userId, $uploadErrors);
            if (!$stored) {
                $pdo->rollBack();
                exp_json_error('No se pudo guardar el PDF principal.', 422, $uploadErrors);
            }
        }
        exp_timeline_add($pdo, 'expediente', $expedienteId, 'poliza_registrada', 'Poliza registrada: ' . $codigo, [
            'poliza_id' => $id,
            'codigo' => $codigo,
            'estado_poliza' => $data['estado_poliza'],
        ], $userId, $now);
        if ($stored) {
            exp_timeline_add($pdo, 'expediente', $expedienteId, 'poliza_documento_principal_cargado', 'PDF principal cargado para poliza: ' . $codigo, [
                'poliza_id' => $id,
                'archivo_id' => (int) $stored['id'],
                'vinculo_id' => (int) (($stored['vinculo']['id'] ?? 0)),
            ], $userId, $now);
        }
        $pdo->commit();
        exp_json_success(['id' => $id, 'codigo' => $codigo], 'Poliza registrada correctamente.');
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

function pol_actualizar(): void
{
    $payload = exp_require_change('puede_editar');
    $pdo = exp_db();
    $expedienteId = (int) ($payload['expediente_id'] ?? 0);
    $id = (int) ($payload['id'] ?? 0);
    $expediente = pol_require_expediente($pdo, $expedienteId, false);
    $record = pol_fetch($pdo, $id, $expedienteId);
    if (!$record) {
        exp_json_error('Poliza no encontrada.', 404);
    }
    $data = pol_validate($pdo, $payload, $expediente, true);
    if (in_array($data['estado_poliza'], ['emitida', 'vigente'], true) && !pol_pdf_activo($pdo, $id)) {
        exp_json_error('Para dejar la poliza como emitida o vigente debe tener PDF principal activo.', 409, ['estado_poliza' => 'PDF principal requerido.']);
    }

    $changes = pol_detect_changes($record, $data);
    if ($changes === []) {
        exp_json_success(['id' => $id], 'No hubo cambios para guardar.');
    }

    $userId = exp_user_id();
    $now = exp_now();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('UPDATE seg_polizas SET
                aseguradora_id = :aseguradora_id,
                tipo_documento_emitido = :tipo_documento_emitido,
                numero_documento = :numero_documento,
                beneficiario_nombre = :beneficiario_nombre,
                fecha_emision = :fecha_emision,
                vigencia_inicio = :vigencia_inicio,
                vigencia_fin = :vigencia_fin,
                vigencia_dias = :vigencia_dias,
                suma_asegurada = :suma_asegurada,
                moneda = :moneda,
                prima_comercial = :prima_comercial,
                igv = :igv,
                prima_total = :prima_total,
                estado_poliza = :estado_poliza,
                observaciones = :observaciones,
                estado = :estado,
                actualizado_por_usuario_externo_id = :actualizado_por,
                actualizado_en = :actualizado_en
            WHERE id = :id AND expediente_id = :expediente_id');
        $stmt->execute(pol_params($data, [
            ':id' => $id,
            ':expediente_id' => $expedienteId,
            ':actualizado_por' => $userId,
            ':actualizado_en' => $now,
        ]));
        exp_timeline_add($pdo, 'expediente', $expedienteId, 'poliza_editada', 'Poliza editada: ' . (string) $record['codigo'], [
            'poliza_id' => $id,
            'codigo' => (string) $record['codigo'],
            'campos_cambiados' => $changes,
        ], $userId, $now);
        if (isset($changes['estado_poliza'])) {
            exp_timeline_add($pdo, 'expediente', $expedienteId, 'poliza_estado_modificado', 'Estado de poliza modificado: ' . (string) $record['codigo'], [
                'poliza_id' => $id,
                'codigo' => (string) $record['codigo'],
                'estado_anterior' => (string) $record['estado_poliza'],
                'estado_anterior_nombre' => exp_poliza_estado_label((string) $record['estado_poliza']),
                'estado_nuevo' => $data['estado_poliza'],
                'estado_nuevo_nombre' => exp_poliza_estado_label($data['estado_poliza']),
            ], $userId, $now);
        }
        $pdo->commit();
        exp_json_success(['id' => $id], 'Poliza actualizada correctamente.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function pol_cambiar_estado(): void
{
    $payload = exp_require_change('puede_eliminar');
    $id = (int) ($payload['id'] ?? 0);
    $expedienteId = (int) ($payload['expediente_id'] ?? 0);
    $pdo = exp_db();
    $record = pol_fetch($pdo, $id, $expedienteId);
    if (!$record) {
        exp_json_error('Poliza no encontrada.', 404);
    }

    $nuevoEstado = (int) $record['estado'] === 1 ? 0 : 1;
    $userId = exp_user_id();
    $now = exp_now();
    try {
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE seg_polizas
            SET estado = :estado,
                actualizado_por_usuario_externo_id = :usuario,
                actualizado_en = :fecha
            WHERE id = :id AND expediente_id = :expediente_id')
            ->execute([':estado' => $nuevoEstado, ':usuario' => $userId, ':fecha' => $now, ':id' => $id, ':expediente_id' => $expedienteId]);
        exp_timeline_add($pdo, 'expediente', $expedienteId, $nuevoEstado === 1 ? 'poliza_activada' : 'poliza_desactivada', $nuevoEstado === 1 ? 'Poliza activada.' : 'Poliza desactivada.', [
            'poliza_id' => $id,
            'codigo' => (string) $record['codigo'],
            'estado_anterior' => (int) $record['estado'],
            'estado_nuevo' => $nuevoEstado,
        ], $userId, $now);
        $pdo->commit();
        exp_json_success(['id' => $id, 'estado' => $nuevoEstado], $nuevoEstado === 1 ? 'Poliza activada.' : 'Poliza desactivada.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function pol_cargar_pdf(): void
{
    $payload = exp_require_change('puede_editar');
    $id = (int) ($payload['id'] ?? 0);
    $expedienteId = (int) ($payload['expediente_id'] ?? 0);
    if (!isset($_FILES['archivo_pdf']) || !is_array($_FILES['archivo_pdf'])) {
        exp_json_error('Seleccione el PDF principal.', 422, ['archivo_pdf' => 'PDF requerido.']);
    }
    pol_validate_pdf_upload((array) $_FILES['archivo_pdf']);
    $pdo = exp_db();
    $record = pol_fetch($pdo, $id, $expedienteId);
    if (!$record) {
        exp_json_error('Poliza no encontrada.', 404);
    }
    $userId = exp_user_id();
    $now = exp_now();
    $stored = null;
    try {
        $pdo->beginTransaction();
        pol_archivar_pdf_activo($pdo, $id, $userId, $now);
        $uploadErrors = [];
        $stored = pol_guardar_pdf($pdo, $id, (string) $record['codigo'], $userId, $uploadErrors);
        if (!$stored) {
            $pdo->rollBack();
            exp_json_error('No se pudo guardar el PDF principal.', 422, $uploadErrors);
        }
        exp_timeline_add($pdo, 'expediente', $expedienteId, 'poliza_documento_principal_cargado', 'PDF principal cargado para poliza: ' . (string) $record['codigo'], [
            'poliza_id' => $id,
            'codigo' => (string) $record['codigo'],
            'archivo_id' => (int) $stored['id'],
            'vinculo_id' => (int) (($stored['vinculo']['id'] ?? 0)),
        ], $userId, $now);
        $pdo->commit();
        exp_json_success(['id' => $id, 'archivo_id' => (int) $stored['id']], 'PDF principal cargado correctamente.');
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

function pol_archivar_pdf(): void
{
    $payload = exp_require_change('puede_editar');
    $vinculoId = (int) ($payload['vinculo_id'] ?? 0);
    $expedienteId = (int) ($payload['expediente_id'] ?? 0);
    $pdo = exp_db();
    $doc = pol_fetch_pdf_vinculo($pdo, $vinculoId, $expedienteId, false);
    if (!$doc) {
        exp_json_error('PDF no encontrado.', 404);
    }
    if ((int) $doc['vinculo_estado'] !== 1) {
        exp_json_success(['vinculo_id' => $vinculoId], 'El PDF ya estaba archivado.');
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
        exp_timeline_add($pdo, 'expediente', $expedienteId, 'poliza_documento_principal_archivado', 'PDF principal archivado para poliza: ' . (string) $doc['codigo'], [
            'poliza_id' => (int) $doc['poliza_id'],
            'codigo' => (string) $doc['codigo'],
            'archivo_id' => (int) $doc['archivo_id'],
            'vinculo_id' => $vinculoId,
        ], $userId, $now);
        $pdo->commit();
        exp_json_success(['vinculo_id' => $vinculoId], 'PDF archivado correctamente.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function pol_descargar_pdf(): void
{
    exp_require_method('GET');
    exp_require_perm('puede_ver');
    $vinculoId = (int) ($_GET['vinculo_id'] ?? 0);
    $expedienteId = (int) ($_GET['expediente_id'] ?? 0);
    if ($vinculoId <= 0 || $expedienteId <= 0) {
        http_response_code(422);
        echo 'PDF no valido.';
        exit;
    }
    $pdo = exp_db();
    $doc = pol_fetch_pdf_vinculo($pdo, $vinculoId, $expedienteId, true);
    if (!$doc) {
        http_response_code(404);
        echo 'PDF no encontrado.';
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

function pol_require_expediente(PDO $pdo, int $expedienteId, bool $mustBeActive): array
{
    if ($expedienteId <= 0) {
        exp_json_error('Expediente no valido.', 422);
    }
    $record = exp_fetch($pdo, $expedienteId);
    if (!$record) {
        exp_json_error('Expediente no encontrado.', 404);
    }
    if ($mustBeActive && (int) $record['estado'] !== 1) {
        exp_json_error('No se pueden crear polizas en expedientes inactivos.', 409);
    }
    return $record;
}

function pol_validate(PDO $pdo, array $payload, array $expediente, bool $isUpdate): array
{
    $errors = [];
    $id = isset($payload['id']) && is_numeric($payload['id']) ? (int) $payload['id'] : 0;
    if ($isUpdate && $id <= 0) {
        $errors['id'] = 'Poliza no valida.';
    }

    $aseguradoraId = isset($payload['aseguradora_id']) && is_numeric($payload['aseguradora_id']) ? (int) $payload['aseguradora_id'] : 0;
    $tipoDoc = strtolower(trim((string) ($payload['tipo_documento_emitido'] ?? '')));
    $numero = exp_str($payload, 'numero_documento', 80, true);
    $beneficiario = exp_str($payload, 'beneficiario_nombre', 180, true);
    $fechaEmision = pol_date($payload, 'fecha_emision');
    $vigenciaInicio = pol_datetime($payload, 'vigencia_inicio');
    $vigenciaFin = pol_datetime($payload, 'vigencia_fin');
    $moneda = strtoupper(trim((string) ($payload['moneda'] ?? 'PEN')));
    $estadoPoliza = strtolower(trim((string) ($payload['estado_poliza'] ?? 'borrador')));
    $observaciones = exp_str($payload, 'observaciones', 3000, true);
    $estado = exp_estado_value($payload);

    if ($aseguradoraId <= 0) {
        $errors['aseguradora_id'] = 'Seleccione una aseguradora activa.';
    }
    if (!exp_poliza_documento_valido($tipoDoc)) {
        $errors['tipo_documento_emitido'] = 'Seleccione un tipo de documento valido.';
    }
    if (!exp_poliza_estado_valido($estadoPoliza)) {
        $errors['estado_poliza'] = 'Seleccione un estado valido.';
    }
    if (in_array($estadoPoliza, ['emitida', 'vigente'], true) && $numero === null) {
        $errors['numero_documento'] = 'Ingrese el numero de documento para polizas emitidas o vigentes.';
    }
    if (!in_array($moneda, ['PEN', 'USD', 'EUR', 'OTRA'], true)) {
        $errors['moneda'] = 'Use PEN, USD, EUR u OTRA.';
    }
    if ($vigenciaFin <= $vigenciaInicio) {
        $errors['vigencia_fin'] = 'La fecha de fin debe ser posterior a la fecha de inicio.';
    }

    $suma = pol_decimal($payload, 'suma_asegurada', $errors);
    $primaComercial = pol_decimal($payload, 'prima_comercial', $errors);
    $igv = pol_decimal($payload, 'igv', $errors);
    $primaTotal = pol_decimal($payload, 'prima_total', $errors);

    if ($errors) {
        exp_json_error('Revisa los campos marcados.', 422, $errors);
    }
    pol_require_active_aseguradora($pdo, $aseguradoraId);

    return [
        'id' => $id,
        'expediente_id' => (int) $expediente['id'],
        'cliente_id' => (int) $expediente['cliente_id'],
        'tipo_seguro_id' => (int) $expediente['tipo_seguro_id'],
        'aseguradora_id' => $aseguradoraId,
        'tipo_documento_emitido' => $tipoDoc,
        'numero_documento' => $numero,
        'contratante_nombre_snapshot' => (string) $expediente['cliente_razon_social'],
        'contratante_ruc_snapshot' => $expediente['cliente_ruc'] ?: null,
        'beneficiario_nombre' => $beneficiario,
        'fecha_emision' => $fechaEmision,
        'vigencia_inicio' => $vigenciaInicio->format('Y-m-d H:i:s'),
        'vigencia_fin' => $vigenciaFin->format('Y-m-d H:i:s'),
        'vigencia_dias' => max(1, (int) ceil(($vigenciaFin->getTimestamp() - $vigenciaInicio->getTimestamp()) / 86400)),
        'suma_asegurada' => $suma,
        'moneda' => $moneda,
        'prima_comercial' => $primaComercial,
        'igv' => $igv,
        'prima_total' => $primaTotal,
        'estado_poliza' => $estadoPoliza,
        'observaciones' => $observaciones,
        'estado' => $estado,
    ];
}

function pol_date(array $payload, string $key): string
{
    $value = trim((string) ($payload[$key] ?? ''));
    if ($value === '') {
        exp_json_error('Revisa los campos marcados.', 422, [$key => 'Fecha requerida.']);
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $value, new DateTimeZone('America/Lima'));
    if (!$dt || $dt->format('Y-m-d') !== $value) {
        exp_json_error('Revisa los campos marcados.', 422, [$key => 'Fecha no valida.']);
    }
    return $value;
}

function pol_datetime(array $payload, string $key): DateTimeImmutable
{
    $value = trim((string) ($payload[$key] ?? ''));
    if ($value === '') {
        exp_json_error('Revisa los campos marcados.', 422, [$key => 'Fecha y hora requerida.']);
    }
    $value = str_replace('T', ' ', $value);
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value) === 1) {
        $value .= ':00';
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, new DateTimeZone('America/Lima'));
    if (!$dt || $dt->format('Y-m-d H:i:s') !== $value) {
        exp_json_error('Revisa los campos marcados.', 422, [$key => 'Fecha y hora no valida.']);
    }
    return $dt;
}

function pol_decimal(array $payload, string $key, array &$errors): ?string
{
    $raw = trim((string) ($payload[$key] ?? ''));
    if ($raw === '') {
        return null;
    }
    $raw = str_replace(',', '.', $raw);
    if (!is_numeric($raw) || (float) $raw < 0) {
        $errors[$key] = 'Ingrese un monto no negativo.';
        return null;
    }
    return number_format((float) $raw, 2, '.', '');
}

function pol_require_active_aseguradora(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare('SELECT id FROM seg_aseguradoras WHERE id = :id AND estado = 1 LIMIT 1');
    $stmt->execute([':id' => $id]);
    if (!$stmt->fetchColumn()) {
        exp_json_error('Solo se pueden usar aseguradoras activas.', 422, ['aseguradora_id' => 'Aseguradora no disponible.']);
    }
}

function pol_params(array $data, array $extra): array
{
    return $extra + [
        ':expediente_id' => $data['expediente_id'],
        ':cliente_id' => $data['cliente_id'],
        ':tipo_seguro_id' => $data['tipo_seguro_id'],
        ':aseguradora_id' => $data['aseguradora_id'],
        ':tipo_documento_emitido' => $data['tipo_documento_emitido'],
        ':numero_documento' => $data['numero_documento'],
        ':contratante_nombre_snapshot' => $data['contratante_nombre_snapshot'],
        ':contratante_ruc_snapshot' => $data['contratante_ruc_snapshot'],
        ':beneficiario_nombre' => $data['beneficiario_nombre'],
        ':fecha_emision' => $data['fecha_emision'],
        ':vigencia_inicio' => $data['vigencia_inicio'],
        ':vigencia_fin' => $data['vigencia_fin'],
        ':vigencia_dias' => $data['vigencia_dias'],
        ':suma_asegurada' => $data['suma_asegurada'],
        ':moneda' => $data['moneda'],
        ':prima_comercial' => $data['prima_comercial'],
        ':igv' => $data['igv'],
        ':prima_total' => $data['prima_total'],
        ':estado_poliza' => $data['estado_poliza'],
        ':observaciones' => $data['observaciones'],
        ':estado' => $data['estado'],
    ];
}

function pol_next_codigo(PDO $pdo, string $year): string
{
    $prefix = 'POL-' . $year . '-';
    $stmt = $pdo->prepare("SELECT codigo FROM seg_polizas WHERE codigo LIKE :prefix ORDER BY codigo DESC LIMIT 1 FOR UPDATE");
    $stmt->execute([':prefix' => $prefix . '%']);
    $last = (string) ($stmt->fetchColumn() ?: '');
    $next = 1;
    if (preg_match('/^POL-' . preg_quote($year, '/') . '-([0-9]{6})$/', $last, $m) === 1) {
        $next = (int) $m[1] + 1;
    }
    return $prefix . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
}

function pol_fetch(PDO $pdo, int $id, int $expedienteId): ?array
{
    if ($id <= 0 || $expedienteId <= 0) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT
            p.*,
            a.razon_social AS aseguradora_razon_social,
            a.nombre_comercial AS aseguradora_nombre_comercial
        FROM seg_polizas p
        INNER JOIN seg_aseguradoras a ON a.id = p.aseguradora_id
        WHERE p.id = :id AND p.expediente_id = :expediente_id
        LIMIT 1');
    $stmt->execute([':id' => $id, ':expediente_id' => $expedienteId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (is_array($row)) {
        $row['estado_poliza_nombre'] = exp_poliza_estado_label((string) $row['estado_poliza']);
        $row['tipo_documento_emitido_nombre'] = exp_poliza_documento_label((string) $row['tipo_documento_emitido']);
        return $row;
    }
    return null;
}

function pol_pdf_activo(PDO $pdo, int $polizaId): ?array
{
    $stmt = $pdo->prepare("SELECT
            v.id AS vinculo_id,
            v.archivo_id,
            v.estado AS vinculo_estado,
            a.nombre_original,
            a.mime_type,
            a.tamanio_bytes,
            a.estado AS archivo_estado
        FROM seg_archivos_vinculos v
        INNER JOIN seg_archivos a ON a.id = v.archivo_id
        WHERE v.codigo_uso = 'poliza_documento_principal'
          AND v.entidad_tipo = 'poliza'
          AND v.entidad_id = :id
          AND v.slot = 'documento_principal'
          AND v.estado = 1
          AND a.estado = 1
        ORDER BY v.id DESC
        LIMIT 1");
    $stmt->execute([':id' => $polizaId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function pol_archivar_pdf_activo(PDO $pdo, int $polizaId, ?int $userId, string $now): void
{
    $pdo->prepare("UPDATE seg_archivos_vinculos
        SET estado = 0,
            actualizado_por_usuario_externo_id = :usuario,
            actualizado_en = :fecha
        WHERE codigo_uso = 'poliza_documento_principal'
          AND entidad_tipo = 'poliza'
          AND entidad_id = :id
          AND slot = 'documento_principal'
          AND estado = 1")
        ->execute([':usuario' => $userId, ':fecha' => $now, ':id' => $polizaId]);
}

function pol_guardar_pdf(PDO $pdo, int $polizaId, string $codigo, ?int $userId, array &$errors = []): ?array
{
    $errors = [];
    $stored = cb_almacen_guardar_upload($pdo, (array) $_FILES['archivo_pdf'], [
        'carpeta' => 'polizas/documentos',
        'usuario_id' => $userId,
        'descripcion' => 'PDF principal de poliza: ' . $codigo,
        'vinculo' => [
            'codigo_uso' => 'poliza_documento_principal',
            'entidad_tipo' => 'poliza',
            'entidad_id' => $polizaId,
            'slot' => 'documento_principal',
            'metadata' => [
                'poliza_id' => $polizaId,
                'codigo' => $codigo,
            ],
        ],
    ], $errors);
    if (!$stored) {
        return null;
    }
    $extension = strtolower((string) ($stored['extension'] ?? ''));
    $mime = strtolower((string) ($stored['mime_type'] ?? ''));
    if ($extension !== 'pdf' || $mime !== 'application/pdf') {
        if (!empty($stored['absolute_path']) && is_file((string) $stored['absolute_path'])) {
            @unlink((string) $stored['absolute_path']);
        }
        $errors['archivo_pdf'] = 'Solo PDF.';
        return null;
    }
    return $stored;
}

function pol_validate_pdf_upload(array $fileInfo): void
{
    $error = (int) ($fileInfo['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        exp_json_error('Seleccione el PDF principal.', 422, ['archivo_pdf' => 'PDF requerido.']);
    }
    $name = basename((string) ($fileInfo['name'] ?? ''));
    $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
    $tmp = (string) ($fileInfo['tmp_name'] ?? '');
    $mime = cb_almacen_detect_mime($tmp);
    if ($extension !== 'pdf' || strtolower($mime) !== 'application/pdf') {
        exp_json_error('El documento principal de poliza debe ser PDF.', 422, ['archivo_pdf' => 'Solo PDF.']);
    }
}

function pol_fetch_pdf_vinculo(PDO $pdo, int $vinculoId, int $expedienteId, bool $soloActivo): ?array
{
    $sql = "SELECT
            v.id AS vinculo_id,
            v.archivo_id,
            v.estado AS vinculo_estado,
            a.estado AS archivo_estado,
            p.id AS poliza_id,
            p.codigo,
            p.expediente_id
        FROM seg_archivos_vinculos v
        INNER JOIN seg_archivos a ON a.id = v.archivo_id
        INNER JOIN seg_polizas p ON p.id = v.entidad_id
        WHERE v.id = :id
          AND v.codigo_uso = 'poliza_documento_principal'
          AND v.entidad_tipo = 'poliza'
          AND v.slot = 'documento_principal'
          AND p.expediente_id = :expediente_id";
    if ($soloActivo) {
        $sql .= ' AND v.estado = 1 AND a.estado = 1';
    }
    $sql .= ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $vinculoId, ':expediente_id' => $expedienteId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function pol_detect_changes(array $record, array $data): array
{
    $fields = [
        'aseguradora_id' => 'int',
        'tipo_documento_emitido' => 'string',
        'numero_documento' => 'string',
        'beneficiario_nombre' => 'string',
        'fecha_emision' => 'string',
        'vigencia_inicio' => 'string',
        'vigencia_fin' => 'string',
        'vigencia_dias' => 'int',
        'suma_asegurada' => 'decimal',
        'moneda' => 'string',
        'prima_comercial' => 'decimal',
        'igv' => 'decimal',
        'prima_total' => 'decimal',
        'estado_poliza' => 'string',
        'observaciones' => 'string',
        'estado' => 'int',
    ];

    $changes = [];
    foreach ($fields as $field => $type) {
        $old = pol_compare_value($record[$field] ?? null, $type);
        $new = pol_compare_value($data[$field] ?? null, $type);
        if ($old === $new) {
            continue;
        }
        $changes[$field] = ['anterior' => $old, 'nuevo' => $new];
    }
    return $changes;
}

function pol_compare_value($value, string $type)
{
    if ($type === 'int') {
        return (int) $value;
    }
    if ($type === 'decimal') {
        return $value === null || $value === '' ? null : number_format((float) $value, 2, '.', '');
    }
    $value = trim((string) ($value ?? ''));
    return $value === '' ? null : $value;
}
