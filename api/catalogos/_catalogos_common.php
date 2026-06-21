<?php
require_once __DIR__ . '/../../includes/session_guard.php';
require_once __DIR__ . '/../../includes/conexion_cliente.php';
require_once __DIR__ . '/../../includes/almacen_core.php';

function cat_db()
{
    try {
        return cb_cliente_db_required();
    } catch (Throwable $e) {
        cb_json_error('db_no_disponible', 'No se pudo conectar con la base de datos local.', 500);
    }
}

function cat_now_lima()
{
    return date('Y-m-d H:i:s');
}

function cat_payload()
{
    $payload = cb_request_payload();
    return is_array($payload) ? $payload : [];
}

function cat_trim($value)
{
    return trim((string) $value);
}

function cat_nullable($value)
{
    $value = cat_trim($value);
    return $value === '' ? null : $value;
}

function cat_is_valid_utf8($value)
{
    return preg_match('//u', (string) $value) === 1;
}

function cat_validate_utf8_value($value, $field, array &$errors)
{
    if (!cat_is_valid_utf8($value)) {
        $errors[$field] = 'El texto contiene caracteres no validos.';
    }
}

function cat_validate_max($value, $field, $max, array &$errors)
{
    $value = (string) $value;
    if (strlen($value) > (int) $max) {
        $errors[$field] = 'Maximo ' . (int) $max . ' caracteres.';
    }
}

function cat_codigo($value)
{
    $value = strtoupper(cat_trim($value));
    return preg_match('/^[A-Z0-9_-]{2,40}$/', $value) === 1 ? $value : '';
}

function cat_codigo_from_nombre($value)
{
    $value = strtoupper(cat_trim($value));
    $value = strtr($value, [
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
        'Ä' => 'A', 'Ë' => 'E', 'Ï' => 'I', 'Ö' => 'O', 'Ü' => 'U',
        'À' => 'A', 'È' => 'E', 'Ì' => 'I', 'Ò' => 'O', 'Ù' => 'U',
        'Ñ' => 'N', 'Ç' => 'C',
        'á' => 'A', 'é' => 'E', 'í' => 'I', 'ó' => 'O', 'ú' => 'U',
        'ä' => 'A', 'ë' => 'E', 'ï' => 'I', 'ö' => 'O', 'ü' => 'U',
        'à' => 'A', 'è' => 'E', 'ì' => 'I', 'ò' => 'O', 'ù' => 'U',
        'ñ' => 'N', 'ç' => 'C',
    ]);
    $value = preg_replace('/[^A-Z0-9]+/', '_', $value);
    $value = trim((string) preg_replace('/_+/', '_', (string) $value), '_');
    if ($value === '') {
        $value = 'CATALOGO';
    }
    return substr($value, 0, 40);
}

function cat_codigo_unico_desde_nombre(PDO $pdo, $table, $nombre)
{
    $base = cat_codigo_from_nombre($nombre);
    $codigo = $base;
    $suffix = 2;

    while (cat_value_exists($pdo, $table, 'codigo', $codigo)) {
        $tail = '_' . $suffix;
        $codigo = substr($base, 0, 40 - strlen($tail)) . $tail;
        $suffix++;
        if ($suffix > 999) {
            cb_json_error('codigo_no_disponible', 'No se pudo generar un codigo tecnico unico.', 409);
        }
    }

    return $codigo;
}

function cat_estado_value($value, $default = 1)
{
    if ($value === null || $value === '') {
        return (int) $default;
    }
    return (int) $value === 1 ? 1 : 0;
}

function cat_estado_filter()
{
    $estado = strtolower(cat_trim($_GET['estado'] ?? 'todos'));
    return in_array($estado, ['todos', 'activo', 'desactivado'], true) ? $estado : 'todos';
}

function cat_search()
{
    return substr(cat_trim($_GET['q'] ?? ''), 0, 120);
}

function cat_page()
{
    $page = (int) ($_GET['page'] ?? 1);
    return $page > 0 ? $page : 1;
}

function cat_per_page()
{
    $perPage = (int) ($_GET['per_page'] ?? 10);
    if ($perPage < 1) {
        return 10;
    }
    return min($perPage, 50);
}

