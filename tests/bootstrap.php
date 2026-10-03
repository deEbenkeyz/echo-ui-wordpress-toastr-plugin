<?php
/**
 * PHPUnit bootstrap: loads the plugin class without WordPress.
 *
 * WordPress functions are stubbed per test with Brain Monkey (see TestCase).
 *
 * @package Echo_UI_Toasts
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

define( 'ABSPATH', __DIR__ . '/' );
define( 'ECHO_UI_TOASTS_VERSION', 'test' );
define( 'ECHO_UI_TOASTS_FILE', dirname( __DIR__ ) . '/echo-ui-wordpress-toastr-plugin.php' );
define( 'ECHO_UI_TOASTS_DIR', dirname( __DIR__ ) . '/' );
define( 'ECHO_UI_TOASTS_URL', 'https://example.test/wp-content/plugins/echo-ui-wordpress-toastr-plugin/' );

require_once dirname( __DIR__ ) . '/includes/class-echo-ui-toastr-plugin.php';
require_once __DIR__ . '/TestCase.php';
