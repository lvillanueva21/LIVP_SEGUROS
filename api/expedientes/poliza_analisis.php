<?php
declare(strict_types=1);

require_once __DIR__ . '/_expedientes_common.php';

$accion = strtolower(trim((string) ($_GET['accion'] ?? $_POST['accion'] ?? 'analizar_pdf')));

try {
    switch ($accion) {
        case 'conocimiento':
            pa_conocimiento();
            break;
        case 'analizar_pdf':
            pa_analizar_pdf();
            break;
        default:
            exp_json_error('Accion no valida.', 404);
    }
} catch (PDOException $e) {
    exp_db_error($e);
} catch (Throwable $e) {
    exp_db_error($e);
}

function pa_conocimiento(): void
{
    exp_require_method('GET');
    exp_require_perm('puede_ver');
    exp_json_success([
        'conocimiento' => pa_load_knowledge(),
        'csrf' => cb_local_csrf_token('expedientes'),
    ]);
}

function pa_analizar_pdf(): void
{
    $payload = exp_require_change('puede_crear');
    $modo = strtolower(trim((string) ($payload['modo_extraccion'] ?? 'auto')));
    if (!in_array($modo, ['auto', 'texto_pdf', 'ocr'], true)) {
        $modo = 'auto';
    }
    if (!isset($_FILES['archivo_pdf']) || !is_array($_FILES['archivo_pdf'])) {
        exp_json_error('Seleccione un PDF para analizar.', 422, ['archivo_pdf' => 'PDF requerido.']);
    }
    pa_validate_pdf((array) $_FILES['archivo_pdf']);

    $messages = [];
    $text = '';
    $method = 'ninguno';
    $status = 'sin_texto';

    if ($modo !== 'ocr') {
        $text = pa_extract_pdftotext((array) $_FILES['archivo_pdf'], $messages);
        if (trim($text) !== '') {
            $method = 'texto_pdf';
            $status = 'texto_extraido';
        }
    }

    if ($modo === 'ocr' || ($modo === 'auto' && pa_needs_ocr($text))) {
        $messages[] = 'OCR local copiado en plugins/tesseract; para PDF escaneado se requiere motor de renderizado PDF a imagen o binarios de servidor disponibles.';
        if ($method === 'ninguno') {
            $method = 'ocr_pendiente';
            $status = 'ocr_no_disponible';
        }
    }

    $knowledge = pa_load_knowledge();
    $parsed = pa_parse_policy_text($text, $knowledge, exp_db());
    $confidence = pa_confidence($parsed, $text);

    exp_json_success([
        'estado_extraccion' => $status,
        'metodo_usado' => $method,
        'requiere_ocr' => pa_needs_ocr($text),
        'confianza_global' => $confidence,
        'campos' => $parsed['campos'],
        'candidatos' => $parsed['candidatos'],
        'texto_extraido' => pa_limit_text($text, 60000),
        'texto_preview' => pa_limit_text($text, 2500),
        'mensajes' => $messages,
        'conocimiento_version' => (string) ($knowledge['version'] ?? ''),
    ], trim($text) === '' ? 'No se pudo extraer texto util del PDF. Puedes llenar el formulario manualmente.' : 'Analisis completado. Revisa los datos antes de guardar.');
}

