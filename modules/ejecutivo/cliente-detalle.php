<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';

demo_require_roles(['ejecutivo']);

$currentUser = demo_current_user();
$executiveId = $currentUser['id'] ?? '';
$clientId = trim((string)($_GET['id'] ?? ''));

$clients = demo_store('clients', []);
$client = demo_find_by_id($clients, $clientId);

if (!$client || ($client['assigned_executive_user_id'] ?? '') !== $executiveId) {
    demo_push_toast('No puedes ver clientes ajenos a tu cartera.', 'error', 'Acceso denegado');
    demo_redirect('modules/ejecutivo/clientes.php');
}

$policies = demo_filter_policies_by_executive(demo_store('policies', []), $executiveId);
$documents = demo_store('documents', []);
$installments = demo_store('installments', []);
$users = demo_store('users', []);
$clientNotesStore = demo_store('client_notes', []);

$clientPolicies = array_values(array_filter($policies, fn($p) => ($p['client_id'] ?? '') === $clientId));
$policyIds = array_column($clientPolicies, 'id');

$activePolicies = array_values(array_filter($clientPolicies, fn($p) => ($p['status'] ?? '') === 'activa'));
$clientDocuments = array_values(array_filter($documents, function ($doc) use ($clientId, $policyIds) {
    return (
        (($doc['entity_type'] ?? '') === 'client' && ($doc['entity_id'] ?? '') === $clientId) ||
        (($doc['entity_type'] ?? '') === 'policy' && in_array(($doc['entity_id'] ?? ''), $policyIds, true))
    );
}));
usort($clientDocuments, fn($a, $b) => strtotime((string)($b['created_at'] ?? '')) <=> strtotime((string)($a['created_at'] ?? '')));

$clientInstallments = array_values(array_filter($installments, fn($i) => in_array(($i['policy_id'] ?? ''), $policyIds, true)));
$pendingInstallments = array_values(array_filter($clientInstallments, fn($i) => in_array(strtolower((string)($i['status'] ?? '')), ['pendiente', 'vencida', 'en revisión'], true)));

$notes = array_values(array_filter($clientNotesStore, fn($n) => ($n['client_id'] ?? '') === $clientId));
usort($notes, fn($a, $b) => strtotime((string)($b['created_at'] ?? '')) <=> strtotime((string)($a['created_at'] ?? '')));

if (empty($notes) && !empty($client['notes'])) {
    $notes[] = [
        'id' => 'seed-note-' . $clientId,
        'client_id' => $clientId,
        'note' => $client['notes'],
        'author_name' => 'Sistema demo',
        'created_at' => $client['created_at'] ?? date('Y-m-d H:i:s'),
    ];
}

$executive = demo_find_by_id($users, $executiveId);

$portalUser = null;
foreach ($users as $user) {
    if (($user['role'] ?? '') === 'cliente' && ($user['client_id'] ?? null) === $clientId) {
        $portalUser = $user;
        break;
    }
}

