<?php
/**
 * Fired during plugin deactivation.
 *
 * @package    ABSLoja\ProtheusConnector
 * @subpackage ABSLoja\ProtheusConnector\Includes
 */

namespace ABSLoja\ProtheusConnector;

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 */
class Deactivator {

	/**
	 * Deactivate the plugin.
	 *
	 * Clears scheduled cron events and flushes rewrite rules.
	 * Note: Does not delete database tables or options to preserve data.
	 */
	public static function deactivate() {
		// Clear scheduled cron events
		self::clear_cron_events();

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Clear all scheduled WP-Cron events.
	 */
	private static function clear_cron_events() {
		$events = array(
			'absloja_protheus_sync_catalog',
			'absloja_protheus_sync_stock',
			'absloja_protheus_process_retries',
			'absloja_protheus_cleanup_logs',
		);

		foreach ( $events as $event ) {
			$timestamp = wp_next_scheduled( $event );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $event );
			}
		}
	}
}
