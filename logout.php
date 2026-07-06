<?php

declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';

sendNoCacheHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrfValidate('logout_form', (string) ($_POST['csrf_token'] ?? ''))) {
    header('Location: ' . appRelativeUrl('index.php?m=sesion'));
    exit;
}

destroyCurrentSession();

header('Location: ' . appRelativeUrl('index.php?m=logout'));
exit;
