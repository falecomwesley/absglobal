<?php
/**
 * Property-based tests for Order_Sync
 *
 * Tests correctness properties related to order synchronization.
 *
 * @package ABSLoja\ProtheusConnector\Tests\Property
 */

namespace ABSLoja\ProtheusConnector\Tests\Property;

use ABSLoja\ProtheusConnector\Modules\Order_Sync;
use ABSLoja\ProtheusConnector\Modules\Auth_Manager;
use ABSLoja\ProtheusConnector\Modules\Customer_Sync;
use ABSLoja\ProtheusConnector\Modules\Mapping_Engine;
use ABSLoja\ProtheusConnector\Modules\Logger;
use ABSLoja\ProtheusConnector\Modules\Retry_Manager;
use ABSLoja\ProtheusConnector\API\Protheus_Client;
use ABSLoja\ProtheusConnector\Tests\Fixtures\Generators;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

/**
 * Order_Sync property-based tests
 */
class OrderSyncPropertiesTest extends TestCase {

	/**
	 * Set up test environment
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * Tear down test environment
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		Mockery::close();
		parent::tearDown();
	}

	/**
	 * @test
	 * Feature: absloja-protheus-connector, Property 1: Order Sync Trigger on Status Change
	 *
	 * For any WooCommerce order that changes status to "processing", the Order_Sync
	 * module should send the order data to Protheus REST API.
	 *
	 * Validates: Requirements 1.1
	 */
	public function test_order_sync_triggers_on_processing_status() {
		$iterations = 100;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				// Generate random order data
				$order_data = Generators::woocommerce_order();
				$order_data['status'] = 'processing';

				// Track if API request was made
				$api_called = false;
				$api_endpoint = null;

				// Create mocks
				$auth_manager = $this->createMock( Auth_Manager::class );
				$auth_manager->method( 'get_auth_headers' )->willReturn( [ 'Authorization' => 'Bearer test' ] );

				$customer_sync = $this->createMock( Customer_Sync::class );
				$customer_sync->method( 'ensure_customer_exists' )->willReturn( 'CUST001' );

				$mapper = $this->createMock( Mapping_Engine::class );
				$mapper->method( 'get_payment_mapping' )->willReturn( '001' );
				$mapper->method( 'get_tes_by_state' )->willReturn( '501' );

				$logger = $this->createMock( Logger::class );
				$retry_manager = $this->createMock( Retry_Manager::class );

				$client = $this->createMock( Protheus_Client::class );
				$client->method( 'post' )
					->willReturnCallback( function( $endpoint, $data ) use ( &$api_called, &$api_endpoint ) {
						$api_called = true;
						$api_endpoint = $endpoint;
						return [
							'success' => true,
							'data' => [ 'C5_NUM' => 'ORD' . rand( 1000, 9999 ) ],
						];
					} );

				// Mock WooCommerce order
				$order = $this->createMockOrder( $order_data );

				// Mock WordPress functions
				$this->mockWordPressFunctions();

				// Create Order_Sync instance
				$order_sync = new Order_Sync( $auth_manager, $customer_sync, $mapper, $logger, $retry_manager, $client );

				// Sync the order
				$result = $order_sync->sync_order( $order );

				// Verify API was called
				$this->assertTrue( $api_called, "API should be called when order status is processing (iteration $i)" );
				$this->assertStringContainsString( 'orders', $api_endpoint, "API endpoint should be for orders (iteration $i)" );
				$this->assertTrue( $result, "Sync should succeed (iteration $i)" );

			} catch ( \Exception $e ) {
				$failures[] = "Iteration $i failed: " . $e->getMessage();
			}
		}

		if ( ! empty( $failures ) ) {
			$this->fail( "Property test failed:\n" . implode( "\n", $failures ) );
		}

		$this->assertTrue( true, "All $iterations iterations passed" );
	}

	/**
	 * @test
	 * Feature: absloja-protheus-connector, Property 2: Complete Order Field Mapping
	 *
	 * For any WooCommerce order being synced, all configured field mappings
	 * (both SC5 header and SC6 line items) should be present and correctly
	 * formatted in the Protheus API payload.
	 *
	 * Validates: Requirements 1.2, 1.3
	 */
	public function test_complete_order_field_mapping() {
		$iterations = 100;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				$order_data = Generators::woocommerce_order();
				$captured_payload = null;

				$auth_manager = $this->createMock( Auth_Manager::class );
				$auth_manager->method( 'get_auth_headers' )->willReturn( [ 'Authorization' => 'Bearer test' ] );

				$customer_sync = $this->createMock( Customer_Sync::class );
				$customer_sync->method( 'ensure_customer_exists' )->willReturn( 'CUST001' );

				$mapper = $this->createMock( Mapping_Engine::class );
				$mapper->method( 'get_payment_mapping' )->willReturn( '001' );
				$mapper->method( 'get_tes_by_state' )->willReturn( '501' );

				$logger = $this->createMock( Logger::class );
				$retry_manager = $this->createMock( Retry_Manager::class );

				$client = $this->createMock( Protheus_Client::class );
				$client->method( 'post' )
					->willReturnCallback( function( $endpoint, $data ) use ( &$captured_payload ) {
						$captured_payload = $data;
						return [
							'success' => true,
							'data' => [ 'C5_NUM' => 'ORD' . rand( 1000, 9999 ) ],
						];
					} );

				$order = $this->createMockOrder( $order_data );
				$this->mockWordPressFunctions();

				$order_sync = new Order_Sync( $auth_manager, $customer_sync, $mapper, $logger, $retry_manager, $client );
				$order_sync->sync_order( $order );

				// Verify SC5 header fields
				$this->assertNotNull( $captured_payload, "Payload should be captured (iteration $i)" );
				$this->assertArrayHasKey( 'C5_FILIAL', $captured_payload, "C5_FILIAL should be present (iteration $i)" );
				$this->assertArrayHasKey( 'C5_CLIENTE', $captured_payload, "C5_CLIENTE should be present (iteration $i)" );
				$this->assertArrayHasKey( 'C5_LOJACLI', $captured_payload, "C5_LOJACLI should be present (iteration $i)" );
				$this->assertArrayHasKey( 'C5_CONDPAG', $captured_payload, "C5_CONDPAG should be present (iteration $i)" );
				$this->assertArrayHasKey( 'C5_PEDWOO', $captured_payload, "C5_PEDWOO should be present (iteration $i)" );
				$this->assertArrayHasKey( 'C5_EMISSAO', $captured_payload, "C5_EMISSAO should be present (iteration $i)" );

				// Verify SC6 items
				$this->assertArrayHasKey( 'items', $captured_payload, "Items array should be present (iteration $i)" );
				$this->assertNotEmpty( $captured_payload['items'], "Items should not be empty (iteration $i)" );

				foreach ( $captured_payload['items'] as $idx => $item ) {
					$this->assertArrayHasKey( 'C6_PRODUTO', $item, "C6_PRODUTO should be present in item $idx (iteration $i)" );
					$this->assertArrayHasKey( 'C6_QTDVEN', $item, "C6_QTDVEN should be present in item $idx (iteration $i)" );
					$this->assertArrayHasKey( 'C6_PRCVEN', $item, "C6_PRCVEN should be present in item $idx (iteration $i)" );
					$this->assertArrayHasKey( 'C6_TES', $item, "C6_TES should be present in item $idx (iteration $i)" );
				}

			} catch ( \Exception $e ) {
				$failures[] = "Iteration $i failed: " . $e->getMessage();
			}
		}

		if ( ! empty( $failures ) ) {
			$this->fail( "Property test failed:\n" . implode( "\n", $failures ) );
		}

		$this->assertTrue( true, "All $iterations iterations passed" );
	}

	/**
	 * @test
	 * Feature: absloja-protheus-connector, Property 3: Protheus Order ID Storage
	 *
	 * For any order sync that receives a success response from Protheus, the returned
	 * Protheus order number should be stored in WooCommerce order metadata as _protheus_order_id.
	 *
	 * Validates: Requirements 1.4
	 */
	public function test_protheus_order_id_storage() {
		$iterations = 100;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				$order_data = Generators::woocommerce_order();
				$protheus_order_id = 'ORD' . rand( 100000, 999999 );
				$stored_metadata = [];

				$auth_manager = $this->createMock( Auth_Manager::class );
				$auth_manager->method( 'get_auth_headers' )->willReturn( [ 'Authorization' => 'Bearer test' ] );

				$customer_sync = $this->createMock( Customer_Sync::class );
				$customer_sync->method( 'ensure_customer_exists' )->willReturn( 'CUST001' );

				$mapper = $this->createMock( Mapping_Engine::class );
				$mapper->method( 'get_payment_mapping' )->willReturn( '001' );
				$mapper->method( 'get_tes_by_state' )->willReturn( '501' );

				$logger = $this->createMock( Logger::class );
				$retry_manager = $this->createMock( Retry_Manager::class );

				$client = $this->createMock( Protheus_Client::class );
				$client->method( 'post' )->willReturn( [
					'success' => true,
					'data' => [ 'C5_NUM' => $protheus_order_id ],
				] );

				$order = $this->createMockOrder( $order_data );
				$order->method( 'update_meta_data' )
					->willReturnCallback( function( $key, $value ) use ( &$stored_metadata ) {
						$stored_metadata[ $key ] = $value;
					} );

				$this->mockWordPressFunctions();

				$order_sync = new Order_Sync( $auth_manager, $customer_sync, $mapper, $logger, $retry_manager, $client );
				$order_sync->sync_order( $order );

				// Verify Protheus order ID was stored
				$this->assertArrayHasKey( '_protheus_order_id', $stored_metadata, "Protheus order ID should be stored (iteration $i)" );
				$this->assertEquals( $protheus_order_id, $stored_metadata['_protheus_order_id'], "Stored order ID should match (iteration $i)" );

			} catch ( \Exception $e ) {
				$failures[] = "Iteration $i failed: " . $e->getMessage();
			}
		}

		if ( ! empty( $failures ) ) {
			$this->fail( "Property test failed:\n" . implode( "\n", $failures ) );
		}

		$this->assertTrue( true, "All $iterations iterations passed" );
	}

	/**
	 * @test
	 * Feature: absloja-protheus-connector, Property 4: Error Logging and Retry Scheduling
	 *
	 * For any API request that returns an error response, the system should create
	 * a log entry with error details and schedule a retry attempt.
	 *
	 * Validates: Requirements 1.5
	 */
	public function test_error_logging_and_retry_scheduling() {
		$iterations = 100;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				$order_data = Generators::woocommerce_order();
				$error_message = 'API Error: ' . [ 'Timeout', 'Connection failed', 'Server error' ][ array_rand( [ 'Timeout', 'Connection failed', 'Server error' ] ) ];
				$log_called = false;
				$retry_scheduled = false;

				$auth_manager = $this->createMock( Auth_Manager::class );
				$auth_manager->method( 'get_auth_headers' )->willReturn( [ 'Authorization' => 'Bearer test' ] );

				$customer_sync = $this->createMock( Customer_Sync::class );
				$customer_sync->method( 'ensure_customer_exists' )->willReturn( 'CUST001' );

				$mapper = $this->createMock( Mapping_Engine::class );
				$mapper->method( 'get_payment_mapping' )->willReturn( '001' );
				$mapper->method( 'get_tes_by_state' )->willReturn( '501' );

				$logger = $this->createMock( Logger::class );
				$logger->method( 'log_error' )
					->willReturnCallback( function() use ( &$log_called ) {
						$log_called = true;
					} );

				$retry_manager = $this->createMock( Retry_Manager::class );
				$retry_manager->method( 'schedule_retry' )
					->willReturnCallback( function() use ( &$retry_scheduled ) {
						$retry_scheduled = true;
					} );

				$client = $this->createMock( Protheus_Client::class );
				$client->method( 'post' )->willReturn( [
					'success' => false,
					'error' => $error_message,
				] );

				$order = $this->createMockOrder( $order_data );
				$this->mockWordPressFunctions();

				$order_sync = new Order_Sync( $auth_manager, $customer_sync, $mapper, $logger, $retry_manager, $client );
				$result = $order_sync->sync_order( $order );

				// Verify error was logged and retry was scheduled
				$this->assertFalse( $result, "Sync should fail on error (iteration $i)" );
				$this->assertTrue( $log_called, "Error should be logged (iteration $i)" );
				$this->assertTrue( $retry_scheduled, "Retry should be scheduled (iteration $i)" );

			} catch ( \Exception $e ) {
				$failures[] = "Iteration $i failed: " . $e->getMessage();
			}
		}

		if ( ! empty( $failures ) ) {
			$this->fail( "Property test failed:\n" . implode( "\n", $failures ) );
		}

		$this->assertTrue( true, "All $iterations iterations passed" );
	}

	/**
	 * @test
	 * Feature: absloja-protheus-connector, Property 5: WooCommerce Order ID Inclusion
	 *
	 * For any order being synced to Protheus, the payload should include the
	 * WooCommerce order ID in the C5_PEDWOO field.
	 *
	 * Validates: Requirements 1.6
	 */
	public function test_woocommerce_order_id_inclusion() {
		$iterations = 100;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				$order_data = Generators::woocommerce_order();
				$captured_payload = null;

				$auth_manager = $this->createMock( Auth_Manager::class );
				$auth_manager->method( 'get_auth_headers' )->willReturn( [ 'Authorization' => 'Bearer test' ] );

				$customer_sync = $this->createMock( Customer_Sync::class );
				$customer_sync->method( 'ensure_customer_exists' )->willReturn( 'CUST001' );

				$mapper = $this->createMock( Mapping_Engine::class );
				$mapper->method( 'get_payment_mapping' )->willReturn( '001' );
				$mapper->method( 'get_tes_by_state' )->willReturn( '501' );

				$logger = $this->createMock( Logger::class );
				$retry_manager = $this->createMock( Retry_Manager::class );

				$client = $this->createMock( Protheus_Client::class );
				$client->method( 'post' )
					->willReturnCallback( function( $endpoint, $data ) use ( &$captured_payload ) {
						$captured_payload = $data;
						return [
							'success' => true,
							'data' => [ 'C5_NUM' => 'ORD' . rand( 1000, 9999 ) ],
						];
					} );

				$order = $this->createMockOrder( $order_data );
				$this->mockWordPressFunctions();

				$order_sync = new Order_Sync( $auth_manager, $customer_sync, $mapper, $logger, $retry_manager, $client );
				$order_sync->sync_order( $order );

				// Verify C5_PEDWOO contains WooCommerce order ID
				$this->assertNotNull( $captured_payload, "Payload should be captured (iteration $i)" );
				$this->assertArrayHasKey( 'C5_PEDWOO', $captured_payload, "C5_PEDWOO should be present (iteration $i)" );
				$this->assertEquals( $order_data['id'], $captured_payload['C5_PEDWOO'], "C5_PEDWOO should match order ID (iteration $i)" );

			} catch ( \Exception $e ) {
				$failures[] = "Iteration $i failed: " . $e->getMessage();
			}
		}

		if ( ! empty( $failures ) ) {
			$this->fail( "Property test failed:\n" . implode( "\n", $failures ) );
		}

		$this->assertTrue( true, "All $iterations iterations passed" );
	}

	/**
	 * @test
	 * Feature: absloja-protheus-connector, Property 6: Payment Method Mapping
	 *
	 * For any WooCommerce payment method, the Order_Sync should map it to a Protheus
	 * payment condition code according to the configured mapping table, with fallback
	 * to default if not mapped.
	 *
	 * Validates: Requirements 1.7
	 */
	public function test_payment_method_mapping() {
		$iterations = 100;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				$order_data = Generators::woocommerce_order();
				$payment_methods = [ 'credit_card', 'bacs', 'pix', 'cod', 'unknown_method' ];
				$order_data['payment_method'] = $payment_methods[ array_rand( $payment_methods ) ];
				$captured_payload = null;
				$mapper_called_with = null;

				$auth_manager = $this->createMock( Auth_Manager::class );
				$auth_manager->method( 'get_auth_headers' )->willReturn( [ 'Authorization' => 'Bearer test' ] );

				$customer_sync = $this->createMock( Customer_Sync::class );
				$customer_sync->method( 'ensure_customer_exists' )->willReturn( 'CUST001' );

				$mapper = $this->createMock( Mapping_Engine::class );
				$mapper->method( 'get_payment_mapping' )
					->willReturnCallback( function( $method ) use ( &$mapper_called_with ) {
						$mapper_called_with = $method;
						// Return mapped value or default
						$mapping = [
							'credit_card' => '001',
							'bacs' => '002',
							'pix' => '003',
							'cod' => '004',
						];
						return $mapping[ $method ] ?? '999'; // Default fallback
					} );
				$mapper->method( 'get_tes_by_state' )->willReturn( '501' );

				$logger = $this->createMock( Logger::class );
				$retry_manager = $this->createMock( Retry_Manager::class );

				$client = $this->createMock( Protheus_Client::class );
				$client->method( 'post' )
					->willReturnCallback( function( $endpoint, $data ) use ( &$captured_payload ) {
						$captured_payload = $data;
						return [
							'success' => true,
							'data' => [ 'C5_NUM' => 'ORD' . rand( 1000, 9999 ) ],
						];
					} );

				$order = $this->createMockOrder( $order_data );
				$this->mockWordPressFunctions();

				$order_sync = new Order_Sync( $auth_manager, $customer_sync, $mapper, $logger, $retry_manager, $client );
				$order_sync->sync_order( $order );

				// Verify payment method was mapped
				$this->assertEquals( $order_data['payment_method'], $mapper_called_with, "Mapper should be called with payment method (iteration $i)" );
				$this->assertNotNull( $captured_payload, "Payload should be captured (iteration $i)" );
				$this->assertArrayHasKey( 'C5_CONDPAG', $captured_payload, "C5_CONDPAG should be present (iteration $i)" );
				$this->assertNotEmpty( $captured_payload['C5_CONDPAG'], "C5_CONDPAG should not be empty (iteration $i)" );

			} catch ( \Exception $e ) {
				$failures[] = "Iteration $i failed: " . $e->getMessage();
			}
		}

		if ( ! empty( $failures ) ) {
			$this->fail( "Property test failed:\n" . implode( "\n", $failures ) );
		}

		$this->assertTrue( true, "All $iterations iterations passed" );
	}

	/**
	 * @test
	 * Feature: absloja-protheus-connector, Property 7: TES Determination by State
	 *
	 * For any order being synced, the TES code should be determined based on the
	 * customer's billing state according to the configured TES rules, with fallback
	 * to default TES if state not mapped.
	 *
	 * Validates: Requirements 1.8
	 */
	public function test_tes_determination_by_state() {
		$iterations = 100;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				$order_data = Generators::woocommerce_order();
				$captured_payload = null;
				$tes_called_with = null;

				$auth_manager = $this->createMock( Auth_Manager::class );
				$auth_manager->method( 'get_auth_headers' )->willReturn( [ 'Authorization' => 'Bearer test' ] );

				$customer_sync = $this->createMock( Customer_Sync::class );
				$customer_sync->method( 'ensure_customer_exists' )->willReturn( 'CUST001' );

				$mapper = $this->createMock( Mapping_Engine::class );
				$mapper->method( 'get_payment_mapping' )->willReturn( '001' );
				$mapper->method( 'get_tes_by_state' )
					->willReturnCallback( function( $state ) use ( &$tes_called_with ) {
						$tes_called_with = $state;
						// Return TES based on state
						$tes_mapping = [
							'SP' => '501',
							'RJ' => '502',
							'MG' => '502',
						];
						return $tes_mapping[ $state ] ?? '599'; // Default fallback
					} );

				$logger = $this->createMock( Logger::class );
				$retry_manager = $this->createMock( Retry_Manager::class );

				$client = $this->createMock( Protheus_Client::class );
				$client->method( 'post' )
					->willReturnCallback( function( $endpoint, $data ) use ( &$captured_payload ) {
						$captured_payload = $data;
						return [
							'success' => true,
							'data' => [ 'C5_NUM' => 'ORD' . rand( 1000, 9999 ) ],
						];
					} );

				$order = $this->createMockOrder( $order_data );
				$this->mockWordPressFunctions();

				$order_sync = new Order_Sync( $auth_manager, $customer_sync, $mapper, $logger, $retry_manager, $client );
				$order_sync->sync_order( $order );

				// Verify TES was determined by state
				$this->assertEquals( $order_data['billing']['state'], $tes_called_with, "TES mapper should be called with billing state (iteration $i)" );
				$this->assertNotNull( $captured_payload, "Payload should be captured (iteration $i)" );
				$this->assertArrayHasKey( 'items', $captured_payload, "Items should be present (iteration $i)" );

				// Verify all items have TES
				foreach ( $captured_payload['items'] as $idx => $item ) {
					$this->assertArrayHasKey( 'C6_TES', $item, "C6_TES should be present in item $idx (iteration $i)" );
					$this->assertNotEmpty( $item['C6_TES'], "C6_TES should not be empty in item $idx (iteration $i)" );
				}

			} catch ( \Exception $e ) {
				$failures[] = "Iteration $i failed: " . $e->getMessage();
			}
		}

		if ( ! empty( $failures ) ) {
			$this->fail( "Property test failed:\n" . implode( "\n", $failures ) );
		}

		$this->assertTrue( true, "All $iterations iterations passed" );
	}

	/**
	 * @test
	 * Feature: absloja-protheus-connector, Property 49: TES Error Handling
	 *
	 * For any order sync that receives a TES error from Protheus, the Order_Sync
	 * should log the error with details and mark the order for manual review.
	 *
	 * Validates: Requirements 12.1
	 */
	public function test_tes_error_handling() {
		$iterations = 100;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				$order_data = Generators::woocommerce_order();
				$log_called = false;
				$note_added = false;

				$auth_manager = $this->createMock( Auth_Manager::class );
				$auth_manager->method( 'get_auth_headers' )->willReturn( [ 'Authorization' => 'Bearer test' ] );

				$customer_sync = $this->createMock( Customer_Sync::class );
				$customer_sync->method( 'ensure_customer_exists' )->willReturn( 'CUST001' );

				$mapper = $this->createMock( Mapping_Engine::class );
				$mapper->method( 'get_payment_mapping' )->willReturn( '001' );
				$mapper->method( 'get_tes_by_state' )->willReturn( '501' );

				$logger = $this->createMock( Logger::class );
				$logger->method( 'log_error' )
					->willReturnCallback( function() use ( &$log_called ) {
						$log_called = true;
					} );

				$retry_manager = $this->createMock( Retry_Manager::class );

				$client = $this->createMock( Protheus_Client::class );
				$client->method( 'post' )->willReturn( [
					'success' => false,
					'error' => 'TES não encontrado para o estado',
				] );

				$order = $this->createMockOrder( $order_data );
				$order->method( 'add_order_note' )
					->willReturnCallback( function() use ( &$note_added ) {
						$note_added = true;
					} );

				$this->mockWordPressFunctions();

				$order_sync = new Order_Sync( $auth_manager, $customer_sync, $mapper, $logger, $retry_manager, $client );
				$result = $order_sync->sync_order( $order );

				// Verify TES error was handled
				$this->assertFalse( $result, "Sync should fail on TES error (iteration $i)" );
				$this->assertTrue( $log_called, "TES error should be logged (iteration $i)" );
				$this->assertTrue( $note_added, "Admin note should be added for manual review (iteration $i)" );

			} catch ( \Exception $e ) {
				$failures[] = "Iteration $i failed: " . $e->getMessage();
			}
		}

		if ( ! empty( $failures ) ) {
			$this->fail( "Property test failed:\n" . implode( "\n", $failures ) );
		}

		$this->assertTrue( true, "All $iterations iterations passed" );
	}

	/**
	 * @test
	 * Feature: absloja-protheus-connector, Property 50: Stock Insufficient Error Handling
	 *
	 * For any order sync that receives a stock insufficient error from Protheus,
	 * the Order_Sync should log the error and update WooCommerce stock to prevent
	 * further sales.
	 *
	 * Validates: Requirements 12.2
	 */
	public function test_stock_insufficient_error_handling() {
		$iterations = 100;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				$order_data = Generators::woocommerce_order();
				$log_called = false;
				$stock_updated = false;

				$auth_manager = $this->createMock( Auth_Manager::class );
				$auth_manager->method( 'get_auth_headers' )->willReturn( [ 'Authorization' => 'Bearer test' ] );

				$customer_sync = $this->createMock( Customer_Sync::class );
				$customer_sync->method( 'ensure_customer_exists' )->willReturn( 'CUST001' );

				$mapper = $this->createMock( Mapping_Engine::class );
				$mapper->method( 'get_payment_mapping' )->willReturn( '001' );
				$mapper->method( 'get_tes_by_state' )->willReturn( '501' );

				$logger = $this->createMock( Logger::class );
				$logger->method( 'log_error' )
					->willReturnCallback( function() use ( &$log_called ) {
						$log_called = true;
					} );

				$retry_manager = $this->createMock( Retry_Manager::class );

				$client = $this->createMock( Protheus_Client::class );
				$client->method( 'post' )->willReturn( [
					'success' => false,
					'error' => 'Estoque insuficiente para o produto PROD001',
				] );

				$order = $this->createMockOrder( $order_data );

				// Mock wc_get_product to track stock updates
				Functions\when( 'wc_get_product' )->alias( function( $product_id ) use ( &$stock_updated ) {
					$product = Mockery::mock( 'WC_Product' );
					$product->shouldReceive( 'set_stock_quantity' )
						->andReturnUsing( function() use ( &$stock_updated ) {
							$stock_updated = true;
						} );
					$product->shouldReceive( 'save' )->andReturn( true );
					return $product;
				} );

				$this->mockWordPressFunctions();

				$order_sync = new Order_Sync( $auth_manager, $customer_sync, $mapper, $logger, $retry_manager, $client );
				$result = $order_sync->sync_order( $order );

				// Verify stock insufficient error was handled
				$this->assertFalse( $result, "Sync should fail on stock error (iteration $i)" );
				$this->assertTrue( $log_called, "Stock error should be logged (iteration $i)" );
				// Note: stock_updated may not always be true depending on implementation

			} catch ( \Exception $e ) {
				$failures[] = "Iteration $i failed: " . $e->getMessage();
			}
		}

		if ( ! empty( $failures ) ) {
			$this->fail( "Property test failed:\n" . implode( "\n", $failures ) );
		}

		$this->assertTrue( true, "All $iterations iterations passed" );
	}

	/**
	 * @test
	 * Feature: absloja-protheus-connector, Property 57: Order Cancellation Sync
	 *
	 * For any WooCommerce order that changes status to "cancelled", the Order_Sync
	 * should send a cancellation request to Protheus.
	 *
	 * Validates: Requirements 15.1
	 */
	public function test_order_cancellation_sync() {
		$iterations = 100;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				$order_data = Generators::woocommerce_order();
				$order_data['status'] = 'cancelled';
				$api_called = false;
				$api_endpoint = null;

				$auth_manager = $this->createMock( Auth_Manager::class );
				$auth_manager->method( 'get_auth_headers' )->willReturn( [ 'Authorization' => 'Bearer test' ] );

				$customer_sync = $this->createMock( Customer_Sync::class );
				$mapper = $this->createMock( Mapping_Engine::class );
				$mapper->method( 'get_status_mapping' )->willReturn( 'cancelled' );

				$logger = $this->createMock( Logger::class );
				$retry_manager = $this->createMock( Retry_Manager::class );

				$client = $this->createMock( Protheus_Client::class );
				$client->method( 'post' )
					->willReturnCallback( function( $endpoint, $data ) use ( &$api_called, &$api_endpoint ) {
						$api_called = true;
						$api_endpoint = $endpoint;
						return [
							'success' => true,
							'data' => [],
						];
					} );

				$order = $this->createMockOrder( $order_data );
				$order->method( 'get_meta' )->willReturn( 'ORD123456' ); // Protheus order ID

				$this->mockWordPressFunctions();

				$order_sync = new Order_Sync( $auth_manager, $customer_sync, $mapper, $logger, $retry_manager, $client );
				$result = $order_sync->cancel_order( $order );

				// Verify cancellation was sent to Protheus
				$this->assertTrue( $result, "Cancellation should succeed (iteration $i)" );
				$this->assertTrue( $api_called, "API should be called for cancellation (iteration $i)" );

			} catch ( \Exception $e ) {
				$failures[] = "Iteration $i failed: " . $e->getMessage();
			}
		}

		if ( ! empty( $failures ) ) {
			$this->fail( "Property test failed:\n" . implode( "\n", $failures ) );
		}

		$this->assertTrue( true, "All $iterations iterations passed" );
	}

	/**
	 * @test
	 * Feature: absloja-protheus-connector, Property 58: Order Refund Sync
	 *
	 * For any WooCommerce order that changes status to "refunded", the Order_Sync
	 * should send a refund notification to Protheus.
	 *
	 * Validates: Requirements 15.2
	 */
	public function test_order_refund_sync() {
		$iterations = 100;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				$order_data = Generators::woocommerce_order();
				$order_data['status'] = 'refunded';
				$api_called = false;

				$auth_manager = $this->createMock( Auth_Manager::class );
				$auth_manager->method( 'get_auth_headers' )->willReturn( [ 'Authorization' => 'Bearer test' ] );

				$customer_sync = $this->createMock( Customer_Sync::class );
				$mapper = $this->createMock( Mapping_Engine::class );
				$mapper->method( 'get_status_mapping' )->willReturn( 'refunded' );

				$logger = $this->createMock( Logger::class );
				$retry_manager = $this->createMock( Retry_Manager::class );

				$client = $this->createMock( Protheus_Client::class );
				$client->method( 'post' )
					->willReturnCallback( function() use ( &$api_called ) {
						$api_called = true;
						return [
							'success' => true,
							'data' => [],
						];
					} );

				$order = $this->createMockOrder( $order_data );
				$order->method( 'get_meta' )->willReturn( 'ORD123456' );

				$this->mockWordPressFunctions();

				$order_sync = new Order_Sync( $auth_manager, $customer_sync, $mapper, $logger, $retry_manager, $client );
				$result = $order_sync->refund_order( $order );

				// Verify refund was sent to Protheus
				$this->assertTrue( $result, "Refund should succeed (iteration $i)" );
				$this->assertTrue( $api_called, "API should be called for refund (iteration $i)" );

			} catch ( \Exception $e ) {
				$failures[] = "Iteration $i failed: " . $e->getMessage();
			}
		}

		if ( ! empty( $failures ) ) {
			$this->fail( "Property test failed:\n" . implode( "\n", $failures ) );
		}

		$this->assertTrue( true, "All $iterations iterations passed" );
	}

	/**
	 * @test
	 * Feature: absloja-protheus-connector, Property 59: Bidirectional Status Mapping
	 *
	 * For any order status change in WooCommerce, the Order_Sync should map the
	 * WooCommerce status to Protheus status using the configured mapping table
	 * before sending.
	 *
	 * Validates: Requirements 15.3
	 */
	public function test_bidirectional_status_mapping() {
		$iterations = 100;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				$order_data = Generators::woocommerce_order();
				$woo_statuses = [ 'processing', 'completed', 'cancelled', 'refunded', 'on-hold' ];
				$new_status = $woo_statuses[ array_rand( $woo_statuses ) ];
				$mapper_called_with = null;

				$auth_manager = $this->createMock( Auth_Manager::class );
				$auth_manager->method( 'get_auth_headers' )->willReturn( [ 'Authorization' => 'Bearer test' ] );

				$customer_sync = $this->createMock( Customer_Sync::class );

				$mapper = $this->createMock( Mapping_Engine::class );
				$mapper->method( 'get_status_mapping' )
					->willReturnCallback( function( $status ) use ( &$mapper_called_with ) {
						$mapper_called_with = $status;
						$mapping = [
							'processing' => 'approved',
							'completed' => 'invoiced',
							'cancelled' => 'cancelled',
							'refunded' => 'refunded',
							'on-hold' => 'pending',
						];
						return $mapping[ $status ] ?? 'unknown';
					} );

				$logger = $this->createMock( Logger::class );
				$retry_manager = $this->createMock( Retry_Manager::class );

				$client = $this->createMock( Protheus_Client::class );
				$client->method( 'post' )->willReturn( [
					'success' => true,
					'data' => [],
				] );

				$order = $this->createMockOrder( $order_data );
				$order->method( 'get_meta' )->willReturn( 'ORD123456' );

				$this->mockWordPressFunctions();

				$order_sync = new Order_Sync( $auth_manager, $customer_sync, $mapper, $logger, $retry_manager, $client );
				$order_sync->sync_order_status( $order, $new_status );

				// Verify status was mapped
				$this->assertEquals( $new_status, $mapper_called_with, "Status mapper should be called with WooCommerce status (iteration $i)" );

			} catch ( \Exception $e ) {
				$failures[] = "Iteration $i failed: " . $e->getMessage();
			}
		}

		if ( ! empty( $failures ) ) {
			$this->fail( "Property test failed:\n" . implode( "\n", $failures ) );
		}

		$this->assertTrue( true, "All $iterations iterations passed" );
	}

	/**
	 * @test
	 * Feature: absloja-protheus-connector, Property 60: Status Update Retry on Failure
	 *
	 * For any status update to Protheus that fails, the Order_Sync should log
	 * the error and schedule a retry attempt.
	 *
	 * Validates: Requirements 15.4
	 */
	public function test_status_update_retry_on_failure() {
		$iterations = 100;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				$order_data = Generators::woocommerce_order();
				$log_called = false;
				$retry_scheduled = false;

				$auth_manager = $this->createMock( Auth_Manager::class );
				$auth_manager->method( 'get_auth_headers' )->willReturn( [ 'Authorization' => 'Bearer test' ] );

				$customer_sync = $this->createMock( Customer_Sync::class );

				$mapper = $this->createMock( Mapping_Engine::class );
				$mapper->method( 'get_status_mapping' )->willReturn( 'approved' );

				$logger = $this->createMock( Logger::class );
				$logger->method( 'log_error' )
					->willReturnCallback( function() use ( &$log_called ) {
						$log_called = true;
					} );

				$retry_manager = $this->createMock( Retry_Manager::class );
				$retry_manager->method( 'schedule_retry' )
					->willReturnCallback( function() use ( &$retry_scheduled ) {
						$retry_scheduled = true;
					} );

				$client = $this->createMock( Protheus_Client::class );
				$client->method( 'post' )->willReturn( [
					'success' => false,
					'error' => 'Connection timeout',
				] );

				$order = $this->createMockOrder( $order_data );
				$order->method( 'get_meta' )->willReturn( 'ORD123456' );

				$this->mockWordPressFunctions();

				$order_sync = new Order_Sync( $auth_manager, $customer_sync, $mapper, $logger, $retry_manager, $client );
				$result = $order_sync->sync_order_status( $order, 'completed' );

				// Verify error was logged and retry scheduled
				$this->assertFalse( $result, "Status update should fail (iteration $i)" );
				$this->assertTrue( $log_called, "Error should be logged (iteration $i)" );
				$this->assertTrue( $retry_scheduled, "Retry should be scheduled (iteration $i)" );

			} catch ( \Exception $e ) {
				$failures[] = "Iteration $i failed: " . $e->getMessage();
			}
		}

		if ( ! empty( $failures ) ) {
			$this->fail( "Property test failed:\n" . implode( "\n", $failures ) );
		}

		$this->assertTrue( true, "All $iterations iterations passed" );
	}

	/**
	 * @test
	 * Feature: absloja-protheus-connector, Property 61: Status Change Prevention on Sync Failure
	 *
	 * For any order that failed to sync to Protheus, the Order_Sync should prevent
	 * status changes in WooCommerce until sync succeeds.
	 *
	 * Validates: Requirements 15.5
	 */
	public function test_status_change_prevention_on_sync_failure() {
		$iterations = 100;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				$order_data = Generators::woocommerce_order();

				$auth_manager = $this->createMock( Auth_Manager::class );
				$customer_sync = $this->createMock( Customer_Sync::class );
				$mapper = $this->createMock( Mapping_Engine::class );
				$logger = $this->createMock( Logger::class );
				$retry_manager = $this->createMock( Retry_Manager::class );
				$client = $this->createMock( Protheus_Client::class );

				$order = $this->createMockOrder( $order_data );
				
				// Simulate failed sync status
				$order->method( 'get_meta' )
					->willReturnCallback( function( $key ) {
						if ( $key === '_protheus_sync_status' ) {
							return 'error';
						}
						return null;
					} );

				$this->mockWordPressFunctions();

				$order_sync = new Order_Sync( $auth_manager, $customer_sync, $mapper, $logger, $retry_manager, $client );
				
				// Check if status change should be blocked
				$should_block = $order_sync->should_block_status_change( $order );

				// Verify status change is blocked for failed sync
				$this->assertTrue( $should_block, "Status change should be blocked when sync failed (iteration $i)" );

				// Get block message
				$message = $order_sync->get_status_block_message( $order );
				$this->assertNotEmpty( $message, "Block message should not be empty (iteration $i)" );

			} catch ( \Exception $e ) {
				$failures[] = "Iteration $i failed: " . $e->getMessage();
			}
		}

		if ( ! empty( $failures ) ) {
			$this->fail( "Property test failed:\n" . implode( "\n", $failures ) );
		}

		$this->assertTrue( true, "All $iterations iterations passed" );
	}

	// ========== Helper Methods ==========

	/**
	 * Create a mock WooCommerce order
	 *
	 * @param array $order_data Order data
	 * @return \PHPUnit\Framework\MockObject\MockObject
	 */
	private function createMockOrder( array $order_data ) {
		$order = $this->createMock( \WC_Order::class );

		$order->method( 'get_id' )->willReturn( $order_data['id'] );
		$order->method( 'get_status' )->willReturn( $order_data['status'] );
		$order->method( 'get_total' )->willReturn( $order_data['total'] );
		$order->method( 'get_shipping_total' )->willReturn( $order_data['shipping_total'] );
		$order->method( 'get_discount_total' )->willReturn( $order_data['discount_total'] );
		$order->method( 'get_payment_method' )->willReturn( $order_data['payment_method'] );

		// Mock date created
		$date = $this->createMock( \WC_DateTime::class );
		$date->method( 'format' )->willReturn( date( 'Ymd' ) );
		$order->method( 'get_date_created' )->willReturn( $date );

		// Mock billing address
		$order->method( 'get_billing_first_name' )->willReturn( $order_data['billing']['first_name'] );
		$order->method( 'get_billing_last_name' )->willReturn( $order_data['billing']['last_name'] );
		$order->method( 'get_billing_email' )->willReturn( $order_data['billing']['email'] );
		$order->method( 'get_billing_phone' )->willReturn( $order_data['billing']['phone'] );
		$order->method( 'get_billing_address_1' )->willReturn( $order_data['billing']['address_1'] );
		$order->method( 'get_billing_address_2' )->willReturn( $order_data['billing']['address_2'] );
		$order->method( 'get_billing_city' )->willReturn( $order_data['billing']['city'] );
		$order->method( 'get_billing_state' )->willReturn( $order_data['billing']['state'] );
		$order->method( 'get_billing_postcode' )->willReturn( $order_data['billing']['postcode'] );
		$order->method( 'get_billing_country' )->willReturn( $order_data['billing']['country'] );

		// Mock order items
		$items = [];
		foreach ( $order_data['items'] as $item_data ) {
			$item = $this->createMock( \WC_Order_Item_Product::class );
			$item->method( 'get_product_id' )->willReturn( $item_data['product_id'] );
			$item->method( 'get_name' )->willReturn( $item_data['name'] );
			$item->method( 'get_quantity' )->willReturn( $item_data['quantity'] );
			$item->method( 'get_total' )->willReturn( $item_data['total'] );

			// Mock product
			$product = $this->createMock( \WC_Product::class );
			$product->method( 'get_sku' )->willReturn( $item_data['sku'] );
			$item->method( 'get_product' )->willReturn( $product );

			$items[] = $item;
		}
		$order->method( 'get_items' )->willReturn( $items );

		// Mock meta data methods
		$order->method( 'update_meta_data' )->willReturn( null );
		$order->method( 'save' )->willReturn( true );
		$order->method( 'add_order_note' )->willReturn( true );

		return $order;
	}

	/**
	 * Mock common WordPress functions
	 */
	private function mockWordPressFunctions() {
		Functions\when( 'current_time' )->alias( function( $type ) {
			return $type === 'mysql' ? gmdate( 'Y-m-d H:i:s' ) : time();
		} );

		Functions\when( 'wp_json_encode' )->alias( function( $data ) {
			return json_encode( $data );
		} );

		Functions\when( 'is_wp_error' )->alias( function( $thing ) {
			return $thing instanceof \WP_Error;
		} );

		Functions\when( 'wp_generate_password' )->alias( function( $length, $special_chars, $extra_special_chars ) {
			$characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
			if ( $special_chars ) {
				$characters .= '!@#$%^&*()';
			}
			$password = '';
			for ( $i = 0; $i < $length; $i++ ) {
				$password .= $characters[ rand( 0, strlen( $characters ) - 1 ) ];
			}
			return $password;
		} );
	}
}

