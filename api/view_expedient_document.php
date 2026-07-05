<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';
$user = requireAuth();

if (!in_array((string) ($user['role'] ?? ''), ['gerente', 'ejecutivo'], true)) {
    http_response_code(403);
    exit('No tienes permiso para ver este documento.');
}
$path = str_replace('\\', '/', (string) ($_GET['path'] ?? ''));
if (!preg_match('#^expedientes/\d{4}/\d{2}/\d{2}/[A-Za-z0-9_-]+\.(pdf|png|jpg|webp)$#', $path)) {
    http_response_code(404);
    exit('Documento no disponible.');
}
$base = realpath(__DIR__ . '/../almacen');
$file = $base ? realpath($base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path)) : false;
if ($file === false || $base === false || !str_starts_with($file, $base . DIRECTORY_SEPARATOR) || !is_file($file)) {
    http_response_code(404);
    exit('Documento no disponible.');
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file);
if (!in_array($mime, ['application/pdf', 'image/png', 'image/jpeg', 'image/webp'], true)) {
    http_response_code(404);
    exit('Documento no disponible.');
}
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($file));
header('Cache-Control: private, max-age=3600');
readfile($file);