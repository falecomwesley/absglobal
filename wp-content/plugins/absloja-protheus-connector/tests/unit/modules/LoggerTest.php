<?php
/**
 * Logger Unit Tests
 *
 * @package ABSLoja\ProtheusConnector\Tests\Unit\Modules
 */

namespace ABSLoja\ProtheusConnector\Tests\Unit\Modules;

use ABSLoja\ProtheusConnector\Modules\Logger;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

/**
 * Class LoggerTest
 *
 * Unit tests for Logger class.
 */
class LoggerTest extends TestCase {

	/**
	 * Set up test environment
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * Tear down test environment
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		Mockery::close();
		parent::tearDown();
	}

	/**
	 * Test log_api_request creates log entry with correct data
	 */
	public function test_log_api_request_creates_log_entry() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->insert_id = 123;

		$endpoint = '/api/v1/orders';
		$payload  = array( 'order_id' => 456 );
		$response = array( 'success' => true, 'protheus_id' => '789' );
		$duration = 1.234;

		Functions\expect( 'current_time' )
			->once()
			->with( 'mysql' )
			->andReturn( '2024-01-15 10:30:00' );

		Functions\expect( 'wp_json_encode' )
			->twice()
			->andReturnUsing( function( $data ) {
				return json_encode( $data );
			} );

		$wpdb->shouldReceive( 'insert' )
			->once()
			->with( 'wp_absloja_logs', Mockery::on( function( $data ) use ( $endpoint, $duration ) {
				$this->assertEquals( '2024-01-15 10:30:00', $data['timestamp'] );
				$this->assertEquals( 'api_request', $data['type'] );
				$this->assertEquals( $endpoint, $data['operation'] );
				$this->assertEquals( 'success', $data['status'] );
				$this->assertStringContainsString( $endpoint, $data['message'] );
				$this->assertNotNull( $data['payload'] );
				$this->assertNotNull( $data['response'] );
				$this->assertEquals( $duration, $data['duration'] );
				$this->assertNull( $data['error_trace'] );
				$this->assertNull( $data['context'] );
				return true;
			} ) )
			->andReturn( 1 );

		$logger = new Logger();
		$result = $logger->log_api_request( $endpoint, $payload, $response, $duration );

