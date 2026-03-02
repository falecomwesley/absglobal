<?php
/**
 * The core plugin class.
 *
 * @package    ABSLoja\ProtheusConnector
 * @subpackage ABSLoja\ProtheusConnector\Includes
 */

namespace ABSLoja\ProtheusConnector;

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks. Also maintains the unique identifier of this
 * plugin as well as the current version of the plugin.
 *
 * Uses Singleton pattern to ensure only one instance exists.
 */
class Plugin {

	/**
	 * The single instance of the class.
	 *
	 * @var Plugin
	 */
	private static $instance = null;

	/**
	 * The loader that's responsible for maintaining and registering all hooks.
	 *
	 * @var Loader
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @var string
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * Main Plugin Instance.
	 *
	 * Ensures only one instance of Plugin is loaded or can be loaded.
	 *
	 * @return Plugin - Main instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 */
	private function __construct() {
		$this->version     = ABSLOJA_PROTHEUS_CONNECTOR_VERSION;
		$this->plugin_name = 'absloja-protheus-connector';

		$this->loader = new Loader();

		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_woocommerce_hooks();
		$this->define_cron_hooks();
		$this->define_rest_api_hooks();
		$this->register_custom_cron_schedules();
	}

	/**
	 * Prevent cloning of the instance.
	 */
	private function __clone() {}

	/**
	 * Prevent unserializing of the instance.
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 */
	private function set_locale() {
		$this->loader->add_action( 'plugins_loaded', $this, 'load_plugin_textdomain' );
	}

	/**
	 * Load the plugin text domain for translation.
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'absloja-protheus-connector',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);
	}

	/**
	 * Register all of the hooks related to the admin area functionality.
	 */
	private function define_admin_hooks() {
		// Only load admin functionality in admin area
		if ( ! is_admin() ) {
			return;
		}

		// Instantiate dependencies for Admin
		$logger = new Modules\Logger();
		$auth_config = $this->get_auth_config();
		$auth_manager = new Modules\Auth_Manager( $auth_config );
		$retry_manager = new Modules\Retry_Manager( $logger );
		
		// Get API URL from config
		$api_url = ! empty( $auth_config['api_url'] ) ? $auth_config['api_url'] : 'http://localhost';
		
		// Instantiate Protheus_Client and Mapping_Engine for Catalog_Sync
		$client = new API\Protheus_Client( $auth_manager, $api_url );
		$mapper = new Modules\Mapping_Engine();
		$catalog_sync = new Modules\Catalog_Sync( $client, $mapper, $logger );

		// Instantiate Admin
		$admin = new Admin\Admin( $auth_manager, $logger, $retry_manager, $catalog_sync );
		
		// Initialize admin hooks
		$admin->init();
	}

	/**
	 * Register all of the hooks related to WooCommerce functionality.
	 */
	private function define_woocommerce_hooks() {
		// Register order status change hook for processing status
		$this->loader->add_action( 'woocommerce_order_status_processing', $this, 'handle_order_status_processing', 10, 1 );
		
		// Register order status change hooks for cancellation and refund
		$this->loader->add_action( 'woocommerce_order_status_cancelled', $this, 'handle_order_status_cancelled', 10, 1 );
		$this->loader->add_action( 'woocommerce_order_status_refunded', $this, 'handle_order_status_refunded', 10, 1 );
		
		// Register hook to prevent status changes on failed sync orders
		$this->loader->add_filter( 'woocommerce_order_status_changed', $this, 'prevent_status_change_on_sync_failure', 10, 4 );
		
		// Register admin notice for blocked status changes
		$this->loader->add_action( 'admin_notices', $this, 'display_status_change_block_notice' );
	}

	/**
	 * Register all of the hooks related to WP-Cron functionality.
	 */
	private function define_cron_hooks() {
		// Register retry processing hook
		$this->loader->add_action( 'absloja_protheus_process_retries', $this, 'process_retries_callback' );
		
		// Register catalog sync hook
		$this->loader->add_action( 'absloja_protheus_sync_catalog', $this, 'sync_catalog_callback' );
		
		// Register stock sync hook
		$this->loader->add_action( 'absloja_protheus_sync_stock', $this, 'sync_stock_callback' );
	}

