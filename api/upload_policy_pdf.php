<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function policyJson(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function policyUploadLimitBytes(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }

    $unit = strtolower(substr($value, -1));
    $number = (float) $value;

    return match ($unit) {
        'g' => (int) ($number * 1024 * 1024 * 1024),
        'm' => (int) ($number * 1024 * 1024),
        'k' => (int) ($number * 1024),
        default => (int) $number,
    };
}

function policySafeFolder(string $value): string
{
    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: '';
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?: '';
    $value = trim($value, '-');

    return $value !== '' ? substr($value, 0, 80) : 'sin-tipo';
}

function policySafeCode(string $value): string
{
    $value = strtoupper(preg_replace('/[^A-Za-z0-9-]+/', '', $value) ?: '');

    return $value !== '' ? substr($value, 0, 40) : 'POLIZA';
}

function policyCurrentUser(): array
{
    $user = requireAuth();
    $role = (string) ($user['role'] ?? '');

    if (!in_array($role, ['gerente', 'ejecutivo'], true)) {
        policyJson(403, [
            'ok' => false,
            'message' => 'Tu perfil no tiene permiso para adjuntar documentos de pólizas.',
        ]);
    }

    return $user;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    policyJson(405, [
        'ok' => false,
        'message' => 'Método no permitido para subir el PDF.',
    ]);
}

$user = policyCurrentUser();

if (!isset($_FILES['pdf'])) {
    $postLimit = policyUploadLimitBytes((string) ini_get('post_max_size'));
    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);

    if ($postLimit > 0 && $contentLength > $postLimit) {
        policyJson(413, [
            'ok' => false,
            'message' => 'El PDF supera el máximo permitido actualmente por el servidor. El archivo no fue guardado.',
            'server_limit_bytes' => $postLimit,
        ]);
    }

    policyJson(422, [
        'ok' => false,
        'message' => 'No se recibió ningún PDF. Verifica la conexión e intenta nuevamente.',
    ]);
}

$file = $_FILES['pdf'];
$error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

if ($error !== UPLOAD_ERR_OK) {
    $messages = [
        UPLOAD_ERR_INI_SIZE => 'El PDF supera el máximo permitido actualmente por el servidor. El archivo no fue guardado.',
        UPLOAD_ERR_FORM_SIZE => 'El PDF supera el máximo permitido por el formulario. El archivo no fue guardado.',
        UPLOAD_ERR_PARTIAL => 'El PDF se subió solo parcialmente. Revisa la conexión e intenta nuevamente.',
        UPLOAD_ERR_NO_FILE => 'No se seleccionó ningún PDF para subir.',
        UPLOAD_ERR_NO_TMP_DIR => 'El servidor no tiene carpeta temporal disponible para recibir el PDF.',
        UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo escribir el PDF. Revisa permisos de la carpeta almacen.',
        UPLOAD_ERR_EXTENSION => 'La carga fue detenida por una extensión del servidor.',
    ];

    policyJson(422, [
        'ok' => false,
        'message' => $messages[$error] ?? 'No se pudo cargar el PDF por un error del servidor.',
        'upload_error' => $error,
    ]);
}

$tmpName = (string) ($file['tmp_name'] ?? '');
$originalName = basename((string) ($file['name'] ?? ''));

if ($tmpName === '' || !is_uploaded_file($tmpName)) {
    policyJson(422, [
        'ok' => false,
        'message' => 'El archivo recibido no es válido. Intenta seleccionar el PDF nuevamente.',
    ]);
}

if (!preg_match('/\.pdf$/i', $originalName)) {
    policyJson(422, [
        'ok' => false,
        'message' => 'Solo se permiten archivos PDF.',
    ]);
}

$header = file_get_contents($tmpName, false, null, 0, 5);

if ($header !== '%PDF-') {
    policyJson(422, [
        'ok' => false,
        'message' => 'El archivo no contiene una firma PDF válida. Selecciona un PDF real.',
    ]);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = (string) $finfo->file($tmpName);

if (!in_array($mime, ['application/pdf', 'application/x-pdf', 'application/octet-stream'], true)) {
    policyJson(422, [
        'ok' => false,
        'message' => 'El servidor no reconoció el archivo como PDF.',
    ]);
}

$policyCode = policySafeCode((string) ($_POST['policy_code'] ?? ''));
$typeFolder = policySafeFolder((string) ($_POST['insurance_type'] ?? ''));
$clientDocument = preg_replace('/\D+/', '', (string) ($_POST['client_document'] ?? '')) ?: '';
$now = new DateTimeImmutable('now', new DateTimeZone('America/Lima'));

$relativeDirectory = sprintf(
    'almacen/polizas/%s/%s/%s/%s',
    $typeFolder,
    $now->format('Y'),
    $now->format('m'),
    $now->format('d')
);

$projectRoot = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
$absoluteDirectory = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);

if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0755, true) && !is_dir($absoluteDirectory)) {
    policyJson(500, [
        'ok' => false,
        'message' => 'No se pudo preparar la carpeta de almacenamiento para el PDF. Revisa permisos de almacen.',
    ]);
}

try {
    $random = bin2hex(random_bytes(12));
} catch (Throwable) {
    $random = str_replace('.', '', uniqid('', true));
}

$internalName = $policyCode . '_' . $random . '.pdf';
$absolutePath = $absoluteDirectory . DIRECTORY_SEPARATOR . $internalName;

if (!move_uploaded_file($tmpName, $absolutePath)) {
    policyJson(500, [
        'ok' => false,
        'message' => 'El servidor recibió el PDF, pero no pudo guardarlo. Revisa permisos y espacio disponible.',
    ]);
}

@chmod($absolutePath, 0640);

// El archivo auxiliar permite dar acceso al PDF al Cliente correcto sin exponerlo por URL directa.
// Si no hay documento asociado, el PDF queda disponible únicamente para Gerente/Ejecutivo.
$meta = implode("\n", [
    'client_document=' . $clientDocument,
    'published_for_client=' . ($clientDocument !== '' ? '1' : '0'),
    'uploaded_at=' . $now->format('Y-m-d H:i:s'),
]) . "\n";

if (file_put_contents($absolutePath . '.meta', $meta, LOCK_EX) === false) {
    @unlink($absolutePath);
    policyJson(500, [
        'ok' => false,
        'message' => 'No se pudo registrar el permiso del documento. El PDF no fue conservado.',
    ]);
}

policyJson(201, [
    'ok' => true,
    'message' => 'PDF guardado correctamente.',
    'file' => [
        'relative_path' => $relativeDirectory . '/' . $internalName,
        'original_name' => $originalName,
        'internal_name' => $internalName,
        'mime' => 'application/pdf',
        'size_bytes' => (int) ($file['size'] ?? 0),
        'uploaded_at' => $now->format('Y-m-d H:i:s'),
        'uploaded_by' => (string) ($user['name'] ?? $user['id'] ?? 'Usuario interno'),
    ],
]);
