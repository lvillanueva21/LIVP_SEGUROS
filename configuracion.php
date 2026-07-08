<?php

declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';

$context = requireModuleAccess('configuracion');
$user = $context['user'];
$module = $context['module'];
$menu = modulesForRole((string) $user['role']);
$activeModule = 'configuracion';
$pageTitle = 'Configuración';

$options = [
    [
        'id' => 'configuracion-archivos',
        'icon' => '▧',
        'title' => 'Gestión de Archivos',
        'description' => 'Configuración futura de almacenamiento, rutas, documentos y archivos adjuntos.',
        'url' => moduleUrl('configuracion-archivos'),
    ],
    [
        'id' => 'configuracion-correos',
        'icon' => '✉',
        'title' => 'Gestión de Correos',
        'description' => 'Configuración real del correo Zoho remitente, prueba SMTP, historial y métricas de notificaciones.',
        'url' => appRelativeUrl('configuracion_correos.php'),
    ],
    [
        'id' => 'configuracion-whatsapp',
        'icon' => '◍',
        'title' => 'Gestión de WhatsApp',
        'description' => 'Configuración futura de WhatsApp, mensajes automáticos e integraciones.',
        'url' => moduleUrl('configuracion-whatsapp'),
    ],
];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(APP_NAME) ?> | Configuración</title>
    <link rel="stylesheet" href="assets/css/app.css?v=BS-CORREOV1">
    <link rel="stylesheet" href="assets/css/modules.css?v=BS-CORREOV1">
</head>
<body class="app-body" data-role="<?= e((string) $user['role']) ?>" data-user="<?= e((string) $user['id']) ?>">
<div class="app-shell">
    <?php require __DIR__ . '/views/partials/sidebar.php'; ?>
    <div class="sidebar-overlay" id="sidebar-overlay"></div>
    <main class="workspace">
        <?php require __DIR__ . '/views/partials/topbar.php'; ?>
        <section class="workspace-content">
            <article class="module-hero">
                <div class="module-hero-icon" aria-hidden="true">⚙</div>
                <div>
                    <p class="eyebrow">DESARROLLO</p>
                    <h2>Configuración</h2>
                    <p>Secciones preparadas para configuraciones técnicas del sistema.</p>
                </div>
                <span class="module-access-badge">Acceso habilitado</span>
            </article>

            <section class="configuration-menu-grid" aria-label="Submenús de configuración">
                <?php foreach ($options as $option): ?>
                    <a class="configuration-menu-card" href="<?= e((string) $option['url']) ?>">
                        <span class="configuration-menu-icon" aria-hidden="true"><?= e((string) $option['icon']) ?></span>
                        <h3><?= e((string) $option['title']) ?></h3>
                        <p><?= e((string) $option['description']) ?></p>
                    </a>
                <?php endforeach; ?>
            </section>
        </section>
    </main>
</div>
<script src="assets/js/app.js?v=BS-CORREOV1"></script>
</body>
</html>
