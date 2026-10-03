<?php
/**
 * Toast normalization.
 *
 * @package Echo_UI_Toasts
 */

namespace Echo_UI_Toasts\Tests\Unit;

use Echo_UI_Toasts\Tests\TestCase;

/**
 * Tests normalize_toast().
 */
class NormalizeToastTest extends TestCase {

	public function test_unknown_type_becomes_info_and_markup_is_stripped() {
		$toast = $this->call( 'normalize_toast', 'shout', '<b>Saved</b><script>x()</script>', array( 'description' => '<i>Done</i>' ) );

		$this->assertSame( 'info', $toast['type'] );
		$this->assertSame( 'Saved', $toast['title'] );
		$this->assertSame( 'Done', $toast['description'] );
		$this->assertStringStartsWith( 'echo-wp-', $toast['id'] );
	}

	public function test_duration_and_context() {
		$toast = $this->call(
			'normalize_toast',
			'success',
			'Saved',
			array(
				'duration' => '2500',
				'context'  => 'admin',
			)
		);

		$this->assertSame( 2500, $toast['duration'] );
		$this->assertSame( 'admin', $toast['context'] );
	}

	public function test_auto_context_follows_the_request() {
		$this->assertSame( 'frontend', $this->call( 'normalize_toast', 'info', 'Hi', array() )['context'] );

		$this->is_admin = true;
		$this->assertSame( 'admin', $this->call( 'normalize_toast', 'info', 'Hi', array( 'context' => 'auto' ) )['context'] );
	}

	public function test_only_http_actions_are_kept() {
		$safe   = $this->call(
			'normalize_toast',
			'success',
			'Order placed',
			array(
				'action' => array(
					'label' => 'View <b>order</b>',
					'url'   => 'https://example.test/order/1/',
				),
			)
		);
		$unsafe = $this->call(
			'normalize_toast',
			'success',
			'Order placed',
			array(
				'action' => array(
					'label' => 'Click',
					'url'   => 'javascript:alert(1)',
				),
			)
		);

		$this->assertSame(
			array(
				'label' => 'View order',
				'url'   => 'https://example.test/order/1/',
			),
			$safe['action']
		);
		$this->assertArrayNotHasKey( 'action', $unsafe );
	}
}
