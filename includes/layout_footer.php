      </div>
    </section>
  </div>

  <aside class="control-sidebar control-sidebar-dark"></aside>

  <?php
    $cbFooterVisual = cb_get_visual_config();
    $cbFooterTexto = trim((string) ($cbFooterVisual['footer_texto'] ?? ''));
    if ($cbFooterTexto === '') {
        $cbFooterTexto = (string) CLIENTE_NOMBRE . ' - Todos los derechos reservados.';
    }
    $cbFooterVersion = trim((string) ($cbFooterVisual['footer_version_label'] ?? ''));
    if ($cbFooterVersion === '') {
        $cbFooterVersion = cb_cliente_version_label();
    }
    $GLOBALS['CB_CLIENTE_VERSION_LABEL_OVERRIDE'] = $cbFooterVersion;
  ?>
  <footer class="main-footer cliente-footer">
    <strong>&copy; <?php echo date('Y'); ?> - <?php echo cb_e($cbFooterTexto); ?></strong>
    <div class="float-right d-none d-sm-inline-block">
      <b>Versión</b> <?php echo cb_e(cb_cliente_version_label()); ?>
    </div>
  </footer>
</div>

<script src="<?php echo cb_e(cb_url('plugins/jquery/jquery.min.js')); ?>"></script>
<script src="<?php echo cb_e(cb_url('plugins/bootstrap/js/bootstrap.bundle.min.js')); ?>"></script>
<script src="<?php echo cb_e(cb_url('dist/js/adminlte.min.js')); ?>"></script>
<script src="<?php echo cb_e(cb_url('assets/js/cliente.js')); ?>"></script>
</body>
</html>
