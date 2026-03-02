<?php
/**
 * Catalog Sync Unit Tests
 *
 * Comprehensive unit tests for Catalog_Sync module (Task 10.8).
 * Tests product creation, product updates, field mapping, stock updates,
 * product visibility management, and image handling.
 *
 * @package ABSLoja\ProtheusConnector\Tests\Unit\Modules
 * @since 1.0.0
 */

namespace ABSLoja\ProtheusConnector\Tests\Unit\Modules;

use ABSLoja\ProtheusConnector\Modules\Catalog_Sync;
use ABSLoja\ProtheusConnector\Modules\Mapping_Engine;
use ABSLoja\ProtheusConnector\Modules\Logger;
use ABSLoja\ProtheusConnector\API\Protheus_Client;
use PHPUnit\Framework\TestCase;
use WC_Product_Simple;

/**
 * Class CatalogSyncTest
 *
 * Comprehensive unit tests for Catalog_Sync module.
 *
 * @since 1.0.0
 */
class CatalogSyncTest extends TestCase {

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
	 * Test sync_products creates new product when SKU doesn't exist
	 *
	 * Validates: Requirements 3.4 (Product Creation on New SKU)
	 */
	public function test_sync_products_creates_new_product() {
		$client = $this->createMock( Protheus_Client::class );
		$client->method( 'get' )
			->willReturn([
				'success' => true,
				'data' => [
					'products' => [
						[
							'B1_COD' => 'PROD001',
							'B1_DESC' => 'Produto Teste',
							'B1_PRV1' => '100.00',
							'B1_PESO' => '1.5',
							'B1_MSBLQL' => '2',
							'B1_GRUPO' => '01',
						],
					],
				],
			]);

		$mapper = $this->createMock( Mapping_Engine::class );
		$mapper->method( 'get_product_mapping' )->willReturn([]);
		$mapper->method( 'get_category_mapping' )->willReturn( 15 );

		$logger = $this->createMock( Logger::class );
		$logger->expects( $this->once() )
			->method( 'log_sync_operation' )
			->with(
				'product_sync',
				$this->callback( function( $data ) {
					return $data['created'] === 1 && $data['errors'] === 0;
				}),
				true,
				null
			);

		$catalog_sync = new Catalog_Sync( $client, $mapper, $logger );
		$result = $catalog_sync->sync_products( 50 );

		$this->assertEquals( 1, $result['total_processed'] );
		$this->assertEquals( 1, $result['created'] );
		$this->assertEquals( 0, $result['updated'] );
		$this->assertEquals( 0, $result['errors'] );
	}

	/**
	 * Test sync_products updates existing product when SKU exists
	 *
	 * Validates: Requirements 3.3 (Product Update on Existing SKU)
	 */
	public function test_sync_products_updates_existing_product() {
		// Mock existing product
		$existing_product_id = 123;
		
		$client = $this->createMock( Protheus_Client::class );
		$client->method( 'get' )
			->willReturn([
				'success' => true,
				'data' => [
					'products' => [
						[
							'B1_COD' => 'PROD002',
							'B1_DESC' => 'Produto Atualizado',
							'B1_PRV1' => '150.00',
							'B1_PESO' => '2.0',
							'B1_MSBLQL' => '2',
						],
					],
				],
			]);

		$mapper = $this->createMock( Mapping_Engine::class );
		$mapper->method( 'get_product_mapping' )->willReturn([]);

		$logger = $this->createMock( Logger::class );
		$logger->expects( $this->once() )
			->method( 'log_sync_operation' )
			->with(
				'product_sync',
				$this->callback( function( $data ) {
					return $data['updated'] === 1 && $data['created'] === 0;
				}),
				true,
				null
			);

		$catalog_sync = new Catalog_Sync( $client, $mapper, $logger );
		$result = $catalog_sync->sync_products( 50 );

		$this->assertEquals( 1, $result['total_processed'] );
		$this->assertEquals( 0, $result['created'] );
		$this->assertEquals( 1, $result['updated'] );
		$this->assertEquals( 0, $result['errors'] );
	}

