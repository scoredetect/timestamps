<?php
/**
 * Screenshot feature.
 *
 * @since 1.5.0
 * @package  SDCOM_Timestamps
 */

namespace SDCOM_Timestamps\Feature\Timestamp;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Utils as Psr7Utils;
use SDCOM_Timestamps\Feature;
use SDCOM_Timestamps\Utils;

use function SDCOM_Timestamps\Utils\get_plugin_option;

/**
 * Screenshot feature class
 */
class Screenshot extends Feature {

	/**
	 * The shortcode name.
	 *
	 * @var string
	 */
	protected $shortcode = 'timestamps_screenshot';

	/**
	 * Initialize feature setting it's config
	 *
	 * @since 1.5.0
	 */
	public function __construct() {
		$this->slug = 'screenshot_timestamp';

		$this->title = esc_html__( 'Screenshot Timestamp', 'timestamps' );

		$this->summary = __( 'Create a timestamp from a browser screenshot of the current page.', 'timestamps' );

		$this->docs_url = __( 'https://docs.scoredetect.com/', 'timestamps' );

		parent::__construct();
	}

	/**
	 * We need to delay setup up until init to ensure all plugins are loaded.
	 *
	 * @since 1.5.0
	 */
	public function setup() {
		add_action( 'init', array( $this, 'setup_init' ) );
	}

