<?php
/**
 * Property-based tests for Protheus_Client
 *
 * Tests correctness properties related to HTTP client functionality.
 *
 * @package ABSLoja\ProtheusConnector\Tests\Property
 */

namespace ABSLoja\ProtheusConnector\Tests\Property;

use ABSLoja\ProtheusConnector\API\Protheus_Client;
use ABSLoja\ProtheusConnector\Modules\Auth_Manager;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

/**
 * Protheus_Client property-based tests
 */
class ProtheusClientPropertiesTest extends TestCase {

	/**
	 * Set up test environment
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Define WordPress constants if not defined
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/var/www/html/' );
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
	 * Feature: absloja-protheus-connector, Property 34: Authentication Headers Inclusion
	 *
	 * For any API request made to Protheus, the Auth_Manager should include
	 * appropriate authentication headers (Basic Auth or OAuth2 Bearer token).
	 *
	 * Validates: Requirements 7.4
	 */
	public function test_authentication_headers_are_included_in_all_requests() {
		// Run property test with multiple iterations
		$iterations = 100;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				// Generate random request parameters
				$request_type = $this->generate_random_request_type();
				$endpoint = $this->generate_random_endpoint();
				$auth_headers = $this->generate_random_auth_headers();
				$request_data = $this->generate_random_request_data();

				// Track what headers are sent in the HTTP request
				$captured_headers = null;

				// Mock Auth_Manager to return specific headers
				$auth_manager = $this->createMock( Auth_Manager::class );
				$auth_manager->method( 'get_auth_headers' )
					->willReturn( $auth_headers );

				// Create Protheus_Client instance
				$client = new Protheus_Client(
					$auth_manager,
					'https://api.protheus.example.com',
					30
				);

				// Mock WordPress HTTP functions to capture headers
				if ( $request_type === 'POST' ) {
					Functions\when( 'wp_remote_post' )->alias(
						function( $url, $args ) use ( &$captured_headers ) {
							$captured_headers = $args['headers'] ?? [];
							// Return a successful mock response
							return [
								'response' => [ 'code' => 200 ],
								'body' => json_encode( [ 'success' => true ] ),
							];
						}
					);
				} else {
					Functions\when( 'wp_remote_get' )->alias(
						function( $url, $args ) use ( &$captured_headers ) {
							$captured_headers = $args['headers'] ?? [];
							// Return a successful mock response
							return [
								'response' => [ 'code' => 200 ],
								'body' => json_encode( [ 'success' => true ] ),
							];
						}
					);
				}

				// Mock other WordPress functions
				Functions\when( 'wp_json_encode' )->alias( function( $data ) {
					return json_encode( $data );
				} );

				Functions\when( 'trailingslashit' )->alias( function( $string ) {
					return rtrim( $string, '/' ) . '/';
				} );

				Functions\when( 'ltrim' )->alias( function( $string, $chars ) {
					return ltrim( $string, $chars );
				} );

				Functions\when( 'add_query_arg' )->alias( function( $params, $url ) {
					$query = http_build_query( $params );
					$separator = strpos( $url, '?' ) === false ? '?' : '&';
					return $url . $separator . $query;
				} );

				Functions\when( 'is_wp_error' )->alias( function( $thing ) {
					return $thing instanceof \WP_Error;
				} );

				Functions\when( 'wp_remote_retrieve_response_code' )->alias( function( $response ) {
					return $response['response']['code'] ?? 0;
				} );

				Functions\when( 'wp_remote_retrieve_body' )->alias( function( $response ) {
					return $response['body'] ?? '';
				} );

				// Make the request
				if ( $request_type === 'POST' ) {
					$client->post( $endpoint, $request_data );
				} else {
					$client->get( $endpoint, $request_data );
				}

				// Verify that authentication headers were included
				$this->assertNotNull(
					$captured_headers,
					"Headers should be captured from HTTP request (iteration $i)"
				);

				// Verify that all auth headers from Auth_Manager are present in the request
				foreach ( $auth_headers as $header_name => $header_value ) {
					$this->assertArrayHasKey(
						$header_name,
						$captured_headers,
						"Authentication header '$header_name' should be included in request (iteration $i, $request_type)"
					);

					$this->assertEquals(
						$header_value,
						$captured_headers[ $header_name ],
						"Authentication header '$header_name' should have correct value (iteration $i, $request_type)"
					);
				}

				// Verify that at least one authentication header is present
				$this->assertNotEmpty(
					$auth_headers,
					"Auth_Manager should provide at least one authentication header (iteration $i)"
				);

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
	 * Feature: absloja-protheus-connector, Property 34: Authentication Headers Inclusion
	 *
	 * Verifies that requests are not made when Auth_Manager returns empty headers.
	 *
	 * Validates: Requirements 7.4
	 */
	public function test_requests_fail_when_auth_headers_are_empty() {
		$iterations = 50;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				$request_type = $this->generate_random_request_type();
				$endpoint = $this->generate_random_endpoint();
				$request_data = $this->generate_random_request_data();

				// Mock Auth_Manager to return empty headers
				$auth_manager = $this->createMock( Auth_Manager::class );
				$auth_manager->method( 'get_auth_headers' )
					->willReturn( [] );

				$client = new Protheus_Client(
					$auth_manager,
					'https://api.protheus.example.com',
					30
				);

				// Mock WordPress functions
				Functions\when( 'trailingslashit' )->alias( function( $string ) {
					return rtrim( $string, '/' ) . '/';
				} );

				Functions\when( 'ltrim' )->alias( function( $string, $chars ) {
					return ltrim( $string, $chars );
				} );

				Functions\when( 'add_query_arg' )->alias( function( $params, $url ) {
					$query = http_build_query( $params );
					$separator = strpos( $url, '?' ) === false ? '?' : '&';
					return $url . $separator . $query;
				} );

				Functions\when( 'wp_json_encode' )->alias( function( $data ) {
					return json_encode( $data );
				} );

				// Make the request
				if ( $request_type === 'POST' ) {
					$result = $client->post( $endpoint, $request_data );
				} else {
					$result = $client->get( $endpoint, $request_data );
				}

				// Verify that the request failed due to missing auth headers
				$this->assertFalse(
					$result['success'],
					"Request should fail when auth headers are empty (iteration $i, $request_type)"
				);

				$this->assertStringContainsString(
					'Authentication',
					$result['error'],
					"Error message should mention authentication (iteration $i, $request_type)"
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
	 * Feature: absloja-protheus-connector, Property 34: Authentication Headers Inclusion
	 *
	 * Verifies that both Basic Auth and OAuth2 Bearer token headers are properly included.
	 *
	 * Validates: Requirements 7.4
	 */
	public function test_both_basic_auth_and_oauth2_headers_are_supported() {
		$iterations = 50;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				$request_type = $this->generate_random_request_type();
				$endpoint = $this->generate_random_endpoint();
				$request_data = $this->generate_random_request_data();

				// Alternate between Basic Auth and OAuth2
				$auth_type = $i % 2 === 0 ? 'basic' : 'oauth2';
				$auth_headers = $this->generate_auth_headers_by_type( $auth_type );

				$captured_headers = null;

				$auth_manager = $this->createMock( Auth_Manager::class );
				$auth_manager->method( 'get_auth_headers' )
					->willReturn( $auth_headers );

				$client = new Protheus_Client(
					$auth_manager,
					'https://api.protheus.example.com',
					30
				);

				// Mock WordPress HTTP functions
				if ( $request_type === 'POST' ) {
					Functions\when( 'wp_remote_post' )->alias(
						function( $url, $args ) use ( &$captured_headers ) {
							$captured_headers = $args['headers'] ?? [];
							return [
								'response' => [ 'code' => 200 ],
								'body' => json_encode( [ 'success' => true ] ),
							];
						}
					);
				} else {
					Functions\when( 'wp_remote_get' )->alias(
						function( $url, $args ) use ( &$captured_headers ) {
							$captured_headers = $args['headers'] ?? [];
							return [
								'response' => [ 'code' => 200 ],
								'body' => json_encode( [ 'success' => true ] ),
							];
						}
					);
				}

				// Mock other WordPress functions
				Functions\when( 'wp_json_encode' )->alias( function( $data ) {
					return json_encode( $data );
				} );

				Functions\when( 'trailingslashit' )->alias( function( $string ) {
					return rtrim( $string, '/' ) . '/';
				} );

				Functions\when( 'ltrim' )->alias( function( $string, $chars ) {
					return ltrim( $string, $chars );
				} );

				Functions\when( 'add_query_arg' )->alias( function( $params, $url ) {
					$query = http_build_query( $params );
					$separator = strpos( $url, '?' ) === false ? '?' : '&';
					return $url . $separator . $query;
				} );

				Functions\when( 'is_wp_error' )->alias( function( $thing ) {
					return $thing instanceof \WP_Error;
				} );

				Functions\when( 'wp_remote_retrieve_response_code' )->alias( function( $response ) {
					return $response['response']['code'] ?? 0;
				} );

				Functions\when( 'wp_remote_retrieve_body' )->alias( function( $response ) {
					return $response['body'] ?? '';
				} );

				// Make the request
				if ( $request_type === 'POST' ) {
					$client->post( $endpoint, $request_data );
				} else {
					$client->get( $endpoint, $request_data );
				}

				// Verify Authorization header is present
				$this->assertArrayHasKey(
					'Authorization',
					$captured_headers,
					"Authorization header should be present for $auth_type (iteration $i, $request_type)"
				);

				// Verify the format matches the auth type
				if ( $auth_type === 'basic' ) {
					$this->assertStringStartsWith(
						'Basic ',
						$captured_headers['Authorization'],
						"Basic Auth header should start with 'Basic ' (iteration $i, $request_type)"
					);
				} else {
					$this->assertStringStartsWith(
						'Bearer ',
						$captured_headers['Authorization'],
						"OAuth2 header should start with 'Bearer ' (iteration $i, $request_type)"
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
	 * Generate random request type (GET or POST)
	 *
	 * @return string
	 */
	private function generate_random_request_type(): string {
		$types = [ 'GET', 'POST' ];
		return $types[ array_rand( $types ) ];
	}

	/**
	 * Generate random API endpoint
	 *
	 * @return string
	 */
	private function generate_random_endpoint(): string {
		$endpoints = [
			'/api/v1/orders',
			'/api/v1/customers',
			'/api/v1/products',
			'/api/v1/stock',
			'/api/v1/orders/' . rand( 1, 1000 ),
			'/api/v1/customers/' . rand( 1, 1000 ),
		];

		return $endpoints[ array_rand( $endpoints ) ];
	}

	/**
	 * Generate random authentication headers
	 *
	 * @return array
	 */
	private function generate_random_auth_headers(): array {
		$auth_types = [ 'basic', 'oauth2' ];
		$auth_type = $auth_types[ array_rand( $auth_types ) ];

		return $this->generate_auth_headers_by_type( $auth_type );
	}

	/**
	 * Generate authentication headers by type
	 *
	 * @param string $auth_type Authentication type (basic or oauth2).
	 * @return array
	 */
	private function generate_auth_headers_by_type( string $auth_type ): array {
		if ( $auth_type === 'basic' ) {
			$username = 'user_' . $this->generate_random_string( rand( 5, 15 ) );
			$password = $this->generate_random_string( rand( 10, 30 ) );
			$credentials = base64_encode( "$username:$password" );

			return [
				'Authorization' => 'Basic ' . $credentials,
				'Content-Type' => 'application/json',
			];
		} else {
			$token = $this->generate_random_string( rand( 40, 80 ) );

			return [
				'Authorization' => 'Bearer ' . $token,
				'Content-Type' => 'application/json',
			];
		}
	}

	/**
	 * Generate random request data
	 *
	 * @return array
	 */
	private function generate_random_request_data(): array {
		$data_types = [
			// Empty data
			[],
			// Simple data
			[ 'id' => rand( 1, 1000 ) ],
			// Complex data
			[
				'customer' => [
					'name' => 'Customer ' . rand( 1, 100 ),
					'email' => 'customer' . rand( 1, 100 ) . '@example.com',
				],
				'items' => [
					[ 'sku' => 'PROD' . rand( 1, 100 ), 'quantity' => rand( 1, 10 ) ],
				],
			],
			// Query parameters for GET
			[
				'page' => rand( 1, 10 ),
				'limit' => rand( 10, 100 ),
			],
		];

		return $data_types[ array_rand( $data_types ) ];
	}

	/**
	 * Generate random alphanumeric string
	 *
	 * @param int $length String length.
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
}
