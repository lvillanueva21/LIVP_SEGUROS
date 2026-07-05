<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';
$user = requireAuth();
header('Content-Type: application/json; charset=utf-8');

function responseJson(bool $ok, string $message, int $status = 200): never {
    http_response_code($status);
    echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!in_array((string) ($user['role'] ?? ''), ['gerente', 'ejecutivo'], true)) {
    responseJson(false, 'No tienes permiso para retirar documentos internos.', 403);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responseJson(false, 'Método no permitido.', 405);
}
$path = str_replace('\\', '/', (string) ($_POST['path'] ?? ''));
if (!preg_match('#^expedientes/\d{4}/\d{2}/\d{2}/[A-Za-z0-9_-]+\.(pdf|png|jpg|webp)$#', $path)) {
    responseJson(false, 'Documento no disponible.', 404);
}
$base = realpath(__DIR__ . '/../almacen');
$file = $base ? realpath($base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path)) : false;
if ($file === false || $base === false || !str_starts_with($file, $base . DIRECTORY_SEPARATOR) || !is_file($file)) {
    responseJson(false, 'Documento no disponible.', 404);
}
if (!unlink($file)) {
    responseJson(false, 'No se pudo retirar el archivo.', 500);
}
responseJson(true, 'Archivo retirado correctamente.');