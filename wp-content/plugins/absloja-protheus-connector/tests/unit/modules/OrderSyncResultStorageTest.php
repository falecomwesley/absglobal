<?php
/**
 * Tests for Order_Sync result storage functionality
 *
 * Validates that sync metadata is correctly stored in WooCommerce order meta.
 *
 * @package ABSLoja\ProtheusConnector\Tests
 */

namespace ABSLoja\ProtheusConnector\Tests\Unit\Modules;

use ABSLoja\ProtheusConnector\Modules\Order_Sync;
use ABSLoja\ProtheusConnector\Modules\Customer_Sync;
use ABSLoja\ProtheusConnector\Modules\Mapping_Engine;
use ABSLoja\ProtheusConnector\Modules\Logger;
use ABSLoja\ProtheusConnector\Modules\Retry_Manager;
use ABSLoja\ProtheusConnector\API\Protheus_Client;
use PHPUnit\Framework\TestCase;
use Mockery;

/**
 * Test Order_Sync result storage
 *
 * @group order-sync
 * @group result-storage
 */
class OrderSyncResultStorageTest extends TestCase {

	/**
	 * Tear down after each test
	 */
	protected function tearDown(): void {
		Mockery::close();
		parent::tearDown();
	}

	/**
	 * Test that successful sync stores all required metadata
	 *
	 * Validates: Requirements 1.4
	 * - _protheus_order_id should be stored
	 * - _protheus_sync_date should be stored
	 * - _protheus_sync_status should be 'synced'
	 * - _protheus_customer_code should be stored
	 */
	public function test_successful_sync_stores_all_metadata() {
		// Mock dependencies
		$client = Mockery::mock( Protheus_Client::class );
		$customer_sync = Mockery::mock( Customer_Sync::class );
		$mapper = Mockery::mock( Mapping_Engine::class );
		$logger = Mockery::mock( Logger::class );
		$retry_manager = Mockery::mock( Retry_Manager::class );

		// Mock WooCommerce order
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_id' )->andReturn( 123 );
		$order->shouldReceive( 'get_meta' )->with( '_protheus_order_id', true )->andReturn( '' );
		
		// Customer sync returns customer code
		$customer_sync->shouldReceive( 'ensure_customer_exists' )
			->with( $order )
			->andReturn( 'CUST001' );

		// Order should store customer code
		$order->shouldReceive( 'update_meta_data' )
			->with( '_protheus_customer_code', 'CUST001' )
			->once();
		$order->shouldReceive( 'save' )->times( 2 );

		// Mock order data
		$order->shouldReceive( 'get_billing_state' )->andReturn( 'SP' );
		$order->shouldReceive( 'get_payment_method' )->andReturn( 'bacs' );
		$order->shouldReceive( 'get_date_created' )->andReturn( 
			Mockery::mock( 'WC_DateTime' )->shouldReceive( 'format' )->with( 'Ymd' )->andReturn( '20240115' )->getMock()
		);
		$order->shouldReceive( 'get_shipping_total' )->andReturn( 10.00 );
		$order->shouldReceive( 'get_discount_total' )->andReturn( 5.00 );
		$order->shouldReceive( 'get_items' )->andReturn( array() );

		// Mock mapper
		$mapper->shouldReceive( 'get_tes_by_state' )->with( 'SP' )->andReturn( '501' );
		$mapper->shouldReceive( 'get_payment_mapping' )->with( 'bacs' )->andReturn( '001' );

		// Mock successful API response
		$client->shouldReceive( 'post' )
			->with( 'api/v1/orders', Mockery::any() )
			->andReturn( array(
				'success' => true,
				'data' => array(
					'C5_NUM' => 'PED123456',
					'C5_FILIAL' => '01',
				),
			) );

		// Mock logger
		$logger->shouldReceive( 'log_api_request' )->once();
		$logger->shouldReceive( 'log_sync_operation' )->once();

		// Order should store all sync metadata
		$order->shouldReceive( 'update_meta_data' )
			->with( '_protheus_order_id', 'PED123456' )
			->once();
		$order->shouldReceive( 'update_meta_data' )
			->with( '_protheus_sync_date', Mockery::type( 'string' ) )
			->once();
		$order->shouldReceive( 'update_meta_data' )
			->with( '_protheus_sync_status', 'synced' )
			->once();
		$order->shouldReceive( 'delete_meta_data' )
			->with( '_protheus_sync_error' )
			->once();
		$order->shouldReceive( 'add_order_note' )->once();

		// Create Order_Sync instance
		$order_sync = new Order_Sync( $client, $customer_sync, $mapper, $logger, $retry_manager );

		// Execute sync
		$result = $order_sync->sync_order( $order );

		// Assert success
		$this->assertTrue( $result );
	}

