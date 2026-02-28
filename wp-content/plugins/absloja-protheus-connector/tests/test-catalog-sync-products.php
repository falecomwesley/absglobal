<?php
/**
 * Test Catalog_Sync Product Search and Processing
 *
 * Validates task 10.2 requirements:
 * - Fetch products from Protheus via GET /api/v1/products
 * - Implement pagination with configurable batch_size
 * - For each product: check existence by SKU
 * - If exists: update WooCommerce product
 * - If not exists: create new WooCommerce product
 *
 * @package ABSLoja\ProtheusConnector\Tests
 */

// Load WordPress test environment
require_once dirname(__FILE__) . '/bootstrap.php';

use ABSLoja\ProtheusConnector\Modules\Catalog_Sync;
use ABSLoja\ProtheusConnector\API\Protheus_Client;
use ABSLoja\ProtheusConnector\Modules\Mapping_Engine;
use ABSLoja\ProtheusConnector\Modules\Logger;

/**
 * Test class for Catalog_Sync product synchronization
 */
class Test_Catalog_Sync_Products extends WP_UnitTestCase {

	/**
	 * Mock Protheus Client
	 *
	 * @var Protheus_Client
	 */
	private $mock_client;

	/**
	 * Mapping Engine
	 *
	 * @var Mapping_Engine
	 */
	private $mapper;

	/**
	 * Logger
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Catalog Sync instance
	 *
	 * @var Catalog_Sync
	 */
	private $catalog_sync;

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();

