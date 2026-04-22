<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/mock-data.php';

if (!function_exists('demo_project_root')) {
    function demo_project_root(): string
    {
        return realpath(dirname(__DIR__)) ?: dirname(__DIR__);
    }
}

if (!function_exists('demo_current_script_relative_dir')) {
    function demo_current_script_relative_dir(): string
    {
        $projectRoot = str_replace('\\', '/', demo_project_root());
        $scriptFile = isset($_SERVER['SCRIPT_FILENAME']) ? str_replace('\\', '/', realpath($_SERVER['SCRIPT_FILENAME']) ?: $_SERVER['SCRIPT_FILENAME']) : '';

        if ($scriptFile && str_starts_with($scriptFile, $projectRoot)) {
            $dir = trim(str_replace('\\', '/', substr(dirname($scriptFile), strlen($projectRoot))), '/');
            return $dir;
        }

        return '';
    }
}

if (!function_exists('demo_root_prefix')) {
    function demo_root_prefix(): string
    {
        $relativeDir = demo_current_script_relative_dir();
        if ($relativeDir === '') {
            return '';
        }

        $depth = count(array_filter(explode('/', $relativeDir)));
        return $depth > 0 ? str_repeat('../', $depth) : '';
    }
}

if (!function_exists('demo_url')) {
    function demo_url(string $path = ''): string
    {
        return demo_root_prefix() . ltrim($path, '/');
    }
}

if (!function_exists('demo_current_script_relative_path')) {
    function demo_current_script_relative_path(): string
    {
        $projectRoot = str_replace('\\', '/', demo_project_root());
        $scriptFile = isset($_SERVER['SCRIPT_FILENAME']) ? str_replace('\\', '/', realpath($_SERVER['SCRIPT_FILENAME']) ?: $_SERVER['SCRIPT_FILENAME']) : '';

        if ($scriptFile && str_starts_with($scriptFile, $projectRoot)) {
            return ltrim(str_replace('\\', '/', substr($scriptFile, strlen($projectRoot))), '/');
        }

        return '';
    }
}

