<?php
/**
 * @package WPUpstream
 * @version 0.1.8
 * @author Usability Dynamics Inc.
 */

namespace WPUpstream;

/**
 * This class contains some static utility functions.
 */
final class Util {
	/**
	 * Returns an array of commit objects, ordered by date, latest commits first.
	 *
	 * The function works similar to WordPress `get_posts()`, with an arguments array.
	 *
	 * @param array $args array of arguments
	 * @param int $output contant for output mode, either OBJECT or OBJECT_K
	 * @return array array of commit objects
	 */
	public static function get_commits( $args = array(), $output = OBJECT ) {
		$git = Git::instance();

		$_args = wp_parse_args( $args, array(
			'mode'			=> 'all',
			'number'		=> 10,
			'offset'		=> 0,
			'author'		=> '',
			'committer'		=> '',
		) );

		$args = array();
		switch ( $_args['mode'] ) {
			case 'unpushed':
				$args[] = $git->remote_name . '/' . $git->branch_name . '..' . $git->branch_name;
				break;
			case 'unpulled':
				$args[] = $git->branch_name . '..' . $git->remote_name . '/' . $git->branch_name;
				break;
			default:
				$args[] = 'HEAD';
		}
		if ( $_args['number'] >= 0 ) {
			$args[] = '--max-count=' . $_args['number'];
		}
		if ( $_args['offset'] > 0 ) {
			$args[] = '--skip=' . $_args['offset'];
		}
		if ( ! empty( $_args['author'] ) ) {
			$args[] = '--author=' . $_args['author'];
		}
		if ( ! empty( $_args['committer'] ) ) {
			$args[] = '--committer=' . $_args['committer'];
		}

		if ( $output == 'list' ) {
			$response = $git->rev_list( $args );

			$commits = array_map( 'trim', $response['raw_output'] );

			return $commits;
		} else {
			$response = $git->log( $args );

			$commits = $response['commits'];

			if ( $output != OBJECT_K ) {
				$commits = array_values( $commits );
			}

			return array_map( array( 'WPUpstream\Commit', 'get' ), $commits );
		}
	}

	/**
	 * Checks whether there are unpushed commits.
	 *
	 * @return boolean
	 */
	public static function has_unpushed_commits() {
		$unpushed_commits = self::get_commits( array(
			'mode'		=> 'unpushed',
			'number'	=> -1,
		), 'list' );

		return count( $unpushed_commits ) > 0;
	}

