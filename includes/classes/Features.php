<?php
/**
 * Handles registering and storing feature instances.
 *
 * @since 1.0.0
 * @package SDCOM_Timestamps
 */

namespace SDCOM_Timestamps;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class for storing and managing features.
 */
class Features {

	/**
	 * Stores all features that have been properly included (both active and inactive).
	 *
	 * @since 1.0.0
	 * @var array
	 */
	public $registered_features = [];

	/**
	 * Initiate class actions
	 *
	 * @since 1.0.0
	 */
	public function setup() {
		add_action( 'init', array( $this, 'setup_features' ), 0 );
	}

	/**
	 * Registers a feature for use in the plugin.
	 *
	 * @param  Feature $feature An instance of the Feature class
	 * @since 1.0.0
	 * @return boolean
	 */
	public function register_feature( Feature $feature ) {
		$this->registered_features[ $feature->slug ] = $feature;
		return true;
	}

	/**
	 * Easy access function to get a Feature object from a slug.
	 *
	 * @param  string $slug Feature slug
	 * @since 1.0.0
	 * @return Feature
	 */
	public function get_registered_feature( $slug ) {
		if ( empty( $this->registered_features[ $slug ] ) ) {
			return false;
		}

		return $this->registered_features[ $slug ];
	}

	/**
	 * Set up all active features.
	 *
	 * @since 1.0.0
	 */
	public function setup_features() {
		/**
		 * Fires before features are setup
		 *
		 * @hook sdcom_setup_features
		 * @since 1.0.0
		 */
		do_action( 'sdcom_setup_features' );

		foreach ( $this->registered_features as $feature_slug => $feature ) {
			$feature->setup();
		}
	}

	/**
	 * Return singleton instance of class.
	 *
	 * @return object
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
