<?php
/**
 * Module: Plugin Monitor
 * Detects plugins that become deactivated unexpectedly during update processes.
 *
 * Flow:
 * 1. Before updates: snapshot active plugins in a short-lived transient.
 * 2. After updates: compare snapshot with current active plugins.
 * 3. If any plugin was active before and inactive after, store an alert.
 * 4. Alerts are displayed on the Plugin Monitor admin page.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Charrua_MH_Plugin_Monitor {

    const ALERTS_OPTION   = 'charrua_mh_monitor_alerts';
    const SNAPSHOT_KEY    = 'charrua_mh_pre_update_snapshot';
    const SNAPSHOT_TTL    = 300; // 5 minutes.

    /**
     * Register hooks.
     */
    public static function init() {
        add_filter( 'upgrader_pre_install', array( __CLASS__, 'capture_snapshot' ), 10, 2 );
        add_action( 'upgrader_process_complete', array( __CLASS__, 'check_deactivations' ), 20, 2 );
    }

    /**
     * Capture the list of active plugins before an update begins.
     * Only sets the transient once per update batch (5 min TTL auto-cleans).
     *
     * @param bool|WP_Error $response Install response.
     * @param array         $hook_extra Extra data.
     * @return bool|WP_Error Unmodified response (pass-through filter).
     */
    public static function capture_snapshot( $response, $hook_extra ) {
        // Only snapshot once per batch; skip if transient already exists.
        if ( false !== get_transient( self::SNAPSHOT_KEY ) ) {
            return $response;
        }

        $active_plugins = get_option( 'active_plugins', array() );
        set_transient( self::SNAPSHOT_KEY, $active_plugins, self::SNAPSHOT_TTL );

        return $response;
    }

    /**
     * After updates complete, compare the snapshot with current active plugins.
     * If any were deactivated, store alerts.
     *
     * @param WP_Upgrader $upgrader   Upgrader instance.
     * @param array       $hook_extra Extra data about what was upgraded.
     */
    public static function check_deactivations( $upgrader, $hook_extra ) {
        // Only check after plugin updates.
        if ( ! isset( $hook_extra['type'] ) || 'plugin' !== $hook_extra['type'] ) {
            return;
        }

        $before = get_transient( self::SNAPSHOT_KEY );
        if ( false === $before || ! is_array( $before ) ) {
            return;
        }

        $after        = get_option( 'active_plugins', array() );
        $deactivated  = array_diff( $before, $after );

        // Clean up the transient regardless.
        delete_transient( self::SNAPSHOT_KEY );

        if ( empty( $deactivated ) ) {
            return;
        }

        // Build alerts for each deactivated plugin.
        $alerts = self::get_alerts();

        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        foreach ( $deactivated as $plugin_basename ) {
            $plugin_file = WP_PLUGIN_DIR . '/' . $plugin_basename;
            $plugin_name = $plugin_basename;

            if ( file_exists( $plugin_file ) ) {
                $plugin_data = get_plugin_data( $plugin_file, false, false );
                $plugin_name = $plugin_data['Name'];
            }

            $alerts[] = array(
                'id'          => wp_generate_uuid4(),
                'plugin'      => $plugin_basename,
                'plugin_name' => $plugin_name,
                'detected_at' => current_time( 'mysql', true ),
                'user_id'     => get_current_user_id(),
            );
        }

        update_option( self::ALERTS_OPTION, $alerts, false );
    }

    /**
     * Get all pending alerts.
     *
     * @return array
     */
    public static function get_alerts() {
        $alerts = get_option( self::ALERTS_OPTION, array() );
        return is_array( $alerts ) ? $alerts : array();
    }

    /**
     * Dismiss a single alert by its ID.
     *
     * @param string $alert_id UUID of the alert to dismiss.
     * @return bool Whether the alert was found and removed.
     */
    public static function dismiss_alert( $alert_id ) {
        $alerts  = self::get_alerts();
        $updated = array_filter( $alerts, function( $alert ) use ( $alert_id ) {
            return $alert['id'] !== $alert_id;
        } );

        if ( count( $updated ) === count( $alerts ) ) {
            return false;
        }

        update_option( self::ALERTS_OPTION, array_values( $updated ), false );
        return true;
    }

    /**
     * Dismiss all alerts at once.
     */
    public static function dismiss_all_alerts() {
        update_option( self::ALERTS_OPTION, array(), false );
    }
}
