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
}
