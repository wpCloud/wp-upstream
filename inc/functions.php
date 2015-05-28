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
	$class = 'error';
	if ( class_exists( 'WPUpstream\Plugin' ) && WPUpstream\Plugin::has_instance() && WPUpstream\Plugin::instance()->get_status() ) {
		$status = __( '%s is running.', 'wpupstream' );
		$class = '';
	}

	$wp_admin_bar->add_node( array(
		'id'		=> 'wp-upstream',
		'parent'	=> 'top-secondary',
		'title'		=> '<span class="ab-icon dashicons dashicons-upload ' . $class . '"></span>',
	));

	$wp_admin_bar->add_node( array(
		'id'		=> 'wp-upstream-status',
		'parent'	=> 'wp-upstream',
		'title'		=> '<span class="' . $class . '">' . sprintf( $status, 'WP Upstream' ) . '</span>',
	) );
}

function wpupstream_add_inline_style() {
	if ( ! is_super_admin() ) {
		return;
	}

	?>
	<style type="text/css">
		#wpadminbar .ab-icon.success,
		#wpadminbar .ab-icon.success:before,
		#wpadminbar .ab-item.success,
		#wpadminbar .ab-item.success:before,
		#wpadminbar .ab-item > .success,
		#wpadminbar .hover .ab-icon.success,
		#wpadminbar .hover .ab-icon.success:before,
		#wpadminbar .hover .ab-item.success,
		#wpadminbar .hover .ab-item.success:before,
		#wpadminbar .hover .ab-item > .success {
			color: #7ad03a;
		}

		#wpadminbar .ab-icon.error,
		#wpadminbar .ab-icon.error:before,
		#wpadminbar .ab-item.error,
		#wpadminbar .ab-item.error:before,
		#wpadminbar .ab-item > .error,
		#wpadminbar .hover .ab-icon.error,
		#wpadminbar .hover .ab-icon.error:before,
		#wpadminbar .hover .ab-item.error,
		#wpadminbar .hover .ab-item.error:before,
		#wpadminbar .hover .ab-item > .error {
			color: #dd3d36;
		}
	</style>
	<?php
}
