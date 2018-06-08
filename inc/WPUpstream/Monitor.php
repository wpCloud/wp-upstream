<?php
/**
 * @package WPUpstream
 * @version 0.1.8
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

		add_action( 'delete_plugin', array( $this, 'maybe_start_process' ), 1 );
		add_action( 'deleted_plugin', array( $this, 'maybe_finish_process' ), 100 );

		add_action( 'wp_ajax_delete-theme', array( $this, 'maybe_start_process' ), 0 );
		add_action( 'delete_site_transient_update_themes', array( $this, 'maybe_finish_process' ), 100 );

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
				// note: for multisite the action 'delete-selected' is used, for a normal site just 'delete'
				if ( ! ( isset( $_REQUEST['action'] ) && ( $_REQUEST['action'] == 'delete' || $_REQUEST['action'] == 'delete-selected' && isset( $_REQUEST['verify-delete'] ) ) && current_user_can( 'delete_themes' ) ) ) {
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
				if ( ! ( isset( $_REQUEST['deleted'] ) && ( $_REQUEST['deleted'] == 'true' || $_REQUEST['deleted'] == 1 ) && current_user_can( 'delete_themes' ) ) ) {
					return $ret;
				}
				break;
			case 'load-plugins.php':
				if ( ! ( isset( $_REQUEST['deleted'] ) && ( $_REQUEST['deleted'] == 'true' || $_REQUEST['deleted'] == 1 ) && current_user_can( 'delete_plugins' ) ) ) {
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
					$this->git->add( $path, '-A' );
				}

				$commit_message = $this->build_commit_message( $actions, $auto_update );

				$commit_env_vars = $this->get_author_env_vars( $auto_update );

				if ( ! empty( $commit_message ) ) {
					$this->git->commit( '-m', $commit_message, $commit_env_vars );
					if ( defined( 'WP_UPSTREAM_AUTOMATIC_PUSH' ) && WP_UPSTREAM_AUTOMATIC_PUSH ) {
						$this->git->push();
					}
				}
			}

			foreach ( $paths_originally_staged as $path ) {
				$this->git->add( $path, '-A' );
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
		if ( $start_time !== false && $start_time + 120 < current_time( 'timestamp' ) ) {
			$this->finish_process();
		}
	}

	/**
	 * Adds a new action to the active process.
	 *
	 * This method is called by the Detector class.
	 *
	 * @param string $mode either 'install', 'update' or 'delete'
	 * @param string $item_name the name of the item that has changed
	 * @param string $item_type the type of the item that has changed (either 'core', 'plugin' or 'theme')
	 * @param string|null $new_version new version if available
	 * @param string|null $old_version old version if available
	 * @return boolean true if the action was added to the active process successfully, otherwise false
	 */
	public function add_process_action( $mode, $item_name, $item_type, $new_version = null, $old_version = null ) {
		$actions = Util::get_transient( 'wpupstream_process_actions', 'array' );
		if ( $actions !== false ) {
			if ( isset( $actions[ $mode ] ) ) {
				$actions[ $mode ][] = array(
					'name'			=> $item_name,
					'type'			=> $item_type,
					'version_new'	=> $new_version,
					'version_old'	=> $old_version,
				);
				return Util::set_transient( 'wpupstream_process_actions', $actions );
			}
		}
		return false;
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

		$start_time_status = Util::set_transient( 'wpupstream_process_start_time', current_time( 'timestamp' ) );
		$pre_filechanges_status = Util::set_transient( 'wpupstream_process_pre_filechanges', $pre_filechanges );
		$actions_status = Util::set_transient( 'wpupstream_process_actions', $actions );

		return $start_time_status && $pre_filechanges_status && $actions_status;
	}

	/**
	 * Checks if a process is currently active.
	 *
	 * @return boolean true if a process is active, otherwise false
	 */
	private function is_process_active() {
		$start_time = Util::get_transient( 'wpupstream_process_start_time' );
		$pre_filechanges = Util::get_transient( 'wpupstream_process_pre_filechanges' );
		$actions = Util::get_transient( 'wpupstream_process_actions' );

		return $start_time !== false && $pre_filechanges !== false && $actions !== false;
	}

	/**
	 * Finishes a process.
	 *
	 * @return boolean true if the process could be finished successfully, otherwise false (if no process has been active before, it will return false too)
	 */
	private function finish_process() {
		$start_time_status = Util::delete_transient( 'wpupstream_process_start_time' );
		$pre_filechanges_status = Util::delete_transient( 'wpupstream_process_pre_filechanges' );
		$actions_status = Util::delete_transient( 'wpupstream_process_actions' );

		return $start_time_status && $pre_filechanges_status && $actions_status;
	}

	/**
	 * Gets the start time of the active process.
	 *
	 * @return int|false timestamp start time or false if no process is active
	 */
	private function get_start_time() {
		return Util::get_transient( 'wpupstream_process_start_time' );
	}

	/**
	 * Gets the filechanges made before starting the active process.
	 *
	 * @return array|false filechanges array or false if no process is active
	 */
	private function get_pre_filechanges() {
		return Util::get_transient( 'wpupstream_process_pre_filechanges', 'array' );
	}

	/**
	 * Gets the actions performed during the active process.
	 *
	 * @return array|false actions array or false if no process is active
	 */
	private function get_actions() {
		return Util::get_transient( 'wpupstream_process_actions', 'array' );
	}

	/**
	 * Gets the environment variables to set for a commit.
	 *
	 * If the changes were not performed by an auto-update, the current user's name and email are used.
	 *
	 * @param boolean $auto_update whether the actions were performed by a WordPress auto-update
	 * @return array array of environment variables and their values
	 */
	private function get_author_env_vars( $auto_update = false ) {
		$author_name = __( 'unknown user', 'wpupstream' );
		$author_email = '';

		if ( $auto_update ) {
			$author_name = __( 'WordPress', 'wpupstream' );
		} else {
			$current_user = $this->get_current_user();
			if ( $current_user ) {
				$author_name = $current_user->display_name;
				$author_email = $current_user->user_email;
			}
		}

		if ( empty( $author_email ) ) {
			if ( is_multisite() ) {
				$author_email = get_site_option( 'admin_email' );
			} else {
				$author_email = get_option( 'admin_email' );
			}
		}

		return array(
			'GIT_AUTHOR_NAME'		=> $author_name,
			'GIT_AUTHOR_EMAIL'		=> $author_email,
			'GIT_COMMITTER_NAME'	=> $author_name,
			'GIT_COMMITTER_EMAIL'	=> $author_email,
		);
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
		$initiator = __( 'unknown user', 'wpupstream' );

		if ( $auto_update ) {
			$initiator = __( 'auto-update', 'wpupstream' );
		} else {
			$current_user = $this->get_current_user();
			if ( $current_user ) {
				$initiator = $current_user->display_name;
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

	/**
	 * Gets the current user.
	 *
	 * If WP_CLI is being used, its configuration is checked.
	 * Otherwise the current user as set in WordPress is being used.
	 *
	 * @return WP_User|bool either a WP_User object or false if no user could be detected
	 */
	private function get_current_user() {
		$udata = false;

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			if ( class_exists( 'WP_CLI' ) ) {
				$runner = \WP_CLI::get_runner();
				$config = $runner->config;
				if ( isset( $config['user'] ) ) {
					if ( is_numeric( $config['user'] ) ) {
						$udata = get_user_by( 'id', $config['user'] );
					} elseif ( is_email( $config['user'] ) ) {
						$udata = get_user_by( 'email', $config['user'] );
					} else {
						$udata = get_user_by( 'login', $config['user'] );
					}
				}
			}
		}

		if ( ! $udata && function_exists( 'wp_get_current_user' ) ) {
			$udata = wp_get_current_user();
		}

		return $udata;
	}
}
