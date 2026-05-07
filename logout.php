<?php
require_once __DIR__ . '/includes/helpers.php';

cb_boot_session();
cb_destroy_session();
cb_redirect('login.php');

