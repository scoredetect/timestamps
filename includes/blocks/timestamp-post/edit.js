/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { Placeholder } from '@wordpress/components';

/**
 * Edit component.
 *
 * @see https://wordpress.org/gutenberg/handbook/designers-developers/developers/block-api/block-edit-save/#edit
 *
 * @returns {Placeholder} The edit component.
 */

const BlockEdit = () => {
	return <Placeholder label={__('Timestamps', 'timestamps')} />;
};

export default BlockEdit;
