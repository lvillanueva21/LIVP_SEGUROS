<?php
require_once __DIR__ . '/../../includes/module_guard.php';
cb_require_module_context('clientes');

$csrfClientes = cb_local_csrf_token('clientes');
$permClientes = [
    'puede_crear' => cb_cliente_puede('clientes', 'puede_crear'),
    'puede_editar' => cb_cliente_puede('clientes', 'puede_editar'),
    'puede_eliminar' => cb_cliente_puede('clientes', 'puede_eliminar'),
];
?>
<div class="clientes-module" data-csrf="<?php echo cb_e($csrfClientes); ?>">
  <div class="row mb-3">
    <div class="col-12">
      <h1 class="h4 mb-1">Clientes</h1>
      <p class="text-muted mb-0">Registro y administraci&oacute;n de empresas cliente de Broker Seguros.</p>
    </div>
  </div>

  <div class="card card-primary card-outline">
    <div class="card-body">
      <div class="clientes-toolbar">
        <div class="input-group input-group-sm">
          <input type="search" class="form-control" id="cli-search" placeholder="Buscar por RUC, raz&oacute;n social, tel&eacute;fono o correo">
          <div class="input-group-append">
            <button class="btn btn-outline-secondary" type="button" id="cli-btn-buscar" title="Buscar" aria-label="Buscar">
              <i class="fas fa-search"></i>
            </button>
          </div>
        </div>
        <select class="form-control form-control-sm" id="cli-filtro-estado" aria-label="Filtro de estado">
          <option value="todos">Todos</option>
          <option value="activo">Activos</option>
          <option value="inactivo">Inactivos</option>
        </select>
        <button class="btn btn-outline-secondary btn-sm" type="button" id="cli-btn-limpiar">
          <i class="fas fa-eraser"></i> Limpiar
        </button>
        <?php if ($permClientes['puede_crear']): ?>
          <button class="btn btn-primary btn-sm" type="button" id="cli-btn-nuevo">
            <i class="fas fa-plus"></i> Registrar empresa
          </button>
        <?php endif; ?>
      </div>

      <div class="text-muted small mb-2" id="cli-counter">0 registros</div>
      <div class="table-responsive">
        <table class="table table-bordered table-hover table-sm">
          <thead>
            <tr>
              <th>RUC</th>
              <th>Empresa</th>
              <th>Contacto principal</th>
              <th>Tel&eacute;fono / correo</th>
              <th>Estado</th>
              <th class="text-center" style="width:86px;">Acciones</th>
            </tr>
          </thead>
          <tbody id="cli-body"></tbody>
        </table>
      </div>
      <div class="clientes-loading" id="cli-loading">Cargando clientes...</div>
      <div class="clientes-empty" id="cli-empty">Todav&iacute;a no hay empresas cliente registradas.</div>
      <div class="clientes-pagination" id="cli-pagination"></div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalCliente" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <form class="modal-content" id="formCliente" autocomplete="off" novalidate>
      <div class="modal-header">
        <h5 class="modal-title" id="modalClienteTitle">Empresa cliente</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id">
        <div class="form-row">
          <div class="form-group col-md-4">
            <label>RUC</label>
            <input class="form-control" name="ruc" maxlength="11" inputmode="numeric" pattern="[0-9]{11}" required>
          </div>
          <div class="form-group col-md-8">
            <label>Raz&oacute;n social</label>
            <input class="form-control text-uppercase" name="razon_social" maxlength="180" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Nombre comercial</label>
            <input class="form-control" name="nombre_comercial" maxlength="180">
          </div>
          <div class="form-group col-md-3">
            <label>Tel&eacute;fono principal</label>
            <input class="form-control" name="telefono_principal" maxlength="40" inputmode="tel">
          </div>
          <div class="form-group col-md-3">
            <label>Estado</label>
            <select class="form-control" name="estado">
              <option value="1">Activo</option>
              <option value="0">Inactivo</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Correo principal</label>
            <input class="form-control" name="correo_principal" maxlength="160" type="email">
          </div>
          <div class="form-group col-md-6">
            <label>Direcci&oacute;n</label>
            <input class="form-control" name="direccion" maxlength="255">
          </div>
        </div>
        <div class="form-group">
          <label>Observaciones</label>
          <textarea class="form-control" name="observaciones" rows="2" maxlength="3000"></textarea>
        </div>

        <div class="clientes-contacto-box">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <strong>Contacto principal inicial</strong>
            <span class="badge badge-info">Principal</span>
          </div>
          <input type="hidden" name="contacto_es_principal" value="1">
          <div class="form-row">
            <div class="form-group col-md-6">
              <label>Nombre completo</label>
              <input class="form-control" name="contacto_nombre_completo" maxlength="180">
            </div>
            <div class="form-group col-md-6">
              <label>Cargo o relaci&oacute;n</label>
              <input class="form-control" name="contacto_cargo_relacion" maxlength="120">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group col-md-4">
              <label>Tel&eacute;fono</label>
              <input class="form-control" name="contacto_telefono" maxlength="40" inputmode="tel">
            </div>
            <div class="form-group col-md-5">
              <label>Correo</label>
              <input class="form-control" name="contacto_correo" maxlength="160" type="email">
            </div>
            <div class="form-group col-md-3">
              <label>Estado contacto</label>
              <select class="form-control" name="contacto_estado">
                <option value="1">Activo</option>
                <option value="0">Inactivo</option>
              </select>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary" id="cli-btn-guardar">Guardar</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="modalConfirmClientes" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Confirmar acci&oacute;n</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body" id="modalConfirmClientesText"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="modalConfirmClientesOk">Confirmar</button>
      </div>
    </div>
  </div>
