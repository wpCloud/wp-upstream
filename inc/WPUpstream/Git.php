<?php
/**
 * @package WPUpstream
 * @version 0.1.8
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
			'remote_name'		=> defined( 'WP_UPSTREAM_REMOTE_NAME' ) ? WP_UPSTREAM_REMOTE_NAME : 'origin',
		);

		$this->config['git_dir'] = $this->get_git_dir();

		$this->config['remote_url'] = $this->get_remote_url();

		$this->config['branch_name'] = $this->get_branch_name();

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
		$blacklist = array( 'exec', 'init_config', 'format_response', 'get_git_dir', 'get_remote_url', 'get_branch_name', '__get', '__call' );

		if ( ! empty( $name ) && ! in_array( $name, $blacklist ) ) {
			$env_vars = array();
			if ( isset( $args[0] ) && is_array( $args[0] ) && isset( $args[0][0] ) ) {
				if ( isset( $args[1] ) && is_array( $args[1] ) && count( $args[1] ) > 0 ) {
					$env_vars = $args[1];
				}
				$args = $args[0];
			} else {
				for ( $i = 0; $i < count( $args ); $i++ ) {
					if ( is_array( $args[ $i ] ) ) {
						if ( count( $args[ $i ] ) > 0 ) {
							$env_vars = $args[ $i ];
						}
						unset( $args[ $i ] );
					}
				}
				$args = array_values( $args );
			}
			if ( method_exists( $this, $name ) ) {
				return call_user_func( array( $this, $name ), $args, $env_vars );
			} else {
				return $this->exec( $name, $args, $env_vars );
			}
		}
		return false;
	}

	/**
	 * Runs 'git status' and detects the filechanges properly.
	 *
	 * @uses WPUpstream\Git::exec()
	 * @param array $args arguments for the command (the last element can optionally be an array of environment variables)
	 * @return array results array containing a 'filechanges' key and a 'raw_output' key
	 */
	private function status( $args = array(), $env_vars = array() ) {
		$this->exec( 'status', $args, $env_vars );

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
	 * Runs 'git log' and generates an array of commits from it.
	 *
	 * @uses WPUpstream\Git::exec()
	 * @param array $args arguments for the command (the last element can optionally be an array of environment variables)
	 * @return array results array containing a 'commits' key and a 'raw_output' key
	 */
	private function log( $args = array(), $env_vars = array() ) {
		$index = -1;
		for ( $i = 0; $i < count( $args ); $i++ ) {
			if ( strpos( $args[ $i ], '--pretty=' ) === 0 ) {
				$index = $i;
				break;
			}
		}

		if ( $index > -1 ) {
			$args[ $index ] = '--pretty=format:' . Commit::get_formatstring();
		} else {
			$args[] = '--pretty=format:' . Commit::get_formatstring();
		}

		$this->exec( 'log', $args, $env_vars );

		$commits = array();

		$current_commit = array();

		if ( is_array( $this->current_output ) ) {
			foreach ( $this->current_output as $index => $line ) {
				$line = trim( $line );
				if ( ! empty( $line ) ) {
					$key = explode( ':', $line );
					if ( isset( $key[0] ) ) {
						$key = $key[0];
						$value = str_replace( $key . ':', '', $line );

						$current_commit[ $key ] = $value;
					}
				}
				if ( empty( $line ) || $index == count( $this->current_output ) - 1 ) {
					if ( isset( $current_commit['commit_hash'] ) ) {
						$commits[ $current_commit['commit_hash'] ] = $current_commit;
						$current_commit = array();
					}
				}
			}
		}

		return $this->format_response( compact( 'commits' ) );
	}

	/**
	 * Runs any Git command.
	 *
	 * @param string $command name of the command to run
	 * @param array $args arguments for the command (the last element can optionally be an array of environment variables)
	 * @return array results array containing a 'raw_output' key
	 */
	private function exec( $command, $args = array(), $env_vars = array() ) {
		$command = str_replace( '_', '-', $command );

		$env_vars_string = '';
		foreach ( $env_vars as $env_var => $value ) {
			$env_vars_string .= $env_var . '="' . $value . '" ';
		}

		$path = Util::escape_shell_arg( $this->config['git_path'] );
		$command = Util::escape_shell_arg( $command );
		$args = join( ' ', array_map( array( '\WPUpstream\Util', 'escape_shell_arg' ), $args ) );

		$this->current_output = array();
		$this->current_status = 0;

		chdir( $this->config['git_dir'] );
		exec( "$env_vars_string$path $command $args 2>&1", $this->current_output, $this->current_status );
		chdir( $this->config['current_dir'] );

		if ( ! is_array( $this->current_output ) ) {
			if ( $this->current_output ) {
				$this->current_output = array( $this->current_output );
			} else {
				$this->current_output = array();
			}
		}

		// log all Git activities if constant is set
		if ( ( defined( 'WP_UPSTREAM_DEBUG' ) && WP_UPSTREAM_DEBUG ) ) {
			$original_log_errors = ini_get( 'log_errors' );
			$original_error_log = ini_get( 'error_log' );
			ini_set( 'log_errors', 1 );
			ini_set( 'error_log', trailingslashit( WP_CONTENT_DIR ) . 'wpupstream-git.log' );
			error_log( 'Command: ' . "$path $command $args" );
			if ( $env_vars ) {
				error_log( 'Environment vars: ' . print_r( $env_vars, true ) );
			}
			if ( $this->current_output ) {
				error_log( 'Output: ' . print_r( $this->current_output, true ) );
			}
			if ( $this->current_status ) {
				error_log( 'Status: ' . $this->current_status );
			}
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

	/**
	 * Returns the URL of the remote (if git is available).
	 *
	 * This function is used for configuration.
	 *
	 * @return string URL to the remote or an empty string if it could not be detected
	 */
	private function get_remote_url() {
		if ( $this->config['git_dir'] ) {
			$response = $this->exec( 'config', array( '--get', 'remote.origin.url' ) );
			if ( isset( $response['raw_output'][0] ) ) {
				return trim( $response['raw_output'][0] );
			}
		}
		return '';
	}

	/**
	 * Detects the current branch we're in (if git is available).
	 *
	 * This function is used for configuration.
	 *
	 * @return string name of the current branch or an empty string if it could not be detected
	 */
	private function get_branch_name() {
		$dir = $this->config['git_dir'];
		if ( $dir && is_dir( trailingslashit( $dir ) . '.git' ) && is_file( trailingslashit( $dir ) . '.git/HEAD' ) ) {
			$filecontents = file( trailingslashit( $dir ) . '.git/HEAD', FILE_USE_INCLUDE_PATH );
			$line = $filecontents[0];
			$line = explode( '/', $line, 3 );
			return trim( $line[2] );
		}
		return '';
	}
}
