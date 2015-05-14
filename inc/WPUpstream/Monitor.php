<?php
/**
 * @package WPOD
 * @version 0.0.1
 * @author Usability Dynamics Inc.
 */

namespace WPUpstream;

final class Monitor {
	private static $instance = null;

	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private $git = null;

	private function __construct() {
		$this->git = Git::instance();

		add_filter( 'upgrader_pre_download', array( $this, 'maybe_start_process' ) );
		add_action( 'upgrader_process_complete', array( $this, 'maybe_finish_process' ) );
	}

	public function maybe_start_process( $value = null ) {
		if ( $this->get_active_process() === false ) {
			$response = $this->git->status();
			$this->start_process( $response['filechanges'] );
		}

		return $value;
	}

	public function maybe_finish_process( $value = null ) {
		$pre_filechanges = $this->get_active_process();
		if ( $pre_filechanges !== false ) {
			$response = $this->git->status();
			$post_filechanges = $response['filechanges'];

			$paths_originally_staged = array();
			if ( count( $post_filechanges['staged'] ) > 0 ) {
				foreach ( $post_filechanges['staged'] as $path ) {
					$paths_originally_staged[] = $path;
					$this->git->reset( 'HEAD', $path );
				}
			}

			$paths_to_add = array_merge( array_diff( $post_filechanges['unstaged'], $pre_filechanges['unstaged'] ), array_diff( $post_filechanges['untracked'], $pre_filechanges['untracked'] ) );

			$this->finish_process();

			foreach ( $paths_to_add as $path ) {
				$this->git->add( $path );
			}

			//TODO: find out which user installed/updated what

			//$this->git->commit( '-m', '"a commit message"' );

			foreach ( $paths_originally_staged as $path ) {
				$this->git->add( $path );
			}
		}

		return $value;
	}

	private function start_process( $filechanges = array() ) {
		return set_transient( 'wpupstream_process_running', json_encode( $filechanges ) );
	}

	private function get_active_process() {
		$filechanges = get_transient( 'wpupstream_process_running' );
		if ( $filechanges ) {
			return json_decode( $filechanges, true );
		}
		return false;
	}

	private function finish_process() {
		return delete_transient( 'wpupstream_process_running' );
	}
}
