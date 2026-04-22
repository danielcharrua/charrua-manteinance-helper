<?php
/**
 * Plugin Name: Charrua Maintenance Helper
 * Plugin URI: https://charrua.es
 * Description: Maintenance toolkit for WordPress sites. Detects plugin deactivations after updates and keeps an activity log of plugin/theme changes.
 * Version: 1.4.0
 * Author: Daniel Pereyra Costas @ Charrua
 * Author URI: https://charrua.es
 * Text Domain: charrua-maintenance-helper
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CHARRUA_MH_VERSION', '1.4.0' );
define( 'CHARRUA_MH_FILE', __FILE__ );
define( 'CHARRUA_MH_PATH', plugin_dir_path( __FILE__ ) );
define( 'CHARRUA_MH_URL', plugin_dir_url( __FILE__ ) );
define( 'CHARRUA_MH_BASENAME', plugin_basename( __FILE__ ) );

require_once CHARRUA_MH_PATH . 'includes/class-database.php';
require_once CHARRUA_MH_PATH . 'includes/class-loader.php';

// GitHub update checker (public repo, no auth needed).
require_once CHARRUA_MH_PATH . 'vendor/yahnis-elsts/plugin-update-checker/load-v5p6.php';
use YahnisElsts\PluginUpdateChecker\v5p6\PucFactory;

$charrua_mh_update_checker = PucFactory::buildUpdateChecker(
    'https://github.com/Jesjsssi/charrua-maintenance-helper',
    __FILE__,
    'charrua-maintenance-helper'
);
$charrua_mh_update_checker->setBranch( 'main' );
$charrua_mh_update_checker->getVcsApi()->enableReleaseAssets();

// Activation and deactivation hooks must be registered at top-level.
register_activation_hook( __FILE__, array( 'Charrua_MH_Database', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Charrua_MH_Database', 'deactivate' ) );

// Boot the plugin on plugins_loaded to ensure all dependencies are available.
add_action( 'plugins_loaded', array( 'Charrua_MH_Loader', 'init' ) );