function cat_response_page($rows, $total, $page, $perPage)
{
    $total = (int) $total;
    $perPage = (int) $perPage;
    $lastPage = $perPage > 0 ? max(1, (int) ceil($total / $perPage)) : 1;

    return [
        'rows' => $rows,
        'pagination' => [
            'page' => (int) $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => $lastPage,
        ],
    ];
}

function cat_require_catalogos($accion)
{
    cb_require_cliente_permission('catalogos', $accion);
}

function cat_require_post_change($accion)
{
    cb_require_method('POST');
    cat_require_catalogos($accion);
    cb_require_local_csrf('catalogos');
}

function cat_bind_like($value)
{
    return '%' . trim((string) $value) . '%';
}

function cat_db_error()
{
    cb_json_error('error_bd', 'No se pudo completar la operacion solicitada.', 500);
}

function cat_duplicate_error($message = 'Ya existe un registro con los datos indicados.')
{
    cb_json_error('registro_duplicado', $message, 409);
}

function cat_user_id()
{
    $id = cb_cliente_usuario_externo_id();
    if ($id <= 0) {
        cb_json_error('usuario_no_identificado', 'No se pudo identificar al usuario actual.', 401);
    }
    return $id;
}

function cat_fetch_one(PDO $pdo, $sql, array $params)
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function cat_value_exists(PDO $pdo, $table, $column, $value, $excludeId = 0)
{
    if ($value === null || $value === '') {
        return false;
    }

    $sql = "SELECT id FROM {$table} WHERE {$column} = :value";
    $params = [':value' => $value];
    if ((int) $excludeId > 0) {
        $sql .= ' AND id <> :id';
        $params[':id'] = (int) $excludeId;
    }
    $sql .= ' LIMIT 1';

    return cat_fetch_one($pdo, $sql, $params) !== null;
}

function cat_require_record(PDO $pdo, $table, $id, $message = 'Registro no encontrado.')
{
    $id = (int) $id;
    if ($id <= 0) {
        cb_json_error('id_invalido', 'Identificador invalido.', 422);
    }

    $row = cat_fetch_one($pdo, "SELECT * FROM {$table} WHERE id = :id LIMIT 1", [':id' => $id]);
    if (!$row) {
        cb_json_error('no_encontrado', $message, 404);
    }

    return $row;
}

function cat_validate_email($value, $field, array &$errors)
{
    $value = cat_trim($value);
    if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
        $errors[$field] = 'Ingrese un correo valido.';
    }
}

function cat_validate_url($value, $field, array &$errors)
{
    $value = cat_trim($value);
    if ($value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
        $errors[$field] = 'Ingrese una URL valida.';
    }
}

function cat_abort_if_errors(array $errors)
{
    if ($errors) {
        cb_json_error('validacion', 'Revise los campos marcados.', 422, $errors);
    }
}

function cat_int_range($value, $default, $min, $max)
{
    if ($value === null || $value === '') {
        return (int) $default;
    }
    $value = (int) $value;
    if ($value < (int) $min) {
        return (int) $min;
    }
    if ($value > (int) $max) {
        return (int) $max;
    }
    return $value;
}

function cat_bool_value($value, $default = 0)
{
    if ($value === null || $value === '') {
        return (int) $default;
    }
    return in_array((string) $value, ['1', 'true', 'on', 'yes'], true) ? 1 : 0;
}

function cat_validate_hex_color($value, $field, array &$errors)
{
    $value = cat_trim($value);
    if ($value !== '' && preg_match('/^#[0-9A-Fa-f]{6}$/', $value) !== 1) {
        $errors[$field] = 'Seleccione un color valido.';
    }
}

function cat_logo_table_exists(PDO $pdo)
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'seg_aseguradora_logo_archivos'");
        $exists = $stmt && $stmt->fetchColumn() !== false;
    } catch (Throwable $e) {
        $exists = false;
    }

    return $exists;
}

function cat_require_logo_table(PDO $pdo)
{
    if (!cat_logo_table_exists($pdo)) {
        cb_json_error('tabla_logo_pendiente', 'La tabla de logos de aseguradoras todavia no existe. Ejecute el query de esta fase en phpMyAdmin.', 409);
    }
}

