<?php
/**
 * Settings sanitization.
 *
 * @package Echo_UI_Toasts
 */

namespace Echo_UI_Toasts\Tests\Unit;

use Echo_UI_Toasts\Tests\TestCase;

/**
 * Tests Echo_UI_Toastr_Plugin::sanitize_options().
 */
class SanitizeOptionsTest extends TestCase {

	public function test_empty_input_falls_back_to_defaults_with_switches_off() {
		$clean = $this->plugin->sanitize_options( array() );

		$this->assertSame( 0, $clean['enable_admin'], 'Unchecked checkboxes are not posted, so they must save as off.' );
		$this->assertSame( 0, $clean['enable_frontend'] );
		$this->assertSame( 'top-right', $clean['position'] );
		$this->assertSame( 'normal', $clean['motion_speed'] );
		$this->assertSame( 'dark', $clean['settings_theme'] );
		$this->assertSame( array(), $clean['history_types'] );
	}

	public function test_numbers_are_clamped_to_their_ranges() {
		$clean = $this->plugin->sanitize_options(
			array(
				'max'              => '99',
				'duration_success' => '99999',
				'duration_error'   => '-5',
				'sound_volume'     => '1',
				'history_limit'    => '0',
			)
		);

		$this->assertSame( 10, $clean['max'] );
		$this->assertSame( 60000, $clean['duration_success'] );
		$this->assertSame( 5, $clean['duration_error'], 'absint() turns -5 into 5.' );
		$this->assertSame( 0.2, $clean['sound_volume'] );
		$this->assertSame( 1, $clean['history_limit'] );
	}

	public function test_unknown_choices_fall_back_to_defaults() {
		$clean = $this->plugin->sanitize_options(
			array(
				'position'       => 'middle',
				'enter'          => 'spin',
				'exit'           => 'drop',
				'motion_speed'   => 'ludicrous',
				'settings_theme' => 'neon',
			)
		);

		$this->assertSame( 'top-right', $clean['position'] );
		$this->assertSame( 'slide', $clean['enter'] );
		$this->assertSame( 'fade', $clean['exit'], 'Echo UI has no drop exit animation.' );
		$this->assertSame( 'normal', $clean['motion_speed'] );
		$this->assertSame( 'dark', $clean['settings_theme'] );
	}

	public function test_valid_choices_are_kept() {
		$clean = $this->plugin->sanitize_options(
			array(
				'enable_admin'   => '1',
				'position'       => 'bottom-left',
				'enter'          => 'drop',
				'exit'           => 'pop',
				'motion_speed'   => 'relaxed',
				'settings_theme' => 'auto',
				'woo_notices'    => '1',
			)
		);

		$this->assertSame( 1, $clean['enable_admin'] );
		$this->assertSame( 'bottom-left', $clean['position'] );
		$this->assertSame( 'drop', $clean['enter'] );
		$this->assertSame( 'pop', $clean['exit'] );
		$this->assertSame( 'relaxed', $clean['motion_speed'] );
		$this->assertSame( 'auto', $clean['settings_theme'] );
		$this->assertSame( 1, $clean['woo_notices'] );
	}

	public function test_type_lists_keep_known_types_in_canonical_order() {
		$clean = $this->plugin->sanitize_options(
			array(
				'history_types' => array( 'warning', 'bogus', 'SUCCESS', 'error' ),
				'sound_types'   => 'error',
			)
		);

		$this->assertSame( array( 'success', 'error', 'warning' ), $clean['history_types'] );
		$this->assertSame( array(), $clean['sound_types'], 'A non-array value is rejected.' );
	}
}
