<?php
/** Delete local plugin options only on uninstall. */
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}
delete_option('tvrtke_registar_api_url');
delete_option('tvrtke_registar_enabled');
