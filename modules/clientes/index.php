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
<div class="clientes-module">
  <div class="row mb-3">
    <div class="col-12">
      <h1 class="h4 mb-1">Clientes</h1>
      <p class="text-muted mb-0">Registro de empresas y consorcios comerciales de Broker Seguros.</p>
    </div>
  </div>

  <div class="card card-primary card-outline">
    <div class="card-header p-0 border-bottom-0">
      <ul class="nav nav-tabs" id="clientesTabs" role="tablist">
        <li class="nav-item">
          <a class="nav-link active" id="tab-empresas-link" data-toggle="pill" href="#tab-empresas" role="tab">Empresas</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" id="tab-consorcios-link" data-toggle="pill" href="#tab-consorcios" role="tab">Consorcios</a>
        </li>
      </ul>
    </div>
    <div class="card-body">
      <div class="tab-content">
        <div class="tab-pane fade show active" id="tab-empresas" role="tabpanel">
          <div class="clientes-toolbar">
            <div class="input-group input-group-sm">
              <input type="search" class="form-control" id="emp-search" placeholder="Buscar empresa por RUC, razon social, telefono o correo">
              <div class="input-group-append">
                <button class="btn btn-outline-secondary" type="button" id="emp-btn-buscar" title="Buscar" aria-label="Buscar"><i class="fas fa-search"></i></button>
              </div>
            </div>
            <select class="form-control form-control-sm" id="emp-estado">
              <option value="todos">Todos</option>
              <option value="activo">Activos</option>
              <option value="inactivo">Inactivos</option>
            </select>
            <button class="btn btn-outline-secondary btn-sm" type="button" id="emp-btn-limpiar"><i class="fas fa-eraser"></i> Limpiar</button>
            <?php if ($permClientes['puede_crear']): ?>
              <button class="btn btn-primary btn-sm" type="button" id="emp-btn-nuevo"><i class="fas fa-plus"></i> Registrar empresa</button>
            <?php endif; ?>
          </div>
          <div class="text-muted small mb-2" id="emp-counter">0 registros</div>
          <div class="table-responsive">
            <table class="table table-bordered table-hover table-sm">
              <thead>
                <tr>
                  <th>RUC</th>
                  <th>Empresa</th>
                  <th>Contacto principal</th>
                  <th>Telefono / correo</th>
                  <th>Estado</th>
                  <th class="text-center" style="width:86px;">Acciones</th>
                </tr>
              </thead>
              <tbody id="emp-body"></tbody>
            </table>
          </div>
          <div class="clientes-loading" id="emp-loading">Cargando empresas...</div>
          <div class="clientes-empty" id="emp-empty">Todavia no hay empresas registradas.</div>
          <div class="clientes-pagination" id="emp-pagination"></div>
        </div>

        <div class="tab-pane fade" id="tab-consorcios" role="tabpanel">
          <div class="clientes-toolbar">
            <div class="input-group input-group-sm">
              <input type="search" class="form-control" id="con-search" placeholder="Buscar consorcio por RUC, razon social u operador">
              <div class="input-group-append">
                <button class="btn btn-outline-secondary" type="button" id="con-btn-buscar" title="Buscar" aria-label="Buscar"><i class="fas fa-search"></i></button>
              </div>
            </div>
            <select class="form-control form-control-sm" id="con-estado">
              <option value="todos">Todos</option>
              <option value="activo">Activos</option>
              <option value="inactivo">Inactivos</option>
            </select>
            <button class="btn btn-outline-secondary btn-sm" type="button" id="con-btn-limpiar"><i class="fas fa-eraser"></i> Limpiar</button>
            <?php if ($permClientes['puede_crear']): ?>
              <button class="btn btn-primary btn-sm" type="button" id="con-btn-nuevo"><i class="fas fa-plus"></i> Registrar consorcio</button>
            <?php endif; ?>
          </div>
          <div class="text-muted small mb-2" id="con-counter">0 registros</div>
          <div class="table-responsive">
            <table class="table table-bordered table-hover table-sm">
              <thead>
                <tr>
                  <th>RUC</th>
                  <th>Consorcio</th>
                  <th>Modalidad</th>
                  <th>Operador tributario</th>
                  <th>Integrantes</th>
                  <th>Estado</th>
                  <th class="text-center" style="width:86px;">Acciones</th>
                </tr>
              </thead>
              <tbody id="con-body"></tbody>
            </table>
          </div>
          <div class="clientes-loading" id="con-loading">Cargando consorcios...</div>
          <div class="clientes-empty" id="con-empty">Todavia no hay consorcios registrados.</div>
          <div class="clientes-pagination" id="con-pagination"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalEmpresa" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <form class="modal-content" id="formEmpresa" autocomplete="off" novalidate>
      <div class="modal-header">
        <h5 class="modal-title" id="modalEmpresaTitle">Empresa cliente</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id">
        <div class="form-row">
          <div class="form-group col-md-4"><label>RUC</label><input class="form-control" name="ruc" maxlength="11" inputmode="numeric" pattern="[0-9]{11}" required></div>
          <div class="form-group col-md-8"><label>Razon social</label><input class="form-control text-uppercase" name="razon_social" maxlength="180" required></div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-6"><label>Nombre comercial</label><input class="form-control" name="nombre_comercial" maxlength="180"></div>
          <div class="form-group col-md-3"><label>Telefono principal</label><input class="form-control" name="telefono_principal" maxlength="40" inputmode="tel"></div>
          <div class="form-group col-md-3"><label>Estado</label><select class="form-control" name="estado"><option value="1">Activo</option><option value="0">Inactivo</option></select></div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-6"><label>Correo principal</label><input class="form-control" name="correo_principal" maxlength="160" type="email"></div>
          <div class="form-group col-md-6"><label>Direccion</label><input class="form-control" name="direccion" maxlength="255"></div>
        </div>
        <div class="form-group"><label>Observaciones</label><textarea class="form-control" name="observaciones" rows="2" maxlength="3000"></textarea></div>
        <div class="clientes-contacto-box">
          <div class="d-flex align-items-center justify-content-between mb-2"><strong>Contacto principal inicial</strong><span class="badge badge-info">Principal</span></div>
          <input type="hidden" name="contacto_es_principal" value="1">
          <div class="form-row">
            <div class="form-group col-md-6"><label>Nombre completo</label><input class="form-control" name="contacto_nombre_completo" maxlength="180"></div>
            <div class="form-group col-md-6"><label>Cargo o relacion</label><input class="form-control" name="contacto_cargo_relacion" maxlength="120"></div>
          </div>
          <div class="form-row">
            <div class="form-group col-md-4"><label>Telefono</label><input class="form-control" name="contacto_telefono" maxlength="40" inputmode="tel"></div>
            <div class="form-group col-md-5"><label>Correo</label><input class="form-control" name="contacto_correo" maxlength="160" type="email"></div>
            <div class="form-group col-md-3"><label>Estado contacto</label><select class="form-control" name="contacto_estado"><option value="1">Activo</option><option value="0">Inactivo</option></select></div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary" id="emp-btn-guardar">Guardar</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="modalConsorcio" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <form class="modal-content" id="formConsorcio" autocomplete="off" novalidate>
      <div class="modal-header">
        <h5 class="modal-title" id="modalConsorcioTitle">Consorcio</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id">
        <div class="form-row">
          <div class="form-group col-md-4">
            <label>Modalidad</label>
            <select class="form-control" name="modalidad" id="con-modalidad" required>
              <option value="con_ruc_propio">Con RUC propio</option>
              <option value="con_operador_tributario">Con operador tributario</option>
            </select>
          </div>
          <div class="form-group col-md-4"><label>RUC</label><input class="form-control" name="ruc" maxlength="11" inputmode="numeric"></div>
          <div class="form-group col-md-4"><label>Estado</label><select class="form-control" name="estado"><option value="1">Activo</option><option value="0">Inactivo</option></select></div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-7"><label>Razon social</label><input class="form-control text-uppercase" name="razon_social" maxlength="180" required></div>
          <div class="form-group col-md-5"><label>Nombre comercial</label><input class="form-control" name="nombre_comercial" maxlength="180"></div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-4"><label>Telefono principal</label><input class="form-control" name="telefono_principal" maxlength="40" inputmode="tel"></div>
          <div class="form-group col-md-4"><label>Correo principal</label><input class="form-control" name="correo_principal" maxlength="160" type="email"></div>
          <div class="form-group col-md-4"><label>Operador tributario</label><select class="form-control" name="operador_cliente_id" id="con-operador"></select></div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-8"><label>Direccion</label><input class="form-control" name="direccion" maxlength="255"></div>
          <div class="form-group col-md-4"><label>Observaciones</label><input class="form-control" name="observaciones" maxlength="3000"></div>
        </div>
        <div class="clientes-contacto-box">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <strong>Empresas integrantes</strong>
            <button class="btn btn-outline-primary btn-sm" type="button" id="con-btn-agregar-integrante"><i class="fas fa-plus"></i> Agregar integrante</button>
          </div>
          <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
              <thead><tr><th>Empresa</th><th style="width:140px;">Participacion %</th><th>Rol / descripcion</th><th style="width:54px;">Accion</th></tr></thead>
              <tbody id="con-integrantes-body"></tbody>
            </table>
          </div>
          <div class="text-muted small mt-2">Agrega minimo dos empresas activas. Si usas operador tributario, debe estar dentro de los integrantes.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary" id="con-btn-guardar">Guardar</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="modalConfirmClientes" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Confirmar accion</h5><button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button></div>
      <div class="modal-body" id="modalConfirmClientesText"></div>
      <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button><button type="button" class="btn btn-primary" id="modalConfirmClientesOk">Confirmar</button></div>
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
  var endpoints = {emp:'api/clientes/empresas.php', con:'api/clientes/consorcios.php'};
  var empRows=[], conRows=[], empresasActivas=[], empPage=1, conPage=1, confirmCallback=null, timer=null;

  function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[c];});}
  function toast(msg,type){var z=document.getElementById('clientes-toast-zone'), t=document.createElement('div');t.className='alert alert-'+(type||'info')+' shadow-sm mb-2';t.textContent=msg;z.appendChild(t);setTimeout(function(){t.remove();},4200);}
  function fetchJson(url,opt){return fetch(url,opt||{}).then(function(r){return r.json().catch(function(){return{ok:false,message:'Respuesta no valida del servidor.'};}).then(function(j){if(!r.ok||!j.ok)throw j;return j;});});}
  function post(url,action,data){data.set('_csrf',csrf);return fetchJson(url+'?accion='+encodeURIComponent(action),{method:'POST',body:data});}
  function estado(v){return Number(v)===1?'<span class="badge badge-success">Activo</span>':'<span class="badge badge-secondary">Inactivo</span>';}
  function acciones(row,prefix){var h='<span class="clientes-actions">', any=false;if(permisos.puede_editar){any=true;h+='<button class="btn btn-xs btn-outline-primary" type="button" data-action="edit" data-id="'+row.id+'" title="Editar" aria-label="Editar"><i class="fas fa-edit"></i></button>';}if(permisos.puede_eliminar){any=true;var title=Number(row.estado)===1?'Desactivar':'Activar';h+='<button class="btn btn-xs btn-outline-secondary" type="button" data-action="toggle" data-id="'+row.id+'" data-state="'+row.estado+'" title="'+title+'" aria-label="'+title+'"><i class="fas '+(Number(row.estado)===1?'fa-toggle-on':'fa-toggle-off')+'"></i></button>';}return any?h+'</span>':'<span class="text-muted">-</span>';}
  function clearValidation(f){f.classList.remove('was-validated');Array.prototype.forEach.call(f.querySelectorAll('.is-invalid'),function(el){el.classList.remove('is-invalid');});Array.prototype.forEach.call(f.querySelectorAll('.invalid-feedback.dynamic'),function(el){el.remove();});}
  function applyErrors(f,errors){if(!errors)return;Object.keys(errors).forEach(function(k){var input=f.querySelector('[name="'+k+'"]');if(!input&&k==='integrantes')input=document.getElementById('con-btn-agregar-integrante');if(!input)return;input.classList.add('is-invalid');var d=document.createElement('div');d.className='invalid-feedback dynamic d-block';d.textContent=String(errors[k]);input.parentNode.appendChild(d);});}
  function setVal(f,n,v){if(f.elements[n])f.elements[n].value=v==null?'':v;}
  function confirmAction(text,cb){document.getElementById('modalConfirmClientesText').textContent=text;confirmCallback=cb;$('#modalConfirmClientes').modal('show');}

  function empParams(){var p=new URLSearchParams();p.set('accion','listar');p.set('page',empPage);p.set('q',document.getElementById('emp-search').value||'');p.set('estado',document.getElementById('emp-estado').value||'todos');return p;}
  function loadEmp(){document.getElementById('emp-loading').style.display='block';fetchJson(endpoints.emp+'?'+empParams().toString()).then(function(r){var d=r.data||{};empRows=d.rows||[];renderEmp();renderPag('emp',d.pagination||{});}).catch(function(e){toast(e.message||'No se pudo cargar empresas.','danger');}).finally(function(){document.getElementById('emp-loading').style.display='none';});}
  function renderEmp(){var h='';empRows.forEach(function(r){var empresa='<strong>'+esc(r.razon_social)+'</strong>'+(r.nombre_comercial?'<div class="text-muted small">'+esc(r.nombre_comercial)+'</div>':'');var contacto=r.contacto_nombre_completo?'<strong>'+esc(r.contacto_nombre_completo)+'</strong><div class="text-muted small">'+esc(r.contacto_cargo_relacion||'')+'</div>':'<span class="text-muted">Sin contacto activo</span>';var medios=(r.telefono_principal?'<div>'+esc(r.telefono_principal)+'</div>':'')+(r.correo_principal?'<div class="text-muted small">'+esc(r.correo_principal)+'</div>':'');h+='<tr><td>'+esc(r.ruc)+'</td><td>'+empresa+'</td><td>'+contacto+'</td><td>'+(medios||'<span class="text-muted">Sin datos</span>')+'</td><td>'+estado(r.estado)+'</td><td class="text-center">'+acciones(r,'emp')+'</td></tr>';});document.getElementById('emp-body').innerHTML=h;document.getElementById('emp-empty').style.display=empRows.length?'none':'block';}

  function conParams(){var p=new URLSearchParams();p.set('accion','listar');p.set('page',conPage);p.set('q',document.getElementById('con-search').value||'');p.set('estado',document.getElementById('con-estado').value||'todos');return p;}
  function loadCon(){document.getElementById('con-loading').style.display='block';fetchJson(endpoints.con+'?'+conParams().toString()).then(function(r){var d=r.data||{};conRows=d.rows||[];renderCon();renderPag('con',d.pagination||{});}).catch(function(e){toast(e.message||'No se pudo cargar consorcios.','danger');}).finally(function(){document.getElementById('con-loading').style.display='none';});}
  function renderCon(){var h='';conRows.forEach(function(r){var modalidad=r.modalidad==='con_operador_tributario'?'Operador tributario':'RUC propio';var operador=r.operador_razon_social?'<strong>'+esc(r.operador_razon_social)+'</strong><div class="text-muted small">'+esc(r.operador_ruc||'')+'</div>':'<span class="text-muted">No aplica</span>';var nombre='<strong>'+esc(r.razon_social)+'</strong>'+(r.nombre_comercial?'<div class="text-muted small">'+esc(r.nombre_comercial)+'</div>':'');h+='<tr><td>'+(r.ruc?esc(r.ruc):'<span class="text-muted">Sin RUC</span>')+'</td><td>'+nombre+'</td><td>'+modalidad+'</td><td>'+operador+'</td><td>'+esc(r.integrantes_activos||0)+'</td><td>'+estado(r.estado)+'</td><td class="text-center">'+acciones(r,'con')+'</td></tr>';});document.getElementById('con-body').innerHTML=h;document.getElementById('con-empty').style.display=conRows.length?'none':'block';}
  function renderPag(prefix,pag){var total=Number(pag.total||0),cur=Number(pag.page||1),last=Number(pag.last_page||1),wrap=document.getElementById(prefix+'-pagination');document.getElementById(prefix+'-counter').textContent=total+(total===1?' registro':' registros');if(last<=1){wrap.innerHTML='';return;}wrap.innerHTML='<div class="btn-group btn-group-sm"><button class="btn btn-outline-secondary" data-page="'+(cur-1)+'" '+(cur<=1?'disabled':'')+'><i class="fas fa-chevron-left"></i></button><button class="btn btn-outline-secondary" disabled>'+cur+' / '+last+'</button><button class="btn btn-outline-secondary" data-page="'+(cur+1)+'" '+(cur>=last?'disabled':'')+'><i class="fas fa-chevron-right"></i></button></div>';}

  function openEmpresa(id){var f=document.getElementById('formEmpresa');f.reset();clearValidation(f);f.elements.estado.value='1';f.elements.contacto_estado.value='1';document.getElementById('modalEmpresaTitle').textContent=id?'Editar empresa cliente':'Registrar empresa cliente';if(!id){$('#modalEmpresa').modal('show');return;}fetchJson(endpoints.emp+'?accion=obtener&id='+id).then(function(r){var e=(r.data||{}).empresa||{}, c=(r.data||{}).contacto||{};['id','ruc','razon_social','nombre_comercial','telefono_principal','correo_principal','direccion','observaciones','estado'].forEach(function(k){setVal(f,k,e[k]);});setVal(f,'contacto_nombre_completo',c.nombre_completo);setVal(f,'contacto_cargo_relacion',c.cargo_relacion);setVal(f,'contacto_telefono',c.telefono);setVal(f,'contacto_correo',c.correo);setVal(f,'contacto_estado',c.estado==null?'1':c.estado);$('#modalEmpresa').modal('show');}).catch(function(e){toast(e.message||'No se pudo cargar la empresa.','danger');});}
  function saveEmpresa(f){if(!f.checkValidity()){f.classList.add('was-validated');return;}clearValidation(f);var data=new FormData(f), creating=!f.elements.id.value;post(endpoints.emp,creating?'crear':'actualizar',data).then(function(r){$('#modalEmpresa').modal('hide');toast(r.message||'Empresa guardada.','success');loadEmp();cargarEmpresasActivas();}).catch(function(e){applyErrors(f,e.errors||{});toast(e.message||'No se pudo guardar.','danger');});}

  function cargarEmpresasActivas(){return fetchJson(endpoints.con+'?accion=empresas_activas').then(function(r){empresasActivas=(r.data||{}).rows||[];renderEmpresaOptions();}).catch(function(){empresasActivas=[];});}
  function optionEmpresas(selected){var h='<option value="">Seleccione empresa</option>';empresasActivas.forEach(function(e){h+='<option value="'+e.id+'" '+(Number(selected)===Number(e.id)?'selected':'')+'>'+esc(e.ruc+' - '+e.razon_social)+'</option>';});return h;}
  function renderEmpresaOptions(){document.getElementById('con-operador').innerHTML=optionEmpresas(document.getElementById('con-operador').value);}
  function addIntegrante(item){item=item||{};var tr=document.createElement('tr');tr.innerHTML='<td><select class="form-control form-control-sm" name="integrante_empresa_id[]">'+optionEmpresas(item.empresa_cliente_id)+'</select></td><td><input class="form-control form-control-sm" name="integrante_participacion[]" type="number" min="0.01" max="100" step="0.01" value="'+esc(item.participacion_porcentaje||'')+'"></td><td><input class="form-control form-control-sm" name="integrante_rol[]" maxlength="160" value="'+esc(item.rol_descripcion||'')+'"></td><td class="text-center"><button class="btn btn-xs btn-outline-danger" type="button" data-remove-integrante title="Quitar" aria-label="Quitar"><i class="fas fa-trash"></i></button></td>';document.getElementById('con-integrantes-body').appendChild(tr);}
  function syncModalidad(){var f=document.getElementById('formConsorcio'), mod=f.elements.modalidad.value, ruc=f.elements.ruc, op=f.elements.operador_cliente_id;if(mod==='con_operador_tributario'){ruc.value='';ruc.disabled=true;op.disabled=false;}else{ruc.disabled=false;op.value='';op.disabled=true;}}
  function openConsorcio(id){var f=document.getElementById('formConsorcio');f.reset();clearValidation(f);document.getElementById('con-integrantes-body').innerHTML='';f.elements.estado.value='1';f.elements.modalidad.value='con_ruc_propio';syncModalidad();document.getElementById('modalConsorcioTitle').textContent=id?'Editar consorcio':'Registrar consorcio';cargarEmpresasActivas().then(function(){if(!id){addIntegrante();addIntegrante();$('#modalConsorcio').modal('show');return;}fetchJson(endpoints.con+'?accion=obtener&id='+id).then(function(r){var c=(r.data||{}).consorcio||{}, ints=(r.data||{}).integrantes||[];['id','ruc','razon_social','nombre_comercial','telefono_principal','correo_principal','direccion','observaciones','estado','modalidad','operador_cliente_id'].forEach(function(k){setVal(f,k,c[k]);});syncModalidad();ints.forEach(addIntegrante);if(!ints.length){addIntegrante();addIntegrante();}$('#modalConsorcio').modal('show');}).catch(function(e){toast(e.message||'No se pudo cargar el consorcio.','danger');});});}
  function saveConsorcio(f){if(!f.checkValidity()){f.classList.add('was-validated');return;}clearValidation(f);var data=new FormData(f), creating=!f.elements.id.value;post(endpoints.con,creating?'crear':'actualizar',data).then(function(r){$('#modalConsorcio').modal('hide');toast(r.message||'Consorcio guardado.','success');loadCon();}).catch(function(e){applyErrors(f,e.errors||{});toast(e.message||'No se pudo guardar el consorcio.','danger');});}
  function toggle(url,id,state,after){var data=new FormData();data.set('id',id);post(url,'cambiar_estado',data).then(function(r){toast(r.message||'Estado actualizado.','success');after();}).catch(function(e){toast(e.message||'No se pudo actualizar estado.','danger');});}

  document.getElementById('emp-btn-buscar').addEventListener('click',function(){empPage=1;loadEmp();});
  document.getElementById('con-btn-buscar').addEventListener('click',function(){conPage=1;loadCon();});
  document.getElementById('emp-btn-limpiar').addEventListener('click',function(){document.getElementById('emp-search').value='';document.getElementById('emp-estado').value='todos';empPage=1;loadEmp();});
  document.getElementById('con-btn-limpiar').addEventListener('click',function(){document.getElementById('con-search').value='';document.getElementById('con-estado').value='todos';conPage=1;loadCon();});
  ['emp','con'].forEach(function(prefix){document.getElementById(prefix+'-search').addEventListener('input',function(){clearTimeout(timer);timer=setTimeout(function(){if(prefix==='emp'){empPage=1;loadEmp();}else{conPage=1;loadCon();}},300);});document.getElementById(prefix+'-estado').addEventListener('change',function(){if(prefix==='emp'){empPage=1;loadEmp();}else{conPage=1;loadCon();}});document.getElementById(prefix+'-pagination').addEventListener('click',function(e){var b=e.target.closest('[data-page]');if(!b)return;if(prefix==='emp'){empPage=Number(b.dataset.page)||1;loadEmp();}else{conPage=Number(b.dataset.page)||1;loadCon();}});});
  var empNuevo=document.getElementById('emp-btn-nuevo');if(empNuevo)empNuevo.addEventListener('click',function(){openEmpresa(null);});
  var conNuevo=document.getElementById('con-btn-nuevo');if(conNuevo)conNuevo.addEventListener('click',function(){openConsorcio(null);});
  document.getElementById('emp-body').addEventListener('click',function(e){var b=e.target.closest('[data-action]');if(!b)return;var id=b.dataset.id;if(b.dataset.action==='edit')openEmpresa(id);else confirmAction(Number(b.dataset.state)===1?'Desea desactivar esta empresa?':'Desea activar esta empresa?',function(){toggle(endpoints.emp,id,b.dataset.state,loadEmp);});});
  document.getElementById('con-body').addEventListener('click',function(e){var b=e.target.closest('[data-action]');if(!b)return;var id=b.dataset.id;if(b.dataset.action==='edit')openConsorcio(id);else confirmAction(Number(b.dataset.state)===1?'Desea desactivar este consorcio?':'Desea activar este consorcio?',function(){toggle(endpoints.con,id,b.dataset.state,loadCon);});});
  document.getElementById('formEmpresa').addEventListener('submit',function(e){e.preventDefault();saveEmpresa(e.target);});
  document.getElementById('formConsorcio').addEventListener('submit',function(e){e.preventDefault();saveConsorcio(e.target);});
  document.getElementById('con-modalidad').addEventListener('change',syncModalidad);
  document.getElementById('con-btn-agregar-integrante').addEventListener('click',function(){addIntegrante();});
  document.getElementById('con-integrantes-body').addEventListener('click',function(e){var b=e.target.closest('[data-remove-integrante]');if(b)b.closest('tr').remove();});
  document.getElementById('modalConfirmClientesOk').addEventListener('click',function(){var cb=confirmCallback;confirmCallback=null;$('#modalConfirmClientes').modal('hide');if(typeof cb==='function')cb();});
  loadEmp();loadCon();cargarEmpresasActivas();
});
</script>
