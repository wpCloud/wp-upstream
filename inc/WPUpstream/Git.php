<?php
/**
 * @package WPOD
 * @version 0.1.1
 * @author Usability Dynamics Inc.
 */

namespace WPUpstream;

/**
 * This class handles Git operations.
 *
 * It can run any Git command, returning an associative array with one key 'raw_output'.
 * For some Git commands however, this class contains a specific function to parse the response from the console.
 * In that case, the return array will contain additional keys.
 */
final class Git {
	private static $instance = null;

	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private $config = array();
	private $current_output = '';
	private $current_status = 0;

	private function __construct() {

	}

	/**
	 * Initializes the Git configuration.
	 *
	 * @return boolean true if Git was successfully configured, false if Git could not be configured/found
	 */
	public function init_config() {
		$this->config = array(
			'exec_available'	=> function_exists( 'exec' ),
			'current_dir'		=> getcwd(),
			'git_path'			=> defined( 'WP_UPSTREAM_GIT_PATH' ) ? WP_UPSTREAM_GIT_PATH : 'git',
		);

		$this->config['git_dir'] = $this->get_git_dir();

		if ( count( array_filter( $this->config ) ) < count( $this->config ) ) {
			$this->config['ready'] = false;
		} else {
			$this->config['ready'] = true;
		}

		return $this->config['ready'];
	}

	/**
	 * Magic isset method.
	 *
	 * @param string $name the key to check the existance for
	 * @return boolean true if the key exists, otherwise false
	 */
	public function __isset( $name ) {
		if ( property_exists( $this, $name ) ) {
			return true;
		} elseif ( isset( $this->config[ $name ] ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Magic get method.
	 *
	 * @param string $name key to get the value for
	 * @return mixed the value or false if it could not be found
	 */
	public function __get( $name ) {
		if ( property_exists( $this, $name ) ) {
			return $this->$name;
		} elseif ( isset( $this->config[ $name ] ) ) {
			return $this->config[ $name ];
		}
		return false;
	}

	/**
	 * Magic call method.
	 *
	 * It is used to execute Git commands.
	 * If the class has a method with the same name as the command, this function is called.
	 * Otherwise the default function for executing Git commands is called.
	 *
	 * @param string $name name of the function to call
	 * @param array $args arguments of the function as an array
	 * @return mixed results of the function or false if it could not be found
	 */
	public function __call( $name, $args = array() ) {
		$blacklist = array( 'exec', 'init_config', '__get', '__call' );
		if ( ! in_array( $name, $blacklist ) ) {
			if ( method_exists( $this, $name ) ) {
				return call_user_func_array( array( $this, $name ), $args );
			} else {
				return $this->exec( $name, $args );
			}
		}
		return false;
	}

	/**
	 * Runs 'git status' and detects the filechanges properly.
	 *
	 * @uses WPUpstream\Git::exec()
	 * @param array $args arguments for the command
	 * @return array results array containing a 'filechanges' key and a 'raw_output' key
	 */
	private function status( $args = array() ) {
		$this->exec( 'status', $args );

		$filechanges = array(
			'staged'	=> array(),
			'unstaged'	=> array(),
			'untracked'	=> array(),
		);

		$mode = 'staged';
		if ( is_array( $this->current_output ) ) {
			foreach ( $this->current_output as $index => $line ) {
				switch ( $line ) {
					case 'Changes staged for commit:':
						$mode = 'staged';
						break;
					case 'Changes not staged for commit:':
						$mode = 'unstaged';
						break;
					case 'Untracked files:':
						$mode = 'untracked';
						break;
					default:
						$line = preg_replace( '/\s+/', ' ', $line );
						$data = array_values( array_filter( explode( ' ', $line ) ) );
						if ( $mode == 'staged' || $mode == 'unstaged' ) {
							if ( isset( $data[0] ) ) {
								$data[0] = str_replace( ':', '', $data[0] );
								if ( in_array( $data[0], array( 'new file', 'modified', 'deleted' ) ) ) {
									if ( Util::is_path( trailingslashit( $this->config['git_dir'] ) . $data[1], $data[0] == 'deleted' ) ) {
										$filechanges[ $mode ][] = $data[1];
									}
								}
							}
						} elseif ( $mode == 'untracked' ) {
							if ( count( $data ) == 1 ) {
								if ( Util::is_path( trailingslashit( $this->config['git_dir'] ) . $data[0] ) ) {
									$filechanges[ $mode ][] = $data[0];
								}
							}
						}
				}
			}
		}

		return $this->format_response( compact( 'filechanges' ) );
	}

	/**
	 * Runs any Git command.
	 *
	 * @param string $command name of the command to run
	 * @param array $args arguments for the command
	 * @return array results array containing a 'raw_output' key
	 */
	private function exec( $command, $args = array() ) {
		$path = Util::escape_shell_arg( $this->config['git_path'] );
		$command = Util::escape_shell_arg( $command );
		$args = join( ' ', array_map( array( '\WPUpstream\Util', 'escape_shell_arg' ), $args ) );

		$this->current_output = '';
		$this->current_status = 0;

		chdir( $this->config['git_dir'] );
		exec( "$path $command $args 2>&1", $this->current_output, $this->current_status );
		chdir( $this->config['current_dir'] );

		// log all Git activities
		if ( defined( 'WP_UPSTREAM_DEBUG' ) && WP_UPSTREAM_DEBUG ) {
			$original_log_errors = ini_get( 'log_errors' );
			$original_error_log = ini_get( 'error_log' );
			ini_set( 'log_errors', 1 );
			ini_set( 'error_log', trailingslashit( WP_CONTENT_DIR ) . 'wpupstream-git.log' );
			error_log( "$path $command $args" );
			error_log( print_r( $this->current_output, true ) );
			ini_set( 'log_errors', $original_log_errors );
			ini_set( 'error_log', $original_error_log );
		}

		return $this->format_response();
	}

	/**
	 * Formats the response of a Git command.
	 *
	 * The function basically adds a 'raw_output' key to the response array.
	 *
	 * @param array $response the response array to return
	 * @return array the response array containing an additional 'raw_output' key with the basic output as an array of lines
	 */
	private function format_response( $response = array() ) {
		return array_merge( $response, array( 'raw_output' => $this->current_output ) );
	}

	/**
	 * Detects the directory where the Git repository is located.
	 *
	 * This function is used for configuration.
	 *
	 * @return string path to the directory or an empty string if the directory could not be detected
	 */
	private function get_git_dir() {
		$path = $this->config['git_path'];
		if ( $path && $this->config['exec_available'] ) {
			chdir( ABSPATH );
			$git_dir = exec( "$path rev-parse --show-toplevel" );
			chdir( $this->config['current_dir'] );

			if ( $git_dir ) {
				return $git_dir;
			}
		}
		return '';
	}
}
