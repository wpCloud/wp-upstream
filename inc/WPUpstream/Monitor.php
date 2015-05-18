<?php
/**
 * @package WPOD
 * @version 0.0.1
 * @author Usability Dynamics Inc.
 */

namespace WPUpstream;

/**
 * This class monitors file changes triggered by WordPress.
 *
 * When a file changing action/filter is run, a process is started.
 * Then, at a specific other action/filter, this process is finished.
 * The resulting file changes (those that happened during the process) will then be committed with Git.
 */
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

		add_filter( 'upgrader_pre_download', array( $this, 'maybe_start_process' ), 1 );
		add_action( 'upgrader_process_complete', array( $this, 'maybe_finish_process' ), 100 );
	}

	public function maybe_start_process( $ret = null ) {
		if ( ! $this->is_process_active() ) {
			$response = $this->git->status();
			$this->start_process( $response['filechanges'] );
		}

		return $ret;
	}

	public function maybe_finish_process( $ret = null ) {
		if ( $this->is_process_active() ) {
			$pre_filechanges = $this->get_pre_filechanges();
			$actions = $this->get_actions();

			$response = $this->git->status();
			$post_filechanges = $response['filechanges'];

			error_log( print_r( $pre_filechanges, true ) );
			error_log( print_r( $post_filechanges, true ) );
			error_log( print_r( $actions, true ) );

			$paths_originally_staged = array();
			if ( count( $post_filechanges['staged'] ) > 0 ) {
				foreach ( $post_filechanges['staged'] as $path ) {
					$paths_originally_staged[] = $path;
					//$this->git->reset( 'HEAD', $path );
				}
			}

			$paths_to_add = array_merge( array_diff( $post_filechanges['unstaged'], $pre_filechanges['unstaged'] ), array_diff( $post_filechanges['untracked'], $pre_filechanges['untracked'] ) );

			$this->finish_process();

			foreach ( $paths_to_add as $path ) {
				//$this->git->add( $path );
			}

			//$this->git->commit( '-m', '"a commit message"' );

			foreach ( $paths_originally_staged as $path ) {
				//$this->git->add( $path );
			}
		}

		return $ret;
	}

	private function start_process( $pre_filechanges = array(), $actions = array() ) {
		$pre_filechanges = wp_parse_args( $pre_filechanges, array(
			'staged'		=> array(),
			'unstaged'		=> array(),
			'untracked'		=> array(),
		) );
		$actions = wp_parse_args( $actions, array(
			'install'		=> array(),
			'update'		=> array(),
			'delete'		=> array(),
		) );

		$pre_filechanges_status = set_transient( 'wpupstream_process_pre_filechanges', json_encode( $pre_filechanges ) );
		$actions_status = set_transient( 'wpupstream_process_actions', json_encode( $actions ) );
		return $pre_filechanges_status && $actions_status;
	}

	private function is_process_active() {
		$pre_filechanges = get_transient( 'wpupstream_process_pre_filechanges' );
		$actions = get_transient( 'wpupstream_process_actions' );

		return $pre_filechanges !== false && $actions !== false;
	}

	private function finish_process() {
		$pre_filechanges_status = delete_transient( 'wpupstream_process_pre_filechanges' );
		$actions_status = delete_transient( 'wpupstream_process_actions' );
		return $pre_filechanges_status && $actions_status;
	}

	private function get_pre_filechanges() {
		$pre_filechanges = get_transient( 'wpupstream_process_pre_filechanges' );
		if ( $pre_filechanges !== false ) {
			return json_decode( $pre_filechanges, true );
		}
		return false;
	}

	private function get_actions() {
		$actions = get_transient( 'wpupstream_process_actions' );
		if ( $actions !== false ) {
			return json_decode( $actions, true );
		}
		return false;
	}
}
