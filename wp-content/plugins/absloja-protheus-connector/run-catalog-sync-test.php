<?php
/**
 * Manual test runner for Catalog_Sync unit tests
 * Task 10.8: Unit tests for Catalog_Sync
 */

require_once 'vendor/autoload.php';
require_once 'tests/bootstrap.php';

echo "Running CatalogSyncTest...\n\n";

$test = new \ABSLoja\ProtheusConnector\Tests\Unit\Modules\CatalogSyncTest();

$tests = [
    'test_sync_products_creates_new_product' => 'Sync products creates new product',
    'test_sync_products_updates_existing_product' => 'Sync products updates existing product',
    'test_product_field_mapping' => 'Product field mapping',
    'test_blocked_product_status_mapping' => 'Blocked product status mapping',
    'test_sync_stock_updates_quantities' => 'Stock sync updates quantities',
    'test_product_hidden_when_stock_zero' => 'Product hidden when stock zero',
    'test_product_visibility_restored_when_stock_available' => 'Product visibility restored when stock available',
    'test_sync_single_product_success' => 'Sync single product success',
    'test_sync_single_product_handles_api_failure' => 'Sync single product handles API failure',
    'test_sync_single_stock_success' => 'Sync single stock success',
    'test_sync_single_stock_returns_false_for_empty_sku' => 'Sync single stock returns false for empty SKU',
    'test_image_download_and_attachment' => 'Image download and attachment',
    'test_image_url_pattern_processing' => 'Image URL pattern processing',
    'test_existing_images_preserved_when_no_url' => 'Existing images preserved when no URL',
    'test_category_mapping_from_b1_grupo' => 'Category mapping from B1_GRUPO',
    'test_batch_processing_with_pagination' => 'Batch processing with pagination',
    'test_error_handling_missing_sku' => 'Error handling missing SKU',
    'test_stock_sync_error_handling_missing_sku' => 'Stock sync error handling missing SKU',
    'test_sync_products_handles_api_failure' => 'Sync products handles API failure',
    'test_sync_stock_handles_api_failure' => 'Stock sync handles API failure',
    'test_metadata_storage_for_synced_products' => 'Metadata storage for synced products',
    'test_price_lock_metadata_set' => 'Price lock metadata set',
];

$passed = 0;
$failed = 0;
$errors = [];

foreach ($tests as $method => $description) {
    try {
        $test->setUp();
        echo "Test: {$description}...\n";
        $test->$method();
        echo "✓ PASSED\n\n";
        $passed++;
    } catch (\Exception $e) {
        echo "✗ FAILED: " . $e->getMessage() . "\n\n";
        $failed++;
        $errors[] = [
            'test' => $description,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ];
    } finally {
        try {
            $test->tearDown();
        } catch (\Exception $e) {
            // Ignore tearDown errors
        }
    }
}

echo "\n" . str_repeat('=', 70) . "\n";
echo "Test Summary:\n";
echo "  Total: " . count($tests) . "\n";
echo "  Passed: {$passed}\n";
echo "  Failed: {$failed}\n";
echo str_repeat('=', 70) . "\n";

if ($failed > 0) {
    echo "\nFailed Tests Details:\n";
    foreach ($errors as $error) {
        echo "\n" . str_repeat('-', 70) . "\n";
        echo "Test: {$error['test']}\n";
        echo "Error: {$error['error']}\n";
        echo "Trace:\n{$error['trace']}\n";
    }
}

exit($failed > 0 ? 1 : 0);
