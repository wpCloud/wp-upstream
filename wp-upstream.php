<?php
/**
 * Plugin Name: WP-Upstream
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
 * @version 0.1.2
 * @author Usability Dynamics Inc.
 */

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

define( 'WP_UPSTREAM_VERSION', '0.1.1' );
define( 'WP_UPSTREAM_REQUIRED_PHP', '5.3.0' );
define( 'WP_UPSTREAM_REQUIRED_WP', '4.0' );

define( 'WP_UPSTREAM_BASENAME', plugin_basename( __FILE__ ) );
define( 'WP_UPSTREAM_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'WP_UPSTREAM_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );

if ( ! defined( 'WP_UPSTREAM_DEBUG' ) ) {
	define( 'WP_UPSTREAM_DEBUG', true );
}

function wpupstream() {

  if( class_exists( 'WPUpstream\Plugin' ) ) {
    return WPUpstream\Plugin::instance();
  }

}

function wpupstream_maybe_init() {
	global $wp_version;

	require_once WP_UPSTREAM_PATH . '/inc/functions.php';

	$running = false;

	add_action( 'plugins_loaded', 'wpupstream_load_textdomain', 1 );

  // Admin bar status indicator.
  add_action( 'admin_bar_menu', 'wpupstream_admin_bar_menu', 10 );

  if ( function_exists( 'spl_autoload_register' ) ) {

    if ( file_exists( WP_UPSTREAM_PATH . '/vendor/autoload_52.php' ) ) {
      require_once WP_UPSTREAM_PATH . '/vendor/autoload_52.php';
		}

    // Check for native autoload file.
    if ( file_exists( WP_UPSTREAM_PATH . '/vendor/autoload.php' ) ) {
      require_once WP_UPSTREAM_PATH . '/vendor/autoload.php';
		}

		if ( version_compare( phpversion(), WP_UPSTREAM_REQUIRED_PHP ) >= 0 ) {
			if ( version_compare( $wp_version, WP_UPSTREAM_REQUIRED_WP ) >= 0 ) {
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
