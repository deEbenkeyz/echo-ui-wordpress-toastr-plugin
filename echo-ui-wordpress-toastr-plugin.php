<?php
/**
 * Plugin Name: Echo UI Toasts
 * Plugin URI: https://github.com/deEbenkeyz/echo-ui
 * Description: WordPress wrapper for Echo UI toast notifications.
 * Version: 0.1.5
 * Author: Onkyer Studio Labs
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: echo-ui-toasts
 *
 * @package Echo_UI_Toasts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ECHO_UI_TOASTS_VERSION', '0.1.5' );
define( 'ECHO_UI_TOASTS_FILE', __FILE__ );
define( 'ECHO_UI_TOASTS_DIR', plugin_dir_path( __FILE__ ) );
define( 'ECHO_UI_TOASTS_URL', plugin_dir_url( __FILE__ ) );

require_once ECHO_UI_TOASTS_DIR . 'includes/class-echo-ui-toastr-plugin.php';

register_activation_hook( __FILE__, array( 'Echo_UI_Toastr_Plugin', 'activate' ) );

add_action( 'plugins_loaded', array( 'Echo_UI_Toastr_Plugin', 'instance' ) );

if ( ! function_exists( 'echo_ui_toast' ) ) {
	/**
	 * Queue a toast from PHP.
	 *
	 * @param string $type    Toast type: success, error, warning, info, or loading.
	 * @param string $title   Toast title.
	 * @param array  $options Optional Echo UI options.
	 * @return string Toast id.
	 */
	function echo_ui_toast( $type, $title, $options = array() ) {
		return Echo_UI_Toastr_Plugin::instance()->add_toast( $type, $title, $options );
	}
}
