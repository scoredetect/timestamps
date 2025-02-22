<?php
/**
 * Timestamp Helper.
 *
 * @since 1.8.0
 * @package SDCOM_Timestamps
 */

namespace SDCOM_Timestamps;

/**
 * Timestamp Helper Class.
 *
 * @since 1.8.0
 */
class TimestampHelper {

	/**
	 * Initialize class.
	 *
	 * @since 1.8.0
	 */
	public function setup() {}

	/**
	 * Generates the timestamp for a post.
	 *
	 * @since 1.8.0
	 *
	 * @param int|WP_Post|null $post Optional. Post ID or post object. `null`, `false`, `0` and other PHP falsey values
	 *                               return the current global post inside the loop. A numerically valid post ID that
	 *                               points to a non-existent post returns `null`. Defaults to global $post.
	 * @return object|false          The data returned by the API on success, or false on failure.
	 * @throws \Exception            If the options, post content, or API key is empty.
	 */
	public function generate_timestamp_for_post( $post = null ) {

		try {
			$post = get_post( $post );

			// Bail early if the post is empty.
			if ( empty( $post ) ) {
				return false;
			}

			$post_id = $post->ID;

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

			// Update the content to set the data-id to the new certificate id.
			$pattern = '/<div class="sdcom-timestamps" data-id="(.*?)">.*<\/div>/';
			if ( preg_match( $pattern, $post->post_content, $matches ) ) {
				$current_certificate_id = $matches[1];

				// Check if the current certificate id is not the new certificate id.
				if ( $current_certificate_id !== $certificate_id ) {

					// Update the post content to replace the previous certificate id.
					$replacement = sprintf(
						'<div class="sdcom-timestamps" data-id="%s"></div>',
						esc_attr( $certificate_id )
					);

					$post->post_content = preg_replace( $pattern, $replacement, $post->post_content );

					wp_update_post( $post );
				}
			}
		} catch ( \Exception $e ) {
			// Handle the exception.
			error_log( 'An error occurred: ' . $e->getMessage() );
		}
	}

	/**
	 * Creates a certificate for a post.
	 *
	 * @since 1.8.0
	 *
	 * @param int|WP_Post|null $post Optional. Post ID or post object. `null`, `false`, `0` and other PHP falsey values
	 *                               return the current global post inside the loop. A numerically valid post ID that
	 *                               points to a non-existent post returns `null`. Defaults to global $post.
	 * @return object|false The data returned by the API on success, or false on failure.
	 * @throws \Throwable If an exception occurs during the process.
	 * @throws \Exception If the options, post content, or API key is empty.
	 */
	private function create_certificate_post( $post = null ) {

		try {
			$post = get_post( $post );

			// Bail early if the post is empty.
			if ( empty( $post ) ) {
				return false;
			}

			$post_id                       = $post->ID;
			$post_content                  = $post->post_content;
			$post_permalink                = get_permalink( $post_id );
			$user_agent                    = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
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

			$url = SDCOM_TIMESTAMPS_PUBLIC_API_URL . '/create-certificate';

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

			$headers = [
				'Authorization' => 'Bearer ' . $sdcom_timestamps_api_key,
			];

			if ( ! empty( $user_agent ) ) {
				$headers['User-Agent'] = $user_agent;
			}

			if ( ! empty( $post_permalink ) ) {
				$headers['X-ScoreDetect-Referer'] = $post_permalink;
			}

			$request = wp_remote_post(
				$url,
				array(
					'timeout' => 30,
					'headers' => $headers,
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
	 * @since 1.8.0
	 *
	 * @param int|WP_Post|null $post           Optional. Post ID or post object. `null`, `false`, `0` and other PHP falsey values
	 *                                         return the current global post inside the loop. A numerically valid post ID that
	 *                                         points to a non-existent post returns `null`. Defaults to global $post.
	 * @param string           $certificate_id The id of the certificate to update.
	 * @return object|false                    The data returned by the API on success, or false on failure.
	 * @throws \Exception                      If the certificate id, options, post content, or API key is empty.
	 * @throws \Throwable                      If an exception occurs during the process.
	 */
	private function update_certificate_post( $post = null, $certificate_id ) {
		try {

			$post = get_post( $post );

			// Bail early if the post is empty.
			if ( empty( $post ) ) {
				return false;
			}

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

			$url = SDCOM_TIMESTAMPS_PUBLIC_API_URL . '/update-certificate';

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

	/**
	 * Return singleton instance of class.
	 *
	 * @return self
	 * @since 1.8.0
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}
}
