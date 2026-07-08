<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/correo.php';

function correo_now(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('America/Lima')))->format('Y-m-d H:i:s');
}

function correo_current_database_user_id(?array $user = null): ?int
{
    $user = $user ?? currentUser();
    $id = is_array($user) ? (int) ($user['database_user_id'] ?? 0) : 0;

    return $id > 0 ? $id : null;
}

function correo_clean_app_password(string $value): string
{
    return preg_replace('/\s+/', '', trim($value)) ?: '';
}

function correo_clean_email(string $value): string
{
    return strtolower(trim($value));
}

function correo_valid_email(string $email): bool
{
    return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function correo_truncate(?string $value, int $length): ?string
{
    if ($value === null) {
        return null;
    }

    $value = trim($value);
    if ($value === '') {
        return '';
    }

    return function_exists('mb_substr') ? mb_substr($value, 0, $length) : substr($value, 0, $length);
}

function correo_encrypt_key(): string
{
    $secret = (string) (defined('SEG_CORREO_ENCRYPTION_KEY') ? SEG_CORREO_ENCRYPTION_KEY : '');
    if (trim($secret) === '') {
        throw new RuntimeException('No se encontró la llave de cifrado del módulo de correos.');
    }

    return hash('sha256', $secret, true);
}

function correo_encrypt_password(string $plain): array
{
    if (!function_exists('openssl_encrypt')) {
        throw new RuntimeException('La extensión OpenSSL no está disponible en PHP.');
    }

    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', correo_encrypt_key(), OPENSSL_RAW_DATA, $iv, $tag);

    if ($cipher === false || $tag === '') {
        throw new RuntimeException('No se pudo cifrar la clave de aplicación.');
    }

    return [
        'cipher' => base64_encode($cipher),
        'iv' => base64_encode($iv),
        'tag' => base64_encode($tag),
    ];
}

function correo_decrypt_password(array $config): string
{
    if (!function_exists('openssl_decrypt')) {
        throw new RuntimeException('La extensión OpenSSL no está disponible en PHP.');
    }

    $cipher = base64_decode((string) ($config['clave_aplicacion_cifrada'] ?? ''), true);
    $iv = base64_decode((string) ($config['clave_aplicacion_iv'] ?? ''), true);
    $tag = base64_decode((string) ($config['clave_aplicacion_tag'] ?? ''), true);

    if ($cipher === false || $iv === false || $tag === false || $cipher === '' || $iv === '' || $tag === '') {
        throw new RuntimeException('La clave de aplicación no está registrada o no se puede leer.');
    }

    $plain = openssl_decrypt($cipher, 'aes-256-gcm', correo_encrypt_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($plain === false || $plain === '') {
        throw new RuntimeException('No se pudo descifrar la clave de aplicación. Si cambiaste la llave de cifrado, vuelve a registrar la clave Zoho.');
    }

    return $plain;
}

function correo_default_config_values(): array
{
    return [
        'clave_configuracion' => 'global',
        'proveedor' => 'zoho',
        'correo_remitente' => '',
        'nombre_remitente' => SEG_CORREO_DEFAULT_FROM_NAME,
        'correo_copia_administrativa' => '',
        'asunto_prueba' => SEG_CORREO_DEFAULT_TEST_SUBJECT,
        'mensaje_prueba' => SEG_CORREO_DEFAULT_TEST_MESSAGE,
        'estado_verificacion' => 'incompleta',
        'version_configuracion' => 1,
        'ultima_prueba_en' => null,
        'ultima_prueba_exitosa_en' => null,
        'ultimo_error' => null,
    ];
}

function correo_fetch_config(): ?array
{
    $stmt = livpDb()->prepare('SELECT * FROM seg_correo_configuracion WHERE clave_configuracion = "global" LIMIT 1');
    $stmt->execute();
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function correo_ensure_config(?int $userId = null): array
{
    $row = correo_fetch_config();
    if (is_array($row)) {
        return $row;
    }

    $defaults = correo_default_config_values();
    $stmt = livpDb()->prepare(
        'INSERT INTO seg_correo_configuracion
        (clave_configuracion, proveedor, nombre_remitente, asunto_prueba, mensaje_prueba, estado_verificacion, version_configuracion, creado_por, actualizado_por)
        VALUES
        ("global", "zoho", :nombre_remitente, :asunto_prueba, :mensaje_prueba, "incompleta", 1, :creado_por, :actualizado_por)'
    );
    $stmt->execute([
        ':nombre_remitente' => $defaults['nombre_remitente'],
        ':asunto_prueba' => $defaults['asunto_prueba'],
        ':mensaje_prueba' => $defaults['mensaje_prueba'],
        ':creado_por' => $userId,
        ':actualizado_por' => $userId,
    ]);

    correo_audit('crear_configuracion', 'Se creó la configuración global inicial de correos.', $userId);

    return correo_fetch_config() ?? $defaults;
}

function correo_fetch_test_recipients(int $configId): array
{
    $stmt = livpDb()->prepare(
        'SELECT * FROM seg_correo_destinatarios_prueba
         WHERE id_configuracion = :id_configuracion AND activo = 1
         ORDER BY orden ASC, id ASC'
    );
    $stmt->execute([':id_configuracion' => $configId]);

    return $stmt->fetchAll() ?: [];
}

function correo_config_has_password(array $config): bool
{
    return trim((string) ($config['clave_aplicacion_cifrada'] ?? '')) !== ''
        && trim((string) ($config['clave_aplicacion_iv'] ?? '')) !== ''
        && trim((string) ($config['clave_aplicacion_tag'] ?? '')) !== '';
}

function correo_config_is_complete(array $config, array $recipients): bool
{
    return correo_valid_email((string) ($config['correo_remitente'] ?? ''))
        && trim((string) ($config['nombre_remitente'] ?? '')) !== ''
        && correo_config_has_password($config)
        && count($recipients) >= 1;
}

function correo_compute_state(array $config, array $recipients): string
{
    if (!correo_config_is_complete($config, $recipients)) {
        return 'incompleta';
    }

    $state = (string) ($config['estado_verificacion'] ?? 'lista');
    return in_array($state, ['lista', 'verificada', 'fallida', 'requiere_prueba'], true) ? $state : 'lista';
}

function correo_audit(string $accion, string $detalle, ?int $userId = null): void
{
    try {
        $stmt = livpDb()->prepare(
            'INSERT INTO seg_correo_auditoria (accion, detalle, id_usuario, ip, user_agent)
             VALUES (:accion, :detalle, :id_usuario, :ip, :user_agent)'
        );
        $stmt->execute([
            ':accion' => correo_truncate($accion, 80),
            ':detalle' => correo_truncate($detalle, 1000),
            ':id_usuario' => $userId,
            ':ip' => function_exists('securityClientIp') ? securityClientIp() : null,
            ':user_agent' => function_exists('securityUserAgent') ? securityUserAgent() : null,
        ]);
    } catch (Throwable $exception) {
        error_log('[LIVP CORREO AUDIT] ' . $exception->getMessage());
    }
}

function correo_save_config(array $post, array $user): array
{
    $userId = correo_current_database_user_id($user);
    $config = correo_ensure_config($userId);
    $configId = (int) ($config['id'] ?? 0);

    $from = correo_clean_email((string) ($post['correo_remitente'] ?? ''));
    $fromName = trim((string) ($post['nombre_remitente'] ?? ''));
    $appPassword = correo_clean_app_password((string) ($post['clave_aplicacion'] ?? ''));
    $cc = correo_clean_email((string) ($post['correo_copia_administrativa'] ?? ''));
    $subject = trim((string) ($post['asunto_prueba'] ?? ''));
    $message = trim((string) ($post['mensaje_prueba'] ?? ''));

    $recipient1 = correo_clean_email((string) ($post['destinatario_prueba_1'] ?? ''));
    $recipient2 = correo_clean_email((string) ($post['destinatario_prueba_2'] ?? ''));
    $recipients = [];
    if ($recipient1 !== '') {
        $recipients[] = $recipient1;
    }
    if ($recipient2 !== '') {
        $recipients[] = $recipient2;
    }
    $recipients = array_values(array_unique($recipients));

    $errors = [];
    if (!correo_valid_email($from)) {
        $errors[] = 'Ingresa un correo remitente válido.';
    }
    if ($fromName === '') {
        $errors[] = 'Ingresa el nombre visible del remitente.';
    }
    if (!correo_config_has_password($config) && $appPassword === '') {
        $errors[] = 'Ingresa la clave de aplicación Zoho.';
    }
    if (count($recipients) < 1) {
        $errors[] = 'Debes configurar al menos un destinatario de prueba.';
    }
    if (count($recipients) > 2) {
        $errors[] = 'Solo se permiten hasta dos destinatarios de prueba.';
    }
    foreach ($recipients as $email) {
        if (!correo_valid_email($email)) {
            $errors[] = 'Hay un destinatario de prueba inválido: ' . $email;
        }
    }
    if ($cc !== '' && !correo_valid_email($cc)) {
        $errors[] = 'El correo de copia administrativa no es válido.';
    }
    if ($cc !== '' && in_array($cc, $recipients, true)) {
        $errors[] = 'El correo de copia administrativa no debe repetirse como destinatario de prueba.';
    }
    if ($subject === '') {
        $errors[] = 'Ingresa un asunto de prueba.';
    }
    if ($message === '') {
        $errors[] = 'Ingresa un mensaje de prueba.';
    }

    if ($errors !== []) {
        return ['ok' => false, 'errors' => $errors];
    }

    $senderChanged = $from !== (string) ($config['correo_remitente'] ?? '');
    $passwordChanged = $appPassword !== '';
    $majorChanged = $senderChanged || $passwordChanged;
    $newVersion = (int) ($config['version_configuracion'] ?? 1) + ($majorChanged ? 1 : 0);
    $oldState = (string) ($config['estado_verificacion'] ?? 'incompleta');
    $newState = $majorChanged ? (((string) ($config['correo_remitente'] ?? '') === '' && !correo_config_has_password($config)) ? 'lista' : 'requiere_prueba') : $oldState;

    $encrypted = null;
    if ($passwordChanged) {
        $encrypted = correo_encrypt_password($appPassword);
    }

    $pdo = livpDb();
    $pdo->beginTransaction();

    try {
        $sql = 'UPDATE seg_correo_configuracion
                SET correo_remitente = :correo_remitente,
                    nombre_remitente = :nombre_remitente,
                    correo_copia_administrativa = :correo_copia_administrativa,
                    asunto_prueba = :asunto_prueba,
                    mensaje_prueba = :mensaje_prueba,
                    version_configuracion = :version_configuracion,
                    estado_verificacion = :estado_verificacion,
                    actualizado_por = :actualizado_por';
        $params = [
            ':correo_remitente' => $from,
            ':nombre_remitente' => correo_truncate($fromName, 190),
            ':correo_copia_administrativa' => $cc !== '' ? $cc : null,
            ':asunto_prueba' => correo_truncate($subject, 255),
            ':mensaje_prueba' => $message,
            ':version_configuracion' => $newVersion,
            ':estado_verificacion' => $newState,
            ':actualizado_por' => $userId,
            ':id' => $configId,
        ];

        if ($encrypted !== null) {
            $sql .= ', clave_aplicacion_cifrada = :clave_aplicacion_cifrada,
                      clave_aplicacion_iv = :clave_aplicacion_iv,
                      clave_aplicacion_tag = :clave_aplicacion_tag';
            $params[':clave_aplicacion_cifrada'] = $encrypted['cipher'];
            $params[':clave_aplicacion_iv'] = $encrypted['iv'];
            $params[':clave_aplicacion_tag'] = $encrypted['tag'];
        }

        $sql .= ' WHERE id = :id LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $delete = $pdo->prepare('DELETE FROM seg_correo_destinatarios_prueba WHERE id_configuracion = :id_configuracion');
        $delete->execute([':id_configuracion' => $configId]);

        $insert = $pdo->prepare(
            'INSERT INTO seg_correo_destinatarios_prueba (id_configuracion, correo, orden, activo)
             VALUES (:id_configuracion, :correo, :orden, 1)'
        );
        foreach ($recipients as $index => $email) {
            $insert->execute([
                ':id_configuracion' => $configId,
                ':correo' => $email,
                ':orden' => $index + 1,
            ]);
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    $changes = [];
    if ($senderChanged) {
        $changes[] = 'correo remitente';
    }
    if ($passwordChanged) {
        $changes[] = 'clave Zoho';
    }
    $changes[] = 'datos de prueba/configuración';
    correo_audit('actualizar_configuracion', 'Se actualizó: ' . implode(', ', $changes) . '.', $userId);

    return ['ok' => true, 'message' => 'Configuración de correos guardada correctamente.'];
}

function correo_status_label(string $state): string
{
    return [
        'incompleta' => 'Incompleta',
        'lista' => 'Lista para probar',
        'verificada' => 'Verificada',
        'fallida' => 'Última prueba fallida',
        'requiere_prueba' => 'Requiere nueva prueba',
    ][$state] ?? 'Sin estado';
}

function correo_status_class(string $state): string
{
    return [
        'incompleta' => 'mail-status-warning',
        'lista' => 'mail-status-info',
        'verificada' => 'mail-status-success',
        'fallida' => 'mail-status-danger',
        'requiere_prueba' => 'mail-status-warning',
    ][$state] ?? 'mail-status-info';
}

function correo_format_datetime(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return 'No registrado';
    }

    try {
        $date = new DateTimeImmutable($value, new DateTimeZone('America/Lima'));
        return $date->format('d/m/Y · H:i');
    } catch (Throwable $exception) {
        return 'No registrado';
    }
}

function correo_metrics(): array
{
    $metrics = [
        'envios_total' => 0,
        'envios_enviados' => 0,
        'envios_fallidos' => 0,
        'envios_parciales' => 0,
        'destinatarios_aceptados' => 0,
        'destinatarios_fallidos' => 0,
    ];

    try {
        $row = livpDb()->query(
            'SELECT
                COUNT(*) AS envios_total,
                SUM(CASE WHEN estado_general = "enviado" THEN 1 ELSE 0 END) AS envios_enviados,
                SUM(CASE WHEN estado_general = "fallido" THEN 1 ELSE 0 END) AS envios_fallidos,
                SUM(CASE WHEN estado_general = "parcial" THEN 1 ELSE 0 END) AS envios_parciales
             FROM seg_correo_envios'
        )->fetch();
        if (is_array($row)) {
            foreach (['envios_total', 'envios_enviados', 'envios_fallidos', 'envios_parciales'] as $key) {
                $metrics[$key] = (int) ($row[$key] ?? 0);
            }
        }

        $row = livpDb()->query(
            'SELECT
                SUM(CASE WHEN estado = "aceptado" THEN 1 ELSE 0 END) AS aceptados,
                SUM(CASE WHEN estado = "fallido" THEN 1 ELSE 0 END) AS fallidos
             FROM seg_correo_envio_destinatarios'
        )->fetch();
        if (is_array($row)) {
            $metrics['destinatarios_aceptados'] = (int) ($row['aceptados'] ?? 0);
            $metrics['destinatarios_fallidos'] = (int) ($row['fallidos'] ?? 0);
        }
    } catch (Throwable $exception) {
        error_log('[LIVP CORREO METRICS] ' . $exception->getMessage());
    }

    return $metrics;
}

function correo_history(int $limit = 20): array
{
    $stmt = livpDb()->prepare(
        'SELECT e.*,
                COUNT(d.id) AS total_destinatarios,
                SUM(CASE WHEN d.estado = "aceptado" THEN 1 ELSE 0 END) AS aceptados,
                SUM(CASE WHEN d.estado = "fallido" THEN 1 ELSE 0 END) AS fallidos
         FROM seg_correo_envios e
         LEFT JOIN seg_correo_envio_destinatarios d ON d.id_envio = e.id
         GROUP BY e.id
         ORDER BY e.created_at DESC, e.id DESC
         LIMIT :limit'
    );
    $stmt->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll() ?: [];
}

function correo_history_details(int $sendId): array
{
    $stmt = livpDb()->prepare(
        'SELECT * FROM seg_correo_envio_destinatarios WHERE id_envio = :id_envio ORDER BY id ASC'
    );
    $stmt->execute([':id_envio' => $sendId]);

    return $stmt->fetchAll() ?: [];
}

function correo_generate_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function correo_header_encode(string $text): string
{
    return '=?UTF-8?B?' . base64_encode($text) . '?=';
}

function correo_address_header(string $email, string $name = ''): string
{
    $email = trim($email);
    $name = trim($name);

    return $name !== '' ? correo_header_encode($name) . ' <' . $email . '>' : '<' . $email . '>';
}

function correo_smtp_read($socket): array
{
    $message = '';
    $code = 0;

    while (($line = fgets($socket, 515)) !== false) {
        $message .= $line;
        if (preg_match('/^(\d{3})([ -])/', $line, $matches) === 1) {
            $code = (int) $matches[1];
            if ($matches[2] === ' ') {
                break;
            }
        } else {
            break;
        }
    }

    return [$code, trim($message)];
}

function correo_smtp_write($socket, string $command): void
{
    fwrite($socket, $command . "\r\n");
}

function correo_smtp_expect($socket, string $command, array $okCodes, array &$transcript): array
{
    correo_smtp_write($socket, $command);
    [$code, $message] = correo_smtp_read($socket);
    $safeCommand = preg_match('/^(AUTH|PASS)/i', $command) === 1 ? preg_replace('/ .*/', ' ******', $command) : $command;
    $transcript[] = '> ' . $safeCommand;
    $transcript[] = '< ' . $message;

    if (!in_array($code, $okCodes, true)) {
        throw new RuntimeException('SMTP ' . $code . ': ' . $message);
    }

    return [$code, $message];
}

function correo_build_raw_message(string $fromEmail, string $fromName, string $toEmail, array $acceptedCc, string $subject, string $message): string
{
    $headers = [];
    $headers[] = 'Date: ' . date(DATE_RFC2822);
    $headers[] = 'From: ' . correo_address_header($fromEmail, $fromName);
    $headers[] = 'To: <' . $toEmail . '>';
    if ($acceptedCc !== []) {
        $headers[] = 'Cc: ' . implode(', ', array_map(static fn (string $email): string => '<' . $email . '>', $acceptedCc));
    }
    $headers[] = 'Subject: ' . correo_header_encode($subject);
    $headers[] = 'Message-ID: <' . bin2hex(random_bytes(12)) . '@broker-seguros.local>';
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers[] = 'Content-Transfer-Encoding: base64';

    return implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($message), 76, "\r\n");
}

function correo_smtp_send(array $params): array
{
    $host = (string) SEG_CORREO_SMTP_HOST;
    $port = (int) SEG_CORREO_SMTP_PORT;
    $timeout = (int) SEG_CORREO_SMTP_TIMEOUT;
    $fromEmail = (string) $params['from_email'];
    $fromName = (string) $params['from_name'];
    $username = (string) $params['username'];
    $password = (string) $params['password'];
    $toEmail = (string) $params['to'];
    $ccEmails = array_values(array_filter(array_map('correo_clean_email', (array) ($params['cc'] ?? []))));
    $subject = (string) $params['subject'];
    $body = (string) $params['message'];
    $transcript = [];
    $accepted = [];
    $failed = [];

    $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false]]);
    $socket = @stream_socket_client('tcp://' . $host . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);

    if (!is_resource($socket)) {
        throw new RuntimeException('No se pudo conectar a SMTP Zoho: ' . trim((string) $errstr));
    }

    stream_set_timeout($socket, $timeout);

    try {
        [$code, $message] = correo_smtp_read($socket);
        $transcript[] = '< ' . $message;
        if ($code !== 220) {
            throw new RuntimeException('SMTP ' . $code . ': ' . $message);
        }

        $serverName = preg_replace('/[^a-zA-Z0-9.-]/', '', (string) ($_SERVER['SERVER_NAME'] ?? 'localhost')) ?: 'localhost';
        correo_smtp_expect($socket, 'EHLO ' . $serverName, [250], $transcript);
        correo_smtp_expect($socket, 'STARTTLS', [220], $transcript);

        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('No se pudo activar TLS/STARTTLS con Zoho.');
        }

        correo_smtp_expect($socket, 'EHLO ' . $serverName, [250], $transcript);
        correo_smtp_expect($socket, 'AUTH LOGIN', [334], $transcript);
        correo_smtp_expect($socket, base64_encode($username), [334], $transcript);
        correo_smtp_expect($socket, base64_encode($password), [235], $transcript);
        correo_smtp_expect($socket, 'MAIL FROM:<' . $fromEmail . '>', [250], $transcript);

        $requested = array_values(array_unique(array_merge([$toEmail], $ccEmails)));
        foreach ($requested as $email) {
            correo_smtp_write($socket, 'RCPT TO:<' . $email . '>');
            [$rcptCode, $rcptMessage] = correo_smtp_read($socket);
            $transcript[] = '> RCPT TO:<' . $email . '>';
            $transcript[] = '< ' . $rcptMessage;

            if (in_array($rcptCode, [250, 251], true)) {
                $accepted[] = $email;
            } else {
                $failed[$email] = 'SMTP ' . $rcptCode . ': ' . $rcptMessage;
            }
        }

        if ($accepted === []) {
            throw new RuntimeException('Zoho no aceptó ningún destinatario.');
        }

        $acceptedCc = array_values(array_intersect($ccEmails, $accepted));
        $raw = correo_build_raw_message($fromEmail, $fromName, $toEmail, $acceptedCc, $subject, $body);
        $raw = preg_replace('/^\./m', '..', $raw) ?: $raw;

        correo_smtp_expect($socket, 'DATA', [354], $transcript);
        fwrite($socket, $raw . "\r\n.\r\n");
        [$dataCode, $dataMessage] = correo_smtp_read($socket);
        $transcript[] = '> [DATA]';
        $transcript[] = '< ' . $dataMessage;
        if (!in_array($dataCode, [250], true)) {
            throw new RuntimeException('SMTP ' . $dataCode . ': ' . $dataMessage);
        }

        correo_smtp_write($socket, 'QUIT');
        correo_smtp_read($socket);
        fclose($socket);

        return [
            'accepted' => $accepted,
            'failed' => $failed,
            'response' => $dataMessage,
            'transcript' => implode("\n", array_slice($transcript, -18)),
        ];
    } catch (Throwable $exception) {
        if (is_resource($socket)) {
            @correo_smtp_write($socket, 'QUIT');
            @fclose($socket);
        }
        throw $exception;
    }
}

