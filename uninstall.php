<?php
/**
 * Remove plugin data when Echo UI Toasts is deleted from the Plugins screen.
 *
 * @package Echo_UI_Toasts
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$echo_ui_toasts_option = 'echo_ui_toasts_options';

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $echo_ui_toasts_site_id ) {
		switch_to_blog( $echo_ui_toasts_site_id );
		delete_option( $echo_ui_toasts_option );
		restore_current_blog();
	}
} else {
	delete_option( $echo_ui_toasts_option );
}
