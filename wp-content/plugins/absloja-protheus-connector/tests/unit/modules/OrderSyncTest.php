<?php
/**
 * Order Sync Unit Tests
 *
 * Comprehensive unit tests for Order_Sync module (Task 8.9).
 * Tests order synchronization, field mapping, TES determination,
 * cancellation, refund, and error handling.
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
use WC_Order;
use WC_Order_Item_Product;

/**
 * Class OrderSyncTest
 *
 * Comprehensive unit tests for Order_Sync module.
 *
 * @since 1.0.0
 */
class OrderSyncTest extends TestCase {

	/**
	 * Set up test environment
	 */
	protected function setUp(): void {
		parent::setUp();
	}

	/**
	 * Tear down test environment
	 */
	protected function tearDown(): void {
		parent::tearDown();
	}

	/**
	 * Test successful order sync
	 */
	public function test_sync_order_success() {
		// Create mock order
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_id' )->willReturn( 123 );
		$order->method( 'get_meta' )->willReturnMap([
			[ '_protheus_order_id', true, '' ],
		]);
		$order->method( 'get_payment_method' )->willReturn( 'credit_card' );
		$order->method( 'get_billing_state' )->willReturn( 'SP' );
		$order->method( 'get_date_created' )->willReturn( new \WC_DateTime( '2024-01-15 10:00:00' ) );
		$order->method( 'get_shipping_total' )->willReturn( 15.50 );
		$order->method( 'get_discount_total' )->willReturn( 5.00 );
		$order->method( 'get_items' )->willReturn( [] );
		$order->expects( $this->atLeastOnce() )->method( 'update_meta_data' );
		$order->expects( $this->atLeastOnce() )->method( 'save' );
		$order->expects( $this->once() )->method( 'add_order_note' );

		// Mock customer sync
		$customer_sync = $this->createMock( Customer_Sync::class );
		$customer_sync->method( 'ensure_customer_exists' )->willReturn( '000123' );

		// Mock mapper
		$mapper = $this->createMock( Mapping_Engine::class );
		$mapper->method( 'get_order_mapping' )->willReturn([
			'SC5' => [
				'C5_FILIAL' => '01',
				'C5_TIPO' => 'N',
				'C5_LOJACLI' => '01',
				'C5_TABELA' => '001',
				'C5_VEND1' => '000001',
			],
			'SC6' => [
				'C6_FILIAL' => '01',
			],
		]);
		$mapper->method( 'get_payment_mapping' )->willReturn( '004' );
		$mapper->method( 'get_tes_by_state' )->willReturn( '501' );

		// Mock client
		$client = $this->createMock( Protheus_Client::class );
		$client->method( 'post' )->willReturn([
			'success' => true,
			'data' => [
				'C5_NUM' => 'PED001',
			],
		]);

		$logger = $this->createMock( Logger::class );
		$retry_manager = $this->createMock( Retry_Manager::class );

		$order_sync = new Order_Sync( $client, $customer_sync, $mapper, $logger, $retry_manager );
		$result = $order_sync->sync_order( $order );

		$this->assertTrue( $result );
	}

	/**
	 * Test order sync skips already synced orders
	 */
	public function test_sync_order_skips_already_synced() {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_id' )->willReturn( 456 );
		$order->method( 'get_meta' )->willReturnMap([
			[ '_protheus_order_id', true, 'PED999' ],
		]);

		$customer_sync = $this->createMock( Customer_Sync::class );
		$mapper = $this->createMock( Mapping_Engine::class );
		$client = $this->createMock( Protheus_Client::class );
		$logger = $this->createMock( Logger::class );
		$retry_manager = $this->createMock( Retry_Manager::class );

		$order_sync = new Order_Sync( $client, $customer_sync, $mapper, $logger, $retry_manager );
		$result = $order_sync->sync_order( $order );

		$this->assertTrue( $result );
	}

	/**
	 * Test order sync aborts when customer creation fails
	 */
	public function test_sync_order_aborts_on_customer_creation_failure() {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_id' )->willReturn( 789 );
		$order->method( 'get_meta' )->willReturnMap([
			[ '_protheus_order_id', true, '' ],
		]);
		$order->expects( $this->once() )->method( 'add_order_note' );

		$customer_sync = $this->createMock( Customer_Sync::class );
		$customer_sync->method( 'ensure_customer_exists' )->willReturn( null );

		$mapper = $this->createMock( Mapping_Engine::class );
		$client = $this->createMock( Protheus_Client::class );
		$logger = $this->createMock( Logger::class );
		
		$retry_manager = $this->createMock( Retry_Manager::class );
		$retry_manager->expects( $this->once() )->method( 'schedule_retry' );

		$order_sync = new Order_Sync( $client, $customer_sync, $mapper, $logger, $retry_manager );
		$result = $order_sync->sync_order( $order );

		$this->assertFalse( $result );
	}

