<?php
/**
 * Timestamps uninstaller
 *
 * Used when clicking "Delete" from inside of WordPress's plugins page.
 *
 * @package SDCOM_Timestamps
 * @since 1.0.0
 */

namespace SDCOM_Timestamps;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/utils.php';

/**
 * Class Uninstaller
 */
class Uninstaller {

	/**
	 * List of option keys that need to be deleted when uninstalling the plugin.
	 *
	 * @var array
	 */
	protected $options = [
		'sdcom_timestamps',

		// Admin notices options
		'sdcom_timestamps_hide_need_setup_notice',
	];

	/**
	 * List of transient keys that need to be deleted when uninstalling the plugin.
	 *
	 * @var array
	 */
	protected $transients = [];

	/**
	 * Initialize uninstaller
	 *
	 * Perform some checks to make sure plugin can/should be uninstalled
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Exit if accessed directly.
		if ( ! defined( 'ABSPATH' ) ) {
			$this->exit_uninstaller();
		}

		// Uninstall the plugin.
		$this->clean_options_and_transients();
	}

	/**
	 * Delete all the options in a single site context.
	 */
	protected function delete_options() {
		foreach ( $this->options as $option ) {
			delete_option( $option );
		}
	}

	/**
	 * Delete all the transients in a single site context.
	 */
	protected function delete_transients() {
		foreach ( $this->transients as $transient ) {
			delete_transient( $transient );
		}
	}

	/**
	 * Cleanup options and transients
	 *
	 * Deletes the plugin options and transients.
	 *
	 * @since 1.0.0
	 */
	protected function clean_options_and_transients() {
		$this->delete_options();
		$this->delete_transients();
	}

	/**
	 * Exit uninstaller
	 *
	 * Gracefully exit the uninstaller if we should not be here
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function exit_uninstaller() {
		status_header( 404 );
		exit;
	}
}

new Uninstaller();
