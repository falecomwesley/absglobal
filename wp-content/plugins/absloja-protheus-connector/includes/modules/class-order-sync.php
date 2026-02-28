<?php
/**
 * Order Sync Module
 *
 * Manages order synchronization between WooCommerce and Protheus ERP.
 * Handles order creation, status updates, cancellations, and refunds.
 *
 * @package ABSLoja\ProtheusConnector\Modules
 * @since 1.0.0
 */

namespace ABSLoja\ProtheusConnector\Modules;

use ABSLoja\ProtheusConnector\API\Protheus_Client;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Order_Sync
 *
 * Handles synchronization of WooCommerce orders to Protheus SC5/SC6 tables.
 * Manages order lifecycle including creation, status updates, cancellations, and refunds.
 *
 * @since 1.0.0
 */
class Order_Sync {

	/**
	 * Protheus API client
	 *
	 * @var Protheus_Client
	 */
	private $client;

	/**
	 * Customer Sync instance
	 *
	 * @var Customer_Sync
	 */
	private $customer_sync;

	/**
	 * Mapping Engine instance
	 *
	 * @var Mapping_Engine
	 */
	private $mapper;

	/**
	 * Logger instance
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Retry Manager instance
	 *
	 * @var Retry_Manager
	 */
	private $retry_manager;

	/**
	 * Constructor
	 *
	 * @param Protheus_Client $client         Protheus API client instance.
	 * @param Customer_Sync   $customer_sync  Customer sync instance.
	 * @param Mapping_Engine  $mapper         Mapping engine instance.
	 * @param Logger          $logger         Logger instance.
	 * @param Retry_Manager   $retry_manager  Retry manager instance.
	 */
	public function __construct(
		Protheus_Client $client,
		Customer_Sync $customer_sync,
		Mapping_Engine $mapper,
		Logger $logger,
		Retry_Manager $retry_manager
	) {
		$this->client         = $client;
		$this->customer_sync  = $customer_sync;
		$this->mapper         = $mapper;
		$this->logger         = $logger;
		$this->retry_manager  = $retry_manager;
	}

