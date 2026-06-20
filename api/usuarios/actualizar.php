<?php
require_once __DIR__ . '/_usuarios_master_client.php';

usu_require_action('puede_editar');
$payload = usu_payload();
$data = usu_master_call('actualizar.php', [
    'id_usuario_externo' => (string) ($payload['id_usuario_externo'] ?? ''),
    'tipo_documento' => (string) ($payload['tipo_documento'] ?? ''),
    'numero_documento' => (string) ($payload['numero_documento'] ?? ''),
    'nombres' => (string) ($payload['nombres'] ?? ''),
    'apellidos' => (string) ($payload['apellidos'] ?? ''),
    'id_rol' => (string) ($payload['id_rol'] ?? ''),
]);
cb_json_success('Usuario actualizado correctamente.', $data);
