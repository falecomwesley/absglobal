<?php
/**
 * Retry Manager Unit Tests
 *
 * @package ABSLoja\ProtheusConnector\Tests\Unit\Modules
 */

namespace ABSLoja\ProtheusConnector\Tests\Unit\Modules;

use ABSLoja\ProtheusConnector\Modules\Retry_Manager;
use ABSLoja\ProtheusConnector\Modules\Logger;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

/**
 * Class RetryManagerTest
 *
 * Unit tests for Retry_Manager class.
 */
class RetryManagerTest extends TestCase {

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
	 * Test schedule_retry creates retry entry with correct data
	 */
	public function test_schedule_retry_creates_retry_entry() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->insert_id = 1;

		$logger = Mockery::mock( Logger::class );

		$operation_type = 'order_sync';
		$data           = array( 'order_id' => 123 );
		$error          = 'Connection timeout';

		Functions\expect( 'current_time' )
			->once()
			->with( 'mysql' )
			->andReturn( '2024-01-15 10:00:00' );

		Functions\expect( 'gmdate' )
			->once()
			->with( 'Y-m-d H:i:s', Mockery::type( 'int' ) )
			->andReturn( '2024-01-15 11:00:00' );

		Functions\expect( 'time' )
			->once()
			->andReturn( 1705316400 );

		Functions\expect( 'wp_json_encode' )
			->once()
			->with( $data )
			->andReturn( '{"order_id":123}' );

		$wpdb->shouldReceive( 'insert' )
			->once()
			->with( 'wp_absloja_retry_queue', Mockery::on( function( $retry_data ) use ( $operation_type, $error ) {
				$this->assertEquals( $operation_type, $retry_data['operation_type'] );
				$this->assertEquals( '{"order_id":123}', $retry_data['data'] );
				$this->assertEquals( 0, $retry_data['attempts'] );
				$this->assertEquals( 5, $retry_data['max_attempts'] );
				$this->assertEquals( '2024-01-15 11:00:00', $retry_data['next_attempt'] );
				$this->assertEquals( $error, $retry_data['last_error'] );
				$this->assertEquals( 'pending', $retry_data['status'] );
				$this->assertEquals( '2024-01-15 10:00:00', $retry_data['created_at'] );
				$this->assertEquals( '2024-01-15 10:00:00', $retry_data['updated_at'] );
				return true;
			} ) )
			->andReturn( 1 );

		$logger->shouldReceive( 'log_sync_operation' )
			->once()
			->with(
				'retry_scheduled',
				Mockery::on( function( $log_data ) {
					$this->assertEquals( 1, $log_data['retry_id'] );
					$this->assertEquals( 'order_sync', $log_data['operation_type'] );
					$this->assertEquals( '2024-01-15 11:00:00', $log_data['next_attempt'] );
					return true;
				} ),
				true
			);

		$retry_manager = new Retry_Manager( $logger );
		$result        = $retry_manager->schedule_retry( $operation_type, $data, $error );