function pa_validate_pdf(array $fileInfo): void
{
    $error = (int) ($fileInfo['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        exp_json_error('No se pudo recibir el PDF.', 422, ['archivo_pdf' => 'PDF requerido.']);
    }
    $name = basename((string) ($fileInfo['name'] ?? ''));
    $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
    $mime = cb_almacen_detect_mime((string) ($fileInfo['tmp_name'] ?? ''));
    if ($extension !== 'pdf' || strtolower($mime) !== 'application/pdf') {
        exp_json_error('El archivo debe ser un PDF valido.', 422, ['archivo_pdf' => 'Solo PDF.']);
    }
}

function pa_extract_pdftotext(array $fileInfo, array &$messages): string
{
    $tmp = (string) ($fileInfo['tmp_name'] ?? '');
    if ($tmp === '' || !is_file($tmp)) {
        return '';
    }
    if (!pa_command_exists('pdftotext')) {
        $messages[] = 'Extraccion simple no disponible: el servidor no tiene pdftotext en PATH.';
        return '';
    }
    $out = tempnam(sys_get_temp_dir(), 'seg_pdf_text_');
    if (!is_string($out) || $out === '') {
        return '';
    }
    $cmd = 'pdftotext -layout -enc UTF-8 ' . escapeshellarg($tmp) . ' ' . escapeshellarg($out);
    $lines = [];
    $code = 1;
    @exec($cmd, $lines, $code);
    $text = ($code === 0 && is_file($out)) ? (string) @file_get_contents($out) : '';
    @unlink($out);
    if ($text === '') {
        $messages[] = 'pdftotext no devolvio texto util. El PDF puede ser escaneado o estar protegido.';
    }
    return pa_normalize_spaces($text);
}

function pa_command_exists(string $command): bool
{
    $command = preg_replace('/[^A-Za-z0-9_.-]/', '', $command);
    if ($command === '') {
        return false;
    }
    $probe = stripos(PHP_OS_FAMILY, 'Windows') !== false ? 'where ' . escapeshellarg($command) : 'command -v ' . escapeshellarg($command);
    $out = [];
    $code = 1;
    @exec($probe, $out, $code);
    return $code === 0 && !empty($out);
}

function pa_load_knowledge(): array
{
    $path = dirname(__DIR__, 2) . '/data/polizas_extraccion_conocimiento.json';
    $raw = is_file($path) ? (string) file_get_contents($path) : '{}';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function pa_parse_policy_text(string $text, array $knowledge, PDO $pdo): array
{
    $text = pa_normalize_spaces($text);
    $upper = pa_upper($text);
    $campos = [];
    $candidatos = [];

    $campos['tipo_documento_emitido'] = pa_detect_document_type($upper, $knowledge);
    $campos['numero_documento'] = pa_first_match($text, [
        '/N(?:U|Ú)MERO\s+DE\s+P(?:O|Ó)LIZA\s*[:#]?\s*([A-Z0-9\-\/.]+)/iu',
        '/P(?:O|Ó)LIZA\s*(?:N[°ºO.]*)?\s*[:#]?\s*([A-Z0-9\-\/.]{5,})/iu',
        '/(?:CARTA\s+FIANZA|FIANZA)\s*(?:N[°ºO.]*)?\s*[:#]?\s*([A-Z0-9\-\/.]{5,})/iu',
        '/CONSTANCIA\s*(?:N[°ºO.]*)?\s*[:#]?\s*([A-Z0-9\-\/.]{5,})/iu',
        '/(?:ENDOSO|SUPLEMENTO)\s*(?:N[°ºO.]*)?\s*[:#]?\s*([A-Z0-9\-\/.]{5,})/iu'
    ]);

    $aseg = pa_detect_aseguradora($pdo, $text, $upper, $knowledge);
    if ($aseg) {
        $campos['aseguradora_id'] = (int) $aseg['id'];
        $candidatos['aseguradora_detectada'] = $aseg;
    }

    $campos['fecha_emision'] = pa_date_to_input(pa_first_match($text, [
        '/Fecha\s+de\s+Emisi(?:o|ó)n\s*:\s*([0-9]{1,2}[\/\-][0-9]{1,2}[\/\-][0-9]{4})/iu',
        '/Emitido\s+en\s+.+?,\s*el\s*([0-9]{1,2}\s+de\s+[A-Za-zÁÉÍÓÚáéíóú]+\s+de\s+[0-9]{4})/iu',
        '/Lima,\s*([0-9]{1,2}\s+de\s+[A-Za-zÁÉÍÓÚáéíóú]+\s+de\s+[0-9]{4})/iu'
    ]));

    $vig = pa_first_match_group($text, [
        '/Inicio\s+de\s+Vigencia\s*:\s*([0-9]{1,2}[\/\-][0-9]{1,2}[\/\-][0-9]{4})(?:\s+([0-9]{1,2}:[0-9]{2}))?[\s\S]{0,180}?Vencimiento\s*:\s*([0-9]{1,2}[\/\-][0-9]{1,2}[\/\-][0-9]{4})(?:\s+([0-9]{1,2}:[0-9]{2}))?/iu',
        '/VIGENCIA\s*:\s*([0-9]{1,2}[\/\-][0-9]{1,2}[\/\-][0-9]{4})(?:\s+([0-9]{1,2}:[0-9]{2}))?\s*(?:AL|A|HASTA|\/|-)\s*([0-9]{1,2}[\/\-][0-9]{1,2}[\/\-][0-9]{4})(?:\s+([0-9]{1,2}:[0-9]{2}))?/iu',
        '/vigencia\s+del\s+([0-9]{1,2}[\/\-][0-9]{1,2}[\/\-][0-9]{4})(?:\s+([0-9]{1,2}:[0-9]{2}))?\s+hasta\s+el\s+([0-9]{1,2}[\/\-][0-9]{1,2}[\/\-][0-9]{4})(?:\s+([0-9]{1,2}:[0-9]{2}))?/iu'
    ]);
    if ($vig) {
        $campos['vigencia_inicio'] = pa_datetime_to_input((string) $vig[1], (string) ($vig[2] ?? '00:00'), '00:00');
        $campos['vigencia_fin'] = pa_datetime_to_input((string) $vig[3], (string) ($vig[4] ?? '23:59'), '23:59');
    }

    $money = pa_money_fields($text);
    $campos = array_merge($campos, $money);
    $campos['moneda'] = pa_detect_currency($text, $upper);
    $campos['beneficiario_nombre'] = pa_first_match($text, [
        '/Beneficiario\s*:\s*([^\n]+)/iu',
        '/A\s+favor\s+de\s*:\s*([^\n]+)/iu',
        '/Entidad\s+Contratante\s*:\s*([^\n]+)/iu'
    ]);
    $candidatos['contratante_nombre'] = pa_first_match($text, [
        '/Contratante\s*:\s*(.+?)(?:\s+Doc\.|\n)/iu',
        '/Tomador\s*:\s*([^\n]+)/iu',
        '/Afianzado\s*:\s*([^\n]+)/iu'
    ]);
    $candidatos['contratante_ruc'] = pa_first_match($text, ['/RUC\s*[:#]?\s*([0-9]{11})/iu']);

    return [
        'campos' => array_filter($campos, static fn($v) => $v !== null && $v !== ''),
        'candidatos' => array_filter($candidatos, static fn($v) => $v !== null && $v !== ''),
    ];
}

function pa_detect_document_type(string $upper, array $knowledge): string
{
    $types = $knowledge['tipos_documento'] ?? [];
    foreach ($types as $code => $terms) {
        foreach ((array) $terms as $term) {
            if ($term !== '' && strpos($upper, pa_upper((string) $term)) !== false) {
                return (string) $code;
            }
        }
    }
    return 'poliza';
}

function pa_detect_aseguradora(PDO $pdo, string $text, string $upper, array $knowledge): ?array
{
    $stmt = $pdo->query('SELECT id, razon_social, nombre_comercial FROM seg_aseguradoras WHERE estado = 1 ORDER BY nombre_comercial ASC, razon_social ASC');
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $names = [(string) ($row['razon_social'] ?? ''), (string) ($row['nombre_comercial'] ?? '')];
        foreach ($names as $name) {
            $needle = pa_upper($name);
            if ($needle !== '' && strpos($upper, $needle) !== false) {
                return ['id' => (int) $row['id'], 'nombre' => $name, 'origen' => 'catalogo'];
            }
        }
    }
    foreach (($knowledge['aseguradoras_comunes'] ?? []) as $name) {
        if (strpos($upper, pa_upper((string) $name)) !== false) {
            return ['id' => 0, 'nombre' => (string) $name, 'origen' => 'conocimiento'];
        }
    }
    $free = pa_first_match($text, ['/([A-ZÁÉÍÓÚÑ ]+(?:SEGUROS|ASEGURADORA|EPS|REASEGUROS)[A-ZÁÉÍÓÚÑ .]*)/u']);
    return $free !== '' ? ['id' => 0, 'nombre' => $free, 'origen' => 'texto'] : null;
}

function pa_money_fields(string $text): array
{
    $out = [];
    $out['suma_asegurada'] = pa_money_to_decimal(pa_first_match($text, [
        '/(?:Suma\s+Asegurada|Monto\s+Asegurado|Monto\s+Garantizado|Importe\s+Garantizado|Suma\s+Afianzada)\s*[:#]?\s*(?:US\$|USD|S\/|PEN)?\s*([0-9][0-9.,]*)/iu'
    ]));
    $out['prima_total'] = pa_money_to_decimal(pa_first_match($text, [
        '/(?:Prima\s+Total(?:\s+con\s+IGV)?|Prima\s+Comercial\s*\+\s*IGV|Total\s+a\s+pagar|Importe\s+Total)\s*[:#]?\s*(?:US\$|USD|S\/|PEN)?\s*([0-9][0-9.,]*)/iu'
    ]));
    $out['prima_comercial'] = pa_money_to_decimal(pa_first_match($text, [
        '/(?:Prima\s+Comercial|Prima\s+Neta|Prima\s+Resultante)\s*[:#]?\s*(?:US\$|USD|S\/|PEN)?\s*([0-9][0-9.,]*)/iu'
    ]));
    $out['igv'] = pa_money_to_decimal(pa_first_match($text, [
        '/(?:IGV|Impuesto\s+General\s+a\s+las\s+Ventas)\s*[:#]?\s*(?:US\$|USD|S\/|PEN)?\s*([0-9][0-9.,]*)/iu'
    ]));
    return array_filter($out, static fn($v) => $v !== '');
}

function pa_detect_currency(string $text, string $upper): string
{
    if (strpos($upper, 'USD') !== false || strpos($text, 'US$') !== false || stripos($upper, 'DOLARES') !== false) {
        return 'USD';
    }
    if (strpos($upper, 'EUR') !== false) {
        return 'EUR';
    }
    return 'PEN';
}

function pa_confidence(array $parsed, string $text): int
{
    if (trim($text) === '') {
        return 0;
    }
    $keys = ['aseguradora_id', 'numero_documento', 'fecha_emision', 'vigencia_inicio', 'vigencia_fin', 'suma_asegurada', 'prima_total'];
    $hits = 0;
    foreach ($keys as $key) {
        if (!empty($parsed['campos'][$key])) {
            $hits++;
        }
    }
    return min(95, 20 + ($hits * 10));
}

function pa_needs_ocr(string $text): bool
{
    $clean = preg_replace('/\s+/', '', $text);
    return strlen((string) $clean) < 120;
}

function pa_first_match(string $text, array $regexes): string
{
    foreach ($regexes as $rx) {
        if (preg_match($rx, $text, $m) === 1) {
            return pa_clean((string) ($m[1] ?? $m[0]));
        }
    }
    return '';
}

function pa_first_match_group(string $text, array $regexes): ?array
{
    foreach ($regexes as $rx) {
        if (preg_match($rx, $text, $m) === 1) {
            return $m;
        }
    }
    return null;
}

function pa_date_to_input(string $value): string
{
    $dt = pa_parse_date($value);
    return $dt ? $dt->format('Y-m-d') : '';
}

function pa_datetime_to_input(string $date, string $time, string $fallback): string
{
    $dt = pa_parse_date($date);
    if (!$dt) {
        return '';
    }
    $time = preg_match('/^\d{1,2}:\d{2}$/', $time) === 1 ? $time : $fallback;
    [$h, $i] = array_map('intval', explode(':', $time));
    return $dt->setTime($h, $i)->format('Y-m-d\TH:i');
}

function pa_parse_date(string $value): ?DateTimeImmutable
{
    $value = pa_clean($value);
    if ($value === '') {
        return null;
    }
    $tz = new DateTimeZone('America/Lima');
    foreach (['d/m/Y', 'd-m-Y'] as $fmt) {
        $dt = DateTimeImmutable::createFromFormat($fmt, $value, $tz);
        if ($dt instanceof DateTimeImmutable) {
            return $dt;
        }
    }
    $lower = strtolower($value);
    $months = ['enero' => '01', 'febrero' => '02', 'marzo' => '03', 'abril' => '04', 'mayo' => '05', 'junio' => '06', 'julio' => '07', 'agosto' => '08', 'septiembre' => '09', 'setiembre' => '09', 'octubre' => '10', 'noviembre' => '11', 'diciembre' => '12'];
    if (preg_match('/(\d{1,2})\s+de\s+([a-záéíóú]+)\s+de\s+(\d{4})/iu', $lower, $m) === 1) {
        $month = $months[pa_unaccent($m[2])] ?? null;
        if ($month) {
            return DateTimeImmutable::createFromFormat('Y-m-d', $m[3] . '-' . $month . '-' . str_pad($m[1], 2, '0', STR_PAD_LEFT), $tz) ?: null;
        }
    }
    return null;
}

function pa_money_to_decimal(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    $value = preg_replace('/[^0-9.,]/', '', $value);
    if (substr_count($value, ',') > 0 && substr_count($value, '.') > 0) {
        $value = str_replace(',', '', $value);
    } elseif (substr_count($value, ',') === 1 && substr_count($value, '.') === 0) {
        $value = str_replace(',', '.', $value);
    }
    return is_numeric($value) ? number_format((float) $value, 2, '.', '') : '';
}

function pa_normalize_spaces(string $value): string
{
    $value = str_replace("\r", "\n", $value);
    $value = preg_replace('/[\t ]+/', ' ', $value);
    $value = preg_replace('/\n[ \t]+/', "\n", (string) $value);
    $value = preg_replace('/[ \t]+\n/', "\n", (string) $value);
    return trim((string) preg_replace('/\n{3,}/', "\n\n", (string) $value));
}

function pa_clean(string $value): string
{
    return trim((string) preg_replace('/\s+/', ' ', $value));
}

function pa_upper(string $value): string
{
    return strtoupper(pa_unaccent($value));
}

function pa_unaccent(string $value): string
{
    return strtr($value, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N', 'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);
}

function pa_limit_text(string $text, int $max): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $max, 'UTF-8');
    }
    return substr($text, 0, $max);
}
