<?php
declare(strict_types=1);

require_once __DIR__ . '/_expedientes_common.php';

try {
    exp_require_method('GET');
    exp_require_perm('puede_ver');

    $expedienteId = (int) ($_GET['expediente_id'] ?? 0);
    if ($expedienteId <= 0) {
        exp_json_error('Expediente no valido.', 422);
    }

    $pdo = exp_db();
    $stmtExp = $pdo->prepare('SELECT id FROM seg_expedientes WHERE id = :id LIMIT 1');
    $stmtExp->execute([':id' => $expedienteId]);
    if (!$stmtExp->fetchColumn()) {
        exp_json_error('Expediente no encontrado.', 404);
    }

    $stmt = $pdo->prepare("SELECT
            id,
            entidad_tipo,
            entidad_id,
            codigo_evento,
            descripcion,
            actor_usuario_externo_id,
            fecha_evento,
            metadata_json
        FROM seg_timeline_eventos
        WHERE entidad_tipo = 'expediente'
          AND entidad_id = :id
        ORDER BY fecha_evento DESC, id DESC");
    $stmt->execute([':id' => $expedienteId]);
    exp_json_success(['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (PDOException $e) {
    exp_db_error($e);
} catch (Throwable $e) {
    exp_db_error($e);
}
