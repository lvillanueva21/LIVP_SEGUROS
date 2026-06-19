<?php
require_once __DIR__ . '/../../includes/module_guard.php';

// Al duplicar esta plantilla en modules/{codigo}/, reemplazar "_plantilla" por el codigo real del modulo.
cb_require_module_context('_plantilla');
?>
<div class="card card-primary card-outline">
  <div class="card-header">
    <h3 class="card-title">Modulo plantilla</h3>
  </div>
  <div class="card-body">
    <p class="mb-2">Esta plantilla muestra la estructura minima de un modulo protegido.</p>
    <p class="mb-0">Duplicar esta carpeta como modules/{codigo}, cambiar el codigo y agregar la interfaz requerida por el modulo real.</p>
  </div>
</div>
