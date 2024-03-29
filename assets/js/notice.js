/**
 * WordPress dependencies.
 */
import apiFetch from '@wordpress/api-fetch';
import domReady from '@wordpress/dom-ready';

/**
 * Window dependencies.
 */
const { timestampsAdmin, ajaxurl } = window;

/**
 * Initialize.
 *
 * @returns {void}
 */
const init = () => {
	const notices = document.querySelectorAll('.notice[data-timestamps-notice]');

	/**
	 * Handle clicking in the plugin notice.
	 *
	 * If the click target is the dismiss button send an AJAX request to remember
	 * the dismissal.
	 *
	 * @param {Event} event Click event.
	 * @returns {void}
	 */
	const onClick = (event) => {
		/**
		 * Only proceed if we're clicking dismiss.
		 */
		if (!event.target.classList.contains('notice-dismiss')) {
			return;
		}

		/**
		 * Handler is admin-ajax.php, so the body needs to be form data.
		 */
		const formData = new FormData();

		formData.append('action', 'timestamps_notice_dismiss');
		formData.append('notice', event.currentTarget.dataset.timestampsNotice);
		formData.append('nonce', timestampsAdmin.nonce);

		apiFetch({
			method: 'POST',
			url: ajaxurl,
			body: formData,
		});
	};

	/**
	 * Bind click events to notices.
	 */
	for (const notice of notices) {
		notice.addEventListener('click', onClick);
	}
};

/**
 * Initialize.
 */
domReady(init);
