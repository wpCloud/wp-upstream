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
	public static function escape_shell_arg( $arg ) {
		$os = self::get_os();
		if ( $os === 'WIN' ) {
			return '"' . str_replace( "'", "'\\''", $arg ) . '"';
		}
		return escapeshellarg( $arg );
	}

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

	public static function get_os() {
		return strtoupper( substr( php_uname( 's' ), 0, 3 ) );
	}
}
