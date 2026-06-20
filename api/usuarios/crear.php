<?php
require_once __DIR__ . '/_usuarios_master_client.php';

usu_require_action('puede_crear');
$payload = usu_payload();
$data = usu_master_call('crear.php', [
    'tipo_documento' => (string) ($payload['tipo_documento'] ?? ''),
    'numero_documento' => (string) ($payload['numero_documento'] ?? ''),
    'nombres' => (string) ($payload['nombres'] ?? ''),
    'apellidos' => (string) ($payload['apellidos'] ?? ''),
    'clave' => (string) ($payload['clave'] ?? ''),
    'clave_confirmar' => (string) ($payload['clave_confirmar'] ?? ''),
    'id_rol' => (string) ($payload['id_rol'] ?? ''),
]);
cb_json_success('Usuario creado correctamente.', $data, 201);
