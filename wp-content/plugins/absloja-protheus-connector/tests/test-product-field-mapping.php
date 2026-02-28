<?php
/**
 * Test Product Field Mapping
 *
 * Validates that all product fields are correctly mapped from Protheus to WooCommerce
 * according to task 10.3 requirements.
 *
 * @package ABSLoja\ProtheusConnector\Tests
 * @since 1.0.0
 */

require_once __DIR__ . '/../includes/modules/class-catalog-sync.php';
require_once __DIR__ . '/../includes/modules/class-mapping-engine.php';
require_once __DIR__ . '/../includes/modules/class-logger.php';
require_once __DIR__ . '/../includes/api/class-protheus-client.php';

use ABSLoja\ProtheusConnector\Modules\Catalog_Sync;
use ABSLoja\ProtheusConnector\Modules\Mapping_Engine;
use ABSLoja\ProtheusConnector\Modules\Logger;
use ABSLoja\ProtheusConnector\API\Protheus_Client;

/**
 * Test Product Field Mapping
 *
 * This test validates that the Catalog_Sync class correctly maps all required
 * Protheus product fields to WooCommerce product fields as specified in task 10.3:
 *
 * - B1_COD → SKU
 * - B1_DESC → product name
 * - B1_PRV1 → regular_price
 * - B1_PESO → weight
 * - B1_MSBLQL → status (1=draft, 2=publish)
 * - B1_GRUPO → categories (using Mapping_Engine)
 * - Metadata: _protheus_synced, _protheus_sync_date
 *
 * Requirements: 3.2, 3.5, 3.6, 3.7, 3.8, 3.9
 */

echo "\n=== Testing Product Field Mapping (Task 10.3) ===\n\n";

// Test data representing a Protheus product
$test_products = array(
	// Test 1: Normal product (not blocked)
	array(
		'B1_COD'     => 'TEST-PROD-001',
		'B1_DESC'    => 'Test Product One',
		'B1_PRV1'    => '99.90',
		'B1_PESO'    => '1.5',
		'B1_MSBLQL'  => '2', // Not blocked = publish
		'B1_GRUPO'   => '01',
		'B1_DESCMAR' => 'Short description for test product',
	),
	// Test 2: Blocked product
	array(
		'B1_COD'     => 'TEST-PROD-002',
		'B1_DESC'    => 'Test Product Two (Blocked)',
		'B1_PRV1'    => '149.90',
		'B1_PESO'    => '2.3',
		'B1_MSBLQL'  => '1', // Blocked = draft
		'B1_GRUPO'   => '02',
		'B1_DESCMAR' => 'This product is blocked',
	),
	// Test 3: Product without category
	array(
		'B1_COD'     => 'TEST-PROD-003',
		'B1_DESC'    => 'Test Product Three (No Category)',
		'B1_PRV1'    => '29.90',
		'B1_PESO'    => '0.5',
		'B1_MSBLQL'  => '2',
		'B1_GRUPO'   => '', // No category
		'B1_DESCMAR' => 'Product without category',
	),
);

// Initialize dependencies
$logger = new Logger();
$mapper = new Mapping_Engine();

// Create a mock Protheus client that returns our test data
class Mock_Protheus_Client extends Protheus_Client {
	private $test_products;
	private $current_index = 0;

	public function __construct( $test_products ) {
		$this->test_products = $test_products;
	}

	public function get( $endpoint, $params = array() ) {
		// Return test products for batch sync
		if ( strpos( $endpoint, 'api/v1/products' ) !== false && ! isset( $params['sku'] ) ) {
			$page = $params['page'] ?? 1;
			$limit = $params['limit'] ?? 50;
			
			$start = ( $page - 1 ) * $limit;
			$products = array_slice( $this->test_products, $start, $limit );
			
			return array(
				'success' => true,
				'data'    => array(
					'products' => $products,
				),
			);
		}
		
		return array( 'success' => false, 'error' => 'Not implemented' );
	}
}

$mock_client = new Mock_Protheus_Client( $test_products );
$catalog_sync = new Catalog_Sync( $mock_client, $mapper, $logger );

