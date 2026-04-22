<?php
/**
 * Uninstall handler for Charrua Maintenance Helper.
 * Removes all plugin data (tables, options, transients, cron events).
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-database.php';

Charrua_MH_Database::uninstall();
