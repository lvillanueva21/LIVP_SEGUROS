<?php
require_once __DIR__ . '/_usuarios_master_client.php';

usu_require_action('puede_eliminar');
$payload = usu_payload();
$data = usu_master_call('cambiar_estado.php', [
    'id_usuario_externo' => (string) ($payload['id_usuario_externo'] ?? ''),
    'estado_objetivo' => (string) ($payload['estado_objetivo'] ?? '0'),
]);
cb_json_success((int) ($data['estado'] ?? 0) === 1 ? 'Acceso activado correctamente.' : 'Acceso desactivado correctamente.', $data);
