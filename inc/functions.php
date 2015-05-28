<?php
/**
 * @package WPUpstream
 * @version 0.1.2
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
	</div>
	<?php
}

function wpupstream_display_phpversion_error_notice() {
	?>
	<div class="error">
		<p><?php printf( __( 'Fatal error with plugin %s', 'wpupstream' ), '<strong>WP Upstream</strong>' ); ?></p>
		<p><?php printf( __( 'The plugin requires PHP version %1$s. However, you are using version %2$s.', 'wpupstream' ), WP_UPSTREAM_REQUIRED_PHP, phpversion() ); ?></p>
	</div>
	<?php
}

function wpupstream_display_spl_error_notice() {
	?>
	<div class="error">
		<p><?php printf( __( 'Fatal error with plugin %s', 'wpupstream' ), '<strong>WP Upstream</strong>' ); ?></p>
		<p><?php _e( 'The PHP SPL functions can not be found. Please ask your hosting provider to enable them.', 'wpupstream' ); ?></p>
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
	if ( is_admin() && current_user_can( 'activate_plugins' ) ) {
		deactivate_plugins( WP_UPSTREAM_BASENAME );
		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}
	}
}

/**
 * Show indicator if enabled but broken or whatever else is critical.
 *
 * @param $wp_admin_bar
 */
function wpupstream_admin_bar_menu( $wp_admin_bar ) {
	if ( ! is_super_admin() || ! $wp_admin_bar ) {
		return;
	}

	$status = __( '%s is not running.', 'wpupstream' );
	$color = '#dd3d36';
	if ( class_exists( 'WPUpstream\Plugin' ) && WPUpstream\Plugin::has_instance() && WPUpstream\Plugin::instance()->get_status() ) {
		$status = __( '%s is running.', 'wpupstream' );
		$color = '#eeeeee';
		//$color = '#7ad03a';
	}

	$wp_admin_bar->add_menu( array(
		'id'		=> 'wp-upstream',
		'parent'	=> 'top-secondary',
		'title'		=> '<span style="color:' . $color . ';">' . sprintf( $status, 'WP Upstream' ) . '</span>',
		'href'		=> '#'
	));
}
