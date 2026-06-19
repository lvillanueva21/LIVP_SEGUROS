<?php
require_once __DIR__ . '/../../includes/module_guard.php';
cb_require_module_context('catalogos');

$csrfCatalogos = cb_local_csrf_token('catalogos');
$permCatalogos = [
    'puede_crear' => cb_cliente_puede('catalogos', 'puede_crear'),
    'puede_editar' => cb_cliente_puede('catalogos', 'puede_editar'),
    'puede_eliminar' => cb_cliente_puede('catalogos', 'puede_eliminar'),
];
?>
<div class="catalogos-module" data-csrf="<?php echo cb_e($csrfCatalogos); ?>">
  <div class="row mb-3">
    <div class="col-12">
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
          <h1 class="h4 mb-1">Catálogos</h1>
          <p class="text-muted mb-0">Gestión de aseguradoras, ramos y productos o planes.</p>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-md-4">
      <div class="small-box bg-info">
        <div class="inner">
          <h3 id="cat-kpi-aseguradoras">0</h3>
          <p>Aseguradoras activas</p>
        </div>
        <div class="icon"><i class="fas fa-building"></i></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="small-box bg-success">
        <div class="inner">
          <h3 id="cat-kpi-ramos">0</h3>
          <p>Ramos activos</p>
        </div>
        <div class="icon"><i class="fas fa-layer-group"></i></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="small-box bg-warning">
        <div class="inner">
          <h3 id="cat-kpi-productos">0</h3>
          <p>Productos o planes activos</p>
        </div>
        <div class="icon"><i class="fas fa-file-contract"></i></div>
      </div>
    </div>
  </div>

  <div class="card card-primary card-outline">
    <div class="card-header p-0 border-bottom-0">
      <ul class="nav nav-tabs" id="catalogos-tabs" role="tablist">
        <li class="nav-item">
          <a class="nav-link active" id="tab-aseguradoras-link" data-toggle="pill" href="#tab-aseguradoras" role="tab" aria-controls="tab-aseguradoras" aria-selected="true">Aseguradoras</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" id="tab-ramos-link" data-toggle="pill" href="#tab-ramos" role="tab" aria-controls="tab-ramos" aria-selected="false">Ramos</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" id="tab-productos-link" data-toggle="pill" href="#tab-productos" role="tab" aria-controls="tab-productos" aria-selected="false">Productos / Planes</a>
        </li>
      </ul>
    </div>
    <div class="card-body">
      <div class="tab-content">
        <div class="tab-pane fade show active" id="tab-aseguradoras" role="tabpanel" aria-labelledby="tab-aseguradoras-link">
          <div class="catalogos-toolbar">
            <div class="input-group input-group-sm">
              <input type="search" class="form-control" id="aseguradoras-search" placeholder="Buscar aseguradora">
              <div class="input-group-append">
                <button class="btn btn-outline-secondary" type="button" data-action="search" data-entity="aseguradoras" title="Buscar" aria-label="Buscar"><i class="fas fa-search"></i></button>
              </div>
            </div>
            <select class="form-control form-control-sm" id="aseguradoras-estado" aria-label="Filtro de estado">
              <option value="todos">Todos</option>
              <option value="activo">Activos</option>
              <option value="inactivo">Inactivos</option>
            </select>
            <button class="btn btn-outline-secondary btn-sm" type="button" data-action="clear" data-entity="aseguradoras">
              <i class="fas fa-eraser"></i> Limpiar
            </button>
            <?php if ($permCatalogos['puede_crear']): ?>
              <button class="btn btn-primary btn-sm ml-md-auto" type="button" data-action="new" data-entity="aseguradoras">
                <i class="fas fa-plus"></i> Nuevo
              </button>
            <?php endif; ?>
          </div>
          <div class="text-muted small mb-2" id="aseguradoras-counter">0 registros</div>
          <div class="table-responsive">
            <table class="table table-bordered table-hover table-sm">
              <thead>
                <tr>
                  <th>Código</th>
                  <th>Razón social</th>
                  <th>Nombre comercial</th>
                  <th>RUC</th>
                  <th>Estado</th>
                  <th class="text-center" style="width:72px;">Acciones</th>
                </tr>
              </thead>
              <tbody id="aseguradoras-body"></tbody>
            </table>
          </div>
          <div class="catalogos-loading" id="aseguradoras-loading">Cargando aseguradoras...</div>
          <div class="catalogos-empty" id="aseguradoras-empty">No hay aseguradoras para mostrar.</div>
          <div class="catalogos-pagination" id="aseguradoras-pagination"></div>
        </div>

        <div class="tab-pane fade" id="tab-ramos" role="tabpanel" aria-labelledby="tab-ramos-link">
          <div class="catalogos-toolbar">
            <div class="input-group input-group-sm">
              <input type="search" class="form-control" id="ramos-search" placeholder="Buscar ramo">
              <div class="input-group-append">
                <button class="btn btn-outline-secondary" type="button" data-action="search" data-entity="ramos" title="Buscar" aria-label="Buscar"><i class="fas fa-search"></i></button>
              </div>
            </div>
            <select class="form-control form-control-sm" id="ramos-estado" aria-label="Filtro de estado">
              <option value="todos">Todos</option>
              <option value="activo">Activos</option>
              <option value="inactivo">Inactivos</option>
            </select>
            <button class="btn btn-outline-secondary btn-sm" type="button" data-action="clear" data-entity="ramos">
              <i class="fas fa-eraser"></i> Limpiar
            </button>
            <?php if ($permCatalogos['puede_crear']): ?>
              <button class="btn btn-primary btn-sm ml-md-auto" type="button" data-action="new" data-entity="ramos">
                <i class="fas fa-plus"></i> Nuevo
              </button>
            <?php endif; ?>
          </div>
          <div class="text-muted small mb-2" id="ramos-counter">0 registros</div>
          <div class="table-responsive">
            <table class="table table-bordered table-hover table-sm">
              <thead>
                <tr>
                  <th>Código</th>
                  <th>Nombre</th>
                  <th>Descripción resumida</th>
                  <th>Estado</th>
                  <th class="text-center" style="width:72px;">Acciones</th>
                </tr>
              </thead>
              <tbody id="ramos-body"></tbody>
            </table>
          </div>
          <div class="catalogos-loading" id="ramos-loading">Cargando ramos...</div>
          <div class="catalogos-empty" id="ramos-empty">No hay ramos para mostrar.</div>
          <div class="catalogos-pagination" id="ramos-pagination"></div>
        </div>

        <div class="tab-pane fade" id="tab-productos" role="tabpanel" aria-labelledby="tab-productos-link">
          <div class="catalogos-toolbar">
            <div class="input-group input-group-sm">
              <input type="search" class="form-control" id="productos-search" placeholder="Buscar producto o plan">
              <div class="input-group-append">
                <button class="btn btn-outline-secondary" type="button" data-action="search" data-entity="productos" title="Buscar" aria-label="Buscar"><i class="fas fa-search"></i></button>
              </div>
            </div>
            <select class="form-control form-control-sm" id="productos-estado" aria-label="Filtro de estado">
              <option value="todos">Todos</option>
              <option value="activo">Activos</option>
              <option value="inactivo">Inactivos</option>
            </select>
            <button class="btn btn-outline-secondary btn-sm" type="button" data-action="clear" data-entity="productos">
              <i class="fas fa-eraser"></i> Limpiar
            </button>
            <?php if ($permCatalogos['puede_crear']): ?>
              <button class="btn btn-primary btn-sm ml-md-auto" type="button" data-action="new" data-entity="productos">
                <i class="fas fa-plus"></i> Nuevo
              </button>
            <?php endif; ?>
          </div>
          <div class="text-muted small mb-2" id="productos-counter">0 registros</div>
          <div class="table-responsive">
            <table class="table table-bordered table-hover table-sm">
              <thead>
                <tr>
                  <th>Código</th>
                  <th>Producto / Plan</th>
                  <th>Aseguradora</th>
                  <th>Ramo</th>
                  <th>Estado</th>
                  <th class="text-center" style="width:72px;">Acciones</th>
                </tr>
              </thead>
              <tbody id="productos-body"></tbody>
            </table>
          </div>
          <div class="catalogos-loading" id="productos-loading">Cargando productos...</div>
          <div class="catalogos-empty" id="productos-empty">No hay productos o planes para mostrar.</div>
          <div class="catalogos-pagination" id="productos-pagination"></div>
        </div>
      </div>
    </div>
  </div>

  <div id="catalogos-toast-zone" class="catalogos-toast-zone" aria-live="polite"></div>
