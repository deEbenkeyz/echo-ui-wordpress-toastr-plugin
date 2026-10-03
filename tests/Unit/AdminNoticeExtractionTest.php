<?php
/**
 * Admin notice detection.
 *
 * @package Echo_UI_Toasts
 */

namespace Echo_UI_Toasts\Tests\Unit;

use Brain\Monkey\Filters;
use Echo_UI_Toasts\Tests\TestCase;

/**
 * Tests extract_admin_notices().
 */
class AdminNoticeExtractionTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->is_admin = true;
	}

	public function test_only_real_status_notices_are_extracted() {
		$html = <<<'HTML'
<div id="setting-error-settings_updated" class="notice notice-success settings-error is-dismissible"><p><strong>Settings saved.</strong></p></div>
<div class="notice notice-info promo"><div class="inner"><div class="logo"><img src="logo.png" alt=""></div><div><h3>Set up analytics</h3><p>Over 3 million sites.</p></div></div><p><a href="https://example.com" class="button button-primary">Connect</a></p></div>
<div class="updated notice is-dismissible"><p>Post published. <a href="post.php?post=12&amp;action=edit">Edit post</a></p></div>
<div class="form-error">Field error, not a notice.</div>
<div class="notice notice-warning hidden"><p>Hidden until JavaScript shows it.</p></div>
<div class="error"><p>Legacy-style error.</p></div>
HTML;

		$notices = $this->call( 'extract_admin_notices', $html );

		$this->assertSame(
			array(
				array(
					'type'   => 'success',
					'title'  => 'Settings saved.',
					'action' => null,
				),
				array(
					'type'   => 'success',
					'title'  => 'Post published.',
					'action' => array(
						'label' => 'Edit post',
						'url'   => 'https://example.test/wp-admin/post.php?post=12&action=edit',
					),
				),
				array(
					'type'   => 'error',
					'title'  => 'Legacy-style error.',
					'action' => null,
				),
			),
			$notices
		);
	}

	public function test_a_notice_nested_in_another_counts_once() {
		$notices = $this->call( 'extract_admin_notices', '<div class="notice notice-warning"><p>Outer</p><div class="notice notice-error"><p>Inner</p></div></div>' );

		$this->assertCount( 1, $notices );
		$this->assertSame( 'warning', $notices[0]['type'] );
		$this->assertSame( 'Outer Inner', $notices[0]['title'] );
	}

	public function test_long_copy_is_treated_as_promotional() {
		$notices = $this->call( 'extract_admin_notices', '<div class="notice"><p>' . str_repeat( 'word ', 80 ) . '</p></div>' );

		$this->assertSame( array(), $notices );
	}

	public function test_filter_can_veto_a_notice() {
		Filters\expectApplied( 'echo_ui_toasts_capture_admin_notice' )->once()->andReturn( false );

		$this->assertSame( array(), $this->call( 'extract_admin_notices', '<div class="notice notice-success"><p>Saved.</p></div>' ) );
	}

	public function test_empty_html_returns_nothing() {
		$this->assertSame( array(), $this->call( 'extract_admin_notices', "  \n " ) );
	}
}
