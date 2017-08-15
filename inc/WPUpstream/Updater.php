<?php
/**
 *
 * - pre_set_site_transient_update_plugins
 * - site_transient_update_plugins - on every run
 *
 *    wp transient delete update_plugins
 */
namespace WPUpstream;

class Updater {

  function __construct() {

  // show a custom message next to the update nag, perhaps display last commit message or something
    add_action( 'in_plugin_update_message-wp-upstream/wp-upstream.php', function ( $plugin_data, $response ) {

      if( $response && $response->message ) {
        echo $response->message;
      } else {
        echo 'You are seeing this because you subscribed to latest updates.';
      }

    }, 10, 2 );

    if( defined( 'WP_UPSTREAM_AUTO_UPDATE' ) && WP_UPSTREAM_AUTO_UPDATE === true ) {
      add_filter( 'auto_update_plugin', array( 'WPUpstream\Updater', 'auto_update_specific_plugins' ), 10, 2 );
    }

    add_filter( 'site_transient_update_plugins', array( 'WPUpstream\Updater', 'update_check_handler' ), 10 );
    add_filter( 'upgrader_process_complete', array( 'WPUpstream\Updater', 'upgrader_process_complete' ), 10, 2 );



  }

  /**
   * Automatic Updater.
   *
   * @param $update
   * @param $item
   * @return bool
   */
  static public function auto_update_specific_plugins( $update, $item ) {
    // Array of plugin slugs to always auto-update

    $plugins = array( 'wp-upstream' );

    if( in_array( $item->slug, $plugins ) ) {
      return true; // Always update plugins in this array
    } else {
      return $update; // Else, use the normal API response to decide whether to update or not
    }

  }

  /**
   * Check API for pre-release updates.
   *
   * @author potanin@UD
   * @return array|mixed
   */
  static public function get_update_check_result() {

    if( get_site_transient( 'wp_upstream_updates' ) && !isset( $_GET[ 'force-check' ] ) ) {

      $_transient = get_site_transient( 'wp_upstream_updates' );

      if( is_array( $_transient ) ) {
        return $_transient;
      }

    }

    $_products = array( 'wp-upstream' => 'wp-upstream/wp-upstream.php' );

    foreach( $_products as $_product_name => $_product_path ) {

      try {

        // Must be able to parse composer.json from plugin file, hopefully to detect the "_build.sha" field.
        $_composer = json_decode( @file_get_contents( trailingslashit( plugin_dir_path( __DIR__ ) ) . '../composer.json' ) );

        if( is_object( $_composer ) && $_composer->extra && isset( $_composer->extra->_build ) && isset( $_composer->extra->_build->sha ) ) {
          $_version = $_composer->extra->_build->sha;
        } else {
          $_version = null;
        }

        $_detail[ $_product_name ] = array(
          'request_url' => 'https://api.usabilitydynamics.com/v1/product/updates/' . $_product_name . '/latest/?version=' . $_version,
          'product_path' => $_product_path,
          'response' => null,
          'have_update' => null,
        );

        $_response = wp_remote_get( $_detail[ $_product_name ][ 'request_url' ] );

        if( wp_remote_retrieve_response_code( $_response ) === 200 ) {
          $_body = wp_remote_retrieve_body( $_response );
          $_body = json_decode( $_body );

          if( isset( $_body->data ) ) {
            $_detail[ $_product_name ][ 'response' ] = $_body->data;
            if(isset($_version) && isset($_body->data->versionSha) && $_version != $_body->data->versionSha) {
              if (!$_body->data->changesSince || $_body->data->changesSince > 0) {
                $_detail[$_product_name]['have_update'] = true;
              }
            }

          } else {
            $_detail[ $_product_name ][ 'response' ] = null;
            $_detail[ $_product_name ][ 'have_update' ] = false;
          }
        }

      } catch ( \Exception $e ) {
      }

    }

    if( isset( $_detail ) ) {
      $_transient_result = set_site_transient( 'wp_upstream_updates', array( 'data' => $_detail, 'cached' => true ), 1800 );
    }

    return array( 'data' => isset( $_detail ) ? $_detail : null, 'cached' => false, 'transient_result' => isset( $_transient_result ) ? $_transient_result : 0 );

  }

  /**
   *
   */
  static public function upgrader_process_complete() {
    delete_site_transient( 'wp_upstream_updates' );
  }

  /**
   * Check pre-release updates.
   *
   * @todo Refine the "when-to-check" logic. Right now multple requests may be made per request.
   *
   * @author potanin@UD
   *
   * @param $response
   *
   * @return mixed
   */
  static public function update_check_handler( $response ) {

    $_ud_get_product_updates = self::get_update_check_result();

    foreach( (array)$_ud_get_product_updates[ 'data' ] as $_product_short_name => $_product_detail ) {

      if( $_product_detail[ 'have_update' ] ) {
        $response->response[ $_product_detail[ 'product_path' ] ] = $_product_detail[ 'response' ];
      }

    }
    return $response;

  }


}
