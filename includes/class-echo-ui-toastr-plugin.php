<?php
/**
 * Main plugin integration.
 *
 * @package Echo_UI_Toasts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers Echo UI assets and exposes WordPress-friendly helpers.
 */
class Echo_UI_Toastr_Plugin {
	const SCRIPT_VENDOR = 'echo-ui';
	const SCRIPT_BRIDGE = 'echo-ui-toasts';
	const STYLE_VENDOR  = 'echo-ui';
	const STYLE_BRIDGE  = 'echo-ui-toasts';
	const STYLE_ADMIN   = 'echo-ui-toasts-admin';
	const STYLE_FONT    = 'echo-ui-admin-font';
	const SCRIPT_ADMIN  = 'echo-ui-toasts-admin';
	const OPTION_NAME   = 'echo_ui_toasts_options';
	const SETTINGS_SLUG = 'echo-ui-toasts-dashboard';

	/**
	 * Singleton instance.
	 *
	 * @var Echo_UI_Toastr_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Toasts queued from PHP.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private $toasts = array();

	/**
	 * Whether boot settings have been injected.
	 *
	 * @var bool
	 */
	private $settings_printed = false;

	/**
	 * Active admin notice output buffer level.
	 *
	 * @var int|null
	 */
	private $notice_buffer_level = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Echo_UI_Toastr_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Set default options on activation.
	 */
	public static function activate() {
		if ( false === get_option( self::OPTION_NAME, false ) ) {
			add_option(
				self::OPTION_NAME,
				array(
					'enable_frontend' => 1,
				)
			);
		}
	}

