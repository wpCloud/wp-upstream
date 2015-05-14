<?php
/**
 * @package WPOD
 * @version 0.0.1
 * @author Usability Dynamics Inc.
 */

namespace WPUpstream;

final class Plugin {
	private static $instance = null;

	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private $git = null;
	private $monitor = null;

	private function __construct() {
		$this->git = Git::instance();
		if ( $this->git->init_config() ) {
			$this->monitor = Monitor::instance();
		} else {
			add_action( 'admin_notices', 'wpupstream_display_git_warning_notice' );
		}
	}

	private function __clone() {
		_doing_it_wrong( __METHOD__, __( 'Cheatin&#8217; huh?', 'wpupstream' ), '0.0.1' );
	}

	private function __wakeup() {
		_doing_it_wrong( __METHOD__, __( 'Cheatin&#8217; huh?', 'wpupstream' ), '0.0.1' );
	}
}
