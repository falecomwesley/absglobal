<?php
/**
 * Webhook Handler Class
 *
 * Handles incoming webhooks from Protheus ERP for order status updates and stock updates.
 *
 * @package ABSLoja\ProtheusConnector\Modules
 * @since 1.0.0
 */

namespace ABSLoja\ProtheusConnector\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Webhook_Handler
 *
 * Processes webhooks from Protheus including order status updates and stock updates.
 * Provides REST API endpoints and authentication for webhook requests.
 *
 * @since 1.0.0
 */
class Webhook_Handler {

	/**
	 * Auth Manager instance
	 *
	 * @var Auth_Manager
	 */
	private $auth_manager;

	/**
	 * Logger instance
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * WordPress options prefix
	 *
	 * @var string
	 */
	private const OPTION_PREFIX = 'absloja_protheus_';

	/**
	 * REST API namespace
	 *
	 * @var string
	 */
	private const REST_NAMESPACE = 'absloja-protheus/v1';

	/**
	 * Constructor
	 *
	 * @param Auth_Manager $auth_manager Auth Manager instance for configuration access.
	 * @param Logger       $logger       Logger instance for webhook logging.
	 */
	public function __construct( Auth_Manager $auth_manager, Logger $logger ) {
		$this->auth_manager = $auth_manager;
		$this->logger       = $logger;
	}

	/**
	 * Register REST API routes for webhooks
	 *
	 * Registers the following endpoints:
	 * - POST /wp-json/absloja-protheus/v1/webhook/order-status
	 * - POST /wp-json/absloja-protheus/v1/webhook/stock
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/webhook/order-status',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_order_status_update' ),
				'permission_callback' => array( $this, 'authenticate_webhook' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/webhook/stock',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_stock_update' ),
				'permission_callback' => array( $this, 'authenticate_webhook' ),
			)
		);
	}

	/**
	 * Handle order status update webhook
	 *
	 * Processes incoming order status updates from Protheus.
	 * Expected payload:
	 * {
	 *   "order_id": "123456",           // Protheus order ID
	 *   "woo_order_id": "789",          // WooCommerce order ID
	 *   "status": "approved",           // Protheus status
	 *   "tracking_code": "BR123456789", // Optional tracking code
	 *   "invoice_number": "000123",     // Optional invoice number
	 *   "invoice_date": "2024-01-15"    // Optional invoice date
	 * }
	 *
	 * @param \WP_REST_Request $request The REST API request object.
	 * @return \WP_REST_Response REST API response.
	 */
	public function handle_order_status_update( \WP_REST_Request $request ): \WP_REST_Response {
		$start_time = microtime( true );
		$payload    = $request->get_json_params();

		// Validate required fields
		if ( empty( $payload['woo_order_id'] ) || empty( $payload['status'] ) ) {
			$response = new \WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Missing required fields: woo_order_id and status are required',
				),
				400
			);

