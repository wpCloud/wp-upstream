<?php
/**
 * @package WPOD
 * @version 0.0.1
 * @author Usability Dynamics Inc.
 */

namespace WPUpstream;

/**
 * This class detects which actions are performed during a process.
 *
 * From the actions detected we can later create a proper Git commit message.
 */
final class Detector {
	private static $instance = null;

	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'upgrader_process_complete', array( $this, 'check_action' ), 10, 2 );
		add_filter( 'install_theme_complete_actions', array( $this, 'check_action' ), 10, 2 );
		add_filter( 'install_plugin_complete_actions', array( $this, 'check_action' ), 10, 2 );

		// there is no action or filter for deletion, so we need to use the actions below as a workaround
		add_action( 'load-themes.php', array( $this, 'check_action' ) );
		add_action( 'load-plugins.php', array( $this, 'check_action' ) );

		// the following filters do not need to be used since 'upgrader_process_complete' already handles their actions
		//add_filter( 'update_theme_complete_actions', array( $this, 'check_action' ), 10, 2 );
		//add_filter( 'update_plugin_complete_actions', array( $this, 'check_action' ), 10, 2 );
		//add_filter( 'update_bulk_theme_complete_actions', array( $this, 'check_action' ), 10, 2 );
		//add_filter( 'update_bulk_plugins_complete_actions', array( $this, 'check_action' ), 10, 2 );
	}

	public function check_action( $ret = array(), $data = null ) {
		$filter = current_filter();

		switch ( $filter ) {
			case 'upgrader_process_complete':
				if ( is_array( $data ) && count( $data ) > 0 ) {
					if ( isset( $data['action'] ) && isset( $data['type'] ) ) {
						$mode = $data['action'];
						$type = $data['type'];
						if ( $type == 'core' ) {
							global $wp_version;
							$name = __( 'WordPress', 'wpupstream' );
							$version_new = $wp_version;

							$this->add_process_action( $mode, $name, $type, $version_new );
						} elseif ( isset( $data['plugins'] ) || isset( $data['themes'] ) ) {
							$items = isset( $data['themes'] ) ? $data['themes'] : $data['plugins'];
							foreach ( $items as $item ) {
								if ( ! empty( $item ) ) {
									$item_data = $this->get_data( $item, $type );
									$name = $this->get_data_field( 'Name', $item_data );
									$version_new = $this->get_data_field( 'Version', $item_data );

									$this->add_process_action( $mode, $name, $type, $version_new );
								}
							}
						} elseif ( isset( $data['plugin'] ) || isset( $data['theme'] ) ) {
							$item = isset( $data['theme'] ) ? $data['theme'] : $data['plugin'];
							if ( ! empty( $item ) ) {
								$item_data = $this->get_data( $item, $type );
								$name = $this->get_data_field( 'Name', $item_data );
								$version_new = $this->get_data_field( 'Version', $item_data );

								$this->add_process_action( $mode, $name, $type, $version_new );
							}
						}
					}
				}
				break;
			case 'install_theme_complete_actions':
			case 'install_plugin_complete_actions':
				if ( $data !== null ) {
					$mode = 'install';
					$type = strpos( $filter, 'theme' ) !== false ? 'theme' : 'plugin';
					$name = $this->get_data_field( 'Name', $data );
					$version_new = $this->get_data_field( 'Version', $data );
					$this->add_process_action( $mode, $name, $type, $version_new );
				}
				break;
			case 'load-themes.php':
				$mode = 'delete';
				$type = 'theme';
				if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'delete' && current_user_can( 'delete_themes' ) ) {
					if ( isset( $_GET['stylesheet'] ) ) {
						$theme = $_GET['stylesheet'];
						$theme_data = $this->get_data( $theme, $type, 'local' );
						$name = $this->get_data_field( 'Name', $theme_data );

						$this->add_process_action( $mode, $name, $type );
					}
				}
				break;
			case 'load-plugins.php':
				$mode = 'delete';
				$type = 'plugin';
				if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'delete-selected' && isset( $_REQUEST['verify-delete'] ) && current_user_can( 'delete_plugins' ) ) {
					$plugins = isset( $_REQUEST['checked'] ) ? (array) $_REQUEST['checked'] : array();
					$plugins = array_filter( $plugins, 'is_plugin_inactive' );
					foreach ( $plugins as $plugin ) {
						$plugin_data = $this->get_data( $plugin, $type, 'local' );
						$name = $this->get_data_field( 'Name', $plugin_data );

						$this->add_process_action( $mode, $name, $type );
					}
				}
				break;
			default:
		}

		return $ret;
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
				return set_transient( 'wpupstream_process_actions', json_encode( $actions ) );
			}
		}
		return false;
	}

	private function get_data( $item, $type = 'plugin', $mode = '' ) {
		if ( ! empty( $item ) ) {
			switch ( $type ) {
				case 'theme':
					if ( $mode == 'local' ) {
						return wp_get_theme( $item );
					}
					return themes_api( 'theme_information', array( 'slug' => $item, 'fields' => array( 'sections' => false, 'tags' => false ) ) );
				case 'plugin':
					if ( $mode == 'api' ) {
						return plugins_api( 'plugin_information', array( 'slug' => $item, 'fields' => array( 'banners' => false, 'reviews' => false ) ) );
					}
					return get_plugin_data( WP_PLUGIN_DIR . '/' . $item, false, true );
				default:
			}
		}
		return false;
	}

	private function get_data_field( $field, $data ) {
		if ( is_array( $data ) ) {
			if ( isset( $data[ $field ] ) ) {
				return $data[ $field ];
			}
			$field = strtolower( $field );
			if ( isset( $data[ $field ] ) ) {
				return $data[ $field ];
			}
		} elseif ( is_object( $data ) ) {
			if ( isset( $data->$field ) ) {
				return $data->$field;
			}
			$field = strtolower( $field );
			if ( isset( $data->$field ) ) {
				return $data->$field;
			}
		}
		return 'unknown';
	}
}
