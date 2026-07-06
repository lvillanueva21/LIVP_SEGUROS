<?php

declare(strict_types=1);

function authReservedDemoDocuments(): array
{
    return ['12345678', '87654321'];
}

function authNormalizeDocument(string $documentType, string $document): string
{
    $documentType = strtoupper(trim($documentType));
    $document = trim($document);

    if ($documentType === 'DNI' || $documentType === 'RUC') {
        return preg_replace('/\D+/', '', $document) ?: '';
    }

    return strtoupper(preg_replace('/\s+/', '', $document) ?: '');
}

/**
 * Detecta el tipo de documento sin pedirlo en el login.
 * Regla V1: 8 dígitos = DNI, 11 dígitos = RUC y cualquier otro formato = CE.
 * Los CE numéricos de 8 u 11 caracteres son ambiguos; en esta V1 se consideran
 * DNI/RUC por longitud para conservar el acceso rápido de la maqueta.
 */
function authDetectLoginDocumentType(string $document): string
{
    $compact = trim($document);
    $onlyDigits = preg_replace('/\D+/', '', $compact) ?: '';

    if (preg_match('/^\d{8}$/', $onlyDigits) === 1) {
        return 'DNI';
    }

    if (preg_match('/^\d{11}$/', $onlyDigits) === 1) {
        return 'RUC';
    }

    return 'CE';
}

function authFindDevelopmentByDni(string $dni): ?array
{
    $statement = livpDb()->prepare(
        'SELECT
            u.id,
            u.tipo_documento,
            u.numero_documento,
            u.nombres,
            u.apellidos,
            u.nombre_mostrar,
            u.clave_hash,
            u.estado,
            u.ultimo_login_en,
            r.codigo AS rol_codigo,
            r.nombre AS rol_nombre
         FROM seg_usuarios u
         INNER JOIN seg_usuario_roles ur ON ur.id_usuario = u.id AND ur.estado = 1
         INNER JOIN seg_roles r ON r.id = ur.id_rol AND r.estado = 1
         WHERE u.tipo_documento = "DNI"
           AND u.numero_documento = :dni
           AND r.codigo = "desarrollo"
         ORDER BY ur.es_principal DESC, ur.id ASC
         LIMIT 1'
    );

    $statement->execute([':dni' => $dni]);
    $row = $statement->fetch();

    return is_array($row) ? $row : null;
}

function authDevelopmentSessionUser(array $row): array
{
    $name = trim((string) ($row['nombre_mostrar'] ?? ''));
    if ($name === '') {
        $name = trim((string) ($row['nombres'] ?? '') . ' ' . (string) ($row['apellidos'] ?? ''));
    }

    return [
        'id' => 'development-' . (int) $row['id'],
        'database_user_id' => (int) $row['id'],
        'auth_source' => 'database',
        'role' => 'desarrollo',
        'role_label' => 'Desarrollo',
        'name' => $name !== '' ? $name : 'Usuario Desarrollo',
        'document_type' => 'DNI',
        'document' => (string) ($row['numero_documento'] ?? ''),
        'account_type' => 'persona',
        'profile_title' => 'Desarrollo del sistema',
        'entity_name' => APP_NAME,
        'entity_type' => 'Entorno de desarrollo',
        'scope' => 'Inicio y configuración técnica',
        'active' => (int) ($row['estado'] ?? 0) === 1,
    ];
}

function authAttemptDevelopmentLogin(string $documentType, string $document, string $password): array
{
    if ($documentType !== 'DNI' || preg_match('/^\d{8}$/', $document) !== 1) {
        return ['status' => 'not_applicable'];
    }

    try {
        $row = authFindDevelopmentByDni($document);
    } catch (Throwable $exception) {
        error_log('[LIVP AUTH] ' . $exception->getMessage());

        return ['status' => 'database_error'];
    }

    if ($row === null) {
        return ['status' => 'not_found'];
    }

    $databaseUserId = (int) ($row['id'] ?? 0);
    if ((int) ($row['estado'] ?? 0) !== 1) {
        return ['status' => 'inactive', 'database_user_id' => $databaseUserId];
    }

    if (!password_verify($password, (string) ($row['clave_hash'] ?? ''))) {
        return ['status' => 'invalid_password', 'database_user_id' => $databaseUserId];
    }

    try {
        $statement = livpDb()->prepare('UPDATE seg_usuarios SET ultimo_login_en = NOW() WHERE id = :id');
        $statement->execute([':id' => $databaseUserId]);
    } catch (Throwable $exception) {
        error_log('[LIVP AUTH] No se pudo actualizar último acceso: ' . $exception->getMessage());
    }

    return [
        'status' => 'success',
        'database_user_id' => $databaseUserId,
        'user' => authDevelopmentSessionUser($row),
    ];
}