	/**
	 * Register all of the hooks related to REST API functionality.
	 */
	private function define_rest_api_hooks() {
		// Register webhook REST API routes
		$this->loader->add_action( 'rest_api_init', $this, 'register_webhook_routes' );
	}

	/**
	 * Register custom cron schedules.
	 */
	private function register_custom_cron_schedules() {
		$this->loader->add_filter( 'cron_schedules', $this, 'add_custom_cron_schedules' );
	}

	/**
	 * Add custom cron schedules.
	 *
	 * @param array $schedules Existing cron schedules.
	 * @return array Modified cron schedules.
	 */
	public function add_custom_cron_schedules( $schedules ) {
		$schedules['every_15_minutes'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => esc_html__( 'A cada 15 minutos', 'absloja-protheus-connector' ),
		);

		$schedules['every_30_minutes'] = array(
			'interval' => 30 * MINUTE_IN_SECONDS,
			'display'  => esc_html__( 'A cada 30 minutos', 'absloja-protheus-connector' ),
		);

		$schedules['every_6_hours'] = array(
			'interval' => 6 * HOUR_IN_SECONDS,
			'display'  => esc_html__( 'A cada 6 horas', 'absloja-protheus-connector' ),
		);

		return $schedules;
	}

	/**
	 * WP-Cron callback for processing retries.
	 *
	 * This method is called by WP-Cron on an hourly schedule to process
	 * pending retry operations. It instantiates the Retry_Manager and
	 * calls process_retries() to handle all due retry attempts.
	 */
	public function process_retries_callback() {
		// Instantiate Logger (required dependency for Retry_Manager)
		$logger = new Modules\Logger();

		// Instantiate Retry_Manager
		$retry_manager = new Modules\Retry_Manager( $logger );

		// Process all pending retries
		$results = $retry_manager->process_retries();

		// Log the cron execution results
		$logger->log_sync_operation(
			'cron_retry_processing',
			array(
				'processed' => $results['processed'],
				'succeeded' => $results['succeeded'],
				'failed'    => $results['failed'],
			),
			true
		);
	}

	/**
	 * WP-Cron callback for catalog synchronization.
	 *
	 * This method is called by WP-Cron based on the configured frequency
	 * to synchronize products from Protheus to WooCommerce. It instantiates
	 * the Catalog_Sync module and calls sync_products() to fetch and update
	 * product data.
	 */
	public function sync_catalog_callback() {
		// Instantiate Logger
		$logger = new Modules\Logger();

		// Instantiate Protheus_Client
		$client = $this->create_protheus_client();

		// Instantiate Mapping_Engine
		$mapper = new Modules\Mapping_Engine();

		// Instantiate Catalog_Sync
		$catalog_sync = new Modules\Catalog_Sync( $client, $mapper, $logger );

		// Get configured batch size (default: 50)
		$batch_size = get_option( 'absloja_protheus_batch_size', 50 );

		// Sync products
		$results = $catalog_sync->sync_products( $batch_size );

		// Log the cron execution results
		$logger->log_sync_operation(
			'cron_catalog_sync',
			array(
				'total_fetched' => $results['total_fetched'],
				'created'       => $results['created'],
				'updated'       => $results['updated'],
				'errors'        => $results['errors'],
			),
			count( $results['errors'] ) === 0
		);
	}

	/**
	 * WP-Cron callback for stock synchronization.
	 *
	 * This method is called by WP-Cron based on the configured frequency
	 * to synchronize stock quantities from Protheus to WooCommerce. It
	 * instantiates the Catalog_Sync module and calls sync_stock() to update
	 * stock levels and product visibility.
	 */
	public function sync_stock_callback() {
		// Instantiate Logger
		$logger = new Modules\Logger();

		// Instantiate Protheus_Client
		$client = $this->create_protheus_client();

		// Instantiate Mapping_Engine
		$mapper = new Modules\Mapping_Engine();

		// Instantiate Catalog_Sync
		$catalog_sync = new Modules\Catalog_Sync( $client, $mapper, $logger );

		// Sync stock
		$results = $catalog_sync->sync_stock();

		// Log the cron execution results
		$logger->log_sync_operation(
			'cron_stock_sync',
			array(
				'total_fetched' => $results['total_fetched'],
				'updated'       => $results['updated'],
				'hidden'        => $results['hidden'],
				'restored'      => $results['restored'],
				'errors'        => $results['errors'],
			),
			count( $results['errors'] ) === 0
		);
	}