	/**
	 * Test SC5/SC6 field mapping
	 */
	public function test_order_field_mapping_sc5_sc6() {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_id' )->willReturn( 100 );
		$order->method( 'get_meta' )->willReturnMap([
			[ '_protheus_order_id', true, '' ],
		]);
		$order->method( 'get_payment_method' )->willReturn( 'pix' );
		$order->method( 'get_billing_state' )->willReturn( 'RJ' );
		$order->method( 'get_date_created' )->willReturn( new \WC_DateTime( '2024-02-20 14:30:00' ) );
		$order->method( 'get_shipping_total' )->willReturn( 20.00 );
		$order->method( 'get_discount_total' )->willReturn( 10.00 );
		
		// Mock order items
		$product = $this->createMock( \WC_Product::class );
		$product->method( 'get_sku' )->willReturn( 'PROD001' );
		
		$item = $this->createMock( WC_Order_Item_Product::class );
		$item->method( 'get_product' )->willReturn( $product );
		$item->method( 'get_quantity' )->willReturn( 2 );
		$item->method( 'get_total' )->willReturn( 100.00 );
		
		$order->method( 'get_items' )->willReturn( [ $item ] );
		$order->method( 'update_meta_data' )->willReturn( true );
		$order->method( 'save' )->willReturn( true );
		$order->method( 'add_order_note' )->willReturn( true );

		$customer_sync = $this->createMock( Customer_Sync::class );
		$customer_sync->method( 'ensure_customer_exists' )->willReturn( '000456' );

		$mapper = $this->createMock( Mapping_Engine::class );
		$mapper->method( 'get_order_mapping' )->willReturn([
			'SC5' => [
				'C5_FILIAL' => '01',
				'C5_TIPO' => 'N',
				'C5_LOJACLI' => '01',
				'C5_TABELA' => '001',
				'C5_VEND1' => '000001',
			],
			'SC6' => [
				'C6_FILIAL' => '01',
			],
		]);
		$mapper->method( 'get_payment_mapping' )->willReturn( '005' );
		$mapper->method( 'get_tes_by_state' )->willReturn( '502' );

		$client = $this->createMock( Protheus_Client::class );
		$client->expects( $this->once() )
			->method( 'post' )
			->with(
				'api/v1/orders',
				$this->callback( function( $payload ) {
					// Verify SC5 fields
					$this->assertEquals( '01', $payload['header']['C5_FILIAL'] );
					$this->assertEquals( 'N', $payload['header']['C5_TIPO'] );
					$this->assertEquals( '000456', $payload['header']['C5_CLIENTE'] );
					$this->assertEquals( '01', $payload['header']['C5_LOJACLI'] );
					$this->assertEquals( '005', $payload['header']['C5_CONDPAG'] );
					$this->assertEquals( '100', $payload['header']['C5_PEDWOO'] );
					$this->assertEquals( '20240220', $payload['header']['C5_EMISSAO'] );
					$this->assertEquals( 20.00, $payload['header']['C5_FRETE'] );
					$this->assertEquals( 10.00, $payload['header']['C5_DESCONT'] );
					
					// Verify SC6 fields
					$this->assertCount( 1, $payload['items'] );
					$this->assertEquals( '01', $payload['items'][0]['C6_FILIAL'] );
					$this->assertEquals( '01', $payload['items'][0]['C6_ITEM'] );
					$this->assertEquals( 'PROD001', $payload['items'][0]['C6_PRODUTO'] );
					$this->assertEquals( 2.0, $payload['items'][0]['C6_QTDVEN'] );
					$this->assertEquals( 50.0, $payload['items'][0]['C6_PRCVEN'] );
					$this->assertEquals( 100.00, $payload['items'][0]['C6_VALOR'] );
					$this->assertEquals( '502', $payload['items'][0]['C6_TES'] );
					
					return true;
				})
			)
			->willReturn([
				'success' => true,
				'data' => [ 'C5_NUM' => 'PED100' ],
			]);

		$logger = $this->createMock( Logger::class );
		$retry_manager = $this->createMock( Retry_Manager::class );

		$order_sync = new Order_Sync( $client, $customer_sync, $mapper, $logger, $retry_manager );
		$result = $order_sync->sync_order( $order );

		$this->assertTrue( $result );
	}

