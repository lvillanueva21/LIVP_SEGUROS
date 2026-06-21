<?php
declare(strict_types=1);

require_once __DIR__ . '/_expedientes_common.php';

$accion = strtolower(trim((string) ($_GET['accion'] ?? $_POST['accion'] ?? 'listar')));

try {
    switch ($accion) {
        case 'contexto':
            cot_contexto();
            break;
        case 'listar':
            cot_listar();
            break;
        case 'obtener':
            cot_obtener();
            break;
        case 'crear':
            cot_crear();
            break;
        case 'actualizar':
            cot_actualizar();
            break;
        case 'cambiar_estado':
            cot_cambiar_estado();
            break;
        default:
            exp_json_error('Accion no valida.', 404);
    }
} catch (PDOException $e) {
    exp_db_error($e);
} catch (Throwable $e) {
    exp_db_error($e);
}

function cot_estados(): array
{
    return [
        'borrador' => 'Borrador',
        'enviada' => 'Enviada',
        'aceptada' => 'Aceptada',
        'vencida' => 'Vencida',
        'perdida' => 'Perdida',
        'cancelada' => 'Cancelada',
    ];
}

function cot_estado_label(string $codigo): string
{
    $estados = cot_estados();
    return $estados[$codigo] ?? $codigo;
}

function cot_estado_valido(string $codigo): bool
{
    return isset(cot_estados()[$codigo]);
}

function cot_cuota_modalidades(): array
{
    return [
        'afiliacion' => 'Afiliacion',
        'cupon' => 'Cupon',
        'contado' => 'Contado',
        'otro' => 'Otro',
    ];
}

function cot_gps_options(): array
{
    return [
        'no_requiere' => 'No requiere',
        'requerido' => 'Requerido',
        'opcional' => 'Opcional',
        'pendiente' => 'Pendiente',
    ];
}

function cot_comparativo_secciones(): array
{
    return [
        'cobertura' => 'Cobertura',
        'servicio' => 'Servicio',
        'deducible' => 'Deducible',
        'condicion' => 'Condicion',
        'otro' => 'Otro',
    ];
}