	/**
	 * Handle WooCommerce order status change to processing.
	 *
	 * This method is called when a WooCommerce order status changes to "processing".
	 * It checks if the order was already synced to Protheus and, if not, triggers
	 * the order synchronization process.
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public function handle_order_status_processing( $order_id ) {
		// Get WooCommerce order object
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		// Check if order was already synced to Protheus
		$protheus_order_id = $order->get_meta( '_protheus_order_id', true );
		if ( ! empty( $protheus_order_id ) ) {
			// Order already synced, skip
			return;
		}

		// Instantiate dependencies
		$logger = new Modules\Logger();
		$client = $this->create_protheus_client();
		$mapper = new Modules\Mapping_Engine();
		$retry_manager = new Modules\Retry_Manager( $logger );
		$customer_sync = new Modules\Customer_Sync( $client, $mapper, $logger, $retry_manager );

		// Instantiate Order_Sync
		$order_sync = new Modules\Order_Sync(
			$client,
			$customer_sync,
			$mapper,
			$logger,
			$retry_manager
		);

		// Sync order to Protheus
		$order_sync->sync_order( $order );
	}

	/**
	 * Handle WooCommerce order status change to cancelled.
	 *
	 * This method is called when a WooCommerce order status changes to "cancelled".
	 * It triggers the order cancellation synchronization to Protheus.
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public function handle_order_status_cancelled( $order_id ) {
		// Get WooCommerce order object
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		// Instantiate dependencies
		$logger = new Modules\Logger();
		$client = $this->create_protheus_client();
		$mapper = new Modules\Mapping_Engine();
		$retry_manager = new Modules\Retry_Manager( $logger );
		$customer_sync = new Modules\Customer_Sync( $client, $mapper, $logger, $retry_manager );

		// Instantiate Order_Sync
		$order_sync = new Modules\Order_Sync(
			$client,
			$customer_sync,
			$mapper,
			$logger,
			$retry_manager
		);

		// Cancel order in Protheus
		$order_sync->cancel_order( $order );
	}

	/**
	 * Handle WooCommerce order status change to refunded.
	 *
	 * This method is called when a WooCommerce order status changes to "refunded".
	 * It triggers the order refund synchronization to Protheus.
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public function handle_order_status_refunded( $order_id ) {
		// Get WooCommerce order object
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		// Instantiate dependencies
		$logger = new Modules\Logger();
		$client = $this->create_protheus_client();
		$mapper = new Modules\Mapping_Engine();
		$retry_manager = new Modules\Retry_Manager( $logger );
		$customer_sync = new Modules\Customer_Sync( $client, $mapper, $logger, $retry_manager );

		// Instantiate Order_Sync
		$order_sync = new Modules\Order_Sync(
			$client,
			$customer_sync,
			$mapper,
			$logger,
			$retry_manager
		);

		// Refund order in Protheus
		$order_sync->refund_order( $order );
	}

	/**
	 * Prevent status changes on orders that failed to sync to Protheus.
	 *
	 * This method is called before a WooCommerce order status changes.
	 * It checks if the order has a sync failure and prevents the status change
	 * to maintain data consistency between WooCommerce and Protheus.
	 *
	 * @param int    $order_id   WooCommerce order ID.
	 * @param string $old_status Old order status.
	 * @param string $new_status New order status.
	 * @param object $order      WooCommerce order object.
	 */
	public function prevent_status_change_on_sync_failure( $order_id, $old_status, $new_status, $order ) {
		// Skip if not a WC_Order object
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		// Check if order has sync failure
		$sync_status = $order->get_meta( '_protheus_sync_status', true );
		
		if ( $sync_status === 'error' ) {
			// Instantiate dependencies to get block message
			$logger = new Modules\Logger();
			$client = $this->create_protheus_client();
			$mapper = new Modules\Mapping_Engine();
			$retry_manager = new Modules\Retry_Manager( $logger );
			$customer_sync = new Modules\Customer_Sync( $client, $mapper, $logger, $retry_manager );

			// Instantiate Order_Sync
			$order_sync = new Modules\Order_Sync(
				$client,
				$customer_sync,
				$mapper,
				$logger,
				$retry_manager
			);

			// Check if status change should be blocked
			if ( $order_sync->should_block_status_change( $order ) ) {
				// Store block message in transient for admin notice
				$message = $order_sync->get_status_block_message( $order );
				set_transient( 'absloja_protheus_status_block_' . $order_id, $message, 60 );
				
				// Revert status change by updating back to old status
				// This is done by removing the action temporarily to avoid recursion
				remove_filter( 'woocommerce_order_status_changed', array( $this, 'prevent_status_change_on_sync_failure' ), 10 );
				$order->set_status( $old_status, __( 'Status change blocked due to Protheus sync failure.', 'absloja-protheus-connector' ) );
				$order->save();
				add_filter( 'woocommerce_order_status_changed', array( $this, 'prevent_status_change_on_sync_failure' ), 10, 4 );
			}
		}
	}

