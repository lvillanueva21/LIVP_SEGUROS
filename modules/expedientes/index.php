<?php
require_once __DIR__ . '/../../includes/module_guard.php';
cb_require_module_context('expedientes');

$csrfExpedientes = cb_local_csrf_token('expedientes');
$permExpedientes = [
    'puede_crear' => cb_cliente_puede('expedientes', 'puede_crear'),
    'puede_editar' => cb_cliente_puede('expedientes', 'puede_editar'),
    'puede_eliminar' => cb_cliente_puede('expedientes', 'puede_eliminar'),
];
?>
<div class="expedientes-module">
  <div class="row mb-3">
    <div class="col-12">
      <h1 class="h4 mb-1">Expedientes</h1>
      <p class="text-muted mb-0">Registro base de solicitudes comerciales por cliente y tipo de seguro.</p>
    </div>
  </div>

  <div class="card card-primary card-outline">
    <div class="card-body">
      <div class="exp-toolbar">
        <div class="input-group input-group-sm">
          <input type="search" class="form-control" id="exp-search" placeholder="Buscar por codigo, cliente, RUC, tipo o descripcion">
          <div class="input-group-append">
            <button class="btn btn-outline-secondary" type="button" id="exp-btn-buscar" title="Buscar" aria-label="Buscar"><i class="fas fa-search"></i></button>
          </div>
        </div>
        <select class="form-control form-control-sm" id="exp-filtro-cliente"><option value="0">Todos los clientes</option></select>
        <select class="form-control form-control-sm" id="exp-filtro-tipo"><option value="0">Todos los tipos</option></select>
        <select class="form-control form-control-sm" id="exp-filtro-estado-exp"><option value="0">Todos los estados</option></select>
        <select class="form-control form-control-sm" id="exp-filtro-activo">
          <option value="todos">Todos</option>
          <option value="activo">Activos</option>
          <option value="inactivo">Inactivos</option>
        </select>
        <button class="btn btn-outline-secondary btn-sm" type="button" id="exp-btn-limpiar"><i class="fas fa-eraser"></i> Limpiar</button>
        <?php if ($permExpedientes['puede_crear']): ?>
          <button class="btn btn-primary btn-sm" type="button" id="exp-btn-nuevo"><i class="fas fa-plus"></i> Registrar expediente</button>
        <?php endif; ?>
      </div>

      <div class="text-muted small mb-2" id="exp-counter">0 registros</div>
      <div class="table-responsive">
        <table class="table table-bordered table-hover table-sm exp-table">
          <thead>
            <tr>
              <th class="exp-col-codigo">Codigo</th>
              <th class="exp-col-cliente">Cliente</th>
              <th class="exp-col-tipo">Tipo de seguro</th>
              <th class="exp-col-descripcion">Descripcion</th>
              <th class="exp-col-estado">Estado expediente</th>
              <th class="exp-col-fecha">Fecha apertura</th>
              <th class="exp-col-activo">Activo</th>
              <th class="text-center" style="width:86px;">Acciones</th>
            </tr>
          </thead>
          <tbody id="exp-body"></tbody>
        </table>
      </div>
      <div class="exp-loading" id="exp-loading">Cargando expedientes...</div>
      <div class="exp-empty" id="exp-empty">Todavia no hay expedientes para mostrar.</div>
      <div class="exp-pagination" id="exp-pagination"></div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalExpediente" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <form class="modal-content" id="formExpediente" autocomplete="off" novalidate>
      <div class="modal-header">
        <h5 class="modal-title" id="modalExpedienteTitle">Expediente</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id">
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Cliente</label>
            <select class="form-control" name="cliente_id" required></select>
          </div>
          <div class="form-group col-md-6">
            <label>Tipo de seguro</label>
            <select class="form-control" name="tipo_seguro_id" required></select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-4">
            <label>Fecha de apertura</label>
            <input class="form-control" name="fecha_apertura" type="date" required>
          </div>
          <div class="form-group col-md-4">
            <label>Estado expediente</label>
            <select class="form-control" name="estado_expediente_id"></select>
            <small class="text-muted" id="exp-estado-help">Al crear se usara el estado inicial activo.</small>
          </div>
          <div class="form-group col-md-4">
            <label>Activo</label>
            <select class="form-control" name="estado">
              <option value="1">Activo</option>
              <option value="0">Inactivo</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label>Descripcion breve u objeto del seguro</label>
          <input class="form-control" name="descripcion" maxlength="255" required>
        </div>
        <div class="form-group mb-0">
          <label>Observaciones</label>
          <textarea class="form-control" name="observaciones" rows="3" maxlength="3000"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary" id="exp-btn-guardar">Guardar</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="modalDetalleExpediente" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-xl exp-detail-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detalle de expediente</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <ul class="nav nav-tabs" id="expDetalleTabs" role="tablist">
          <li class="nav-item"><a class="nav-link active" data-toggle="pill" href="#exp-tab-resumen" role="tab">Resumen</a></li>
          <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#exp-tab-documentos" role="tab">Documentos</a></li>
          <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#exp-tab-requisitos" role="tab">Requisitos</a></li>
          <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#exp-tab-cotizaciones" role="tab">Cotizaciones</a></li>
          <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#exp-tab-polizas" role="tab">Polizas</a></li>
          <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#exp-tab-actividad" role="tab">Actividad</a></li>
        </ul>
        <div class="tab-content border-left border-right border-bottom p-3 exp-detail-content">
          <div class="tab-pane fade show active" id="exp-tab-resumen" role="tabpanel">
            <div id="exp-detalle-resumen"></div>
          </div>
          <div class="tab-pane fade" id="exp-tab-documentos" role="tabpanel">
            <?php if ($permExpedientes['puede_crear']): ?>
              <form id="formExpDocumento" class="mb-3" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="expediente_id">
                <div class="form-row align-items-end">
                  <div class="form-group col-lg-3 col-md-6">
                    <label>Tipo</label>
                    <select class="form-control form-control-sm" name="tipo_documento" required></select>
                  </div>
                  <div class="form-group col-lg-3 col-md-6">
                    <label>Descripcion</label>
                    <input class="form-control form-control-sm" name="descripcion" maxlength="255">
                  </div>
                  <div class="form-group col-lg-4 col-md-8">
                    <label>Archivo</label>
                    <input class="form-control form-control-sm" name="archivo" type="file" required>
                  </div>
                  <div class="form-group col-lg-2 col-md-4">
                    <button class="btn btn-primary btn-sm btn-block" type="submit"><i class="fas fa-upload"></i> Cargar</button>
                  </div>
                </div>
              </form>
            <?php endif; ?>
            <div class="table-responsive">
              <table class="table table-bordered table-sm mb-0 exp-doc-table">
                <thead>
                  <tr>
                    <th>Tipo</th>
                    <th>Archivo</th>
                    <th>Descripcion</th>
                    <th>Fecha</th>
                    <th>Usuario</th>
                    <th>Estado</th>
                    <th class="text-center" style="width:86px;">Acciones</th>
                  </tr>
                </thead>
                <tbody id="exp-docs-body"></tbody>
              </table>
            </div>
            <div class="exp-empty mt-2" id="exp-docs-empty">No hay documentos vinculados.</div>
          </div>
          <div class="tab-pane fade" id="exp-tab-requisitos" role="tabpanel">
            <div class="mb-3">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <h6 class="mb-0">Formatos disponibles</h6>
              </div>
              <div class="table-responsive">
                <table class="table table-bordered table-sm mb-0 exp-formatos-table">
                  <thead>
                    <tr>
                      <th>Formato</th>
                      <th>Descripcion</th>
                      <th>Requisito relacionado</th>
                      <th class="text-center" style="width:86px;">Acciones</th>
                    </tr>
                  </thead>
                  <tbody id="exp-formatos-body"></tbody>
                </table>
              </div>
              <div class="exp-empty mt-2" id="exp-formatos-empty">No hay formatos activos para este tipo de seguro.</div>
            </div>
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
              <div class="form-inline">
                <label class="mr-2 mb-2" for="exp-req-filtro-estado">Estado</label>
                <select class="form-control form-control-sm mb-2" id="exp-req-filtro-estado">
                  <option value="todos">Todos</option>
                </select>
              </div>
              <?php if ($permExpedientes['puede_crear']): ?>
                <button class="btn btn-primary btn-sm mb-2" type="button" id="exp-req-generar" style="display:none;"><i class="fas fa-tasks"></i> Generar requisitos</button>
              <?php endif; ?>
            </div>
            <div class="table-responsive">
              <table class="table table-bordered table-sm mb-0 exp-req-table">
                <thead>
                  <tr>
                    <th>Orden</th>
                    <th>Requisito</th>
                    <th>Descripcion</th>
                    <th>Condicion</th>
                    <th>Estado</th>
                    <th>Observacion</th>
                    <th>Entrega</th>
                    <th>Evaluacion</th>
                    <th>Docs</th>
                    <th class="text-center" style="width:96px;">Acciones</th>
                  </tr>
                </thead>
                <tbody id="exp-req-body"></tbody>
              </table>
            </div>
            <div class="exp-empty mt-2" id="exp-req-empty">Este expediente no tiene requisitos generados.</div>
          </div>
          <div class="tab-pane fade" id="exp-tab-cotizaciones" role="tabpanel">
            <div class="cot-toolbar-detail mb-2">
              <div class="input-group input-group-sm">
                <input type="search" class="form-control" id="cot-search" placeholder="Buscar por codigo, titulo, cliente, aseguradora o descripcion">
                <div class="input-group-append">
                  <button class="btn btn-outline-secondary" type="button" id="cot-btn-buscar" title="Buscar" aria-label="Buscar"><i class="fas fa-search"></i></button>
                </div>
              </div>
              <select class="form-control form-control-sm" id="cot-filtro-estado"><option value="todos">Todos los estados</option></select>
              <select class="form-control form-control-sm" id="cot-filtro-activo">
                <option value="todos">Todos</option>
                <option value="activo">Activas</option>
                <option value="inactivo">Inactivas</option>
              </select>
              <?php if ($permExpedientes['puede_crear']): ?>
                <button class="btn btn-primary btn-sm" type="button" id="cot-btn-nuevo"><i class="fas fa-plus"></i> Registrar cotizacion</button>
              <?php endif; ?>
            </div>
            <div class="text-muted small mb-2" id="cot-counter">0 registros</div>
            <div class="table-responsive">
              <table class="table table-bordered table-sm mb-0 exp-cot-table">
                <thead>
                  <tr>
                    <th>Codigo</th>
                    <th>Titulo</th>
                    <th>Fechas</th>
                    <th>Estado</th>
                    <th>Alternativas</th>
                    <th>Aceptada</th>
                    <th>Activo</th>
                    <th class="text-center" style="width:110px;">Acciones</th>
                  </tr>
                </thead>
                <tbody id="cot-body"></tbody>
              </table>
            </div>
            <div class="exp-empty mt-2" id="cot-empty">No hay cotizaciones registradas para este expediente.</div>
            <div class="exp-pagination" id="cot-pagination"></div>
          </div>
          <div class="tab-pane fade" id="exp-tab-polizas" role="tabpanel">
            <div class="pol-toolbar-detail mb-2">
              <div class="input-group input-group-sm">
                <input type="search" class="form-control" id="pol-search" placeholder="Buscar por codigo, numero, contratante o aseguradora">
                <div class="input-group-append">
                  <button class="btn btn-outline-secondary" type="button" id="pol-btn-buscar" title="Buscar" aria-label="Buscar"><i class="fas fa-search"></i></button>
                </div>
              </div>
              <select class="form-control form-control-sm" id="pol-filtro-aseguradora"><option value="0">Todas las aseguradoras</option></select>
              <select class="form-control form-control-sm" id="pol-filtro-estado-poliza"><option value="todos">Todos los estados</option></select>
              <select class="form-control form-control-sm" id="pol-filtro-activo">
                <option value="todos">Todos</option>
                <option value="activo">Activas</option>
                <option value="inactivo">Inactivas</option>
              </select>
              <?php if ($permExpedientes['puede_crear']): ?>
                <button class="btn btn-outline-primary btn-sm" type="button" id="pol-btn-analizar"><i class="fas fa-search"></i> Analizar PDF</button>
                <button class="btn btn-primary btn-sm" type="button" id="pol-btn-nuevo"><i class="fas fa-plus"></i> Registrar poliza</button>
              <?php endif; ?>
            </div>
            <div class="text-muted small mb-2" id="pol-counter">0 registros</div>
            <div class="table-responsive">
              <table class="table table-bordered table-sm mb-0 exp-pol-table">
                <thead>
                  <tr>
                    <th>Codigo</th>
                    <th>Documento</th>
                    <th>Aseguradora</th>
                    <th>Vigencia</th>
                    <th>Suma asegurada</th>
                    <th>Prima total</th>
                    <th>Estado</th>
                    <th>Activo</th>
                    <th>PDF</th>
                    <th class="text-center" style="width:96px;">Acciones</th>
                  </tr>
                </thead>
                <tbody id="pol-body"></tbody>
              </table>
            </div>
            <div class="exp-empty mt-2" id="pol-empty">No hay polizas registradas para este expediente.</div>
            <div class="exp-pagination" id="pol-pagination"></div>
          </div>
          <div class="tab-pane fade" id="exp-tab-actividad" role="tabpanel">
            <div id="exp-actividad-body"></div>
            <div class="exp-empty" id="exp-actividad-empty">No hay actividad registrada.</div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalReqEstadoExp" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form class="modal-content" id="formReqEstadoExp" autocomplete="off" novalidate>
      <div class="modal-header">
        <h5 class="modal-title">Cambiar estado de requisito</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id">
        <input type="hidden" name="expediente_id">
        <div class="form-group">
          <label>Requisito</label>
          <input class="form-control" id="req-estado-nombre" readonly>
        </div>
        <div class="form-group">
          <label>Estado</label>
          <select class="form-control" name="estado_requisito" required></select>
        </div>
        <div class="form-group mb-0">
          <label>Observacion o motivo</label>
          <textarea class="form-control" name="observacion_actual" rows="3" maxlength="1000"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="modalReqDocExp" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form class="modal-content" id="formReqDocExp" enctype="multipart/form-data" autocomplete="off" novalidate>
      <div class="modal-header">
        <h5 class="modal-title">Cargar respuesta documental</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id">
        <input type="hidden" name="expediente_id">
        <div class="form-group">
          <label>Requisito</label>
          <input class="form-control" id="req-doc-nombre" readonly>
        </div>
        <div class="form-group mb-0">
          <label>Archivo(s)</label>
          <input class="form-control" name="archivo" type="file" multiple required>
          <small class="text-muted">Se usaran las extensiones y validaciones permitidas por almacen del sistema.</small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Cargar</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="modalCotizacionExp" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-xl cot-modal-dialog" role="document">
    <form class="modal-content" id="formCotizacionExp" autocomplete="off" novalidate>
      <div class="modal-header">
        <h5 class="modal-title" id="modalCotizacionExpTitle">Cotizacion</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id">
        <input type="hidden" name="expediente_id">
        <input type="hidden" name="riesgos_json">
        <input type="hidden" name="alternativas_json">
        <input type="hidden" name="comparativos_json">

        <ul class="nav nav-tabs mb-3" role="tablist">
          <li class="nav-item"><a class="nav-link active" data-toggle="pill" href="#cot-form-general" role="tab">General</a></li>
          <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#cot-form-riesgo" role="tab">Riesgo</a></li>
          <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#cot-form-alternativas" role="tab">Alternativas</a></li>
          <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#cot-form-comparativo" role="tab">Comparativo</a></li>
          <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#cot-form-preview" role="tab">Vista previa</a></li>
        </ul>

        <div class="tab-content">
          <div class="tab-pane fade show active" id="cot-form-general" role="tabpanel">
            <div class="row">
              <div class="col-lg-5">
                <div class="card card-light">
                  <div class="card-header py-2"><strong>Datos del expediente</strong></div>
                  <div class="card-body py-2" id="cot-exp-info"></div>
                </div>
              </div>
              <div class="col-lg-7">
                <div class="form-row">
                  <div class="form-group col-md-6">
                    <label>Fecha cotizacion</label>
                    <input class="form-control" name="fecha_cotizacion" type="date" required>
                  </div>
                  <div class="form-group col-md-6">
                    <label>Fecha vencimiento</label>
                    <input class="form-control" name="fecha_vencimiento" type="date" required>
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-group col-md-8">
                    <label>Titulo</label>
                    <input class="form-control" name="titulo" maxlength="180">
                  </div>
                  <div class="form-group col-md-4">
                    <label>Estado cotizacion</label>
                    <select class="form-control" name="estado_cotizacion" required></select>
                  </div>
                </div>
                <div class="form-group">
                  <label>Descripcion u objeto</label>
                  <textarea class="form-control" name="descripcion" rows="2" maxlength="1000"></textarea>
                </div>
                <div class="form-row">
                  <div class="form-group col-md-8">
                    <label>Observaciones</label>
                    <textarea class="form-control" name="observaciones" rows="2" maxlength="3000"></textarea>
                  </div>
                  <div class="form-group col-md-4">
                    <label>Activo</label>
                    <select class="form-control" name="estado">
                      <option value="1">Activo</option>
                      <option value="0">Inactivo</option>
                    </select>
                  </div>
                </div>
                <div class="form-group mb-0">
                  <label>Nota PDF</label>
                  <textarea class="form-control" name="nota_pdf" rows="2" maxlength="1000"></textarea>
                </div>
              </div>
            </div>
          </div>

          <div class="tab-pane fade" id="cot-form-riesgo" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h6 class="mb-0">Datos del riesgo</h6>
              <button class="btn btn-outline-primary btn-sm" type="button" id="cot-add-riesgo"><i class="fas fa-plus"></i> Agregar dato</button>
            </div>
            <div class="table-responsive">
              <table class="table table-bordered table-sm cot-dyn-table">
                <thead><tr><th>Etiqueta</th><th>Valor</th><th>Orden</th><th class="text-center">Acciones</th></tr></thead>
                <tbody id="cot-riesgos-body"></tbody>
              </table>
            </div>
          </div>

          <div class="tab-pane fade" id="cot-form-alternativas" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h6 class="mb-0">Alternativas y cuotas</h6>
              <button class="btn btn-outline-primary btn-sm" type="button" id="cot-add-alt"><i class="fas fa-plus"></i> Agregar alternativa</button>
            </div>
            <div id="cot-alt-body"></div>
          </div>

          <div class="tab-pane fade" id="cot-form-comparativo" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h6 class="mb-0">Comparativo</h6>
              <button class="btn btn-outline-primary btn-sm" type="button" id="cot-add-comp"><i class="fas fa-plus"></i> Agregar fila</button>
            </div>
            <div class="table-responsive">
              <table class="table table-bordered table-sm cot-dyn-table" id="cot-comp-table">
                <thead id="cot-comp-head"></thead>
                <tbody id="cot-comp-body"></tbody>
              </table>
            </div>
          </div>

          <div class="tab-pane fade" id="cot-form-preview" role="tabpanel">
            <div class="d-flex justify-content-end mb-2">
              <button class="btn btn-outline-secondary btn-sm mr-2" type="button" id="cot-refresh-preview"><i class="fas fa-sync"></i> Actualizar vista</button>
              <button class="btn btn-outline-info btn-sm mr-2" type="button" id="cot-ver-pdf"><i class="fas fa-file-pdf"></i> Ver PDF</button>
              <button class="btn btn-primary btn-sm" type="button" id="cot-descargar-pdf"><i class="fas fa-download"></i> Descargar PDF</button>
            </div>
            <div class="cot-a4-preview" id="cot-preview"></div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar cotizacion</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="modalPolizaExp" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <form class="modal-content" id="formPolizaExp" enctype="multipart/form-data" autocomplete="off" novalidate>
      <div class="modal-header">
        <h5 class="modal-title" id="modalPolizaExpTitle">Poliza</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id">
        <input type="hidden" name="expediente_id">
        <div class="form-row">
          <div class="form-group col-lg-4 col-md-6">
            <label>Aseguradora</label>
            <select class="form-control" name="aseguradora_id" required></select>
          </div>
          <div class="form-group col-lg-4 col-md-6">
            <label>Tipo de documento emitido</label>
            <select class="form-control" name="tipo_documento_emitido" required></select>
          </div>
          <div class="form-group col-lg-4 col-md-6">
            <label>Estado de poliza</label>
            <select class="form-control" name="estado_poliza" required></select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-lg-4 col-md-6">
            <label>Numero de documento</label>
            <input class="form-control" name="numero_documento" maxlength="80">
          </div>
          <div class="form-group col-lg-4 col-md-6">
            <label>Beneficiario</label>
            <input class="form-control" name="beneficiario_nombre" maxlength="180">
          </div>
          <div class="form-group col-lg-4 col-md-6">
            <label>Fecha de emision</label>
            <input class="form-control" name="fecha_emision" type="date" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-lg-4 col-md-6">
            <label>Vigencia inicio</label>
            <input class="form-control" name="vigencia_inicio" type="datetime-local" required>
          </div>
          <div class="form-group col-lg-4 col-md-6">
            <label>Vigencia fin</label>
            <input class="form-control" name="vigencia_fin" type="datetime-local" required>
          </div>
          <div class="form-group col-lg-2 col-md-3">
            <label>Moneda</label>
            <select class="form-control" name="moneda">
              <option value="PEN">PEN</option>
              <option value="USD">USD</option>
              <option value="EUR">EUR</option>
              <option value="OTRA">OTRA</option>
            </select>
          </div>
          <div class="form-group col-lg-2 col-md-3">
            <label>Activo</label>
            <select class="form-control" name="estado">
              <option value="1">Activo</option>
              <option value="0">Inactivo</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-lg-3 col-md-6">
            <label>Suma asegurada</label>
            <input class="form-control" name="suma_asegurada" type="number" step="0.01" min="0">
          </div>
          <div class="form-group col-lg-3 col-md-6">
            <label>Prima comercial</label>
            <input class="form-control" name="prima_comercial" type="number" step="0.01" min="0">
          </div>
          <div class="form-group col-lg-3 col-md-6">
            <label>IGV</label>
            <input class="form-control" name="igv" type="number" step="0.01" min="0">
          </div>
          <div class="form-group col-lg-3 col-md-6">
            <label>Prima total</label>
            <input class="form-control" name="prima_total" type="number" step="0.01" min="0">
          </div>
        </div>
        <div class="form-group">
          <label>Observaciones</label>
          <textarea class="form-control" name="observaciones" rows="3" maxlength="3000"></textarea>
        </div>
        <div class="form-group mb-0" id="pol-pdf-group">
          <label>PDF principal</label>
          <input class="form-control" name="archivo_pdf" type="file" accept="application/pdf,.pdf">
          <small class="text-muted">Borrador puede guardarse sin PDF. Emitida o vigente requiere PDF principal activo.</small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="modalPolizaAnalisisExp" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-xl pol-analysis-dialog" role="document">
    <form class="modal-content" id="formPolizaAnalisisExp" enctype="multipart/form-data" autocomplete="off" novalidate>
      <div class="modal-header">
        <h5 class="modal-title">Analizar PDF de poliza</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body p-0">
        <input type="hidden" name="expediente_id">
        <input type="hidden" name="metodo_extraccion">
        <input type="hidden" name="estado_extraccion">
        <input type="hidden" name="confianza_global">
        <input type="hidden" name="campos_extraidos_json">
        <input type="hidden" name="texto_extraido">
        <div class="pol-analysis-layout">
          <section class="pol-analysis-pdf">
            <div class="pol-analysis-toolbar">
              <input class="form-control form-control-sm" name="archivo_pdf" type="file" accept="application/pdf,.pdf" required>
              <select class="form-control form-control-sm" id="pol-ana-modo">
                <option value="auto">Auto</option>
                <option value="texto_pdf">Solo texto PDF</option>
                <option value="ocr">OCR</option>
              </select>
              <button class="btn btn-outline-primary btn-sm" type="button" id="pol-ana-extraer"><i class="fas fa-magic"></i> Extraer</button>
            </div>
            <div class="pol-analysis-status" id="pol-ana-status">Selecciona un PDF para verlo y analizarlo.</div>
            <iframe id="pol-ana-frame" class="pol-analysis-frame" title="Vista previa PDF de poliza"></iframe>
          </section>
          <section class="pol-analysis-form">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <div>
                <h6 class="mb-1">Datos para guardar</h6>
                <div class="text-muted small">Los datos extraidos son una propuesta. Revisa y corrige antes de guardar.</div>
              </div>
              <span class="badge badge-secondary" id="pol-ana-confianza">0%</span>
            </div>
            <div class="form-row">
              <div class="form-group col-md-6">
                <label>Aseguradora</label>
                <select class="form-control form-control-sm" name="aseguradora_id" required></select>
              </div>
              <div class="form-group col-md-3">
                <label>Tipo documento</label>
                <select class="form-control form-control-sm" name="tipo_documento_emitido" required></select>
              </div>
              <div class="form-group col-md-3">
                <label>Estado poliza</label>
                <select class="form-control form-control-sm" name="estado_poliza" required></select>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group col-md-4">
                <label>Numero documento</label>
                <input class="form-control form-control-sm" name="numero_documento" maxlength="80">
              </div>
              <div class="form-group col-md-4">
                <label>Beneficiario</label>
                <input class="form-control form-control-sm" name="beneficiario_nombre" maxlength="180">
              </div>
              <div class="form-group col-md-4">
                <label>Fecha emision</label>
                <input class="form-control form-control-sm" name="fecha_emision" type="date" required>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group col-md-6">
                <label>Vigencia inicio</label>
                <input class="form-control form-control-sm" name="vigencia_inicio" type="datetime-local" required>
              </div>
              <div class="form-group col-md-6">
                <label>Vigencia fin</label>
                <input class="form-control form-control-sm" name="vigencia_fin" type="datetime-local" required>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group col-md-3">
                <label>Moneda</label>
                <select class="form-control form-control-sm" name="moneda">
                  <option value="PEN">PEN</option>
                  <option value="USD">USD</option>
                  <option value="EUR">EUR</option>
                  <option value="OTRA">OTRA</option>
                </select>
              </div>
              <div class="form-group col-md-3">
                <label>Suma asegurada</label>
                <input class="form-control form-control-sm" name="suma_asegurada" type="number" step="0.01" min="0">
              </div>
              <div class="form-group col-md-3">
                <label>Prima comercial</label>
                <input class="form-control form-control-sm" name="prima_comercial" type="number" step="0.01" min="0">
              </div>
              <div class="form-group col-md-3">
                <label>Prima total</label>
                <input class="form-control form-control-sm" name="prima_total" type="number" step="0.01" min="0">
              </div>
            </div>
            <div class="form-row">
              <div class="form-group col-md-3">
                <label>IGV</label>
                <input class="form-control form-control-sm" name="igv" type="number" step="0.01" min="0">
              </div>
              <div class="form-group col-md-3">
                <label>Activo</label>
                <select class="form-control form-control-sm" name="estado">
                  <option value="1">Activo</option>
                  <option value="0">Inactivo</option>
                </select>
              </div>
              <div class="form-group col-md-6">
                <label>Contratante detectado</label>
                <input class="form-control form-control-sm" id="pol-ana-contratante" readonly>
              </div>
            </div>
            <div class="form-group">
              <label>Observaciones</label>
              <textarea class="form-control form-control-sm" name="observaciones" rows="2" maxlength="3000"></textarea>
            </div>
            <div class="form-group">
              <label>Notas de extraccion</label>
              <textarea class="form-control form-control-sm" name="observaciones_extraccion" rows="2" maxlength="1000"></textarea>
            </div>
            <div class="form-group mb-0">
              <label>Texto extraido</label>
              <textarea class="form-control form-control-sm" id="pol-ana-texto" rows="7"></textarea>
            </div>
          </section>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar poliza</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="modalPolizaPdfExp" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form class="modal-content" id="formPolizaPdfExp" enctype="multipart/form-data" autocomplete="off" novalidate>
      <div class="modal-header">
        <h5 class="modal-title">Cargar PDF principal</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id">
        <input type="hidden" name="expediente_id">
        <div class="form-group">
          <label>Poliza</label>
          <input class="form-control" id="pol-pdf-nombre" readonly>
        </div>
        <div class="form-group mb-0">
          <label>PDF</label>
          <input class="form-control" name="archivo_pdf" type="file" accept="application/pdf,.pdf" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Cargar</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="modalPolizaDetalleExp" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detalle de poliza</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body" id="pol-detalle-body"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalConfirmExpediente" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Confirmar accion</h5><button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button></div>
      <div class="modal-body" id="modalConfirmExpedienteText"></div>
      <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button><button type="button" class="btn btn-primary" id="modalConfirmExpedienteOk">Confirmar</button></div>
    </div>
  </div>