	/**
	 * Sync order to Protheus
	 *
	 * Main method to send WooCommerce order to Protheus SC5/SC6 tables.
	 * Checks if order was already synced, verifies/creates customer,
	 * maps order data, and sends to Protheus API.
	 *
	 * @param \WC_Order $order WooCommerce order object.
	 * @return bool True on success, false on failure.
	 */
	public function sync_order( \WC_Order $order ): bool {
		$order_id = $order->get_id();

		// Check if order was already synced
		$protheus_order_id = $order->get_meta( '_protheus_order_id', true );
		if ( ! empty( $protheus_order_id ) ) {
			$this->logger->log_sync_operation(
				'order_sync',
				array(
					'order_id'          => $order_id,
					'protheus_order_id' => $protheus_order_id,
					'action'            => 'already_synced',
				),
				true,
				'Order already synced to Protheus'
			);
			return true;
		}

		// Verify/create customer in Protheus
		$customer_code = $this->customer_sync->ensure_customer_exists( $order );

		if ( empty( $customer_code ) ) {
			// Customer creation failed - abort order sync
			$error_message = 'Customer creation failed - aborting order sync';
			
			$this->logger->log_sync_operation(
				'order_sync',
				array(
					'order_id' => $order_id,
					'action'   => 'aborted',
					'reason'   => 'customer_creation_failed',
				),
				false,
				$error_message
			);

			// Add admin note to order
			$order->add_order_note(
				__( 'Protheus sync failed: Customer could not be created in Protheus.', 'absloja-protheus-connector' ),
				false,
				true
			);

			// Schedule retry
			$this->retry_manager->schedule_retry(
				'order_sync',
				array( 'order_id' => $order_id ),
				$error_message
			);

			return false;
		}

		// Store customer code in order metadata
		$order->update_meta_data( '_protheus_customer_code', $customer_code );
		$order->save();

		// Map order data to Protheus format
		$payload = $this->map_order_to_protheus( $order, $customer_code );

		if ( empty( $payload ) ) {
			$error_message = 'Failed to map order data to Protheus format';
			
			$this->logger->log_sync_operation(
				'order_sync',
				array(
					'order_id' => $order_id,
					'action'   => 'mapping_failed',
				),
				false,
				$error_message
			);

			return false;
		}

		// Send order to Protheus
		$start_time = microtime( true );
		$response = $this->client->post( 'api/v1/orders', $payload );
		$duration = microtime( true ) - $start_time;

		// Log API request
		$this->logger->log_api_request(
			'POST /api/v1/orders',
			$payload,
			$response,
			$duration
		);

		if ( ! $response['success'] ) {
			// Handle API error with enhanced classification
			$error_message = $response['error'] ?? 'Unknown API error';
			$error_type = $response['error_type'] ?? 'unknown_error';
			$business_error = $this->classify_business_error( $error_message );

			$this->logger->log_sync_operation(
				'order_sync',
				array(
					'order_id'       => $order_id,
					'action'         => 'api_error',
					'error_type'     => $error_type,
					'business_error' => $business_error,
				),
				false,
				$error_message
			);

			// Handle specific business errors
			$this->handle_business_error( $order, $business_error, $error_message );

			// Add detailed admin note to order
			$admin_note = $this->format_error_admin_note( $error_message, $error_type, $business_error );
			$order->add_order_note( $admin_note, false, true );

			// Update order metadata
			$order->update_meta_data( '_protheus_sync_status', 'error' );
			$order->update_meta_data( '_protheus_sync_error', $error_message );
			$order->update_meta_data( '_protheus_error_type', $error_type );
			if ( $business_error ) {
				$order->update_meta_data( '_protheus_business_error', $business_error );
				$order->update_meta_data( '_protheus_requires_manual_review', true );
			}
			$order->save();

			// Schedule retry only for transient errors (not business errors)
			$transient_errors = array( 'server_error', 'timeout_error', 'network_error', 'connection_error', 'dns_error', 'ssl_error' );
			if ( ! $business_error && in_array( $error_type, $transient_errors, true ) ) {
				$this->retry_manager->schedule_retry(
					'order_sync',
					array( 'order_id' => $order_id ),
					$error_message
				);
			}

			return false;
		}

		// Extract Protheus order ID from response
		$data = $response['data'];
		$protheus_order_id = $this->extract_protheus_order_id( $data );

		if ( empty( $protheus_order_id ) ) {
			$error_message = 'Protheus order ID not found in response';
			
			$this->logger->log_sync_operation(
				'order_sync',
				array(
					'order_id' => $order_id,
					'action'   => 'missing_order_id',
					'response' => $data,
				),
				false,
				$error_message
			);

			return false;
		}

		// Store sync success metadata
		$order->update_meta_data( '_protheus_order_id', $protheus_order_id );
		$order->update_meta_data( '_protheus_sync_date', current_time( 'mysql' ) );
		$order->update_meta_data( '_protheus_sync_status', 'synced' );
		$order->delete_meta_data( '_protheus_sync_error' );
		$order->save();

		// Add success note to order
		$order->add_order_note(
			sprintf(
				/* translators: %s: Protheus order ID */
				__( 'Order successfully synced to Protheus. Order ID: %s', 'absloja-protheus-connector' ),
				$protheus_order_id
			),
			false,
			false
		);

		// Log success
		$this->logger->log_sync_operation(
			'order_sync',
			array(
				'order_id'          => $order_id,
				'protheus_order_id' => $protheus_order_id,
				'customer_code'     => $customer_code,
				'action'            => 'synced',
			),
			true
		);

		return true;
	}

