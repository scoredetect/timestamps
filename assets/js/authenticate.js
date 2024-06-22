/**
 * WordPress dependencies.
 */
import apiFetch from '@wordpress/api-fetch';
import domReady from '@wordpress/dom-ready';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { PUBLIC_SCOREDETECT_URL } from './lib/constants';
import { authenticateSession, getUser, getUsername } from './lib/utils';

/**
 * Window dependencies.
 */
const { timestampsAdmin, ajaxurl } = window;

/**
 * Deletes the old authentication options.
 *
 * @returns {void}
 */
const handleDeleteOptions = () => {
	const formData = new FormData();

	formData.append('action', 'timestamps_delete_authentication_settings');
	formData.append('nonce', timestampsAdmin.nonce);

	apiFetch({
		method: 'POST',
		url: ajaxurl,
		body: formData,
	})
		.then((response) => {
			if (!response.success) {
				throw new Error('API fetch failed');
			}
		})
		.catch((error) => {
			// eslint-disable-next-line no-console
			console.error('Error:', error);
		});
};

/**
 * Saves the authentication settings.
 *
 * @param {object} params - The parameters.
 * @param {string} params.username - The username.
 * @param {string} params.apiKey - The API key.
 * @param {string} params.avatarUrl - The avatar URL.
 * @returns {void}
 */
const handleSaveOptions = ({ username, apiKey, avatarUrl }) => {
	const formData = new FormData();

	formData.append('action', 'timestamps_save_authentication_settings');
	formData.append('username', username);
	formData.append('api_key', apiKey);
	formData.append('avatar_url', avatarUrl);
	formData.append('nonce', timestampsAdmin.nonce);

	apiFetch({
		method: 'POST',
		url: ajaxurl,
		body: formData,
	})
		.then((response) => {
			if (response.success) {
				window.location.reload();
			} else {
				throw new Error('API fetch failed');
			}
		})
		.catch((error) => {
			// eslint-disable-next-line no-console
			console.error('Error:', error);
		});
};

const handleError = (error) => {
	// eslint-disable-next-line no-console
	console.error(error);
	const errorMessage = document.createElement('p');
	errorMessage.textContent = sprintf(
		/* translators: %s: error message */
		__(`Error: %s. Refresh the page to continue.`, 'timestamps'),
		error.message,
	);
	errorMessage.style.color = 'red';
	const errorDiv = document.getElementById('timestamps-options-errors');
	errorDiv.appendChild(errorMessage);
};

/**
 * Handles the click event.
 *
 * @param {Event} event - The event.
 * @returns {void}
 */
const onClick = async (event) => {
	event.preventDefault();

	try {
		const from = encodeURIComponent(window.location.href);
		const url = `${PUBLIC_SCOREDETECT_URL}/signin?from=${from}`;

		handleDeleteOptions();

		window.open(url, 'Authenticate with ScoreDetect', 'width=600,height=600');

		window.addEventListener(
			'message',
			async (event) => {
				try {
					if (event.origin !== PUBLIC_SCOREDETECT_URL) {
						return;
					}

					if (decodeURIComponent(event.data?.from) !== decodeURIComponent(from)) {
						throw new Error('Invalid query');
					}

					if (!event.data?.api_key) {
						throw new Error(
							'Missing API key. Please make sure you are on the ScoreDetect Pro plan or higher',
						);
					}

					const apiKey = event.data?.api_key ?? '';

					if (!event.data?.session) {
						throw new Error('Missing session');
					}

					await authenticateSession(event.data?.session?.data?.session);

					const user = await getUser();

					const username = await getUsername(user);

					const avatarUrl = user.user_metadata?.avatar_url ?? '';

					handleSaveOptions({ username, apiKey, avatarUrl });
				} catch (error) {
					handleError(error);
				}
			},
			false,
		);
	} catch (error) {
		// eslint-disable-next-line no-console
		console.error(error);
		handleError(error);
	}
};

/**
 * Initializes the script.
 *
 * @returns {void}
 */
const init = () => {
	const timestampsAuthenticateButton = document.getElementById('timestamps-authenticate');

	if (!timestampsAuthenticateButton) {
		return;
	}

	timestampsAuthenticateButton.addEventListener('click', onClick);
};

/**
 * Initializes the script when the DOM is ready.
 */
domReady(init);