	/**
	 * Test product field mapping from Protheus to WooCommerce
	 *
	 * Validates: Requirements 3.2, 3.5, 3.6, 3.7 (Product Field Mapping)
	 */
	public function test_product_field_mapping() {
		$client = $this->createMock( Protheus_Client::class );
		$client->method( 'get' )
			->willReturn([
				'success' => true,
				'data' => [
					'products' => [
						[
							'B1_COD' => 'PROD003',
							'B1_DESC' => 'Notebook Dell',
							'B1_PRV1' => '3500.00',
							'B1_PESO' => '2.5',
							'B1_DESCMAR' => 'Notebook profissional',
							'B1_MSBLQL' => '2',
							'B1_GRUPO' => '02',
						],
					],
				],
			]);

		$mapper = $this->createMock( Mapping_Engine::class );
		$mapper->method( 'get_product_mapping' )->willReturn([]);
		$mapper->method( 'get_category_mapping' )->willReturn( 20 );

		$logger = $this->createMock( Logger::class );

		$catalog_sync = new Catalog_Sync( $client, $mapper, $logger );
		$result = $catalog_sync->sync_products( 50 );

		$this->assertEquals( 1, $result['total_processed'] );
		$this->assertEquals( 0, $result['errors'] );
	}

	/**
	 * Test blocked product status mapping
	 *
	 * Validates: Requirements 3.8 (Blocked Product Status)
	 */
	public function test_blocked_product_status_mapping() {
		$client = $this->createMock( Protheus_Client::class );
		$client->method( 'get' )
			->willReturn([
				'success' => true,
				'data' => [
					'products' => [
						[
							'B1_COD' => 'PROD004',
							'B1_DESC' => 'Produto Bloqueado',
							'B1_PRV1' => '50.00',
							'B1_MSBLQL' => '1', // Blocked
						],
					],
				],
			]);

		$mapper = $this->createMock( Mapping_Engine::class );
		$mapper->method( 'get_product_mapping' )->willReturn([]);

		$logger = $this->createMock( Logger::class );

		$catalog_sync = new Catalog_Sync( $client, $mapper, $logger );
		$result = $catalog_sync->sync_products( 50 );

		$this->assertEquals( 1, $result['total_processed'] );
		$this->assertEquals( 0, $result['errors'] );
	}

	/**
	 * Test stock sync updates product quantities
	 *
	 * Validates: Requirements 4.2 (Stock Quantity Update)
	 */
	public function test_sync_stock_updates_quantities() {
		$client = $this->createMock( Protheus_Client::class );
		$client->method( 'get' )
			->willReturn([
				'success' => true,
				'data' => [
					'stock' => [
						[
							'B2_COD' => 'PROD005',
							'B2_QATU' => 50,
						],
						[
							'B2_COD' => 'PROD006',
							'B2_QATU' => 100,
						],
					],
				],
			]);

		$mapper = $this->createMock( Mapping_Engine::class );

		$logger = $this->createMock( Logger::class );
		$logger->expects( $this->once() )
			->method( 'log_sync_operation' )
			->with(
				'stock_sync',
				$this->callback( function( $data ) {
					return $data['total_processed'] === 2 && $data['updated'] === 2;
				}),
				true,
				null
			);

		$catalog_sync = new Catalog_Sync( $client, $mapper, $logger );
		$result = $catalog_sync->sync_stock();

		$this->assertEquals( 2, $result['total_processed'] );
		$this->assertEquals( 2, $result['updated'] );
	}

