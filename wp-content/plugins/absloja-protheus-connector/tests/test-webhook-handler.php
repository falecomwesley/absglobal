<?php
/**
 * Webhook Handler Validation Tests
 *
 * Validates that the Webhook_Handler implementation meets requirements:
 * - 5.1, 6.1: REST API endpoints are registered
 * - 5.2, 6.2: Webhook authentication works correctly
 * - 5.3: Order status updates are processed
 * - 6.3, 6.4: Stock updates are processed
 * - 5.5, 6.6: Returns 401 on authentication failure
 * - 5.6: Returns 404 when order not found
 * - 5.7, 6.7: Returns 200 on success
 *
 * @package ABSLoja\ProtheusConnector\Tests
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Test webhook handler functionality
 */
function test_webhook_handler() {
	echo "\n=== WEBHOOK HANDLER VALIDATION ===\n\n";

	// Initialize required components
	$auth_manager = new \ABSLoja\ProtheusConnector\Modules\Auth_Manager(
		array(
			'auth_type' => 'basic',
			'api_url'   => get_option( 'absloja_protheus_api_url', 'http://localhost:8080' ),
			'username'  => get_option( 'absloja_protheus_username', 'admin' ),
			'password'  => get_option( 'absloja_protheus_password', 'admin' ),
		)
	);

	$logger = new \ABSLoja\ProtheusConnector\Modules\Logger();
	$webhook_handler = new \ABSLoja\ProtheusConnector\Modules\Webhook_Handler( $auth_manager, $logger );

	// Test 1: Verify REST API routes are registered (Requirements 5.1, 6.1)
	echo "Test 1: Verifying REST API routes registration...\n";
	
	// Trigger rest_api_init to register routes
	do_action( 'rest_api_init' );
	
	$rest_server = rest_get_server();
	$routes = $rest_server->get_routes();
	
	$order_status_route = '/absloja-protheus/v1/webhook/order-status';
	$stock_route = '/absloja-protheus/v1/webhook/stock';
	
	if ( isset( $routes[ $order_status_route ] ) ) {
		echo "✓ Order status webhook endpoint registered: {$order_status_route}\n";
	} else {
		echo "✗ Order status webhook endpoint NOT registered\n";
	}
	
	if ( isset( $routes[ $stock_route ] ) ) {
		echo "✓ Stock webhook endpoint registered: {$stock_route}\n";
	} else {
		echo "✗ Stock webhook endpoint NOT registered\n";
	}
	echo "\n";

	// Test 2: Test webhook authentication (Requirements 5.2, 6.2)
	echo "Test 2: Testing webhook authentication...\n";
	
	// Set up test webhook token
	$test_token = 'test-webhook-token-' . wp_generate_password( 32, false );
	update_option( 'absloja_protheus_webhook_token', $test_token );
	
	// Create mock request with valid token
	$valid_request = new WP_REST_Request( 'POST', $order_status_route );
	$valid_request->set_header( 'X-Protheus-Token', $test_token );
	$valid_request->set_body( wp_json_encode( array(
		'woo_order_id' => 1,
		'status' => 'approved',
	) ) );
	
	$auth_result = $webhook_handler->authenticate_webhook( $valid_request );
	
	if ( $auth_result === true ) {
		echo "✓ Valid token authentication successful\n";
	} else {
		echo "✗ Valid token authentication failed\n";
	}
	
	// Create mock request with invalid token
	$invalid_request = new WP_REST_Request( 'POST', $order_status_route );
	$invalid_request->set_header( 'X-Protheus-Token', 'invalid-token' );
	$invalid_request->set_body( wp_json_encode( array(
		'woo_order_id' => 1,
		'status' => 'approved',
	) ) );
	
	$auth_result = $webhook_handler->authenticate_webhook( $invalid_request );
	
	if ( $auth_result === false ) {
		echo "✓ Invalid token authentication correctly rejected\n";
	} else {
		echo "✗ Invalid token authentication should have been rejected\n";
	}
	echo "\n";

	// Test 3: Test HMAC signature authentication
	echo "Test 3: Testing HMAC signature authentication...\n";
	
	$test_secret = 'test-webhook-secret-' . wp_generate_password( 32, false );
	update_option( 'absloja_protheus_webhook_secret', $test_secret );
	
	$payload = wp_json_encode( array(
		'woo_order_id' => 1,
		'status' => 'approved',
	) );
	
	$valid_signature = hash_hmac( 'sha256', $payload, $test_secret );
	
	$hmac_request = new WP_REST_Request( 'POST', $order_status_route );
	$hmac_request->set_header( 'X-Protheus-Signature', $valid_signature );
	$hmac_request->set_body( $payload );
	
	$auth_result = $webhook_handler->authenticate_webhook( $hmac_request );
	
	if ( $auth_result === true ) {
		echo "✓ Valid HMAC signature authentication successful\n";
	} else {
		echo "✗ Valid HMAC signature authentication failed\n";
	}
	
	// Test invalid signature
	$invalid_hmac_request = new WP_REST_Request( 'POST', $order_status_route );
	$invalid_hmac_request->set_header( 'X-Protheus-Signature', 'invalid-signature' );
	$invalid_hmac_request->set_body( $payload );
	
	$auth_result = $webhook_handler->authenticate_webhook( $invalid_hmac_request );
	
	if ( $auth_result === false ) {
		echo "✓ Invalid HMAC signature correctly rejected\n";
	} else {
		echo "✗ Invalid HMAC signature should have been rejected\n";
	}
	echo "\n";

	// Test 4: Test order status update (Requirement 5.3, 5.4)
	echo "Test 4: Testing order status update webhook...\n";
	
	// Create a test order
	$test_order = wc_create_order();
	$test_order->set_status( 'pending' );
	$test_order->save();
	$order_id = $test_order->get_id();
	
	echo "  - Created test order ID: {$order_id}\n";
	
	// Send order status update webhook
	$status_update_request = new WP_REST_Request( 'POST', $order_status_route );
	$status_update_request->set_header( 'X-Protheus-Token', $test_token );
	$status_update_request->set_body( wp_json_encode( array(
		'woo_order_id' => $order_id,
		'status' => 'approved',
		'order_id' => 'PROT-12345',
		'tracking_code' => 'BR123456789',
		'invoice_number' => '000123',
	) ) );
	
	$response = $webhook_handler->handle_order_status_update( $status_update_request );
	
	if ( $response->get_status() === 200 ) {
		echo "✓ Order status update returned 200 OK (Requirement 5.7)\n";
		
		// Verify order was updated
		$updated_order = wc_get_order( $order_id );
		$new_status = $updated_order->get_status();
		
		if ( $new_status === 'processing' ) {
			echo "✓ Order status correctly updated to 'processing'\n";
		} else {
			echo "✗ Order status not updated correctly (got: {$new_status})\n";
		}
		
		// Verify metadata was stored
		$tracking_code = $updated_order->get_meta( '_protheus_tracking_code' );
		$invoice_number = $updated_order->get_meta( '_protheus_invoice_number' );
		
		if ( $tracking_code === 'BR123456789' ) {
			echo "✓ Tracking code stored correctly\n";
		} else {
			echo "✗ Tracking code not stored correctly\n";
		}
		
		if ( $invoice_number === '000123' ) {
			echo "✓ Invoice number stored correctly\n";
		} else {
			echo "✗ Invoice number not stored correctly\n";
		}
	} else {
		echo "✗ Order status update failed with status: " . $response->get_status() . "\n";
	}
	echo "\n";

	// Test 5: Test order not found (Requirement 5.6)
	echo "Test 5: Testing order not found response...\n";
	
	$not_found_request = new WP_REST_Request( 'POST', $order_status_route );
	$not_found_request->set_header( 'X-Protheus-Token', $test_token );
	$not_found_request->set_body( wp_json_encode( array(
		'woo_order_id' => 999999,
		'status' => 'approved',
	) ) );
	
	$response = $webhook_handler->handle_order_status_update( $not_found_request );
	
	if ( $response->get_status() === 404 ) {
		echo "✓ Order not found correctly returns 404 (Requirement 5.6)\n";
	} else {
		echo "✗ Order not found should return 404, got: " . $response->get_status() . "\n";
	}
	echo "\n";

	// Test 6: Test stock update webhook (Requirements 6.3, 6.4)
	echo "Test 6: Testing stock update webhook...\n";
	
	// Create a test product
	$test_product = new WC_Product_Simple();
	$test_product->set_name( 'Test Webhook Product' );
	$test_product->set_sku( 'TEST-WEBHOOK-001' );
	$test_product->set_regular_price( 100 );
	$test_product->set_manage_stock( true );
	$test_product->set_stock_quantity( 0 );
	$test_product->set_catalog_visibility( 'hidden' );
	$test_product->save();
	$product_id = $test_product->get_id();
	
	echo "  - Created test product SKU: TEST-WEBHOOK-001 (ID: {$product_id})\n";
	
	// Send stock update webhook
	$stock_update_request = new WP_REST_Request( 'POST', $stock_route );
	$stock_update_request->set_header( 'X-Protheus-Token', $test_token );
	$stock_update_request->set_body( wp_json_encode( array(
		'sku' => 'TEST-WEBHOOK-001',
		'quantity' => 50,
		'warehouse' => '01',
	) ) );
	
	$response = $webhook_handler->handle_stock_update( $stock_update_request );
	
	if ( $response->get_status() === 200 ) {
		echo "✓ Stock update returned 200 OK (Requirement 6.7)\n";
		
		// Verify stock was updated
		$updated_product = wc_get_product( $product_id );
		$new_quantity = $updated_product->get_stock_quantity();
		
		if ( $new_quantity === 50 ) {
			echo "✓ Stock quantity correctly updated to 50 (Requirement 6.4)\n";
		} else {
			echo "✗ Stock quantity not updated correctly (got: {$new_quantity})\n";
		}
		
		// Verify visibility was restored
		$visibility = $updated_product->get_catalog_visibility();
		if ( $visibility === 'visible' ) {
			echo "✓ Product visibility restored when stock > 0\n";
		} else {
			echo "✗ Product visibility not restored (got: {$visibility})\n";
		}
	} else {
		echo "✗ Stock update failed with status: " . $response->get_status() . "\n";
	}
	echo "\n";

	// Test 7: Test stock update to zero (Requirement 6.5)
	echo "Test 7: Testing product hiding when stock = 0...\n";
	
	$zero_stock_request = new WP_REST_Request( 'POST', $stock_route );
	$zero_stock_request->set_header( 'X-Protheus-Token', $test_token );
	$zero_stock_request->set_body( wp_json_encode( array(
		'sku' => 'TEST-WEBHOOK-001',
		'quantity' => 0,
	) ) );
	
	$response = $webhook_handler->handle_stock_update( $zero_stock_request );
	
	if ( $response->get_status() === 200 ) {
		$updated_product = wc_get_product( $product_id );
		$new_quantity = $updated_product->get_stock_quantity();
		$visibility = $updated_product->get_catalog_visibility();
		
		if ( $new_quantity === 0 ) {
			echo "✓ Stock quantity correctly updated to 0\n";
		} else {
			echo "✗ Stock quantity not updated correctly (got: {$new_quantity})\n";
		}
		
		if ( $visibility === 'hidden' ) {
			echo "✓ Product correctly hidden when stock = 0 (Requirement 6.5)\n";
		} else {
			echo "✗ Product should be hidden when stock = 0 (got: {$visibility})\n";
		}
	}
	echo "\n";

	// Test 8: Test product not found
	echo "Test 8: Testing product not found response...\n";
	
	$product_not_found_request = new WP_REST_Request( 'POST', $stock_route );
	$product_not_found_request->set_header( 'X-Protheus-Token', $test_token );
	$product_not_found_request->set_body( wp_json_encode( array(
		'sku' => 'NONEXISTENT-SKU',
		'quantity' => 10,
	) ) );
	
	$response = $webhook_handler->handle_stock_update( $product_not_found_request );
	
	if ( $response->get_status() === 404 ) {
		echo "✓ Product not found correctly returns 404\n";
	} else {
		echo "✗ Product not found should return 404, got: " . $response->get_status() . "\n";
	}
	echo "\n";

	// Test 9: Test missing required fields
	echo "Test 9: Testing validation of required fields...\n";
	
	$invalid_order_request = new WP_REST_Request( 'POST', $order_status_route );
	$invalid_order_request->set_header( 'X-Protheus-Token', $test_token );
	$invalid_order_request->set_body( wp_json_encode( array(
		'status' => 'approved',
		// Missing woo_order_id
	) ) );
	
	$response = $webhook_handler->handle_order_status_update( $invalid_order_request );
	
	if ( $response->get_status() === 400 ) {
		echo "✓ Missing order fields correctly returns 400\n";
	} else {
		echo "✗ Missing order fields should return 400, got: " . $response->get_status() . "\n";
	}
	
	$invalid_stock_request = new WP_REST_Request( 'POST', $stock_route );
	$invalid_stock_request->set_header( 'X-Protheus-Token', $test_token );
	$invalid_stock_request->set_body( wp_json_encode( array(
		'quantity' => 10,
		// Missing sku
	) ) );
	
	$response = $webhook_handler->handle_stock_update( $invalid_stock_request );
	
	if ( $response->get_status() === 400 ) {
		echo "✓ Missing stock fields correctly returns 400\n";
	} else {
		echo "✗ Missing stock fields should return 400, got: " . $response->get_status() . "\n";
	}
	echo "\n";

	// Cleanup
	echo "Cleaning up test data...\n";
	wp_delete_post( $order_id, true );
	wp_delete_post( $product_id, true );
	delete_option( 'absloja_protheus_webhook_token' );
	delete_option( 'absloja_protheus_webhook_secret' );
	echo "✓ Cleanup complete\n\n";

	echo "=== WEBHOOK HANDLER VALIDATION COMPLETE ===\n";
}

// Run the test if this file is executed directly
if ( basename( $_SERVER['SCRIPT_FILENAME'] ) === basename( __FILE__ ) ) {
	test_webhook_handler();
}