	/**
	 * Test that failed sync stores error metadata
	 *
	 * Validates: Requirements 1.5
	 * - _protheus_sync_status should be 'error'
	 * - _protheus_sync_error should contain error message
	 */
	public function test_failed_sync_stores_error_metadata() {
		// Mock dependencies
		$client = Mockery::mock( Protheus_Client::class );
		$customer_sync = Mockery::mock( Customer_Sync::class );
		$mapper = Mockery::mock( Mapping_Engine::class );
		$logger = Mockery::mock( Logger::class );
		$retry_manager = Mockery::mock( Retry_Manager::class );

		// Mock WooCommerce order
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_id' )->andReturn( 123 );
		$order->shouldReceive( 'get_meta' )->with( '_protheus_order_id', true )->andReturn( '' );
		
		// Customer sync returns customer code
		$customer_sync->shouldReceive( 'ensure_customer_exists' )
			->with( $order )
			->andReturn( 'CUST001' );

		// Order should store customer code
		$order->shouldReceive( 'update_meta_data' )
			->with( '_protheus_customer_code', 'CUST001' )
			->once();
		$order->shouldReceive( 'save' )->times( 2 );

		// Mock order data
		$order->shouldReceive( 'get_billing_state' )->andReturn( 'SP' );
		$order->shouldReceive( 'get_payment_method' )->andReturn( 'bacs' );
		$order->shouldReceive( 'get_date_created' )->andReturn( 
			Mockery::mock( 'WC_DateTime' )->shouldReceive( 'format' )->with( 'Ymd' )->andReturn( '20240115' )->getMock()
		);
		$order->shouldReceive( 'get_shipping_total' )->andReturn( 10.00 );
		$order->shouldReceive( 'get_discount_total' )->andReturn( 5.00 );
		$order->shouldReceive( 'get_items' )->andReturn( array() );

		// Mock mapper
		$mapper->shouldReceive( 'get_tes_by_state' )->with( 'SP' )->andReturn( '501' );
		$mapper->shouldReceive( 'get_payment_mapping' )->with( 'bacs' )->andReturn( '001' );

		// Mock failed API response
		$error_message = 'TES code not found for state SP';
		$client->shouldReceive( 'post' )
			->with( 'api/v1/orders', Mockery::any() )
			->andReturn( array(
				'success' => false,
				'error' => $error_message,
				'error_type' => 'business_error',
			) );

		// Mock logger
		$logger->shouldReceive( 'log_api_request' )->once();
		$logger->shouldReceive( 'log_sync_operation' )->once();

		// Order should store error metadata
		$order->shouldReceive( 'update_meta_data' )
			->with( '_protheus_sync_status', 'error' )
			->once();
		$order->shouldReceive( 'update_meta_data' )
			->with( '_protheus_sync_error', $error_message )
			->once();
		$order->shouldReceive( 'add_order_note' )->once();

		// Create Order_Sync instance
		$order_sync = new Order_Sync( $client, $customer_sync, $mapper, $logger, $retry_manager );

		// Execute sync
		$result = $order_sync->sync_order( $order );

		// Assert failure
		$this->assertFalse( $result );
	}

