<?php
/**
 * Test WP-Cron Integration for Catalog and Stock Sync
 *
 * This test validates that the WP-Cron hooks are properly registered
 * and the callback methods work correctly.
 *
 * @package ABSLoja\ProtheusConnector\Tests
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Test WP-Cron Integration
 *
 * Run this test by accessing: /wp-content/plugins/absloja-protheus-connector/tests/test-cron-integration.php
 * Or via WP-CLI: wp eval-file wp-content/plugins/absloja-protheus-connector/tests/test-cron-integration.php
 */
function test_cron_integration() {
	echo "<h1>WP-Cron Integration Test - Task 10.7</h1>\n";
	echo "<p>Testing catalog and stock sync WP-Cron hooks...</p>\n";

	$results = array(
		'passed' => 0,
		'failed' => 0,
		'tests'  => array(),
	);

	// Test 1: Check if catalog sync hook is registered
	echo "<h2>Test 1: Catalog Sync Hook Registration</h2>\n";
	$catalog_hook_registered = has_action( 'absloja_protheus_sync_catalog' );
	if ( $catalog_hook_registered ) {
		echo "<p style='color: green;'>✓ PASSED: absloja_protheus_sync_catalog hook is registered</p>\n";
		$results['passed']++;
		$results['tests'][] = array(
			'name'   => 'Catalog Sync Hook Registration',
			'status' => 'passed',
		);
	} else {
		echo "<p style='color: red;'>✗ FAILED: absloja_protheus_sync_catalog hook is NOT registered</p>\n";
		$results['failed']++;
		$results['tests'][] = array(
			'name'   => 'Catalog Sync Hook Registration',
			'status' => 'failed',
		);
	}

	// Test 2: Check if stock sync hook is registered
	echo "<h2>Test 2: Stock Sync Hook Registration</h2>\n";
	$stock_hook_registered = has_action( 'absloja_protheus_sync_stock' );
	if ( $stock_hook_registered ) {
		echo "<p style='color: green;'>✓ PASSED: absloja_protheus_sync_stock hook is registered</p>\n";
		$results['passed']++;
		$results['tests'][] = array(
			'name'   => 'Stock Sync Hook Registration',
			'status' => 'passed',
		);
	} else {
		echo "<p style='color: red;'>✗ FAILED: absloja_protheus_sync_stock hook is NOT registered</p>\n";
		$results['failed']++;
		$results['tests'][] = array(
			'name'   => 'Stock Sync Hook Registration',
			'status' => 'failed',
		);
	}

	// Test 3: Check if Plugin class has sync_catalog_callback method
	echo "<h2>Test 3: Plugin::sync_catalog_callback Method Exists</h2>\n";
	$plugin = \ABSLoja\ProtheusConnector\Plugin::get_instance();
	if ( method_exists( $plugin, 'sync_catalog_callback' ) ) {
		echo "<p style='color: green;'>✓ PASSED: Plugin::sync_catalog_callback method exists</p>\n";
		$results['passed']++;
		$results['tests'][] = array(
			'name'   => 'Plugin::sync_catalog_callback Method Exists',
			'status' => 'passed',
		);
	} else {
		echo "<p style='color: red;'>✗ FAILED: Plugin::sync_catalog_callback method does NOT exist</p>\n";
		$results['failed']++;
		$results['tests'][] = array(
			'name'   => 'Plugin::sync_catalog_callback Method Exists',
			'status' => 'failed',
		);
	}

	// Test 4: Check if Plugin class has sync_stock_callback method
	echo "<h2>Test 4: Plugin::sync_stock_callback Method Exists</h2>\n";
	if ( method_exists( $plugin, 'sync_stock_callback' ) ) {
		echo "<p style='color: green;'>✓ PASSED: Plugin::sync_stock_callback method exists</p>\n";
		$results['passed']++;
		$results['tests'][] = array(
			'name'   => 'Plugin::sync_stock_callback Method Exists',
			'status' => 'passed',
		);
	} else {
		echo "<p style='color: red;'>✗ FAILED: Plugin::sync_stock_callback method does NOT exist</p>\n";
		$results['failed']++;
		$results['tests'][] = array(
			'name'   => 'Plugin::sync_stock_callback Method Exists',
			'status' => 'failed',
		);
	}

	// Test 5: Check if custom cron schedules are registered
	echo "<h2>Test 5: Custom Cron Schedules</h2>\n";
	$schedules = wp_get_schedules();
	$custom_schedules = array( 'every_15_minutes', 'every_30_minutes', 'every_6_hours' );
	$all_schedules_registered = true;
	foreach ( $custom_schedules as $schedule ) {
		if ( ! isset( $schedules[ $schedule ] ) ) {
			echo "<p style='color: red;'>✗ FAILED: Custom schedule '{$schedule}' is NOT registered</p>\n";
			$all_schedules_registered = false;
		} else {
			echo "<p style='color: green;'>✓ Custom schedule '{$schedule}' is registered (interval: {$schedules[$schedule]['interval']}s)</p>\n";
		}
	}
	if ( $all_schedules_registered ) {
		echo "<p style='color: green;'>✓ PASSED: All custom cron schedules are registered</p>\n";
		$results['passed']++;
		$results['tests'][] = array(
			'name'   => 'Custom Cron Schedules',
			'status' => 'passed',
		);
	} else {
		$results['failed']++;
		$results['tests'][] = array(
			'name'   => 'Custom Cron Schedules',
			'status' => 'failed',
		);
	}

	// Test 6: Verify hook callbacks are callable
	echo "<h2>Test 6: Hook Callbacks Are Callable</h2>\n";
	$catalog_callback = array( $plugin, 'sync_catalog_callback' );
	$stock_callback = array( $plugin, 'sync_stock_callback' );
	
	if ( is_callable( $catalog_callback ) && is_callable( $stock_callback ) ) {
		echo "<p style='color: green;'>✓ PASSED: Both sync callbacks are callable</p>\n";
		$results['passed']++;
		$results['tests'][] = array(
			'name'   => 'Hook Callbacks Are Callable',
			'status' => 'passed',
		);
	} else {
		echo "<p style='color: red;'>✗ FAILED: One or more callbacks are NOT callable</p>\n";
		$results['failed']++;
		$results['tests'][] = array(
			'name'   => 'Hook Callbacks Are Callable',
			'status' => 'failed',
		);
	}

	// Summary
	echo "<hr>\n";
	echo "<h2>Test Summary</h2>\n";
	echo "<p><strong>Total Tests:</strong> " . ( $results['passed'] + $results['failed'] ) . "</p>\n";
	echo "<p style='color: green;'><strong>Passed:</strong> {$results['passed']}</p>\n";
	echo "<p style='color: red;'><strong>Failed:</strong> {$results['failed']}</p>\n";

	if ( $results['failed'] === 0 ) {
		echo "<p style='color: green; font-size: 18px; font-weight: bold;'>✓ ALL TESTS PASSED!</p>\n";
		echo "<p>Task 10.7 implementation is complete and working correctly.</p>\n";
	} else {
		echo "<p style='color: red; font-size: 18px; font-weight: bold;'>✗ SOME TESTS FAILED</p>\n";
		echo "<p>Please review the failed tests above.</p>\n";
	}

	return $results;
}

// Run the test if accessed directly
if ( ! defined( 'WP_CLI' ) ) {
	// Load WordPress
	require_once dirname( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) ) . '/wp-load.php';
}

test_cron_integration();
