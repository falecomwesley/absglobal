<?php
/**
 * Database schema management.
 *
 * @package    ABSLoja\ProtheusConnector
 * @subpackage ABSLoja\ProtheusConnector\Database
 */

namespace ABSLoja\ProtheusConnector\Database;

/**
 * Database schema management class.
 *
 * Handles creation and management of custom database tables.
 */
class Schema {

	/**
	 * Create the logs table.
	 *
	 * Creates the wp_absloja_logs table with all required fields and indexes.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function create_logs_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'absloja_logs';

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			timestamp DATETIME NOT NULL,
			type VARCHAR(50) NOT NULL,
			operation VARCHAR(100) NOT NULL,
			status VARCHAR(20) NOT NULL,
			message TEXT,
			payload LONGTEXT,
			response LONGTEXT,
			duration DECIMAL(10,4),
			error_trace TEXT,
			context LONGTEXT,
			INDEX idx_timestamp (timestamp),
			INDEX idx_type (type),
			INDEX idx_status (status),
			INDEX idx_operation (operation)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$result = dbDelta( $sql );

		return ! empty( $result );
	}

	/**
	 * Create the retry queue table.
	 *
	 * Creates the wp_absloja_retry_queue table with all required fields and indexes.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function create_retry_queue_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'absloja_retry_queue';

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			operation_type VARCHAR(100) NOT NULL,
			data LONGTEXT NOT NULL,
			attempts INT DEFAULT 0,
			max_attempts INT DEFAULT 5,
			next_attempt DATETIME NOT NULL,
			last_error TEXT,
			status VARCHAR(20) DEFAULT 'pending',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			INDEX idx_status (status),
			INDEX idx_next_attempt (next_attempt),
			INDEX idx_operation_type (operation_type)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$result = dbDelta( $sql );

		return ! empty( $result );
	}

	/**
	 * Create all custom tables.
	 *
	 * Creates all custom database tables required by the plugin.
	 *
	 * @return array Array of results for each table creation.
	 */
	public static function create_all_tables() {
		return array(
			'logs'        => self::create_logs_table(),
			'retry_queue' => self::create_retry_queue_table(),
		);
	}

	/**
	 * Drop the logs table.
	 *
	 * Removes the wp_absloja_logs table from the database.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function drop_logs_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'absloja_logs';
		$result     = $wpdb->query( "DROP TABLE IF EXISTS $table_name" );

		return false !== $result;
	}

	/**
	 * Drop the retry queue table.
	 *
	 * Removes the wp_absloja_retry_queue table from the database.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function drop_retry_queue_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'absloja_retry_queue';
		$result     = $wpdb->query( "DROP TABLE IF EXISTS $table_name" );

		return false !== $result;
	}

	/**
	 * Drop all custom tables.
	 *
	 * Removes all custom database tables created by the plugin.
	 *
	 * @return array Array of results for each table drop.
	 */
	public static function drop_all_tables() {
		return array(
			'logs'        => self::drop_logs_table(),
			'retry_queue' => self::drop_retry_queue_table(),
		);
	}
}
