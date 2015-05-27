<?php
/**
 * @package WPOD
 * @version 0.1.1
 * @author Usability Dynamics Inc.
 */

function wpupstream_load_textdomain() {
	return load_plugin_textdomain( 'wpupstream', false, WP_UPSTREAM_BASENAME . '/languages/' );
}

function wpupstream_display_wpversion_error_notice() {
	global $wp_version;
	?>
	<div class="error">
		<p><?php printf( __( 'Fatal error with plugin %s', 'wpupstream' ), '<strong>WP Upstream</strong>' ); ?></p>
		<p><?php printf( __( 'The plugin requires WordPress version %1$s. However, you are using version %2$s.', 'wpupstream' ), WP_UPSTREAM_REQUIRED_WP, $wp_version ); ?></p>
		<p><?php _e( 'The plugin has been deactivated for now.', 'wpupstream' ); ?></p>
	</div>
	<?php
}

function wpupstream_display_phpversion_error_notice() {
	?>
	<div class="error">
		<p><?php printf( __( 'Fatal error with plugin %s', 'wpupstream' ), '<strong>WP Upstream</strong>' ); ?></p>
		<p><?php printf( __( 'The plugin requires PHP version %1$s. However, you are using version %2$s.', 'wpupstream' ), WP_UPSTREAM_REQUIRED_PHP, phpversion() ); ?></p>
		<p><?php _e( 'The plugin has been deactivated for now.', 'wpupstream' ); ?></p>
	</div>
	<?php
}

function wpupstream_display_spl_error_notice() {
	?>
	<div class="error">
		<p><?php printf( __( 'Fatal error with plugin %s', 'wpupstream' ), '<strong>WP Upstream</strong>' ); ?></p>
		<p><?php _e( 'The PHP SPL functions can not be found. Please ask your hosting provider to enable them.', 'wpupstream' ); ?></p>
		<p><?php _e( 'The plugin has been deactivated for now.', 'wpupstream' ); ?></p>
	</div>
	<?php
}

function wpupstream_display_git_warning_notice() {
	?>
	<div class="update-nag">
		<p><?php printf( __( 'Warning for plugin %s', 'wpupstream' ), '<strong>WP Upstream</strong>' ); ?></p>
		<p><?php _e( 'Either git could not be found or the PHP function <code>exec</code> is not available.', 'wpupstream' ); ?></p>
	</div>
	<?php
}

function wpupstream_deactivate() {
	deactivate_plugins( WP_UPSTREAM_BASENAME );
	if ( isset( $_GET['activate'] ) ) {
		unset( $_GET['activate'] );
	}
}


/**
 * Show indicator if enabled but broken or whatever else is critical.
 *
 * @param $wp_admin_bar
 */
function wpupstream_admin_bar_menu( $wp_admin_bar ) {

  if( !is_super_admin() || !is_multisite() || !$wp_admin_bar ) {
    return;
  }

  $wp_admin_bar->add_menu( array(
    'id' => 'wp-upstream',
    'parent' => 'top-secondary',
    'title' => sprintf( __( '%s' ), '[]' ),
    'href' => '#'
  ));

}
