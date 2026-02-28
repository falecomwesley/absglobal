<?php
/**
 * Order Sync Field Mapping Tests
 *
 * Tests the field mapping functionality for SC5/SC6 order synchronization.
 *
 * @package ABSLoja\ProtheusConnector\Tests\Unit\Modules
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
 * Class OrderSyncFieldMappingTest
 *
 * Tests the mapping of WooCommerce order fields to Protheus SC5/SC6 format.
 * Validates Requirements 1.2, 1.3, 1.6, 1.7, 1.8
 */
class OrderSyncFieldMappingTest extends TestCase {

	/**
	 * Tear down after each test
	 */
	protected function tearDown(): void {
		Mockery::close();
		parent::tearDown();
	}

	/**
	 * Test that WooCommerce Order ID is included in C5_PEDWOO field
	 *
	 * Validates: Requirements 1.6
	 */
	public function test_woocommerce_order_id_included_in_c5_pedwoo() {
		$order_id = 12345;
		$captured_payload = null;

		// Create mocks
		list( $order_sync, $order, $client ) = $this->create_order_sync_with_mocks( $order_id );

		// Capture the payload sent to API
		$client->shouldReceive( 'post' )
			->once()
			->with( 'api/v1/orders', Mockery::on( function ( $payload ) use ( &$captured_payload, $order_id ) {
				$captured_payload = $payload;
				return isset( $payload['header']['C5_PEDWOO'] ) 
					&& $payload['header']['C5_PEDWOO'] === (string) $order_id;
			} ) )
			->andReturn( array(
				'success' => true,
				'data' => array( 'C5_NUM' => 'ORDER001' ),
			) );

		// Execute sync
		$result = $order_sync->sync_order( $order );

		// Verify
		$this->assertTrue( $result );
		$this->assertNotNull( $captured_payload );
		$this->assertArrayHasKey( 'header', $captured_payload );
		$this->assertArrayHasKey( 'C5_PEDWOO', $captured_payload['header'] );
		$this->assertEquals( (string) $order_id, $captured_payload['header']['C5_PEDWOO'] );
	}

	/**
	 * Test that payment method is mapped using Mapping_Engine
	 *
	 * Validates: Requirements 1.7
	 */
	public function test_payment_method_mapped_using_mapping_engine() {
		$order_id = 12346;
		$payment_method = 'credit_card';
		$expected_payment_condition = '004';
		$captured_payload = null;

		// Create mocks
		list( $order_sync, $order, $client, $mapper ) = $this->create_order_sync_with_mocks( $order_id );

		// Mock payment method
		$order->shouldReceive( 'get_payment_method' )->andReturn( $payment_method );

		// Mock mapper to return specific payment condition
		$mapper->shouldReceive( 'get_payment_mapping' )
			->once()
			->with( $payment_method )
			->andReturn( $expected_payment_condition );

		// Capture the payload
		$client->shouldReceive( 'post' )
			->once()
			->with( 'api/v1/orders', Mockery::on( function ( $payload ) use ( &$captured_payload ) {
				$captured_payload = $payload;
				return true;
			} ) )
			->andReturn( array(
				'success' => true,
				'data' => array( 'C5_NUM' => 'ORDER002' ),
			) );

		// Execute sync
		$result = $order_sync->sync_order( $order );

		// Verify
		$this->assertTrue( $result );
		$this->assertArrayHasKey( 'C5_CONDPAG', $captured_payload['header'] );
		$this->assertEquals( $expected_payment_condition, $captured_payload['header']['C5_CONDPAG'] );
	}

	/**
	 * Test that TES is determined by customer billing state
	 *
	 * Validates: Requirements 1.8
	 */
	public function test_tes_determined_by_billing_state() {
		$order_id = 12347;
		$billing_state = 'SP';
		$expected_tes = '501';
		$captured_payload = null;

		// Create mocks
		list( $order_sync, $order, $client, $mapper ) = $this->create_order_sync_with_mocks( $order_id );

		// Mock billing state
		$order->shouldReceive( 'get_billing_state' )->andReturn( $billing_state );

		// Mock mapper to return TES based on state
		$mapper->shouldReceive( 'get_tes_by_state' )
			->once()
			->with( $billing_state )
			->andReturn( $expected_tes );

		// Mock order with one item
		$product = Mockery::mock( 'WC_Product' );
		$product->shouldReceive( 'get_sku' )->andReturn( 'PROD001' );

		$item = Mockery::mock( 'WC_Order_Item_Product' );
		$item->shouldReceive( 'get_product' )->andReturn( $product );
		$item->shouldReceive( 'get_quantity' )->andReturn( 2 );
		$item->shouldReceive( 'get_total' )->andReturn( 100.00 );

		$order->shouldReceive( 'get_items' )->andReturn( array( $item ) );

		// Capture the payload
		$client->shouldReceive( 'post' )
			->once()
			->with( 'api/v1/orders', Mockery::on( function ( $payload ) use ( &$captured_payload ) {
				$captured_payload = $payload;
				return true;
			} ) )
			->andReturn( array(
				'success' => true,
				'data' => array( 'C5_NUM' => 'ORDER003' ),
			) );

		// Execute sync
		$result = $order_sync->sync_order( $order );

		// Verify TES in items
		$this->assertTrue( $result );
		$this->assertArrayHasKey( 'items', $captured_payload );
		$this->assertCount( 1, $captured_payload['items'] );
		$this->assertArrayHasKey( 'C6_TES', $captured_payload['items'][0] );
		$this->assertEquals( $expected_tes, $captured_payload['items'][0]['C6_TES'] );
	}

