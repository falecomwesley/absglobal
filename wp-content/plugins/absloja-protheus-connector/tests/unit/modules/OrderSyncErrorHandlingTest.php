<?php
/**
 * Order Sync Error Handling Tests
 *
 * Tests for enhanced error handling in Order_Sync module including
 * business error classification, TES errors, stock errors, and retry logic.
 *
 * @package ABSLoja\ProtheusConnector\Tests\Unit\Modules
 * @since 1.0.0
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
 * Class OrderSyncErrorHandlingTest
 *
 * Tests error handling functionality in Order_Sync module.
 *
 * @since 1.0.0
 */
class OrderSyncErrorHandlingTest extends TestCase {

	/**
	 * Tear down after each test
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Mockery::close();
		parent::tearDown();
	}

	/**
	 * Test TES error is classified as business error
	 *
	 * @return void
	 */
	public function test_tes_error_classified_as_business_error(): void {
		$client = Mockery::mock( Protheus_Client::class );
		$customer_sync = Mockery::mock( Customer_Sync::class );
		$mapper = Mockery::mock( Mapping_Engine::class );
		$logger = Mockery::mock( Logger::class );
		$retry_manager = Mockery::mock( Retry_Manager::class );

		$order = Mockery::mock( \WC_Order::class );
		$order->shouldReceive( 'get_id' )->andReturn( 123 );
		$order->shouldReceive( 'get_meta' )->with( '_protheus_order_id', true )->andReturn( '' );
		$order->shouldReceive( 'get_billing_state' )->andReturn( 'SP' );
		$order->shouldReceive( 'update_meta_data' )->andReturnSelf();
		$order->shouldReceive( 'save' )->andReturnSelf();
		$order->shouldReceive( 'add_order_note' )->andReturnSelf();

		$customer_sync->shouldReceive( 'ensure_customer_exists' )->andReturn( 'CUST001' );
		$mapper->shouldReceive( 'get_order_mapping' )->andReturn( array() );

		// Simulate TES error response
		$client->shouldReceive( 'post' )->andReturn( array(
			'success'     => false,
			'error'       => 'TES não encontrado para o estado SP',
			'error_type'  => 'client_error',
			'status_code' => 400,
		) );

		// Logger should log the error with business_error classification
		$logger->shouldReceive( 'log_api_request' )->once();
		$logger->shouldReceive( 'log_sync_operation' )
			->once()
			->with(
				'order_sync',
				Mockery::on( function ( $data ) {
					return isset( $data['business_error'] ) && $data['business_error'] === 'tes_error';
				} ),
				false,
				Mockery::any()
			);
		$logger->shouldReceive( 'log_error' )->once();

		// Retry should NOT be scheduled for business errors
		$retry_manager->shouldReceive( 'schedule_retry' )->never();

		$order_sync = new Order_Sync( $client, $customer_sync, $mapper, $logger, $retry_manager );
		
		$reflection = new \ReflectionClass( $order_sync );
		$method = $reflection->getMethod( 'map_order_to_protheus' );
		$method->setAccessible( true );
		$method->invoke( $order_sync, $order, 'CUST001' );

		$result = $order_sync->sync_order( $order );

		$this->assertFalse( $result );
	}

	/**
	 * Test stock insufficient error updates WooCommerce stock
	 *
	 * @return void
	 */
	public function test_stock_insufficient_error_updates_woocommerce_stock(): void {
		$client = Mockery::mock( Protheus_Client::class );
		$customer_sync = Mockery::mock( Customer_Sync::class );
		$mapper = Mockery::mock( Mapping_Engine::class );
		$logger = Mockery::mock( Logger::class );
		$retry_manager = Mockery::mock( Retry_Manager::class );

		$product = Mockery::mock( \WC_Product::class );
		$product->shouldReceive( 'get_sku' )->andReturn( 'PROD001' );
		$product->shouldReceive( 'get_id' )->andReturn( 456 );
		$product->shouldReceive( 'set_stock_quantity' )->with( 0 )->andReturnSelf();
		$product->shouldReceive( 'set_catalog_visibility' )->with( 'hidden' )->andReturnSelf();
		$product->shouldReceive( 'save' )->andReturnSelf();

		$item = Mockery::mock( \WC_Order_Item_Product::class );
		$item->shouldReceive( 'get_product' )->andReturn( $product );

		$order = Mockery::mock( \WC_Order::class );
		$order->shouldReceive( 'get_id' )->andReturn( 123 );
		$order->shouldReceive( 'get_meta' )->with( '_protheus_order_id', true )->andReturn( '' );
		$order->shouldReceive( 'get_items' )->andReturn( array( $item ) );
		$order->shouldReceive( 'update_meta_data' )->andReturnSelf();
		$order->shouldReceive( 'save' )->andReturnSelf();
		$order->shouldReceive( 'add_order_note' )->andReturnSelf();

		$customer_sync->shouldReceive( 'ensure_customer_exists' )->andReturn( 'CUST001' );
		$mapper->shouldReceive( 'get_order_mapping' )->andReturn( array() );

		// Simulate stock insufficient error
		$client->shouldReceive( 'post' )->andReturn( array(
			'success'     => false,
			'error'       => 'Estoque insuficiente para o produto PROD001',
			'error_type'  => 'client_error',
			'status_code' => 400,
		) );

		$logger->shouldReceive( 'log_api_request' )->once();
		$logger->shouldReceive( 'log_sync_operation' )->twice(); // Once for error, once for stock update
		$retry_manager->shouldReceive( 'schedule_retry' )->never();

		$order_sync = new Order_Sync( $client, $customer_sync, $mapper, $logger, $retry_manager );
		
		$reflection = new \ReflectionClass( $order_sync );
		$method = $reflection->getMethod( 'map_order_to_protheus' );
		$method->setAccessible( true );
		$method->invoke( $order_sync, $order, 'CUST001' );

		$result = $order_sync->sync_order( $order );

		$this->assertFalse( $result );
	}

