<?php
require_once __DIR__ . '/includes/helpers.php';

if (cb_is_logged_in()) {
    cb_redirect('dashboard.php');
}

cb_redirect('login.php');