	/**
	 * Sync order status to Protheus
	 *
	 * Updates order status in Protheus when WooCommerce order status changes.
	 *
	 * @param \WC_Order $order      WooCommerce order object.
	 * @param string    $new_status New order status.
	 * @return bool True on success, false on failure.
	 */
	public function sync_order_status( \WC_Order $order, string $new_status ): bool {
		$order_id = $order->get_id();

		// Check if order was synced to Protheus
		$protheus_order_id = $order->get_meta( '_protheus_order_id', true );
		if ( empty( $protheus_order_id ) ) {
			$this->logger->log_sync_operation(
				'order_status_sync',
				array(
					'order_id'   => $order_id,
					'new_status' => $new_status,
					'action'     => 'skipped',
					'reason'     => 'order_not_synced',
				),
				false,
				'Order not synced to Protheus - cannot update status'
			);
			return false;
		}

		// Map WooCommerce status to Protheus status
		$protheus_status = $this->mapper->get_status_mapping( $new_status );

		// Build payload
		$payload = array(
			'order_id' => $protheus_order_id,
			'status'   => $protheus_status,
		);

		// Send status update to Protheus
		$start_time = microtime( true );
		$response = $this->client->post( 'api/v1/orders/status', $payload );
		$duration = microtime( true ) - $start_time;

		// Log API request
		$this->logger->log_api_request(
			'POST /api/v1/orders/status',
			$payload,
			$response,
			$duration
		);

		if ( ! $response['success'] ) {
			$error_message = $response['error'] ?? 'Unknown API error';
			
			$this->logger->log_sync_operation(
				'order_status_sync',
				array(
					'order_id'          => $order_id,
					'protheus_order_id' => $protheus_order_id,
					'new_status'        => $new_status,
					'action'            => 'failed',
				),
				false,
				$error_message
			);

			// Schedule retry
			$this->retry_manager->schedule_retry(
				'order_status_sync',
				array(
					'order_id'   => $order_id,
					'new_status' => $new_status,
				),
				$error_message
			);

			return false;
		}

		// Log success
		$this->logger->log_sync_operation(
			'order_status_sync',
			array(
				'order_id'          => $order_id,
				'protheus_order_id' => $protheus_order_id,
				'new_status'        => $new_status,
				'protheus_status'   => $protheus_status,
				'action'            => 'updated',
			),
			true
		);

		return true;
	}

	/**
	 * Cancel order in Protheus
	 *
	 * Sends cancellation request to Protheus when WooCommerce order is cancelled.
	 *
	 * @param \WC_Order $order WooCommerce order object.
	 * @return bool True on success, false on failure.
	 */
	public function cancel_order( \WC_Order $order ): bool {
		$order_id = $order->get_id();

		// Check if order was synced to Protheus
		$protheus_order_id = $order->get_meta( '_protheus_order_id', true );
		if ( empty( $protheus_order_id ) ) {
			$this->logger->log_sync_operation(
				'order_cancel',
				array(
					'order_id' => $order_id,
					'action'   => 'skipped',
					'reason'   => 'order_not_synced',
				),
				false,
				'Order not synced to Protheus - cannot cancel'
			);
			return false;
		}

		// Build payload
		$payload = array(
			'order_id' => $protheus_order_id,
			'action'   => 'cancel',
			'reason'   => 'Cancelled in WooCommerce',
		);

		// Send cancellation request to Protheus
		$start_time = microtime( true );
		$response = $this->client->post( 'api/v1/orders/cancel', $payload );
		$duration = microtime( true ) - $start_time;

		// Log API request
		$this->logger->log_api_request(
			'POST /api/v1/orders/cancel',
			$payload,
			$response,
			$duration
		);

		if ( ! $response['success'] ) {
			$error_message = $response['error'] ?? 'Unknown API error';
			
			$this->logger->log_sync_operation(
				'order_cancel',
				array(
					'order_id'          => $order_id,
					'protheus_order_id' => $protheus_order_id,
					'action'            => 'failed',
				),
				false,
				$error_message
			);

			// Add admin note
			$order->add_order_note(
				sprintf(
					/* translators: %s: Error message */
					__( 'Protheus cancellation failed: %s', 'absloja-protheus-connector' ),
					$error_message
				),
				false,
				true
			);

			// Schedule retry
			$this->retry_manager->schedule_retry(
				'order_cancel',
				array( 'order_id' => $order_id ),
				$error_message
			);

			return false;
		}

		// Add success note
		$order->add_order_note(
			__( 'Order cancellation synced to Protheus successfully.', 'absloja-protheus-connector' ),
			false,
			false
		);

		// Log success
		$this->logger->log_sync_operation(
			'order_cancel',
			array(
				'order_id'          => $order_id,
				'protheus_order_id' => $protheus_order_id,
				'action'            => 'cancelled',
			),
			true
		);

		return true;
	}

