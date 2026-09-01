<?php
/**
 * Plugin Name:       Memml Calendar
 * Plugin URI:        https://memml.com/
 * Description:       Display your nonprofit's Memml events and volunteer opportunities in WordPress.
 * Version:           0.4.3
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Memml
 * Author URI:        https://memml.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       memml
 * Domain Path:       /languages
 *
 * @package Memml
 */

defined( 'ABSPATH' ) || exit;

define( 'MEMML_VERSION', '0.4.3' );
define( 'MEMML_PLUGIN_FILE', __FILE__ );
define( 'MEMML_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MEMML_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once MEMML_PLUGIN_DIR . 'includes/class-feed-client.php';
require_once MEMML_PLUGIN_DIR . 'includes/class-settings.php';
require_once MEMML_PLUGIN_DIR . 'includes/class-renderer.php';
require_once MEMML_PLUGIN_DIR . 'includes/class-plugin.php';

/**
 * Starts the plugin after all active plugins are loaded.
 *
 * @return void
 */
function memml_calendar_initialize() {
	$plugin = new Memml_Plugin();
	$plugin->register();
}

add_action( 'plugins_loaded', 'memml_calendar_initialize' );
