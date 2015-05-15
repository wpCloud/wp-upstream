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

		add_filter( 'upgrader_pre_download', array( $this, 'maybe_start_process' ), 1 );
		add_action( 'upgrader_process_complete', array( $this, 'maybe_finish_process' ), 100 );

		add_filter( 'install_theme_complete_actions', array( $this, 'check_action' ), 10, 2 );
		add_filter( 'update_theme_complete_actions', array( $this, 'check_action' ), 10, 2 );
		add_filter( 'update_bulk_theme_complete_actions', array( $this, 'check_action' ), 10, 2 );
		add_filter( 'install_plugin_complete_actions', array( $this, 'check_action' ), 10, 2 );
		add_filter( 'update_plugin_complete_actions', array( $this, 'check_action' ), 10, 2 );
		add_filter( 'update_bulk_plugins_complete_actions', array( $this, 'check_action' ), 10, 2 );
	}

	public function check_action( $actions = array(), $data = null ) {
		if ( $data !== null ) {
			$filter = current_filter();
			//var_dump( $filter );
			//var_dump( $data );
		}

		return $actions;
	}

	public function maybe_start_process( $value = null ) {
		if ( ! $this->is_process_active() ) {
			$response = $this->git->status();
			$this->start_process( $response['filechanges'] );
		}

		return $value;
	}

	public function maybe_finish_process( $value = null ) {
		if ( $this->is_process_active() ) {
			$pre_filechanges = $this->get_pre_filechanges();
			$actions = $this->get_actions();

			print_r( $pre_filechanges );
			print_r( $actions );

			$response = $this->git->status();
			$post_filechanges = $response['filechanges'];

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

		return $value;
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

	private function add_process_pre_filechange( $mode, $path ) {
		$pre_filechanges = get_transient( 'wpupstream_process_pre_filechanges' );
		if ( $pre_filechanges !== false ) {
			$pre_filechanges = json_decode( $pre_filechanges, true );
			if ( isset( $pre_filechanges[ $mode ] ) ) {
				$pre_filechanges[ $mode ][] = $path;
				return true;
			}
		}
		return false;
	}

	private function add_process_action( $mode, $item_name, $item_type, $new_version = null, $old_version = null ) {
		$actions = get_transient( 'wpupstream_process_actions' );
		if ( $actions !== false ) {
			$actions = json_decode( $actions, true );
			if ( isset( $actions[ $mode ] ) ) {
				$actions[ $mode ][] = array(
					'name'			=> $item_name,
					'type'			=> $item_type,
					'version_new'	=> $new_version,
					'version_old'	=> $old_version,
				);
				return true;
			}
		}
		return false;
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
