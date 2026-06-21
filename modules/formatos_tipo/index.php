<?php
require_once __DIR__ . '/../../includes/module_guard.php';
cb_require_module_context('formatos_tipo');

$csrfFormatosTipo = cb_local_csrf_token('formatos_tipo');
$permFormatosTipo = [
    'puede_crear' => cb_cliente_puede('formatos_tipo', 'puede_crear'),
    'puede_editar' => cb_cliente_puede('formatos_tipo', 'puede_editar'),
    'puede_eliminar' => cb_cliente_puede('formatos_tipo', 'puede_eliminar'),
];
?>
<div class="formatos-tipo-module">
  <div class="row mb-3">
    <div class="col-12">
      <h1 class="h4 mb-1">Formatos por tipo de seguro</h1>
      <p class="text-muted mb-0">Archivos descargables reutilizables para cada tipo de seguro y sus requisitos.</p>
    </div>
  </div>

  <div class="card card-primary card-outline">
    <div class="card-body">
      <div class="fmt-toolbar">
        <div class="input-group input-group-sm">
          <input type="search" class="form-control" id="fmt-search" placeholder="Buscar por codigo, nombre o descripcion">
          <div class="input-group-append">
            <button class="btn btn-outline-secondary" type="button" id="fmt-btn-buscar" title="Buscar" aria-label="Buscar"><i class="fas fa-search"></i></button>
          </div>
        </div>
        <select class="form-control form-control-sm" id="fmt-filtro-tipo"><option value="0">Todos los tipos</option></select>
        <select class="form-control form-control-sm" id="fmt-filtro-estado">
          <option value="todos">Todos</option>
          <option value="activo">Activos</option>
          <option value="inactivo">Inactivos</option>
        </select>
        <button class="btn btn-outline-secondary btn-sm" type="button" id="fmt-btn-limpiar"><i class="fas fa-eraser"></i> Limpiar</button>
        <?php if ($permFormatosTipo['puede_crear']): ?>
          <button class="btn btn-primary btn-sm" type="button" id="fmt-btn-nuevo"><i class="fas fa-plus"></i> Registrar formato</button>
        <?php endif; ?>
      </div>

      <div class="alert alert-warning py-2" id="fmt-sin-tipos" style="display:none;">
        No hay tipos de seguro activos. Configura primero los tipos desde Catalogos.
      </div>

      <div class="text-muted small mb-2" id="fmt-counter">0 registros</div>
      <div class="table-responsive">
        <table class="table table-bordered table-hover table-sm fmt-table">
          <thead>
            <tr>
              <th class="fmt-col-codigo">Codigo</th>
              <th class="fmt-col-tipo">Tipo de seguro</th>
              <th class="fmt-col-nombre">Formato</th>
              <th class="fmt-col-descripcion">Descripcion</th>
              <th class="fmt-col-requisito">Requisito relacionado</th>
              <th class="fmt-col-archivo">Archivo</th>
              <th class="fmt-col-orden">Orden</th>
              <th class="fmt-col-estado">Estado</th>
              <th class="text-center" style="width:96px;">Acciones</th>
            </tr>
          </thead>
          <tbody id="fmt-body"></tbody>
        </table>
      </div>
      <div class="fmt-loading" id="fmt-loading">Cargando formatos...</div>
      <div class="fmt-empty" id="fmt-empty">Todavia no hay formatos registrados.</div>
      <div class="fmt-pagination" id="fmt-pagination"></div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalFormatoTipo" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <form class="modal-content" id="formFormatoTipo" enctype="multipart/form-data" autocomplete="off" novalidate>
      <div class="modal-header">
        <h5 class="modal-title" id="modalFormatoTipoTitle">Formato</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id">
        <div class="form-row">
          <div class="form-group col-md-8">
            <label>Tipo de seguro</label>
            <select class="form-control" name="tipo_seguro_id" required></select>
          </div>
          <div class="form-group col-md-4">
            <label>Estado</label>
            <select class="form-control" name="estado">
              <option value="1">Activo</option>
              <option value="0">Inactivo</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-8">
            <label>Nombre del formato</label>
            <input class="form-control" name="nombre" maxlength="180" required>
          </div>
          <div class="form-group col-md-4">
            <label>Orden visual</label>
            <input class="form-control" name="orden_visual" type="number" step="1" value="0">
          </div>
        </div>
        <div class="form-group">
          <label>Requisito relacionado</label>
          <select class="form-control" name="requisito_tipo_seguro_id">
            <option value="">Sin requisito relacionado</option>
          </select>
          <small class="text-muted">Solo se muestran requisitos activos del tipo de seguro seleccionado.</small>
        </div>
        <div class="form-group">
          <label>Descripcion</label>
          <textarea class="form-control" name="descripcion" rows="2" maxlength="1000"></textarea>
        </div>
        <div class="form-group mb-0" id="fmt-archivo-group">
          <label>Archivo principal</label>
          <input class="form-control" name="archivo" type="file">
          <small class="text-muted" id="fmt-archivo-help">Al crear el formato debes cargar su archivo principal.</small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="modalFormatoArchivo" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form class="modal-content" id="formFormatoArchivo" enctype="multipart/form-data" autocomplete="off" novalidate>
      <div class="modal-header">
        <h5 class="modal-title">Reemplazar archivo principal</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id">
        <div class="form-group">
          <label>Formato</label>
          <input class="form-control" id="fmt-archivo-nombre" readonly>
        </div>
        <div class="form-group mb-0">
          <label>Nuevo archivo</label>
          <input class="form-control" name="archivo" type="file" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Reemplazar</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="modalConfirmFormatoTipo" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Confirmar accion</h5><button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button></div>
      <div class="modal-body" id="modalConfirmFormatoTipoText"></div>
      <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button><button type="button" class="btn btn-primary" id="modalConfirmFormatoTipoOk">Confirmar</button></div>
    </div>
  </div>
