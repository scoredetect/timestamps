<?php
/**
 * WooCommerce Orders feature.
 *
 * @since 1.3.0
 * @package  SDCOM_Timestamps
 */

namespace SDCOM_Timestamps\Feature\WooCommerce;

use SDCOM_Timestamps\Feature;

use function SDCOM_Timestamps\Utils\get_plugin_option;
use function SDCOM_Timestamps\Utils\get_wc_volatile_order_data_keys;
use function SDCOM_Timestamps\Utils\is_timestamps_woocommerce_orders_active;
use function SDCOM_Timestamps\Utils\is_woocommerce_active;

/**
 * Orders feature class
 */
class Orders extends Feature {

	/**
	 * Initialize feature setting it's config
	 *
	 * @since 1.3.0
	 */
	public function __construct() {
		$this->slug = 'woocommerce-orders';

		$this->title = esc_html__( 'WooCommerce Orders', 'timestamps' );

		$this->summary = __( 'Easily create timestamps when creating and updating your WooCommerce orders.', 'timestamps' );

		$this->docs_url = __( 'https://docs.scoredetect.com/', 'timestamps' );

		parent::__construct();
	}

	/**
	 * We need to delay setup up until init to ensure all plugins are loaded.
	 *
	 * @since 1.3.0
	 */
	public function setup() {
		add_action( 'init', array( $this, 'setup_init' ) );
	}

	/**
	 * Setup feature on each page load.
	 *
	 * @since 1.3.0
	 */
	public function setup_init() {

		// WooCommerce Orders.
		if ( is_woocommerce_active() && is_timestamps_woocommerce_orders_active() ) {
			add_action( 'woocommerce_new_order', array( $this, 'woocommerce_new_order' ) );
			add_action( 'woocommerce_update_order', array( $this, 'woocommerce_update_order' ) );
			add_action( 'woocommerce_before_delete_order', array( $this, 'woocommerce_before_delete_order' ), 10, 2 );
			add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_order_timestamps_column' ), 30 );
			add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'order_timestamps_column' ), 30, 2 );
			add_filter( 'woocommerce_email_format_string', array( $this, 'timestamps_placeholder_wc_email' ), 10, 2 );

