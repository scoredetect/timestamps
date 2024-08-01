<?php
/**
 * Timestamp feature.
 *
 * @since 1.0.0
 * @package  SDCOM_Timestamps
 */

namespace SDCOM_Timestamps\Feature\Timestamp;

use SDCOM_Timestamps\Feature;
use SDCOM_Timestamps\Utils;

use function SDCOM_Timestamps\Utils\get_plugin_option;
use function SDCOM_Timestamps\Utils\get_timestamp_post_types;

/**
 * Timestamp feature class
 */
class Timestamp extends Feature {

	/**
	 * Post meta.
	 *
	 * This array contains the meta keys and their corresponding data types.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	protected $post_meta = [
		'sdcom_timestamp_post'          => 'boolean',
		'sdcom_previous_certificate_id' => 'string',
	];

	/**
	 * Initialize feature setting it's config
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->slug = 'timestamp';

		$this->title = esc_html__( 'Post Timestamp', 'timestamps' );

		$this->summary = __( 'Timestamps are a way to prove that you’ve published your content at a certain time.', 'timestamps' );

		$this->docs_url = __( 'https://docs.scoredetect.com/', 'timestamps' );

		parent::__construct();
	}

	/**
	 * We need to delay setup up until init to ensure all plugins are loaded.
	 *
	 * @since 1.0.0
	 */
	public function setup() {
		add_action( 'init', array( $this, 'setup_init' ) );
	}