if (!function_exists('demo_e')) {
    function demo_e(?string $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('demo_mock_base')) {
    function demo_mock_base(): array
    {
        return demo_mock_seed();
    }
}

if (!function_exists('demo_store_init')) {
    function demo_store_init(): void
    {
        if (!isset($_SESSION['demo_store']) || !is_array($_SESSION['demo_store'])) {
            $_SESSION['demo_store'] = demo_mock_base();
        }

        if (!isset($_SESSION['demo_toasts']) || !is_array($_SESSION['demo_toasts'])) {
            $_SESSION['demo_toasts'] = [];
        }
    }
}

if (!function_exists('demo_store_ref')) {
    function &demo_store_ref(): array
    {
        demo_store_init();
        return $_SESSION['demo_store'];
    }
}

if (!function_exists('demo_store')) {
    function demo_store(?string $key = null, mixed $default = null): mixed
    {
        $store = demo_store_ref();
        if ($key === null) {
            return $store;
        }

        return $store[$key] ?? $default;
    }
}

if (!function_exists('demo_store_set')) {
    function demo_store_set(string $key, mixed $value): void
    {
        $store = &demo_store_ref();
        $store[$key] = $value;
    }
}

if (!function_exists('demo_generate_id')) {
    function demo_generate_id(string $prefix = 'id'): string
    {
        try {
            return $prefix . '_' . bin2hex(random_bytes(4));
        } catch (Throwable $e) {
            return $prefix . '_' . uniqid();
        }
    }
}

if (!function_exists('demo_push_toast')) {
    function demo_push_toast(string $message, string $type = 'info', string $title = ''): void
    {
        demo_store_init();
        $_SESSION['demo_toasts'][] = [
            'id' => demo_generate_id('toast'),
            'title' => $title,
            'message' => $message,
            'type' => $type,
        ];
    }
}

if (!function_exists('demo_consume_toasts')) {
    function demo_consume_toasts(): array
    {
        demo_store_init();
        $toasts = $_SESSION['demo_toasts'] ?? [];
        $_SESSION['demo_toasts'] = [];
        return $toasts;
    }
}

if (!function_exists('demo_users')) {
    function demo_users(): array
    {
        return demo_store('users', []);
    }
}

if (!function_exists('demo_find_user_by_username')) {
    function demo_find_user_by_username(string $username): ?array
    {
        foreach (demo_users() as $user) {
            if (($user['username'] ?? '') === trim($username)) {
                return $user;
            }
        }
        return null;
    }
}

if (!function_exists('demo_find_user_by_id')) {
    function demo_find_user_by_id(string $id): ?array
    {
        foreach (demo_users() as $user) {
            if (($user['id'] ?? '') === $id) {
                return $user;
            }
        }
        return null;
    }
}

if (!function_exists('demo_login_user')) {
    function demo_login_user(array $user): void
    {
        $_SESSION['demo_auth'] = [
            'user_id' => $user['id'],
            'logged_in_at' => date('Y-m-d H:i:s'),
        ];
    }
}

if (!function_exists('demo_logout_user')) {
    function demo_logout_user(): void
    {
        unset($_SESSION['demo_auth']);
    }
}

if (!function_exists('demo_current_user')) {
    function demo_current_user(): ?array
    {
        demo_store_init();
        $auth = $_SESSION['demo_auth'] ?? null;
        if (!$auth || empty($auth['user_id'])) {
            return null;
        }

        return demo_find_user_by_id($auth['user_id']);
    }
}

if (!function_exists('demo_is_logged_in')) {
    function demo_is_logged_in(): bool
    {
        return demo_current_user() !== null;
    }
}

if (!function_exists('demo_current_role')) {
    function demo_current_role(): ?string
    {
        $user = demo_current_user();
        return $user['role'] ?? null;
    }
}

if (!function_exists('demo_is_ajax_request')) {
    function demo_is_ajax_request(): bool
    {
        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return strtolower($requestedWith) === 'xmlhttprequest' || str_contains(strtolower($accept), 'application/json');
    }
}

if (!function_exists('demo_json')) {
    function demo_json(bool $success, array $payload = [], int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(array_merge(['success' => $success], $payload), JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('demo_redirect')) {
    function demo_redirect(string $path): never
    {
        header('Location: ' . demo_url($path));
        exit;
    }
}

if (!function_exists('demo_require_login')) {
    function demo_require_login(): void
    {
        if (demo_is_logged_in()) {
            return;
        }

        if (demo_is_ajax_request()) {
            demo_json(false, ['message' => 'Sesión no válida.', 'redirect' => demo_url('index.php')], 401);
        }

        demo_redirect('index.php');
    }
}

if (!function_exists('demo_require_roles')) {
    function demo_require_roles(array|string $roles): void
    {
        demo_require_login();

        $roles = (array)$roles;
        $role = demo_current_role();

        if (in_array($role, $roles, true)) {
            return;
        }

        if (demo_is_ajax_request()) {
            demo_json(false, ['message' => 'No tienes permisos para esta acción.'], 403);
        }

        demo_push_toast('No tienes permisos para acceder a ese módulo.', 'error', 'Acceso denegado');
        demo_redirect('home.php');
    }
}

if (!function_exists('demo_default_route')) {
    function demo_default_route(string $role): string
    {
        return match ($role) {
            'superadmin', 'gerente' => 'modules/dashboard/gerente.php',
            'ejecutivo'            => 'modules/dashboard/ejecutivo.php',
            'cliente'              => 'modules/portal/inicio.php',
            default                => 'home.php',
        };
    }
}

if (!function_exists('demo_role_label')) {
    function demo_role_label(?string $role): string
    {
        return match ($role) {
            'superadmin' => 'Superadmin',
            'gerente'    => 'Gerente',
            'ejecutivo'  => 'Ejecutivo',
            'cliente'    => 'Cliente',
            default      => 'Invitado',
        };
    }
}

if (!function_exists('demo_badge_class')) {
    function demo_badge_class(?string $status): string
    {
        $status = strtolower(trim((string)$status));

        return match ($status) {
            'activo', 'activa', 'pagada', 'confirmado', 'cerrado', 'renovada' => 'success',
            'pendiente', 'en revisión', 'reportado', 'warning'               => 'warning',
            'vencida', 'anulada', 'error', 'inactivo'                        => 'danger',
            'info'                                                            => 'info',
            default                                                           => 'neutral',
        };
    }
}

if (!function_exists('demo_badge')) {
    function demo_badge(string $text, ?string $tone = null): string
    {
        $class = demo_badge_class($tone ?? $text);
        return '<span class="badge badge-' . demo_e($class) . '">' . demo_e(ucfirst($text)) . '</span>';
    }
}

if (!function_exists('demo_money')) {
    function demo_money(float|int|string $amount, string $currency = 'S/'): string
    {
        return $currency . ' ' . number_format((float)$amount, 2, '.', ',');
    }
}

if (!function_exists('demo_date')) {
    function demo_date(?string $date, string $format = 'd/m/Y'): string
    {
        if (!$date) {
            return '—';
        }

        $timestamp = strtotime($date);
        return $timestamp ? date($format, $timestamp) : $date;
    }
}

if (!function_exists('demo_avatar_initials')) {
    function demo_avatar_initials(?string $name): string
    {
        $name = trim((string)$name);
        if ($name === '') {
            return 'NA';
        }

        $words = preg_split('/\s+/', $name);
        $letters = '';

        foreach ($words as $word) {
            if ($word !== '') {
                $letters .= mb_strtoupper(mb_substr($word, 0, 1));
            }
            if (mb_strlen($letters) >= 2) {
                break;
            }
        }

        return $letters ?: 'NA';
    }
}

if (!function_exists('demo_user_notifications')) {
    function demo_user_notifications(?string $userId = null): array
    {
        $userId ??= demo_current_user()['id'] ?? null;
        if (!$userId) {
            return [];
        }

        return array_values(array_filter(demo_store('notifications', []), fn ($item) => ($item['user_id'] ?? null) === $userId));
    }
}

if (!function_exists('demo_unread_notifications_count')) {
    function demo_unread_notifications_count(?string $userId = null): int
    {
        return count(array_filter(demo_user_notifications($userId), fn ($item) => !($item['read'] ?? false)));
    }
}

if (!function_exists('demo_menu_items')) {
    function demo_menu_items(?string $role = null): array
    {
        $role ??= demo_current_role();

        $managerMenu = [
            ['label' => 'Dashboard', 'icon' => '◫', 'href' => 'modules/dashboard/gerente.php'],
            ['label' => 'Usuarios', 'icon' => '👥', 'href' => 'modules/usuarios/index.php'],
            ['label' => 'Clientes', 'icon' => '▣', 'href' => 'modules/clientes/index.php'],
            ['label' => 'Pólizas', 'icon' => '🛡', 'href' => 'modules/polizas/index.php'],
            ['label' => 'Cobranzas', 'icon' => '💳', 'href' => 'modules/cobranzas/index.php'],
            ['label' => 'Siniestros', 'icon' => '⚠', 'href' => 'modules/siniestros/index.php'],
            ['label' => 'Catálogos', 'icon' => '☰', 'href' => 'modules/catalogos/index.php'],
            ['label' => 'Reportes', 'icon' => '📄', 'href' => 'modules/reportes/index.php'],
        ];

        $executiveMenu = [
            ['label' => 'Dashboard', 'icon' => '◫', 'href' => 'modules/dashboard/ejecutivo.php'],
            ['label' => 'Clientes', 'icon' => '▣', 'href' => 'modules/ejecutivo/clientes.php'],
            ['label' => 'Pólizas', 'icon' => '🛡', 'href' => 'modules/ejecutivo/polizas.php'],
            ['label' => 'Cobranzas', 'icon' => '💳', 'href' => 'modules/ejecutivo/cobranzas.php'],
            ['label' => 'Siniestros', 'icon' => '⚠', 'href' => 'modules/ejecutivo/siniestros.php'],
            ['label' => 'Catálogos', 'icon' => '☰', 'href' => 'modules/ejecutivo/catalogos.php'],
        ];

        $clientMenu = [
            ['label' => 'Inicio', 'icon' => '⌂', 'href' => 'modules/portal/inicio.php'],
            ['label' => 'Mis pólizas', 'icon' => '🛡', 'href' => 'modules/portal/polizas.php'],
            ['label' => 'Mis pagos', 'icon' => '💳', 'href' => 'modules/portal/pagos.php'],
            ['label' => 'Mis siniestros', 'icon' => '⚠', 'href' => 'modules/portal/siniestros.php'],
            ['label' => 'Mi perfil', 'icon' => '👤', 'href' => 'modules/portal/perfil.php'],
        ];

        return match ($role) {
            'superadmin', 'gerente' => $managerMenu,
            'ejecutivo'             => $executiveMenu,
            'cliente'               => $clientMenu,
            default                 => [],
        };
    }
}

if (!function_exists('demo_active_menu')) {
    function demo_active_menu(string $href): bool
    {
        $current = ltrim(demo_current_script_relative_path(), '/');
        $href = ltrim($href, '/');

        return $current === $href;
    }
}

if (!function_exists('demo_find_by_id')) {
    function demo_find_by_id(array $items, string $id): ?array
    {
        foreach ($items as $item) {
            if (($item['id'] ?? null) === $id) {
                return $item;
            }
        }
        return null;
    }
}

if (!function_exists('demo_client_name')) {
    function demo_client_name(string $clientId): string
    {
        $client = demo_find_by_id(demo_store('clients', []), $clientId);
        return $client['name'] ?? 'Cliente no encontrado';
    }
}

if (!function_exists('demo_policy_number')) {
    function demo_policy_number(string $policyId): string
    {
        $policy = demo_find_by_id(demo_store('policies', []), $policyId);
        return $policy['policy_number'] ?? 'Sin póliza';
    }
}

if (!function_exists('demo_insurer_name')) {
    function demo_insurer_name(string $insurerId): string
    {
        $insurer = demo_find_by_id(demo_store('insurers', []), $insurerId);
        return $insurer['name'] ?? 'Aseguradora';
    }
}

if (!function_exists('demo_insurance_type_name')) {
    function demo_insurance_type_name(string $typeId): string
    {
        $type = demo_find_by_id(demo_store('insurance_types', []), $typeId);
        return $type['name'] ?? 'Tipo';
    }
}

if (!function_exists('demo_filter_clients_by_executive')) {
    function demo_filter_clients_by_executive(array $clients, ?string $executiveId = null): array
    {
        if (!$executiveId) {
            return $clients;
        }

        return array_values(array_filter($clients, fn ($client) => ($client['assigned_executive_user_id'] ?? null) === $executiveId));
    }
}

if (!function_exists('demo_filter_policies_by_executive')) {
    function demo_filter_policies_by_executive(array $policies, ?string $executiveId = null): array
    {
        if (!$executiveId) {
            return $policies;
        }

        return array_values(array_filter($policies, fn ($policy) => ($policy['assigned_executive_user_id'] ?? null) === $executiveId));
    }
}

if (!function_exists('demo_has_role')) {
    function demo_has_role(array|string $roles): bool
    {
        return in_array(demo_current_role(), (array)$roles, true);
    }
}

demo_store_init();