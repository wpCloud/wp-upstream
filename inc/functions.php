<?php
/**
 * @package WPUpstream
 * @version 0.1.8
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

function wpupstream_admin_bar_menu( $wp_admin_bar ) {
	if ( ! is_super_admin() || ! $wp_admin_bar ) {
		return;
	}

	$admin_bar_menu = WP_Upstream_Admin_Bar_Menu::instance( $wp_admin_bar );
}

function wpupstream_add_inline_style() {
	if ( ! is_super_admin() ) {
		return;
	}

	WP_Upstream_Admin_Bar_Menu::print_styles();
}

if ( ! class_exists( 'WP_Upstream_Admin_Bar_Menu' ) ) {

	class WP_Upstream_Admin_Bar_Menu {

		private static $instance = null;

		public static function instance( $wp_admin_bar ) {
			if ( self::$instance === null ) {
				self::$instance = new self( $wp_admin_bar );
			}
			return self::$instance;
		}

		private $admin_bar = null;

		private $global_status = '';

		private function __construct( $wp_admin_bar ) {
			$this->admin_bar = $wp_admin_bar;

			if ( class_exists( 'WPUpstream\Plugin' ) && WPUpstream\Plugin::has_instance() && WPUpstream\Plugin::instance()->get_status() ) {
				if ( \WPUpstream\Util::has_uncommitted_changes() ) {
					$this->add_item( array(
						'id'		=> 'wp-upstream-uncommitted-changes',
						'parent'	=> 'wp-upstream',
						'title'		=> __( 'The repository contains uncommitted changes.', 'wpupstream' ),
					), 'warning' );
				}

				if ( \WPUpstream\Util::has_unpushed_commits() ) {
					$this->add_item( array(
						'id'		=> 'wp-upstream-unpushed-commits',
						'parent'	=> 'wp-upstream',
						'title'		=> __( 'The repository contains unpushed commits.', 'wpupstream' ),
					), 'warning' );
				}

				$this->add_item( array(
					'id'		=> 'wp-upstream-repository-branch',
					'parent'	=> 'wp-upstream',
					'title'		=> sprintf( __( 'Branch: %s', 'wpupstream' ), \WPUpstream\Util::get_current_branch() ),
				) );

				$this->add_item( array(
					'id'		=> 'wp-upstream-repository-version',
					'parent'	=> 'wp-upstream',
					'title'		=> sprintf( __( 'Version: %s', 'wpupstream' ), \WPUpstream\Util::get_repository_version( 'short' ) ),
				) );

				$commits = \WPUpstream\Util::get_commits( array( 'number' => 1 ) );
				if ( isset( $commits[0] ) ) {
					$latest_commit = $commits[0];

					$href = $latest_commit->is_pushed() ? $latest_commit->commit_url : '';

					$this->add_item( array(
						'id'		=> 'wp-upstream-repository-latest-commit',
						'parent'	=> 'wp-upstream',
						'title'		=> sprintf( __( 'Latest Commit: %s', 'wpupstream' ), $latest_commit->commit_message ),
						'href'		=> $href,
						'meta'		=> array( 'target' => '_blank' ),
					) );
				}
			} else {
				$this->add_item( array(
					'id'		=> 'wp-upstream-status',
					'parent'	=> 'wp-upstream',
					'title'		=> sprintf( __( '%s is not running.', 'wpupstream' ), 'WP Upstream' ),
				), 'error' );
			}

			$this->add_menu();
		}

		private function add_menu() {
			$this->admin_bar->add_node( array(
				'id'		=> 'wp-upstream',
				'parent'	=> 'top-secondary',
				'title'		=> $this->status_wrap( '<span class="ab-icon dashicons dashicons-upload"></span>', $this->global_status ),
			));
		}

		private function add_item( $args, $status = '' ) {
			$args = wp_parse_args( $args, array(
				'parent'	=> 'wp-upstream',
			) );

			if ( isset( $args['title'] ) ) {
				if ( strlen( $args['title'] ) > 100 ) {
					$args['title'] = substr( $args['title'], 0, 100 ) . '...';
				}
				$args['title'] = $this->status_wrap( $args['title'], $status );
			}

			$this->admin_bar->add_node( $args );

			if ( ! empty( $status ) ) {
				$this->global_status = $status;
			}
		}

		private function status_wrap( $text, $status = '' ) {
			$status = $this->sanitize_status_class( $status );

			if ( ! empty( $status ) ) {
				if ( strpos( $text, '<span class="' ) === 0 ) {
					return str_replace( '<span class="', '<span class="' . $status . ' ', $text );
				}
				return '<span class="' . $status . '">' . $text . '</span>';
			}
			return $text;
		}

		private function sanitize_status_class( $status ) {
			if ( ! empty( $status ) ) {
				return 'wp-upstream-' . $status;
			}
			return '';
		}

		public static function print_styles() {
			?>
			<style type="text/css">
				#wpadminbar .ab-icon.wp-upstream-success,
				#wpadminbar .ab-icon.wp-upstream-success:before,
				#wpadminbar .ab-item.wp-upstream-success,
				#wpadminbar .ab-item.wp-upstream-success:before,
				#wpadminbar .ab-item > .wp-upstream-success,
				#wpadminbar .hover .ab-icon.wp-upstream-success,
				#wpadminbar .hover .ab-icon.wp-upstream-success:before,
				#wpadminbar .hover .ab-item.wp-upstream-success,
				#wpadminbar .hover .ab-item.wp-upstream-success:before,
				#wpadminbar .hover .ab-item > .wp-upstream-success {
					color: #7ad03a;
				}

				#wpadminbar .ab-icon.wp-upstream-warning,
				#wpadminbar .ab-icon.wp-upstream-warning:before,
				#wpadminbar .ab-item.wp-upstream-warning,
				#wpadminbar .ab-item.wp-upstream-warning:before,
				#wpadminbar .ab-item > .wp-upstream-warning,
				#wpadminbar .hover .ab-icon.wp-upstream-warning,
				#wpadminbar .hover .ab-icon.wp-upstream-warning:before,
				#wpadminbar .hover .ab-item.wp-upstream-warning,
				#wpadminbar .hover .ab-item.wp-upstream-warning:before,
				#wpadminbar .hover .ab-item > .wp-upstream-warning {
					color: #ffba00;
				}

				#wpadminbar .ab-icon.wp-upstream-error,
				#wpadminbar .ab-icon.wp-upstream-error:before,
				#wpadminbar .ab-item.wp-upstream-error,
				#wpadminbar .ab-item.wp-upstream-error:before,
				#wpadminbar .ab-item > .wp-upstream-error,
				#wpadminbar .hover .ab-icon.wp-upstream-error,
				#wpadminbar .hover .ab-icon.wp-upstream-error:before,
				#wpadminbar .hover .ab-item.wp-upstream-error,
				#wpadminbar .hover .ab-item.wp-upstream-error:before,
				#wpadminbar .hover .ab-item > .wp-upstream-error {
					color: #dd3d36;
				}
			</style>
			<?php
		}

	}

}
