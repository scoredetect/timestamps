<?php
/**
 * The plugin admin notice handler
 *
 * phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
 *
 * @since 1.0.0
 * @package SDCOM_Timestamps
 */

namespace SDCOM_Timestamps;

use SDCOM_Timestamps\Utils;
use SDCOM_Timestamps\Screen;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Admin notices class
 */
class AdminNotices {

	/**
	 * Notice keys
	 *
	 * @since 1.0.0
	 * @var array
	 */
	protected $notice_keys = [
		'need_setup',
	];

	/**
	 * Notices that should be shown on a screen
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected $notices = [];

	/**
	 * Process all notifications and prepare ones that should be displayed
	 *
	 * @since 1.0.0
	 */
	public function process_notices() {
		$this->notices = [];

		foreach ( $this->notice_keys as $notice ) {
			$output = call_user_func( [ $this, 'process_' . $notice . '_notice' ] );

			if ( ! empty( $output ) ) {
				$this->notices[ $notice ] = $output;
			}
		}
	}

	/**
	 * Process need setup notice.
	 *
	 * Type: notice
	 * Dismiss: yes
	 * Show: All screens except settings
	 *
	 * @since 1.0.0
	 * @return array|bool
	 */
	protected function process_need_setup_notice() {

		$timestamps_options = get_option( SDCOM_TIMESTAMPS_OPTIONS );
		$timestamps_api_key = isset( $timestamps_options['api_key'] ) ? $timestamps_options['api_key'] : '';

		if ( ! empty( $timestamps_api_key ) ) {
			return false;
		}

		$dismiss = get_option( 'sdcom_timestamps_hide_need_setup_notice', false );

		if ( $dismiss ) {
			return false;
		}

		$screen = Screen::factory()->get_current_screen();

		if ( in_array( $screen, [ 'settings' ], true ) ) {
			return false;
		}

		$url = admin_url( 'options-general.php?page=' . Screen::factory()->get_settings_page() );

		return [
			'type'    => 'info',
			'dismiss' => ! $dismiss,
			'html'    => sprintf(
				/* translators: Sync Page URL */
				__( 'The Timestamps plugin is almost ready. Click to <a href="%s">enter your settings here</a>.', 'timestamps' ),
				esc_url( $url )
			),
		];
	}

	/**
	 * Get notices that should be displayed
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_notices() {
		/**
		 * Filter admin notices
		 *
		 * @hook timestamps_admin_notices
		 * @param  {array} $notices Admin notices
		 * @return {array} New notices
		 */
		return apply_filters( 'timestamps_admin_notices', $this->notices );
	}

	/**
	 * Dismiss a notice given a notice key.
	 *
	 * @param  string $notice Notice key
	 * @since 1.0.0
	 */
	public function dismiss_notice( $notice ) {
		$value = true;

		update_option( 'timestamps_hide_' . $notice . '_notice', $value );
	}

	/**
	 * Return singleton instance of class
	 *
	 * @return object
	 * @since 1.0.0
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}
}
