<?php
/**
 * Plugin Name: ABS Loja Protheus Connector
 * Plugin URI: https://absglobal.com.br
 * Description: Integra WooCommerce com TOTVS Protheus ERP através de REST API, automatizando sincronização de pedidos, clientes, catálogo e estoque.
 * Version: 1.0.0
 * Author: ABS Global
 * Author URI: https://absglobal.com.br
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: absloja-protheus-connector
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 10.5
 * Requires Plugins: woocommerce
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Current plugin version.
 */
define( 'ABSLOJA_PROTHEUS_CONNECTOR_VERSION', '1.0.0' );

/**
 * Plugin directory path.
 */
define( 'ABSLOJA_PROTHEUS_CONNECTOR_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 */
define( 'ABSLOJA_PROTHEUS_CONNECTOR_URL', plugin_dir_url( __FILE__ ) );

/**
 * PSR-4 Autoloader implementation.
 */
spl_autoload_register( function ( $class ) {
	$prefix = 'ABSLoja\\ProtheusConnector\\';
	$base_dir = ABSLOJA_PROTHEUS_CONNECTOR_PATH . 'includes/';

	$len = strlen( $prefix );
	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}

	$relative_class = substr( $class, $len );
	
	// Split into namespace parts and class name
	$parts = explode( '\\', $relative_class );
	$class_name = array_pop( $parts );
	
	// Convert namespace parts to lowercase directories
	$namespace_path = implode( '/', array_map( 'strtolower', $parts ) );
	
	// Convert class name to WordPress file naming convention
	$file_name = 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';
	
	// Build full path
	$file = $base_dir;
	if ( ! empty( $namespace_path ) ) {
		$file .= $namespace_path . '/';
	}
	$file .= $file_name;

	if ( file_exists( $file ) ) {
		require $file;
	}
} );

/**
 * The code that runs during plugin activation.
 */
function activate_absloja_protheus_connector() {
	require_once ABSLOJA_PROTHEUS_CONNECTOR_PATH . 'includes/class-activator.php';
	ABSLoja\ProtheusConnector\Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_absloja_protheus_connector() {
	require_once ABSLOJA_PROTHEUS_CONNECTOR_PATH . 'includes/class-deactivator.php';
	ABSLoja\ProtheusConnector\Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_absloja_protheus_connector' );
register_deactivation_hook( __FILE__, 'deactivate_absloja_protheus_connector' );

/**
 * Begins execution of the plugin.
 */
function run_absloja_protheus_connector() {
	require_once ABSLOJA_PROTHEUS_CONNECTOR_PATH . 'includes/class-plugin.php';
	$plugin = ABSLoja\ProtheusConnector\Plugin::get_instance();
	$plugin->run();
}

// Check if WooCommerce is active
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	// Declare HPOS compatibility
	add_action( 'before_woocommerce_init', function() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	} );
	
	run_absloja_protheus_connector();
} else {
	add_action( 'admin_notices', function() {
		?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'ABS Loja Protheus Connector requer WooCommerce para funcionar.', 'absloja-protheus-connector' ); ?></p>
		</div>
		<?php
	} );
}