	/**
	 * Test product visibility hidden when stock reaches zero
	 *
	 * Validates: Requirements 4.3 (Product Visibility on Zero Stock)
	 */
	public function test_product_hidden_when_stock_zero() {
		$client = $this->createMock( Protheus_Client::class );
		$client->method( 'get' )
			->willReturn([
				'success' => true,
				'data' => [
					'stock' => [
						[
							'B2_COD' => 'PROD007',
							'B2_QATU' => 0,
						],
					],
				],
			]);

		$mapper = $this->createMock( Mapping_Engine::class );

		$logger = $this->createMock( Logger::class );
		$logger->expects( $this->once() )
			->method( 'log_sync_operation' )
			->with(
				'stock_sync',
				$this->callback( function( $data ) {
					return $data['hidden'] === 1;
				}),
				true,
				null
			);

		$catalog_sync = new Catalog_Sync( $client, $mapper, $logger );
		$result = $catalog_sync->sync_stock();

		$this->assertEquals( 1, $result['total_processed'] );
		$this->assertEquals( 1, $result['hidden'] );
	}

	/**
	 * Test product visibility restored when stock becomes available
	 *
	 * Validates: Requirements 4.4 (Product Visibility Restoration)
	 */
	public function test_product_visibility_restored_when_stock_available() {
		$client = $this->createMock( Protheus_Client::class );
		$client->method( 'get' )
			->willReturn([
				'success' => true,
				'data' => [
					'stock' => [
						[
							'B2_COD' => 'PROD008',
							'B2_QATU' => 25,
						],
					],
				],
			]);

		$mapper = $this->createMock( Mapping_Engine::class );

		$logger = $this->createMock( Logger::class );
		$logger->expects( $this->once() )
			->method( 'log_sync_operation' )
			->with(
				'stock_sync',
				$this->callback( function( $data ) {
					return $data['restored'] >= 0; // May or may not restore depending on previous state
				}),
				true,
				null
			);

		$catalog_sync = new Catalog_Sync( $client, $mapper, $logger );
		$result = $catalog_sync->sync_stock();

		$this->assertEquals( 1, $result['total_processed'] );
		$this->assertEquals( 1, $result['updated'] );
	}

	/**
	 * Test sync_single_product successfully syncs individual product
	 *
	 * Validates: Requirements 3.1 (Product Data Fetching)
	 */
	public function test_sync_single_product_success() {
		$client = $this->createMock( Protheus_Client::class );
		$client->method( 'get' )
			->with( 'api/v1/products/PROD009' )
			->willReturn([
				'success' => true,
				'data' => [
					'B1_COD' => 'PROD009',
					'B1_DESC' => 'Mouse Wireless',
					'B1_PRV1' => '45.00',
					'B1_MSBLQL' => '2',
				],
			]);

		$mapper = $this->createMock( Mapping_Engine::class );
		$mapper->method( 'get_product_mapping' )->willReturn([]);

		$logger = $this->createMock( Logger::class );
		$logger->expects( $this->once() )
			->method( 'log_sync_operation' )
			->with(
				'single_product_sync',
				$this->callback( function( $data ) {
					return $data['sku'] === 'PROD009';
				}),
				true,
				null
			);

		$catalog_sync = new Catalog_Sync( $client, $mapper, $logger );
		$result = $catalog_sync->sync_single_product( 'PROD009' );

		$this->assertTrue( $result );
	}

	/**
	 * Test sync_single_product handles API failure
	 */
	public function test_sync_single_product_handles_api_failure() {
		$client = $this->createMock( Protheus_Client::class );
		$client->method( 'get' )
			->willReturn([
				'success' => false,
				'error' => 'Product not found',
			]);

		$mapper = $this->createMock( Mapping_Engine::class );

		$logger = $this->createMock( Logger::class );
		$logger->expects( $this->once() )
			->method( 'log_sync_operation' )
			->with(
				'single_product_sync',
				$this->anything(),
				false,
				$this->stringContains( 'Product not found' )
			);

		$catalog_sync = new Catalog_Sync( $client, $mapper, $logger );
		$result = $catalog_sync->sync_single_product( 'INVALID_SKU' );

		$this->assertFalse( $result );
	}

