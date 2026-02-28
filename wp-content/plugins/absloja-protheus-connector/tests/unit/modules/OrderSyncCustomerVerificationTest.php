<?php
/**
 * Order Sync Customer Verification Integration Tests
 *
 * Tests the integration between Order_Sync and Customer_Sync modules,
 * validating that customer verification/creation happens before order sync.
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

/**
 * Class OrderSyncCustomerVerificationTest
 *
 * Integration tests for customer verification before order sync.
 *
 * @since 1.0.0
 */
class OrderSyncCustomerVerificationTest extends TestCase {

	/**
	 * Test that ensure_customer_exists is called before order sync
	 *
	 * @test
	 */
	public function test_ensure_customer_exists_called_before_order_sync() {
		// Create mock order
		$order = $this->createMockOrder( 123 );

		// Create mock Customer_Sync that tracks method calls
		$customer_sync = $this->createMock( Customer_Sync::class );
		$customer_sync->expects( $this->once() )
			->method( 'ensure_customer_exists' )
			->with( $order )
			->willReturn( '000001' );

		// Create mock Protheus_Client
		$client = $this->createMock( Protheus_Client::class );
		$client->method( 'post' )
			->willReturn( array(
				'success' => true,
				'data'    => array( 'C5_NUM' => 'PED001' ),
			) );

		// Create other dependencies
		$mapper = $this->createMockMappingEngine();
		$logger = $this->createMock( Logger::class );
		$retry_manager = $this->createMock( Retry_Manager::class );

		// Create Order_Sync instance
		$order_sync = new Order_Sync( $client, $customer_sync, $mapper, $logger, $retry_manager );

		// Execute sync_order
		$result = $order_sync->sync_order( $order );

		// Assert success
		$this->assertTrue( $result );
	}

	/**
	 * Test that order sync aborts when customer creation fails
	 *
	 * @test
	 */
	public function test_order_sync_aborts_when_customer_creation_fails() {
		// Create mock order
		$order = $this->createMockOrder( 124 );

		// Create mock Customer_Sync that returns null (failure)
		$customer_sync = $this->createMock( Customer_Sync::class );
		$customer_sync->method( 'ensure_customer_exists' )
			->with( $order )
			->willReturn( null );

		// Create mock Protheus_Client - should NOT be called
		$client = $this->createMock( Protheus_Client::class );
		$client->expects( $this->never() )
			->method( 'post' );

		// Create mock Logger - should log the failure
		$logger = $this->createMock( Logger::class );
		$logger->expects( $this->once() )
			->method( 'log_sync_operation' )
			->with(
				'order_sync',
				$this->callback( function( $data ) {
					return $data['action'] === 'aborted' && $data['reason'] === 'customer_creation_failed';
				} ),
				false,
				'Customer creation failed - aborting order sync'
			);

		// Create mock Retry_Manager - should schedule retry
		$retry_manager = $this->createMock( Retry_Manager::class );
		$retry_manager->expects( $this->once() )
			->method( 'schedule_retry' )
			->with(
				'order_sync',
				array( 'order_id' => 124 ),
				'Customer creation failed - aborting order sync'
			);

		// Create other dependencies
		$mapper = $this->createMockMappingEngine();

		// Create Order_Sync instance
		$order_sync = new Order_Sync( $client, $customer_sync, $mapper, $logger, $retry_manager );

		// Execute sync_order
		$result = $order_sync->sync_order( $order );

		// Assert failure
		$this->assertFalse( $result );
	}

