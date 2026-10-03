<?php
/**
 * WooCommerce session notices to toasts.
 *
 * @package Echo_UI_Toasts
 */

namespace Echo_UI_Toasts\Tests\Unit;

use Brain\Monkey\Functions;
use Echo_UI_Toasts\Tests\TestCase;

/**
 * Tests queue_woocommerce_notices().
 */
class WooCommerceNoticesTest extends TestCase {

	/**
	 * Fake WooCommerce notice store.
	 *
	 * @var array<string,array<int,array<string,string>>>
	 */
	private $wc_notices = array();

	protected function setUp(): void {
		parent::setUp();

		$this->options    = array(
			'enable_frontend' => 1,
			'woo_notices'     => 1,
		);
		$this->wc_notices = array(
			'success' => array( array( 'notice' => '<a href="https://example.test/cart/" class="button wc-forward">View cart</a> Hoodie has been added to your cart.' ) ),
			'error'   => array( array( 'notice' => 'Please enter a valid postcode.' ) ),
			'notice'  => array( array( 'notice' => '   ' ) ),
		);

		Functions\when( 'wc_get_notices' )->alias(
			function () {
				return $this->wc_notices;
			}
		);
		Functions\when( 'wc_clear_notices' )->alias(
			function () {
				$this->wc_notices = array();
			}
		);
	}

	public function test_notices_become_toasts_and_are_cleared() {
		$this->plugin->queue_woocommerce_notices();

		$this->assertSame(
			array(
				array(
					'type'    => 'success',
					'title'   => 'Hoodie has been added to your cart.',
					'silent'  => true,
					'action'  => array(
						'label' => 'View cart',
						'url'   => 'https://example.test/cart/',
					),
					'context' => 'frontend',
				),
				array(
					'type'    => 'error',
					'title'   => 'Please enter a valid postcode.',
					'silent'  => true,
					'context' => 'frontend',
				),
			),
			$this->queued_toasts()
		);
		$this->assertSame( array(), $this->wc_notices, 'Notices must be cleared so WooCommerce does not print them again.' );
	}

	public function test_switch_off_leaves_notices_for_woocommerce() {
		$this->options['woo_notices'] = 0;

		$this->plugin->queue_woocommerce_notices();

		$this->assertSame( array(), $this->queued_toasts() );
		$this->assertNotEmpty( $this->wc_notices );
	}

	public function test_frontend_disabled_leaves_notices_for_woocommerce() {
		$this->options['enable_frontend'] = 0;

		$this->plugin->queue_woocommerce_notices();

		$this->assertSame( array(), $this->queued_toasts() );
		$this->assertNotEmpty( $this->wc_notices );
	}

	public function test_admin_requests_are_ignored() {
		$this->is_admin = true;

		$this->plugin->queue_woocommerce_notices();

		$this->assertNotEmpty( $this->wc_notices );
	}
}