	/**
	 * Test transient errors schedule retry
	 *
	 * @return void
	 */
	public function test_transient_errors_schedule_retry(): void {
		$client = Mockery::mock( Protheus_Client::class );
		$customer_sync = Mockery::mock( Customer_Sync::class );
		$mapper = Mockery::mock( Mapping_Engine::class );
		$logger = Mockery::mock( Logger::class );
		$retry_manager = Mockery::mock( Retry_Manager::class );

		$order = Mockery::mock( \WC_Order::class );
		$order->shouldReceive( 'get_id' )->andReturn( 123 );
		$order->shouldReceive( 'get_meta' )->with( '_protheus_order_id', true )->andReturn( '' );
		$order->shouldReceive( 'update_meta_data' )->andReturnSelf();
		$order->shouldReceive( 'save' )->andReturnSelf();
		$order->shouldReceive( 'add_order_note' )->andReturnSelf();

		$customer_sync->shouldReceive( 'ensure_customer_exists' )->andReturn( 'CUST001' );
		$mapper->shouldReceive( 'get_order_mapping' )->andReturn( array() );

		// Simulate timeout error (transient)
		$client->shouldReceive( 'post' )->andReturn( array(
			'success'     => false,
			'error'       => 'Connection timeout',
			'error_type'  => 'timeout_error',
			'status_code' => 0,
		) );

		$logger->shouldReceive( 'log_api_request' )->once();
		$logger->shouldReceive( 'log_sync_operation' )->once();

		// Retry SHOULD be scheduled for transient errors
		$retry_manager->shouldReceive( 'schedule_retry' )
			->once()
			->with( 'order_sync', array( 'order_id' => 123 ), 'Connection timeout' );

		$order_sync = new Order_Sync( $client, $customer_sync, $mapper, $logger, $retry_manager );
		
		$reflection = new \ReflectionClass( $order_sync );
		$method = $reflection->getMethod( 'map_order_to_protheus' );
		$method->setAccessible( true );
		$method->invoke( $order_sync, $order, 'CUST001' );

		$result = $order_sync->sync_order( $order );

		$this->assertFalse( $result );
	}

	/**
	 * Test business errors add manual review flag
	 *
	 * @return void
	 */
	public function test_business_errors_add_manual_review_flag(): void {
		$client = Mockery::mock( Protheus_Client::class );
		$customer_sync = Mockery::mock( Customer_Sync::class );
		$mapper = Mockery::mock( Mapping_Engine::class );
		$logger = Mockery::mock( Logger::class );
		$retry_manager = Mockery::mock( Retry_Manager::class );

		$order = Mockery::mock( \WC_Order::class );
		$order->shouldReceive( 'get_id' )->andReturn( 123 );
		$order->shouldReceive( 'get_meta' )->with( '_protheus_order_id', true )->andReturn( '' );
		$order->shouldReceive( 'save' )->andReturnSelf();
		$order->shouldReceive( 'add_order_note' )->andReturnSelf();

		// Expect manual review flag to be set
		$order->shouldReceive( 'update_meta_data' )
			->with( '_protheus_requires_manual_review', true )
			->once();
		$order->shouldReceive( 'update_meta_data' )->andReturnSelf();

		$customer_sync->shouldReceive( 'ensure_customer_exists' )->andReturn( 'CUST001' );
		$mapper->shouldReceive( 'get_order_mapping' )->andReturn( array() );

		// Simulate business error
		$client->shouldReceive( 'post' )->andReturn( array(
			'success'     => false,
			'error'       => 'CPF inválido',
			'error_type'  => 'client_error',
			'status_code' => 400,
		) );

		$logger->shouldReceive( 'log_api_request' )->once();
		$logger->shouldReceive( 'log_sync_operation' )->once();
		$retry_manager->shouldReceive( 'schedule_retry' )->never();

		$order_sync = new Order_Sync( $client, $customer_sync, $mapper, $logger, $retry_manager );
		
		$reflection = new \ReflectionClass( $order_sync );
		$method = $reflection->getMethod( 'map_order_to_protheus' );
		$method->setAccessible( true );
		$method->invoke( $order_sync, $order, 'CUST001' );

		$result = $order_sync->sync_order( $order );

		$this->assertFalse( $result );
	}