	/**
	 * Display admin notice when status change is blocked.
	 *
	 * Shows a notice to administrators explaining why the order status
	 * change was blocked due to Protheus sync failure.
	 */
	public function display_status_change_block_notice() {
		// Only show on order edit screen
		$screen = get_current_screen();
		if ( ! $screen || ( $screen->id !== 'shop_order' && $screen->id !== 'woocommerce_page_wc-orders' ) ) {
			return;
		}

		// Get order ID from request
		$order_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : ( isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0 );
		
		if ( ! $order_id ) {
			return;
		}

		// Check for block message transient
		$message = get_transient( 'absloja_protheus_status_block_' . $order_id );
		
		if ( $message ) {
			// Delete transient after displaying
			delete_transient( 'absloja_protheus_status_block_' . $order_id );
			
			// Display error notice
			echo '<div class="notice notice-error is-dismissible">';
			echo '<p><strong>' . esc_html__( 'Protheus Connector:', 'absloja-protheus-connector' ) . '</strong> ' . esc_html( $message ) . '</p>';
			echo '</div>';
		}
	}

	/**
	 * Get authentication configuration from WordPress options.
	 *
	 * @return array Authentication configuration array.
	 */
	private function get_auth_config() {
		return array(
			'auth_type'       => get_option( 'absloja_protheus_auth_type', 'basic' ),
			'api_url'         => get_option( 'absloja_protheus_api_url', '' ),
			'username'        => get_option( 'absloja_protheus_username', '' ),
			'password'        => get_option( 'absloja_protheus_password', '' ),
			'client_id'       => get_option( 'absloja_protheus_client_id', '' ),
			'client_secret'   => get_option( 'absloja_protheus_client_secret', '' ),
			'token_endpoint'  => get_option( 'absloja_protheus_token_endpoint', '' ),
			'access_token'    => get_option( 'absloja_protheus_access_token', '' ),
			'token_expires'   => get_option( 'absloja_protheus_token_expires', 0 ),
		);
	}

	/**
	 * Create Protheus Client instance with proper configuration.
	 *
	 * @return API\Protheus_Client Configured Protheus Client instance.
	 */
	private function create_protheus_client() {
		$auth_config = $this->get_auth_config();
		$auth_manager = new Modules\Auth_Manager( $auth_config );
		$api_url = ! empty( $auth_config['api_url'] ) ? $auth_config['api_url'] : 'http://localhost';
		
		return new API\Protheus_Client( $auth_manager, $api_url );
	}

	/**
	 * Register webhook REST API routes.
	 *
	 * This method is called on rest_api_init hook to register the webhook
	 * endpoints for receiving order status updates and stock updates from Protheus.
	 */
	public function register_webhook_routes() {
		// Instantiate dependencies
		$logger = new Modules\Logger();
		$auth_manager = new Modules\Auth_Manager( $this->get_auth_config() );

		// Instantiate Webhook_Handler
		$webhook_handler = new Modules\Webhook_Handler( $auth_manager, $logger );

		// Register routes
		$webhook_handler->register_routes();
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @return string The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @return Loader Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @return string The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}
}