function cat_validate_logo_upload($fieldName = 'logo_archivo')
{
    if (!isset($_FILES[$fieldName]) || !is_array($_FILES[$fieldName])) {
        return null;
    }

    $file = $_FILES[$fieldName];
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK) {
        cb_json_error('logo_invalido', 'No se pudo recibir el archivo de logo.', 422, [$fieldName => 'Archivo no recibido correctamente.']);
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        cb_json_error('logo_invalido', 'El archivo de logo no es valido.', 422, [$fieldName => 'Archivo no valido.']);
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0) {
        cb_json_error('logo_tamanio_invalido', 'El archivo de logo esta vacio.', 422, [$fieldName => 'Archivo vacio.']);
    }

    $info = @getimagesize($tmpName);
    if (!is_array($info) || empty($info[0]) || empty($info[1])) {
        cb_json_error('logo_imagen_invalida', 'El archivo no es una imagen valida.', 422, [$fieldName => 'Imagen no valida.']);
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($tmpName);
    $allowed = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];
    if (empty($allowed[$mime]) || empty($allowed[(string) ($info['mime'] ?? '')]) || $mime !== (string) $info['mime']) {
        cb_json_error('logo_tipo_invalido', 'Tipo de imagen no permitido.', 422, [$fieldName => 'Tipo de imagen no permitido.']);
    }

    return [
        'nombre_original' => substr(basename((string) ($file['name'] ?? 'logo')), 0, 255),
        'file_info' => $file,
        'tmp_name' => $tmpName,
        'extension' => $allowed[$mime],
        'mime_type' => $mime,
        'tamanio_bytes' => $size,
        'ancho_px' => (int) $info[0],
        'alto_px' => (int) $info[1],
    ];
}

function cat_storage_rel_base()
{
    return 'storage';
}

