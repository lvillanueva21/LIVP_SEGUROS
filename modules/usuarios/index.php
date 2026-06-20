<?php
require_once __DIR__ . '/../../includes/module_guard.php';
cb_require_module_context('usuarios');

$csrfUsuarios = cb_local_csrf_token('usuarios');
$permUsuarios = [
    'puede_crear' => cb_cliente_puede('usuarios', 'puede_crear'),
    'puede_editar' => cb_cliente_puede('usuarios', 'puede_editar'),
    'puede_eliminar' => cb_cliente_puede('usuarios', 'puede_eliminar'),
];
?>
<div class="usuarios-module" data-csrf="<?php echo cb_e($csrfUsuarios); ?>">
  <div class="row mb-3">
    <div class="col-12">
      <h1 class="h4 mb-1">Usuarios</h1>
      <p class="text-muted mb-0">Gesti&oacute;n de accesos de Broker Seguros.</p>
    </div>
  </div>

  <div class="row">
    <div class="col-md-4">
      <div class="small-box bg-success">
        <div class="inner"><h3 id="usu-kpi-activos">0</h3><p>Usuarios activos</p></div>
        <div class="icon"><i class="fas fa-user-check"></i></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="small-box bg-secondary">
        <div class="inner"><h3 id="usu-kpi-desactivados">0</h3><p>Usuarios desactivados</p></div>
        <div class="icon"><i class="fas fa-user-slash"></i></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="small-box bg-info">
        <div class="inner"><h3 id="usu-kpi-roles">0</h3><p>Roles disponibles</p></div>
        <div class="icon"><i class="fas fa-id-badge"></i></div>
      </div>
    </div>
  </div>

  <div class="card card-primary card-outline">
    <div class="card-body">
      <div class="usuarios-toolbar">
        <div class="input-group input-group-sm">
          <input type="search" class="form-control" id="usu-search" placeholder="Buscar usuario">
          <div class="input-group-append">
            <button class="btn btn-outline-secondary" type="button" id="usu-btn-buscar" title="Buscar" aria-label="Buscar"><i class="fas fa-search"></i></button>
          </div>
        </div>
        <select class="form-control form-control-sm" id="usu-filtro-rol" aria-label="Filtro de rol">
          <option value="0">Todos los roles</option>
        </select>
        <select class="form-control form-control-sm" id="usu-filtro-estado" aria-label="Filtro de estado">
          <option value="todos">Todos</option>
          <option value="activo">Activos</option>
          <option value="desactivado">Desactivados</option>
        </select>
        <button class="btn btn-outline-secondary btn-sm" type="button" id="usu-btn-limpiar"><i class="fas fa-eraser"></i> Limpiar</button>
        <?php if ($permUsuarios['puede_crear']): ?>
          <button class="btn btn-primary btn-sm" type="button" id="usu-btn-nuevo"><i class="fas fa-plus"></i> Nuevo usuario</button>
        <?php endif; ?>
      </div>
      <div class="text-muted small mb-2" id="usu-counter">0 registros</div>
      <div class="table-responsive">
        <table class="table table-bordered table-hover table-sm">
          <thead>
            <tr>
              <th>Usuario</th>
              <th>Nombre completo</th>
              <th>Rol</th>
              <th>Estado</th>
              <th>Fecha de creaci&oacute;n</th>
              <th class="text-center" style="width:86px;">Acciones</th>
            </tr>
          </thead>
          <tbody id="usu-body"></tbody>
        </table>
      </div>
      <div class="usuarios-loading" id="usu-loading">Cargando usuarios...</div>
      <div class="usuarios-empty" id="usu-empty">No hay usuarios para mostrar.</div>
      <div class="usuarios-pagination" id="usu-pagination"></div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalUsuario" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <form class="modal-content" id="formUsuario" autocomplete="off" novalidate>
      <div class="modal-header">
        <h5 class="modal-title" id="modalUsuarioTitle">Usuario</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id_usuario_externo">
        <div class="form-row">
          <div class="form-group col-md-4">
            <label>Tipo documento</label>
            <select class="form-control" name="tipo_documento" required>
              <option value="DNI">DNI</option>
              <option value="CE">CE</option>
            </select>
          </div>
          <div class="form-group col-md-4">
            <label>N&uacute;mero documento</label>
            <input class="form-control" name="numero_documento" maxlength="15" required>
          </div>
          <div class="form-group col-md-4">
            <label>Rol</label>
            <select class="form-control" name="id_rol" required></select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Nombres</label>
            <input class="form-control text-uppercase" name="nombres" maxlength="120" required>
          </div>
          <div class="form-group col-md-6">
            <label>Apellidos</label>
            <input class="form-control text-uppercase" name="apellidos" maxlength="120" required>
          </div>
        </div>
        <div class="form-row" id="usu-password-row">
          <div class="form-group col-md-6">
            <label>Contrase&ntilde;a inicial</label>
            <div class="input-group">
              <input class="form-control" name="clave" type="password" autocomplete="new-password">
              <div class="input-group-append">
                <button class="btn btn-outline-secondary" type="button" data-toggle-pass="clave" title="Mostrar u ocultar" aria-label="Mostrar u ocultar"><i class="fas fa-eye"></i></button>
              </div>
            </div>
          </div>
          <div class="form-group col-md-6">
            <label>Confirmar contrase&ntilde;a</label>
            <div class="input-group">
              <input class="form-control" name="clave_confirmar" type="password" autocomplete="new-password">
              <div class="input-group-append">
                <button class="btn btn-outline-secondary" type="button" data-toggle-pass="clave_confirmar" title="Mostrar u ocultar" aria-label="Mostrar u ocultar"><i class="fas fa-eye"></i></button>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary" id="usu-btn-guardar">Guardar</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="modalConfirmUsuarios" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Confirmar acci&oacute;n</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body" id="modalConfirmUsuariosText"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="modalConfirmUsuariosOk">Confirmar</button>
      </div>
    </div>
  </div>