ob_start();
?>
<style>
    .exec-client-detail-grid {
        display: grid;
        gap: 1rem;
    }

    .exec-client-hero {
        display: grid;
        gap: 1rem;
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: center;
    }

    .exec-client-hero__main {
        display: flex;
        align-items: center;
        gap: 1rem;
        min-width: 0;
    }

    .exec-client-hero__meta h2 {
        margin: .2rem 0 .25rem;
        font-size: clamp(1.45rem, 2vw, 2rem);
        line-height: 1.1;
    }

    .exec-client-hero__meta p {
        margin: 0;
        color: var(--text-soft);
        line-height: 1.5;
    }

    .exec-client-hero__actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: .65rem;
    }

    .exec-client-summary {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .exec-client-meta-grid {
        display: grid;
        gap: .9rem;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .exec-client-meta-item {
        padding: .95rem 1rem;
        border-radius: 18px;
        border: 1px solid rgba(219, 227, 239, .85);
        background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
    }

    .exec-client-meta-item strong {
        display: block;
        margin-bottom: .35rem;
        color: var(--text-soft);
        font-size: .8rem;
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .exec-client-meta-item span {
        display: block;
        line-height: 1.5;
        overflow-wrap: anywhere;
    }

    .exec-client-tab-panels > div[hidden] {
        display: none !important;
    }

    .exec-client-list {
        display: grid;
        gap: .8rem;
    }

    .exec-client-item {
        padding: .95rem 1rem;
        border-radius: 18px;
        border: 1px solid rgba(219, 227, 239, .85);
        background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
    }

    .exec-client-item__top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: .75rem;
        margin-bottom: .35rem;
    }

    .exec-client-item h4 {
        margin: 0;
        font-size: .96rem;
    }

    .exec-client-item p,
    .exec-client-item small {
        margin: 0;
        color: var(--text-soft);
        line-height: 1.55;
    }

    .exec-client-inline-note {
        padding: .85rem 1rem;
        border-radius: 16px;
        border: 1px dashed rgba(100, 116, 139, .28);
        background: linear-gradient(180deg, #fbfdff 0%, #f7faff 100%);
    }

    .exec-client-note-helper {
        margin: 0 0 1rem;
        color: var(--text-soft);
        line-height: 1.5;
    }

    @media (max-width: 1100px) {
        .exec-client-summary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 920px) {
        .exec-client-hero,
        .exec-client-meta-grid {
            grid-template-columns: 1fr;
        }

        .exec-client-hero__actions {
            justify-content: flex-start;
        }
    }

    @media (max-width: 680px) {
        .exec-client-summary {
            grid-template-columns: 1fr;
        }

        .exec-client-item__top {
            flex-direction: column;
        }
    }
</style>

<div class="exec-client-detail-grid">
    <section class="card">
        <div class="exec-client-hero">
            <div class="exec-client-hero__main">
                <div class="avatar avatar--lg"><?= demo_e(demo_avatar_initials($client['name'] ?? '')) ?></div>
                <div class="exec-client-hero__meta">
                    <span class="badge badge-<?= demo_e(($client['status'] ?? '') === 'activo' ? 'success' : 'danger') ?>">
                        <?= demo_e(ucfirst((string)($client['status'] ?? '—'))) ?>
                    </span>
                    <h2><?= demo_e($client['name'] ?? 'Cliente') ?></h2>
                    <p><?= demo_e(($client['document_type'] ?? 'Doc') . ' ' . ($client['document_number'] ?? '—')) ?> · <?= demo_e(ucfirst((string)($client['type'] ?? 'persona'))) ?></p>
                </div>
            </div>

            <div class="exec-client-hero__actions">
                <button type="button" class="btn btn-ghost" id="btn-new-policy">Nueva póliza</button>
                <button type="button" class="btn btn-secondary" id="btn-send-message">Enviar mensaje</button>
                <button type="button" class="btn btn-primary" id="btn-create-portal" <?= !empty($client['has_portal_access']) ? 'disabled' : '' ?>>
                    <?= !empty($client['has_portal_access']) ? 'Portal activo' : 'Crear acceso portal' ?>
                </button>
            </div>
        </div>
    </section>

    <section class="exec-client-summary">
        <article class="card kpi-card">
            <p class="kpi-card__label">Pólizas activas</p>
            <h3 class="kpi-card__value"><?= demo_e((string)count($activePolicies)) ?></h3>
            <p class="kpi-card__meta">Coberturas vigentes dentro de tu cartera.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Cuotas pendientes</p>
            <h3 class="kpi-card__value"><?= demo_e((string)count($pendingInstallments)) ?></h3>
            <p class="kpi-card__meta">Pendientes, vencidas y en revisión del cliente.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Documentos</p>
            <h3 class="kpi-card__value"><?= demo_e((string)count($clientDocuments)) ?></h3>
            <p class="kpi-card__meta">Archivos de cliente y pólizas relacionadas.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Notas</p>
            <h3 class="kpi-card__value" id="notes-count"><?= demo_e((string)count($notes)) ?></h3>
            <p class="kpi-card__meta">Observaciones internas de tu seguimiento comercial.</p>
        </article>
    </section>

    <section class="card">
        <div class="tab-nav" data-tab-group>
            <button type="button" class="tab-btn is-active" data-tab-button="resumen">Resumen</button>
            <button type="button" class="tab-btn" data-tab-button="polizas">Pólizas</button>
            <button type="button" class="tab-btn" data-tab-button="documentos">Documentos</button>
            <button type="button" class="tab-btn" data-tab-button="notas">Notas</button>
        </div>

        <div class="exec-client-tab-panels" data-tab-group>
            <div data-tab-panel="resumen">
                <div class="exec-client-meta-grid">
                    <div class="exec-client-meta-item">
                        <strong>Correo</strong>
                        <span><?= demo_e($client['email'] ?? '—') ?></span>
                    </div>

                    <div class="exec-client-meta-item">
                        <strong>Teléfono</strong>
                        <span><?= demo_e($client['phone'] ?? '—') ?></span>
                    </div>

                    <div class="exec-client-meta-item">
                        <strong>Dirección</strong>
                        <span><?= demo_e($client['address'] ?? '—') ?></span>
                    </div>

                    <div class="exec-client-meta-item">
                        <strong>Ejecutivo responsable</strong>
                        <span><?= demo_e($executive['full_name'] ?? 'Ejecutivo') ?></span>
                    </div>

                    <div class="exec-client-meta-item">
                        <strong>Acceso portal</strong>
                        <span id="portal-status"><?= !empty($client['has_portal_access']) ? 'Activo' : 'No creado' ?></span>
                    </div>

                    <div class="exec-client-meta-item">
                        <strong>Usuario portal</strong>
                        <span id="portal-username"><?= demo_e($portalUser['username'] ?? 'Sin acceso generado') ?></span>
                    </div>
                </div>

                <div class="exec-client-inline-note mt-2">
                    <strong>Resumen comercial</strong>
                    <p class="muted mt-1" id="summary-note"><?= demo_e($client['notes'] ?? 'Sin observaciones registradas por ahora.') ?></p>
                </div>
            </div>

            <div data-tab-panel="polizas" hidden>
                <div class="exec-client-list">
                    <?php if (empty($clientPolicies)): ?>
                        <div class="empty-state">Este cliente aún no tiene pólizas registradas dentro de tu cartera.</div>
                    <?php else: ?>
                        <?php foreach ($clientPolicies as $policy): ?>
                            <article class="exec-client-item">
                                <div class="exec-client-item__top">
                                    <div>
                                        <h4><?= demo_e($policy['policy_number'] ?? 'Póliza') ?></h4>
                                        <p><?= demo_e(demo_insurance_type_name((string)($policy['insurance_type_id'] ?? ''))) ?> · <?= demo_e(demo_insurer_name((string)($policy['insurer_id'] ?? ''))) ?></p>
                                    </div>
                                    <small><?= demo_badge((string)($policy['status'] ?? '—'), (string)($policy['status'] ?? '—')) ?></small>
                                </div>
                                <p>Vigencia: <?= demo_e(demo_date((string)($policy['start_date'] ?? null))) ?> al <?= demo_e(demo_date((string)($policy['end_date'] ?? null))) ?> · Prima: <?= demo_e(demo_money((float)($policy['premium'] ?? 0), (string)($policy['currency'] ?? 'S/'))) ?></p>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div data-tab-panel="documentos" hidden>
                <div class="exec-client-list">
                    <?php if (empty($clientDocuments)): ?>
                        <div class="empty-state">No hay documentos asociados a este cliente dentro de tu cartera.</div>
                    <?php else: ?>
                        <?php foreach ($clientDocuments as $document): ?>
                            <article class="exec-client-item">
                                <div class="exec-client-item__top">
                                    <div>
                                        <h4><?= demo_e($document['original_name'] ?? 'Documento') ?></h4>
                                        <p><?= demo_e($document['type'] ?? 'Archivo') ?></p>
                                    </div>
                                    <small><?= demo_e(demo_date((string)($document['created_at'] ?? null), 'd/m/Y H:i')) ?></small>
                                </div>
                                <p>Subido por <?= demo_e(demo_find_user_by_id((string)($document['uploaded_by'] ?? ''))['full_name'] ?? 'Sistema') ?></p>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div data-tab-panel="notas" hidden>
                <div class="card__header" style="padding: 0 0 1rem;">
                    <div>
                        <h3 class="card__title">Notas rápidas</h3>
                        <p class="card__subtitle">Seguimiento comercial y observaciones internas del cliente.</p>
                    </div>

                    <button type="button" class="btn btn-primary" id="btn-new-note">Nota rápida</button>
                </div>

                <div class="exec-client-list" id="notes-list">
                    <?php if (empty($notes)): ?>
                        <div class="empty-state">No hay notas registradas todavía.</div>
                    <?php else: ?>
                        <?php foreach ($notes as $note): ?>
                            <article class="exec-client-item">
                                <div class="exec-client-item__top">
                                    <div>
                                        <h4><?= demo_e($note['author_name'] ?? 'Sistema') ?></h4>
                                        <p><?= demo_e($note['note'] ?? '') ?></p>
                                    </div>
                                    <small><?= demo_e(demo_date((string)($note['created_at'] ?? null), 'd/m/Y H:i')) ?></small>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</div>

<div class="modal" id="client-note-modal" hidden>
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__dialog modal__dialog--sm">
        <div class="modal__header">
            <h3>Nota rápida</h3>
            <button type="button" class="icon-btn" data-modal-close aria-label="Cerrar">✕</button>
        </div>
        <div class="modal__body">
            <p class="exec-client-note-helper">Agrega una observación interna vinculada a este cliente. Quedará disponible en la sesión actual.</p>

            <form id="client-note-form">
                <input type="hidden" name="action" value="add_note">
                <input type="hidden" name="client_id" value="<?= demo_e($clientId) ?>">

                <div>
                    <label class="form-label" for="note-text">Contenido de la nota</label>
                    <textarea class="textarea" id="note-text" name="note" placeholder="Describe el seguimiento comercial realizado"></textarea>
                </div>
            </form>
        </div>
        <div class="modal__footer">
            <button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button>
            <button type="button" class="btn btn-primary" id="note-submit">Guardar nota</button>
        </div>
    </div>
</div>

<script>
    (() => {
        const clientId = <?= json_encode($clientId, JSON_UNESCAPED_UNICODE) ?>;
        const endpoint = <?= json_encode(demo_url('ajax/ejecutivo-clientes.php'), JSON_UNESCAPED_UNICODE) ?>;

        const notesList = document.getElementById('notes-list');
        const notesCount = document.getElementById('notes-count');
        const summaryNote = document.getElementById('summary-note');

        const portalButton = document.getElementById('btn-create-portal');
        const portalStatus = document.getElementById('portal-status');
        const portalUsername = document.getElementById('portal-username');

        const noteForm = document.getElementById('client-note-form');
        const noteSubmit = document.getElementById('note-submit');

        const escapeHtml = (value) => String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');

        const nowLabel = () => {
            const d = new Date();
            return d.toLocaleDateString('es-PE') + ' ' + d.toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' });
        };

        const appendNote = (note) => {
            const empty = notesList.querySelector('.empty-state');
            if (empty) empty.remove();

            const article = document.createElement('article');
            article.className = 'exec-client-item';
            article.innerHTML = `
                <div class="exec-client-item__top">
                    <div>
                        <h4>${escapeHtml(note.author_name || 'Sistema')}</h4>
                        <p>${escapeHtml(note.note || '')}</p>
                    </div>
                    <small>${escapeHtml(note.created_at_label || nowLabel())}</small>
                </div>
            `;
            notesList.prepend(article);
        };

        const showPortalCredentials = (payload) => {
            const title = document.getElementById('generic-modal-title');
            const body = document.getElementById('generic-modal-body');

            title.textContent = 'Acceso portal creado';
            body.innerHTML = `
                <div class="grid" style="gap: .9rem;">
                    <div class="panel">
                        <strong>Cliente</strong>
                        <p class="mt-1">${escapeHtml(payload.client?.name || '—')}</p>
                    </div>
                    <div class="panel">
                        <strong>Usuario</strong>
                        <p class="mt-1"><code>${escapeHtml(payload.credentials?.username || '—')}</code></p>
                    </div>
                    <div class="panel">
                        <strong>Clave inicial</strong>
                        <p class="mt-1"><code>${escapeHtml(payload.credentials?.password || '—')}</code></p>
                    </div>
                    <div class="panel">
                        <strong>Detalle</strong>
                        <p class="mt-1">Acceso generado solo para fines demo dentro de la sesión actual.</p>
                    </div>
                </div>
            `;
            DemoApp.openModal('generic-modal');
        };

        document.getElementById('btn-new-policy').addEventListener('click', () => {
            DemoApp.toast({
                title: 'Nueva póliza',
                message: 'Acción demo lista para conectarse al módulo de pólizas del ejecutivo.',
                type: 'info'
            });
        });

        document.getElementById('btn-send-message').addEventListener('click', () => {
            DemoApp.toast({
                title: 'Enviar mensaje',
                message: 'Acción demo lista para integrarse con comunicaciones o WhatsApp.',
                type: 'info'
            });
        });

        document.getElementById('btn-new-note').addEventListener('click', () => {
            noteForm.reset();
            DemoApp.openModal('client-note-modal');
        });

        portalButton.addEventListener('click', () => {
            DemoApp.confirm({
                title: 'Crear acceso portal',
                message: '¿Deseas crear el acceso portal para este cliente?',
                onAccept: async () => {
                    const formData = new FormData();
                    formData.append('action', 'create_portal_access');
                    formData.append('client_id', clientId);

                    const response = await DemoApp.api(endpoint, {
                        method: 'POST',
                        body: formData
                    });

                    if (!response.success) {
                        DemoApp.toast({
                            title: response.title || 'No se pudo crear el acceso',
                            message: response.message || 'Verifica la información del cliente.',
                            type: 'error'
                        });
                        return;
                    }

                    portalButton.disabled = true;
                    portalButton.textContent = 'Portal activo';
                    portalStatus.textContent = 'Activo';
                    portalUsername.textContent = response.credentials?.username || '—';

                    DemoApp.toast({
                        title: response.title || 'Acceso creado',
                        message: response.message || 'El acceso portal fue generado correctamente.',
                        type: 'success'
                    });

                    showPortalCredentials(response);
                }
            });
        });

        noteSubmit.addEventListener('click', async () => {
            const formData = new FormData(noteForm);

            const response = await DemoApp.api(endpoint, {
                method: 'POST',
                body: formData
            });

            if (!response.success) {
                DemoApp.toast({
                    title: response.title || 'No se pudo guardar la nota',
                    message: response.message || 'Verifica el contenido ingresado.',
                    type: 'error'
                });
                return;
            }

            DemoApp.closeModal('client-note-modal');
            DemoApp.toast({
                title: response.title || 'Nota guardada',
                message: response.message || 'La nota fue registrada correctamente.',
                type: 'success'
            });

            if (response.note) {
                appendNote(response.note);
                notesCount.textContent = String(Number(notesCount.textContent || '0') + 1);
                summaryNote.textContent = response.note.note || summaryNote.textContent;
            }

            noteForm.reset();
        });
    })();
</script>
<?php
$content = ob_get_clean();

demo_render_internal_layout(
    'Detalle de cliente',
    $content,
    [
        'breadcrumb' => ['Inicio', 'Clientes', $client['name'] ?? 'Detalle'],
        'subtitle' => 'Ficha comercial del cliente dentro de tu cartera ejecutiva.',
    ]
);