function authRegisterDevelopment(array $data): array
{
    $names = trim((string) ($data['nombres'] ?? ''));
    $lastNames = trim((string) ($data['apellidos'] ?? ''));
    $documentType = strtoupper(trim((string) ($data['document_type'] ?? 'DNI')));
    $document = authNormalizeDocument($documentType, (string) ($data['document'] ?? ''));
    $password = (string) ($data['password'] ?? '');
    $passwordRepeat = (string) ($data['password_repeat'] ?? '');

    if ($names === '' || $lastNames === '' || $document === '' || $password === '' || $passwordRepeat === '') {
        return ['ok' => false, 'error' => 'Completa todos los campos obligatorios.'];
    }

    if ($documentType !== 'DNI' || preg_match('/^\d{8}$/', $document) !== 1) {
        return ['ok' => false, 'error' => 'Desarrollo solo admite DNI de 8 dígitos en esta versión.'];
    }

    if (in_array($document, authReservedDemoDocuments(), true)) {
        return ['ok' => false, 'error' => 'Ese DNI está reservado para uno de los accesos demo del sistema.'];
    }

    $namesLength = function_exists('mb_strlen') ? mb_strlen($names) : strlen($names);
    $lastNamesLength = function_exists('mb_strlen') ? mb_strlen($lastNames) : strlen($lastNames);
    if ($namesLength > 120 || $lastNamesLength > 120) {
        return ['ok' => false, 'error' => 'Los nombres y apellidos no pueden superar 120 caracteres.'];
    }

    if (strlen($password) < 8) {
        return ['ok' => false, 'error' => 'La contraseña debe tener al menos 8 caracteres.'];
    }

    if (!hash_equals($password, $passwordRepeat)) {
        return ['ok' => false, 'error' => 'La repetición de contraseña no coincide.'];
    }

    try {
        $pdo = livpDb();
        $pdo->beginTransaction();

        $exists = $pdo->prepare(
            'SELECT id FROM seg_usuarios WHERE tipo_documento = "DNI" AND numero_documento = :dni LIMIT 1'
        );
        $exists->execute([':dni' => $document]);
        if ($exists->fetch()) {
            $pdo->rollBack();

            return ['ok' => false, 'error' => 'Ya existe un usuario registrado con ese DNI.'];
        }

        $roleStatement = $pdo->prepare('SELECT id FROM seg_roles WHERE codigo = "desarrollo" AND estado = 1 LIMIT 1');
        $roleStatement->execute();
        $roleId = (int) ($roleStatement->fetchColumn() ?: 0);
        if ($roleId <= 0) {
            $pdo->rollBack();

            return ['ok' => false, 'error' => 'No se encontró el rol Desarrollo. Ejecuta primero las consultas SQL indicadas.'];
        }

        $displayName = trim($names . ' ' . $lastNames);
        $insertUser = $pdo->prepare(
            'INSERT INTO seg_usuarios
             (tipo_documento, numero_documento, nombres, apellidos, nombre_mostrar, clave_hash, estado)
             VALUES
             ("DNI", :dni, :nombres, :apellidos, :nombre_mostrar, :clave_hash, 1)'
        );
        $insertUser->execute([
            ':dni' => $document,
            ':nombres' => $names,
            ':apellidos' => $lastNames,
            ':nombre_mostrar' => $displayName,
            ':clave_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);

        $userId = (int) $pdo->lastInsertId();
        $insertRole = $pdo->prepare(
            'INSERT INTO seg_usuario_roles (id_usuario, id_rol, es_principal, estado)
             VALUES (:id_usuario, :id_rol, 1, 1)'
        );
        $insertRole->execute([
            ':id_usuario' => $userId,
            ':id_rol' => $roleId,
        ]);

        $pdo->commit();

        return ['ok' => true, 'user_id' => $userId];
    } catch (Throwable $exception) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[LIVP AUTH] Registro desarrollo: ' . $exception->getMessage());

        return ['ok' => false, 'error' => 'No se pudo crear el usuario. Revisa la conexión y las tablas de autenticación.'];
    }
}

function authCurrentDatabaseSessionIsActive(): bool
{
    $user = $_SESSION['livp_user'] ?? null;
    if (!is_array($user) || ($user['auth_source'] ?? '') !== 'database') {
        return true;
    }

    $databaseUserId = (int) ($user['database_user_id'] ?? 0);
    if ($databaseUserId <= 0) {
        return false;
    }

    try {
        $statement = livpDb()->prepare(
            'SELECT u.estado
             FROM seg_usuarios u
             INNER JOIN seg_usuario_roles ur ON ur.id_usuario = u.id AND ur.estado = 1
             INNER JOIN seg_roles r ON r.id = ur.id_rol AND r.estado = 1
             WHERE u.id = :id AND r.codigo = "desarrollo"
             LIMIT 1'
        );
        $statement->execute([':id' => $databaseUserId]);
        $state = $statement->fetchColumn();

        return (int) $state === 1;
    } catch (Throwable $exception) {
        error_log('[LIVP AUTH] Validación de sesión: ' . $exception->getMessage());

        return false;
    }
}
