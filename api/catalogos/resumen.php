<?php
require_once __DIR__ . '/_catalogos_common.php';

cb_require_method('GET');
cat_require_catalogos('puede_ver');

$pdo = cat_db();

try {
    $data = [
        'aseguradoras_activas' => (int) $pdo->query('SELECT COUNT(*) FROM seg_aseguradoras WHERE estado = 1')->fetchColumn(),
        'ramos_activos' => (int) $pdo->query('SELECT COUNT(*) FROM seg_ramos WHERE estado = 1')->fetchColumn(),
        'productos_activos' => (int) $pdo->query('SELECT COUNT(*) FROM seg_productos WHERE estado = 1')->fetchColumn(),
    ];
    cb_json_success('Resumen cargado correctamente.', $data);
} catch (Throwable $e) {
    cat_db_error();
}
