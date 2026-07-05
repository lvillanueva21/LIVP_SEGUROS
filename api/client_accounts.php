<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../config/client_accounts.php';

$user = requireAuth();

header('Content-Type: application/json; charset=UTF-8');

function clientAccountsResponse(bool $ok, string $message, array $extra = [], int $status = 200): never
{
    http_response_code($status);
    echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ((string) ($user['role'] ?? '') !== 'gerente') {
    clientAccountsResponse(false, 'Solo Gerencia puede administrar accesos Cliente.', [], 403);
}

function currentPublicAccounts(): array
{
    return array_map('publicClientAccount', loadPersistentClientAccounts());
}

function publishedAt(): string
{
    $state = clientReadJsonFile(portalStateStorePath(), []);
    return trim((string) ($state['published_at'] ?? ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    clientAccountsResponse(true, 'Listado actualizado.', [
        'accounts' => currentPublicAccounts(),
        'published_at' => publishedAt(),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    clientAccountsResponse(false, 'Método no permitido.', [], 405);
}

$action = trim((string) ($_POST['action'] ?? ''));

if ($action === 'save') {
    $entityId = trim((string) ($_POST['entity_id'] ?? ''));
    $entityName = trim((string) ($_POST['entity_name'] ?? ''));
    $entityType = trim((string) ($_POST['entity_type'] ?? ''));
    $accountType = trim((string) ($_POST['account_type'] ?? 'empresa'));
    $document = clientNormalizeDocument((string) ($_POST['document'] ?? ''));
    $email = trim((string) ($_POST['contact_email'] ?? ''));
    $phone = trim((string) ($_POST['contact_phone'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($entityId === '' || $entityName === '' || !in_array($accountType, clientAllowedAccountTypes(), true) || !preg_match('/^\d{11}$/', $document)) {
        clientAccountsResponse(false, 'La entidad debe tener nombre, tipo permitido y RUC propio de 11 dígitos.', [], 422);
    }

    if (mb_strlen($password) < 8) {
        clientAccountsResponse(false, 'La contraseña debe tener al menos 8 caracteres.', [], 422);
    }

    $accounts = loadPersistentClientAccounts();
    $index = null;

    foreach ($accounts as $position => $account) {
        if ((string) $account['entity_id'] === $entityId || (string) $account['document'] === $document) {
            $index = $position;
            break;
        }
    }

    $now = (new DateTimeImmutable('now', new DateTimeZone('America/Lima')))->format('Y-m-d H:i:s');
    $record = [
        'id' => $index === null ? 'client-account-' . bin2hex(random_bytes(8)) : (string) $accounts[$index]['id'],
        'document_type' => 'RUC',
        'document' => $document,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => 'cliente',
        'role_label' => 'Cliente',
        'account_type' => $accountType,
        'name' => $entityName,
        'profile_title' => $accountType === 'consorcio' ? 'Consorcio cliente' : 'Empresa cliente',
        'entity_id' => $entityId,
        'entity_name' => $entityName,
        'entity_type' => $entityType !== '' ? $entityType : ($accountType === 'consorcio' ? 'Consorcio' : 'Empresa'),
        'scope' => 'Consulta de expedientes, pólizas, pagos y siniestros',
        'contact_email' => $email,
        'contact_phone' => $phone,
        'active' => true,
        'created_at' => $index === null ? $now : (string) ($accounts[$index]['created_at'] ?? $now),
        'updated_at' => $now,
    ];

    if ($index === null) {
        $accounts[] = $record;
    } else {
        $accounts[$index] = $record;
    }

    if (!savePersistentClientAccounts($accounts)) {
        clientAccountsResponse(false, 'No se pudo guardar el acceso en el almacenamiento del servidor.', [], 500);
    }

    clientAccountsResponse(true, $index === null ? 'Acceso Cliente creado. Publica la cartera para que pueda ver sus expedientes.' : 'Acceso Cliente actualizado. La contraseña anterior dejó de ser válida.', [
        'accounts' => currentPublicAccounts(),
    ]);
}

if ($action === 'toggle') {
    $id = trim((string) ($_POST['id'] ?? ''));
    $active = (string) ($_POST['active'] ?? '') === '1';

    if ($id === '') {
        clientAccountsResponse(false, 'No se identificó la cuenta a actualizar.', [], 422);
    }

    $accounts = loadPersistentClientAccounts();
    $found = false;
    $now = (new DateTimeImmutable('now', new DateTimeZone('America/Lima')))->format('Y-m-d H:i:s');

    foreach ($accounts as &$account) {
        if ((string) $account['id'] === $id) {
            $account['active'] = $active;
            $account['updated_at'] = $now;
            $found = true;
            break;
        }
    }
    unset($account);

    if (!$found) {
        clientAccountsResponse(false, 'La cuenta solicitada no existe.', [], 404);
    }

    if (!savePersistentClientAccounts($accounts)) {
        clientAccountsResponse(false, 'No se pudo actualizar la cuenta.', [], 500);
    }

    clientAccountsResponse(true, $active ? 'La cuenta fue activada.' : 'La cuenta fue desactivada.', [
        'accounts' => currentPublicAccounts(),
    ]);
}

clientAccountsResponse(false, 'Acción no reconocida.', [], 422);
