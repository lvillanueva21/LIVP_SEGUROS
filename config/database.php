<?php

declare(strict_types=1);

/*
 * EDITAR UNA SOLA VEZ ANTES DE SUBIR.
 * Estos valores son los de la base MySQL creada en Hostinger/phpMyAdmin.
 */
const LIVP_DB_HOST = 'localhost';
const LIVP_DB_NAME = 'u517204426_hm_Br0K3RS3gvr';
const LIVP_DB_USER = 'u517204426_hm_L31V4_Bs';
const LIVP_DB_PASS = 'jZxm6^AVm!0';
const LIVP_DB_CHARSET = 'utf8mb4';

function livpDb(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . LIVP_DB_HOST . ';dbname=' . LIVP_DB_NAME . ';charset=' . LIVP_DB_CHARSET;

    try {
        $pdo = new PDO($dsn, LIVP_DB_USER, LIVP_DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '-05:00'");
    } catch (PDOException $exception) {
        error_log('[LIVP DB] ' . $exception->getMessage());
        throw new RuntimeException('No se pudo conectar con la base de datos.');
    }

    return $pdo;
}