	/**
	 * Test that SC5 header fields are correctly mapped
	 *
	 * Validates: Requirements 1.2
	 */
	public function test_sc5_header_fields_mapped_correctly() {
		$order_id = 12348;
		$customer_code = 'CUST001';
		$captured_payload = null;

		// Create mocks
		list( $order_sync, $order, $client, $mapper ) = $this->create_order_sync_with_mocks( $order_id, $customer_code );

		// Mock order data
		$order->shouldReceive( 'get_date_created' )->andReturn( new \WC_DateTime( '2024-01-15 10:30:00' ) );
		$order->shouldReceive( 'get_shipping_total' )->andReturn( 15.50 );
		$order->shouldReceive( 'get_discount_total' )->andReturn( 10.00 );

		// Capture the payload
		$client->shouldReceive( 'post' )
			->once()
			->with( 'api/v1/orders', Mockery::on( function ( $payload ) use ( &$captured_payload ) {
				$captured_payload = $payload;
				return true;
			} ) )
			->andReturn( array(
				'success' => true,
				'data' => array( 'C5_NUM' => 'ORDER004' ),
			) );

		// Execute sync
		$result = $order_sync->sync_order( $order );

		// Verify SC5 header fields
		$this->assertTrue( $result );
		$this->assertArrayHasKey( 'header', $captured_payload );
		
		$header = $captured_payload['header'];
		$this->assertEquals( '01', $header['C5_FILIAL'] );
		$this->assertEquals( 'N', $header['C5_TIPO'] );
		$this->assertEquals( $customer_code, $header['C5_CLIENTE'] );
		$this->assertEquals( '01', $header['C5_LOJACLI'] );
		$this->assertEquals( '001', $header['C5_TABELA'] );
		$this->assertEquals( '000001', $header['C5_VEND1'] );
		$this->assertEquals( (string) $order_id, $header['C5_PEDWOO'] );
		$this->assertEquals( '20240115', $header['C5_EMISSAO'] );
		$this->assertEquals( 15.50, $header['C5_FRETE'] );
		$this->assertEquals( 10.00, $header['C5_DESCONT'] );
	}

	/**
	 * Test that SC6 line items are correctly mapped
	 *
	 * Validates: Requirements 1.3
	 */
	public function test_sc6_line_items_mapped_correctly() {
		$order_id = 12349;
		$captured_payload = null;

		// Create mocks
		list( $order_sync, $order, $client, $mapper ) = $this->create_order_sync_with_mocks( $order_id );

		// Mock multiple order items
		$product1 = Mockery::mock( 'WC_Product' );
		$product1->shouldReceive( 'get_sku' )->andReturn( 'PROD001' );

		$item1 = Mockery::mock( 'WC_Order_Item_Product' );
		$item1->shouldReceive( 'get_product' )->andReturn( $product1 );
		$item1->shouldReceive( 'get_quantity' )->andReturn( 2 );
		$item1->shouldReceive( 'get_total' )->andReturn( 100.00 );

		$product2 = Mockery::mock( 'WC_Product' );
		$product2->shouldReceive( 'get_sku' )->andReturn( 'PROD002' );

		$item2 = Mockery::mock( 'WC_Order_Item_Product' );
		$item2->shouldReceive( 'get_product' )->andReturn( $product2 );
		$item2->shouldReceive( 'get_quantity' )->andReturn( 1 );
		$item2->shouldReceive( 'get_total' )->andReturn( 50.00 );

		$order->shouldReceive( 'get_items' )->andReturn( array( $item1, $item2 ) );

		// Capture the payload
		$client->shouldReceive( 'post' )
			->once()
			->with( 'api/v1/orders', Mockery::on( function ( $payload ) use ( &$captured_payload ) {
				$captured_payload = $payload;
				return true;
			} ) )
			->andReturn( array(
				'success' => true,
				'data' => array( 'C5_NUM' => 'ORDER005' ),
			) );

		// Execute sync
		$result = $order_sync->sync_order( $order );

		// Verify SC6 items
		$this->assertTrue( $result );
		$this->assertArrayHasKey( 'items', $captured_payload );
		$this->assertCount( 2, $captured_payload['items'] );

		// Verify first item
		$sc6_item1 = $captured_payload['items'][0];
		$this->assertEquals( '01', $sc6_item1['C6_FILIAL'] );
		$this->assertEquals( '01', $sc6_item1['C6_ITEM'] );
		$this->assertEquals( 'PROD001', $sc6_item1['C6_PRODUTO'] );
		$this->assertEquals( 2.0, $sc6_item1['C6_QTDVEN'] );
		$this->assertEquals( 50.0, $sc6_item1['C6_PRCVEN'] ); // 100 / 2
		$this->assertEquals( 100.0, $sc6_item1['C6_VALOR'] );
		$this->assertEquals( '501', $sc6_item1['C6_TES'] );

		// Verify second item
		$sc6_item2 = $captured_payload['items'][1];
		$this->assertEquals( '01', $sc6_item2['C6_FILIAL'] );
		$this->assertEquals( '02', $sc6_item2['C6_ITEM'] );
		$this->assertEquals( 'PROD002', $sc6_item2['C6_PRODUTO'] );
		$this->assertEquals( 1.0, $sc6_item2['C6_QTDVEN'] );
		$this->assertEquals( 50.0, $sc6_item2['C6_PRCVEN'] ); // 50 / 1
		$this->assertEquals( 50.0, $sc6_item2['C6_VALOR'] );
		$this->assertEquals( '501', $sc6_item2['C6_TES'] );
	}

