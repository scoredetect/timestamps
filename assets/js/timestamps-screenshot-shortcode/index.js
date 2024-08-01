/* global sdcom_timestamps_screenshot, jQuery */

/**
 * WordPress dependencies.
 */
import domReady from '@wordpress/dom-ready';
import { __, sprintf } from '@wordpress/i18n';

/**
 * External dependencies.
 */
import html2canvas from 'html2canvas';
import jsPDF from 'jspdf'; // eslint-disable-line import/no-extraneous-dependencies
import autoTable from 'jspdf-autotable'; // eslint-disable-line import/no-extraneous-dependencies

/**
 * Internal dependencies.
 */
import { PUBLIC_SCOREDETECT_URL } from '../lib/constants';

/**
 * Handles the click event on the element.
 *
 * This function will take a screenshot of the webpage and create a PDF certificate.
 *
 * @param {HTMLButtonElement} btn - The button element.
 */
function onClick(btn) {
	const { body } = document;

	// Get the original button text.
	const originalText = btn.innerHTML;

	// Set the button to disabled and add a loading text to it.
	btn.disabled = true;

	// Prepare the loading text.
	const loadingDots = ['.', '..', '...'];
	let loadingIndex = 0;

	const updateLoadingText = () => {
		btn.innerHTML = `${__('Loading', 'timestamps')}${loadingDots[loadingIndex]}`;
		loadingIndex = (loadingIndex + 1) % loadingDots.length;
	};

	// Update the loading text every second.
	const loadingInterval = setInterval(updateLoadingText, 200);

	// Temporarily hide the WP admin bar, if it exists. It will be restored after the screenshot is taken.
	const adminBar = document.getElementById('wpadminbar');

	if (adminBar) {
		adminBar.style.display = 'none';
	}

	// Get the screenshot of the webpage.
	html2canvas(body).then(function (canvas) {
		canvas.toBlob(function (blob) {
			const file = new File(
				[blob],
				`${__('screenshot-ORIGINAL', 'timestamps')}-${new Date()
					.getTime()
					.toString()
					.slice(0, 10)}.png`,
				{ type: 'image/png' },
			);

			const screenshot = new Image();
			screenshot.src = URL.createObjectURL(blob);

			const formData = new FormData();
			formData.append('nonce', sdcom_timestamps_screenshot.nonce);
			formData.append('file', file);
			formData.append('action', 'sdcom_timestamps_screenshot');

			const a = document.createElement('a');
			const img = URL.createObjectURL(blob);
			a.href = img;
			a.download = file.name;

			// Append the anchor to body to ensure it can be clicked.
			document.body.appendChild(a);

			a.click();

			// Clean up by removing the anchor.
			document.body.removeChild(a);

			// Remove the canvas element.
			canvas.remove();

			// Restore the WP admin bar.
			if (adminBar) {
				adminBar.style.display = 'block';
			}

			try {
				// @todo remove jQuery dependency. Replace with a solution that allows for file uploads.
				jQuery.ajax({
					url: sdcom_timestamps_screenshot.ajaxurl,
					data: formData,
					processData: false,
					contentType: false,
					type: 'POST',
					success(data) {
						if (!data.success) {
							throw new Error(data.data.message);
						}

						const { certificate } = data.data;

						const certificateId = certificate.id;
						const filename = `${__('certificate', 'timestamps')}-${certificateId}.pdf`;
						const docTitle = __('Verification Certificate', 'timestamps');

						const doc = new jsPDF(); // eslint-disable-line new-cap

						// Get the page width and height.
						const pageWidth = doc.internal.pageSize.getWidth();
						const pageHeight = doc.internal.pageSize.getHeight();

						const base64Img = `${sdcom_timestamps_screenshot.plugin_dist_url}images/logo.png`;

						const headStyles = {
							fillColor: [0, 0, 0],
							textColor: [255, 255, 255],
							fontStyle: 'bold',
						};

						// Save the original console.warn function.
						const originalWarn = console.warn;

						// Override console.warn - suppress jspdf autotable warnings.
						console.warn = () => {
							return null;
						};

						let marginLeft = 0;

						// Setup PDF header.
						autoTable(doc, {
							willDrawPage(data) {
								// Header
								doc.setFontSize(20);
								if (base64Img) {
									doc.addImage(
										base64Img,
										'JPEG',
										data.settings.margin.left,
										15,
										10,
										10,
									);
								}
								// Update the margin left.
								marginLeft = data.settings.margin.left;

								doc.text(docTitle, data.settings.margin.left + 15, 22);
							},
							margin: { top: 30 },
							headStyles,
						});

						// Add a table for each certificate data field.
						autoTable(doc, {
							head: [['Current Time and Date']],
							body: [[new Date().toISOString()]],
							styles: { fontSize: 10 },
							headStyles,
						});

						if (certificateId) {
							autoTable(doc, {
								head: [['Certificate ID']],
								body: [[certificateId]],
								headStyles,
							});
						}

						if (certificate.created_at) {
							autoTable(doc, {
								head: [['Created at']],
								body: [[certificate.created_at]],
								headStyles,
							});
						}

						if (certificate.metadata?.certificateType) {
							autoTable(doc, {
								head: [['Certificate Type']],
								body: [[certificate.metadata?.certificateType]],
								headStyles,
							});
						}

						if (certificate.version) {
							autoTable(doc, {
								head: [['Version']],
								body: [[certificate.version]],
								headStyles,
							});
						}

						if (certificate.time_elapsed) {
							autoTable(doc, {
								head: [['Speed Created (milliseconds)']],
								body: [[certificate.time_elapsed]],
								headStyles,
							});
						}

						autoTable(doc, {
							head: [['Public Ledger URL']],
							body: [[`${PUBLIC_SCOREDETECT_URL}/certificate/${certificateId}`]],
							headStyles,
						});

						if (certificate.blockchain_transaction?.url) {
							autoTable(doc, {
								head: [['Public Blockchain URL']],
								body: [[certificate.blockchain_transaction?.url]],
								headStyles,
							});
						}

						autoTable(doc, {
							head: [['Hash']],
							body: [[`sha256:${certificate.blockchain_transaction_checksum}`]],
							headStyles,
						});

						autoTable(doc, {
							head: [['Public Ledger']],
							body: [
								[
									JSON.stringify(
										certificate.blockchain_transaction?.verificationCertificate,
									),
								],
							],
							headStyles,
						});

						autoTable(doc, {
							head: [['Raw Body']],
							body: [[JSON.stringify(certificate)]],
							headStyles,
						});

						// Add a new page.
						doc.addPage();

						// Assuming screenshot is an image element.
						const originalWidth = screenshot.width;
						const originalHeight = screenshot.height;

						// Calculate aspect ratio.
						const aspectRatio = originalWidth / originalHeight;

						// Determine new dimensions.
						let newWidth = pageWidth;
						let newHeight = pageHeight;

						// Adjust dimensions to maintain aspect ratio.
						if (newWidth / newHeight > aspectRatio) {
							newWidth = newHeight * aspectRatio;
						} else {
							newHeight = newWidth / aspectRatio;
						}

						// Add the sample screenshot image using the page dimensions.
						doc.addImage(screenshot, 'PNG', 0, 0, newWidth, newHeight);

						const totalPagesExp = doc.internal.getNumberOfPages();

						const setFooter = () => {
							const { pageSize } = doc.internal;
							const pageHeight = pageSize.height
								? pageSize.height
								: pageSize.getHeight();

							for (let i = 1; i <= totalPagesExp; i++) {
								doc.setPage(i);
								doc.text(
									sprintf(
										/* translators: %1$s: current page number, %2$s: total number of pages */
										__('Page %1$s of %2$s', 'timestamps'),
										i,
										totalPagesExp,
									),
									marginLeft,
									pageHeight - 10,
								);
							}
						};

						// Add a watermark indicating the image is a reference only.
						doc.setPage(3);
						doc.setTextColor(150);

						const watermarkText = __(
							'Reference only. Valid original image is downloaded separately for security purposes.',
							'timestamps',
						);

						// Calculate the center position.
						const centerX = pageWidth / 2 + 10;
						const centerY = pageHeight / 2 + 10;

						// Set the text alignment to center.
						doc.text(watermarkText, centerX, centerY, { align: 'center', angle: 30 });

						// Setup PDF footer.
						autoTable(doc, {
							didDrawPage(data) {
								doc.setFontSize(10);
								setFooter({ data });
							},
							margin: { top: 30 },
						});

						// Restore the original console.warn function.
						console.warn = originalWarn;

						// Save the PDF and reset the button.
						try {
							// Save the PDF.
							doc.save(filename);

							// Set the button to enabled and add the original text to it.
							btn.disabled = false;

							// Stop the interval when loading is complete.
							clearInterval(loadingInterval);

							btn.innerHTML = originalText;
						} catch {
							console.error('Error saving PDF');
						}
					},
				});
			} catch (error) {
				console.error(error);
			}
		}, 'image/png');
	});
}

/**
 * Initializes the script.
 *
 * @returns {void}
 */
const init = () => {
	const btn = document.getElementById('sdcom-timestamps-screenshot');

	if (!btn) {
		return;
	}

	btn.addEventListener('click', function () {
		onClick(btn);
	});
};

/**
 * Initializes the script when the DOM is ready.
 */
domReady(init);
