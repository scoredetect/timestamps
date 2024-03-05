/**
 * WordPress dependencies.
 */
import domReady from '@wordpress/dom-ready';
import { __, sprintf } from '@wordpress/i18n';

const handleDisplayCreatedByChecked = ({ element, checked }) => {
	const username = document.getElementById('username');

	function runChecked() {
		// add is-displayCreatedBy class to all .timestamps-options__profile
		const profile = document.querySelectorAll('.timestamps-options__profile');
		profile.forEach((_element) => {
			_element.classList.add('is-displayCreatedBy');
		});

		const profileAuthor = document.querySelectorAll('.timestamps-options__profileAuthor');
		profileAuthor.forEach((_element) => {
			// set text to 'anonymous'
			_element.textContent = sprintf(__('by %s', 'timestamps'), username.value);
		});
	}
	function runUnchecked() {
		// remove is-displayCreatedBy class to all .timestamps-options__profile
		const profile = document.querySelectorAll('.timestamps-options__profile');
		profile.forEach((_element) => {
			_element.classList.remove('is-displayCreatedBy');
		});

		const profileAuthor = document.querySelectorAll('.timestamps-options__profileAuthor');
		profileAuthor.forEach((_element) => {
			// set text to 'anonymous'
			_element.textContent = sprintf(__('by %s', 'timestamps'), 'anonymous');
		});
	}

	if (checked) {
		runChecked();
	} else {
		runUnchecked();
	}

	element.addEventListener('change', (event) => {
		if (event.target.checked) {
			runChecked();
		} else {
			runUnchecked();
		}
	});
};

/**
 * Initializes the script.
 *
 * @returns {void}
 */
const init = () => {
	const timestampsOptions = document.getElementById('timestamps-options');

	if (!timestampsOptions) {
		return;
	}

	const displayCreatedBy = document.getElementById('display_created_by');

	if (!displayCreatedBy) {
		return;
	}

	/**
	 * Load initial animation.
	 *
	 * `.js-anim-hidden-init` class to hide elements.
	 * `.js-anim-show-init` class to show elements.
	 *
	 * Example: class="js-anim-show-init js-anim-hidden-init"
	 */
	const show = document.querySelectorAll('.js-anim-hidden-init');
	show.forEach((_element) => {
		// remove hidden class
		_element.classList.remove('js-anim-hidden-init');
	});

	handleDisplayCreatedByChecked({ element: displayCreatedBy, checked: displayCreatedBy.checked });
};

/**
 * Initializes the script when the DOM is ready.
 */
domReady(init);
