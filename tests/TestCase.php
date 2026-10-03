<?php
/**
 * Base test case with WordPress function stubs.
 *
 * @package Echo_UI_Toasts
 */

namespace Echo_UI_Toasts\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Echo_UI_Toastr_Plugin;
use ReflectionClass;
use ReflectionMethod;

/**
 * Stubs the small slice of WordPress the plugin logic touches.
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase {

	/**
	 * Stored plugin option returned by get_option().
	 *
	 * @var array<string,mixed>
	 */
	protected $options = array();

	/**
	 * Value returned by is_admin().
	 *
	 * @var bool
	 */
	protected $is_admin = false;

	/**
	 * Fresh plugin instance.
	 *
	 * @var Echo_UI_Toastr_Plugin
	 */
	protected $plugin;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();

		Functions\stubs(
			array(
				'absint'              => static function ( $value ) {
					return abs( (int) $value );
				},
				'sanitize_key'        => static function ( $key ) {
					return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
				},
				'sanitize_text_field' => static function ( $text ) {
					return trim( strip_tags( (string) $text ) );
				},
				'wp_strip_all_tags'   => static function ( $text ) {
					return trim( strip_tags( preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text ) ) );
				},
				'esc_url_raw'         => static function ( $url, $protocols = null ) {
					$url = trim( (string) $url );

					if ( preg_match( '/^([a-z][a-z0-9+.\-]*):/i', $url, $scheme ) && ! in_array( strtolower( $scheme[1] ), $protocols ? $protocols : array( 'http', 'https', 'mailto' ), true ) ) {
						return '';
					}

					return $url;
				},
				'wp_parse_url'        => static function ( $url, $component = -1 ) {
					return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
				},
				'home_url'            => static function ( $path = '' ) {
					return 'https://example.test' . ( $path ? '/' . ltrim( $path, '/' ) : '' );
				},
				'admin_url'           => static function ( $path = '' ) {
					return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
				},
				'get_bloginfo'        => 'UTF-8',
				'wp_generate_uuid4'   => static function () {
					return uniqid( '', true );
				},
				'wp_parse_args'       => static function ( $args, $defaults ) {
					return array_merge( $defaults, (array) $args );
				},
				'get_option'          => function () {
					return $this->options;
				},
				'is_admin'            => function () {
					return $this->is_admin;
				},
				'wp_doing_ajax'       => false,
				'plugin_basename'     => 'echo-ui-wordpress-toastr-plugin/echo-ui-wordpress-toastr-plugin.php',
				'add_shortcode'       => null,
				'wp_style_is'         => true,
				'wp_script_is'        => true,
				'wp_enqueue_style'    => null,
				'wp_enqueue_script'   => null,
			)
		);

		$this->plugin = $this->fresh_plugin();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Reset the singleton and build a new instance.
	 *
	 * @return Echo_UI_Toastr_Plugin
	 */
	protected function fresh_plugin() {
		$instance = ( new ReflectionClass( Echo_UI_Toastr_Plugin::class ) )->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );

		return Echo_UI_Toastr_Plugin::instance();
	}

	/**
	 * Call a private plugin method.
	 *
	 * @param string $method Method name.
	 * @param mixed  ...$args Arguments.
	 * @return mixed
	 */
	protected function call( $method, ...$args ) {
		$reflection = new ReflectionMethod( $this->plugin, $method );
		$reflection->setAccessible( true );

		return $reflection->invoke( $this->plugin, ...$args );
	}

	/**
	 * Toasts queued on the plugin instance.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	protected function queued_toasts() {
		$toasts = ( new ReflectionClass( $this->plugin ) )->getProperty( 'toasts' );
		$toasts->setAccessible( true );

		return array_map(
			static function ( $toast ) {
				unset( $toast['id'] );
				return $toast;
			},
			$toasts->getValue( $this->plugin )
		);
	}
}