	/**
	 * Refund order in Protheus
	 *
	 * Sends refund notification to Protheus when WooCommerce order is refunded.
	 *
	 * @param \WC_Order $order WooCommerce order object.
	 * @return bool True on success, false on failure.
	 */
	public function refund_order( \WC_Order $order ): bool {
		$order_id = $order->get_id();

		// Check if order was synced to Protheus
		$protheus_order_id = $order->get_meta( '_protheus_order_id', true );
		if ( empty( $protheus_order_id ) ) {
			$this->logger->log_sync_operation(
				'order_refund',
				array(
					'order_id' => $order_id,
					'action'   => 'skipped',
					'reason'   => 'order_not_synced',
				),
				false,
				'Order not synced to Protheus - cannot refund'
			);
			return false;
		}

		// Build payload
		$payload = array(
			'order_id' => $protheus_order_id,
			'action'   => 'refund',
			'amount'   => $order->get_total(),
			'reason'   => 'Refunded in WooCommerce',
		);

		// Send refund notification to Protheus
		$start_time = microtime( true );
		$response = $this->client->post( 'api/v1/orders/refund', $payload );
		$duration = microtime( true ) - $start_time;

		// Log API request
		$this->logger->log_api_request(
			'POST /api/v1/orders/refund',
			$payload,
			$response,
			$duration
		);

		if ( ! $response['success'] ) {
			$error_message = $response['error'] ?? 'Unknown API error';
			
			$this->logger->log_sync_operation(
				'order_refund',
				array(
					'order_id'          => $order_id,
					'protheus_order_id' => $protheus_order_id,
					'action'            => 'failed',
				),
				false,
				$error_message
			);

			// Add admin note
			$order->add_order_note(
				sprintf(
					/* translators: %s: Error message */
					__( 'Protheus refund notification failed: %s', 'absloja-protheus-connector' ),
					$error_message
				),
				false,
				true
			);

			// Schedule retry
			$this->retry_manager->schedule_retry(
				'order_refund',
				array( 'order_id' => $order_id ),
				$error_message
			);

			return false;
		}

		// Add success note
		$order->add_order_note(
			__( 'Order refund synced to Protheus successfully.', 'absloja-protheus-connector' ),
			false,
			false
		);

		// Log success
		$this->logger->log_sync_operation(
			'order_refund',
			array(
				'order_id'          => $order_id,
				'protheus_order_id' => $protheus_order_id,
				'action'            => 'refunded',
			),
			true
		);

		return true;
	}

	/**
	 * Map order data to Protheus format
	 *
	 * Converts WooCommerce order to Protheus SC5/SC6 format.
	 *
	 * @param \WC_Order $order         WooCommerce order object.
	 * @param string    $customer_code Protheus customer code.
	 * @return array Mapped order data for Protheus API.
	 */
	private function map_order_to_protheus( \WC_Order $order, string $customer_code ): array {
		$mapping = $this->mapper->get_order_mapping();

		// Get payment condition
		$payment_method = $order->get_payment_method();
		$payment_condition = $this->mapper->get_payment_mapping( $payment_method );

		// Get TES based on customer state
		$billing_state = $order->get_billing_state();
		$tes_code = $this->mapper->get_tes_by_state( $billing_state );

		// Build SC5 (order header) payload
		$sc5 = array(
			'C5_FILIAL'   => $mapping['SC5']['C5_FILIAL'] ?? '01',
			'C5_NUM'      => '', // Generated by Protheus
			'C5_TIPO'     => $mapping['SC5']['C5_TIPO'] ?? 'N',
			'C5_CLIENTE'  => $customer_code,
			'C5_LOJACLI'  => $mapping['SC5']['C5_LOJACLI'] ?? '01',
			'C5_CONDPAG'  => $payment_condition,
			'C5_TABELA'   => $mapping['SC5']['C5_TABELA'] ?? '001',
			'C5_VEND1'    => $mapping['SC5']['C5_VEND1'] ?? '000001',
			'C5_PEDWOO'   => (string) $order->get_id(),
			'C5_EMISSAO'  => $order->get_date_created()->format( 'Ymd' ),
			'C5_FRETE'    => (float) $order->get_shipping_total(),
			'C5_DESCONT'  => (float) $order->get_discount_total(),
		);

		// Build SC6 (order items) payload
		$sc6_items = array();
		$item_sequence = 1;

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			
			if ( ! $product ) {
				continue;
			}

			$sku = $product->get_sku();
			
			if ( empty( $sku ) ) {
				continue;
			}

			$quantity = $item->get_quantity();
			$line_total = (float) $item->get_total();
			$unit_price = $quantity > 0 ? $line_total / $quantity : 0;

			$sc6_items[] = array(
				'C6_FILIAL'  => $mapping['SC6']['C6_FILIAL'] ?? '01',
				'C6_NUM'     => '', // Same as SC5, filled by Protheus
				'C6_ITEM'    => str_pad( $item_sequence, 2, '0', STR_PAD_LEFT ),
				'C6_PRODUTO' => $sku,
				'C6_QTDVEN'  => (float) $quantity,
				'C6_PRCVEN'  => $unit_price,
				'C6_VALOR'   => $line_total,
				'C6_TES'     => $tes_code,
			);

			$item_sequence++;
		}