function cot_contexto(): void
{
    exp_require_method('GET');
    exp_require_perm('puede_ver');
    $expedienteId = (int) ($_GET['expediente_id'] ?? 0);
    $pdo = exp_db();
    $expediente = cot_require_expediente($pdo, $expedienteId);
    $ramoId = (int) ($expediente['ramo_id'] ?? 0);

    $aseguradoras = $pdo->query("SELECT id, codigo, razon_social, nombre_comercial
        FROM seg_aseguradoras
        WHERE estado = 1
        ORDER BY nombre_comercial ASC, razon_social ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

    $stmtProd = $pdo->prepare('SELECT id, aseguradora_id, ramo_id, codigo, nombre_producto, nombre_plan
        FROM seg_productos
        WHERE estado = 1
          AND ramo_id = :ramo_id
        ORDER BY aseguradora_id ASC, nombre_producto ASC, nombre_plan ASC, id ASC');
    $stmtProd->execute([':ramo_id' => $ramoId]);

    $estados = [];
    foreach (cot_estados() as $codigo => $nombre) {
        $estados[] = ['codigo' => $codigo, 'nombre' => $nombre];
    }
    $gps = [];
    foreach (cot_gps_options() as $codigo => $nombre) {
        $gps[] = ['codigo' => $codigo, 'nombre' => $nombre];
    }
    $modalidades = [];
    foreach (cot_cuota_modalidades() as $codigo => $nombre) {
        $modalidades[] = ['codigo' => $codigo, 'nombre' => $nombre];
    }
    $secciones = [];
    foreach (cot_comparativo_secciones() as $codigo => $nombre) {
        $secciones[] = ['codigo' => $codigo, 'nombre' => $nombre];
    }

    exp_json_success([
        'expediente' => $expediente,
        'aseguradoras' => $aseguradoras,
        'productos' => $stmtProd->fetchAll(PDO::FETCH_ASSOC),
        'estados' => $estados,
        'gps' => $gps,
        'modalidades' => $modalidades,
        'secciones' => $secciones,
        'csrf' => cb_local_csrf_token('expedientes'),
    ]);
}

function cot_listar(): void
{
    exp_require_method('GET');
    exp_require_perm('puede_ver');
    $pdo = exp_db();
    $expedienteId = (int) ($_GET['expediente_id'] ?? 0);
    cot_require_expediente($pdo, $expedienteId);
    [$page, $perPage, $offset] = exp_page_params();

    $where = ['c.expediente_id = :expediente_id'];
    $params = [':expediente_id' => $expedienteId];
    $estadoCot = strtolower(trim((string) ($_GET['estado_cotizacion'] ?? 'todos')));
    if ($estadoCot !== 'todos' && cot_estado_valido($estadoCot)) {
        $where[] = 'c.estado_cotizacion = :estado_cotizacion';
        $params[':estado_cotizacion'] = $estadoCot;
    }
    $estado = strtolower(trim((string) ($_GET['estado'] ?? 'todos')));
    if ($estado === 'activo') {
        $where[] = 'c.estado = 1';
    } elseif ($estado === 'inactivo') {
        $where[] = 'c.estado = 0';
    }
    $q = trim((string) ($_GET['q'] ?? ''));
    $q = function_exists('mb_substr') ? mb_substr($q, 0, 120, 'UTF-8') : substr($q, 0, 120);
    if ($q !== '') {
        $where[] = "(c.codigo LIKE :q ESCAPE '\\\\'
            OR c.titulo LIKE :q ESCAPE '\\\\'
            OR c.descripcion LIKE :q ESCAPE '\\\\'
            OR cli.razon_social LIKE :q ESCAPE '\\\\'
            OR a.razon_social LIKE :q ESCAPE '\\\\'
            OR a.nombre_comercial LIKE :q ESCAPE '\\\\')";
        $params[':q'] = exp_bind_like($q);
    }
    $whereSql = ' WHERE ' . implode(' AND ', $where);
    $fromSql = " FROM seg_cotizaciones c
        INNER JOIN seg_expedientes e ON e.id = c.expediente_id
        INNER JOIN seg_clientes cli ON cli.id = e.cliente_id
        LEFT JOIN seg_cotizacion_alternativas alt ON alt.cotizacion_id = c.id AND alt.estado = 1 AND alt.es_aceptada = 1
        LEFT JOIN seg_aseguradoras a ON a.id = alt.aseguradora_id";

    $stmtTotal = $pdo->prepare('SELECT COUNT(DISTINCT c.id)' . $fromSql . $whereSql);
    $stmtTotal->execute($params);
    $total = (int) $stmtTotal->fetchColumn();

    $stmt = $pdo->prepare("SELECT
            c.id,
            c.codigo,
            c.fecha_cotizacion,
            c.fecha_vencimiento,
            c.titulo,
            c.estado_cotizacion,
            c.descripcion,
            c.estado,
            cli.razon_social AS cliente_razon_social,
            a.nombre_comercial AS aseguradora_aceptada_nombre,
            a.razon_social AS aseguradora_aceptada_razon_social,
            COUNT(DISTINCT alt_all.id) AS total_alternativas
        " . $fromSql . "
        LEFT JOIN seg_cotizacion_alternativas alt_all ON alt_all.cotizacion_id = c.id AND alt_all.estado = 1
        " . $whereSql . '
        GROUP BY c.id, c.codigo, c.fecha_cotizacion, c.fecha_vencimiento, c.titulo, c.estado_cotizacion, c.descripcion, c.estado, cli.razon_social, a.nombre_comercial, a.razon_social
        ORDER BY c.fecha_cotizacion DESC, c.id DESC
        LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['estado_cotizacion_nombre'] = cot_estado_label((string) $row['estado_cotizacion']);
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

function cot_obtener(): void
{
    exp_require_method('GET');
    exp_require_perm('puede_ver');
    $pdo = exp_db();
    $id = (int) ($_GET['id'] ?? 0);
    $expedienteId = (int) ($_GET['expediente_id'] ?? 0);
    $record = cot_fetch($pdo, $id, $expedienteId);
    if (!$record) {
        exp_json_error('Cotizacion no encontrada.', 404);
    }
    $record['riesgos'] = cot_fetch_riesgos($pdo, $id);
    $record['alternativas'] = cot_fetch_alternativas($pdo, $id);
    $record['comparativos'] = cot_fetch_comparativos($pdo, $id);
    $record['expediente'] = cot_require_expediente($pdo, $expedienteId);
    exp_json_success(['record' => $record]);
}

function cot_crear(): void
{
    $payload = exp_require_change('puede_crear');
    $pdo = exp_db();
    $expedienteId = (int) ($payload['expediente_id'] ?? 0);
    $expediente = cot_require_expediente($pdo, $expedienteId);
    $data = cot_validate($pdo, $payload, $expediente, false);
    $children = cot_parse_children($payload);
    cot_validate_children($pdo, $children, (int) $expediente['ramo_id'], (string) $data['estado_cotizacion']);

    $userId = exp_user_id();
    $now = exp_now();
    try {
        $pdo->beginTransaction();
        $codigo = cot_next_codigo($pdo, substr($data['fecha_cotizacion'], 0, 4));
        $stmt = $pdo->prepare('INSERT INTO seg_cotizaciones
            (codigo, expediente_id, fecha_cotizacion, fecha_vencimiento, titulo, estado_cotizacion, descripcion, observaciones, nota_pdf, estado, creado_por_usuario_externo_id, actualizado_por_usuario_externo_id, creado_en, actualizado_en)
            VALUES
            (:codigo, :expediente_id, :fecha_cotizacion, :fecha_vencimiento, :titulo, :estado_cotizacion, :descripcion, :observaciones, :nota_pdf, :estado, :creado_por, :actualizado_por, :creado_en, :actualizado_en)');
        $stmt->execute([
            ':codigo' => $codigo,
            ':expediente_id' => $data['expediente_id'],
            ':fecha_cotizacion' => $data['fecha_cotizacion'],
            ':fecha_vencimiento' => $data['fecha_vencimiento'],
            ':titulo' => $data['titulo'],
            ':estado_cotizacion' => $data['estado_cotizacion'],
            ':descripcion' => $data['descripcion'],
            ':observaciones' => $data['observaciones'],
            ':nota_pdf' => $data['nota_pdf'],
            ':estado' => $data['estado'],
            ':creado_por' => $userId,
            ':actualizado_por' => $userId,
            ':creado_en' => $now,
            ':actualizado_en' => $now,
        ]);
        $id = (int) $pdo->lastInsertId();
        cot_insert_children($pdo, $id, $children, $userId, $now, $expedienteId);
        exp_timeline_add($pdo, 'expediente', $expedienteId, 'cotizacion_creada', 'Cotizacion creada: ' . $codigo, [
            'cotizacion_id' => $id,
            'codigo' => $codigo,
            'estado_cotizacion' => $data['estado_cotizacion'],
        ], $userId, $now);
        $pdo->commit();
        exp_json_success(['id' => $id, 'codigo' => $codigo], 'Cotizacion registrada correctamente.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function cot_actualizar(): void
{
    $payload = exp_require_change('puede_editar');
    $pdo = exp_db();
    $id = (int) ($payload['id'] ?? 0);
    $expedienteId = (int) ($payload['expediente_id'] ?? 0);
    $record = cot_fetch($pdo, $id, $expedienteId);
    if (!$record) {
        exp_json_error('Cotizacion no encontrada.', 404);
    }
    $expediente = cot_require_expediente($pdo, $expedienteId);
    $data = cot_validate($pdo, $payload, $expediente, true);
    $children = cot_parse_children($payload);
    cot_validate_children($pdo, $children, (int) $expediente['ramo_id'], (string) $data['estado_cotizacion']);
    $changes = cot_detect_changes($record, $data);
    $childrenChanged = cot_children_signature_existing($pdo, $id) !== cot_children_signature_new($children);
    if ($changes === [] && !$childrenChanged) {
        exp_json_success(['id' => $id], 'No hubo cambios para guardar.');
    }

    $userId = exp_user_id();
    $now = exp_now();
    try {
        $pdo->beginTransaction();
        if ($changes !== []) {
            $stmt = $pdo->prepare('UPDATE seg_cotizaciones SET
                    fecha_cotizacion = :fecha_cotizacion,
                    fecha_vencimiento = :fecha_vencimiento,
                    titulo = :titulo,
                    estado_cotizacion = :estado_cotizacion,
                    descripcion = :descripcion,
                    observaciones = :observaciones,
                    nota_pdf = :nota_pdf,
                    estado = :estado,
                    actualizado_por_usuario_externo_id = :actualizado_por,
                    actualizado_en = :actualizado_en
                WHERE id = :id AND expediente_id = :expediente_id');
            $stmt->execute([
                ':id' => $id,
                ':expediente_id' => $expedienteId,
                ':fecha_cotizacion' => $data['fecha_cotizacion'],
                ':fecha_vencimiento' => $data['fecha_vencimiento'],
                ':titulo' => $data['titulo'],
                ':estado_cotizacion' => $data['estado_cotizacion'],
                ':descripcion' => $data['descripcion'],
                ':observaciones' => $data['observaciones'],
                ':nota_pdf' => $data['nota_pdf'],
                ':estado' => $data['estado'],
                ':actualizado_por' => $userId,
                ':actualizado_en' => $now,
            ]);
            exp_timeline_add($pdo, 'expediente', $expedienteId, 'cotizacion_editada', 'Cotizacion editada: ' . (string) $record['codigo'], [
                'cotizacion_id' => $id,
                'codigo' => (string) $record['codigo'],
                'campos_cambiados' => $changes,
            ], $userId, $now);
            if (isset($changes['estado_cotizacion'])) {
                exp_timeline_add($pdo, 'expediente', $expedienteId, 'cotizacion_estado_modificado', 'Estado de cotizacion modificado: ' . (string) $record['codigo'], [
                    'cotizacion_id' => $id,
                    'estado_anterior' => (string) $record['estado_cotizacion'],
                    'estado_nuevo' => $data['estado_cotizacion'],
                ], $userId, $now);
            }
        }
        if ($childrenChanged) {
            cot_deactivate_children($pdo, $id, $userId, $now);
            cot_insert_children($pdo, $id, $children, $userId, $now, $expedienteId);
            exp_timeline_add($pdo, 'expediente', $expedienteId, 'cotizacion_alternativa_modificada', 'Alternativas de cotizacion actualizadas: ' . (string) $record['codigo'], [
                'cotizacion_id' => $id,
                'codigo' => (string) $record['codigo'],
                'total_alternativas' => count($children['alternativas']),
            ], $userId, $now);
        }
        $pdo->commit();
        exp_json_success(['id' => $id], 'Cotizacion actualizada correctamente.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function cot_cambiar_estado(): void
{
    $payload = exp_require_change('puede_editar');
    $id = (int) ($payload['id'] ?? 0);
    $expedienteId = (int) ($payload['expediente_id'] ?? 0);
    $pdo = exp_db();
    $record = cot_fetch($pdo, $id, $expedienteId);
    if (!$record) {
        exp_json_error('Cotizacion no encontrada.', 404);
    }
    $nuevoEstado = (int) $record['estado'] === 1 ? 0 : 1;
    $userId = exp_user_id();
    $now = exp_now();
    try {
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE seg_cotizaciones
            SET estado = :estado,
                actualizado_por_usuario_externo_id = :usuario,
                actualizado_en = :fecha
            WHERE id = :id AND expediente_id = :expediente_id')
            ->execute([':estado' => $nuevoEstado, ':usuario' => $userId, ':fecha' => $now, ':id' => $id, ':expediente_id' => $expedienteId]);
        exp_timeline_add($pdo, 'expediente', $expedienteId, $nuevoEstado === 1 ? 'cotizacion_activada' : 'cotizacion_desactivada', $nuevoEstado === 1 ? 'Cotizacion activada.' : 'Cotizacion desactivada.', [
            'cotizacion_id' => $id,
            'codigo' => (string) $record['codigo'],
            'estado_anterior' => (int) $record['estado'],
            'estado_nuevo' => $nuevoEstado,
        ], $userId, $now);
        $pdo->commit();
        exp_json_success(['id' => $id, 'estado' => $nuevoEstado], $nuevoEstado === 1 ? 'Cotizacion activada.' : 'Cotizacion desactivada.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function cot_require_expediente(PDO $pdo, int $expedienteId): array
{
    $stmt = $pdo->prepare('SELECT
            e.*,
            c.ruc AS cliente_ruc,
            c.razon_social AS cliente_razon_social,
            c.nombre_comercial AS cliente_nombre_comercial,
            c.telefono_principal,
            c.correo_principal,
            ts.nombre AS tipo_seguro_nombre,
            r.id AS ramo_id,
            r.nombre AS ramo_nombre,
            cc.nombre_completo AS contacto_nombre,
            cc.telefono AS contacto_telefono,
            cc.correo AS contacto_correo
        FROM seg_expedientes e
        INNER JOIN seg_clientes c ON c.id = e.cliente_id
        INNER JOIN seg_tipos_seguro ts ON ts.id = e.tipo_seguro_id
        INNER JOIN seg_ramos r ON r.id = ts.ramo_id
        LEFT JOIN seg_cliente_contactos cc ON cc.cliente_id = c.id AND cc.es_principal = 1 AND cc.estado = 1
        WHERE e.id = :id
        LIMIT 1');
    $stmt->execute([':id' => $expedienteId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        exp_json_error('Expediente no encontrado.', 404);
    }
    return $row;
}

function cot_validate(PDO $pdo, array $payload, array $expediente, bool $isUpdate): array
{
    $errors = [];
    $id = isset($payload['id']) && is_numeric($payload['id']) ? (int) $payload['id'] : 0;
    if ($isUpdate && $id <= 0) {
        $errors['id'] = 'Cotizacion no valida.';
    }
    $fechaCotizacion = cot_date($payload, 'fecha_cotizacion', true);
    $fechaVencimiento = cot_date($payload, 'fecha_vencimiento', true);
    if ($fechaVencimiento < $fechaCotizacion) {
        $errors['fecha_vencimiento'] = 'El vencimiento no puede ser anterior a la fecha de cotizacion.';
    }
    $estadoCot = strtolower(trim((string) ($payload['estado_cotizacion'] ?? 'borrador')));
    if (!cot_estado_valido($estadoCot)) {
        $errors['estado_cotizacion'] = 'Seleccione un estado valido.';
    }
    if ($errors) {
        exp_json_error('Revisa los campos marcados.', 422, $errors);
    }
    return [
        'id' => $id,
        'expediente_id' => (int) $expediente['id'],
        'fecha_cotizacion' => $fechaCotizacion,
        'fecha_vencimiento' => $fechaVencimiento,
        'titulo' => exp_str($payload, 'titulo', 180, true),
        'estado_cotizacion' => $estadoCot,
        'descripcion' => exp_str($payload, 'descripcion', 1000, true),
        'observaciones' => exp_str($payload, 'observaciones', 3000, true),
        'nota_pdf' => exp_str($payload, 'nota_pdf', 1000, true),
        'estado' => exp_estado_value($payload),
    ];
}

function cot_date(array $payload, string $key, bool $required): string
{
    $value = trim((string) ($payload[$key] ?? ''));
    if ($value === '') {
        if ($required) {
            exp_json_error('Revisa los campos marcados.', 422, [$key => 'Fecha requerida.']);
        }
        return '';
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $value, new DateTimeZone('America/Lima'));
    if (!$dt || $dt->format('Y-m-d') !== $value) {
        exp_json_error('Revisa los campos marcados.', 422, [$key => 'Fecha no valida.']);
    }
    return $value;
}

function cot_next_codigo(PDO $pdo, string $year): string
{
    $prefix = 'COT-' . $year . '-';
    $stmt = $pdo->prepare("SELECT codigo FROM seg_cotizaciones WHERE codigo LIKE :prefix ORDER BY codigo DESC LIMIT 1 FOR UPDATE");
    $stmt->execute([':prefix' => $prefix . '%']);
    $last = (string) ($stmt->fetchColumn() ?: '');
    $next = 1;
    if (preg_match('/^COT-' . preg_quote($year, '/') . '-([0-9]{6})$/', $last, $m) === 1) {
        $next = (int) $m[1] + 1;
    }
    return $prefix . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
}

function cot_json_array(array $payload, string $key): array
{
    $raw = trim((string) ($payload[$key] ?? '[]'));
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function cot_parse_children(array $payload): array
{
    return [
        'riesgos' => cot_json_array($payload, 'riesgos_json'),
        'alternativas' => cot_json_array($payload, 'alternativas_json'),
        'comparativos' => cot_json_array($payload, 'comparativos_json'),
    ];
}

function cot_validate_children(PDO $pdo, array $children, int $ramoId, string $estadoCotizacion): void
{
    $accepted = 0;
    foreach ($children['alternativas'] as $alt) {
        if ((int) ($alt['es_aceptada'] ?? 0) === 1) {
            $accepted++;
        }
        $aseguradoraId = (int) ($alt['aseguradora_id'] ?? 0);
        if ($aseguradoraId <= 0) {
            exp_json_error('Cada alternativa debe tener aseguradora activa.', 422);
        }
        cot_require_aseguradora($pdo, $aseguradoraId);
        $productoId = (int) ($alt['producto_id'] ?? 0);
        if ($productoId > 0) {
            cot_require_producto($pdo, $productoId, $aseguradoraId, $ramoId);
        }
        foreach (['suma_asegurada', 'prima_comercial', 'igv', 'prima_total'] as $field) {
            cot_decimal_value($alt[$field] ?? null, $field);
        }
        foreach (($alt['cuotas'] ?? []) as $cuota) {
            cot_decimal_value($cuota['valor_cuota'] ?? null, 'valor_cuota');
            if ((int) ($cuota['cantidad_cuotas'] ?? 0) < 0) {
                exp_json_error('La cantidad de cuotas no puede ser negativa.', 422);
            }
        }
    }
    if ($accepted > 1) {
        exp_json_error('Solo una alternativa activa puede estar aceptada por cotizacion.', 409);
    }
    if ($estadoCotizacion === 'aceptada' && $accepted !== 1) {
        exp_json_error('Una cotizacion aceptada debe tener exactamente una alternativa aceptada.', 409);
    }
}

function cot_decimal_value($value, string $field): ?string
{
    $raw = trim((string) ($value ?? ''));
    if ($raw === '') {
        return null;
    }
    $raw = str_replace(',', '.', $raw);
    if (!is_numeric($raw) || (float) $raw < 0) {
        exp_json_error('Los montos no pueden ser negativos.', 422, [$field => 'Monto invalido.']);
    }
    return number_format((float) $raw, 2, '.', '');
}

function cot_require_aseguradora(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare('SELECT id FROM seg_aseguradoras WHERE id = :id AND estado = 1 LIMIT 1');
    $stmt->execute([':id' => $id]);
    if (!$stmt->fetchColumn()) {
        exp_json_error('Solo se pueden usar aseguradoras activas.', 422);
    }
}

function cot_require_producto(PDO $pdo, int $id, int $aseguradoraId, int $ramoId): array
{
    $stmt = $pdo->prepare('SELECT id, nombre_producto, nombre_plan
        FROM seg_productos
        WHERE id = :id
          AND aseguradora_id = :aseguradora_id
          AND ramo_id = :ramo_id
          AND estado = 1
        LIMIT 1');
    $stmt->execute([':id' => $id, ':aseguradora_id' => $aseguradoraId, ':ramo_id' => $ramoId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        exp_json_error('El producto seleccionado no es compatible con la aseguradora y ramo del expediente.', 422);
    }
    return $row;
}

function cot_deactivate_children(PDO $pdo, int $cotizacionId, ?int $userId, string $now): void
{
    foreach (['seg_cotizacion_datos_riesgo', 'seg_cotizacion_comparativos'] as $table) {
        $pdo->prepare("UPDATE {$table}
            SET estado = 0, actualizado_por_usuario_externo_id = :usuario, actualizado_en = :fecha
            WHERE cotizacion_id = :id AND estado = 1")
            ->execute([':usuario' => $userId, ':fecha' => $now, ':id' => $cotizacionId]);
    }
    $stmtAlt = $pdo->prepare('SELECT id FROM seg_cotizacion_alternativas WHERE cotizacion_id = :id AND estado = 1');
    $stmtAlt->execute([':id' => $cotizacionId]);
    $ids = array_map('intval', $stmtAlt->fetchAll(PDO::FETCH_COLUMN));
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("UPDATE seg_cotizacion_alternativa_cuotas SET estado = 0, actualizado_por_usuario_externo_id = ?, actualizado_en = ? WHERE alternativa_id IN ({$in})")
            ->execute(array_merge([$userId, $now], $ids));
        $pdo->prepare("UPDATE seg_cotizacion_comparativo_valores SET estado = 0, actualizado_por_usuario_externo_id = ?, actualizado_en = ? WHERE alternativa_id IN ({$in})")
            ->execute(array_merge([$userId, $now], $ids));
    }
    $pdo->prepare('UPDATE seg_cotizacion_alternativas
        SET estado = 0, actualizado_por_usuario_externo_id = :usuario, actualizado_en = :fecha
        WHERE cotizacion_id = :id AND estado = 1')
        ->execute([':usuario' => $userId, ':fecha' => $now, ':id' => $cotizacionId]);
}

function cot_insert_children(PDO $pdo, int $cotizacionId, array $children, ?int $userId, string $now, int $expedienteId): void
{
    $stmtR = $pdo->prepare('INSERT INTO seg_cotizacion_datos_riesgo
        (cotizacion_id, etiqueta, valor, orden_visual, estado, creado_por_usuario_externo_id, actualizado_por_usuario_externo_id, creado_en, actualizado_en)
        VALUES (:cotizacion_id, :etiqueta, :valor, :orden_visual, 1, :creado_por, :actualizado_por, :creado_en, :actualizado_en)');
    foreach ($children['riesgos'] as $i => $row) {
        $etiqueta = trim((string) ($row['etiqueta'] ?? ''));
        $valor = trim((string) ($row['valor'] ?? ''));
        if ($etiqueta === '' && $valor === '') {
            continue;
        }
        $stmtR->execute([
            ':cotizacion_id' => $cotizacionId,
            ':etiqueta' => substr($etiqueta, 0, 120),
            ':valor' => substr($valor, 0, 500),
            ':orden_visual' => (int) ($row['orden_visual'] ?? $i),
            ':creado_por' => $userId,
            ':actualizado_por' => $userId,
            ':creado_en' => $now,
            ':actualizado_en' => $now,
        ]);
    }

    $altIdMap = [];
    $stmtAlt = $pdo->prepare('INSERT INTO seg_cotizacion_alternativas
        (cotizacion_id, aseguradora_id, producto_id, nombre_plan_snapshot, orden_visual, vigencia_meses, vigencia_texto, suma_asegurada, moneda, prima_comercial, igv, prima_total, condicion_gps, es_aceptada, observaciones, estado, creado_por_usuario_externo_id, actualizado_por_usuario_externo_id, creado_en, actualizado_en)
        VALUES (:cotizacion_id, :aseguradora_id, :producto_id, :nombre_plan_snapshot, :orden_visual, :vigencia_meses, :vigencia_texto, :suma_asegurada, :moneda, :prima_comercial, :igv, :prima_total, :condicion_gps, :es_aceptada, :observaciones, 1, :creado_por, :actualizado_por, :creado_en, :actualizado_en)');
    $stmtCuota = $pdo->prepare('INSERT INTO seg_cotizacion_alternativa_cuotas
        (alternativa_id, modalidad, cantidad_cuotas, valor_cuota, descripcion, orden_visual, estado, creado_por_usuario_externo_id, actualizado_por_usuario_externo_id, creado_en, actualizado_en)
        VALUES (:alternativa_id, :modalidad, :cantidad_cuotas, :valor_cuota, :descripcion, :orden_visual, 1, :creado_por, :actualizado_por, :creado_en, :actualizado_en)');
    foreach ($children['alternativas'] as $i => $alt) {
        $aseguradoraId = (int) ($alt['aseguradora_id'] ?? 0);
        if ($aseguradoraId <= 0) {
            continue;
        }
        $frontId = (string) ($alt['uid'] ?? ('alt_' . $i));
        $stmtAlt->execute([
            ':cotizacion_id' => $cotizacionId,
            ':aseguradora_id' => $aseguradoraId,
            ':producto_id' => ((int) ($alt['producto_id'] ?? 0)) > 0 ? (int) $alt['producto_id'] : null,
            ':nombre_plan_snapshot' => substr(trim((string) ($alt['nombre_plan_snapshot'] ?? '')), 0, 180) ?: null,
            ':orden_visual' => (int) ($alt['orden_visual'] ?? $i),
            ':vigencia_meses' => ((int) ($alt['vigencia_meses'] ?? 0)) > 0 ? (int) $alt['vigencia_meses'] : null,
            ':vigencia_texto' => substr(trim((string) ($alt['vigencia_texto'] ?? '')), 0, 120) ?: null,
            ':suma_asegurada' => cot_decimal_value($alt['suma_asegurada'] ?? null, 'suma_asegurada'),
            ':moneda' => cot_moneda($alt['moneda'] ?? 'PEN'),
            ':prima_comercial' => cot_decimal_value($alt['prima_comercial'] ?? null, 'prima_comercial'),
            ':igv' => cot_decimal_value($alt['igv'] ?? null, 'igv'),
            ':prima_total' => cot_decimal_value($alt['prima_total'] ?? null, 'prima_total'),
            ':condicion_gps' => cot_gps_val((string) ($alt['condicion_gps'] ?? 'pendiente')),
            ':es_aceptada' => (int) ($alt['es_aceptada'] ?? 0) === 1 ? 1 : 0,
            ':observaciones' => substr(trim((string) ($alt['observaciones'] ?? '')), 0, 1000) ?: null,
            ':creado_por' => $userId,
            ':actualizado_por' => $userId,
            ':creado_en' => $now,
            ':actualizado_en' => $now,
        ]);
        $altId = (int) $pdo->lastInsertId();
        $altIdMap[$frontId] = $altId;
        exp_timeline_add($pdo, 'expediente', $expedienteId, ((int) ($alt['es_aceptada'] ?? 0) === 1 ? 'cotizacion_alternativa_aceptada' : 'cotizacion_alternativa_registrada'), 'Alternativa de cotizacion registrada.', [
            'cotizacion_id' => $cotizacionId,
            'alternativa_id' => $altId,
            'aseguradora_id' => $aseguradoraId,
        ], $userId, $now);
        foreach (($alt['cuotas'] ?? []) as $j => $cuota) {
            $stmtCuota->execute([
                ':alternativa_id' => $altId,
                ':modalidad' => cot_modalidad_val((string) ($cuota['modalidad'] ?? 'otro')),
                ':cantidad_cuotas' => max(0, (int) ($cuota['cantidad_cuotas'] ?? 0)),
                ':valor_cuota' => cot_decimal_value($cuota['valor_cuota'] ?? null, 'valor_cuota'),
                ':descripcion' => substr(trim((string) ($cuota['descripcion'] ?? '')), 0, 255) ?: null,
                ':orden_visual' => (int) ($cuota['orden_visual'] ?? $j),
                ':creado_por' => $userId,
                ':actualizado_por' => $userId,
                ':creado_en' => $now,
                ':actualizado_en' => $now,
            ]);
            exp_timeline_add($pdo, 'expediente', $expedienteId, 'cotizacion_cuota_registrada', 'Cuota de alternativa registrada.', [
                'cotizacion_id' => $cotizacionId,
                'alternativa_id' => $altId,
            ], $userId, $now);
        }
    }

    $stmtComp = $pdo->prepare('INSERT INTO seg_cotizacion_comparativos
        (cotizacion_id, seccion, etiqueta, detalle, orden_visual, estado, creado_por_usuario_externo_id, actualizado_por_usuario_externo_id, creado_en, actualizado_en)
        VALUES (:cotizacion_id, :seccion, :etiqueta, :detalle, :orden_visual, 1, :creado_por, :actualizado_por, :creado_en, :actualizado_en)');
    $stmtVal = $pdo->prepare('INSERT INTO seg_cotizacion_comparativo_valores
        (comparativo_id, alternativa_id, valor, estado, creado_por_usuario_externo_id, actualizado_por_usuario_externo_id, creado_en, actualizado_en)
        VALUES (:comparativo_id, :alternativa_id, :valor, 1, :creado_por, :actualizado_por, :creado_en, :actualizado_en)');
    foreach ($children['comparativos'] as $i => $row) {
        $etiqueta = trim((string) ($row['etiqueta'] ?? ''));
        if ($etiqueta === '') {
            continue;
        }
        $stmtComp->execute([
            ':cotizacion_id' => $cotizacionId,
            ':seccion' => cot_seccion_val((string) ($row['seccion'] ?? 'otro')),
            ':etiqueta' => substr($etiqueta, 0, 180),
            ':detalle' => substr(trim((string) ($row['detalle'] ?? '')), 0, 500) ?: null,
            ':orden_visual' => (int) ($row['orden_visual'] ?? $i),
            ':creado_por' => $userId,
            ':actualizado_por' => $userId,
            ':creado_en' => $now,
            ':actualizado_en' => $now,
        ]);
        $compId = (int) $pdo->lastInsertId();
        foreach (($row['valores'] ?? []) as $frontAlt => $valor) {
            if (!isset($altIdMap[(string) $frontAlt])) {
                continue;
            }
            $stmtVal->execute([
                ':comparativo_id' => $compId,
                ':alternativa_id' => $altIdMap[(string) $frontAlt],
                ':valor' => substr(trim((string) $valor), 0, 500),
                ':creado_por' => $userId,
                ':actualizado_por' => $userId,
                ':creado_en' => $now,
                ':actualizado_en' => $now,
            ]);
        }
    }
    if ($children['comparativos']) {
        exp_timeline_add($pdo, 'expediente', $expedienteId, 'cotizacion_comparativo_actualizado', 'Comparativo de cotizacion actualizado.', [
            'cotizacion_id' => $cotizacionId,
        ], $userId, $now);
    }
}

function cot_moneda($value): string
{
    $v = strtoupper(trim((string) $value));
    return in_array($v, ['PEN', 'USD', 'EUR', 'OTRA'], true) ? $v : 'PEN';
}

function cot_gps_val(string $value): string
{
    return isset(cot_gps_options()[$value]) ? $value : 'pendiente';
}

function cot_modalidad_val(string $value): string
{
    return isset(cot_cuota_modalidades()[$value]) ? $value : 'otro';
}

function cot_seccion_val(string $value): string
{
    return isset(cot_comparativo_secciones()[$value]) ? $value : 'otro';
}

function cot_fetch(PDO $pdo, int $id, int $expedienteId): ?array
{
    if ($id <= 0 || $expedienteId <= 0) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM seg_cotizaciones WHERE id = :id AND expediente_id = :expediente_id LIMIT 1');
    $stmt->execute([':id' => $id, ':expediente_id' => $expedienteId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (is_array($row)) {
        $row['estado_cotizacion_nombre'] = cot_estado_label((string) $row['estado_cotizacion']);
        return $row;
    }
    return null;
}

function cot_fetch_riesgos(PDO $pdo, int $cotizacionId): array
{
    $stmt = $pdo->prepare('SELECT * FROM seg_cotizacion_datos_riesgo WHERE cotizacion_id = :id AND estado = 1 ORDER BY orden_visual ASC, id ASC');
    $stmt->execute([':id' => $cotizacionId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function cot_fetch_alternativas(PDO $pdo, int $cotizacionId): array
{
    $stmt = $pdo->prepare('SELECT
            alt.*,
            a.razon_social AS aseguradora_razon_social,
            a.nombre_comercial AS aseguradora_nombre_comercial,
            p.nombre_producto,
            p.nombre_plan
        FROM seg_cotizacion_alternativas alt
        INNER JOIN seg_aseguradoras a ON a.id = alt.aseguradora_id
        LEFT JOIN seg_productos p ON p.id = alt.producto_id
        WHERE alt.cotizacion_id = :id AND alt.estado = 1
        ORDER BY alt.orden_visual ASC, alt.id ASC');
    $stmt->execute([':id' => $cotizacionId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $ids = array_map(static function ($r) {
        return (int) $r['id'];
    }, $rows);
    $cuotas = cot_fetch_cuotas($pdo, $ids);
    foreach ($rows as &$row) {
        $row['uid'] = 'db_' . (int) $row['id'];
        $row['cuotas'] = $cuotas[(int) $row['id']] ?? [];
    }
    unset($row);
    return $rows;
}

function cot_fetch_cuotas(PDO $pdo, array $altIds): array
{
    $altIds = array_values(array_unique(array_filter(array_map('intval', $altIds))));
    if (!$altIds) {
        return [];
    }
    $in = implode(',', array_fill(0, count($altIds), '?'));
    $stmt = $pdo->prepare("SELECT * FROM seg_cotizacion_alternativa_cuotas WHERE alternativa_id IN ({$in}) AND estado = 1 ORDER BY orden_visual ASC, id ASC");
    $stmt->execute($altIds);
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $out[(int) $row['alternativa_id']][] = $row;
    }
    return $out;
}

function cot_fetch_comparativos(PDO $pdo, int $cotizacionId): array
{
    $stmt = $pdo->prepare('SELECT * FROM seg_cotizacion_comparativos WHERE cotizacion_id = :id AND estado = 1 ORDER BY orden_visual ASC, id ASC');
    $stmt->execute([':id' => $cotizacionId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $ids = array_map(static function ($r) {
        return (int) $r['id'];
    }, $rows);
    $vals = cot_fetch_comparativo_valores($pdo, $ids);
    foreach ($rows as &$row) {
        $row['valores'] = $vals[(int) $row['id']] ?? [];
    }
    unset($row);
    return $rows;
}

function cot_fetch_comparativo_valores(PDO $pdo, array $compIds): array
{
    $compIds = array_values(array_unique(array_filter(array_map('intval', $compIds))));
    if (!$compIds) {
        return [];
    }
    $in = implode(',', array_fill(0, count($compIds), '?'));
    $stmt = $pdo->prepare("SELECT v.*, alt.id AS alternativa_db_id
        FROM seg_cotizacion_comparativo_valores v
        INNER JOIN seg_cotizacion_alternativas alt ON alt.id = v.alternativa_id
        WHERE v.comparativo_id IN ({$in}) AND v.estado = 1
        ORDER BY v.id ASC");
    $stmt->execute($compIds);
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $out[(int) $row['comparativo_id']]['db_' . (int) $row['alternativa_db_id']] = (string) $row['valor'];
    }
    return $out;
}

function cot_detect_changes(array $record, array $data): array
{
    $fields = ['fecha_cotizacion', 'fecha_vencimiento', 'titulo', 'estado_cotizacion', 'descripcion', 'observaciones', 'nota_pdf', 'estado'];
    $changes = [];
    foreach ($fields as $field) {
        $old = trim((string) ($record[$field] ?? ''));
        $new = trim((string) ($data[$field] ?? ''));
        if ($old !== $new) {
            $changes[$field] = ['anterior' => $old === '' ? null : $old, 'nuevo' => $new === '' ? null : $new];
        }
    }
    return $changes;
}

function cot_children_signature_existing(PDO $pdo, int $cotizacionId): string
{
    $riesgos = array_map(static function (array $r): array {
        return [
            'etiqueta' => cot_sig($r['etiqueta'] ?? ''),
            'valor' => cot_sig($r['valor'] ?? ''),
            'orden_visual' => (int) ($r['orden_visual'] ?? 0),
        ];
    }, cot_fetch_riesgos($pdo, $cotizacionId));

    $alternativas = array_map(static function (array $a): array {
        return [
            'aseguradora_id' => (int) ($a['aseguradora_id'] ?? 0),
            'producto_id' => (int) ($a['producto_id'] ?? 0),
            'nombre_plan_snapshot' => cot_sig($a['nombre_plan_snapshot'] ?? ''),
            'orden_visual' => (int) ($a['orden_visual'] ?? 0),
            'vigencia_meses' => (int) ($a['vigencia_meses'] ?? 0),
            'vigencia_texto' => cot_sig($a['vigencia_texto'] ?? ''),
            'suma_asegurada' => cot_sig_decimal($a['suma_asegurada'] ?? ''),
            'moneda' => cot_sig($a['moneda'] ?? 'PEN'),
            'prima_comercial' => cot_sig_decimal($a['prima_comercial'] ?? ''),
            'igv' => cot_sig_decimal($a['igv'] ?? ''),
            'prima_total' => cot_sig_decimal($a['prima_total'] ?? ''),
            'condicion_gps' => cot_sig($a['condicion_gps'] ?? ''),
            'es_aceptada' => (int) ($a['es_aceptada'] ?? 0),
            'observaciones' => cot_sig($a['observaciones'] ?? ''),
            'cuotas' => array_map(static function (array $c): array {
                return [
                    'modalidad' => cot_sig($c['modalidad'] ?? ''),
                    'cantidad_cuotas' => (int) ($c['cantidad_cuotas'] ?? 0),
                    'valor_cuota' => cot_sig_decimal($c['valor_cuota'] ?? ''),
                    'descripcion' => cot_sig($c['descripcion'] ?? ''),
                    'orden_visual' => (int) ($c['orden_visual'] ?? 0),
                ];
            }, $a['cuotas'] ?? []),
        ];
    }, cot_fetch_alternativas($pdo, $cotizacionId));

    $comparativos = array_map(static function (array $c): array {
        return [
            'seccion' => cot_sig($c['seccion'] ?? ''),
            'etiqueta' => cot_sig($c['etiqueta'] ?? ''),
            'detalle' => cot_sig($c['detalle'] ?? ''),
            'orden_visual' => (int) ($c['orden_visual'] ?? 0),
            'valores' => array_values(array_map('cot_sig', $c['valores'] ?? [])),
        ];
    }, cot_fetch_comparativos($pdo, $cotizacionId));

    return json_encode(['riesgos' => $riesgos, 'alternativas' => $alternativas, 'comparativos' => $comparativos], JSON_UNESCAPED_UNICODE);
}

function cot_children_signature_new(array $children): string
{
    $riesgos = [];
    foreach ($children['riesgos'] as $i => $r) {
        if (trim((string) ($r['etiqueta'] ?? '')) === '' && trim((string) ($r['valor'] ?? '')) === '') {
            continue;
        }
        $riesgos[] = [
            'etiqueta' => cot_sig($r['etiqueta'] ?? ''),
            'valor' => cot_sig($r['valor'] ?? ''),
            'orden_visual' => (int) ($r['orden_visual'] ?? $i),
        ];
    }

    $alternativas = [];
    foreach ($children['alternativas'] as $i => $a) {
        if ((int) ($a['aseguradora_id'] ?? 0) <= 0) {
            continue;
        }
        $cuotas = [];
        foreach (($a['cuotas'] ?? []) as $j => $c) {
            $cuotas[] = [
                'modalidad' => cot_sig(cot_modalidad_val((string) ($c['modalidad'] ?? 'otro'))),
                'cantidad_cuotas' => max(0, (int) ($c['cantidad_cuotas'] ?? 0)),
                'valor_cuota' => cot_sig_decimal($c['valor_cuota'] ?? ''),
                'descripcion' => cot_sig($c['descripcion'] ?? ''),
                'orden_visual' => (int) ($c['orden_visual'] ?? $j),
            ];
        }
        $alternativas[] = [
            'aseguradora_id' => (int) ($a['aseguradora_id'] ?? 0),
            'producto_id' => (int) ($a['producto_id'] ?? 0),
            'nombre_plan_snapshot' => cot_sig($a['nombre_plan_snapshot'] ?? ''),
            'orden_visual' => (int) ($a['orden_visual'] ?? $i),
            'vigencia_meses' => (int) ($a['vigencia_meses'] ?? 0),
            'vigencia_texto' => cot_sig($a['vigencia_texto'] ?? ''),
            'suma_asegurada' => cot_sig_decimal($a['suma_asegurada'] ?? ''),
            'moneda' => cot_sig(cot_moneda($a['moneda'] ?? 'PEN')),
            'prima_comercial' => cot_sig_decimal($a['prima_comercial'] ?? ''),
            'igv' => cot_sig_decimal($a['igv'] ?? ''),
            'prima_total' => cot_sig_decimal($a['prima_total'] ?? ''),
            'condicion_gps' => cot_sig(cot_gps_val((string) ($a['condicion_gps'] ?? 'pendiente'))),
            'es_aceptada' => (int) ($a['es_aceptada'] ?? 0) === 1 ? 1 : 0,
            'observaciones' => cot_sig($a['observaciones'] ?? ''),
            'cuotas' => $cuotas,
        ];
    }

    $comparativos = [];
    foreach ($children['comparativos'] as $i => $c) {
        if (trim((string) ($c['etiqueta'] ?? '')) === '') {
            continue;
        }
        $comparativos[] = [
            'seccion' => cot_sig(cot_seccion_val((string) ($c['seccion'] ?? 'otro'))),
            'etiqueta' => cot_sig($c['etiqueta'] ?? ''),
            'detalle' => cot_sig($c['detalle'] ?? ''),
            'orden_visual' => (int) ($c['orden_visual'] ?? $i),
            'valores' => array_values(array_map('cot_sig', $c['valores'] ?? [])),
        ];
    }

    return json_encode(['riesgos' => $riesgos, 'alternativas' => $alternativas, 'comparativos' => $comparativos], JSON_UNESCAPED_UNICODE);
}

function cot_sig($value): string
{
    return trim((string) $value);
}

function cot_sig_decimal($value): string
{
    $raw = trim((string) ($value ?? ''));
    if ($raw === '') {
        return '';
    }
    return number_format((float) str_replace(',', '.', $raw), 2, '.', '');
}
