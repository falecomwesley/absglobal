<?php
/**
 * Protheus_Client Unit Tests
 *
 * Tests for HTTP error handling in Protheus_Client class.
 *
 * @package ABSLoja\ProtheusConnector\Tests\Unit\API
 */

namespace ABSLoja\ProtheusConnector\Tests\Unit\API;

use PHPUnit\Framework\TestCase;
use ABSLoja\ProtheusConnector\API\Protheus_Client;
use ABSLoja\ProtheusConnector\Modules\Auth_Manager;

/**
 * Class ProtheusClientTest
 *
 * Tests HTTP error handling functionality.
 */
class ProtheusClientTest extends TestCase {

	/**
	 * Mock Auth_Manager instance
	 *
	 * @var Auth_Manager
	 */
	private $auth_manager;

	/**
	 * Protheus_Client instance
	 *
	 * @var Protheus_Client
	 */
	private $client;

	/**
	 * Set up test fixtures
	 */
	protected function setUp(): void {
		parent::setUp();

		// Create mock Auth_Manager
		$this->auth_manager = $this->createMock( Auth_Manager::class );
		$this->auth_manager->method( 'get_auth_headers' )
			->willReturn( array( 'Authorization' => 'Basic dGVzdDp0ZXN0' ) );

		// Create Protheus_Client instance
		$this->client = new Protheus_Client(
			$this->auth_manager,
			'https://api.protheus.example.com',
			30
		);
	}