// Run the sync
echo "Running product sync...\n";
$results = $catalog_sync->sync_products( 10 );

echo "\nSync Results:\n";
echo "- Total Processed: {$results['total_processed']}\n";
echo "- Created: {$results['created']}\n";
echo "- Updated: {$results['updated']}\n";
echo "- Errors: {$results['errors']}\n";

if ( ! empty( $results['error_details'] ) ) {
	echo "\nErrors:\n";
	foreach ( $results['error_details'] as $error ) {
		echo "  - $error\n";
	}
}

// Validate each product mapping
echo "\n=== Validating Field Mappings ===\n\n";

$all_tests_passed = true;

foreach ( $test_products as $index => $protheus_product ) {
	$test_num = $index + 1;
	$sku = $protheus_product['B1_COD'];
	
	echo "Test $test_num: Validating product $sku\n";
	
	// Get the WooCommerce product
	$product_id = wc_get_product_id_by_sku( $sku );
	
	if ( ! $product_id ) {
		echo "  ❌ FAIL: Product not found in WooCommerce\n";
		$all_tests_passed = false;
		continue;
	}
	
	$product = wc_get_product( $product_id );
	
	if ( ! $product ) {
		echo "  ❌ FAIL: Could not load product object\n";
		$all_tests_passed = false;
		continue;
	}
	
	$test_passed = true;
	
	// Test 1: B1_COD → SKU
	if ( $product->get_sku() !== $protheus_product['B1_COD'] ) {
		echo "  ❌ FAIL: SKU mapping incorrect\n";
		echo "     Expected: {$protheus_product['B1_COD']}\n";
		echo "     Got: {$product->get_sku()}\n";
		$test_passed = false;
	} else {
		echo "  ✓ SKU correctly mapped: {$product->get_sku()}\n";
	}
	
	// Test 2: B1_DESC → product name
	if ( $product->get_name() !== $protheus_product['B1_DESC'] ) {
		echo "  ❌ FAIL: Name mapping incorrect\n";
		echo "     Expected: {$protheus_product['B1_DESC']}\n";
		echo "     Got: {$product->get_name()}\n";
		$test_passed = false;
	} else {
		echo "  ✓ Name correctly mapped: {$product->get_name()}\n";
	}
	
	// Test 3: B1_PRV1 → regular_price
	$expected_price = $protheus_product['B1_PRV1'];
	$actual_price = $product->get_regular_price();
	if ( $actual_price != $expected_price ) {
		echo "  ❌ FAIL: Price mapping incorrect\n";
		echo "     Expected: $expected_price\n";
		echo "     Got: $actual_price\n";
		$test_passed = false;
	} else {
		echo "  ✓ Price correctly mapped: $actual_price\n";
	}
	
	// Test 4: B1_PESO → weight
	if ( $product->get_weight() !== $protheus_product['B1_PESO'] ) {
		echo "  ❌ FAIL: Weight mapping incorrect\n";
		echo "     Expected: {$protheus_product['B1_PESO']}\n";
		echo "     Got: {$product->get_weight()}\n";
		$test_passed = false;
	} else {
		echo "  ✓ Weight correctly mapped: {$product->get_weight()}\n";
	}
	
	// Test 5: B1_MSBLQL → status (1=draft, 2=publish)
	$expected_status = $protheus_product['B1_MSBLQL'] === '1' ? 'draft' : 'publish';
	$actual_status = $product->get_status();
	if ( $actual_status !== $expected_status ) {
		echo "  ❌ FAIL: Status mapping incorrect\n";
		echo "     B1_MSBLQL: {$protheus_product['B1_MSBLQL']}\n";
		echo "     Expected status: $expected_status\n";
		echo "     Got: $actual_status\n";
		$test_passed = false;
	} else {
		echo "  ✓ Status correctly mapped: $actual_status (B1_MSBLQL={$protheus_product['B1_MSBLQL']})\n";
	}
	
	// Test 6: B1_GRUPO → categories (using Mapping_Engine)
	if ( ! empty( $protheus_product['B1_GRUPO'] ) ) {
		$expected_category_id = $mapper->get_category_mapping( $protheus_product['B1_GRUPO'] );
		$actual_categories = $product->get_category_ids();
		
		if ( $expected_category_id && ! in_array( $expected_category_id, $actual_categories ) ) {
			echo "  ❌ FAIL: Category mapping incorrect\n";
			echo "     B1_GRUPO: {$protheus_product['B1_GRUPO']}\n";
			echo "     Expected category ID: $expected_category_id\n";
			echo "     Got categories: " . implode( ', ', $actual_categories ) . "\n";
			$test_passed = false;
		} else if ( $expected_category_id ) {
			echo "  ✓ Category correctly mapped: B1_GRUPO={$protheus_product['B1_GRUPO']} → Category ID=$expected_category_id\n";
		} else {
			echo "  ℹ No category mapping configured for B1_GRUPO={$protheus_product['B1_GRUPO']}\n";
		}
	} else {
		echo "  ℹ No B1_GRUPO provided, skipping category test\n";
	}
	
	// Test 7: Metadata _protheus_synced
	$synced = $product->get_meta( '_protheus_synced' );
	if ( ! $synced ) {
		echo "  ❌ FAIL: _protheus_synced metadata not set\n";
		$test_passed = false;
	} else {
		echo "  ✓ Metadata _protheus_synced: true\n";
	}
	
	// Test 8: Metadata _protheus_sync_date
	$sync_date = $product->get_meta( '_protheus_sync_date' );
	if ( empty( $sync_date ) ) {
		echo "  ❌ FAIL: _protheus_sync_date metadata not set\n";
		$test_passed = false;
	} else {
		echo "  ✓ Metadata _protheus_sync_date: $sync_date\n";
	}
	
	// Test 9: Metadata _protheus_b1_grupo
	$stored_grupo = $product->get_meta( '_protheus_b1_grupo' );
	if ( $stored_grupo !== $protheus_product['B1_GRUPO'] ) {
		echo "  ❌ FAIL: _protheus_b1_grupo metadata incorrect\n";
		echo "     Expected: {$protheus_product['B1_GRUPO']}\n";
		echo "     Got: $stored_grupo\n";
		$test_passed = false;
	} else {
		echo "  ✓ Metadata _protheus_b1_grupo: $stored_grupo\n";
	}
	
	// Test 10: Metadata _protheus_b1_cod
	$stored_cod = $product->get_meta( '_protheus_b1_cod' );
	if ( $stored_cod !== $protheus_product['B1_COD'] ) {
		echo "  ❌ FAIL: _protheus_b1_cod metadata incorrect\n";
		echo "     Expected: {$protheus_product['B1_COD']}\n";
		echo "     Got: $stored_cod\n";
		$test_passed = false;
	} else {
		echo "  ✓ Metadata _protheus_b1_cod: $stored_cod\n";
	}
	
	if ( $test_passed ) {
		echo "  ✅ All field mappings validated successfully\n";
	} else {
		$all_tests_passed = false;
	}
	
	echo "\n";
}

// Final summary
echo "=== Test Summary ===\n";
if ( $all_tests_passed ) {
	echo "✅ ALL TESTS PASSED - Product field mapping is correct\n";
	echo "\nTask 10.3 Requirements Validated:\n";
	echo "  ✓ B1_COD → SKU\n";
	echo "  ✓ B1_DESC → product name\n";
	echo "  ✓ B1_PRV1 → regular_price\n";
	echo "  ✓ B1_PESO → weight\n";
	echo "  ✓ B1_MSBLQL → status (1=draft, 2=publish)\n";
	echo "  ✓ B1_GRUPO → categories (using Mapping_Engine)\n";
	echo "  ✓ Metadata _protheus_synced stored\n";
	echo "  ✓ Metadata _protheus_sync_date stored\n";
	echo "  ✓ Metadata _protheus_b1_grupo stored\n";
	echo "  ✓ Metadata _protheus_b1_cod stored\n";
	echo "\nRequirements validated: 3.2, 3.5, 3.6, 3.7, 3.8, 3.9\n";
	exit( 0 );
} else {
	echo "❌ SOME TESTS FAILED - Please review the errors above\n";
	exit( 1 );
}
