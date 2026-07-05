<?php
declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';

sendNoCacheHeaders();
destroyCurrentSession();

header('Location: ' . appRelativeUrl('index.php'));
exit;