</div>

<div class="fmt-toast-zone" id="fmt-toast-zone"></div>

<style>
  .fmt-toolbar{display:grid;gap:.5rem;grid-template-columns:minmax(260px,1fr) minmax(240px,320px) 140px auto auto;align-items:center;margin-bottom:.75rem}
  .formatos-tipo-module .table-responsive{overflow-x:auto}
  .fmt-table{min-width:1320px;table-layout:auto}
  .fmt-table th,.fmt-table td{vertical-align:middle}
  .fmt-col-codigo{width:185px}
  .fmt-col-tipo{min-width:250px}
  .fmt-col-nombre{min-width:220px}
  .fmt-col-descripcion{min-width:260px}
  .fmt-col-requisito{min-width:230px}
  .fmt-col-archivo{min-width:220px}
  .fmt-col-orden{width:90px}
  .fmt-col-estado{width:100px}
  .fmt-text-clip{display:block;max-width:420px;white-space:normal;line-height:1.25}
  .fmt-loading,.fmt-empty{display:none;padding:1rem;border:1px dashed #ced4da;border-radius:.25rem;text-align:center;color:#6c757d;background:#f8f9fa}
  .fmt-actions{display:inline-flex;flex-direction:column;gap:.25rem}
  .fmt-pagination .btn{min-width:36px}
  .fmt-toast-zone{position:fixed;right:1rem;bottom:1rem;z-index:1080;width:min(360px,calc(100vw - 2rem))}
  @media(max-width:1199.98px){.fmt-toolbar{grid-template-columns:1fr 1fr}}
  @media(max-width:767.98px){.fmt-toolbar{grid-template-columns:1fr}}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var csrf = <?php echo json_encode($csrfFormatosTipo); ?>;
  var permisos = <?php echo json_encode($permFormatosTipo); ?>;
  var endpoint = 'api/formatos_tipo/formatos.php';
  var tipos = [], requisitos = [], rows = [], page = 1, timer = null, confirmCallback = null;

  function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[c];});}
  function resumen(v,max){v=String(v==null?'':v);return v.length>max?v.slice(0,max-1)+'...':v;}
  function toast(msg,type){var z=document.getElementById('fmt-toast-zone'), t=document.createElement('div');t.className='alert alert-'+(type||'info')+' shadow-sm mb-2';t.textContent=msg;z.appendChild(t);setTimeout(function(){t.remove();},4200);}
  function fetchJson(url,opt){return fetch(url,opt||{}).then(function(r){return r.json().catch(function(){return{ok:false,message:'Respuesta no valida del servidor.'};}).then(function(j){if(!r.ok||!j.ok)throw j;return j;});});}
  function post(action,data){data.set('_csrf',csrf);return fetchJson(endpoint+'?accion='+encodeURIComponent(action),{method:'POST',body:data});}
  function badgeEstado(v){return Number(v)===1?'<span class="badge badge-success">Activo</span>':'<span class="badge badge-secondary">Inactivo</span>';}
  function optionTipos(selected){var h='<option value="">Seleccione tipo de seguro</option>';tipos.forEach(function(t){h+='<option value="'+t.id+'" '+(Number(selected)===Number(t.id)?'selected':'')+'>'+esc((t.ramo_nombre?t.ramo_nombre+' / ':'')+t.nombre)+'</option>';});return h;}
  function optionRequisitos(tipoId,selected){var h='<option value="">Sin requisito relacionado</option>';requisitos.forEach(function(r){if(Number(r.tipo_seguro_id)!==Number(tipoId))return;h+='<option value="'+r.id+'" '+(Number(selected)===Number(r.id)?'selected':'')+'>'+esc(r.nombre)+'</option>';});return h;}
  function fillOptions(){document.getElementById('fmt-filtro-tipo').innerHTML='<option value="0">Todos los tipos</option>'+optionTipos(0).replace('<option value="">Seleccione tipo de seguro</option>','');var f=document.getElementById('formFormatoTipo');f.elements.tipo_seguro_id.innerHTML=optionTipos();f.elements.requisito_tipo_seguro_id.innerHTML=optionRequisitos(0,0);document.getElementById('fmt-sin-tipos').style.display=tipos.length?'none':'block';var btn=document.getElementById('fmt-btn-nuevo');if(btn)btn.disabled=!tipos.length;}
  function loadContext(){return fetchJson(endpoint+'?accion=contexto').then(function(r){var d=r.data||{};tipos=d.tipos_seguro||[];requisitos=d.requisitos||[];csrf=d.csrf||csrf;fillOptions();}).catch(function(e){toast(e.message||'No se pudo cargar contexto.','danger');});}
  function params(){var p=new URLSearchParams();p.set('accion','listar');p.set('page',page);p.set('q',document.getElementById('fmt-search').value||'');p.set('tipo_seguro_id',document.getElementById('fmt-filtro-tipo').value||'0');p.set('estado',document.getElementById('fmt-filtro-estado').value||'todos');return p;}
  function load(){document.getElementById('fmt-loading').style.display='block';fetchJson(endpoint+'?'+params().toString()).then(function(r){var d=r.data||{};rows=d.rows||[];renderRows();renderPagination(d.pagination||{});}).catch(function(e){toast(e.message||'No se pudo cargar formatos.','danger');}).finally(function(){document.getElementById('fmt-loading').style.display='none';});}
  function renderRows(){var h='';rows.forEach(function(r){var tipo='<strong class="fmt-text-clip" title="'+esc((r.ramo_nombre?r.ramo_nombre+' / ':'')+r.tipo_seguro_nombre)+'">'+esc(resumen((r.ramo_nombre?r.ramo_nombre+' / ':'')+r.tipo_seguro_nombre,100))+'</strong>';var archivo=r.archivo_vinculo_id?'<a href="'+endpoint+'?accion=descargar&vinculo_id='+encodeURIComponent(r.archivo_vinculo_id)+'" title="Descargar">'+esc(resumen(r.archivo_nombre||'Archivo principal',70))+'</a>':'<span class="text-danger">Sin archivo activo</span>';h+='<tr><td><strong>'+esc(r.codigo)+'</strong></td><td>'+tipo+'</td><td><span class="fmt-text-clip" title="'+esc(r.nombre||'')+'">'+esc(resumen(r.nombre||'',90))+'</span></td><td><span class="fmt-text-clip" title="'+esc(r.descripcion||'')+'">'+esc(resumen(r.descripcion||'-',120))+'</span></td><td>'+esc(r.requisito_nombre||'-')+'</td><td>'+archivo+'</td><td>'+esc(r.orden_visual)+'</td><td>'+badgeEstado(r.estado)+'</td><td class="text-center">'+actions(r)+'</td></tr>';});document.getElementById('fmt-body').innerHTML=h;document.getElementById('fmt-empty').style.display=rows.length?'none':'block';}
  function actions(row){var h='<span class="fmt-actions">';if(permisos.puede_editar){h+='<button type="button" class="btn btn-xs btn-outline-primary" data-action="edit" data-id="'+row.id+'" title="Editar" aria-label="Editar"><i class="fas fa-edit"></i></button><button type="button" class="btn btn-xs btn-outline-success" data-action="archivo" data-id="'+row.id+'" title="Reemplazar archivo" aria-label="Reemplazar archivo"><i class="fas fa-upload"></i></button>';}if(row.archivo_vinculo_id){h+='<a class="btn btn-xs btn-outline-info" href="'+endpoint+'?accion=descargar&vinculo_id='+encodeURIComponent(row.archivo_vinculo_id)+'" title="Descargar" aria-label="Descargar"><i class="fas fa-download"></i></a>';}if(permisos.puede_eliminar){var title=Number(row.estado)===1?'Desactivar':'Activar';h+='<button type="button" class="btn btn-xs btn-outline-secondary" data-action="toggle" data-id="'+row.id+'" data-state="'+row.estado+'" title="'+title+'" aria-label="'+title+'"><i class="fas '+(Number(row.estado)===1?'fa-toggle-on':'fa-toggle-off')+'"></i></button>';}return h+'</span>';}
  function renderPagination(pag){var total=Number(pag.total||0),cur=Number(pag.page||1),last=Number(pag.last_page||1),wrap=document.getElementById('fmt-pagination');document.getElementById('fmt-counter').textContent=total+(total===1?' registro':' registros');if(last<=1){wrap.innerHTML='';return;}wrap.innerHTML='<div class="btn-group btn-group-sm"><button type="button" class="btn btn-outline-secondary" data-page="'+(cur-1)+'" '+(cur<=1?'disabled':'')+' title="Anterior" aria-label="Anterior"><i class="fas fa-chevron-left"></i></button><button type="button" class="btn btn-outline-secondary" disabled>'+cur+' / '+last+'</button><button type="button" class="btn btn-outline-secondary" data-page="'+(cur+1)+'" '+(cur>=last?'disabled':'')+' title="Siguiente" aria-label="Siguiente"><i class="fas fa-chevron-right"></i></button></div>';}
  function clearValidation(form){form.classList.remove('was-validated');Array.prototype.forEach.call(form.querySelectorAll('.is-invalid'),function(el){el.classList.remove('is-invalid');});Array.prototype.forEach.call(form.querySelectorAll('.invalid-feedback.dynamic'),function(el){el.remove();});}
  function applyErrors(form,errors){Object.keys(errors||{}).forEach(function(k){var input=form.querySelector('[name="'+k+'"]');if(!input)return;input.classList.add('is-invalid');var f=document.createElement('div');f.className='invalid-feedback dynamic';f.textContent=String(errors[k]);input.parentNode.appendChild(f);});}
  function setVal(form,name,value){if(form.elements[name])form.elements[name].value=value==null?'':value;}
  function openModal(id){var f=document.getElementById('formFormatoTipo');f.reset();clearValidation(f);f.dataset.mode=id?'edit':'create';f.elements.id.value='';f.elements.estado.value='1';f.elements.orden_visual.value='0';f.elements.archivo.required=!id;document.getElementById('fmt-archivo-group').style.display=id?'none':'block';document.getElementById('modalFormatoTipoTitle').textContent=id?'Editar formato':'Registrar formato';if(!id){f.elements.requisito_tipo_seguro_id.innerHTML=optionRequisitos(0,0);$('#modalFormatoTipo').modal('show');return;}fetchJson(endpoint+'?accion=obtener&id='+encodeURIComponent(id)).then(function(r){var rec=(r.data||{}).record||{};['id','tipo_seguro_id','requisito_tipo_seguro_id','nombre','descripcion','orden_visual','estado'].forEach(function(k){setVal(f,k,rec[k]);});f.elements.requisito_tipo_seguro_id.innerHTML=optionRequisitos(rec.tipo_seguro_id,rec.requisito_tipo_seguro_id);$('#modalFormatoTipo').modal('show');}).catch(function(e){toast(e.message||'No se pudo cargar el formato.','danger');});}
  function save(form){if(!form.checkValidity()){form.classList.add('was-validated');return;}clearValidation(form);var creating=form.dataset.mode!=='edit';post(creating?'crear':'actualizar',new FormData(form)).then(function(r){$('#modalFormatoTipo').modal('hide');toast(r.message||'Formato guardado.','success');loadContext().then(load);}).catch(function(e){applyErrors(form,e.errors||{});toast(e.message||'No se pudo guardar el formato.','danger');});}
  function rowById(id){for(var i=0;i<rows.length;i++){if(Number(rows[i].id)===Number(id))return rows[i];}return null;}
  function openArchivo(id){var r=rowById(id);if(!r)return;var f=document.getElementById('formFormatoArchivo');f.reset();clearValidation(f);f.elements.id.value=id;document.getElementById('fmt-archivo-nombre').value=r.nombre||'';$('#modalFormatoArchivo').modal('show');}
  function saveArchivo(form){if(!form.checkValidity()){form.classList.add('was-validated');return;}clearValidation(form);post('reemplazar_archivo',new FormData(form)).then(function(r){$('#modalFormatoArchivo').modal('hide');toast(r.message||'Archivo reemplazado.','success');load();}).catch(function(e){applyErrors(form,e.errors||{});toast(e.message||'No se pudo reemplazar el archivo.','danger');});}
  function confirmAction(text,cb){document.getElementById('modalConfirmFormatoTipoText').textContent=text;confirmCallback=cb;$('#modalConfirmFormatoTipo').modal('show');}
  function toggle(id){var data=new FormData();data.set('id',id);post('cambiar_estado',data).then(function(r){toast(r.message||'Estado actualizado.','success');load();}).catch(function(e){toast(e.message||'No se pudo actualizar estado.','danger');});}

  document.getElementById('fmt-btn-buscar').addEventListener('click',function(){page=1;load();});
  document.getElementById('fmt-btn-limpiar').addEventListener('click',function(){document.getElementById('fmt-search').value='';document.getElementById('fmt-filtro-tipo').value='0';document.getElementById('fmt-filtro-estado').value='todos';page=1;load();});
  var btnNuevo=document.getElementById('fmt-btn-nuevo');if(btnNuevo)btnNuevo.addEventListener('click',function(){openModal(null);});
  ['fmt-search','fmt-filtro-tipo','fmt-filtro-estado'].forEach(function(id){document.getElementById(id).addEventListener(id==='fmt-search'?'input':'change',function(){clearTimeout(timer);timer=setTimeout(function(){page=1;load();},id==='fmt-search'?300:0);});});
  document.getElementById('formFormatoTipo').elements.tipo_seguro_id.addEventListener('change',function(){var f=document.getElementById('formFormatoTipo');f.elements.requisito_tipo_seguro_id.innerHTML=optionRequisitos(this.value,f.elements.requisito_tipo_seguro_id.value);});
  document.getElementById('fmt-pagination').addEventListener('click',function(e){var b=e.target.closest('[data-page]');if(!b)return;page=Number(b.dataset.page)||1;load();});
  document.getElementById('fmt-body').addEventListener('click',function(e){var b=e.target.closest('[data-action]');if(!b)return;var id=b.dataset.id;if(b.dataset.action==='edit')openModal(id);else if(b.dataset.action==='archivo')openArchivo(id);else confirmAction(Number(b.dataset.state)===1?'Desea desactivar este formato?':'Desea activar este formato?',function(){toggle(id);});});
  document.getElementById('formFormatoTipo').addEventListener('submit',function(e){e.preventDefault();save(e.target);});
  document.getElementById('formFormatoArchivo').addEventListener('submit',function(e){e.preventDefault();saveArchivo(e.target);});
  document.getElementById('modalConfirmFormatoTipoOk').addEventListener('click',function(){var cb=confirmCallback;confirmCallback=null;$('#modalConfirmFormatoTipo').modal('hide');if(typeof cb==='function')cb();});
  loadContext().then(load);
});
</script>