	/**
	 * Test that shipping and discount totals are calculated correctly
	 *
	 * Validates: Requirements 1.2
	 */
	public function test_shipping_and_discount_calculated() {
		$order_id = 12350;
		$captured_payload = null;

		// Create mocks
		list( $order_sync, $order, $client ) = $this->create_order_sync_with_mocks( $order_id );

		// Mock shipping and discount
		$order->shouldReceive( 'get_shipping_total' )->andReturn( 25.75 );
		$order->shouldReceive( 'get_discount_total' )->andReturn( 12.50 );

		// Capture the payload
		$client->shouldReceive( 'post' )
			->once()
			->with( 'api/v1/orders', Mockery::on( function ( $payload ) use ( &$captured_payload ) {
				$captured_payload = $payload;
				return true;
			} ) )
			->andReturn( array(
				'success' => true,
				'data' => array( 'C5_NUM' => 'ORDER006' ),
			) );

		// Execute sync
		$result = $order_sync->sync_order( $order );

		// Verify
		$this->assertTrue( $result );
		$this->assertEquals( 25.75, $captured_payload['header']['C5_FRETE'] );
		$this->assertEquals( 12.50, $captured_payload['header']['C5_DESCONT'] );
	}

	/**
	 * Test that items without SKU are skipped
	 *
	 * Validates: Requirements 1.3
	 */
	public function test_items_without_sku_are_skipped() {
		$order_id = 12351;
		$captured_payload = null;

		// Create mocks
		list( $order_sync, $order, $client ) = $this->create_order_sync_with_mocks( $order_id );

		// Mock item with product but no SKU
		$product1 = Mockery::mock( 'WC_Product' );
		$product1->shouldReceive( 'get_sku' )->andReturn( '' ); // Empty SKU

		$item1 = Mockery::mock( 'WC_Order_Item_Product' );
		$item1->shouldReceive( 'get_product' )->andReturn( $product1 );
		$item1->shouldReceive( 'get_quantity' )->andReturn( 1 );
		$item1->shouldReceive( 'get_total' )->andReturn( 50.00 );

		// Mock item with valid SKU
		$product2 = Mockery::mock( 'WC_Product' );
		$product2->shouldReceive( 'get_sku' )->andReturn( 'PROD002' );

		$item2 = Mockery::mock( 'WC_Order_Item_Product' );
		$item2->shouldReceive( 'get_product' )->andReturn( $product2 );
		$item2->shouldReceive( 'get_quantity' )->andReturn( 2 );
		$item2->shouldReceive( 'get_total' )->andReturn( 100.00 );

		$order->shouldReceive( 'get_items' )->andReturn( array( $item1, $item2 ) );

		// Capture the payload
		$client->shouldReceive( 'post' )
			->once()
			->with( 'api/v1/orders', Mockery::on( function ( $payload ) use ( &$captured_payload ) {
				$captured_payload = $payload;
				return true;
			} ) )
			->andReturn( array(
				'success' => true,
				'data' => array( 'C5_NUM' => 'ORDER007' ),
			) );

		// Execute sync
		$result = $order_sync->sync_order( $order );

		// Verify only one item (with SKU) is included
		$this->assertTrue( $result );
		$this->assertArrayHasKey( 'items', $captured_payload );
		$this->assertCount( 1, $captured_payload['items'] );
		$this->assertEquals( 'PROD002', $captured_payload['items'][0]['C6_PRODUTO'] );
	}