			// Schedule event to delete certificates.
			add_action( 'admin_init', array( $this, 'maybe_delete_certificates_old_woocommerce_orders' ) );
			add_action( 'timestamps_delete_certificates_old_woocommerce_orders', array( $this, 'delete_certificates_old_woocommerce_orders_cron' ) );
		}
	}

	/**
	 * Adds a column to the WooCommerce orders page.
	 *
	 * @param array $columns The columns on the orders page.
	 * @since 1.3.0
	 * @return array The columns on the orders page.
	 */
	public function add_order_timestamps_column( $columns ) {

		// Add the column at the end.
		$columns = array_merge( $columns, array( 'order_timestamps' => __( 'Latest Certificate', 'timestamps' ) ) );

		return $columns;
	}

	/**
	 * Outputs the content for the order timestamps column.
	 *
	 * @param string $column_name The column name.
	 * @param int    $order_or_order_id The order or order ID.
	 * @since 1.3.0
	 * @return void
	 */
	public function order_timestamps_column( $column_name, $order_or_order_id ) {

		// Get the order object. Backwards compatibility for CPT-based orders.
		$order = $order_or_order_id instanceof \WC_Order ? $order_or_order_id : wc_get_order( $order_or_order_id );

		if ( 'order_timestamps' !== $column_name ) {
			return;
		}

		$sdcom_previous_certificate_id = $order->get_meta( 'sdcom_previous_certificate_id' );

		if ( empty( $sdcom_previous_certificate_id ) ) {
			return;
		}

		printf(
			'<a class="button-link" href="%s" target="_blank" rel="nofollow noopener noreferrer">%s</a>',
			esc_url( 'https://scoredetect.com/certificate/' . $sdcom_previous_certificate_id ),
			esc_html__( 'View Certificate', 'timestamps' )
		);
	}

	/**
	 * Creates a certificate for a new WooCommerce order.
	 *
	 * @param int $order_id The order ID.
	 * @since 1.3.0
	 * @return mixed|void The data returned by the API on success, or void on failure.
	 * @throws \Exception If the order is not valid, the order items are empty, the options are empty, or the API key is empty.
	 */
	public function woocommerce_new_order( $order_id ) {

		$order = wc_get_order( $order_id );

		// Bail early if the order has already been timestamped.
		$sdcom_is_timestamped = $order->get_meta( 'sdcom_is_timestamped' );
		if ( $sdcom_is_timestamped === '1' ) {
			return;
		}

		try {
			$create_certificate = $this->create_certificate_shop_order( $order );

			// Handle the case where the method returned false.
			if ( $create_certificate === false ) {
				throw new \Exception( 'Create certificate failed.' );
			}

			$certificate_id   = ! empty( $create_certificate->{'certificate'}->{'id'} ) ? $create_certificate->{'certificate'}->{'id'} : '';
			$certificate_hash = ! empty( $create_certificate->{'certificate'}->{'verificationCertificate'}->{'associatedMedia'}->{'sha256'} ) ? $create_certificate->{'certificate'}->{'verificationCertificate'}->{'associatedMedia'}->{'sha256'} : '';

			// Handle the case where the certificate id is empty.
			if ( empty( $certificate_id ) ) {
				throw new \Exception( 'Certificate id is empty.' );
			}

			$update_certificate = $this->update_certificate_shop_order( $order, $certificate_id );

			// Handle the case where the method returned false.
			if ( $update_certificate === false ) {
				throw new \Exception( 'Update certificate failed.' );
			}

			// Update the post meta with the new certificate id.
			$order->update_meta_data( 'sdcom_previous_certificate_id', $certificate_id );

			// Update the post meta with the new certificate hash.
			$order->update_meta_data( 'sdcom_previous_certificate_hash', $certificate_hash );

			// Save the order to update the meta data.
			$order->save();
		} catch ( \Exception $e ) {
			// Handle the exception
			error_log( 'An error occurred: ' . $e->getMessage() );
		}
	}

	/**
	 * Creates a certificate for an updated WooCommerce order.
	 *
	 * @param int $order_id The order ID.
	 * @since 1.3.0
	 * @return mixed|void The data returned by the API on success, or void on failure.
	 * @throws \Exception If the order is not valid, the order items are empty, the options are empty, or the API key is empty.
	 */
	public function woocommerce_update_order( $order_id ) {

		$order = wc_get_order( $order_id );

		// Bail early if the the order has __not__ already been timestamped.
		$sdcom_is_timestamped = $order->get_meta( 'sdcom_is_timestamped' );
		if ( empty( $sdcom_is_timestamped ) ) {
			return;
		}

		$sdcom_previous_certificate_hash = $order->get_meta( 'sdcom_previous_certificate_hash' );

		// Bail early if the previous certificate hash is empty.
		if ( empty( $sdcom_previous_certificate_hash ) ) {
			return;
		}

		$order_data = $order->get_data();

		// List of keys for data that changes frequently.
		$wc_volatile_order_data_keys = get_wc_volatile_order_data_keys();

		// Remove volatile data from the order data.
		foreach ( $wc_volatile_order_data_keys as $key ) {
			unset( $order_data[ $key ] );
		}

		$order_data_checksum = hash( 'sha256', wp_json_encode( $order_data ) );

		// Bail early if the order data checksum is the same as the previous certificate hash.
		if ( $order_data_checksum === $sdcom_previous_certificate_hash ) {
			return;
		}

		try {
			$create_certificate = $this->create_certificate_shop_order( $order );

			// Handle the case where the method returned false.
			if ( $create_certificate === false ) {
				throw new \Exception( 'Create certificate failed.' );
			}

			$certificate_id = ! empty( $create_certificate->{'certificate'}->{'id'} ) ? $create_certificate->{'certificate'}->{'id'} : '';

			// Handle the case where the certificate id is empty.
			if ( empty( $certificate_id ) ) {
				throw new \Exception( 'Certificate id is empty.' );
			}

			$update_certificate = $this->update_certificate_shop_order( $order, $certificate_id );

			// Handle the case where the method returned false.
			if ( $update_certificate === false ) {
				throw new \Exception( 'Update certificate failed.' );
			}

			// Update the post meta with the new certificate id.
			$order->update_meta_data( 'sdcom_previous_certificate_id', $certificate_id );

			// Update the post meta with the new certificate hash.
			$order->update_meta_data( 'sdcom_previous_certificate_hash', $order_data_checksum );

			// Save the order to update the meta data.
			$order->save();
		} catch ( \Exception $e ) {
			// Handle the exception
			error_log( 'An error occurred: ' . $e->getMessage() );
		}
	}

	/**
	 * Deletes a certificate for a WooCommerce order when the order is permanently deleted (not in trash).
	 *
	 * This does not delete the blockchain transaction. It only deletes the certificate from the API.
	 * This is because the blockchain transaction can never be deleted, as written in the smart contract.
	 *
	 * This will clear up unused certificates and help save money for you by giving more space for more certificates.
	 *
	 * We recommend visiting the certificate URL and saving to PDF / printing it, before deleting it.
	 *
	 * You can also visit https://docs.scoredetect.com/certificates/how-can-i-download-export-my-certificates to bulk export your certificates.
	 *
	 * @param int       $order_id The order ID.
	 * @param \WC_Order $order The order object.
	 * @since 1.4.0
	 * @return void
	 */
	public function woocommerce_before_delete_order( $order_id, $order ) {

		// Bail early if the order is not valid.
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$sdcom_previous_certificate_id = $order->get_meta( 'sdcom_previous_certificate_id' );

		// Bail early if there is no previous certificate id.
		if ( empty( $sdcom_previous_certificate_id ) ) {
			return;
		}

		// Delete the certificate from the API.
		$this->delete_certificates( [ $sdcom_previous_certificate_id ] );
	}

	/**
	 * Creates a certificate for a WooCommerce order.
	 *
	 * @param WC_Order $order The order to create a certificate for.
	 * @since 1.3.0
	 * @return object|false The data returned by the API on success, or false on failure.
	 * @throws \Throwable If an exception occurs during the process.
	 * @throws \Exception If the options, post content, or API key is empty.
	 */
	private function create_certificate_shop_order( $order ) {
		try {

			// Bail early if the order is not valid.
			if ( ! $order instanceof \WC_Order ) {
				return false;
			}

			$order_items = $order->get_items();

			// Bail early if the order items are empty.
			if ( empty( $order_items ) ) {
				return false;
			}

			$order_data                    = $order->get_data();
			$sdcom_timestamps              = get_option( SDCOM_TIMESTAMPS_OPTIONS );
			$sdcom_previous_certificate_id = $order->get_meta( 'sdcom_previous_certificate_id' );

			// Bail early if the options are empty.
			if ( empty( $sdcom_timestamps ) ) {
				throw new \Exception( 'Options are empty.' );
			}

			// Bail early if the order data is empty.
			if ( empty( $order_data ) ) {
				throw new \Exception( 'Order data is empty.' );
			}

			// List of keys for data that changes frequently.
			$wc_volatile_order_data_keys = get_wc_volatile_order_data_keys();

			// Remove volatile data from the order data.
			foreach ( $wc_volatile_order_data_keys as $key ) {
				unset( $order_data[ $key ] );
			}

			$sdcom_timestamps_display_created_by = get_plugin_option( 'display_created_by', false );
			$sdcom_timestamps_username           = 'anonymous';
			$sdcom_timestamps_api_key            = get_plugin_option( 'api_key', '' );

			// Bail early if the api key is empty.
			if ( empty( $sdcom_timestamps_api_key ) ) {
				throw new \Exception( 'API key is empty.' );
			}

			if ( $sdcom_timestamps_display_created_by === 'true' ) {
				$sdcom_timestamps_username = get_plugin_option( 'username', $sdcom_timestamps_username );
			}

			$url = 'https://api.scoredetect.com/create-certificate';

			$metadata = array(
				'certificateType'  => 'plain_text_upload',
				'displayCreatedBy' => $sdcom_timestamps_display_created_by === 'true' ? true : false,
				'username'         => $sdcom_timestamps_username,
			);

			$form_data = array(
				'file'     => wp_json_encode( $order_data ),
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

			// Mark the order as timestamped.
			$order->update_meta_data( 'sdcom_is_timestamped', '1' );

			// Save the order to update the meta data.
			$order->save();

			// Return the data.
			return $data;

		} catch ( \Throwable $th ) {
			throw $th;
		}
	}

	/**
	 * Updates a certificate for a WooCommerce order.
	 *
	 * @param WC_Order $order The order to update a certificate for.
	 * @param string   $certificate_id The id of the certificate to update.
	 * @since 1.3.0
	 * @return object|false The data returned by the API on success, or false on failure.
	 * @throws \Exception If the certificate id, options, post content, or API key is empty.
	 * @throws \Throwable If an exception occurs during the process.
	 */
	private function update_certificate_shop_order( $order, $certificate_id ) {
		try {

			// Bail early if the order is not valid.
			if ( ! $order instanceof \WC_Order ) {
				return;
			}

			$order_items = $order->get_items();

			// Bail early if the order items are empty.
			if ( empty( $order_items ) ) {
				return;
			}

			$order_data       = $order->get_data();
			$sdcom_timestamps = get_option( SDCOM_TIMESTAMPS_OPTIONS );

			// Bail early if the certificate id is empty.
			if ( empty( $certificate_id ) ) {
				throw new \Exception( 'Certificate id is empty.' );
			}

			// Bail early if the options are empty.
			if ( empty( $sdcom_timestamps ) ) {
				throw new \Exception( 'Options are empty.' );
			}

			// Bail early if the order data is empty.
			if ( empty( $order_data ) ) {
				throw new \Exception( 'Order data is empty.' );
			}

			$sdcom_timestamps_display_created_by = get_plugin_option( 'display_created_by', false );
			$sdcom_timestamps_username           = 'anonymous';
			$sdcom_timestamps_api_key            = get_plugin_option( 'api_key', '' );

			// Bail early if the api key is empty.
			if ( empty( $sdcom_timestamps_api_key ) ) {
				throw new \Exception( 'API key is empty.' );
			}

			if ( $sdcom_timestamps_display_created_by === 'true' ) {
				$sdcom_timestamps_username = get_plugin_option( 'username', $sdcom_timestamps_username );
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

	/**
	 * Allows a {timestamps} placeholder to display a "View Certificate" link on the WooCommerce order emails.
	 *
	 * @param string    $_string The email placeholders.
	 * @param \WC_Email $email The WooCommerce email object.
	 * @since 1.3.0
	 * @return string The email placeholders.
	 */
	public function timestamps_placeholder_wc_email( $_string, $email ): string {
		$order = $email->object;

		$sdcom_previous_certificate_id = $order->get_meta( 'sdcom_previous_certificate_id' );

		// Bail early if there is no previous certificate id.
		if ( empty( $sdcom_previous_certificate_id ) ) {
			return $_string;
		}

		$placeholders = [
			'{timestamps}' => apply_filters(
				'timestamps_placeholder_wc_email',
				sprintf(
					'<a class="button-link" href="%s" target="_blank" rel="nofollow noopener noreferrer">%s</a>',
					esc_url( 'https://scoredetect.com/certificate/' . $sdcom_previous_certificate_id ),
					esc_html__( 'View Certificate', 'timestamps' )
				),
				$order
			),
		];

		return str_replace( array_keys( $placeholders ), array_values( $placeholders ), $_string );
	}

	/**
	 * Schedules the event to delete certificates from old WooCommerce Orders.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public function maybe_delete_certificates_old_woocommerce_orders() {

		if ( ! wp_next_scheduled( 'timestamps_delete_certificates_old_woocommerce_orders' ) ) {

			wp_schedule_event(
				time() - 1,
				'daily',
				'timestamps_delete_certificates_old_woocommerce_orders',
			);
		}
	}

	/**
	 * Cron job to delete certificates from old WooCommerce Orders.
	 *
	 * @since 1.3.0
	 * @return void
	 * @throws \Exception If the method fails.
	 */
	public function delete_certificates_old_woocommerce_orders_cron() {
		try {
			// Get old orders with certificates.
			$orders = $this->get_old_orders_with_certificates();

			// Bail early if there are no old orders with certificates.
			if ( empty( $orders ) ) {
				return;
			}

			// Loop through the orders and delete the certificate meta data.
			foreach ( $orders as $order ) {
				$this->delete_order_certificate_meta_data( $order );
			}

			// Delete the certificates from the API.
			$delete_certificates = $this->delete_certificates( $orders );

			// Handle the case where the method returned false.
			if ( $delete_certificates === false ) {
				throw new \Exception( 'Delete certificates failed.' );
			}
		} catch ( \Exception $e ) {
			// Handle the exception
			error_log( 'An error occurred: ' . $e->getMessage() );
		}
	}

	/**
	 * Deletes the certificate meta data from a WooCommerce order.
	 *
	 * @param \WC_Order $order The order to delete the meta data from.
	 * @since 1.3.0
	 * @return void
	 */
	public function delete_order_certificate_meta_data( $order ) {

		// Bail early if the order is not valid.
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		// Delete the certificate meta data.
		$order->delete_meta_data( 'sdcom_previous_certificate_id' );
		$order->delete_meta_data( 'sdcom_previous_certificate_hash' );
		$order->delete_meta_data( 'sdcom_is_timestamped' );

		// Save the order to update the meta data.
		$order->save();
	}

	/**
	 * Retrieves old WooCommerce orders with certificates.
	 *
	 * @since 1.4.0
	 * @return \WC_Order[] An array of old WooCommerce orders with certificates.
	 */
	public function get_old_orders_with_certificates(): array {

		// Bail early if the WooCommerce plugin is not active.
		if ( ! is_woocommerce_active() ) {
			return [];
		}

		$woocommerce_order_statuses_marked_old_certificates = get_plugin_option( 'woocommerce_order_statuses_marked_old_certificates', [] );
		$delete_certificates_old_woocommerce_orders_age     = get_plugin_option( 'delete_certificates_old_woocommerce_orders_age', 365 );

		// Bail early if the option is empty.
		if ( empty( $woocommerce_order_statuses_marked_old_certificates ) ) {
			return [];
		}

		$query = new \WC_Order_Query(
			array(
				// Only get orders older than the specified age from the plugin settings.
				'date_created' => gmdate( 'Y-m-d', strtotime( "-{$delete_certificates_old_woocommerce_orders_age} days" ) ),
				// Only get orders with the specified statuses from the plugin settings.
				'status'       => $woocommerce_order_statuses_marked_old_certificates,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'   => array(
					array(
						'key'     => 'sdcom_previous_certificate_id',
						// Check for a non-empty value.
						'value'   => '',
						// Ensures the meta key exists and its value is not empty.
						'compare' => '!=',
					),
				),
				// Set to a large int but never -1 (for performance reasons)
				'limit'        => 100000,
				// Only get order IDs to improve performance.
				'fields'       => 'ids',
			)
		);

		return $query->get_orders();
	}

	/**
	 * Deletes certificates from a list of certificate IDs.
	 *
	 * We recommend visiting the certificate URL and saving to PDF / printing it, before deleting it.
	 *
	 * You can also visit https://docs.scoredetect.com/certificates/how-can-i-download-export-my-certificates to bulk export your certificates.
	 *
	 * @param array $certificate_ids The certificate ids to delete.
	 * @since 1.3.0
	 * @return object|false The data returned by the API on success, or false on failure.
	 * @throws \Throwable If an exception occurs during the process.
	 * @throws \Exception If the certificate ids is not an array, the options are empty, or the API key is empty.
	 */
	public function delete_certificates( $certificate_ids = [] ) {

		try {

			// Bail early if $certificate_ids is not an array.
			if ( ! is_array( $certificate_ids ) ) {
				throw new \Exception( 'Certificate ids is not an array.' );
			}

			// Loop through the $certificate_ids array and remove any invalid certificate IDs.
			foreach ( $certificate_ids as $key => $certificate_id ) {
				if ( ! wp_is_uuid( $certificate_id ) ) {
					unset( $certificate_ids[ $key ] );
				}
			}

			// Bail early if the $certificate_ids array is empty.
			if ( empty( $certificate_ids ) ) {
				throw new \Exception( 'Certificate ids is empty.' );
			}

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

			$url = 'https://api.scoredetect.com/certificates/batch-delete';

			$body = wp_json_encode(
				array(
					'certificateIds' => $certificate_ids,
				)
			);

			$request = wp_remote_request(
				$url,
				array(
					'method'  => 'DELETE',
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