	/**
	 * Test that customer code is used in C5_CLIENTE field
	 *
	 * @test
	 */
	public function test_customer_code_used_in_c5_cliente_field() {
		$customer_code = '000042';

		// Create mock order
		$order = $this->createMockOrder( 125 );

		// Create mock Customer_Sync that returns customer code
		$customer_sync = $this->createMock( Customer_Sync::class );
		$customer_sync->method( 'ensure_customer_exists' )
			->with( $order )
			->willReturn( $customer_code );

		// Create mock Protheus_Client that captures the payload
		$captured_payload = null;
		$client = $this->createMock( Protheus_Client::class );
		$client->method( 'post' )
			->willReturnCallback( function( $endpoint, $payload ) use ( &$captured_payload ) {
				$captured_payload = $payload;
				return array(
					'success' => true,
					'data'    => array( 'C5_NUM' => 'PED002' ),
				);
			} );

		// Create other dependencies
		$mapper = $this->createMockMappingEngine();
		$logger = $this->createMock( Logger::class );
		$retry_manager = $this->createMock( Retry_Manager::class );

		// Create Order_Sync instance
		$order_sync = new Order_Sync( $client, $customer_sync, $mapper, $logger, $retry_manager );

		// Execute sync_order
		$result = $order_sync->sync_order( $order );

		// Assert success
		$this->assertTrue( $result );

		// Assert customer code is in payload
		$this->assertNotNull( $captured_payload );
		$this->assertArrayHasKey( 'header', $captured_payload );
		$this->assertArrayHasKey( 'C5_CLIENTE', $captured_payload['header'] );
		$this->assertEquals( $customer_code, $captured_payload['header']['C5_CLIENTE'] );
	}

	/**
	 * Test that customer code is stored in order metadata
	 *
	 * @test
	 */
	public function test_customer_code_stored_in_order_metadata() {
		$customer_code = '000099';

		// Create mock order with metadata tracking
		$order = $this->createMockOrder( 126 );
		$metadata = array();
		
		$order->method( 'update_meta_data' )
			->willReturnCallback( function( $key, $value ) use ( &$metadata ) {
				$metadata[ $key ] = $value;
			} );

		// Create mock Customer_Sync
		$customer_sync = $this->createMock( Customer_Sync::class );
		$customer_sync->method( 'ensure_customer_exists' )
			->willReturn( $customer_code );

		// Create mock Protheus_Client
		$client = $this->createMock( Protheus_Client::class );
		$client->method( 'post' )
			->willReturn( array(
				'success' => true,
				'data'    => array( 'C5_NUM' => 'PED003' ),
			) );

		// Create other dependencies
		$mapper = $this->createMockMappingEngine();
		$logger = $this->createMock( Logger::class );
		$retry_manager = $this->createMock( Retry_Manager::class );

		// Create Order_Sync instance
		$order_sync = new Order_Sync( $client, $customer_sync, $mapper, $logger, $retry_manager );

		// Execute sync_order
		$result = $order_sync->sync_order( $order );

		// Assert success
		$this->assertTrue( $result );

		// Assert customer code is stored in metadata
		$this->assertArrayHasKey( '_protheus_customer_code', $metadata );
		$this->assertEquals( $customer_code, $metadata['_protheus_customer_code'] );
	}

	/**
	 * Test that admin note is added when customer creation fails
	 *
	 * @test
	 */
	public function test_admin_note_added_when_customer_creation_fails() {
		// Create mock order with note tracking
		$order = $this->createMockOrder( 127 );
		$notes = array();
		
		$order->method( 'add_order_note' )
			->willReturnCallback( function( $note ) use ( &$notes ) {
				$notes[] = $note;
			} );

		// Create mock Customer_Sync that fails
		$customer_sync = $this->createMock( Customer_Sync::class );
		$customer_sync->method( 'ensure_customer_exists' )
			->willReturn( null );

		// Create other dependencies
		$client = $this->createMock( Protheus_Client::class );
		$mapper = $this->createMockMappingEngine();
		$logger = $this->createMock( Logger::class );
		$retry_manager = $this->createMock( Retry_Manager::class );

		// Create Order_Sync instance
		$order_sync = new Order_Sync( $client, $customer_sync, $mapper, $logger, $retry_manager );

		// Execute sync_order
		$result = $order_sync->sync_order( $order );

		// Assert failure
		$this->assertFalse( $result );

		// Assert admin note was added
		$this->assertNotEmpty( $notes );
		$this->assertStringContainsString( 'Customer could not be created', $notes[0] );
	}

