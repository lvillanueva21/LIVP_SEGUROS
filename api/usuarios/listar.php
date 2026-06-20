<?php
require_once __DIR__ . '/_usuarios_master_client.php';

usu_require_action('puede_ver', 'GET', false);
$payload = [
    'page' => (string) ($_GET['page'] ?? '1'),
    'per_page' => (string) ($_GET['per_page'] ?? '10'),
    'q' => (string) ($_GET['q'] ?? ''),
    'estado' => (string) ($_GET['estado'] ?? 'todos'),
    'id_rol' => (string) ($_GET['id_rol'] ?? '0'),
];
$data = usu_master_call('listar.php', $payload);
cb_json_success('Usuarios cargados correctamente.', $data);
