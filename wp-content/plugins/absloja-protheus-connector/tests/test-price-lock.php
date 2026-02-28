<?php
/**
 * Test Price Lock Functionality
 *
 * Manual test script to validate price editing prevention for synced products.
 *
 * @package ABSLoja\ProtheusConnector\Tests
 */

// Load WordPress
require_once dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php';

// Check if WooCommerce is active
if ( ! function_exists( 'WC' ) ) {
	die( "WooCommerce is not active.\n" );
}

echo "=== Price Lock Functionality Test ===\n\n";

// Test 1: Create a synced product with price lock
echo "Test 1: Creating synced product with price lock...\n";

$product = new WC_Product_Simple();
$product->set_name( 'Test Synced Product - Price Lock' );
$product->set_sku( 'TEST-PRICE-LOCK-001' );
$product->set_regular_price( '99.99' );
$product->set_status( 'publish' );

// Mark as synced from Protheus
$product->update_meta_data( '_protheus_synced', true );
$product->update_meta_data( '_protheus_price_locked', true );
$product->update_meta_data( '_protheus_original_price', '99.99' );
$product->update_meta_data( '_protheus_sync_date', current_time( 'mysql' ) );

$product_id = $product->save();

if ( $product_id ) {
	echo "✓ Product created successfully (ID: {$product_id})\n";
	echo "  - SKU: TEST-PRICE-LOCK-001\n";
	echo "  - Original Price: 99.99\n";
	echo "  - Price Locked: Yes\n";
} else {
	echo "✗ Failed to create product\n";
	exit( 1 );
}

echo "\n";

// Test 2: Verify metadata is set correctly
echo "Test 2: Verifying product metadata...\n";

$product = wc_get_product( $product_id );

$is_synced = $product->get_meta( '_protheus_synced', true );
$price_locked = $product->get_meta( '_protheus_price_locked', true );
$original_price = $product->get_meta( '_protheus_original_price', true );

if ( $is_synced && $price_locked && $original_price === '99.99' ) {
	echo "✓ Metadata verified correctly\n";
	echo "  - _protheus_synced: {$is_synced}\n";
	echo "  - _protheus_price_locked: {$price_locked}\n";
	echo "  - _protheus_original_price: {$original_price}\n";
} else {
	echo "✗ Metadata verification failed\n";
	echo "  - _protheus_synced: " . var_export( $is_synced, true ) . "\n";
	echo "  - _protheus_price_locked: " . var_export( $price_locked, true ) . "\n";
	echo "  - _protheus_original_price: " . var_export( $original_price, true ) . "\n";
	exit( 1 );
}

echo "\n";

// Test 3: Simulate manual price change
echo "Test 3: Simulating manual price change...\n";

$product->set_regular_price( '149.99' );
$product->save();

echo "  - Changed price from 99.99 to 149.99\n";

// Trigger the restore_original_price hook manually
$catalog_sync = new \ABSLoja\ProtheusConnector\Modules\Catalog_Sync(
	new \ABSLoja\ProtheusConnector\API\Protheus_Client(
		new \ABSLoja\ProtheusConnector\Modules\Auth_Manager( array() )
	),
	new \ABSLoja\ProtheusConnector\Modules\Mapping_Engine(),
	new \ABSLoja\ProtheusConnector\Modules\Logger()
);

// Call the restore method
$catalog_sync->restore_original_price( $product_id );

// Reload product to check if price was restored
$product = wc_get_product( $product_id );
$current_price = $product->get_regular_price();

if ( $current_price === '99.99' ) {
	echo "✓ Price successfully restored to original value\n";
	echo "  - Current Price: {$current_price}\n";
} else {
	echo "✗ Price was not restored\n";
	echo "  - Expected: 99.99\n";
	echo "  - Current: {$current_price}\n";
	exit( 1 );
}

echo "\n";

// Test 4: Test with non-synced product (should allow price change)
echo "Test 4: Testing non-synced product (should allow price change)...\n";

$non_synced_product = new WC_Product_Simple();
$non_synced_product->set_name( 'Test Non-Synced Product' );
$non_synced_product->set_sku( 'TEST-NON-SYNCED-001' );
$non_synced_product->set_regular_price( '50.00' );
$non_synced_product->set_status( 'publish' );

$non_synced_id = $non_synced_product->save();

// Change price
$non_synced_product->set_regular_price( '75.00' );
$non_synced_product->save();

// Trigger restore (should not restore for non-synced products)
$catalog_sync->restore_original_price( $non_synced_id );

// Reload and check
$non_synced_product = wc_get_product( $non_synced_id );
$non_synced_price = $non_synced_product->get_regular_price();

if ( $non_synced_price === '75.00' ) {
	echo "✓ Non-synced product price change allowed\n";
	echo "  - Price changed from 50.00 to 75.00 (as expected)\n";
} else {
	echo "✗ Non-synced product price was incorrectly modified\n";
	echo "  - Expected: 75.00\n";
	echo "  - Current: {$non_synced_price}\n";
	exit( 1 );
}

echo "\n";

// Test 5: Test price update during sync
echo "Test 5: Testing price update during sync...\n";

$product = wc_get_product( $product_id );

// Simulate a sync update with new price
$product->set_regular_price( '129.99' );
$product->update_meta_data( '_protheus_original_price', '129.99' );
$product->update_meta_data( '_protheus_sync_date', current_time( 'mysql' ) );
$product->save();

$updated_price = $product->get_regular_price();
$updated_original = $product->get_meta( '_protheus_original_price', true );

if ( $updated_price === '129.99' && $updated_original === '129.99' ) {
	echo "✓ Price updated successfully during sync\n";
	echo "  - New Price: {$updated_price}\n";
	echo "  - New Original Price: {$updated_original}\n";
} else {
	echo "✗ Price update during sync failed\n";
	echo "  - Expected Price: 129.99\n";
	echo "  - Current Price: {$updated_price}\n";
	echo "  - Original Price: {$updated_original}\n";
	exit( 1 );
}

echo "\n";

// Cleanup
echo "Cleaning up test products...\n";

wp_delete_post( $product_id, true );
wp_delete_post( $non_synced_id, true );

echo "✓ Test products deleted\n";

echo "\n=== All Tests Passed ===\n\n";

echo "Manual Testing Instructions:\n";
echo "1. Go to WooCommerce > Products\n";
echo "2. Create a new product or edit an existing synced product\n";
echo "3. For synced products, you should see:\n";
echo "   - Price fields are readonly (grayed out)\n";
echo "   - A notice below the price field explaining it's synced\n";
echo "   - An info notice at the top of the page\n";
echo "4. Try to change the price and save\n";
echo "5. The price should be restored to the original value\n";
echo "6. A warning notice should appear explaining the reversion\n";
echo "\n";
