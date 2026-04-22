<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';

demo_require_roles(['cliente']);

$portalUser = demo_current_user();
$clientId = (string)($portalUser['client_id'] ?? '');

$clients = demo_store('clients', []);
$client = demo_find_by_id($clients, $clientId);

if (!$client) {
    demo_push_toast('No se encontró la ficha del cliente vinculada a este usuario.', 'error', 'Portal incompleto');
    demo_redirect('logout.php');
}

$policies = demo_store('policies', []);
$documents = demo_store('documents', []);

$portalPolicies = array_values(array_filter($policies, fn($policy) => (string)($policy['client_id'] ?? '') === $clientId));
$policyIds = array_column($portalPolicies, 'id');

$clientDocuments = array_values(array_filter($documents, function ($document) use ($clientId, $policyIds) {
    return (
        ((string)($document['entity_type'] ?? '') === 'client' && (string)($document['entity_id'] ?? '') === $clientId)
        || ((string)($document['entity_type'] ?? '') === 'policy' && in_array((string)($document['entity_id'] ?? ''), $policyIds, true))
        || ((string)($document['entity_type'] ?? '') === 'installment' && in_array((string)($document['entity_id'] ?? ''), array_column(demo_store('installments', []), 'id'), true))
    );
}));
usort($clientDocuments, fn($a, $b) => strtotime((string)($b['created_at'] ?? '')) <=> strtotime((string)($a['created_at'] ?? '')));
$clientDocuments = array_slice($clientDocuments, 0, 10);

$preferences = $client['contact_preferences'] ?? [
    'email' => true,
    'whatsapp' => true,
    'phone' => false,
];

$portalActive = 'perfil';