	/**
	 * Test that existing customer code is reused
	 *
	 * @test
	 */
	public function test_existing_customer_code_reused() {
		$existing_customer_code = '000123';

		// Create mock order
		$order = $this->createMockOrder( 128 );

		// Create mock Customer_Sync that returns existing customer
		$customer_sync = $this->createMock( Customer_Sync::class );
		$customer_sync->expects( $this->once() )
			->method( 'ensure_customer_exists' )
			->with( $order )
			->willReturn( $existing_customer_code );

		// Create mock Protheus_Client
		$captured_payload = null;
		$client = $this->createMock( Protheus_Client::class );
		$client->method( 'post' )
			->willReturnCallback( function( $endpoint, $payload ) use ( &$captured_payload ) {
				$captured_payload = $payload;
				return array(
					'success' => true,
					'data'    => array( 'C5_NUM' => 'PED004' ),
				);
			} );

		// Create other dependencies
		$mapper = $this->createMockMappingEngine();
		$logger = $this->createMock( Logger::class );
		$retry_manager = $this->createMock( Retry_Manager::class );

		// Create Order_Sync instance
		$order_sync = new Order_Sync( $client, $customer_sync, $mapper, $logger, $retry_manager );

		// Execute sync_order
		$result = $order_sync->sync_order( $order );

		// Assert success
		$this->assertTrue( $result );

		// Assert existing customer code is used
		$this->assertEquals( $existing_customer_code, $captured_payload['header']['C5_CLIENTE'] );
	}

	/**
	 * Create mock WC_Order
	 *
	 * @param int $order_id Order ID.
	 * @return \WC_Order|\PHPUnit\Framework\MockObject\MockObject
	 */
	private function createMockOrder( int $order_id ) {
		$order = $this->createMock( \WC_Order::class );
		
		$order->method( 'get_id' )->willReturn( $order_id );
		$order->method( 'get_meta' )->willReturn( '' );
		$order->method( 'get_payment_method' )->willReturn( 'bacs' );
		$order->method( 'get_billing_state' )->willReturn( 'SP' );
		$order->method( 'get_billing_first_name' )->willReturn( 'João' );
		$order->method( 'get_billing_last_name' )->willReturn( 'Silva' );
		$order->method( 'get_billing_address_1' )->willReturn( 'Rua Teste, 123' );
		$order->method( 'get_billing_city' )->willReturn( 'São Paulo' );
		$order->method( 'get_billing_postcode' )->willReturn( '01234-567' );
		$order->method( 'get_billing_phone' )->willReturn( '(11) 98765-4321' );
		$order->method( 'get_billing_email' )->willReturn( 'joao@example.com' );
		$order->method( 'get_shipping_total' )->willReturn( 10.00 );
		$order->method( 'get_discount_total' )->willReturn( 5.00 );
		$order->method( 'get_items' )->willReturn( array() );
		
		$date_created = $this->createMock( \WC_DateTime::class );
		$date_created->method( 'format' )->willReturn( '20240115' );
		$order->method( 'get_date_created' )->willReturn( $date_created );
		
		$order->method( 'update_meta_data' )->willReturn( null );
		$order->method( 'delete_meta_data' )->willReturn( null );
		$order->method( 'save' )->willReturn( null );
		$order->method( 'add_order_note' )->willReturn( null );

		return $order;
	}

	/**
	 * Create mock Mapping_Engine
	 *
	 * @return Mapping_Engine|\PHPUnit\Framework\MockObject\MockObject
	 */
	private function createMockMappingEngine() {
		$mapper = $this->createMock( Mapping_Engine::class );
		
		$mapper->method( 'get_order_mapping' )->willReturn( array(
			'SC5' => array(
				'C5_FILIAL'  => '01',
				'C5_TIPO'    => 'N',
				'C5_LOJACLI' => '01',
				'C5_TABELA'  => '001',
				'C5_VEND1'   => '000001',
			),
			'SC6' => array(
				'C6_FILIAL' => '01',
			),
		) );
		
		$mapper->method( 'get_payment_mapping' )->willReturn( '001' );
		$mapper->method( 'get_tes_by_state' )->willReturn( '501' );
		$mapper->method( 'get_status_mapping' )->willReturn( 'pending' );

		return $mapper;
	}
}