		$this->assertEquals( 1, $result );
	}

	/**
	 * Test schedule_retry returns false on database insert failure
	 */
	public function test_schedule_retry_returns_false_on_insert_failure() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$logger = Mockery::mock( Logger::class );

		Functions\expect( 'current_time' )
			->once()
			->andReturn( '2024-01-15 10:00:00' );

		Functions\expect( 'gmdate' )
			->once()
			->andReturn( '2024-01-15 11:00:00' );

		Functions\expect( 'time' )
			->once()
			->andReturn( 1705316400 );

		Functions\expect( 'wp_json_encode' )
			->once()
			->andReturn( '{"order_id":123}' );

		$wpdb->shouldReceive( 'insert' )
			->once()
			->andReturn( false );

		$retry_manager = new Retry_Manager( $logger );
		$result        = $retry_manager->schedule_retry( 'order_sync', array( 'order_id' => 123 ), 'Error' );

		$this->assertFalse( $result );
	}

	/**
	 * Test process_retries processes pending retries successfully
	 */
	public function test_process_retries_processes_pending_retries_successfully() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$logger = Mockery::mock( Logger::class );

		$pending_retries = array(
			array(
				'id'             => 1,
				'operation_type' => 'order_sync',
				'data'           => '{"order_id":123}',
				'attempts'       => 0,
				'max_attempts'   => 5,
				'next_attempt'   => '2024-01-15 10:00:00',
				'last_error'     => 'Connection timeout',
				'status'         => 'pending',
				'created_at'     => '2024-01-15 09:00:00',
				'updated_at'     => '2024-01-15 09:00:00',
			),
		);

		Functions\expect( 'current_time' )
			->twice()
			->with( 'mysql' )
			->andReturn( '2024-01-15 10:30:00' );

		$wpdb->shouldReceive( 'prepare' )
			->once()
			->with(
				Mockery::pattern( '/SELECT \* FROM/' ),
				'pending',
				'2024-01-15 10:30:00'
			)
			->andReturn( "SELECT * FROM wp_absloja_retry_queue WHERE status = 'pending' AND next_attempt <= '2024-01-15 10:30:00' ORDER BY next_attempt ASC" );

		$wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( $pending_retries );

		// Mock successful retry execution
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'absloja_protheus_execute_retry', false, 'order_sync', array( 'order_id' => 123 ) )
			->andReturn( true );

		// Mock delete from queue
		$wpdb->shouldReceive( 'delete' )
			->once()
			->with( 'wp_absloja_retry_queue', array( 'id' => 1 ), array( '%d' ) )
			->andReturn( 1 );

		$logger->shouldReceive( 'log_sync_operation' )
			->once()
			->with(
				'retry_succeeded',
				Mockery::on( function( $data ) {
					$this->assertEquals( 1, $data['retry_id'] );
					$this->assertEquals( 'order_sync', $data['operation_type'] );
					$this->assertEquals( 1, $data['attempts'] );
					return true;
				} ),
				true
			);

		$retry_manager = new Retry_Manager( $logger );
		$result        = $retry_manager->process_retries();

		$this->assertEquals( 1, $result['processed'] );
		$this->assertEquals( 1, $result['succeeded'] );
		$this->assertEquals( 0, $result['failed'] );
	}

	/**
	 * Test process_retries reschedules failed retry when under max attempts
	 */
	public function test_process_retries_reschedules_failed_retry_when_under_max_attempts() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$logger = Mockery::mock( Logger::class );

		$pending_retries = array(
			array(
				'id'             => 2,
				'operation_type' => 'customer_sync',
				'data'           => '{"customer_id":456}',
				'attempts'       => 2,
				'max_attempts'   => 5,
				'next_attempt'   => '2024-01-15 10:00:00',
				'last_error'     => 'API error',
				'status'         => 'pending',
				'created_at'     => '2024-01-15 08:00:00',
				'updated_at'     => '2024-01-15 09:00:00',
			),
		);

		Functions\expect( 'current_time' )
			->twice()
			->with( 'mysql' )
			->andReturn( '2024-01-15 10:30:00' );

		Functions\expect( 'gmdate' )
			->once()
			->with( 'Y-m-d H:i:s', Mockery::type( 'int' ) )
			->andReturn( '2024-01-15 11:30:00' );

		Functions\expect( 'time' )
			->once()
			->andReturn( 1705320600 );

		$wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( "SELECT * FROM wp_absloja_retry_queue WHERE status = 'pending' AND next_attempt <= '2024-01-15 10:30:00' ORDER BY next_attempt ASC" );

		$wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( $pending_retries );

		// Mock failed retry execution
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'absloja_protheus_execute_retry', false, 'customer_sync', array( 'customer_id' => 456 ) )
			->andReturn( false );

		// Mock update for rescheduling
		$wpdb->shouldReceive( 'update' )
			->once()
			->with(
				'wp_absloja_retry_queue',
				Mockery::on( function( $data ) {
					$this->assertEquals( 3, $data['attempts'] );
					$this->assertEquals( '2024-01-15 11:30:00', $data['next_attempt'] );
					$this->assertEquals( 'pending', $data['status'] );
					return true;
				} ),
				array( 'id' => 2 ),
				array( '%d', '%s', '%s', '%s' ),
				array( '%d' )
			)
			->andReturn( 1 );

		$logger->shouldReceive( 'log_sync_operation' )
			->once()
			->with(
				'retry_rescheduled',
				Mockery::on( function( $data ) {
					$this->assertEquals( 2, $data['retry_id'] );
					$this->assertEquals( 3, $data['attempts'] );
					return true;
				} ),
				true
			);

		$retry_manager = new Retry_Manager( $logger );
		$result        = $retry_manager->process_retries();

		$this->assertEquals( 1, $result['processed'] );
		$this->assertEquals( 0, $result['succeeded'] );
		$this->assertEquals( 0, $result['failed'] );
	}

	/**
	 * Test process_retries marks as failed when max attempts reached
	 */
	public function test_process_retries_marks_as_failed_when_max_attempts_reached() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$logger = Mockery::mock( Logger::class );

		$pending_retries = array(
			array(
				'id'             => 3,
				'operation_type' => 'product_sync',
				'data'           => '{"sku":"PROD001"}',
				'attempts'       => 4,
				'max_attempts'   => 5,
				'next_attempt'   => '2024-01-15 10:00:00',
				'last_error'     => 'Product not found',
				'status'         => 'pending',
				'created_at'     => '2024-01-15 06:00:00',
				'updated_at'     => '2024-01-15 09:00:00',
			),
		);

		Functions\expect( 'current_time' )
			->times( 3 )
			->with( 'mysql' )
			->andReturn( '2024-01-15 10:30:00' );

		$wpdb->shouldReceive( 'prepare' )
			->twice()
			->andReturnUsing( function( $query ) {
				if ( strpos( $query, 'SELECT' ) !== false ) {
					return "SELECT * FROM wp_absloja_retry_queue WHERE status = 'pending' AND next_attempt <= '2024-01-15 10:30:00' ORDER BY next_attempt ASC";
				}
				return "SELECT * FROM wp_absloja_retry_queue WHERE id = 3";
			} );

		$wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( $pending_retries );

		// Mock failed retry execution
		Functions\expect( 'apply_filters' )
			->once()
			->andReturn( false );

		// Mock get_row for mark_as_failed
		$wpdb->shouldReceive( 'get_row' )
			->once()
			->andReturn( (object) $pending_retries[0] );

		// Mock update for marking as failed
		$wpdb->shouldReceive( 'update' )
			->once()
			->with(
				'wp_absloja_retry_queue',
				Mockery::on( function( $data ) {
					$this->assertEquals( 'failed', $data['status'] );
					return true;
				} ),
				array( 'id' => 3 ),
				array( '%s', '%s' ),
				array( '%d' )
			)
			->andReturn( 1 );

		$logger->shouldReceive( 'log_sync_operation' )
			->once()
			->with(
				'retry_permanently_failed',
				Mockery::on( function( $data ) {
					$this->assertEquals( 3, $data['retry_id'] );
					$this->assertEquals( 5, $data['attempts'] );
					return true;
				} ),
				false,
				'Maximum retry attempts exhausted'
			);

		// Mock email notification
		Functions\expect( 'get_option' )
			->twice()
			->andReturnUsing( function( $option ) {
				if ( $option === 'admin_email' ) {
					return 'admin@example.com';
				}
				return 'Test Site';
			} );

		Functions\expect( '__' )
			->twice()
			->andReturnUsing( function( $text ) {
				return $text;
			} );

		Functions\expect( 'wp_mail' )
			->once()
			->andReturn( true );

		$retry_manager = new Retry_Manager( $logger );
		$result        = $retry_manager->process_retries();

		$this->assertEquals( 1, $result['processed'] );
		$this->assertEquals( 0, $result['succeeded'] );
		$this->assertEquals( 1, $result['failed'] );
	}

	/**
	 * Test process_retries handles empty queue
	 */
	public function test_process_retries_handles_empty_queue() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$logger = Mockery::mock( Logger::class );

		Functions\expect( 'current_time' )
			->once()
			->with( 'mysql' )
			->andReturn( '2024-01-15 10:30:00' );

		$wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( "SELECT * FROM wp_absloja_retry_queue WHERE status = 'pending' AND next_attempt <= '2024-01-15 10:30:00' ORDER BY next_attempt ASC" );

		$wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		$retry_manager = new Retry_Manager( $logger );
		$result        = $retry_manager->process_retries();

		$this->assertEquals( 0, $result['processed'] );
		$this->assertEquals( 0, $result['succeeded'] );
		$this->assertEquals( 0, $result['failed'] );
	}

	/**
	 * Test get_pending_retries returns all pending retries
	 */
	public function test_get_pending_retries_returns_all_pending_retries() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$logger = Mockery::mock( Logger::class );

		$mock_retries = array(
			array(
				'id'             => 1,
				'operation_type' => 'order_sync',
				'data'           => '{"order_id":123}',
				'attempts'       => 1,
				'max_attempts'   => 5,
				'next_attempt'   => '2024-01-15 11:00:00',
				'last_error'     => 'Timeout',
				'status'         => 'pending',
				'created_at'     => '2024-01-15 09:00:00',
				'updated_at'     => '2024-01-15 10:00:00',
			),
			array(
				'id'             => 2,
				'operation_type' => 'customer_sync',
				'data'           => '{"customer_id":456}',
				'attempts'       => 2,
				'max_attempts'   => 5,
				'next_attempt'   => '2024-01-15 12:00:00',
				'last_error'     => 'API error',
				'status'         => 'pending',
				'created_at'     => '2024-01-15 08:00:00',
				'updated_at'     => '2024-01-15 10:00:00',
			),
		);

		$wpdb->shouldReceive( 'prepare' )
			->once()
			->with(
				Mockery::pattern( '/SELECT \* FROM/' ),
				'pending'
			)
			->andReturn( "SELECT * FROM wp_absloja_retry_queue WHERE status = 'pending' ORDER BY next_attempt ASC" );

		$wpdb->shouldReceive( 'get_results' )
			->once()
			->with( Mockery::any(), ARRAY_A )
			->andReturn( $mock_retries );

		$retry_manager = new Retry_Manager( $logger );
		$result        = $retry_manager->get_pending_retries();

		$this->assertIsArray( $result );
		$this->assertCount( 2, $result );
		$this->assertEquals( 1, $result[0]['id'] );
		$this->assertIsArray( $result[0]['data'] );
		$this->assertEquals( 123, $result[0]['data']['order_id'] );
		$this->assertEquals( 2, $result[1]['id'] );
		$this->assertIsArray( $result[1]['data'] );
		$this->assertEquals( 456, $result[1]['data']['customer_id'] );
	}

	/**
	 * Test manual_retry successfully retries operation
	 */
	public function test_manual_retry_successfully_retries_operation() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$logger = Mockery::mock( Logger::class );

		$retry_entry = array(
			'id'             => 5,
			'operation_type' => 'order_sync',
			'data'           => '{"order_id":789}',
			'attempts'       => 1,
			'max_attempts'   => 5,
			'next_attempt'   => '2024-01-15 12:00:00',
			'last_error'     => 'Timeout',
			'status'         => 'pending',
			'created_at'     => '2024-01-15 10:00:00',
			'updated_at'     => '2024-01-15 11:00:00',
		);

		Functions\expect( 'current_time' )
			->once()
			->with( 'mysql' )
			->andReturn( '2024-01-15 11:30:00' );

		$wpdb->shouldReceive( 'prepare' )
			->once()
			->with(
				Mockery::pattern( '/SELECT \* FROM/' ),
				5
			)
			->andReturn( "SELECT * FROM wp_absloja_retry_queue WHERE id = 5" );

		$wpdb->shouldReceive( 'get_row' )
			->once()
			->andReturn( (object) $retry_entry );

		// Mock successful retry execution
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'absloja_protheus_execute_retry', false, 'order_sync', array( 'order_id' => 789 ) )
			->andReturn( true );

		// Mock delete from queue
		$wpdb->shouldReceive( 'delete' )
			->once()
			->with( 'wp_absloja_retry_queue', array( 'id' => 5 ), array( '%d' ) )
			->andReturn( 1 );

		$logger->shouldReceive( 'log_sync_operation' )
			->once()
			->with(
				'manual_retry_succeeded',
				Mockery::on( function( $data ) {
					$this->assertEquals( 5, $data['retry_id'] );
					$this->assertEquals( 2, $data['attempts'] );
					return true;
				} ),
				true
			);

		$retry_manager = new Retry_Manager( $logger );
		$result        = $retry_manager->manual_retry( 5 );

		$this->assertTrue( $result );
	}

	/**
	 * Test manual_retry returns false when retry not found
	 */
	public function test_manual_retry_returns_false_when_retry_not_found() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$logger = Mockery::mock( Logger::class );

		$wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( "SELECT * FROM wp_absloja_retry_queue WHERE id = 999" );

		$wpdb->shouldReceive( 'get_row' )
			->once()
			->andReturn( null );

		$retry_manager = new Retry_Manager( $logger );
		$result        = $retry_manager->manual_retry( 999 );

		$this->assertFalse( $result );
	}

	/**
	 * Test mark_as_failed updates status and sends notification
	 */
	public function test_mark_as_failed_updates_status_and_sends_notification() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$logger = Mockery::mock( Logger::class );

		$retry_entry = array(
			'id'             => 10,
			'operation_type' => 'product_sync',
			'data'           => '{"sku":"PROD999"}',
			'attempts'       => 5,
			'max_attempts'   => 5,
			'next_attempt'   => '2024-01-15 15:00:00',
			'last_error'     => 'Product not found',
			'status'         => 'pending',
			'created_at'     => '2024-01-15 10:00:00',
			'updated_at'     => '2024-01-15 14:00:00',
		);

		Functions\expect( 'current_time' )
			->once()
			->with( 'mysql' )
			->andReturn( '2024-01-15 15:30:00' );

		$wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( "SELECT * FROM wp_absloja_retry_queue WHERE id = 10" );

		$wpdb->shouldReceive( 'get_row' )
			->once()
			->andReturn( (object) $retry_entry );

		$wpdb->shouldReceive( 'update' )
			->once()
			->with(
				'wp_absloja_retry_queue',
				Mockery::on( function( $data ) {
					$this->assertEquals( 'failed', $data['status'] );
					$this->assertEquals( '2024-01-15 15:30:00', $data['updated_at'] );
					return true;
				} ),
				array( 'id' => 10 ),
				array( '%s', '%s' ),
				array( '%d' )
			)
			->andReturn( 1 );

		$logger->shouldReceive( 'log_sync_operation' )
			->once()
			->with(
				'retry_permanently_failed',
				Mockery::on( function( $data ) {
					$this->assertEquals( 10, $data['retry_id'] );
					$this->assertEquals( 'product_sync', $data['operation_type'] );
					$this->assertEquals( 5, $data['attempts'] );
					$this->assertEquals( 'Product not found', $data['last_error'] );
					return true;
				} ),
				false,
				'Maximum retry attempts exhausted'
			);

		// Mock email notification
		Functions\expect( 'get_option' )
			->twice()
			->andReturnUsing( function( $option ) {
				if ( $option === 'admin_email' ) {
					return 'admin@example.com';
				}
				return 'Test Site';
			} );

		Functions\expect( '__' )
			->twice()
			->andReturnUsing( function( $text ) {
				return $text;
			} );

		Functions\expect( 'wp_mail' )
			->once()
			->with(
				'admin@example.com',
				Mockery::type( 'string' ),
				Mockery::type( 'string' )
			)
			->andReturn( true );

		$retry_manager = new Retry_Manager( $logger );
		$result        = $retry_manager->mark_as_failed( 10 );

		$this->assertTrue( $result );
	}

	/**
	 * Test mark_as_failed returns false when retry not found
	 */
	public function test_mark_as_failed_returns_false_when_retry_not_found() {
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$logger = Mockery::mock( Logger::class );

		$wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( "SELECT * FROM wp_absloja_retry_queue WHERE id = 999" );

		$wpdb->shouldReceive( 'get_row' )
			->once()
			->andReturn( null );

		$retry_manager = new Retry_Manager( $logger );
		$result        = $retry_manager->mark_as_failed( 999 );

		$this->assertFalse( $result );
	}
}
