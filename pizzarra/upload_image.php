<?php
// upload_image.php - PIZZARRA v1
// Sube una imagen a almacen/año/mes/día y devuelve JSON.
header('Content-Type: application/json; charset=utf-8');

function pz_json($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function pz_safe_random_name($extension) {
    try {
        $base = bin2hex(random_bytes(12));
    } catch (Exception $e) {
        $base = uniqid('pz_', true);
        $base = str_replace('.', '_', $base);
    }
    return $base . '.' . $extension;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    pz_json(array('ok' => false, 'message' => 'Método no permitido.'), 405);
}

if (!isset($_FILES['imagen'])) {
    pz_json(array('ok' => false, 'message' => 'No se recibió ninguna imagen.'), 400);
}

$file = $_FILES['imagen'];

if (!isset($file['error']) || is_array($file['error'])) {
    pz_json(array('ok' => false, 'message' => 'Carga inválida.'), 400);
}

if ($file['error'] !== UPLOAD_ERR_OK) {
    pz_json(array('ok' => false, 'message' => 'Error al subir la imagen.', 'code' => $file['error']), 400);
}

$maxBytes = 8 * 1024 * 1024;
if ($file['size'] > $maxBytes) {
    pz_json(array('ok' => false, 'message' => 'La imagen supera el límite de 8 MB.'), 400);
}

$tmp = $file['tmp_name'];
if (!is_uploaded_file($tmp)) {
    pz_json(array('ok' => false, 'message' => 'Archivo temporal no válido.'), 400);
}

$info = @getimagesize($tmp);
if ($info === false || !isset($info['mime'])) {
    pz_json(array('ok' => false, 'message' => 'El archivo no parece ser una imagen válida.'), 400);
}

$allowed = array(
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp'
);

if (!isset($allowed[$info['mime']])) {
    pz_json(array('ok' => false, 'message' => 'Formato no permitido. Usa JPG, PNG, GIF o WEBP.'), 400);
}

$year = date('Y');
$month = date('m');
$day = date('d');
$relativeDir = 'almacen/' . $year . '/' . $month . '/' . $day;
$targetDir = __DIR__ . DIRECTORY_SEPARATOR . 'almacen' . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $month . DIRECTORY_SEPARATOR . $day;

if (!is_dir($targetDir)) {
    if (!mkdir($targetDir, 0755, true)) {
        pz_json(array('ok' => false, 'message' => 'No se pudo crear la carpeta de almacenamiento.'), 500);
    }
}

$extension = $allowed[$info['mime']];
$fileName = pz_safe_random_name($extension);
$targetPath = $targetDir . DIRECTORY_SEPARATOR . $fileName;

if (!move_uploaded_file($tmp, $targetPath)) {
    pz_json(array('ok' => false, 'message' => 'No se pudo guardar la imagen.'), 500);
}

@chmod($targetPath, 0644);

$url = $relativeDir . '/' . $fileName;
pz_json(array(
    'ok' => true,
    'message' => 'Imagen subida correctamente.',
    'url' => $url,
    'path' => $url,
    'name' => $fileName,
    'mime' => $info['mime'],
    'size' => (int)$file['size']
));
