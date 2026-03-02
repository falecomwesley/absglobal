#!/usr/bin/env php
<?php
/**
 * Standalone property test runner for Retry_Manager
 */

// Load bootstrap
require_once __DIR__ . '/tests/bootstrap.php';

// Load the test class
require_once __DIR__ . '/tests/property/RetryPropertiesTest.php';

use ABSLoja\ProtheusConnector\Tests\Property\RetryPropertiesTest;

echo "Running Property Tests for Retry_Manager...\n\n";

$test = new RetryPropertiesTest();
$test->setUp();

try {
	echo "Test 1: Property 42 - Retry Scheduling on Failure...\n";
	$test->test_retry_scheduling_on_failure_sets_correct_next_attempt_time();
	echo "✓ PASSED\n\n";
} catch ( Exception $e ) {
	echo "✗ FAILED: " . $e->getMessage() . "\n\n";
	echo "Stack trace:\n" . $e->getTraceAsString() . "\n\n";
}

try {
	echo "Test 2: Property 43 - Maximum Retry Attempts...\n";
	$test->test_maximum_retry_attempts_enforced();
	echo "✓ PASSED\n\n";
} catch ( Exception $e ) {
	echo "✗ FAILED: " . $e->getMessage() . "\n\n";
	echo "Stack trace:\n" . $e->getTraceAsString() . "\n\n";
}

try {
	echo "Test 3: Property 44 - Permanent Failure Notification...\n";
	$test->test_permanent_failure_notification_sent_when_retries_exhausted();
	echo "✓ PASSED\n\n";
} catch ( Exception $e ) {
	echo "✗ FAILED: " . $e->getMessage() . "\n\n";
	echo "Stack trace:\n" . $e->getTraceAsString() . "\n\n";
}

try {
	echo "Test 4: Property 45 - Retry Queue Removal on Success...\n";
	$test->test_retry_queue_removal_on_success();
	echo "✓ PASSED\n\n";
} catch ( Exception $e ) {
	echo "✗ FAILED: " . $e->getMessage() . "\n\n";
	echo "Stack trace:\n" . $e->getTraceAsString() . "\n\n";
}

$test->tearDown();

echo "\nAll Retry_Manager property tests completed!\n";
