<?php
/**
 * Manual test runner for CustomerSyncTest
 */

require_once 'vendor/autoload.php';
require_once 'tests/bootstrap.php';

echo "Running CustomerSyncTest...\n\n";

$test = new \ABSLoja\ProtheusConnector\Tests\Unit\Modules\CustomerSyncTest();

$tests = [
	'test_check_customer_exists_returns_code_when_found',
	'test_check_customer_exists_returns_null_when_not_found',
	'test_check_customer_exists_handles_array_response',
	'test_check_customer_exists_returns_null_on_api_failure',
	'test_create_customer_returns_code_on_success',
	'test_create_customer_returns_null_on_api_failure',
	'test_clean_document_removes_cpf_formatting',
	'test_clean_document_removes_cnpj_formatting',
	'test_clean_document_handles_already_clean_document',
	'test_clean_document_removes_various_special_characters',
	'test_name_concatenation_with_space',
	'test_customer_type_determination_cpf_returns_f',
	'test_customer_type_determination_cnpj_returns_j',
	'test_ensure_customer_exists_returns_existing_code',
	'test_ensure_customer_exists_creates_new_customer',
	'test_ensure_customer_exists_returns_null_when_no_document',
	'test_ensure_customer_exists_schedules_retry_on_failure',
];

$passed = 0;
$failed = 0;

foreach ( $tests as $test_name ) {
	try {
		echo "Test: {$test_name}...\n";
		$test->setUp();
		$test->$test_name();
		echo "✓ PASSED\n\n";
		$passed++;
	} catch ( \Exception $e ) {
		echo "✗ FAILED: " . $e->getMessage() . "\n";
		echo "  File: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
		$failed++;
	}
}

echo "\n========================================\n";
echo "Test Results:\n";
echo "  Passed: {$passed}\n";
echo "  Failed: {$failed}\n";
echo "  Total:  " . count( $tests ) . "\n";
echo "========================================\n";

if ( $failed === 0 ) {
	echo "\n✓ All tests passed!\n";
	exit( 0 );
} else {
	echo "\n✗ Some tests failed!\n";
	exit( 1 );
}