</div>

<div class="exp-toast-zone" id="exp-toast-zone"></div>

<style>
  .exp-toolbar{display:grid;gap:.5rem;grid-template-columns:minmax(220px,1fr) 190px 170px 170px 120px auto auto;align-items:center;margin-bottom:.75rem}
  .expedientes-module .table-responsive{overflow-x:auto}
  .exp-table{min-width:1180px;table-layout:auto}
  .exp-table th,.exp-table td{vertical-align:middle}
  .exp-col-codigo{width:135px}
  .exp-col-cliente{min-width:260px}
  .exp-col-tipo{min-width:230px}
  .exp-col-descripcion{min-width:260px}
  .exp-col-estado{width:170px}
  .exp-col-fecha{width:130px}
  .exp-col-activo{width:100px}
  .exp-text-clip{display:block;max-width:360px;white-space:normal;line-height:1.25}
  .exp-detail-dialog{max-width:min(1320px,calc(100vw - 2rem))}
  .exp-detail-dialog .modal-content{max-height:calc(100vh - 2rem)}
  .exp-detail-dialog .modal-body{overflow-y:auto}
  .exp-detail-content{min-height:360px}
  .exp-doc-table{min-width:1000px}
  .exp-formatos-table{min-width:860px}
  .exp-req-table{min-width:1500px}
  .exp-req-docs{margin:.35rem 0 0 0;padding-left:1rem}
  .exp-req-docs li{margin-bottom:.2rem}
  .pol-toolbar-detail{display:grid;gap:.5rem;grid-template-columns:minmax(240px,1fr) 210px 160px 120px auto;align-items:center}
  .exp-pol-table{min-width:1280px}
  .exp-pol-table th,.exp-pol-table td{vertical-align:middle}
  .pol-analysis-dialog{max-width:min(1480px,calc(100vw - 1rem))}
  .pol-analysis-dialog .modal-content{height:calc(100vh - 1rem)}
  .pol-analysis-dialog .modal-body{overflow:hidden}
  .pol-analysis-layout{display:grid;grid-template-columns:minmax(420px,52%) minmax(420px,48%);height:100%}
  .pol-analysis-pdf,.pol-analysis-form{min-width:0;min-height:0}
  .pol-analysis-pdf{display:flex;flex-direction:column;border-right:1px solid #dee2e6;background:#f8f9fa}
  .pol-analysis-toolbar{display:grid;grid-template-columns:1fr 140px auto;gap:.5rem;padding:.75rem;border-bottom:1px solid #dee2e6;background:#fff}
  .pol-analysis-status{padding:.5rem .75rem;font-size:.875rem;color:#495057;border-bottom:1px solid #dee2e6;background:#fff}
  .pol-analysis-frame{width:100%;height:100%;border:0;background:#e9ecef}
  .pol-analysis-form{overflow-y:auto;padding:1rem;background:#fff}
  .cot-toolbar-detail{display:grid;gap:.5rem;grid-template-columns:minmax(260px,1fr) 180px 130px auto;align-items:center}
  .exp-cot-table{min-width:1120px}
  .cot-modal-dialog{max-width:min(1380px,calc(100vw - 2rem))}
  .cot-modal-dialog .modal-content{max-height:calc(100vh - 2rem)}
  .cot-modal-dialog .modal-body{overflow-y:auto}
  .cot-dyn-table{min-width:900px}
  .cot-alt-card{border:1px solid #ced4da;border-radius:.25rem;padding:.75rem;margin-bottom:.75rem;background:#fff}
  .cot-alt-grid{display:grid;gap:.5rem;grid-template-columns:repeat(6,minmax(130px,1fr))}
  .cot-cuotas-table{min-width:700px}
  .cot-a4-preview{width:210mm;min-height:297mm;margin:0 auto;background:#fff;color:#212529;padding:16mm;box-shadow:0 0 0 1px #dee2e6,0 .5rem 1.5rem rgba(0,0,0,.15);font-size:12px}
  .cot-a4-preview h2{font-size:22px;margin-bottom:6px}
  .cot-a4-preview h3{font-size:15px;margin-top:14px;border-bottom:1px solid #dee2e6;padding-bottom:4px}
  .cot-a4-preview table{width:100%;border-collapse:collapse;margin-bottom:8px}
  .cot-a4-preview th,.cot-a4-preview td{border:1px solid #ced4da;padding:5px;vertical-align:top}
  .cot-a4-preview th{background:#f1f3f5}
  .exp-loading,.exp-empty{display:none;padding:1rem;border:1px dashed #ced4da;border-radius:.25rem;text-align:center;color:#6c757d;background:#f8f9fa}
  .exp-actions{display:inline-flex;flex-direction:column;gap:.25rem}
  .exp-pagination .btn{min-width:36px}
  .exp-toast-zone{position:fixed;right:1rem;bottom:1rem;z-index:1080;width:min(360px,calc(100vw - 2rem))}
  @media(max-width:575.98px){.exp-detail-dialog{max-width:calc(100vw - .5rem);margin:.25rem}.exp-detail-dialog .modal-content{max-height:calc(100vh - .5rem)}}
  @media(max-width:991.98px){.pol-analysis-dialog .modal-content{height:calc(100vh - .5rem)}.pol-analysis-layout{grid-template-columns:1fr}.pol-analysis-pdf{height:45vh;border-right:0;border-bottom:1px solid #dee2e6}.pol-analysis-toolbar{grid-template-columns:1fr}.pol-analysis-form{height:calc(55vh - 4rem)}}
  @media(max-width:1199.98px){.pol-toolbar-detail,.cot-toolbar-detail{grid-template-columns:1fr 1fr}.cot-alt-grid{grid-template-columns:1fr 1fr}.cot-a4-preview{width:100%;min-height:auto;padding:1rem}}
  @media(max-width:1199.98px){.exp-toolbar{grid-template-columns:1fr 1fr}}
  @media(max-width:767.98px){.exp-toolbar,.pol-toolbar-detail,.cot-toolbar-detail,.cot-alt-grid{grid-template-columns:1fr}}
</style>

<script src="<?php echo cb_e(cb_url('plugins/pdfmake/pdfmake.min.js')); ?>"></script>
<script src="<?php echo cb_e(cb_url('plugins/pdfmake/vfs_fonts.js')); ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var csrf = <?php echo json_encode($csrfExpedientes); ?>;
  var permisos = <?php echo json_encode($permExpedientes); ?>;
  var endpoint = 'api/expedientes/expedientes.php';
  var documentosEndpoint = 'api/expedientes/documentos.php';
  var requisitosEndpoint = 'api/expedientes/requisitos.php';
  var formatosExpEndpoint = 'api/expedientes/formatos.php';
  var polizasEndpoint = 'api/expedientes/polizas.php';
  var polAnalisisEndpoint = 'api/expedientes/poliza_analisis.php';
  var cotizacionesEndpoint = 'api/expedientes/cotizaciones.php';
  var timelineEndpoint = 'api/expedientes/timeline.php';
  var rows = [], clientes = [], tipos = [], estados = [], estadoInicial = null, docTipos = [];
  var reqEstados = [], reqRows = [];
  var polAseguradoras = [], polEstados = [], polTiposDoc = [], polRows = [], polPage = 1;
  var cotCtx = {}, cotEstados = [], cotRows = [], cotPage = 1, cotRiesgos = [], cotAlternativas = [], cotComparativos = [], cotSeq = 1;
  var expedienteDetalleId = 0;
  var page = 1, timer = null, confirmCallback = null, polAnaPdfUrl = null;

  function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[c];});}
  function toast(msg,type){var z=document.getElementById('exp-toast-zone'), t=document.createElement('div');t.className='alert alert-'+(type||'info')+' shadow-sm mb-2';t.textContent=msg;z.appendChild(t);setTimeout(function(){t.remove();},4200);}
  function sectionError(section,e,fallback){toast(section+': '+((e&&e.message)||fallback),'danger');}
  function fetchJson(url,opt){return fetch(url,opt||{}).then(function(r){return r.json().catch(function(){return{ok:false,message:'Respuesta no valida del servidor.'};}).then(function(j){if(!r.ok||!j.ok)throw j;return j;});});}
  function post(action,data){data.set('_csrf',csrf);return fetchJson(endpoint+'?accion='+encodeURIComponent(action),{method:'POST',body:data});}
  function postDocs(action,data){data.set('_csrf',csrf);return fetchJson(documentosEndpoint+'?accion='+encodeURIComponent(action),{method:'POST',body:data});}
  function badgeActivo(v){return Number(v)===1?'<span class="badge badge-success">Activo</span>':'<span class="badge badge-secondary">Inactivo</span>';}
  function badgeEstado(row){var color=row.color_etiqueta||'#6c757d';return '<span class="badge" style="background:'+esc(color)+';color:#fff">'+esc(row.estado_expediente_nombre||'-')+'</span>';}
  function badgeReqEstado(v){var m={pendiente:'secondary',entregado:'primary',observado:'warning',aprobado:'success',rechazado:'danger',no_aplica:'info'};var label=reqEstadoLabel(v);return '<span class="badge badge-'+(m[v]||'secondary')+'">'+esc(label)+'</span>';}
  function badgePolEstado(v){var m={borrador:'secondary',emitida:'primary',vigente:'success',cancelada:'warning',anulada:'danger'};return '<span class="badge badge-'+(m[v]||'secondary')+'">'+esc(polEstadoLabel(v))+'</span>';}
  function reqEstadoLabel(v){var out=v;reqEstados.forEach(function(e){if(e.codigo===v)out=e.nombre;});return out||'-';}
  function polEstadoLabel(v){var out=v;polEstados.forEach(function(e){if(e.codigo===v)out=e.nombre;});return out||'-';}
  function polTipoDocLabel(v){var out=v;polTiposDoc.forEach(function(e){if(e.codigo===v)out=e.nombre;});return out||'-';}
  function bytes(v){var n=Number(v||0);if(n<1024)return n+' B';if(n<1048576)return (n/1024).toFixed(1)+' KB';return (n/1048576).toFixed(1)+' MB';}
  function resumen(v,max){v=String(v==null?'':v);return v.length>max?v.slice(0,max-1)+'...':v;}
  function params(){var p=new URLSearchParams();p.set('accion','listar');p.set('page',page);p.set('q',document.getElementById('exp-search').value||'');p.set('cliente_id',document.getElementById('exp-filtro-cliente').value||'0');p.set('tipo_seguro_id',document.getElementById('exp-filtro-tipo').value||'0');p.set('estado_expediente_id',document.getElementById('exp-filtro-estado-exp').value||'0');p.set('estado',document.getElementById('exp-filtro-activo').value||'todos');return p;}
  function optionClientes(selected){var h='<option value="">Seleccione cliente</option>';clientes.forEach(function(c){var tipo=c.tipo_cliente==='consorcio'?'Consorcio':'Empresa';var ruc=c.ruc?c.ruc+' - ':'';h+='<option value="'+c.id+'" '+(Number(selected)===Number(c.id)?'selected':'')+'>'+esc(ruc+c.razon_social+' ('+tipo+')')+'</option>';});return h;}
  function optionTipos(selected){var h='<option value="">Seleccione tipo</option>';tipos.forEach(function(t){h+='<option value="'+t.id+'" '+(Number(selected)===Number(t.id)?'selected':'')+'>'+esc((t.ramo_nombre?t.ramo_nombre+' / ':'')+t.nombre)+'</option>';});return h;}
  function optionEstados(selected){var h='<option value="">Seleccione estado</option>';estados.forEach(function(e){h+='<option value="'+e.id+'" '+(Number(selected)===Number(e.id)?'selected':'')+'>'+esc(e.nombre)+'</option>';});return h;}
  function fillOptions(){document.getElementById('exp-filtro-cliente').innerHTML='<option value="0">Todos los clientes</option>'+optionClientes(0).replace('<option value="">Seleccione cliente</option>','');document.getElementById('exp-filtro-tipo').innerHTML='<option value="0">Todos los tipos</option>'+optionTipos(0).replace('<option value="">Seleccione tipo</option>','');document.getElementById('exp-filtro-estado-exp').innerHTML='<option value="0">Todos los estados</option>'+optionEstados(0).replace('<option value="">Seleccione estado</option>','');var f=document.getElementById('formExpediente');f.elements.cliente_id.innerHTML=optionClientes();f.elements.tipo_seguro_id.innerHTML=optionTipos();f.elements.estado_expediente_id.innerHTML=optionEstados();}
  function fillDocTipos(){var form=document.getElementById('formExpDocumento');if(!form)return;var h='<option value="">Seleccione tipo</option>';docTipos.forEach(function(t){h+='<option value="'+esc(t.codigo)+'">'+esc(t.nombre)+'</option>';});form.elements.tipo_documento.innerHTML=h;}
  function loadDocTipos(){return fetchJson(documentosEndpoint+'?accion=tipos').then(function(r){docTipos=(r.data||{}).rows||[];fillDocTipos();}).catch(function(e){toast(e.message||'No se pudo cargar tipos de documento.','danger');});}
  function fillReqEstados(){var filtro=document.getElementById('exp-req-filtro-estado');if(filtro){var h='<option value="todos">Todos</option>';reqEstados.forEach(function(e){h+='<option value="'+esc(e.codigo)+'">'+esc(e.nombre)+'</option>';});filtro.innerHTML=h;}var form=document.getElementById('formReqEstadoExp');if(form){var s='';reqEstados.forEach(function(e){s+='<option value="'+esc(e.codigo)+'">'+esc(e.nombre)+'</option>';});form.elements.estado_requisito.innerHTML=s;}}
  function loadReqEstados(){return fetchJson(requisitosEndpoint+'?accion=estados').then(function(r){reqEstados=(r.data||{}).rows||[];fillReqEstados();}).catch(function(e){toast(e.message||'No se pudo cargar estados de requisitos.','danger');});}
  function optionPolAseguradoras(selected){var h='<option value="">Seleccione aseguradora</option>';polAseguradoras.forEach(function(a){var n=a.nombre_comercial||a.razon_social;h+='<option value="'+a.id+'" '+(Number(selected)===Number(a.id)?'selected':'')+'>'+esc(n)+'</option>';});return h;}
  function optionPolEstados(selected,all){var h=all?'<option value="todos">Todos los estados</option>':'';polEstados.forEach(function(e){h+='<option value="'+esc(e.codigo)+'" '+(selected===e.codigo?'selected':'')+'>'+esc(e.nombre)+'</option>';});return h;}
  function optionPolTipos(selected){var h='<option value="">Seleccione tipo</option>';polTiposDoc.forEach(function(t){h+='<option value="'+esc(t.codigo)+'" '+(selected===t.codigo?'selected':'')+'>'+esc(t.nombre)+'</option>';});return h;}
  function fillPolContext(){document.getElementById('pol-filtro-aseguradora').innerHTML='<option value="0">Todas las aseguradoras</option>'+optionPolAseguradoras(0).replace('<option value="">Seleccione aseguradora</option>','');document.getElementById('pol-filtro-estado-poliza').innerHTML=optionPolEstados('todos',true);var f=document.getElementById('formPolizaExp');f.elements.aseguradora_id.innerHTML=optionPolAseguradoras();f.elements.estado_poliza.innerHTML=optionPolEstados('borrador',false);f.elements.tipo_documento_emitido.innerHTML=optionPolTipos('poliza');var a=document.getElementById('formPolizaAnalisisExp');if(a){a.elements.aseguradora_id.innerHTML=optionPolAseguradoras();a.elements.estado_poliza.innerHTML=optionPolEstados('borrador',false);a.elements.tipo_documento_emitido.innerHTML=optionPolTipos('poliza');}}
  function loadPolContext(){return fetchJson(polizasEndpoint+'?accion=contexto').then(function(r){var d=r.data||{};polAseguradoras=d.aseguradoras||[];polEstados=d.estados||[];polTiposDoc=d.tipos_documento||[];csrf=d.csrf||csrf;fillPolContext();}).catch(function(e){sectionError('Contexto de polizas',e,'No se pudo cargar contexto de polizas.');});}
  function cotEstadoLabel(v){var out=v;cotEstados.forEach(function(e){if(e.codigo===v)out=e.nombre;});return out||'-';}
  function badgeCotEstado(v){var m={borrador:'secondary',enviada:'primary',aceptada:'success',vencida:'warning',perdida:'danger',cancelada:'dark'};return '<span class="badge badge-'+(m[v]||'secondary')+'">'+esc(cotEstadoLabel(v))+'</span>';}
  function optionCotEstados(selected,all){var h=all?'<option value="todos">Todos los estados</option>':'';cotEstados.forEach(function(e){h+='<option value="'+esc(e.codigo)+'" '+(selected===e.codigo?'selected':'')+'>'+esc(e.nombre)+'</option>';});return h;}
  function cotAsegName(id){var out='';(cotCtx.aseguradoras||[]).forEach(function(a){if(Number(a.id)===Number(id))out=a.nombre_comercial||a.razon_social;});return out||'-';}
  function optionCotAseg(selected){var h='<option value="">Aseguradora</option>';(cotCtx.aseguradoras||[]).forEach(function(a){h+='<option value="'+a.id+'" '+(Number(selected)===Number(a.id)?'selected':'')+'>'+esc(a.nombre_comercial||a.razon_social)+'</option>';});return h;}
  function optionCotProductos(asegId,selected){var h='<option value="">Plan libre o sin producto</option>';(cotCtx.productos||[]).forEach(function(p){if(Number(p.aseguradora_id)!==Number(asegId))return;h+='<option value="'+p.id+'" '+(Number(selected)===Number(p.id)?'selected':'')+'>'+esc(p.nombre_producto+(p.nombre_plan?' / '+p.nombre_plan:''))+'</option>';});return h;}
  function optionCotGps(selected){var h='';(cotCtx.gps||[]).forEach(function(g){h+='<option value="'+esc(g.codigo)+'" '+(selected===g.codigo?'selected':'')+'>'+esc(g.nombre)+'</option>';});return h;}
  function optionCotModalidades(selected){var h='';(cotCtx.modalidades||[]).forEach(function(m){h+='<option value="'+esc(m.codigo)+'" '+(selected===m.codigo?'selected':'')+'>'+esc(m.nombre)+'</option>';});return h;}
  function optionCotSecciones(selected){var h='';(cotCtx.secciones||[]).forEach(function(s){h+='<option value="'+esc(s.codigo)+'" '+(selected===s.codigo?'selected':'')+'>'+esc(s.nombre)+'</option>';});return h;}
  function loadCotContext(id){return fetchJson(cotizacionesEndpoint+'?accion=contexto&expediente_id='+encodeURIComponent(id)).then(function(r){cotCtx=r.data||{};cotEstados=cotCtx.estados||[];csrf=cotCtx.csrf||csrf;document.getElementById('cot-filtro-estado').innerHTML=optionCotEstados('todos',true);document.getElementById('formCotizacionExp').elements.estado_cotizacion.innerHTML=optionCotEstados('borrador',false);renderCotExpInfo();}).catch(function(e){sectionError('Contexto de cotizaciones',e,'No se pudo cargar contexto de cotizaciones.');});}
  function renderCotExpInfo(){var x=cotCtx.expediente||{};document.getElementById('cot-exp-info').innerHTML='<dl class="row mb-0"><dt class="col-sm-4">Cliente</dt><dd class="col-sm-8">'+esc(x.cliente_razon_social||'-')+'<div class="text-muted small">'+esc(x.cliente_ruc||'Sin RUC')+'</div></dd><dt class="col-sm-4">Contacto</dt><dd class="col-sm-8">'+esc(x.contacto_nombre||'-')+'<div class="text-muted small">'+esc(x.contacto_correo||x.correo_principal||'-')+'</div></dd><dt class="col-sm-4">Tipo</dt><dd class="col-sm-8">'+esc(x.tipo_seguro_nombre||'-')+'</dd><dt class="col-sm-4">Ramo</dt><dd class="col-sm-8">'+esc(x.ramo_nombre||'-')+'</dd></dl>';}
  function cotParams(){var p=new URLSearchParams();p.set('accion','listar');p.set('expediente_id',expedienteDetalleId);p.set('page',cotPage);p.set('q',document.getElementById('cot-search').value||'');p.set('estado_cotizacion',document.getElementById('cot-filtro-estado').value||'todos');p.set('estado',document.getElementById('cot-filtro-activo').value||'todos');return p;}
  function loadCotizaciones(id){if(id)expedienteDetalleId=id;document.getElementById('cot-body').innerHTML='<tr><td colspan="8" class="text-muted text-center">Cargando cotizaciones...</td></tr>';document.getElementById('cot-empty').style.display='none';fetchJson(cotizacionesEndpoint+'?'+cotParams().toString()).then(function(r){var d=r.data||{};cotRows=d.rows||[];renderCotizaciones(cotRows);renderCotPagination(d.pagination||{});}).catch(function(e){document.getElementById('cot-body').innerHTML='';document.getElementById('cot-empty').style.display='block';sectionError('Cotizaciones',e,'No se pudieron cargar cotizaciones.');});}
  function renderCotizaciones(items){var h='';items.forEach(function(c){var titulo=c.titulo||c.descripcion||'-';var fechas=esc((c.fecha_cotizacion||'-')+' / '+(c.fecha_vencimiento||'-'));var aceptada=c.aseguradora_aceptada_nombre||c.aseguradora_aceptada_razon_social||'-';h+='<tr><td><strong>'+esc(c.codigo)+'</strong></td><td><span class="exp-text-clip" title="'+esc(titulo)+'">'+esc(resumen(titulo,80))+'</span></td><td>'+fechas+'</td><td>'+badgeCotEstado(c.estado_cotizacion)+'</td><td>'+esc(c.total_alternativas||0)+'</td><td><span class="exp-text-clip" title="'+esc(aceptada)+'">'+esc(resumen(aceptada,60))+'</span></td><td>'+badgeActivo(c.estado)+'</td><td class="text-center">'+cotActions(c)+'</td></tr>';});document.getElementById('cot-body').innerHTML=h;document.getElementById('cot-empty').style.display=items.length?'none':'block';}
  function cotActions(c){var h='<span class="exp-actions"><button type="button" class="btn btn-xs btn-outline-info" data-cot-action="view" data-id="'+c.id+'" title="Ver / PDF" aria-label="Ver cotizacion"><i class="fas fa-eye"></i></button>';if(permisos.puede_editar)h+='<button type="button" class="btn btn-xs btn-outline-primary" data-cot-action="edit" data-id="'+c.id+'" title="Editar" aria-label="Editar cotizacion"><i class="fas fa-edit"></i></button>';if(permisos.puede_eliminar){var title=Number(c.estado)===1?'Desactivar':'Activar';h+='<button type="button" class="btn btn-xs btn-outline-secondary" data-cot-action="toggle" data-id="'+c.id+'" data-state="'+c.estado+'" title="'+title+'" aria-label="'+title+'"><i class="fas '+(Number(c.estado)===1?'fa-toggle-on':'fa-toggle-off')+'"></i></button>';}return h+'</span>';}
  function renderCotPagination(pag){var total=Number(pag.total||0),cur=Number(pag.page||1),last=Number(pag.last_page||1),wrap=document.getElementById('cot-pagination');document.getElementById('cot-counter').textContent=total+(total===1?' registro':' registros');if(last<=1){wrap.innerHTML='';return;}wrap.innerHTML='<div class="btn-group btn-group-sm"><button type="button" class="btn btn-outline-secondary" data-cot-page="'+(cur-1)+'" '+(cur<=1?'disabled':'')+' title="Anterior" aria-label="Anterior"><i class="fas fa-chevron-left"></i></button><button type="button" class="btn btn-outline-secondary" disabled>'+cur+' / '+last+'</button><button type="button" class="btn btn-outline-secondary" data-cot-page="'+(cur+1)+'" '+(cur>=last?'disabled':'')+' title="Siguiente" aria-label="Siguiente"><i class="fas fa-chevron-right"></i></button></div>';}
  function cotDefaultDue(){var d=new Date();d.setDate(d.getDate()+15);return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');}
  function cotResetArrays(){cotRiesgos=[];cotAlternativas=[];cotComparativos=[];cotSeq=1;}
  function cotNewUid(){return 'a'+(cotSeq++);}
  function cotProductName(productId){var out='';(cotCtx.productos||[]).forEach(function(p){if(Number(p.id)===Number(productId))out=p.nombre_producto+(p.nombre_plan?' / '+p.nombre_plan:'');});return out;}
  function cotLoadRecordToForm(rec){var f=document.getElementById('formCotizacionExp');f.reset();clearValidation(f);f.dataset.codigo=rec.codigo||'';f.elements.id.value=rec.id||'';f.elements.expediente_id.value=expedienteDetalleId;f.elements.fecha_cotizacion.value=rec.fecha_cotizacion||todayDate();f.elements.fecha_vencimiento.value=rec.fecha_vencimiento||cotDefaultDue();f.elements.titulo.value=rec.titulo||'';f.elements.estado_cotizacion.value=rec.estado_cotizacion||'borrador';f.elements.descripcion.value=rec.descripcion||'';f.elements.observaciones.value=rec.observaciones||'';f.elements.nota_pdf.value=rec.nota_pdf||'';f.elements.estado.value=rec.estado==null?'1':String(rec.estado);cotRiesgos=(rec.riesgos||[]).map(function(r){return{etiqueta:r.etiqueta||'',valor:r.valor||'',orden_visual:r.orden_visual||0};});cotAlternativas=(rec.alternativas||[]).map(function(a){return{id:a.id||0,uid:cotNewUid(),aseguradora_id:a.aseguradora_id||'',producto_id:a.producto_id||'',nombre_plan_snapshot:a.nombre_plan_snapshot||'',orden_visual:a.orden_visual||0,vigencia_meses:a.vigencia_meses||'',vigencia_texto:a.vigencia_texto||'',suma_asegurada:a.suma_asegurada||'',moneda:a.moneda||'PEN',prima_comercial:a.prima_comercial||'',igv:a.igv||'',prima_total:a.prima_total||'',condicion_gps:a.condicion_gps||'no_requiere',es_aceptada:Number(a.es_aceptada||0),observaciones:a.observaciones||'',cuotas:(a.cuotas||[]).map(function(c){return{modalidad:c.modalidad||'contado',cantidad_cuotas:c.cantidad_cuotas||1,valor_cuota:c.valor_cuota||'',descripcion:c.descripcion||'',orden_visual:c.orden_visual||0};})};});cotComparativos=(rec.comparativos||[]).map(function(c){var valores={}, src=c.valores||{};Object.keys(src).forEach(function(k){var dbId=String(k).replace('db_','');var alt=cotAlternativas.find(function(a){return Number(a.id)===Number(dbId);});if(alt)valores[alt.uid]=src[k]||'';});return{seccion:c.seccion||'cobertura',etiqueta:c.etiqueta||'',detalle:c.detalle||'',orden_visual:c.orden_visual||0,valores:valores};});renderCotRiesgos();renderCotAlternativas();renderCotComparativos();renderCotPreview();}
  function openCotModal(id,previewOnly){document.getElementById('modalCotizacionExpTitle').textContent=id?'Cotizacion':'Registrar cotizacion';cotResetArrays();if(!id){cotLoadRecordToForm({fecha_cotizacion:todayDate(),fecha_vencimiento:cotDefaultDue(),estado_cotizacion:'borrador',estado:1,riesgos:[{etiqueta:'',valor:'',orden_visual:1}],alternativas:[],comparativos:[]});$('#modalCotizacionExp').modal('show');return;}fetchJson(cotizacionesEndpoint+'?accion=obtener&expediente_id='+encodeURIComponent(expedienteDetalleId)+'&id='+encodeURIComponent(id)).then(function(r){cotLoadRecordToForm((r.data||{}).record||{});$('#modalCotizacionExp').modal('show');if(previewOnly){$('#modalCotizacionExp a[href="#cot-form-preview"]').tab('show');renderCotPreview();}}).catch(function(e){toast(e.message||'No se pudo cargar la cotizacion.','danger');});}
  function renderCotRiesgos(){var h='';cotRiesgos.forEach(function(r,i){h+='<tr class="cot-riesgo-row"><td><input class="form-control form-control-sm" data-field="etiqueta" value="'+esc(r.etiqueta)+'" maxlength="120"></td><td><input class="form-control form-control-sm" data-field="valor" value="'+esc(r.valor)+'" maxlength="500"></td><td><input class="form-control form-control-sm" data-field="orden_visual" type="number" min="0" value="'+esc(r.orden_visual||i+1)+'"></td><td class="text-center"><button class="btn btn-xs btn-outline-danger" type="button" data-cot-remove-riesgo="'+i+'" title="Quitar" aria-label="Quitar"><i class="fas fa-trash"></i></button></td></tr>';});document.getElementById('cot-riesgos-body').innerHTML=h||'<tr><td colspan="4" class="text-muted text-center">Agrega datos del riesgo.</td></tr>';}
  function renderCotAlternativas(){var h='';cotAlternativas.forEach(function(a,i){if(!a.uid)a.uid=cotNewUid();h+='<div class="card cot-alt-card" data-uid="'+esc(a.uid)+'"><div class="card-header py-2 d-flex justify-content-between align-items-center"><strong>Alternativa '+(i+1)+'</strong><button class="btn btn-xs btn-outline-danger" type="button" data-cot-remove-alt="'+esc(a.uid)+'" title="Quitar alternativa" aria-label="Quitar alternativa"><i class="fas fa-trash"></i></button></div><div class="card-body"><div class="cot-alt-grid"><div><label>Aseguradora</label><select class="form-control form-control-sm cot-alt-aseg">'+optionCotAseg(a.aseguradora_id)+'</select></div><div><label>Producto / plan</label><select class="form-control form-control-sm cot-alt-producto">'+optionCotProductos(a.aseguradora_id,a.producto_id)+'</select></div><div><label>Plan libre</label><input class="form-control form-control-sm cot-alt-plan" value="'+esc(a.nombre_plan_snapshot||'')+'" maxlength="180"></div><div><label>Orden</label><input class="form-control form-control-sm cot-alt-orden" type="number" min="0" value="'+esc(a.orden_visual||i+1)+'"></div><div><label>Vigencia meses</label><input class="form-control form-control-sm cot-alt-vig-meses" type="number" min="0" value="'+esc(a.vigencia_meses||'')+'"></div><div><label>Vigencia texto</label><input class="form-control form-control-sm cot-alt-vig-texto" value="'+esc(a.vigencia_texto||'')+'" maxlength="120"></div><div><label>Suma asegurada</label><input class="form-control form-control-sm cot-alt-suma" type="number" min="0" step="0.01" value="'+esc(a.suma_asegurada||'')+'"></div><div><label>Moneda</label><input class="form-control form-control-sm cot-alt-moneda" value="'+esc(a.moneda||'PEN')+'" maxlength="10"></div><div><label>Prima comercial</label><input class="form-control form-control-sm cot-alt-prima" type="number" min="0" step="0.01" value="'+esc(a.prima_comercial||'')+'"></div><div><label>IGV</label><input class="form-control form-control-sm cot-alt-igv" type="number" min="0" step="0.01" value="'+esc(a.igv||'')+'"></div><div><label>Prima total</label><input class="form-control form-control-sm cot-alt-total" type="number" min="0" step="0.01" value="'+esc(a.prima_total||'')+'"></div><div><label>GPS</label><select class="form-control form-control-sm cot-alt-gps">'+optionCotGps(a.condicion_gps||'no_requiere')+'</select></div></div><div class="form-row mt-2"><div class="form-group col-md-9"><label>Observaciones</label><input class="form-control form-control-sm cot-alt-obs" value="'+esc(a.observaciones||'')+'" maxlength="1000"></div><div class="form-group col-md-3 d-flex align-items-end"><div class="custom-control custom-checkbox"><input class="custom-control-input cot-alt-aceptada" type="checkbox" id="cot-aceptada-'+esc(a.uid)+'" '+(Number(a.es_aceptada)===1?'checked':'')+'><label class="custom-control-label" for="cot-aceptada-'+esc(a.uid)+'">Aceptada</label></div></div></div><div class="d-flex justify-content-between align-items-center mb-1"><strong>Opciones de pago</strong><button class="btn btn-xs btn-outline-primary" type="button" data-cot-add-cuota="'+esc(a.uid)+'"><i class="fas fa-plus"></i> Cuota</button></div><div class="table-responsive"><table class="table table-bordered table-sm cot-cuotas-table"><thead><tr><th>Modalidad</th><th>Cantidad</th><th>Valor</th><th>Descripcion</th><th>Orden</th><th></th></tr></thead><tbody>'+renderCotCuotas(a)+'</tbody></table></div></div></div>';});document.getElementById('cot-alt-body').innerHTML=h||'<div class="text-muted text-center py-3">Agrega alternativas de aseguradoras para la cotizacion.</div>';renderCotComparativos();}
  function renderCotCuotas(a){var h='';(a.cuotas||[]).forEach(function(c,i){h+='<tr class="cot-cuota-row"><td><select class="form-control form-control-sm cot-cuota-modalidad">'+optionCotModalidades(c.modalidad||'contado')+'</select></td><td><input class="form-control form-control-sm cot-cuota-cantidad" type="number" min="0" value="'+esc(c.cantidad_cuotas||1)+'"></td><td><input class="form-control form-control-sm cot-cuota-valor" type="number" min="0" step="0.01" value="'+esc(c.valor_cuota||'')+'"></td><td><input class="form-control form-control-sm cot-cuota-desc" value="'+esc(c.descripcion||'')+'" maxlength="255"></td><td><input class="form-control form-control-sm cot-cuota-orden" type="number" min="0" value="'+esc(c.orden_visual||i+1)+'"></td><td class="text-center"><button class="btn btn-xs btn-outline-danger" type="button" data-cot-remove-cuota="'+i+'"><i class="fas fa-trash"></i></button></td></tr>';});return h||'<tr><td colspan="6" class="text-muted text-center">Sin cuotas registradas.</td></tr>';}
  function renderCotComparativos(){var alts=cotReadAlternativas(false);var head='<tr><th>Seccion</th><th>Etiqueta</th><th>Detalle</th><th>Orden</th>';alts.forEach(function(a,i){head+='<th>'+esc(cotAsegName(a.aseguradora_id))+'<div class="text-muted small">'+esc(a.nombre_plan_snapshot||cotProductName(a.producto_id)||'Alternativa '+(i+1))+'</div></th>';});head+='<th class="text-center">Acciones</th></tr>';var body='';cotComparativos.forEach(function(c,i){body+='<tr class="cot-comp-row"><td><select class="form-control form-control-sm cot-comp-seccion">'+optionCotSecciones(c.seccion||'cobertura')+'</select></td><td><input class="form-control form-control-sm cot-comp-etiqueta" value="'+esc(c.etiqueta||'')+'" maxlength="180"></td><td><input class="form-control form-control-sm cot-comp-detalle" value="'+esc(c.detalle||'')+'" maxlength="500"></td><td><input class="form-control form-control-sm cot-comp-orden" type="number" min="0" value="'+esc(c.orden_visual||i+1)+'"></td>';alts.forEach(function(a){body+='<td><input class="form-control form-control-sm cot-comp-valor" data-alt-uid="'+esc(a.uid)+'" value="'+esc((c.valores||{})[a.uid]||'')+'" maxlength="500"></td>';});body+='<td class="text-center"><button class="btn btn-xs btn-outline-danger" type="button" data-cot-remove-comp="'+i+'"><i class="fas fa-trash"></i></button></td></tr>';});document.getElementById('cot-comp-head').innerHTML=head;document.getElementById('cot-comp-body').innerHTML=body||'<tr><td colspan="'+(5+alts.length)+'" class="text-muted text-center">Agrega filas comparativas.</td></tr>';}
  function cotReadRiesgos(){var out=[];Array.prototype.forEach.call(document.querySelectorAll('#cot-riesgos-body .cot-riesgo-row'),function(tr,i){out.push({etiqueta:tr.querySelector('[data-field="etiqueta"]').value.trim(),valor:tr.querySelector('[data-field="valor"]').value.trim(),orden_visual:tr.querySelector('[data-field="orden_visual"]').value||i+1});});cotRiesgos=out;return out;}
  function cotReadAlternativas(sync){var out=[];Array.prototype.forEach.call(document.querySelectorAll('#cot-alt-body .cot-alt-card'),function(card,i){var uid=card.dataset.uid||cotNewUid();var cuotas=[];Array.prototype.forEach.call(card.querySelectorAll('.cot-cuota-row'),function(tr,j){cuotas.push({modalidad:tr.querySelector('.cot-cuota-modalidad').value,cantidad_cuotas:tr.querySelector('.cot-cuota-cantidad').value||0,valor_cuota:tr.querySelector('.cot-cuota-valor').value,descripcion:tr.querySelector('.cot-cuota-desc').value.trim(),orden_visual:tr.querySelector('.cot-cuota-orden').value||j+1});});out.push({uid:uid,aseguradora_id:card.querySelector('.cot-alt-aseg').value,producto_id:card.querySelector('.cot-alt-producto').value,nombre_plan_snapshot:card.querySelector('.cot-alt-plan').value.trim(),orden_visual:card.querySelector('.cot-alt-orden').value||i+1,vigencia_meses:card.querySelector('.cot-alt-vig-meses').value,vigencia_texto:card.querySelector('.cot-alt-vig-texto').value.trim(),suma_asegurada:card.querySelector('.cot-alt-suma').value,moneda:card.querySelector('.cot-alt-moneda').value.trim()||'PEN',prima_comercial:card.querySelector('.cot-alt-prima').value,igv:card.querySelector('.cot-alt-igv').value,prima_total:card.querySelector('.cot-alt-total').value,condicion_gps:card.querySelector('.cot-alt-gps').value,es_aceptada:card.querySelector('.cot-alt-aceptada').checked?1:0,observaciones:card.querySelector('.cot-alt-obs').value.trim(),cuotas:cuotas});});if(sync!==false)cotAlternativas=out;return out;}
  function cotReadComparativos(){var alts=cotReadAlternativas(false), out=[];Array.prototype.forEach.call(document.querySelectorAll('#cot-comp-body .cot-comp-row'),function(tr,i){var vals={};alts.forEach(function(a){var input=tr.querySelector('.cot-comp-valor[data-alt-uid="'+a.uid+'"]');vals[a.uid]=input?input.value.trim():'';});out.push({seccion:tr.querySelector('.cot-comp-seccion').value,etiqueta:tr.querySelector('.cot-comp-etiqueta').value.trim(),detalle:tr.querySelector('.cot-comp-detalle').value.trim(),orden_visual:tr.querySelector('.cot-comp-orden').value||i+1,valores:vals});});cotComparativos=out;return out;}
  function cotCurrentPayload(){var f=document.getElementById('formCotizacionExp');var riesgos=cotReadRiesgos(), alts=cotReadAlternativas(), comps=cotReadComparativos();f.elements.riesgos_json.value=JSON.stringify(riesgos);f.elements.alternativas_json.value=JSON.stringify(alts);f.elements.comparativos_json.value=JSON.stringify(comps);return{form:f,riesgos:riesgos,alternativas:alts,comparativos:comps,expediente:cotCtx.expediente||{},codigo:f.dataset.codigo||'BORRADOR',fecha_cotizacion:f.elements.fecha_cotizacion.value,fecha_vencimiento:f.elements.fecha_vencimiento.value,titulo:f.elements.titulo.value,estado_cotizacion:f.elements.estado_cotizacion.value,descripcion:f.elements.descripcion.value,observaciones:f.elements.observaciones.value,nota_pdf:f.elements.nota_pdf.value};}
  function saveCot(form){if(!form.checkValidity()){form.classList.add('was-validated');return;}clearValidation(form);cotCurrentPayload();var creating=!form.elements.id.value;var data=new FormData(form);if(creating)data.delete('id');data.set('expediente_id',expedienteDetalleId);data.set('_csrf',csrf);fetchJson(cotizacionesEndpoint+'?accion='+(creating?'crear':'actualizar'),{method:'POST',body:data}).then(function(r){$('#modalCotizacionExp').modal('hide');toast(r.message||'Cotizacion guardada.','success');loadCotizaciones(expedienteDetalleId);loadActividad(expedienteDetalleId);}).catch(function(e){applyErrors(form,e.errors||{});toast(e.message||'No se pudo guardar la cotizacion.','danger');});}
  function cotMoney(v,mon){return (mon||'')+' '+(v===''||v==null?'-':v);}
  function renderCotPreview(){var d=cotCurrentPayload(), x=d.expediente;var h='<div class="d-flex justify-content-between align-items-start mb-3"><div><h4 class="mb-1">Broker Seguros</h4><div class="text-muted">Cotizacion '+esc(d.codigo||'BORRADOR')+'</div></div><div class="text-right small"><div>Fecha: '+esc(d.fecha_cotizacion||'-')+'</div><div>Vence: '+esc(d.fecha_vencimiento||'-')+'</div><div>Estado: '+esc(cotEstadoLabel(d.estado_cotizacion))+'</div></div></div><h5>'+esc(d.titulo||'Cotizacion de seguro')+'</h5><table class="table table-sm table-bordered"><tbody><tr><th>Cliente</th><td>'+esc(x.cliente_razon_social||'-')+'</td><th>RUC</th><td>'+esc(x.cliente_ruc||'Sin RUC')+'</td></tr><tr><th>Contacto</th><td>'+esc(x.contacto_nombre||'-')+'</td><th>Tipo seguro</th><td>'+esc(x.tipo_seguro_nombre||'-')+'</td></tr></tbody></table>';
    if(d.riesgos.length){h+='<h6>Datos del riesgo</h6><table class="table table-sm table-bordered"><tbody>';d.riesgos.forEach(function(r){if(r.etiqueta||r.valor)h+='<tr><th>'+esc(r.etiqueta)+'</th><td>'+esc(r.valor)+'</td></tr>';});h+='</tbody></table>';}
    if(d.alternativas.length){h+='<h6>Alternativas</h6><table class="table table-sm table-bordered"><thead><tr><th>Aseguradora</th><th>Plan</th><th>Vigencia</th><th>Suma asegurada</th><th>Prima total</th><th>GPS</th></tr></thead><tbody>';d.alternativas.forEach(function(a){h+='<tr><td>'+esc(cotAsegName(a.aseguradora_id))+'</td><td>'+esc(a.nombre_plan_snapshot||cotProductName(a.producto_id)||'-')+'</td><td>'+esc(a.vigencia_texto||((a.vigencia_meses||'-')+' meses'))+'</td><td>'+esc(cotMoney(a.suma_asegurada,a.moneda))+'</td><td>'+esc(cotMoney(a.prima_total,a.moneda))+'</td><td>'+esc(a.condicion_gps||'-')+'</td></tr>';});h+='</tbody></table>';}
    if(d.alternativas.some(function(a){return a.cuotas&&a.cuotas.length;})){h+='<h6>Alternativas de pago</h6><table class="table table-sm table-bordered"><thead><tr><th>Alternativa</th><th>Modalidad</th><th>Cantidad</th><th>Valor</th><th>Descripcion</th></tr></thead><tbody>';d.alternativas.forEach(function(a,i){(a.cuotas||[]).forEach(function(c){h+='<tr><td>'+esc(cotAsegName(a.aseguradora_id)||('Alternativa '+(i+1)))+'</td><td>'+esc(c.modalidad)+'</td><td>'+esc(c.cantidad_cuotas)+'</td><td>'+esc(cotMoney(c.valor_cuota,a.moneda))+'</td><td>'+esc(c.descripcion||'-')+'</td></tr>';});});h+='</tbody></table>';}
    ['cobertura','servicio','deducible','condicion','otro'].forEach(function(sec){var rows=d.comparativos.filter(function(c){return c.seccion===sec;});if(!rows.length)return;h+='<h6>'+esc(cotEstadoLabel(sec)||sec)+'</h6><div class="table-responsive"><table class="table table-sm table-bordered"><thead><tr><th>Concepto</th>';d.alternativas.forEach(function(a){h+='<th>'+esc(cotAsegName(a.aseguradora_id))+'</th>';});h+='</tr></thead><tbody>';rows.forEach(function(c){h+='<tr><td><strong>'+esc(c.etiqueta)+'</strong><div class="text-muted small">'+esc(c.detalle||'')+'</div></td>';d.alternativas.forEach(function(a){h+='<td>'+esc((c.valores||{})[a.uid]||'-')+'</td>';});h+='</tr>';});h+='</tbody></table></div>';});
    h+='<p><strong>Observaciones:</strong> '+esc(d.observaciones||'-')+'</p><p class="text-muted small">'+esc(d.nota_pdf||'Valores y condiciones sujetos a validacion y emision de la aseguradora.')+'</p>';document.getElementById('cot-preview').innerHTML=h;}
  function cotPdfDefinition(){var d=cotCurrentPayload(), x=d.expediente;var content=[{text:'Broker Seguros',style:'brand'},{text:'Cotizacion '+(d.codigo||'BORRADOR'),style:'title'},{columns:[{text:'Fecha: '+(d.fecha_cotizacion||'-')},{text:'Vence: '+(d.fecha_vencimiento||'-'),alignment:'right'}],margin:[0,0,0,8]},{table:{widths:['25%','25%','25%','25%'],body:[['Cliente',x.cliente_razon_social||'-','RUC',x.cliente_ruc||'Sin RUC'],['Contacto',x.contacto_nombre||'-','Tipo seguro',x.tipo_seguro_nombre||'-']]},layout:'lightHorizontalLines',margin:[0,0,0,10]}];
    if(d.descripcion)content.push({text:d.descripcion,margin:[0,0,0,8]});
    if(d.riesgos.length)content.push({text:'Datos del riesgo',style:'section'},{table:{widths:['35%','65%'],body:[['Etiqueta','Valor']].concat(d.riesgos.filter(function(r){return r.etiqueta||r.valor;}).map(function(r){return[r.etiqueta,r.valor];}))},margin:[0,0,0,10]});
    if(d.alternativas.length)content.push({text:'Alternativas',style:'section'},{table:{widths:['18%','22%','15%','15%','15%','15%'],body:[['Aseguradora','Plan','Vigencia','Suma asegurada','Prima total','GPS']].concat(d.alternativas.map(function(a){return[cotAsegName(a.aseguradora_id),a.nombre_plan_snapshot||cotProductName(a.producto_id)||'-',a.vigencia_texto||((a.vigencia_meses||'-')+' meses'),cotMoney(a.suma_asegurada,a.moneda),cotMoney(a.prima_total,a.moneda),a.condicion_gps||'-'];}))},fontSize:8,margin:[0,0,0,10]});
    var cuotas=[];d.alternativas.forEach(function(a,i){(a.cuotas||[]).forEach(function(c){cuotas.push([cotAsegName(a.aseguradora_id)||('Alternativa '+(i+1)),c.modalidad,c.cantidad_cuotas,cotMoney(c.valor_cuota,a.moneda),c.descripcion||'-']);});});if(cuotas.length)content.push({text:'Alternativas de pago',style:'section'},{table:{widths:['25%','18%','12%','18%','27%'],body:[['Alternativa','Modalidad','Cantidad','Valor','Descripcion']].concat(cuotas)},fontSize:8,margin:[0,0,0,10]});
    ['cobertura','servicio','deducible','condicion','otro'].forEach(function(sec){var rows=d.comparativos.filter(function(c){return c.seccion===sec;});if(!rows.length)return;var head=['Concepto'].concat(d.alternativas.map(function(a){return cotAsegName(a.aseguradora_id);}));var body=[head].concat(rows.map(function(c){return[c.etiqueta+(c.detalle?' - '+c.detalle:'')].concat(d.alternativas.map(function(a){return(c.valores||{})[a.uid]||'-';}));}));content.push({text:sec.charAt(0).toUpperCase()+sec.slice(1),style:'section'},{table:{body:body},fontSize:8,margin:[0,0,0,10]});});
    content.push({text:'Observaciones',style:'section'},{text:d.observaciones||'-',margin:[0,0,0,8]},{text:d.nota_pdf||'Valores y condiciones sujetos a validacion y emision de la aseguradora.',italics:true,fontSize:8});return{pageSize:'A4',pageMargins:[36,36,36,42],content:content,footer:function(current,total){return{text:'Pagina '+current+' de '+total,alignment:'center',fontSize:8,margin:[0,10,0,0]};},styles:{brand:{fontSize:16,bold:true,color:'#0b2b55'},title:{fontSize:13,bold:true,margin:[0,2,0,10]},section:{fontSize:11,bold:true,margin:[0,8,0,4]}},defaultStyle:{fontSize:9}};}
  function cotPdfName(){var f=document.getElementById('formCotizacionExp'), code=f.dataset.codigo||('COT-'+(new Date().getFullYear())+'-BORRADOR');return code+'.pdf';}
  function cotOpenPdf(download){if(typeof pdfMake==='undefined'){toast('pdfMake local no esta disponible.','danger');return;}renderCotPreview();var pdf=pdfMake.createPdf(cotPdfDefinition());if(download)pdf.download(cotPdfName());else pdf.open();}
  function loadContext(){return fetchJson(endpoint+'?accion=contexto').then(function(r){var d=r.data||{};clientes=d.clientes||[];tipos=d.tipos_seguro||[];estados=d.estados_expediente||[];estadoInicial=d.estado_inicial||null;csrf=d.csrf||csrf;fillOptions();}).catch(function(e){toast(e.message||'No se pudo cargar contexto.','danger');});}
  function load(){document.getElementById('exp-loading').style.display='block';fetchJson(endpoint+'?'+params().toString()).then(function(r){var d=r.data||{};rows=d.rows||[];renderRows();renderPagination(d.pagination||{});}).catch(function(e){toast(e.message||'No se pudo cargar expedientes.','danger');}).finally(function(){document.getElementById('exp-loading').style.display='none';});}
  function renderRows(){var h='';rows.forEach(function(r){var cliente='<strong class="exp-text-clip" title="'+esc(r.cliente_razon_social||'')+'">'+esc(resumen(r.cliente_razon_social,90))+'</strong><div class="text-muted small">'+esc((r.cliente_ruc||'Sin RUC')+' / '+(r.tipo_cliente||''))+'</div>';var tipo='<span class="exp-text-clip" title="'+esc(r.tipo_seguro_nombre||'')+'">'+esc(resumen(r.tipo_seguro_nombre,90))+'</span>';var descripcion='<span class="exp-text-clip" title="'+esc(r.descripcion||'')+'">'+esc(resumen(r.descripcion||'-',110))+'</span>';h+='<tr><td><strong>'+esc(r.codigo)+'</strong></td><td>'+cliente+'</td><td>'+tipo+'</td><td>'+descripcion+'</td><td>'+badgeEstado(r)+'</td><td>'+esc(r.fecha_apertura)+'</td><td>'+badgeActivo(r.estado)+'</td><td class="text-center">'+actions(r)+'</td></tr>';});document.getElementById('exp-body').innerHTML=h;document.getElementById('exp-empty').style.display=rows.length?'none':'block';}
  function actions(row){var h='<span class="exp-actions"><button type="button" class="btn btn-xs btn-outline-info" data-action="view" data-id="'+row.id+'" title="Ver detalle" aria-label="Ver detalle"><i class="fas fa-eye"></i></button>';if(permisos.puede_editar)h+='<button type="button" class="btn btn-xs btn-outline-primary" data-action="edit" data-id="'+row.id+'" title="Editar" aria-label="Editar"><i class="fas fa-edit"></i></button>';if(permisos.puede_eliminar){var title=Number(row.estado)===1?'Desactivar':'Activar';h+='<button type="button" class="btn btn-xs btn-outline-secondary" data-action="toggle" data-id="'+row.id+'" data-state="'+row.estado+'" title="'+title+'" aria-label="'+title+'"><i class="fas '+(Number(row.estado)===1?'fa-toggle-on':'fa-toggle-off')+'"></i></button>';}return h+'</span>';}
  function renderPagination(pag){var total=Number(pag.total||0), cur=Number(pag.page||1), last=Number(pag.last_page||1), wrap=document.getElementById('exp-pagination');document.getElementById('exp-counter').textContent=total+(total===1?' registro':' registros');if(last<=1){wrap.innerHTML='';return;}wrap.innerHTML='<div class="btn-group btn-group-sm"><button type="button" class="btn btn-outline-secondary" data-page="'+(cur-1)+'" '+(cur<=1?'disabled':'')+' title="Anterior" aria-label="Anterior"><i class="fas fa-chevron-left"></i></button><button type="button" class="btn btn-outline-secondary" disabled>'+cur+' / '+last+'</button><button type="button" class="btn btn-outline-secondary" data-page="'+(cur+1)+'" '+(cur>=last?'disabled':'')+' title="Siguiente" aria-label="Siguiente"><i class="fas fa-chevron-right"></i></button></div>';}
  function clearValidation(form){form.classList.remove('was-validated');Array.prototype.forEach.call(form.querySelectorAll('.is-invalid'),function(el){el.classList.remove('is-invalid');});Array.prototype.forEach.call(form.querySelectorAll('.invalid-feedback.dynamic'),function(el){el.remove();});}
  function applyErrors(form,errors){Object.keys(errors||{}).forEach(function(k){var input=form.querySelector('[name="'+k+'"]');if(!input)return;input.classList.add('is-invalid');var f=document.createElement('div');f.className='invalid-feedback dynamic';f.textContent=String(errors[k]);input.parentNode.appendChild(f);});}
  function setVal(form,name,value){if(form.elements[name])form.elements[name].value=value==null?'':value;}
  function today(){var d=new Date();return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');}
  function openModal(id){var form=document.getElementById('formExpediente');form.reset();clearValidation(form);form.dataset.mode=id?'edit':'create';form.elements.id.value='';form.elements.estado.value='1';form.elements.fecha_apertura.value=today();form.elements.estado_expediente_id.disabled=!id;document.getElementById('exp-estado-help').style.display=id?'none':'block';document.getElementById('modalExpedienteTitle').textContent=id?'Editar expediente':'Registrar expediente';if(!id){if(estadoInicial){form.elements.estado_expediente_id.value=estadoInicial.id;}$('#modalExpediente').modal('show');return;}fetchJson(endpoint+'?accion=obtener&id='+encodeURIComponent(id)).then(function(r){var rec=(r.data||{}).record||{};['id','cliente_id','tipo_seguro_id','estado_expediente_id','descripcion','observaciones','fecha_apertura','estado'].forEach(function(k){setVal(form,k,rec[k]);});$('#modalExpediente').modal('show');}).catch(function(e){toast(e.message||'No se pudo cargar el expediente.','danger');});}
  function save(form){if(!form.checkValidity()){form.classList.add('was-validated');return;}clearValidation(form);var creating=form.dataset.mode!=='edit';var data=new FormData(form);if(creating){data.delete('id');if(estadoInicial){data.set('estado_expediente_id',estadoInicial.id);}}post(creating?'crear':'actualizar',data).then(function(r){$('#modalExpediente').modal('hide');toast(r.message||'Expediente guardado.','success');loadContext().then(load);}).catch(function(e){applyErrors(form,e.errors||{});toast(e.message||'No se pudo guardar el expediente.','danger');});}
  function viewDetail(id){fetchJson(endpoint+'?accion=obtener&id='+encodeURIComponent(id)).then(function(r){var x=(r.data||{}).record||{};expedienteDetalleId=id;document.getElementById('exp-detalle-resumen').innerHTML='<dl class="row mb-0"><dt class="col-sm-3">Codigo</dt><dd class="col-sm-9">'+esc(x.codigo)+'</dd><dt class="col-sm-3">Cliente</dt><dd class="col-sm-9">'+esc(x.cliente_razon_social)+'<div class="text-muted small">'+esc(x.cliente_ruc||'Sin RUC')+'</div></dd><dt class="col-sm-3">Tipo</dt><dd class="col-sm-9">'+esc(x.tipo_seguro_nombre)+'</dd><dt class="col-sm-3">Estado</dt><dd class="col-sm-9">'+esc(x.estado_expediente_nombre)+'</dd><dt class="col-sm-3">Fecha apertura</dt><dd class="col-sm-9">'+esc(x.fecha_apertura)+'</dd><dt class="col-sm-3">Descripcion</dt><dd class="col-sm-9">'+esc(x.descripcion)+'</dd><dt class="col-sm-3">Observaciones</dt><dd class="col-sm-9">'+esc(x.observaciones||'-')+'</dd></dl>';var form=document.getElementById('formExpDocumento');if(form){form.reset();clearValidation(form);form.elements.expediente_id.value=id;}document.getElementById('exp-req-filtro-estado').value='todos';document.getElementById('pol-search').value='';document.getElementById('pol-filtro-aseguradora').value='0';document.getElementById('pol-filtro-estado-poliza').value='todos';document.getElementById('pol-filtro-activo').value='todos';document.getElementById('cot-search').value='';document.getElementById('cot-filtro-activo').value='todos';polPage=1;cotPage=1;$('#expDetalleTabs a[href="#exp-tab-resumen"]').tab('show');loadDocs(id);loadFormatos(id);loadReqs(id);loadCotContext(id).then(function(){document.getElementById('cot-filtro-estado').value='todos';loadCotizaciones(id);});loadPolizas(id);loadActividad(id);$('#modalDetalleExpediente').modal('show');}).catch(function(e){toast(e.message||'No se pudo cargar detalle.','danger');});}
  function loadDocs(id){document.getElementById('exp-docs-body').innerHTML='<tr><td colspan="7" class="text-muted text-center">Cargando documentos...</td></tr>';document.getElementById('exp-docs-empty').style.display='none';fetchJson(documentosEndpoint+'?accion=listar&expediente_id='+encodeURIComponent(id)).then(function(r){renderDocs((r.data||{}).rows||[]);}).catch(function(e){document.getElementById('exp-docs-body').innerHTML='';document.getElementById('exp-docs-empty').style.display='block';sectionError('Documentos',e,'No se pudieron cargar documentos.');});}
  function renderDocs(items){var h='';items.forEach(function(d){var activo=Number(d.vinculo_estado)===1&&Number(d.archivo_estado)===1;var acciones='<span class="exp-actions"><a class="btn btn-xs btn-outline-info" href="'+documentosEndpoint+'?accion=descargar&vinculo_id='+encodeURIComponent(d.vinculo_id)+'" title="Descargar" aria-label="Descargar"><i class="fas fa-download"></i></a>';if(permisos.puede_editar&&activo){acciones+='<button type="button" class="btn btn-xs btn-outline-secondary" data-doc-archive="'+esc(d.vinculo_id)+'" title="Archivar" aria-label="Archivar"><i class="fas fa-archive"></i></button>';}acciones+='</span>';h+='<tr><td>'+esc(d.tipo_documento_nombre||d.slot||'-')+'</td><td><strong>'+esc(d.nombre_original||'-')+'</strong><div class="text-muted small">'+esc(d.mime_type||'')+' '+esc(bytes(d.tamanio_bytes))+'</div></td><td>'+esc(d.descripcion||'-')+'</td><td>'+esc(d.cargado_en||'-')+'</td><td>'+esc(d.cargado_por_usuario_externo_id||'-')+'</td><td>'+badgeActivo(activo?1:0)+'</td><td class="text-center">'+acciones+'</td></tr>';});document.getElementById('exp-docs-body').innerHTML=h;document.getElementById('exp-docs-empty').style.display=items.length?'none':'block';}
  function loadFormatos(id){document.getElementById('exp-formatos-body').innerHTML='<tr><td colspan="4" class="text-muted text-center">Cargando formatos...</td></tr>';document.getElementById('exp-formatos-empty').style.display='none';fetchJson(formatosExpEndpoint+'?accion=listar&expediente_id='+encodeURIComponent(id)).then(function(r){renderFormatos((r.data||{}).rows||[]);}).catch(function(e){document.getElementById('exp-formatos-body').innerHTML='';document.getElementById('exp-formatos-empty').style.display='block';sectionError('Formatos',e,'No se pudieron cargar formatos.');});}
  function renderFormatos(items){var h='';items.forEach(function(f){h+='<tr><td><strong class="exp-text-clip" title="'+esc(f.nombre||'')+'">'+esc(resumen(f.nombre||'',90))+'</strong><div class="text-muted small">'+esc(f.nombre_original||'')+'</div></td><td><span class="exp-text-clip" title="'+esc(f.descripcion||'')+'">'+esc(resumen(f.descripcion||'-',120))+'</span></td><td>'+esc(f.requisito_nombre||'-')+'</td><td class="text-center"><span class="exp-actions"><a class="btn btn-xs btn-outline-info" href="'+formatosExpEndpoint+'?accion=descargar&expediente_id='+encodeURIComponent(expedienteDetalleId)+'&vinculo_id='+encodeURIComponent(f.vinculo_id)+'" title="Descargar" aria-label="Descargar"><i class="fas fa-download"></i></a></span></td></tr>';});document.getElementById('exp-formatos-body').innerHTML=h;document.getElementById('exp-formatos-empty').style.display=items.length?'none':'block';}
  function loadReqs(id){var estado=document.getElementById('exp-req-filtro-estado').value||'todos';document.getElementById('exp-req-body').innerHTML='<tr><td colspan="10" class="text-muted text-center">Cargando requisitos...</td></tr>';document.getElementById('exp-req-empty').style.display='none';var gen=document.getElementById('exp-req-generar');if(gen)gen.style.display='none';fetchJson(requisitosEndpoint+'?accion=listar&expediente_id='+encodeURIComponent(id)+'&estado='+encodeURIComponent(estado)).then(function(r){var d=r.data||{};reqRows=d.rows||[];renderReqs(reqRows,!!d.tiene_requisitos);}).catch(function(e){document.getElementById('exp-req-body').innerHTML='';document.getElementById('exp-req-empty').style.display='block';sectionError('Requisitos',e,'No se pudieron cargar requisitos.');});}
  function renderReqs(items,tieneRequisitos){var h='';items.forEach(function(r){var docs=renderReqDocs(r);var acciones='<span class="exp-actions">';if(permisos.puede_editar){acciones+='<button type="button" class="btn btn-xs btn-outline-primary" data-req-action="estado" data-id="'+r.id+'" title="Cambiar estado" aria-label="Cambiar estado"><i class="fas fa-edit"></i></button>';}if(permisos.puede_crear&&['pendiente','entregado','observado','rechazado'].indexOf(String(r.estado_requisito))!==-1){acciones+='<button type="button" class="btn btn-xs btn-outline-success" data-req-action="upload" data-id="'+r.id+'" title="Cargar documento" aria-label="Cargar documento"><i class="fas fa-upload"></i></button>';}acciones+='</span>';h+='<tr><td>'+esc(r.orden_visual_snapshot)+'</td><td><strong class="exp-text-clip" title="'+esc(r.nombre_snapshot||'')+'">'+esc(resumen(r.nombre_snapshot,100))+'</strong></td><td><span class="exp-text-clip" title="'+esc(r.descripcion_snapshot||'')+'">'+esc(resumen(r.descripcion_snapshot||'-',120))+'</span></td><td>'+(Number(r.es_obligatorio_snapshot)===1?'<span class="badge badge-danger">Obligatorio</span>':'<span class="badge badge-info">Opcional</span>')+'</td><td>'+badgeReqEstado(r.estado_requisito)+'</td><td><span class="exp-text-clip" title="'+esc(r.observacion_actual||'')+'">'+esc(resumen(r.observacion_actual||'-',100))+'</span></td><td>'+esc(r.fecha_entrega||'-')+'<div class="text-muted small">Usuario: '+esc(r.entregado_por_usuario_externo_id||'-')+'</div></td><td>'+esc(r.fecha_evaluacion||'-')+'<div class="text-muted small">Usuario: '+esc(r.evaluado_por_usuario_externo_id||'-')+'</div></td><td>'+esc(r.documentos_activos||0)+docs+'</td><td class="text-center">'+acciones+'</td></tr>';});document.getElementById('exp-req-body').innerHTML=h;document.getElementById('exp-req-empty').textContent=tieneRequisitos?'No hay requisitos para el filtro seleccionado.':'Este expediente no tiene requisitos generados.';document.getElementById('exp-req-empty').style.display=items.length?'none':'block';var gen=document.getElementById('exp-req-generar');if(gen)gen.style.display=!tieneRequisitos?'inline-block':'none';}
  function renderReqDocs(r){var docs=r.documentos||[];if(!docs.length)return '';var h='<ul class="exp-req-docs">';docs.forEach(function(d){var activo=Number(d.vinculo_estado)===1&&Number(d.archivo_estado)===1;h+='<li><span title="'+esc(d.nombre_original||'')+'">'+esc(resumen(d.nombre_original||'-',42))+'</span> '+badgeActivo(activo?1:0)+'<div class="text-muted small">'+esc(d.cargado_en||'-')+' / Usuario: '+esc(d.cargado_por_usuario_externo_id||'-')+'</div><span class="exp-actions"><a class="btn btn-xs btn-outline-info" href="'+requisitosEndpoint+'?accion=descargar&expediente_id='+encodeURIComponent(expedienteDetalleId)+'&vinculo_id='+encodeURIComponent(d.vinculo_id)+'" title="Descargar" aria-label="Descargar"><i class="fas fa-download"></i></a>';if(permisos.puede_editar&&activo){h+='<button type="button" class="btn btn-xs btn-outline-secondary" data-req-doc-archive="'+esc(d.vinculo_id)+'" title="Archivar" aria-label="Archivar"><i class="fas fa-archive"></i></button>';}h+='</span></li>';});return h+'</ul>';}
  function reqById(id){for(var i=0;i<reqRows.length;i++){if(Number(reqRows[i].id)===Number(id))return reqRows[i];}return null;}
  function openReqEstado(id){var r=reqById(id);if(!r)return;var f=document.getElementById('formReqEstadoExp');f.reset();clearValidation(f);f.elements.id.value=r.id;f.elements.expediente_id.value=expedienteDetalleId;f.elements.estado_requisito.value=r.estado_requisito;f.elements.observacion_actual.value=r.observacion_actual||'';document.getElementById('req-estado-nombre').value=r.nombre_snapshot||'';$('#modalReqEstadoExp').modal('show');}
  function openReqDoc(id){var r=reqById(id);if(!r)return;var f=document.getElementById('formReqDocExp');f.reset();clearValidation(f);f.elements.id.value=r.id;f.elements.expediente_id.value=expedienteDetalleId;document.getElementById('req-doc-nombre').value=r.nombre_snapshot||'';$('#modalReqDocExp').modal('show');}
  function saveReqEstado(form){if(!form.checkValidity()){form.classList.add('was-validated');return;}clearValidation(form);postReq('cambiar_estado',new FormData(form)).then(function(r){$('#modalReqEstadoExp').modal('hide');toast(r.message||'Requisito actualizado.','success');loadReqs(expedienteDetalleId);loadActividad(expedienteDetalleId);}).catch(function(e){applyErrors(form,e.errors||{});toast(e.message||'No se pudo actualizar el requisito.','danger');});}
  function uploadReqDocs(form){var files=form.elements.archivo.files;if(!files||!files.length){form.classList.add('was-validated');return;}clearValidation(form);var id=form.elements.id.value, expId=form.elements.expediente_id.value;var chain=Promise.resolve(), ok=0;Array.prototype.forEach.call(files,function(file){chain=chain.then(function(){var data=new FormData();data.set('id',id);data.set('expediente_id',expId);data.set('archivo',file);return postReq('cargar_documento',data).then(function(){ok++;});});});chain.then(function(){$('#modalReqDocExp').modal('hide');toast(ok+' documento(s) cargado(s).','success');loadReqs(expedienteDetalleId);loadActividad(expedienteDetalleId);}).catch(function(e){applyErrors(form,e.errors||{});toast(e.message||'No se pudo cargar uno de los documentos.','danger');});}
  function postReq(action,data){data.set('_csrf',csrf);return fetchJson(requisitosEndpoint+'?accion='+encodeURIComponent(action),{method:'POST',body:data});}
  function generarReqs(){var data=new FormData();data.set('expediente_id',expedienteDetalleId);postReq('generar',data).then(function(r){toast(r.message||'Requisitos generados.','success');loadReqs(expedienteDetalleId);loadActividad(expedienteDetalleId);}).catch(function(e){toast(e.message||'No se pudieron generar requisitos.','danger');});}
  function polParams(){var p=new URLSearchParams();p.set('accion','listar');p.set('expediente_id',expedienteDetalleId);p.set('page',polPage);p.set('q',document.getElementById('pol-search').value||'');p.set('aseguradora_id',document.getElementById('pol-filtro-aseguradora').value||'0');p.set('estado_poliza',document.getElementById('pol-filtro-estado-poliza').value||'todos');p.set('estado',document.getElementById('pol-filtro-activo').value||'todos');return p;}
  function loadPolizas(id){if(id)expedienteDetalleId=id;document.getElementById('pol-body').innerHTML='<tr><td colspan="10" class="text-muted text-center">Cargando polizas...</td></tr>';document.getElementById('pol-empty').style.display='none';fetchJson(polizasEndpoint+'?'+polParams().toString()).then(function(r){var d=r.data||{};polRows=d.rows||[];renderPolizas(polRows);renderPolPagination(d.pagination||{});}).catch(function(e){document.getElementById('pol-body').innerHTML='';document.getElementById('pol-empty').style.display='block';sectionError('Polizas',e,'No se pudieron cargar polizas.');});}
  function renderPolizas(items){var h='';items.forEach(function(p){var aseg=p.aseguradora_nombre_comercial||p.aseguradora_razon_social||'-';var doc='<strong>'+esc(polTipoDocLabel(p.tipo_documento_emitido))+'</strong><div class="text-muted small">'+esc(p.numero_documento||'Sin numero')+'</div>';var vig=esc((p.vigencia_inicio||'-')+' / '+(p.vigencia_fin||'-'))+'<div class="text-muted small">'+esc(p.vigencia_dias||0)+' dias</div>';var pdf=p.pdf_vinculo_id?'<span class="badge badge-success">PDF</span>':'<span class="badge badge-warning">Sin PDF</span>';h+='<tr><td><strong>'+esc(p.codigo)+'</strong></td><td>'+doc+'</td><td><span class="exp-text-clip" title="'+esc(aseg)+'">'+esc(resumen(aseg,80))+'</span></td><td>'+vig+'</td><td>'+esc(p.moneda||'')+' '+esc(p.suma_asegurada||'-')+'</td><td>'+esc(p.moneda||'')+' '+esc(p.prima_total||'-')+'</td><td>'+badgePolEstado(p.estado_poliza)+'</td><td>'+badgeActivo(p.estado)+'</td><td>'+pdf+'</td><td class="text-center">'+polActions(p)+'</td></tr>';});document.getElementById('pol-body').innerHTML=h;document.getElementById('pol-empty').style.display=items.length?'none':'block';}
  function polActions(p){var h='<span class="exp-actions"><button type="button" class="btn btn-xs btn-outline-info" data-pol-action="view" data-id="'+p.id+'" title="Ver detalle" aria-label="Ver detalle"><i class="fas fa-eye"></i></button>';if(permisos.puede_editar){h+='<button type="button" class="btn btn-xs btn-outline-primary" data-pol-action="edit" data-id="'+p.id+'" title="Editar" aria-label="Editar"><i class="fas fa-edit"></i></button><button type="button" class="btn btn-xs btn-outline-success" data-pol-action="pdf" data-id="'+p.id+'" title="Cargar PDF" aria-label="Cargar PDF"><i class="fas fa-upload"></i></button>';}if(p.pdf_vinculo_id){h+='<a class="btn btn-xs btn-outline-info" href="'+polizasEndpoint+'?accion=descargar_pdf&expediente_id='+encodeURIComponent(expedienteDetalleId)+'&vinculo_id='+encodeURIComponent(p.pdf_vinculo_id)+'" title="Descargar PDF" aria-label="Descargar PDF"><i class="fas fa-download"></i></a>';if(permisos.puede_editar){h+='<button type="button" class="btn btn-xs btn-outline-secondary" data-pol-pdf-archive="'+p.pdf_vinculo_id+'" title="Archivar PDF" aria-label="Archivar PDF"><i class="fas fa-archive"></i></button>';}}if(permisos.puede_eliminar){var title=Number(p.estado)===1?'Desactivar':'Activar';h+='<button type="button" class="btn btn-xs btn-outline-secondary" data-pol-action="toggle" data-id="'+p.id+'" data-state="'+p.estado+'" title="'+title+'" aria-label="'+title+'"><i class="fas '+(Number(p.estado)===1?'fa-toggle-on':'fa-toggle-off')+'"></i></button>';}return h+'</span>';}
  function renderPolPagination(pag){var total=Number(pag.total||0),cur=Number(pag.page||1),last=Number(pag.last_page||1),wrap=document.getElementById('pol-pagination');document.getElementById('pol-counter').textContent=total+(total===1?' registro':' registros');if(last<=1){wrap.innerHTML='';return;}wrap.innerHTML='<div class="btn-group btn-group-sm"><button type="button" class="btn btn-outline-secondary" data-pol-page="'+(cur-1)+'" '+(cur<=1?'disabled':'')+' title="Anterior" aria-label="Anterior"><i class="fas fa-chevron-left"></i></button><button type="button" class="btn btn-outline-secondary" disabled>'+cur+' / '+last+'</button><button type="button" class="btn btn-outline-secondary" data-pol-page="'+(cur+1)+'" '+(cur>=last?'disabled':'')+' title="Siguiente" aria-label="Siguiente"><i class="fas fa-chevron-right"></i></button></div>';}
  function polById(id){for(var i=0;i<polRows.length;i++){if(Number(polRows[i].id)===Number(id))return polRows[i];}return null;}
  function postPol(action,data){data.set('_csrf',csrf);return fetchJson(polizasEndpoint+'?accion='+encodeURIComponent(action),{method:'POST',body:data});}
  function nowLocal(){var d=new Date();d.setMinutes(d.getMinutes()-d.getTimezoneOffset());return d.toISOString().slice(0,16);}
  function todayDate(){return nowLocal().slice(0,10);}
  function toLocalInput(v){v=String(v||'').replace(' ','T');return v.slice(0,16);}
  function openPolModal(id){var f=document.getElementById('formPolizaExp');f.reset();clearValidation(f);f.dataset.mode=id?'edit':'create';f.elements.id.value='';f.elements.expediente_id.value=expedienteDetalleId;f.elements.fecha_emision.value=todayDate();f.elements.vigencia_inicio.value=nowLocal();f.elements.vigencia_fin.value=nowLocal();f.elements.moneda.value='PEN';f.elements.estado.value='1';f.elements.tipo_documento_emitido.innerHTML=optionPolTipos('poliza');f.elements.estado_poliza.innerHTML=optionPolEstados('borrador',false);f.elements.aseguradora_id.innerHTML=optionPolAseguradoras();document.getElementById('pol-pdf-group').style.display=id?'none':'block';document.getElementById('modalPolizaExpTitle').textContent=id?'Editar poliza':'Registrar poliza';if(!id){$('#modalPolizaExp').modal('show');return;}fetchJson(polizasEndpoint+'?accion=obtener&expediente_id='+encodeURIComponent(expedienteDetalleId)+'&id='+encodeURIComponent(id)).then(function(r){var x=(r.data||{}).record||{};['id','expediente_id','aseguradora_id','tipo_documento_emitido','numero_documento','beneficiario_nombre','fecha_emision','moneda','suma_asegurada','prima_comercial','igv','prima_total','estado_poliza','observaciones','estado'].forEach(function(k){setVal(f,k,x[k]);});f.elements.vigencia_inicio.value=toLocalInput(x.vigencia_inicio);f.elements.vigencia_fin.value=toLocalInput(x.vigencia_fin);$('#modalPolizaExp').modal('show');}).catch(function(e){toast(e.message||'No se pudo cargar la poliza.','danger');});}
  function savePol(form){if(!form.checkValidity()){form.classList.add('was-validated');return;}clearValidation(form);var creating=form.dataset.mode!=='edit';var data=new FormData(form);if(creating){data.delete('id');}postPol(creating?'crear':'actualizar',data).then(function(r){$('#modalPolizaExp').modal('hide');toast(r.message||'Poliza guardada.','success');loadPolizas(expedienteDetalleId);loadActividad(expedienteDetalleId);}).catch(function(e){applyErrors(form,e.errors||{});toast(e.message||'No se pudo guardar la poliza.','danger');});}
  function polAnaStatus(text,type){var el=document.getElementById('pol-ana-status');el.textContent=text;el.className='pol-analysis-status text-'+(type||'muted');}
  function openPolAnalisis(){var f=document.getElementById('formPolizaAnalisisExp');f.reset();clearValidation(f);f.elements.expediente_id.value=expedienteDetalleId;f.elements.fecha_emision.value=todayDate();f.elements.vigencia_inicio.value=nowLocal();f.elements.vigencia_fin.value=nowLocal();f.elements.moneda.value='PEN';f.elements.estado.value='1';f.elements.estado_poliza.value='borrador';f.elements.tipo_documento_emitido.value='poliza';f.elements.metodo_extraccion.value='manual';f.elements.estado_extraccion.value='pendiente';f.elements.confianza_global.value='0';f.elements.campos_extraidos_json.value='{}';f.elements.texto_extraido.value='';document.getElementById('pol-ana-texto').value='';document.getElementById('pol-ana-contratante').value='';document.getElementById('pol-ana-confianza').textContent='0%';if(polAnaPdfUrl){URL.revokeObjectURL(polAnaPdfUrl);polAnaPdfUrl=null;}document.getElementById('pol-ana-frame').removeAttribute('src');polAnaStatus('Selecciona un PDF para verlo y analizarlo.','muted');$('#modalPolizaAnalisisExp').modal('show');}
  function polAnaPreview(file){if(polAnaPdfUrl){URL.revokeObjectURL(polAnaPdfUrl);}polAnaPdfUrl=URL.createObjectURL(file);document.getElementById('pol-ana-frame').src=polAnaPdfUrl;polAnaStatus('PDF cargado en vista previa. Puedes extraer datos o llenar manualmente.','info');}
  function polAnaApply(data){var f=document.getElementById('formPolizaAnalisisExp'), d=(data||{}), campos=d.campos||{}, cand=d.candidatos||{};Object.keys(campos).forEach(function(k){if(!f.elements[k])return;if(k==='aseguradora_id'&&Number(campos[k])<=0)return;f.elements[k].value=campos[k];});f.elements.metodo_extraccion.value=d.metodo_usado||'manual';f.elements.estado_extraccion.value=d.estado_extraccion==='texto_extraido'?'extraida':(d.requiere_ocr?'ocr_requerido':'pendiente');f.elements.confianza_global.value=d.confianza_global||0;f.elements.campos_extraidos_json.value=JSON.stringify({campos:campos,candidatos:cand,conocimiento_version:d.conocimiento_version||''});f.elements.texto_extraido.value=d.texto_extraido||'';document.getElementById('pol-ana-texto').value=d.texto_extraido||'';document.getElementById('pol-ana-contratante').value=[cand.contratante_nombre||'',cand.contratante_ruc||''].filter(Boolean).join(' / ');document.getElementById('pol-ana-confianza').textContent=String(d.confianza_global||0)+'%';if((d.mensajes||[]).length){f.elements.observaciones_extraccion.value=(d.mensajes||[]).join('\\n');}polAnaStatus((d.mensajes||[])[0]||'Analisis completado. Revisa antes de guardar.',d.estado_extraccion==='texto_extraido'?'success':'warning');}
  function polAnaExtraer(){var f=document.getElementById('formPolizaAnalisisExp');var file=f.elements.archivo_pdf.files&&f.elements.archivo_pdf.files[0];if(!file){f.classList.add('was-validated');polAnaStatus('Selecciona un PDF primero.','danger');return;}var data=new FormData();data.set('archivo_pdf',file);data.set('modo_extraccion',document.getElementById('pol-ana-modo').value||'auto');data.set('_csrf',csrf);polAnaStatus('Analizando PDF...','info');fetchJson(polAnalisisEndpoint+'?accion=analizar_pdf',{method:'POST',body:data}).then(function(r){polAnaApply(r.data||{});toast(r.message||'Analisis completado.','success');}).catch(function(e){polAnaStatus(e.message||'No se pudo analizar el PDF.','danger');toast(e.message||'No se pudo analizar el PDF.','danger');});}
  function savePolAnalisis(form){if(!form.checkValidity()){form.classList.add('was-validated');return;}clearValidation(form);form.elements.texto_extraido.value=document.getElementById('pol-ana-texto').value;var campos={};['aseguradora_id','tipo_documento_emitido','numero_documento','beneficiario_nombre','fecha_emision','vigencia_inicio','vigencia_fin','moneda','suma_asegurada','prima_comercial','igv','prima_total','estado_poliza'].forEach(function(k){if(form.elements[k])campos[k]=form.elements[k].value;});form.elements.campos_extraidos_json.value=JSON.stringify({campos_guardados:campos,extraidos:JSON.parse(form.elements.campos_extraidos_json.value||'{}')});var data=new FormData(form);postPol('crear',data).then(function(r){$('#modalPolizaAnalisisExp').modal('hide');if(polAnaPdfUrl){URL.revokeObjectURL(polAnaPdfUrl);polAnaPdfUrl=null;}toast(r.message||'Poliza guardada.','success');loadPolizas(expedienteDetalleId);loadActividad(expedienteDetalleId);}).catch(function(e){applyErrors(form,e.errors||{});toast(e.message||'No se pudo guardar la poliza.','danger');});}
  function openPolPdf(id){var p=polById(id);if(!p)return;var f=document.getElementById('formPolizaPdfExp');f.reset();clearValidation(f);f.elements.id.value=id;f.elements.expediente_id.value=expedienteDetalleId;document.getElementById('pol-pdf-nombre').value=p.codigo+' - '+polTipoDocLabel(p.tipo_documento_emitido);$('#modalPolizaPdfExp').modal('show');}
  function savePolPdf(form){if(!form.checkValidity()){form.classList.add('was-validated');return;}clearValidation(form);postPol('cargar_pdf',new FormData(form)).then(function(r){$('#modalPolizaPdfExp').modal('hide');toast(r.message||'PDF cargado.','success');loadPolizas(expedienteDetalleId);loadActividad(expedienteDetalleId);}).catch(function(e){applyErrors(form,e.errors||{});toast(e.message||'No se pudo cargar el PDF.','danger');});}
  function viewPol(id){fetchJson(polizasEndpoint+'?accion=obtener&expediente_id='+encodeURIComponent(expedienteDetalleId)+'&id='+encodeURIComponent(id)).then(function(r){var p=(r.data||{}).record||{};var pdf=p.pdf&&p.pdf.vinculo_id?'<a class="btn btn-sm btn-outline-info" href="'+polizasEndpoint+'?accion=descargar_pdf&expediente_id='+encodeURIComponent(expedienteDetalleId)+'&vinculo_id='+encodeURIComponent(p.pdf.vinculo_id)+'"><i class="fas fa-download"></i> Descargar PDF</a>':'<span class="badge badge-warning">Sin PDF activo</span>';document.getElementById('pol-detalle-body').innerHTML='<dl class="row mb-0"><dt class="col-sm-4">Codigo</dt><dd class="col-sm-8">'+esc(p.codigo)+'</dd><dt class="col-sm-4">Documento</dt><dd class="col-sm-8">'+esc(polTipoDocLabel(p.tipo_documento_emitido))+'<div class="text-muted small">'+esc(p.numero_documento||'Sin numero')+'</div></dd><dt class="col-sm-4">Aseguradora</dt><dd class="col-sm-8">'+esc(p.aseguradora_nombre_comercial||p.aseguradora_razon_social||'-')+'</dd><dt class="col-sm-4">Contratante snapshot</dt><dd class="col-sm-8">'+esc(p.contratante_nombre_snapshot||'-')+'<div class="text-muted small">'+esc(p.contratante_ruc_snapshot||'Sin RUC')+'</div></dd><dt class="col-sm-4">Beneficiario</dt><dd class="col-sm-8">'+esc(p.beneficiario_nombre||'-')+'</dd><dt class="col-sm-4">Emision</dt><dd class="col-sm-8">'+esc(p.fecha_emision||'-')+'</dd><dt class="col-sm-4">Vigencia</dt><dd class="col-sm-8">'+esc((p.vigencia_inicio||'-')+' a '+(p.vigencia_fin||'-'))+'<div class="text-muted small">'+esc(p.vigencia_dias||0)+' dias</div></dd><dt class="col-sm-4">Montos</dt><dd class="col-sm-8">'+esc(p.moneda||'')+' suma '+esc(p.suma_asegurada||'-')+' | prima total '+esc(p.prima_total||'-')+'</dd><dt class="col-sm-4">Estado</dt><dd class="col-sm-8">'+badgePolEstado(p.estado_poliza)+' '+badgeActivo(p.estado)+'</dd><dt class="col-sm-4">PDF</dt><dd class="col-sm-8">'+pdf+'</dd><dt class="col-sm-4">Observaciones</dt><dd class="col-sm-8">'+esc(p.observaciones||'-')+'</dd></dl>';$('#modalPolizaDetalleExp').modal('show');}).catch(function(e){toast(e.message||'No se pudo cargar detalle de poliza.','danger');});}
  function togglePol(id){var data=new FormData();data.set('id',id);data.set('expediente_id',expedienteDetalleId);postPol('cambiar_estado',data).then(function(r){toast(r.message||'Estado actualizado.','success');loadPolizas(expedienteDetalleId);loadActividad(expedienteDetalleId);}).catch(function(e){toast(e.message||'No se pudo actualizar estado.','danger');});}
  function loadActividad(id){document.getElementById('exp-actividad-body').innerHTML='<div class="text-muted text-center py-2">Cargando actividad...</div>';document.getElementById('exp-actividad-empty').style.display='none';fetchJson(timelineEndpoint+'?accion=listar&expediente_id='+encodeURIComponent(id)).then(function(r){renderActividad((r.data||{}).rows||[]);}).catch(function(e){document.getElementById('exp-actividad-body').innerHTML='';document.getElementById('exp-actividad-empty').style.display='block';sectionError('Actividad',e,'No se pudo cargar actividad.');});}
  function renderActividad(items){var h='';items.forEach(function(ev){h+='<div class="border-bottom py-2"><div class="d-flex justify-content-between"><strong>'+esc(ev.descripcion||ev.codigo_evento)+'</strong><span class="text-muted small">'+esc(ev.fecha_evento||'-')+'</span></div><div class="text-muted small">Evento: '+esc(ev.codigo_evento||'-')+' - Usuario: '+esc(ev.actor_usuario_externo_id||'-')+'</div></div>';});document.getElementById('exp-actividad-body').innerHTML=h;document.getElementById('exp-actividad-empty').style.display=items.length?'none':'block';}
  function confirmAction(text,cb){document.getElementById('modalConfirmExpedienteText').textContent=text;confirmCallback=cb;$('#modalConfirmExpediente').modal('show');}
  function toggle(id,state){var data=new FormData();data.set('id',id);post('cambiar_estado',data).then(function(r){toast(r.message||'Estado actualizado.','success');load();}).catch(function(e){toast(e.message||'No se pudo actualizar estado.','danger');});}

  document.getElementById('exp-btn-buscar').addEventListener('click',function(){page=1;load();});
  document.getElementById('exp-btn-limpiar').addEventListener('click',function(){document.getElementById('exp-search').value='';document.getElementById('exp-filtro-cliente').value='0';document.getElementById('exp-filtro-tipo').value='0';document.getElementById('exp-filtro-estado-exp').value='0';document.getElementById('exp-filtro-activo').value='todos';page=1;load();});
  var btnNuevo=document.getElementById('exp-btn-nuevo');if(btnNuevo)btnNuevo.addEventListener('click',function(){openModal(null);});
  ['exp-search','exp-filtro-cliente','exp-filtro-tipo','exp-filtro-estado-exp','exp-filtro-activo'].forEach(function(id){document.getElementById(id).addEventListener(id==='exp-search'?'input':'change',function(){clearTimeout(timer);timer=setTimeout(function(){page=1;load();}, id==='exp-search'?300:0);});});
  document.getElementById('exp-pagination').addEventListener('click',function(e){var b=e.target.closest('[data-page]');if(!b)return;page=Number(b.dataset.page)||1;load();});
  document.getElementById('exp-body').addEventListener('click',function(e){var b=e.target.closest('[data-action]');if(!b)return;var id=b.dataset.id;if(b.dataset.action==='view')viewDetail(id);else if(b.dataset.action==='edit')openModal(id);else confirmAction(Number(b.dataset.state)===1?'Desea desactivar este expediente?':'Desea activar este expediente?',function(){toggle(id,b.dataset.state);});});
  document.getElementById('formExpediente').addEventListener('submit',function(e){e.preventDefault();save(e.target);});
  var formDoc=document.getElementById('formExpDocumento');if(formDoc)formDoc.addEventListener('submit',function(e){e.preventDefault();var form=e.target;if(!form.checkValidity()){form.classList.add('was-validated');return;}clearValidation(form);var expedienteId=form.elements.expediente_id.value;postDocs('cargar',new FormData(form)).then(function(r){toast(r.message||'Documento cargado.','success');form.reset();form.elements.expediente_id.value=expedienteId;loadDocs(expedienteId);loadActividad(expedienteId);}).catch(function(e){applyErrors(form,e.errors||{});toast(e.message||'No se pudo cargar el documento.','danger');});});
  document.getElementById('exp-docs-body').addEventListener('click',function(e){var b=e.target.closest('[data-doc-archive]');if(!b)return;var id=b.getAttribute('data-doc-archive');confirmAction('Desea archivar este documento del expediente?',function(){var data=new FormData();data.set('vinculo_id',id);postDocs('archivar',data).then(function(r){toast(r.message||'Documento archivado.','success');if(expedienteDetalleId){loadDocs(expedienteDetalleId);loadActividad(expedienteDetalleId);}}).catch(function(e){toast(e.message||'No se pudo archivar el documento.','danger');});});});
  document.getElementById('exp-req-filtro-estado').addEventListener('change',function(){if(expedienteDetalleId)loadReqs(expedienteDetalleId);});
  var btnReqGenerar=document.getElementById('exp-req-generar');if(btnReqGenerar)btnReqGenerar.addEventListener('click',function(){confirmAction('Desea generar los requisitos para este expediente?',generarReqs);});
  document.getElementById('exp-req-body').addEventListener('click',function(e){var b=e.target.closest('[data-req-action]');if(b){var id=b.dataset.id;if(b.dataset.reqAction==='estado')openReqEstado(id);else openReqDoc(id);return;}var a=e.target.closest('[data-req-doc-archive]');if(!a)return;var vinculoId=a.getAttribute('data-req-doc-archive');confirmAction('Desea archivar este documento de requisito?',function(){var data=new FormData();data.set('vinculo_id',vinculoId);data.set('expediente_id',expedienteDetalleId);postReq('archivar_documento',data).then(function(r){toast(r.message||'Documento archivado.','success');loadReqs(expedienteDetalleId);loadActividad(expedienteDetalleId);}).catch(function(e){toast(e.message||'No se pudo archivar el documento.','danger');});});});
  document.getElementById('formReqEstadoExp').addEventListener('submit',function(e){e.preventDefault();saveReqEstado(e.target);});
  document.getElementById('formReqDocExp').addEventListener('submit',function(e){e.preventDefault();uploadReqDocs(e.target);});
  document.getElementById('pol-btn-buscar').addEventListener('click',function(){polPage=1;loadPolizas(expedienteDetalleId);});
  ['pol-search','pol-filtro-aseguradora','pol-filtro-estado-poliza','pol-filtro-activo'].forEach(function(id){document.getElementById(id).addEventListener(id==='pol-search'?'input':'change',function(){clearTimeout(timer);timer=setTimeout(function(){polPage=1;loadPolizas(expedienteDetalleId);},id==='pol-search'?300:0);});});
  var btnPolNuevo=document.getElementById('pol-btn-nuevo');if(btnPolNuevo)btnPolNuevo.addEventListener('click',function(){openPolModal(null);});
  var btnPolAnalizar=document.getElementById('pol-btn-analizar');if(btnPolAnalizar)btnPolAnalizar.addEventListener('click',openPolAnalisis);
  document.getElementById('pol-pagination').addEventListener('click',function(e){var b=e.target.closest('[data-pol-page]');if(!b)return;polPage=Number(b.dataset.polPage)||1;loadPolizas(expedienteDetalleId);});
  document.getElementById('pol-body').addEventListener('click',function(e){var b=e.target.closest('[data-pol-action]');if(b){var id=b.dataset.id;if(b.dataset.polAction==='view')viewPol(id);else if(b.dataset.polAction==='edit')openPolModal(id);else if(b.dataset.polAction==='pdf')openPolPdf(id);else confirmAction(Number(b.dataset.state)===1?'Desea desactivar esta poliza?':'Desea activar esta poliza?',function(){togglePol(id);});return;}var a=e.target.closest('[data-pol-pdf-archive]');if(!a)return;var vinculoId=a.getAttribute('data-pol-pdf-archive');confirmAction('Desea archivar el PDF principal de esta poliza?',function(){var data=new FormData();data.set('vinculo_id',vinculoId);data.set('expediente_id',expedienteDetalleId);postPol('archivar_pdf',data).then(function(r){toast(r.message||'PDF archivado.','success');loadPolizas(expedienteDetalleId);loadActividad(expedienteDetalleId);}).catch(function(e){toast(e.message||'No se pudo archivar el PDF.','danger');});});});
  document.getElementById('formPolizaExp').addEventListener('submit',function(e){e.preventDefault();savePol(e.target);});
  document.getElementById('formPolizaPdfExp').addEventListener('submit',function(e){e.preventDefault();savePolPdf(e.target);});
  document.getElementById('formPolizaAnalisisExp').addEventListener('submit',function(e){e.preventDefault();savePolAnalisis(e.target);});
  document.getElementById('formPolizaAnalisisExp').elements.archivo_pdf.addEventListener('change',function(){var file=this.files&&this.files[0];if(file)polAnaPreview(file);});
  document.getElementById('pol-ana-extraer').addEventListener('click',polAnaExtraer);
  document.getElementById('cot-btn-buscar').addEventListener('click',function(){cotPage=1;loadCotizaciones(expedienteDetalleId);});
  ['cot-search','cot-filtro-estado','cot-filtro-activo'].forEach(function(id){document.getElementById(id).addEventListener(id==='cot-search'?'input':'change',function(){clearTimeout(timer);timer=setTimeout(function(){cotPage=1;loadCotizaciones(expedienteDetalleId);},id==='cot-search'?300:0);});});
  var btnCotNuevo=document.getElementById('cot-btn-nuevo');if(btnCotNuevo)btnCotNuevo.addEventListener('click',function(){openCotModal(null);});
  document.getElementById('cot-pagination').addEventListener('click',function(e){var b=e.target.closest('[data-cot-page]');if(!b)return;cotPage=Number(b.dataset.cotPage)||1;loadCotizaciones(expedienteDetalleId);});
  document.getElementById('cot-body').addEventListener('click',function(e){var b=e.target.closest('[data-cot-action]');if(!b)return;var id=b.dataset.id;if(b.dataset.cotAction==='view')openCotModal(id,true);else if(b.dataset.cotAction==='edit')openCotModal(id,false);else confirmAction(Number(b.dataset.state)===1?'Desea desactivar esta cotizacion?':'Desea activar esta cotizacion?',function(){var data=new FormData();data.set('id',id);data.set('expediente_id',expedienteDetalleId);data.set('_csrf',csrf);fetchJson(cotizacionesEndpoint+'?accion=cambiar_estado',{method:'POST',body:data}).then(function(r){toast(r.message||'Estado actualizado.','success');loadCotizaciones(expedienteDetalleId);loadActividad(expedienteDetalleId);}).catch(function(e){toast(e.message||'No se pudo actualizar estado.','danger');});});});
  document.getElementById('formCotizacionExp').addEventListener('submit',function(e){e.preventDefault();saveCot(e.target);});
  document.getElementById('cot-add-riesgo').addEventListener('click',function(){cotReadRiesgos();cotRiesgos.push({etiqueta:'',valor:'',orden_visual:cotRiesgos.length+1});renderCotRiesgos();});
  document.getElementById('cot-riesgos-body').addEventListener('click',function(e){var b=e.target.closest('[data-cot-remove-riesgo]');if(!b)return;cotReadRiesgos();cotRiesgos.splice(Number(b.getAttribute('data-cot-remove-riesgo')),1);renderCotRiesgos();});
  document.getElementById('cot-add-alt').addEventListener('click',function(){cotReadAlternativas();cotAlternativas.push({uid:cotNewUid(),aseguradora_id:'',producto_id:'',nombre_plan_snapshot:'',orden_visual:cotAlternativas.length+1,vigencia_meses:'',vigencia_texto:'',suma_asegurada:'',moneda:'PEN',prima_comercial:'',igv:'',prima_total:'',condicion_gps:'no_requiere',es_aceptada:0,observaciones:'',cuotas:[]});renderCotAlternativas();});
  document.getElementById('cot-alt-body').addEventListener('change',function(e){var card=e.target.closest('.cot-alt-card');if(!card)return;if(e.target.classList.contains('cot-alt-aseg')){var prod=card.querySelector('.cot-alt-producto');prod.innerHTML=optionCotProductos(e.target.value,'');renderCotComparativos();}if(e.target.classList.contains('cot-alt-aceptada')&&e.target.checked){Array.prototype.forEach.call(document.querySelectorAll('.cot-alt-aceptada'),function(ch){if(ch!==e.target)ch.checked=false;});}if(e.target.classList.contains('cot-alt-aseg')||e.target.classList.contains('cot-alt-producto')||e.target.classList.contains('cot-alt-plan'))renderCotComparativos();});
  document.getElementById('cot-alt-body').addEventListener('click',function(e){var add=e.target.closest('[data-cot-add-cuota]');if(add){cotReadAlternativas();var uid=add.getAttribute('data-cot-add-cuota');cotAlternativas.forEach(function(a){if(a.uid===uid){a.cuotas=a.cuotas||[];a.cuotas.push({modalidad:'contado',cantidad_cuotas:1,valor_cuota:'',descripcion:'',orden_visual:a.cuotas.length+1});}});renderCotAlternativas();return;}var rem=e.target.closest('[data-cot-remove-alt]');if(rem){cotReadAlternativas();cotAlternativas=cotAlternativas.filter(function(a){return a.uid!==rem.getAttribute('data-cot-remove-alt');});renderCotAlternativas();return;}var remCuota=e.target.closest('[data-cot-remove-cuota]');if(!remCuota)return;var card=remCuota.closest('.cot-alt-card'), uid=card.dataset.uid, idx=Number(remCuota.getAttribute('data-cot-remove-cuota'));cotReadAlternativas();cotAlternativas.forEach(function(a){if(a.uid===uid)a.cuotas.splice(idx,1);});renderCotAlternativas();});
  document.getElementById('cot-add-comp').addEventListener('click',function(){cotReadComparativos();cotComparativos.push({seccion:'cobertura',etiqueta:'',detalle:'',orden_visual:cotComparativos.length+1,valores:{}});renderCotComparativos();});
  document.getElementById('cot-comp-body').addEventListener('click',function(e){var b=e.target.closest('[data-cot-remove-comp]');if(!b)return;cotReadComparativos();cotComparativos.splice(Number(b.getAttribute('data-cot-remove-comp')),1);renderCotComparativos();});
  document.getElementById('cot-refresh-preview').addEventListener('click',renderCotPreview);
  document.getElementById('cot-ver-pdf').addEventListener('click',function(){cotOpenPdf(false);});
  document.getElementById('cot-descargar-pdf').addEventListener('click',function(){cotOpenPdf(true);});
  document.getElementById('modalConfirmExpedienteOk').addEventListener('click',function(){var cb=confirmCallback;confirmCallback=null;$('#modalConfirmExpediente').modal('hide');if(typeof cb==='function')cb();});
  Promise.all([loadContext(),loadDocTipos(),loadReqEstados(),loadPolContext()]).then(load);
});
</script>