</div>

<div class="usuarios-toast-zone" id="usuarios-toast-zone"></div>

<style>
  .usuarios-toolbar{display:grid;gap:.5rem;grid-template-columns:minmax(180px,1fr) 180px 150px auto auto;align-items:center;margin-bottom:.75rem}
  .usuarios-loading,.usuarios-empty{display:none;padding:1rem;border:1px dashed #ced4da;border-radius:.25rem;text-align:center;color:#6c757d;background:#f8f9fa}
  .usuarios-actions{display:inline-flex;flex-direction:column;gap:.25rem}
  .usuarios-pagination .btn{min-width:36px}
  .usuarios-toast-zone{position:fixed;right:1rem;bottom:1rem;z-index:1080;width:min(360px,calc(100vw - 2rem))}
  @media(max-width:991.98px){.usuarios-toolbar{grid-template-columns:1fr}}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var csrf = <?php echo json_encode($csrfUsuarios); ?>;
  var permisos = <?php echo json_encode($permUsuarios); ?>;
  var endpoints = {
    contexto: 'api/usuarios/contexto.php',
    listar: 'api/usuarios/listar.php',
    crear: 'api/usuarios/crear.php',
    actualizar: 'api/usuarios/actualizar.php',
    cambiarEstado: 'api/usuarios/cambiar_estado.php'
  };
  var roles = [];
  var rows = [];
  var page = 1;
  var searchTimer = null;
  var confirmCallback = null;
  var saveInFlight = false;

  function escapeHtml(value){return String(value == null ? '' : value).replace(/[&<>"']/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[c];});}
  function showToast(message,type){var z=document.getElementById('usuarios-toast-zone');var t=document.createElement('div');t.className='alert alert-'+(type||'info')+' shadow-sm mb-2';t.textContent=message;z.appendChild(t);setTimeout(function(){t.remove();},4200);}
  function fetchJson(url,options){return fetch(url,options||{}).then(function(r){return r.json().catch(function(){return{ok:false,message:'Respuesta no valida del servidor.'};}).then(function(j){if(!r.ok||!j.ok)throw j;return j.data||{};});});}
  function badgeEstado(v){return Number(v)===1?'<span class="badge badge-success">Activo</span>':'<span class="badge badge-secondary">Desactivado</span>';}
  function params(){var p=new URLSearchParams();p.set('page',page);p.set('per_page',10);p.set('q',document.getElementById('usu-search').value||'');p.set('estado',document.getElementById('usu-filtro-estado').value||'todos');p.set('id_rol',document.getElementById('usu-filtro-rol').value||'0');return p;}
  function post(url,data){data.set('_csrf',csrf);return fetchJson(url,{method:'POST',body:data});}
  function renderRoles(){var opts='<option value="0">Todos los roles</option>';roles.forEach(function(r){opts+='<option value="'+r.id+'">'+escapeHtml(r.nombre)+'</option>';});document.getElementById('usu-filtro-rol').innerHTML=opts;var formOpts='<option value="">Seleccione rol</option>';roles.forEach(function(r){formOpts+='<option value="'+r.id+'">'+escapeHtml(r.nombre)+'</option>';});document.querySelector('#formUsuario [name="id_rol"]').innerHTML=formOpts;document.getElementById('usu-kpi-roles').textContent=roles.length;}
  function actionButtons(row){if(!row.gestion_desde_esclavo){return '<span class="badge badge-warning">Gestionar en Luigi Sistemas</span>';}var h='<span class="usuarios-actions">';if(permisos.puede_editar)h+='<button type="button" class="btn btn-xs btn-outline-primary" data-action="edit" data-id="'+row.id+'" title="Editar" aria-label="Editar"><i class="fas fa-edit"></i></button>';if(permisos.puede_eliminar){var title=Number(row.estado)===1?'Desactivar':'Activar';var icon=Number(row.estado)===1?'fa-toggle-on':'fa-toggle-off';h+='<button type="button" class="btn btn-xs btn-outline-secondary" data-action="toggle" data-id="'+row.id+'" data-state="'+row.estado+'" title="'+title+'" aria-label="'+title+'"><i class="fas '+icon+'"></i></button>';}return h+'</span>';}
  function renderRows(){var html='';var activos=0,desactivados=0;rows.forEach(function(row){if(Number(row.estado)===1)activos++;else desactivados++;var rol=row.rol||{};var gerente=!row.gestion_desde_esclavo;html+='<tr><td>'+escapeHtml(row.tipo_documento+' '+row.numero_documento)+'</td><td>'+escapeHtml((row.nombres||'')+' '+(row.apellidos||''))+'</td><td><span class="badge '+(gerente?'badge-warning':'badge-info')+'">'+escapeHtml(rol.nombre||'Sin rol')+'</span></td><td>'+badgeEstado(row.estado)+'</td><td>'+escapeHtml(row.creado_en||'-')+'</td><td class="text-center">'+actionButtons(row)+'</td></tr>';});document.getElementById('usu-body').innerHTML=html;document.getElementById('usu-empty').style.display=rows.length?'none':'block';document.getElementById('usu-kpi-activos').textContent=activos;document.getElementById('usu-kpi-desactivados').textContent=desactivados;}
  function renderPagination(pag){var wrap=document.getElementById('usu-pagination');var total=Number(pag.total||0),cur=Number(pag.page||1),last=Number(pag.last_page||1);document.getElementById('usu-counter').textContent=total+(total===1?' registro':' registros');if(last<=1){wrap.innerHTML='';return;}wrap.innerHTML='<div class="btn-group btn-group-sm"><button type="button" class="btn btn-outline-secondary" data-page="'+(cur-1)+'" '+(cur<=1?'disabled':'')+' title="Anterior" aria-label="Anterior"><i class="fas fa-chevron-left"></i></button><button type="button" class="btn btn-outline-secondary" disabled>'+cur+' / '+last+'</button><button type="button" class="btn btn-outline-secondary" data-page="'+(cur+1)+'" '+(cur>=last?'disabled':'')+' title="Siguiente" aria-label="Siguiente"><i class="fas fa-chevron-right"></i></button></div>';}
  function load(){document.getElementById('usu-loading').style.display='block';fetchJson(endpoints.listar+'?'+params().toString()).then(function(data){rows=data.rows||[];renderRows();renderPagination(data.pagination||{});}).catch(function(e){showToast(e.message||'No se pudo cargar usuarios.','danger');}).finally(function(){document.getElementById('usu-loading').style.display='none';});}
  function loadContext(){fetchJson(endpoints.contexto).then(function(data){roles=data.roles_asignables||[];csrf=data.csrf||csrf;renderRoles();load();}).catch(function(e){showToast(e.message||'No se pudo cargar contexto.','danger');});}
  function clearValidation(form){Array.prototype.forEach.call(form.querySelectorAll('.is-invalid'),function(el){el.classList.remove('is-invalid');});Array.prototype.forEach.call(form.querySelectorAll('.invalid-feedback.dynamic'),function(el){el.remove();});}
  function applyErrors(form,errors){if(!errors)return;Object.keys(errors).forEach(function(k){var input=form.querySelector('[name="'+k+'"]');if(!input)return;input.classList.add('is-invalid');var f=document.createElement('div');f.className='invalid-feedback dynamic';f.textContent=String(errors[k]);input.parentNode.appendChild(f);});}
  function openModal(id){var form=document.getElementById('formUsuario');form.reset();clearValidation(form);var row=null;if(id){rows.forEach(function(r){if(Number(r.id)===Number(id))row=r;});}var creating=!row;document.getElementById('modalUsuarioTitle').textContent=creating?'Nuevo usuario':'Editar usuario';document.getElementById('usu-password-row').style.display=creating?'flex':'none';form.elements.id_usuario_externo.value=creating?'':row.id;form.elements.tipo_documento.value=creating?'DNI':row.tipo_documento;form.elements.numero_documento.value=creating?'':row.numero_documento;form.elements.nombres.value=creating?'':row.nombres;form.elements.apellidos.value=creating?'':row.apellidos;form.elements.id_rol.value=creating?'':(row.rol&&row.rol.id?row.rol.id:'');$('#modalUsuario').modal('show');}
  function save(form){if(saveInFlight)return;saveInFlight=true;clearValidation(form);var btn=document.getElementById('usu-btn-guardar');btn.disabled=true;var data=new FormData(form);var creating=!form.elements.id_usuario_externo.value;post(creating?endpoints.crear:endpoints.actualizar,data).then(function(d){$('#modalUsuario').modal('hide');showToast(d.message||'Usuario guardado correctamente.','success');load();}).catch(function(e){applyErrors(form,e.errors||{});showToast(e.message||'No se pudo guardar usuario.','danger');}).finally(function(){saveInFlight=false;btn.disabled=false;});}
  function confirmAction(text,cb){document.getElementById('modalConfirmUsuariosText').textContent=text;confirmCallback=cb;$('#modalConfirmUsuarios').modal('show');}
  function toggle(id,state){var data=new FormData();data.set('id_usuario_externo',id);data.set('estado_objetivo',Number(state)===1?'0':'1');post(endpoints.cambiarEstado,data).then(function(d){showToast(d.message||'Estado actualizado correctamente.','success');load();}).catch(function(e){showToast(e.message||'No se pudo actualizar estado.','danger');});}
  document.getElementById('usu-btn-buscar').addEventListener('click',function(){page=1;load();});
  document.getElementById('usu-btn-limpiar').addEventListener('click',function(){document.getElementById('usu-search').value='';document.getElementById('usu-filtro-rol').value='0';document.getElementById('usu-filtro-estado').value='todos';page=1;load();});
  var btnNuevo=document.getElementById('usu-btn-nuevo');if(btnNuevo)btnNuevo.addEventListener('click',function(){openModal(null);});
  document.getElementById('usu-search').addEventListener('input',function(){clearTimeout(searchTimer);searchTimer=setTimeout(function(){page=1;load();},300);});
  document.getElementById('usu-search').addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();clearTimeout(searchTimer);page=1;load();}});
  document.getElementById('usu-filtro-rol').addEventListener('change',function(){page=1;load();});
  document.getElementById('usu-filtro-estado').addEventListener('change',function(){page=1;load();});
  document.getElementById('usu-pagination').addEventListener('click',function(e){var b=e.target.closest('[data-page]');if(!b)return;page=Number(b.getAttribute('data-page'))||1;load();});
  document.getElementById('usu-body').addEventListener('click',function(e){var b=e.target.closest('[data-action]');if(!b)return;var id=b.getAttribute('data-id');if(b.getAttribute('data-action')==='edit')openModal(id);else if(b.getAttribute('data-action')==='toggle'){var s=b.getAttribute('data-state');confirmAction(Number(s)===1?'Desea desactivar este acceso?':'Desea activar este acceso?',function(){toggle(id,s);});}});
  document.getElementById('formUsuario').addEventListener('submit',function(e){e.preventDefault();save(e.target);});
  document.getElementById('modalConfirmUsuariosOk').addEventListener('click',function(){var cb=confirmCallback;confirmCallback=null;$('#modalConfirmUsuarios').modal('hide');if(typeof cb==='function')cb();});
  document.addEventListener('click',function(e){var b=e.target.closest('[data-toggle-pass]');if(!b)return;var name=b.getAttribute('data-toggle-pass');var input=document.querySelector('#formUsuario [name="'+name+'"]');if(!input)return;input.type=input.type==='password'?'text':'password';var icon=b.querySelector('i');if(icon){icon.className=input.type==='password'?'fas fa-eye':'fas fa-eye-slash';}});
  loadContext();
});
</script>
