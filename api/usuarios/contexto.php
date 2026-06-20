<?php
require_once __DIR__ . '/_usuarios_master_client.php';

usu_require_action('puede_ver', 'GET', false);
$data = usu_master_call('contexto.php');
$data['csrf'] = cb_local_csrf_token(usu_scope());
cb_json_success('Contexto cargado correctamente.', $data);
