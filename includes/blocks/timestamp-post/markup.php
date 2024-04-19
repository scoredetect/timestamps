<?php
/**
 * Template for the "Timestamp Post" block.
 *
 * @package SDCOM_Timestamps\Blocks\TimestampPost
 */

namespace SDCOM_Timestamps\Blocks\TimestampPost;

$sdcom_previous_certificate_id = get_post_meta( get_the_ID(), 'sdcom_previous_certificate_id', true );

// Bail early if there is no embed_code.
if ( empty( $sdcom_previous_certificate_id ) ) {
	return;
}

printf(
	'<div class="sdcom-timestamps" data-id="%s"></div>',
	esc_attr( $sdcom_previous_certificate_id )
);