	/**
	 * Wire WordPress hooks.
	 */
	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_print_footer_scripts', array( $this, 'print_settings' ), 5 );
		add_action( 'admin_print_footer_scripts', array( $this, 'print_settings' ), 5 );
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( ECHO_UI_TOASTS_FILE ), array( $this, 'plugin_action_links' ) );
		add_action( 'admin_page_access_denied', array( $this, 'redirect_legacy_settings_page' ) );
		add_action( 'admin_notices', array( $this, 'start_admin_notice_capture' ), -9999 );
		add_action( 'admin_notices', array( $this, 'finish_admin_notice_capture' ), 9999 );
		add_action( 'all_admin_notices', array( $this, 'start_admin_notice_capture' ), -9999 );
		add_action( 'all_admin_notices', array( $this, 'finish_admin_notice_capture' ), 9999 );
		add_action( 'network_admin_notices', array( $this, 'start_admin_notice_capture' ), -9999 );
		add_action( 'network_admin_notices', array( $this, 'finish_admin_notice_capture' ), 9999 );
		add_action( 'user_admin_notices', array( $this, 'start_admin_notice_capture' ), -9999 );
		add_action( 'user_admin_notices', array( $this, 'finish_admin_notice_capture' ), 9999 );
		add_action( 'template_redirect', array( $this, 'queue_woocommerce_notices' ), 100 );
		add_action( 'wp_footer', array( $this, 'queue_woocommerce_notices' ), 1 );
		add_shortcode( 'echo_ui_toast', array( $this, 'shortcode' ) );
	}

	/**
	 * Register plugin assets.
	 */
	public function register_assets() {
		wp_register_style(
			self::STYLE_VENDOR,
			ECHO_UI_TOASTS_URL . 'assets/vendor/echo-ui/style.css',
			array(),
			ECHO_UI_TOASTS_VERSION
		);

		wp_register_style(
			self::STYLE_BRIDGE,
			ECHO_UI_TOASTS_URL . 'assets/css/echo-ui-wp.css',
			array( self::STYLE_VENDOR ),
			ECHO_UI_TOASTS_VERSION
		);

		wp_register_style(
			self::STYLE_FONT,
			ECHO_UI_TOASTS_URL . 'assets/vendor/figtree/figtree.css',
			array(),
			ECHO_UI_TOASTS_VERSION
		);

		wp_register_style(
			self::STYLE_ADMIN,
			ECHO_UI_TOASTS_URL . 'assets/css/echo-ui-admin.css',
			array( self::STYLE_FONT ),
			ECHO_UI_TOASTS_VERSION
		);

		wp_register_script(
			self::SCRIPT_VENDOR,
			ECHO_UI_TOASTS_URL . 'assets/vendor/echo-ui/echo.umd.js',
			array(),
			ECHO_UI_TOASTS_VERSION,
			true
		);

		wp_register_script(
			self::SCRIPT_BRIDGE,
			ECHO_UI_TOASTS_URL . 'assets/js/echo-ui-wp.js',
			array( self::SCRIPT_VENDOR ),
			ECHO_UI_TOASTS_VERSION,
			true
		);

		wp_register_script(
			self::SCRIPT_ADMIN,
			ECHO_UI_TOASTS_URL . 'assets/js/echo-ui-admin.js',
			array(),
			ECHO_UI_TOASTS_VERSION,
			true
		);
	}

	/**
	 * Enqueue assets in WP Admin for users who can receive admin toasts.
	 */
	public function enqueue_admin_assets() {
		if ( $this->is_settings_screen() ) {
			if ( ! wp_style_is( self::STYLE_ADMIN, 'registered' ) ) {
				$this->register_assets();
			}

			wp_enqueue_style( self::STYLE_ADMIN );
			wp_enqueue_script( self::SCRIPT_ADMIN );
			wp_add_inline_script(
				self::SCRIPT_ADMIN,
				'window.echoUiAdmin = ' . wp_json_encode( $this->admin_script_data() ) . ';',
				'before'
			);
		}

		if ( $this->admin_enabled() && $this->can_show_admin_toasts() ) {
			$this->enqueue_assets( 'admin' );
		}
	}

	/**
	 * Enqueue assets on the public frontend when enabled.
	 */
	public function enqueue_frontend_assets() {
		if ( $this->frontend_enabled() ) {
			$this->enqueue_assets( 'frontend' );
		}
	}

	/**
	 * Enqueue Echo UI assets.
	 *
	 * @param string $context Surface context.
	 * @return bool Whether assets were enqueued.
	 */
	public function enqueue_assets( $context = 'auto' ) {
		$context = $this->resolve_context( $context );

		if ( 'admin' === $context && ! $this->can_show_admin_toasts() ) {
			return false;
		}

		if ( 'admin' === $context && ! $this->admin_enabled() ) {
			return false;
		}

		if ( 'frontend' === $context && ! $this->frontend_enabled() ) {
			return false;
		}

		if ( ! wp_style_is( self::STYLE_VENDOR, 'registered' ) || ! wp_script_is( self::SCRIPT_BRIDGE, 'registered' ) ) {
			$this->register_assets();
		}

		wp_enqueue_style( self::STYLE_VENDOR );
		wp_enqueue_style( self::STYLE_BRIDGE );
		wp_enqueue_script( self::SCRIPT_VENDOR );
		wp_enqueue_script( self::SCRIPT_BRIDGE );

		return true;
	}

	/**
	 * Queue a toast for display on the current request.
	 *
	 * @param string $type    Toast type.
	 * @param string $title   Toast title.
	 * @param array  $options Echo UI options.
	 * @return string Toast id.
	 */
	public function add_toast( $type, $title, $options = array() ) {
		$toast = $this->normalize_toast( $type, $title, $options );

		if ( ! $this->can_show_context( $toast['context'] ) ) {
			return '';
		}

		$this->toasts[] = $toast;
		$this->enqueue_assets( $toast['context'] );

		return $toast['id'];
	}

	/**
	 * Shortcode handler.
	 *
	 * Usage:
	 * [echo_ui_toast type="success" title="Saved" description="Profile updated"]
	 * [echo_ui_toast label="Show toast" type="info" title="Heads up"]
	 *
	 * @param array<string,string> $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'type'        => 'info',
				'title'       => '',
				'message'     => '',
				'description' => '',
				'duration'    => '',
				'position'    => '',
				'label'       => '',
				'class'       => '',
			),
			$atts,
			'echo_ui_toast'
		);

		$title       = $atts['title'] ? $atts['title'] : $atts['message'];
		$description = $atts['description'];
		$options     = array(
			'description' => $description,
			'duration'    => $atts['duration'],
			'position'    => $atts['position'],
			'context'     => is_admin() ? 'admin' : 'frontend',
		);

		if ( '' === trim( (string) $title ) ) {
			return '';
		}

		if ( ! $this->enqueue_assets( $options['context'] ) ) {
			return '';
		}

		if ( '' === trim( (string) $atts['label'] ) ) {
			$this->add_toast( $atts['type'], $title, $options );
			return '';
		}

		$toast = $this->normalize_toast( $atts['type'], $title, $options );
		$class = trim( 'echo-ui-toast-button ' . sanitize_html_class( $atts['class'] ) );

		return sprintf(
			'<button type="button" class="%1$s" data-echo-ui-toast="%2$s">%3$s</button>',
			esc_attr( $class ),
			esc_attr( wp_json_encode( $toast ) ),
			esc_html( $atts['label'] )
		);
	}

	/**
	 * Inject settings before the bridge script prints.
	 */
	public function print_settings() {
		if ( $this->settings_printed ) {
			return;
		}

		if ( ! wp_script_is( self::SCRIPT_BRIDGE, 'enqueued' ) && empty( $this->toasts ) ) {
			return;
		}

		$this->enqueue_assets();

		$settings = array(
			'options'   => $this->get_echo_config(),
			'toasts'    => $this->toasts,
			'context'   => is_admin() ? 'admin' : 'frontend',
			'wooBlocks' => ! is_admin() && $this->woocommerce_toasts_enabled(),
		);

		wp_add_inline_script(
			self::SCRIPT_BRIDGE,
			'window.echoUiToastrSettings = ' . wp_json_encode( $settings ) . ';',
			'before'
		);

		$this->settings_printed = true;
	}

	/**
	 * Add settings screen and theme classes to the admin body.
	 *
	 * @param string $classes Space-separated body classes.
	 * @return string
	 */
	public function admin_body_class( $classes ) {
		if ( ! $this->is_settings_screen() ) {
			return $classes;
		}

		$options = $this->get_options();

		return $classes . ' echo-ui-settings-screen echo-ui-theme-' . sanitize_html_class( $options['settings_theme'] ) . ' ';
	}

	/**
	 * URL of the Notification Center settings page.
	 *
	 * @return string
	 */
	public static function settings_url() {
		return admin_url( 'admin.php?page=' . self::SETTINGS_SLUG );
	}

	/**
	 * Add a Settings link to the plugin's row on the Plugins screen.
	 *
	 * @param string[] $links Action links.
	 * @return string[]
	 */
	public function plugin_action_links( $links ) {
		if ( current_user_can( 'manage_options' ) ) {
			array_unshift( $links, sprintf( '<a href="%1$s">%2$s</a>', esc_url( self::settings_url() ), esc_html__( 'Settings', 'echo-ui-toasts' ) ) );
		}

		return $links;
	}

	/**
	 * Send the retired Settings > Echo UI Toasts URL to the Notification Center.
	 *
	 * WordPress denies access to unregistered pages before admin_init, so this runs on
	 * admin_page_access_denied.
	 */
	public function redirect_legacy_settings_page() {
		global $pagenow;

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'options-general.php' === $pagenow && 'echo-ui-toasts' === $page && current_user_can( 'manage_options' ) ) {
			wp_safe_redirect( self::settings_url() );
			exit;
		}
	}

	/**
	 * Add the settings page.
	 */
	public function add_settings_page() {
		add_menu_page(
			__( 'Echo UI Toasts', 'echo-ui-toasts' ),
			__( 'Echo UI', 'echo-ui-toasts' ),
			'manage_options',
			self::SETTINGS_SLUG,
			array( $this, 'render_settings_page' ),
			'dashicons-megaphone',
			80
		);
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings() {
		register_setting(
			'echo_ui_toasts',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_options' ),
				'default'           => $this->default_options(),
			)
		);
	}

	/**
	 * Get default option values.
	 *
	 * @return array<string,mixed>
	 */
	private function default_options() {
		return array(
			'enable_admin'      => 1,
			'enable_frontend'   => 1,
			'position'          => 'top-right',
			'max'               => 4,
			'enter'             => 'slide',
			'exit'              => 'fade',
			'motion_speed'      => 'normal',
			'duration_success'  => 4000,
			'duration_info'     => 4000,
			'duration_warning'  => 6000,
			'duration_error'    => 7000,
			'duration_loading'  => 0,
			'sound_enabled'     => 1,
			'sound_volume'      => 0.09,
			'sound_types'       => array( 'success', 'error', 'warning', 'info' ),
			'history_enabled'   => 1,
			'history_limit'     => 20,
			'history_types'     => array( 'error', 'warning' ),
			'settings_theme'    => 'dark',
			'woo_notices'       => 1,
			'admin_notices'     => 1,
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array<string,mixed> $options Raw options.
	 * @return array<string,int>
	 */
	public function sanitize_options( $options ) {
		$options  = is_array( $options ) ? $options : array();
		$defaults = $this->default_options();
		$pick     = function ( $key, $allowed ) use ( $options, $defaults ) {
			return in_array( $options[ $key ] ?? '', $allowed, true ) ? $options[ $key ] : $defaults[ $key ];
		};

		return array(
			'enable_admin'     => empty( $options['enable_admin'] ) ? 0 : 1,
			'enable_frontend'  => empty( $options['enable_frontend'] ) ? 0 : 1,
			'position'         => $pick( 'position', array_keys( $this->positions() ) ),
			'max'              => min( 10, max( 1, absint( $options['max'] ?? $defaults['max'] ) ) ),
			'enter'            => $pick( 'enter', array_keys( $this->enter_animations() ) ),
			'exit'             => $pick( 'exit', array_keys( $this->exit_animations() ) ),
			'motion_speed'     => $pick( 'motion_speed', array_keys( $this->motion_speeds() ) ),
			'duration_success' => min( 60000, max( 0, absint( $options['duration_success'] ?? $defaults['duration_success'] ) ) ),
			'duration_info'    => min( 60000, max( 0, absint( $options['duration_info'] ?? $defaults['duration_info'] ) ) ),
			'duration_warning' => min( 60000, max( 0, absint( $options['duration_warning'] ?? $defaults['duration_warning'] ) ) ),
			'duration_error'   => min( 60000, max( 0, absint( $options['duration_error'] ?? $defaults['duration_error'] ) ) ),
			'duration_loading' => min( 60000, max( 0, absint( $options['duration_loading'] ?? $defaults['duration_loading'] ) ) ),
			'sound_enabled'    => empty( $options['sound_enabled'] ) ? 0 : 1,
			'sound_volume'     => min( 0.2, max( 0, (float) ( $options['sound_volume'] ?? $defaults['sound_volume'] ) ) ),
			'sound_types'      => $this->sanitize_types( $options['sound_types'] ?? array() ),
			'history_enabled'  => empty( $options['history_enabled'] ) ? 0 : 1,
			'history_limit'    => min( 100, max( 1, absint( $options['history_limit'] ?? $defaults['history_limit'] ) ) ),
			'history_types'    => $this->sanitize_types( $options['history_types'] ?? array() ),
			'settings_theme'   => $pick( 'settings_theme', array( 'auto', 'dark', 'light' ) ),
			'woo_notices'      => empty( $options['woo_notices'] ) ? 0 : 1,
			'admin_notices'    => empty( $options['admin_notices'] ) ? 0 : 1,
		);
	}

	/**
	 * Keep only known toast types, in canonical order. An empty list is allowed.
	 *
	 * @param mixed $types Raw list.
	 * @return string[]
	 */
	private function sanitize_types( $types ) {
		$types = is_array( $types ) ? array_map( 'sanitize_key', $types ) : array();

		return array_values( array_intersect( array_keys( $this->toast_types() ), $types ) );
	}

	/**
	 * Render the Notification Center settings page.
	 *
	 * Every control is a real form field posted to options.php. The admin script keeps
	 * the live preview, summary pills, and save bar in sync with those fields.
	 */
	public function render_settings_page() {
		$options = $this->get_options();
		$types   = $this->toast_types();

		// The Settings API only prints "Settings saved." on options-general.php pages, not top-level ones.
		if ( $this->settings_just_saved() ) {
			$this->add_toast(
				'success',
				__( 'Settings saved', 'echo-ui-toasts' ),
				array(
					'description' => __( 'Echo UI defaults are now live.', 'echo-ui-toasts' ),
					'context'     => 'admin',
				)
			);
		}
		?>
		<div class="wrap echo-ui-nc">
			<form id="echo-ui-settings-form" method="post" action="options.php" novalidate>
				<?php settings_fields( 'echo_ui_toasts' ); ?>

				<header class="nc-top">
					<div>
						<div class="nc-brand"><?php esc_html_e( 'Onkyer Studio Labs · Echo UI', 'echo-ui-toasts' ); ?></div>
						<h1><?php esc_html_e( 'Notification Center', 'echo-ui-toasts' ); ?></h1>
						<p class="nc-sub"><?php esc_html_e( 'Choose where toasts appear, how long they stay, and what visitors can look back on. The preview updates as you change settings.', 'echo-ui-toasts' ); ?></p>
					</div>
					<div class="nc-side">
						<div class="nc-sum" id="nc-sum"></div>
						<?php $this->render_theme_switch( $options ); ?>
					</div>
				</header>
				<hr class="wp-header-end">

				<div class="nc-grid">
					<main>
						<section class="nc-card">
							<h2><?php esc_html_e( 'Where toasts show', 'echo-ui-toasts' ); ?></h2>
							<p class="nc-d"><?php esc_html_e( 'Turn notifications on or off for each side of your site.', 'echo-ui-toasts' ); ?></p>
							<?php
							$this->render_switch_row( 'enable_admin', __( 'WP admin dashboard', 'echo-ui-toasts' ), __( 'Operational notices for editors and administrators.', 'echo-ui-toasts' ), $options );
							$this->render_switch_row( 'admin_notices', __( 'Admin notices as toasts', 'echo-ui-toasts' ), __( 'Also show WordPress status messages, like "Settings saved", as toasts. Promotional banners are left alone.', 'echo-ui-toasts' ), $options );
							$this->render_switch_row( 'enable_frontend', __( 'Public frontend', 'echo-ui-toasts' ), __( 'Visitor-safe notices for visitors and logged-in users.', 'echo-ui-toasts' ), $options );

							if ( class_exists( 'WooCommerce' ) ) {
								$this->render_switch_row( 'woo_notices', __( 'WooCommerce notices as toasts', 'echo-ui-toasts' ), __( 'Show cart, checkout, and account messages as toasts instead of inline banners. Links like "View cart" become a toast button.', 'echo-ui-toasts' ), $options );
							} else {
								// Keep the stored value when WooCommerce is inactive so saving does not switch it off.
								printf( '<input type="hidden" name="%1$s" value="%2$d">', esc_attr( $this->field_name( 'woo_notices' ) ), (int) $options['woo_notices'] );
							}
							?>
						</section>

						<section class="nc-card">
							<h2><?php esc_html_e( 'Position and motion', 'echo-ui-toasts' ); ?></h2>
							<p class="nc-d"><?php esc_html_e( 'Defaults for every toast. Individual toasts can override them.', 'echo-ui-toasts' ); ?></p>
							<span class="nc-lab" id="nc-pos-label"><?php esc_html_e( 'Screen position', 'echo-ui-toasts' ); ?></span>
							<div class="nc-pos" id="nc-pos" role="group" aria-labelledby="nc-pos-label">
								<?php foreach ( $this->positions() as $value => $label ) : ?>
									<button type="button" data-p="<?php echo esc_attr( $value ); ?>" aria-label="<?php echo esc_attr( $label ); ?>" aria-pressed="<?php echo $value === $options['position'] ? 'true' : 'false'; ?>"></button>
								<?php endforeach; ?>
							</div>
							<input type="hidden" name="<?php echo esc_attr( $this->field_name( 'position' ) ); ?>" value="<?php echo esc_attr( $options['position'] ); ?>" data-k="position">

							<div class="nc-two" style="margin-top:18px">
								<div>
									<label class="nc-lab" for="nc-enter"><?php esc_html_e( 'Enter animation', 'echo-ui-toasts' ); ?></label>
									<?php $this->render_select( 'enter', $this->enter_animations(), $options ); ?>
								</div>
								<div>
									<label class="nc-lab" for="nc-exit"><?php esc_html_e( 'Exit animation', 'echo-ui-toasts' ); ?></label>
									<?php $this->render_select( 'exit', $this->exit_animations(), $options ); ?>
								</div>
								<div>
									<label class="nc-lab" for="nc-motion_speed"><?php esc_html_e( 'Animation speed', 'echo-ui-toasts' ); ?></label>
									<?php $this->render_select( 'motion_speed', wp_list_pluck( $this->motion_speeds(), 'label' ), $options ); ?>
								</div>
							</div>

							<div style="margin-top:18px">
								<span class="nc-lab"><?php esc_html_e( 'Maximum visible toasts', 'echo-ui-toasts' ); ?></span>
								<?php $this->render_stepper( 'max', 1, 10, 1, __( 'Maximum visible toasts', 'echo-ui-toasts' ), $options ); ?>
								<div class="nc-hint" style="margin-top:6px"><?php esc_html_e( 'Extra toasts wait in a queue until there is room.', 'echo-ui-toasts' ); ?></div>
							</div>
						</section>

						<section class="nc-card">
							<h2><?php esc_html_e( 'How long each type stays', 'echo-ui-toasts' ); ?></h2>
							<p class="nc-d"><?php esc_html_e( 'Set time in milliseconds. Enter 0 to keep a toast open until it is dismissed.', 'echo-ui-toasts' ); ?></p>
							<div>
								<?php foreach ( $types as $type => $label ) : ?>
									<div class="nc-dur" style="--c:var(--nc-<?php echo esc_attr( $type ); ?>)">
										<div class="nc-t"><span class="nc-dot"></span><?php echo esc_html( $label ); ?></div>
										<div>
											<div class="nc-msbox">
												<?php /* translators: %s: toast type label. */ ?>
												<input type="number" min="0" max="60000" step="500" name="<?php echo esc_attr( $this->field_name( 'duration_' . $type ) ); ?>" value="<?php echo esc_attr( $options[ 'duration_' . $type ] ); ?>" data-k="<?php echo esc_attr( 'duration_' . $type ); ?>" aria-label="<?php echo esc_attr( sprintf( __( '%s duration', 'echo-ui-toasts' ), $label ) ); ?>">
												<em>ms</em>
											</div>
										</div>
										<div class="nc-hint" data-h="<?php echo esc_attr( 'duration_' . $type ); ?>"></div>
									</div>
								<?php endforeach; ?>
							</div>
						</section>

						<section class="nc-card">
							<h2><?php esc_html_e( 'Sound and history', 'echo-ui-toasts' ); ?></h2>
							<p class="nc-d"><?php esc_html_e( 'Add subtle audio feedback and let people reopen toasts they missed.', 'echo-ui-toasts' ); ?></p>
							<?php $this->render_switch_row( 'sound_enabled', __( 'Play notification sounds', 'echo-ui-toasts' ), __( 'A short, quiet tone when a toast appears.', 'echo-ui-toasts' ), $options ); ?>
							<div id="nc-volwrap" style="padding:4px 0 14px">
								<label class="nc-lab" for="nc-vol"><?php esc_html_e( 'Volume', 'echo-ui-toasts' ); ?> <span class="nc-hint" id="nc-volv"></span></label>
								<div style="display:flex;gap:12px;align-items:center">
									<input type="range" id="nc-vol" min="0" max="0.2" step="0.01" name="<?php echo esc_attr( $this->field_name( 'sound_volume' ) ); ?>" value="<?php echo esc_attr( $options['sound_volume'] ); ?>" data-k="sound_volume">
									<button class="nc-btn" id="nc-tsnd" type="button"><?php esc_html_e( 'Test', 'echo-ui-toasts' ); ?></button>
								</div>
								<div class="nc-hint"><?php esc_html_e( 'Range 0 to 0.2. Kept low on purpose.', 'echo-ui-toasts' ); ?></div>
								<span class="nc-lab" style="margin-top:14px"><?php esc_html_e( 'Types that play a sound', 'echo-ui-toasts' ); ?></span>
								<?php $this->render_type_chips( 'sound_types', $options ); ?>
							</div>
							<?php $this->render_switch_row( 'history_enabled', __( 'Toast history', 'echo-ui-toasts' ), __( 'Let users reopen recent important toasts.', 'echo-ui-toasts' ), $options ); ?>
							<div id="nc-hwrap">
								<div style="margin:4px 0 16px">
									<span class="nc-lab"><?php esc_html_e( 'History limit', 'echo-ui-toasts' ); ?></span>
									<?php $this->render_stepper( 'history_limit', 1, 100, 5, __( 'History limit', 'echo-ui-toasts' ), $options ); ?>
									<div class="nc-hint" style="margin-top:6px"><?php esc_html_e( 'Maximum stored items per session.', 'echo-ui-toasts' ); ?></div>
								</div>
								<span class="nc-lab"><?php esc_html_e( 'Types saved to history', 'echo-ui-toasts' ); ?></span>
								<?php $this->render_type_chips( 'history_types', $options ); ?>
							</div>
						</section>

						<section class="nc-card">
							<h2><?php esc_html_e( 'Developer reference', 'echo-ui-toasts' ); ?></h2>
							<p class="nc-d"><?php esc_html_e( 'AJAX and REST responses can include a toast object. Use the frontend context for visitor-facing messages.', 'echo-ui-toasts' ); ?></p>
							<div class="nc-chips" id="nc-ctx">
								<button type="button" class="nc-chip" data-cx="frontend" style="--c:var(--nc-brand)" aria-pressed="true"><i></i><?php esc_html_e( 'Frontend', 'echo-ui-toasts' ); ?></button>
								<button type="button" class="nc-chip" data-cx="admin" style="--c:var(--nc-brand)" aria-pressed="false"><i></i><?php esc_html_e( 'Admin', 'echo-ui-toasts' ); ?></button>
							</div>
							<pre id="nc-code"></pre>
							<button class="nc-btn" id="nc-copy" type="button" style="margin-top:10px"><?php esc_html_e( 'Copy snippet', 'echo-ui-toasts' ); ?></button>
						</section>
					</main>

					<aside class="nc-stagecol">
						<div class="nc-stagehead">
							<b><?php esc_html_e( 'Live preview', 'echo-ui-toasts' ); ?></b>
							<div style="display:flex;gap:6px">
								<button class="nc-btn" id="nc-stack" type="button"><?php esc_html_e( 'Test stack', 'echo-ui-toasts' ); ?></button>
								<button class="nc-btn nc-pri" id="nc-play" type="button" style="padding:7px 14px"><?php esc_html_e( 'Play toast', 'echo-ui-toasts' ); ?></button>
							</div>
						</div>
						<div class="nc-frame">
							<div class="nc-bar"><i></i><i></i><i></i></div>
							<div class="nc-vp" id="nc-vp">
								<div class="nc-skel" style="width:46%;height:16px"></div>
								<div class="nc-skel" style="width:90%"></div>
								<div class="nc-skel" style="width:78%"></div>
								<div class="nc-skel" style="width:84%"></div>
								<div class="nc-skel" style="width:60%"></div>
								<div class="nc-tc" id="nc-tc" aria-live="polite"></div>
							</div>
							<div class="nc-queue"><span id="nc-qinfo"></span><span><?php esc_html_e( 'Preview uses your real timings', 'echo-ui-toasts' ); ?></span></div>
						</div>
						<div class="nc-tabs" id="nc-ptypes" role="group" aria-label="<?php esc_attr_e( 'Preview type', 'echo-ui-toasts' ); ?>">
							<?php foreach ( $types as $type => $label ) : ?>
								<button type="button" class="nc-chip" data-pt="<?php echo esc_attr( $type ); ?>" style="--c:var(--nc-<?php echo esc_attr( $type ); ?>)" aria-pressed="<?php echo 'success' === $type ? 'true' : 'false'; ?>"><i></i><?php echo esc_html( $label ); ?></button>
							<?php endforeach; ?>
						</div>
					</aside>
				</div>

				<div class="nc-savebar" id="nc-savebar">
					<div>
						<span class="nc-status"><i></i><span id="nc-stxt"><?php esc_html_e( 'All changes saved', 'echo-ui-toasts' ); ?></span></span>
						<span style="display:flex;gap:8px">
							<button class="nc-btn" id="nc-reset" type="button"><?php esc_html_e( 'Reset to defaults', 'echo-ui-toasts' ); ?></button>
							<button class="nc-btn nc-pri" id="nc-save" type="submit"><?php esc_html_e( 'Save Echo UI settings', 'echo-ui-toasts' ); ?></button>
						</span>
					</div>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the settings page theme switcher.
	 *
	 * @param array<string,mixed> $options Current options.
	 */
	private function render_theme_switch( $options ) {
		$themes = array(
			'auto'  => __( 'Auto', 'echo-ui-toasts' ),
			'dark'  => __( 'Dark', 'echo-ui-toasts' ),
			'light' => __( 'Light', 'echo-ui-toasts' ),
		);
		?>
		<div class="nc-seg" role="radiogroup" aria-label="<?php esc_attr_e( 'Settings page theme', 'echo-ui-toasts' ); ?>">
			<?php foreach ( $themes as $value => $label ) : ?>
				<label>
					<input type="radio" name="<?php echo esc_attr( $this->field_name( 'settings_theme' ) ); ?>" value="<?php echo esc_attr( $value ); ?>" data-k="settings_theme" <?php checked( $value, $options['settings_theme'] ); ?>>
					<span><?php echo esc_html( $label ); ?></span>
				</label>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render a labelled on/off switch row.
	 *
	 * @param string              $key     Option key.
	 * @param string              $title   Row title.
	 * @param string              $copy    Row description.
	 * @param array<string,mixed> $options Current options.
	 */
	private function render_switch_row( $key, $title, $copy, $options ) {
		?>
		<div class="nc-row">
			<div>
				<div class="nc-t"><?php echo esc_html( $title ); ?></div>
				<div class="nc-s"><?php echo esc_html( $copy ); ?></div>
			</div>
			<label class="nc-sw">
				<input type="checkbox" name="<?php echo esc_attr( $this->field_name( $key ) ); ?>" value="1" data-k="<?php echo esc_attr( $key ); ?>" aria-label="<?php echo esc_attr( $title ); ?>" <?php checked( 1, (int) $options[ $key ] ); ?>>
				<span></span>
			</label>
		</div>
		<?php
	}

	/**
	 * Render toggle chips for a list-of-types option, mirrored into hidden inputs for options.php.
	 *
	 * @param string              $key     Option key (history_types or sound_types).
	 * @param array<string,mixed> $options Current options.
	 */
	private function render_type_chips( $key, $options ) {
		$selected = (array) $options[ $key ];
		?>
		<div class="nc-chips">
			<?php foreach ( $this->toast_types() as $type => $label ) : ?>
				<button type="button" class="nc-chip" data-list="<?php echo esc_attr( $key ); ?>" data-type="<?php echo esc_attr( $type ); ?>" style="--c:var(--nc-<?php echo esc_attr( $type ); ?>)" aria-pressed="<?php echo in_array( $type, $selected, true ) ? 'true' : 'false'; ?>"><i></i><?php echo esc_html( $label ); ?></button>
			<?php endforeach; ?>
		</div>
		<span data-list-inputs="<?php echo esc_attr( $key ); ?>">
			<?php foreach ( $selected as $type ) : ?>
				<input type="hidden" name="<?php echo esc_attr( $this->field_name( $key ) ); ?>[]" value="<?php echo esc_attr( $type ); ?>">
			<?php endforeach; ?>
		</span>
		<?php
	}

	/**
	 * Render a native select bound to an option.
	 *
	 * @param string               $key     Option key.
	 * @param array<string,string> $choices Value => label.
	 * @param array<string,mixed>  $options Current options.
	 */
	private function render_select( $key, $choices, $options ) {
		?>
		<select id="<?php echo esc_attr( 'nc-' . $key ); ?>" name="<?php echo esc_attr( $this->field_name( $key ) ); ?>" data-k="<?php echo esc_attr( $key ); ?>">
			<?php foreach ( $choices as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $options[ $key ] ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Render a number stepper bound to an option.
	 *
	 * @param string              $key     Option key.
	 * @param int                 $min     Minimum value.
	 * @param int                 $max     Maximum value.
	 * @param int                 $step    Button step.
	 * @param string              $label   Accessible label.
	 * @param array<string,mixed> $options Current options.
	 */
	private function render_stepper( $key, $min, $max, $step, $label, $options ) {
		?>
		<div class="nc-stepper">
			<button type="button" data-step="<?php echo esc_attr( -$step ); ?>" data-for="<?php echo esc_attr( $key ); ?>" aria-label="<?php esc_attr_e( 'Decrease', 'echo-ui-toasts' ); ?>">−</button>
			<input type="number" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" name="<?php echo esc_attr( $this->field_name( $key ) ); ?>" value="<?php echo esc_attr( $options[ $key ] ); ?>" data-k="<?php echo esc_attr( $key ); ?>" aria-label="<?php echo esc_attr( $label ); ?>">
			<button type="button" data-step="<?php echo esc_attr( $step ); ?>" data-for="<?php echo esc_attr( $key ); ?>" aria-label="<?php esc_attr_e( 'Increase', 'echo-ui-toasts' ); ?>">+</button>
		</div>
		<?php
	}

	/**
	 * Form field name for an option key.
	 *
	 * @param string $key Option key.
	 * @return string
	 */
	private function field_name( $key ) {
		return self::OPTION_NAME . '[' . $key . ']';
	}

	/**
	 * Whether the settings form was just saved.
	 *
	 * @return bool
	 */
	private function settings_just_saved() {
		return isset( $_GET['settings-updated'] ) && 'true' === sanitize_key( wp_unslash( $_GET['settings-updated'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Data for the settings page script.
	 *
	 * @return array<string,mixed>
	 */
	private function admin_script_data() {
		return array(
			'saved'     => $this->admin_state( $this->get_options() ),
			'defaults'  => $this->admin_state( $this->default_options() ),
			'justSaved' => $this->settings_just_saved(),
			'types'     => array_keys( $this->toast_types() ),
			'speeds'    => $this->motion_speeds(),
			'i18n'      => array(
				'admin'      => __( 'Admin', 'echo-ui-toasts' ),
				'frontend'   => __( 'Frontend', 'echo-ui-toasts' ),
				'on'         => __( 'On', 'echo-ui-toasts' ),
				'off'        => __( 'Off', 'echo-ui-toasts' ),
				'stack'      => __( 'Stack', 'echo-ui-toasts' ),
				'positions'  => $this->positions(),
				'staysOpen'  => __( 'Stays until dismissed', 'echo-ui-toasts' ),
				/* translators: %s: duration in seconds, e.g. "4" or "1.5". */
				'seconds'    => __( '%s s', 'echo-ui-toasts' ),
				/* translators: %d: number of toasts on screen in the preview. */
				'visible'    => __( '%d visible', 'echo-ui-toasts' ),
				/* translators: 1: number of toasts on screen, 2: number of toasts waiting in the queue. */
				'queued'     => __( '%1$d visible · %2$d queued', 'echo-ui-toasts' ),
				'unsaved'    => __( 'You have unsaved changes', 'echo-ui-toasts' ),
				'allSaved'   => __( 'All changes saved', 'echo-ui-toasts' ),
				'copy'       => __( 'Copy snippet', 'echo-ui-toasts' ),
				'copied'     => __( 'Copied', 'echo-ui-toasts' ),
				'copyFailed' => __( 'Copy failed', 'echo-ui-toasts' ),
				'dismiss'    => __( 'Dismiss', 'echo-ui-toasts' ),
				'savedToast' => array( __( 'Settings saved', 'echo-ui-toasts' ), __( 'Echo UI defaults are now live.', 'echo-ui-toasts' ) ),
				'messages'   => array(
					'success' => array( __( 'Saved', 'echo-ui-toasts' ), __( 'Your changes are live.', 'echo-ui-toasts' ) ),
					'error'   => array( __( 'Could not save', 'echo-ui-toasts' ), __( 'Check the form and try again.', 'echo-ui-toasts' ) ),
					'warning' => array( __( 'Low stock', 'echo-ui-toasts' ), __( 'Only 3 items left.', 'echo-ui-toasts' ) ),
					'info'    => array( __( 'Order status updated', 'echo-ui-toasts' ), __( 'A concise message appears here.', 'echo-ui-toasts' ) ),
					'loading' => array( __( 'Uploading files', 'echo-ui-toasts' ), __( 'This stays until it finishes.', 'echo-ui-toasts' ) ),
				),
			),
		);
	}

	/**
	 * Typed option values for the settings page script.
	 *
	 * @param array<string,mixed> $options Option values.
	 * @return array<string,mixed>
	 */
	private function admin_state( $options ) {
		$state = array();

		foreach ( $this->default_options() as $key => $default ) {
			$value = $options[ $key ] ?? $default;

			if ( in_array( $key, array( 'enable_admin', 'enable_frontend', 'sound_enabled', 'history_enabled', 'woo_notices', 'admin_notices' ), true ) ) {
				$state[ $key ] = ! empty( $value );
			} elseif ( 'sound_volume' === $key ) {
				$state[ $key ] = round( (float) $value, 2 );
			} elseif ( is_int( $default ) ) {
				$state[ $key ] = (int) $value;
			} else {
				$state[ $key ] = $value;
			}
		}

		$state['history_types'] = $this->sanitize_types( $state['history_types'] );
		$state['sound_types']   = $this->sanitize_types( $state['sound_types'] );

		return $state;
	}

	/**
	 * Toast types and labels.
	 *
	 * @return array<string,string>
	 */
	private function toast_types() {
		return array(
			'success' => __( 'Success', 'echo-ui-toasts' ),
			'error'   => __( 'Error', 'echo-ui-toasts' ),
			'warning' => __( 'Warning', 'echo-ui-toasts' ),
			'info'    => __( 'Info', 'echo-ui-toasts' ),
			'loading' => __( 'Loading', 'echo-ui-toasts' ),
		);
	}

	/**
	 * Screen positions in grid order.
	 *
	 * @return array<string,string>
	 */
	private function positions() {
		return array(
			'top-left'      => __( 'Top left', 'echo-ui-toasts' ),
			'top-center'    => __( 'Top center', 'echo-ui-toasts' ),
			'top-right'     => __( 'Top right', 'echo-ui-toasts' ),
			'bottom-left'   => __( 'Bottom left', 'echo-ui-toasts' ),
			'bottom-center' => __( 'Bottom center', 'echo-ui-toasts' ),
			'bottom-right'  => __( 'Bottom right', 'echo-ui-toasts' ),
		);
	}

	/**
	 * Enter animations supported by Echo UI.
	 *
	 * @return array<string,string>
	 */
	private function enter_animations() {
		return array(
			'slide' => __( 'Slide', 'echo-ui-toasts' ),
			'fade'  => __( 'Fade', 'echo-ui-toasts' ),
			'pop'   => __( 'Pop', 'echo-ui-toasts' ),
			'drop'  => __( 'Drop', 'echo-ui-toasts' ),
		);
	}

	/**
	 * Animation speed presets, in milliseconds (Echo UI enterMs / exitMs).
	 *
	 * @return array<string,array{label:string,enter:int,exit:int}>
	 */
	private function motion_speeds() {
		return array(
			'fast'    => array(
				'label' => __( 'Fast', 'echo-ui-toasts' ),
				'enter' => 160,
				'exit'  => 120,
			),
			'normal'  => array(
				'label' => __( 'Normal', 'echo-ui-toasts' ),
				'enter' => 240,
				'exit'  => 180,
			),
			'relaxed' => array(
				'label' => __( 'Relaxed', 'echo-ui-toasts' ),
				'enter' => 380,
				'exit'  => 280,
			),
		);
	}

	/**
	 * Exit animations supported by Echo UI.
	 *
	 * @return array<string,string>
	 */
	private function exit_animations() {
		return array(
			'fade'  => __( 'Fade', 'echo-ui-toasts' ),
			'slide' => __( 'Slide', 'echo-ui-toasts' ),
			'pop'   => __( 'Pop', 'echo-ui-toasts' ),
		);
	}

	/**
	 * Start capturing admin notices so they can also be shown as toasts.
	 */
	public function start_admin_notice_capture() {
		if ( ! $this->can_show_admin_toasts() || ! $this->admin_notice_toasts_enabled() ) {
			return;
		}

		if ( null !== $this->notice_buffer_level ) {
			return;
		}

		$this->notice_buffer_level = ob_get_level();
		ob_start();
	}

	/**
	 * Finish capturing admin notices, preserve the original HTML, and queue toasts.
	 */
	public function finish_admin_notice_capture() {
		if ( null === $this->notice_buffer_level || ob_get_level() <= $this->notice_buffer_level ) {
			return;
		}

		$html = ob_get_clean();
		$this->notice_buffer_level = null;

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$this->queue_admin_notices_from_html( $html );
	}

	/**
	 * Convert pending WooCommerce notices into toasts and remove them from the page.
	 *
	 * Runs on template_redirect (after WooCommerce's form handlers queue notices, before
	 * templates print them) and again on wp_footer for notices added while rendering.
	 * WooCommerce clears notices once it prints them, so collecting them any later misses them.
	 */
	public function queue_woocommerce_notices() {
		if ( is_admin() || wp_doing_ajax() || ! $this->woocommerce_toasts_enabled() || ! function_exists( 'wc_get_notices' ) ) {
			return;
		}

		$notices = wc_get_notices();
		if ( empty( $notices ) || ! is_array( $notices ) ) {
			return;
		}

		foreach ( $notices as $type => $items ) {
			foreach ( (array) $items as $item ) {
				$html  = (string) ( is_array( $item ) && isset( $item['notice'] ) ? $item['notice'] : $item );
				$toast = $this->parse_notice_html( $html );

				if ( '' === $toast['title'] ) {
					continue;
				}

				$options = array(
					'context' => 'frontend',
					'silent'  => true,
				);

				if ( $toast['action'] ) {
					$options['action'] = $toast['action'];
				}

				$this->add_toast( $this->map_woocommerce_type( $type ), $toast['title'], $options );
			}
		}

		wc_clear_notices();
	}

	/**
	 * Split a notice (WooCommerce or WP admin) into plain text and an optional link action.
	 *
	 * "<a href=".../cart/" class="button wc-forward">View cart</a> Hoodie has been added to your cart."
	 * becomes title "Hoodie has been added to your cart." with a "View cart" action.
	 *
	 * @param string $html Notice HTML.
	 * @return array{title:string,action:array<string,string>|null}
	 */
	private function parse_notice_html( $html ) {
		$action = null;

		if ( preg_match( '/<a\s[^>]*href=(["\'])(.*?)\1[^>]*>(.*?)<\/a>/is', $html, $link ) ) {
			$url   = $this->absolute_url( html_entity_decode( $link[2], ENT_QUOTES, get_bloginfo( 'charset' ) ) );
			$label = trim( wp_strip_all_tags( $link[3] ) );

			if ( $url && '' !== $label ) {
				$action = array(
					'label' => $label,
					'url'   => $url,
				);
				$html   = str_replace( $link[0], ' ', $html );
			}
		}

		$title = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, get_bloginfo( 'charset' ) );

		return array(
			'title'  => trim( preg_replace( '/\s+/', ' ', $title ) ),
			'action' => $action,
		);
	}

	/**
	 * Resolve a notice link to an absolute http(s) URL, or '' if it cannot be used.
	 *
	 * Admin notices often link relatively ("post.php?post=12&action=edit").
	 *
	 * @param string $url Raw href.
	 * @return string
	 */
	private function absolute_url( $url ) {
		$url = trim( $url );

		if ( '' === $url || '#' === $url[0] ) {
			return '';
		}

		if ( ! wp_parse_url( $url, PHP_URL_SCHEME ) && 0 !== strpos( $url, '//' ) ) {
			if ( '/' === $url[0] ) {
				$home = wp_parse_url( home_url() );
				$url  = $home['scheme'] . '://' . $home['host'] . ( isset( $home['port'] ) ? ':' . $home['port'] : '' ) . $url;
			} else {
				$url = is_admin() ? admin_url( $url ) : home_url( '/' . $url );
			}
		}

		return esc_url_raw( $url, array( 'http', 'https' ) );
	}

	/**
	 * Default Echo UI config, filterable by themes/plugins.
	 *
	 * @return array<string,mixed>
	 */
	private function get_echo_config() {
		$options = $this->get_options();

		$config = array(
			'position' => $options['position'],
			'max'      => (int) $options['max'],
			'enter'    => $options['enter'],
			'exit'     => $options['exit'],
			'enterMs'  => $this->motion_speeds()[ $options['motion_speed'] ]['enter'] ?? 240,
			'exitMs'   => $this->motion_speeds()[ $options['motion_speed'] ]['exit'] ?? 180,
			'duration' => array(
				'success' => (int) $options['duration_success'],
				'info'    => (int) $options['duration_info'],
				'warning' => (int) $options['duration_warning'],
				'error'   => (int) $options['duration_error'],
				'loading' => (int) $options['duration_loading'],
			),
			'history'  => array(
				'enabled' => (bool) $options['history_enabled'],
				'limit'   => (int) $options['history_limit'],
				'types'   => $options['history_types'],
			),
			'sound'    => array(
				'enabled' => (bool) $options['sound_enabled'],
				'volume'  => (float) $options['sound_volume'],
				'types'   => array_values( (array) $options['sound_types'] ),
			),
		);

		/**
		 * Filters Echo UI frontend configuration.
		 *
		 * @param array<string,mixed> $config Echo UI config.
		 */
		return apply_filters( 'echo_ui_toastr_config', $config );
	}

	/**
	 * Get plugin options with defaults.
	 *
	 * @return array<string,int>
	 */
	private function get_options() {
		$options = get_option( self::OPTION_NAME, array() );
		$options = is_array( $options ) ? $options : array();

		return wp_parse_args(
			$options,
			$this->default_options()
		);
	}

	/**
	 * Whether admin notifications are enabled.
	 *
	 * @return bool
	 */
	private function admin_enabled() {
		$options = $this->get_options();
		return ! empty( $options['enable_admin'] );
	}

	/**
	 * Whether public frontend notifications are enabled.
	 *
	 * @return bool
	 */
	private function frontend_enabled() {
		$options = $this->get_options();
		return ! empty( $options['enable_frontend'] );
	}

	/**
	 * Whether WP admin notices should be repeated as toasts.
	 *
	 * @return bool
	 */
	private function admin_notice_toasts_enabled() {
		$options = $this->get_options();

		/**
		 * Filters whether admin notices are repeated as toasts.
		 *
		 * @param bool $enabled Whether the conversion runs on this request.
		 */
		return (bool) apply_filters( 'echo_ui_toasts_admin_notices', $this->admin_enabled() && ! empty( $options['admin_notices'] ) );
	}

	/**
	 * Whether WooCommerce notices should be shown as toasts.
	 *
	 * @return bool
	 */
	private function woocommerce_toasts_enabled() {
		$options = $this->get_options();

		/**
		 * Filters whether WooCommerce notices are converted into toasts.
		 *
		 * @param bool $enabled Whether the conversion runs on this request.
		 */
		return (bool) apply_filters( 'echo_ui_toasts_woocommerce_notices', $this->frontend_enabled() && ! empty( $options['woo_notices'] ) );
	}

	/**
	 * Whether the current admin page is an Echo UI settings page.
	 *
	 * @return bool
	 */
	private function is_settings_screen() {
		if ( ! is_admin() ) {
			return false;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return in_array( $page, array( self::SETTINGS_SLUG ), true );
	}

	/**
	 * Whether current user may receive admin toasts.
	 *
	 * @return bool
	 */
	private function can_show_admin_toasts() {
		return is_admin() && is_user_logged_in() && ( current_user_can( 'edit_posts' ) || current_user_can( 'manage_options' ) );
	}

	/**
	 * Normalize context into admin or frontend.
	 *
	 * @param string $context Requested context.
	 * @return string
	 */
	private function resolve_context( $context ) {
		$context = sanitize_key( (string) $context );

		if ( 'admin' === $context || 'frontend' === $context ) {
			return $context;
		}

		return is_admin() ? 'admin' : 'frontend';
	}

	/**
	 * Whether the current request may display a context.
	 *
	 * @param string $context Resolved context.
	 * @return bool
	 */
	private function can_show_context( $context ) {
		if ( 'admin' === $context ) {
			return $this->admin_enabled() && $this->can_show_admin_toasts();
		}

		if ( 'frontend' === $context ) {
			return ! is_admin() && $this->frontend_enabled();
		}

		return false;
	}

	/**
	 * Parse captured admin notices into toasts.
	 *
	 * The original notice HTML is always printed; this only adds toasts.
	 *
	 * @param string $html Captured admin notice HTML.
	 */
	private function queue_admin_notices_from_html( $html ) {
		if ( ! $this->admin_notice_toasts_enabled() ) {
			return;
		}

		foreach ( $this->extract_admin_notices( $html ) as $notice ) {
			$options = array(
				'context' => 'admin',
				'silent'  => true,
			);

			if ( $notice['action'] ) {
				$options['action'] = $notice['action'];
			}

			$this->add_toast( $notice['type'], $notice['title'], $options );
		}
	}

	/**
	 * Find toast-worthy admin notices in captured HTML.
	 *
	 * Only outermost elements carrying a real notice class (notice, updated, error) count,
	 * so nested markup inside a notice stays part of it. Hidden notices and promotional
	 * banners (forms, images, buttons) are skipped and remain inline only.
	 *
	 * @param string $html Captured admin notice HTML.
	 * @return array<int,array{type:string,title:string,action:array<string,string>|null}>
	 */
	private function extract_admin_notices( $html ) {
		if ( '' === trim( $html ) || ! class_exists( 'DOMDocument' ) ) {
			return array();
		}

		$doc      = new DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8" ?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		$xpath   = new DOMXPath( $doc );
		$notices = array();

		foreach ( $xpath->query( '//div[@class]' ) as $node ) {
			$classes = $this->class_list( $node );

			if ( ! $this->is_notice( $classes ) || $this->has_notice_ancestor( $node ) ) {
				continue;
			}

			$inner = '';
			foreach ( $node->childNodes as $child ) {
				$inner .= $doc->saveHTML( $child );
			}

			$type    = $this->map_admin_notice_type( $classes );
			$parsed  = $this->parse_notice_html( $inner );
			$capture = '' !== $parsed['title'] && ! in_array( 'hidden', $classes, true ) && ! $this->is_promotional_notice( $xpath, $node, $parsed['title'] );

			/**
			 * Filters whether an admin notice is repeated as a toast.
			 *
			 * @param bool   $capture Whether to show the notice as a toast.
			 * @param string $inner   Notice inner HTML.
			 * @param string $type    Toast type the notice maps to.
			 */
			if ( ! apply_filters( 'echo_ui_toasts_capture_admin_notice', $capture, $inner, $type ) ) {
				continue;
			}

			$notices[] = array(
				'type'   => $type,
				'title'  => $parsed['title'],
				'action' => $parsed['action'],
			);
		}

		return $notices;
	}

	/**
	 * Class tokens of a DOM element.
	 *
	 * @param DOMElement $node Element.
	 * @return string[]
	 */
	private function class_list( $node ) {
		return preg_split( '/\s+/', trim( (string) $node->getAttribute( 'class' ) ), -1, PREG_SPLIT_NO_EMPTY );
	}

	/**
	 * Whether class tokens mark a WordPress admin notice.
	 *
	 * @param string[] $classes Class tokens.
	 * @return bool
	 */
	private function is_notice( $classes ) {
		return (bool) array_intersect( array( 'notice', 'updated', 'error' ), $classes );
	}

	/**
	 * Whether an element sits inside another admin notice.
	 *
	 * @param DOMNode $node Element.
	 * @return bool
	 */
	private function has_notice_ancestor( $node ) {
		for ( $parent = $node->parentNode; $parent instanceof DOMElement; $parent = $parent->parentNode ) {
			if ( $this->is_notice( $this->class_list( $parent ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a notice looks like a banner or call to action rather than a status message.
	 *
	 * @param DOMXPath   $xpath XPath for the document.
	 * @param DOMElement $node  Notice element.
	 * @param string     $text  Notice plain text.
	 * @return bool
	 */
	private function is_promotional_notice( $xpath, $node, $text ) {
		$has_class = function ( $class ) {
			return "contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')";
		};
		$query     = './/form | .//input | .//select | .//textarea | .//iframe | .//img | .//svg | .//video'
			. ' | .//button[not(' . $has_class( 'notice-dismiss' ) . ')]'
			. ' | .//a[' . $has_class( 'button' ) . ']';

		return $xpath->query( $query, $node )->length > 0 || strlen( $text ) > 280;
	}

	/**
	 * Map admin notice classes to toast types.
	 *
	 * @param string[] $classes Notice class tokens.
	 * @return string
	 */
	private function map_admin_notice_type( $classes ) {
		if ( array_intersect( array( 'notice-error', 'error' ), $classes ) ) {
			return 'error';
		}

		if ( in_array( 'notice-warning', $classes, true ) ) {
			return 'warning';
		}

		if ( array_intersect( array( 'notice-success', 'updated' ), $classes ) ) {
			return 'success';
		}

		return 'info';
	}

	/**
	 * Map WooCommerce notice types to toast types.
	 *
	 * @param string $type WooCommerce notice type.
	 * @return string
	 */
	private function map_woocommerce_type( $type ) {
		if ( 'error' === $type ) {
			return 'error';
		}

		if ( 'success' === $type ) {
			return 'success';
		}

		return 'info';
	}

	/**
	 * Normalize a toast into frontend-safe data.
	 *
	 * @param string $type    Toast type.
	 * @param string $title   Toast title.
	 * @param array  $options Echo UI options.
	 * @return array<string,mixed>
	 */
	private function normalize_toast( $type, $title, $options ) {
		$options = is_array( $options ) ? $options : array();
		$type    = sanitize_key( $type );

		if ( ! in_array( $type, array( 'success', 'error', 'warning', 'info', 'loading' ), true ) ) {
			$type = 'info';
		}

		$toast = array(
			'id'    => 'echo-wp-' . wp_generate_uuid4(),
			'type'  => $type,
			'title' => wp_strip_all_tags( (string) $title ),
		);

		if ( ! empty( $options['description'] ) ) {
			$toast['description'] = wp_strip_all_tags( (string) $options['description'] );
		}

		if ( isset( $options['duration'] ) && '' !== $options['duration'] ) {
			$toast['duration'] = max( 0, absint( $options['duration'] ) );
		}

		foreach ( array( 'position', 'enter', 'exit', 'accent', 'icon' ) as $key ) {
			if ( ! empty( $options[ $key ] ) ) {
				$toast[ $key ] = sanitize_text_field( (string) $options[ $key ] );
			}
		}

		if ( isset( $options['silent'] ) ) {
			$toast['silent'] = (bool) $options['silent'];
		}

		if ( ! empty( $options['action']['label'] ) && ! empty( $options['action']['url'] ) ) {
			$url = esc_url_raw( (string) $options['action']['url'], array( 'http', 'https' ) );

			if ( $url ) {
				$toast['action'] = array(
					'label' => sanitize_text_field( (string) $options['action']['label'] ),
					'url'   => $url,
				);
			}
		}

		$toast['context'] = $this->resolve_context( isset( $options['context'] ) ? $options['context'] : 'auto' );

		return $toast;
	}
}
