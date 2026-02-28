<?php
/**
 * Logger module for transaction logging.
 *
 * @package    ABSLoja\ProtheusConnector
 * @subpackage ABSLoja\ProtheusConnector\Modules
 */

namespace ABSLoja\ProtheusConnector\Modules;

/**
 * Logger class.
 *
 * Handles logging of all plugin operations including API requests,
 * webhooks, sync operations, and errors.
 */
class Logger {

	/**
	 * Log an API request.
	 *
	 * Records API requests sent to Protheus with timestamp, endpoint,
	 * payload, response, and duration.
	 *
	 * @param string $endpoint The API endpoint called.
	 * @param array  $payload  The request payload.
	 * @param mixed  $response The API response.
	 * @param float  $duration The request duration in seconds.
	 * @return int|false The log entry ID on success, false on failure.
	 */
	public function log_api_request( string $endpoint, array $payload, $response, float $duration ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'absloja_logs';

		$status = $this->determine_response_status( $response );

		$data = array(
			'timestamp'  => current_time( 'mysql' ),
			'type'       => 'api_request',
			'operation'  => $endpoint,
			'status'     => $status,
			'message'    => sprintf( 'API request to %s', $endpoint ),
			'payload'    => wp_json_encode( $payload ),
			'response'   => is_string( $response ) ? $response : wp_json_encode( $response ),
			'duration'   => $duration,
			'error_trace' => null,
			'context'    => null,
		);

		$result = $wpdb->insert( $table_name, $data );

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Log a webhook request.
	 *
	 * Records webhook requests received from Protheus with timestamp,
	 * endpoint, payload, and response.
	 *
	 * @param string $type     The webhook type (e.g., 'order_status', 'stock').
	 * @param array  $payload  The webhook payload.
	 * @param mixed  $response The response sent back.
	 * @return int|false The log entry ID on success, false on failure.
	 */
	public function log_webhook( string $type, array $payload, $response ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'absloja_logs';

		$status = $this->determine_response_status( $response );

		$data = array(
			'timestamp'   => current_time( 'mysql' ),
			'type'        => 'webhook',
			'operation'   => $type,
			'status'      => $status,
			'message'     => sprintf( 'Webhook received: %s', $type ),
			'payload'     => wp_json_encode( $payload ),
			'response'    => is_string( $response ) ? $response : wp_json_encode( $response ),
			'duration'    => null,
			'error_trace' => null,
			'context'     => null,
		);

		$result = $wpdb->insert( $table_name, $data );

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Log a sync operation.
	 *
	 * Records sync operations with timestamp, operation type,
	 * affected records, and result status.
	 *
	 * @param string $type    The sync operation type (e.g., 'order_sync', 'product_sync').
	 * @param array  $data    Data about the sync operation.
	 * @param bool   $success Whether the operation succeeded.
	 * @param string $error   Optional error message if operation failed.
	 * @return int|false The log entry ID on success, false on failure.
	 */
	public function log_sync_operation( string $type, array $data, bool $success, ?string $error = null ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'absloja_logs';

		$log_data = array(
			'timestamp'   => current_time( 'mysql' ),
			'type'        => 'sync',
			'operation'   => $type,
			'status'      => $success ? 'success' : 'error',
			'message'     => $success
				? sprintf( 'Sync operation completed: %s', $type )
				: sprintf( 'Sync operation failed: %s', $type ),
			'payload'     => wp_json_encode( $data ),
			'response'    => null,
			'duration'    => isset( $data['duration'] ) ? $data['duration'] : null,
			'error_trace' => $error,
			'context'     => isset( $data['context'] ) ? wp_json_encode( $data['context'] ) : null,
		);

		$result = $wpdb->insert( $table_name, $log_data );

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Log an error.
	 *
	 * Records errors and exceptions with timestamp, error message,
	 * stack trace, and context data.
	 *
	 * @param string     $message   The error message.
	 * @param \Throwable $exception The exception object.
	 * @param array      $context   Additional context data.
	 * @return int|false The log entry ID on success, false on failure.
	 */
	public function log_error( string $message, \Throwable $exception, array $context = array() ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'absloja_logs';

		$data = array(
			'timestamp'   => current_time( 'mysql' ),
			'type'        => 'error',
			'operation'   => isset( $context['operation'] ) ? $context['operation'] : 'unknown',
			'status'      => 'error',
			'message'     => $message,
			'payload'     => isset( $context['payload'] ) ? wp_json_encode( $context['payload'] ) : null,
			'response'    => isset( $context['response'] ) ? wp_json_encode( $context['response'] ) : null,
			'duration'    => null,
			'error_trace' => $exception->getTraceAsString(),
			'context'     => wp_json_encode( $context ),
		);

		$result = $wpdb->insert( $table_name, $data );

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get logs with filtering and pagination.
	 *
	 * Retrieves log entries from the database with support for filtering
	 * by date range, type, status, operation, and pagination.
	 *
	 * @param array $filters {
	 *     Optional. Array of filters to apply.
	 *
	 *     @type string $date_from    Start date for filtering (Y-m-d H:i:s format).
	 *     @type string $date_to      End date for filtering (Y-m-d H:i:s format).
	 *     @type string $type         Log type filter (e.g., 'api_request', 'webhook', 'sync', 'error').
	 *     @type string $status       Status filter (e.g., 'success', 'error', 'retry').
	 *     @type string $operation    Operation filter (e.g., 'order_sync', 'product_sync').
	 *     @type int    $page         Page number for pagination (default: 1).
	 *     @type int    $per_page     Number of results per page (default: 20).
	 *     @type string $order_by     Column to order by (default: 'timestamp').
	 *     @type string $order        Sort order 'ASC' or 'DESC' (default: 'DESC').
	 * }
	 * @return array {
	 *     Array containing logs and pagination info.
	 *
	 *     @type array $logs       Array of log entries.
	 *     @type int   $total      Total number of logs matching filters.
	 *     @type int   $page       Current page number.
	 *     @type int   $per_page   Results per page.
	 *     @type int   $total_pages Total number of pages.
	 * }
	 */
	public function get_logs( array $filters = array() ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'absloja_logs';

		// Set defaults.
		$defaults = array(
			'date_from'  => null,
			'date_to'    => null,
			'type'       => null,
			'status'     => null,
			'operation'  => null,
			'page'       => 1,
			'per_page'   => 20,
			'order_by'   => 'timestamp',
			'order'      => 'DESC',
		);

		$filters = wp_parse_args( $filters, $defaults );

		// Validate and sanitize inputs.
		$filters['page']     = max( 1, absint( $filters['page'] ) );
		$filters['per_page'] = max( 1, min( 100, absint( $filters['per_page'] ) ) ); // Max 100 per page.
		$filters['order']    = strtoupper( $filters['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		// Validate order_by column.
		$allowed_order_by = array( 'id', 'timestamp', 'type', 'operation', 'status', 'duration' );
		if ( ! in_array( $filters['order_by'], $allowed_order_by, true ) ) {
			$filters['order_by'] = 'timestamp';
		}

		// Build WHERE clause.
		$where_clauses = array();
		$where_values  = array();

		if ( ! empty( $filters['date_from'] ) ) {
			$where_clauses[] = 'timestamp >= %s';
			$where_values[]  = $filters['date_from'];
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where_clauses[] = 'timestamp <= %s';
			$where_values[]  = $filters['date_to'];
		}

		if ( ! empty( $filters['type'] ) ) {
			$where_clauses[] = 'type = %s';
			$where_values[]  = $filters['type'];
		}

		if ( ! empty( $filters['status'] ) ) {
			$where_clauses[] = 'status = %s';
			$where_values[]  = $filters['status'];
		}

		if ( ! empty( $filters['operation'] ) ) {
			$where_clauses[] = 'operation = %s';
			$where_values[]  = $filters['operation'];
		}

		$where_sql = '';
		if ( ! empty( $where_clauses ) ) {
			$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
		}

		// Get total count.
		$count_query = "SELECT COUNT(*) FROM {$table_name} {$where_sql}";
		if ( ! empty( $where_values ) ) {
			$count_query = $wpdb->prepare( $count_query, $where_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		$total = (int) $wpdb->get_var( $count_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Calculate pagination.
		$total_pages = ceil( $total / $filters['per_page'] );
		$offset      = ( $filters['page'] - 1 ) * $filters['per_page'];

		// Build main query.
		$order_by_sql = sprintf( 'ORDER BY %s %s', esc_sql( $filters['order_by'] ), esc_sql( $filters['order'] ) );
		$limit_sql    = sprintf( 'LIMIT %d OFFSET %d', $filters['per_page'], $offset );

		$query = "SELECT * FROM {$table_name} {$where_sql} {$order_by_sql} {$limit_sql}";
		if ( ! empty( $where_values ) ) {
			$query = $wpdb->prepare( $query, $where_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$logs = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Decode JSON fields.
		foreach ( $logs as &$log ) {
			if ( ! empty( $log['payload'] ) ) {
				$log['payload'] = json_decode( $log['payload'], true );
			}
			if ( ! empty( $log['response'] ) ) {
				$log['response'] = json_decode( $log['response'], true );
			}
			if ( ! empty( $log['context'] ) ) {
				$log['context'] = json_decode( $log['context'], true );
			}
		}

		return array(
			'logs'        => $logs,
			'total'       => $total,
			'page'        => $filters['page'],
			'per_page'    => $filters['per_page'],
			'total_pages' => $total_pages,
		);
	}

	/**
	 * Count logs matching filters.
	 *
	 * @param array $filters {
	 *     Optional. Array of filter parameters.
	 *
	 *     @type string $date_from  Start date for filtering (Y-m-d H:i:s format).
	 *     @type string $date_to    End date for filtering (Y-m-d H:i:s format).
	 *     @type string $type       Log type to filter by.
	 *     @type string $status     Status to filter by.
	 *     @type string $operation  Operation to filter by.
	 * }
	 * @return int Number of logs matching filters.
	 */
	public function count_logs( array $filters = array() ): int {
		global $wpdb;

		$table_name = $wpdb->prefix . 'absloja_logs';

		// Build WHERE clause.
		$where_clauses = array();
		$where_values  = array();

		if ( ! empty( $filters['date_from'] ) ) {
			$where_clauses[] = 'timestamp >= %s';
			$where_values[]  = $filters['date_from'];
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where_clauses[] = 'timestamp <= %s';
			$where_values[]  = $filters['date_to'];
		}

		if ( ! empty( $filters['type'] ) ) {
			$where_clauses[] = 'type = %s';
			$where_values[]  = $filters['type'];
		}

		if ( ! empty( $filters['status'] ) ) {
			$where_clauses[] = 'status = %s';
			$where_values[]  = $filters['status'];
		}

		if ( ! empty( $filters['operation'] ) ) {
			$where_clauses[] = 'operation = %s';
			$where_values[]  = $filters['operation'];
		}

		$where_sql = '';
		if ( ! empty( $where_clauses ) ) {
			$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
		}

		// Get count.
		$query = "SELECT COUNT(*) FROM {$table_name} {$where_sql}";
		if ( ! empty( $where_values ) ) {
			$query = $wpdb->prepare( $query, $where_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return (int) $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Export logs to CSV format.
	 *
	 * Generates a CSV file containing all log entries matching the specified filters.
	 * Includes all relevant fields: id, timestamp, type, operation, status, message,
	 * payload, response, duration, error_trace, context.
	 *
	 * @param array $filters {
	 *     Optional. Array of filters to apply (same as get_logs).
	 *
	 *     @type string $date_from    Start date for filtering (Y-m-d H:i:s format).
	 *     @type string $date_to      End date for filtering (Y-m-d H:i:s format).
	 *     @type string $type         Log type filter (e.g., 'api_request', 'webhook', 'sync', 'error').
	 *     @type string $status       Status filter (e.g., 'success', 'error', 'retry').
	 *     @type string $operation    Operation filter (e.g., 'order_sync', 'product_sync').
	 * }
	 * @return string The CSV content as a string.
	 */
	public function export_logs_csv( array $filters = array() ): string {
		// Remove pagination from filters to get all matching logs.
		$filters['per_page'] = 999999; // Get all logs matching filters.
		$filters['page']     = 1;

		// Get filtered logs.
		$result = $this->get_logs( $filters );
		$logs   = $result['logs'];

		// Create CSV content.
		$csv_output = '';

		// Add CSV header.
		$headers = array(
			'ID',
			'Timestamp',
			'Type',
			'Operation',
			'Status',
			'Message',
			'Payload',
			'Response',
			'Duration',
			'Error Trace',
			'Context',
		);

		$csv_output .= $this->array_to_csv_line( $headers );

		// Add log entries.
		foreach ( $logs as $log ) {
			$row = array(
				$log['id'],
				$log['timestamp'],
				$log['type'],
				$log['operation'],
				$log['status'],
				$log['message'],
				is_array( $log['payload'] ) ? wp_json_encode( $log['payload'] ) : $log['payload'],
				is_array( $log['response'] ) ? wp_json_encode( $log['response'] ) : $log['response'],
				$log['duration'],
				$log['error_trace'],
				is_array( $log['context'] ) ? wp_json_encode( $log['context'] ) : $log['context'],
			);

			$csv_output .= $this->array_to_csv_line( $row );
		}

		return $csv_output;
	}

	/**
	 * Convert an array to a CSV line.
	 *
	 * Helper method to properly format an array as a CSV line with proper escaping.
	 *
	 * @param array $fields The fields to convert to CSV.
	 * @return string The CSV line with newline character.
	 */
	private function array_to_csv_line( array $fields ): string {
		$escaped_fields = array();

		foreach ( $fields as $field ) {
			// Convert null to empty string.
			if ( is_null( $field ) ) {
				$field = '';
			}

			// Convert to string.
			$field = (string) $field;

			// Escape double quotes by doubling them.
			$field = str_replace( '"', '""', $field );

			// Wrap field in double quotes.
			$escaped_fields[] = '"' . $field . '"';
		}

		return implode( ',', $escaped_fields ) . "\n";
	}


	/**
	 * Clean up old logs.
	 *
	 * Deletes logs older than 30 days when total log count exceeds 1000 entries.
	 * Error logs are always preserved regardless of age.
	 * This method is designed to be executed via WP-Cron on a daily schedule.
	 *
	 * @return int The number of logs deleted.
	 */
	public function cleanup_old_logs(): int {
		global $wpdb;

		$table_name = $wpdb->prefix . 'absloja_logs';

		// Get total log count.
		$total_logs = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Only cleanup if we have more than 1000 logs.
		if ( $total_logs <= 1000 ) {
			return 0;
		}

		// Calculate the date 30 days ago.
		$thirty_days_ago = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );

		// Delete logs older than 30 days, but preserve error logs.
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} WHERE timestamp < %s AND type != %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$thirty_days_ago,
				'error'
			)
		);

		return $deleted !== false ? (int) $deleted : 0;
	}

	/**
	 * Determine response status.
	 *
	 * Analyzes a response to determine if it represents success or error.
	 *
	 * @param mixed $response The response to analyze.
	 * @return string The status ('success' or 'error').
	 */
	private function determine_response_status( $response ): string {
		// If response is a WP_Error, it's an error.
		if ( is_wp_error( $response ) ) {
			return 'error';
		}

		// If response is an array with 'response' key (HTTP response).
		if ( is_array( $response ) && isset( $response['response']['code'] ) ) {
			$code = $response['response']['code'];
			return ( $code >= 200 && $code < 300 ) ? 'success' : 'error';
		}

		// If response is an array with 'success' key.
		if ( is_array( $response ) && isset( $response['success'] ) ) {
			return $response['success'] ? 'success' : 'error';
		}

		// Default to success if we can't determine otherwise.
		return 'success';
	}
}