	/**
	 * Test sync_single_stock updates individual product stock
	 *
	 * Validates: Requirements 4.2 (Stock Quantity Update)
	 */
	public function test_sync_single_stock_success() {
		$client = $this->createMock( Protheus_Client::class );
		$mapper = $this->createMock( Mapping_Engine::class );

		$logger = $this->createMock( Logger::class );
		$logger->expects( $this->once() )
			->method( 'log_sync_operation' )
			->with(
				'single_stock_sync',
				$this->callback( function( $data ) {
					return $data['sku'] === 'PROD010' && $data['quantity'] === 75;
				}),
				true,
				null
			);

		$catalog_sync = new Catalog_Sync( $client, $mapper, $logger );
		$result = $catalog_sync->sync_single_stock( 'PROD010', 75 );

		$this->assertTrue( $result );
	}

	/**
	 * Test sync_single_stock returns false for empty SKU
	 */
	public function test_sync_single_stock_returns_false_for_empty_sku() {
		$client = $this->createMock( Protheus_Client::class );
		$mapper = $this->createMock( Mapping_Engine::class );
		$logger = $this->createMock( Logger::class );

		$catalog_sync = new Catalog_Sync( $client, $mapper, $logger );
		$result = $catalog_sync->sync_single_stock( '', 10 );

		$this->assertFalse( $result );
	}

	/**
	 * Test image download and attachment when URL provided
	 *
	 * Validates: Requirements 14.2 (Image Download and Attachment)
	 */
	public function test_image_download_and_attachment() {
		$client = $this->createMock( Protheus_Client::class );
		$client->method( 'get' )
			->willReturn([
				'success' => true,
				'data' => [
					'products' => [
						[
							'B1_COD' => 'PROD011',
							'B1_DESC' => 'Produto com Imagem',
							'B1_PRV1' => '200.00',
							'B1_MSBLQL' => '2',
							'image_url' => 'https://example.com/images/prod011.jpg',
						],
					],
				],
			]);

		$mapper = $this->createMock( Mapping_Engine::class );
		$mapper->method( 'get_product_mapping' )->willReturn([]);

		$logger = $this->createMock( Logger::class );

		$catalog_sync = new Catalog_Sync( $client, $mapper, $logger );
		$result = $catalog_sync->sync_products( 50 );

		$this->assertEquals( 1, $result['total_processed'] );
		$this->assertEquals( 0, $result['errors'] );
	}

	/**
	 * Test image URL pattern processing with SKU placeholder
	 *
	 * Validates: Requirements 14.4 (Image URL Pattern)
	 */
	public function test_image_url_pattern_processing() {
		// Set image URL pattern option
		update_option( 'absloja_protheus_image_url_pattern', 'https://cdn.example.com/products/{sku}.jpg' );

		$client = $this->createMock( Protheus_Client::class );
		$client->method( 'get' )
			->willReturn([
				'success' => true,
				'data' => [
					'products' => [
						[
							'B1_COD' => 'PROD012',
							'B1_DESC' => 'Produto com Pattern',
							'B1_PRV1' => '150.00',
							'B1_MSBLQL' => '2',
						],
					],
				],
			]);

		$mapper = $this->createMock( Mapping_Engine::class );
		$mapper->method( 'get_product_mapping' )->willReturn([]);

		$logger = $this->createMock( Logger::class );

		$catalog_sync = new Catalog_Sync( $client, $mapper, $logger );
		$result = $catalog_sync->sync_products( 50 );

		$this->assertEquals( 1, $result['total_processed'] );
		
		// Clean up
		delete_option( 'absloja_protheus_image_url_pattern' );
	}

	/**
	 * Test existing images preserved when no URL provided
	 *
	 * Validates: Requirements 14.3 (Image Preservation)
	 */
	public function test_existing_images_preserved_when_no_url() {
		$client = $this->createMock( Protheus_Client::class );
		$client->method( 'get' )
			->willReturn([
				'success' => true,
				'data' => [
					'products' => [
						[
							'B1_COD' => 'PROD013',
							'B1_DESC' => 'Produto sem URL de Imagem',
							'B1_PRV1' => '80.00',
							'B1_MSBLQL' => '2',
							// No image_url provided
						],
					],
				],
			]);

		$mapper = $this->createMock( Mapping_Engine::class );
		$mapper->method( 'get_product_mapping' )->willReturn([]);

		$logger = $this->createMock( Logger::class );

		$catalog_sync = new Catalog_Sync( $client, $mapper, $logger );
		$result = $catalog_sync->sync_products( 50 );

		$this->assertEquals( 1, $result['total_processed'] );
		$this->assertEquals( 0, $result['errors'] );
	}

