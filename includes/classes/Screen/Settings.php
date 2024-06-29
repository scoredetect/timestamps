<?php
/**
 * Settings screen.
 *
 * @since 1.0.0
 * @package SDCOM_Timestamps
 */

namespace SDCOM_Timestamps\Screen;

use SDCOM_Timestamps\AdminNotices;
use SDCOM_Timestamps\Screen;
use SDCOM_Timestamps\Utils;

use function SDCOM_Timestamps\Utils\get_plugin_option;
use function SDCOM_Timestamps\Utils\is_authenticated;
use function SDCOM_Timestamps\Utils\is_woocommerce_active;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Settings screen Class.
 */
class Settings extends Screen {

	/**
	 * Setup actions and filters for all things settings.
	 *
	 * @since 1.0.0
	 */
	public function setup() {

		add_filter( 'plugin_action_links', [ $this, 'filter_plugin_action_links' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'action_admin_enqueue_scripts' ] );
		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_notices', [ $this, 'maybe_notice' ] );
		add_action( 'wp_ajax_timestamps_notice_dismiss', [ $this, 'action_wp_ajax_timestamps_notice_dismiss' ] );
		add_action( 'wp_ajax_timestamps_save_authentication_settings', [ $this, 'action_wp_ajax_timestamps_save_authentication_settings' ] );
		add_action( 'wp_ajax_timestamps_delete_authentication_settings', [ $this, 'action_wp_ajax_timestamps_delete_authentication_settings' ] );
	}

	/**
	 * Outputs the settings link in plugin actions.
	 *
	 * @param  array  $plugin_actions Array of HTML.
	 * @param  string $plugin_file Path to plugin file.
	 * @since 1.0.0
	 * @return array
	 */
	public function filter_plugin_action_links( $plugin_actions, $plugin_file ) {

		$url         = admin_url( 'options-general.php?page=' . $this->settings_page );
		$new_actions = [];

		if ( basename( SDCOM_TIMESTAMPS_PATH ) . '/timestamps.php' === $plugin_file ) {
			$new_actions['timestamps_settings'] = sprintf( '<a href="%s">%s</a>', esc_url( $url ), __( 'Settings', 'timestamps' ) );
		}

		return array_merge( $new_actions, $plugin_actions );
	}