			$this->logger->log_webhook( 'order_status_update', $payload, $response );
			return $response;
		}

		$woo_order_id = absint( $payload['woo_order_id'] );
		$order        = wc_get_order( $woo_order_id );

		// Check if order exists
		if ( ! $order ) {
			$response = new \WP_REST_Response(
				array(
					'success' => false,
					'message' => sprintf( 'Order not found: %d', $woo_order_id ),
				),
				404
			);

			$this->logger->log_webhook( 'order_status_update', $payload, $response );
			return $response;
		}

		// Map Protheus status to WooCommerce status
		$woo_status = $this->map_protheus_status_to_woo( $payload['status'] );

		// Update order status
		$order->update_status( $woo_status, sprintf( 'Status updated via Protheus webhook: %s', $payload['status'] ) );

		// Store additional metadata if provided
		if ( ! empty( $payload['tracking_code'] ) ) {
			$order->update_meta_data( '_protheus_tracking_code', sanitize_text_field( $payload['tracking_code'] ) );
		}

		if ( ! empty( $payload['invoice_number'] ) ) {
			$order->update_meta_data( '_protheus_invoice_number', sanitize_text_field( $payload['invoice_number'] ) );
		}

		if ( ! empty( $payload['invoice_date'] ) ) {
			$order->update_meta_data( '_protheus_invoice_date', sanitize_text_field( $payload['invoice_date'] ) );
		}

		if ( ! empty( $payload['order_id'] ) ) {
			$order->update_meta_data( '_protheus_order_id', sanitize_text_field( $payload['order_id'] ) );
		}

		$order->save();

		$duration = microtime( true ) - $start_time;

		$response = new \WP_REST_Response(
			array(
				'success' => true,
				'message' => sprintf( 'Order %d status updated to %s', $woo_order_id, $woo_status ),
			),
			200
		);

		$this->logger->log_webhook( 'order_status_update', $payload, $response );

		return $response;
	}

	/**
	 * Handle stock update webhook
	 *
	 * Processes incoming stock updates from Protheus.
	 * Expected payload:
	 * {
	 *   "sku": "PROD001",      // Product SKU
	 *   "quantity": 50,        // Stock quantity
	 *   "warehouse": "01"      // Optional warehouse code
	 * }
	 *
	 * @param \WP_REST_Request $request The REST API request object.
	 * @return \WP_REST_Response REST API response.
	 */
	public function handle_stock_update( \WP_REST_Request $request ): \WP_REST_Response {
		$start_time = microtime( true );
		$payload    = $request->get_json_params();

		// Validate required fields
		if ( empty( $payload['sku'] ) || ! isset( $payload['quantity'] ) ) {
			$response = new \WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Missing required fields: sku and quantity are required',
				),
				400
			);

			$this->logger->log_webhook( 'stock_update', $payload, $response );
			return $response;
		}

		$sku      = sanitize_text_field( $payload['sku'] );
		$quantity = absint( $payload['quantity'] );

		// Find product by SKU
		$product_id = wc_get_product_id_by_sku( $sku );

		if ( ! $product_id ) {
			$response = new \WP_REST_Response(
				array(
					'success' => false,
					'message' => sprintf( 'Product not found with SKU: %s', $sku ),
				),
				404
			);

			$this->logger->log_webhook( 'stock_update', $payload, $response );
			return $response;
		}

		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			$response = new \WP_REST_Response(
				array(
					'success' => false,
					'message' => sprintf( 'Failed to load product with ID: %d', $product_id ),
				),
				500
			);

			$this->logger->log_webhook( 'stock_update', $payload, $response );
			return $response;
		}

		// Update stock quantity
		$product->set_manage_stock( true );
		$product->set_stock_quantity( $quantity );

		// Handle visibility based on stock
		if ( $quantity === 0 ) {
			// Hide product when out of stock
			$product->set_catalog_visibility( 'hidden' );
		} else {
			// Restore visibility if it was hidden due to stock
			$current_visibility = $product->get_catalog_visibility();
			if ( 'hidden' === $current_visibility ) {
				$product->set_catalog_visibility( 'visible' );
			}
		}

		$product->save();

		$duration = microtime( true ) - $start_time;

		$response = new \WP_REST_Response(
			array(
				'success' => true,
				'message' => sprintf( 'Stock updated for product %s (ID: %d) to %d', $sku, $product_id, $quantity ),
			),
			200
		);

		$this->logger->log_webhook( 'stock_update', $payload, $response );

		return $response;
	}

	/**
	 * Authenticate webhook request
	 *
	 * Validates webhook requests using configured authentication method:
	 * - Method 1: Token-based authentication via X-Protheus-Token header
	 * - Method 2: HMAC signature authentication via X-Protheus-Signature header
	 *
	 * @param \WP_REST_Request $request The REST API request object.
	 * @return bool True if authenticated, false otherwise.
	 */
	public function authenticate_webhook( \WP_REST_Request $request ): bool {
		// Get configured webhook token and secret
		$webhook_token  = get_option( self::OPTION_PREFIX . 'webhook_token', '' );
		$webhook_secret = get_option( self::OPTION_PREFIX . 'webhook_secret', '' );

		// If no authentication is configured, deny access
		if ( empty( $webhook_token ) && empty( $webhook_secret ) ) {
			return false;
		}

		// Method 1: Token-based authentication
		$provided_token = $request->get_header( 'X-Protheus-Token' );
		if ( ! empty( $webhook_token ) && ! empty( $provided_token ) ) {
			if ( hash_equals( $webhook_token, $provided_token ) ) {
				return true;
			}
		}

		// Method 2: HMAC signature authentication
		$provided_signature = $request->get_header( 'X-Protheus-Signature' );
		if ( ! empty( $webhook_secret ) && ! empty( $provided_signature ) ) {
			$body              = $request->get_body();
			$calculated_signature = hash_hmac( 'sha256', $body, $webhook_secret );
			$provided_signature   = trim( (string) $provided_signature );

			// Accept common formats: "<hash>" or "sha256=<hash>".
			if ( strpos( $provided_signature, 'sha256=' ) === 0 ) {
				$provided_signature = substr( $provided_signature, 7 );
			}

			if ( hash_equals( $calculated_signature, $provided_signature ) ) {
				return true;
			}
		}

		// Authentication failed
		return false;
	}

	/**
	 * Map Protheus status to WooCommerce status
	 *
	 * Converts Protheus order status codes to WooCommerce order statuses
	 * using configured mapping or default mapping.
	 *
	 * @param string $protheus_status Protheus status code.
	 * @return string WooCommerce status.
	 */
	private function map_protheus_status_to_woo( string $protheus_status ): string {
		// Get configured status mapping
		$status_mapping = get_option( self::OPTION_PREFIX . 'status_mapping', array() );

		// Default mapping if not configured
		$default_mapping = array(
			'pending'   => 'pending',
			'approved'  => 'processing',
			'invoiced'  => 'completed',
			'shipped'   => 'completed',
			'cancelled' => 'cancelled',
			'rejected'  => 'failed',
		);

		// Merge with configured mapping
		$mapping = ! empty( $status_mapping ) ? $status_mapping : $default_mapping;

		// Return mapped status or default to 'processing'
		return $mapping[ $protheus_status ] ?? 'processing';
	}
}