function correo_send_system(array $toEmails, string $subject, string $message, array $options = []): array
{
    $user = is_array($options['user'] ?? null) ? $options['user'] : currentUser();
    $userId = correo_current_database_user_id($user);
    $config = correo_ensure_config($userId);
    $configId = (int) ($config['id'] ?? 0);
    $testRecipients = correo_fetch_test_recipients($configId);

    if (!correo_config_is_complete($config, $testRecipients)) {
        throw new RuntimeException('La configuración de correos está incompleta.');
    }

    $fromEmail = (string) $config['correo_remitente'];
    $fromName = (string) $config['nombre_remitente'];
    $password = correo_decrypt_password($config);
    $useAdminCc = (bool) ($options['usar_copia_administrativa'] ?? false);
    $adminCc = correo_clean_email((string) ($config['correo_copia_administrativa'] ?? ''));
    $cc = $useAdminCc && correo_valid_email($adminCc) ? [$adminCc] : [];
    $toEmails = array_values(array_unique(array_filter(array_map('correo_clean_email', $toEmails), 'correo_valid_email')));

    if ($toEmails === []) {
        throw new RuntimeException('No hay destinatarios válidos para enviar el correo.');
    }

    $tipo = correo_truncate((string) ($options['tipo_envio'] ?? 'manual'), 40) ?: 'manual';
    $modulo = correo_truncate((string) ($options['modulo_origen'] ?? 'sistema'), 80) ?: 'sistema';
    $entidad = correo_truncate((string) ($options['entidad_origen'] ?? ''), 80);
    $entidadId = correo_truncate((string) ($options['id_entidad_origen'] ?? ''), 80);

    $pdo = livpDb();
    $stmt = $pdo->prepare(
        'INSERT INTO seg_correo_envios
        (uuid, id_configuracion, tipo_envio, modulo_origen, entidad_origen, id_entidad_origen, configuracion_version,
         correo_remitente, nombre_remitente, asunto, mensaje, estado_general, solicitado_por, created_at)
         VALUES
        (:uuid, :id_configuracion, :tipo_envio, :modulo_origen, :entidad_origen, :id_entidad_origen, :configuracion_version,
         :correo_remitente, :nombre_remitente, :asunto, :mensaje, "pendiente", :solicitado_por, NOW())'
    );
    $stmt->execute([
        ':uuid' => correo_generate_uuid(),
        ':id_configuracion' => $configId,
        ':tipo_envio' => $tipo,
        ':modulo_origen' => $modulo,
        ':entidad_origen' => $entidad !== '' ? $entidad : null,
        ':id_entidad_origen' => $entidadId !== '' ? $entidadId : null,
        ':configuracion_version' => (int) ($config['version_configuracion'] ?? 1),
        ':correo_remitente' => $fromEmail,
        ':nombre_remitente' => $fromName,
        ':asunto' => correo_truncate($subject, 255),
        ':mensaje' => $message,
        ':solicitado_por' => $userId,
    ]);
    $sendId = (int) $pdo->lastInsertId();

    $detailStmt = $pdo->prepare(
        'INSERT INTO seg_correo_envio_destinatarios
         (id_envio, tipo_destinatario, correo, estado, respuesta_smtp, detalle_error, procesado_en)
         VALUES
         (:id_envio, :tipo_destinatario, :correo, :estado, :respuesta_smtp, :detalle_error, NOW())'
    );

    $acceptedCount = 0;
    $failedCount = 0;
    $errors = [];

    foreach ($toEmails as $to) {
        $requestedDetails = array_merge([['tipo' => 'to', 'correo' => $to]], array_map(static fn (string $email): array => ['tipo' => 'cc', 'correo' => $email], $cc));

        try {
            $result = correo_smtp_send([
                'from_email' => $fromEmail,
                'from_name' => $fromName,
                'username' => $fromEmail,
                'password' => $password,
                'to' => $to,
                'cc' => $cc,
                'subject' => $subject,
                'message' => $message,
            ]);

            foreach ($requestedDetails as $item) {
                $email = $item['correo'];
                $isAccepted = in_array($email, $result['accepted'], true);
                $detailStmt->execute([
                    ':id_envio' => $sendId,
                    ':tipo_destinatario' => $item['tipo'],
                    ':correo' => $email,
                    ':estado' => $isAccepted ? 'aceptado' : 'fallido',
                    ':respuesta_smtp' => $isAccepted ? correo_truncate((string) ($result['response'] ?? 'Aceptado por SMTP'), 1000) : null,
                    ':detalle_error' => $isAccepted ? null : correo_truncate((string) (($result['failed'][$email] ?? 'No aceptado por SMTP')), 2000),
                ]);
                $isAccepted ? $acceptedCount++ : $failedCount++;
            }
        } catch (Throwable $exception) {
            $errors[] = $to . ': ' . $exception->getMessage();
            foreach ($requestedDetails as $item) {
                $detailStmt->execute([
                    ':id_envio' => $sendId,
                    ':tipo_destinatario' => $item['tipo'],
                    ':correo' => $item['correo'],
                    ':estado' => 'fallido',
                    ':respuesta_smtp' => null,
                    ':detalle_error' => correo_truncate($exception->getMessage(), 2000),
                ]);
                $failedCount++;
            }
        }
    }

    $estado = 'fallido';
    if ($acceptedCount > 0 && $failedCount === 0) {
        $estado = 'enviado';
    } elseif ($acceptedCount > 0 && $failedCount > 0) {
        $estado = 'parcial';
    }

    $update = $pdo->prepare(
        'UPDATE seg_correo_envios
         SET estado_general = :estado_general, error_general = :error_general, procesado_en = NOW()
         WHERE id = :id LIMIT 1'
    );
    $update->execute([
        ':estado_general' => $estado,
        ':error_general' => $errors !== [] ? correo_truncate(implode(' | ', $errors), 2000) : null,
        ':id' => $sendId,
    ]);

    return [
        'ok' => $estado === 'enviado',
        'estado' => $estado,
        'id_envio' => $sendId,
        'aceptados' => $acceptedCount,
        'fallidos' => $failedCount,
        'errores' => $errors,
    ];
}

