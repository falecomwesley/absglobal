<?php
/**
 * Auth Manager Encryption Unit Tests
 *
 * Tests for secure credential storage functionality.
 *
 * @package ABSLoja\ProtheusConnector\Tests\Unit\Modules
 */

namespace ABSLoja\ProtheusConnector\Tests\Unit\Modules;

use ABSLoja\ProtheusConnector\Modules\Auth_Manager;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

/**
 * Class AuthManagerEncryptionTest
 *
 * Unit tests for Auth_Manager encryption/decryption functionality.
 */
class AuthManagerEncryptionTest extends TestCase {

	/**
	 * Set up test environment
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Define WordPress constants if not defined
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/tmp/' );
		}
		if ( ! defined( 'AUTH_KEY' ) ) {
			define( 'AUTH_KEY', 'test-auth-key-for-encryption-testing-12345' );
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
	 * Test encryption method exists and encrypts data
	 *
	 * Uses reflection to test private encrypt() method.
	 */
	public function test_encrypt_method_encrypts_data() {
		$config = array(
			'auth_type' => 'basic',
			'username'  => 'testuser',
			'password'  => 'testpass',
		);

		$auth_manager = new Auth_Manager( $config );

		// Use reflection to access private method
		$reflection = new \ReflectionClass( $auth_manager );
		$method     = $reflection->getMethod( 'encrypt' );
		$method->setAccessible( true );

		$plaintext = 'sensitive-password-123';
		$encrypted = $method->invoke( $auth_manager, $plaintext );

		// Encrypted data should be different from plaintext
		$this->assertNotEquals( $plaintext, $encrypted );

		// Encrypted data should be base64 encoded
		$this->assertMatchesRegularExpression( '/^[A-Za-z0-9+\/=]+$/', $encrypted );

		// Encrypted data should be longer than plaintext (includes IV)
		$this->assertGreaterThan( strlen( $plaintext ), strlen( $encrypted ) );
	}

	/**
	 * Test decrypt method correctly decrypts encrypted data
	 */
	public function test_decrypt_method_decrypts_encrypted_data() {
		$config = array(
			'auth_type' => 'basic',
			'username'  => 'testuser',
			'password'  => 'testpass',
		);

		$auth_manager = new Auth_Manager( $config );

		// Use reflection to access private methods
		$reflection      = new \ReflectionClass( $auth_manager );
		$encrypt_method  = $reflection->getMethod( 'encrypt' );
		$decrypt_method  = $reflection->getMethod( 'decrypt' );
		$encrypt_method->setAccessible( true );
		$decrypt_method->setAccessible( true );

		$plaintext = 'my-secret-password-456';
		$encrypted = $encrypt_method->invoke( $auth_manager, $plaintext );
		$decrypted = $decrypt_method->invoke( $auth_manager, $encrypted );

		// Decrypted data should match original plaintext
		$this->assertEquals( $plaintext, $decrypted );
	}

	/**
	 * Test encryption produces different output for same input
	 *
	 * Due to random IV, same plaintext should produce different ciphertext.
	 */
	public function test_encryption_produces_different_output_each_time() {
		$config = array(
			'auth_type' => 'basic',
			'username'  => 'testuser',
			'password'  => 'testpass',
		);

		$auth_manager = new Auth_Manager( $config );

		// Use reflection to access private method
		$reflection = new \ReflectionClass( $auth_manager );
		$method     = $reflection->getMethod( 'encrypt' );
		$method->setAccessible( true );

		$plaintext  = 'same-password-every-time';
		$encrypted1 = $method->invoke( $auth_manager, $plaintext );
		$encrypted2 = $method->invoke( $auth_manager, $plaintext );

		// Two encryptions of same data should produce different results
		// (due to random IV)
		$this->assertNotEquals( $encrypted1, $encrypted2 );
	}

	/**
	 * Test get_encryption_key uses AUTH_KEY constant
	 */
	public function test_get_encryption_key_uses_auth_key() {
		$config = array(
			'auth_type' => 'basic',
			'username'  => 'testuser',
			'password'  => 'testpass',
		);

		$auth_manager = new Auth_Manager( $config );

		// Use reflection to access private method
		$reflection = new \ReflectionClass( $auth_manager );
		$method     = $reflection->getMethod( 'get_encryption_key' );
		$method->setAccessible( true );

		$key = $method->invoke( $auth_manager );

		// Key should be 32 bytes (256 bits) for AES-256
		$this->assertEquals( 32, strlen( $key ) );

		// Key should be derived from AUTH_KEY
		$expected_key = hash( 'sha256', AUTH_KEY, true );
		$this->assertEquals( $expected_key, $key );
	}

