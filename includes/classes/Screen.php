<?php
/**
 * Determine which plugin screen we are viewing
 *
 * @since 1.0.0
 * @package SDCOM_Timestamps
 */

namespace SDCOM_Timestamps;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Screen class
 */
class Screen {
	/**
	 * Current screen
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $screen = null;

	/**
	 * Settings instance
	 *
	 * @var Screen\Settings
	 * @since 1.0.0
	 */
	public $settings;

	/**
	 * Settings page.
	 *
	 * This is the slug of the settings page.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $settings_page = 'sdcom-timestamps';

	/**
	 * Get settings page.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_settings_page() {
		return $this->settings_page;
	}

	/**
	 * Initialize class
	 *
	 * @since 1.0.0
	 */
	public function setup() {
		add_action( 'admin_init', [ $this, 'determine_screen' ] );

		$this->settings = new Screen\Settings();

		$this->settings->setup();
	}

	/**
	 * Determine current plugin screen.
	 *
	 * @since 1.0.0
	 */
	public function determine_screen() {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( ! empty( $_GET['page'] ) && false !== strpos( sanitize_key( $_GET['page'] ), $this->settings_page ) ) {

			if ( $this->settings_page === $_GET['page'] ) {
				$this->screen = 'settings';
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification
	}

	/**
	 * Get current screen
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_current_screen() {
		return $this->screen;
	}

	/**
	 * Set current screen
	 *
	 * @since 1.0.0
	 * @param  string $screen Screen to set
	 */
	public function set_current_screen( $screen ) {
		$this->screen = $screen;
	}

	/**
	 * Return singleton instance of class
	 *
	 * @return self
	 * @since 1.0.0
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}
}
