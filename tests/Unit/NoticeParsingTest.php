<?php
/**
 * Notice HTML parsing and link resolution.
 *
 * @package Echo_UI_Toasts
 */

namespace Echo_UI_Toasts\Tests\Unit;

use Echo_UI_Toasts\Tests\TestCase;

/**
 * Tests parse_notice_html() and absolute_url().
 */
class NoticeParsingTest extends TestCase {

	public function test_woocommerce_link_becomes_an_action() {
		$parsed = $this->call( 'parse_notice_html', '<a href="https://example.test/cart/" tabindex="1" class="button wc-forward">View cart</a> &ldquo;Jollof Tray&rdquo; has been added to your cart.' );

		$this->assertSame( '“Jollof Tray” has been added to your cart.', $parsed['title'] );
		$this->assertSame(
			array(
				'label' => 'View cart',
				'url'   => 'https://example.test/cart/',
			),
			$parsed['action']
		);
	}

	public function test_unsafe_links_are_dropped_but_their_text_kept() {
		$parsed = $this->call( 'parse_notice_html', 'Wrong password. <a href="javascript:alert(1)">Lost it?</a>' );

		$this->assertNull( $parsed['action'] );
		$this->assertSame( 'Wrong password. Lost it?', $parsed['title'] );
	}

	public function test_relative_admin_links_resolve_against_admin_url() {
		$this->is_admin = true;

		$this->assertSame( 'https://example.test/wp-admin/post.php?post=12&action=edit', $this->call( 'absolute_url', 'post.php?post=12&action=edit' ) );
	}

	public function test_root_relative_links_resolve_against_the_site_origin() {
		$this->assertSame( 'https://example.test/shop/cart/', $this->call( 'absolute_url', '/shop/cart/' ) );
	}

	public function test_anchors_and_empty_links_are_ignored() {
		$this->assertSame( '', $this->call( 'absolute_url', '#details' ) );
		$this->assertSame( '', $this->call( 'absolute_url', '   ' ) );
	}

	public function test_whitespace_and_markup_are_collapsed() {
		$parsed = $this->call( 'parse_notice_html', "<p><strong>Error:</strong>\n\t Could not   write.</p>" );

		$this->assertSame( 'Error: Could not write.', $parsed['title'] );
	}
}