	/**
	 * Test that customer code is stored before order sync
	 *
	 * Validates: Requirements 1.4
	 * - _protheus_customer_code should be stored from ensure_customer_exists
	 */
	public function test_customer_code_stored_before_order_sync() {
		// Mock dependencies
		$client = Mockery::mock( Protheus_Client::class );
		$customer_sync = Mockery::mock( Customer_Sync::class );
		$mapper = Mockery::mock( Mapping_Engine::class );
		$logger = Mockery::mock( Logger::class );
		$retry_manager = Mockery::mock( Retry_Manager::class );

		// Mock WooCommerce order
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_id' )->andReturn( 123 );
		$order->shouldReceive( 'get_meta' )->with( '_protheus_order_id', true )->andReturn( '' );
		
		// Customer sync returns customer code
		$customer_code = 'CUST999';
		$customer_sync->shouldReceive( 'ensure_customer_exists' )
			->with( $order )
			->andReturn( $customer_code );

		// Order should store customer code BEFORE API call
		$order->shouldReceive( 'update_meta_data' )
			->with( '_protheus_customer_code', $customer_code )
			->once()
			->ordered();
		$order->shouldReceive( 'save' )->once()->ordered();

		// Mock order data
		$order->shouldReceive( 'get_billing_state' )->andReturn( 'RJ' );
		$order->shouldReceive( 'get_payment_method' )->andReturn( 'credit_card' );
		$order->shouldReceive( 'get_date_created' )->andReturn( 
			Mockery::mock( 'WC_DateTime' )->shouldReceive( 'format' )->with( 'Ymd' )->andReturn( '20240115' )->getMock()
		);
		$order->shouldReceive( 'get_shipping_total' )->andReturn( 15.00 );
		$order->shouldReceive( 'get_discount_total' )->andReturn( 0.00 );
		$order->shouldReceive( 'get_items' )->andReturn( array() );

		// Mock mapper
		$mapper->shouldReceive( 'get_tes_by_state' )->with( 'RJ' )->andReturn( '502' );
		$mapper->shouldReceive( 'get_payment_mapping' )->with( 'credit_card' )->andReturn( '004' );

		// Mock successful API response (called AFTER customer code storage)
		$client->shouldReceive( 'post' )
			->with( 'api/v1/orders', Mockery::any() )
			->andReturn( array(
				'success' => true,
				'data' => array(
					'C5_NUM' => 'PED789',
				),
			) )
			->ordered();

		// Mock logger
		$logger->shouldReceive( 'log_api_request' )->once();
		$logger->shouldReceive( 'log_sync_operation' )->once();

		// Order should store sync metadata
		$order->shouldReceive( 'update_meta_data' )->with( '_protheus_order_id', 'PED789' )->once();
		$order->shouldReceive( 'update_meta_data' )->with( '_protheus_sync_date', Mockery::type( 'string' ) )->once();
		$order->shouldReceive( 'update_meta_data' )->with( '_protheus_sync_status', 'synced' )->once();
		$order->shouldReceive( 'delete_meta_data' )->with( '_protheus_sync_error' )->once();
		$order->shouldReceive( 'save' )->once();
		$order->shouldReceive( 'add_order_note' )->once();

		// Create Order_Sync instance
		$order_sync = new Order_Sync( $client, $customer_sync, $mapper, $logger, $retry_manager );

		// Execute sync
		$result = $order_sync->sync_order( $order );

		// Assert success
		$this->assertTrue( $result );
	}

