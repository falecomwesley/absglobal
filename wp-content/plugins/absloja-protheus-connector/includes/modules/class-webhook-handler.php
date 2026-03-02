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
			'/orders',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_orders_list' ),
				'permission_callback' => array( $this, 'authenticate_api_request' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/orders/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_order_detail' ),
				'permission_callback' => array( $this, 'authenticate_api_request' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/customers/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_customer_detail' ),
				'permission_callback' => array( $this, 'authenticate_api_request' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/products',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_products_list' ),
				'permission_callback' => array( $this, 'authenticate_api_request' ),
			)
		);

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
	 * Handle orders list endpoint
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function handle_orders_list( \WP_REST_Request $request ): \WP_REST_Response {
		$page          = max( 1, absint( $request->get_param( 'page' ) ?: 1 ) );
		$limit         = min( 200, max( 1, absint( $request->get_param( 'limit' ) ?: 50 ) ) );
		$updated_after = $request->get_param( 'updated_after' );

		$args = array(
			'limit'    => $limit,
			'page'     => $page,
			'paginate' => true,
			'orderby'  => 'date_modified',
			'order'    => 'ASC',
			'return'   => 'objects',
		);

		$statuses = $this->parse_status_filter( $request->get_param( 'status' ) );
		if ( ! empty( $statuses ) ) {
			$args['status'] = $statuses;
		}

		if ( ! empty( $updated_after ) ) {
			$timestamp = strtotime( (string) $updated_after );
			if ( false === $timestamp ) {
				return new \WP_REST_Response(
					array(
						'success' => false,
						'message' => 'Invalid updated_after parameter. Use ISO 8601 date format.',
					),
					400
				);
			}
			$args['date_modified'] = '>' . gmdate( 'Y-m-d H:i:s', $timestamp );
		}

		$result = wc_get_orders( $args );
		$orders = is_object( $result ) && isset( $result->orders ) ? $result->orders : array();

		$data = array_map(
			function ( $order ) {
				return $this->serialize_order( $order );
			},
			$orders
		);

		return new \WP_REST_Response(
			array(
				'success'    => true,
				'data'       => $data,
				'pagination' => array(
					'page'        => $page,
					'limit'       => $limit,
					'total'       => is_object( $result ) && isset( $result->total ) ? (int) $result->total : count( $data ),
					'total_pages' => is_object( $result ) && isset( $result->max_num_pages ) ? (int) $result->max_num_pages : 1,
				),
			),
			200
		);
	}

	/**
	 * Handle order detail endpoint
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function handle_order_detail( \WP_REST_Request $request ): \WP_REST_Response {
		$order_id = absint( $request->get_param( 'id' ) );
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => sprintf( 'Order not found: %d', $order_id ),
				),
				404
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $this->serialize_order( $order ),
			),
			200
		);
	}

	/**
	 * Handle customer detail endpoint
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function handle_customer_detail( \WP_REST_Request $request ): \WP_REST_Response {
		$customer_id = absint( $request->get_param( 'id' ) );
		$user        = get_user_by( 'id', $customer_id );

		if ( ! $user ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => sprintf( 'Customer not found: %d', $customer_id ),
				),
				404
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $this->serialize_customer( $user ),
			),
			200
		);
	}

	/**
	 * Handle products list endpoint
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function handle_products_list( \WP_REST_Request $request ): \WP_REST_Response {
		$page          = max( 1, absint( $request->get_param( 'page' ) ?: 1 ) );
		$limit         = min( 200, max( 1, absint( $request->get_param( 'limit' ) ?: 100 ) ) );
		$updated_after = $request->get_param( 'updated_after' );

		$args = array(
			'limit'    => $limit,
			'page'     => $page,
			'paginate' => true,
			'orderby'  => 'date_modified',
			'order'    => 'ASC',
			'status'   => array( 'publish' ),
			'return'   => 'objects',
		);

		if ( ! empty( $updated_after ) ) {
			$timestamp = strtotime( (string) $updated_after );
			if ( false === $timestamp ) {
				return new \WP_REST_Response(
					array(
						'success' => false,
						'message' => 'Invalid updated_after parameter. Use ISO 8601 date format.',
					),
					400
				);
			}
			$args['date_modified'] = '>' . gmdate( 'Y-m-d H:i:s', $timestamp );
		}

		$result   = wc_get_products( $args );
		$products = is_object( $result ) && isset( $result->products ) ? $result->products : array();

		$data = array_map(
			function ( $product ) {
				return $this->serialize_product( $product );
			},
			$products
		);

		return new \WP_REST_Response(
			array(
				'success'    => true,
				'data'       => $data,
				'pagination' => array(
					'page'        => $page,
					'limit'       => $limit,
					'total'       => is_object( $result ) && isset( $result->total ) ? (int) $result->total : count( $data ),
					'total_pages' => is_object( $result ) && isset( $result->max_num_pages ) ? (int) $result->max_num_pages : 1,
				),
			),
			200
		);
	}

	/**
	 * Handle order status update webhook
	 *
	 * Processes incoming order status updates from Protheus.
	 * Expected payload:
	 * {
	 *   "woo_order_id": "789",          // WooCommerce order ID
	 *   "status": "approved"            // Protheus status
	 * }
	 *
	 * @param \WP_REST_Request $request The REST API request object.
	 * @return \WP_REST_Response REST API response.
	 */
	public function handle_order_status_update( \WP_REST_Request $request ): \WP_REST_Response {
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

		$order->save();

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
	 *   "quantity": 50         // Stock quantity
	 * }
	 *
	 * @param \WP_REST_Request $request The REST API request object.
	 * @return \WP_REST_Response REST API response.
	 */
	public function handle_stock_update( \WP_REST_Request $request ): \WP_REST_Response {
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
	 * Authenticate API consumer request.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	public function authenticate_api_request( \WP_REST_Request $request ): bool {
		if ( current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}

		return $this->authenticate_webhook( $request );
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

	/**
	 * Parse status filter parameter.
	 *
	 * @param mixed $status_param Raw status parameter.
	 * @return array
	 */
	private function parse_status_filter( $status_param ): array {
		if ( empty( $status_param ) ) {
			return array();
		}

		$statuses = is_array( $status_param ) ? $status_param : explode( ',', (string) $status_param );
		$statuses = array_map(
			function ( $status ) {
				$status = sanitize_text_field( (string) $status );
				$status = strtolower( $status );
				return preg_replace( '/^wc-/', '', $status );
			},
			$statuses
		);

		return array_values( array_filter( array_unique( $statuses ) ) );
	}

	/**
	 * Serialize order to API response format.
	 *
	 * @param \WC_Order $order Order.
	 * @return array
	 */
	private function serialize_order( \WC_Order $order ): array {
		$items = array();

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			$items[] = array(
				'id'         => $item->get_id(),
				'product_id' => $item->get_product_id(),
				'sku'        => $product ? $product->get_sku() : '',
				'name'       => $item->get_name(),
				'qty'        => (float) $item->get_quantity(),
				'unit_price' => (float) $order->get_item_total( $item, false ),
				'total'      => (float) $item->get_total(),
			);
		}

		$document = $order->get_meta( '_billing_cpf', true );
		if ( empty( $document ) ) {
			$document = $order->get_meta( '_billing_cnpj', true );
		}
		if ( empty( $document ) ) {
			$document = $order->get_meta( '_billing_cpfcnpj', true );
		}

		return array(
			'id'         => $order->get_id(),
			'number'     => $order->get_order_number(),
			'status'     => $order->get_status(),
			'created_at' => $this->format_datetime( $order->get_date_created() ),
			'updated_at' => $this->format_datetime( $order->get_date_modified() ),
			'currency'   => $order->get_currency(),
			'customer'   => array(
				'id'       => $order->get_customer_id(),
				'name'     => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
				'email'    => $order->get_billing_email(),
				'document' => $document,
			),
			'totals'     => array(
				'subtotal' => (float) $order->get_subtotal(),
				'discount' => (float) $order->get_discount_total(),
				'shipping' => (float) $order->get_shipping_total(),
				'total'    => (float) $order->get_total(),
			),
			'payment'    => array(
				'method'       => $order->get_payment_method(),
				'method_title' => $order->get_payment_method_title(),
			),
			'billing'    => array(
				'first_name' => $order->get_billing_first_name(),
				'last_name'  => $order->get_billing_last_name(),
				'company'    => $order->get_billing_company(),
				'address_1'  => $order->get_billing_address_1(),
				'address_2'  => $order->get_billing_address_2(),
				'city'       => $order->get_billing_city(),
				'state'      => $order->get_billing_state(),
				'postcode'   => $order->get_billing_postcode(),
				'country'    => $order->get_billing_country(),
				'phone'      => $order->get_billing_phone(),
			),
			'shipping'   => array(
				'first_name' => $order->get_shipping_first_name(),
				'last_name'  => $order->get_shipping_last_name(),
				'company'    => $order->get_shipping_company(),
				'address_1'  => $order->get_shipping_address_1(),
				'address_2'  => $order->get_shipping_address_2(),
				'city'       => $order->get_shipping_city(),
				'state'      => $order->get_shipping_state(),
				'postcode'   => $order->get_shipping_postcode(),
				'country'    => $order->get_shipping_country(),
			),
			'items'      => $items,
		);
	}

	/**
	 * Serialize customer to API response format.
	 *
	 * @param \WP_User $user User.
	 * @return array
	 */
	private function serialize_customer( \WP_User $user ): array {
		$customer_id = (int) $user->ID;
		$document    = get_user_meta( $customer_id, 'billing_cpf', true );
		if ( empty( $document ) ) {
			$document = get_user_meta( $customer_id, 'billing_cnpj', true );
		}
		if ( empty( $document ) ) {
			$document = get_user_meta( $customer_id, 'billing_cpfcnpj', true );
		}

		return array(
			'id'         => $customer_id,
			'email'      => (string) $user->user_email,
			'first_name' => (string) $user->first_name,
			'last_name'  => (string) $user->last_name,
			'name'       => (string) $user->display_name,
			'document'   => (string) $document,
			'registered_at' => gmdate( 'Y-m-d\TH:i:s\Z', strtotime( (string) $user->user_registered ) ),
			'billing'    => array(
				'first_name' => (string) get_user_meta( $customer_id, 'billing_first_name', true ),
				'last_name'  => (string) get_user_meta( $customer_id, 'billing_last_name', true ),
				'company'    => (string) get_user_meta( $customer_id, 'billing_company', true ),
				'address_1'  => (string) get_user_meta( $customer_id, 'billing_address_1', true ),
				'address_2'  => (string) get_user_meta( $customer_id, 'billing_address_2', true ),
				'city'       => (string) get_user_meta( $customer_id, 'billing_city', true ),
				'state'      => (string) get_user_meta( $customer_id, 'billing_state', true ),
				'postcode'   => (string) get_user_meta( $customer_id, 'billing_postcode', true ),
				'country'    => (string) get_user_meta( $customer_id, 'billing_country', true ),
				'phone'      => (string) get_user_meta( $customer_id, 'billing_phone', true ),
			),
			'shipping'   => array(
				'first_name' => (string) get_user_meta( $customer_id, 'shipping_first_name', true ),
				'last_name'  => (string) get_user_meta( $customer_id, 'shipping_last_name', true ),
				'company'    => (string) get_user_meta( $customer_id, 'shipping_company', true ),
				'address_1'  => (string) get_user_meta( $customer_id, 'shipping_address_1', true ),
				'address_2'  => (string) get_user_meta( $customer_id, 'shipping_address_2', true ),
				'city'       => (string) get_user_meta( $customer_id, 'shipping_city', true ),
				'state'      => (string) get_user_meta( $customer_id, 'shipping_state', true ),
				'postcode'   => (string) get_user_meta( $customer_id, 'shipping_postcode', true ),
				'country'    => (string) get_user_meta( $customer_id, 'shipping_country', true ),
			),
		);
	}

	/**
	 * Serialize product to API response format.
	 *
	 * @param \WC_Product $product Product.
	 * @return array
	 */
	private function serialize_product( \WC_Product $product ): array {
		return array(
			'id'             => $product->get_id(),
			'sku'            => $product->get_sku(),
			'name'           => $product->get_name(),
			'type'           => $product->get_type(),
			'status'         => $product->get_status(),
			'price'          => (float) $product->get_price(),
			'regular_price'  => (float) $product->get_regular_price(),
			'sale_price'     => (float) $product->get_sale_price(),
			'stock_quantity' => $product->get_stock_quantity(),
			'stock_status'   => $product->get_stock_status(),
			'manage_stock'   => $product->get_manage_stock(),
			'updated_at'     => $this->format_datetime( $product->get_date_modified() ),
			'created_at'     => $this->format_datetime( $product->get_date_created() ),
			'permalink'      => get_permalink( $product->get_id() ),
		);
	}

	/**
	 * Format WooCommerce datetime to ISO 8601.
	 *
	 * @param \WC_DateTime|\DateTimeInterface|null $datetime Datetime.
	 * @return string|null
	 */
	private function format_datetime( $datetime ): ?string {
		if ( ! $datetime ) {
			return null;
		}

		if ( method_exists( $datetime, 'getTimestamp' ) ) {
			return gmdate( 'Y-m-d\TH:i:s\Z', $datetime->getTimestamp() );
		}

		return null;
	}
}
