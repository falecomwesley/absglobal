<?php
/**
 * Settings Manager
 *
 * @package ABSLoja\ProtheusConnector
 */

namespace ABSLoja\ProtheusConnector\Admin;

use ABSLoja\ProtheusConnector\Modules\Auth_Manager;
use ABSLoja\ProtheusConnector\Modules\Logger;
use ABSLoja\ProtheusConnector\Modules\Retry_Manager;
use ABSLoja\ProtheusConnector\Modules\Catalog_Sync;

/**
 * Settings class
 */
class Settings {
	/**
	 * Auth Manager instance
	 *
	 * @var Auth_Manager
	 */
	private $auth_manager;

	/**
	 * Logger instance
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Retry Manager instance
	 *
	 * @var Retry_Manager
	 */
	private $retry_manager;

	/**
	 * Catalog Sync instance
	 *
	 * @var Catalog_Sync
	 */
	private $catalog_sync;

	/**
	 * Constructor
	 *
	 * @param Auth_Manager  $auth_manager  Auth Manager instance.
	 * @param Logger        $logger        Logger instance.
	 * @param Retry_Manager $retry_manager Retry Manager instance.
	 * @param Catalog_Sync  $catalog_sync  Catalog Sync instance.
	 */
	public function __construct( $auth_manager, $logger, $retry_manager, $catalog_sync ) {
		$this->auth_manager  = $auth_manager;
		$this->logger        = $logger;
		$this->retry_manager = $retry_manager;
		$this->catalog_sync  = $catalog_sync;

		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register settings
	 */
	public function register_settings() {
		// Connection settings.
		register_setting( 'absloja_protheus_connection', 'absloja_protheus_auth_type', array( $this, 'sanitize_auth_type' ) );
		register_setting( 'absloja_protheus_connection', 'absloja_protheus_api_url', array( $this, 'sanitize_url' ) );
		register_setting( 'absloja_protheus_connection', 'absloja_protheus_username', 'sanitize_text_field' );
		register_setting( 'absloja_protheus_connection', 'absloja_protheus_password', array( $this, 'sanitize_password' ) );
		register_setting( 'absloja_protheus_connection', 'absloja_protheus_client_id', 'sanitize_text_field' );
		register_setting( 'absloja_protheus_connection', 'absloja_protheus_client_secret', array( $this, 'sanitize_password' ) );
		register_setting( 'absloja_protheus_connection', 'absloja_protheus_token_endpoint', 'sanitize_text_field' );

		// Mapping settings.
		register_setting( 'absloja_protheus_mappings', 'absloja_protheus_payment_mapping', array( $this, 'sanitize_array' ) );
		register_setting( 'absloja_protheus_mappings', 'absloja_protheus_category_mapping', array( $this, 'sanitize_array' ) );
		register_setting( 'absloja_protheus_mappings', 'absloja_protheus_tes_rules', array( $this, 'sanitize_array' ) );
		register_setting( 'absloja_protheus_mappings', 'absloja_protheus_status_mapping', array( $this, 'sanitize_array' ) );

		// Sync schedule settings.
		register_setting( 'absloja_protheus_schedule', 'absloja_protheus_catalog_sync_frequency', array( $this, 'sanitize_frequency' ) );
		register_setting( 'absloja_protheus_schedule', 'absloja_protheus_stock_sync_frequency', array( $this, 'sanitize_frequency' ) );

		// Advanced settings.
		register_setting( 'absloja_protheus_advanced', 'absloja_protheus_batch_size', 'absint' );
		register_setting( 'absloja_protheus_advanced', 'absloja_protheus_retry_interval', 'absint' );
		register_setting( 'absloja_protheus_advanced', 'absloja_protheus_max_retries', 'absint' );
		register_setting( 'absloja_protheus_advanced', 'absloja_protheus_log_retention', 'absint' );
		register_setting( 'absloja_protheus_advanced', 'absloja_protheus_webhook_token', 'sanitize_text_field' );
		register_setting( 'absloja_protheus_advanced', 'absloja_protheus_webhook_secret', 'sanitize_text_field' );
		register_setting( 'absloja_protheus_advanced', 'absloja_protheus_image_url_pattern', 'esc_url_raw' );
	}

	/**
	 * Sanitize auth type
	 *
	 * @param string $value Input value.
	 * @return string Sanitized value.
	 */
	public function sanitize_auth_type( $value ) {
		$valid = array( 'basic', 'oauth2' );
		return in_array( $value, $valid, true ) ? $value : 'basic';
	}

	/**
	 * Sanitize URL
	 *
	 * @param string $value Input value.
	 * @return string Sanitized value.
	 */
	public function sanitize_url( $value ) {
		$url = esc_url_raw( $value );
		if ( empty( $url ) ) {
			add_settings_error(
				'absloja_protheus_api_url',
				'invalid_url',
				__( 'Please enter a valid API URL.', 'absloja-protheus-connector' )
			);
		}
		return $url;
	}

	/**
	 * Sanitize password (encrypt)
	 *
	 * @param string $value Input value.
	 * @return string Encrypted value.
	 */
	public function sanitize_password( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		// Don't re-encrypt if already encrypted.
		if ( strpos( $value, 'encrypted:' ) === 0 ) {
			return $value;
		}

		// Encrypt the password.
		$key        = substr( AUTH_KEY, 0, 32 );
		$iv         = openssl_random_pseudo_bytes( 16 );
		$encrypted  = openssl_encrypt( $value, 'AES-256-CBC', $key, 0, $iv );
		$encrypted  = base64_encode( $encrypted . '::' . $iv );

		return 'encrypted:' . $encrypted;
	}

	/**
	 * Sanitize array
	 *
	 * @param array $value Input value.
	 * @return array Sanitized value.
	 */
	public function sanitize_array( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_map( 'sanitize_text_field', $value );
	}

	/**
	 * Sanitize frequency
	 *
	 * @param string $value Input value.
	 * @return string Sanitized value.
	 */
	public function sanitize_frequency( $value ) {
		$valid = array( '15min', '30min', '1hour', '6hours', '12hours', '24hours' );
		return in_array( $value, $valid, true ) ? $value : '1hour';
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'absloja-protheus-connector' ) );
		}

		// Get current tab.
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'connection';

		// Define tabs.
		$tabs = array(
			'connection' => __( 'Connection', 'absloja-protheus-connector' ),
			'mappings'   => __( 'Mappings', 'absloja-protheus-connector' ),
			'schedule'   => __( 'Sync Schedule', 'absloja-protheus-connector' ),
			'logs'       => __( 'Logs', 'absloja-protheus-connector' ),
			'advanced'   => __( 'Advanced', 'absloja-protheus-connector' ),
		);

		include plugin_dir_path( __FILE__ ) . 'views/settings-page.php';
	}

	/**
	 * Render connection tab
	 */
	public function render_connection_tab() {
		include plugin_dir_path( __FILE__ ) . 'views/tab-connection.php';
	}

	/**
	 * Render mappings tab
	 */
	public function render_mappings_tab() {
		include plugin_dir_path( __FILE__ ) . 'views/tab-mappings.php';
	}

	/**
	 * Render schedule tab
	 */
	public function render_schedule_tab() {
		include plugin_dir_path( __FILE__ ) . 'views/tab-schedule.php';
	}

	/**
	 * Render logs tab
	 */
	public function render_logs_tab() {
		$filters = array();

		if ( isset( $_GET['date_from'] ) ) {
			$filters['date_from'] = sanitize_text_field( wp_unslash( $_GET['date_from'] ) );
		}

		if ( isset( $_GET['date_to'] ) ) {
			$filters['date_to'] = sanitize_text_field( wp_unslash( $_GET['date_to'] ) );
		}

		if ( isset( $_GET['type'] ) ) {
			$filters['type'] = sanitize_text_field( wp_unslash( $_GET['type'] ) );
		}

		if ( isset( $_GET['status'] ) ) {
			$filters['status'] = sanitize_text_field( wp_unslash( $_GET['status'] ) );
		}

		$page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$filters['page']  = $page;
		$filters['limit'] = 20;

		$result      = $this->logger->get_logs( $filters );
		$logs        = $result['logs'];
		$total_logs  = $result['total'];
		$total_pages = $result['total_pages'];

		include plugin_dir_path( __FILE__ ) . 'views/tab-logs.php';
	}

	/**
	 * Render advanced tab
	 */
	public function render_advanced_tab() {
		$pending_retries = $this->retry_manager->get_pending_retries();
		include plugin_dir_path( __FILE__ ) . 'views/tab-advanced.php';
	}
}