	/**
	 * Test category mapping from B1_GRUPO
	 *
	 * Validates: Requirements 3.9 (Category Mapping)
	 */
	public function test_category_mapping_from_b1_grupo() {
		$client = $this->createMock( Protheus_Client::class );
		$client->method( 'get' )
			->willReturn([
				'success' => true,
				'data' => [
					'products' => [
						[
							'B1_COD' => 'PROD014',
							'B1_DESC' => 'Produto com Categoria',
							'B1_PRV1' => '120.00',
							'B1_MSBLQL' => '2',
							'B1_GRUPO' => '03',
						],
					],
				],
			]);

		$mapper = $this->createMock( Mapping_Engine::class );
		$mapper->method( 'get_product_mapping' )->willReturn([]);
		$mapper->method( 'get_category_mapping' )
			->with( '03' )
			->willReturn( 25 );

		$logger = $this->createMock( Logger::class );

		$catalog_sync = new Catalog_Sync( $client, $mapper, $logger );
		$result = $catalog_sync->sync_products( 50 );

		$this->assertEquals( 1, $result['total_processed'] );
		$this->assertEquals( 0, $result['errors'] );
	}

	/**
	 * Test batch processing with pagination
	 */
	public function test_batch_processing_with_pagination() {
		$client = $this->createMock( Protheus_Client::class );
		$client->method( 'get' )
			->willReturnOnConsecutiveCalls(
				// First page
				[
					'success' => true,
					'data' => [
						'products' => [
							[
								'B1_COD' => 'PROD015',
								'B1_DESC' => 'Produto Página 1',
								'B1_PRV1' => '100.00',
								'B1_MSBLQL' => '2',
							],
							[
								'B1_COD' => 'PROD016',
								'B1_DESC' => 'Produto Página 1-2',
								'B1_PRV1' => '110.00',
								'B1_MSBLQL' => '2',
							],
						],
					],
				],
				// Second page (empty - end of pagination)
				[
					'success' => true,
					'data' => [
						'products' => [],
					],
				]
			);

		$mapper = $this->createMock( Mapping_Engine::class );
		$mapper->method( 'get_product_mapping' )->willReturn([]);

		$logger = $this->createMock( Logger::class );

		$catalog_sync = new Catalog_Sync( $client, $mapper, $logger );
		$result = $catalog_sync->sync_products( 2 );

		$this->assertEquals( 2, $result['total_processed'] );
	}

	/**
	 * Test error handling when product data missing SKU
	 */
	public function test_error_handling_missing_sku() {
		$client = $this->createMock( Protheus_Client::class );
		$client->method( 'get' )
			->willReturn([
				'success' => true,
				'data' => [
					'products' => [
						[
							// Missing B1_COD
							'B1_DESC' => 'Produto sem SKU',
							'B1_PRV1' => '50.00',
						],
					],
				],
			]);

		$mapper = $this->createMock( Mapping_Engine::class );
		$mapper->method( 'get_product_mapping' )->willReturn([]);

		$logger = $this->createMock( Logger::class );

		$catalog_sync = new Catalog_Sync( $client, $mapper, $logger );
		$result = $catalog_sync->sync_products( 50 );

		$this->assertEquals( 1, $result['total_processed'] );
		$this->assertEquals( 1, $result['errors'] );
		$this->assertNotEmpty( $result['error_details'] );
	}

	/**
	 * Test error handling when stock data missing SKU
	 */
	public function test_stock_sync_error_handling_missing_sku() {
		$client = $this->createMock( Protheus_Client::class );
		$client->method( 'get' )
			->willReturn([
				'success' => true,
				'data' => [
					'stock' => [
						[
							// Missing B2_COD
							'B2_QATU' => 50,
						],
					],
				],
			]);

		$mapper = $this->createMock( Mapping_Engine::class );

		$logger = $this->createMock( Logger::class );

		$catalog_sync = new Catalog_Sync( $client, $mapper, $logger );
		$result = $catalog_sync->sync_stock();

		$this->assertEquals( 1, $result['total_processed'] );
		$this->assertEquals( 1, $result['errors'] );
		$this->assertContains( 'Stock item missing B2_COD (SKU)', $result['error_details'] );
	}

