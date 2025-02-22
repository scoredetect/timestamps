/**
 * WordPress dependencies.
 */
import { CheckboxControl } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { PluginPostStatusInfo } from '@wordpress/edit-post';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies.
 */
import { useAfterSave } from '../../../lib/utils';
import { PUBLIC_API_URL } from '../../../lib/constants';

export default () => {
	const isAfterSave = useAfterSave();
	const { editPost } = useDispatch('core/editor');
	const { createNotice } = useDispatch('core/notices');
	const postType = useSelect((select) => select('core/editor').getCurrentPostType(), []);

	const timestampsOptions = useSelect((select) => {
		const siteOptions = select('core').getEntityRecord('root', 'site');
		return siteOptions?.sdcom_timestamps;
	});

	const {
		sdcom_timestamp_post = false,
		sdcom_previous_certificate_id = '',
		...meta
	} = useSelect((select) => select('core/editor').getEditedPostAttribute('meta') || {});
	const postContent = useSelect((select) => select('core/editor').getEditedPostContent(), []);

	// get api endpoint for the post type
	const postTypeEndpoint = useSelect(
		(select) => select('core').getPostType(postType),
		[postType],
	);

	const postId = useSelect((select) => select('core/editor').getCurrentPostId(), []);
	const postPermalink = useSelect((select) => select('core/editor').getPermalink(), []);

	// Check if it is a new post by checking if the post status is 'auto-draft'.
	const isNewPost = useSelect(
		(select) => select('core/editor').getEditedPostAttribute('status') === 'auto-draft',
	);

	const postApiEndpoint = `/${postTypeEndpoint?.rest_namespace}/${postTypeEndpoint?.rest_base}/${postId}`;

	const onChange = (sdcom_timestamp_post) => {
		editPost({ meta: { ...meta, sdcom_timestamp_post } });
	};

	// create certificate
	const createCertificate = async ({ apiKey, formData }) => {
		try {
			const url = `${PUBLIC_API_URL}/create-certificate`;
			const { userAgent } = navigator;

			const headers = new Headers({
				Authorization: `Bearer ${apiKey}`,
				'User-Agent': userAgent,
				'X-ScoreDetect-Referer': `${postPermalink}`,
			});

			const res = await fetch(url, {
				method: 'POST',
				headers,
				body: formData,
			});

			if (!res.ok) {
				// eslint-disable-next-line no-console
				console.log('Error in postData', { url, res });

				throw Error(res.statusText);
			}

			return res.json();
		} catch (error) {
			// eslint-disable-next-line no-console
			console.error(error);
			throw error;
		}
	};

	// update certificate
	const updateCertificate = async ({ apiKey, certificateId, metadata }) => {
		try {
			const url = `${PUBLIC_API_URL}/update-certificate`;

			const headers = new Headers({
				Authorization: `Bearer ${apiKey}`,
				'Content-Type': 'application/json',
			});

			const res = await fetch(url, {
				method: 'PATCH',
				headers,
				body: JSON.stringify({
					certificateId,
					metadata,
				}),
			});

			if (!res.ok) {
				// eslint-disable-next-line no-console
				console.log('Error in postData', { url, res });

				throw Error(res.statusText);
			}

			return res.json();
		} catch (error) {
			// eslint-disable-next-line no-console
			console.error(error);
			throw error;
		}
	};

	// Set the default value of 'sdcom_timestamp_post' to the value of 'timestampsOptions.default_timestamps_enabled' on load.
	useEffect(() => {
		if (!isNewPost) {
			return;
		}
		const sdcom_timestamp_post_checked =
			timestampsOptions?.default_timestamps_enabled === 'true';
		if (sdcom_timestamp_post_checked !== sdcom_timestamp_post) {
			editPost({ meta: { ...meta, sdcom_timestamp_post: sdcom_timestamp_post_checked } });
		}
	}, [timestampsOptions]); // eslint-disable-line react-hooks/exhaustive-deps

	// create certificate on post save.
	useEffect(
		() => {
			const handleCreateCertificate = async () => {
				// Bail early if not after save.
				if (!isAfterSave) {
					return;
				}

				// Bail early if sdcom_timestamp_post is false.
				if (!sdcom_timestamp_post) {
					return;
				}

				// Proceed with creating certificate.
				try {
					// Bail early if postContent is empty.
					if (postContent.length < 0) {
						throw new Error('postContent is empty.');
					}

					const metadata = {
						certificateType: 'plain_text_upload',
						displayCreatedBy: !!timestampsOptions?.display_created_by,
						username:
							timestampsOptions?.username &&
							timestampsOptions?.display_created_by === 'true'
								? timestampsOptions?.username
								: 'anonymous',
					};

					const formData = new FormData();

					// add the plain text to the form data
					formData.append('file', postContent);

					// add username to the form data
					formData.append('username', metadata.username);

					// add previous certificate id to the form data
					formData.append('previous_certificate_id', sdcom_previous_certificate_id);

					// create certificate
					const certificate = await createCertificate({
						apiKey: timestampsOptions.api_key,
						formData,
					});

					const certificateId = certificate?.certificate?.id;

					// update certificate
					await updateCertificate({
						apiKey: timestampsOptions.api_key,
						certificateId,
						metadata,
					});

					// update the 'sdcom_previous_certificate_id' post meta with the new certificate id
					apiFetch({
						path: postApiEndpoint,
						method: 'POST',
						data: {
							meta: {
								...meta,
								sdcom_previous_certificate_id: certificateId,
							},
						},
					}).catch((error) => {
						// Handle error response
						// eslint-disable-next-line no-console
						console.error('Error updating post meta:', error);
					});

					// Add success notice.
					createNotice('success', __('Certificate created successfully.', 'timestamps'), {
						isDismissible: true,
					});
				} catch (error) {
					// eslint-disable-next-line no-console
					console.error(error);
				}
			};
			handleCreateCertificate();
		},
		// eslint-disable-next-line react-hooks/exhaustive-deps -- We only want to run this effect once via isAfterSave.
		[isAfterSave],
	);

	return (
		<PluginPostStatusInfo>
			<CheckboxControl
				label={sprintf(
					/* translators: %s: post type name */
					__('Enable timestamps for this %s', 'timestamps'),
					postType,
				)}
				help={__(
					'Timestamps prove that you keep your content regularly up-to-date.',
					'timestamps',
				)}
				checked={sdcom_timestamp_post}
				onChange={onChange}
			/>
		</PluginPostStatusInfo>
	);
};
