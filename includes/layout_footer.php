      </div>
    </section>
  </div>

  <aside class="control-sidebar control-sidebar-dark"></aside>

  <footer class="main-footer cliente-footer">
    <strong>&copy; <?php echo date('Y'); ?> - <?php echo cb_e(CLIENTE_NOMBRE); ?> - Todos los derechos reservados.</strong>
    <div class="float-right d-none d-sm-inline-block">
      <b>Version</b> <?php echo cb_e(cb_cliente_version_label()); ?>
    </div>
  </footer>
</div>

<script src="<?php echo cb_e(cb_url('plugins/jquery/jquery.min.js')); ?>"></script>
<script src="<?php echo cb_e(cb_url('plugins/bootstrap/js/bootstrap.bundle.min.js')); ?>"></script>
<script src="<?php echo cb_e(cb_url('dist/js/adminlte.min.js')); ?>"></script>
<script src="<?php echo cb_e(cb_url('assets/js/cliente.js')); ?>"></script>
</body>
</html>