	/**
	 * Test that items without product are skipped
	 *
	 * Validates: Requirements 1.3
	 */
	public function test_items_without_product_are_skipped() {
		$order_id = 12352;
		$captured_payload = null;

		// Create mocks
		list( $order_sync, $order, $client ) = $this->create_order_sync_with_mocks( $order_id );

		// Mock item without product
		$item1 = Mockery::mock( 'WC_Order_Item_Product' );
		$item1->shouldReceive( 'get_product' )->andReturn( null ); // No product

		// Mock item with valid product
		$product2 = Mockery::mock( 'WC_Product' );
		$product2->shouldReceive( 'get_sku' )->andReturn( 'PROD003' );

		$item2 = Mockery::mock( 'WC_Order_Item_Product' );
		$item2->shouldReceive( 'get_product' )->andReturn( $product2 );
		$item2->shouldReceive( 'get_quantity' )->andReturn( 1 );
		$item2->shouldReceive( 'get_total' )->andReturn( 75.00 );

		$order->shouldReceive( 'get_items' )->andReturn( array( $item1, $item2 ) );

		// Capture the payload
		$client->shouldReceive( 'post' )
			->once()
			->with( 'api/v1/orders', Mockery::on( function ( $payload ) use ( &$captured_payload ) {
				$captured_payload = $payload;
				return true;
			} ) )
			->andReturn( array(
				'success' => true,
				'data' => array( 'C5_NUM' => 'ORDER008' ),
			) );

		// Execute sync
		$result = $order_sync->sync_order( $order );

		// Verify only one item (with product) is included
		$this->assertTrue( $result );
		$this->assertArrayHasKey( 'items', $captured_payload );
		$this->assertCount( 1, $captured_payload['items'] );
		$this->assertEquals( 'PROD003', $captured_payload['items'][0]['C6_PRODUTO'] );
	}

	/**
	 * Helper method to create Order_Sync instance with mocked dependencies
	 *
	 * @param int    $order_id      Order ID.
	 * @param string $customer_code Customer code (default: 'CUST001').
	 * @return array Array containing [order_sync, order, client, mapper, logger, retry_manager, customer_sync]
	 */
	private function create_order_sync_with_mocks( int $order_id, string $customer_code = 'CUST001' ): array {
		// Mock dependencies
		$client = Mockery::mock( Protheus_Client::class );
		$customer_sync = Mockery::mock( Customer_Sync::class );
		$mapper = Mockery::mock( Mapping_Engine::class );
		$logger = Mockery::mock( Logger::class );
		$retry_manager = Mockery::mock( Retry_Manager::class );

		// Mock WooCommerce order
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_id' )->andReturn( $order_id );
		$order->shouldReceive( 'get_meta' )
			->with( '_protheus_order_id', true )
			->andReturn( '' ); // Not synced yet

		// Mock customer sync
		$customer_sync->shouldReceive( 'ensure_customer_exists' )
			->with( $order )
			->andReturn( $customer_code );

		// Mock order metadata operations
		$order->shouldReceive( 'update_meta_data' )->andReturn( true );
		$order->shouldReceive( 'save' )->andReturn( true );
		$order->shouldReceive( 'add_order_note' )->andReturn( true );
		$order->shouldReceive( 'delete_meta_data' )->andReturn( true );

		// Mock default order data
		$order->shouldReceive( 'get_payment_method' )->andReturn( 'bacs' );
		$order->shouldReceive( 'get_billing_state' )->andReturn( 'SP' );
		$order->shouldReceive( 'get_date_created' )->andReturn( new \WC_DateTime( '2024-01-15' ) );
		$order->shouldReceive( 'get_shipping_total' )->andReturn( 10.00 );
		$order->shouldReceive( 'get_discount_total' )->andReturn( 5.00 );
		$order->shouldReceive( 'get_items' )->andReturn( array() );

		// Mock mapper with default mappings
		$mapper->shouldReceive( 'get_order_mapping' )->andReturn( array(
			'SC5' => array(
				'C5_FILIAL' => '01',
				'C5_TIPO' => 'N',
				'C5_LOJACLI' => '01',
				'C5_TABELA' => '001',
				'C5_VEND1' => '000001',
			),
			'SC6' => array(
				'C6_FILIAL' => '01',
			),
		) );
		$mapper->shouldReceive( 'get_payment_mapping' )->andReturn( '001' );
		$mapper->shouldReceive( 'get_tes_by_state' )->andReturn( '501' );

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

		return array( $order_sync, $order, $client, $mapper, $logger, $retry_manager, $customer_sync );
	}
}
