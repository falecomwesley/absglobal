<?php
/**
 * Fired during plugin activation.
 *
 * @package    ABSLoja\ProtheusConnector
 * @subpackage ABSLoja\ProtheusConnector\Includes
 */

namespace ABSLoja\ProtheusConnector;

use ABSLoja\ProtheusConnector\Database\Schema;

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 */
class Activator {

	/**
	 * Activate the plugin.
	 *
	 * Creates database tables, sets default options, and schedules cron events.
	 */
	public static function activate() {
		// Check WordPress version
		if ( version_compare( get_bloginfo( 'version' ), '6.0', '<' ) ) {
			wp_die(
				esc_html__( 'Este plugin requer WordPress 6.0 ou superior.', 'absloja-protheus-connector' ),
				esc_html__( 'Erro de Ativação', 'absloja-protheus-connector' ),
				array( 'back_link' => true )
			);
		}

		// Check PHP version
		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			wp_die(
				esc_html__( 'Este plugin requer PHP 7.4 ou superior.', 'absloja-protheus-connector' ),
				esc_html__( 'Erro de Ativação', 'absloja-protheus-connector' ),
				array( 'back_link' => true )
			);
		}

		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			wp_die(
				esc_html__( 'Este plugin requer WooCommerce para funcionar.', 'absloja-protheus-connector' ),
				esc_html__( 'Erro de Ativação', 'absloja-protheus-connector' ),
				array( 'back_link' => true )
			);
		}

		// Store plugin version
		update_option( 'absloja_protheus_version', ABSLOJA_PROTHEUS_CONNECTOR_VERSION );

		// Set default options if not already set
		self::set_default_options();

		// Create custom database tables
		self::create_tables();

		// Schedule cron events
		self::schedule_cron_events();

		// Flush rewrite rules for REST API endpoints
		flush_rewrite_rules();
	}

	/**
	 * Set default plugin options.
	 */
	private static function set_default_options() {
		$defaults = array(
			'absloja_protheus_auth_type'              => 'basic',
			'absloja_protheus_api_url'                => '',
			'absloja_protheus_catalog_sync_frequency' => '1hour',
			'absloja_protheus_stock_sync_frequency'   => '15min',
			'absloja_protheus_batch_size'             => 50,
			'absloja_protheus_retry_interval'         => 3600,
			'absloja_protheus_max_retries'            => 5,
			'absloja_protheus_log_retention'          => 30,
			'absloja_protheus_webhook_token'          => wp_generate_password( 32, false ),
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $value );
			}
		}

		// Set default mappings if not already set
		self::set_default_mappings();
	}

	/**
	 * Set default field mappings.
	 */
	private static function set_default_mappings() {
		// Default payment method mappings
		if ( false === get_option( 'absloja_protheus_payment_mapping' ) ) {
			$payment_mapping = array(
				'bacs'        => '001',
				'cheque'      => '002',
				'cod'         => '003',
				'credit_card' => '004',
				'pix'         => '005',
			);
			add_option( 'absloja_protheus_payment_mapping', $payment_mapping );
		}

		// Default TES rules by state
		if ( false === get_option( 'absloja_protheus_tes_rules' ) ) {
			$tes_rules = array(
				'SP'      => '501',
				'default' => '502',
			);
			add_option( 'absloja_protheus_tes_rules', $tes_rules );
		}

		// Default status mappings
		if ( false === get_option( 'absloja_protheus_status_mapping' ) ) {
			$status_mapping = array(
				'pending'   => 'pending',
				'approved'  => 'processing',
				'invoiced'  => 'completed',
				'shipped'   => 'completed',
				'cancelled' => 'cancelled',
				'rejected'  => 'failed',
			);
			add_option( 'absloja_protheus_status_mapping', $status_mapping );
		}
	}

	/**
	 * Create custom database tables.
	 */
	private static function create_tables() {
		Schema::create_all_tables();
	}

	/**
	 * Schedule WP-Cron events.
	 */
	private static function schedule_cron_events() {
		// Schedule catalog sync
		if ( ! wp_next_scheduled( 'absloja_protheus_sync_catalog' ) ) {
			$frequency = get_option( 'absloja_protheus_catalog_sync_frequency', '1hour' );
			wp_schedule_event( time(), self::get_cron_schedule( $frequency ), 'absloja_protheus_sync_catalog' );
		}

		// Schedule stock sync
		if ( ! wp_next_scheduled( 'absloja_protheus_sync_stock' ) ) {
			$frequency = get_option( 'absloja_protheus_stock_sync_frequency', '15min' );
			wp_schedule_event( time(), self::get_cron_schedule( $frequency ), 'absloja_protheus_sync_stock' );
		}

		// Schedule retry processing (hourly)
		if ( ! wp_next_scheduled( 'absloja_protheus_process_retries' ) ) {
			wp_schedule_event( time(), 'hourly', 'absloja_protheus_process_retries' );
		}

		// Schedule log cleanup (daily)
		if ( ! wp_next_scheduled( 'absloja_protheus_cleanup_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'absloja_protheus_cleanup_logs' );
		}
	}

	/**
	 * Convert frequency string to WP-Cron schedule.
	 *
	 * @param string $frequency Frequency string (15min, 30min, 1hour, etc.).
	 * @return string WP-Cron schedule name.
	 */
	private static function get_cron_schedule( $frequency ) {
		$schedules = array(
			'15min'   => 'every_15_minutes',
			'30min'   => 'every_30_minutes',
			'1hour'   => 'hourly',
			'6hours'  => 'every_6_hours',
			'12hours' => 'twicedaily',
			'24hours' => 'daily',
		);

		return isset( $schedules[ $frequency ] ) ? $schedules[ $frequency ] : 'hourly';
	}
}