	/**
	 * Test TES determination by state
	 */
	public function test_tes_determination_by_state() {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_id' )->willReturn( 200 );
		$order->method( 'get_meta' )->willReturnMap([
			[ '_protheus_order_id', true, '' ],
		]);
		$order->method( 'get_payment_method' )->willReturn( 'bacs' );
		$order->method( 'get_billing_state' )->willReturn( 'MG' );
		$order->method( 'get_date_created' )->willReturn( new \WC_DateTime( '2024-03-10' ) );
		$order->method( 'get_shipping_total' )->willReturn( 0 );
		$order->method( 'get_discount_total' )->willReturn( 0 );
		$order->method( 'get_items' )->willReturn( [] );
		$order->method( 'update_meta_data' )->willReturn( true );
		$order->method( 'save' )->willReturn( true );
		$order->method( 'add_order_note' )->willReturn( true );

		$customer_sync = $this->createMock( Customer_Sync::class );
		$customer_sync->method( 'ensure_customer_exists' )->willReturn( '000789' );

		$mapper = $this->createMock( Mapping_Engine::class );
		$mapper->method( 'get_order_mapping' )->willReturn([
			'SC5' => [ 'C5_FILIAL' => '01' ],
			'SC6' => [ 'C6_FILIAL' => '01' ],
		]);
		$mapper->method( 'get_payment_mapping' )->willReturn( '001' );
		$mapper->expects( $this->once() )
			->method( 'get_tes_by_state' )
			->with( 'MG' )
			->willReturn( '503' );

		$client = $this->createMock( Protheus_Client::class );
		$client->method( 'post' )->willReturn([
			'success' => true,
			'data' => [ 'C5_NUM' => 'PED200' ],
		]);

		$logger = $this->createMock( Logger::class );
		$retry_manager = $this->createMock( Retry_Manager::class );

		$order_sync = new Order_Sync( $client, $customer_sync, $mapper, $logger, $retry_manager );
		$result = $order_sync->sync_order( $order );

		$this->assertTrue( $result );
	}

	/**
	 * Test successful order cancellation
	 */
	public function test_cancel_order_success() {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_id' )->willReturn( 300 );
		$order->method( 'get_meta' )->willReturnMap([
			[ '_protheus_order_id', true, 'PED300' ],
		]);
		$order->expects( $this->once() )->method( 'add_order_note' );

		$client = $this->createMock( Protheus_Client::class );
		$client->expects( $this->once() )
			->method( 'post' )
			->with(
				'api/v1/orders/cancel',
				$this->callback( function( $payload ) {
					$this->assertEquals( 'PED300', $payload['order_id'] );
					$this->assertEquals( 'cancel', $payload['action'] );
					return true;
				})
			)
			->willReturn([
				'success' => true,
				'data' => [],
			]);

		$customer_sync = $this->createMock( Customer_Sync::class );
		$mapper = $this->createMock( Mapping_Engine::class );
		$logger = $this->createMock( Logger::class );
		$retry_manager = $this->createMock( Retry_Manager::class );

		$order_sync = new Order_Sync( $client, $customer_sync, $mapper, $logger, $retry_manager );
		$result = $order_sync->cancel_order( $order );

		$this->assertTrue( $result );
	}

	/**
	 * Test order cancellation skips non-synced orders
	 */
	public function test_cancel_order_skips_non_synced() {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_id' )->willReturn( 400 );
		$order->method( 'get_meta' )->willReturnMap([
			[ '_protheus_order_id', true, '' ],
		]);

		$client = $this->createMock( Protheus_Client::class );
		$client->expects( $this->never() )->method( 'post' );

		$customer_sync = $this->createMock( Customer_Sync::class );
		$mapper = $this->createMock( Mapping_Engine::class );
		$logger = $this->createMock( Logger::class );
		$retry_manager = $this->createMock( Retry_Manager::class );

		$order_sync = new Order_Sync( $client, $customer_sync, $mapper, $logger, $retry_manager );
		$result = $order_sync->cancel_order( $order );

		$this->assertFalse( $result );
	}

