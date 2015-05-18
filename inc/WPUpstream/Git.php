<?php
/**
 * @package WPOD
 * @version 0.0.1
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

	public function init_config() {
		$this->config = array(
			'exec_available'	=> function_exists( 'exec' ),
			'current_dir'		=> getcwd(),
			'git_path'			=> defined( 'WPUPSTREAM_GIT_PATH' ) ? WPUPSTREAM_GIT_PATH : 'git',
		);

		$this->config['git_dir'] = $this->get_git_dir();

		if ( count( array_filter( $this->config ) ) < count( $this->config ) ) {
			$this->config['ready'] = false;
		} else {
			$this->config['ready'] = true;
		}

		return $this->config['ready'];
	}

	public function __get( $name ) {
		if ( property_exists( $this, $name ) ) {
			return $this->$name;
		} elseif ( isset( $this->config[ $name ] ) ) {
			return $this->config[ $name ];
		}
		return false;
	}

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

	private function exec( $command, $args = array() ) {
		$path = Util::escape_shell_arg( $this->config['git_path'] );
		$command = Util::escape_shell_arg( $command );
		$args = join( ' ', array_map( array( '\WPUpstream\Util', 'escape_shell_arg' ), $args ) );

		$this->current_output = '';
		$this->current_status = 0;

		chdir( $this->config['git_dir'] );
		exec( "$path $command $args 2>&1", $this->current_output, $this->current_status );
		chdir( $this->config['current_dir'] );

		return $this->format_response();
	}

	private function format_response( $response = array() ) {
		return array_merge( $response, array( 'raw_output' => $this->current_output ) );
	}

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