	/**
	 * Setup feature on each page load.
	 *
	 * @since 1.0.0
	 */
	public function setup_init() {
		add_action( 'init', array( $this, 'register_post_meta' ), 20 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
		add_action( 'post_submitbox_misc_actions', array( $this, 'output_setting' ) );
		add_action( 'edit_post', array( $this, 'save_post_meta' ), 10, 2 );
		add_action( 'rest_insert_post', array( $this, 'save_post_meta_rest' ), 10, 2 );
		add_filter( 'is_protected_meta', array( $this, 'is_protected_meta' ), 10, 3 );
		add_shortcode( 'timestamps', array( $this, 'shortcode' ) );
	}

	/**
	 * Registers post meta for the feature.
	 *
	 * This function iterates over the $post_meta array, where each key-value pair represents
	 * a meta key and its corresponding type. It then registers each meta key with WordPress
	 * using the register_post_meta function.
	 *
	 * The 'show_in_rest' option is set to true, which means this meta key-value pair will be
	 * included in the REST API responses. The 'single' option is also set to true, indicating
	 * that a single key-value pair will be returned for the meta key.
	 *
	 * The 'auth_callback' option is set to a function that checks if the current user has the
	 * 'manage_options' capability. This means only users with this capability will be able to
	 * modify the meta key-value pair.
	 */
	public function register_post_meta() {
		foreach ( $this->post_meta as $meta_key => $data_type ) {

			// Register the post meta.
			register_post_meta(
				'',
				$meta_key,
				array(
					'show_in_rest'  => true,
					'single'        => true,
					'type'          => $data_type,
					'auth_callback' => function () {
						return current_user_can( 'manage_options' );
					},
				)
			);
		}
	}

	/**
	 * Enqueue block editor assets.
	 */
	public function enqueue_block_editor_assets() {
		global $post;

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		if ( ! current_user_can( 'manage_options', $post ) ) {
			return;
		}

		// Bail early if $post is not a valid post type.
		$post_types = get_timestamp_post_types();
		if ( ! in_array( $post->post_type, $post_types, true ) ) {
			return;
		}

		wp_enqueue_script(
			'timestamp-post-editor',
			SDCOM_TIMESTAMPS_URL . '/dist/js/timestamp-post.js',
			Utils\get_asset_info( 'timestamp-post', 'dependencies' ),
			Utils\get_asset_info( 'timestamp-post', 'version' ),
			true
		);

		wp_set_script_translations( 'timestamp-post-editor', 'timestamps' );

		$timestamps_api_key = get_plugin_option( 'api_key', '' );

		wp_localize_script(
			'timestamp-post-editor',
			'timestamps',
			array(
				'api_key' => $timestamps_api_key,
			)
		);
	}

	/**
	 * Outputs the shortcode for the feature.
	 *
	 * @see includes/blocks/timestamp-post/markup.php for the original code.
	 *
	 * @return string The shortcode output.
	 */
	public function shortcode(): string {
		ob_start();

		$sdcom_previous_certificate_id = get_post_meta( get_the_ID(), 'sdcom_previous_certificate_id', true );

		// Bail early if there is no embed_code.
		if ( empty( $sdcom_previous_certificate_id ) ) {
			return '';
		}

		printf(
			'<div class="sdcom-timestamps" data-id="%s"></div>',
			esc_attr( $sdcom_previous_certificate_id )
		);

		return ob_get_clean();
	}

	/**
	 * Outputs the checkbox field.
	 *
	 * @param WP_POST $post Post object.
	 */
	public function output_setting( $post ) {

		$post_types = get_timestamp_post_types();
		if ( ! in_array( $post->post_type, $post_types, true ) ) {
			return;
		}

		$post_type_object = get_post_type_object( $post->post_type );
		$post_type_label  = strtolower( $post_type_object->labels->singular_name );
		?>
		<div class="misc-pub-section">
			<input id="sdcom_timestamp_post" name="sdcom_timestamp_post" type="checkbox" value="1" <?php checked( get_post_meta( get_the_ID(), 'sdcom_timestamp_post', true ) ); ?>>
			<label for="sdcom_timestamp_post">
				<?php
				printf(
					/* translators: %s: post type name */
					wp_kses_post( __( 'Enable timestamps for this %s', 'timestamps' ) ),
					wp_kses_post( $post_type_label ),
				);
				?>
			</label>
			<p class="howto"><?php esc_html_e( 'Timestamps prove that you keep your content regularly up-to-date.', 'timestamps' ); ?></p>
			<?php wp_nonce_field( 'save-timestamp-post', 'sdcom-timestamp-post-nonce' ); ?>
		</div>
		<?php
	}

	/**
	 * Saves the post meta in Classic Editor mode.
	 *
	 * @param int     $post_id The post ID.
	 * @param WP_Post $post Post object.
	 *
	 * @uses update_post_meta() - Updates the value of an existing meta key in the database.
	 * @uses delete_post_meta() - Deletes the value of an existing meta key in the database.
	 * @uses wp_update_post() - Updates the post in the database.
	 *
	 * @throws \Exception If an error occurs during the process.
	 */
	public function save_post_meta( $post_id, $post ) {

		if ( wp_doing_ajax() ) {
			return;
		}

		if ( ! isset( $_POST['sdcom-timestamp-post-nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['sdcom-timestamp-post-nonce'] ), 'save-timestamp-post' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['sdcom_timestamp_post'] ) ) {
			try {
				update_post_meta( $post_id, 'sdcom_timestamp_post', true );

				$create_certificate = $this->create_certificate_post( $post );

				// Handle the case where the method returned false.
				if ( $create_certificate === false ) {
					throw new \Exception( 'Create certificate failed.' );
				}

				$certificate_id = ! empty( $create_certificate->{'certificate'}->{'id'} ) ? $create_certificate->{'certificate'}->{'id'} : '';

				// Handle the case where the certificate id is empty.
				if ( empty( $certificate_id ) ) {
					throw new \Exception( 'Certificate id is empty.' );
				}

				$update_certificate = $this->update_certificate_post( $post, $certificate_id );

				// Handle the case where the method returned false.
				if ( $update_certificate === false ) {
					throw new \Exception( 'Update certificate failed.' );
				}

				// Bail early if the certificate id is empty.
				if ( empty( $certificate_id ) ) {
					throw new \Exception( 'Certificate id is empty.' );
				}

				// Update the post meta with the new certificate id.
				update_post_meta( $post_id, 'sdcom_previous_certificate_id', $certificate_id );

				// update the content to set the data-id to the new certificate id.
				$pattern = '/<div class="sdcom-timestamps" data-id="(.*?)">.*<\/div>/';
				if ( preg_match( $pattern, $post->post_content, $matches ) ) {
					$current_certificate_id = $matches[1];

					// Check if the current certificate id is not the new certificate id
					if ( $current_certificate_id !== $certificate_id ) {
						// Update the post content to replace the previous certificate id
						$replacement = sprintf(
							'<div class="sdcom-timestamps" data-id="%s"></div>',
							esc_attr( $certificate_id )
						);

						$post->post_content = preg_replace( $pattern, $replacement, $post->post_content );

						wp_update_post( $post );
					}

					return;
				}
			} catch ( \Exception $e ) {
				// Handle the exception
				error_log( 'An error occurred: ' . $e->getMessage() );
			}
		} else {
			delete_post_meta( $post_id, 'sdcom_timestamp_post' );
		}
	}

	/**
	 * Save post meta data during a REST request.
	 *
	 * @param WP_Post|null    $post The post to save meta data for.
	 * @param WP_REST_Request $request The REST request.
	 * @throws \Exception If the request is not a REST request, the post is invalid, the user does not have the required capabilities,
	 *                    the certificate creation or update fails, or the certificate id is empty.
	 */
	public function save_post_meta_rest( $post, $request ) {
		try {
			if ( wp_doing_ajax() ) {
				throw new \Exception( 'Doing ajax.' );
			}

			if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
				throw new \Exception( 'Not a REST request.' );
			}

			if ( ! $post instanceof \WP_Post ) {
				throw new \Exception( 'Invalid post.' );
			}

			$post_id = $post->ID;

			if ( ! current_user_can( 'manage_options', $post_id ) ) {
				throw new \Exception( 'Invalid user capability.' );
			}

			$sdcom_timestamp_post = get_post_meta( $post_id, 'sdcom_timestamp_post', true );

			// Bail early if the post meta is not set.
			if ( $sdcom_timestamp_post !== '1' ) {
				return;
			}

			$create_certificate = $this->create_certificate_post( $post );

			// Handle the case where the method returned false.
			if ( $create_certificate === false ) {
				throw new \Exception( 'Create certificate failed.' );
			}

			$certificate_id = ! empty( $create_certificate->{'certificate'}->{'id'} ) ? $create_certificate->{'certificate'}->{'id'} : '';

			// Handle the case where the certificate id is empty.
			if ( empty( $certificate_id ) ) {
				throw new \Exception( 'Certificate id is empty.' );
			}

			$update_certificate = $this->update_certificate_post( $post, $certificate_id );

			// Handle the case where the method returned false.
			if ( $update_certificate === false ) {
				throw new \Exception( 'Update certificate failed.' );
			}

			// Bail early if the certificate id is empty.
			if ( empty( $certificate_id ) ) {
				throw new \Exception( 'Certificate id is empty.' );
			}

			// Update the post meta with the new certificate id.
			update_post_meta( $post_id, 'sdcom_previous_certificate_id', $certificate_id );

			// update the content to set the data-id to the new certificate id.
			$pattern = '/<div class="sdcom-timestamps" data-id="(.*?)">.*<\/div>/';
			if ( preg_match( $pattern, $post->post_content, $matches ) ) {
				$current_certificate_id = $matches[1];

				// Check if the current certificate id is not the new certificate id
				if ( $current_certificate_id !== $certificate_id ) {
					// Update the post content to replace the previous certificate id
					$replacement = sprintf(
						'<div class="sdcom-timestamps" data-id="%s"></div>',
						esc_attr( $certificate_id )
					);

					$post->post_content = preg_replace( $pattern, $replacement, $post->post_content );

					wp_update_post( $post );
				}

				return;
			}
		} catch ( \Exception $e ) {
			// Handle the exception
			error_log( 'An error occurred: ' . $e->getMessage() );
		}
	}

	/**
	 * Protects the post meta.
	 *
	 * Turns the post meta to a *protected* meta without having to change
	 * the meta key (to _example_post_meta).
	 *
	 * @param bool   $_protected Whether the meta is protected.
	 * @param string $meta_key  The meta key.
	 * @param string $meta_type The meta type.
	 * @return bool Whether the meta is protected.
	 */
	public function is_protected_meta( $_protected, $meta_key, $meta_type ) {
		foreach ( $this->post_meta as $_meta_key => $data_type ) {
			if ( 'post' === $meta_type && $_meta_key === $meta_key ) {
				return true;
			} else {
				return $_protected;
			}
		}
	}

	/**
	 * Creates a certificate for a post.
	 *
	 * @param WP_Post $post The post to create a certificate for.
	 * @return object|false The data returned by the API on success, or false on failure.
	 * @throws \Throwable If an exception occurs during the process.
	 * @throws \Exception If the options, post content, or API key is empty.
	 */
	private function create_certificate_post( $post ) {
		try {
			$post_id                       = $post->ID;
			$post_content                  = $post->post_content;
			$sdcom_timestamps              = get_option( SDCOM_TIMESTAMPS_OPTIONS );
			$sdcom_previous_certificate_id = get_post_meta( $post_id, 'sdcom_previous_certificate_id', true );

			// Bail early if the options are empty.
			if ( empty( $sdcom_timestamps ) ) {
				throw new \Exception( 'Options are empty.' );
			}

			// Bail early if the post content is empty.
			if ( empty( $post_content ) ) {
				throw new \Exception( 'Post content is empty.' );
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
				'certificateType'  => 'plain_text_upload',
				'displayCreatedBy' => $sdcom_timestamps_display_created_by === 'true' ? true : false,
				'username'         => $sdcom_timestamps_username,
			);

			$form_data = array(
				'file'     => $post_content,
				'username' => $metadata['username'],
			);

			if ( ! empty( $sdcom_previous_certificate_id ) ) {
				$form_data['previous_certificate_id'] = $sdcom_previous_certificate_id;
			}

			$request = wp_remote_post(
				$url,
				array(
					'timeout' => 30,
					'headers' => array(
						'Authorization' => 'Bearer ' . $sdcom_timestamps_api_key,
					),
					'body'    => $form_data,
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
	 * Updates a certificate for a post.
	 *
	 * @param WP_Post $post The post to update a certificate for.
	 * @param string  $certificate_id The id of the certificate to update.
	 * @return object|false The data returned by the API on success, or false on failure.
	 * @throws \Exception If the certificate id, options, post content, or API key is empty.
	 * @throws \Throwable If an exception occurs during the process.
	 */
	private function update_certificate_post( $post, $certificate_id ) {
		try {
			$post_content     = $post->post_content;
			$sdcom_timestamps = get_option( SDCOM_TIMESTAMPS_OPTIONS );

			// Bail early if the certificate id is empty.
			if ( empty( $certificate_id ) ) {
				throw new \Exception( 'Certificate id is empty.' );
			}

			// Bail early if the options are empty.
			if ( empty( $sdcom_timestamps ) ) {
				throw new \Exception( 'Options are empty.' );
			}

			// Bail early if the post content is empty.
			if ( empty( $post_content ) ) {
				throw new \Exception( 'Post content is empty.' );
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
				'certificateType'  => 'plain_text_upload',
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
}
