<?php
declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Sesión no válida']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Método no permitido']);
    exit;
}

$action = trim((string) ($_POST['action'] ?? ''));
$section = trim((string) ($_POST['section'] ?? ''));

if ($action === '' || $section === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Datos incompletos']);
    exit;
}

$entry = [
    'action' => sliceText($action, 160),
    'section' => sliceText($section, 80),
    'at' => date('Y-m-d H:i:s'),
];

$_SESSION['action_cache'] ??= [];
array_unshift($_SESSION['action_cache'], $entry);
$_SESSION['action_cache'] = array_slice($_SESSION['action_cache'], 0, 20);

echo json_encode(['ok' => true, 'entry' => $entry]);
