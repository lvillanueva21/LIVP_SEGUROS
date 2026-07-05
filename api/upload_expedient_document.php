<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';
$user = requireAuth();
header('Content-Type: application/json; charset=utf-8');

function responseJson(bool $ok, string $message, array $extra = [], int $status = 200): never {
    http_response_code($status);
    echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!in_array((string) ($user['role'] ?? ''), ['gerente', 'ejecutivo'], true)) {
    responseJson(false, 'No tienes permiso para adjuntar documentos internos.', [], 403);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responseJson(false, 'Método no permitido.', [], 405);
}
if (!isset($_FILES['document']) || !is_array($_FILES['document'])) {
    responseJson(false, 'Selecciona un archivo antes de continuar.', [], 422);
}

$file = $_FILES['document'];
if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $messages = [
        UPLOAD_ERR_INI_SIZE => 'El archivo excede el límite permitido por el servidor.',
        UPLOAD_ERR_FORM_SIZE => 'El archivo excede el límite permitido.',
        UPLOAD_ERR_PARTIAL => 'El archivo se cargó de forma incompleta.',
        UPLOAD_ERR_NO_FILE => 'No se recibió un archivo.',
    ];
    responseJson(false, $messages[(int) ($file['error'] ?? 0)] ?? 'No se pudo recibir el archivo.', [], 422);
}
if ((int) ($file['size'] ?? 0) <= 0 || (int) $file['size'] > 7 * 1024 * 1024) {
    responseJson(false, 'El archivo debe pesar como máximo 7 MB.', [], 422);
}

$tmp = (string) ($file['tmp_name'] ?? '');
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
$extensions = [
    'application/pdf' => 'pdf',
    'image/png' => 'png',
    'image/jpeg' => 'jpg',
    'image/webp' => 'webp',
];
if (!isset($extensions[$mime])) {
    responseJson(false, 'Formato no permitido. Usa PDF, PNG, JPG o WEBP.', [], 422);
}

$now = new DateTimeImmutable('now', new DateTimeZone('America/Lima'));
$base = realpath(__DIR__ . '/../almacen');
if ($base === false) {
    responseJson(false, 'No se encontró la carpeta de almacenamiento.', [], 500);
}
$folder = 'expedientes/' . $now->format('Y/m/d');
$targetDirectory = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $folder);
if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
    responseJson(false, 'No se pudo preparar la carpeta de almacenamiento.', [], 500);
}

$reference = preg_replace('/[^a-z0-9_-]/i', '-', (string) ($_POST['expedient_code'] ?? 'expediente'));
$reference = trim((string) $reference, '-') ?: 'expediente';
$name = $reference . '-' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
$target = $targetDirectory . DIRECTORY_SEPARATOR . $name;
if (!move_uploaded_file($tmp, $target)) {
    responseJson(false, 'No se pudo guardar el archivo.', [], 500);
}

responseJson(true, 'Archivo guardado correctamente.', [
    'file' => [
        'relative_path' => $folder . '/' . $name,
        'original_name' => (string) ($file['name'] ?? ''),
        'internal_name' => $name,
        'mime' => $mime,
        'size_bytes' => (int) $file['size'],
        'uploaded_at' => $now->format('Y-m-d H:i:s'),
        'uploaded_by' => (string) ($user['name'] ?? ''),
    ],
]);