	/**
	 * Checks whether there are uncommitted changes.
	 *
	 * @return boolean
	 */
	public static function has_uncommitted_changes() {
		$git = Git::instance();

		if ( $git->ready ) {
			$response = $git->status();
			$filechanges = $response['filechanges'];
			foreach ( $filechanges as $mode => $changes ) {
				if ( count( $changes ) > 0 ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Returns the URL for a commit on the remote repository (if possible).
	 *
	 * Currently supports Github and Bitbucket.
	 *
	 * @param string $commit_hash the commit hash to retrieve the URL for
	 * @return string the URL to the commit or an empty string if it could not be detected
	 */
	public static function get_commit_url( $commit_hash ) {
		$git = Git::instance();

		$remote_url = $git->remote_url;
		if ( $remote_url ) {
			$remote_url = str_replace( '.git', '', $remote_url );

			$remote_url = str_replace( array( 'http://', 'https://', 'git@' ), '', $remote_url );

			$domain = explode( '/', $remote_url );
			$domain = $domain[0];

			switch ( $domain ) {
				case 'github.com':
				case 'www.github.com':
					return trailingslashit( 'https://' . $remote_url ) . 'commit/' . $commit_hash;
				case 'bitbucket.org':
				case 'www.bitbucket.org':
					return trailingslashit( 'https://' . $remote_url ) . 'commits/' . $commit_hash;
				default:
			}
		}

		return '';
	}

	/**
	 * Generates the version of the repository.
	 *
	 * @param string $mode either 'full' or 'short'
	 * @return string the repository version
	 */
	public static function get_repository_version( $mode = 'full' ) {
		$git = Git::instance();

		if ( $git->ready ) {
			$version_mini_hash = $git->describe( '--always' );
			$version_number = $git->rev_list( 'HEAD' );

			$version_mini_hash = $version_mini_hash['raw_output'];
			$latest_commit_hash = isset( $version_number['raw_output'][0] ) ? trim( $version_number['raw_output'][0] ) : '';
			$version_number = count( $version_number['raw_output'] );

			$version = 'v1.' . $version_number . ( isset( $version_mini_hash[0] ) ? '.' . $version_mini_hash[0] : '' );

			if ( $mode != 'short' && $latest_commit_hash ) {
				$version .= '.' . $latest_commit_hash;
			}

			return $version;
		}

		return '';
	}

	/**
	 * Returns the current branch name
	 *
	 * @return string the current branch name
	 */
	public static function get_current_branch() {
		$git = Git::instance();

		return $git->branch_name;
	}

	/**
	 * Escapes a shell arguments, Windows-compatible.
	 *
	 * This function was taken from the Revisr plugin for WordPress.
	 *
	 * @param string $arg argument to escape
	 * @return string the escaped argument
	 */
	public static function escape_shell_arg( $arg ) {
		$os = self::get_os();
		if ( $os === 'WIN' ) {
			return '"' . str_replace( "'", "'\\''", $arg ) . '"';
		}
		return escapeshellarg( $arg );
	}

	/**
	 * Sets a transient.
	 *
	 * If multisite, then site transient will be used.
	 *
	 * @param string $transient name of the transient
	 * @param mixed $value value of the transient
	 * @param integer $expiration expiration in seconds
	 * @return bool status of the operation
	 */
	public static function set_transient( $transient, $value, $expiration = 0 ) {
		if ( is_object( $value ) || is_array( $value ) ) {
			$value = json_encode( $value );
		}

		if ( is_multisite() ) {
			return set_site_transient( $transient, $value, $expiration );
		}
		return set_transient( $transient, $value, $expiration );
	}

	/**
	 * Gets a transient.
	 *
	 * If multisite, then site transient will be used.
	 *
	 * @param string $transient name of the transient
	 * @param string $mode type of the transient value ('default', 'array' or 'string')
	 * @return mixed value of the transient or false
	 */
	public static function get_transient( $transient, $mode = 'default' ) {
		$value = false;
		if ( is_multisite() ) {
			$value = get_site_transient( $transient );
		} else {
			$value = get_transient( $transient );
		}

		if ( $value !== false ) {
			if ( $mode == 'array' ) {
				$value = json_decode( $value, true );
			} elseif ( $mode == 'object' ) {
				$value = json_decode( $value );
			}
		}

		return $value;
	}

	/**
	 * Deletes a transient.
	 *
	 * If multisite, then site transient will be used.
	 *
	 * @param string $transient name of the transient
	 * @return bool status of the operation
	 */
	public static function delete_transient( $transient ) {
		if ( is_multisite() ) {
			return delete_site_transient( $transient );
		}
		return delete_transient( $transient );
	}

	/**
	 * Checks is a string is a path.
	 *
	 * If the path has not been flagged as deleted, the function checks whether the path exists as either a directory or a file.
	 *
	 * @param string $path the path to check if it is a valid path
	 * @param boolean $deleted whether the path does not actually exist anymore (because it has just been deleted)
	 * @return boolean true if $path is a valid path, otherwise false
	 */
	public static function is_path( $path, $deleted = false ) {
		if ( ! empty( $path ) ) {
			// always return true for a deleted path since we cannot check if it still exists
			// TODO: add a regex validation instead
			if ( $deleted ) {
				return true;
			}
			if ( is_dir( $path ) || file_exists( $path ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Gets a three-character code for the current OS PHP is running on.
	 *
	 * @return string the current OS (for example 'LIN' for Linux, 'WIN' for Windows)
	 */
	public static function get_os() {
		return strtoupper( substr( php_uname( 's' ), 0, 3 ) );
	}
}
