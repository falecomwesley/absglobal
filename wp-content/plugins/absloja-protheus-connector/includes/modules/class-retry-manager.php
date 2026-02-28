<?php
/**
 * Retry Manager module for handling failed operations.
 *
 * @package    ABSLoja\ProtheusConnector
 * @subpackage ABSLoja\ProtheusConnector\Modules
 */

namespace ABSLoja\ProtheusConnector\Modules;

/**
 * Retry_Manager class.
 *
 * Manages the retry queue for failed operations, scheduling retries,
 * processing pending retries, and handling permanent failures.
 */
class Retry_Manager {

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Maximum number of retry attempts.
	 *
	 * @var int
	 */
	private const MAX_ATTEMPTS = 5;

	/**
	 * Retry interval in seconds (1 hour).
	 *
	 * @var int
	 */
	private const RETRY_INTERVAL = 3600;

	/**
	 * Constructor.
	 *
	 * @param Logger $logger Logger instance for dependency injection.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Schedule a retry for a failed operation.
	 *
	 * Adds a failed operation to the retry queue with initial attempt count
	 * and next retry time set to 1 hour from now.
	 *
	 * @param string      $operation_type The type of operation (e.g., 'order_sync', 'customer_sync').
	 * @param array       $data           The data needed to retry the operation.
	 * @param string|null $error          Optional error message from the failed operation.
	 * @return int|false The retry queue entry ID on success, false on failure.
	 */
	public function schedule_retry( string $operation_type, array $data, ?string $error = null ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'absloja_retry_queue';

		$next_attempt = gmdate( 'Y-m-d H:i:s', time() + self::RETRY_INTERVAL );

		$retry_data = array(
			'operation_type' => $operation_type,
			'data'           => wp_json_encode( $data ),
			'attempts'       => 0,
			'max_attempts'   => self::MAX_ATTEMPTS,
			'next_attempt'   => $next_attempt,
			'last_error'     => $error,
			'status'         => 'pending',
			'created_at'     => current_time( 'mysql' ),
			'updated_at'     => current_time( 'mysql' ),
		);

		$result = $wpdb->insert( $table_name, $retry_data );

		if ( $result ) {
			$retry_id = $wpdb->insert_id;

			// Log the retry scheduling.
			$this->logger->log_sync_operation(
				'retry_scheduled',
				array(
					'retry_id'       => $retry_id,
					'operation_type' => $operation_type,
					'next_attempt'   => $next_attempt,
				),
				true
			);

			return $retry_id;
		}

		return false;
	}

	/**
	 * Process pending retries.
	 *
	 * Retrieves all pending retry entries whose next_attempt time has passed
	 * and attempts to reprocess them. This method is designed to be called
	 * by WP-Cron on an hourly schedule.
	 *
	 * @return array Array of results with 'processed', 'succeeded', and 'failed' counts.
	 */
	public function process_retries(): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'absloja_retry_queue';

