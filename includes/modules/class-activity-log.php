<?php
/**
 * Module: Activity Log
 * Tracks plugin and theme events (install, update, activate, deactivate, delete, switch).
 * Stores entries in a custom database table with automatic 90-day cleanup.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Charrua_MH_Activity_Log {

    /**
     * Stores plugin versions before updates so we can log "from → to".
     *
     * @var array Keyed by plugin basename.
     */
    private static $pre_update_versions = array();

    /**
     * Stores theme versions before updates.
     *
     * @var array Keyed by theme slug.
     */
    private static $pre_update_theme_versions = array();
    /**
     * Full snapshot of all installed plugin versions, taken once before any update.
     * Keyed by folder name => array( 'version' => ..., 'name' => ..., 'author' => ... ).
     *
     * @var array|null
     */
    private static $all_plugins_snapshot = null;
    /**
     * Register all event hooks.
     */
    public static function init() {
        // Plugin events.
        add_action( 'activated_plugin', array( __CLASS__, 'on_plugin_activated' ), 10, 2 );
        add_action( 'deactivated_plugin', array( __CLASS__, 'on_plugin_deactivated' ), 10, 2 );
        add_action( 'deleted_plugin', array( __CLASS__, 'on_plugin_deleted' ), 10, 2 );

        // Capture versions before update, then log after.
        add_filter( 'upgrader_pre_install', array( __CLASS__, 'capture_pre_update_versions' ), 10, 2 );
        add_action( 'upgrader_process_complete', array( __CLASS__, 'on_upgrader_complete' ), 10, 2 );

        // Theme events.
        add_action( 'switch_theme', array( __CLASS__, 'on_theme_switch' ), 10, 3 );
    }

    /**
     * Capture current versions of plugins/themes before the update replaces files.
     *
     * @param bool|WP_Error $response Install response (pass-through).
     * @param array         $hook_extra Extra data about the update.
     * @return bool|WP_Error
     */
    public static function capture_pre_update_versions( $response, $hook_extra ) {
        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        // On first call, snapshot ALL installed plugins (covers zip upload replacements).
        if ( null === self::$all_plugins_snapshot ) {
            if ( ! function_exists( 'get_plugins' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            self::$all_plugins_snapshot = array();
            foreach ( get_plugins() as $basename => $info ) {
                $folder = dirname( $basename );
                if ( '.' !== $folder ) {
                    self::$all_plugins_snapshot[ $folder ] = array(
                        'version' => $info['Version'],
                        'name'    => $info['Name'],
                        'author'  => $info['Author'],
                    );
                }
            }
        }

        // Single plugin update.
        if ( isset( $hook_extra['plugin'] ) ) {
            $plugin_file = WP_PLUGIN_DIR . '/' . $hook_extra['plugin'];
            if ( file_exists( $plugin_file ) ) {
                $data = get_plugin_data( $plugin_file, false, false );
                self::$pre_update_versions[ $hook_extra['plugin'] ] = $data['Version'];
            }
        }

        // Single theme update.
        if ( isset( $hook_extra['theme'] ) ) {
            $theme = wp_get_theme( $hook_extra['theme'] );
            if ( $theme->exists() ) {
                self::$pre_update_theme_versions[ $hook_extra['theme'] ] = $theme->get( 'Version' );
            }
        }

        return $response;
    }

    /**
     * Log a plugin activation.
     *
     * @param string $plugin Plugin basename.
     * @param bool   $network_wide Whether network-wide activation.
     */
    public static function on_plugin_activated( $plugin, $network_wide ) {
        $plugin_data = self::get_plugin_data( $plugin );
        self::log(
            'activated',
            'plugin',
            $plugin_data['Name'],
            $plugin_data['Author'],
            $plugin_data['Version']
        );
    }

    /**
     * Log a plugin deactivation.
     *
     * @param string $plugin Plugin basename.
     * @param bool   $network_deactivating Whether network-wide deactivation.
     */
    public static function on_plugin_deactivated( $plugin, $network_deactivating ) {
        $plugin_data = self::get_plugin_data( $plugin );
        self::log(
            'deactivated',
            'plugin',
            $plugin_data['Name'],
            $plugin_data['Author'],
            $plugin_data['Version']
        );
    }

    /**
     * Log a plugin deletion.
     *
     * @param string $plugin Plugin basename.
     * @param bool   $deleted Whether the plugin was successfully deleted.
     */
    public static function on_plugin_deleted( $plugin, $deleted ) {
        if ( ! $deleted ) {
            return;
        }
        self::log(
            'deleted',
            'plugin',
            $plugin,
            ''
        );
    }

    /**
     * Log installs and updates for plugins and themes after the upgrader finishes.
     *
     * @param WP_Upgrader $upgrader Upgrader instance.
     * @param array       $hook_extra Extra data about what was upgraded.
     */
    public static function on_upgrader_complete( $upgrader, $hook_extra ) {
        if ( ! isset( $hook_extra['type'] ) || ! isset( $hook_extra['action'] ) ) {
            return;
        }

        $type   = $hook_extra['type'];   // 'plugin' or 'theme'.
        $action = $hook_extra['action']; // 'install' or 'update'.

        if ( 'plugin' === $type ) {
            self::log_plugin_upgrader_event( $upgrader, $hook_extra, $action );
        } elseif ( 'theme' === $type ) {
            self::log_theme_upgrader_event( $upgrader, $hook_extra, $action );
        }
    }

    /**
     * Log plugin install or update events.
     *
     * @param WP_Upgrader $upgrader   Upgrader instance.
     * @param array       $hook_extra Extra data.
     * @param string      $action     'install' or 'update'.
     */
    private static function log_plugin_upgrader_event( $upgrader, $hook_extra, $action ) {
        $event_type = ( 'install' === $action ) ? 'installed' : 'updated';

        // Bulk update.
        if ( isset( $hook_extra['plugins'] ) && is_array( $hook_extra['plugins'] ) ) {
            foreach ( $hook_extra['plugins'] as $plugin ) {
                $plugin_data  = self::get_plugin_data( $plugin );
                $version_from = isset( self::$pre_update_versions[ $plugin ] ) ? self::$pre_update_versions[ $plugin ] : '';
                self::log(
                    $event_type,
                    'plugin',
                    $plugin_data['Name'],
                    $plugin_data['Author'],
                    $plugin_data['Version'],
                    $version_from
                );
            }
            return;
        }

        // Single update/install.
        if ( isset( $hook_extra['plugin'] ) ) {
            $plugin_data  = self::get_plugin_data( $hook_extra['plugin'] );
            $version_from = isset( self::$pre_update_versions[ $hook_extra['plugin'] ] ) ? self::$pre_update_versions[ $hook_extra['plugin'] ] : '';
            self::log(
                $event_type,
                'plugin',
                $plugin_data['Name'],
                $plugin_data['Author'],
                $plugin_data['Version'],
                $version_from
            );
            return;
        }

        // Fallback for install/update via zip upload.
        if ( isset( $upgrader->result ) && isset( $upgrader->result['destination_name'] ) ) {
            $folder_name = $upgrader->result['destination_name'];
            $plugin_data = self::get_plugin_data_from_folder( $folder_name );

            // Check snapshot to determine if this was a replacement (update) or fresh install.
            $was_installed = ( null !== self::$all_plugins_snapshot && isset( self::$all_plugins_snapshot[ $folder_name ] ) );

            if ( $was_installed ) {
                $version_from = self::$all_plugins_snapshot[ $folder_name ]['version'];
                self::log(
                    'updated',
                    'plugin',
                    $plugin_data['Name'],
                    $plugin_data['Author'],
                    $plugin_data['Version'],
                    $version_from,
                    'Manual zip upload'
                );
            } else {
                self::log(
                    'installed',
                    'plugin',
                    $plugin_data['Name'],
                    $plugin_data['Author'],
                    $plugin_data['Version'],
                    '',
                    'Manual zip upload'
                );
            }
        }
    }

    /**
     * Log theme install or update events.
     *
     * @param WP_Upgrader $upgrader   Upgrader instance.
     * @param array       $hook_extra Extra data.
     * @param string      $action     'install' or 'update'.
     */
    private static function log_theme_upgrader_event( $upgrader, $hook_extra, $action ) {
        $event_type = ( 'install' === $action ) ? 'installed' : 'updated';

        // Bulk update.
        if ( isset( $hook_extra['themes'] ) && is_array( $hook_extra['themes'] ) ) {
            foreach ( $hook_extra['themes'] as $theme_slug ) {
                $theme        = wp_get_theme( $theme_slug );
                $version_from = isset( self::$pre_update_theme_versions[ $theme_slug ] ) ? self::$pre_update_theme_versions[ $theme_slug ] : '';
                self::log(
                    $event_type,
                    'theme',
                    $theme->get( 'Name' ),
                    $theme->get( 'Author' ),
                    $theme->get( 'Version' ),
                    $version_from
                );
            }
            return;
        }

        // Single update/install.
        if ( isset( $hook_extra['theme'] ) ) {
            $theme        = wp_get_theme( $hook_extra['theme'] );
            $version_from = isset( self::$pre_update_theme_versions[ $hook_extra['theme'] ] ) ? self::$pre_update_theme_versions[ $hook_extra['theme'] ] : '';
            self::log(
                $event_type,
                'theme',
                $theme->get( 'Name' ),
                $theme->get( 'Author' ),
                $theme->get( 'Version' ),
                $version_from
            );
            return;
        }

        // Fallback for install via upgrader result (e.g. zip upload).
        if ( isset( $upgrader->result ) && isset( $upgrader->result['destination_name'] ) ) {
            $theme = wp_get_theme( $upgrader->result['destination_name'] );
            self::log(
                $event_type,
                'theme',
                $theme->exists() ? $theme->get( 'Name' ) : $upgrader->result['destination_name'],
                $theme->exists() ? $theme->get( 'Author' ) : '',
                $theme->exists() ? $theme->get( 'Version' ) : ''
            );
        }
    }

    /**
     * Log a theme switch.
     *
     * @param string   $new_name  New theme name.
     * @param WP_Theme $new_theme New theme object.
     * @param WP_Theme $old_theme Old theme object.
     */
    public static function on_theme_switch( $new_name, $new_theme, $old_theme ) {
        self::log(
            'switched',
            'theme',
            $new_name,
            $new_theme->get( 'Author' ),
            $new_theme->get( 'Version' ),
            '',
            sprintf( 'Switched from %s', $old_theme->get( 'Name' ) )
        );
    }

    /**
     * Insert a log entry into the activity log table.
     *
     * @param string $event_type        Event type (activated, deactivated, updated, installed, deleted, switched).
     * @param string $object_type       Object type (plugin or theme).
     * @param string $object_name       Name of the plugin or theme.
     * @param string $object_author     Author of the plugin or theme.
     * @param string $version           Optional current version string.
     * @param string $version_from      Optional previous version (for updates).
     * @param string $details           Optional additional details.
     */
    public static function log( $event_type, $object_type, $object_name, $object_author = '', $version = '', $version_from = '', $details = '' ) {
        global $wpdb;

        $table_name = Charrua_MH_Database::get_table_name();

        // Gather user info.
        $user_ip = self::get_client_ip();
        $source  = self::detect_source();

        // Strip HTML tags from author (plugin headers may contain links).
        $object_author = wp_strip_all_tags( $object_author );

        $wpdb->insert(
            $table_name,
            array(
                'event_type'          => sanitize_key( $event_type ),
                'object_type'         => sanitize_key( $object_type ),
                'object_name'         => sanitize_text_field( $object_name ),
                'object_author'       => sanitize_text_field( $object_author ),
                'object_version'      => sanitize_text_field( $version ),
                'object_version_from' => sanitize_text_field( $version_from ),
                'details'             => sanitize_text_field( $details ),
                'user_id'             => get_current_user_id(),
                'user_ip'             => sanitize_text_field( $user_ip ),
                'source'              => sanitize_key( $source ),
                'created_at'          => current_time( 'mysql', true ),
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
        );
    }

    /**
     * Detect the execution context / source of the current request.
     *
     * @return string One of: admin, wp-cli, cron, rest-api, ajax, unknown.
     */
    private static function detect_source() {
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            return 'wp-cli';
        }

        if ( wp_doing_cron() ) {
            return 'cron';
        }

        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            return 'rest-api';
        }

        if ( wp_doing_ajax() ) {
            return 'ajax';
        }

        if ( is_admin() ) {
            return 'admin';
        }

        return 'unknown';
    }

    /**
     * Get the client IP address.
     *
     * @return string
     */
    private static function get_client_ip() {
        // Check common headers for proxied requests.
        $headers = array(
            'HTTP_CF_CONNECTING_IP', // Cloudflare.
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        );

        foreach ( $headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
                // HTTP_X_FORWARDED_FOR can contain multiple IPs; take the first.
                if ( strpos( $ip, ',' ) !== false ) {
                    $ip = trim( explode( ',', $ip )[0] );
                }
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }

        return '';
    }

    /**
     * Get entries from the activity log.
     *
     * @param int    $per_page Number of entries per page.
     * @param int    $page     Current page number.
     * @param string $object_type Optional filter by object type.
     * @return array { 'items' => array, 'total' => int }
     */
    public static function get_entries( $per_page = 30, $page = 1, $object_type = '' ) {
        global $wpdb;

        $table_name = Charrua_MH_Database::get_table_name();
        $offset     = ( $page - 1 ) * $per_page;
        $where      = '';

        if ( ! empty( $object_type ) ) {
            $where = $wpdb->prepare( 'WHERE object_type = %s', sanitize_key( $object_type ) );
        }

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} {$where}" );

        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $per_page,
                $offset
            )
        );

        return array(
            'items' => $items ? $items : array(),
            'total' => $total,
        );
    }

    /**
     * Get plugin data safely (plugin may have been deleted).
     *
     * @param string $plugin Plugin basename (e.g. 'woocommerce/woocommerce.php').
     * @return array Plugin data array with at least 'Name' and 'Version' keys.
     */
    private static function get_plugin_data( $plugin ) {
        $plugin_file = WP_PLUGIN_DIR . '/' . $plugin;

        if ( ! file_exists( $plugin_file ) ) {
            return array(
                'Name'    => $plugin,
                'Version' => '',
                'Author'  => '',
            );
        }

        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return get_plugin_data( $plugin_file, false, false );
    }

    /**
     * Find the main plugin file inside a folder and return its data.
     * Used as fallback when installing via zip upload.
     *
     * @param string $folder_name Plugin folder name (e.g. 'woocommerce').
     * @return array Plugin data array with at least 'Name', 'Version' and 'Author' keys.
     */
    private static function get_plugin_data_from_folder( $folder_name ) {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();

        foreach ( $all_plugins as $plugin_basename => $plugin_info ) {
            if ( strpos( $plugin_basename, $folder_name . '/' ) === 0 ) {
                return array(
                    'Name'    => $plugin_info['Name'],
                    'Version' => $plugin_info['Version'],
                    'Author'  => $plugin_info['Author'],
                );
            }
        }

        return array(
            'Name'    => $folder_name,
            'Version' => '',
            'Author'  => '',
        );
    }
}
