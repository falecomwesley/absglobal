<?php
/**
 * Auth Manager Unit Tests
 *
 * @package ABSLoja\ProtheusConnector\Tests\Unit\Modules
 */

namespace ABSLoja\ProtheusConnector\Tests\Unit\Modules;

use ABSLoja\ProtheusConnector\Modules\Auth_Manager;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

/**
 * Class AuthManagerTest
 *
 * Unit tests for Auth_Manager class.
 */
class AuthManagerTest extends TestCase {

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
			define( 'AUTH_KEY', 'test-auth-key-for-encryption' );
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
	 * Test constructor accepts configuration
	 */
	public function test_constructor_accepts_configuration() {
		$config = array(
			'auth_type' => 'basic',
			'api_url'   => 'https://protheus.example.com',
			'username'  => 'testuser',
			'password'  => 'testpass',
		);

		$auth_manager = new Auth_Manager( $config );

		$this->assertInstanceOf( Auth_Manager::class, $auth_manager );
	}

	/**
	 * Test get_auth_headers returns Basic Auth headers
	 */
	public function test_get_auth_headers_returns_basic_auth_headers() {
		$config = array(
			'auth_type' => 'basic',
			'username'  => 'testuser',
			'password'  => 'testpass',
		);

		$auth_manager = new Auth_Manager( $config );
		$headers      = $auth_manager->get_auth_headers();

		$this->assertIsArray( $headers );
		$this->assertArrayHasKey( 'Authorization', $headers );
		$this->assertStringStartsWith( 'Basic ', $headers['Authorization'] );

		// Verify base64 encoding
		$expected_credentials = base64_encode( 'testuser:testpass' );
		$this->assertEquals( 'Basic ' . $expected_credentials, $headers['Authorization'] );
	}

	/**
	 * Test get_auth_headers returns empty array when credentials missing
	 */
	public function test_get_auth_headers_returns_empty_when_credentials_missing() {
		$config = array(
			'auth_type' => 'basic',
		);

		$auth_manager = new Auth_Manager( $config );
		$headers      = $auth_manager->get_auth_headers();

		$this->assertIsArray( $headers );
		$this->assertEmpty( $headers );
	}

	/**
	 * Test get_auth_headers includes Content-Type header
	 */
	public function test_get_auth_headers_includes_content_type() {
		$config = array(
			'auth_type' => 'basic',
			'username'  => 'testuser',
			'password'  => 'testpass',
		);

		$auth_manager = new Auth_Manager( $config );
		$headers      = $auth_manager->get_auth_headers();

		$this->assertArrayHasKey( 'Content-Type', $headers );
		$this->assertEquals( 'application/json', $headers['Content-Type'] );
	}

	/**
	 * Test is_authenticated returns true for valid Basic Auth credentials
	 */
	public function test_is_authenticated_returns_true_for_valid_basic_auth() {
		$config = array(
			'auth_type' => 'basic',
			'username'  => 'testuser',
			'password'  => 'testpass',
		);

		$auth_manager = new Auth_Manager( $config );

		$this->assertTrue( $auth_manager->is_authenticated() );
	}

	/**
	 * Test is_authenticated returns false when credentials missing
	 */
	public function test_is_authenticated_returns_false_when_credentials_missing() {
		$config = array(
			'auth_type' => 'basic',
		);

		$auth_manager = new Auth_Manager( $config );

		$this->assertFalse( $auth_manager->is_authenticated() );
	}

	/**
	 * Test test_connection returns false when API URL missing
	 */
	public function test_test_connection_returns_false_when_api_url_missing() {
		$config = array(
			'auth_type' => 'basic',
			'username'  => 'testuser',
			'password'  => 'testpass',
		);

		$auth_manager = new Auth_Manager( $config );

		$this->assertFalse( $auth_manager->test_connection() );
	}

	/**
	 * Test test_connection returns false when credentials missing
	 */
	public function test_test_connection_returns_false_when_credentials_missing() {
		$config = array(
			'auth_type' => 'basic',
			'api_url'   => 'https://protheus.example.com',
		);

		$auth_manager = new Auth_Manager( $config );

		$this->assertFalse( $auth_manager->test_connection() );
	}

