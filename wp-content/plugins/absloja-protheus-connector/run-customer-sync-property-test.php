#!/usr/bin/env php
<?php
/**
 * Standalone property test runner for Customer_Sync
 */

// Load bootstrap
require_once __DIR__ . '/tests/bootstrap.php';

// Load the test class
require_once __DIR__ . '/tests/property/CustomerSyncPropertiesTest.php';

use ABSLoja\ProtheusConnector\Tests\Property\CustomerSyncPropertiesTest;

echo "Running Property Tests for Customer_Sync...\n\n";

$test = new CustomerSyncPropertiesTest();
$test->setUp();

try {
	echo "Test 1: Property 8 - Customer Existence Check...\n";
	$test->test_customer_existence_check_queries_protheus();
	echo "✓ PASSED\n\n";
} catch ( Exception $e ) {
	echo "✗ FAILED: " . $e->getMessage() . "\n\n";
}

try {
	echo "Test 2: Property 9 - Customer Creation on Non-Existence...\n";
	$test->test_customer_creation_when_not_exists();
	echo "✓ PASSED\n\n";
} catch ( Exception $e ) {
	echo "✗ FAILED: " . $e->getMessage() . "\n\n";
}

try {
	echo "Test 3: Property 10 - Customer Field Mapping...\n";
	$test->test_customer_field_mapping_completeness();
	echo "✓ PASSED\n\n";
} catch ( Exception $e ) {
	echo "✗ FAILED: " . $e->getMessage() . "\n\n";
}

try {
	echo "Test 4: Property 11 - CPF/CNPJ Extraction and Cleaning...\n";
	$test->test_cpf_cnpj_extraction_and_cleaning();
	echo "✓ PASSED\n\n";
} catch ( Exception $e ) {
	echo "✗ FAILED: " . $e->getMessage() . "\n\n";
}

try {
	echo "Test 5: Property 12 - Name Concatenation...\n";
	$test->test_name_concatenation();
	echo "✓ PASSED\n\n";
} catch ( Exception $e ) {
	echo "✗ FAILED: " . $e->getMessage() . "\n\n";
}

try {
	echo "Test 6: Property 13 - Order Sync Abortion on Customer Creation Failure...\n";
	$test->test_order_sync_abortion_on_customer_creation_failure();
	echo "✓ PASSED\n\n";
} catch ( Exception $e ) {
	echo "✗ FAILED: " . $e->getMessage() . "\n\n";
}

$test->tearDown();

echo "\nAll Customer_Sync property tests completed!\n";
