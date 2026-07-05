<?php
declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';
require __DIR__ . '/config/client_accounts.php';

$context = requireModuleAccess('usuarios');
$user = $context['user'];
$module = $context['module'];
$menu = modulesForRole((string) $user['role']);
$activeModule = 'usuarios';
$pageTitle = 'Usuarios Cliente';

if ((string) $user['role'] !== 'gerente') {
    header('Location: ' . appRelativeUrl('acceso_denegado.php?modulo=usuarios&motivo=permiso'));
    exit;
}

$defaults = require __DIR__ . '/config/demo_clients.php';
$expedients = require __DIR__ . '/config/demo_expedients.php';

function usuariosPortalJson(mixed $value): string
{
    $json = json_encode(
        $value,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
    );

    return $json === false ? '{}' : $json;
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(APP_NAME) ?> | <?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="assets/css/app.css?v=BS-20260627-234916-CLIENTACCOUNTSV1">
    <link rel="stylesheet" href="assets/css/modules.css?v=BS-20260627-234916-CLIENTACCOUNTSV1">
    <link rel="stylesheet" href="assets/css/client-accounts.css?v=BS-20260627-234916-CLIENTACCOUNTSV1">
</head>
<body class="app-body" data-role="<?= e((string) $user['role']) ?>" data-user="<?= e((string) $user['id']) ?>">
<div class="app-shell">
    <?php require __DIR__ . '/views/partials/sidebar.php'; ?>
    <div class="sidebar-overlay" id="sidebar-overlay"></div>

    <main class="workspace">
        <?php require __DIR__ . '/views/partials/topbar.php'; ?>

        <section class="workspace-content">
            <article class="module-hero">
                <div class="module-hero-icon" aria-hidden="true">♙</div>
                <div>
                    <p class="eyebrow">ADMINISTRACIÓN</p>
                    <h2>Accesos de empresas y consorcios</h2>
                    <p>Crea, actualiza, activa o desactiva cuentas Cliente vinculadas a las entidades registradas.</p>
                </div>
                <span class="module-access-badge">Solo Gerente</span>
            </article>

            <section id="client-accounts-app" class="client-accounts-app" aria-live="polite">
                <section class="client-sync-card">
                    <div>
                        <p class="eyebrow">PUBLICACIÓN DE CARTERA</p>
                        <h3>Actualizar datos para los portales Cliente</h3>
                        <p>Envía el estado actual de Empresas, Consorcios, Expedientes y Pólizas de esta maqueta al almacenamiento protegido del servidor. Hazlo después de crear o editar información que deba ver un Cliente desde otro navegador.</p>
                        <small id="client-sync-status">Aún no se consultó el estado de publicación.</small>
                    </div>
                    <button id="client-sync-button" class="client-account-primary" type="button">Publicar cartera actual</button>
                </section>

                <section class="client-account-grid">
                    <article class="client-account-card">
                        <div class="client-account-heading">
                            <div>
                                <p class="eyebrow">NUEVO ACCESO</p>
                                <h3>Crear o actualizar cuenta Cliente</h3>
                                <p>La entidad debe tener un RUC propio de 11 dígitos. La contraseña se guarda cifrada y no vuelve a mostrarse.</p>
                            </div>
                        </div>

                        <form id="client-account-form" class="client-account-form" novalidate>
                            <label class="wide">
                                <span>Empresa o consorcio <b>*</b></span>
                                <select id="client-account-entity" required>
                                    <option value="">Selecciona una entidad</option>
                                </select>
                                <small>Los datos provienen de Clientes registrados en esta maqueta.</small>
                            </label>

                            <label>
                                <span>RUC de acceso</span>
                                <input id="client-account-document" maxlength="11" inputmode="numeric" readonly>
                            </label>

                            <label>
                                <span>Tipo de entidad</span>
                                <input id="client-account-type" readonly>
                            </label>

                            <label class="wide">
                                <span>Contraseña <b>*</b></span>
                                <input id="client-account-password" type="password" minlength="8" maxlength="100" autocomplete="new-password" placeholder="Mínimo 8 caracteres" required>
                                <small>Al actualizar una cuenta existente, esta será su nueva contraseña.</small>
                            </label>

                            <label class="wide">
                                <span>Confirmar contraseña <b>*</b></span>
                                <input id="client-account-password-confirm" type="password" minlength="8" maxlength="100" autocomplete="new-password" placeholder="Repite la contraseña" required>
                            </label>

                            <div class="client-account-actions">
                                <button id="client-account-submit" class="client-account-primary" type="submit">Guardar acceso</button>
                            </div>
                        </form>
                    </article>

                    <article class="client-account-card">
                        <div class="client-account-heading">
                            <div>
                                <p class="eyebrow">ACCESOS CREADOS</p>
                                <h3>Cuentas Cliente</h3>
                                <p>Una cuenta desactivada no puede iniciar sesión. No se elimina el historial de su entidad.</p>
                            </div>
                        </div>
                        <div id="client-account-list" class="client-account-list">
                            <p class="client-account-empty">Cargando cuentas…</p>
                        </div>
                    </article>
                </section>

                <section class="client-account-note">
                    <strong>Alcance de maqueta:</strong>
                    <span>Las cuentas se guardan en un archivo protegido del servidor. La cartera publicada se almacena como una instantánea de demostración; la fase productiva migrará estos datos a MySQL con permisos y auditoría completos.</span>
                </section>
            </section>
        </section>
    </main>
</div>

<script id="account-default-entities" type="application/json"><?= usuariosPortalJson($defaults) ?></script>
<script id="account-default-expedients" type="application/json"><?= usuariosPortalJson($expedients) ?></script>
<script src="assets/js/cache-migrations.js?v=BS-20260627-234916-CLIENTACCOUNTSV1"></script>
<script src="assets/js/app.js?v=BS-20260627-234916-CLIENTACCOUNTSV1"></script>
<script src="assets/js/client-accounts.js?v=BS-20260627-234916-CLIENTACCOUNTSV1"></script>
</body>
</html>
