<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';

demo_require_roles(['gerente', 'superadmin']);

$clientId = trim((string)($_GET['id'] ?? ''));
$clients = demo_store('clients', []);
$policies = demo_store('policies', []);
$installments = demo_store('installments', []);
$claims = demo_store('claims', []);
$documents = demo_store('documents', []);
$users = demo_store('users', []);
$clientNotesStore = demo_store('client_notes', []);
$clientActivityStore = demo_store('client_activity', []);

$client = demo_find_by_id($clients, $clientId);

if (!$client) {
    ob_start();
    ?>
    <div class="empty-state">
        No se encontró el cliente solicitado en el store demo actual.
        <div class="mt-2">
            <a href="<?= demo_e(demo_url('modules/clientes/index.php')) ?>" class="btn btn-primary">Volver al listado</a>
        </div>
    </div>
    <?php
    $content = ob_get_clean();

    demo_render_internal_layout(
        'Ficha de cliente',
        $content,
        [
            'breadcrumb' => ['Inicio', 'Clientes', 'Detalle'],
            'subtitle' => 'El registro solicitado no existe en esta sesión.',
        ]
    );
    return;
}

$executive = !empty($client['assigned_executive_user_id']) ? demo_find_by_id($users, $client['assigned_executive_user_id']) : null;
$clientPolicies = array_values(array_filter($policies, fn($p) => ($p['client_id'] ?? '') === $clientId));
$clientPolicyIds = array_column($clientPolicies, 'id');

$activePolicies = array_values(array_filter($clientPolicies, fn($p) => ($p['status'] ?? '') === 'activa'));
$clientInstallments = array_values(array_filter($installments, fn($i) => in_array(($i['policy_id'] ?? ''), $clientPolicyIds, true)));
$pendingInstallments = array_values(array_filter($clientInstallments, fn($i) => in_array(strtolower((string)($i['status'] ?? '')), ['pendiente', 'vencida', 'en revisión'], true)));
$clientClaims = array_values(array_filter($claims, fn($c) => ($c['client_id'] ?? '') === $clientId));
$openClaims = array_values(array_filter($clientClaims, fn($c) => ($c['status'] ?? '') !== 'cerrado'));

$clientDocuments = array_values(array_filter($documents, function ($doc) use ($clientId, $clientPolicyIds, $clientClaims) {
    $claimIds = array_column($clientClaims, 'id');

    return (
        (($doc['entity_type'] ?? '') === 'client' && ($doc['entity_id'] ?? '') === $clientId) ||
        (($doc['entity_type'] ?? '') === 'policy' && in_array(($doc['entity_id'] ?? ''), $clientPolicyIds, true)) ||
        (($doc['entity_type'] ?? '') === 'claim' && in_array(($doc['entity_id'] ?? ''), $claimIds, true))
    );
}));

$clientNotes = array_values(array_filter($clientNotesStore, fn($n) => ($n['client_id'] ?? '') === $clientId));
usort($clientNotes, fn($a, $b) => strtotime($b['created_at'] ?? '') <=> strtotime($a['created_at'] ?? ''));

if (empty($clientNotes) && !empty($client['notes'])) {
    $clientNotes[] = [
        'id' => 'seed-note-' . $clientId,
        'client_id' => $clientId,
        'note' => $client['notes'],
        'author_name' => 'Sistema demo',
        'created_at' => $client['created_at'] ?? date('Y-m-d H:i:s'),
    ];
}

$clientActivity = array_values(array_filter($clientActivityStore, fn($a) => ($a['client_id'] ?? '') === $clientId));

foreach ($activePolicies as $policy) {
    $clientActivity[] = [
        'id' => 'pol-' . ($policy['id'] ?? ''),
        'client_id' => $clientId,
        'title' => 'Póliza activa',
        'description' => 'La póliza ' . ($policy['policy_number'] ?? '—') . ' permanece vigente con ' . demo_insurer_name($policy['insurer_id'] ?? '') . '.',
        'created_at' => $policy['created_at'] ?? date('Y-m-d H:i:s'),
    ];
}
foreach ($clientClaims as $claim) {
    $clientActivity[] = [
        'id' => 'claim-' . ($claim['id'] ?? ''),
        'client_id' => $clientId,
        'title' => 'Movimiento de siniestro',
        'description' => 'Caso ' . ($claim['code'] ?? '—') . ' en estado ' . ($claim['status'] ?? '—') . '.',
        'created_at' => ($claim['date'] ?? date('Y-m-d')) . ' 10:00:00',
    ];
}
foreach ($clientDocuments as $document) {
    $clientActivity[] = [
        'id' => 'doc-' . ($document['id'] ?? ''),
        'client_id' => $clientId,
        'title' => 'Documento cargado',
        'description' => ($document['original_name'] ?? 'Documento') . ' fue agregado al expediente del cliente.',
        'created_at' => $document['created_at'] ?? date('Y-m-d H:i:s'),
    ];
}

