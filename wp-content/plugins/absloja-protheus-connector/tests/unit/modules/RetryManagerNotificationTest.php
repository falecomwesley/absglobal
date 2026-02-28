<?php
/**
 * Tests for Retry_Manager permanent failure notification functionality.
 *
 * @package ABSLoja\ProtheusConnector\Tests
 */

namespace ABSLoja\ProtheusConnector\Tests\Modules;

use PHPUnit\Framework\TestCase;
use ABSLoja\ProtheusConnector\Modules\Retry_Manager;
use ABSLoja\ProtheusConnector\Modules\Logger;

/**
 * Test Retry_Manager notification functionality.
 */
class RetryManagerNotificationTest extends TestCase {

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Retry_Manager instance.
	 *
	 * @var Retry_Manager
	 */
	private $retry_manager;

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Create Logger instance
		$this->logger = new Logger();

		// Create Retry_Manager instance
		$this->retry_manager = new Retry_Manager( $this->logger );

		// Clear any existing retry queue entries
		global $wpdb;
		$table_name = $wpdb->prefix . 'absloja_retry_queue';
		$wpdb->query( "TRUNCATE TABLE {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Test that send_failure_notification is called when marking as failed.
	 *
	 * This test verifies that when a retry operation is marked as permanently
	 * failed, an email notification is sent to the administrator.
	 */
	public function test_notification_sent_when_marked_as_failed() {
		// Schedule a retry operation
		$operation_type = 'order_sync';
		$data = array(
			'order_id' => 123,
			'customer_code' => 'CUST001',
		);
		$error = 'Test error message';

		$retry_id = $this->retry_manager->schedule_retry( $operation_type, $data, $error );

		$this->assertIsInt( $retry_id, 'schedule_retry should return an integer ID' );

		// Mock wp_mail to capture the email being sent
		$email_sent = false;
		$email_data = array();

		add_filter( 'pre_wp_mail', function( $null, $atts ) use ( &$email_sent, &$email_data ) {
			$email_sent = true;
			$email_data = $atts;
			return true; // Prevent actual email sending
		}, 10, 2 );

		// Mark the retry as failed
		$result = $this->retry_manager->mark_as_failed( $retry_id );

		$this->assertTrue( $result, 'mark_as_failed should return true' );
		$this->assertTrue( $email_sent, 'An email notification should be sent' );

		// Verify email details
		$this->assertArrayHasKey( 'to', $email_data, 'Email should have a recipient' );
		$this->assertArrayHasKey( 'subject', $email_data, 'Email should have a subject' );
		$this->assertArrayHasKey( 'message', $email_data, 'Email should have a message' );

		// Verify email is sent to admin
		$admin_email = get_option( 'admin_email' );
		$this->assertEquals( array( $admin_email ), $email_data['to'], 'Email should be sent to admin email' );

		// Verify subject contains relevant information
		$this->assertStringContainsString( 'Protheus Integration', $email_data['subject'], 'Subject should mention Protheus Integration' );
		$this->assertStringContainsString( 'Permanent Operation Failure', $email_data['subject'], 'Subject should mention permanent failure' );

		// Verify message contains operation details
		$this->assertStringContainsString( $operation_type, $email_data['message'], 'Message should contain operation type' );
		$this->assertStringContainsString( $error, $email_data['message'], 'Message should contain error message' );
		$this->assertStringContainsString( '123', $email_data['message'], 'Message should contain operation data' );
	}

	/**
	 * Test notification includes all required information.
	 *
	 * Validates: Requirements 9.4
	 * - Operation type
	 * - Number of attempts
	 * - Last error message
	 * - Operation data
	 */
	public function test_notification_includes_all_required_information() {
		global $wpdb;

		// Create a retry entry directly in the database with multiple attempts
		$table_name = $wpdb->prefix . 'absloja_retry_queue';
		$operation_type = 'customer_sync';
		$data = array(
			'customer_id' => 456,
			'billing_email' => 'test@example.com',
		);
		$last_error = 'CPF/CNPJ validation failed';
		$attempts = 5;

		$wpdb->insert(
			$table_name,
			array(
				'operation_type' => $operation_type,
				'data'           => wp_json_encode( $data ),
				'attempts'       => $attempts,
				'max_attempts'   => 5,
				'next_attempt'   => current_time( 'mysql' ),
				'last_error'     => $last_error,
				'status'         => 'pending',
				'created_at'     => current_time( 'mysql' ),
				'updated_at'     => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		$retry_id = $wpdb->insert_id;

		// Capture email
		$email_data = array();
		add_filter( 'pre_wp_mail', function( $null, $atts ) use ( &$email_data ) {
			$email_data = $atts;
			return true;
		}, 10, 2 );

		// Mark as failed
		$this->retry_manager->mark_as_failed( $retry_id );

		// Verify all required information is in the message
		$message = $email_data['message'];

		// 1. Operation type
		$this->assertStringContainsString( 
			'Operation Type: ' . $operation_type, 
			$message, 
			'Message should include operation type' 
		);

		// 2. Number of attempts
		$this->assertStringContainsString( 
			'Attempts: ' . $attempts, 
			$message, 
			'Message should include number of attempts' 
		);

		// 3. Last error message
		$this->assertStringContainsString( 
			'Last Error: ' . $last_error, 
			$message, 
			'Message should include last error message' 
		);

		// 4. Operation data
		$this->assertStringContainsString( 
			'456', 
			$message, 
			'Message should include customer_id from operation data' 
		);
		$this->assertStringContainsString( 
			'test@example.com', 
			$message, 
			'Message should include billing_email from operation data' 
		);
	}

	/**
	 * Test that notification is only sent when retry is exhausted.
	 *
	 * This verifies that notifications are not sent prematurely.
	 */
	public function test_notification_only_sent_on_permanent_failure() {
		// Schedule a retry
		$retry_id = $this->retry_manager->schedule_retry(
			'order_sync',
			array( 'order_id' => 789 ),
			'Temporary error'
		);

		// Track email sending
		$email_count = 0;
		add_filter( 'pre_wp_mail', function( $null ) use ( &$email_count ) {
			$email_count++;
			return true;
		}, 10, 1 );

		// Process retries multiple times (simulating failures)
		// This should NOT send notifications yet
		for ( $i = 0; $i < 4; $i++ ) {
			$this->retry_manager->process_retries();
		}

		$this->assertEquals( 0, $email_count, 'No notification should be sent before max attempts reached' );

		// Now mark as permanently failed
		$this->retry_manager->mark_as_failed( $retry_id );

		$this->assertEquals( 1, $email_count, 'Exactly one notification should be sent when marked as failed' );
	}

	/**
	 * Test that mark_as_failed returns false for non-existent retry.
	 */
	public function test_mark_as_failed_returns_false_for_invalid_id() {
		$result = $this->retry_manager->mark_as_failed( 99999 );
		$this->assertFalse( $result, 'mark_as_failed should return false for non-existent retry ID' );
	}

	/**
	 * Clean up after tests.
	 */
	protected function tearDown(): void {
		// Clear retry queue
		global $wpdb;
		$table_name = $wpdb->prefix . 'absloja_retry_queue';
		$wpdb->query( "TRUNCATE TABLE {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		parent::tearDown();
	}
}
