<?php
/**
 * @package WPUpstream
 * @version 0.1.8
 * @author Usability Dynamics Inc.
 */

namespace WPUpstream;

/**
 * This class represents a single commit.
 */
final class Commit {
	private static $instances = array();

	public static function get_formatstring() {
		$fields = array(
			'commit_hash'				=> '%H',
			'commit_hash_abbrev'		=> '%h',
			'commit_message'			=> '%s',
			'tree_hash'					=> '%T',
			'tree_hash_abbrev'			=> '%t',
			'author_name'				=> '%an',
			'author_email'				=> '%ae',
			'author_date'				=> '%ai',
			'author_date_timestamp'		=> '%at',
			'committer_name'			=> '%cn',
			'committer_email'			=> '%ce',
			'committer_date'			=> '%ci',
			'committer_date_timestamp'	=> '%ct',
		);

		$formatstring = '';
		foreach ( $fields as $field => $placeholder ) {
			$formatstring .= $field . ':' . $placeholder . '%n';
		}

		return $formatstring;
	}

	public static function get( $commit_args, $output = OBJECT ) {
		$commit = null;
		if ( is_string( $commit_args ) ) {
			if ( isset( self::$instances[ $commit_args ] ) ) {
				$commit = self::$instances[ $commit_args ];
			}
		} elseif ( is_array( $commit_args ) && isset( $commit_args['commit_hash'] ) ) {
			if ( ! isset( self::$instances[ $commit_args['commit_hash'] ] ) ) {
				self::$instances[ $commit_args['commit_hash'] ] = new self( $commit_args );
			}
			$commit = self::$instances[ $commit_args['commit_hash'] ];
		}

		if ( $commit !== null ) {
			if ( $output == ARRAY_A ) {
				return $commit->to_array();
			} elseif ( $output == ARRAY_N ) {
				return array_values( $commit->to_array() );
			}
			return $commit;
		}

		if ( $output == ARRAY_A || $output == ARRAY_N ) {
			return array();
		}
		return null;
	}

	private $data = array();

	private function __construct( $data ) {
		$this->data = $data;

		$this->data['commit_url'] = Util::get_commit_url( $this->data['commit_hash'] );
	}

	public function __isset( $key ) {
		return isset( $this->data[ $key ] );
	}

	public function __get( $key ) {
		if ( isset( $this->data[ $key ] ) ) {
			return $this->data[ $key ];
		}
		return false;
	}

	public function to_array() {
		return $this->data;
	}

	public function is_pushed() {
		$unpushed_commits = Util::get_commits( array(
			'mode'		=> 'unpushed',
			'author'	=> $this->data['author_name'],
			'number'	=> -1,
		), 'list' );

		if ( in_array( $this->data['commit_hash'], $unpushed_commits ) ) {
			return false;
		}
		return true;
	}
}
