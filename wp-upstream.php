<?php
/**
 * Plugin Name: WP Upstream
 * Plugin URI: http://wordpress.org/plugins/wp-upstream/
 * Description: This plugin handles Git automation in WordPress.
 * Version: 0.1.1
 * Author: Usability Dynamics Inc.
 * Author URI: http://www.usabilitydynamics.com/
 * License: GNU General Public License v2
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wpupstream
 * Domain Path: /languages/
 * Tags: wordpress, plugin, git, automization
 * GitHub Plugin URI: wpCloud/wp-upstream
 * GitHub Branch: v0.1
 * Network: True
*/
/**
 * @package WPOD
 * @version 0.1.1
 * @author Usability Dynamics Inc.
 */

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

define( 'WPUPSTREAM_VERSION', '0.1.1' );
define( 'WPUPSTREAM_REQUIRED_PHP', '5.3.0' );
define( 'WPUPSTREAM_REQUIRED_WP', '4.0' );

define( 'WPUPSTREAM_BASENAME', plugin_basename( __FILE__ ) );
define( 'WPUPSTREAM_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'WPUPSTREAM_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );

if ( ! defined( 'WPUPSTREAM_DEBUG' ) ) {
	define( 'WPUPSTREAM_DEBUG', true );
}

function wpupstream() {
	return WPUpstream\Plugin::instance();
}

function wpupstream_maybe_init() {
	global $wp_version;

	require_once WPUPSTREAM_PATH . '/inc/functions.php';

	$running = false;

	add_action( 'plugins_loaded', 'wpupstream_load_textdomain', 1 );

	if ( function_exists( 'spl_autoload_register' ) ) {
		if ( file_exists( WPUPSTREAM_PATH . '/vendor/autoload_52.php' ) ) {
			require_once WPUPSTREAM_PATH . '/vendor/autoload_52.php';
		}

		if ( version_compare( phpversion(), WPUPSTREAM_REQUIRED_PHP ) >= 0 ) {
			if ( version_compare( $wp_version, WPUPSTREAM_REQUIRED_WP ) >= 0 ) {
				$running = true;
				add_action( 'plugins_loaded', 'wpupstream' );
			} else {
				add_action( 'admin_notices', 'wpupstream_display_wpversion_error_notice' );
			}
		} else {
			add_action( 'admin_notices', 'wpupstream_display_phpversion_error_notice' );
		}
	} else {
		add_action( 'admin_notices', 'wpupstream_display_spl_error_notice' );
	}

	if ( ! $running && is_admin() ) {
		add_action( 'admin_init', 'wpupstream_deactivate' );
	}
}
wpupstream_maybe_init();