	/**
	 * Test successful order refund
	 */
	public function test_refund_order_success() {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_id' )->willReturn( 500 );
		$order->method( 'get_meta' )->willReturnMap([
			[ '_protheus_order_id', true, 'PED500' ],
		]);
		$order->method( 'get_total' )->willReturn( 250.00 );
		$order->expects( $this->once() )->method( 'add_order_note' );

		$client = $this->createMock( Protheus_Client::class );
		$client->expects( $this->once() )
			->method( 'post' )
			->with(
				'api/v1/orders/refund',
				$this->callback( function( $payload ) {
					$this->assertEquals( 'PED500', $payload['order_id'] );
					$this->assertEquals( 'refund', $payload['action'] );
					$this->assertEquals( 250.00, $payload['amount'] );
					return true;
				})
			)
			->willReturn([
				'success' => true,
				'data' => [],
			]);

		$customer_sync = $this->createMock( Customer_Sync::class );
		$mapper = $this->createMock( Mapping_Engine::class );
		$logger = $this->createMock( Logger::class );
		$retry_manager = $this->createMock( Retry_Manager::class );

		$order_sync = new Order_Sync( $client, $customer_sync, $mapper, $logger, $retry_manager );
		$result = $order_sync->refund_order( $order );

		$this->assertTrue( $result );
	}

	/**
	 * Test order refund skips non-synced orders
	 */
	public function test_refund_order_skips_non_synced() {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_id' )->willReturn( 600 );
		$order->method( 'get_meta' )->willReturnMap([
			[ '_protheus_order_id', true, '' ],
		]);

		$client = $this->createMock( Protheus_Client::class );
		$client->expects( $this->never() )->method( 'post' );

		$customer_sync = $this->createMock( Customer_Sync::class );
		$mapper = $this->createMock( Mapping_Engine::class );
		$logger = $this->createMock( Logger::class );
		$retry_manager = $this->createMock( Retry_Manager::class );

		$order_sync = new Order_Sync( $client, $customer_sync, $mapper, $logger, $retry_manager );
		$result = $order_sync->refund_order( $order );

		$this->assertFalse( $result );
	}

	/**
	 * Test TES error handling
	 */
	public function test_tes_error_handling() {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_id' )->willReturn( 700 );
		$order->method( 'get_meta' )->willReturnMap([
			[ '_protheus_order_id', true, '' ],
		]);
		$order->method( 'get_payment_method' )->willReturn( 'credit_card' );
		$order->method( 'get_billing_state' )->willReturn( 'SP' );
		$order->method( 'get_date_created' )->willReturn( new \WC_DateTime( '2024-04-01' ) );
		$order->method( 'get_shipping_total' )->willReturn( 0 );
		$order->method( 'get_discount_total' )->willReturn( 0 );
		$order->method( 'get_items' )->willReturn( [] );
		$order->expects( $this->atLeastOnce() )->method( 'update_meta_data' );
		$order->expects( $this->atLeastOnce() )->method( 'save' );
		$order->expects( $this->once() )->method( 'add_order_note' );

		$customer_sync = $this->createMock( Customer_Sync::class );
		$customer_sync->method( 'ensure_customer_exists' )->willReturn( '000111' );

		$mapper = $this->createMock( Mapping_Engine::class );
		$mapper->method( 'get_order_mapping' )->willReturn([
			'SC5' => [ 'C5_FILIAL' => '01' ],
			'SC6' => [ 'C6_FILIAL' => '01' ],
		]);
		$mapper->method( 'get_payment_mapping' )->willReturn( '004' );
		$mapper->method( 'get_tes_by_state' )->willReturn( '999' );

		$client = $this->createMock( Protheus_Client::class );
		$client->method( 'post' )->willReturn([
			'success' => false,
			'error' => 'TES 999 not found in Protheus',
			'error_type' => 'validation_error',
		]);

		$logger = $this->createMock( Logger::class );
		$logger->expects( $this->once() )->method( 'log_error' );
		
		$retry_manager = $this->createMock( Retry_Manager::class );
		// TES error should NOT schedule retry (business error)
		$retry_manager->expects( $this->never() )->method( 'schedule_retry' );

		$order_sync = new Order_Sync( $client, $customer_sync, $mapper, $logger, $retry_manager );
		$result = $order_sync->sync_order( $order );

		$this->assertFalse( $result );
	}

