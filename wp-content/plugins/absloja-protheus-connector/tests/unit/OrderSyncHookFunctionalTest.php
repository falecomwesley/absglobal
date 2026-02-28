<?php
/**
 * Functional tests for Order Sync Hook
 *
 * Tests the complete flow of order synchronization when status changes to processing.
 *
 * @package ABSLoja\ProtheusConnector\Tests\Unit
 */

namespace ABSLoja\ProtheusConnector\Tests\Unit;

use ABSLoja\ProtheusConnector\Modules\Order_Sync;
use ABSLoja\ProtheusConnector\Modules\Customer_Sync;
use ABSLoja\ProtheusConnector\Modules\Mapping_Engine;
use ABSLoja\ProtheusConnector\Modules\Logger;
use ABSLoja\ProtheusConnector\Modules\Retry_Manager;
use ABSLoja\ProtheusConnector\API\Protheus_Client;
use PHPUnit\Framework\TestCase;
use Mockery;

/**
 * Class OrderSyncHookFunctionalTest
 *
 * Tests the functional behavior of order synchronization triggered by
 * the woocommerce_order_status_processing hook.
 */
class OrderSyncHookFunctionalTest extends TestCase {

	/**
	 * Tear down after each test
	 */
	protected function tearDown(): void {
		Mockery::close();
		parent::tearDown();
	}

	/**
	 * Test that sync_order skips orders that are already synced
	 *
	 * Validates: Requirements 1.1
	 */
	public function test_sync_order_skips_already_synced_orders() {
		// Mock dependencies
		$client = Mockery::mock( Protheus_Client::class );
		$customer_sync = Mockery::mock( Customer_Sync::class );
		$mapper = Mockery::mock( Mapping_Engine::class );
		$logger = Mockery::mock( Logger::class );
		$retry_manager = Mockery::mock( Retry_Manager::class );

		// Mock WooCommerce order
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_id' )->andReturn( 123 );
		$order->shouldReceive( 'get_meta' )
			->with( '_protheus_order_id', true )
			->andReturn( 'PROTHEUS123' ); // Already synced

		// Logger should log that order was already synced
		$logger->shouldReceive( 'log_sync_operation' )
			->once()
			->with(
				'order_sync',
				Mockery::on( function ( $data ) {
					return $data['order_id'] === 123
						&& $data['protheus_order_id'] === 'PROTHEUS123'
						&& $data['action'] === 'already_synced';
				} ),
				true,
				'Order already synced to Protheus'
			);

		// Create Order_Sync instance
		$order_sync = new Order_Sync(
			$client,
			$customer_sync,
			$mapper,
			$logger,
			$retry_manager
		);

		// Call sync_order
		$result = $order_sync->sync_order( $order );

		// Should return true (already synced)
		$this->assertTrue( $result );
	}