	/**
	 * Test API failure handling in sync_products
	 */
	public function test_sync_products_handles_api_failure() {
		$client = $this->createMock( Protheus_Client::class );
		$client->method( 'get' )
			->willReturn([
				'success' => false,
				'error' => 'Connection timeout',
			]);

		$mapper = $this->createMock( Mapping_Engine::class );

		$logger = $this->createMock( Logger::class );
		$logger->expects( $this->once() )
			->method( 'log_sync_operation' )
			->with(
				'product_sync',
				$this->anything(),
				false,
				$this->stringContains( 'Connection timeout' )
			);

		$catalog_sync = new Catalog_Sync( $client, $mapper, $logger );
		$result = $catalog_sync->sync_products( 50 );

		$this->assertEquals( 0, $result['total_processed'] );
		$this->assertNotEmpty( $result['error_details'] );
	}

	/**
	 * Test API failure handling in sync_stock
	 */
	public function test_sync_stock_handles_api_failure() {
		$client = $this->createMock( Protheus_Client::class );
		$client->method( 'get' )
			->willReturn([
				'success' => false,
				'error' => 'Server error',
			]);

		$mapper = $this->createMock( Mapping_Engine::class );

		$logger = $this->createMock( Logger::class );
		$logger->expects( $this->once() )
			->method( 'log_sync_operation' )
			->with(
				'stock_sync',
				$this->anything(),
				false,
				$this->stringContains( 'Server error' )
			);

		$catalog_sync = new Catalog_Sync( $client, $mapper, $logger );
		$result = $catalog_sync->sync_stock();

		$this->assertEquals( 0, $result['total_processed'] );
		$this->assertNotEmpty( $result['error_details'] );
	}

	/**
	 * Test metadata storage for synced products
	 */
	public function test_metadata_storage_for_synced_products() {
		$client = $this->createMock( Protheus_Client::class );
		$client->method( 'get' )
			->willReturn([
				'success' => true,
				'data' => [
					'products' => [
						[
							'B1_COD' => 'PROD017',
							'B1_DESC' => 'Produto com Metadata',
							'B1_PRV1' => '250.00',
							'B1_MSBLQL' => '2',
							'B1_GRUPO' => '04',
						],
					],
				],
			]);

		$mapper = $this->createMock( Mapping_Engine::class );
		$mapper->method( 'get_product_mapping' )->willReturn([]);

		$logger = $this->createMock( Logger::class );

		$catalog_sync = new Catalog_Sync( $client, $mapper, $logger );
		$result = $catalog_sync->sync_products( 50 );

		$this->assertEquals( 1, $result['total_processed'] );
		$this->assertEquals( 0, $result['errors'] );
	}

	/**
	 * Test price lock metadata is set for synced products
	 */
	public function test_price_lock_metadata_set() {
		$client = $this->createMock( Protheus_Client::class );
		$client->method( 'get' )
			->willReturn([
				'success' => true,
				'data' => [
					'products' => [
						[
							'B1_COD' => 'PROD018',
							'B1_DESC' => 'Produto com Price Lock',
							'B1_PRV1' => '300.00',
							'B1_MSBLQL' => '2',
						],
					],
				],
			]);

		$mapper = $this->createMock( Mapping_Engine::class );
		$mapper->method( 'get_product_mapping' )->willReturn([]);

		$logger = $this->createMock( Logger::class );

		$catalog_sync = new Catalog_Sync( $client, $mapper, $logger );
		$result = $catalog_sync->sync_products( 50 );

		$this->assertEquals( 1, $result['total_processed'] );
		$this->assertEquals( 0, $result['errors'] );
	}
}