	/**
	 * Test insufficient stock error handling
	 */
	public function test_insufficient_stock_error_handling() {
		$product = $this->createMock( \WC_Product::class );
		$product->method( 'get_sku' )->willReturn( 'PROD999' );
		$product->method( 'get_id' )->willReturn( 999 );
		$product->expects( $this->once() )->method( 'set_stock_quantity' )->with( 0 );
		$product->expects( $this->once() )->method( 'set_catalog_visibility' )->with( 'hidden' );
		$product->expects( $this->once() )->method( 'save' );

		$item = $this->createMock( WC_Order_Item_Product::class );
		$item->method( 'get_product' )->willReturn( $product );
		$item->method( 'get_quantity' )->willReturn( 5 );
		$item->method( 'get_total' )->willReturn( 500.00 );

		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_id' )->willReturn( 800 );
		$order->method( 'get_meta' )->willReturnMap([
			[ '_protheus_order_id', true, '' ],
		]);
		$order->method( 'get_payment_method' )->willReturn( 'pix' );
		$order->method( 'get_billing_state' )->willReturn( 'RJ' );
		$order->method( 'get_date_created' )->willReturn( new \WC_DateTime( '2024-05-01' ) );
		$order->method( 'get_shipping_total' )->willReturn( 0 );
		$order->method( 'get_discount_total' )->willReturn( 0 );
		$order->method( 'get_items' )->willReturn( [ $item ] );
		$order->expects( $this->atLeastOnce() )->method( 'update_meta_data' );
		$order->expects( $this->atLeastOnce() )->method( 'save' );
		$order->expects( $this->once() )->method( 'add_order_note' );

		$customer_sync = $this->createMock( Customer_Sync::class );
		$customer_sync->method( 'ensure_customer_exists' )->willReturn( '000222' );

		$mapper = $this->createMock( Mapping_Engine::class );
		$mapper->method( 'get_order_mapping' )->willReturn([
			'SC5' => [ 'C5_FILIAL' => '01' ],
			'SC6' => [ 'C6_FILIAL' => '01' ],
		]);
		$mapper->method( 'get_payment_mapping' )->willReturn( '005' );
		$mapper->method( 'get_tes_by_state' )->willReturn( '502' );

		$client = $this->createMock( Protheus_Client::class );
		$client->method( 'post' )->willReturn([
			'success' => false,
			'error' => 'Estoque insuficiente para produto PROD999',
			'error_type' => 'business_error',
		]);

		$logger = $this->createMock( Logger::class );
		
		$retry_manager = $this->createMock( Retry_Manager::class );
		// Stock error should NOT schedule retry (business error)
		$retry_manager->expects( $this->never() )->method( 'schedule_retry' );

		$order_sync = new Order_Sync( $client, $customer_sync, $mapper, $logger, $retry_manager );
		$result = $order_sync->sync_order( $order );

		$this->assertFalse( $result );
	}

	/**
	 * Test network error schedules retry
	 */
	public function test_network_error_schedules_retry() {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_id' )->willReturn( 900 );
		$order->method( 'get_meta' )->willReturnMap([
			[ '_protheus_order_id', true, '' ],
		]);
		$order->method( 'get_payment_method' )->willReturn( 'bacs' );
		$order->method( 'get_billing_state' )->willReturn( 'SP' );
		$order->method( 'get_date_created' )->willReturn( new \WC_DateTime( '2024-06-01' ) );
		$order->method( 'get_shipping_total' )->willReturn( 0 );
		$order->method( 'get_discount_total' )->willReturn( 0 );
		$order->method( 'get_items' )->willReturn( [] );
		$order->expects( $this->atLeastOnce() )->method( 'update_meta_data' );
		$order->expects( $this->atLeastOnce() )->method( 'save' );
		$order->expects( $this->once() )->method( 'add_order_note' );

		$customer_sync = $this->createMock( Customer_Sync::class );
		$customer_sync->method( 'ensure_customer_exists' )->willReturn( '000333' );

		$mapper = $this->createMock( Mapping_Engine::class );
		$mapper->method( 'get_order_mapping' )->willReturn([
			'SC5' => [ 'C5_FILIAL' => '01' ],
			'SC6' => [ 'C6_FILIAL' => '01' ],
		]);
		$mapper->method( 'get_payment_mapping' )->willReturn( '001' );
		$mapper->method( 'get_tes_by_state' )->willReturn( '501' );

		$client = $this->createMock( Protheus_Client::class );
		$client->method( 'post' )->willReturn([
			'success' => false,
			'error' => 'Connection timeout',
			'error_type' => 'timeout_error',
		]);

		$logger = $this->createMock( Logger::class );
		
		$retry_manager = $this->createMock( Retry_Manager::class );
		// Network error SHOULD schedule retry
		$retry_manager->expects( $this->once() )
			->method( 'schedule_retry' )
			->with( 'order_sync', [ 'order_id' => 900 ], 'Connection timeout' );

		$order_sync = new Order_Sync( $client, $customer_sync, $mapper, $logger, $retry_manager );
		$result = $order_sync->sync_order( $order );

		$this->assertFalse( $result );
	}