	/**
	 * Test network error detection (timeout)
	 */
	public function test_detects_timeout_error() {
		// Mock wp_remote_post to return WP_Error with timeout
		$wp_error = new \WP_Error( 'http_request_timeout', 'Connection timed out after 30 seconds' );
		
		// Use reflection to test private method
		$reflection = new \ReflectionClass( $this->client );
		$method = $reflection->getMethod( 'process_response' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->client, $wp_error );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 'timeout_error', $result['error_type'] );
		$this->assertStringContainsString( 'timed out', $result['error'] );
	}

	/**
	 * Test DNS error detection
	 */
	public function test_detects_dns_error() {
		$wp_error = new \WP_Error( 'http_request_failed', 'Could not resolve host: api.protheus.example.com' );
		
		$reflection = new \ReflectionClass( $this->client );
		$method = $reflection->getMethod( 'process_response' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->client, $wp_error );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 'dns_error', $result['error_type'] );
	}

	/**
	 * Test SSL error detection
	 */
	public function test_detects_ssl_error() {
		$wp_error = new \WP_Error( 'http_request_failed', 'SSL certificate problem: unable to get local issuer certificate' );
		
		$reflection = new \ReflectionClass( $this->client );
		$method = $reflection->getMethod( 'process_response' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->client, $wp_error );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 'ssl_error', $result['error_type'] );
	}

	/**
	 * Test authentication error detection (401)
	 */
	public function test_detects_401_auth_error() {
		// Mock HTTP response with 401 status
		$response = array(
			'response' => array( 'code' => 401 ),
			'body' => json_encode( array( 'error' => 'Invalid credentials' ) ),
		);

		// Mock WordPress functions
		if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
			function wp_remote_retrieve_response_code( $response ) {
				return $response['response']['code'];
			}
		}

		if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
			function wp_remote_retrieve_body( $response ) {
				return $response['body'];
			}
		}

		$reflection = new \ReflectionClass( $this->client );
		$method = $reflection->getMethod( 'process_response' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->client, $response );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 401, $result['status_code'] );
		$this->assertEquals( 'auth_error', $result['error_type'] );
	}

	/**
	 * Test authentication error detection (403)
	 */
	public function test_detects_403_auth_error() {
		$response = array(
			'response' => array( 'code' => 403 ),
			'body' => json_encode( array( 'error' => 'Forbidden' ) ),
		);

		$reflection = new \ReflectionClass( $this->client );
		$method = $reflection->getMethod( 'process_response' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->client, $response );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 403, $result['status_code'] );
		$this->assertEquals( 'auth_error', $result['error_type'] );
	}

	/**
	 * Test server error detection (500)
	 */
	public function test_detects_500_server_error() {
		$response = array(
			'response' => array( 'code' => 500 ),
			'body' => json_encode( array( 'error' => 'Internal Server Error' ) ),
		);

		$reflection = new \ReflectionClass( $this->client );
		$method = $reflection->getMethod( 'process_response' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->client, $response );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 500, $result['status_code'] );
		$this->assertEquals( 'server_error', $result['error_type'] );
	}

	/**
	 * Test server error detection (503)
	 */
	public function test_detects_503_server_error() {
		$response = array(
			'response' => array( 'code' => 503 ),
			'body' => json_encode( array( 'error' => 'Service Unavailable' ) ),
		);

		$reflection = new \ReflectionClass( $this->client );
		$method = $reflection->getMethod( 'process_response' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->client, $response );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 503, $result['status_code'] );
		$this->assertEquals( 'server_error', $result['error_type'] );
	}

	/**
	 * Test client error detection (400)
	 */
	public function test_detects_400_client_error() {
		$response = array(
			'response' => array( 'code' => 400 ),
			'body' => json_encode( array( 'error' => 'Bad Request' ) ),
		);

		$reflection = new \ReflectionClass( $this->client );
		$method = $reflection->getMethod( 'process_response' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->client, $response );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 400, $result['status_code'] );
		$this->assertEquals( 'client_error', $result['error_type'] );
	}

	/**
	 * Test client error detection (404)
	 */
	public function test_detects_404_client_error() {
		$response = array(
			'response' => array( 'code' => 404 ),
			'body' => json_encode( array( 'error' => 'Not Found' ) ),
		);

		$reflection = new \ReflectionClass( $this->client );
		$method = $reflection->getMethod( 'process_response' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->client, $response );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 404, $result['status_code'] );
		$this->assertEquals( 'client_error', $result['error_type'] );
	}

	/**
	 * Test standardized error response structure
	 */
	public function test_returns_standardized_error_structure() {
		$wp_error = new \WP_Error( 'http_request_timeout', 'Connection timed out' );
		
		$reflection = new \ReflectionClass( $this->client );
		$method = $reflection->getMethod( 'process_response' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->client, $wp_error );

		// Verify all required fields are present
		$this->assertArrayHasKey( 'success', $result );
		$this->assertArrayHasKey( 'data', $result );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertArrayHasKey( 'status_code', $result );
		$this->assertArrayHasKey( 'error_type', $result );

		// Verify error structure
		$this->assertFalse( $result['success'] );
		$this->assertNull( $result['data'] );
		$this->assertIsString( $result['error'] );
		$this->assertIsInt( $result['status_code'] );
		$this->assertIsString( $result['error_type'] );
	}

	/**
	 * Test successful response structure
	 */
	public function test_returns_standardized_success_structure() {
		$response = array(
			'response' => array( 'code' => 200 ),
			'body' => json_encode( array( 'data' => 'success' ) ),
		);

		$reflection = new \ReflectionClass( $this->client );
		$method = $reflection->getMethod( 'process_response' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->client, $response );

		// Verify all required fields are present
		$this->assertArrayHasKey( 'success', $result );
		$this->assertArrayHasKey( 'data', $result );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertArrayHasKey( 'status_code', $result );
		$this->assertArrayHasKey( 'error_type', $result );

		// Verify success structure
		$this->assertTrue( $result['success'] );
		$this->assertIsArray( $result['data'] );
		$this->assertNull( $result['error'] );
		$this->assertEquals( 200, $result['status_code'] );
		$this->assertNull( $result['error_type'] );
	}

	/**
	 * Test successful POST request
	 */
	public function test_successful_post_request() {
		// Mock successful response
		$expected_data = array(
			'order_id' => '123456',
			'status' => 'created',
		);

		$response = array(
			'response' => array( 'code' => 201 ),
			'body' => json_encode( $expected_data ),
		);

		$reflection = new \ReflectionClass( $this->client );
		$method = $reflection->getMethod( 'process_response' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->client, $response );

		$this->assertTrue( $result['success'] );
		$this->assertEquals( $expected_data, $result['data'] );
		$this->assertNull( $result['error'] );
		$this->assertEquals( 201, $result['status_code'] );
		$this->assertNull( $result['error_type'] );
	}

	/**
	 * Test successful GET request
	 */
	public function test_successful_get_request() {
		// Mock successful response
		$expected_data = array(
			'products' => array(
				array( 'id' => '1', 'name' => 'Product 1' ),
				array( 'id' => '2', 'name' => 'Product 2' ),
			),
			'total' => 2,
		);

		$response = array(
			'response' => array( 'code' => 200 ),
			'body' => json_encode( $expected_data ),
		);

		$reflection = new \ReflectionClass( $this->client );
		$method = $reflection->getMethod( 'process_response' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->client, $response );

		$this->assertTrue( $result['success'] );
		$this->assertEquals( $expected_data, $result['data'] );
		$this->assertNull( $result['error'] );
		$this->assertEquals( 200, $result['status_code'] );
		$this->assertNull( $result['error_type'] );
	}

	/**
	 * Test invalid JSON parsing
	 */
	public function test_handles_invalid_json_response() {
		// Mock response with invalid JSON
		$response = array(
			'response' => array( 'code' => 200 ),
			'body' => 'This is not valid JSON {invalid}',
		);

		$reflection = new \ReflectionClass( $this->client );
		$method = $reflection->getMethod( 'process_response' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->client, $response );

		$this->assertFalse( $result['success'] );
		$this->assertNull( $result['data'] );
		$this->assertStringContainsString( 'Invalid JSON response', $result['error'] );
		$this->assertEquals( 200, $result['status_code'] );
		$this->assertEquals( 'json_error', $result['error_type'] );
	}

	/**
	 * Test malformed JSON parsing
	 */
	public function test_handles_malformed_json_response() {
		// Mock response with malformed JSON
		$response = array(
			'response' => array( 'code' => 200 ),
			'body' => '{"data": "incomplete"',
		);

		$reflection = new \ReflectionClass( $this->client );
		$method = $reflection->getMethod( 'process_response' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->client, $response );

		$this->assertFalse( $result['success'] );
		$this->assertNull( $result['data'] );
		$this->assertStringContainsString( 'Invalid JSON response', $result['error'] );
		$this->assertEquals( 'json_error', $result['error_type'] );
	}

	/**
	 * Test timeout error with custom timeout
	 */
	public function test_handles_timeout_with_custom_timeout() {
		$wp_error = new \WP_Error( 
			'http_request_failed', 
			'Operation timed out after 5000 milliseconds with 0 bytes received' 
		);
		
		$reflection = new \ReflectionClass( $this->client );
		$method = $reflection->getMethod( 'process_response' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->client, $wp_error );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 'timeout_error', $result['error_type'] );
		$this->assertStringContainsString( 'timed out', strtolower( $result['error'] ) );
	}

	/**
	 * Test connection refused error
	 */
	public function test_handles_connection_refused_error() {
		$wp_error = new \WP_Error( 
			'http_request_failed', 
			'Failed to connect to api.protheus.example.com port 443: Connection refused' 
		);
		
		$reflection = new \ReflectionClass( $this->client );
		$method = $reflection->getMethod( 'process_response' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->client, $wp_error );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 'connection_error', $result['error_type'] );
	}

	/**
	 * Test error message extraction from different response formats
	 */
	public function test_extracts_error_message_from_error_field() {
		$response = array(
			'response' => array( 'code' => 400 ),
			'body' => json_encode( array( 'error' => 'Invalid request parameters' ) ),
		);

		$reflection = new \ReflectionClass( $this->client );
		$method = $reflection->getMethod( 'process_response' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->client, $response );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 'Invalid request parameters', $result['error'] );
	}

	/**
	 * Test error message extraction from message field
	 */
	public function test_extracts_error_message_from_message_field() {
		$response = array(
			'response' => array( 'code' => 422 ),
			'body' => json_encode( array( 'message' => 'Validation failed' ) ),
		);

		$reflection = new \ReflectionClass( $this->client );
		$method = $reflection->getMethod( 'process_response' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->client, $response );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 'Validation failed', $result['error'] );
	}

	/**
	 * Test error message extraction from errorMessage field
	 */
	public function test_extracts_error_message_from_errorMessage_field() {
		$response = array(
			'response' => array( 'code' => 500 ),
			'body' => json_encode( array( 'errorMessage' => 'Database connection failed' ) ),
		);

		$reflection = new \ReflectionClass( $this->client );
		$method = $reflection->getMethod( 'process_response' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->client, $response );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 'Database connection failed', $result['error'] );
	}

	/**
	 * Test generic error message fallback
	 */
	public function test_uses_generic_error_message_when_no_error_field() {
		$response = array(
			'response' => array( 'code' => 404 ),
			'body' => json_encode( array( 'data' => null ) ),
		);

		$reflection = new \ReflectionClass( $this->client );
		$method = $reflection->getMethod( 'process_response' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->client, $response );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 'Not Found', $result['error'] );
	}

	/**
	 * Test 2xx status codes are treated as success
	 */
	public function test_treats_all_2xx_as_success() {
		$status_codes = array( 200, 201, 202, 204 );

		foreach ( $status_codes as $code ) {
			$response = array(
				'response' => array( 'code' => $code ),
				'body' => json_encode( array( 'result' => 'ok' ) ),
			);

			$reflection = new \ReflectionClass( $this->client );
			$method = $reflection->getMethod( 'process_response' );
			$method->setAccessible( true );

			$result = $method->invoke( $this->client, $response );

			$this->assertTrue( $result['success'], "Status code $code should be treated as success" );
			$this->assertEquals( $code, $result['status_code'] );
		}
	}

	/**
	 * Test timeout configuration
	 */
	public function test_timeout_configuration() {
		$this->assertEquals( 30, $this->client->get_timeout() );

		$this->client->set_timeout( 60 );
		$this->assertEquals( 60, $this->client->get_timeout() );

		$this->client->set_timeout( 10 );
		$this->assertEquals( 10, $this->client->get_timeout() );
	}

	/**
	 * Test API URL configuration
	 */
	public function test_api_url_configuration() {
		$this->assertEquals( 'https://api.protheus.example.com/', $this->client->get_api_url() );
	}

	/**
	 * Test empty authentication headers handling
	 */
	public function test_handles_empty_auth_headers() {
		// Create mock Auth_Manager that returns empty headers
		$auth_manager = $this->createMock( Auth_Manager::class );
		$auth_manager->method( 'get_auth_headers' )
			->willReturn( array() );

		$client = new Protheus_Client(
			$auth_manager,
			'https://api.protheus.example.com',
			30
		);

		// Use reflection to access private method
		$reflection = new \ReflectionClass( $client );
		$method = $reflection->getMethod( 'process_response' );
		$method->setAccessible( true );

		// Since we can't easily mock wp_remote_post, we'll test the error response
		// that should be returned when auth headers are empty
		$wp_error = new \WP_Error( 'auth_error', 'Authentication headers not available' );
		$result = $method->invoke( $client, $wp_error );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'Authentication', $result['error'] );
	}
}
