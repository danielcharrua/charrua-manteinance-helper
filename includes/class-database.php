<?php
/**
 * Database management: table creation, activation, deactivation, and cleanup.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Charrua_MH_Database {

    const DB_VERSION     = '1.4.0';
    const DB_VERSION_KEY = 'charrua_mh_db_version';
    const TABLE_NAME     = 'charrua_mh_activity_log';

    /**
     * Get the full table name with prefix.
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Plugin activation callback.
     * Creates custom tables and schedules cron events.
     */
    public static function activate() {
        self::create_tables();
        self::schedule_cleanup();
    }

    /**
     * Plugin deactivation callback.
     * Clears scheduled cron events and transients.
     */
    public static function deactivate() {
        wp_clear_scheduled_hook( 'charrua_mh_cleanup_old_logs' );
        delete_transient( 'charrua_mh_pre_update_snapshot' );
    }

    /**
     * Create the activity log table.
     * Uses dbDelta for safe creation and future upgrades.
     */
    public static function create_tables() {
        global $wpdb;

        $table_name      = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            object_type varchar(20) NOT NULL,
            object_name varchar(255) NOT NULL,
            object_author varchar(255) DEFAULT '',
            object_version varchar(50) DEFAULT '',
            object_version_from varchar(50) DEFAULT '',
            details text DEFAULT '',
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            user_ip varchar(45) DEFAULT '',
            source varchar(30) DEFAULT '',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_event_type (event_type),
            KEY idx_object_type (object_type),
            KEY idx_created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( self::DB_VERSION_KEY, self::DB_VERSION );
    }

    /**
     * Check if tables need upgrade and run if necessary.
     */
    public static function maybe_upgrade() {
        $installed_version = get_option( self::DB_VERSION_KEY, '0' );
        if ( version_compare( $installed_version, self::DB_VERSION, '<' ) ) {
            self::create_tables();
        }
    }

    /**
     * Schedule the daily cleanup cron event.
     */
    public static function schedule_cleanup() {
        if ( ! wp_next_scheduled( 'charrua_mh_cleanup_old_logs' ) ) {
            wp_schedule_event( time(), 'daily', 'charrua_mh_cleanup_old_logs' );
        }
    }

    /**
     * Delete activity log entries older than 90 days.
     */
    public static function cleanup_old_logs() {
        global $wpdb;

        $table_name = self::get_table_name();
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table_name} WHERE created_at < %s",
                gmdate( 'Y-m-d H:i:s', strtotime( '-90 days' ) )
            )
        );
    }

    /**
     * Remove all plugin data. Called from uninstall.php.
     */
    public static function uninstall() {
        global $wpdb;

        // Drop custom table.
        $table_name = self::get_table_name();
        $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

        // Remove options.
        delete_option( self::DB_VERSION_KEY );
        delete_option( 'charrua_mh_monitor_alerts' );

        // Remove transients.
        delete_transient( 'charrua_mh_pre_update_snapshot' );

        // Clear scheduled events.
        wp_clear_scheduled_hook( 'charrua_mh_cleanup_old_logs' );
    }
}
