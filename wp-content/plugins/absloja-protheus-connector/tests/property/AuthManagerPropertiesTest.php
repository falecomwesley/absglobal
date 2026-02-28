<?php
/**
 * Property-based tests for Auth_Manager
 *
 * Tests correctness properties related to authentication and credential storage.
 *
 * @package ABSLoja\ProtheusConnector\Tests\Property
 */

namespace ABSLoja\ProtheusConnector\Tests\Property;

use ABSLoja\ProtheusConnector\Modules\Auth_Manager;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

/**
 * Auth_Manager property-based tests
 */
class AuthManagerPropertiesTest extends TestCase {

	/**
	 * Set up test environment
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Define WordPress constants if not defined
		if ( ! defined( 'AUTH_KEY' ) ) {
			define( 'AUTH_KEY', 'test-auth-key-for-encryption-' . wp_generate_password( 32, true, true ) );
		}
	}

	/**
	 * Tear down test environment
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * @test
	 * Feature: absloja-protheus-connector, Property 33: Secure Credential Storage
	 *
	 * For any API credentials stored by Auth_Manager, they should be encrypted
	 * before storage in WordPress options table.
	 *
	 * Validates: Requirements 7.3
	 */
	public function test_credentials_are_encrypted_before_storage() {
		// Run property test with multiple iterations
		$iterations = 100;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				// Generate random credentials
				$credentials = $this->generate_random_credentials();

				// Track what gets stored in wp_options
				$stored_values = [];

				// Mock update_option to capture stored values
				Functions\when( 'update_option' )->alias( function( $key, $value ) use ( &$stored_values ) {
					$stored_values[ $key ] = $value;
					return true;
				} );

				// Mock get_option to return stored values
				Functions\when( 'get_option' )->alias( function( $key, $default = '' ) use ( &$stored_values ) {
					return $stored_values[ $key ] ?? $default;
				} );

				// Create Auth_Manager instance
				$auth_manager = new Auth_Manager( [] );

				// Save credentials
				$auth_manager->save_credentials( $credentials );

				// Verify that stored values are encrypted (not plaintext)
				foreach ( $stored_values as $key => $stored_value ) {
					// Skip non-credential options
					if ( strpos( $key, 'absloja_protheus_' ) !== 0 ) {
						continue;
					}

					// Check that sensitive fields are not stored in plaintext
					if ( isset( $credentials['password'] ) && ! empty( $credentials['password'] ) ) {
						$this->assertStringNotContainsString(
							$credentials['password'],
							$stored_value,
							"Password should not be stored in plaintext (iteration $i)"
						);
					}

					if ( isset( $credentials['client_secret'] ) && ! empty( $credentials['client_secret'] ) ) {
						$this->assertStringNotContainsString(
							$credentials['client_secret'],
							$stored_value,
							"Client secret should not be stored in plaintext (iteration $i)"
						);
					}

					// Verify that stored value appears to be encrypted (base64 encoded)
					if ( ! empty( $stored_value ) ) {
						$this->assertMatchesRegularExpression(
							'/^[A-Za-z0-9+\/=]+$/',
							$stored_value,
							"Stored value should be base64 encoded (iteration $i)"
						);
					}
				}

				// Verify that credentials can be loaded back correctly (encryption/decryption roundtrip)
				$loaded_credentials = $auth_manager->load_credentials();

				// Check that decrypted values match original values
				if ( isset( $credentials['password'] ) ) {
					$this->assertEquals(
						$credentials['password'],
						$loaded_credentials['password'] ?? '',
						"Password should decrypt correctly (iteration $i)"
					);
				}

				if ( isset( $credentials['client_secret'] ) ) {
					$this->assertEquals(
						$credentials['client_secret'],
						$loaded_credentials['client_secret'] ?? '',
						"Client secret should decrypt correctly (iteration $i)"
					);
				}

			} catch ( \Exception $e ) {
				$failures[] = "Iteration $i failed: " . $e->getMessage();
			}
		}

		// Report any failures
		if ( ! empty( $failures ) ) {
			$this->fail( "Property test failed:\n" . implode( "\n", $failures ) );
		}

		$this->assertTrue( true, "All $iterations iterations passed" );
	}

	/**
	 * @test
	 * Feature: absloja-protheus-connector, Property 33: Secure Credential Storage
	 *
	 * Verifies that encryption uses OpenSSL when available and produces
	 * different ciphertext for the same plaintext (due to random IV).
	 *
	 * Validates: Requirements 7.3
	 */
	public function test_encryption_uses_random_iv() {
		// Skip if OpenSSL not available
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			$this->markTestSkipped( 'OpenSSL not available' );
		}

		$iterations = 50;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				// Generate random plaintext
				$plaintext = $this->generate_random_string( rand( 10, 100 ) );

				// Track stored values for two saves of the same credential
				$stored_values_1 = [];
				$stored_values_2 = [];

				// First save
				Functions\when( 'update_option' )->alias( function( $key, $value ) use ( &$stored_values_1 ) {
					$stored_values_1[ $key ] = $value;
					return true;
				} );

				Functions\when( 'get_option' )->alias( function( $key, $default = '' ) use ( &$stored_values_1 ) {
					return $stored_values_1[ $key ] ?? $default;
				} );

				$auth_manager_1 = new Auth_Manager( [] );
				$auth_manager_1->save_credentials( [ 'password' => $plaintext ] );

				// Second save with same plaintext
				Functions\when( 'update_option' )->alias( function( $key, $value ) use ( &$stored_values_2 ) {
					$stored_values_2[ $key ] = $value;
					return true;
				} );

				Functions\when( 'get_option' )->alias( function( $key, $default = '' ) use ( &$stored_values_2 ) {
					return $stored_values_2[ $key ] ?? $default;
				} );

				$auth_manager_2 = new Auth_Manager( [] );
				$auth_manager_2->save_credentials( [ 'password' => $plaintext ] );

				// Get the encrypted password from both saves
				$encrypted_1 = $stored_values_1['absloja_protheus_password'] ?? '';
				$encrypted_2 = $stored_values_2['absloja_protheus_password'] ?? '';

				// Verify that the same plaintext produces different ciphertext (due to random IV)
				$this->assertNotEquals(
					$encrypted_1,
					$encrypted_2,
					"Same plaintext should produce different ciphertext due to random IV (iteration $i)"
				);

				// Verify both can be decrypted to the same plaintext
				$loaded_1 = $auth_manager_1->load_credentials();
				$loaded_2 = $auth_manager_2->load_credentials();

				$this->assertEquals(
					$plaintext,
					$loaded_1['password'] ?? '',
					"First encrypted value should decrypt correctly (iteration $i)"
				);

				$this->assertEquals(
					$plaintext,
					$loaded_2['password'] ?? '',
					"Second encrypted value should decrypt correctly (iteration $i)"
				);

			} catch ( \Exception $e ) {
				$failures[] = "Iteration $i failed: " . $e->getMessage();
			}
		}

		if ( ! empty( $failures ) ) {
			$this->fail( "Property test failed:\n" . implode( "\n", $failures ) );
		}

		$this->assertTrue( true, "All $iterations iterations passed" );
	}

	/**
	 * @test
	 * Feature: absloja-protheus-connector, Property 33: Secure Credential Storage
	 *
	 * Verifies that encryption/decryption is idempotent - encrypting and
	 * decrypting any value should return the original value.
	 *
	 * Validates: Requirements 7.3
	 */
	public function test_encryption_decryption_roundtrip_is_idempotent() {
		$iterations = 100;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				// Generate random credentials with various character sets
				$test_values = [
					$this->generate_random_string( rand( 1, 200 ) ),
					$this->generate_random_string_with_special_chars( rand( 10, 100 ) ),
					str_repeat( 'a', rand( 1, 500 ) ), // Repeated characters
					'', // Empty string
					' ', // Single space
					"\n\t\r", // Whitespace characters
					'🔐🔑🛡️', // Unicode characters
				];

				foreach ( $test_values as $idx => $original_value ) {
					$stored_values = [];

					Functions\when( 'update_option' )->alias( function( $key, $value ) use ( &$stored_values ) {
						$stored_values[ $key ] = $value;
						return true;
					} );

					Functions\when( 'get_option' )->alias( function( $key, $default = '' ) use ( &$stored_values ) {
						return $stored_values[ $key ] ?? $default;
					} );

					$auth_manager = new Auth_Manager( [] );
					$auth_manager->save_credentials( [ 'password' => $original_value ] );

					$loaded = $auth_manager->load_credentials();
					$decrypted_value = $loaded['password'] ?? null;

					$this->assertEquals(
						$original_value,
						$decrypted_value,
						"Encryption/decryption roundtrip should preserve value (iteration $i, test value $idx)"
					);
				}

			} catch ( \Exception $e ) {
				$failures[] = "Iteration $i failed: " . $e->getMessage();
			}
		}

		if ( ! empty( $failures ) ) {
			$this->fail( "Property test failed:\n" . implode( "\n", $failures ) );
		}

		$this->assertTrue( true, "All $iterations iterations passed" );
	}

	/**
	 * @test
	 * Feature: absloja-protheus-connector, Property 33: Secure Credential Storage
	 *
	 * Verifies that credentials are stored with the correct option prefix
	 * and can be deleted securely.
	 *
	 * Validates: Requirements 7.3
	 */
	public function test_credentials_use_correct_option_prefix_and_can_be_deleted() {
		$iterations = 50;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				$credentials = $this->generate_random_credentials();
				$stored_values = [];
				$deleted_keys = [];

				Functions\when( 'update_option' )->alias( function( $key, $value ) use ( &$stored_values ) {
					$stored_values[ $key ] = $value;
					return true;
				} );

				Functions\when( 'get_option' )->alias( function( $key, $default = '' ) use ( &$stored_values ) {
					return $stored_values[ $key ] ?? $default;
				} );

				Functions\when( 'delete_option' )->alias( function( $key ) use ( &$stored_values, &$deleted_keys ) {
					$deleted_keys[] = $key;
					unset( $stored_values[ $key ] );
					return true;
				} );

				$auth_manager = new Auth_Manager( [] );
				$auth_manager->save_credentials( $credentials );

				// Verify all stored keys have the correct prefix
				foreach ( array_keys( $stored_values ) as $key ) {
					$this->assertStringStartsWith(
						'absloja_protheus_',
						$key,
						"All credential options should use the correct prefix (iteration $i)"
					);
				}

				// Delete credentials
				$auth_manager->delete_credentials();

				// Verify credentials were deleted
				$loaded_after_delete = $auth_manager->load_credentials();
				$this->assertEmpty(
					$loaded_after_delete,
					"Credentials should be empty after deletion (iteration $i)"
				);

				// Verify delete_option was called for credential keys
				$this->assertNotEmpty(
					$deleted_keys,
					"delete_option should be called when deleting credentials (iteration $i)"
				);

			} catch ( \Exception $e ) {
				$failures[] = "Iteration $i failed: " . $e->getMessage();
			}
		}

		if ( ! empty( $failures ) ) {
			$this->fail( "Property test failed:\n" . implode( "\n", $failures ) );
		}

		$this->assertTrue( true, "All $iterations iterations passed" );
	}

	/**
	 * Generate random credentials for testing
	 *
	 * @return array
	 */
	private function generate_random_credentials(): array {
		$auth_types = [ 'basic', 'oauth2' ];
		$auth_type = $auth_types[ array_rand( $auth_types ) ];

		$credentials = [
			'auth_type' => $auth_type,
			'api_url' => 'https://protheus-' . rand( 1000, 9999 ) . '.example.com',
		];

		if ( $auth_type === 'basic' ) {
			$credentials['username'] = 'user_' . $this->generate_random_string( rand( 5, 20 ) );
			$credentials['password'] = $this->generate_random_string_with_special_chars( rand( 10, 50 ) );
		} else {
			$credentials['client_id'] = 'client_' . $this->generate_random_string( rand( 10, 30 ) );
			$credentials['client_secret'] = $this->generate_random_string_with_special_chars( rand( 20, 64 ) );
			$credentials['token_endpoint'] = '/oauth2/token';
		}

		return $credentials;
	}

	/**
	 * Generate random alphanumeric string
	 *
	 * @param int $length String length
	 * @return string
	 */
	private function generate_random_string( int $length ): string {
		$characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		$string = '';

		for ( $i = 0; $i < $length; $i++ ) {
			$string .= $characters[ rand( 0, strlen( $characters ) - 1 ) ];
		}

		return $string;
	}

	/**
	 * Generate random string with special characters
	 *
	 * @param int $length String length
	 * @return string
	 */
	private function generate_random_string_with_special_chars( int $length ): string {
		$characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+-=[]{}|;:,.<>?';
		$string = '';

		for ( $i = 0; $i < $length; $i++ ) {
			$string .= $characters[ rand( 0, strlen( $characters ) - 1 ) ];
		}

		return $string;
	}
}