	/**
	 * Test order status sync success
	 */
	public function test_sync_order_status_success() {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_id' )->willReturn( 1000 );
		$order->method( 'get_meta' )->willReturnMap([
			[ '_protheus_order_id', true, 'PED1000' ],
		]);

		$mapper = $this->createMock( Mapping_Engine::class );
		$mapper->method( 'get_status_mapping' )->willReturn( 'completed' );

		$client = $this->createMock( Protheus_Client::class );
		$client->expects( $this->once() )
			->method( 'post' )
			->with(
				'api/v1/orders/status',
				$this->callback( function( $payload ) {
					$this->assertEquals( 'PED1000', $payload['order_id'] );
					$this->assertEquals( 'completed', $payload['status'] );
					return true;
				})
			)
			->willReturn([
				'success' => true,
				'data' => [],
			]);

		$customer_sync = $this->createMock( Customer_Sync::class );
		$logger = $this->createMock( Logger::class );
		$retry_manager = $this->createMock( Retry_Manager::class );

		$order_sync = new Order_Sync( $client, $customer_sync, $mapper, $logger, $retry_manager );
		$result = $order_sync->sync_order_status( $order, 'completed' );

		$this->assertTrue( $result );
	}

	/**
	 * Test order status sync skips non-synced orders
	 */
	public function test_sync_order_status_skips_non_synced() {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_id' )->willReturn( 1100 );
		$order->method( 'get_meta' )->willReturnMap([
			[ '_protheus_order_id', true, '' ],
		]);

		$client = $this->createMock( Protheus_Client::class );
		$client->expects( $this->never() )->method( 'post' );

		$customer_sync = $this->createMock( Customer_Sync::class );
		$mapper = $this->createMock( Mapping_Engine::class );
		$logger = $this->createMock( Logger::class );
		$retry_manager = $this->createMock( Retry_Manager::class );

		$order_sync = new Order_Sync( $client, $customer_sync, $mapper, $logger, $retry_manager );
		$result = $order_sync->sync_order_status( $order, 'completed' );

		$this->assertFalse( $result );
	}

	/**
	 * Test order status sync schedules retry on failure
	 */
	public function test_sync_order_status_schedules_retry_on_failure() {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_id' )->willReturn( 1200 );
		$order->method( 'get_meta' )->willReturnMap([
			[ '_protheus_order_id', true, 'PED1200' ],
		]);

		$mapper = $this->createMock( Mapping_Engine::class );
		$mapper->method( 'get_status_mapping' )->willReturn( 'processing' );

		$client = $this->createMock( Protheus_Client::class );
		$client->method( 'post' )->willReturn([
			'success' => false,
			'error' => 'Server error',
		]);

		$customer_sync = $this->createMock( Customer_Sync::class );
		$logger = $this->createMock( Logger::class );
		
		$retry_manager = $this->createMock( Retry_Manager::class );
		$retry_manager->expects( $this->once() )
			->method( 'schedule_retry' )
			->with(
				'order_status_sync',
				[ 'order_id' => 1200, 'new_status' => 'processing' ],
				'Server error'
			);

		$order_sync = new Order_Sync( $client, $customer_sync, $mapper, $logger, $retry_manager );
		$result = $order_sync->sync_order_status( $order, 'processing' );

		$this->assertFalse( $result );
	}

