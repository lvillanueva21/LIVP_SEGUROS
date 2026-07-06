<?php

declare(strict_types=1);

const LIVP_LOGIN_MAX_FAILURES = 5;
const LIVP_LOGIN_WINDOW_MINUTES = 15;
const LIVP_LOGIN_BLOCK_MINUTES = 15;

function csrfToken(string $scope): string
{
    if (!isset($_SESSION['livp_csrf']) || !is_array($_SESSION['livp_csrf'])) {
        $_SESSION['livp_csrf'] = [];
    }

    if (empty($_SESSION['livp_csrf'][$scope]) || !is_string($_SESSION['livp_csrf'][$scope])) {
        $_SESSION['livp_csrf'][$scope] = bin2hex(random_bytes(32));
    }

    return $_SESSION['livp_csrf'][$scope];
}

function csrfValidate(string $scope, ?string $token): bool
{
    $stored = $_SESSION['livp_csrf'][$scope] ?? '';
    $token = is_string($token) ? $token : '';

    return is_string($stored) && $stored !== '' && $token !== '' && hash_equals($stored, $token);
}

function csrfRotate(string $scope): string
{
    unset($_SESSION['livp_csrf'][$scope]);

    return csrfToken($scope);
}

function securityClientIp(): string
{
    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));

    return $ip !== '' ? substr($ip, 0, 64) : '0.0.0.0';
}

function securityUserAgent(): string
{
    return substr(trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 500);
}

function securityRecordLoginAttempt(
    string $documentType,
    string $document,
    string $result,
    string $source,
    ?int $databaseUserId = null,
    ?string $detail = null
): void {
    try {
        $statement = livpDb()->prepare(
            'INSERT INTO seg_login_intentos
            (id_usuario, tipo_documento, numero_documento, ip, user_agent, resultado, fuente, detalle)
            VALUES
            (:id_usuario, :tipo_documento, :numero_documento, :ip, :user_agent, :resultado, :fuente, :detalle)'
        );

        $statement->execute([
            ':id_usuario' => $databaseUserId,
            ':tipo_documento' => strtoupper(trim($documentType)),
            ':numero_documento' => substr(trim($document), 0, 30),
            ':ip' => securityClientIp(),
            ':user_agent' => securityUserAgent(),
            ':resultado' => substr($result, 0, 20),
            ':fuente' => substr($source, 0, 30),
            ':detalle' => $detail !== null ? substr($detail, 0, 255) : null,
        ]);
    } catch (Throwable $exception) {
        error_log('[LIVP SECURITY] No se pudo registrar intento: ' . $exception->getMessage());
    }
}

function securityLoginBlockInfo(string $documentType, string $document): array
{
    $documentType = strtoupper(trim($documentType));
    $document = trim($document);

    if ($documentType === '' || $document === '') {
        return ['blocked' => false, 'minutes' => 0];
    }

    try {
        $windowStart = (new DateTimeImmutable('now', new DateTimeZone('America/Lima')))
            ->modify('-' . LIVP_LOGIN_WINDOW_MINUTES . ' minutes')
            ->format('Y-m-d H:i:s');

        $statement = livpDb()->prepare(
            'SELECT MAX(creado_en) AS ultimo_fallo, COUNT(*) AS cantidad
             FROM seg_login_intentos
             WHERE tipo_documento = :tipo_documento
               AND numero_documento = :numero_documento
               AND ip = :ip
               AND resultado = "fallido"
               AND creado_en >= :window_start'
        );

        $statement->execute([
            ':tipo_documento' => $documentType,
            ':numero_documento' => $document,
            ':ip' => securityClientIp(),
            ':window_start' => $windowStart,
        ]);

        $row = $statement->fetch();
        $count = (int) ($row['cantidad'] ?? 0);
        $lastFailure = trim((string) ($row['ultimo_fallo'] ?? ''));

        if ($count < LIVP_LOGIN_MAX_FAILURES || $lastFailure === '') {
            return ['blocked' => false, 'minutes' => 0];
        }

        $until = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $lastFailure, new DateTimeZone('America/Lima'));
        if (!$until instanceof DateTimeImmutable) {
            return ['blocked' => false, 'minutes' => 0];
        }

        $until = $until->modify('+' . LIVP_LOGIN_BLOCK_MINUTES . ' minutes');
        $seconds = $until->getTimestamp() - time();

        if ($seconds <= 0) {
            return ['blocked' => false, 'minutes' => 0];
        }

        return [
            'blocked' => true,
            'minutes' => (int) ceil($seconds / 60),
            'until' => $until->format('Y-m-d H:i:s'),
        ];
    } catch (Throwable $exception) {
        error_log('[LIVP SECURITY] No se pudo validar bloqueo: ' . $exception->getMessage());

        return ['blocked' => false, 'minutes' => 0];
    }
}

function securityClearLoginFailures(string $documentType, string $document): void
{
    try {
        $statement = livpDb()->prepare(
            'DELETE FROM seg_login_intentos
             WHERE tipo_documento = :tipo_documento
               AND numero_documento = :numero_documento
               AND ip = :ip
               AND resultado = "fallido"'
        );

        $statement->execute([
            ':tipo_documento' => strtoupper(trim($documentType)),
            ':numero_documento' => trim($document),
            ':ip' => securityClientIp(),
        ]);
    } catch (Throwable $exception) {
        error_log('[LIVP SECURITY] No se pudo limpiar fallos: ' . $exception->getMessage());
    }
}