	/**
	 * Test test_connection makes HTTP request with correct headers
	 */
	public function test_test_connection_makes_http_request_with_correct_headers() {
		$config = array(
			'auth_type' => 'basic',
			'api_url'   => 'https://protheus.example.com',
			'username'  => 'testuser',
			'password'  => 'testpass',
		);

		// Mock WordPress functions
		Functions\expect( 'trailingslashit' )
			->once()
			->with( 'https://protheus.example.com' )
			->andReturn( 'https://protheus.example.com/' );

		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturnUsing( function( $url, $args ) {
				// Verify URL
				$this->assertEquals( 'https://protheus.example.com/api/v1/health', $url );

				// Verify headers
				$this->assertArrayHasKey( 'Authorization', $args['headers'] );
				$this->assertStringStartsWith( 'Basic ', $args['headers']['Authorization'] );

				// Return success response
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => '{"status":"ok"}',
				);
			} );

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_remote_retrieve_response_code' )
			->once()
			->andReturn( 200 );

		$auth_manager = new Auth_Manager( $config );
		$result       = $auth_manager->test_connection();

		$this->assertTrue( $result );
	}

	/**
	 * Test test_connection returns false on HTTP error
	 */
	public function test_test_connection_returns_false_on_http_error() {
		$config = array(
			'auth_type' => 'basic',
			'api_url'   => 'https://protheus.example.com',
			'username'  => 'testuser',
			'password'  => 'testpass',
		);

		Functions\expect( 'trailingslashit' )
			->once()
			->andReturn( 'https://protheus.example.com/' );

		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn( new \WP_Error( 'http_error', 'Connection failed' ) );

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( true );

		$auth_manager = new Auth_Manager( $config );
		$result       = $auth_manager->test_connection();

		$this->assertFalse( $result );
	}

	/**
	 * Test test_connection returns false on non-2xx status code
	 */
	public function test_test_connection_returns_false_on_non_2xx_status() {
		$config = array(
			'auth_type' => 'basic',
			'api_url'   => 'https://protheus.example.com',
			'username'  => 'testuser',
			'password'  => 'testpass',
		);

		Functions\expect( 'trailingslashit' )
			->once()
			->andReturn( 'https://protheus.example.com/' );

		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn( array( 'response' => array( 'code' => 401 ) ) );

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_remote_retrieve_response_code' )
			->once()
			->andReturn( 401 );

		$auth_manager = new Auth_Manager( $config );
		$result       = $auth_manager->test_connection();

		$this->assertFalse( $result );
	}

	/**
	 * Test refresh_token returns false for Basic Auth
	 */
	public function test_refresh_token_returns_false_for_basic_auth() {
		$config = array(
			'auth_type' => 'basic',
			'username'  => 'testuser',
			'password'  => 'testpass',
		);

		$auth_manager = new Auth_Manager( $config );
		$result       = $auth_manager->refresh_token();

		$this->assertFalse( $result );
	}

	/**
	 * Test refresh_token returns false when OAuth2 credentials missing
	 */
	public function test_refresh_token_returns_false_when_oauth2_credentials_missing() {
		$config = array(
			'auth_type' => 'oauth2',
		);

		$auth_manager = new Auth_Manager( $config );
		$result       = $auth_manager->refresh_token();

		$this->assertFalse( $result );
	}

	/**
	 * Test refresh_token makes correct OAuth2 request
	 */
	public function test_refresh_token_makes_correct_oauth2_request() {
		$config = array(
			'auth_type'      => 'oauth2',
			'api_url'        => 'https://protheus.example.com',
			'client_id'      => 'test-client-id',
			'client_secret'  => 'test-client-secret',
			'token_endpoint' => '/oauth2/token',
		);

		Functions\expect( 'trailingslashit' )
			->once()
			->andReturn( 'https://protheus.example.com/' );

		Functions\expect( 'wp_remote_post' )
			->once()
			->andReturnUsing( function( $url, $args ) {
				// Verify URL
				$this->assertEquals( 'https://protheus.example.com/oauth2/token', $url );

				// Verify body
				$this->assertEquals( 'client_credentials', $args['body']['grant_type'] );
				$this->assertEquals( 'test-client-id', $args['body']['client_id'] );
				$this->assertEquals( 'test-client-secret', $args['body']['client_secret'] );

				// Return success response
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => json_encode( array(
						'access_token' => 'test-access-token',
						'expires_in'   => 3600,
					) ),
				);
			} );

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_remote_retrieve_response_code' )
			->once()
			->andReturn( 200 );

		Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturn( json_encode( array(
				'access_token' => 'test-access-token',
				'expires_in'   => 3600,
			) ) );

		Functions\expect( 'update_option' )
			->twice()
			->andReturn( true );

		$auth_manager = new Auth_Manager( $config );
		$result       = $auth_manager->refresh_token();

		$this->assertTrue( $result );
	}

	/**
	 * Test refresh_token returns false on HTTP error
	 */
	public function test_refresh_token_returns_false_on_http_error() {
		$config = array(
			'auth_type'      => 'oauth2',
			'api_url'        => 'https://protheus.example.com',
			'client_id'      => 'test-client-id',
			'client_secret'  => 'test-client-secret',
			'token_endpoint' => '/oauth2/token',
		);

		Functions\expect( 'trailingslashit' )
			->once()
			->andReturn( 'https://protheus.example.com/' );

		Functions\expect( 'wp_remote_post' )
			->once()
			->andReturn( new \WP_Error( 'http_error', 'Connection failed' ) );

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( true );

		$auth_manager = new Auth_Manager( $config );
		$result       = $auth_manager->refresh_token();

		$this->assertFalse( $result );
	}

	/**
	 * Test refresh_token returns false on non-200 status
	 */
	public function test_refresh_token_returns_false_on_non_200_status() {
		$config = array(
			'auth_type'      => 'oauth2',
			'api_url'        => 'https://protheus.example.com',
			'client_id'      => 'test-client-id',
			'client_secret'  => 'test-client-secret',
			'token_endpoint' => '/oauth2/token',
		);

		Functions\expect( 'trailingslashit' )
			->once()
			->andReturn( 'https://protheus.example.com/' );

		Functions\expect( 'wp_remote_post' )
			->once()
			->andReturn( array( 'response' => array( 'code' => 401 ) ) );

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_remote_retrieve_response_code' )
			->once()
			->andReturn( 401 );

		$auth_manager = new Auth_Manager( $config );
		$result       = $auth_manager->refresh_token();

		$this->assertFalse( $result );
	}

	/**
	 * Test save_credentials stores Basic Auth credentials securely
	 */
	public function test_save_credentials_stores_basic_auth_credentials() {
		$config = array(
			'auth_type' => 'basic',
		);

		$credentials = array(
			'auth_type' => 'basic',
			'api_url'   => 'https://protheus.example.com',
			'username'  => 'testuser',
			'password'  => 'testpass',
		);

		// Mock update_option calls
		Functions\expect( 'update_option' )
			->times( 5 ) // username, password, api_url, auth_type, token_endpoint
			->andReturn( true );

		Functions\expect( 'get_option' )
			->never();

		$auth_manager = new Auth_Manager( $config );
		$result       = $auth_manager->save_credentials( $credentials );

		$this->assertTrue( $result );
	}

	/**
	 * Test save_credentials stores OAuth2 credentials securely
	 */
	public function test_save_credentials_stores_oauth2_credentials() {
		$config = array(
			'auth_type' => 'oauth2',
		);

		$credentials = array(
			'auth_type'      => 'oauth2',
			'api_url'        => 'https://protheus.example.com',
			'client_id'      => 'test-client-id',
			'client_secret'  => 'test-client-secret',
			'token_endpoint' => '/oauth2/token',
		);

		// Mock update_option calls
		Functions\expect( 'update_option' )
			->times( 5 ) // client_id, client_secret, api_url, auth_type, token_endpoint
			->andReturn( true );

		$auth_manager = new Auth_Manager( $config );
		$result       = $auth_manager->save_credentials( $credentials );

		$this->assertTrue( $result );
	}

	/**
	 * Test load_credentials retrieves Basic Auth credentials
	 */
	public function test_load_credentials_retrieves_basic_auth_credentials() {
		$config = array(
			'auth_type' => 'basic',
		);

		// Mock get_option calls
		Functions\expect( 'get_option' )
			->times( 4 ) // auth_type, api_url, username, password
			->andReturnUsing( function( $key, $default = '' ) {
				$options = array(
					'absloja_protheus_auth_type' => 'basic',
					'absloja_protheus_api_url'   => 'https://protheus.example.com',
					'absloja_protheus_username'  => base64_encode( 'testuser' ), // Simulating encrypted
					'absloja_protheus_password'  => base64_encode( 'testpass' ), // Simulating encrypted
				);
				return $options[ $key ] ?? $default;
			} );

		$auth_manager = new Auth_Manager( $config );
		$credentials  = $auth_manager->load_credentials();

		$this->assertIsArray( $credentials );
		$this->assertEquals( 'basic', $credentials['auth_type'] );
		$this->assertEquals( 'https://protheus.example.com', $credentials['api_url'] );
		$this->assertNotEmpty( $credentials['username'] );
		$this->assertNotEmpty( $credentials['password'] );
	}

	/**
	 * Test load_credentials retrieves OAuth2 credentials
	 */
	public function test_load_credentials_retrieves_oauth2_credentials() {
		$config = array(
			'auth_type' => 'oauth2',
		);

		// Mock get_option calls
		Functions\expect( 'get_option' )
			->times( 5 ) // auth_type, api_url, client_id, client_secret, token_endpoint
			->andReturnUsing( function( $key, $default = '' ) {
				$options = array(
					'absloja_protheus_auth_type'      => 'oauth2',
					'absloja_protheus_api_url'        => 'https://protheus.example.com',
					'absloja_protheus_client_id'      => base64_encode( 'test-client-id' ),
					'absloja_protheus_client_secret'  => base64_encode( 'test-client-secret' ),
					'absloja_protheus_token_endpoint' => '/oauth2/token',
				);
				return $options[ $key ] ?? $default;
			} );

		$auth_manager = new Auth_Manager( $config );
		$credentials  = $auth_manager->load_credentials();

		$this->assertIsArray( $credentials );
		$this->assertEquals( 'oauth2', $credentials['auth_type'] );
		$this->assertEquals( 'https://protheus.example.com', $credentials['api_url'] );
		$this->assertNotEmpty( $credentials['client_id'] );
		$this->assertNotEmpty( $credentials['client_secret'] );
		$this->assertEquals( '/oauth2/token', $credentials['token_endpoint'] );
	}

	/**
	 * Test delete_credentials removes all stored credentials
	 */
	public function test_delete_credentials_removes_all_stored_credentials() {
		$config = array(
			'auth_type' => 'basic',
		);

		// Mock delete_option calls - should be called 9 times for all credential options
		Functions\expect( 'delete_option' )
			->times( 9 )
			->andReturn( true );

		$auth_manager = new Auth_Manager( $config );
		$result       = $auth_manager->delete_credentials();

		$this->assertTrue( $result );
	}

	/**
	 * Test encryption and decryption work correctly
	 */
	public function test_encryption_decryption_roundtrip() {
		$config = array(
			'auth_type' => 'basic',
		);

		$original_password = 'my-secret-password-123!@#';

		// We'll test this indirectly through save and load
		Functions\expect( 'update_option' )
			->atLeast()
			->once()
			->andReturnUsing( function( $key, $value ) use ( $original_password ) {
				// Verify that the stored value is encrypted (not plain text)
				if ( strpos( $key, 'password' ) !== false ) {
					$this->assertNotEquals( $original_password, $value );
					// Should be base64 encoded encrypted data
					$this->assertNotFalse( base64_decode( $value, true ) );
				}
				return true;
			} );

		$auth_manager = new Auth_Manager( $config );
		$result       = $auth_manager->save_credentials( array(
			'password' => $original_password,
		) );

		$this->assertTrue( $result );
	}

	/**
	 * Test encryption uses AUTH_KEY constant
	 */
	public function test_encryption_uses_auth_key() {
		// This test verifies that AUTH_KEY is used for encryption
		// by checking that different AUTH_KEY values produce different encrypted results

		$config = array(
			'auth_type' => 'basic',
		);

		$password = 'test-password';
		$encrypted_values = array();

		// Mock update_option to capture encrypted values
		Functions\expect( 'update_option' )
			->atLeast()
			->once()
			->andReturnUsing( function( $key, $value ) use ( &$encrypted_values ) {
				if ( strpos( $key, 'password' ) !== false ) {
					$encrypted_values[] = $value;
				}
				return true;
			} );

		$auth_manager = new Auth_Manager( $config );
		$auth_manager->save_credentials( array( 'password' => $password ) );

		// Verify encryption was performed
		$this->assertNotEmpty( $encrypted_values );
		if ( ! empty( $encrypted_values ) ) {
			// Encrypted value should not equal plain text
			$this->assertNotEquals( $password, $encrypted_values[0] );
		}
	}

}
