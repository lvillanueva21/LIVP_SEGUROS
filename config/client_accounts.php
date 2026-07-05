<?php
declare(strict_types=1);

/*
 * Persistencia ligera de accesos Cliente para la maqueta.
 * No reemplaza MySQL ni un sistema real de identidad.
 */

function clientAccountStorePath(): string
{
    return __DIR__ . '/../almacen/client_accounts.json';
}

function portalStateStorePath(): string
{
    return __DIR__ . '/../almacen/demo_portal_state.json';
}

function clientNormalizeDocument(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?: '';
}

function clientReadJsonFile(string $path, array $fallback = []): array
{
    if (!is_file($path) || !is_readable($path)) {
        return $fallback;
    }

    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return $fallback;
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : $fallback;
}

function clientWriteJsonFile(string $path, array $value): bool
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        return false;
    }

    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false) {
        return false;
    }

    $temporary = $path . '.tmp';
    if (file_put_contents($temporary, $json . PHP_EOL, LOCK_EX) === false) {
        return false;
    }

    @chmod($temporary, 0640);
    return rename($temporary, $path);
}

function clientAllowedAccountTypes(): array
{
    return ['empresa', 'consorcio'];
}

function clientNormalizeStoredAccount(array $account): ?array
{
    $document = clientNormalizeDocument((string) ($account['document'] ?? ''));
    $entityId = trim((string) ($account['entity_id'] ?? ''));
    $entityName = trim((string) ($account['entity_name'] ?? ''));
    $accountType = (string) ($account['account_type'] ?? 'empresa');

    if ($document === '' || $entityId === '' || $entityName === '' || !in_array($accountType, clientAllowedAccountTypes(), true)) {
        return null;
    }

    return [
        'id' => trim((string) ($account['id'] ?? 'client-account-' . substr(sha1($entityId . $document), 0, 12))),
        'document_type' => 'RUC',
        'document' => $document,
        'password_hash' => (string) ($account['password_hash'] ?? ''),
        'role' => 'cliente',
        'role_label' => 'Cliente',
        'account_type' => $accountType,
        'name' => $entityName,
        'profile_title' => $accountType === 'consorcio' ? 'Consorcio cliente' : 'Empresa cliente',
        'entity_id' => $entityId,
        'entity_name' => $entityName,
        'entity_type' => trim((string) ($account['entity_type'] ?? ($accountType === 'consorcio' ? 'Consorcio' : 'Empresa'))),
        'scope' => 'Consulta de expedientes, pólizas, pagos y siniestros',
        'contact_email' => trim((string) ($account['contact_email'] ?? '')),
        'contact_phone' => trim((string) ($account['contact_phone'] ?? '')),
        'active' => ($account['active'] ?? true) !== false,
        'created_at' => trim((string) ($account['created_at'] ?? '')),
        'updated_at' => trim((string) ($account['updated_at'] ?? '')),
    ];
}

function loadPersistentClientAccounts(): array
{
    $store = clientReadJsonFile(clientAccountStorePath(), ['accounts' => []]);
    $accounts = is_array($store['accounts'] ?? null) ? $store['accounts'] : [];

    $normalized = [];
    foreach ($accounts as $account) {
        if (!is_array($account)) {
            continue;
        }
        $item = clientNormalizeStoredAccount($account);
        if ($item !== null) {
            $normalized[] = $item;
        }
    }

    return $normalized;
}

function savePersistentClientAccounts(array $accounts): bool
{
    $safe = [];

    foreach ($accounts as $account) {
        if (!is_array($account)) {
            continue;
        }

        $normalized = clientNormalizeStoredAccount($account);
        if ($normalized === null) {
            continue;
        }

        $safe[] = [
            'id' => $normalized['id'],
            'document_type' => 'RUC',
            'document' => $normalized['document'],
            'password_hash' => $normalized['password_hash'],
            'role' => 'cliente',
            'role_label' => 'Cliente',
            'account_type' => $normalized['account_type'],
            'name' => $normalized['name'],
            'profile_title' => $normalized['profile_title'],
            'entity_id' => $normalized['entity_id'],
            'entity_name' => $normalized['entity_name'],
            'entity_type' => $normalized['entity_type'],
            'scope' => $normalized['scope'],
            'contact_email' => $normalized['contact_email'],
            'contact_phone' => $normalized['contact_phone'],
            'active' => $normalized['active'],
            'created_at' => $normalized['created_at'],
            'updated_at' => $normalized['updated_at'],
        ];
    }

    return clientWriteJsonFile(clientAccountStorePath(), ['accounts' => $safe]);
}

function loginUsers(): array
{
    $demo = require __DIR__ . '/demo_users.php';
    $demo = is_array($demo) ? $demo : [];
    $persistent = loadPersistentClientAccounts();

    $taken = [];
    foreach ($persistent as $account) {
        $taken['RUC:' . $account['document']] = true;
    }

    $merged = $persistent;
    foreach ($demo as $user) {
        if (!is_array($user)) {
            continue;
        }

        $type = strtoupper(trim((string) ($user['document_type'] ?? '')));
        $document = clientNormalizeDocument((string) ($user['document'] ?? ''));
        if ($type === 'RUC' && isset($taken['RUC:' . $document])) {
            continue;
        }

        $merged[] = $user;
    }

    return $merged;
}

function publicClientAccount(array $account): array
{
    return [
        'id' => (string) ($account['id'] ?? ''),
        'entity_id' => (string) ($account['entity_id'] ?? ''),
        'entity_name' => (string) ($account['entity_name'] ?? $account['name'] ?? ''),
        'entity_type' => (string) ($account['entity_type'] ?? ''),
        'account_type' => (string) ($account['account_type'] ?? ''),
        'document_type' => 'RUC',
        'document' => (string) ($account['document'] ?? ''),
        'contact_email' => (string) ($account['contact_email'] ?? ''),
        'contact_phone' => (string) ($account['contact_phone'] ?? ''),
        'active' => ($account['active'] ?? true) !== false,
        'created_at' => (string) ($account['created_at'] ?? ''),
        'updated_at' => (string) ($account['updated_at'] ?? ''),
    ];
}

function clientLower(string $value): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
}

function resolveClientEntity(array $user, array $state): ?array
{
    $entities = is_array($state['entities'] ?? null) ? $state['entities'] : [];
    $companies = is_array($entities['companies'] ?? null) ? $entities['companies'] : [];
    $consortia = is_array($entities['consortia'] ?? null) ? $entities['consortia'] : [];
    $all = array_merge($companies, $consortia);

    $requestedId = trim((string) ($user['entity_id'] ?? ''));
    $document = clientNormalizeDocument((string) ($user['document'] ?? ''));
    $name = trim((string) ($user['entity_name'] ?? $user['name'] ?? ''));

    foreach ($all as $entity) {
        if (!is_array($entity)) {
            continue;
        }

        $entityId = trim((string) ($entity['id'] ?? ''));
        $entityDocument = clientNormalizeDocument((string) ($entity['ruc'] ?? $entity['ruc_principal'] ?? ''));
        $entityName = trim((string) ($entity['name'] ?? ''));

        if ($requestedId !== '' && $requestedId === $entityId) {
            return $entity;
        }

        if ($document !== '' && $document === $entityDocument) {
            return $entity;
        }

        if ($name !== '' && clientLower($name) === clientLower($entityName)) {
            return $entity;
        }
    }

    return null;
}
