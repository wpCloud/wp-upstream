<?php
/**
 * @package WPUpstream
 * @version 0.1.2
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
		add_action( 'automatic_updates_complete', array( $this, 'maybe_finish_process' ), 100 );

		add_action( 'load-themes.php', array( $this, 'maybe_start_process' ), 1 );
		add_action( 'load-themes.php', array( $this, 'maybe_finish_process' ), 100 );

		add_action( 'load-plugins.php', array( $this, 'maybe_start_process' ), 1 );
		add_action( 'load-plugins.php', array( $this, 'maybe_finish_process' ), 100 );

		// ensures that a broken process does not prevent the plugin from working
		add_action( 'admin_init', array( $this, 'cleanup_old_process' ) );
	}

	/**
	 * Starts a new process if there isn't anyone active yet.
	 * Furthermore a few other dependencies might be checked according to the current filter.
	 *
	 * Before starting a process, the function runs 'git status' to see if any files have been modified before the process starts.
	 *
	 * @param mixed $ret compatibility for filters; if the function is hooked into a filter, it needs to return the first parameter
	 * @return mixed same like the $ret parameter
	 */
	public function maybe_start_process( $ret = null ) {
		$filter = current_filter();

		switch ( $filter ) {
			case 'load-themes.php':
				if ( ! ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'delete' && current_user_can( 'delete_themes' ) ) ) {
					return $ret;
				}
				break;
			case 'load-plugins.php':
				if ( ! ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'delete-selected' && isset( $_REQUEST['verify-delete'] ) && current_user_can( 'delete_plugins' ) ) ) {
					return $ret;
				}
				break;
			default;
		}

		if ( ! $this->is_process_active() ) {
			// if we're doing an auto update, disable the default finishing hook temporarily since 'automatic_updates_complete' is used for this instead
			if ( get_option( 'auto_updater.lock' ) ) {
				remove_action( 'upgrader_process_complete', array( $this, 'maybe_finish_process' ), 100 );
			}

			$response = $this->git->status();
			$this->start_process( $response['filechanges'] );
		}

		return $ret;
	}

	/**
	 * Finishes a process if there is one active.
	 * Furthermore a few other dependencies might be checked according to the current filter.
	 *
	 * While finishing a process, the function runs 'git status' to retrieve the filechanges and compares those to the initial filechanges, only committing what is actually new.
	 * It then commits (and optionally pushes) the changes.
	 *
	 * @param mixed $ret compatibility for filters; if the function is hooked into a filter, it needs to return the first parameter
	 * @return mixed same like the $ret parameter
	 */
	public function maybe_finish_process( $ret = null ) {
		$filter = current_filter();

		$auto_update = false;

		switch ( $filter ) {
			case 'load-themes.php':
				if ( ! ( isset( $_REQUEST['deleted'] ) && $_REQUEST['deleted'] == 'true' && current_user_can( 'delete_themes' ) ) ) {
					return $ret;
				}
				break;
			case 'load-plugins.php':
				if ( ! ( isset( $_REQUEST['deleted'] ) && $_REQUEST['deleted'] == 'true' && current_user_can( 'delete_plugins' ) ) ) {
					return $ret;
				}
				break;
			case 'automatic_updates_complete':
				$auto_update = true;
				if ( ! has_action( 'upgrader_process_complete', array( $this, 'maybe_finish_process' ) ) ) {
					add_action( 'upgrader_process_complete', array( $this, 'maybe_finish_process' ), 100 );
				}
				break;
			default;
		}

		if ( $this->is_process_active() ) {
			$pre_filechanges = $this->get_pre_filechanges();
			$actions = $this->get_actions();

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

			if ( count( $paths_to_add ) > 0 ) {
				foreach ( $paths_to_add as $path ) {
					$this->git->add( $path );
				}

				$commit_message = $this->build_commit_message( $actions, $auto_update );

				if ( ! empty( $commit_message ) ) {
					$this->git->commit( '-m', $commit_message );
					if ( defined( 'WP_UPSTREAM_AUTOMATIC_PUSH' ) && WP_UPSTREAM_AUTOMATIC_PUSH ) {
						$this->git->push();
					}
				}
			}

			foreach ( $paths_originally_staged as $path ) {
				$this->git->add( $path );
			}
		}

		return $ret;
	}

	/**
	 * This function automatically finishes a process if it has been active for more than 5 minutes.
	 * By doing that, it ensures that, if for some reason a process was never finished, the plugin can still continue to work.
	 */
	public function cleanup_old_process() {
		$start_time = $this->get_start_time();
		if ( $start_time !== false && $start_time + 300 < current_time( 'timestamp' ) ) {
			$this->finish_process();
		}
	}

	/**
	 * Starts a new process.
	 *
	 * @param array $pre_filechanges results of the 'git status' command run immediately before starting the process
	 * @param array $actions actions that are gonna be performed during the process
	 * @return boolean true if the process could be started successfully, otherwise false
	 */
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

		$start_time_status = set_transient( 'wpupstream_process_start_time', current_time( 'timestamp' ) );
		$pre_filechanges_status = set_transient( 'wpupstream_process_pre_filechanges', json_encode( $pre_filechanges ) );
		$actions_status = set_transient( 'wpupstream_process_actions', json_encode( $actions ) );
		return $start_time_status && $pre_filechanges_status && $actions_status;
	}

	/**
	 * Checks if a process is currently active.
	 *
	 * @return boolean true if a process is active, otherwise false
	 */
	private function is_process_active() {
		$start_time = get_transient( 'wpupstream_process_start_time' );
		$pre_filechanges = get_transient( 'wpupstream_process_pre_filechanges' );
		$actions = get_transient( 'wpupstream_process_actions' );

		return $start_time !== false && $pre_filechanges !== false && $actions !== false;
	}

	/**
	 * Finishes a process.
	 *
	 * @return boolean true if the process could be finished successfully, otherwise false (if no process has been active before, it will return false too)
	 */
	private function finish_process() {
		$start_time_status = delete_transient( 'wpupstream_process_start_time' );
		$pre_filechanges_status = delete_transient( 'wpupstream_process_pre_filechanges' );
		$actions_status = delete_transient( 'wpupstream_process_actions' );
		return $start_time_status && $pre_filechanges_status && $actions_status;
	}

	/**
	 * Gets the start time of the active process.
	 *
	 * @return int|false timestamp start time or false if no process is active
	 */
	private function get_start_time() {
		return get_transient( 'wpupstream_process_start_time' );
	}

	/**
	 * Gets the filechanges made before starting the active process.
	 *
	 * @return array|false filechanges array or false if no process is active
	 */
	private function get_pre_filechanges() {
		$pre_filechanges = get_transient( 'wpupstream_process_pre_filechanges' );
		if ( $pre_filechanges !== false ) {
			return json_decode( $pre_filechanges, true );
		}
		return false;
	}

	/**
	 * Gets the actions performed during the active process.
	 *
	 * @return array|false actions array or false if no process is active
	 */
	private function get_actions() {
		$actions = get_transient( 'wpupstream_process_actions' );
		if ( $actions !== false ) {
			return json_decode( $actions, true );
		}
		return false;
	}

	/**
	 * Builds a commit message.
	 *
	 * If the changes were not performed by an auto-update, the current user's name is used as the initiator.
	 *
	 * Example results:
	 * * "admin installed WordPress SEO 2.0.1"
	 * * "auto-update updated WordPress to 4.0"
	 *
	 * @param array $actions the actions to build the commit message for
	 * @param boolean $auto_update whether the actions were performed by a WordPress auto-update
	 * @return string the commit message
	 */
	private function build_commit_message( $actions, $auto_update = false ) {
		$initiator = __( 'auto-update', 'wpupstream' );
		if ( ! $auto_update ) {
			$uid = get_current_user_id();
			if ( $uid > 0 ) {
				$udata = get_userdata( $uid );
				$initiator = $udata->user_nicename;
			} else {
				$initiator = __( 'unknown user', 'wpupstream' );
			}
		}

		$action_string = '';
		$action_data = array();

		if ( count( $actions['install'] ) > 0 ) {
			$action_string = __( '%1$s installed %2$s', 'wpupstream' );
			foreach ( $actions['install'] as $item ) {
				$action_data[] = sprintf( __( '%1$s %3$s', 'wpupstream' ), $item['name'], $item['type'], $item['version_new'] );
			}
		} elseif ( count( $actions['update'] ) > 0 ) {
			$action_string = __( '%1$s updated %2$s', 'wpupstream' );
			foreach ( $actions['update'] as $item ) {
				$action_data[] = sprintf( __( '%1$s to %3$s', 'wpupstream' ), $item['name'], $item['type'], $item['version_new'] );
			}
		} elseif ( count( $actions['delete'] ) > 0 ) {
			$action_string = __( '%1$s deleted %2$s', 'wpupstream' );
			foreach ( $actions['delete'] as $item ) {
				$action_data[] = sprintf( __( '%1$s', 'wpupstream' ), $item['name'], $item['type'] );
			}
		}

		return apply_filters( 'wpupstream_commit_message', sprintf( $action_string, $initiator, implode( ', ', $action_data ) ), $actions, $auto_update );
	}
}
