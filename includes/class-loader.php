<?php
/**
 * Central loader: registers hooks for all modules.
 * Keeps the bootstrap file minimal and side-effect-free at load time.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Charrua_MH_Loader {

    /**
     * Initialize the plugin.
     * Loads modules and registers shared hooks.
     */
    public static function init() {
        // Run upgrade check on every load (cheap option comparison).
        Charrua_MH_Database::maybe_upgrade();
        Charrua_MH_Database::schedule_cleanup();

        // Load modules.
        require_once CHARRUA_MH_PATH . 'includes/modules/class-activity-log.php';
        require_once CHARRUA_MH_PATH . 'includes/modules/class-plugin-monitor.php';

        // Initialize modules.
        Charrua_MH_Activity_Log::init();
        Charrua_MH_Plugin_Monitor::init();

        // Load admin UI only in admin context.
        if ( is_admin() ) {
            require_once CHARRUA_MH_PATH . 'admin/class-admin.php';
            Charrua_MH_Admin::init();
        }

        // Cron hook for log cleanup.
        add_action( 'charrua_mh_cleanup_old_logs', array( 'Charrua_MH_Database', 'cleanup_old_logs' ) );
    }
}
