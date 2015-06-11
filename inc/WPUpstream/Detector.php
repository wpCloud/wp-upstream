<?php
/**
 * @package WPUpstream
 * @version 0.1.3
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

	/**
	 * Checks if the current filter handles something that should be dealt with as a process action.
	 *
	 * Depending on which filter the function is hooked into, it does different things.
	 *
	 * @param mixed $ret compatibility for filters; if the function is hooked into a filter, it needs to return the first parameter
	 * @param mixed $data maybe the action or filter provides additional data that we can use
	 * @return mixed same like the $ret parameter
	 */
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
				if ( isset( $_REQUEST['action'] ) && current_user_can( 'delete_themes' ) ) {
					if ( $_REQUEST['action'] == 'delete' ) {
						if ( isset( $_GET['stylesheet'] ) ) {
							$theme = $_GET['stylesheet'];
							$theme_data = $this->get_data( $theme, $type );
							$name = $this->get_data_field( 'Name', $theme_data );

							$this->add_process_action( $mode, $name, $type );
						}
					} elseif ( $_REQUEST['action'] == 'delete-selected' && isset( $_REQUEST['verify-delete'] ) ) {
						$themes = isset( $_REQUEST['checked'] ) ? (array) $_REQUEST['checked'] : array();
						foreach ( $themes as $theme ) {
							$theme_data = $this->get_data( $theme, $type );
							$name = $this->get_data_field( 'Name', $theme_data );

							$this->add_process_action( $mode, $name, $type );
						}
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
						$plugin_data = $this->get_data( $plugin, $type );
						$name = $this->get_data_field( 'Name', $plugin_data );

						$this->add_process_action( $mode, $name, $type );
					}
				}
				break;
			default:
		}

		return $ret;
	}

	/**
	 * Adds a new action to the active process.
	 *
	 * @param string $mode either 'install', 'update' or 'delete'
	 * @param string $item_name the name of the item that has changed
	 * @param string $item_type the type of the item that has changed (either 'core', 'plugin' or 'theme')
	 * @param string|null $new_version new version if available
	 * @param string|null $old_version old version if available
	 * @return boolean true if the action was added to the active process successfully, otherwise false
	 */
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

	/**
	 * Gets data for a plugin or theme.
	 *
	 * By default, plugin information is retrieved locally while theme information is retrieved by using the wordpress.org API.
	 *
	 * @param string $item slug of the plugin or theme
	 * @param string $type either 'plugin' or 'theme'
	 * @param string $mode either 'local' or 'api' (or empty for using the default)
	 * @return array|object|false the plugin/theme data or false if not available
	 */
	private function get_data( $item, $type = 'plugin', $mode = '' ) {
		if ( ! empty( $item ) ) {
			switch ( $type ) {
				case 'theme':
					if ( $mode == 'api' ) {
						if ( function_exists( 'themes_api' ) ) {
							return themes_api( 'theme_information', array( 'slug' => $item, 'fields' => array( 'sections' => false, 'tags' => false ) ) );
						}
					} else {
						if ( function_exists( 'wp_get_theme' ) ) {
							return wp_get_theme( $item );
						}
					}
					break;
				case 'plugin':
					if ( $mode == 'api' ) {
						if ( function_exists( 'plugins_api' ) ) {
							return plugins_api( 'plugin_information', array( 'slug' => $item, 'fields' => array( 'banners' => false, 'reviews' => false ) ) );
						}
					} else {
						if ( function_exists( 'get_plugin_data' ) ) {
							return get_plugin_data( WP_PLUGIN_DIR . '/' . $item, false, true );
						}
					}
					break;
				default:
			}
		}
		return false;
	}

	/**
	 * Gets a field value from a plugin/theme data object or array.
	 *
	 * This function automatically checks in what format the data is stored and then gets the value.
	 *
	 * @param string $field field name to retrieve the value for
	 * @param object|array $data the data from which to retrieve the value
	 * @return string the field value
	 */
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
