<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../config/client_accounts.php';

$user = requireAuth();
header('Content-Type: application/json; charset=UTF-8');

if (!in_array((string) ($user['role'] ?? ''), ['gerente', 'ejecutivo'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'No tienes permiso para actualizar esta cartera.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$state = clientReadJsonFile(portalStateStorePath(), []);
if (!is_array($state['entities'] ?? null) || !is_array($state['expedients'] ?? null)) {
    $defaults = require __DIR__ . '/../config/demo_expedients.php';
    $state = [
        'published_at' => '',
        'entities' => require __DIR__ . '/../config/demo_clients.php',
        'expedients' => is_array($defaults['items'] ?? null) ? $defaults['items'] : [],
    ];
}

echo json_encode([
    'ok' => true,
    'message' => 'Cartera publicada disponible.',
    'published_at' => (string) ($state['published_at'] ?? ''),
    'entities' => $state['entities'],
    'expedients' => $state['expedients'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