</div>

<div class="modal fade" id="modalAseguradora" tabindex="-1" role="dialog" aria-labelledby="modalAseguradoraTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <form class="modal-content" id="formAseguradora">
      <input type="hidden" name="id">
      <div class="modal-header">
        <h5 class="modal-title" id="modalAseguradoraTitle">Aseguradora</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group col-md-4">
            <label>Código</label>
            <input type="text" name="codigo" class="form-control" maxlength="40" required>
          </div>
          <div class="form-group col-md-8">
            <label>Razón social</label>
            <input type="text" name="razon_social" class="form-control" maxlength="180" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Nombre comercial</label>
            <input type="text" name="nombre_comercial" class="form-control" maxlength="180">
          </div>
          <div class="form-group col-md-3">
            <label>RUC</label>
            <input type="text" name="ruc" class="form-control" maxlength="20">
          </div>
          <div class="form-group col-md-3">
            <label>Estado</label>
            <select name="estado" class="form-control">
              <option value="1">Activo</option>
              <option value="0">Inactivo</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-4">
            <label>Contacto</label>
            <input type="text" name="contacto_nombre" class="form-control" maxlength="120">
          </div>
          <div class="form-group col-md-4">
            <label>Email</label>
            <input type="email" name="contacto_email" class="form-control" maxlength="150">
          </div>
          <div class="form-group col-md-4">
            <label>Teléfono</label>
            <input type="text" name="contacto_telefono" class="form-control" maxlength="60">
          </div>
        </div>
        <div class="form-group">
          <label>Sitio web</label>
          <input type="url" name="sitio_web" class="form-control" maxlength="180">
        </div>
        <div class="form-group mb-0">
          <label>Observaciones</label>
          <textarea name="observaciones" class="form-control" rows="3"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="modalRamo" tabindex="-1" role="dialog" aria-labelledby="modalRamoTitle" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form class="modal-content" id="formRamo">
      <input type="hidden" name="id">
      <div class="modal-header">
        <h5 class="modal-title" id="modalRamoTitle">Ramo</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>Código</label>
          <input type="text" name="codigo" class="form-control" maxlength="40" required>
        </div>
        <div class="form-group">
          <label>Nombre</label>
          <input type="text" name="nombre" class="form-control" maxlength="150" required>
        </div>
        <div class="form-group">
          <label>Descripción</label>
          <textarea name="descripcion" class="form-control" rows="3"></textarea>
        </div>
        <div class="form-group mb-0">
          <label>Estado</label>
          <select name="estado" class="form-control">
            <option value="1">Activo</option>
            <option value="0">Inactivo</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="modalProducto" tabindex="-1" role="dialog" aria-labelledby="modalProductoTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <form class="modal-content" id="formProducto">
      <input type="hidden" name="id">
      <div class="modal-header">
        <h5 class="modal-title" id="modalProductoTitle">Producto / Plan</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Aseguradora</label>
            <select name="aseguradora_id" class="form-control" required></select>
          </div>
          <div class="form-group col-md-6">
            <label>Ramo</label>
            <select name="ramo_id" class="form-control" required></select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-4">
            <label>Código</label>
            <input type="text" name="codigo" class="form-control" maxlength="40" required>
          </div>
          <div class="form-group col-md-5">
            <label>Producto</label>
            <input type="text" name="nombre_producto" class="form-control" maxlength="180" required>
          </div>
          <div class="form-group col-md-3">
            <label>Plan</label>
            <input type="text" name="nombre_plan" class="form-control" maxlength="180">
          </div>
        </div>
        <div class="form-group">
          <label>Descripción</label>
          <textarea name="descripcion" class="form-control" rows="3"></textarea>
        </div>
        <div class="form-group mb-0">
          <label>Estado</label>
          <select name="estado" class="form-control">
            <option value="1">Activo</option>
            <option value="0">Inactivo</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
      </div>
    </form>
  </div>