	/**
	 * Setup feature on each page load.
	 *
	 * @since 1.5.0
	 */
	public function setup_init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ) );
		add_shortcode( $this->shortcode, array( $this, 'shortcode' ) );
		add_action( 'wp_ajax_sdcom_timestamps_screenshot', array( $this, 'ajax_sdcom_timestamps_screenshot' ) );
		add_action( 'wp_ajax_nopriv_sdcom_timestamps_screenshot', array( $this, 'ajax_sdcom_timestamps_screenshot' ) );
		add_action( 'wp_ajax_sdcom_timestamps_screenshot_generate_certificate_id', array( $this, 'ajax_sdcom_timestamps_screenshot_generate_certificate_id' ) );
		add_action( 'wp_ajax_nopriv_sdcom_timestamps_screenshot_generate_certificate_id', array( $this, 'ajax_sdcom_timestamps_screenshot_generate_certificate_id' ) );
	}

	/**
	 * Enqueue scripts.
	 *
	 * @since 1.5.0
	 */
	public function scripts() {

		// Bail early if the shortcode is not present.
		if ( ! has_shortcode( get_the_content(), $this->shortcode ) ) {
			return;
		}

		wp_enqueue_script(
			'timestamps-screenshot-shortcode',
			SDCOM_TIMESTAMPS_URL . '/dist/js/timestamps-screenshot-shortcode.js',
			array_merge( Utils\get_asset_info( 'timestamps-screenshot-shortcode', 'dependencies' ), [ 'wp-util' ] ),
			Utils\get_asset_info( 'timestamps-screenshot-shortcode', 'version' ),
			true
		);

		wp_set_script_translations( 'timestamps-screenshot-shortcode', 'timestamps' );

		wp_localize_script(
			'timestamps-screenshot-shortcode',
			'sdcom_timestamps_screenshot',
			array(
				'nonce'                         => wp_create_nonce( 'sdcom_timestamps_screenshot' ),
				'generate_certificate_id_nonce' => wp_create_nonce( 'sdcom_timestamps_screenshot_generate_certificate_id' ),
				'ajaxurl'                       => admin_url( 'admin-ajax.php' ),
				'plugin_dist_url'               => SDCOM_TIMESTAMPS_DIST_URL,
			)
		);
	}

	/**
	 * AJAX handler for the screenshot timestamp.
	 *
	 * @since 1.5.0
	 */
	public function ajax_sdcom_timestamps_screenshot() {

		// Check the nonce.
		check_ajax_referer( 'sdcom_timestamps_screenshot', 'nonce' );

		$file = $_FILES['file'];

		// Bail early if the file is not set.
		if ( empty( $file ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'The file is not set.', 'timestamps' ),
				)
			);
		}

		// Bail early if the filetype is not allowed.
		if ( ! wp_check_filetype( basename( $_FILES['file']['tmp_name'] ) ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'The file type is not allowed.', 'timestamps' ),
				)
			);
		}

		// Bail early if the file is not an image.
		if ( ! @getimagesize( $_FILES['file']['tmp_name'] ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'The file is not an image.', 'timestamps' ),
				)
			);
		}

		$id = ! empty( sanitize_text_field( wp_unslash( $_POST['id'] ) ) ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';

		// Bail early if the API key is not set.
		$timestamps_api_key = get_plugin_option( 'api_key', '' );
		if ( empty( $timestamps_api_key ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'The API key is not set.', 'timestamps' ),
				)
			);
		}

		$file = file_get_contents( $_FILES['file']['tmp_name'] );

		$create_certificate = $this->create_certificate( $file, $id );

		// Handle the case where the method returned false.
		if ( $create_certificate === false ) {
			throw new \Exception( 'Create certificate failed.' );
		}

		$certificate_id = ! empty( $create_certificate->{'certificate'}->{'id'} ) ? $create_certificate->{'certificate'}->{'id'} : '';

		// Handle the case where the certificate id is empty.
		if ( empty( $certificate_id ) ) {
			throw new \Exception( 'Certificate id is empty.' );
		}

		$update_certificate = $this->update_certificate( $certificate_id );

		// Handle the case where the method returned false.
		if ( $update_certificate === false ) {
			throw new \Exception( 'Update certificate failed.' );
		}

		// Bail early if the certificate id is empty.
		if ( empty( $certificate_id ) ) {
			throw new \Exception( 'Certificate id is empty.' );
		}

		$get_certificate = $this->get_certificate( $certificate_id );

		$data = [
			'certificate' => $get_certificate,
		];

		return wp_send_json_success( $data );
	}

	/**
	 * AJAX handler for generating the screenshot timestamp hash.
	 *
	 * @since 1.5.0
	 */
	public function ajax_sdcom_timestamps_screenshot_generate_certificate_id() {

		// Check the nonce.
		check_ajax_referer( 'sdcom_timestamps_screenshot_generate_certificate_id', 'nonce' );

		// Bail early if the API key is not set.
		$timestamps_api_key = get_plugin_option( 'api_key', '' );
		if ( empty( $timestamps_api_key ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'The API key is not set.', 'timestamps' ),
				)
			);
		}

		$generate_certificate_id = $this->generate_certificate_id();

		// Handle the case where the method returned false.
		if ( $generate_certificate_id === false ) {
			throw new \Exception( 'Generate checksum failed.' );
		}

		if ( empty( $generate_certificate_id->{'uuid'} ) ) {
			throw new \Exception( 'UUID is empty.' );
		}

		$uuid = $generate_certificate_id->{'uuid'} ?? '';

		$data = [
			'uuid' => $uuid,
		];

		return wp_send_json_success( $data );
	}

	/**
	 * Outputs the shortcode for the feature.
	 *
	 * @since 1.5.0
	 *
	 * @see includes/blocks/timestamp-post/markup.php for the original code.
	 *
	 * @return string The shortcode output.
	 */
	public function shortcode(): string {
		ob_start();

		printf(
			'<button id="sdcom-timestamps-screenshot" class="sdcom-timestamps-screenshot">%s</button>',
			_x( 'Create Timestamp', 'timestamps screenshot button', 'timestamps' )
		);

		return ob_get_clean();
	}

	/**
	 * Creates a certificate for a file.
	 *
	 * @since 1.5.0
	 *
	 * @param File   $file The file to create a certificate for.
	 * @param string $id The id of the certificate to create. Optional.
	 * @return object|false The data returned by the API on success, or false on failure.
	 * @throws \Throwable If an exception occurs during the process.
	 * @throws \Exception If the options, file, or API key is empty.
	 */
	private function create_certificate( $file, $id = '' ) {
		try {
			$sdcom_timestamps = get_option( SDCOM_TIMESTAMPS_OPTIONS );

			// Bail early if the options are empty.
			if ( empty( $sdcom_timestamps ) ) {
				throw new \Exception( 'Options are empty.' );
			}

			// Bail early if the file is empty.
			if ( empty( $file ) ) {
				throw new \Exception( 'File is empty.' );
			}

			$sdcom_timestamps_display_created_by = ! empty( $sdcom_timestamps['display_created_by'] ) ? $sdcom_timestamps['display_created_by'] : false;
			$sdcom_timestamps_username           = 'anonymous';
			$sdcom_timestamps_api_key            = ! empty( $sdcom_timestamps['api_key'] ) ? $sdcom_timestamps['api_key'] : '';

			// Bail early if the api key is empty.
			if ( empty( $sdcom_timestamps_api_key ) ) {
				throw new \Exception( 'API key is empty.' );
			}

			if ( $sdcom_timestamps_display_created_by === 'true' ) {
				$sdcom_timestamps_username = ! empty( $sdcom_timestamps['username'] ) ? $sdcom_timestamps['username'] : 'anonymous';
			}

			$url = 'https://api.scoredetect.com/create-certificate';

			$metadata = array(
				'certificateType'  => 'file_upload',
				'displayCreatedBy' => $sdcom_timestamps_display_created_by === 'true' ? true : false,
				'username'         => $sdcom_timestamps_username,
			);

			$client = new Client();

			// Convert the file content to a stream.
			$file_stream = Psr7Utils::streamFor( $file );

			// Prepare the multipart form data.
			$multipart = [
				[
					'name'     => 'file',
					'contents' => $file_stream,

					// Specify any filename as this is required, however not vital to the operation.
					'filename' => 'screenshot.png',
				],
				[
					'name'     => 'username',
					'contents' => $metadata['username'],
				],
			];

			if ( ! empty( $id ) ) {
				$multipart[] = [
					'name'     => 'id',
					'contents' => $id,
				];
			}

			// Send the POST request.
			$request = $client->post(
				$url,
				[
					'timeout'   => 30,
					'headers'   => [
						'Authorization' => 'Bearer ' . $sdcom_timestamps_api_key,
					],
					'multipart' => $multipart,
				]
			);

			// Handle the response.
			$response_body = $request->getBody()->getContents();

			/**
			 * Retrieve and parse the contents of the API response, which is JSON.
			 */
			$content = $response_body;
			$data    = json_decode( $content );

			/**
			 * Detect any issues with decoding the JSON string into a PHP object.
			 */
			if ( empty( $data ) ) {
				throw new \Exception( 'Data is empty' );
			}

			// Return the data.
			return $data;

		} catch ( \Throwable $th ) {
			throw $th;
		}
	}

	/**
	 * Generates a unique certificate ID for use.
	 *
	 * @since 1.5.0
	 *
	 * @return object|false The data returned by the API on success, or false on failure.
	 * @throws \Throwable If an exception occurs during the process.
	 * @throws \Exception If the options, file, or API key is empty.
	 */
	private function generate_certificate_id() {
		try {
			$sdcom_timestamps = get_option( SDCOM_TIMESTAMPS_OPTIONS );

			// Bail early if the options are empty.
			if ( empty( $sdcom_timestamps ) ) {
				throw new \Exception( 'Options are empty.' );
			}

			$sdcom_timestamps_api_key = ! empty( $sdcom_timestamps['api_key'] ) ? $sdcom_timestamps['api_key'] : '';

			// Bail early if the api key is empty.
			if ( empty( $sdcom_timestamps_api_key ) ) {
				throw new \Exception( 'API key is empty.' );
			}

			$url = 'https://api.scoredetect.com/generate-certificate-id';

			$client = new Client();

			// Send the POST request.
			$request = $client->post(
				$url,
				[
					'timeout' => 30,
					'headers' => [
						'Authorization' => 'Bearer ' . $sdcom_timestamps_api_key,
					],
				]
			);

			// Handle the response.
			$response_body = $request->getBody()->getContents();

			/**
			 * Retrieve and parse the contents of the API response, which is JSON.
			 */
			$content = $response_body;
			$data    = json_decode( $content );

			/**
			 * Detect any issues with decoding the JSON string into a PHP object.
			 */
			if ( empty( $data ) ) {
				throw new \Exception( 'Data is empty' );
			}

			// Return the data.
			return $data;

		} catch ( \Throwable $th ) {
			throw $th;
		}
	}

	/**
	 * Updates a certificate.
	 *
	 * @since 1.5.0
	 *
	 * @param string $certificate_id The id of the certificate to update.
	 * @return object|false The data returned by the API on success, or false on failure.
	 * @throws \Exception If the certificate id, options, or API key is empty.
	 * @throws \Throwable If an exception occurs during the process.
	 */
	private function update_certificate( $certificate_id ) {
		try {
			$sdcom_timestamps = get_option( SDCOM_TIMESTAMPS_OPTIONS );

			// Bail early if the certificate id is empty.
			if ( empty( $certificate_id ) ) {
				throw new \Exception( 'Certificate id is empty.' );
			}

			// Bail early if the options are empty.
			if ( empty( $sdcom_timestamps ) ) {
				throw new \Exception( 'Options are empty.' );
			}

			$sdcom_timestamps_display_created_by = ! empty( $sdcom_timestamps['display_created_by'] ) ? $sdcom_timestamps['display_created_by'] : false;
			$sdcom_timestamps_username           = 'anonymous';
			$sdcom_timestamps_api_key            = ! empty( $sdcom_timestamps['api_key'] ) ? $sdcom_timestamps['api_key'] : '';

			// Bail early if the api key is empty.
			if ( empty( $sdcom_timestamps_api_key ) ) {
				throw new \Exception( 'API key is empty.' );
			}

			if ( $sdcom_timestamps_display_created_by === 'true' ) {
				$sdcom_timestamps_username = ! empty( $sdcom_timestamps['username'] ) ? $sdcom_timestamps['username'] : $sdcom_timestamps_username;
			}

			$url = 'https://api.scoredetect.com/update-certificate';

			$metadata = array(
				'certificateType'  => 'file_upload',
				'displayCreatedBy' => $sdcom_timestamps_display_created_by === 'true' ? true : false,
				'username'         => $sdcom_timestamps_username,
			);

			$body = wp_json_encode(
				array(
					'certificateId' => $certificate_id,
					'metadata'      => $metadata,
				)
			);

			$request = wp_remote_request(
				$url,
				array(
					'method'  => 'PATCH',
					'timeout' => 30,
					'headers' => array(
						'Authorization' => 'Bearer ' . $sdcom_timestamps_api_key,
						'Content-Type'  => 'application/json',
					),
					'body'    => $body,
				)
			);

			if ( is_wp_error( $request ) ) {
				throw new \Exception( 'WP Error' );
			}

			/**
			 * Ensure we have a successful response code.
			 */
			if ( 200 !== wp_remote_retrieve_response_code( $request ) ) {
				throw new \Exception( 'Response code is not 200' );
			}

			/**
			 * Retrieve and parse the contents of the API response, which is JSON.
			 */
			$content = wp_remote_retrieve_body( $request );
			$data    = json_decode( $content );

			/**
			 * Detect any issues with decoding the JSON string into a PHP object.
			 */
			if ( empty( $data ) ) {
				throw new \Exception( 'Data is empty' );
			}

			// Return the data.
			return $data;

		} catch ( \Throwable $th ) {
			throw $th;
		}
	}

	/**
	 * Gets a certificate.
	 *
	 * @since 1.5.0
	 *
	 * @param string $certificate_id The id of the certificate to get.
	 * @return object|false The data returned by the API on success, or false on failure.
	 */
	private function get_certificate( $certificate_id ) {
		try {
			$sdcom_timestamps = get_option( SDCOM_TIMESTAMPS_OPTIONS );

			// Bail early if the certificate id is empty.
			if ( empty( $certificate_id ) ) {
				throw new \Exception( 'Certificate id is empty.' );
			}

			// Bail early if the options are empty.
			if ( empty( $sdcom_timestamps ) ) {
				throw new \Exception( 'Options are empty.' );
			}

			$sdcom_timestamps_api_key = ! empty( $sdcom_timestamps['api_key'] ) ? $sdcom_timestamps['api_key'] : '';

			// Bail early if the api key is empty.
			if ( empty( $sdcom_timestamps_api_key ) ) {
				throw new \Exception( 'API key is empty.' );
			}

			$url = 'https://api.scoredetect.com/get-certificate/?id=' . $certificate_id;

			$request = wp_remote_get(
				$url,
				array(
					'timeout' => 30,
					'headers' => array(
						'Authorization' => 'Bearer ' . $sdcom_timestamps_api_key,
					),
				)
			);

			if ( is_wp_error( $request ) ) {
				throw new \Exception( 'WP Error' );
			}

			/**
			 * Ensure we have a successful response code.
			 */
			if ( 200 !== wp_remote_retrieve_response_code( $request ) ) {
				throw new \Exception( 'Response code is not 200' );
			}

			/**
			 * Retrieve and parse the contents of the API response, which is JSON.
			 */
			$content = wp_remote_retrieve_body( $request );
			$data    = json_decode( $content );

			/**
			 * Detect any issues with decoding the JSON string into a PHP object.
			 */
			if ( empty( $data ) ) {
				throw new \Exception( 'Data is empty' );
			}

			// Return the data.
			return $data;

		} catch ( \Throwable $th ) {
			throw $th;
		}
	}
}
