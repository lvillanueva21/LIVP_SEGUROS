<?php
require_once __DIR__ . '/../../includes/module_guard.php';
cb_require_module_context('requisitos_tipo');

$csrfRequisitosTipo = cb_local_csrf_token('requisitos_tipo');
$permRequisitosTipo = [
    'puede_crear' => cb_cliente_puede('requisitos_tipo', 'puede_crear'),
    'puede_editar' => cb_cliente_puede('requisitos_tipo', 'puede_editar'),
    'puede_eliminar' => cb_cliente_puede('requisitos_tipo', 'puede_eliminar'),
];
?>
<div class="requisitos-tipo-module">
  <div class="row mb-3">
    <div class="col-12">
      <h1 class="h4 mb-1">Requisitos por tipo de seguro</h1>
      <p class="text-muted mb-0">Configuracion reutilizable de requisitos que se pediran segun el tipo de seguro.</p>
    </div>
  </div>

  <div class="card card-primary card-outline">
    <div class="card-body">
      <div class="req-toolbar">
        <div class="input-group input-group-sm">
          <input type="search" class="form-control" id="req-search" placeholder="Buscar por codigo, nombre o descripcion">
          <div class="input-group-append">
            <button class="btn btn-outline-secondary" type="button" id="req-btn-buscar" title="Buscar" aria-label="Buscar"><i class="fas fa-search"></i></button>
          </div>
        </div>
        <select class="form-control form-control-sm" id="req-filtro-tipo"><option value="0">Todos los tipos</option></select>
        <select class="form-control form-control-sm" id="req-filtro-estado">
          <option value="todos">Todos</option>
          <option value="activo">Activos</option>
          <option value="inactivo">Inactivos</option>
        </select>
        <button class="btn btn-outline-secondary btn-sm" type="button" id="req-btn-limpiar"><i class="fas fa-eraser"></i> Limpiar</button>
        <?php if ($permRequisitosTipo['puede_crear']): ?>
          <button class="btn btn-primary btn-sm" type="button" id="req-btn-nuevo"><i class="fas fa-plus"></i> Registrar requisito</button>
        <?php endif; ?>
      </div>

      <div class="alert alert-warning py-2" id="req-sin-tipos" style="display:none;">
        No hay tipos de seguro activos. Configura primero los tipos desde Catalogos.
      </div>

      <div class="text-muted small mb-2" id="req-counter">0 registros</div>
      <div class="table-responsive">
        <table class="table table-bordered table-hover table-sm req-table">
          <thead>
            <tr>
              <th class="req-col-codigo">Codigo</th>
              <th class="req-col-tipo">Tipo de seguro</th>
              <th class="req-col-nombre">Requisito</th>
              <th class="req-col-descripcion">Descripcion</th>
              <th class="req-col-obligatorio">Condicion</th>
              <th class="req-col-orden">Orden</th>
              <th class="req-col-estado">Estado</th>
              <th class="text-center" style="width:86px;">Acciones</th>
            </tr>
          </thead>
          <tbody id="req-body"></tbody>
        </table>
      </div>
      <div class="req-loading" id="req-loading">Cargando requisitos...</div>
      <div class="req-empty" id="req-empty">Todavia no hay requisitos registrados.</div>
      <div class="req-pagination" id="req-pagination"></div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalReqTipo" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <form class="modal-content" id="formReqTipo" autocomplete="off" novalidate>
      <div class="modal-header">
        <h5 class="modal-title" id="modalReqTipoTitle">Requisito</h5>
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
            <label>Nombre del requisito</label>
            <input class="form-control" name="nombre" maxlength="180" required>
          </div>
          <div class="form-group col-md-4">
            <label>Orden visual</label>
            <input class="form-control" name="orden_visual" type="number" step="1" value="0">
          </div>
        </div>
        <div class="form-row align-items-end">
          <div class="form-group col-md-4">
            <div class="custom-control custom-switch mt-2">
              <input type="checkbox" class="custom-control-input" id="req-es-obligatorio" name="es_obligatorio" value="1" checked>
              <label class="custom-control-label" for="req-es-obligatorio">Requisito obligatorio</label>
            </div>
          </div>
          <div class="form-group col-md-8">
            <label>Descripcion</label>
            <input class="form-control" name="descripcion" maxlength="1000">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="modalConfirmReqTipo" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Confirmar accion</h5><button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button></div>
      <div class="modal-body" id="modalConfirmReqTipoText"></div>
      <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button><button type="button" class="btn btn-primary" id="modalConfirmReqTipoOk">Confirmar</button></div>
    </div>
  </div>
