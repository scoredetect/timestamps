import { RawHTML } from '@wordpress/element';

const BlockSave = ({ attributes }) => {
	const { sdcomPreviousCertificateId } = attributes;

	const embedCode = `<div class="sdcom-timestamps" data-id="${sdcomPreviousCertificateId}"></div>`;

	return <RawHTML>{embedCode}</RawHTML>;
};

export default BlockSave;
