<?php
// index.php - PIZZARRA v1
// Interfaz visual sin base de datos. Rutas relativas para facilitar migración.
if (!function_exists('pz_e')) {
    function pz_e($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$PZ_ASSET_PREFIX = isset($PZ_ASSET_PREFIX) ? $PZ_ASSET_PREFIX : './';
$PZ_UPLOAD_URL = isset($PZ_UPLOAD_URL) ? $PZ_UPLOAD_URL : $PZ_ASSET_PREFIX . 'upload_image.php';
$PZ_STANDALONE = isset($PZ_STANDALONE) ? (bool)$PZ_STANDALONE : true;
?>
<?php if ($PZ_STANDALONE): ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PIZZARRA | Caja, pizarra y piezas</title>
    <link rel="icon" type="image/png" href="<?php echo pz_e($PZ_ASSET_PREFIX); ?>favicon.png">
    <link rel="stylesheet" href="<?php echo pz_e($PZ_ASSET_PREFIX); ?>plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="<?php echo pz_e($PZ_ASSET_PREFIX); ?>plugins/summernote/summernote-bs4.min.css">
    <link rel="stylesheet" href="<?php echo pz_e($PZ_ASSET_PREFIX); ?>dist/css/adminlte.min.css">
    <link rel="stylesheet" href="<?php echo pz_e($PZ_ASSET_PREFIX); ?>pizzarra.css?v=1.0.0">
</head>
<body>
<?php endif; ?>

<div class="pz-page" id="pizzarraApp" data-upload-url="<?php echo pz_e($PZ_UPLOAD_URL); ?>" data-asset-prefix="<?php echo pz_e($PZ_ASSET_PREFIX); ?>">
    <header class="pz-brand-hero">
        <div class="pz-brand-main">
            <img src="<?php echo pz_e($PZ_ASSET_PREFIX); ?>logo_pizzarra.png" alt="PIZZARRA" class="pz-brand-logo">
            <div>
                <h1>PIZZARRA</h1>
                <p><strong>Organiza</strong> tus proyectos. <strong class="pz-red-text">Sazona</strong> tus ideas. <strong class="pz-green-text">Sirve</strong> resultados.</p>
            </div>
        </div>
        <div class="pz-palette" aria-label="Paleta de colores de PIZZARRA">
            <div class="pz-palette-title">Paleta de colores</div>
            <div class="pz-palette-grid">
                <span class="pz-swatch" style="--c:#F58A1F" title="Naranja marca #F58A1F"></span>
                <span class="pz-swatch" style="--c:#FFF8EE" title="Fondo crema #FFF8EE"></span>
                <span class="pz-swatch" style="--c:#FFFDF9" title="Tarjeta #FFFDF9"></span>
                <span class="pz-swatch" style="--c:#222222" title="Texto #222222"></span>
                <span class="pz-swatch" style="--c:#F6C945" title="Queso #F6C945"></span>
                <span class="pz-swatch" style="--c:#F14937" title="Salsa #F14937"></span>
                <span class="pz-swatch" style="--c:#6BAE45" title="Albahaca #6BAE45"></span>
                <span class="pz-swatch" style="--c:#2B2B2F" title="Aceituna #2B2B2F"></span>
                <span class="pz-swatch" style="--c:#7C3AED" title="Morado auxiliar #7C3AED"></span>
                <span class="pz-swatch" style="--c:#4DA3FF" title="Azul suave #4DA3FF"></span>
            </div>
        </div>
    </header>

    <main class="pz-app-window">
        <nav class="pz-appbar">
            <div class="pz-appbar-brand">
                <img src="<?php echo pz_e($PZ_ASSET_PREFIX); ?>logo_pizzarra.png" alt="PIZZARRA">
                <span>PIZZARRA</span>
            </div>

            <div class="pz-appbar-control pz-project-control">
                <label for="pzProjectSelect">Caja</label>
                <select id="pzProjectSelect" aria-label="Seleccionar caja o proyecto"></select>
            </div>

            <div class="pz-date-nav" aria-label="Navegación de fecha">
                <button type="button" class="pz-icon-btn" id="pzPrevDay" title="Día anterior">‹</button>
                <button type="button" class="pz-date-pill" id="pzDateLabel" title="Cambiar fecha">Hoy</button>
                <input type="date" id="pzDateInput" class="pz-date-input" aria-label="Fecha actual">
                <button type="button" class="pz-icon-btn" id="pzNextDay" title="Día siguiente">›</button>
            </div>

            <div class="pz-view-switch" role="group" aria-label="Cambiar vista">
                <button type="button" class="active" data-view="day">Día</button>
                <button type="button" data-view="week">Semana</button>
                <button type="button" data-view="month">Mes</button>
            </div>

            <div class="pz-actions">
                <button type="button" class="pz-btn pz-btn-light" id="pzBtnNewPiece"><span>+</span> Nueva pieza</button>
                <button type="button" class="pz-btn pz-btn-primary" id="pzBtnNewBox"><span>+</span> Nueva caja</button>
            </div>
        </nav>

        <div class="pz-app-layout">
            <aside class="pz-left-panel">
                <section class="pz-panel-card">
                    <div class="pz-panel-title">
                        <span><i class="pz-mini-icon">▣</i> Cajas</span>
                        <button type="button" class="pz-mini-btn" id="pzBtnNewBoxSide" title="Nueva caja">+</button>
                    </div>
                    <div class="pz-project-list" id="pzProjectList"></div>
                </section>

                <section class="pz-panel-card">
                    <div class="pz-panel-title">
                        <span><i class="pz-mini-icon">★</i> Favoritos</span>
                        <button type="button" class="pz-mini-btn" id="pzBtnManageColors" title="Gestionar colores">+</button>
                    </div>
                    <div class="pz-color-favorites" id="pzFavoriteColors"></div>
                </section>

                <section class="pz-panel-card">
                    <div class="pz-panel-title"><span><i class="pz-mini-icon">⌕</i> Filtros</span></div>
                    <label class="pz-search-wrap">
                        <span>Buscar</span>
                        <input type="search" id="pzSearch" placeholder="Título, etiqueta, texto...">
                    </label>
                    <label class="pz-check"><input type="checkbox" id="pzFilterImages"> Solo con imágenes</label>
                    <label class="pz-check"><input type="checkbox" id="pzFilterUrgent"> Solo urgentes</label>
                    <label class="pz-check"><input type="checkbox" id="pzFilterDone"> Solo culminadas</label>
                    <label class="pz-check"><input type="checkbox" id="pzFilterExtras"> Solo con extras</label>
                </section>

                <section class="pz-panel-card">
                    <div class="pz-panel-title"><span><i class="pz-mini-icon">◔</i> Resumen</span></div>
                    <div class="pz-summary-list" id="pzSummaryList"></div>
                    <div class="pz-progress-shell">
                        <div class="pz-progress-head">
                            <span>Avance del día</span>
                            <strong id="pzProgressText">0%</strong>
                        </div>
                        <div class="pz-progress-track"><div id="pzProgressBar"></div></div>
                    </div>
                </section>
            </aside>

            <section class="pz-board-area">
                <div class="pz-board-head">
                    <div>
                        <div class="pz-kicker">Board diario = Pizarra</div>
                        <h2 id="pzBoardTitle">Pizarra de hoy</h2>
                        <p id="pzBoardSubtitle">Cada pieza conserva su estado, color, extras y contenido enriquecido mientras no recargues la página.</p>
                    </div>
                    <div class="pz-board-tools">
                        <button type="button" class="pz-btn pz-btn-light" id="pzBtnToday">Hoy</button>
                        <button type="button" class="pz-btn pz-btn-dark" id="pzBtnReset">Restaurar vista</button>
                    </div>
                </div>

                <div class="pz-board-view" id="pzDayView">
                    <div class="pz-kanban" id="pzKanbanBoard">
                        <article class="pz-column" data-status="pendiente">
                            <header class="pz-column-head pz-head-pending">
                                <div class="pz-status-icon">🧀</div>
                                <div>
                                    <h3>PENDIENTE</h3>
                                    <p>Queso por fundir</p>
                                </div>
                                <button type="button" class="pz-column-add" data-status="pendiente" title="Agregar pieza">+</button>
                            </header>
                            <div class="pz-column-dropzone" id="pzColPendiente" data-status="pendiente"></div>
                            <button type="button" class="pz-add-piece-link" data-status="pendiente">+ Agregar pieza</button>
                        </article>

                        <article class="pz-column" data-status="proceso">
                            <header class="pz-column-head pz-head-process">
                                <div class="pz-status-icon">🍅</div>
                                <div>
                                    <h3>EN PROCESO</h3>
                                    <p>Bañando en salsa</p>
                                </div>
                                <button type="button" class="pz-column-add" data-status="proceso" title="Agregar pieza">+</button>
                            </header>
                            <div class="pz-column-dropzone" id="pzColProceso" data-status="proceso"></div>
                            <button type="button" class="pz-add-piece-link" data-status="proceso">+ Agregar pieza</button>
                        </article>

                        <article class="pz-column" data-status="culminada">
                            <header class="pz-column-head pz-head-done">
                                <div class="pz-status-icon">🌿</div>
                                <div>
                                    <h3>CULMINADA</h3>
                                    <p>Toque final de albahaca</p>
                                </div>
                                <button type="button" class="pz-column-add" data-status="culminada" title="Agregar pieza">+</button>
                            </header>
                            <div class="pz-column-dropzone" id="pzColCulminada" data-status="culminada"></div>
                            <button type="button" class="pz-add-piece-link" data-status="culminada">+ Agregar pieza</button>
                        </article>

                        <article class="pz-column" data-status="pausada">
                            <header class="pz-column-head pz-head-paused">
                                <div class="pz-status-icon">🫒</div>
                                <div>
                                    <h3>PAUSADA</h3>
                                    <p>Aceituna aparte</p>
                                </div>
                                <button type="button" class="pz-column-add" data-status="pausada" title="Agregar pieza">+</button>
                            </header>
                            <div class="pz-column-dropzone" id="pzColPausada" data-status="pausada"></div>
                            <button type="button" class="pz-add-piece-link" data-status="pausada">+ Agregar pieza</button>
                        </article>
                    </div>
                </div>

                <div class="pz-board-view pz-hidden" id="pzWeekView"></div>
                <div class="pz-board-view pz-hidden" id="pzMonthView"></div>
            </section>

            <aside class="pz-right-panel">
                <section class="pz-concept-card">
                    <div class="pz-concept-title"><span>💡</span> Concepto</div>
                    <div class="pz-concept-item">
                        <div class="pz-concept-icon pz-red-bg">▣</div>
                        <div><strong>Proyecto = Caja</strong><p>Una caja contiene todas las piezas de un proyecto.</p></div>
                    </div>
                    <div class="pz-concept-item">
                        <div class="pz-concept-icon pz-yellow-bg">▦</div>
                        <div><strong>Board diario = Pizarra</strong><p>Cada día es una pizarra para ordenar el trabajo.</p></div>
                    </div>
                    <div class="pz-concept-item">
                        <div class="pz-concept-icon pz-green-bg">✦</div>
                        <div><strong>Tarea = Pieza</strong><p>Cada pieza tiene estado, color, fecha y detalles.</p></div>
                    </div>
                    <div class="pz-concept-item">
                        <div class="pz-concept-icon pz-purple-bg">⌁</div>
                        <div><strong>Contenido = Extra</strong><p>Imágenes, enlaces y notas enriquecen cada pieza.</p></div>
                    </div>
                </section>

                <section class="pz-panel-card pz-tip-card">
                    <div class="pz-panel-title"><span>Atajos de flujo</span></div>
                    <ul>
                        <li>Arrastra una pieza entre columnas.</li>
                        <li>Doble clic para editar rápido.</li>
                        <li>Usa “Doble queso” para marcar prioridad.</li>
                    </ul>
                </section>
            </aside>
        </div>
    </main>

    <section class="pz-benefits">
        <div><span>🍕</span><strong>Visual y delicioso</strong><p>Colores cálidos que inspiran foco.</p></div>
        <div><span>🌿</span><strong>Organización clara</strong><p>Kanban simple de entender.</p></div>
        <div><span>⏱</span><strong>Enfocado</strong><p>Fechas, filtros y prioridades.</p></div>
        <div><span>📎</span><strong>Extras a mano</strong><p>Imágenes, enlaces y notas.</p></div>
        <div><span>📊</span><strong>Resultados servidos</strong><p>Proyectos listos para entregar.</p></div>
    </section>

    <div class="pz-modal-backdrop" id="pzModalBackdrop" hidden></div>

    <section class="pz-modal" id="pzPieceModal" aria-hidden="true" aria-label="Formulario de pieza">
        <div class="pz-modal-dialog pz-modal-wide">
            <header class="pz-modal-head">
                <div>
                    <div class="pz-kicker">Pieza de trabajo</div>
                    <h3 id="pzPieceModalTitle">Nueva pieza</h3>
                </div>
                <button type="button" class="pz-modal-close" data-close-modal="pzPieceModal">×</button>
            </header>
            <form id="pzPieceForm" class="pz-modal-body">
                <input type="hidden" id="pzPieceId">
                <div class="pz-form-grid">
                    <label class="pz-field pz-span-2">
                        <span>Título</span>
                        <input type="text" id="pzPieceTitle" maxlength="120" placeholder="Ej. Maquetar vista semanal" required>
                    </label>
                    <label class="pz-field">
                        <span>Caja / proyecto</span>
                        <select id="pzPieceProject"></select>
                    </label>
                    <label class="pz-field">
                        <span>Fecha</span>
                        <input type="date" id="pzPieceDate" required>
                    </label>
                    <label class="pz-field">
                        <span>Estado</span>
                        <select id="pzPieceStatus">
                            <option value="pendiente">Pendiente · Queso por fundir</option>
                            <option value="proceso">En proceso · Bañando en salsa</option>
                            <option value="culminada">Culminada · Toque final de albahaca</option>
                            <option value="pausada">Pausada · Aceituna aparte</option>
                        </select>
                    </label>
                    <label class="pz-field">
                        <span>Prioridad</span>
                        <select id="pzPiecePriority">
                            <option value="Normal">Normal</option>
                            <option value="Alta">Alta</option>
                            <option value="Doble queso">Doble queso</option>
                            <option value="Picante">Picante</option>
                            <option value="Se enfrió">Se enfrió</option>
                        </select>
                    </label>
                    <label class="pz-field">
                        <span>Etiqueta</span>
                        <input type="text" id="pzPieceTag" maxlength="40" placeholder="UI/UX, Backend, Diseño...">
                    </label>
                    <label class="pz-field">
                        <span>Link opcional</span>
                        <input type="url" id="pzPieceLink" placeholder="https://...">
                    </label>
                </div>

                <div class="pz-field pz-color-picker-field">
                    <span>Color de la pieza</span>
                    <div class="pz-color-options" id="pzPieceColorOptions"></div>
                    <input type="color" id="pzPieceCustomColor" value="#F6C945" title="Color personalizado">
                </div>

                <div class="pz-field">
                    <span>Contenido</span>
                    <div id="pzPieceEditor" class="pz-editor" contenteditable="true" data-placeholder="Escribe detalles, pega una tabla, agrega pendientes o texto enriquecido..."></div>
                    <small>Si tienes Summernote en <strong>plugins/summernote/</strong>, se activará automáticamente. Si no, este editor funciona igual como contenido enriquecido básico.</small>
                </div>

                <div class="pz-form-grid">
                    <label class="pz-field">
                        <span>Imágenes / extras</span>
                        <input type="file" id="pzPieceImages" accept="image/*" multiple>
                        <small>Las imágenes se previsualizan al instante. Si el servidor permite escritura, también se guardan en <strong>almacen/año/mes/día</strong>.</small>
                    </label>
                    <div class="pz-field">
                        <span>Previsualización de extras</span>
                        <div class="pz-upload-preview" id="pzUploadPreview"></div>
                    </div>
                </div>
            </form>
            <footer class="pz-modal-foot">
                <button type="button" class="pz-btn pz-btn-light" data-close-modal="pzPieceModal">Cancelar</button>
                <button type="button" class="pz-btn pz-btn-dark" id="pzBtnDeletePiece">Eliminar</button>
                <button type="submit" form="pzPieceForm" class="pz-btn pz-btn-primary">Guardar pieza</button>
            </footer>
        </div>
    </section>

    <section class="pz-modal" id="pzBoxModal" aria-hidden="true" aria-label="Formulario de caja">
        <div class="pz-modal-dialog">
            <header class="pz-modal-head">
                <div>
                    <div class="pz-kicker">Proyecto = Caja</div>
                    <h3>Nueva caja</h3>
                </div>
                <button type="button" class="pz-modal-close" data-close-modal="pzBoxModal">×</button>
            </header>
            <form id="pzBoxForm" class="pz-modal-body">
                <label class="pz-field">
                    <span>Nombre de la caja</span>
                    <input type="text" id="pzBoxName" maxlength="80" placeholder="Ej. Sabores CRM" required>
                </label>
                <label class="pz-field">
                    <span>Descripción breve</span>
                    <textarea id="pzBoxDescription" rows="3" maxlength="180" placeholder="Describe qué piezas contiene este proyecto."></textarea>
                </label>
                <label class="pz-field">
                    <span>Color de la caja</span>
                    <input type="color" id="pzBoxColor" value="#F58A1F">
                </label>
            </form>
            <footer class="pz-modal-foot">
                <button type="button" class="pz-btn pz-btn-light" data-close-modal="pzBoxModal">Cancelar</button>
                <button type="submit" form="pzBoxForm" class="pz-btn pz-btn-primary">Crear caja</button>
            </footer>
        </div>
    </section>

    <section class="pz-modal" id="pzColorsModal" aria-hidden="true" aria-label="Colores favoritos">
        <div class="pz-modal-dialog">
            <header class="pz-modal-head">
                <div>
                    <div class="pz-kicker">Colores favoritos</div>
                    <h3>Define tus colores de piezas</h3>
                </div>
                <button type="button" class="pz-modal-close" data-close-modal="pzColorsModal">×</button>
            </header>
            <div class="pz-modal-body">
                <p class="pz-muted">Estos colores viven solo en la sesión actual. Al recargar la página, vuelve la información genérica inicial.</p>
                <div class="pz-color-favorites pz-color-favorites-large" id="pzColorsManager"></div>
                <div class="pz-inline-form">
                    <input type="color" id="pzNewFavoriteColor" value="#7C3AED">
                    <input type="text" id="pzNewFavoriteName" maxlength="30" placeholder="Nombre del color">
                    <button type="button" class="pz-btn pz-btn-primary" id="pzBtnAddFavoriteColor">Agregar</button>
                </div>
            </div>
            <footer class="pz-modal-foot">
                <button type="button" class="pz-btn pz-btn-light" data-close-modal="pzColorsModal">Cerrar</button>
            </footer>
        </div>
    </section>

    <div class="pz-toast-wrap" id="pzToastWrap" aria-live="polite" aria-atomic="true"></div>
</div>

<?php if ($PZ_STANDALONE): ?>
<script src="<?php echo pz_e($PZ_ASSET_PREFIX); ?>plugins/jquery/jquery.min.js"></script>
<script src="<?php echo pz_e($PZ_ASSET_PREFIX); ?>plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo pz_e($PZ_ASSET_PREFIX); ?>plugins/summernote/summernote-bs4.min.js"></script>
<script>
    window.PIZZARRA_CONFIG = {
        uploadUrl: <?php echo json_encode($PZ_UPLOAD_URL); ?>,
        assetPrefix: <?php echo json_encode($PZ_ASSET_PREFIX); ?>
    };
</script>
<script src="<?php echo pz_e($PZ_ASSET_PREFIX); ?>pizzarra.js?v=1.0.0"></script>
</body>
</html>
<?php endif; ?>
