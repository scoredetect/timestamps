/**
 * WordPress dependencies.
 */
import { registerPlugin } from '@wordpress/plugins';

/**
 * Internal dependencies.
 */
import TimestampPost from './plugins/timestamp-post';

registerPlugin('timestamp-post', {
	render: TimestampPost,
	icon: null,
});