		// Return complete payload
		return array(
			'header' => $sc5,
			'items'  => $sc6_items,
		);
	}

	/**
	 * Extract Protheus order ID from API response
	 *
	 * @param mixed $data API response data.
	 * @return string|null Protheus order ID or null if not found.
	 */
	private function extract_protheus_order_id( $data ): ?string {
		if ( ! is_array( $data ) ) {
			return null;
		}

		// Try common field names
		if ( isset( $data['C5_NUM'] ) && ! empty( $data['C5_NUM'] ) ) {
			return $data['C5_NUM'];
		}

		if ( isset( $data['order_id'] ) && ! empty( $data['order_id'] ) ) {
			return $data['order_id'];
		}

		if ( isset( $data['order_number'] ) && ! empty( $data['order_number'] ) ) {
			return $data['order_number'];
		}

		if ( isset( $data['id'] ) && ! empty( $data['id'] ) ) {
			return $data['id'];
		}

		return null;
	}

	/**
	 * Classify business error from error message
	 *
	 * Identifies specific business errors that should not be retried automatically.
	 *
	 * @param string $error_message Error message from API.
	 * @return string|null Business error type or null if not a business error.
	 */
	private function classify_business_error( string $error_message ): ?string {
		$error_lower = strtolower( $error_message );

		// TES errors
		if ( strpos( $error_lower, 'tes' ) !== false && 
		     ( strpos( $error_lower, 'not found' ) !== false || 
		       strpos( $error_lower, 'não encontrado' ) !== false ||
		       strpos( $error_lower, 'inválido' ) !== false ||
		       strpos( $error_lower, 'invalid' ) !== false ) ) {
			return 'tes_error';
		}

		// Stock errors
		if ( strpos( $error_lower, 'estoque' ) !== false || 
		     strpos( $error_lower, 'stock' ) !== false ) {
			if ( strpos( $error_lower, 'insuficiente' ) !== false || 
			     strpos( $error_lower, 'insufficient' ) !== false ||
			     strpos( $error_lower, 'indisponível' ) !== false ||
			     strpos( $error_lower, 'unavailable' ) !== false ) {
				return 'stock_insufficient';
			}
		}

		// CPF/CNPJ validation errors
		if ( ( strpos( $error_lower, 'cpf' ) !== false || strpos( $error_lower, 'cnpj' ) !== false ) &&
		     ( strpos( $error_lower, 'inválido' ) !== false || 
		       strpos( $error_lower, 'invalid' ) !== false ) ) {
			return 'document_invalid';
		}

		// Customer errors
		if ( strpos( $error_lower, 'cliente' ) !== false || strpos( $error_lower, 'customer' ) !== false ) {
			if ( strpos( $error_lower, 'not found' ) !== false || 
			     strpos( $error_lower, 'não encontrado' ) !== false ) {
				return 'customer_not_found';
			}
		}

		// Product/SKU errors
		if ( ( strpos( $error_lower, 'produto' ) !== false || strpos( $error_lower, 'product' ) !== false ||
		       strpos( $error_lower, 'sku' ) !== false ) &&
		     ( strpos( $error_lower, 'not found' ) !== false || 
		       strpos( $error_lower, 'não encontrado' ) !== false ) ) {
			return 'product_not_found';
		}

		return null;
	}

	/**
	 * Handle specific business errors
	 *
	 * Performs special actions for specific business error types.
	 *
	 * @param \WC_Order   $order WooCommerce order object.
	 * @param string|null $business_error Business error type.
	 * @param string      $error_message Full error message.
	 * @return void
	 */
	private function handle_business_error( \WC_Order $order, ?string $business_error, string $error_message ): void {
		if ( ! $business_error ) {
			return;
		}

		switch ( $business_error ) {
			case 'stock_insufficient':
				// Update WooCommerce stock to prevent further sales
				$this->update_stock_from_error( $order, $error_message );
				break;

			case 'tes_error':
				// Log TES configuration issue for admin review
				$this->logger->log_error(
					'TES configuration error detected',
					new \Exception( $error_message ),
					array(
						'order_id'      => $order->get_id(),
						'billing_state' => $order->get_billing_state(),
						'error_type'    => 'tes_error',
					)
				);
				break;

			case 'product_not_found':
				// Log product sync issue
				$this->logger->log_error(
					'Product not found in Protheus',
					new \Exception( $error_message ),
					array(
						'order_id' => $order->get_id(),
						'items'    => $this->get_order_skus( $order ),
					)
				);
				break;
		}
	}

	/**
	 * Update WooCommerce stock based on insufficient stock error
	 *
	 * Attempts to extract product SKU from error message and update stock to zero.
	 *
	 * @param \WC_Order $order WooCommerce order object.
	 * @param string    $error_message Error message.
	 * @return void
	 */
	private function update_stock_from_error( \WC_Order $order, string $error_message ): void {
		// Get all items from the order
		$items = $order->get_items();

		foreach ( $items as $item ) {
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}

			$sku = $product->get_sku();
			
			// Check if this product is mentioned in the error message
			if ( ! empty( $sku ) && strpos( $error_message, $sku ) !== false ) {
				// Set stock to zero and hide product
				$product->set_stock_quantity( 0 );
				$product->set_catalog_visibility( 'hidden' );
				$product->save();

				$this->logger->log_sync_operation(
					'stock_update',
					array(
						'product_id' => $product->get_id(),
						'sku'        => $sku,
						'action'     => 'stock_set_zero',
						'reason'     => 'insufficient_stock_error',
					),
					true,
					'Stock set to zero due to insufficient stock error from Protheus'
				);
			}
		}
	}

	/**
	 * Get all SKUs from order items
	 *
	 * @param \WC_Order $order WooCommerce order object.
	 * @return array Array of SKUs.
	 */
	private function get_order_skus( \WC_Order $order ): array {
		$skus = array();
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( $product ) {
				$skus[] = $product->get_sku();
			}
		}
		return $skus;
	}

	/**
	 * Format error admin note with detailed information
	 *
	 * Creates a user-friendly admin note with error details and guidance.
	 *
	 * @param string      $error_message Error message.
	 * @param string      $error_type Error type classification.
	 * @param string|null $business_error Business error type.
	 * @return string Formatted admin note.
	 */
	private function format_error_admin_note( string $error_message, string $error_type, ?string $business_error ): string {
		$note = __( 'Protheus sync failed', 'absloja-protheus-connector' ) . "\n\n";

		// Add specific guidance based on error type
		if ( $business_error ) {
			switch ( $business_error ) {
				case 'tes_error':
					$note .= __( '⚠️ TES Configuration Error', 'absloja-protheus-connector' ) . "\n";
					$note .= __( 'The TES (Tipo de Entrada/Saída) code for this order\'s state is not configured or invalid in Protheus.', 'absloja-protheus-connector' ) . "\n";
					$note .= __( 'Action required: Configure the TES mapping for the customer\'s state in plugin settings or verify TES exists in Protheus.', 'absloja-protheus-connector' ) . "\n\n";
					break;

				case 'stock_insufficient':
					$note .= __( '⚠️ Insufficient Stock', 'absloja-protheus-connector' ) . "\n";
					$note .= __( 'One or more products in this order do not have sufficient stock in Protheus.', 'absloja-protheus-connector' ) . "\n";
					$note .= __( 'Action taken: Product stock has been updated to zero in WooCommerce to prevent further sales.', 'absloja-protheus-connector' ) . "\n";
					$note .= __( 'Action required: Verify stock levels in Protheus and manually process this order.', 'absloja-protheus-connector' ) . "\n\n";
					break;

				case 'document_invalid':
					$note .= __( '⚠️ Invalid CPF/CNPJ', 'absloja-protheus-connector' ) . "\n";
					$note .= __( 'The customer\'s CPF or CNPJ is invalid or not accepted by Protheus.', 'absloja-protheus-connector' ) . "\n";
					$note .= __( 'Action required: Verify and correct the customer\'s document number.', 'absloja-protheus-connector' ) . "\n\n";
					break;

				case 'customer_not_found':
					$note .= __( '⚠️ Customer Not Found', 'absloja-protheus-connector' ) . "\n";
					$note .= __( 'The customer could not be found or created in Protheus.', 'absloja-protheus-connector' ) . "\n";
					$note .= __( 'Action required: Verify customer data and try syncing again.', 'absloja-protheus-connector' ) . "\n\n";
					break;

				case 'product_not_found':
					$note .= __( '⚠️ Product Not Found', 'absloja-protheus-connector' ) . "\n";
					$note .= __( 'One or more products in this order do not exist in Protheus.', 'absloja-protheus-connector' ) . "\n";
					$note .= __( 'Action required: Verify product SKUs match between WooCommerce and Protheus.', 'absloja-protheus-connector' ) . "\n\n";
					break;
			}

			$note .= __( '⚠️ This order requires manual review and will not be retried automatically.', 'absloja-protheus-connector' ) . "\n\n";
		} else {
			// Transient error - will be retried
			$transient_errors = array( 'server_error', 'timeout_error', 'network_error', 'connection_error', 'dns_error', 'ssl_error' );
			if ( in_array( $error_type, $transient_errors, true ) ) {
				$note .= __( 'ℹ️ Temporary Connection Issue', 'absloja-protheus-connector' ) . "\n";
				$note .= __( 'This appears to be a temporary network or server issue.', 'absloja-protheus-connector' ) . "\n";
				$note .= __( 'The system will automatically retry this sync operation.', 'absloja-protheus-connector' ) . "\n\n";
			}
		}

		$note .= __( 'Error details:', 'absloja-protheus-connector' ) . "\n";
		$note .= $error_message;

		return $note;
	}

	/**
	 * Check if order status change should be blocked due to sync failure
	 *
	 * Prevents status changes on orders that failed to sync to Protheus.
	 * This ensures data consistency between WooCommerce and Protheus.
	 *
	 * @param \WC_Order $order WooCommerce order object.
	 * @return bool True if status change should be blocked, false otherwise.
	 */
	public function should_block_status_change( \WC_Order $order ): bool {
		$sync_status = $order->get_meta( '_protheus_sync_status', true );
		
		// Block status changes if sync failed
		return $sync_status === 'error';
	}

	/**
	 * Get status change block message
	 *
	 * Returns a user-friendly message explaining why status change is blocked.
	 *
	 * @param \WC_Order $order WooCommerce order object.
	 * @return string Block message.
	 */
	public function get_status_block_message( \WC_Order $order ): string {
		$sync_error = $order->get_meta( '_protheus_sync_error', true );
		$business_error = $order->get_meta( '_protheus_business_error', true );
		
		$message = __( 'Status change blocked: This order failed to sync to Protheus and requires manual review.', 'absloja-protheus-connector' );
		
		if ( $business_error ) {
			$message .= ' ' . sprintf(
				/* translators: %s: Business error type */
				__( 'Error type: %s.', 'absloja-protheus-connector' ),
				$business_error
			);
		}
		
		if ( $sync_error ) {
			$message .= ' ' . sprintf(
				/* translators: %s: Error message */
				__( 'Details: %s', 'absloja-protheus-connector' ),
				$sync_error
			);
		}
		
		$message .= ' ' . __( 'Please resolve the sync issue before changing the order status.', 'absloja-protheus-connector' );
		
		return $message;
	}
}