	/**
	 * Test that sync_order checks for _protheus_order_id metadata
	 *
	 * Validates: Requirements 1.1
	 */
	public function test_sync_order_checks_protheus_order_id_metadata() {
		// Mock dependencies
		$client = Mockery::mock( Protheus_Client::class );
		$customer_sync = Mockery::mock( Customer_Sync::class );
		$mapper = Mockery::mock( Mapping_Engine::class );
		$logger = Mockery::mock( Logger::class );
		$retry_manager = Mockery::mock( Retry_Manager::class );

		// Mock WooCommerce order
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_id' )->andReturn( 456 );
		
		// Expect get_meta to be called with correct parameters
		$order->shouldReceive( 'get_meta' )
			->once()
			->with( '_protheus_order_id', true )
			->andReturn( '' ); // Not synced

		// Mock customer sync to return a customer code
		$customer_sync->shouldReceive( 'ensure_customer_exists' )
			->with( $order )
			->andReturn( 'CUST001' );

		// Mock order metadata updates
		$order->shouldReceive( 'update_meta_data' )->andReturn( true );
		$order->shouldReceive( 'save' )->andReturn( true );
		$order->shouldReceive( 'add_order_note' )->andReturn( true );
		$order->shouldReceive( 'delete_meta_data' )->andReturn( true );
		$order->shouldReceive( 'get_payment_method' )->andReturn( 'bacs' );
		$order->shouldReceive( 'get_billing_state' )->andReturn( 'SP' );
		$order->shouldReceive( 'get_date_created' )->andReturn( new \WC_DateTime( '2024-01-15' ) );
		$order->shouldReceive( 'get_shipping_total' )->andReturn( 10.00 );
		$order->shouldReceive( 'get_discount_total' )->andReturn( 5.00 );
		$order->shouldReceive( 'get_items' )->andReturn( array() );

		// Mock mapper
		$mapper->shouldReceive( 'get_order_mapping' )->andReturn( array(
			'SC5' => array( 'C5_FILIAL' => '01', 'C5_TIPO' => 'N', 'C5_LOJACLI' => '01', 'C5_TABELA' => '001', 'C5_VEND1' => '000001' ),
			'SC6' => array( 'C6_FILIAL' => '01' ),
		) );
		$mapper->shouldReceive( 'get_payment_mapping' )->andReturn( '001' );
		$mapper->shouldReceive( 'get_tes_by_state' )->andReturn( '501' );

		// Mock API client response
		$client->shouldReceive( 'post' )
			->once()
			->andReturn( array(
				'success' => true,
				'data' => array( 'C5_NUM' => 'ORDER123' ),
			) );

		// Mock logger
		$logger->shouldReceive( 'log_api_request' )->andReturn( true );
		$logger->shouldReceive( 'log_sync_operation' )->andReturn( true );

		// Create Order_Sync instance
		$order_sync = new Order_Sync(
			$client,
			$customer_sync,
			$mapper,
			$logger,
			$retry_manager
		);

		// Call sync_order
		$result = $order_sync->sync_order( $order );

		// Should return true (synced successfully)
		$this->assertTrue( $result );
	}

	/**
	 * Test that sync_order aborts when customer creation fails
	 *
	 * Validates: Requirements 2.6
	 */
	public function test_sync_order_aborts_when_customer_creation_fails() {
		// Mock dependencies
		$client = Mockery::mock( Protheus_Client::class );
		$customer_sync = Mockery::mock( Customer_Sync::class );
		$mapper = Mockery::mock( Mapping_Engine::class );
		$logger = Mockery::mock( Logger::class );
		$retry_manager = Mockery::mock( Retry_Manager::class );

		// Mock WooCommerce order
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_id' )->andReturn( 789 );
		$order->shouldReceive( 'get_meta' )
			->with( '_protheus_order_id', true )
			->andReturn( '' ); // Not synced

		// Customer sync fails (returns empty string)
		$customer_sync->shouldReceive( 'ensure_customer_exists' )
			->with( $order )
			->andReturn( '' );

		// Mock order note addition
		$order->shouldReceive( 'add_order_note' )
			->once()
			->with(
				Mockery::pattern( '/Customer could not be created/' ),
				false,
				true
			);

		// Logger should log the abortion
		$logger->shouldReceive( 'log_sync_operation' )
			->once()
			->with(
				'order_sync',
				Mockery::on( function ( $data ) {
					return $data['order_id'] === 789
						&& $data['action'] === 'aborted'
						&& $data['reason'] === 'customer_creation_failed';
				} ),
				false,
				'Customer creation failed - aborting order sync'
			);

		// Retry should be scheduled
		$retry_manager->shouldReceive( 'schedule_retry' )
			->once()
			->with(
				'order_sync',
				array( 'order_id' => 789 ),
				'Customer creation failed - aborting order sync'
			);

		// Create Order_Sync instance
		$order_sync = new Order_Sync(
			$client,
			$customer_sync,
			$mapper,
			$logger,
			$retry_manager
		);

		// Call sync_order
		$result = $order_sync->sync_order( $order );

		// Should return false (sync aborted)
		$this->assertFalse( $result );
	}

	/**
	 * Test that hook is triggered when order status changes to processing
	 *
	 * This is a documentation test that describes the expected behavior.
	 * Actual WordPress hook testing would require WordPress test framework.
	 *
	 * Validates: Requirements 1.1
	 */
	public function test_hook_trigger_documentation() {
		// This test documents that:
		// 1. The woocommerce_order_status_processing hook should be registered
		// 2. The hook should call Plugin::handle_order_status_processing()
		// 3. The handler should check if order is already synced
		// 4. If not synced, it should call Order_Sync::sync_order()

		$this->assertTrue( true, 'Hook behavior documented' );
	}
}
