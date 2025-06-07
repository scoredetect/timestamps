<?php
/**
 * "Timestamp Post" block.
 *
 * @package SDCOM_Timestamps\Blocks\TimestampPost
 */

namespace SDCOM_Timestamps\Blocks\TimestampPost;

/**
 * Hooks into WordPress lifecycle.
 */
function setup() {
	add_action( 'init', __NAMESPACE__ . '\register_custom_block' );
	add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\scripts' );
}

/**
 * Enqueue scripts.
 */
function scripts() {
	wp_enqueue_script( 'sdcom-timestamp-post-block', 'https://platform.scoredetect.com/widgets.js', array(), SDCOM_TIMESTAMPS_VERSION, true );
}

/**
 * Register the block.
 */
function register_custom_block() {

	register_block_type(
		__DIR__,
		[
			'render_callback' => __NAMESPACE__ . '\render_block_callback',
			'category'        => 'text',
		]
	);
}

/**
 * Render callback method for the block.
 *
 * @param array     $attributes The blocks attributes
 * @param string    $content    Data returned from InnerBlocks.Content
 * @param \WP_Block $block      Block information such as context.
 *
 * @return string The rendered block markup.
 */
function render_block_callback( $attributes, $content, $block ) {

	// Include template file.
	ob_start();
	load_template(
		__DIR__ . '/markup.php',
		false,
		$attributes
	);
	return ob_get_clean();
}
