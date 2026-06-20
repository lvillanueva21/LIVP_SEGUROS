<?php
require_once __DIR__ . '/_catalogos_common.php';

cb_require_method('GET');
cat_require_catalogos('puede_ver');

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    echo 'Logo no disponible.';
    exit;
}

$pdo = cat_db();
if (!cat_logo_table_exists($pdo)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    echo 'Logo no disponible.';
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT l.mime_type, l.tamanio_bytes, l.ruta_relativa
        FROM seg_aseguradora_logo_archivos l
        INNER JOIN seg_aseguradoras a ON a.id = l.aseguradora_id
        WHERE l.aseguradora_id = :id
        LIMIT 1');
    $stmt->execute([':id' => $id]);
    $logo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$logo || !in_array((string) $logo['mime_type'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        echo 'Logo no disponible.';
        exit;
    }
    $absPath = cat_abs_from_rel((string) $logo['ruta_relativa']);
    if (!is_file($absPath) || !cat_is_safe_storage_path($absPath)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        echo 'Logo no disponible.';
        exit;
    }

    header('Content-Type: ' . $logo['mime_type']);
    header('Content-Length: ' . (int) filesize($absPath));
    header('Cache-Control: private, max-age=86400');
    header('X-Content-Type-Options: nosniff');
    readfile($absPath);
    exit;
} catch (Throwable $e) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    echo 'Logo no disponible.';
    exit;
}