</div>

<style>
  .catalogos-toolbar {
    display: grid;
    gap: .5rem;
    grid-template-columns: minmax(180px, 1fr) 150px auto;
    align-items: center;
    margin-bottom: .75rem;
  }
  .catalogos-loading,
  .catalogos-empty {
    display: none;
    padding: 1rem;
    border: 1px dashed #ced4da;
    border-radius: .25rem;
    text-align: center;
    color: #6c757d;
    background: #f8f9fa;
  }
  .catalogos-pagination .btn {
    min-width: 36px;
  }
  .catalogos-actions {
    display: inline-flex;
    flex-direction: column;
    gap: .25rem;
  }
  .catalogos-toast-zone {
    position: fixed;
    right: 1rem;
    bottom: 1rem;
    z-index: 1080;
    width: min(360px, calc(100vw - 2rem));
  }
  @media (max-width: 767.98px) {
    .catalogos-toolbar {
      grid-template-columns: 1fr;
    }
  }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var csrf = <?php echo json_encode($csrfCatalogos); ?>;
  var permisos = <?php echo json_encode($permCatalogos); ?>;
  var endpoints = {
    aseguradoras: 'api/catalogos/aseguradoras.php',
    ramos: 'api/catalogos/ramos.php',
    productos: 'api/catalogos/productos.php',
    resumen: 'api/catalogos/resumen.php'
  };
  var state = {
    aseguradoras: { page: 1 },
    ramos: { page: 1 },
    productos: { page: 1 }
  };

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char];
    });
  }

  function truncate(value, length) {
    value = String(value || '');
    return value.length > length ? value.substring(0, length - 1) + '…' : value;
  }

  function badgeEstado(estado) {
    return Number(estado) === 1
      ? '<span class="badge badge-success">Activo</span>'
      : '<span class="badge badge-secondary">Inactivo</span>';
  }

  function showToast(message, type) {
    var zone = document.getElementById('catalogos-toast-zone');
    var toast = document.createElement('div');
    toast.className = 'alert alert-' + (type || 'info') + ' shadow-sm mb-2';
    toast.setAttribute('role', 'status');
    toast.textContent = message;
    zone.appendChild(toast);
    setTimeout(function () { toast.remove(); }, 4200);
  }

  function fetchJson(url, options) {
    return fetch(url, options || {}).then(function (response) {
      return response.json().catch(function () {
        return { ok: false, message: 'Respuesta no valida del servidor.' };
      }).then(function (json) {
        if (!response.ok || !json.ok) {
          throw json;
        }
        return json.data || {};
      });
    });
  }

  function setLoading(entity, loading) {
    document.getElementById(entity + '-loading').style.display = loading ? 'block' : 'none';
  }

  function entityParams(entity) {
    var params = new URLSearchParams();
    params.set('page', state[entity].page || 1);
    params.set('per_page', 10);
    params.set('q', document.getElementById(entity + '-search').value || '');
    params.set('estado', document.getElementById(entity + '-estado').value || 'todos');
    return params;
  }

  function actionButtons(entity, row) {
    var buttons = '<span class="catalogos-actions">';
    if (permisos.puede_editar) {
      buttons += '<button type="button" class="btn btn-xs btn-outline-primary" data-action="edit" data-entity="' + entity + '" data-id="' + row.id + '" title="Editar" aria-label="Editar"><i class="fas fa-edit"></i></button>';
    }
    if (permisos.puede_eliminar) {
      var icon = Number(row.estado) === 1 ? 'fa-toggle-on' : 'fa-toggle-off';
      var title = Number(row.estado) === 1 ? 'Inactivar' : 'Activar';
      buttons += '<button type="button" class="btn btn-xs btn-outline-secondary" data-action="toggle" data-entity="' + entity + '" data-id="' + row.id + '" title="' + title + '" aria-label="' + title + '"><i class="fas ' + icon + '"></i></button>';
    }
    buttons += '</span>';
    return buttons;
  }

  function renderRows(entity, rows) {
    var html = '';
    rows.forEach(function (row) {
      if (entity === 'aseguradoras') {
        html += '<tr><td>' + escapeHtml(row.codigo) + '</td><td>' + escapeHtml(row.razon_social) + '</td><td>' + escapeHtml(row.nombre_comercial || '-') + '</td><td>' + escapeHtml(row.ruc || '-') + '</td><td>' + badgeEstado(row.estado) + '</td><td class="text-center">' + actionButtons(entity, row) + '</td></tr>';
      } else if (entity === 'ramos') {
        html += '<tr><td>' + escapeHtml(row.codigo) + '</td><td>' + escapeHtml(row.nombre) + '</td><td>' + escapeHtml(truncate(row.descripcion || '-', 80)) + '</td><td>' + badgeEstado(row.estado) + '</td><td class="text-center">' + actionButtons(entity, row) + '</td></tr>';
      } else {
        var producto = escapeHtml(row.nombre_producto) + (row.nombre_plan ? '<br><small class="text-muted">' + escapeHtml(row.nombre_plan) + '</small>' : '');
        html += '<tr><td>' + escapeHtml(row.codigo) + '</td><td>' + producto + '</td><td>' + escapeHtml(row.aseguradora_nombre) + '</td><td>' + escapeHtml(row.ramo_nombre) + '</td><td>' + badgeEstado(row.estado) + '</td><td class="text-center">' + actionButtons(entity, row) + '</td></tr>';
      }
    });
    document.getElementById(entity + '-body').innerHTML = html;
    document.getElementById(entity + '-empty').style.display = rows.length ? 'none' : 'block';
  }

  function renderPagination(entity, pagination) {
    var wrap = document.getElementById(entity + '-pagination');
    var total = Number(pagination.total || 0);
    var page = Number(pagination.page || 1);
    var last = Number(pagination.last_page || 1);
    document.getElementById(entity + '-counter').textContent = total + (total === 1 ? ' registro' : ' registros');

    if (last <= 1) {
      wrap.innerHTML = '';
      return;
    }

    var html = '<div class="btn-group btn-group-sm">';
    html += '<button type="button" class="btn btn-outline-secondary" data-action="page" data-entity="' + entity + '" data-page="' + (page - 1) + '"' + (page <= 1 ? ' disabled' : '') + ' title="Anterior" aria-label="Anterior"><i class="fas fa-chevron-left"></i></button>';
    html += '<button type="button" class="btn btn-outline-secondary" disabled>' + page + ' / ' + last + '</button>';
    html += '<button type="button" class="btn btn-outline-secondary" data-action="page" data-entity="' + entity + '" data-page="' + (page + 1) + '"' + (page >= last ? ' disabled' : '') + ' title="Siguiente" aria-label="Siguiente"><i class="fas fa-chevron-right"></i></button>';
    html += '</div>';
    wrap.innerHTML = html;
  }

  function loadEntity(entity) {
    setLoading(entity, true);
    fetchJson(endpoints[entity] + '?' + entityParams(entity).toString())
      .then(function (data) {
        renderRows(entity, data.rows || []);
        renderPagination(entity, data.pagination || {});
      })
      .catch(function (error) {
        showToast(error.message || 'No se pudo cargar la informacion.', 'danger');
      })
      .finally(function () {
        setLoading(entity, false);
      });
  }

  function loadResumen() {
    fetchJson(endpoints.resumen)
      .then(function (data) {
        document.getElementById('cat-kpi-aseguradoras').textContent = data.aseguradoras_activas || 0;
        document.getElementById('cat-kpi-ramos').textContent = data.ramos_activos || 0;
        document.getElementById('cat-kpi-productos').textContent = data.productos_activos || 0;
      })
      .catch(function () {
        document.getElementById('cat-kpi-aseguradoras').textContent = '-';
        document.getElementById('cat-kpi-ramos').textContent = '-';
        document.getElementById('cat-kpi-productos').textContent = '-';
      });
  }

  function formDataWithAction(form, action) {
    var data = new FormData(form);
    data.set('action', action);
    data.set('_csrf', csrf);
    return data;
  }

  function fillForm(form, record) {
    Array.prototype.forEach.call(form.elements, function (field) {
      if (!field.name) {
        return;
      }
      field.value = record[field.name] == null ? '' : record[field.name];
    });
  }

  function openModal(entity, id) {
    var modalId = entity === 'aseguradoras' ? 'modalAseguradora' : (entity === 'ramos' ? 'modalRamo' : 'modalProducto');
    var formId = entity === 'aseguradoras' ? 'formAseguradora' : (entity === 'ramos' ? 'formRamo' : 'formProducto');
    var form = document.getElementById(formId);
    form.reset();
    form.elements.id.value = '';

    var ready = Promise.resolve();
    if (entity === 'productos') {
      ready = loadProductOptions();
    }

    ready.then(function () {
      if (!id) {
        $('#' + modalId).modal('show');
        return;
      }
      fetchJson(endpoints[entity] + '?action=get&id=' + encodeURIComponent(id))
        .then(function (data) {
          fillForm(form, data.record || {});
          $('#' + modalId).modal('show');
        })
        .catch(function (error) {
          showToast(error.message || 'No se pudo abrir el registro.', 'danger');
        });
    });
  }

  function loadProductOptions() {
    return Promise.all([
      fetchJson(endpoints.aseguradoras + '?action=options'),
      fetchJson(endpoints.ramos + '?action=options')
    ]).then(function (responses) {
      var asegSelect = document.querySelector('#formProducto [name="aseguradora_id"]');
      var ramoSelect = document.querySelector('#formProducto [name="ramo_id"]');
      asegSelect.innerHTML = '<option value="">Seleccione aseguradora</option>' + (responses[0].rows || []).map(function (row) {
        var label = row.razon_social + (row.nombre_comercial ? ' - ' + row.nombre_comercial : '');
        return '<option value="' + row.id + '">' + escapeHtml(label) + '</option>';
      }).join('');
      ramoSelect.innerHTML = '<option value="">Seleccione ramo</option>' + (responses[1].rows || []).map(function (row) {
        return '<option value="' + row.id + '">' + escapeHtml(row.nombre) + '</option>';
      }).join('');
    });
  }

  function saveEntity(entity, form, modalId) {
    var action = form.elements.id.value ? 'update' : 'create';
    fetchJson(endpoints[entity], { method: 'POST', body: formDataWithAction(form, action) })
      .then(function (data) {
        $('#' + modalId).modal('hide');
        showToast(data.message || 'Registro guardado correctamente.', 'success');
        loadEntity(entity);
        loadResumen();
      })
      .catch(function (error) {
        showToast(error.message || 'No se pudo guardar el registro.', 'danger');
      });
  }

  function toggleEntity(entity, id) {
    var data = new FormData();
    data.set('action', 'toggle');
    data.set('id', id);
    data.set('_csrf', csrf);
    fetchJson(endpoints[entity], { method: 'POST', body: data })
      .then(function (result) {
        showToast(result.message || 'Estado actualizado correctamente.', 'success');
        loadEntity(entity);
        loadResumen();
      })
      .catch(function (error) {
        showToast(error.message || 'No se pudo actualizar el estado.', 'danger');
      });
  }

  document.addEventListener('click', function (event) {
    var btn = event.target.closest('[data-action]');
    if (!btn) {
      return;
    }
    var entity = btn.getAttribute('data-entity');
    var action = btn.getAttribute('data-action');
    if (!entity && action !== 'page') {
      return;
    }
    if (action === 'search') {
      state[entity].page = 1;
      loadEntity(entity);
    } else if (action === 'clear') {
      document.getElementById(entity + '-search').value = '';
      document.getElementById(entity + '-estado').value = 'todos';
      state[entity].page = 1;
      loadEntity(entity);
    } else if (action === 'new') {
      openModal(entity, null);
    } else if (action === 'edit') {
      openModal(entity, btn.getAttribute('data-id'));
    } else if (action === 'toggle') {
      toggleEntity(entity, btn.getAttribute('data-id'));
    } else if (action === 'page') {
      state[entity].page = Number(btn.getAttribute('data-page')) || 1;
      loadEntity(entity);
    }
  });

  ['aseguradoras', 'ramos', 'productos'].forEach(function (entity) {
    document.getElementById(entity + '-estado').addEventListener('change', function () {
      state[entity].page = 1;
      loadEntity(entity);
    });
    document.getElementById(entity + '-search').addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        state[entity].page = 1;
        loadEntity(entity);
      }
    });
  });

  document.getElementById('formAseguradora').addEventListener('submit', function (event) {
    event.preventDefault();
    saveEntity('aseguradoras', event.target, 'modalAseguradora');
  });
  document.getElementById('formRamo').addEventListener('submit', function (event) {
    event.preventDefault();
    saveEntity('ramos', event.target, 'modalRamo');
  });
  document.getElementById('formProducto').addEventListener('submit', function (event) {
    event.preventDefault();
    saveEntity('productos', event.target, 'modalProducto');
  });

  loadResumen();
  loadEntity('aseguradoras');
  loadEntity('ramos');
  loadEntity('productos');
});
</script>
