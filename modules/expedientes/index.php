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
  .exp-loading,.exp-empty{display:none;padding:1rem;border:1px dashed #ced4da;border-radius:.25rem;text-align:center;color:#6c757d;background:#f8f9fa}
  .exp-actions{display:inline-flex;flex-direction:column;gap:.25rem}
  .exp-pagination .btn{min-width:36px}
  .exp-toast-zone{position:fixed;right:1rem;bottom:1rem;z-index:1080;width:min(360px,calc(100vw - 2rem))}
  @media(max-width:575.98px){.exp-detail-dialog{max-width:calc(100vw - .5rem);margin:.25rem}.exp-detail-dialog .modal-content{max-height:calc(100vh - .5rem)}}
  @media(max-width:1199.98px){.exp-toolbar{grid-template-columns:1fr 1fr}}
  @media(max-width:767.98px){.exp-toolbar{grid-template-columns:1fr}}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var csrf = <?php echo json_encode($csrfExpedientes); ?>;
  var permisos = <?php echo json_encode($permExpedientes); ?>;
  var endpoint = 'api/expedientes/expedientes.php';
  var documentosEndpoint = 'api/expedientes/documentos.php';
  var timelineEndpoint = 'api/expedientes/timeline.php';
  var rows = [], clientes = [], tipos = [], estados = [], estadoInicial = null, docTipos = [];
  var expedienteDetalleId = 0;
  var page = 1, timer = null, confirmCallback = null;

  function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[c];});}
  function toast(msg,type){var z=document.getElementById('exp-toast-zone'), t=document.createElement('div');t.className='alert alert-'+(type||'info')+' shadow-sm mb-2';t.textContent=msg;z.appendChild(t);setTimeout(function(){t.remove();},4200);}
  function fetchJson(url,opt){return fetch(url,opt||{}).then(function(r){return r.json().catch(function(){return{ok:false,message:'Respuesta no valida del servidor.'};}).then(function(j){if(!r.ok||!j.ok)throw j;return j;});});}
  function post(action,data){data.set('_csrf',csrf);return fetchJson(endpoint+'?accion='+encodeURIComponent(action),{method:'POST',body:data});}
  function postDocs(action,data){data.set('_csrf',csrf);return fetchJson(documentosEndpoint+'?accion='+encodeURIComponent(action),{method:'POST',body:data});}
  function badgeActivo(v){return Number(v)===1?'<span class="badge badge-success">Activo</span>':'<span class="badge badge-secondary">Inactivo</span>';}
  function badgeEstado(row){var color=row.color_etiqueta||'#6c757d';return '<span class="badge" style="background:'+esc(color)+';color:#fff">'+esc(row.estado_expediente_nombre||'-')+'</span>';}
  function bytes(v){var n=Number(v||0);if(n<1024)return n+' B';if(n<1048576)return (n/1024).toFixed(1)+' KB';return (n/1048576).toFixed(1)+' MB';}
  function resumen(v,max){v=String(v==null?'':v);return v.length>max?v.slice(0,max-1)+'...':v;}
  function params(){var p=new URLSearchParams();p.set('accion','listar');p.set('page',page);p.set('q',document.getElementById('exp-search').value||'');p.set('cliente_id',document.getElementById('exp-filtro-cliente').value||'0');p.set('tipo_seguro_id',document.getElementById('exp-filtro-tipo').value||'0');p.set('estado_expediente_id',document.getElementById('exp-filtro-estado-exp').value||'0');p.set('estado',document.getElementById('exp-filtro-activo').value||'todos');return p;}
  function optionClientes(selected){var h='<option value="">Seleccione cliente</option>';clientes.forEach(function(c){var tipo=c.tipo_cliente==='consorcio'?'Consorcio':'Empresa';var ruc=c.ruc?c.ruc+' - ':'';h+='<option value="'+c.id+'" '+(Number(selected)===Number(c.id)?'selected':'')+'>'+esc(ruc+c.razon_social+' ('+tipo+')')+'</option>';});return h;}
  function optionTipos(selected){var h='<option value="">Seleccione tipo</option>';tipos.forEach(function(t){h+='<option value="'+t.id+'" '+(Number(selected)===Number(t.id)?'selected':'')+'>'+esc((t.ramo_nombre?t.ramo_nombre+' / ':'')+t.nombre)+'</option>';});return h;}
  function optionEstados(selected){var h='<option value="">Seleccione estado</option>';estados.forEach(function(e){h+='<option value="'+e.id+'" '+(Number(selected)===Number(e.id)?'selected':'')+'>'+esc(e.nombre)+'</option>';});return h;}
  function fillOptions(){document.getElementById('exp-filtro-cliente').innerHTML='<option value="0">Todos los clientes</option>'+optionClientes(0).replace('<option value="">Seleccione cliente</option>','');document.getElementById('exp-filtro-tipo').innerHTML='<option value="0">Todos los tipos</option>'+optionTipos(0).replace('<option value="">Seleccione tipo</option>','');document.getElementById('exp-filtro-estado-exp').innerHTML='<option value="0">Todos los estados</option>'+optionEstados(0).replace('<option value="">Seleccione estado</option>','');var f=document.getElementById('formExpediente');f.elements.cliente_id.innerHTML=optionClientes();f.elements.tipo_seguro_id.innerHTML=optionTipos();f.elements.estado_expediente_id.innerHTML=optionEstados();}
  function fillDocTipos(){var form=document.getElementById('formExpDocumento');if(!form)return;var h='<option value="">Seleccione tipo</option>';docTipos.forEach(function(t){h+='<option value="'+esc(t.codigo)+'">'+esc(t.nombre)+'</option>';});form.elements.tipo_documento.innerHTML=h;}
  function loadDocTipos(){return fetchJson(documentosEndpoint+'?accion=tipos').then(function(r){docTipos=(r.data||{}).rows||[];fillDocTipos();}).catch(function(e){toast(e.message||'No se pudo cargar tipos de documento.','danger');});}
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
  function viewDetail(id){fetchJson(endpoint+'?accion=obtener&id='+encodeURIComponent(id)).then(function(r){var x=(r.data||{}).record||{};expedienteDetalleId=id;document.getElementById('exp-detalle-resumen').innerHTML='<dl class="row mb-0"><dt class="col-sm-3">Codigo</dt><dd class="col-sm-9">'+esc(x.codigo)+'</dd><dt class="col-sm-3">Cliente</dt><dd class="col-sm-9">'+esc(x.cliente_razon_social)+'<div class="text-muted small">'+esc(x.cliente_ruc||'Sin RUC')+'</div></dd><dt class="col-sm-3">Tipo</dt><dd class="col-sm-9">'+esc(x.tipo_seguro_nombre)+'</dd><dt class="col-sm-3">Estado</dt><dd class="col-sm-9">'+esc(x.estado_expediente_nombre)+'</dd><dt class="col-sm-3">Fecha apertura</dt><dd class="col-sm-9">'+esc(x.fecha_apertura)+'</dd><dt class="col-sm-3">Descripcion</dt><dd class="col-sm-9">'+esc(x.descripcion)+'</dd><dt class="col-sm-3">Observaciones</dt><dd class="col-sm-9">'+esc(x.observaciones||'-')+'</dd></dl>';var form=document.getElementById('formExpDocumento');if(form){form.reset();clearValidation(form);form.elements.expediente_id.value=id;}$('#expDetalleTabs a[href="#exp-tab-resumen"]').tab('show');loadDocs(id);loadActividad(id);$('#modalDetalleExpediente').modal('show');}).catch(function(e){toast(e.message||'No se pudo cargar detalle.','danger');});}
  function loadDocs(id){document.getElementById('exp-docs-body').innerHTML='<tr><td colspan="7" class="text-muted text-center">Cargando documentos...</td></tr>';document.getElementById('exp-docs-empty').style.display='none';fetchJson(documentosEndpoint+'?accion=listar&expediente_id='+encodeURIComponent(id)).then(function(r){renderDocs((r.data||{}).rows||[]);}).catch(function(e){document.getElementById('exp-docs-body').innerHTML='';document.getElementById('exp-docs-empty').style.display='block';toast(e.message||'No se pudieron cargar documentos.','danger');});}
  function renderDocs(items){var h='';items.forEach(function(d){var activo=Number(d.vinculo_estado)===1&&Number(d.archivo_estado)===1;var acciones='<span class="exp-actions"><a class="btn btn-xs btn-outline-info" href="'+documentosEndpoint+'?accion=descargar&vinculo_id='+encodeURIComponent(d.vinculo_id)+'" title="Descargar" aria-label="Descargar"><i class="fas fa-download"></i></a>';if(permisos.puede_editar&&activo){acciones+='<button type="button" class="btn btn-xs btn-outline-secondary" data-doc-archive="'+esc(d.vinculo_id)+'" title="Archivar" aria-label="Archivar"><i class="fas fa-archive"></i></button>';}acciones+='</span>';h+='<tr><td>'+esc(d.tipo_documento_nombre||d.slot||'-')+'</td><td><strong>'+esc(d.nombre_original||'-')+'</strong><div class="text-muted small">'+esc(d.mime_type||'')+' '+esc(bytes(d.tamanio_bytes))+'</div></td><td>'+esc(d.descripcion||'-')+'</td><td>'+esc(d.cargado_en||'-')+'</td><td>'+esc(d.cargado_por_usuario_externo_id||'-')+'</td><td>'+badgeActivo(activo?1:0)+'</td><td class="text-center">'+acciones+'</td></tr>';});document.getElementById('exp-docs-body').innerHTML=h;document.getElementById('exp-docs-empty').style.display=items.length?'none':'block';}
  function loadActividad(id){document.getElementById('exp-actividad-body').innerHTML='<div class="text-muted text-center py-2">Cargando actividad...</div>';document.getElementById('exp-actividad-empty').style.display='none';fetchJson(timelineEndpoint+'?accion=listar&expediente_id='+encodeURIComponent(id)).then(function(r){renderActividad((r.data||{}).rows||[]);}).catch(function(e){document.getElementById('exp-actividad-body').innerHTML='';document.getElementById('exp-actividad-empty').style.display='block';toast(e.message||'No se pudo cargar actividad.','danger');});}
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
  document.getElementById('modalConfirmExpedienteOk').addEventListener('click',function(){var cb=confirmCallback;confirmCallback=null;$('#modalConfirmExpediente').modal('hide');if(typeof cb==='function')cb();});
  Promise.all([loadContext(),loadDocTipos()]).then(load);
});
</script>
