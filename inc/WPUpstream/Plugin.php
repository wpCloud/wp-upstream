<?php
/**
 * @package WPUpstream
 * @version 0.1.5
 * @author Usability Dynamics Inc.
 */

namespace WPUpstream;

/**
 * This is the main plugin class.
 *
 * If Git could be configured successfully, it initializes the plugin.
 * Otherwise it will show a warning message in the admin.
 */
final class Plugin {
	private static $instance = null;

	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function has_instance() {
		return self::$instance !== null;
	}

	private $git = null;
	private $monitor = null;
	private $detector = null;

	private $status = false;

	private function __construct() {
		$this->git = Git::instance();
		if ( $this->git->init_config() ) {
			$this->status = true;
			$this->monitor = Monitor::instance();
			$this->detector = Detector::instance();
		} else {
			add_action( 'admin_notices', 'wpupstream_display_git_warning_notice' );
		}
	}

	public function get_status() {
		return $this->status;
	}

	private function __clone() {
		_doing_it_wrong( __METHOD__, __( 'Cheatin&#8217; huh?', 'wpupstream' ), '0.0.1' );
	}

	private function __wakeup() {
		_doing_it_wrong( __METHOD__, __( 'Cheatin&#8217; huh?', 'wpupstream' ), '0.0.1' );
	}
}
