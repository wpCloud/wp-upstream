<?php
/**
 * @package WPUpstream
 * @version 0.1.3
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

	$global_class = '';
	if ( class_exists( 'WPUpstream\Plugin' ) && WPUpstream\Plugin::has_instance() && WPUpstream\Plugin::instance()->get_status() ) {
		if ( \WPUpstream\Util::has_uncommitted_changes() ) {
			$global_class = 'warning';
			$wp_admin_bar->add_node( array(
				'id'		=> 'wp-upstream-uncommitted-changes',
				'parent'	=> 'wp-upstream',
				'title'		=> '<span class="warning">' . __( 'The repository contains uncommitted changes.', 'wpupstream' ) . '</span>',
			) );
		}

		if ( \WPUpstream\Util::has_unpushed_commits() ) {
			$global_class = 'warning';
			$wp_admin_bar->add_node( array(
				'id'		=> 'wp-upstream-unpushed-commits',
				'parent'	=> 'wp-upstream',
				'title'		=> '<span class="warning">' . __( 'The repository contains unpushed commits.', 'wpupstream' ) . '</span>',
			) );
		}

		$wp_admin_bar->add_node( array(
			'id'		=> 'wp-upstream-repository-branch',
			'parent'	=> 'wp-upstream',
			'title'		=> sprintf( __( 'Branch: %s', 'wpupstream' ), \WPUpstream\Util::get_current_branch() ),
		) );

		$wp_admin_bar->add_node( array(
			'id'		=> 'wp-upstream-repository-version',
			'parent'	=> 'wp-upstream',
			'title'		=> sprintf( __( 'Version: %s', 'wpupstream' ), \WPUpstream\Util::get_repository_version( 'short' ) ),
		) );

		$commits = \WPUpstream\Util::get_commits( array( 'number' => 1 ) );
		if ( isset( $commits[0] ) ) {
			$latest_commit = $commits[0];

			$href = $latest_commit->is_pushed() ? $latest_commit->commit_url : '';

			$wp_admin_bar->add_node( array(
				'id'		=> 'wp-upstream-repository-latest-commit',
				'parent'	=> 'wp-upstream',
				'title'		=> sprintf( __( 'Latest Commit: %s', 'wpupstream' ), $latest_commit->commit_message ),
				'href'		=> $href,
				'meta'		=> array( 'target' => '_blank' ),
			) );
		}
	} else {
		$global_class = 'error';
		$wp_admin_bar->add_node( array(
			'id'		=> 'wp-upstream-status',
			'parent'	=> 'wp-upstream',
			'title'		=> '<span class="error">' . sprintf( __( '%s is not running.', 'wpupstream' ), 'WP Upstream' ) . '</span>',
		) );
	}

	$wp_admin_bar->add_node( array(
		'id'		=> 'wp-upstream',
		'parent'	=> 'top-secondary',
		'title'		=> '<span class="ab-icon dashicons dashicons-upload ' . $global_class . '"></span>',
	));
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

		#wpadminbar .ab-icon.warning,
		#wpadminbar .ab-icon.warning:before,
		#wpadminbar .ab-item.warning,
		#wpadminbar .ab-item.warning:before,
		#wpadminbar .ab-item > .warning,
		#wpadminbar .hover .ab-icon.warning,
		#wpadminbar .hover .ab-icon.warning:before,
		#wpadminbar .hover .ab-item.warning,
		#wpadminbar .hover .ab-item.warning:before,
		#wpadminbar .hover .ab-item > .warning {
			color: #ffba00;
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