	/**
	 * Test that sync date uses current timestamp
	 *
	 * Validates: Requirements 1.4
	 * - _protheus_sync_date should contain a valid MySQL datetime
	 */
	public function test_sync_date_uses_current_timestamp() {
		// Mock dependencies
		$client = Mockery::mock( Protheus_Client::class );
		$customer_sync = Mockery::mock( Customer_Sync::class );
		$mapper = Mockery::mock( Mapping_Engine::class );
		$logger = Mockery::mock( Logger::class );
		$retry_manager = Mockery::mock( Retry_Manager::class );

		// Mock WooCommerce order
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_id' )->andReturn( 123 );
		$order->shouldReceive( 'get_meta' )->with( '_protheus_order_id', true )->andReturn( '' );
		
		// Customer sync returns customer code
		$customer_sync->shouldReceive( 'ensure_customer_exists' )
			->with( $order )
			->andReturn( 'CUST001' );

		// Order should store customer code
		$order->shouldReceive( 'update_meta_data' )
			->with( '_protheus_customer_code', 'CUST001' )
			->once();
		$order->shouldReceive( 'save' )->times( 2 );

		// Mock order data
		$order->shouldReceive( 'get_billing_state' )->andReturn( 'SP' );
		$order->shouldReceive( 'get_payment_method' )->andReturn( 'bacs' );
		$order->shouldReceive( 'get_date_created' )->andReturn( 
			Mockery::mock( 'WC_DateTime' )->shouldReceive( 'format' )->with( 'Ymd' )->andReturn( '20240115' )->getMock()
		);
		$order->shouldReceive( 'get_shipping_total' )->andReturn( 10.00 );
		$order->shouldReceive( 'get_discount_total' )->andReturn( 5.00 );
		$order->shouldReceive( 'get_items' )->andReturn( array() );

		// Mock mapper
		$mapper->shouldReceive( 'get_tes_by_state' )->with( 'SP' )->andReturn( '501' );
		$mapper->shouldReceive( 'get_payment_mapping' )->with( 'bacs' )->andReturn( '001' );

		// Mock successful API response
		$client->shouldReceive( 'post' )
			->with( 'api/v1/orders', Mockery::any() )
			->andReturn( array(
				'success' => true,
				'data' => array(
					'C5_NUM' => 'PED123456',
				),
			) );

		// Mock logger
		$logger->shouldReceive( 'log_api_request' )->once();
		$logger->shouldReceive( 'log_sync_operation' )->once();

		// Capture the sync date value
		$captured_sync_date = null;
		$order->shouldReceive( 'update_meta_data' )
			->with( '_protheus_sync_date', Mockery::on( function( $value ) use ( &$captured_sync_date ) {
				$captured_sync_date = $value;
				return true;
			} ) )
			->once();

		// Order should store other metadata
		$order->shouldReceive( 'update_meta_data' )->with( '_protheus_order_id', 'PED123456' )->once();
		$order->shouldReceive( 'update_meta_data' )->with( '_protheus_sync_status', 'synced' )->once();
		$order->shouldReceive( 'delete_meta_data' )->with( '_protheus_sync_error' )->once();
		$order->shouldReceive( 'add_order_note' )->once();

		// Create Order_Sync instance
		$order_sync = new Order_Sync( $client, $customer_sync, $mapper, $logger, $retry_manager );

		// Execute sync
		$result = $order_sync->sync_order( $order );

		// Assert success
		$this->assertTrue( $result );

		// Validate sync date format (MySQL datetime: YYYY-MM-DD HH:MM:SS)
		$this->assertNotNull( $captured_sync_date );
		$this->assertMatchesRegularExpression( 
			'/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', 
			$captured_sync_date,
			'Sync date should be in MySQL datetime format'
		);
	}

	/**
	 * Test that already synced orders are not re-synced
	 *
	 * Validates: Requirements 1.4
	 * - Orders with _protheus_order_id should not be synced again
	 */
	public function test_already_synced_orders_not_resynced() {
		// Mock dependencies
		$client = Mockery::mock( Protheus_Client::class );
		$customer_sync = Mockery::mock( Customer_Sync::class );
		$mapper = Mockery::mock( Mapping_Engine::class );
		$logger = Mockery::mock( Logger::class );
		$retry_manager = Mockery::mock( Retry_Manager::class );

		// Mock WooCommerce order with existing Protheus order ID
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_id' )->andReturn( 123 );
		$order->shouldReceive( 'get_meta' )
			->with( '_protheus_order_id', true )
			->andReturn( 'PED999999' );

		// Logger should log that order was already synced
		$logger->shouldReceive( 'log_sync_operation' )
			->with(
				'order_sync',
				Mockery::on( function( $data ) {
					return $data['action'] === 'already_synced' 
						&& $data['protheus_order_id'] === 'PED999999';
				} ),
				true,
				'Order already synced to Protheus'
			)
			->once();

		// No other methods should be called
		$customer_sync->shouldNotReceive( 'ensure_customer_exists' );
		$client->shouldNotReceive( 'post' );
		$order->shouldNotReceive( 'update_meta_data' );
		$order->shouldNotReceive( 'save' );

		// Create Order_Sync instance
		$order_sync = new Order_Sync( $client, $customer_sync, $mapper, $logger, $retry_manager );

		// Execute sync
		$result = $order_sync->sync_order( $order );

		// Assert success (returns true because order is already synced)
		$this->assertTrue( $result );
	}
}