		// Get all pending retries that are due for processing.
		$current_time = current_time( 'mysql' );
		$retries      = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE status = %s AND next_attempt <= %s ORDER BY next_attempt ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'pending',
				$current_time
			),
			ARRAY_A
		);

		$results = array(
			'processed' => 0,
			'succeeded' => 0,
			'failed'    => 0,
		);

		foreach ( $retries as $retry ) {
			$results['processed']++;

			// Decode the data.
			$data = json_decode( $retry['data'], true );

			// Attempt to process the retry based on operation type.
			$success = $this->execute_retry( $retry['operation_type'], $data );

			// Update the retry entry.
			$retry['attempts']++;
			$retry['updated_at'] = current_time( 'mysql' );

			if ( $success ) {
				// Retry succeeded - remove from queue.
				$this->remove_from_queue( $retry['id'] );

				$this->logger->log_sync_operation(
					'retry_succeeded',
					array(
						'retry_id'       => $retry['id'],
						'operation_type' => $retry['operation_type'],
						'attempts'       => $retry['attempts'],
					),
					true
				);

				$results['succeeded']++;
			} else {
				// Retry failed - check if we should try again.
				if ( $retry['attempts'] >= $retry['max_attempts'] ) {
					// Max attempts reached - mark as permanently failed.
					$this->mark_as_failed( $retry['id'] );
					$results['failed']++;
				} else {
					// Schedule next retry.
					$next_attempt = gmdate( 'Y-m-d H:i:s', time() + self::RETRY_INTERVAL );

					$wpdb->update(
						$table_name,
						array(
							'attempts'     => $retry['attempts'],
							'next_attempt' => $next_attempt,
							'status'       => 'pending',
							'updated_at'   => $retry['updated_at'],
						),
						array( 'id' => $retry['id'] ),
						array( '%d', '%s', '%s', '%s' ),
						array( '%d' )
					);

					$this->logger->log_sync_operation(
						'retry_rescheduled',
						array(
							'retry_id'       => $retry['id'],
							'operation_type' => $retry['operation_type'],
							'attempts'       => $retry['attempts'],
							'next_attempt'   => $next_attempt,
						),
						true
					);
				}
			}
		}

		return $results;
	}

	/**
	 * Get all pending retries.
	 *
	 * Retrieves all operations currently in the retry queue with their details.
	 * Used by the admin interface to display pending retries.
	 *
	 * @return array Array of pending retry entries.
	 */
	public function get_pending_retries(): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'absloja_retry_queue';

		$retries = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE status = %s ORDER BY next_attempt ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'pending'
			),
			ARRAY_A
		);

		// Decode JSON data for each retry.
		foreach ( $retries as &$retry ) {
			if ( ! empty( $retry['data'] ) ) {
				$retry['data'] = json_decode( $retry['data'], true );
			}
		}

		return $retries;
	}

	/**
	 * Manually retry a specific operation.
	 *
	 * Allows manual triggering of a retry from the admin interface.
	 * Attempts to process the retry immediately regardless of next_attempt time.
	 *
	 * @param int $retry_id The ID of the retry entry to process.
	 * @return bool True if retry succeeded, false otherwise.
	 */
	public function manual_retry( int $retry_id ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'absloja_retry_queue';

		// Get the retry entry.
		$retry = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$retry_id
			),
			ARRAY_A
		);

		if ( ! $retry ) {
			return false;
		}

		// Decode the data.
		$data = json_decode( $retry['data'], true );

		// Attempt to process the retry.
		$success = $this->execute_retry( $retry['operation_type'], $data );

		// Update the retry entry.
		$retry['attempts']++;
		$retry['updated_at'] = current_time( 'mysql' );

		if ( $success ) {
			// Retry succeeded - remove from queue.
			$this->remove_from_queue( $retry_id );

			$this->logger->log_sync_operation(
				'manual_retry_succeeded',
				array(
					'retry_id'       => $retry_id,
					'operation_type' => $retry['operation_type'],
					'attempts'       => $retry['attempts'],
				),
				true
			);

			return true;
		} else {
			// Retry failed - check if we should try again.
			if ( $retry['attempts'] >= $retry['max_attempts'] ) {
				// Max attempts reached - mark as permanently failed.
				$this->mark_as_failed( $retry_id );
			} else {
				// Schedule next retry.
				$next_attempt = gmdate( 'Y-m-d H:i:s', time() + self::RETRY_INTERVAL );

				$wpdb->update(
					$table_name,
					array(
						'attempts'     => $retry['attempts'],
						'next_attempt' => $next_attempt,
						'status'       => 'pending',
						'updated_at'   => $retry['updated_at'],
					),
					array( 'id' => $retry_id ),
					array( '%d', '%s', '%s', '%s' ),
					array( '%d' )
				);

				$this->logger->log_sync_operation(
					'manual_retry_failed',
					array(
						'retry_id'       => $retry_id,
						'operation_type' => $retry['operation_type'],
						'attempts'       => $retry['attempts'],
						'next_attempt'   => $next_attempt,
					),
					false
				);
			}

			return false;
		}
	}

	/**
	 * Mark a retry as permanently failed.
	 *
	 * Updates the retry entry status to 'failed' and sends an admin notification.
	 * This is called when all retry attempts have been exhausted.
	 *
	 * @param int $retry_id The ID of the retry entry to mark as failed.
	 * @return bool True on success, false on failure.
	 */
	public function mark_as_failed( int $retry_id ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'absloja_retry_queue';

		// Get the retry entry for notification details.
		$retry = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$retry_id
			),
			ARRAY_A
		);

		if ( ! $retry ) {
			return false;
		}

		// Update status to failed.
		$result = $wpdb->update(
			$table_name,
			array(
				'status'     => 'failed',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $retry_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( $result !== false ) {
			// Log the permanent failure.
			$this->logger->log_sync_operation(
				'retry_permanently_failed',
				array(
					'retry_id'       => $retry_id,
					'operation_type' => $retry['operation_type'],
					'attempts'       => $retry['attempts'],
					'last_error'     => $retry['last_error'],
				),
				false,
				'Maximum retry attempts exhausted'
			);

			// Send admin notification.
			$this->send_failure_notification( $retry );

			return true;
		}

		return false;
	}

	/**
	 * Execute a retry operation.
	 *
	 * Attempts to reprocess a failed operation based on its type.
	 * This method will be extended to handle different operation types
	 * as they are implemented (order_sync, customer_sync, etc.).
	 *
	 * @param string $operation_type The type of operation to retry.
	 * @param array  $data           The data needed for the operation.
	 * @return bool True if operation succeeded, false otherwise.
	 */
	private function execute_retry( string $operation_type, array $data ): bool {
		// This is a placeholder implementation.
		// In the full implementation, this would dispatch to the appropriate
		// sync module based on operation_type.
		//
		// Example:
		// switch ( $operation_type ) {
		//     case 'order_sync':
		//         return $this->order_sync->sync_order( $data['order_id'] );
		//     case 'customer_sync':
		//         return $this->customer_sync->create_customer( $data );
		//     default:
		//         return false;
		// }

		// For now, we'll use a WordPress action hook to allow other modules
		// to handle the retry execution.
		$result = apply_filters( 'absloja_protheus_execute_retry', false, $operation_type, $data );

		return (bool) $result;
	}

	/**
	 * Remove a retry entry from the queue.
	 *
	 * Deletes a retry entry after successful processing.
	 *
	 * @param int $retry_id The ID of the retry entry to remove.
	 * @return bool True on success, false on failure.
	 */
	private function remove_from_queue( int $retry_id ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'absloja_retry_queue';

		$result = $wpdb->delete(
			$table_name,
			array( 'id' => $retry_id ),
			array( '%d' )
		);

		return $result !== false;
	}

	/**
	 * Send admin notification for permanent failure.
	 *
	 * Sends an email to the site administrator when a retry operation
	 * has exhausted all attempts and is marked as permanently failed.
	 *
	 * @param array $retry The retry entry data.
	 * @return bool True if email was sent successfully, false otherwise.
	 */
	private function send_failure_notification( array $retry ): bool {
		$admin_email = get_option( 'admin_email' );
		$site_name   = get_option( 'blogname' );

		$subject = sprintf(
			/* translators: %s: Site name */
			__( '[%s] Protheus Integration - Permanent Operation Failure', 'absloja-protheus-connector' ),
			$site_name
		);

		$data_decoded = json_decode( $retry['data'], true );

		$message = sprintf(
			/* translators: 1: Operation type, 2: Number of attempts, 3: Last error message, 4: Operation data */
			__(
				"A Protheus integration operation has permanently failed after exhausting all retry attempts.\n\n" .
				"Operation Type: %1\$s\n" .
				"Attempts: %2\$d\n" .
				"Last Error: %3\$s\n\n" .
				"Operation Data:\n%4\$s\n\n" .
				"Please review this operation in the WordPress admin panel and take appropriate action.",
				'absloja-protheus-connector'
			),
			$retry['operation_type'],
			$retry['attempts'],
			$retry['last_error'] ?? 'Unknown error',
			print_r( $data_decoded, true ) // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		);

		return wp_mail( $admin_email, $subject, $message );
	}
}
