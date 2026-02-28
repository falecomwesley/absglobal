<?php
/**
 * Simple property test for Auth_Manager encryption
 */

echo "Starting simple property test...\n";

// Load Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Load Auth_Manager
require_once __DIR__ . '/includes/modules/class-auth-manager.php';

// Define AUTH_KEY
if ( ! defined( 'AUTH_KEY' ) ) {
	define( 'AUTH_KEY', 'test-auth-key-for-encryption' );
}

// Mock WordPress functions
function update_option( $key, $value ) {
	global $wp_options;
	$wp_options[ $key ] = $value;
	return true;
}

function get_option( $key, $default = '' ) {
	global $wp_options;
	return $wp_options[ $key ] ?? $default;
}

function delete_option( $key ) {
	global $wp_options;
	unset( $wp_options[ $key ] );
	return true;
}

global $wp_options;
$wp_options = [];

use ABSLoja\ProtheusConnector\Modules\Auth_Manager;

echo "Testing Property 33: Secure Credential Storage\n\n";

// Test 1: Verify credentials are encrypted
echo "Test 1: Credentials are encrypted before storage\n";
$test_password = 'my-secret-password-123!@#';
$auth = new Auth_Manager( [] );
$auth->save_credentials( [ 'password' => $test_password ] );

$stored_value = $wp_options['absloja_protheus_password'] ?? '';
if ( strpos( $stored_value, $test_password ) !== false ) {
	echo "✗ FAILED: Password stored in plaintext!\n";
	exit( 1 );
}

if ( ! preg_match( '/^[A-Za-z0-9+\/=]+$/', $stored_value ) ) {
	echo "✗ FAILED: Stored value is not base64 encoded!\n";
	exit( 1 );
}

echo "✓ Password is encrypted (not in plaintext)\n";
echo "✓ Stored value is base64 encoded\n";

// Test 2: Verify decryption works
$loaded = $auth->load_credentials();
if ( $loaded['password'] !== $test_password ) {
	echo "✗ FAILED: Decryption failed! Expected: $test_password, Got: " . ( $loaded['password'] ?? 'null' ) . "\n";
	exit( 1 );
}

echo "✓ Decryption works correctly\n\n";

// Test 3: Multiple iterations with random data
echo "Test 2: Running 100 iterations with random credentials\n";
$failures = 0;

for ( $i = 0; $i < 100; $i++ ) {
	$wp_options = [];
	
	$random_password = bin2hex( random_bytes( rand( 10, 50 ) ) );
	$random_secret = bin2hex( random_bytes( rand( 20, 64 ) ) );
	
	$auth = new Auth_Manager( [] );
	$auth->save_credentials( [
		'password' => $random_password,
		'client_secret' => $random_secret,
	] );
	
	// Verify not in plaintext
	foreach ( $wp_options as $key => $value ) {
		if ( strpos( $value, $random_password ) !== false || strpos( $value, $random_secret ) !== false ) {
			echo "✗ FAILED at iteration $i: Credentials in plaintext!\n";
			$failures++;
			break;
		}
	}
	
	// Verify roundtrip
	$loaded = $auth->load_credentials();
	if ( $loaded['password'] !== $random_password || $loaded['client_secret'] !== $random_secret ) {
		echo "✗ FAILED at iteration $i: Roundtrip failed!\n";
		$failures++;
	}
}

if ( $failures === 0 ) {
	echo "✓ All 100 iterations passed\n\n";
} else {
	echo "✗ $failures iterations failed\n\n";
	exit( 1 );
}

echo "All property tests PASSED!\n";
echo "\nProperty 33: Secure Credential Storage - VALIDATED ✓\n";
