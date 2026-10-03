=== Echo UI Toasts ===
Contributors: onkyer-studio-labs
Tags: toast, notifications, toastr, alerts
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1.5
License: MIT
License URI: https://opensource.org/licenses/MIT

WordPress wrapper for Echo UI toast notifications.

== Description ==

Echo UI Toasts bundles Echo UI and exposes it to WordPress through frontend assets, a PHP helper, and a shortcode.

It can run in WP Admin and on the public frontend. Public frontend notifications are enabled by default and can be disabled in Echo UI or Settings > Echo UI Toasts.

== Usage ==

Queue a toast from PHP:

`echo_ui_toast( 'success', 'Profile saved', array( 'description' => 'Your changes are live.', 'context' => 'frontend' ) );`

Show a toast with a shortcode:

`[echo_ui_toast type="success" title="Saved" description="Profile updated"]`

Render a trigger button:

`[echo_ui_toast label="Show toast" type="info" title="Heads up"]`

AJAX and REST JSON responses can include `toast` or `toasts` payloads. Public frontend payloads should use `context: "frontend"` or `context: "public"`.

== Settings ==

The Echo UI settings page lets administrators customize admin/frontend visibility, toast position, maximum visible toasts, animations, per-type durations, sounds, and toast history.

== Changelog ==

= 0.1.0 =
Initial WordPress wrapper for Echo UI 0.1.0.

= 0.1.1 =
Improved admin settings UI, Select2 dropdowns, dual-surface controls, and cache-busted admin assets.

= 0.1.2 =
Refined the admin dashboard layout, tightened spacing, and improved desktop panel rhythm.

= 0.1.3 =
Added a dark, light, and auto theme switcher for the settings page. Dark is the default.

= 0.1.4 =
Redesigned the settings page as the Notification Center: live toast preview, position picker, per-type durations with readable hints, sound test, history type chips, copyable developer snippet, and a save bar that tracks unsaved changes. Select2 is no longer loaded.

= 0.1.5 =
WooCommerce notices now reliably become toasts (collected before WooCommerce prints them), with links such as "View cart" kept as a toast button. Toggle it under Where toasts show. The settings page font is now bundled instead of loaded from Google Fonts.
