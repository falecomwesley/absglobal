<?php
/**
 * Error Handler Module
 *
 * @package ABSLoja\ProtheusConnector
 */

namespace ABSLoja\ProtheusConnector\Modules;

/**
 * Error_Handler class
 *
 * Handles business errors, administrative notifications, and error classification.
 */
class Error_Handler {
	/**
	 * Logger instance
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor
	 *
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Detect and classify business errors from API response
	 *
	 * @param array $response API response array.
	 * @return array|null Business error details or null if not a business error.
	 */
	public function detect_business_error( array $response ): ?array {
		if ( $response['success'] ) {
			return null;
		}

		$error_message = $response['error'] ?? '';
		$error_type    = $this->classify_business_error( $error_message );

		if ( $error_type === 'unknown' ) {
			return null;
		}

		return array(
			'type'    => $error_type,
			'message' => $error_message,
			'code'    => $response['status_code'] ?? 0,
		);
	}

	/**
	 * Classify business error type
	 *
	 * @param string $error_message Error message from API.
	 * @return string Error type classification.
	 */
	private function classify_business_error( string $error_message ): string {
		$error_lower = strtolower( $error_message );

		// TES errors.
		if ( strpos( $error_lower, 'tes' ) !== false ||
		     strpos( $error_lower, 'tipo de entrada' ) !== false ||
		     strpos( $error_lower, 'tipo de saída' ) !== false ) {
			return 'tes_error';
		}

		// Stock errors.
		if ( strpos( $error_lower, 'estoque' ) !== false ||
		     strpos( $error_lower, 'stock' ) !== false ||
		     strpos( $error_lower, 'insufficient' ) !== false ||
		     strpos( $error_lower, 'insuficiente' ) !== false ) {
			return 'stock_error';
		}

		// CPF/CNPJ validation errors.
		if ( strpos( $error_lower, 'cpf' ) !== false ||
		     strpos( $error_lower, 'cnpj' ) !== false ||
		     strpos( $error_lower, 'cgc' ) !== false ||
		     strpos( $error_lower, 'documento' ) !== false ||
		     strpos( $error_lower, 'invalid document' ) !== false ) {
			return 'document_error';
		}

		// Customer errors.
		if ( strpos( $error_lower, 'cliente' ) !== false ||
		     strpos( $error_lower, 'customer' ) !== false ||
		     strpos( $error_lower, 'cadastro' ) !== false ) {
			return 'customer_error';
		}

		// Product errors.
		if ( strpos( $error_lower, 'produto' ) !== false ||
		     strpos( $error_lower, 'product' ) !== false ||
		     strpos( $error_lower, 'sku' ) !== false ) {
			return 'product_error';
		}

		// Payment errors.
		if ( strpos( $error_lower, 'pagamento' ) !== false ||
		     strpos( $error_lower, 'payment' ) !== false ||
		     strpos( $error_lower, 'condição' ) !== false ) {
			return 'payment_error';
		}

		return 'unknown';
	}

	/**
	 * Handle business error
	 *
	 * Logs error, adds order note, and marks for manual review.
	 * Does NOT schedule retry for business errors.
	 *
	 * @param array    $error Business error details.
	 * @param \WC_Order $order WooCommerce order object.
	 * @param string   $context Context where error occurred.
	 * @return void
	 */
	public function handle_business_error( array $error, $order, string $context ): void {
		// Log the business error.
		$this->logger->log_error(
			sprintf( 'Business error in %s: %s', $context, $error['message'] ),
			new \Exception( $error['message'] ),
			array(
				'error_type' => $error['type'],
				'order_id'   => $order->get_id(),
				'context'    => $context,
			)
		);

		// Add admin note to order.
		$note = $this->get_business_error_note( $error );
		$order->add_order_note( $note, false, true );

		// Mark order for manual review.
		$order->update_meta_data( '_protheus_sync_status', 'error' );
		$order->update_meta_data( '_protheus_sync_error', $error['message'] );
		$order->update_meta_data( '_protheus_error_type', $error['type'] );
		$order->update_meta_data( '_protheus_requires_manual_review', true );
		$order->save();

		// Send admin notification for critical errors.
		if ( in_array( $error['type'], array( 'tes_error', 'stock_error' ), true ) ) {
			$this->send_admin_notification( $error, $order );
		}
	}

	/**
	 * Get formatted business error note for order
	 *
	 * @param array $error Business error details.
	 * @return string Formatted error note.
	 */
	private function get_business_error_note( array $error ): string {
		$notes = array(
			'tes_error'      => __( 'Protheus Sync Error: TES (Tipo de Entrada/Saída) not found or invalid. Please configure TES rules in plugin settings or contact support.', 'absloja-protheus-connector' ),
			'stock_error'    => __( 'Protheus Sync Error: Insufficient stock in Protheus. Stock has been updated in WooCommerce to prevent further sales.', 'absloja-protheus-connector' ),
			'document_error' => __( 'Protheus Sync Error: Invalid CPF/CNPJ. Please verify customer document number.', 'absloja-protheus-connector' ),
			'customer_error' => __( 'Protheus Sync Error: Customer registration failed. Please verify customer data.', 'absloja-protheus-connector' ),
			'product_error'  => __( 'Protheus Sync Error: Product not found or invalid in Protheus.', 'absloja-protheus-connector' ),
			'payment_error'  => __( 'Protheus Sync Error: Payment condition not configured. Please map payment methods in plugin settings.', 'absloja-protheus-connector' ),
		);

		$note = $notes[ $error['type'] ] ?? __( 'Protheus Sync Error: Unknown business error.', 'absloja-protheus-connector' );

		return sprintf(
			'%s<br><strong>%s:</strong> %s',
			$note,
			__( 'Error Details', 'absloja-protheus-connector' ),
			esc_html( $error['message'] )
		);
	}