function correo_run_test(array $user): array
{
    $userId = correo_current_database_user_id($user);
    $config = correo_ensure_config($userId);
    $configId = (int) ($config['id'] ?? 0);
    $recipients = correo_fetch_test_recipients($configId);
    $toEmails = array_map(static fn (array $row): string => (string) ($row['correo'] ?? ''), $recipients);

    $result = correo_send_system($toEmails, (string) $config['asunto_prueba'], (string) $config['mensaje_prueba'], [
        'tipo_envio' => 'prueba',
        'modulo_origen' => 'configuracion-correos',
        'usar_copia_administrativa' => true,
        'user' => $user,
    ]);

    $state = $result['estado'] === 'enviado' ? 'verificada' : ($result['estado'] === 'parcial' ? 'fallida' : 'fallida');
    $stmt = livpDb()->prepare(
        'UPDATE seg_correo_configuracion
         SET estado_verificacion = :estado_verificacion,
             ultima_prueba_en = NOW(),
             ultima_prueba_exitosa_en = CASE WHEN :exitosa = 1 THEN NOW() ELSE ultima_prueba_exitosa_en END,
             ultimo_error = :ultimo_error,
             actualizado_por = :actualizado_por
         WHERE id = :id LIMIT 1'
    );
    $stmt->execute([
        ':estado_verificacion' => $state,
        ':exitosa' => $result['estado'] === 'enviado' ? 1 : 0,
        ':ultimo_error' => $result['errores'] !== [] ? correo_truncate(implode(' | ', $result['errores']), 2000) : null,
        ':actualizado_por' => $userId,
        ':id' => $configId,
    ]);

    correo_audit('probar_notificaciones', 'Se ejecutó una prueba de notificaciones por correo. Resultado: ' . $result['estado'] . '.', $userId);

    return $result;
}
