/**
 * WordPress dependencies.
 */
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Placeholder } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';

/**
 * Edit component.
 *
 * @see https://wordpress.org/gutenberg/handbook/designers-developers/developers/block-api/block-edit-save/#edit
 *
 * @param {object} props The block props.
 * @returns {Placeholder} The edit component.
 */

const BlockEdit = (props) => {
	const { setAttributes } = props;
	const postId = useSelect((select) => select('core/editor').getCurrentPostId(), []);

	useEffect(() => {
		// Get the post meta.
		apiFetch({ path: `/wp/v2/posts/${postId}` }).then((post) => {
			setAttributes({ sdcomPreviousCertificateId: post.meta.sdcom_previous_certificate_id });
		});
	}, [postId, setAttributes]);

	return <Placeholder label={__('Timestamps', 'timestamps')} />;
};

export default BlockEdit;