		// Create mock client
		$this->mock_client = $this->createMock( Protheus_Client::class );
		$this->mapper      = new Mapping_Engine();
		$this->logger      = new Logger();
		$this->catalog_sync = new Catalog_Sync( $this->mock_client, $this->mapper, $this->logger );
	}

	/**
	 * Test: Fetch products from Protheus via GET /api/v1/products
	 *
	 * Validates Requirement 3.1
	 */
	public function test_fetches_products_from_protheus_api() {
		// Mock API response
		$this->mock_client->expects( $this->once() )
			->method( 'get' )
			->with(
				$this->equalTo( 'api/v1/products' ),
				$this->callback( function( $params ) {
					return isset( $params['page'] ) && isset( $params['limit'] );
				} )
			)
			->willReturn( array(
				'success' => true,
				'data'    => array(
					'products' => array(
						array(
							'B1_COD'  => 'PROD001',
							'B1_DESC' => 'Test Product',
							'B1_PRV1' => 100.00,
						),
					),
				),
			) );

		// Execute sync
		$result = $this->catalog_sync->sync_products( 50 );

		// Verify API was called
		$this->assertIsArray( $result );
	}

	/**
	 * Test: Implement pagination with configurable batch_size
	 *
	 * Validates pagination functionality
	 */
	public function test_implements_pagination_with_batch_size() {
		$batch_size = 25;

		// Mock first page response
		$this->mock_client->expects( $this->exactly( 2 ) )
			->method( 'get' )
			->withConsecutive(
				array(
					'api/v1/products',
					array( 'page' => 1, 'limit' => $batch_size ),
				),
				array(
					'api/v1/products',
					array( 'page' => 2, 'limit' => $batch_size ),
				)
			)
			->willReturnOnConsecutiveCalls(
				// First page - full batch
				array(
					'success' => true,
					'data'    => array(
						'products' => array_fill( 0, $batch_size, array(
							'B1_COD'  => 'PROD001',
							'B1_DESC' => 'Test Product',
							'B1_PRV1' => 100.00,
						) ),
					),
				),
				// Second page - empty (no more products)
				array(
					'success' => true,
					'data'    => array(
						'products' => array(),
					),
				)
			);

		// Execute sync with custom batch size
		$result = $this->catalog_sync->sync_products( $batch_size );

		// Verify pagination worked
		$this->assertEquals( $batch_size, $result['total_processed'] );
	}

	/**
	 * Test: Create new product when SKU doesn't exist
	 *
	 * Validates Requirement 3.4
	 */
	public function test_creates_new_product_when_sku_not_exists() {
		$new_sku = 'NEWPROD' . time();

		// Mock API response with new product
		$this->mock_client->expects( $this->once() )
			->method( 'get' )
			->willReturn( array(
				'success' => true,
				'data'    => array(
					'products' => array(
						array(
							'B1_COD'  => $new_sku,
							'B1_DESC' => 'New Test Product',
							'B1_PRV1' => 150.00,
						),
					),
				),
			) );

		// Verify product doesn't exist
		$this->assertFalse( wc_get_product_id_by_sku( $new_sku ) );

		// Execute sync
		$result = $this->catalog_sync->sync_products( 50 );

		// Verify product was created
		$this->assertEquals( 1, $result['created'] );
		$this->assertEquals( 0, $result['updated'] );
		$this->assertGreaterThan( 0, wc_get_product_id_by_sku( $new_sku ) );
	}

	/**
	 * Test: Update existing product when SKU exists
	 *
	 * Validates Requirement 3.3
	 */
	public function test_updates_existing_product_when_sku_exists() {
		$existing_sku = 'EXISTPROD' . time();

		// Create existing product
		$product = new WC_Product_Simple();
		$product->set_sku( $existing_sku );
		$product->set_name( 'Old Name' );
		$product->set_regular_price( 100.00 );
		$product_id = $product->save();

		// Mock API response with updated product data
		$this->mock_client->expects( $this->once() )
			->method( 'get' )
			->willReturn( array(
				'success' => true,
				'data'    => array(
					'products' => array(
						array(
							'B1_COD'  => $existing_sku,
							'B1_DESC' => 'Updated Name',
							'B1_PRV1' => 200.00,
						),
					),
				),
			) );

		// Execute sync
		$result = $this->catalog_sync->sync_products( 50 );

		// Verify product was updated, not created
		$this->assertEquals( 0, $result['created'] );
		$this->assertEquals( 1, $result['updated'] );

		// Verify product data was updated
		$updated_product = wc_get_product( $product_id );
		$this->assertEquals( 'Updated Name', $updated_product->get_name() );
		$this->assertEquals( 200.00, $updated_product->get_regular_price() );
	}

	/**
	 * Test: Check product existence by SKU before processing
	 *
	 * Validates that the implementation checks existence by SKU
	 */
	public function test_checks_product_existence_by_sku() {
		$test_sku = 'CHECKSKU' . time();

		// Create existing product
		$product = new WC_Product_Simple();
		$product->set_sku( $test_sku );
		$product->set_name( 'Existing Product' );
		$product->save();

		// Mock API response
		$this->mock_client->expects( $this->once() )
			->method( 'get' )
			->willReturn( array(
				'success' => true,
				'data'    => array(
					'products' => array(
						array(
							'B1_COD'  => $test_sku,
							'B1_DESC' => 'Updated Product',
							'B1_PRV1' => 100.00,
						),
					),
				),
			) );

		// Execute sync
		$result = $this->catalog_sync->sync_products( 50 );

		// Verify it was recognized as existing (updated, not created)
		$this->assertEquals( 1, $result['updated'] );
		$this->assertEquals( 0, $result['created'] );
	}

	/**
	 * Test: Handle API errors gracefully
	 */
	public function test_handles_api_errors_gracefully() {
		// Mock API error response
		$this->mock_client->expects( $this->once() )
			->method( 'get' )
			->willReturn( array(
				'success' => false,
				'error'   => 'Connection timeout',
			) );

		// Execute sync
		$result = $this->catalog_sync->sync_products( 50 );

		// Verify error was handled
		$this->assertEquals( 0, $result['total_processed'] );
		$this->assertGreaterThan( 0, count( $result['error_details'] ) );
	}

	/**
	 * Test: Process multiple pages of products
	 */
	public function test_processes_multiple_pages() {
		$batch_size = 2;

		// Mock multiple pages
		$this->mock_client->expects( $this->exactly( 3 ) )
			->method( 'get' )
			->willReturnOnConsecutiveCalls(
				// Page 1 - full batch
				array(
					'success' => true,
					'data'    => array(
						'products' => array(
							array( 'B1_COD' => 'PROD001', 'B1_DESC' => 'Product 1', 'B1_PRV1' => 100 ),
							array( 'B1_COD' => 'PROD002', 'B1_DESC' => 'Product 2', 'B1_PRV1' => 200 ),
						),
					),
				),
				// Page 2 - full batch
				array(
					'success' => true,
					'data'    => array(
						'products' => array(
							array( 'B1_COD' => 'PROD003', 'B1_DESC' => 'Product 3', 'B1_PRV1' => 300 ),
							array( 'B1_COD' => 'PROD004', 'B1_DESC' => 'Product 4', 'B1_PRV1' => 400 ),
						),
					),
				),
				// Page 3 - partial batch (end of data)
				array(
					'success' => true,
					'data'    => array(
						'products' => array(
							array( 'B1_COD' => 'PROD005', 'B1_DESC' => 'Product 5', 'B1_PRV1' => 500 ),
						),
					),
				)
			);

		// Execute sync
		$result = $this->catalog_sync->sync_products( $batch_size );

		// Verify all products were processed
		$this->assertEquals( 5, $result['total_processed'] );
	}

	/**
	 * Test: Return proper result structure
	 */
	public function test_returns_proper_result_structure() {
		// Mock API response
		$this->mock_client->expects( $this->once() )
			->method( 'get' )
			->willReturn( array(
				'success' => true,
				'data'    => array(
					'products' => array(),
				),
			) );

		// Execute sync
		$result = $this->catalog_sync->sync_products( 50 );

		// Verify result structure
		$this->assertArrayHasKey( 'total_processed', $result );
		$this->assertArrayHasKey( 'created', $result );
		$this->assertArrayHasKey( 'updated', $result );
		$this->assertArrayHasKey( 'errors', $result );
		$this->assertArrayHasKey( 'error_details', $result );
	}
}
