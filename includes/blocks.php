<?php
/**
 * Blocks Setup.
 *
 * @package SDCOM_Timestamps\Blocks
 */

namespace SDCOM_Timestamps\Blocks;

/**
 * Sets up the blocks.
 *
 * @return void
 */
function setup() {
	register_custom_blocks();
}

/**
 * Adds the various Custom Blocks.
 */
function register_custom_blocks() {

	require_once SDCOM_TIMESTAMPS_INC . 'blocks/timestamp-post/register.php';

	TimestampPost\setup();
}
