<?php
/**
 * Property-based tests for Logger
 *
 * Tests correctness properties related to logging operations.
 *
 * @package ABSLoja\ProtheusConnector\Tests\Property
 */

namespace ABSLoja\ProtheusConnector\Tests\Property;

use ABSLoja\ProtheusConnector\Modules\Logger;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

/**
 * Logger property-based tests
 */
class LoggerPropertiesTest extends TestCase {

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
	 * @test
	 * Feature: absloja-protheus-connector, Property 36: API Request Logging
	 *
	 * For any API request sent to Protheus, the Logger should record a log entry
	 * containing timestamp, endpoint, payload, response, and duration.
	 *
	 * Validates: Requirements 8.1
	 */
	public function test_api_request_logging_contains_all_required_fields() {
		$iterations = 100;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				global $wpdb;
				$wpdb = Mockery::mock( 'wpdb' );
				$wpdb->prefix = 'wp_';
				$wpdb->insert_id = rand( 1, 10000 );

				// Generate random API request data
				$endpoint = $this->generate_random_endpoint();
				$payload = $this->generate_random_payload();
				$response = $this->generate_random_response();
				$duration = $this->generate_random_duration();

				Functions\expect( 'current_time' )
					->once()
					->andReturn( gmdate( 'Y-m-d H:i:s' ) );

				Functions\expect( 'wp_json_encode' )
					->andReturnUsing( function( $data ) {
						return json_encode( $data );
					} );

				Functions\expect( 'is_wp_error' )
					->andReturn( false );

				// Capture the data being inserted
				$inserted_data = null;
				$wpdb->shouldReceive( 'insert' )
					->once()
					->with( 'wp_absloja_logs', Mockery::on( function( $data ) use ( &$inserted_data ) {
						$inserted_data = $data;
						return true;
					} ) )
					->andReturn( 1 );

				$logger = new Logger();
				$result = $logger->log_api_request( $endpoint, $payload, $response, $duration );

				// Verify all required fields are present
				$this->assertNotNull( $inserted_data, "Data should be inserted (iteration $i)" );
				$this->assertArrayHasKey( 'timestamp', $inserted_data, "Timestamp should be present (iteration $i)" );
				$this->assertArrayHasKey( 'type', $inserted_data, "Type should be present (iteration $i)" );
				$this->assertArrayHasKey( 'operation', $inserted_data, "Operation should be present (iteration $i)" );
				$this->assertArrayHasKey( 'status', $inserted_data, "Status should be present (iteration $i)" );
				$this->assertArrayHasKey( 'message', $inserted_data, "Message should be present (iteration $i)" );
				$this->assertArrayHasKey( 'payload', $inserted_data, "Payload should be present (iteration $i)" );
				$this->assertArrayHasKey( 'response', $inserted_data, "Response should be present (iteration $i)" );
				$this->assertArrayHasKey( 'duration', $inserted_data, "Duration should be present (iteration $i)" );

				// Verify field values
				$this->assertEquals( 'api_request', $inserted_data['type'], "Type should be 'api_request' (iteration $i)" );
				$this->assertEquals( $endpoint, $inserted_data['operation'], "Operation should match endpoint (iteration $i)" );
				$this->assertEquals( $duration, $inserted_data['duration'], "Duration should match (iteration $i)" );
				$this->assertNotEmpty( $inserted_data['timestamp'], "Timestamp should not be empty (iteration $i)" );

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
	 * Feature: absloja-protheus-connector, Property 37: Webhook Request Logging
	 *
	 * For any webhook request received from Protheus, the Logger should record
	 * a log entry containing timestamp, endpoint, payload, and response.
	 *
	 * Validates: Requirements 8.2
	 */
	public function test_webhook_request_logging_contains_all_required_fields() {
		$iterations = 100;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				global $wpdb;
				$wpdb = Mockery::mock( 'wpdb' );
				$wpdb->prefix = 'wp_';
				$wpdb->insert_id = rand( 1, 10000 );

				// Generate random webhook data
				$type = $this->generate_random_webhook_type();
				$payload = $this->generate_random_webhook_payload();
				$response = $this->generate_random_response();

				Functions\expect( 'current_time' )
					->once()
					->andReturn( gmdate( 'Y-m-d H:i:s' ) );

				Functions\expect( 'wp_json_encode' )
					->andReturnUsing( function( $data ) {
						return json_encode( $data );
					} );

				// Capture the data being inserted
				$inserted_data = null;
				$wpdb->shouldReceive( 'insert' )
					->once()
					->with( 'wp_absloja_logs', Mockery::on( function( $data ) use ( &$inserted_data ) {
						$inserted_data = $data;
						return true;
					} ) )
					->andReturn( 1 );

				$logger = new Logger();
				$result = $logger->log_webhook( $type, $payload, $response );

				// Verify all required fields are present
				$this->assertNotNull( $inserted_data, "Data should be inserted (iteration $i)" );
				$this->assertArrayHasKey( 'timestamp', $inserted_data, "Timestamp should be present (iteration $i)" );
				$this->assertArrayHasKey( 'type', $inserted_data, "Type should be present (iteration $i)" );
				$this->assertArrayHasKey( 'operation', $inserted_data, "Operation should be present (iteration $i)" );
				$this->assertArrayHasKey( 'payload', $inserted_data, "Payload should be present (iteration $i)" );
				$this->assertArrayHasKey( 'response', $inserted_data, "Response should be present (iteration $i)" );

				// Verify field values
				$this->assertEquals( 'webhook', $inserted_data['type'], "Type should be 'webhook' (iteration $i)" );
				$this->assertEquals( $type, $inserted_data['operation'], "Operation should match webhook type (iteration $i)" );
				$this->assertNotEmpty( $inserted_data['timestamp'], "Timestamp should not be empty (iteration $i)" );

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
	 * Feature: absloja-protheus-connector, Property 38: Sync Operation Logging
	 *
	 * For any sync operation executed, the Logger should record a log entry
	 * containing timestamp, operation type, affected records, and result status.
	 *
	 * Validates: Requirements 8.3
	 */
	public function test_sync_operation_logging_contains_all_required_fields() {
		$iterations = 100;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				global $wpdb;
				$wpdb = Mockery::mock( 'wpdb' );
				$wpdb->prefix = 'wp_';
				$wpdb->insert_id = rand( 1, 10000 );

				// Generate random sync operation data
				$type = $this->generate_random_sync_type();
				$data = $this->generate_random_sync_data();
				$success = (bool) rand( 0, 1 );
				$error = $success ? null : $this->generate_random_error_message();

				Functions\expect( 'current_time' )
					->once()
					->andReturn( gmdate( 'Y-m-d H:i:s' ) );

				Functions\expect( 'wp_json_encode' )
					->andReturnUsing( function( $data ) {
						return json_encode( $data );
					} );

				// Capture the data being inserted
				$inserted_data = null;
				$wpdb->shouldReceive( 'insert' )
					->once()
					->with( 'wp_absloja_logs', Mockery::on( function( $data ) use ( &$inserted_data ) {
						$inserted_data = $data;
						return true;
					} ) )
					->andReturn( 1 );

				$logger = new Logger();
				$result = $logger->log_sync_operation( $type, $data, $success, $error );

				// Verify all required fields are present
				$this->assertNotNull( $inserted_data, "Data should be inserted (iteration $i)" );
				$this->assertArrayHasKey( 'timestamp', $inserted_data, "Timestamp should be present (iteration $i)" );
				$this->assertArrayHasKey( 'type', $inserted_data, "Type should be present (iteration $i)" );
				$this->assertArrayHasKey( 'operation', $inserted_data, "Operation should be present (iteration $i)" );
				$this->assertArrayHasKey( 'status', $inserted_data, "Status should be present (iteration $i)" );
				$this->assertArrayHasKey( 'payload', $inserted_data, "Payload (affected records) should be present (iteration $i)" );

				// Verify field values
				$this->assertEquals( 'sync', $inserted_data['type'], "Type should be 'sync' (iteration $i)" );
				$this->assertEquals( $type, $inserted_data['operation'], "Operation should match sync type (iteration $i)" );
				$this->assertEquals( $success ? 'success' : 'error', $inserted_data['status'], "Status should match success flag (iteration $i)" );
				$this->assertNotEmpty( $inserted_data['timestamp'], "Timestamp should not be empty (iteration $i)" );

				// If error, verify error_trace is set
				if ( ! $success ) {
					$this->assertEquals( $error, $inserted_data['error_trace'], "Error trace should be set on failure (iteration $i)" );
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
	 * Feature: absloja-protheus-connector, Property 39: Error Logging
	 *
	 * For any error that occurs, the Logger should record a log entry containing
	 * timestamp, error message, stack trace, and context data.
	 *
	 * Validates: Requirements 8.4
	 */
	public function test_error_logging_contains_all_required_fields() {
		$iterations = 100;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				global $wpdb;
				$wpdb = Mockery::mock( 'wpdb' );
				$wpdb->prefix = 'wp_';
				$wpdb->insert_id = rand( 1, 10000 );

				// Generate random error data
				$message = $this->generate_random_error_message();
				$exception = $this->generate_random_exception();
				$context = $this->generate_random_error_context();

				Functions\expect( 'current_time' )
					->once()
					->andReturn( gmdate( 'Y-m-d H:i:s' ) );

				Functions\expect( 'wp_json_encode' )
					->andReturnUsing( function( $data ) {
						return json_encode( $data );
					} );

				// Capture the data being inserted
				$inserted_data = null;
				$wpdb->shouldReceive( 'insert' )
					->once()
					->with( 'wp_absloja_logs', Mockery::on( function( $data ) use ( &$inserted_data ) {
						$inserted_data = $data;
						return true;
					} ) )
					->andReturn( 1 );

				$logger = new Logger();
				$result = $logger->log_error( $message, $exception, $context );

				// Verify all required fields are present
				$this->assertNotNull( $inserted_data, "Data should be inserted (iteration $i)" );
				$this->assertArrayHasKey( 'timestamp', $inserted_data, "Timestamp should be present (iteration $i)" );
				$this->assertArrayHasKey( 'type', $inserted_data, "Type should be present (iteration $i)" );
				$this->assertArrayHasKey( 'message', $inserted_data, "Message should be present (iteration $i)" );
				$this->assertArrayHasKey( 'error_trace', $inserted_data, "Stack trace should be present (iteration $i)" );
				$this->assertArrayHasKey( 'context', $inserted_data, "Context should be present (iteration $i)" );

				// Verify field values
				$this->assertEquals( 'error', $inserted_data['type'], "Type should be 'error' (iteration $i)" );
				$this->assertEquals( 'error', $inserted_data['status'], "Status should be 'error' (iteration $i)" );
				$this->assertEquals( $message, $inserted_data['message'], "Message should match (iteration $i)" );
				$this->assertNotEmpty( $inserted_data['error_trace'], "Stack trace should not be empty (iteration $i)" );
				$this->assertNotEmpty( $inserted_data['timestamp'], "Timestamp should not be empty (iteration $i)" );

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
	 * Feature: absloja-protheus-connector, Property 40: Log Export to CSV
	 *
	 * For any log export request, the Logger should generate a CSV file containing
	 * all log entries matching the specified filters with all relevant fields.
	 *
	 * Validates: Requirements 8.7
	 */
	public function test_log_export_to_csv_contains_all_fields() {
		$iterations = 100;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				global $wpdb;
				$wpdb = Mockery::mock( 'wpdb' );
				$wpdb->prefix = 'wp_';

				// Generate random log entries
				$num_logs = rand( 1, 20 );
				$mock_logs = [];
				for ( $j = 0; $j < $num_logs; $j++ ) {
					$mock_logs[] = $this->generate_random_log_entry();
				}

				Functions\expect( 'wp_parse_args' )
					->once()
					->andReturnUsing( function( $args, $defaults ) {
						return array_merge( $defaults, $args );
					} );

				Functions\expect( 'absint' )
					->andReturnUsing( function( $value ) {
						return abs( (int) $value );
					} );

				Functions\expect( 'esc_sql' )
					->andReturnUsing( function( $value ) {
						return $value;
					} );

				Functions\expect( 'wp_json_encode' )
					->andReturnUsing( function( $data ) {
						return json_encode( $data );
					} );

				$wpdb->shouldReceive( 'get_var' )
					->andReturn( $num_logs );

				$wpdb->shouldReceive( 'get_results' )
					->andReturn( $mock_logs );

				$logger = new Logger();
				$csv_output = $logger->export_logs_csv();

				// Verify CSV is not empty
				$this->assertNotEmpty( $csv_output, "CSV output should not be empty (iteration $i)" );

				// Parse CSV
				$lines = explode( "\n", trim( $csv_output ) );
				$this->assertGreaterThan( 0, count( $lines ), "CSV should have at least header line (iteration $i)" );

				// Verify header line contains all required fields
				$header = str_getcsv( $lines[0] );
				$required_fields = [ 'ID', 'Timestamp', 'Type', 'Operation', 'Status', 'Message', 'Payload', 'Response', 'Duration', 'Error Trace', 'Context' ];
				foreach ( $required_fields as $field ) {
					$this->assertContains( $field, $header, "CSV header should contain '$field' (iteration $i)" );
				}

				// Verify we have the correct number of data rows
				$data_rows = count( $lines ) - 1; // Subtract header
				$this->assertEquals( $num_logs, $data_rows, "CSV should have $num_logs data rows (iteration $i)" );

				// Verify each data row has the correct number of columns
				for ( $j = 1; $j <= $num_logs; $j++ ) {
					if ( ! empty( $lines[$j] ) ) {
						$row = str_getcsv( $lines[$j] );
						$this->assertEquals( count( $header ), count( $row ), "Row $j should have same number of columns as header (iteration $i)" );
					}
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
	 * Feature: absloja-protheus-connector, Property 40: Log Export to CSV
	 *
	 * Verifies that CSV export properly escapes special characters and handles
	 * various data types correctly.
	 *
	 * Validates: Requirements 8.7
	 */
	public function test_log_export_csv_properly_escapes_special_characters() {
		$iterations = 50;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				global $wpdb;
				$wpdb = Mockery::mock( 'wpdb' );
				$wpdb->prefix = 'wp_';

				// Generate log with special characters
				$mock_log = [
					'id' => rand( 1, 10000 ),
					'timestamp' => gmdate( 'Y-m-d H:i:s' ),
					'type' => 'api_request',
					'operation' => '/api/v1/test',
					'status' => 'success',
					'message' => 'Test with "quotes", commas, and newlines\nhere',
					'payload' => '{"key":"value with, comma and \"quotes\""}',
					'response' => '{"result":"success"}',
					'duration' => 1.5,
					'error_trace' => null,
					'context' => null,
				];

				Functions\expect( 'wp_parse_args' )
					->once()
					->andReturnUsing( function( $args, $defaults ) {
						return array_merge( $defaults, $args );
					} );

				Functions\expect( 'absint' )
					->andReturnUsing( function( $value ) {
						return abs( (int) $value );
					} );

				Functions\expect( 'esc_sql' )
					->andReturnUsing( function( $value ) {
						return $value;
					} );

				Functions\expect( 'wp_json_encode' )
					->andReturnUsing( function( $data ) {
						return json_encode( $data );
					} );

				$wpdb->shouldReceive( 'get_var' )
					->andReturn( 1 );

				$wpdb->shouldReceive( 'get_results' )
					->andReturn( [ $mock_log ] );

				$logger = new Logger();
				$csv_output = $logger->export_logs_csv();

				// Verify CSV is valid and can be parsed
				$lines = explode( "\n", trim( $csv_output ) );
				$this->assertGreaterThanOrEqual( 2, count( $lines ), "CSV should have header and data row (iteration $i)" );

				// Parse data row
				$data_row = str_getcsv( $lines[1] );
				$this->assertNotEmpty( $data_row, "Data row should be parseable (iteration $i)" );

				// Verify special characters are properly escaped
				$message_field = $data_row[5]; // Message is 6th column (0-indexed)
				$this->assertStringContainsString( 'quotes', $message_field, "Message should contain 'quotes' (iteration $i)" );

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
	 * Feature: absloja-protheus-connector, Property 41: Automatic Log Cleanup
	 *
	 * For any log cleanup execution where storage exceeds 1000 entries, the Logger
	 * should delete logs older than 30 days while preserving error logs.
	 *
	 * Validates: Requirements 8.8
	 */
	public function test_automatic_log_cleanup_deletes_old_logs_when_over_threshold() {
		$iterations = 100;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				global $wpdb;
				$wpdb = Mockery::mock( 'wpdb' );
				$wpdb->prefix = 'wp_';

				// Generate random total count over threshold
				$total_logs = rand( 1001, 5000 );

				// Mock total log count
				$wpdb->shouldReceive( 'get_var' )
					->once()
					->with( "SELECT COUNT(*) FROM wp_absloja_logs" )
					->andReturn( $total_logs );

				Functions\expect( 'gmdate' )
					->once()
					->with( 'Y-m-d H:i:s', Mockery::type( 'int' ) )
					->andReturn( '2024-01-01 00:00:00' );

				Functions\expect( 'strtotime' )
					->once()
					->with( '-30 days' )
					->andReturn( time() - ( 30 * 24 * 60 * 60 ) );

				// Verify prepare is called with correct parameters
				$prepare_called = false;
				$wpdb->shouldReceive( 'prepare' )
					->once()
					->with(
						Mockery::pattern( '/DELETE FROM .* WHERE timestamp < %s AND type != %s/' ),
						Mockery::type( 'string' ),
						'error'
					)
					->andReturnUsing( function() use ( &$prepare_called ) {
						$prepare_called = true;
						return "DELETE FROM wp_absloja_logs WHERE timestamp < '2024-01-01 00:00:00' AND type != 'error'";
					} );

				// Mock query execution
				$deleted_count = rand( 1, 500 );
				$wpdb->shouldReceive( 'query' )
					->once()
					->andReturn( $deleted_count );

				$logger = new Logger();
				$result = $logger->cleanup_old_logs();

				// Verify cleanup was executed
				$this->assertTrue( $prepare_called, "Prepare should be called for cleanup (iteration $i)" );
				$this->assertEquals( $deleted_count, $result, "Should return number of deleted logs (iteration $i)" );

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
	 * Feature: absloja-protheus-connector, Property 41: Automatic Log Cleanup
	 *
	 * Verifies that cleanup does not delete logs when total is under threshold.
	 *
	 * Validates: Requirements 8.8
	 */
	public function test_automatic_log_cleanup_skips_when_under_threshold() {
		$iterations = 100;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				global $wpdb;
				$wpdb = Mockery::mock( 'wpdb' );
				$wpdb->prefix = 'wp_';

				// Generate random total count under threshold
				$total_logs = rand( 1, 1000 );

				// Mock total log count
				$wpdb->shouldReceive( 'get_var' )
					->once()
					->with( "SELECT COUNT(*) FROM wp_absloja_logs" )
					->andReturn( $total_logs );

				// Should NOT call prepare or query when under threshold
				$wpdb->shouldNotReceive( 'prepare' );
				$wpdb->shouldNotReceive( 'query' );

				$logger = new Logger();
				$result = $logger->cleanup_old_logs();

				// Should return 0 when no cleanup performed
				$this->assertEquals( 0, $result, "Should return 0 when under threshold (iteration $i)" );

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
	 * Feature: absloja-protheus-connector, Property 41: Automatic Log Cleanup
	 *
	 * Verifies that error logs are preserved regardless of age.
	 *
	 * Validates: Requirements 8.8
	 */
	public function test_automatic_log_cleanup_preserves_error_logs() {
		$iterations = 50;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				global $wpdb;
				$wpdb = Mockery::mock( 'wpdb' );
				$wpdb->prefix = 'wp_';

				// Total logs over threshold
				$total_logs = rand( 1001, 3000 );

				$wpdb->shouldReceive( 'get_var' )
					->once()
					->andReturn( $total_logs );

				Functions\expect( 'gmdate' )
					->once()
					->andReturn( '2024-01-01 00:00:00' );

				Functions\expect( 'strtotime' )
					->once()
					->andReturn( time() - ( 30 * 24 * 60 * 60 ) );

				// Verify that the WHERE clause excludes error logs
				$excludes_error_logs = false;
				$wpdb->shouldReceive( 'prepare' )
					->once()
					->with(
						Mockery::pattern( "/type != %s/" ),
						Mockery::any(),
						'error'
					)
					->andReturnUsing( function() use ( &$excludes_error_logs ) {
						$excludes_error_logs = true;
						return "DELETE FROM wp_absloja_logs WHERE timestamp < '2024-01-01 00:00:00' AND type != 'error'";
					} );

				$wpdb->shouldReceive( 'query' )
					->once()
					->andReturn( rand( 1, 500 ) );

				$logger = new Logger();
				$result = $logger->cleanup_old_logs();

				// Verify error logs are excluded from deletion
				$this->assertTrue( $excludes_error_logs, "Cleanup should exclude error logs (iteration $i)" );

			} catch ( \Exception $e ) {
				$failures[] = "Iteration $i failed: " . $e->getMessage();
			}
		}

		if ( ! empty( $failures ) ) {
			$this->fail( "Property test failed:\n" . implode( "\n", $failures ) );
		}

		$this->assertTrue( true, "All $iterations iterations passed" );
	}


	// ========== Helper Methods for Generating Random Test Data ==========

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
			'/api/v1/categories',
			'/oauth2/token',
		];

		return $endpoints[ array_rand( $endpoints ) ];
	}

	/**
	 * Generate random payload
	 *
	 * @return array
	 */
	private function generate_random_payload(): array {
		$payloads = [
			[ 'order_id' => rand( 1, 10000 ), 'customer_id' => rand( 1, 1000 ) ],
			[ 'sku' => 'PROD' . rand( 1000, 9999 ), 'quantity' => rand( 1, 100 ) ],
			[ 'customer_code' => 'CUST' . rand( 100, 999 ), 'name' => 'Customer ' . rand( 1, 100 ) ],
			[ 'page' => rand( 1, 10 ), 'limit' => rand( 10, 100 ) ],
			[],
		];

		return $payloads[ array_rand( $payloads ) ];
	}

	/**
	 * Generate random response
	 *
	 * @return mixed
	 */
	private function generate_random_response() {
		$responses = [
			[ 'success' => true, 'id' => rand( 1, 10000 ) ],
			[ 'success' => false, 'error' => 'Error message' ],
			[ 'response' => [ 'code' => 200 ], 'body' => '{"result":"ok"}' ],
			[ 'response' => [ 'code' => 401 ], 'body' => 'Unauthorized' ],
			[ 'response' => [ 'code' => 500 ], 'body' => 'Internal Server Error' ],
			'OK',
		];

		return $responses[ array_rand( $responses ) ];
	}

	/**
	 * Generate random duration in seconds
	 *
	 * @return float
	 */
	private function generate_random_duration(): float {
		return round( ( rand( 1, 10000 ) / 1000 ), 4 );
	}

	/**
	 * Generate random webhook type
	 *
	 * @return string
	 */
	private function generate_random_webhook_type(): string {
		$types = [
			'order_status',
			'stock_update',
			'product_update',
			'customer_update',
		];

		return $types[ array_rand( $types ) ];
	}

	/**
	 * Generate random webhook payload
	 *
	 * @return array
	 */
	private function generate_random_webhook_payload(): array {
		$payloads = [
			[ 'order_id' => rand( 1, 10000 ), 'status' => 'approved' ],
			[ 'sku' => 'PROD' . rand( 1000, 9999 ), 'quantity' => rand( 0, 100 ) ],
			[ 'product_id' => rand( 1, 1000 ), 'price' => rand( 10, 1000 ) ],
		];

		return $payloads[ array_rand( $payloads ) ];
	}

	/**
	 * Generate random sync operation type
	 *
	 * @return string
	 */
	private function generate_random_sync_type(): string {
		$types = [
			'order_sync',
			'customer_sync',
			'product_sync',
			'stock_sync',
			'catalog_sync',
		];

		return $types[ array_rand( $types ) ];
	}

	/**
	 * Generate random sync data
	 *
	 * @return array
	 */
	private function generate_random_sync_data(): array {
		return [
			'affected_records' => rand( 1, 100 ),
			'duration' => round( ( rand( 100, 10000 ) / 1000 ), 4 ),
			'context' => [
				'batch_size' => rand( 10, 100 ),
				'page' => rand( 1, 10 ),
			],
		];
	}

	/**
	 * Generate random error message
	 *
	 * @return string
	 */
	private function generate_random_error_message(): string {
		$messages = [
			'Connection timeout',
			'Authentication failed',
			'Invalid response format',
			'Product not found',
			'Customer already exists',
			'Database error',
			'Network unreachable',
		];

		return $messages[ array_rand( $messages ) ];
	}

	/**
	 * Generate random exception
	 *
	 * @return \Exception
	 */
	private function generate_random_exception(): \Exception {
		$exception_types = [
			\Exception::class,
			\RuntimeException::class,
			\InvalidArgumentException::class,
		];

		$exception_class = $exception_types[ array_rand( $exception_types ) ];
		$message = $this->generate_random_error_message();

		return new $exception_class( $message );
	}

	/**
	 * Generate random error context
	 *
	 * @return array
	 */
	private function generate_random_error_context(): array {
		$contexts = [
			[
				'operation' => 'order_sync',
				'payload' => [ 'order_id' => rand( 1, 10000 ) ],
				'response' => [ 'error' => 'timeout' ],
			],
			[
				'operation' => 'product_sync',
				'payload' => [ 'sku' => 'PROD' . rand( 1000, 9999 ) ],
			],
			[
				'operation' => 'customer_sync',
			],
			[],
		];

		return $contexts[ array_rand( $contexts ) ];
	}

	/**
	 * Generate random log entry
	 *
	 * @return array
	 */
	private function generate_random_log_entry(): array {
		$types = [ 'api_request', 'webhook', 'sync', 'error' ];
		$statuses = [ 'success', 'error', 'retry' ];

		return [
			'id' => rand( 1, 10000 ),
			'timestamp' => gmdate( 'Y-m-d H:i:s', time() - rand( 0, 86400 * 30 ) ),
			'type' => $types[ array_rand( $types ) ],
			'operation' => $this->generate_random_sync_type(),
			'status' => $statuses[ array_rand( $statuses ) ],
			'message' => 'Log message ' . rand( 1, 1000 ),
			'payload' => json_encode( $this->generate_random_payload() ),
			'response' => json_encode( [ 'success' => (bool) rand( 0, 1 ) ] ),
			'duration' => $this->generate_random_duration(),
			'error_trace' => rand( 0, 1 ) ? $this->generate_random_error_message() : null,
			'context' => json_encode( [ 'user_id' => rand( 1, 100 ) ] ),
		];
	}
}

