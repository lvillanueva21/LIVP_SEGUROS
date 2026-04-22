<?php
$portalActive = $portalActive ?? 'inicio';
$portalUser = $portalUser ?? demo_current_user();
$portalClient = $portalClient ?? null;

$portalLinks = [
    'inicio' => [
        'label' => 'Inicio',
        'url' => demo_url('modules/portal/inicio.php'),
        'icon' => '⌂',
    ],
    'polizas' => [
        'label' => 'Mis pólizas',
        'url' => demo_url('modules/portal/polizas.php'),
        'icon' => '🛡',
    ],
    'pagos' => [
        'label' => 'Mis pagos',
        'url' => demo_url('modules/portal/pagos.php'),
        'icon' => '💳',
    ],
    'siniestros' => [
        'label' => 'Mis siniestros',
        'url' => demo_url('modules/portal/siniestros.php'),
        'icon' => '⚠',
    ],
    'perfil' => [
        'label' => 'Mi perfil',
        'url' => demo_url('modules/portal/perfil.php'),
        'icon' => '👤',
    ],
];
?>
<style>
    .portal-shell {
        display: grid;
        gap: 1rem;
        grid-template-columns: 280px minmax(0, 1fr);
    }

    .portal-sidebar {
        position: sticky;
        top: 1rem;
        align-self: start;
        border: 1px solid rgba(219, 227, 239, .9);
        border-radius: 24px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        box-shadow: var(--shadow-sm);
        overflow: hidden;
    }

    .portal-sidebar__header {
        padding: 1.1rem 1.1rem 1rem;
        border-bottom: 1px solid rgba(226, 232, 240, .8);
        background:
            radial-gradient(circle at top right, rgba(79, 70, 229, .14), transparent 34%),
            radial-gradient(circle at bottom left, rgba(14, 165, 164, .12), transparent 32%),
            linear-gradient(180deg, #ffffff 0%, #f7fbff 100%);
    }

    .portal-sidebar__brand {
        display: flex;
        align-items: center;
        gap: .9rem;
        margin-bottom: .95rem;
    }

    .portal-sidebar__brand-badge {
        width: 52px;
        height: 52px;
        border-radius: 18px;
        display: grid;
        place-items: center;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: #fff;
        font-weight: 900;
        box-shadow: var(--shadow-sm);
    }

    .portal-sidebar__brand-title {
        margin: 0;
        font-size: 1.02rem;
    }

    .portal-sidebar__brand-text {
        margin: .15rem 0 0;
        color: var(--text-soft);
        font-size: .86rem;
        line-height: 1.4;
    }

    .portal-sidebar__user {
        display: grid;
        gap: .75rem;
        grid-template-columns: auto 1fr;
        align-items: center;
    }

    .portal-sidebar__avatar {
        width: 52px;
        height: 52px;
        border-radius: 18px;
        display: grid;
        place-items: center;
        background: linear-gradient(135deg, rgba(79, 70, 229, .14), rgba(14, 165, 164, .14));
        color: var(--primary);
        font-weight: 800;
        font-size: 1rem;
        border: 1px solid rgba(79, 70, 229, .12);
    }

    .portal-sidebar__name {
        margin: 0;
        font-size: .98rem;
        line-height: 1.2;
    }

    .portal-sidebar__meta {
        margin: .2rem 0 0;
        color: var(--text-soft);
        font-size: .84rem;
        line-height: 1.45;
        overflow-wrap: anywhere;
    }

    .portal-sidebar__nav {
        display: grid;
        gap: .4rem;
        padding: 1rem;
    }

    .portal-sidebar__link {
        display: grid;
        grid-template-columns: 24px 1fr;
        align-items: center;
        gap: .8rem;
        min-height: 48px;
        padding: .8rem .95rem;
        border-radius: 16px;
        color: var(--text);
        text-decoration: none;
        transition: background var(--transition), color var(--transition), transform var(--transition), box-shadow var(--transition);
    }

    .portal-sidebar__link:hover {
        background: rgba(79, 70, 229, .06);
        transform: translateY(-1px);
    }

    .portal-sidebar__link.is-active {
        background: linear-gradient(135deg, rgba(79, 70, 229, .12), rgba(14, 165, 164, .10));
        color: var(--primary);
        font-weight: 700;
        box-shadow: inset 0 0 0 1px rgba(79, 70, 229, .08);
    }

    .portal-sidebar__link-icon {
        display: grid;
        place-items: center;
        font-size: 1rem;
    }

    .portal-sidebar__footer {
        padding: 0 1rem 1rem;
    }

    .portal-sidebar__hint {
        padding: .9rem 1rem;
        border-radius: 16px;
        border: 1px dashed rgba(100, 116, 139, .25);
        background: linear-gradient(180deg, #fbfdff 0%, #f7faff 100%);
        color: var(--text-soft);
        font-size: .86rem;
        line-height: 1.5;
    }

    .portal-main {
        min-width: 0;
    }

    @media (max-width: 980px) {
        .portal-shell {
            grid-template-columns: 1fr;
        }

        .portal-sidebar {
            position: static;
        }

        .portal-sidebar__nav {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 620px) {
        .portal-sidebar__nav {
            grid-template-columns: 1fr;
        }
    }
</style>

<aside class="portal-sidebar">
    <div class="portal-sidebar__header">
        <div class="portal-sidebar__brand">
            <div class="portal-sidebar__brand-badge">BS</div>
            <div>
                <h2 class="portal-sidebar__brand-title">Portal cliente</h2>
                <p class="portal-sidebar__brand-text">Tu espacio seguro para revisar pólizas, pagos y solicitudes.</p>
            </div>
        </div>

        <div class="portal-sidebar__user">
            <div class="portal-sidebar__avatar"><?= demo_e(demo_avatar_initials($portalUser['full_name'] ?? 'Cliente')) ?></div>
            <div>
                <h3 class="portal-sidebar__name"><?= demo_e($portalUser['full_name'] ?? 'Cliente') ?></h3>
                <p class="portal-sidebar__meta">
                    <?= demo_e($portalClient['document_type'] ?? 'Doc') ?>
                    <?= demo_e($portalClient['document_number'] ?? ($portalUser['document'] ?? '—')) ?>
                </p>
            </div>
        </div>
    </div>

    <nav class="portal-sidebar__nav" aria-label="Navegación del portal cliente">
        <?php foreach ($portalLinks as $key => $link): ?>
            <a
                class="portal-sidebar__link <?= $portalActive === $key ? 'is-active' : '' ?>"
                href="<?= demo_e($link['url']) ?>"
            >
                <span class="portal-sidebar__link-icon"><?= demo_e($link['icon']) ?></span>
                <span><?= demo_e($link['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="portal-sidebar__footer">
        <div class="portal-sidebar__hint">
            Tus datos del portal son visibles solo para tu sesión actual. Usa los accesos rápidos para continuar.
        </div>
    </div>
</aside>