function cat_storage_abs_base()
{
    return rtrim(dirname(dirname(__DIR__)), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . cat_storage_rel_base();
}

function cat_slug($value)
{
    $value = strtolower(trim((string) $value));
    $value = preg_replace('/[^a-z0-9_]+/', '_', $value);
    $value = preg_replace('/_+/', '_', (string) $value);
    return trim((string) $value, '_');
}

function cat_random_hex($bytes = 8)
{
    try {
        return bin2hex(random_bytes((int) $bytes));
    } catch (Throwable $e) {
        return substr(md5(uniqid((string) mt_rand(), true)), 0, (int) $bytes * 2);
    }
}

function cat_logo_rel_dir()
{
    return cb_almacen_rel_dir('aseguradoras/logos');
}

function cat_abs_from_rel($rutaRelativa)
{
    $rutaRelativa = trim((string) $rutaRelativa);
    $rutaRelativa = ltrim($rutaRelativa, '/\\');
    $rutaRelativa = str_replace(['..\\', '../'], '', $rutaRelativa);
    return rtrim(dirname(dirname(__DIR__)), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rutaRelativa);
}

function cat_is_safe_storage_path($absolutePath)
{
    $storageBase = realpath(cat_storage_abs_base());
    $almacenBase = realpath(cb_almacen_abs_base());
    $target = realpath($absolutePath);
    if ($target === false) {
        return false;
    }

    if ($storageBase !== false && strpos($target, rtrim($storageBase, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) === 0) {
        return true;
    }

    return $almacenBase !== false && strpos($target, rtrim($almacenBase, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) === 0;
}

function cat_logo_store_file(PDO $pdo, array $logo, $aseguradoraId, $userId)
{
    if (!cb_almacen_schema_ready($pdo)) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        cb_json_error('almacen_schema_pendiente', 'La estructura de almacen todavia no existe. Ejecute los querys de esta fase en phpMyAdmin.', 409);
    }

    $errors = [];
    $stored = cb_almacen_guardar_upload($pdo, (array) ($logo['file_info'] ?? []), [
        'carpeta' => 'aseguradoras/logos',
        'usuario_id' => (int) $userId,
        'descripcion' => 'Logo de aseguradora',
        'vinculo' => [
            'codigo_uso' => 'aseguradora_logo',
            'entidad_tipo' => 'seg_aseguradoras',
            'entidad_id' => (int) $aseguradoraId,
            'slot' => 'logo',
            'orden' => 1,
        ],
    ], $errors);

    if (!$stored) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = 'No se pudo guardar el logo en almacen.';
        if ($errors) {
            $first = reset($errors);
            if (is_string($first) && $first !== '') {
                $message = $first;
            }
        }
        cb_json_error('storage_error', $message, 500, $errors);
    }

    return $stored;
}

function cat_logo_delete_file($rutaRelativa)
{
    $rutaRelativa = trim((string) $rutaRelativa);
    if ($rutaRelativa === '') {
        return;
    }
    if (strpos($rutaRelativa, cb_almacen_rel_base() . '/') === 0) {
        try {
            $pdo = cat_db();
            if (cb_almacen_schema_ready($pdo)) {
                $errors = [];
                $deleted = cb_almacen_delete_by_ruta($pdo, $rutaRelativa, cat_user_id(), $errors);
                if ($deleted) {
                    return;
                }
            }
        } catch (Throwable $e) {
            return;
        }
    }
    $absPath = cat_abs_from_rel($rutaRelativa);
    if (is_file($absPath) && cat_is_safe_storage_path($absPath)) {
        @unlink($absPath);
    }
}

function cat_logo_current(PDO $pdo, $aseguradoraId)
{
    if (!cat_logo_table_exists($pdo)) {
        return null;
    }
    return cat_fetch_one($pdo, 'SELECT * FROM seg_aseguradora_logo_archivos WHERE aseguradora_id = :id LIMIT 1', [':id' => (int) $aseguradoraId]);
}

function cat_save_logo(PDO $pdo, $aseguradoraId, array $logoFile, $userId, $now)
{
    cat_require_logo_table($pdo);
    $stored = cat_logo_store_file($pdo, $logoFile, $aseguradoraId, $userId);
    $previous = cat_logo_current($pdo, $aseguradoraId);

    try {
        $stmt = $pdo->prepare('INSERT INTO seg_aseguradora_logo_archivos
        (aseguradora_id, ruta_relativa, nombre_original, nombre_interno, extension, mime_type, tamanio_bytes, ancho_px, alto_px, checksum_sha256, creado_por_usuario_externo_id, actualizado_por_usuario_externo_id, creado_en, actualizado_en)
        VALUES
        (:aseguradora_id, :ruta_relativa, :nombre_original, :nombre_interno, :extension, :mime_type, :tamanio_bytes, :ancho_px, :alto_px, :checksum_sha256, :creado_por, :actualizado_por, :creado_en, :actualizado_en)
        ON DUPLICATE KEY UPDATE
            ruta_relativa = VALUES(ruta_relativa),
            nombre_original = VALUES(nombre_original),
            nombre_interno = VALUES(nombre_interno),
            extension = VALUES(extension),
            mime_type = VALUES(mime_type),
            tamanio_bytes = VALUES(tamanio_bytes),
            ancho_px = VALUES(ancho_px),
            alto_px = VALUES(alto_px),
            checksum_sha256 = VALUES(checksum_sha256),
            actualizado_por_usuario_externo_id = VALUES(actualizado_por_usuario_externo_id),
            actualizado_en = VALUES(actualizado_en)');
        $stmt->execute([
            ':aseguradora_id' => (int) $aseguradoraId,
            ':ruta_relativa' => $stored['ruta_relativa'],
            ':nombre_original' => $stored['nombre_original'],
            ':nombre_interno' => $stored['nombre_interno'],
            ':extension' => $stored['extension'],
            ':mime_type' => $stored['mime_type'],
            ':tamanio_bytes' => $stored['tamanio_bytes'],
            ':ancho_px' => $stored['ancho_px'],
            ':alto_px' => $stored['alto_px'],
            ':checksum_sha256' => $stored['checksum_sha256'],
            ':creado_por' => (int) $userId,
            ':actualizado_por' => (int) $userId,
            ':creado_en' => $now,
            ':actualizado_en' => $now,
        ]);
    } catch (Throwable $e) {
        @unlink($stored['absolute_path']);
        throw $e;
    }

    return [
        'ruta_relativa' => $stored['ruta_relativa'],
        'absolute_path' => $stored['absolute_path'],
        'previous_ruta_relativa' => is_array($previous ?? null) ? (string) ($previous['ruta_relativa'] ?? '') : '',
    ];
}

function cat_delete_logo(PDO $pdo, $aseguradoraId)
{
    cat_require_logo_table($pdo);
    $previous = cat_logo_current($pdo, $aseguradoraId);
    $stmt = $pdo->prepare('DELETE FROM seg_aseguradora_logo_archivos WHERE aseguradora_id = :aseguradora_id');
    $stmt->execute([':aseguradora_id' => (int) $aseguradoraId]);
    return is_array($previous ?? null) ? (string) ($previous['ruta_relativa'] ?? '') : '';
}