	/**
	 * Test encryption handles empty strings
	 */
	public function test_encryption_handles_empty_strings() {
		$config = array(
			'auth_type' => 'basic',
			'username'  => 'testuser',
			'password'  => 'testpass',
		);

		$auth_manager = new Auth_Manager( $config );

		// Use reflection to access private methods
		$reflection      = new \ReflectionClass( $auth_manager );
		$encrypt_method  = $reflection->getMethod( 'encrypt' );
		$decrypt_method  = $reflection->getMethod( 'decrypt' );
		$encrypt_method->setAccessible( true );
		$decrypt_method->setAccessible( true );

		$plaintext = '';
		$encrypted = $encrypt_method->invoke( $auth_manager, $plaintext );
		$decrypted = $decrypt_method->invoke( $auth_manager, $encrypted );

		// Should handle empty string correctly
		$this->assertEquals( $plaintext, $decrypted );
	}

	/**
	 * Test encryption handles special characters
	 */
	public function test_encryption_handles_special_characters() {
		$config = array(
			'auth_type' => 'basic',
			'username'  => 'testuser',
			'password'  => 'testpass',
		);

		$auth_manager = new Auth_Manager( $config );

		// Use reflection to access private methods
		$reflection      = new \ReflectionClass( $auth_manager );
		$encrypt_method  = $reflection->getMethod( 'encrypt' );
		$decrypt_method  = $reflection->getMethod( 'decrypt' );
		$encrypt_method->setAccessible( true );
		$decrypt_method->setAccessible( true );

		$plaintext = 'p@ssw0rd!#$%^&*(){}[]|\\:;"\'<>,.?/~`';
		$encrypted = $encrypt_method->invoke( $auth_manager, $plaintext );
		$decrypted = $decrypt_method->invoke( $auth_manager, $encrypted );

		// Should handle special characters correctly
		$this->assertEquals( $plaintext, $decrypted );
	}

	/**
	 * Test encryption handles long strings
	 */
	public function test_encryption_handles_long_strings() {
		$config = array(
			'auth_type' => 'basic',
			'username'  => 'testuser',
			'password'  => 'testpass',
		);

		$auth_manager = new Auth_Manager( $config );

		// Use reflection to access private methods
		$reflection      = new \ReflectionClass( $auth_manager );
		$encrypt_method  = $reflection->getMethod( 'encrypt' );
		$decrypt_method  = $reflection->getMethod( 'decrypt' );
		$encrypt_method->setAccessible( true );
		$decrypt_method->setAccessible( true );

		// Create a long string (1000 characters)
		$plaintext = str_repeat( 'a', 1000 );
		$encrypted = $encrypt_method->invoke( $auth_manager, $plaintext );
		$decrypted = $decrypt_method->invoke( $auth_manager, $encrypted );

		// Should handle long strings correctly
		$this->assertEquals( $plaintext, $decrypted );
		$this->assertEquals( 1000, strlen( $decrypted ) );
	}

	/**
	 * Test store_option encrypts before storing
	 */
	public function test_store_option_encrypts_before_storing() {
		$config = array(
			'auth_type' => 'basic',
			'username'  => 'testuser',
			'password'  => 'testpass',
		);

		$stored_value = null;

		Functions\expect( 'update_option' )
			->once()
			->andReturnUsing( function( $key, $value ) use ( &$stored_value ) {
				$stored_value = $value;
				return true;
			} );

		$auth_manager = new Auth_Manager( $config );

		// Use reflection to access private method
		$reflection = new \ReflectionClass( $auth_manager );
		$method     = $reflection->getMethod( 'store_option' );
		$method->setAccessible( true );

		$plaintext = 'my-secret-value';
		$result    = $method->invoke( $auth_manager, 'test_key', $plaintext );

		$this->assertTrue( $result );

		// Stored value should be encrypted (not equal to plaintext)
		$this->assertNotEquals( $plaintext, $stored_value );

		// Stored value should be base64 encoded
		$this->assertMatchesRegularExpression( '/^[A-Za-z0-9+\/=]+$/', $stored_value );
	}

