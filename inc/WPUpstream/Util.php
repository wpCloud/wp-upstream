<?php
/**
 * @package WPOD
 * @version 0.1.0
 * @author Usability Dynamics Inc.
 */

namespace WPUpstream;

/**
 * This class contains some static utility functions.
 */
final class Util {
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