</div>

<div class="clientes-toast-zone" id="clientes-toast-zone"></div>

<style>
  .clientes-toolbar{display:grid;gap:.5rem;grid-template-columns:minmax(240px,1fr) 150px auto auto;align-items:center;margin-bottom:.75rem}
  .clientes-loading,.clientes-empty{display:none;padding:1rem;border:1px dashed #ced4da;border-radius:.25rem;text-align:center;color:#6c757d;background:#f8f9fa}
  .clientes-actions{display:inline-flex;flex-direction:column;gap:.25rem}
  .clientes-pagination .btn{min-width:36px}
  .clientes-contacto-box{border:1px solid #dee2e6;border-radius:.25rem;background:#f8f9fa;padding:.85rem;margin-top:.5rem}
  .clientes-toast-zone{position:fixed;right:1rem;bottom:1rem;z-index:1080;width:min(360px,calc(100vw - 2rem))}
  @media(max-width:991.98px){.clientes-toolbar{grid-template-columns:1fr}}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var csrf = <?php echo json_encode($csrfClientes); ?>;
  var permisos = <?php echo json_encode($permClientes); ?>;
  var endpoint = 'api/clientes/empresas.php';
  var rows = [];
  var page = 1;
  var searchTimer = null;
  var confirmCallback = null;
  var saveInFlight = false;

  function escapeHtml(value){return String(value == null ? '' : value).replace(/[&<>"']/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[c];});}
  function showToast(message,type){var z=document.getElementById('clientes-toast-zone');var t=document.createElement('div');t.className='alert alert-'+(type||'info')+' shadow-sm mb-2';t.textContent=message;z.appendChild(t);setTimeout(function(){t.remove();},4200);}
  function fetchJson(url,options){return fetch(url,options||{}).then(function(r){return r.json().catch(function(){return{ok:false,message:'Respuesta no valida del servidor.'};}).then(function(j){if(!r.ok||!j.ok)throw j;return j;});});}
  function post(action,data){data.set('_csrf',csrf);return fetchJson(endpoint+'?accion='+encodeURIComponent(action),{method:'POST',body:data});}
  function badgeEstado(v){return Number(v)===1?'<span class="badge badge-success">Activo</span>':'<span class="badge badge-secondary">Inactivo</span>';}
  function params(){var p=new URLSearchParams();p.set('accion','listar');p.set('page',page);p.set('q',document.getElementById('cli-search').value||'');p.set('estado',document.getElementById('cli-filtro-estado').value||'todos');return p;}
  function contactoHtml(row){if(!row.contacto_nombre_completo){return '<span class="text-muted">Sin contacto activo</span>';}var cargo=row.contacto_cargo_relacion?'<div class="text-muted small">'+escapeHtml(row.contacto_cargo_relacion)+'</div>':'';return '<strong>'+escapeHtml(row.contacto_nombre_completo)+'</strong>'+cargo;}
  function empresaHtml(row){var comercial=row.nombre_comercial?'<div class="text-muted small">'+escapeHtml(row.nombre_comercial)+'</div>':'';return '<strong>'+escapeHtml(row.razon_social)+'</strong>'+comercial;}
  function contactoMediosHtml(row){var partes=[];if(row.telefono_principal)partes.push('<div><i class="fas fa-phone-alt text-muted"></i> '+escapeHtml(row.telefono_principal)+'</div>');if(row.correo_principal)partes.push('<div><i class="fas fa-envelope text-muted"></i> '+escapeHtml(row.correo_principal)+'</div>');if(row.contacto_telefono)partes.push('<div class="small text-muted">Contacto: '+escapeHtml(row.contacto_telefono)+'</div>');if(row.contacto_correo)partes.push('<div class="small text-muted">'+escapeHtml(row.contacto_correo)+'</div>');return partes.length?partes.join(''):'<span class="text-muted">Sin datos</span>';}
  function actionButtons(row){var h='<span class="clientes-actions">';var any=false;if(permisos.puede_editar){any=true;h+='<button type="button" class="btn btn-xs btn-outline-primary" data-action="edit" data-id="'+row.id+'" title="Editar" aria-label="Editar"><i class="fas fa-edit"></i></button>';}if(permisos.puede_eliminar){any=true;var title=Number(row.estado)===1?'Desactivar':'Activar';var icon=Number(row.estado)===1?'fa-toggle-on':'fa-toggle-off';h+='<button type="button" class="btn btn-xs btn-outline-secondary" data-action="toggle" data-id="'+row.id+'" data-state="'+row.estado+'" title="'+title+'" aria-label="'+title+'"><i class="fas '+icon+'"></i></button>';}return any?h+'</span>':'<span class="text-muted">-</span>';}
  function renderRows(){var html='';rows.forEach(function(row){html+='<tr><td>'+escapeHtml(row.ruc)+'</td><td>'+empresaHtml(row)+'</td><td>'+contactoHtml(row)+'</td><td>'+contactoMediosHtml(row)+'</td><td>'+badgeEstado(row.estado)+'</td><td class="text-center">'+actionButtons(row)+'</td></tr>';});document.getElementById('cli-body').innerHTML=html;document.getElementById('cli-empty').style.display=rows.length?'none':'block';}
  function renderPagination(pag){var wrap=document.getElementById('cli-pagination');var total=Number(pag.total||0),cur=Number(pag.page||1),last=Number(pag.last_page||1);document.getElementById('cli-counter').textContent=total+(total===1?' registro':' registros');if(last<=1){wrap.innerHTML='';return;}wrap.innerHTML='<div class="btn-group btn-group-sm"><button type="button" class="btn btn-outline-secondary" data-page="'+(cur-1)+'" '+(cur<=1?'disabled':'')+' title="Anterior" aria-label="Anterior"><i class="fas fa-chevron-left"></i></button><button type="button" class="btn btn-outline-secondary" disabled>'+cur+' / '+last+'</button><button type="button" class="btn btn-outline-secondary" data-page="'+(cur+1)+'" '+(cur>=last?'disabled':'')+' title="Siguiente" aria-label="Siguiente"><i class="fas fa-chevron-right"></i></button></div>';}
  function load(){document.getElementById('cli-loading').style.display='block';fetchJson(endpoint+'?'+params().toString()).then(function(resp){var data=resp.data||{};rows=data.rows||[];renderRows();renderPagination(data.pagination||{});}).catch(function(e){showToast(e.message||'No se pudo cargar clientes.','danger');}).finally(function(){document.getElementById('cli-loading').style.display='none';});}
  function clearValidation(form){form.classList.remove('was-validated');Array.prototype.forEach.call(form.querySelectorAll('.is-invalid'),function(el){el.classList.remove('is-invalid');});Array.prototype.forEach.call(form.querySelectorAll('.invalid-feedback.dynamic'),function(el){el.remove();});}
  function applyErrors(form,errors){if(!errors)return;Object.keys(errors).forEach(function(k){var input=form.querySelector('[name="'+k+'"]');if(!input)return;input.classList.add('is-invalid');var f=document.createElement('div');f.className='invalid-feedback dynamic';f.textContent=String(errors[k]);input.parentNode.appendChild(f);});}
  function setValue(form,name,value){if(form.elements[name])form.elements[name].value=value == null ? '' : value;}
  function openModal(id){var form=document.getElementById('formCliente');form.reset();clearValidation(form);form.elements.estado.value='1';form.elements.contacto_estado.value='1';document.getElementById('modalClienteTitle').textContent=id?'Editar empresa cliente':'Registrar empresa cliente';if(!id){$('#modalCliente').modal('show');return;}fetchJson(endpoint+'?accion=obtener&id='+encodeURIComponent(id)).then(function(resp){var empresa=(resp.data||{}).empresa||{};var contacto=(resp.data||{}).contacto||{};setValue(form,'id',empresa.id);setValue(form,'ruc',empresa.ruc);setValue(form,'razon_social',empresa.razon_social);setValue(form,'nombre_comercial',empresa.nombre_comercial);setValue(form,'telefono_principal',empresa.telefono_principal);setValue(form,'correo_principal',empresa.correo_principal);setValue(form,'direccion',empresa.direccion);setValue(form,'observaciones',empresa.observaciones);setValue(form,'estado',empresa.estado);setValue(form,'contacto_nombre_completo',contacto.nombre_completo);setValue(form,'contacto_cargo_relacion',contacto.cargo_relacion);setValue(form,'contacto_telefono',contacto.telefono);setValue(form,'contacto_correo',contacto.correo);setValue(form,'contacto_estado',contacto.estado == null ? '1' : contacto.estado);$('#modalCliente').modal('show');}).catch(function(e){showToast(e.message||'No se pudo cargar el cliente.','danger');});}
  function save(form){if(saveInFlight)return;if(!form.checkValidity()){form.classList.add('was-validated');return;}saveInFlight=true;clearValidation(form);var btn=document.getElementById('cli-btn-guardar');btn.disabled=true;var data=new FormData(form);var creating=!form.elements.id.value;post(creating?'crear':'actualizar',data).then(function(resp){$('#modalCliente').modal('hide');showToast(resp.message||'Empresa cliente guardada correctamente.','success');load();}).catch(function(e){applyErrors(form,e.errors||{});showToast(e.message||'No se pudo guardar la empresa cliente.','danger');}).finally(function(){saveInFlight=false;btn.disabled=false;});}
  function confirmAction(text,cb){document.getElementById('modalConfirmClientesText').textContent=text;confirmCallback=cb;$('#modalConfirmClientes').modal('show');}
  function toggle(id,state){var data=new FormData();data.set('id',id);post('cambiar_estado',data).then(function(resp){showToast(resp.message||'Estado actualizado correctamente.','success');load();}).catch(function(e){showToast(e.message||'No se pudo actualizar el estado.','danger');});}

  document.getElementById('cli-btn-buscar').addEventListener('click',function(){page=1;load();});
  document.getElementById('cli-btn-limpiar').addEventListener('click',function(){document.getElementById('cli-search').value='';document.getElementById('cli-filtro-estado').value='todos';page=1;load();});
  var btnNuevo=document.getElementById('cli-btn-nuevo');if(btnNuevo)btnNuevo.addEventListener('click',function(){openModal(null);});
  document.getElementById('cli-search').addEventListener('input',function(){clearTimeout(searchTimer);searchTimer=setTimeout(function(){page=1;load();},300);});
  document.getElementById('cli-search').addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();clearTimeout(searchTimer);page=1;load();}});
  document.getElementById('cli-filtro-estado').addEventListener('change',function(){page=1;load();});
  document.getElementById('cli-pagination').addEventListener('click',function(e){var b=e.target.closest('[data-page]');if(!b)return;page=Number(b.getAttribute('data-page'))||1;load();});
  document.getElementById('cli-body').addEventListener('click',function(e){var b=e.target.closest('[data-action]');if(!b)return;var id=b.getAttribute('data-id');if(b.getAttribute('data-action')==='edit')openModal(id);else if(b.getAttribute('data-action')==='toggle'){var s=b.getAttribute('data-state');confirmAction(Number(s)===1?'Desea desactivar esta empresa cliente?':'Desea activar esta empresa cliente?',function(){toggle(id,s);});}});
  document.getElementById('formCliente').addEventListener('submit',function(e){e.preventDefault();save(e.target);});
  document.getElementById('modalConfirmClientesOk').addEventListener('click',function(){var cb=confirmCallback;confirmCallback=null;$('#modalConfirmClientes').modal('hide');if(typeof cb==='function')cb();});
  load();
});
</script>
