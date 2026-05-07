<?php
require_once __DIR__ . '/config_cliente.php';

function cb_cliente_db()
{
    static $pdo = null;
    static $attempted = false;

    if (!CLIENTE_DB_ACTIVA) {
        return null;
    }

    if ($attempted) {
        return $pdo;
    }

    $attempted = true;

    $host = trim((string) CLIENTE_DB_HOST);
    $dbName = trim((string) CLIENTE_DB_NAME);
    $charset = trim((string) CLIENTE_DB_CHARSET);

    if ($host === '' || $dbName === '' || $charset === '') {
        throw new RuntimeException('Configuración de BD local incompleta.');
    }

    $dsn = 'mysql:host=' . $host . ';dbname=' . $dbName . ';charset=' . $charset;
    $pdo = new PDO($dsn, (string) CLIENTE_DB_USER, (string) CLIENTE_DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