	/**
	 * Add settings page.
	 *
	 * @since 1.0.0
	 */
	public function add_settings_page() {
		add_submenu_page(
			'options-general.php',
			__( 'Timestamps Settings', 'timestamps' ),
			__( 'Timestamps', 'timestamps' ),
			'manage_options',
			$this->settings_page,
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Register settings.
	 *
	 * @since 1.0.0
	 */
	public function register_settings() {

		$option = get_option( SDCOM_TIMESTAMPS_OPTIONS );

		register_setting(
			SDCOM_TIMESTAMPS_OPTIONS,
			SDCOM_TIMESTAMPS_OPTIONS,
			[
				'show_in_rest'  => [
					'schema' => [
						'type'       => 'object',
						'properties' => [
							// The timestamp options.
							'api_key'                    => [
								'type' => 'string',
							],
							'avatar_url'                 => [
								'type' => 'string',
							],
							'display_created_by'         => [
								'type' => 'string',
							],
							'username'                   => [
								'type' => 'string',
							],
							'default_timestamps_enabled' => [
								'type' => 'string',
							],
							'enable_timestamps_woocommerce_orders' => [
								'type' => 'string',
							],
							'delete_certificates_old_woocommerce_orders_age' => [
								'type' => 'string',
							],
							'woocommerce_order_statuses_marked_old_certificates' => [
								'type' => 'array',
							],
						],
					],
				],
				'auth_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			]
		);

		if ( is_authenticated() ) {
			add_settings_section(
				'sdcom_timestamps_certificate_settings_section',
				null,
				[ $this, 'certificate_settings_section' ],
				$this->settings_page
			);

			add_settings_field(
				'sdcom_timestamps_display_created_by',
				__( 'Display “Created by”', 'timestamps' ),
				[ $this, 'display_created_by_settings_field_callback' ],
				$this->settings_page,
				'sdcom_timestamps_certificate_settings_section'
			);

			add_settings_section(
				'sdcom_timestamps_general_settings_section',
				null,
				[ $this, 'general_settings_section' ],
				$this->settings_page
			);

			add_settings_field(
				'sdcom_timestamps_default_timestamps_enabled',
				__( 'Timestamp All Posts', 'timestamps' ),
				[ $this, 'default_timestamps_enabled_settings_field_callback' ],
				$this->settings_page,
				'sdcom_timestamps_general_settings_section'
			);

			// WooCommerce Settings.
			if ( is_woocommerce_active() ) {
				add_settings_section(
					'sdcom_timestamps_woocommerce_settings_section',
					null,
					[ $this, 'woocommerce_settings_section' ],
					$this->settings_page
				);

				add_settings_field(
					'sdcom_timestamps_enable_timestamps_woocommerce_orders',
					__( 'Enable Timestamps on WooCommerce Orders', 'timestamps' ),
					[ $this, 'enable_timestamps_woocommerce_orders_settings_field_callback' ],
					$this->settings_page,
					'sdcom_timestamps_woocommerce_settings_section'
				);

				// Check if the option "enable_timestamps_woocommerce_orders" is present and is true.
				if ( ! empty( $option['enable_timestamps_woocommerce_orders'] ) && $option['enable_timestamps_woocommerce_orders'] === 'true' ) {
					add_settings_field(
						'sdcom_timestamps_delete_certificates_old_woocommerce_orders_age',
						__( 'Delete Old Certificates by WooCommerce Order Age', 'timestamps' ),
						[ $this, 'delete_certificates_old_woocommerce_orders_age_settings_field_callback' ],
						$this->settings_page,
						'sdcom_timestamps_woocommerce_settings_section'
					);

					add_settings_field(
						'sdcom_timestamps_woocommerce_order_statuses_marked_old_certificates',
						__( 'WooCommerce Order Statuses Marked for Old Certificates', 'timestamps' ),
						[ $this, 'woocommerce_order_statuses_marked_old_certificates_settings_field_callback' ],
						$this->settings_page,
						'sdcom_timestamps_woocommerce_settings_section'
					);
				}
			}
		}
	}

	/**
	 * Outputs the certificate settings section.
	 *
	 * @return void
	 */
	public function certificate_settings_section() {
		echo wp_kses_post(
			sprintf(
				'<h2>%s</h2>',
				__( 'Certificate Settings', 'timestamps' )
			)
		);

		echo wp_kses_post(
			sprintf(
				'<p>%s</p>',
				__( 'Click the "Save Changes" button below to save your settings.', 'timestamps' )
			)
		);
	}

	/**
	 * Outputs the general settings section.
	 *
	 * @return void
	 */
	public function general_settings_section() {
		echo wp_kses_post(
			sprintf(
				'<h2>%s</h2>',
				__( 'General Settings', 'timestamps' )
			)
		);
	}

	/**
	 * Outputs the WooCommerce settings section.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public function woocommerce_settings_section() {
		echo wp_kses_post(
			sprintf(
				'<h2>%s</h2>',
				__( 'WooCommerce Settings', 'timestamps' )
			)
		);
	}

	/**
	 * Outputs the "Display Created By" settings input checkbox field.
	 *
	 * If the option value is present, the checkbox will be checked.
	 * If the option value is not present, the checkbox will be unchecked.
	 *
	 * @return void
	 */
	public function display_created_by_settings_field_callback() {
		$option = get_option( SDCOM_TIMESTAMPS_OPTIONS );

		$display_created_by = get_plugin_option( 'display_created_by', false );
		$username           = ! empty( $option['username'] ) && $display_created_by ? $option['username'] : 'anonymous';
		$date_and_time      = current_time( 'l, F j, Y \a\t g:i:s A' );
		$logo_url           = SDCOM_TIMESTAMPS_URL . 'dist/images/logo.png';
		$avatar_url         = ! empty( $option['avatar_url'] ) ? $option['avatar_url'] : $logo_url;
		printf(
			'<label><input type="checkbox" name="' . esc_attr( SDCOM_TIMESTAMPS_OPTIONS ) . '[display_created_by]" id="display_created_by" value="true" %s> %s</label><p class="description">%s<br />%s</p>',
			checked( isset( $option['display_created_by'] ), true, false ),
			wp_kses_post( __( 'Active', 'timestamps' ) ),
			wp_kses_post( __( 'Displays your profile name on the certificate.', 'timestamps' ) ),
			wp_kses_post(
				sprintf(
					'<div class="timestamps-options__profile js-anim-hidden-init js-anim-show-init">
						<div class="timestamps-options__profileImageWrapper">
							<img src="%s" width="32" height="32" class="timestamps-options__profileImage" alt="%s" style="display: none;" />
							<img src="%s" width="32" height="32" class="timestamps-options__profileImage-placeholder" alt="%s" />
						</div>
						<div class="timestamps-options__profileDesc">
							<div class="timestamps-options__profileDate">%s</div>
							<div class="timestamps-options__profileAuthor">%s</div>
						</div>
					</div>',
					esc_attr( $avatar_url ),
					esc_attr( __( 'Profile image', 'timestamps' ) ),
					esc_attr( $logo_url ),
					esc_attr( __( 'Profile image', 'timestamps' ) ),
					wp_kses_post( $date_and_time ),
					wp_kses_post(
						sprintf(
							/* translators: %s: username */
							__( 'by %s', 'timestamps' ),
							$username
						)
					)
				)
			),
		);
	}

	/**
	 * Outputs the "Timestamp All Posts" settings input checkbox field.
	 *
	 * If the option value is present, the checkbox will be checked.
	 * If the option value is not present, the checkbox will be unchecked.
	 *
	 * @return void
	 */
	public function default_timestamps_enabled_settings_field_callback() {
		$option = get_option( SDCOM_TIMESTAMPS_OPTIONS );

		printf(
			'<label><input type="checkbox" name="' . esc_attr( SDCOM_TIMESTAMPS_OPTIONS ) . '[default_timestamps_enabled]" id="default_timestamps_enabled" value="true" %s> %s</label><p class="description">%s</p>',
			checked( isset( $option['default_timestamps_enabled'] ), true, false ),
			wp_kses_post( __( 'Active', 'timestamps' ) ),
			wp_kses_post( __( 'Timestamps all posts by default.', 'timestamps' ) ),
		);
	}

	/**
	 * Outputs the "Enable Timestamps on WooCommerce Orders" settings input checkbox field.
	 *
	 * If the option value is present, the checkbox will be checked.
	 * If the option value is not present, the checkbox will be unchecked.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public function enable_timestamps_woocommerce_orders_settings_field_callback() {
		$option = get_option( SDCOM_TIMESTAMPS_OPTIONS );

		printf(
			'<label><input type="checkbox" name="' . esc_attr( SDCOM_TIMESTAMPS_OPTIONS ) . '[enable_timestamps_woocommerce_orders]" id="enable_timestamps_woocommerce_orders" value="true" %s> %s</label><p class="description">%s</p>',
			checked( isset( $option['enable_timestamps_woocommerce_orders'] ), true, false ),
			wp_kses_post( __( 'Active', 'timestamps' ) ),
			wp_kses_post( __( 'Adds Timestamps to WooCommerce Orders.', 'timestamps' ) ),
		);
	}

	/**
	 * Outputs the "Delete Old Certificates by WooCommerce Order Age" settings input number field.
	 *
	 * The default value is 365 days.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public function delete_certificates_old_woocommerce_orders_age_settings_field_callback() {
		$option = get_option( SDCOM_TIMESTAMPS_OPTIONS );

		// Get the value from the option, or use the default value of 365 days.
		$value = isset( $option['delete_certificates_old_woocommerce_orders_age'] ) && ! empty( $option['delete_certificates_old_woocommerce_orders_age'] ) ? $option['delete_certificates_old_woocommerce_orders_age'] : 365;

		printf(
			'<label><input type="number" min="0" name="' . esc_attr( SDCOM_TIMESTAMPS_OPTIONS ) . '[delete_certificates_old_woocommerce_orders_age]" id="delete_certificates_old_woocommerce_orders_age" value="' . esc_attr( $value ) . '"> %s</label><p class="description">%s</p>',
			wp_kses_post( __( 'Days', 'timestamps' ) ),
			wp_kses_post( __( 'Automatically delete old certificates after a certain age. Defaults to 365 days.', 'timestamps' ) ),
		);
	}

	/**
	 * Outputs the "WooCommerce Order Statuses Marked for Old Certificates" settings input checkbox field.
	 *
	 * The default value is _all_ WooCommerce order statuses.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function woocommerce_order_statuses_marked_old_certificates_settings_field_callback() {
		$option = get_option( SDCOM_TIMESTAMPS_OPTIONS );

		// Get the WooCommerce order statuses.
		$order_statuses = wc_get_order_statuses();

		// Sort the $order_statuses in alphabetical order.
		ksort( $order_statuses );

		// If the option value is not present, set the default value to all WooCommerce order statuses.
		if ( empty( $option['woocommerce_order_statuses_marked_old_certificates'] ) ) {
			$option['woocommerce_order_statuses_marked_old_certificates'] = array_keys( $order_statuses );
		}

		// Loop through the WooCommerce order statuses as checkbox elements.
		foreach ( $order_statuses as $key => $value ) {
			$is_checked = in_array( $key, $option['woocommerce_order_statuses_marked_old_certificates'], true );
			printf(
				'<input type="checkbox" name="%s[woocommerce_order_statuses_marked_old_certificates][]" value="%s" %s>%s<br>',
				esc_attr( SDCOM_TIMESTAMPS_OPTIONS ),
				esc_attr( $key ),
				checked( $is_checked, true, false ),
				esc_html( $value )
			);
		}

		printf(
			'<p class="description">%s</p>',
			wp_kses_post( __( 'Select the statuses to mark for old certificates.', 'timestamps' ) ),
		);
	}

	/**
	 * Render settings page.
	 *
	 * @since 1.0.0
	 */
	public function render_settings_page() {
		// Get the timestamps options.
		$timestamps_api_key    = get_plugin_option( 'api_key', '' );
		$timestamps_username   = get_plugin_option( 'username', '' );
		$timestamps_avatar_url = get_plugin_option( 'avatar_url', '' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php
			if ( ! is_authenticated() ) {
				?>
				<p style="font-size: 1.2em; color: #333; border: 2px solid #0073aa; padding: 10px; background-color: #f5f5f5;">
					<?php echo wp_kses_post( __( 'Welcome to the Timestamps plugin! You will first have to <b>authenticate your ScoreDetect account</b>. Please follow the instructions below:', 'timestamps' ) ); ?>
				</p>
				<?php
			}

			echo wp_kses_post(
				sprintf(
					'<h2>%s</h2>',
					__( 'Authentication', 'timestamps' ),
				)
			);

		if ( ! is_authenticated() ) {
			?>
				<p>
					<ol>
						<li>
						<?php echo wp_kses_post( __( 'Click the "Authenticate" button below.', 'timestamps' ) ); ?>
						</li>
						<li>
						<?php echo wp_kses_post( __( 'A popup will open. Log into your <b>ScoreDetect</b> account.', 'timestamps' ) ); ?>
						</li>
						<li>
						<?php echo wp_kses_post( __( 'Close the popup and return to your WordPress site.', 'timestamps' ) ); ?>
						</li>
					</ol>
				</p>
				<?php
		}

		if ( is_authenticated() ) {
			?>
				<p style="font-size: 1.2em; color: #333; border: 2px solid #0073aa; padding: 10px; background-color: #f5f5f5;">
					<?php echo wp_kses_post( __( 'You are now <b>authenticated</b>. You can now use the Timestamps plugin.', 'timestamps' ) ); ?>
				</p>
				<p>
					<?php echo wp_kses_post( __( 'You may re-authenticate your <b>ScoreDetect</b> account at anytime.', 'timestamps' ) ); ?>
				</p>
			<?php
		}

		echo wp_kses_post(
			sprintf(
				'<button id="timestamps-authenticate" class="button button-primary">%s</button>',
				is_authenticated() ? __( 'Re-authenticate', 'timestamps' ) : __( 'Authenticate', 'timestamps' )
			)
		);
		?>
		<div id="timestamps-options-errors"></div>
		<?php
		if ( is_authenticated() ) {
			?>
			<form id="timestamps-options" action="options.php" method="post">
				<?php
				settings_fields( SDCOM_TIMESTAMPS_OPTIONS );
				do_settings_sections( $this->settings_page );
				?>
				<input type="hidden" name="<?php echo esc_attr( SDCOM_TIMESTAMPS_OPTIONS . '[api_key]' ); ?>" id="api_key" value="<?php echo esc_attr( $timestamps_api_key ); ?>">
				<input type="hidden" name="<?php echo esc_attr( SDCOM_TIMESTAMPS_OPTIONS . '[username]' ); ?>" id="username" value="<?php echo esc_attr( $timestamps_username ); ?>">
				<input type="hidden" name="<?php echo esc_attr( SDCOM_TIMESTAMPS_OPTIONS . '[avatar_url]' ); ?>" id="avatar_url" value="<?php echo esc_attr( $timestamps_avatar_url ); ?>">
				<?php
				submit_button( __( 'Save Changes', 'timestamps' ) );
				?>
			</form>
			<?php
		}
		wp_enqueue_script( 'timestamps_authenticate_script' );
		wp_enqueue_script( 'timestamps_settings_script' );
		wp_enqueue_style( 'timestamps_admin_styles' );
	}

	/**
	 * Output variety of notices.
	 *
	 * @since 1.0.0
	 */
	public function maybe_notice() {

		// Admins only can see the notices
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		AdminNotices::factory()->process_notices();

		$notices = AdminNotices::factory()->get_notices();

		foreach ( $notices as $notice_key => $notice ) {
			?>
			<div data-timestamps-notice="<?php echo esc_attr( $notice_key ); ?>" class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> <?php
			if ( $notice['dismiss'] ) :
				?>
				is-dismissible<?php endif; ?>">
				<p>
					<?php echo wp_kses_post( $notice['html'] ); ?>
				</p>
			</div>
			<?php
		}

		wp_enqueue_script( 'timestamps_notice_script' );

		return $notices;
	}

	/**
	 * Dismiss notice via ajax
	 *
	 * @since 1.0.0
	 */
	public function action_wp_ajax_timestamps_notice_dismiss() {
		if ( empty( $_POST['notice'] ) || ! check_ajax_referer( 'timestamps_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error();
			exit;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
			exit;
		}

		AdminNotices::factory()->dismiss_notice( sanitize_key( $_POST['notice'] ) );

		wp_send_json_success();
	}

	/**
	 * Saves authentication settings via ajax
	 *
	 * @since 1.0.0
	 */
	public function action_wp_ajax_timestamps_save_authentication_settings() {
		if ( ! check_ajax_referer( 'timestamps_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error();
			exit;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
			exit;
		}

		if ( empty( sanitize_text_field( wp_unslash( $_POST['username'] ) ) ) ) {
			wp_send_json_error();
			exit;
		}

		if ( empty( sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) ) ) {
			wp_send_json_error();
			exit;
		}

		// update the options
		$timestamps_options = get_option( SDCOM_TIMESTAMPS_OPTIONS );

		$timestamps_options['username']   = ! empty( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : '';
		$timestamps_options['api_key']    = ! empty( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
		$timestamps_options['avatar_url'] = ! empty( $_POST['avatar_url'] ) ? sanitize_text_field( wp_unslash( $_POST['avatar_url'] ) ) : '';

		update_option( SDCOM_TIMESTAMPS_OPTIONS, $timestamps_options );

		wp_send_json_success();
	}

	/**
	 * Deletes authentication settings via ajax
	 *
	 * @since 1.0.0
	 */
	public function action_wp_ajax_timestamps_delete_authentication_settings() {
		if ( ! check_ajax_referer( 'timestamps_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error();
			exit;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
			exit;
		}

		// delete the options
		delete_option( SDCOM_TIMESTAMPS_OPTIONS );

		wp_send_json_success();
	}


	/**
	 * Register and enqueue scripts.
	 *
	 * @since 1.0.0
	 */
	public function action_admin_enqueue_scripts() {

		wp_register_script(
			'timestamps_notice_script',
			SDCOM_TIMESTAMPS_URL . 'dist/js/notice-script.js',
			Utils\get_asset_info( 'notice-script', 'dependencies' ),
			Utils\get_asset_info( 'notice-script', 'version' ),
			true
		);

		wp_set_script_translations( 'timestamps_notice_script', 'timestamps' );

		wp_localize_script(
			'timestamps_notice_script',
			'timestampsAdmin',
			array(
				'nonce' => wp_create_nonce( 'timestamps_admin_nonce' ),
			)
		);

		wp_register_script(
			'timestamps_authenticate_script',
			SDCOM_TIMESTAMPS_URL . 'dist/js/authenticate-script.js',
			Utils\get_asset_info( 'authenticate-script', 'dependencies' ),
			Utils\get_asset_info( 'authenticate-script', 'version' ),
			true
		);

		wp_set_script_translations( 'timestamps_authenticate_script', 'timestamps' );

		wp_localize_script(
			'timestamps_authenticate_script',
			'timestampsAdmin',
			array(
				'nonce' => wp_create_nonce( 'timestamps_admin_nonce' ),
			)
		);

		wp_register_script(
			'timestamps_settings_script',
			SDCOM_TIMESTAMPS_URL . 'dist/js/settings-script.js',
			Utils\get_asset_info( 'settings-script', 'dependencies' ),
			Utils\get_asset_info( 'settings-script', 'version' ),
			true
		);

		wp_set_script_translations( 'timestamps_settings_script', 'timestamps' );

		wp_localize_script(
			'timestamps_settings_script',
			'timestampsAdmin',
			array(
				'nonce' => wp_create_nonce( 'timestamps_admin_nonce' ),
			)
		);

		wp_register_style(
			'timestamps_admin_styles',
			SDCOM_TIMESTAMPS_URL . 'dist/css/admin-styles.css',
			array(),
			Utils\get_asset_info( 'admin-styles', 'version' )
		);
	}
}
