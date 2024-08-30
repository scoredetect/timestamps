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
import QRCode from 'easyqrcodejs'; // eslint-disable-line import/no-extraneous-dependencies

/**
 * Internal dependencies.
 */
import { PUBLIC_SCOREDETECT_URL } from '../lib/constants';

/**
 * Displays a HUD with the certificate ID and a QR code.
 *
 * @param {string} certificateId - The certificate ID.
 *
 * @returns {void}
 */
function displayHud({ certificateId = '' }) {
	// Create a canvas element.
	const canvas = document.createElement('canvas');
	canvas.id = 'sdcom_timestamps_hud';
	canvas.width = 300;
	canvas.height = 300;
	canvas.style.position = 'absolute';
	canvas.style.top = '0';
	canvas.style.right = '0';
	canvas.style.zIndex = '999999999'; // Force to be on top.
	canvas.style.backgroundColor = 'white';
	canvas.style.border = '2px solid black';
	canvas.style.padding = '10px';
	canvas.style.fontFamily = 'Arial';
	canvas.style.fontSize = '12px';

	// Get the canvas context.
	const ctx = canvas.getContext('2d');

	const maxWidth = canvas.width - 20; // Set a maximum width for the text.
	const lineHeight = 12; // Set the line height.

	// Create QR Code HTML image element.
	const qrCodeSize = 200;
	const qrCodeId = 'qrcode';

	const qrCodeElement = new Image();
	qrCodeElement.width = qrCodeSize;
	qrCodeElement.height = qrCodeSize;
	qrCodeElement.id = qrCodeId;

	document.body.appendChild(qrCodeElement);

	document.getElementById(qrCodeId).style.display = 'none';

	const publicLedgerUrl = `${PUBLIC_SCOREDETECT_URL}/certificate/${certificateId}`;

	// Create QR Code Object.
	const qrcode = new QRCode(document.getElementById(qrCodeId), {
		text: publicLedgerUrl,
	});

	// Get QR Code Data URL.
	const qrCodeDataUrl = qrcode._el.childNodes[0].toDataURL('image/png');

	qrCodeElement.src = qrCodeDataUrl;

	// Draw the QR code on the canvas.
	qrCodeElement.onload = () => {
		ctx.drawImage(qrCodeElement, 50, 55, canvas.width - 100, canvas.height - 100);
	};

	// Remove the QR Code element from the DOM.
	document.body.removeChild(qrCodeElement);

	// Wrap the text. Defaults to every 50 characters.
	function wrapText(context, text, x, y, maxWidth, lineHeight, maxChars = 50) {
		const lines = [];
		for (let i = 0; i < text.length; i += maxChars) {
			lines.push(text.substring(i, i + maxChars));
		}

		for (let k = 0; k < lines.length; k++) {
			context.fillText(lines[k], x, y + k * lineHeight);
		}
	}

	ctx.fillStyle = 'black';
	ctx.textAlign = 'center';
	ctx.font = '20px Arial';

	wrapText(
		ctx,
		__('Blockchain Safety', 'timestamps'),
		canvas.width / 2,
		45,
		maxWidth,
		lineHeight,
		30,
	);

	ctx.font = '12px Arial';

	// Display the date and time to the bottom but inside the square.
	const now = new Date();
	const dateTimeString = `${now.toUTCString()} UTC+0`;
	wrapText(ctx, dateTimeString, canvas.width / 2, canvas.height - 25, maxWidth, lineHeight, 40);

	// Display the certificate ID to the bottom but inside the square.
	wrapText(ctx, certificateId, canvas.width / 2, canvas.height - 10, maxWidth, lineHeight, 40);

	// Display the canvas element.
	document.body.appendChild(canvas);
}

/**
 * Removes the HUD from the DOM.
 *
 * @returns {void}
 */
function removeHud() {
	const hud = document.getElementById('sdcom_timestamps_hud');
	if (hud) {
		hud.remove();
	}
}

/**
 * Handles the screenshot and creates a PDF certificate.
 *
 * @param {HTMLElement} body - The body element.
 * @param {HTMLButtonElement} btn - The button element.
 * @param {string} certificateId - The certificate ID.
 *
 * @returns {void}
 */
function handleScreenshot({ body, btn, certificateId }) {
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

			if (certificateId) {
				formData.append('id', certificateId);
			}

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

			// Remove the HUD.
			removeHud();

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
						const publicLedgerUrl = `${PUBLIC_SCOREDETECT_URL}/certificate/${certificateId}`;

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
							body: [[publicLedgerUrl]],
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

						// Draw a black rectangle around the image.
						doc.setLineWidth(1);
						doc.setDrawColor(0, 0, 0);
						doc.rect(0, 0, newWidth, newHeight).stroke();

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
						doc.text(watermarkText, centerX, centerY, {
							align: 'center',
							angle: 30,
						});

						// Setup PDF footer.
						autoTable(doc, {
							didDrawPage() {
								doc.setFontSize(10);
								setFooter();
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
 * Handles the click event on the element.
 *
 * This function will take a screenshot of the webpage and create a PDF certificate.
 *
 * @param {HTMLButtonElement} btn - The button element.
 */
function onClick(btn) {
	const formDataCertificateId = new FormData();
	formDataCertificateId.append(
		'nonce',
		sdcom_timestamps_screenshot.generate_certificate_id_nonce,
	);
	formDataCertificateId.append('action', 'sdcom_timestamps_screenshot_generate_certificate_id');

	try {
		fetch(sdcom_timestamps_screenshot.ajaxurl, {
			method: 'POST',
			body: formDataCertificateId,
		})
			.then((response) => response.json())
			.then((data) => {
				if (!data.success) {
					throw new Error(data.data.message);
				}

				const { uuid } = data.data;

				const certificateId = uuid;

				// Display the HUD.
				displayHud({
					certificateId,
				});

				// Wait a bit for the HUD to be displayed before taking the screenshot.
				setTimeout(() => {
					handleScreenshot({ body: document.body, btn, certificateId: uuid });
				}, 1);
			})
			.catch((error) => {
				console.error(error);
			});
	} catch (error) {
		console.error(error);
	}
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