	/**
	 * Test admin note includes detailed error information
	 *
	 * @return void
	 */
	public function test_admin_note_includes_detailed_error_information(): void {
		$client = Mockery::mock( Protheus_Client::class );
		$customer_sync = Mockery::mock( Customer_Sync::class );
		$mapper = Mockery::mock( Mapping_Engine::class );
		$logger = Mockery::mock( Logger::class );
		$retry_manager = Mockery::mock( Retry_Manager::class );

		$order = Mockery::mock( \WC_Order::class );
		$order->shouldReceive( 'get_id' )->andReturn( 123 );
		$order->shouldReceive( 'get_meta' )->with( '_protheus_order_id', true )->andReturn( '' );
		$order->shouldReceive( 'get_billing_state' )->andReturn( 'SP' );
		$order->shouldReceive( 'update_meta_data' )->andReturnSelf();
		$order->shouldReceive( 'save' )->andReturnSelf();

		// Expect detailed admin note
		$order->shouldReceive( 'add_order_note' )
			->once()
			->with(
				Mockery::on( function ( $note ) {
					return strpos( $note, 'TES Configuration Error' ) !== false &&
					       strpos( $note, 'Action required' ) !== false &&
					       strpos( $note, 'manual review' ) !== false;
				} ),
				false,
				true
			);

		$customer_sync->shouldReceive( 'ensure_customer_exists' )->andReturn( 'CUST001' );
		$mapper->shouldReceive( 'get_order_mapping' )->andReturn( array() );

		$client->shouldReceive( 'post' )->andReturn( array(
			'success'     => false,
			'error'       => 'TES not found for state SP',
			'error_type'  => 'client_error',
			'status_code' => 400,
		) );

		$logger->shouldReceive( 'log_api_request' )->once();
		$logger->shouldReceive( 'log_sync_operation' )->once();
		$logger->shouldReceive( 'log_error' )->once();
		$retry_manager->shouldReceive( 'schedule_retry' )->never();

		$order_sync = new Order_Sync( $client, $customer_sync, $mapper, $logger, $retry_manager );
		
		$reflection = new \ReflectionClass( $order_sync );
		$method = $reflection->getMethod( 'map_order_to_protheus' );
		$method->setAccessible( true );
		$method->invoke( $order_sync, $order, 'CUST001' );

		$result = $order_sync->sync_order( $order );

		$this->assertFalse( $result );
	}

	/**
	 * Test network errors are classified correctly
	 *
	 * @return void
	 */
	public function test_network_errors_classified_correctly(): void {
		$client = Mockery::mock( Protheus_Client::class );
		$customer_sync = Mockery::mock( Customer_Sync::class );
		$mapper = Mockery::mock( Mapping_Engine::class );
		$logger = Mockery::mock( Logger::class );
		$retry_manager = Mockery::mock( Retry_Manager::class );

		$order = Mockery::mock( \WC_Order::class );
		$order->shouldReceive( 'get_id' )->andReturn( 123 );
		$order->shouldReceive( 'get_meta' )->with( '_protheus_order_id', true )->andReturn( '' );
		$order->shouldReceive( 'update_meta_data' )->andReturnSelf();
		$order->shouldReceive( 'save' )->andReturnSelf();
		$order->shouldReceive( 'add_order_note' )->andReturnSelf();

		$customer_sync->shouldReceive( 'ensure_customer_exists' )->andReturn( 'CUST001' );
		$mapper->shouldReceive( 'get_order_mapping' )->andReturn( array() );

		$network_errors = array(
			'timeout_error',
			'network_error',
			'connection_error',
			'dns_error',
			'ssl_error',
		);

		foreach ( $network_errors as $error_type ) {
			$client->shouldReceive( 'post' )->andReturn( array(
				'success'     => false,
				'error'       => 'Network error',
				'error_type'  => $error_type,
				'status_code' => 0,
			) );

			$logger->shouldReceive( 'log_api_request' )->once();
			$logger->shouldReceive( 'log_sync_operation' )->once();

			// All network errors should schedule retry
			$retry_manager->shouldReceive( 'schedule_retry' )->once();

			$order_sync = new Order_Sync( $client, $customer_sync, $mapper, $logger, $retry_manager );
			
			$reflection = new \ReflectionClass( $order_sync );
			$method = $reflection->getMethod( 'map_order_to_protheus' );
			$method->setAccessible( true );
			$method->invoke( $order_sync, $order, 'CUST001' );

			$result = $order_sync->sync_order( $order );

			$this->assertFalse( $result, "Failed for error type: {$error_type}" );
		}
	}
}
