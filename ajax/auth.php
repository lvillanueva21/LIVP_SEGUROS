<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'logout') {
    demo_logout_user();
    demo_json(true, [
        'title' => 'Sesión cerrada',
        'message' => 'Se cerró tu sesión correctamente.',
        'redirect' => demo_url('index.php'),
        'name' => null,
        'role' => null,
    ]);
}

if ($action !== 'login') {
    demo_json(false, [
        'title' => 'Solicitud no válida',
        'message' => 'La acción solicitada no está permitida.',
    ], 400);
}

$username = trim((string)($_POST['username'] ?? ''));
$password = trim((string)($_POST['password'] ?? ''));

if ($username === '' || $password === '') {
    demo_json(false, [
        'title' => 'Datos incompletos',
        'message' => 'Ingresa usuario y contraseña para continuar.',
        'field' => 'credentials',
    ], 422);
}

$user = demo_find_user_by_username($username);

if (!$user) {
    demo_json(false, [
        'title' => 'Acceso denegado',
        'message' => 'El usuario ingresado no existe en la demo.',
        'field' => 'username',
    ], 401);
}

if (($user['status'] ?? 'inactivo') !== 'activo') {
    demo_json(false, [
        'title' => 'Usuario inactivo',
        'message' => 'Esta cuenta está inactiva y no puede ingresar.',
    ], 403);
}

$validPassword = false;

if ($password === ($user['password'] ?? '')) {
    $validPassword = true;
}

if ($password === ($user['username'] ?? '')) {
    $validPassword = true;
}

if (!$validPassword) {
    demo_json(false, [
        'title' => 'Credenciales inválidas',
        'message' => 'La contraseña no coincide con el usuario demo.',
        'field' => 'password',
    ], 401);
}

if (function_exists('session_regenerate_id')) {
    @session_regenerate_id(true);
}

demo_login_user($user);

demo_push_toast(
    'Hola, ' . ($user['full_name'] ?? 'usuario') . '. Te damos la bienvenida al sistema.',
    'success',
    'Bienvenido'
);

demo_json(true, [
    'suppressToast' => true,
    'redirect' => demo_url('home.php'),
    'name' => $user['full_name'] ?? '',
    'role' => $user['role'] ?? '',
    'default_route' => demo_url(demo_default_route($user['role'] ?? 'cliente')),
]);