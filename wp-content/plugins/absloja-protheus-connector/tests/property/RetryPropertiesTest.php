<?php
/**
 * Property-based tests for Retry_Manager
 *
 * Tests correctness properties related to retry operations.
 *
 * @package ABSLoja\ProtheusConnector\Tests\Property
 */

namespace ABSLoja\ProtheusConnector\Tests\Property;

use ABSLoja\ProtheusConnector\Modules\Retry_Manager;
use ABSLoja\ProtheusConnector\Modules\Logger;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

/**
 * Retry_Manager property-based tests
 */
class RetryPropertiesTest extends TestCase {

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
	 * Feature: absloja-protheus-connector, Property 42: Retry Scheduling on Failure
	 *
	 * For any order sync operation that fails, the Retry_Manager should schedule
	 * a retry attempt using WP-Cron with next execution time set to 1 hour from failure.
	 *
	 * **Validates: Requirements 9.1**
	 */
	public function test_retry_scheduling_on_failure_sets_correct_next_attempt_time() {
		$iterations = 100;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				global $wpdb;
				$wpdb = Mockery::mock( 'wpdb' );
				$wpdb->prefix = 'wp_';
				$wpdb->insert_id = rand( 1, 10000 );

				$logger = Mockery::mock( Logger::class );

				// Generate random operation data
				$operation_type = $this->generate_random_operation_type();
				$data = $this->generate_random_operation_data();
				$error = $this->generate_random_error_message();

				$current_time = time();
				$expected_next_attempt = $current_time + 3600; // 1 hour later

				Functions\expect( 'current_time' )
					->twice()
					->with( 'mysql' )
					->andReturn( gmdate( 'Y-m-d H:i:s', $current_time ) );

				Functions\expect( 'time' )
					->once()
					->andReturn( $current_time );

				Functions\expect( 'gmdate' )
					->once()
					->with( 'Y-m-d H:i:s', $expected_next_attempt )
					->andReturn( gmdate( 'Y-m-d H:i:s', $expected_next_attempt ) );

				Functions\expect( 'wp_json_encode' )
					->once()
					->andReturnUsing( function( $data ) {
						return json_encode( $data );
					} );

				// Capture the inserted data
				$inserted_data = null;
				$wpdb->shouldReceive( 'insert' )
					->once()
					->with( 'wp_absloja_retry_queue', Mockery::on( function( $data ) use ( &$inserted_data ) {
						$inserted_data = $data;
						return true;
					} ) )
					->andReturn( 1 );

				$logger->shouldReceive( 'log_sync_operation' )
					->once();

				$retry_manager = new Retry_Manager( $logger );
				$result = $retry_manager->schedule_retry( $operation_type, $data, $error );

				// Verify retry was scheduled
				$this->assertNotFalse( $result, "Retry should be scheduled (iteration $i)" );
				$this->assertNotNull( $inserted_data, "Data should be inserted (iteration $i)" );

				// Verify next_attempt is set to 1 hour from now
				$this->assertArrayHasKey( 'next_attempt', $inserted_data, "next_attempt should be present (iteration $i)" );
				$next_attempt_timestamp = strtotime( $inserted_data['next_attempt'] );
				$expected_timestamp = $expected_next_attempt;
				
				// Allow 1 second tolerance for timing differences
				$this->assertEqualsWithDelta( 
					$expected_timestamp, 
					$next_attempt_timestamp, 
					1, 
					"next_attempt should be 1 hour from failure time (iteration $i)" 
				);

				// Verify initial state
				$this->assertEquals( 0, $inserted_data['attempts'], "Initial attempts should be 0 (iteration $i)" );
				$this->assertEquals( 'pending', $inserted_data['status'], "Initial status should be 'pending' (iteration $i)" );
				$this->assertEquals( 5, $inserted_data['max_attempts'], "max_attempts should be 5 (iteration $i)" );

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
	 * Feature: absloja-protheus-connector, Property 43: Maximum Retry Attempts
	 *
	 * For any failed operation in the retry queue, the Retry_Manager should attempt
	 * a maximum of 5 retries before marking as permanently failed.
	 *
	 * **Validates: Requirements 9.3**
	 */
	public function test_maximum_retry_attempts_enforced() {
		$iterations = 100;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				global $wpdb;
				$wpdb = Mockery::mock( 'wpdb' );
				$wpdb->prefix = 'wp_';

				$logger = Mockery::mock( Logger::class );

				// Generate random retry entry that has reached max attempts
				$attempts_before_max = rand( 0, 4 ); // 0-4 attempts
				$retry_entry = [
					'id' => rand( 1, 10000 ),
					'operation_type' => $this->generate_random_operation_type(),
					'data' => json_encode( $this->generate_random_operation_data() ),
					'attempts' => $attempts_before_max,
					'max_attempts' => 5,
					'next_attempt' => gmdate( 'Y-m-d H:i:s', time() - 3600 ),
					'last_error' => $this->generate_random_error_message(),
					'status' => 'pending',
					'created_at' => gmdate( 'Y-m-d H:i:s', time() - 86400 ),
					'updated_at' => gmdate( 'Y-m-d H:i:s', time() - 3600 ),
				];

				Functions\expect( 'current_time' )
					->andReturn( gmdate( 'Y-m-d H:i:s' ) );

				$wpdb->shouldReceive( 'prepare' )
					->andReturn( "SELECT * FROM wp_absloja_retry_queue WHERE status = 'pending' AND next_attempt <= '" . gmdate( 'Y-m-d H:i:s' ) . "' ORDER BY next_attempt ASC" );

				$wpdb->shouldReceive( 'get_results' )
					->once()
					->andReturn( [ $retry_entry ] );

				// Mock failed retry execution
				Functions\expect( 'apply_filters' )
					->once()
					->andReturn( false );

				$attempts_after = $attempts_before_max + 1;

				if ( $attempts_after >= 5 ) {
					// Should mark as failed when reaching max attempts
					$wpdb->shouldReceive( 'prepare' )
						->once()
						->andReturn( "SELECT * FROM wp_absloja_retry_queue WHERE id = {$retry_entry['id']}" );

					$wpdb->shouldReceive( 'get_row' )
						->once()
						->andReturn( (object) array_merge( $retry_entry, [ 'attempts' => $attempts_after ] ) );

					$wpdb->shouldReceive( 'update' )
						->once()
						->with(
							'wp_absloja_retry_queue',
							Mockery::on( function( $data ) {
								return $data['status'] === 'failed';
							} ),
							[ 'id' => $retry_entry['id'] ],
							[ '%s', '%s' ],
							[ '%d' ]
						)
						->andReturn( 1 );

					$logger->shouldReceive( 'log_sync_operation' )
						->once()
						->with( 'retry_permanently_failed', Mockery::any(), false, 'Maximum retry attempts exhausted' );

					// Mock notification
					Functions\expect( 'get_option' )
						->twice()
						->andReturnUsing( function( $option ) {
							return $option === 'admin_email' ? 'admin@example.com' : 'Test Site';
						} );

					Functions\expect( '__' )
						->twice()
						->andReturnUsing( function( $text ) {
							return $text;
						} );

					Functions\expect( 'wp_mail' )
						->once()
						->andReturn( true );
				} else {
					// Should reschedule when under max attempts
					Functions\expect( 'time' )
						->once()
						->andReturn( time() );

					Functions\expect( 'gmdate' )
						->once()
						->andReturn( gmdate( 'Y-m-d H:i:s', time() + 3600 ) );

					$wpdb->shouldReceive( 'update' )
						->once()
						->with(
							'wp_absloja_retry_queue',
							Mockery::on( function( $data ) use ( $attempts_after ) {
								return $data['attempts'] === $attempts_after && $data['status'] === 'pending';
							} ),
							[ 'id' => $retry_entry['id'] ],
							[ '%d', '%s', '%s', '%s' ],
							[ '%d' ]
						)
						->andReturn( 1 );

					$logger->shouldReceive( 'log_sync_operation' )
						->once()
						->with( 'retry_rescheduled', Mockery::any(), true );
				}

				$retry_manager = new Retry_Manager( $logger );
				$result = $retry_manager->process_retries();

				// Verify results
				$this->assertEquals( 1, $result['processed'], "Should process 1 retry (iteration $i)" );

				if ( $attempts_after >= 5 ) {
					$this->assertEquals( 1, $result['failed'], "Should mark as failed when max attempts reached (iteration $i)" );
					$this->assertEquals( 0, $result['succeeded'], "Should not succeed (iteration $i)" );
				} else {
					$this->assertEquals( 0, $result['failed'], "Should not mark as failed when under max attempts (iteration $i)" );
					$this->assertEquals( 0, $result['succeeded'], "Should not succeed (iteration $i)" );
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
	 * Feature: absloja-protheus-connector, Property 44: Permanent Failure Notification
	 *
	 * For any operation where all retry attempts are exhausted, the Retry_Manager
	 * should mark the operation as permanently failed and send an admin notification.
	 *
	 * **Validates: Requirements 9.4**
	 */
	public function test_permanent_failure_notification_sent_when_retries_exhausted() {
		$iterations = 100;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				global $wpdb;
				$wpdb = Mockery::mock( 'wpdb' );
				$wpdb->prefix = 'wp_';

				$logger = Mockery::mock( Logger::class );

				// Generate retry entry that has exhausted all attempts
				$retry_entry = [
					'id' => rand( 1, 10000 ),
					'operation_type' => $this->generate_random_operation_type(),
					'data' => json_encode( $this->generate_random_operation_data() ),
					'attempts' => 5,
					'max_attempts' => 5,
					'next_attempt' => gmdate( 'Y-m-d H:i:s', time() - 3600 ),
					'last_error' => $this->generate_random_error_message(),
					'status' => 'pending',
					'created_at' => gmdate( 'Y-m-d H:i:s', time() - 86400 * 5 ),
					'updated_at' => gmdate( 'Y-m-d H:i:s', time() - 3600 ),
				];

				Functions\expect( 'current_time' )
					->andReturn( gmdate( 'Y-m-d H:i:s' ) );

				$wpdb->shouldReceive( 'prepare' )
					->andReturn( "SELECT * FROM wp_absloja_retry_queue WHERE id = {$retry_entry['id']}" );

				$wpdb->shouldReceive( 'get_row' )
					->once()
					->andReturn( (object) $retry_entry );

				// Verify status is updated to 'failed'
				$status_updated_to_failed = false;
				$wpdb->shouldReceive( 'update' )
					->once()
					->with(
						'wp_absloja_retry_queue',
						Mockery::on( function( $data ) use ( &$status_updated_to_failed ) {
							if ( $data['status'] === 'failed' ) {
								$status_updated_to_failed = true;
								return true;
							}
							return false;
						} ),
						[ 'id' => $retry_entry['id'] ],
						[ '%s', '%s' ],
						[ '%d' ]
					)
					->andReturn( 1 );

				// Verify permanent failure is logged
				$permanent_failure_logged = false;
				$logger->shouldReceive( 'log_sync_operation' )
					->once()
					->with(
						'retry_permanently_failed',
						Mockery::on( function( $data ) use ( $retry_entry, &$permanent_failure_logged ) {
							$permanent_failure_logged = true;
							$this->assertEquals( $retry_entry['id'], $data['retry_id'] );
							$this->assertEquals( $retry_entry['operation_type'], $data['operation_type'] );
							$this->assertEquals( $retry_entry['attempts'], $data['attempts'] );
							return true;
						} ),
						false,
						'Maximum retry attempts exhausted'
					);

				// Verify admin notification is sent
				$admin_email = 'admin' . rand( 1, 1000 ) . '@example.com';
				$site_name = 'Test Site ' . rand( 1, 100 );

				Functions\expect( 'get_option' )
					->twice()
					->andReturnUsing( function( $option ) use ( $admin_email, $site_name ) {
						if ( $option === 'admin_email' ) {
							return $admin_email;
						}
						return $site_name;
					} );

				Functions\expect( '__' )
					->twice()
					->andReturnUsing( function( $text ) {
						return $text;
					} );

				$email_sent = false;
				Functions\expect( 'wp_mail' )
					->once()
					->with(
						$admin_email,
						Mockery::type( 'string' ),
						Mockery::on( function( $message ) use ( $retry_entry, &$email_sent ) {
							$email_sent = true;
							// Verify email contains operation details
							$this->assertStringContainsString( $retry_entry['operation_type'], $message );
							$this->assertStringContainsString( (string) $retry_entry['attempts'], $message );
							return true;
						} )
					)
					->andReturn( true );

				$retry_manager = new Retry_Manager( $logger );
				$result = $retry_manager->mark_as_failed( $retry_entry['id'] );

				// Verify all actions were performed
				$this->assertTrue( $result, "mark_as_failed should return true (iteration $i)" );
				$this->assertTrue( $status_updated_to_failed, "Status should be updated to 'failed' (iteration $i)" );
				$this->assertTrue( $permanent_failure_logged, "Permanent failure should be logged (iteration $i)" );
				$this->assertTrue( $email_sent, "Admin notification email should be sent (iteration $i)" );

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
	 * Feature: absloja-protheus-connector, Property 45: Retry Queue Removal on Success
	 *
	 * For any retry attempt that succeeds, the Retry_Manager should remove the
	 * operation from the retry queue and log the success.
	 *
	 * **Validates: Requirements 9.5**
	 */
	public function test_retry_queue_removal_on_success() {
		$iterations = 100;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				global $wpdb;
				$wpdb = Mockery::mock( 'wpdb' );
				$wpdb->prefix = 'wp_';

				$logger = Mockery::mock( Logger::class );

				// Generate random retry entry
				$retry_entry = [
					'id' => rand( 1, 10000 ),
					'operation_type' => $this->generate_random_operation_type(),
					'data' => json_encode( $this->generate_random_operation_data() ),
					'attempts' => rand( 0, 4 ),
					'max_attempts' => 5,
					'next_attempt' => gmdate( 'Y-m-d H:i:s', time() - 3600 ),
					'last_error' => $this->generate_random_error_message(),
					'status' => 'pending',
					'created_at' => gmdate( 'Y-m-d H:i:s', time() - 86400 ),
					'updated_at' => gmdate( 'Y-m-d H:i:s', time() - 3600 ),
				];

				Functions\expect( 'current_time' )
					->andReturn( gmdate( 'Y-m-d H:i:s' ) );

				$wpdb->shouldReceive( 'prepare' )
					->once()
					->andReturn( "SELECT * FROM wp_absloja_retry_queue WHERE status = 'pending' AND next_attempt <= '" . gmdate( 'Y-m-d H:i:s' ) . "' ORDER BY next_attempt ASC" );

				$wpdb->shouldReceive( 'get_results' )
					->once()
					->andReturn( [ $retry_entry ] );

				// Mock successful retry execution
				Functions\expect( 'apply_filters' )
					->once()
					->with( 'absloja_protheus_execute_retry', false, $retry_entry['operation_type'], Mockery::any() )
					->andReturn( true );

				// Verify entry is deleted from queue
				$entry_deleted = false;
				$wpdb->shouldReceive( 'delete' )
					->once()
					->with(
						'wp_absloja_retry_queue',
						[ 'id' => $retry_entry['id'] ],
						[ '%d' ]
					)
					->andReturnUsing( function() use ( &$entry_deleted ) {
						$entry_deleted = true;
						return 1;
					} );

				// Verify success is logged
				$success_logged = false;
				$logger->shouldReceive( 'log_sync_operation' )
					->once()
					->with(
						'retry_succeeded',
						Mockery::on( function( $data ) use ( $retry_entry, &$success_logged ) {
							$success_logged = true;
							$this->assertEquals( $retry_entry['id'], $data['retry_id'] );
							$this->assertEquals( $retry_entry['operation_type'], $data['operation_type'] );
							$this->assertEquals( $retry_entry['attempts'] + 1, $data['attempts'] );
							return true;
						} ),
						true
					);

				$retry_manager = new Retry_Manager( $logger );
				$result = $retry_manager->process_retries();

				// Verify results
				$this->assertEquals( 1, $result['processed'], "Should process 1 retry (iteration $i)" );
				$this->assertEquals( 1, $result['succeeded'], "Should succeed (iteration $i)" );
				$this->assertEquals( 0, $result['failed'], "Should not fail (iteration $i)" );
				$this->assertTrue( $entry_deleted, "Entry should be deleted from queue (iteration $i)" );
				$this->assertTrue( $success_logged, "Success should be logged (iteration $i)" );

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
	 * Generate random operation type
	 *
	 * @return string
	 */
	private function generate_random_operation_type(): string {
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
	 * Generate random operation data
	 *
	 * @return array
	 */
	private function generate_random_operation_data(): array {
		$data_types = [
			[ 'order_id' => rand( 1, 10000 ) ],
			[ 'customer_id' => rand( 1, 1000 ), 'cpf' => $this->generate_random_cpf() ],
			[ 'sku' => 'PROD' . rand( 1000, 9999 ), 'quantity' => rand( 1, 100 ) ],
			[ 'product_id' => rand( 1, 5000 ), 'price' => rand( 10, 1000 ) ],
			[ 'batch_size' => rand( 10, 100 ), 'page' => rand( 1, 10 ) ],
		];

		return $data_types[ array_rand( $data_types ) ];
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
			'API rate limit exceeded',
			'Invalid credentials',
			'TES not found for state',
		];

		return $messages[ array_rand( $messages ) ];
	}


	/**
	 * Generate random CPF
	 *
	 * @return string
	 */
	private function generate_random_cpf(): string {
		return sprintf(
			'%03d.%03d.%03d-%02d',
			rand( 100, 999 ),
			rand( 100, 999 ),
			rand( 100, 999 ),
			rand( 10, 99 )
		);
	}
}