	/**
	 * Test get_stored_option decrypts retrieved value
	 */
	public function test_get_stored_option_decrypts_retrieved_value() {
		$config = array(
			'auth_type' => 'basic',
			'username'  => 'testuser',
			'password'  => 'testpass',
		);

		$auth_manager = new Auth_Manager( $config );

		// First encrypt a value
		$reflection     = new \ReflectionClass( $auth_manager );
		$encrypt_method = $reflection->getMethod( 'encrypt' );
		$encrypt_method->setAccessible( true );

		$plaintext       = 'my-secret-value';
		$encrypted_value = $encrypt_method->invoke( $auth_manager, $plaintext );

		// Mock get_option to return encrypted value
		Functions\expect( 'get_option' )
			->once()
			->andReturn( $encrypted_value );

		// Use reflection to access private method
		$get_method = $reflection->getMethod( 'get_stored_option' );
		$get_method->setAccessible( true );

		$retrieved = $get_method->invoke( $auth_manager, 'test_key' );

		// Retrieved value should be decrypted
		$this->assertEquals( $plaintext, $retrieved );
	}

	/**
	 * Test get_stored_option returns null for non-existent option
	 */
	public function test_get_stored_option_returns_null_for_non_existent() {
		$config = array(
			'auth_type' => 'basic',
			'username'  => 'testuser',
			'password'  => 'testpass',
		);

		Functions\expect( 'get_option' )
			->once()
			->andReturn( '' );

		$auth_manager = new Auth_Manager( $config );

		// Use reflection to access private method
		$reflection = new \ReflectionClass( $auth_manager );
		$method     = $reflection->getMethod( 'get_stored_option' );
		$method->setAccessible( true );

		$result = $method->invoke( $auth_manager, 'non_existent_key' );

		$this->assertNull( $result );
	}

	/**
	 * Test credentials are stored with correct prefix
	 */
	public function test_credentials_stored_with_correct_prefix() {
		$config = array(
			'auth_type' => 'basic',
			'username'  => 'testuser',
			'password'  => 'testpass',
		);

		$option_key = null;

		Functions\expect( 'update_option' )
			->once()
			->andReturnUsing( function( $key, $value ) use ( &$option_key ) {
				$option_key = $key;
				return true;
			} );

		$auth_manager = new Auth_Manager( $config );

		// Use reflection to access private method
		$reflection = new \ReflectionClass( $auth_manager );
		$method     = $reflection->getMethod( 'store_option' );
		$method->setAccessible( true );

		$method->invoke( $auth_manager, 'password', 'secret' );

		// Option key should have correct prefix
		$this->assertEquals( 'absloja_protheus_password', $option_key );
	}

	/**
	 * Test OAuth2 access token is stored encrypted
	 */
	public function test_oauth2_access_token_stored_encrypted() {
		$config = array(
			'auth_type'      => 'oauth2',
			'api_url'        => 'https://protheus.example.com',
			'client_id'      => 'test-client-id',
			'client_secret'  => 'test-client-secret',
			'token_endpoint' => '/oauth2/token',
		);

		$stored_token = null;

		Functions\expect( 'trailingslashit' )
			->once()
			->andReturn( 'https://protheus.example.com/' );

		Functions\expect( 'wp_remote_post' )
			->once()
			->andReturn( array(
				'response' => array( 'code' => 200 ),
				'body'     => json_encode( array(
					'access_token' => 'plain-access-token-12345',
					'expires_in'   => 3600,
				) ),
			) );

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_remote_retrieve_response_code' )
			->once()
			->andReturn( 200 );

		Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturn( json_encode( array(
				'access_token' => 'plain-access-token-12345',
				'expires_in'   => 3600,
			) ) );

		Functions\expect( 'update_option' )
			->twice()
			->andReturnUsing( function( $key, $value ) use ( &$stored_token ) {
				if ( $key === 'absloja_protheus_access_token' ) {
					$stored_token = $value;
				}
				return true;
			} );

		$auth_manager = new Auth_Manager( $config );
		$auth_manager->refresh_token();

		// Stored token should be encrypted (not equal to plaintext)
		$this->assertNotNull( $stored_token );
		$this->assertNotEquals( 'plain-access-token-12345', $stored_token );

		// Stored token should be base64 encoded
		$this->assertMatchesRegularExpression( '/^[A-Za-z0-9+\/=]+$/', $stored_token );
	}
}