		$this->assertEquals( 123, $result );
	}

	/**
	 * Test log_api_request handles WP_Error response
	 */
	public function test_log_api_request_handles_wp_error() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->insert_id = 124;

		$endpoint = '/api/v1/orders';
		$payload  = array( 'order_id' => 456 );
		$response = new \WP_Error( 'http_error', 'Connection failed' );
		$duration = 0.5;

		Functions\expect( 'current_time' )
			->once()
			->andReturn( '2024-01-15 10:30:00' );

		Functions\expect( 'wp_json_encode' )
			->twice()
			->andReturnUsing( function( $data ) {
				return json_encode( $data );
			} );

		Functions\expect( 'is_wp_error' )
			->once()
			->with( $response )
			->andReturn( true );

		$wpdb->shouldReceive( 'insert' )
			->once()
			->with( 'wp_absloja_logs', Mockery::on( function( $data ) {
				$this->assertEquals( 'error', $data['status'] );
				return true;
			} ) )
			->andReturn( 1 );

		$logger = new Logger();
		$result = $logger->log_api_request( $endpoint, $payload, $response, $duration );

		$this->assertEquals( 124, $result );
	}

	/**
	 * Test log_api_request handles HTTP error status codes
	 */
	public function test_log_api_request_handles_http_error_codes() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->insert_id = 125;

		$endpoint = '/api/v1/orders';
		$payload  = array( 'order_id' => 456 );
		$response = array( 'response' => array( 'code' => 401 ), 'body' => 'Unauthorized' );
		$duration = 0.3;

		Functions\expect( 'current_time' )
			->once()
			->andReturn( '2024-01-15 10:30:00' );

		Functions\expect( 'wp_json_encode' )
			->twice()
			->andReturnUsing( function( $data ) {
				return json_encode( $data );
			} );

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		$wpdb->shouldReceive( 'insert' )
			->once()
			->with( 'wp_absloja_logs', Mockery::on( function( $data ) {
				$this->assertEquals( 'error', $data['status'] );
				return true;
			} ) )
			->andReturn( 1 );

		$logger = new Logger();
		$result = $logger->log_api_request( $endpoint, $payload, $response, $duration );

		$this->assertEquals( 125, $result );
	}

	/**
	 * Test log_webhook creates log entry with correct data
	 */
	public function test_log_webhook_creates_log_entry() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->insert_id = 126;

		$type     = 'order_status';
		$payload  = array( 'order_id' => '123', 'status' => 'approved' );
		$response = array( 'success' => true );

		Functions\expect( 'current_time' )
			->once()
			->with( 'mysql' )
			->andReturn( '2024-01-15 10:35:00' );

		Functions\expect( 'wp_json_encode' )
			->twice()
			->andReturnUsing( function( $data ) {
				return json_encode( $data );
			} );

		$wpdb->shouldReceive( 'insert' )
			->once()
			->with( 'wp_absloja_logs', Mockery::on( function( $data ) use ( $type ) {
				$this->assertEquals( '2024-01-15 10:35:00', $data['timestamp'] );
				$this->assertEquals( 'webhook', $data['type'] );
				$this->assertEquals( $type, $data['operation'] );
				$this->assertEquals( 'success', $data['status'] );
				$this->assertStringContainsString( $type, $data['message'] );
				$this->assertNotNull( $data['payload'] );
				$this->assertNotNull( $data['response'] );
				$this->assertNull( $data['duration'] );
				$this->assertNull( $data['error_trace'] );
				$this->assertNull( $data['context'] );
				return true;
			} ) )
			->andReturn( 1 );

		$logger = new Logger();
		$result = $logger->log_webhook( $type, $payload, $response );

		$this->assertEquals( 126, $result );
	}

	/**
	 * Test log_webhook handles string response
	 */
	public function test_log_webhook_handles_string_response() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->insert_id = 127;

		$type     = 'stock';
		$payload  = array( 'sku' => 'PROD001', 'quantity' => 50 );
		$response = 'OK';

		Functions\expect( 'current_time' )
			->once()
			->andReturn( '2024-01-15 10:40:00' );

		Functions\expect( 'wp_json_encode' )
			->once()
			->andReturnUsing( function( $data ) {
				return json_encode( $data );
			} );

		$wpdb->shouldReceive( 'insert' )
			->once()
			->with( 'wp_absloja_logs', Mockery::on( function( $data ) {
				$this->assertEquals( 'OK', $data['response'] );
				return true;
			} ) )
			->andReturn( 1 );

		$logger = new Logger();
		$result = $logger->log_webhook( $type, $payload, $response );

		$this->assertEquals( 127, $result );
	}

	/**
	 * Test log_sync_operation creates log entry for successful sync
	 */
	public function test_log_sync_operation_creates_log_entry_for_success() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->insert_id = 128;

		$type    = 'order_sync';
		$data    = array(
			'order_id' => 456,
			'duration' => 2.5,
			'context'  => array( 'customer_id' => 789 ),
		);
		$success = true;

		Functions\expect( 'current_time' )
			->once()
			->andReturn( '2024-01-15 10:45:00' );

		Functions\expect( 'wp_json_encode' )
			->twice()
			->andReturnUsing( function( $data ) {
				return json_encode( $data );
			} );

		$wpdb->shouldReceive( 'insert' )
			->once()
			->with( 'wp_absloja_logs', Mockery::on( function( $log_data ) use ( $type ) {
				$this->assertEquals( '2024-01-15 10:45:00', $log_data['timestamp'] );
				$this->assertEquals( 'sync', $log_data['type'] );
				$this->assertEquals( $type, $log_data['operation'] );
				$this->assertEquals( 'success', $log_data['status'] );
				$this->assertStringContainsString( 'completed', $log_data['message'] );
				$this->assertNotNull( $log_data['payload'] );
				$this->assertNull( $log_data['response'] );
				$this->assertEquals( 2.5, $log_data['duration'] );
				$this->assertNull( $log_data['error_trace'] );
				$this->assertNotNull( $log_data['context'] );
				return true;
			} ) )
			->andReturn( 1 );

		$logger = new Logger();
		$result = $logger->log_sync_operation( $type, $data, $success );

		$this->assertEquals( 128, $result );
	}

	/**
	 * Test log_sync_operation creates log entry for failed sync
	 */
	public function test_log_sync_operation_creates_log_entry_for_failure() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->insert_id = 129;

		$type    = 'product_sync';
		$data    = array( 'sku' => 'PROD001' );
		$success = false;
		$error   = 'Product not found in Protheus';

		Functions\expect( 'current_time' )
			->once()
			->andReturn( '2024-01-15 10:50:00' );

		Functions\expect( 'wp_json_encode' )
			->once()
			->andReturnUsing( function( $data ) {
				return json_encode( $data );
			} );

		$wpdb->shouldReceive( 'insert' )
			->once()
			->with( 'wp_absloja_logs', Mockery::on( function( $log_data ) use ( $error ) {
				$this->assertEquals( 'error', $log_data['status'] );
				$this->assertStringContainsString( 'failed', $log_data['message'] );
				$this->assertEquals( $error, $log_data['error_trace'] );
				return true;
			} ) )
			->andReturn( 1 );

		$logger = new Logger();
		$result = $logger->log_sync_operation( $type, $data, $success, $error );

		$this->assertEquals( 129, $result );
	}

	/**
	 * Test log_error creates log entry with exception details
	 */
	public function test_log_error_creates_log_entry_with_exception() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->insert_id = 130;

		$message   = 'Failed to connect to Protheus API';
		$exception = new \Exception( 'Connection timeout' );
		$context   = array(
			'operation' => 'order_sync',
			'payload'   => array( 'order_id' => 456 ),
			'response'  => array( 'error' => 'timeout' ),
		);

		Functions\expect( 'current_time' )
			->once()
			->andReturn( '2024-01-15 10:55:00' );

		Functions\expect( 'wp_json_encode' )
			->times( 3 )
			->andReturnUsing( function( $data ) {
				return json_encode( $data );
			} );

		$wpdb->shouldReceive( 'insert' )
			->once()
			->with( 'wp_absloja_logs', Mockery::on( function( $data ) use ( $message ) {
				$this->assertEquals( '2024-01-15 10:55:00', $data['timestamp'] );
				$this->assertEquals( 'error', $data['type'] );
				$this->assertEquals( 'order_sync', $data['operation'] );
				$this->assertEquals( 'error', $data['status'] );
				$this->assertEquals( $message, $data['message'] );
				$this->assertNotNull( $data['payload'] );
				$this->assertNotNull( $data['response'] );
				$this->assertNull( $data['duration'] );
				$this->assertNotEmpty( $data['error_trace'] );
				$this->assertNotNull( $data['context'] );
				return true;
			} ) )
			->andReturn( 1 );

		$logger = new Logger();
		$result = $logger->log_error( $message, $exception, $context );

		$this->assertEquals( 130, $result );
	}

	/**
	 * Test log_error handles minimal context
	 */
	public function test_log_error_handles_minimal_context() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->insert_id = 131;

		$message   = 'Unknown error occurred';
		$exception = new \RuntimeException( 'Runtime error' );
		$context   = array();

		Functions\expect( 'current_time' )
			->once()
			->andReturn( '2024-01-15 11:00:00' );

		Functions\expect( 'wp_json_encode' )
			->once()
			->andReturnUsing( function( $data ) {
				return json_encode( $data );
			} );

		$wpdb->shouldReceive( 'insert' )
			->once()
			->with( 'wp_absloja_logs', Mockery::on( function( $data ) {
				$this->assertEquals( 'unknown', $data['operation'] );
				$this->assertNull( $data['payload'] );
				$this->assertNull( $data['response'] );
				return true;
			} ) )
			->andReturn( 1 );

		$logger = new Logger();
		$result = $logger->log_error( $message, $exception, $context );

		$this->assertEquals( 131, $result );
	}

	/**
	 * Test log methods return false on database insert failure
	 */
	public function test_log_methods_return_false_on_insert_failure() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		Functions\expect( 'current_time' )
			->once()
			->andReturn( '2024-01-15 11:05:00' );

		Functions\expect( 'wp_json_encode' )
			->twice()
			->andReturnUsing( function( $data ) {
				return json_encode( $data );
			} );

		$wpdb->shouldReceive( 'insert' )
			->once()
			->andReturn( false );

		$logger = new Logger();
		$result = $logger->log_api_request( '/api/v1/test', array(), array(), 1.0 );

		$this->assertFalse( $result );
	}

	/**
	 * Test log_api_request stores payloads as JSON
	 */
	public function test_log_api_request_stores_payloads_as_json() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->insert_id = 132;

		$endpoint = '/api/v1/orders';
		$payload  = array(
			'order_id' => 456,
			'items'    => array(
				array( 'sku' => 'PROD001', 'qty' => 2 ),
				array( 'sku' => 'PROD002', 'qty' => 1 ),
			),
		);
		$response = array( 'success' => true );
		$duration = 1.5;

		Functions\expect( 'current_time' )
			->once()
			->andReturn( '2024-01-15 11:10:00' );

		Functions\expect( 'wp_json_encode' )
			->twice()
			->andReturnUsing( function( $data ) {
				return json_encode( $data );
			} );

		$wpdb->shouldReceive( 'insert' )
			->once()
			->with( 'wp_absloja_logs', Mockery::on( function( $data ) {
				// Verify payload is JSON string
				$this->assertIsString( $data['payload'] );
				$decoded_payload = json_decode( $data['payload'], true );
				$this->assertIsArray( $decoded_payload );
				$this->assertArrayHasKey( 'order_id', $decoded_payload );
				$this->assertArrayHasKey( 'items', $decoded_payload );

				// Verify response is JSON string
				$this->assertIsString( $data['response'] );
				$decoded_response = json_decode( $data['response'], true );
				$this->assertIsArray( $decoded_response );
				$this->assertArrayHasKey( 'success', $decoded_response );

				return true;
			} ) )
			->andReturn( 1 );

		$logger = new Logger();
		$result = $logger->log_api_request( $endpoint, $payload, $response, $duration );

		$this->assertEquals( 132, $result );
	}

	/**
	 * Test log_api_request records duration in seconds
	 */
	public function test_log_api_request_records_duration_in_seconds() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->insert_id = 133;

		$endpoint = '/api/v1/products';
		$payload  = array( 'page' => 1 );
		$response = array( 'products' => array() );
		$duration = 3.14159;

		Functions\expect( 'current_time' )
			->once()
			->andReturn( '2024-01-15 11:15:00' );

		Functions\expect( 'wp_json_encode' )
			->twice()
			->andReturnUsing( function( $data ) {
				return json_encode( $data );
			} );

		$wpdb->shouldReceive( 'insert' )
			->once()
			->with( 'wp_absloja_logs', Mockery::on( function( $data ) use ( $duration ) {
				$this->assertEquals( $duration, $data['duration'] );
				return true;
			} ) )
			->andReturn( 1 );

		$logger = new Logger();
		$result = $logger->log_api_request( $endpoint, $payload, $response, $duration );

		$this->assertEquals( 133, $result );
	}

	/**
	 * Test get_logs returns logs with default pagination
	 */
	public function test_get_logs_returns_logs_with_default_pagination() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$mock_logs = array(
			array(
				'id'          => 1,
				'timestamp'   => '2024-01-15 10:00:00',
				'type'        => 'api_request',
				'operation'   => '/api/v1/orders',
				'status'      => 'success',
				'message'     => 'API request to /api/v1/orders',
				'payload'     => '{"order_id":123}',
				'response'    => '{"success":true}',
				'duration'    => 1.5,
				'error_trace' => null,
				'context'     => null,
			),
		);

		Functions\expect( 'wp_parse_args' )
			->once()
			->andReturnUsing( function( $args, $defaults ) {
				return array_merge( $defaults, $args );
			} );

		Functions\expect( 'absint' )
			->twice()
			->andReturnUsing( function( $value ) {
				return abs( (int) $value );
			} );

		Functions\expect( 'esc_sql' )
			->twice()
			->andReturnUsing( function( $value ) {
				return $value;
			} );

		// Mock count query
		$wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( 1 );

		// Mock main query
		$wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( $mock_logs );

		$logger = new Logger();
		$result = $logger->get_logs();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'logs', $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertArrayHasKey( 'page', $result );
		$this->assertArrayHasKey( 'per_page', $result );
		$this->assertArrayHasKey( 'total_pages', $result );
		$this->assertEquals( 1, $result['total'] );
		$this->assertEquals( 1, $result['page'] );
		$this->assertEquals( 20, $result['per_page'] );
		$this->assertEquals( 1, $result['total_pages'] );
		$this->assertCount( 1, $result['logs'] );
	}

	/**
	 * Test get_logs filters by date range
	 */
	public function test_get_logs_filters_by_date_range() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		Functions\expect( 'wp_parse_args' )
			->once()
			->andReturnUsing( function( $args, $defaults ) {
				return array_merge( $defaults, $args );
			} );

		Functions\expect( 'absint' )
			->twice()
			->andReturnUsing( function( $value ) {
				return abs( (int) $value );
			} );

		Functions\expect( 'esc_sql' )
			->twice()
			->andReturnUsing( function( $value ) {
				return $value;
			} );

		// Expect prepare to be called with date filters
		$wpdb->shouldReceive( 'prepare' )
			->twice()
			->andReturnUsing( function( $query, ...$args ) {
				return vsprintf( str_replace( '%s', "'%s'", $query ), $args );
			} );

		$wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( 5 );

		$wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		$logger = new Logger();
		$result = $logger->get_logs( array(
			'date_from' => '2024-01-01 00:00:00',
			'date_to'   => '2024-01-31 23:59:59',
		) );

		$this->assertEquals( 5, $result['total'] );
	}

	/**
	 * Test get_logs filters by type
	 */
	public function test_get_logs_filters_by_type() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		Functions\expect( 'wp_parse_args' )
			->once()
			->andReturnUsing( function( $args, $defaults ) {
				return array_merge( $defaults, $args );
			} );

		Functions\expect( 'absint' )
			->twice()
			->andReturnUsing( function( $value ) {
				return abs( (int) $value );
			} );

		Functions\expect( 'esc_sql' )
			->twice()
			->andReturnUsing( function( $value ) {
				return $value;
			} );

		$wpdb->shouldReceive( 'prepare' )
			->twice()
			->andReturnUsing( function( $query, ...$args ) {
				return vsprintf( str_replace( '%s', "'%s'", $query ), $args );
			} );

		$wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( 3 );

		$wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		$logger = new Logger();
		$result = $logger->get_logs( array( 'type' => 'error' ) );

		$this->assertEquals( 3, $result['total'] );
	}

	/**
	 * Test get_logs filters by status
	 */
	public function test_get_logs_filters_by_status() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		Functions\expect( 'wp_parse_args' )
			->once()
			->andReturnUsing( function( $args, $defaults ) {
				return array_merge( $defaults, $args );
			} );

		Functions\expect( 'absint' )
			->twice()
			->andReturnUsing( function( $value ) {
				return abs( (int) $value );
			} );

		Functions\expect( 'esc_sql' )
			->twice()
			->andReturnUsing( function( $value ) {
				return $value;
			} );

		$wpdb->shouldReceive( 'prepare' )
			->twice()
			->andReturnUsing( function( $query, ...$args ) {
				return vsprintf( str_replace( '%s', "'%s'", $query ), $args );
			} );

		$wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( 10 );

		$wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		$logger = new Logger();
		$result = $logger->get_logs( array( 'status' => 'success' ) );

		$this->assertEquals( 10, $result['total'] );
	}

	/**
	 * Test get_logs filters by operation
	 */
	public function test_get_logs_filters_by_operation() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		Functions\expect( 'wp_parse_args' )
			->once()
			->andReturnUsing( function( $args, $defaults ) {
				return array_merge( $defaults, $args );
			} );

		Functions\expect( 'absint' )
			->twice()
			->andReturnUsing( function( $value ) {
				return abs( (int) $value );
			} );

		Functions\expect( 'esc_sql' )
			->twice()
			->andReturnUsing( function( $value ) {
				return $value;
			} );

		$wpdb->shouldReceive( 'prepare' )
			->twice()
			->andReturnUsing( function( $query, ...$args ) {
				return vsprintf( str_replace( '%s', "'%s'", $query ), $args );
			} );

		$wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( 7 );

		$wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		$logger = new Logger();
		$result = $logger->get_logs( array( 'operation' => 'order_sync' ) );

		$this->assertEquals( 7, $result['total'] );
	}

	/**
	 * Test get_logs handles pagination correctly
	 */
	public function test_get_logs_handles_pagination_correctly() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		Functions\expect( 'wp_parse_args' )
			->once()
			->andReturnUsing( function( $args, $defaults ) {
				return array_merge( $defaults, $args );
			} );

		Functions\expect( 'absint' )
			->twice()
			->andReturnUsing( function( $value ) {
				return abs( (int) $value );
			} );

		Functions\expect( 'esc_sql' )
			->twice()
			->andReturnUsing( function( $value ) {
				return $value;
			} );

		$wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( 50 );

		$wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		$logger = new Logger();
		$result = $logger->get_logs( array(
			'page'     => 2,
			'per_page' => 10,
		) );

		$this->assertEquals( 50, $result['total'] );
		$this->assertEquals( 2, $result['page'] );
		$this->assertEquals( 10, $result['per_page'] );
		$this->assertEquals( 5, $result['total_pages'] );
	}

	/**
	 * Test get_logs decodes JSON fields
	 */
	public function test_get_logs_decodes_json_fields() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$mock_logs = array(
			array(
				'id'          => 1,
				'timestamp'   => '2024-01-15 10:00:00',
				'type'        => 'api_request',
				'operation'   => '/api/v1/orders',
				'status'      => 'success',
				'message'     => 'API request',
				'payload'     => '{"order_id":123,"items":[{"sku":"PROD001"}]}',
				'response'    => '{"success":true,"protheus_id":"789"}',
				'duration'    => 1.5,
				'error_trace' => null,
				'context'     => '{"user_id":456}',
			),
		);

		Functions\expect( 'wp_parse_args' )
			->once()
			->andReturnUsing( function( $args, $defaults ) {
				return array_merge( $defaults, $args );
			} );

		Functions\expect( 'absint' )
			->twice()
			->andReturnUsing( function( $value ) {
				return abs( (int) $value );
			} );

		Functions\expect( 'esc_sql' )
			->twice()
			->andReturnUsing( function( $value ) {
				return $value;
			} );

		$wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( 1 );

		$wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( $mock_logs );

		$logger = new Logger();
		$result = $logger->get_logs();

		$this->assertCount( 1, $result['logs'] );
		$log = $result['logs'][0];

		// Verify JSON fields are decoded
		$this->assertIsArray( $log['payload'] );
		$this->assertArrayHasKey( 'order_id', $log['payload'] );
		$this->assertEquals( 123, $log['payload']['order_id'] );

		$this->assertIsArray( $log['response'] );
		$this->assertArrayHasKey( 'success', $log['response'] );
		$this->assertTrue( $log['response']['success'] );

		$this->assertIsArray( $log['context'] );
		$this->assertArrayHasKey( 'user_id', $log['context'] );
		$this->assertEquals( 456, $log['context']['user_id'] );
	}

	/**
	 * Test get_logs validates order_by column
	 */
	public function test_get_logs_validates_order_by_column() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		Functions\expect( 'wp_parse_args' )
			->once()
			->andReturnUsing( function( $args, $defaults ) {
				return array_merge( $defaults, $args );
			} );

		Functions\expect( 'absint' )
			->twice()
			->andReturnUsing( function( $value ) {
				return abs( (int) $value );
			} );

		Functions\expect( 'esc_sql' )
			->twice()
			->andReturnUsing( function( $value ) {
				return $value;
			} );

		$wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( 0 );

		$wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		$logger = new Logger();
		// Try to use invalid order_by column - should fallback to 'timestamp'
		$result = $logger->get_logs( array( 'order_by' => 'invalid_column' ) );

		$this->assertIsArray( $result );
	}

	/**
	 * Test get_logs limits per_page to maximum 100
	 */
	public function test_get_logs_limits_per_page_to_maximum() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		Functions\expect( 'wp_parse_args' )
			->once()
			->andReturnUsing( function( $args, $defaults ) {
				return array_merge( $defaults, $args );
			} );

		Functions\expect( 'absint' )
			->twice()
			->andReturnUsing( function( $value ) {
				return abs( (int) $value );
			} );

		Functions\expect( 'esc_sql' )
			->twice()
			->andReturnUsing( function( $value ) {
				return $value;
			} );

		$wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( 200 );

		$wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		$logger = new Logger();
		$result = $logger->get_logs( array( 'per_page' => 500 ) );

		// Should be limited to 100
		$this->assertEquals( 100, $result['per_page'] );
	}

	/**
	 * Test cleanup_old_logs returns 0 when total logs <= 1000
	 */
	public function test_cleanup_old_logs_returns_zero_when_under_threshold() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		// Mock total log count as 500 (under threshold)
		$wpdb->shouldReceive( 'get_var' )
			->once()
			->with( "SELECT COUNT(*) FROM wp_absloja_logs" )
			->andReturn( 500 );

		// Should not call query() since we're under threshold
		$wpdb->shouldNotReceive( 'query' );

		$logger = new Logger();
		$result = $logger->cleanup_old_logs();

		$this->assertEquals( 0, $result );
	}

	/**
	 * Test cleanup_old_logs deletes old logs when over threshold
	 */
	public function test_cleanup_old_logs_deletes_old_logs_when_over_threshold() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		// Mock total log count as 1500 (over threshold)
		$wpdb->shouldReceive( 'get_var' )
			->once()
			->with( "SELECT COUNT(*) FROM wp_absloja_logs" )
			->andReturn( 1500 );

		Functions\expect( 'gmdate' )
			->once()
			->with( 'Y-m-d H:i:s', Mockery::type( 'int' ) )
			->andReturn( '2024-01-01 00:00:00' );

		Functions\expect( 'strtotime' )
			->once()
			->with( '-30 days' )
			->andReturn( 1704067200 );

		// Mock prepare
		$wpdb->shouldReceive( 'prepare' )
			->once()
			->with(
				"DELETE FROM wp_absloja_logs WHERE timestamp < %s AND type != %s",
				'2024-01-01 00:00:00',
				'error'
			)
			->andReturn( "DELETE FROM wp_absloja_logs WHERE timestamp < '2024-01-01 00:00:00' AND type != 'error'" );

		// Mock query execution - 250 logs deleted
		$wpdb->shouldReceive( 'query' )
			->once()
			->andReturn( 250 );

		$logger = new Logger();
		$result = $logger->cleanup_old_logs();

		$this->assertEquals( 250, $result );
	}

	/**
	 * Test cleanup_old_logs preserves error logs
	 */
	public function test_cleanup_old_logs_preserves_error_logs() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		// Mock total log count as 2000 (over threshold)
		$wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( 2000 );

		Functions\expect( 'gmdate' )
			->once()
			->andReturn( '2024-01-01 00:00:00' );

		Functions\expect( 'strtotime' )
			->once()
			->andReturn( 1704067200 );

		// Verify that the prepare call excludes error logs (type != 'error')
		$wpdb->shouldReceive( 'prepare' )
			->once()
			->with(
				Mockery::pattern( "/type != %s/" ),
				Mockery::any(),
				'error'
			)
			->andReturn( "DELETE FROM wp_absloja_logs WHERE timestamp < '2024-01-01 00:00:00' AND type != 'error'" );

		$wpdb->shouldReceive( 'query' )
			->once()
			->andReturn( 100 );

		$logger = new Logger();
		$result = $logger->cleanup_old_logs();

		$this->assertEquals( 100, $result );
	}

	/**
	 * Test cleanup_old_logs handles query failure
	 */
	public function test_cleanup_old_logs_handles_query_failure() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		// Mock total log count as 1500 (over threshold)
		$wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( 1500 );

		Functions\expect( 'gmdate' )
			->once()
			->andReturn( '2024-01-01 00:00:00' );

		Functions\expect( 'strtotime' )
			->once()
			->andReturn( 1704067200 );

		$wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( "DELETE FROM wp_absloja_logs WHERE timestamp < '2024-01-01 00:00:00' AND type != 'error'" );

		// Mock query failure
		$wpdb->shouldReceive( 'query' )
			->once()
			->andReturn( false );

		$logger = new Logger();
		$result = $logger->cleanup_old_logs();

		// Should return 0 on failure
		$this->assertEquals( 0, $result );
	}

	/**
	 * Test cleanup_old_logs only deletes logs older than 30 days
	 */
	public function test_cleanup_old_logs_only_deletes_logs_older_than_30_days() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( 1200 );

		$expected_date = '2023-12-15 10:30:00';

		Functions\expect( 'gmdate' )
			->once()
			->with( 'Y-m-d H:i:s', Mockery::type( 'int' ) )
			->andReturn( $expected_date );

		Functions\expect( 'strtotime' )
			->once()
			->with( '-30 days' )
			->andReturn( 1702640400 );

		// Verify the timestamp filter in the prepare call
		$wpdb->shouldReceive( 'prepare' )
			->once()
			->with(
				"DELETE FROM wp_absloja_logs WHERE timestamp < %s AND type != %s",
				$expected_date,
				'error'
			)
			->andReturn( "DELETE FROM wp_absloja_logs WHERE timestamp < '{$expected_date}' AND type != 'error'" );

		$wpdb->shouldReceive( 'query' )
			->once()
			->andReturn( 50 );

		$logger = new Logger();
		$result = $logger->cleanup_old_logs();

		$this->assertEquals( 50, $result );
	}

	/**
	 * Test export_logs_csv generates CSV with headers
	 */
	public function test_export_logs_csv_generates_csv_with_headers() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$mock_logs = array(
			array(
				'id'          => 1,
				'timestamp'   => '2024-01-15 10:00:00',
				'type'        => 'api_request',
				'operation'   => '/api/v1/orders',
				'status'      => 'success',
				'message'     => 'API request to /api/v1/orders',
				'payload'     => '{"order_id":123}',
				'response'    => '{"success":true}',
				'duration'    => 1.5,
				'error_trace' => null,
				'context'     => null,
			),
		);

		Functions\expect( 'wp_parse_args' )
			->once()
			->andReturnUsing( function( $args, $defaults ) {
				return array_merge( $defaults, $args );
			} );

		Functions\expect( 'absint' )
			->twice()
			->andReturnUsing( function( $value ) {
				return abs( (int) $value );
			} );

		Functions\expect( 'esc_sql' )
			->twice()
			->andReturnUsing( function( $value ) {
				return $value;
			} );

		Functions\expect( 'wp_json_encode' )
			->never(); // Already JSON strings in mock data

		$wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( 1 );

		$wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( $mock_logs );

		$logger = new Logger();
		$csv    = $logger->export_logs_csv();

		// Verify CSV structure
		$this->assertIsString( $csv );
		$this->assertNotEmpty( $csv );

		// Verify headers are present
		$this->assertStringContainsString( '"ID"', $csv );
		$this->assertStringContainsString( '"Timestamp"', $csv );
		$this->assertStringContainsString( '"Type"', $csv );
		$this->assertStringContainsString( '"Operation"', $csv );
		$this->assertStringContainsString( '"Status"', $csv );
		$this->assertStringContainsString( '"Message"', $csv );
		$this->assertStringContainsString( '"Payload"', $csv );
		$this->assertStringContainsString( '"Response"', $csv );
		$this->assertStringContainsString( '"Duration"', $csv );
		$this->assertStringContainsString( '"Error Trace"', $csv );
		$this->assertStringContainsString( '"Context"', $csv );

		// Verify data row is present
		$this->assertStringContainsString( '"1"', $csv );
		$this->assertStringContainsString( '"2024-01-15 10:00:00"', $csv );
		$this->assertStringContainsString( '"api_request"', $csv );
	}

	/**
	 * Test export_logs_csv includes all log entries
	 */
	public function test_export_logs_csv_includes_all_log_entries() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$mock_logs = array(
			array(
				'id'          => 1,
				'timestamp'   => '2024-01-15 10:00:00',
				'type'        => 'api_request',
				'operation'   => '/api/v1/orders',
				'status'      => 'success',
				'message'     => 'API request',
				'payload'     => '{"order_id":123}',
				'response'    => '{"success":true}',
				'duration'    => 1.5,
				'error_trace' => null,
				'context'     => null,
			),
			array(
				'id'          => 2,
				'timestamp'   => '2024-01-15 10:05:00',
				'type'        => 'webhook',
				'operation'   => 'order_status',
				'status'      => 'success',
				'message'     => 'Webhook received',
				'payload'     => '{"status":"approved"}',
				'response'    => '{"success":true}',
				'duration'    => null,
				'error_trace' => null,
				'context'     => null,
			),
			array(
				'id'          => 3,
				'timestamp'   => '2024-01-15 10:10:00',
				'type'        => 'error',
				'operation'   => 'order_sync',
				'status'      => 'error',
				'message'     => 'Sync failed',
				'payload'     => '{"order_id":456}',
				'response'    => null,
				'duration'    => null,
				'error_trace' => 'Exception trace here',
				'context'     => '{"user_id":789}',
			),
		);

		Functions\expect( 'wp_parse_args' )
			->once()
			->andReturnUsing( function( $args, $defaults ) {
				return array_merge( $defaults, $args );
			} );

		Functions\expect( 'absint' )
			->twice()
			->andReturnUsing( function( $value ) {
				return abs( (int) $value );
			} );

		Functions\expect( 'esc_sql' )
			->twice()
			->andReturnUsing( function( $value ) {
				return $value;
			} );

		$wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( 3 );

		$wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( $mock_logs );

		$logger = new Logger();
		$csv    = $logger->export_logs_csv();

		// Count lines (header + 3 data rows = 4 lines)
		$lines = explode( "\n", trim( $csv ) );
		$this->assertCount( 4, $lines );

		// Verify all log IDs are present
		$this->assertStringContainsString( '"1"', $csv );
		$this->assertStringContainsString( '"2"', $csv );
		$this->assertStringContainsString( '"3"', $csv );

		// Verify different types are present
		$this->assertStringContainsString( '"api_request"', $csv );
		$this->assertStringContainsString( '"webhook"', $csv );
		$this->assertStringContainsString( '"error"', $csv );
	}

	/**
	 * Test export_logs_csv applies filters
	 */
	public function test_export_logs_csv_applies_filters() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$mock_logs = array(
			array(
				'id'          => 1,
				'timestamp'   => '2024-01-15 10:00:00',
				'type'        => 'error',
				'operation'   => 'order_sync',
				'status'      => 'error',
				'message'     => 'Error occurred',
				'payload'     => '{}',
				'response'    => null,
				'duration'    => null,
				'error_trace' => 'Stack trace',
				'context'     => null,
			),
		);

		Functions\expect( 'wp_parse_args' )
			->once()
			->andReturnUsing( function( $args, $defaults ) {
				return array_merge( $defaults, $args );
			} );

		Functions\expect( 'absint' )
			->twice()
			->andReturnUsing( function( $value ) {
				return abs( (int) $value );
			} );

		Functions\expect( 'esc_sql' )
			->twice()
			->andReturnUsing( function( $value ) {
				return $value;
			} );

		// Expect prepare to be called with type filter
		$wpdb->shouldReceive( 'prepare' )
			->twice()
			->andReturnUsing( function( $query, ...$args ) {
				return vsprintf( str_replace( '%s', "'%s'", $query ), $args );
			} );

		$wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( 1 );

		$wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( $mock_logs );

		$logger = new Logger();
		$csv    = $logger->export_logs_csv( array( 'type' => 'error' ) );

		// Verify CSV contains only error logs
		$this->assertStringContainsString( '"error"', $csv );
		$this->assertStringContainsString( '"order_sync"', $csv );
	}

	/**
	 * Test export_logs_csv handles empty results
	 */
	public function test_export_logs_csv_handles_empty_results() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		Functions\expect( 'wp_parse_args' )
			->once()
			->andReturnUsing( function( $args, $defaults ) {
				return array_merge( $defaults, $args );
			} );

		Functions\expect( 'absint' )
			->twice()
			->andReturnUsing( function( $value ) {
				return abs( (int) $value );
			} );

		Functions\expect( 'esc_sql' )
			->twice()
			->andReturnUsing( function( $value ) {
				return $value;
			} );

		$wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( 0 );

		$wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		$logger = new Logger();
		$csv    = $logger->export_logs_csv();

		// Should still have headers
		$this->assertIsString( $csv );
		$this->assertStringContainsString( '"ID"', $csv );
		$this->assertStringContainsString( '"Timestamp"', $csv );

		// Should only have header line
		$lines = explode( "\n", trim( $csv ) );
		$this->assertCount( 1, $lines );
	}

	/**
	 * Test export_logs_csv properly escapes CSV fields
	 */
	public function test_export_logs_csv_properly_escapes_csv_fields() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$mock_logs = array(
			array(
				'id'          => 1,
				'timestamp'   => '2024-01-15 10:00:00',
				'type'        => 'api_request',
				'operation'   => '/api/v1/orders',
				'status'      => 'success',
				'message'     => 'Message with "quotes" and, commas',
				'payload'     => '{"key":"value with ""quotes"""}',
				'response'    => '{"message":"Success, all good"}',
				'duration'    => 1.5,
				'error_trace' => null,
				'context'     => null,
			),
		);

		Functions\expect( 'wp_parse_args' )
			->once()
			->andReturnUsing( function( $args, $defaults ) {
				return array_merge( $defaults, $args );
			} );

		Functions\expect( 'absint' )
			->twice()
			->andReturnUsing( function( $value ) {
				return abs( (int) $value );
			} );

		Functions\expect( 'esc_sql' )
			->twice()
			->andReturnUsing( function( $value ) {
				return $value;
			} );

		$wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( 1 );

		$wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( $mock_logs );

		$logger = new Logger();
		$csv    = $logger->export_logs_csv();

		// Verify proper CSV escaping (quotes should be doubled and fields wrapped in quotes)
		$this->assertStringContainsString( '""quotes""', $csv );
		$this->assertStringContainsString( 'commas', $csv );

		// Verify CSV is parseable
		$lines = explode( "\n", trim( $csv ) );
		$this->assertCount( 2, $lines ); // Header + 1 data row
	}

	/**
	 * Test export_logs_csv handles null values
	 */
	public function test_export_logs_csv_handles_null_values() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$mock_logs = array(
			array(
				'id'          => 1,
				'timestamp'   => '2024-01-15 10:00:00',
				'type'        => 'webhook',
				'operation'   => 'stock',
				'status'      => 'success',
				'message'     => 'Webhook received',
				'payload'     => '{"sku":"PROD001"}',
				'response'    => '{"success":true}',
				'duration'    => null,
				'error_trace' => null,
				'context'     => null,
			),
		);

		Functions\expect( 'wp_parse_args' )
			->once()
			->andReturnUsing( function( $args, $defaults ) {
				return array_merge( $defaults, $args );
			} );

		Functions\expect( 'absint' )
			->twice()
			->andReturnUsing( function( $value ) {
				return abs( (int) $value );
			} );

		Functions\expect( 'esc_sql' )
			->twice()
			->andReturnUsing( function( $value ) {
				return $value;
			} );

		$wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( 1 );

		$wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( $mock_logs );

		$logger = new Logger();
		$csv    = $logger->export_logs_csv();

		// Verify CSV is generated without errors
		$this->assertIsString( $csv );
		$this->assertNotEmpty( $csv );

		// Null values should be converted to empty strings in CSV
		$lines = explode( "\n", trim( $csv ) );
		$this->assertCount( 2, $lines ); // Header + 1 data row

		// Verify the data row has the correct number of fields (11 fields)
		$data_line = $lines[1];
		$fields    = str_getcsv( $data_line );
		$this->assertCount( 11, $fields );
	}

	/**
	 * Test export_logs_csv converts array payloads to JSON strings
	 */
	public function test_export_logs_csv_converts_array_payloads_to_json() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$mock_logs = array(
			array(
				'id'          => 1,
				'timestamp'   => '2024-01-15 10:00:00',
				'type'        => 'api_request',
				'operation'   => '/api/v1/orders',
				'status'      => 'success',
				'message'     => 'API request',
				'payload'     => array( 'order_id' => 123, 'items' => array( 'sku' => 'PROD001' ) ), // Array instead of JSON string
				'response'    => array( 'success' => true, 'protheus_id' => '789' ), // Array instead of JSON string
				'duration'    => 1.5,
				'error_trace' => null,
				'context'     => array( 'user_id' => 456 ), // Array instead of JSON string
			),
		);

		Functions\expect( 'wp_parse_args' )
			->once()
			->andReturnUsing( function( $args, $defaults ) {
				return array_merge( $defaults, $args );
			} );

		Functions\expect( 'absint' )
			->twice()
			->andReturnUsing( function( $value ) {
				return abs( (int) $value );
			} );

		Functions\expect( 'esc_sql' )
			->twice()
			->andReturnUsing( function( $value ) {
				return $value;
			} );

		Functions\expect( 'wp_json_encode' )
			->times( 3 ) // Called for payload, response, and context
			->andReturnUsing( function( $data ) {
				return json_encode( $data );
			} );

		$wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( 1 );

		$wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( $mock_logs );

		$logger = new Logger();
		$csv    = $logger->export_logs_csv();

		// Verify CSV contains JSON-encoded data
		$this->assertStringContainsString( 'order_id', $csv );
		$this->assertStringContainsString( 'protheus_id', $csv );
		$this->assertStringContainsString( 'user_id', $csv );
	}
}

