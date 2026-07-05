<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function deletePolicyJson(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    deletePolicyJson(405, ['ok' => false, 'message' => 'Método no permitido.']);
}

$user = requireAuth();

if (!in_array((string) ($user['role'] ?? ''), ['gerente', 'ejecutivo'], true)) {
    deletePolicyJson(403, ['ok' => false, 'message' => 'Tu perfil no tiene permiso para eliminar archivos de póliza.']);
}

$path = str_replace('\\', '/', trim((string) ($_POST['path'] ?? '')));

if ($path === '' || !str_starts_with($path, 'almacen/polizas/') || str_contains($path, '..')) {
    deletePolicyJson(422, ['ok' => false, 'message' => 'La ruta del archivo no es válida.']);
}

$projectRoot = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
$storageRoot = realpath($projectRoot . DIRECTORY_SEPARATOR . 'almacen' . DIRECTORY_SEPARATOR . 'polizas');
$target = realpath($projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));

if ($storageRoot === false || $target === false || !str_starts_with($target, $storageRoot . DIRECTORY_SEPARATOR)) {
    deletePolicyJson(404, ['ok' => false, 'message' => 'No se encontró el PDF que se debía reemplazar.']);
}

if (!is_file($target) || !unlink($target)) {
    deletePolicyJson(500, ['ok' => false, 'message' => 'No se pudo eliminar el PDF anterior. Se mantuvo el registro sin cambios.']);
}
if (is_file($target . '.meta')) {
    @unlink($target . '.meta');
}

deletePolicyJson(200, ['ok' => true, 'message' => 'PDF anterior eliminado correctamente.']);
