<?php
require_once __DIR__ . '/helpers.php';

function cb_json_success($message, $data = [], $httpStatus = 200)
{
    $httpStatus = (int) $httpStatus;
    if ($httpStatus < 200 || $httpStatus > 299) {
        $httpStatus = 200;
    }

    cb_json_response($httpStatus, [
        'ok' => true,
        'code' => 'ok',
        'message' => (string) $message,
        'data' => is_array($data) ? $data : [],
        'errors' => [],
    ]);
}

function cb_json_error($code, $message, $httpStatus = 400, $errors = [])
{
    $httpStatus = (int) $httpStatus;
    if ($httpStatus < 400 || $httpStatus > 599) {
        $httpStatus = 400;
    }

    cb_json_response($httpStatus, [
        'ok' => false,
        'code' => preg_match('/^[a-z0-9_-]+$/', (string) $code) === 1 ? (string) $code : 'error',
        'message' => (string) $message,
        'data' => [],
        'errors' => is_array($errors) ? $errors : [],
    ]);
}

function cb_request_method()
{
    return strtoupper(trim((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')));
}

function cb_require_method($methodOrMethods)
{
    $allowed = is_array($methodOrMethods) ? $methodOrMethods : [$methodOrMethods];
    $normalized = [];
    foreach ($allowed as $method) {
        $method = strtoupper(trim((string) $method));
        if ($method !== '' && preg_match('/^[A-Z]+$/', $method) === 1) {
            $normalized[] = $method;
        }
    }
    $normalized = array_values(array_unique($normalized));
    if (!$normalized) {
        cb_json_error('metodo_no_configurado', 'Metodo HTTP no configurado.', 500);
    }

    if (!in_array(cb_request_method(), $normalized, true)) {
        header('Allow: ' . implode(', ', $normalized));
        cb_json_error('metodo_no_permitido', 'Metodo HTTP no permitido.', 405);
    }
}

function cb_normalize_csrf_scope($scope)
{
    $scope = strtolower(trim((string) $scope));
    return preg_match('/^[a-z0-9_-]{3,80}$/', $scope) === 1 ? $scope : '';
}

function cb_local_csrf_token($scope)
{
    cb_boot_session();
    $scope = cb_normalize_csrf_scope($scope);
    if ($scope === '') {
        return '';
    }

    if (!isset($_SESSION['cliente_csrf']) || !is_array($_SESSION['cliente_csrf'])) {
        $_SESSION['cliente_csrf'] = [];
    }
    if (empty($_SESSION['cliente_csrf'][$scope]) || !is_string($_SESSION['cliente_csrf'][$scope])) {
        $_SESSION['cliente_csrf'][$scope] = cb_random_token(32);
    }

    return (string) $_SESSION['cliente_csrf'][$scope];
}

function cb_validate_local_csrf($scope, $token)
{
    cb_boot_session();
    $scope = cb_normalize_csrf_scope($scope);
    $token = (string) $token;
    if ($scope === '' || $token === '') {
        return false;
    }

    $stored = isset($_SESSION['cliente_csrf'][$scope]) && is_string($_SESSION['cliente_csrf'][$scope])
        ? $_SESSION['cliente_csrf'][$scope]
        : '';
    if ($stored === '') {
        return false;
    }

    return hash_equals($stored, $token);
}

function cb_request_payload()
{
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if (strpos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $decoded = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : [];
        return is_array($decoded) ? $decoded : [];
    }

    if ($_POST) {
        return $_POST;
    }

    $raw = file_get_contents('php://input');
    if (is_string($raw) && trim($raw) !== '') {
        $parsed = [];
        parse_str($raw, $parsed);
        return is_array($parsed) ? $parsed : [];
    }

    return [];
}

function cb_extract_csrf_token(array $payload = null)
{
    if ($payload === null) {
        $payload = cb_request_payload();
    }

    $token = trim((string) ($payload['_csrf'] ?? ($payload['csrf_token'] ?? '')));
    if ($token !== '') {
        return $token;
    }

    return trim((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
}

function cb_require_local_csrf($scope)
{
    $payload = cb_request_payload();
    if (!cb_validate_local_csrf($scope, cb_extract_csrf_token($payload))) {
        cb_json_error('csrf_invalido', 'Token de seguridad invalido o expirado.', 403);
    }
}