usort($clientActivity, fn($a, $b) => strtotime($b['created_at'] ?? '') <=> strtotime($a['created_at'] ?? ''));
$clientActivity = array_slice($clientActivity, 0, 12);

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
    .client-detail-grid {
        display: grid;
        gap: 1rem;
    }

    .client-hero {
        display: grid;
        gap: 1rem;
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: center;
    }

    .client-hero__main {
        display: flex;
        align-items: center;
        gap: 1rem;
        min-width: 0;
    }

    .client-hero__meta {
        min-width: 0;
    }

    .client-hero__meta h2 {
        margin: .2rem 0 .25rem;
        font-size: clamp(1.45rem, 2vw, 2rem);
        line-height: 1.1;
    }

    .client-hero__meta p {
        margin: 0;
        color: var(--text-soft);
        line-height: 1.5;
    }

    .client-hero__actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: .65rem;
    }

    .client-summary {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .client-meta-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .9rem;
    }

    .client-meta-item {
        padding: .95rem 1rem;
        border-radius: 18px;
        border: 1px solid rgba(219, 227, 239, .85);
        background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
    }

    .client-meta-item strong {
        display: block;
        margin-bottom: .35rem;
        color: var(--text-soft);
        font-size: .8rem;
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .client-meta-item span {
        display: block;
        line-height: 1.5;
        overflow-wrap: anywhere;
    }

    .client-tab-panels > div[hidden] {
        display: none !important;
    }

    .client-note-list,
    .client-document-list,
    .client-policy-list {
        display: grid;
        gap: .8rem;
    }

    .client-note-item,
    .client-document-item,
    .client-policy-item {
        padding: .95rem 1rem;
        border-radius: 18px;
        border: 1px solid rgba(219, 227, 239, .85);
        background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
    }

    .client-note-item__top,
    .client-document-item__top,
    .client-policy-item__top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: .75rem;
        margin-bottom: .35rem;
    }

    .client-note-item h4,
    .client-document-item h4,
    .client-policy-item h4 {
        margin: 0;
        font-size: .96rem;
    }

    .client-note-item small,
    .client-document-item small,
    .client-policy-item small {
        color: var(--text-soft);
        white-space: nowrap;
    }

    .client-note-item p,
    .client-document-item p,
    .client-policy-item p {
        margin: 0;
        color: var(--text-soft);
        line-height: 1.55;
    }

    .client-document-type {
        margin-top: .65rem;
    }

    .client-header-pills {
        display: flex;
        flex-wrap: wrap;
        gap: .55rem;
        margin-top: .55rem;
    }

    .client-empty {
        padding: 1.1rem;
        text-align: center;
        color: var(--text-soft);
    }

    .client-inline-note {
        padding: .85rem 1rem;
        border-radius: 16px;
        border: 1px dashed rgba(100, 116, 139, .28);
        background: linear-gradient(180deg, #fbfdff 0%, #f7faff 100%);
    }

    .client-portal-box {
        display: grid;
        gap: .65rem;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .client-portal-box .panel strong {
        display: block;
        margin-bottom: .3rem;
    }

    .client-note-form-help {
        margin: 0 0 1rem;
        color: var(--text-soft);
    }

    @media (max-width: 1100px) {
        .client-summary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 920px) {
        .client-hero {
            grid-template-columns: 1fr;
        }

        .client-hero__actions {
            justify-content: flex-start;
        }

        .client-meta-grid,
        .client-portal-box {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 680px) {
        .client-summary {
            grid-template-columns: 1fr;
        }

        .client-hero__main {
            align-items: flex-start;
        }

        .client-note-item__top,
        .client-document-item__top,
        .client-policy-item__top {
            flex-direction: column;
        }
    }
</style>

<div class="client-detail-grid">
    <section class="card">
        <div class="client-hero">
            <div class="client-hero__main">
                <div class="avatar avatar--lg"><?= demo_e(demo_avatar_initials($client['name'] ?? '')) ?></div>
                <div class="client-hero__meta">
                    <span class="badge badge-<?= demo_e(($client['status'] ?? '') === 'activo' ? 'success' : 'danger') ?>">
                        <?= demo_e(ucfirst($client['status'] ?? '—')) ?>
                    </span>
                    <h2><?= demo_e($client['name'] ?? 'Cliente') ?></h2>
                    <p><?= demo_e(($client['document_type'] ?? 'Doc') . ' ' . ($client['document_number'] ?? '—')) ?> · <?= demo_e(ucfirst($client['type'] ?? 'persona')) ?></p>

                    <div class="client-header-pills">
                        <span class="badge badge-info"><?= demo_e($executive['full_name'] ?? 'Sin ejecutivo asignado') ?></span>
                        <?php if (!empty($client['has_portal_access'])): ?>
                            <span class="badge badge-success">Portal activo</span>
                        <?php else: ?>
                            <span class="badge badge-warning">Sin acceso portal</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="client-hero__actions">
                <button type="button" class="btn btn-ghost" id="btn-send-message">Enviar mensaje</button>
                <button type="button" class="btn btn-secondary" id="btn-new-policy">Nueva póliza</button>
                <button type="button" class="btn btn-primary" id="btn-create-portal" <?= !empty($client['has_portal_access']) ? 'disabled' : '' ?>>
                    <?= !empty($client['has_portal_access']) ? 'Portal activo' : 'Crear acceso portal' ?>
                </button>
            </div>
        </div>
    </section>

    <section class="client-summary">
        <article class="card kpi-card">
            <p class="kpi-card__label">Pólizas activas</p>
            <h3 class="kpi-card__value" id="summary-active-policies"><?= demo_e((string)count($activePolicies)) ?></h3>
            <p class="kpi-card__meta">Coberturas vigentes asociadas a este cliente.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Cuotas pendientes</p>
            <h3 class="kpi-card__value" id="summary-pending-installments"><?= demo_e((string)count($pendingInstallments)) ?></h3>
            <p class="kpi-card__meta">Incluye pendientes, vencidas y en revisión.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Siniestros abiertos</p>
            <h3 class="kpi-card__value" id="summary-open-claims"><?= demo_e((string)count($openClaims)) ?></h3>
            <p class="kpi-card__meta">Casos activos que aún requieren seguimiento.</p>
        </article>

        <article class="card kpi-card">
            <p class="kpi-card__label">Documentos</p>
            <h3 class="kpi-card__value" id="summary-documents"><?= demo_e((string)count($clientDocuments)) ?></h3>
            <p class="kpi-card__meta">Archivos relacionados al cliente, pólizas y siniestros.</p>
        </article>
    </section>

    <section class="card">
        <div class="tab-nav" data-tab-group id="client-tabs">
            <button type="button" class="tab-btn is-active" data-tab-button="resumen">Resumen</button>
            <button type="button" class="tab-btn" data-tab-button="polizas">Pólizas</button>
            <button type="button" class="tab-btn" data-tab-button="documentos">Documentos</button>
            <button type="button" class="tab-btn" data-tab-button="notas">Notas</button>
            <button type="button" class="tab-btn" data-tab-button="actividad">Actividad</button>
        </div>

        <div class="client-tab-panels" data-tab-group>
            <div data-tab-panel="resumen">
                <div class="client-meta-grid">
                    <div class="client-meta-item">
                        <strong>Correo</strong>
                        <span><?= demo_e($client['email'] ?? '—') ?></span>
                    </div>
                    <div class="client-meta-item">
                        <strong>Teléfono</strong>
                        <span><?= demo_e($client['phone'] ?? '—') ?></span>
                    </div>
                    <div class="client-meta-item">
                        <strong>Dirección</strong>
                        <span><?= demo_e($client['address'] ?? '—') ?></span>
                    </div>
                    <div class="client-meta-item">
                        <strong>Ejecutivo asignado</strong>
                        <span><?= demo_e($executive['full_name'] ?? 'Sin asignar') ?></span>
                    </div>
                </div>

                <div class="client-inline-note mt-2">
                    <strong>Observaciones generales</strong>
                    <p class="muted mt-1" id="client-general-notes"><?= demo_e($client['notes'] ?? 'Sin observaciones registradas.') ?></p>
                </div>

                <div class="mt-2">
                    <h3 class="card__title">Estado de acceso portal</h3>
                    <p class="card__subtitle">Resumen operativo del acceso del cliente a su portal.</p>

                    <div class="client-portal-box mt-2">
                        <div class="panel">
                            <strong>Acceso</strong>
                            <p id="portal-access-status"><?= !empty($client['has_portal_access']) ? 'Activo' : 'No creado' ?></p>
                        </div>
                        <div class="panel">
                            <strong>Usuario demo</strong>
                            <p id="portal-access-user"><?= demo_e($portalUser['username'] ?? 'Sin acceso generado') ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div data-tab-panel="polizas" hidden>
                <div class="client-policy-list" id="client-policy-list">
                    <?php if (empty($clientPolicies)): ?>
                        <div class="client-empty">Este cliente aún no tiene pólizas asociadas en el demo.</div>
                    <?php else: ?>
                        <?php foreach ($clientPolicies as $policy): ?>
                            <article class="client-policy-item">
                                <div class="client-policy-item__top">
                                    <div>
                                        <h4><?= demo_e($policy['policy_number'] ?? 'Póliza') ?></h4>
                                        <p><?= demo_e(demo_insurer_name($policy['insurer_id'] ?? '')) ?> · <?= demo_e(demo_insurance_type_name($policy['insurance_type_id'] ?? '')) ?></p>
                                    </div>
                                    <div><?= demo_badge((string)($policy['status'] ?? '—'), (string)($policy['status'] ?? '—')) ?></div>
                                </div>
                                <p>Vigencia: <?= demo_e(demo_date($policy['start_date'] ?? null)) ?> al <?= demo_e(demo_date($policy['end_date'] ?? null)) ?> · Prima: <?= demo_e(demo_money((float)($policy['premium'] ?? 0), (string)($policy['currency'] ?? 'S/'))) ?></p>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div data-tab-panel="documentos" hidden>
                <div class="client-document-list">
                    <?php if (empty($clientDocuments)): ?>
                        <div class="client-empty">No hay documentos relacionados a este cliente.</div>
                    <?php else: ?>
                        <?php foreach ($clientDocuments as $document): ?>
                            <article class="client-document-item">
                                <div class="client-document-item__top">
                                    <div>
                                        <h4><?= demo_e($document['original_name'] ?? 'Documento') ?></h4>
                                        <p>Subido por <?= demo_e(demo_find_user_by_id((string)($document['uploaded_by'] ?? ''))['full_name'] ?? 'Sistema') ?></p>
                                    </div>
                                    <small><?= demo_e(demo_date($document['created_at'] ?? null, 'd/m/Y H:i')) ?></small>
                                </div>
                                <div class="client-document-type"><?= demo_badge((string)($document['type'] ?? 'archivo'), 'info') ?></div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div data-tab-panel="notas" hidden>
                <div class="card__header" style="padding: 0 0 1rem;">
                    <div>
                        <h3 class="card__title">Notas internas</h3>
                        <p class="card__subtitle">Seguimiento comercial y observaciones operativas del cliente.</p>
                    </div>
                    <button type="button" class="btn btn-primary" id="btn-add-note">Nueva nota</button>
                </div>

                <div class="client-note-list" id="client-note-list">
                    <?php if (empty($clientNotes)): ?>
                        <div class="client-empty">No hay notas registradas todavía.</div>
                    <?php else: ?>
                        <?php foreach ($clientNotes as $note): ?>
                            <article class="client-note-item">
                                <div class="client-note-item__top">
                                    <div>
                                        <h4><?= demo_e($note['author_name'] ?? 'Sistema') ?></h4>
                                        <p><?= demo_e($note['note'] ?? '') ?></p>
                                    </div>
                                    <small><?= demo_e(demo_date($note['created_at'] ?? null, 'd/m/Y H:i')) ?></small>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div data-tab-panel="actividad" hidden>
                <div class="timeline" id="client-activity-timeline">
                    <?php if (empty($clientActivity)): ?>
                        <div class="client-empty">No hay actividad registrada para este cliente.</div>
                    <?php else: ?>
                        <?php foreach ($clientActivity as $item): ?>
                            <article class="timeline__item">
                                <h4><?= demo_e($item['title'] ?? 'Actividad') ?></h4>
                                <p><?= demo_e($item['description'] ?? '') ?></p>
                                <small class="muted"><?= demo_e(demo_date($item['created_at'] ?? null, 'd/m/Y H:i')) ?></small>
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
            <h3>Nueva nota interna</h3>
            <button type="button" class="icon-btn" data-modal-close aria-label="Cerrar">✕</button>
        </div>
        <div class="modal__body">
            <p class="client-note-form-help">Esta nota se guardará de forma simulada y quedará visible en la ficha del cliente durante la sesión actual.</p>
            <form id="client-note-form">
                <input type="hidden" name="action" value="add_note">
                <input type="hidden" name="client_id" value="<?= demo_e($clientId) ?>">
                <div>
                    <label class="form-label" for="client-note-text">Contenido de la nota</label>
                    <textarea class="textarea" id="client-note-text" name="note" placeholder="Escribe el detalle de seguimiento"></textarea>
                </div>
            </form>
        </div>
        <div class="modal__footer">
            <button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button>
            <button type="button" class="btn btn-primary" id="client-note-submit">Guardar nota</button>
        </div>
    </div>
</div>

<script>
    (() => {
        const clientId = <?= json_encode($clientId, JSON_UNESCAPED_UNICODE) ?>;
        const endpoint = <?= json_encode(demo_url('ajax/clientes.php'), JSON_UNESCAPED_UNICODE) ?>;

        const noteList = document.getElementById('client-note-list');
        const timeline = document.getElementById('client-activity-timeline');
        const createPortalBtn = document.getElementById('btn-create-portal');
        const portalStatus = document.getElementById('portal-access-status');
        const portalUser = document.getElementById('portal-access-user');

        const summaryNotes = document.getElementById('client-general-notes');
        const noteForm = document.getElementById('client-note-form');
        const noteSubmit = document.getElementById('client-note-submit');

        const addNoteBtn = document.getElementById('btn-add-note');
        const sendMessageBtn = document.getElementById('btn-send-message');
        const newPolicyBtn = document.getElementById('btn-new-policy');

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

        const prependNote = (note) => {
            const empty = noteList.querySelector('.client-empty');
            if (empty) empty.remove();

            const article = document.createElement('article');
            article.className = 'client-note-item';
            article.innerHTML = `
                <div class="client-note-item__top">
                    <div>
                        <h4>${escapeHtml(note.author_name || 'Sistema')}</h4>
                        <p>${escapeHtml(note.note || '')}</p>
                    </div>
                    <small>${escapeHtml(note.created_at_label || nowLabel())}</small>
                </div>
            `;
            noteList.prepend(article);
        };

        const prependActivity = (item) => {
            const empty = timeline.querySelector('.client-empty');
            if (empty) empty.remove();

            const article = document.createElement('article');
            article.className = 'timeline__item';
            article.innerHTML = `
                <h4>${escapeHtml(item.title || 'Actividad')}</h4>
                <p>${escapeHtml(item.description || '')}</p>
                <small class="muted">${escapeHtml(item.created_at_label || nowLabel())}</small>
            `;
            timeline.prepend(article);
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
                        <p class="mt-1">El acceso queda activo solo dentro de esta sesión demo.</p>
                    </div>
                </div>
            `;
            DemoApp.openModal('generic-modal');
        };

        addNoteBtn.addEventListener('click', () => {
            noteForm.reset();
            DemoApp.openModal('client-note-modal');
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
                message: response.message || 'La nota fue agregada correctamente.',
                type: 'success'
            });

            if (response.note) {
                prependNote(response.note);
                prependActivity({
                    title: 'Nota interna agregada',
                    description: response.note.note || '',
                    created_at_label: response.note.created_at_label || nowLabel()
                });

                if (!summaryNotes.textContent || summaryNotes.textContent.includes('Sin observaciones')) {
                    summaryNotes.textContent = response.note.note || '';
                }
            }
        });

        createPortalBtn.addEventListener('click', () => {
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
                            message: response.message || 'Verifica los datos del cliente.',
                            type: 'error'
                        });
                        return;
                    }

                    DemoApp.toast({
                        title: response.title || 'Acceso creado',
                        message: response.message || 'El acceso portal se generó correctamente.',
                        type: 'success'
                    });

                    createPortalBtn.disabled = true;
                    createPortalBtn.textContent = 'Portal activo';
                    portalStatus.textContent = 'Activo';
                    portalUser.textContent = response.credentials?.username || '—';

                    prependActivity({
                        title: 'Acceso portal habilitado',
                        description: `Se generó el usuario demo ${response.credentials?.username || '—'} para el portal cliente.`,
                        created_at_label: nowLabel()
                    });

                    showPortalCredentials(response);
                }
            });
        });

        sendMessageBtn.addEventListener('click', () => {
            DemoApp.toast({
                title: 'Enviar mensaje',
                message: 'Acción demo lista para integrarse con el módulo de comunicaciones.',
                type: 'info'
            });
        });

        newPolicyBtn.addEventListener('click', () => {
            DemoApp.toast({
                title: 'Nueva póliza',
                message: 'Acción demo lista para integrarse con el módulo de pólizas.',
                type: 'info'
            });
        });
    })();
</script>
<?php
$content = ob_get_clean();

demo_render_internal_layout(
    'Ficha de cliente',
    $content,
    [
        'breadcrumb' => ['Inicio', 'Clientes', $client['name'] ?? 'Detalle'],
        'subtitle' => 'Vista completa de pólizas, documentos, notas y actividad asociada al cliente.',
    ]
);