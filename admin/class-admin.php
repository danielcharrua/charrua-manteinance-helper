<?php
/**
 * Admin UI: menu pages and request handling for all modules.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Charrua_MH_Admin {

    /**
     * Register admin hooks.
     */
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menus' ) );
        add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
    }

    /**
     * Register the admin menu and subpages.
     */
    public static function register_menus() {
        // Main menu page (opens Activity Log).
        add_menu_page(
            __( 'Maintenance Helper', 'charrua-maintenance-helper' ),
            __( 'Maintenance', 'charrua-maintenance-helper' ),
            'manage_options',
            'charrua-mh-activity-log',
            array( __CLASS__, 'render_activity_log_page' ),
            'dashicons-shield',
            80
        );

        // Activity Log subpage.
        add_submenu_page(
            'charrua-mh-activity-log',
            __( 'Activity Log', 'charrua-maintenance-helper' ),
            __( 'Activity Log', 'charrua-maintenance-helper' ),
            'manage_options',
            'charrua-mh-activity-log',
            array( __CLASS__, 'render_activity_log_page' )
        );

        // Plugin Monitor subpage.
        add_submenu_page(
            'charrua-mh-activity-log',
            __( 'Plugin Monitor', 'charrua-maintenance-helper' ),
            __( 'Plugin Monitor', 'charrua-maintenance-helper' ),
            'manage_options',
            'charrua-mh-monitor',
            array( __CLASS__, 'render_monitor_page' )
        );
    }

    /**
     * Handle admin POST actions (dismiss alerts, etc.).
     */
    public static function handle_actions() {
        if ( ! isset( $_REQUEST['charrua_mh_action'] ) ) {
            return;
        }

        // Verify user capability.
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $action = sanitize_key( wp_unslash( $_REQUEST['charrua_mh_action'] ) );

        // Dismiss single alert.
        if ( 'dismiss_alert' === $action ) {
            if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'charrua_mh_dismiss_alert' ) ) {
                wp_die( __( 'Security check failed.', 'charrua-maintenance-helper' ) );
            }

            $alert_id = isset( $_REQUEST['alert_id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['alert_id'] ) ) : '';
            if ( ! empty( $alert_id ) ) {
                Charrua_MH_Plugin_Monitor::dismiss_alert( $alert_id );
            }

            wp_safe_redirect( admin_url( 'admin.php?page=charrua-mh-monitor&dismissed=1' ) );
            exit;
        }

        // Dismiss all alerts.
        if ( 'dismiss_all' === $action ) {
            if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'charrua_mh_dismiss_all' ) ) {
                wp_die( __( 'Security check failed.', 'charrua-maintenance-helper' ) );
            }

            Charrua_MH_Plugin_Monitor::dismiss_all_alerts();

            wp_safe_redirect( admin_url( 'admin.php?page=charrua-mh-monitor&dismissed_all=1' ) );
            exit;
        }
    }

    /**
     * Render the Plugin Monitor page.
     */
    public static function render_monitor_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $alerts = Charrua_MH_Plugin_Monitor::get_alerts();
        require CHARRUA_MH_PATH . 'admin/views/plugin-monitor.php';
    }

    /**
     * Render the Activity Log page.
     */
    public static function render_activity_log_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $per_page    = 30;
        $current_page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
        $filter_type  = isset( $_GET['object_type'] ) ? sanitize_key( wp_unslash( $_GET['object_type'] ) ) : '';

        $result = Charrua_MH_Activity_Log::get_entries( $per_page, $current_page, $filter_type );
        $items  = $result['items'];
        $total  = $result['total'];
        $pages  = ceil( $total / $per_page );

        require CHARRUA_MH_PATH . 'admin/views/activity-log.php';
    }
}