</div>

<div class="req-toast-zone" id="req-toast-zone"></div>

<style>
  .req-toolbar{display:grid;gap:.5rem;grid-template-columns:minmax(260px,1fr) minmax(240px,320px) 140px auto auto;align-items:center;margin-bottom:.75rem}
  .req-table{min-width:1120px;table-layout:auto}
  .req-table th,.req-table td{vertical-align:middle}
  .req-col-codigo{width:185px}
  .req-col-tipo{min-width:250px}
  .req-col-nombre{min-width:230px}
  .req-col-descripcion{min-width:280px}
  .req-col-obligatorio{width:120px}
  .req-col-orden{width:90px}
  .req-col-estado{width:100px}
  .req-text-clip{display:block;max-width:420px;white-space:normal;line-height:1.25}
  .req-loading,.req-empty{display:none;padding:1rem;border:1px dashed #ced4da;border-radius:.25rem;text-align:center;color:#6c757d;background:#f8f9fa}
  .req-actions{display:inline-flex;flex-direction:column;gap:.25rem}
  .req-pagination .btn{min-width:36px}
  .req-toast-zone{position:fixed;right:1rem;bottom:1rem;z-index:1080;width:min(360px,calc(100vw - 2rem))}
  @media(max-width:1199.98px){.req-toolbar{grid-template-columns:1fr 1fr}}
  @media(max-width:767.98px){.req-toolbar{grid-template-columns:1fr}}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var csrf = <?php echo json_encode($csrfRequisitosTipo); ?>;
  var permisos = <?php echo json_encode($permRequisitosTipo); ?>;
  var endpoint = 'api/requisitos_tipo/requisitos.php';
  var tipos = [], rows = [], page = 1, timer = null, confirmCallback = null;

  function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[c];});}
  function resumen(v,max){v=String(v==null?'':v);return v.length>max?v.slice(0,max-1)+'...':v;}
  function toast(msg,type){var z=document.getElementById('req-toast-zone'), t=document.createElement('div');t.className='alert alert-'+(type||'info')+' shadow-sm mb-2';t.textContent=msg;z.appendChild(t);setTimeout(function(){t.remove();},4200);}
  function fetchJson(url,opt){return fetch(url,opt||{}).then(function(r){return r.json().catch(function(){return{ok:false,message:'Respuesta no valida del servidor.'};}).then(function(j){if(!r.ok||!j.ok)throw j;return j;});});}
  function post(action,data){data.set('_csrf',csrf);return fetchJson(endpoint+'?accion='+encodeURIComponent(action),{method:'POST',body:data});}
  function badgeEstado(v){return Number(v)===1?'<span class="badge badge-success">Activo</span>':'<span class="badge badge-secondary">Inactivo</span>';}
  function badgeObligatorio(v){return Number(v)===1?'<span class="badge badge-danger">Obligatorio</span>':'<span class="badge badge-info">Opcional</span>';}
  function optionTipos(selected){var h='<option value="">Seleccione tipo de seguro</option>';tipos.forEach(function(t){h+='<option value="'+t.id+'" '+(Number(selected)===Number(t.id)?'selected':'')+'>'+esc((t.ramo_nombre?t.ramo_nombre+' / ':'')+t.nombre)+'</option>';});return h;}
  function fillOptions(){document.getElementById('req-filtro-tipo').innerHTML='<option value="0">Todos los tipos</option>'+optionTipos(0).replace('<option value="">Seleccione tipo de seguro</option>','');document.getElementById('formReqTipo').elements.tipo_seguro_id.innerHTML=optionTipos();document.getElementById('req-sin-tipos').style.display=tipos.length?'none':'block';var btn=document.getElementById('req-btn-nuevo');if(btn)btn.disabled=!tipos.length;}
  function loadContext(){return fetchJson(endpoint+'?accion=contexto').then(function(r){var d=r.data||{};tipos=d.tipos_seguro||[];csrf=d.csrf||csrf;fillOptions();}).catch(function(e){toast(e.message||'No se pudo cargar contexto.','danger');});}
  function params(){var p=new URLSearchParams();p.set('accion','listar');p.set('page',page);p.set('q',document.getElementById('req-search').value||'');p.set('tipo_seguro_id',document.getElementById('req-filtro-tipo').value||'0');p.set('estado',document.getElementById('req-filtro-estado').value||'todos');return p;}
  function load(){document.getElementById('req-loading').style.display='block';fetchJson(endpoint+'?'+params().toString()).then(function(r){var d=r.data||{};rows=d.rows||[];renderRows();renderPagination(d.pagination||{});}).catch(function(e){toast(e.message||'No se pudo cargar requisitos.','danger');}).finally(function(){document.getElementById('req-loading').style.display='none';});}
  function renderRows(){var h='';rows.forEach(function(r){var tipo='<strong class="req-text-clip" title="'+esc((r.ramo_nombre?r.ramo_nombre+' / ':'')+r.tipo_seguro_nombre)+'">'+esc(resumen((r.ramo_nombre?r.ramo_nombre+' / ':'')+r.tipo_seguro_nombre,100))+'</strong>';var nombre='<span class="req-text-clip" title="'+esc(r.nombre||'')+'">'+esc(resumen(r.nombre||'',100))+'</span>';var descripcion='<span class="req-text-clip" title="'+esc(r.descripcion||'')+'">'+esc(resumen(r.descripcion||'-',130))+'</span>';h+='<tr><td><strong>'+esc(r.codigo)+'</strong></td><td>'+tipo+'</td><td>'+nombre+'</td><td>'+descripcion+'</td><td>'+badgeObligatorio(r.es_obligatorio)+'</td><td>'+esc(r.orden_visual)+'</td><td>'+badgeEstado(r.estado)+'</td><td class="text-center">'+actions(r)+'</td></tr>';});document.getElementById('req-body').innerHTML=h;document.getElementById('req-empty').style.display=rows.length?'none':'block';}
  function actions(row){var h='<span class="req-actions">', any=false;if(permisos.puede_editar){any=true;h+='<button type="button" class="btn btn-xs btn-outline-primary" data-action="edit" data-id="'+row.id+'" title="Editar" aria-label="Editar"><i class="fas fa-edit"></i></button>';}if(permisos.puede_eliminar){any=true;var title=Number(row.estado)===1?'Desactivar':'Activar';h+='<button type="button" class="btn btn-xs btn-outline-secondary" data-action="toggle" data-id="'+row.id+'" data-state="'+row.estado+'" title="'+title+'" aria-label="'+title+'"><i class="fas '+(Number(row.estado)===1?'fa-toggle-on':'fa-toggle-off')+'"></i></button>';}return any?h+'</span>':'<span class="text-muted">-</span>';}
  function renderPagination(pag){var total=Number(pag.total||0),cur=Number(pag.page||1),last=Number(pag.last_page||1),wrap=document.getElementById('req-pagination');document.getElementById('req-counter').textContent=total+(total===1?' registro':' registros');if(last<=1){wrap.innerHTML='';return;}wrap.innerHTML='<div class="btn-group btn-group-sm"><button type="button" class="btn btn-outline-secondary" data-page="'+(cur-1)+'" '+(cur<=1?'disabled':'')+' title="Anterior" aria-label="Anterior"><i class="fas fa-chevron-left"></i></button><button type="button" class="btn btn-outline-secondary" disabled>'+cur+' / '+last+'</button><button type="button" class="btn btn-outline-secondary" data-page="'+(cur+1)+'" '+(cur>=last?'disabled':'')+' title="Siguiente" aria-label="Siguiente"><i class="fas fa-chevron-right"></i></button></div>';}
  function clearValidation(form){form.classList.remove('was-validated');Array.prototype.forEach.call(form.querySelectorAll('.is-invalid'),function(el){el.classList.remove('is-invalid');});Array.prototype.forEach.call(form.querySelectorAll('.invalid-feedback.dynamic'),function(el){el.remove();});}
  function applyErrors(form,errors){Object.keys(errors||{}).forEach(function(k){var input=form.querySelector('[name="'+k+'"]');if(!input)return;input.classList.add('is-invalid');var f=document.createElement('div');f.className='invalid-feedback dynamic';f.textContent=String(errors[k]);input.parentNode.appendChild(f);});}
  function setVal(form,name,value){if(form.elements[name])form.elements[name].value=value==null?'':value;}
  function openModal(id){var form=document.getElementById('formReqTipo');form.reset();clearValidation(form);form.elements.id.value='';form.elements.estado.value='1';form.elements.orden_visual.value='0';form.elements.es_obligatorio.checked=true;document.getElementById('modalReqTipoTitle').textContent=id?'Editar requisito':'Registrar requisito';if(!id){$('#modalReqTipo').modal('show');return;}fetchJson(endpoint+'?accion=obtener&id='+encodeURIComponent(id)).then(function(r){var rec=(r.data||{}).record||{};['id','tipo_seguro_id','nombre','descripcion','orden_visual','estado'].forEach(function(k){setVal(form,k,rec[k]);});form.elements.es_obligatorio.checked=Number(rec.es_obligatorio)===1;$('#modalReqTipo').modal('show');}).catch(function(e){toast(e.message||'No se pudo cargar el requisito.','danger');});}
  function save(form){if(!form.checkValidity()){form.classList.add('was-validated');return;}clearValidation(form);var data=new FormData(form);if(!form.elements.es_obligatorio.checked){data.set('es_obligatorio','0');}var creating=!form.elements.id.value;post(creating?'crear':'actualizar',data).then(function(r){$('#modalReqTipo').modal('hide');toast(r.message||'Requisito guardado.','success');loadContext().then(load);}).catch(function(e){applyErrors(form,e.errors||{});toast(e.message||'No se pudo guardar el requisito.','danger');});}
  function confirmAction(text,cb){document.getElementById('modalConfirmReqTipoText').textContent=text;confirmCallback=cb;$('#modalConfirmReqTipo').modal('show');}
  function toggle(id,state){var data=new FormData();data.set('id',id);post('cambiar_estado',data).then(function(r){toast(r.message||'Estado actualizado.','success');load();}).catch(function(e){toast(e.message||'No se pudo actualizar estado.','danger');});}

  document.getElementById('req-btn-buscar').addEventListener('click',function(){page=1;load();});
  document.getElementById('req-btn-limpiar').addEventListener('click',function(){document.getElementById('req-search').value='';document.getElementById('req-filtro-tipo').value='0';document.getElementById('req-filtro-estado').value='todos';page=1;load();});
  var btnNuevo=document.getElementById('req-btn-nuevo');if(btnNuevo)btnNuevo.addEventListener('click',function(){openModal(null);});
  ['req-search','req-filtro-tipo','req-filtro-estado'].forEach(function(id){document.getElementById(id).addEventListener(id==='req-search'?'input':'change',function(){clearTimeout(timer);timer=setTimeout(function(){page=1;load();},id==='req-search'?300:0);});});
  document.getElementById('req-pagination').addEventListener('click',function(e){var b=e.target.closest('[data-page]');if(!b)return;page=Number(b.dataset.page)||1;load();});
  document.getElementById('req-body').addEventListener('click',function(e){var b=e.target.closest('[data-action]');if(!b)return;var id=b.dataset.id;if(b.dataset.action==='edit')openModal(id);else confirmAction(Number(b.dataset.state)===1?'Desea desactivar este requisito?':'Desea activar este requisito?',function(){toggle(id,b.dataset.state);});});
  document.getElementById('formReqTipo').addEventListener('submit',function(e){e.preventDefault();save(e.target);});
  document.getElementById('modalConfirmReqTipoOk').addEventListener('click',function(){var cb=confirmCallback;confirmCallback=null;$('#modalConfirmReqTipo').modal('hide');if(typeof cb==='function')cb();});
  loadContext().then(load);
});
</script>
