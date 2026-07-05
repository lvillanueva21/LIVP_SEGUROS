<?php
declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Sesión no válida']);
    exit;
}

echo json_encode([
    'ok' => true,
    'items' => $_SESSION['action_cache'] ?? [],
]);
