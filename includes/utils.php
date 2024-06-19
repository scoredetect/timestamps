<?php
/**
 * Utility functions.
 *
 * @since 1.0.0
 * @package SDCOM_Timestamps
 */

namespace SDCOM_Timestamps\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Get asset info from extracted asset files
 *
 * @param string $slug Asset slug as defined in build/webpack configuration
 * @param string $attribute Optional attribute to get. Can be version or dependencies
 * @return string|array
 */
function get_asset_info( $slug, $attribute = null ) {
	if ( file_exists( SDCOM_TIMESTAMPS_PATH . 'dist/js/' . $slug . '.asset.php' ) ) {
		$asset = require SDCOM_TIMESTAMPS_PATH . 'dist/js/' . $slug . '.asset.php';
	} elseif ( file_exists( SDCOM_TIMESTAMPS_PATH . 'dist/css/' . $slug . '.asset.php' ) ) {
		$asset = require SDCOM_TIMESTAMPS_PATH . 'dist/css/' . $slug . '.asset.php';
	} else {
		return null;
	}

	if ( ! empty( $attribute ) && isset( $asset[ $attribute ] ) ) {
		return $asset[ $attribute ];
	}

	return $asset;
}

/**
 * Use the correct update option function depending on the context.
 *
 * @since 1.0.0
 * @param string $option   Name of the option to update.
 * @param mixed  $value    Option value.
 * @param mixed  $autoload Whether to load the option when WordPress starts up.
 * @return bool
 */
function update_option( $option, $value, $autoload = null ) {
	return \update_option( $option, $value, $autoload );
}

/**
 * Use the correct get option function depending on the context.
 *
 * @since 1.0.0
 * @param string $option        Name of the option to get.
 * @param mixed  $default_value Default value.
 * @return mixed
 */
function get_option( $option, $default_value = false ) {
	return \get_option( $option, $default_value );
}

/**
 * Use the correct delete option function depending on the context.
 *
 * @since 1.0.0
 * @param string $option Name of the option to delete.
 * @return bool
 */
function delete_option( $option ) {
	return \delete_option( $option );
}

/**
 * Checks if the user is authenticated.
 *
 * This function checks if the user is authenticated by checking if the 'api_key' in the 'timestamps' options is set and not empty.
 *
 * @since 1.0.0
 * @return bool Returns true if the 'api_key' is set and not empty, false otherwise.
 */
function is_authenticated() {
	$timestamps_options = get_option( SDCOM_TIMESTAMPS_OPTIONS );
	$timestamps_api_key = isset( $timestamps_options['api_key'] ) ? $timestamps_options['api_key'] : '';

	return ! empty( $timestamps_api_key );
}

/**
 * Checks if the Block Editor is active.
 *
 * @since 1.0.0
 * @return bool Returns true if the Block Editor is active, false otherwise.
 */
function is_block_editor_active() {

	$classic_editor_replace = \get_option( 'classic-editor-replace' );

	// We assume that the Block Editor is active, whilst the Classic Editor plugin never existed.
	if ( empty( $classic_editor_replace ) ) {
		return true;
	}

	// If the Classic Editor plugin is installed and activated, we check the setting.
	if ( $classic_editor_replace === 'block' ) {
		return true;
	}

	// If the Classic Editor plugin is installed and activated, we check the setting.
	if ( $classic_editor_replace === 'classic' ) {
		return false;
	}

	// We assume that the Block Editor is active by default.
	return true;
}

/**
 * Checks if the platform script is in the post content.
 *
 * @since 1.0.0
 * @param WP_Post|int|null $post Optional. Post to check. Defaults to the current post.
 * @return bool Returns true if the platform script is in the post content, false otherwise.
 */
function is_platform_script_in_content( $post = null ) {

	$post = get_post( $post );

	if ( empty( $post ) ) {
		return false;
	}

	$content = $post->post_content;

	if ( empty( $content ) ) {
		return false;
	}

	$content = new \WP_HTML_Tag_Processor( $content );

	$script = $content->next_tag(
		array(
			'tag_name' => 'script',
			'src'      => 'https://platform.scoredetect.com/widgets.js',
		)
	);

	if ( ! empty( $script ) ) {
		return true;
	}

	return false;
}

/**
 * Checks if the WooCommerce plugin is active.
 *
 * @since 1.3.0
 * @return bool Returns true if the WooCommerce plugin is active, false otherwise.
 */
function is_woocommerce_active() {
	return class_exists( 'WooCommerce' );
}

/**
 * Gets the WooCommerce volatile order data keys.
 *
 * This function returns an array of keys that represent volatile data in a WooCommerce order.
 *
 * @return array Filtered volatile order data keys.
 */
function get_wc_volatile_order_data_keys() {
	$wc_volatile_order_data_keys = [ 'date_modified', 'meta_data', 'version' ];

	return apply_filters( 'sdcom_timestamps_wc_volatile_order_data_keys', $wc_volatile_order_data_keys );
}

/**
 * Checks if timestamps for WooCommerce orders are active.
 *
 * @return bool True if the option is active, false otherwise.
 */
function is_timestamps_woocommerce_orders_active(): bool {
	$timestamps_options                    = get_option( SDCOM_TIMESTAMPS_OPTIONS );
	$timestamps_woocommerce_orders_enabled = isset( $timestamps_options['enable_timestamps_woocommerce_orders'] ) ? $timestamps_options['enable_timestamps_woocommerce_orders'] : '';

	return ! empty( $timestamps_woocommerce_orders_enabled );
}

/**
 * Gets the certificate URL for a WooCommerce order.
 *
 * @param \WC_Order $order The WooCommerce order object.
 * @return string The certificate URL.
 */
function get_certificate_url_wc_order( $order ): string {
	$sdcom_previous_certificate_id = $order->get_meta( 'sdcom_previous_certificate_id' );

	// Bail early if there is no previous certificate id.
	if ( empty( $sdcom_previous_certificate_id ) ) {
		return '';
	}

	return apply_filters( 'get_certificate_url_wc_order', esc_url( 'https://scoredetect.com/certificate/' . $sdcom_previous_certificate_id ), $order );
}
