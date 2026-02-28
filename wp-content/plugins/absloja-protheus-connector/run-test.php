<?php
/**
 * Manual test runner
 */

require_once 'vendor/autoload.php';
require_once 'tests/bootstrap.php';

echo "Running OrderFlowIntegrationTest...\n\n";

$test = new \ABSLoja\ProtheusConnector\Tests\OrderFlowIntegrationTest();
$test->setUp();

try {
    echo "Test 1: Complete order flow with new customer...\n";
    $test->test_complete_order_flow_with_new_customer();
    echo "✓ PASSED\n\n";
} catch (\Exception $e) {
    echo "✗ FAILED: " . $e->getMessage() . "\n\n";
}

try {
    echo "Test 2: Complete order flow with existing customer...\n";
    $test->setUp();
    $test->test_complete_order_flow_with_existing_customer();
    echo "✓ PASSED\n\n";
} catch (\Exception $e) {
    echo "✗ FAILED: " . $e->getMessage() . "\n\n";
}

try {
    echo "Test 3: Order flow aborts on customer creation failure...\n";
    $test->setUp();
    $test->test_order_flow_aborts_on_customer_creation_failure();
    echo "✓ PASSED\n\n";
} catch (\Exception $e) {
    echo "✗ FAILED: " . $e->getMessage() . "\n\n";
}

try {
    echo "Test 4: Order flow schedules retry on API error...\n";
    $test->setUp();
    $test->test_order_flow_schedules_retry_on_api_error();
    echo "✓ PASSED\n\n";
} catch (\Exception $e) {
    echo "✗ FAILED: " . $e->getMessage() . "\n\n";
}

try {
    echo "Test 5: Metadata storage after successful sync...\n";
    $test->setUp();
    $test->test_metadata_storage_after_successful_sync();
    echo "✓ PASSED\n\n";
} catch (\Exception $e) {
    echo "✗ FAILED: " . $e->getMessage() . "\n\n";
}

echo "All tests completed!\n";