	/**
	 * Handle stock insufficient error
	 *
	 * Updates WooCommerce stock to prevent further sales.
	 *
	 * @param \WC_Order $order WooCommerce order object.
	 * @return void
	 */
	public function handle_stock_insufficient_error( $order ): void {
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}

			// Set stock to 0 and hide product.
			$product->set_stock_quantity( 0 );
			$product->set_catalog_visibility( 'hidden' );
			$product->save();

			// Log the stock update.
			$this->logger->log_sync_operation(
				'stock_update',
				array(
					'product_id' => $product->get_id(),
					'sku'        => $product->get_sku(),
					'quantity'   => 0,
					'reason'     => 'insufficient_stock_error',
				),
				true
			);
		}
	}

	/**
	 * Send admin notification email
	 *
	 * @param array    $error Business error details.
	 * @param \WC_Order $order WooCommerce order object.
	 * @return void
	 */
	private function send_admin_notification( array $error, $order ): void {
		$admin_email = get_option( 'admin_email' );
		$subject     = sprintf(
			/* translators: %s: order number */
			__( '[Protheus Connector] Business Error - Order #%s', 'absloja-protheus-connector' ),
			$order->get_order_number()
		);

		$message = sprintf(
			/* translators: 1: order number, 2: error type, 3: error message, 4: order edit URL */
			__(
				"A business error occurred while syncing order #%1\$s to Protheus.\n\n" .
				"Error Type: %2\$s\n" .
				"Error Message: %3\$s\n\n" .
				"This order requires manual review. Please check the order details:\n%4\$s\n\n" .
				"This is an automated notification from ABS Loja Protheus Connector.",
				'absloja-protheus-connector'
			),
			$order->get_order_number(),
			$error['type'],
			$error['message'],
			$order->get_edit_order_url()
		);

		wp_mail( $admin_email, $subject, $message );
	}

	/**
	 * Display admin dashboard notice for configuration errors
	 *
	 * @param string $message Error message.
	 * @param string $type Notice type (error, warning, info).
	 * @return void
	 */
	public function add_admin_notice( string $message, string $type = 'error' ): void {
		add_action(
			'admin_notices',
			function () use ( $message, $type ) {
				printf(
					'<div class="notice notice-%s is-dismissible"><p><strong>%s:</strong> %s</p></div>',
					esc_attr( $type ),
					esc_html__( 'Protheus Connector', 'absloja-protheus-connector' ),
					esc_html( $message )
				);
			}
		);
	}

	/**
	 * Check if error should trigger retry
	 *
	 * Business errors should NOT trigger automatic retry.
	 * Only network/server errors should retry.
	 *
	 * @param array $response API response array.
	 * @return bool True if should retry, false otherwise.
	 */
	public function should_retry( array $response ): bool {
		if ( $response['success'] ) {
			return false;
		}

		$error_type = $response['error_type'] ?? 'unknown_error';

		// Network errors should retry.
		$retry_types = array(
			'timeout_error',
			'dns_error',
			'connection_error',
			'network_error',
			'server_error',
		);

		return in_array( $error_type, $retry_types, true );
	}

	/**
	 * Get user-friendly error message
	 *
	 * @param array $response API response array.
	 * @return string User-friendly error message.
	 */
	public function get_user_friendly_message( array $response ): string {
		$error_type = $response['error_type'] ?? 'unknown_error';

		$messages = array(
			'timeout_error'     => __( 'Connection timeout. The Protheus server took too long to respond.', 'absloja-protheus-connector' ),
			'dns_error'         => __( 'DNS resolution failed. Unable to reach Protheus server.', 'absloja-protheus-connector' ),
			'ssl_error'         => __( 'SSL certificate error. Please check Protheus server SSL configuration.', 'absloja-protheus-connector' ),
			'connection_error'  => __( 'Connection failed. Unable to reach Protheus server.', 'absloja-protheus-connector' ),
			'network_error'     => __( 'Network error. Please check your internet connection.', 'absloja-protheus-connector' ),
			'auth_error'        => __( 'Authentication failed. Please check your API credentials.', 'absloja-protheus-connector' ),
			'server_error'      => __( 'Protheus server error. Please contact your system administrator.', 'absloja-protheus-connector' ),
			'client_error'      => __( 'Invalid request. Please check your configuration.', 'absloja-protheus-connector' ),
		);

		return $messages[ $error_type ] ?? $response['error'] ?? __( 'Unknown error occurred.', 'absloja-protheus-connector' );
	}
}
