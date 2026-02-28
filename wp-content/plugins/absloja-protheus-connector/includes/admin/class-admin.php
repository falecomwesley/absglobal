<?php
/**
 * Admin Interface Manager
 *
 * @package ABSLoja\ProtheusConnector
 */

namespace ABSLoja\ProtheusConnector\Admin;

use ABSLoja\ProtheusConnector\Modules\Auth_Manager;
use ABSLoja\ProtheusConnector\Modules\Logger;
use ABSLoja\ProtheusConnector\Modules\Retry_Manager;
use ABSLoja\ProtheusConnector\Modules\Catalog_Sync;

/**
 * Admin class
 */
class Admin {
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
	 * Settings instance
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor
	 *
	 * @param Auth_Manager   $auth_manager   Auth Manager instance.
	 * @param Logger         $logger         Logger instance.
	 * @param Retry_Manager  $retry_manager  Retry Manager instance.
	 * @param Catalog_Sync   $catalog_sync   Catalog Sync instance.
	 */
	public function __construct( $auth_manager, $logger, $retry_manager, $catalog_sync ) {
		$this->auth_manager  = $auth_manager;
		$this->logger        = $logger;
		$this->retry_manager = $retry_manager;
		$this->catalog_sync  = $catalog_sync;
		$this->settings      = new Settings( $auth_manager, $logger, $retry_manager, $catalog_sync );
	}

	/**
	 * Initialize admin hooks
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
		add_action( 'wp_ajax_absloja_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_absloja_sync_catalog', array( $this, 'ajax_sync_catalog' ) );
		add_action( 'wp_ajax_absloja_sync_stock', array( $this, 'ajax_sync_stock' ) );
		add_action( 'wp_ajax_absloja_manual_retry', array( $this, 'ajax_manual_retry' ) );
		add_action( 'wp_ajax_absloja_export_logs', array( $this, 'ajax_export_logs' ) );
	}

	/**
	 * Add admin menu page
	 */
	public function add_menu_page() {
		add_submenu_page(
			'woocommerce',
			__( 'Protheus Connector', 'absloja-protheus-connector' ),
			__( 'Protheus Connector', 'absloja-protheus-connector' ),
			'manage_woocommerce',
			'absloja-protheus-connector',
			array( $this->settings, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin scripts and styles
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		// Only load on our plugin pages.
		if ( strpos( $hook, 'absloja-protheus-connector' ) === false && $hook !== 'index.php' ) {
			return;
		}

		wp_enqueue_style(
			'absloja-protheus-admin',
			plugin_dir_url( dirname( dirname( __FILE__ ) ) ) . 'assets/css/admin.css',
			array(),
			'1.0.0'
		);

		wp_enqueue_script(
			'absloja-protheus-admin',
			plugin_dir_url( dirname( dirname( __FILE__ ) ) ) . 'assets/js/admin.js',
			array( 'jquery' ),
			'1.0.0',
			true
		);

		wp_localize_script(
			'absloja-protheus-admin',
			'abslojaProtheus',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'absloja_protheus_admin' ),
				'strings' => array(
					'testing'       => __( 'Testing connection...', 'absloja-protheus-connector' ),
					'syncing'       => __( 'Syncing...', 'absloja-protheus-connector' ),
					'success'       => __( 'Success!', 'absloja-protheus-connector' ),
					'error'         => __( 'Error:', 'absloja-protheus-connector' ),
					'confirmRetry'  => __( 'Are you sure you want to retry this operation?', 'absloja-protheus-connector' ),
					'confirmExport' => __( 'Export logs to CSV?', 'absloja-protheus-connector' ),
				),
			)
		);
	}