ob_start();
?>
<style>
    .portal-profile-grid {
        display: grid;
        gap: 1rem;
    }

    .portal-profile-hero {
        display: grid;
        gap: 1rem;
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: center;
        border: 1px solid rgba(219, 227, 239, .9);
        border-radius: 26px;
        padding: 1.15rem 1.2rem;
        background:
            radial-gradient(circle at top right, rgba(79, 70, 229, .14), transparent 30%),
            radial-gradient(circle at bottom left, rgba(14, 165, 164, .12), transparent 32%),
            linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        box-shadow: var(--shadow-sm);
    }

    .portal-profile-hero__main {
        display: flex;
        align-items: center;
        gap: 1rem;
        min-width: 0;
    }

    .portal-profile-hero__avatar {
        width: 68px;
        height: 68px;
        border-radius: 22px;
        display: grid;
        place-items: center;
        background: linear-gradient(135deg, rgba(79, 70, 229, .14), rgba(14, 165, 164, .14));
        border: 1px solid rgba(79, 70, 229, .12);
        color: var(--primary);
        font-weight: 800;
        font-size: 1.25rem;
    }

    .portal-profile-hero__text h2 {
        margin: .15rem 0 .25rem;
        font-size: clamp(1.45rem, 2.2vw, 2rem);
        line-height: 1.1;
    }

    .portal-profile-hero__text p {
        margin: 0;
        color: var(--text-soft);
        line-height: 1.55;
    }

    .portal-profile-hero__actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: .65rem;
    }

    .portal-profile-main {
        display: grid;
        gap: 1rem;
        grid-template-columns: minmax(0, 1.1fr) minmax(320px, .9fr);
    }

    .portal-profile-meta {
        display: grid;
        gap: .9rem;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .portal-profile-meta__item {
        padding: .95rem 1rem;
        border-radius: 18px;
        border: 1px solid rgba(219, 227, 239, .85);
        background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
    }

    .portal-profile-meta__item strong {
        display: block;
        margin-bottom: .35rem;
        color: var(--text-soft);
        font-size: .78rem;
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .portal-profile-meta__item span {
        display: block;
        line-height: 1.5;
        overflow-wrap: anywhere;
    }

    .portal-profile-preferences {
        display: grid;
        gap: .8rem;
    }

    .portal-profile-preference {
        padding: .95rem 1rem;
        border-radius: 18px;
        border: 1px solid rgba(219, 227, 239, .85);
        background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
    }

    .portal-profile-docs {
        display: grid;
        gap: .8rem;
    }

    .portal-profile-doc {
        padding: .95rem 1rem;
        border-radius: 18px;
        border: 1px solid rgba(219, 227, 239, .85);
        background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
    }

    .portal-profile-doc__top {
        display: flex;
        align-items: start;
        justify-content: space-between;
        gap: .8rem;
        margin-bottom: .35rem;
    }

    .portal-profile-doc__top h4 {
        margin: 0;
        font-size: .95rem;
    }

    .portal-profile-doc p,
    .portal-profile-doc small {
        margin: 0;
        color: var(--text-soft);
        line-height: 1.5;
    }

    .portal-profile-note {
        padding: .9rem 1rem;
        border-radius: 18px;
        border: 1px dashed rgba(100, 116, 139, .26);
        background: linear-gradient(180deg, #fbfdff 0%, #f7faff 100%);
        color: var(--text-soft);
        line-height: 1.55;
    }

    .portal-profile-form-note {
        margin: 0 0 1rem;
        color: var(--text-soft);
        line-height: 1.5;
    }

    .portal-profile-checks {
        display: grid;
        gap: .75rem;
    }

    .portal-profile-check {
        display: flex;
        align-items: center;
        gap: .6rem;
        min-height: 42px;
    }

    @media (max-width: 1100px) {
        .portal-profile-main {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 860px) {
        .portal-profile-hero,
        .portal-profile-meta {
            grid-template-columns: 1fr;
        }

        .portal-profile-hero__actions {
            justify-content: flex-start;
        }
    }

    @media (max-width: 620px) {
        .portal-profile-doc__top,
        .portal-profile-preference {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>

<div class="portal-shell">
    <?php require __DIR__ . '/_nav.php'; ?>

    <div class="portal-main">
        <div class="portal-profile-grid">
            <section class="portal-profile-hero">
                <div class="portal-profile-hero__main">
                    <div class="portal-profile-hero__avatar" id="profile-avatar"><?= demo_e(demo_avatar_initials($portalUser['full_name'] ?? 'Cliente')) ?></div>
                    <div class="portal-profile-hero__text">
                        <span class="badge badge-info">Mi perfil</span>
                        <h2 id="profile-name"><?= demo_e($portalUser['full_name'] ?? 'Cliente') ?></h2>
                        <p id="profile-document"><?= demo_e(($client['document_type'] ?? 'Doc') . ' ' . ($client['document_number'] ?? '—')) ?></p>
                    </div>
                </div>

                <div class="portal-profile-hero__actions">
                    <button type="button" class="btn btn-secondary" id="btn-edit-profile">Editar perfil</button>
                    <button type="button" class="btn btn-primary" id="btn-change-password">Cambiar contraseña</button>
                </div>
            </section>

            <section class="portal-profile-main">
                <div class="grid" style="gap: 1rem;">
                    <article class="card">
                        <div class="card__header">
                            <div>
                                <h3 class="card__title">Datos principales</h3>
                                <p class="card__subtitle">Información básica visible dentro de tu portal cliente.</p>
                            </div>
                        </div>

                        <div class="portal-profile-meta">
                            <div class="portal-profile-meta__item">
                                <strong>Nombre</strong>
                                <span id="meta-name"><?= demo_e($client['name'] ?? $portalUser['full_name'] ?? '—') ?></span>
                            </div>

                            <div class="portal-profile-meta__item">
                                <strong>Documento</strong>
                                <span id="meta-document"><?= demo_e(($client['document_type'] ?? 'Doc') . ' ' . ($client['document_number'] ?? '—')) ?></span>
                            </div>

                            <div class="portal-profile-meta__item">
                                <strong>Correo</strong>
                                <span id="meta-email"><?= demo_e($client['email'] ?? $portalUser['email'] ?? '—') ?></span>
                            </div>

                            <div class="portal-profile-meta__item">
                                <strong>Teléfono</strong>
                                <span id="meta-phone"><?= demo_e($client['phone'] ?? $portalUser['phone'] ?? '—') ?></span>
                            </div>

                            <div class="portal-profile-meta__item" style="grid-column: 1 / -1;">
                                <strong>Dirección</strong>
                                <span id="meta-address"><?= demo_e($client['address'] ?? 'No registrada') ?></span>
                            </div>
                        </div>
                    </article>

                    <article class="card">
                        <div class="card__header">
                            <div>
                                <h3 class="card__title">Documentos disponibles</h3>
                                <p class="card__subtitle">Archivos vinculados a tu perfil y a tus pólizas visibles en el portal.</p>
                            </div>
                        </div>

                        <div class="portal-profile-docs" id="documents-list">
                            <?php if (empty($clientDocuments)): ?>
                                <div class="empty-state">No hay documentos disponibles en este momento.</div>
                            <?php else: ?>
                                <?php foreach ($clientDocuments as $document): ?>
                                    <article class="portal-profile-doc">
                                        <div class="portal-profile-doc__top">
                                            <div>
                                                <h4><?= demo_e($document['original_name'] ?? 'Documento') ?></h4>
                                                <p><?= demo_e($document['type'] ?? 'Archivo') ?></p>
                                            </div>
                                            <small><?= demo_e(demo_date((string)($document['created_at'] ?? null), 'd/m/Y H:i')) ?></small>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </article>
                </div>

                <div class="grid" style="gap: 1rem;">
                    <article class="card">
                        <div class="card__header">
                            <div>
                                <h3 class="card__title">Preferencias de contacto</h3>
                                <p class="card__subtitle">Cómo prefieres recibir recordatorios y comunicaciones.</p>
                            </div>
                        </div>

                        <div class="portal-profile-preferences" id="preferences-list">
                            <div class="portal-profile-preference">
                                <div>
                                    <strong style="display:block; margin-bottom:.2rem;">Correo electrónico</strong>
                                    <span class="muted">Actualizaciones y avisos del portal</span>
                                </div>
                                <span class="badge badge-<?= !empty($preferences['email']) ? 'success' : 'neutral' ?>" id="pref-email"><?= !empty($preferences['email']) ? 'Activo' : 'Inactivo' ?></span>
                            </div>

                            <div class="portal-profile-preference">
                                <div>
                                    <strong style="display:block; margin-bottom:.2rem;">WhatsApp</strong>
                                    <span class="muted">Recordatorios rápidos y confirmaciones</span>
                                </div>
                                <span class="badge badge-<?= !empty($preferences['whatsapp']) ? 'success' : 'neutral' ?>" id="pref-whatsapp"><?= !empty($preferences['whatsapp']) ? 'Activo' : 'Inactivo' ?></span>
                            </div>

                            <div class="portal-profile-preference">
                                <div>
                                    <strong style="display:block; margin-bottom:.2rem;">Llamadas</strong>
                                    <span class="muted">Contacto directo cuando sea necesario</span>
                                </div>
                                <span class="badge badge-<?= !empty($preferences['phone']) ? 'success' : 'neutral' ?>" id="pref-phone"><?= !empty($preferences['phone']) ? 'Activo' : 'Inactivo' ?></span>
                            </div>
                        </div>
                    </article>

                    <article class="card">
                        <div class="card__header">
                            <div>
                                <h3 class="card__title">Privacidad y acceso</h3>
                                <p class="card__subtitle">Control básico de tu acceso dentro del entorno demo.</p>
                            </div>
                        </div>

                        <div class="portal-profile-note">
                            Puedes actualizar tus datos de contacto y cambiar tu clave demo cuando lo necesites. Estos cambios se guardan en la sesión actual del sistema.
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </div>
</div>

<div class="modal" id="profile-edit-modal" hidden>
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__dialog">
        <div class="modal__header">
            <h3>Editar perfil</h3>
            <button type="button" class="icon-btn" data-modal-close aria-label="Cerrar">✕</button>
        </div>
        <div class="modal__body">
            <p class="portal-profile-form-note">Actualiza tu información principal y tus preferencias de contacto.</p>

            <form id="profile-edit-form" class="form-grid form-grid--2">
                <input type="hidden" name="action" value="update_profile">

                <div>
                    <label class="form-label" for="edit-name">Nombre</label>
                    <input class="input" type="text" id="edit-name" name="name" value="<?= demo_e($client['name'] ?? $portalUser['full_name'] ?? '') ?>">
                </div>

                <div>
                    <label class="form-label" for="edit-email">Correo</label>
                    <input class="input" type="email" id="edit-email" name="email" value="<?= demo_e($client['email'] ?? $portalUser['email'] ?? '') ?>">
                </div>

                <div>
                    <label class="form-label" for="edit-phone">Teléfono</label>
                    <input class="input" type="text" id="edit-phone" name="phone" value="<?= demo_e($client['phone'] ?? $portalUser['phone'] ?? '') ?>">
                </div>

                <div style="grid-column: 1 / -1;">
                    <label class="form-label" for="edit-address">Dirección</label>
                    <input class="input" type="text" id="edit-address" name="address" value="<?= demo_e($client['address'] ?? '') ?>">
                </div>

                <div style="grid-column: 1 / -1;">
                    <label class="form-label">Preferencias de contacto</label>
                    <div class="portal-profile-checks">
                        <label class="portal-profile-check">
                            <input type="checkbox" name="pref_email" value="1" <?= !empty($preferences['email']) ? 'checked' : '' ?>>
                            <span>Recibir avisos por correo electrónico</span>
                        </label>

                        <label class="portal-profile-check">
                            <input type="checkbox" name="pref_whatsapp" value="1" <?= !empty($preferences['whatsapp']) ? 'checked' : '' ?>>
                            <span>Recibir recordatorios por WhatsApp</span>
                        </label>

                        <label class="portal-profile-check">
                            <input type="checkbox" name="pref_phone" value="1" <?= !empty($preferences['phone']) ? 'checked' : '' ?>>
                            <span>Permitir contacto por llamada</span>
                        </label>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal__footer">
            <button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button>
            <button type="button" class="btn btn-primary" id="profile-edit-submit">Guardar cambios</button>
        </div>
    </div>
</div>

<div class="modal" id="password-modal" hidden>
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__dialog modal__dialog--sm">
        <div class="modal__header">
            <h3>Cambiar contraseña</h3>
            <button type="button" class="icon-btn" data-modal-close aria-label="Cerrar">✕</button>
        </div>
        <div class="modal__body">
            <p class="portal-profile-form-note">Cambia tu clave demo actual. Se validará la contraseña vigente antes de guardar.</p>

            <form id="password-form" class="form-grid">
                <input type="hidden" name="action" value="change_password">

                <div>
                    <label class="form-label" for="current-password">Contraseña actual</label>
                    <input class="input" type="password" id="current-password" name="current_password" placeholder="Ingresa tu clave actual">
                </div>

                <div>
                    <label class="form-label" for="new-password">Nueva contraseña</label>
                    <input class="input" type="password" id="new-password" name="new_password" placeholder="Ingresa la nueva clave">
                </div>

                <div>
                    <label class="form-label" for="confirm-password">Confirmar nueva contraseña</label>
                    <input class="input" type="password" id="confirm-password" name="confirm_password" placeholder="Repite la nueva clave">
                </div>
            </form>
        </div>
        <div class="modal__footer">
            <button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button>
            <button type="button" class="btn btn-primary" id="password-submit">Actualizar contraseña</button>
        </div>
    </div>
</div>

<script>
    (() => {
        let clientState = <?= json_encode($client, JSON_UNESCAPED_UNICODE) ?>;
        let userState = <?= json_encode($portalUser, JSON_UNESCAPED_UNICODE) ?>;
        const endpoint = <?= json_encode(demo_url('ajax/portal-cliente.php'), JSON_UNESCAPED_UNICODE) ?>;

        const profileName = document.getElementById('profile-name');
        const profileDocument = document.getElementById('profile-document');
        const profileAvatar = document.getElementById('profile-avatar');

        const metaName = document.getElementById('meta-name');
        const metaDocument = document.getElementById('meta-document');
        const metaEmail = document.getElementById('meta-email');
        const metaPhone = document.getElementById('meta-phone');
        const metaAddress = document.getElementById('meta-address');

        const prefEmail = document.getElementById('pref-email');
        const prefWhatsapp = document.getElementById('pref-whatsapp');
        const prefPhone = document.getElementById('pref-phone');

        const sidebarName = document.querySelector('.portal-sidebar__name');
        const sidebarMeta = document.querySelector('.portal-sidebar__meta');

        const escapeHtml = (value) => String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');

        const initials = (text) => {
            const value = String(text || '').trim();
            if (!value) return 'CL';
            const parts = value.split(/\s+/).slice(0, 2);
            return parts.map((part) => part.charAt(0).toUpperCase()).join('');
        };

        const setPreferenceBadge = (node, active) => {
            node.className = `badge badge-${active ? 'success' : 'neutral'}`;
            node.textContent = active ? 'Activo' : 'Inactivo';
        };

        const renderProfile = () => {
            profileName.textContent = clientState.name || userState.full_name || 'Cliente';
            profileDocument.textContent = `${clientState.document_type || 'Doc'} ${clientState.document_number || '—'}`;
            profileAvatar.textContent = initials(clientState.name || userState.full_name || 'Cliente');

            metaName.textContent = clientState.name || userState.full_name || '—';
            metaDocument.textContent = `${clientState.document_type || 'Doc'} ${clientState.document_number || '—'}`;
            metaEmail.textContent = clientState.email || userState.email || '—';
            metaPhone.textContent = clientState.phone || userState.phone || '—';
            metaAddress.textContent = clientState.address || 'No registrada';

            const prefs = clientState.contact_preferences || { email: true, whatsapp: true, phone: false };
            setPreferenceBadge(prefEmail, !!prefs.email);
            setPreferenceBadge(prefWhatsapp, !!prefs.whatsapp);
            setPreferenceBadge(prefPhone, !!prefs.phone);

            if (sidebarName) {
                sidebarName.textContent = clientState.name || userState.full_name || 'Cliente';
            }

            if (sidebarMeta) {
                sidebarMeta.textContent = `${clientState.document_type || 'Doc'} ${clientState.document_number || userState.document || '—'}`;
            }
        };

        document.getElementById('btn-edit-profile').addEventListener('click', () => {
            document.getElementById('edit-name').value = clientState.name || userState.full_name || '';
            document.getElementById('edit-email').value = clientState.email || userState.email || '';
            document.getElementById('edit-phone').value = clientState.phone || userState.phone || '';
            document.getElementById('edit-address').value = clientState.address || '';

            const prefs = clientState.contact_preferences || { email: true, whatsapp: true, phone: false };
            document.querySelector('input[name="pref_email"]').checked = !!prefs.email;
            document.querySelector('input[name="pref_whatsapp"]').checked = !!prefs.whatsapp;
            document.querySelector('input[name="pref_phone"]').checked = !!prefs.phone;

            DemoApp.openModal('profile-edit-modal');
        });

        document.getElementById('btn-change-password').addEventListener('click', () => {
            document.getElementById('password-form').reset();
            DemoApp.openModal('password-modal');
        });

        document.getElementById('profile-edit-submit').addEventListener('click', async () => {
            const formData = new FormData(document.getElementById('profile-edit-form'));

            const response = await DemoApp.api(endpoint, {
                method: 'POST',
                body: formData
            });

            if (!response.success) {
                DemoApp.toast({
                    title: response.title || 'No se pudo actualizar',
                    message: response.message || 'Verifica la información ingresada.',
                    type: 'error'
                });
                return;
            }

            if (response.client) {
                clientState = response.client;
            }

            if (response.user) {
                userState = response.user;
            }

            renderProfile();
            DemoApp.closeModal('profile-edit-modal');
            DemoApp.toast({
                title: response.title || 'Perfil actualizado',
                message: response.message || 'Tus datos fueron actualizados correctamente.',
                type: 'success'
            });
        });

        document.getElementById('password-submit').addEventListener('click', async () => {
            const formData = new FormData(document.getElementById('password-form'));

            const response = await DemoApp.api(endpoint, {
                method: 'POST',
                body: formData
            });

            if (!response.success) {
                DemoApp.toast({
                    title: response.title || 'No se pudo cambiar la contraseña',
                    message: response.message || 'Verifica los datos ingresados.',
                    type: 'error'
                });
                return;
            }

            DemoApp.closeModal('password-modal');
            DemoApp.toast({
                title: response.title || 'Contraseña actualizada',
                message: response.message || 'Tu clave demo fue actualizada correctamente.',
                type: 'success'
            });

            document.getElementById('password-form').reset();
        });

        renderProfile();
    })();
</script>
<?php
$content = ob_get_clean();

demo_render_internal_layout(
    'Mi perfil',
    $content,
    [
        'breadcrumb' => ['Portal', 'Mi perfil'],
        'subtitle' => 'Gestión básica de datos, preferencias y acceso del cliente en el portal.',
    ]
);