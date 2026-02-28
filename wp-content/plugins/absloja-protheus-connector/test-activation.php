<?php
/**
 * Activation Test Script
 * 
 * This script tests the plugin activation process step by step
 * to identify any issues.
 * 
 * Access at: http://localhost:8888/absglobal/wp-content/plugins/absloja-protheus-connector/test-activation.php
 */

// Load WordPress
require_once '../../../wp-load.php';

// Check if user is admin
if ( ! current_user_can( 'manage_options' ) ) {
	die( 'You must be logged in as an administrator to run this test.' );
}

echo '<h1>Plugin Activation Test</h1>';
echo '<style>
	body { font-family: Arial, sans-serif; padding: 20px; }
	.success { color: green; }
	.error { color: red; }
	.warning { color: orange; }
	pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }
</style>';

// Test 1: Check WordPress version
echo '<h2>Test 1: WordPress Version</h2>';
$wp_version = get_bloginfo( 'version' );
if ( version_compare( $wp_version, '6.0', '>=' ) ) {
	echo '<p class="success">✓ WordPress version: ' . esc_html( $wp_version ) . ' (OK)</p>';
} else {
	echo '<p class="error">✗ WordPress version: ' . esc_html( $wp_version ) . ' (Requires 6.0+)</p>';
}

// Test 2: Check PHP version
echo '<h2>Test 2: PHP Version</h2>';
if ( version_compare( PHP_VERSION, '7.4', '>=' ) ) {
	echo '<p class="success">✓ PHP version: ' . PHP_VERSION . ' (OK)</p>';
} else {
	echo '<p class="error">✗ PHP version: ' . PHP_VERSION . ' (Requires 7.4+)</p>';
}

// Test 3: Check WooCommerce
echo '<h2>Test 3: WooCommerce</h2>';
if ( class_exists( 'WooCommerce' ) ) {
	echo '<p class="success">✓ WooCommerce is active</p>';
	echo '<p>WooCommerce version: ' . WC()->version . '</p>';
} else {
	echo '<p class="error">✗ WooCommerce is not active</p>';
}

// Test 4: Check plugin files
echo '<h2>Test 4: Plugin Files</h2>';
$required_files = array(
	'absloja-protheus-connector.php',
	'includes/class-plugin.php',
	'includes/class-loader.php',
	'includes/class-activator.php',
	'includes/class-deactivator.php',
	'includes/database/class-schema.php',
	'includes/admin/class-admin.php',
	'includes/admin/class-settings.php',
);

foreach ( $required_files as $file ) {
	$path = plugin_dir_path( __FILE__ ) . $file;
	if ( file_exists( $path ) ) {
		echo '<p class="success">✓ ' . esc_html( $file ) . '</p>';
	} else {
		echo '<p class="error">✗ ' . esc_html( $file ) . ' (MISSING)</p>';
	}
}

// Test 5: Check autoloader
echo '<h2>Test 5: Autoloader Test</h2>';
try {
	// Try to load a class
	if ( class_exists( 'ABSLoja\ProtheusConnector\Plugin' ) ) {
		echo '<p class="success">✓ Plugin class loaded successfully</p>';
	} else {
		echo '<p class="error">✗ Plugin class not found</p>';
	}
	
	if ( class_exists( 'ABSLoja\ProtheusConnector\Activator' ) ) {
		echo '<p class="success">✓ Activator class loaded successfully</p>';
	} else {
		echo '<p class="error">✗ Activator class not found</p>';
	}
	
	if ( class_exists( 'ABSLoja\ProtheusConnector\Database\Schema' ) ) {
		echo '<p class="success">✓ Schema class loaded successfully</p>';
	} else {
		echo '<p class="error">✗ Schema class not found</p>';
	}
} catch ( Exception $e ) {
	echo '<p class="error">✗ Autoloader error: ' . esc_html( $e->getMessage() ) . '</p>';
}

// Test 6: Check database tables
echo '<h2>Test 6: Database Tables</h2>';
global $wpdb;
$tables = array(
	$wpdb->prefix . 'absloja_logs',
	$wpdb->prefix . 'absloja_retry_queue',
);

foreach ( $tables as $table ) {
	$exists = $wpdb->get_var( "SHOW TABLES LIKE '$table'" );
	if ( $exists ) {
		echo '<p class="success">✓ Table ' . esc_html( $table ) . ' exists</p>';
	} else {
		echo '<p class="warning">⚠ Table ' . esc_html( $table ) . ' does not exist (will be created on activation)</p>';
	}
}

// Test 7: Check plugin options
echo '<h2>Test 7: Plugin Options</h2>';
$options = array(
	'absloja_protheus_version',
	'absloja_protheus_auth_type',
	'absloja_protheus_api_url',
	'absloja_protheus_webhook_token',
);

foreach ( $options as $option ) {
	$value = get_option( $option );
	if ( $value !== false ) {
		echo '<p class="success">✓ ' . esc_html( $option ) . ': ' . esc_html( substr( $value, 0, 50 ) ) . '</p>';
	} else {
		echo '<p class="warning">⚠ ' . esc_html( $option ) . ' not set (will be created on activation)</p>';
	}
}

// Test 8: Check cron schedules
echo '<h2>Test 8: Cron Schedules</h2>';
$cron_events = array(
	'absloja_protheus_sync_catalog',
	'absloja_protheus_sync_stock',
	'absloja_protheus_process_retries',
	'absloja_protheus_cleanup_logs',
);

foreach ( $cron_events as $event ) {
	$scheduled = wp_next_scheduled( $event );
	if ( $scheduled ) {
		echo '<p class="success">✓ ' . esc_html( $event ) . ' scheduled for ' . date( 'Y-m-d H:i:s', $scheduled ) . '</p>';
	} else {
		echo '<p class="warning">⚠ ' . esc_html( $event ) . ' not scheduled (will be created on activation)</p>';
	}
}

// Test 9: Check if plugin is active
echo '<h2>Test 9: Plugin Status</h2>';
$active_plugins = get_option( 'active_plugins' );
if ( in_array( 'absloja-protheus-connector/absloja-protheus-connector.php', $active_plugins ) ) {
	echo '<p class="success">✓ Plugin is active</p>';
} else {
	echo '<p class="warning">⚠ Plugin is not active</p>';
}

// Test 10: Try to instantiate the plugin
echo '<h2>Test 10: Plugin Instantiation</h2>';
try {
	if ( class_exists( 'ABSLoja\ProtheusConnector\Plugin' ) ) {
		$plugin = ABSLoja\ProtheusConnector\Plugin::get_instance();
		echo '<p class="success">✓ Plugin instance created successfully</p>';
		echo '<p>Plugin name: ' . esc_html( $plugin->get_plugin_name() ) . '</p>';
		echo '<p>Plugin version: ' . esc_html( $plugin->get_version() ) . '</p>';
	} else {
		echo '<p class="error">✗ Plugin class not available</p>';
	}
} catch ( Exception $e ) {
	echo '<p class="error">✗ Error instantiating plugin: ' . esc_html( $e->getMessage() ) . '</p>';
	echo '<pre>' . esc_html( $e->getTraceAsString() ) . '</pre>';
}

echo '<hr>';
echo '<h2>Summary</h2>';
echo '<p>If all tests pass, the plugin should work correctly. If you see errors, please fix them before activating the plugin.</p>';
echo '<p><a href="' . admin_url( 'plugins.php' ) . '">Go to Plugins Page</a></p>';