	/**
	 * Add dashboard widget
	 */
	public function add_dashboard_widget() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'absloja_protheus_dashboard',
			__( 'Protheus Connector Status', 'absloja-protheus-connector' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	/**
	 * Render dashboard widget
	 */
	public function render_dashboard_widget() {
		// Get statistics.
		$stats = $this->get_sync_statistics();

		// Get pending retries.
		$pending_retries = $this->retry_manager->get_pending_retries();

		// Get recent errors.
		$recent_errors = $this->logger->get_logs(
			array(
				'status' => 'error',
				'limit'  => 5,
			)
		);

		// Get orders pending manual review.
		$pending_orders = $this->get_pending_review_orders();

		include plugin_dir_path( __FILE__ ) . 'views/dashboard-widget.php';
	}

	/**
	 * Get sync statistics
	 *
	 * @return array Statistics data.
	 */
	private function get_sync_statistics() {
		global $wpdb;

		$stats = array(
			'last_catalog_sync' => get_option( 'absloja_protheus_last_catalog_sync', __( 'Never', 'absloja-protheus-connector' ) ),
			'last_stock_sync'   => get_option( 'absloja_protheus_last_stock_sync', __( 'Never', 'absloja-protheus-connector' ) ),
			'products_synced'   => get_option( 'absloja_protheus_products_synced', 0 ),
			'orders_synced'     => $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_protheus_order_id'"
			),
			'recent_errors'     => $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}absloja_logs 
					WHERE status = %s AND timestamp > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
					'error'
				)
			),
		);

		return $stats;
	}

	/**
	 * Get orders pending manual review
	 *
	 * @return array Orders pending review.
	 */
	private function get_pending_review_orders() {
		$args = array(
			'limit'      => 10,
			'meta_query' => array(
				array(
					'key'   => '_protheus_sync_status',
					'value' => 'error',
				),
			),
		);

		return wc_get_orders( $args );
	}

	/**
	 * AJAX handler for test connection
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'absloja_protheus_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'absloja-protheus-connector' ) ) );
		}

		$result = $this->auth_manager->test_connection();

		if ( $result ) {
			wp_send_json_success(
				array(
					'message' => __( 'Connection successful!', 'absloja-protheus-connector' ),
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'Connection failed. Please check your credentials.', 'absloja-protheus-connector' ),
				)
			);
		}
	}

	/**
	 * AJAX handler for manual catalog sync
	 */
	public function ajax_sync_catalog() {
		check_ajax_referer( 'absloja_protheus_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'absloja-protheus-connector' ) ) );
		}

		try {
			$result = $this->catalog_sync->sync_products();

			update_option( 'absloja_protheus_last_catalog_sync', current_time( 'mysql' ) );
			update_option( 'absloja_protheus_products_synced', $result['synced'] ?? 0 );

			wp_send_json_success(
				array(
					'message' => sprintf(
						/* translators: %d: number of products synced */
						__( 'Catalog sync completed! %d products synced.', 'absloja-protheus-connector' ),
						$result['synced'] ?? 0
					),
					'result'  => $result,
				)
			);
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: error message */
						__( 'Catalog sync failed: %s', 'absloja-protheus-connector' ),
						$e->getMessage()
					),
				)
			);
		}
	}

	/**
	 * AJAX handler for manual stock sync
	 */
	public function ajax_sync_stock() {
		check_ajax_referer( 'absloja_protheus_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'absloja-protheus-connector' ) ) );
		}

		try {
			$result = $this->catalog_sync->sync_stock();

			update_option( 'absloja_protheus_last_stock_sync', current_time( 'mysql' ) );

			wp_send_json_success(
				array(
					'message' => sprintf(
						/* translators: %d: number of products updated */
						__( 'Stock sync completed! %d products updated.', 'absloja-protheus-connector' ),
						$result['updated'] ?? 0
					),
					'result'  => $result,
				)
			);
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: error message */
						__( 'Stock sync failed: %s', 'absloja-protheus-connector' ),
						$e->getMessage()
					),
				)
			);
		}
	}

	/**
	 * AJAX handler for manual retry
	 */
	public function ajax_manual_retry() {
		check_ajax_referer( 'absloja_protheus_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'absloja-protheus-connector' ) ) );
		}

		$retry_id = isset( $_POST['retry_id'] ) ? intval( $_POST['retry_id'] ) : 0;

		if ( ! $retry_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid retry ID', 'absloja-protheus-connector' ) ) );
		}

		$result = $this->retry_manager->manual_retry( $retry_id );

		if ( $result ) {
			wp_send_json_success(
				array(
					'message' => __( 'Retry completed successfully!', 'absloja-protheus-connector' ),
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'Retry failed. Check logs for details.', 'absloja-protheus-connector' ),
				)
			);
		}
	}

	/**
	 * AJAX handler for log export
	 */
	public function ajax_export_logs() {
		check_ajax_referer( 'absloja_protheus_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'absloja-protheus-connector' ) ) );
		}

		$filters = array();

		if ( isset( $_POST['date_from'] ) && ! empty( $_POST['date_from'] ) ) {
			$filters['date_from'] = sanitize_text_field( wp_unslash( $_POST['date_from'] ) );
		}

		if ( isset( $_POST['date_to'] ) && ! empty( $_POST['date_to'] ) ) {
			$filters['date_to'] = sanitize_text_field( wp_unslash( $_POST['date_to'] ) );
		}

		if ( isset( $_POST['type'] ) && ! empty( $_POST['type'] ) ) {
			$filters['type'] = sanitize_text_field( wp_unslash( $_POST['type'] ) );
		}

		if ( isset( $_POST['status'] ) && ! empty( $_POST['status'] ) ) {
			$filters['status'] = sanitize_text_field( wp_unslash( $_POST['status'] ) );
		}

		try {
			$csv_content = $this->logger->export_logs_csv( $filters );
			$filename    = 'protheus-logs-' . gmdate( 'Y-m-d-His' ) . '.csv';

			wp_send_json_success(
				array(
					'filename' => $filename,
					'content'  => base64_encode( $csv_content ),
				)
			);
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: error message */
						__( 'Export failed: %s', 'absloja-protheus-connector' ),
						$e->getMessage()
					),
				)
			);
		}
	}
}
