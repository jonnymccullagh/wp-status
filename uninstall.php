<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('wp_status_password');
