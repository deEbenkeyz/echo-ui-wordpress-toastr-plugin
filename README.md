# Echo UI Toasts for WordPress

WordPress wrapper for [Echo UI](https://github.com/deEbenkeyz/echo-ui), a framework-agnostic browser toast notification library.

## Install

Copy this folder into `wp-content/plugins/echo-ui-wordpress-toastr-plugin`, then activate **Echo UI Toasts** in WordPress.

## PHP usage

```php
echo_ui_toast( 'success', 'Profile saved', array(
	'description' => 'Your changes are live.',
	'duration'    => 4000,
	'context'     => 'frontend',
) );
```

Supported types are `success`, `error`, `warning`, `info`, and `loading`.
Supported contexts are `admin`, `frontend`, and `auto`.

## Shortcode usage

Show a toast when the page loads:

```text
[echo_ui_toast type="success" title="Saved" description="Profile updated"]
```

Render a button that shows a toast when clicked:

```text
[echo_ui_toast label="Show toast" type="info" title="Heads up"]
```

## JavaScript usage

The plugin loads Echo UI as `window.Echo` and also exposes a small helper:

```js
window.echoUiToast('success', 'Saved', {
	description: 'Your changes are live.',
	context: 'frontend'
});
```

AJAX and REST JSON responses may include a toast payload. Frontend responses must use `context: 'frontend'` or `context: 'public'`.

```json
{
	"success": true,
	"data": {
		"toast": {
			"type": "success",
			"title": "Import completed",
			"context": "admin"
		}
	}
}
```

## Configuration

Go to **Echo UI** in the WP Admin menu, or **Settings > Echo UI Toasts**, to open the Notification Center. Changes are previewed live in the page before you save.

The settings page controls:

- WP Admin Dashboard notifications
- Public Frontend notifications
- toast position
- maximum visible toasts
- enter and exit animations
- per-type durations for success, info, warning, error, and loading
- sound and volume
- history, history limit, and which toast types appear in history
- settings page theme: dark (default), light, or auto (follows the OS)

Frontend notifications are enabled by default.

Filter the default Echo UI config:

```php
add_filter( 'echo_ui_toastr_config', function ( $config ) {
	$config['position'] = 'bottom-right';
	$config['sound']['enabled'] = false;

	return $config;
} );
```

## WooCommerce

When WooCommerce is active, cart, checkout, and account notices are shown as toasts instead of inline banners. The first link in a notice (for example **View cart**) becomes a button on the toast. Turn this off under **Where toasts show**, or per request:

```php
add_filter( 'echo_ui_toasts_woocommerce_notices', '__return_false' );
```

Toasts queued from PHP can carry the same kind of link:

```php
echo_ui_toast( 'success', 'Order placed', array(
	'action'  => array( 'label' => 'View order', 'url' => $order_url ),
	'context' => 'frontend',
) );
```

## Bundled library

This plugin vendors Echo UI `0.1.0` browser assets:

- `assets/vendor/echo-ui/echo.umd.js`
- `assets/vendor/echo-ui/style.css`

The settings page font, Figtree (SIL Open Font License 1.1), is bundled in `assets/vendor/figtree/`.
