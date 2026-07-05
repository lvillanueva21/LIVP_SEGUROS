<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

$user = requireAuth();
$role = (string) ($user['role'] ?? '');

if (!in_array($role, ['gerente', 'ejecutivo', 'cliente'], true)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'No tienes permiso para consultar este documento.';
    exit;
}

$path = str_replace('\\', '/', trim((string) ($_GET['path'] ?? '')));

if ($path === '' || !str_starts_with($path, 'almacen/polizas/') || str_contains($path, '..')) {
    http_response_code(422);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Ruta de documento no válida.';
    exit;
}

$projectRoot = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
$storageRoot = realpath($projectRoot . DIRECTORY_SEPARATOR . 'almacen' . DIRECTORY_SEPARATOR . 'polizas');
$filePath = realpath($projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));

if ($storageRoot === false || $filePath === false || !str_starts_with($filePath, $storageRoot . DIRECTORY_SEPARATOR) || !is_file($filePath)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Documento no encontrado.';
    exit;
}

if ($role === 'cliente') {
    $metaPath = $filePath . '.meta';
    $meta = is_file($metaPath) ? parse_ini_file($metaPath, false, INI_SCANNER_RAW) : false;
    $published = is_array($meta) && (string) ($meta['published_for_client'] ?? '') === '1';
    $ownerDocument = is_array($meta) ? preg_replace('/\D+/', '', (string) ($meta['client_document'] ?? '')) : '';
    $sessionDocument = preg_replace('/\D+/', '', (string) ($user['document'] ?? ''));

    if (!$published || $ownerDocument === '' || $sessionDocument === '' || !hash_equals($ownerDocument, $sessionDocument)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Este documento no está autorizado para esta cuenta.';
        exit;
    }
}

header('Content-Type: application/pdf');
header('Content-Length: ' . (string) filesize($filePath));
header('Content-Disposition: inline; filename="' . rawurlencode(basename($filePath)) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');

readfile($filePath);
exit;
