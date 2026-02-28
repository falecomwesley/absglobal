#!/usr/bin/env php
<?php
/**
 * Standalone property test runner
 */

// Load bootstrap
require_once __DIR__ . '/tests/bootstrap.php';

// Load the test class
require_once __DIR__ . '/tests/property/AuthManagerPropertiesTest.php';

use ABSLoja\ProtheusConnector\Tests\Property\AuthManagerPropertiesTest;

echo "Running Property Tests for Auth_Manager...\n\n";

$test = new AuthManagerPropertiesTest();
$test->setUp();

try {
	echo "Test 1: Credentials are encrypted before storage...\n";
	$test->test_credentials_are_encrypted_before_storage();
	echo "✓ PASSED\n\n";
} catch ( Exception $e ) {
	echo "✗ FAILED: " . $e->getMessage() . "\n\n";
}

try {
	echo "Test 2: Encryption uses random IV...\n";
	$test->test_encryption_uses_random_iv();
	echo "✓ PASSED\n\n";
} catch ( Exception $e ) {
	echo "✗ FAILED: " . $e->getMessage() . "\n\n";
}

try {
	echo "Test 3: Encryption/decryption roundtrip is idempotent...\n";
	$test->test_encryption_decryption_roundtrip_is_idempotent();
	echo "✓ PASSED\n\n";
} catch ( Exception $e ) {
	echo "✗ FAILED: " . $e->getMessage() . "\n\n";
}

try {
	echo "Test 4: Credentials use correct option prefix and can be deleted...\n";
	$test->test_credentials_use_correct_option_prefix_and_can_be_deleted();
	echo "✓ PASSED\n\n";
} catch ( Exception $e ) {
	echo "✗ FAILED: " . $e->getMessage() . "\n\n";
}

$test->tearDown();

echo "\nAll property tests completed!\n";
