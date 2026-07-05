<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../config/client_accounts.php';

$user = requireAuth();

header('Content-Type: application/json; charset=UTF-8');

function portalPublishResponse(bool $ok, string $message, array $extra = [], int $status = 200): never
{
    http_response_code($status);
    echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ((string) ($user['role'] ?? '') !== 'gerente') {
    portalPublishResponse(false, 'Solo Gerencia puede publicar la cartera para Clientes.', [], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    portalPublishResponse(false, 'Método no permitido.', [], 405);
}

$raw = (string) ($_POST['state'] ?? '');
if ($raw === '' || strlen($raw) > 6 * 1024 * 1024) {
    portalPublishResponse(false, 'El estado de cartera es inválido o demasiado grande.', [], 422);
}

$state = json_decode($raw, true);
if (!is_array($state)) {
    portalPublishResponse(false, 'No se pudo interpretar la cartera enviada.', [], 422);
}

$entities = is_array($state['entities'] ?? null) ? $state['entities'] : null;
$expedients = is_array($state['expedients'] ?? null) ? $state['expedients'] : null;

if ($entities === null || $expedients === null) {
    portalPublishResponse(false, 'La cartera debe incluir entidades y expedientes.', [], 422);
}

$publishedAt = (new DateTimeImmutable('now', new DateTimeZone('America/Lima')))->format('Y-m-d H:i:s');
$stored = [
    'published_at' => $publishedAt,
    'published_by' => (string) ($user['name'] ?? ''),
    'entities' => $entities,
    'expedients' => $expedients,
];

if (!clientWriteJsonFile(portalStateStorePath(), $stored)) {
    portalPublishResponse(false, 'No se pudo guardar la cartera publicada.', [], 500);
}

portalPublishResponse(true, 'Cartera publicada para los portales Cliente.', [
    'published_at' => $publishedAt,
    'entity_count' => count((array) ($entities['companies'] ?? [])) + count((array) ($entities['consortia'] ?? [])),
    'expedient_count' => count($expedients),
]);
