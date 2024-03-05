<?php
/**
 * Feature class to be initiated for all features.
 *
 * All features extend this class.
 *
 * @since 1.0.0
 * @package SDCOM_Timestamps
 */

namespace SDCOM_Timestamps;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Feature abstract class
 */
abstract class Feature {
	/**
	 * Feature slug
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public $slug;

	/**
	 * Feature pretty title
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public $title;

	/**
	 * Short title
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public $short_title;

	/**
	 * Feature summary
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public $summary;

	/**
	 * URL to feature documentation.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public $docs_url;

	/**
	 * Run on every page load for feature to set itself up
	 *
	 * @since 1.0.0
	 */
	abstract public function setup();

	/**
	 * Create feature
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		/**
		 * Fires when Feature object is created
		 *
		 * @hook sdcom_feature_create
		 * @param {Feature} $feature Current feature
		 * @since 1.0.0
		 */
		do_action( 'sdcom_feature_create', $this );
	}

	/**
	 * Returns the feature title.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_title(): string {
		return $this->title;
	}

	/**
	 * Returns the feature short title.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_short_title(): string {
		if ( ! empty( $this->short_title ) ) {
			return $this->short_title;
		}

		return $this->get_title();
	}
}