	/**
	 * Test should_block_status_change returns true for error status
	 */
	public function test_should_block_status_change_returns_true_for_error() {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_meta' )->willReturnMap([
			[ '_protheus_sync_status', true, 'error' ],
		]);

		$client = $this->createMock( Protheus_Client::class );
		$customer_sync = $this->createMock( Customer_Sync::class );
		$mapper = $this->createMock( Mapping_Engine::class );
		$logger = $this->createMock( Logger::class );
		$retry_manager = $this->createMock( Retry_Manager::class );

		$order_sync = new Order_Sync( $client, $customer_sync, $mapper, $logger, $retry_manager );
		$result = $order_sync->should_block_status_change( $order );

		$this->assertTrue( $result );
	}

	/**
	 * Test should_block_status_change returns false for synced status
	 */
	public function test_should_block_status_change_returns_false_for_synced() {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_meta' )->willReturnMap([
			[ '_protheus_sync_status', true, 'synced' ],
		]);

		$client = $this->createMock( Protheus_Client::class );
		$customer_sync = $this->createMock( Customer_Sync::class );
		$mapper = $this->createMock( Mapping_Engine::class );
		$logger = $this->createMock( Logger::class );
		$retry_manager = $this->createMock( Retry_Manager::class );

		$order_sync = new Order_Sync( $client, $customer_sync, $mapper, $logger, $retry_manager );
		$result = $order_sync->should_block_status_change( $order );

		$this->assertFalse( $result );
	}

	/**
	 * Test get_status_block_message returns appropriate message
	 */
	public function test_get_status_block_message_includes_error_details() {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_meta' )->willReturnMap([
			[ '_protheus_sync_error', true, 'TES not found' ],
			[ '_protheus_business_error', true, 'tes_error' ],
		]);

		$client = $this->createMock( Protheus_Client::class );
		$customer_sync = $this->createMock( Customer_Sync::class );
		$mapper = $this->createMock( Mapping_Engine::class );
		$logger = $this->createMock( Logger::class );
		$retry_manager = $this->createMock( Retry_Manager::class );

		$order_sync = new Order_Sync( $client, $customer_sync, $mapper, $logger, $retry_manager );
		$message = $order_sync->get_status_block_message( $order );

		$this->assertStringContainsString( 'Status change blocked', $message );
		$this->assertStringContainsString( 'tes_error', $message );
		$this->assertStringContainsString( 'TES not found', $message );
	}

	/**
	 * Test cancellation schedules retry on failure
	 */
	public function test_cancel_order_schedules_retry_on_failure() {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_id' )->willReturn( 1300 );
		$order->method( 'get_meta' )->willReturnMap([
			[ '_protheus_order_id', true, 'PED1300' ],
		]);
		$order->expects( $this->once() )->method( 'add_order_note' );

		$client = $this->createMock( Protheus_Client::class );
		$client->method( 'post' )->willReturn([
			'success' => false,
			'error' => 'Network error',
		]);

		$customer_sync = $this->createMock( Customer_Sync::class );
		$mapper = $this->createMock( Mapping_Engine::class );
		$logger = $this->createMock( Logger::class );
		
		$retry_manager = $this->createMock( Retry_Manager::class );
		$retry_manager->expects( $this->once() )
			->method( 'schedule_retry' )
			->with( 'order_cancel', [ 'order_id' => 1300 ], 'Network error' );

		$order_sync = new Order_Sync( $client, $customer_sync, $mapper, $logger, $retry_manager );
		$result = $order_sync->cancel_order( $order );

		$this->assertFalse( $result );
	}

	/**
	 * Test refund schedules retry on failure
	 */
	public function test_refund_order_schedules_retry_on_failure() {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_id' )->willReturn( 1400 );
		$order->method( 'get_meta' )->willReturnMap([
			[ '_protheus_order_id', true, 'PED1400' ],
		]);
		$order->method( 'get_total' )->willReturn( 150.00 );
		$order->expects( $this->once() )->method( 'add_order_note' );

		$client = $this->createMock( Protheus_Client::class );
		$client->method( 'post' )->willReturn([
			'success' => false,
			'error' => 'API unavailable',
		]);

		$customer_sync = $this->createMock( Customer_Sync::class );
		$mapper = $this->createMock( Mapping_Engine::class );
		$logger = $this->createMock( Logger::class );
		
		$retry_manager = $this->createMock( Retry_Manager::class );
		$retry_manager->expects( $this->once() )
			->method( 'schedule_retry' )
			->with( 'order_refund', [ 'order_id' => 1400 ], 'API unavailable' );

		$order_sync = new Order_Sync( $client, $customer_sync, $mapper, $logger, $retry_manager );
		$result = $order_sync->refund_order( $order );

		$this->assertFalse( $result );
	}
}
