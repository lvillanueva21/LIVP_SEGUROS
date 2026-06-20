<?php
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit('Acceso directo no permitido.');
}

require_once __DIR__ . '/conexion_cliente.php';
require_once __DIR__ . '/request_cliente.php';
require_once __DIR__ . '/autorizacion_cliente.php';

function cb_almacen_rel_base()
{
    return 'almacen';
}

function cb_almacen_abs_base()
{
    return rtrim(dirname(__DIR__), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . cb_almacen_rel_base();
}

function cb_almacen_db()
{
    return cb_cliente_db_required();
}

function cb_almacen_now()
{
    return date('Y-m-d H:i:s');
}

function cb_almacen_table_exists(PDO $pdo, $table)
{
    $table = trim((string) $table);
    if (preg_match('/^[A-Za-z0-9_]{2,80}$/', $table) !== 1) {
        return false;
    }

    try {
        $stmt = $pdo->prepare('SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name');
        $stmt->execute([':table_name' => $table]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function cb_almacen_schema_ready(PDO $pdo = null)
{
    if (!$pdo instanceof PDO) {
        $pdo = cb_almacen_db();
    }

    return cb_almacen_table_exists($pdo, 'seg_archivos')
        && cb_almacen_table_exists($pdo, 'seg_archivos_vinculos');
}

function cb_almacen_require_schema(PDO $pdo = null)
{
    if (!$pdo instanceof PDO) {
        $pdo = cb_almacen_db();
    }

    if (!cb_almacen_schema_ready($pdo)) {
        cb_json_error('almacen_schema_pendiente', 'La estructura de almacen todavia no existe. Ejecute los querys de esta fase en phpMyAdmin.', 409);
    }
}

function cb_almacen_slug_part($value)
{
    $value = strtolower(trim((string) $value));
    $value = str_replace('\\', '/', $value);
    $value = preg_replace('/[^a-z0-9_-]+/', '_', $value);
    $value = preg_replace('/_+/', '_', (string) $value);
    return trim((string) $value, '_-');
}

function cb_almacen_normalize_carpeta($carpeta)
{
    $carpeta = strtolower(trim((string) $carpeta));
    $carpeta = str_replace('\\', '/', $carpeta);
    $parts = explode('/', $carpeta);
    $safeParts = [];
    foreach ($parts as $part) {
        $part = cb_almacen_slug_part($part);
        if ($part !== '') {
            $safeParts[] = $part;
        }
    }

    if (!$safeParts) {
        return '';
    }

    return implode('/', array_slice($safeParts, 0, 5));
}

function cb_almacen_random_hex($bytes = 8)
{
    $bytes = (int) $bytes;
    if ($bytes < 4) {
        $bytes = 8;
    }
    if ($bytes > 32) {
        $bytes = 32;
    }

    try {
        return bin2hex(random_bytes($bytes));
    } catch (Throwable $e) {
        if (function_exists('openssl_random_pseudo_bytes')) {
            $raw = openssl_random_pseudo_bytes($bytes);
            if (is_string($raw) && $raw !== '') {
                return bin2hex($raw);
            }
        }
    }

    return substr(hash('sha256', uniqid((string) mt_rand(), true)), 0, $bytes * 2);
}

function cb_almacen_rel_dir($carpeta)
{
    $carpeta = cb_almacen_normalize_carpeta($carpeta);
    if ($carpeta === '') {
        $carpeta = 'generales';
    }

    return cb_almacen_rel_base() . '/' . $carpeta . '/' . date('Y') . '/' . date('m') . '/' . date('d');
}

function cb_almacen_abs_from_rel($rutaRelativa)
{
    $rutaRelativa = trim((string) $rutaRelativa);
    $rutaRelativa = ltrim($rutaRelativa, '/\\');
    $rutaRelativa = str_replace('\\', '/', $rutaRelativa);
    $rutaRelativa = preg_replace('#/+#', '/', $rutaRelativa);
    $rutaRelativa = str_replace(['../', '..\\'], '', (string) $rutaRelativa);

    return rtrim(dirname(__DIR__), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . str_replace('/', DIRECTORY_SEPARATOR, $rutaRelativa);
}

function cb_almacen_is_safe_rel_path($rutaRelativa)
{
    $rutaRelativa = trim((string) $rutaRelativa);
    if ($rutaRelativa === '') {
        return false;
    }
    if (strpos($rutaRelativa, "\0") !== false) {
        return false;
    }
    if (strpos($rutaRelativa, '\\') !== false) {
        return false;
    }
    if (strpos($rutaRelativa, '..') !== false) {
        return false;
    }
    if (strpos($rutaRelativa, '/') === 0) {
        return false;
    }
    if (preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*:/', $rutaRelativa) === 1) {
        return false;
    }

    return strpos($rutaRelativa, cb_almacen_rel_base() . '/') === 0
        && preg_match('/^[A-Za-z0-9_\\-\\.\\/]+$/', $rutaRelativa) === 1;
}

function cb_almacen_is_under_base($absolutePath)
{
    $base = realpath(cb_almacen_abs_base());
    $target = realpath((string) $absolutePath);
    if ($base === false || $target === false) {
        return false;
    }

    return strpos($target, rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) === 0;
}

function cb_almacen_upload_error_message($errorCode)
{
    $code = (int) $errorCode;
    if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
        return 'El archivo supera el limite configurado por el servidor (upload_max_filesize/post_max_size).';
    }
    if ($code === UPLOAD_ERR_PARTIAL) {
        return 'La carga del archivo quedo incompleta.';
    }
    if ($code === UPLOAD_ERR_NO_FILE) {
        return 'Debes seleccionar un archivo.';
    }
    if ($code === UPLOAD_ERR_NO_TMP_DIR) {
        return 'Falta carpeta temporal en servidor.';
    }
    if ($code === UPLOAD_ERR_CANT_WRITE) {
        return 'No se pudo escribir el archivo en disco.';
    }
    if ($code === UPLOAD_ERR_EXTENSION) {
        return 'Una extension del servidor detuvo la carga.';
    }

    return 'No se pudo recibir el archivo.';
}

function cb_almacen_detect_mime($tmpPath)
{
    $tmpPath = trim((string) $tmpPath);
    if ($tmpPath === '' || !is_file($tmpPath)) {
        return '';
    }

    if (function_exists('finfo_open')) {
        $fi = @finfo_open(FILEINFO_MIME_TYPE);
        if ($fi) {
            $detected = @finfo_file($fi, $tmpPath);
            @finfo_close($fi);
            if (is_string($detected) && trim($detected) !== '') {
                return strtolower(trim($detected));
            }
        }
    }

    if (function_exists('mime_content_type')) {
        $detected = @mime_content_type($tmpPath);
        if (is_string($detected) && trim($detected) !== '') {
            return strtolower(trim($detected));
        }
    }

    return '';
}

function cb_almacen_denied_extensions()
{
    return [
        'php' => true,
        'php3' => true,
        'php4' => true,
        'php5' => true,
        'phtml' => true,
        'phar' => true,
        'cgi' => true,
        'pl' => true,
        'py' => true,
        'rb' => true,
        'asp' => true,
        'aspx' => true,
        'jsp' => true,
        'exe' => true,
        'dll' => true,
        'bat' => true,
        'cmd' => true,
        'com' => true,
        'scr' => true,
        'sh' => true,
        'bash' => true,
        'ps1' => true,
        'msi' => true,
        'jar' => true,
        'html' => true,
        'htm' => true,
        'js' => true,
    ];
}

function cb_almacen_extension_from_mime($mime)
{
    $mime = strtolower(trim((string) $mime));
    $map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/bmp' => 'bmp',
        'image/svg+xml' => 'svg',
        'image/x-icon' => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
        'application/pdf' => 'pdf',
        'text/plain' => 'txt',
        'text/csv' => 'csv',
        'application/zip' => 'zip',
        'application/x-zip-compressed' => 'zip',
        'application/json' => 'json',
        'application/xml' => 'xml',
        'text/xml' => 'xml',
    ];
    if (isset($map[$mime])) {
        return $map[$mime];
    }

    if (strpos($mime, 'image/') === 0) {
        $sub = preg_replace('/[^a-z0-9]+/', '', substr($mime, 6));
        return $sub !== '' ? $sub : 'img';
    }
    if (strpos($mime, 'audio/') === 0) {
        $sub = preg_replace('/[^a-z0-9]+/', '', substr($mime, 6));
        return $sub !== '' ? $sub : 'audio';
    }
    if (strpos($mime, 'video/') === 0) {
        $sub = preg_replace('/[^a-z0-9]+/', '', substr($mime, 6));
        return $sub !== '' ? $sub : 'video';
    }

    return '';
}

function cb_almacen_tipo_archivo($mime, $extension)
{
    $mime = strtolower(trim((string) $mime));
    $extension = strtolower(trim((string) $extension));

    if (strpos($mime, 'image/') === 0) {
        return 'imagen';
    }
    if (strpos($mime, 'audio/') === 0) {
        return 'audio';
    }
    if (strpos($mime, 'video/') === 0) {
        return 'video';
    }
    if (in_array($extension, ['pdf', 'doc', 'docx', 'odt', 'rtf', 'txt', 'csv', 'xml', 'json'], true)) {
        return 'documento';
    }
    if (in_array($extension, ['xls', 'xlsx', 'ods'], true)) {
        return 'hoja_calculo';
    }
    if (in_array($extension, ['ppt', 'pptx', 'odp'], true)) {
        return 'presentacion';
    }
    if (in_array($extension, ['zip', 'rar', '7z', 'gz'], true)) {
        return 'comprimido';
    }

    return 'otro';
}

function cb_almacen_normalize_upload(array $fileInfo, &$errors = [])
{
    $errors = [];
    $error = (int) ($fileInfo['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        $errors['archivo'] = cb_almacen_upload_error_message($error);
        return null;
    }

    $tmpName = trim((string) ($fileInfo['tmp_name'] ?? ''));
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        $errors['archivo'] = 'Archivo temporal invalido.';
        return null;
    }

    $size = (int) ($fileInfo['size'] ?? 0);
    if ($size <= 0) {
        $errors['archivo'] = 'El archivo esta vacio.';
        return null;
    }

    $nombreOriginal = basename((string) ($fileInfo['name'] ?? 'archivo'));
    $nombreOriginal = trim($nombreOriginal);
    if ($nombreOriginal === '') {
        $nombreOriginal = 'archivo';
    }
    if (strlen($nombreOriginal) > 255) {
        $nombreOriginal = substr($nombreOriginal, 0, 255);
    }

    $extension = strtolower(trim((string) pathinfo($nombreOriginal, PATHINFO_EXTENSION)));
    $extension = preg_replace('/[^a-z0-9]+/', '', (string) $extension);
    $mime = cb_almacen_detect_mime($tmpName);
    if ($extension === '') {
        $extension = cb_almacen_extension_from_mime($mime);
    }
    if ($extension === '') {
        $extension = 'bin';
    }

    $deniedExtensions = cb_almacen_denied_extensions();
    if (isset($deniedExtensions[$extension])) {
        $errors['extension'] = 'Tipo de archivo no permitido por seguridad.';
        return null;
    }

    $imageInfo = @getimagesize($tmpName);
    $ancho = null;
    $alto = null;
    if (is_array($imageInfo) && isset($imageInfo[0], $imageInfo[1])) {
        $ancho = (int) $imageInfo[0];
        $alto = (int) $imageInfo[1];
        if (!empty($imageInfo['mime'])) {
            $mime = strtolower(trim((string) $imageInfo['mime']));
        }
    }
    if ($mime === '') {
        $mime = 'application/octet-stream';
    }

    return [
        'tmp_name' => $tmpName,
        'nombre_original' => $nombreOriginal,
        'extension' => $extension,
        'mime_type' => $mime,
        'tamanio_bytes' => $size,
        'ancho_px' => $ancho,
        'alto_px' => $alto,
        'tipo_archivo' => cb_almacen_tipo_archivo($mime, $extension),
    ];
}

function cb_almacen_store_upload_file(array $normalized, $carpeta, &$errors = [])
{
    $errors = [];
    $carpeta = cb_almacen_normalize_carpeta($carpeta);
    if ($carpeta === '') {
        $errors['carpeta'] = 'Carpeta de almacen invalida.';
        return null;
    }

    $relDir = cb_almacen_rel_dir($carpeta);
    $absDir = cb_almacen_abs_from_rel($relDir);
    if (!is_dir($absDir) && !@mkdir($absDir, 0775, true)) {
        $errors['storage'] = 'No se pudo crear la carpeta de almacen.';
        return null;
    }
    if (!is_dir($absDir) || !is_writable($absDir)) {
        $errors['storage'] = 'La carpeta de almacen no esta disponible para escritura.';
        return null;
    }

    $baseName = cb_almacen_slug_part(pathinfo((string) $normalized['nombre_original'], PATHINFO_FILENAME));
    if ($baseName === '') {
        $baseName = 'archivo';
    }
    $nombreInterno = date('Ymd_His') . '_' . $baseName . '_' . cb_almacen_random_hex(8) . '.' . $normalized['extension'];
    if (strlen($nombreInterno) > 255) {
        $nombreInterno = substr($nombreInterno, 0, 255);
    }

    $absPath = rtrim($absDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $nombreInterno;
    if (!@move_uploaded_file((string) $normalized['tmp_name'], $absPath)) {
        $errors['storage'] = 'No se pudo guardar el archivo en almacen.';
        return null;
    }

    if (!cb_almacen_is_under_base($absPath)) {
        @unlink($absPath);
        $errors['storage'] = 'Ruta de almacen invalida.';
        return null;
    }

    $checksum = @hash_file('sha256', $absPath);
    if (!is_string($checksum) || strlen($checksum) !== 64) {
        @unlink($absPath);
        $errors['checksum'] = 'No se pudo validar el checksum del archivo.';
        return null;
    }

    return [
        'codigo_carpeta' => $carpeta,
        'nombre_interno' => $nombreInterno,
        'ruta_relativa' => $relDir . '/' . $nombreInterno,
        'absolute_path' => $absPath,
        'checksum_sha256' => $checksum,
    ] + $normalized;
}

function cb_almacen_guardar_upload(PDO $pdo, array $fileInfo, array $options, &$errors = [])
{
    $errors = [];
    cb_almacen_require_schema($pdo);

    $carpeta = cb_almacen_normalize_carpeta($options['carpeta'] ?? '');
    if ($carpeta === '') {
        $errors['carpeta'] = 'Carpeta de almacen invalida.';
        return null;
    }

    $uploadErrors = [];
    $normalized = cb_almacen_normalize_upload($fileInfo, $uploadErrors);
    if (!$normalized) {
        $errors = $uploadErrors;
        return null;
    }

    $storeErrors = [];
    $stored = cb_almacen_store_upload_file($normalized, $carpeta, $storeErrors);
    if (!$stored) {
        $errors = $storeErrors;
        return null;
    }

    $actorUserId = (int) ($options['usuario_id'] ?? cb_cliente_usuario_externo_id());
    if ($actorUserId <= 0) {
        $actorUserId = null;
    }

    $descripcion = trim((string) ($options['descripcion'] ?? ''));
    if ($descripcion === '') {
        $descripcion = null;
    }
    if (is_string($descripcion) && strlen($descripcion) > 255) {
        $descripcion = substr($descripcion, 0, 255);
    }

    $estado = isset($options['estado']) && (int) $options['estado'] === 0 ? 0 : 1;
    $now = cb_almacen_now();

    $startedTransaction = !$pdo->inTransaction();

    try {
        if ($startedTransaction) {
            $pdo->beginTransaction();
        }
        $stmt = $pdo->prepare('INSERT INTO seg_archivos
            (codigo_carpeta, tipo_archivo, nombre_original, nombre_interno, extension, mime_type, tamanio_bytes, ancho_px, alto_px, ruta_relativa, checksum_sha256, descripcion, estado, creado_por_usuario_externo_id, actualizado_por_usuario_externo_id, creado_en, actualizado_en)
            VALUES
            (:codigo_carpeta, :tipo_archivo, :nombre_original, :nombre_interno, :extension, :mime_type, :tamanio_bytes, :ancho_px, :alto_px, :ruta_relativa, :checksum_sha256, :descripcion, :estado, :creado_por, :actualizado_por, :creado_en, :actualizado_en)');
        $stmt->execute([
            ':codigo_carpeta' => $stored['codigo_carpeta'],
            ':tipo_archivo' => $stored['tipo_archivo'],
            ':nombre_original' => $stored['nombre_original'],
            ':nombre_interno' => $stored['nombre_interno'],
            ':extension' => $stored['extension'],
            ':mime_type' => $stored['mime_type'],
            ':tamanio_bytes' => (int) $stored['tamanio_bytes'],
            ':ancho_px' => $stored['ancho_px'],
            ':alto_px' => $stored['alto_px'],
            ':ruta_relativa' => $stored['ruta_relativa'],
            ':checksum_sha256' => $stored['checksum_sha256'],
            ':descripcion' => $descripcion,
            ':estado' => $estado,
            ':creado_por' => $actorUserId,
            ':actualizado_por' => $actorUserId,
            ':creado_en' => $now,
            ':actualizado_en' => $now,
        ]);
        $archivoId = (int) $pdo->lastInsertId();

        $link = null;
        if (!empty($options['vinculo']) && is_array($options['vinculo'])) {
            $link = cb_almacen_insertar_vinculo_en_transaccion($pdo, $archivoId, $options['vinculo'], $actorUserId, $now);
        }

        if ($startedTransaction) {
            $pdo->commit();
        }

        return [
            'id' => $archivoId,
            'codigo_carpeta' => $stored['codigo_carpeta'],
            'tipo_archivo' => $stored['tipo_archivo'],
            'nombre_original' => $stored['nombre_original'],
            'nombre_interno' => $stored['nombre_interno'],
            'extension' => $stored['extension'],
            'mime_type' => $stored['mime_type'],
            'tamanio_bytes' => (int) $stored['tamanio_bytes'],
            'ancho_px' => $stored['ancho_px'],
            'alto_px' => $stored['alto_px'],
            'ruta_relativa' => $stored['ruta_relativa'],
            'checksum_sha256' => $stored['checksum_sha256'],
            'absolute_path' => $stored['absolute_path'],
            'vinculo' => $link,
        ];
    } catch (Throwable $e) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        @unlink((string) $stored['absolute_path']);
        $errors['db'] = 'No se pudo registrar el archivo en base de datos.';
        return null;
    }
}

function cb_almacen_insertar_vinculo_en_transaccion(PDO $pdo, $archivoId, array $vinculo, $actorUserId, $now)
{
    $codigoUso = cb_almacen_slug_part($vinculo['codigo_uso'] ?? '');
    $entidadTipo = cb_almacen_slug_part($vinculo['entidad_tipo'] ?? '');
    if ($codigoUso === '' || $entidadTipo === '') {
        throw new RuntimeException('Vinculo de almacen invalido.');
    }

    $entidadId = isset($vinculo['entidad_id']) && (int) $vinculo['entidad_id'] > 0 ? (int) $vinculo['entidad_id'] : null;
    $slot = cb_almacen_slug_part($vinculo['slot'] ?? '');
    $orden = isset($vinculo['orden']) ? (int) $vinculo['orden'] : 0;
    $estado = isset($vinculo['estado']) && (int) $vinculo['estado'] === 0 ? 0 : 1;
    $metadataJson = null;
    if (isset($vinculo['metadata']) && is_array($vinculo['metadata'])) {
        $encoded = json_encode($vinculo['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $metadataJson = is_string($encoded) && $encoded !== '' ? $encoded : null;
    }

    $stmt = $pdo->prepare('INSERT INTO seg_archivos_vinculos
        (archivo_id, codigo_uso, entidad_tipo, entidad_id, slot, orden, metadata_json, estado, creado_por_usuario_externo_id, actualizado_por_usuario_externo_id, creado_en, actualizado_en)
        VALUES
        (:archivo_id, :codigo_uso, :entidad_tipo, :entidad_id, :slot, :orden, :metadata_json, :estado, :creado_por, :actualizado_por, :creado_en, :actualizado_en)');
    $stmt->execute([
        ':archivo_id' => (int) $archivoId,
        ':codigo_uso' => $codigoUso,
        ':entidad_tipo' => $entidadTipo,
        ':entidad_id' => $entidadId,
        ':slot' => $slot !== '' ? $slot : null,
        ':orden' => $orden,
        ':metadata_json' => $metadataJson,
        ':estado' => $estado,
        ':creado_por' => $actorUserId,
        ':actualizado_por' => $actorUserId,
        ':creado_en' => $now,
        ':actualizado_en' => $now,
    ]);

    return [
        'id' => (int) $pdo->lastInsertId(),
        'archivo_id' => (int) $archivoId,
        'codigo_uso' => $codigoUso,
        'entidad_tipo' => $entidadTipo,
        'entidad_id' => $entidadId,
        'slot' => $slot !== '' ? $slot : null,
        'orden' => $orden,
    ];
}

function cb_almacen_obtener_archivo(PDO $pdo, $archivoId, $soloActivo = true)
{
    $archivoId = (int) $archivoId;
    if ($archivoId <= 0) {
        return null;
    }

    $sql = 'SELECT * FROM seg_archivos WHERE id = :id';
    if ($soloActivo) {
        $sql .= ' AND estado = 1';
    }
    $sql .= ' LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $archivoId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function cb_almacen_obtener_por_ruta(PDO $pdo, $rutaRelativa, $soloActivo = true)
{
    $rutaRelativa = trim((string) $rutaRelativa);
    if (!cb_almacen_is_safe_rel_path($rutaRelativa)) {
        return null;
    }

    $sql = 'SELECT * FROM seg_archivos WHERE ruta_relativa = :ruta';
    if ($soloActivo) {
        $sql .= ' AND estado = 1';
    }
    $sql .= ' LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':ruta' => $rutaRelativa]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function cb_almacen_payload_archivo(array $row)
{
    $ruta = trim((string) ($row['ruta_relativa'] ?? ''));
    if (!cb_almacen_is_safe_rel_path($ruta)) {
        return null;
    }
    $absPath = cb_almacen_abs_from_rel($ruta);
    if (!is_file($absPath) || !cb_almacen_is_under_base($absPath)) {
        return null;
    }

    return [
        'id' => (int) ($row['id'] ?? 0),
        'codigo_carpeta' => (string) ($row['codigo_carpeta'] ?? ''),
        'tipo_archivo' => (string) ($row['tipo_archivo'] ?? ''),
        'nombre_original' => (string) ($row['nombre_original'] ?? 'archivo'),
        'nombre_interno' => (string) ($row['nombre_interno'] ?? 'archivo'),
        'extension' => (string) ($row['extension'] ?? ''),
        'mime_type' => (string) ($row['mime_type'] ?? 'application/octet-stream'),
        'tamanio_bytes' => (int) ($row['tamanio_bytes'] ?? 0),
        'ruta_relativa' => $ruta,
        'absolute_path' => $absPath,
    ];
}

function cb_almacen_servir_archivo(array $payload, $inline = false)
{
    $absPath = (string) ($payload['absolute_path'] ?? '');
    if ($absPath === '' || !is_file($absPath) || !cb_almacen_is_under_base($absPath)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        echo 'Archivo no disponible.';
        exit;
    }

    $mime = trim((string) ($payload['mime_type'] ?? 'application/octet-stream'));
    if ($mime === '') {
        $mime = 'application/octet-stream';
    }

    $name = trim((string) ($payload['nombre_original'] ?? ''));
    if ($name === '') {
        $name = trim((string) ($payload['nombre_interno'] ?? 'archivo'));
    }
    if ($name === '') {
        $name = 'archivo';
    }

    header('Content-Type: ' . $mime);
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . rawurlencode($name) . '"');
    header('Content-Length: ' . (string) filesize($absPath));
    header('Cache-Control: private, max-age=86400');
    header('X-Content-Type-Options: nosniff');
    readfile($absPath);
    exit;
}

function cb_almacen_delete_file_by_row(PDO $pdo, array $row, $actorUserId, &$errors = [])
{
    $errors = [];
    $payload = cb_almacen_payload_archivo($row);
    if (!$payload) {
        $errors['archivo'] = 'Archivo no disponible en almacen.';
        return null;
    }

    $archivoId = (int) $payload['id'];
    $actorUserId = (int) $actorUserId;
    if ($actorUserId <= 0) {
        $actorUserId = null;
    }

    try {
        $pdo->beginTransaction();

        $stmtLinks = $pdo->prepare('DELETE FROM seg_archivos_vinculos WHERE archivo_id = :archivo_id');
        $stmtLinks->execute([':archivo_id' => $archivoId]);

        $stmt = $pdo->prepare('DELETE FROM seg_archivos WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $archivoId]);
        if ((int) $stmt->rowCount() <= 0) {
            $pdo->rollBack();
            $errors['db'] = 'No se pudo eliminar el registro del archivo.';
            return null;
        }

        $absPath = (string) $payload['absolute_path'];
        if (is_file($absPath) && !@unlink($absPath)) {
            $pdo->rollBack();
            $errors['storage'] = 'No se pudo eliminar fisicamente el archivo.';
            return null;
        }

        $pdo->commit();

        return [
            'id' => $archivoId,
            'ruta_relativa' => (string) $payload['ruta_relativa'],
            'eliminado_fisico' => 1,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errors['db'] = 'No se pudo eliminar el archivo.';
        return null;
    }
}

function cb_almacen_delete_by_ruta(PDO $pdo, $rutaRelativa, $actorUserId, &$errors = [])
{
    $errors = [];
    $row = cb_almacen_obtener_por_ruta($pdo, $rutaRelativa, false);
    if (!$row) {
        $errors['archivo'] = 'Archivo no encontrado en almacen.';
        return null;
    }

    return cb_almacen_delete_file_by_row($pdo, $row, $actorUserId, $errors);
}
