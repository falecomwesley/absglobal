<?php
/**
 * Debug script to check plugin status
 * Access via: http://localhost:8888/absglobal/wp-content/plugins/absloja-protheus-connector/debug-plugin.php
 */

// Load WordPress
require_once dirname( __FILE__ ) . '/../../../../wp-load.php';

header( 'Content-Type: text/html; charset=utf-8' );
?>
<!DOCTYPE html>
<html>
<head>
    <title>ABS Loja Protheus Connector - Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 5px; }
        h1 { color: #333; border-bottom: 2px solid #0073aa; padding-bottom: 10px; }
        h2 { color: #0073aa; margin-top: 30px; }
        .success { color: #46b450; }
        .error { color: #dc3232; }
        .warning { color: #ffb900; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f0f0f0; font-weight: bold; }
        .code { background: #f5f5f5; padding: 10px; border-left: 3px solid #0073aa; margin: 10px 0; font-family: monospace; }
        .status-ok { color: #46b450; font-weight: bold; }
        .status-error { color: #dc3232; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 ABS Loja Protheus Connector - Debug Information</h1>
        
        <h2>1. Environment Check</h2>
        <table>
            <tr>
                <th>Item</th>
                <th>Value</th>
                <th>Status</th>
            </tr>
            <tr>
                <td>WordPress Version</td>
                <td><?php echo get_bloginfo( 'version' ); ?></td>
                <td class="<?php echo version_compare( get_bloginfo( 'version' ), '6.0', '>=' ) ? 'status-ok' : 'status-error'; ?>">
                    <?php echo version_compare( get_bloginfo( 'version' ), '6.0', '>=' ) ? '✅ OK' : '❌ Too Old'; ?>
                </td>
            </tr>
            <tr>
                <td>PHP Version</td>
                <td><?php echo PHP_VERSION; ?></td>
                <td class="<?php echo version_compare( PHP_VERSION, '7.4', '>=' ) ? 'status-ok' : 'status-error'; ?>">
                    <?php echo version_compare( PHP_VERSION, '7.4', '>=' ) ? '✅ OK' : '❌ Too Old'; ?>
                </td>
            </tr>
            <tr>
                <td>WooCommerce</td>
                <td><?php echo class_exists( 'WooCommerce' ) ? WC()->version : 'Not Installed'; ?></td>
                <td class="<?php echo class_exists( 'WooCommerce' ) ? 'status-ok' : 'status-error'; ?>">
                    <?php echo class_exists( 'WooCommerce' ) ? '✅ Active' : '❌ Not Active'; ?>
                </td>
            </tr>
            <tr>
                <td>Plugin Active</td>
                <td><?php echo is_plugin_active( 'absloja-protheus-connector/absloja-protheus-connector.php' ) ? 'Yes' : 'No'; ?></td>
                <td class="<?php echo is_plugin_active( 'absloja-protheus-connector/absloja-protheus-connector.php' ) ? 'status-ok' : 'status-error'; ?>">
                    <?php echo is_plugin_active( 'absloja-protheus-connector/absloja-protheus-connector.php' ) ? '✅ Active' : '❌ Inactive'; ?>
                </td>
            </tr>
        </table>

        <h2>2. Plugin Classes</h2>
        <table>
            <tr>
                <th>Class</th>
                <th>Status</th>
            </tr>
            <?php
            $classes = array(
                'ABSLoja\\ProtheusConnector\\Plugin',
                'ABSLoja\\ProtheusConnector\\Activator',
                'ABSLoja\\ProtheusConnector\\Database\\Schema',
                'ABSLoja\\ProtheusConnector\\Modules\\Auth_Manager',
                'ABSLoja\\ProtheusConnector\\API\\Protheus_Client',
                'ABSLoja\\ProtheusConnector\\Modules\\Logger',
                'ABSLoja\\ProtheusConnector\\Modules\\Retry_Manager',
                'ABSLoja\\ProtheusConnector\\Modules\\Mapping_Engine',
                'ABSLoja\\ProtheusConnector\\Modules\\Customer_Sync',
                'ABSLoja\\ProtheusConnector\\Modules\\Order_Sync',
                'ABSLoja\\ProtheusConnector\\Modules\\Catalog_Sync',
                'ABSLoja\\ProtheusConnector\\Modules\\Webhook_Handler',
                'ABSLoja\\ProtheusConnector\\Admin\\Admin',
                'ABSLoja\\ProtheusConnector\\Admin\\Settings',
            );
            
            foreach ( $classes as $class ) {
                $exists = class_exists( $class );
                echo '<tr>';
                echo '<td>' . esc_html( $class ) . '</td>';
                echo '<td class="' . ( $exists ? 'status-ok' : 'status-error' ) . '">';
                echo $exists ? '✅ Loaded' : '❌ Not Found';
                echo '</td>';
                echo '</tr>';
            }
            ?>
        </table>

        <h2>3. Database Tables</h2>
        <table>
            <tr>
                <th>Table</th>
                <th>Status</th>
                <th>Rows</th>
            </tr>
            <?php
            global $wpdb;
            $tables = array(
                $wpdb->prefix . 'absloja_logs',
                $wpdb->prefix . 'absloja_retry_queue',
            );
            
            foreach ( $tables as $table ) {
                $exists = $wpdb->get_var( "SHOW TABLES LIKE '$table'" );
                $count = $exists ? $wpdb->get_var( "SELECT COUNT(*) FROM $table" ) : 0;
                echo '<tr>';
                echo '<td>' . esc_html( $table ) . '</td>';
                echo '<td class="' . ( $exists ? 'status-ok' : 'status-error' ) . '">';
                echo $exists ? '✅ Exists' : '❌ Missing';
                echo '</td>';
                echo '<td>' . esc_html( $count ) . '</td>';
                echo '</tr>';
            }
            ?>
        </table>

        <h2>4. Plugin Options</h2>
        <table>
            <tr>
                <th>Option</th>
                <th>Value</th>
            </tr>
            <?php
            $options = array(
                'absloja_protheus_version',
                'absloja_protheus_auth_type',
                'absloja_protheus_api_url',
                'absloja_protheus_catalog_sync_frequency',
                'absloja_protheus_stock_sync_frequency',
            );
            
            foreach ( $options as $option ) {
                $value = get_option( $option, 'Not Set' );
                echo '<tr>';
                echo '<td>' . esc_html( $option ) . '</td>';
                echo '<td>' . esc_html( is_array( $value ) ? json_encode( $value ) : $value ) . '</td>';
                echo '</tr>';
            }
            ?>
        </table>

        <h2>5. WP-Cron Events</h2>
        <table>
            <tr>
                <th>Event</th>
                <th>Next Run</th>
                <th>Status</th>
            </tr>
            <?php
            $events = array(
                'absloja_protheus_sync_catalog',
                'absloja_protheus_sync_stock',
                'absloja_protheus_process_retries',
                'absloja_protheus_cleanup_logs',
            );
            
            foreach ( $events as $event ) {
                $timestamp = wp_next_scheduled( $event );
                echo '<tr>';
                echo '<td>' . esc_html( $event ) . '</td>';
                echo '<td>' . ( $timestamp ? date( 'Y-m-d H:i:s', $timestamp ) : 'Not Scheduled' ) . '</td>';
                echo '<td class="' . ( $timestamp ? 'status-ok' : 'status-error' ) . '">';
                echo $timestamp ? '✅ Scheduled' : '❌ Not Scheduled';
                echo '</td>';
                echo '</tr>';
            }
            ?>
        </table>

        <h2>6. REST API Endpoints</h2>
        <?php
        $rest_server = rest_get_server();
        $routes = $rest_server->get_routes();
        $plugin_routes = array_filter( array_keys( $routes ), function( $route ) {
            return strpos( $route, '/absloja-protheus/v1' ) === 0;
        } );
        ?>
        <table>
            <tr>
                <th>Endpoint</th>
                <th>Methods</th>
            </tr>
            <?php
            if ( ! empty( $plugin_routes ) ) {
                foreach ( $plugin_routes as $route ) {
                    $route_data = $routes[ $route ];
                    $methods = array();
                    foreach ( $route_data as $handler ) {
                        if ( isset( $handler['methods'] ) ) {
                            $methods = array_merge( $methods, array_keys( $handler['methods'] ) );
                        }
                    }
                    echo '<tr>';
                    echo '<td>' . esc_html( $route ) . '</td>';
                    echo '<td>' . esc_html( implode( ', ', array_unique( $methods ) ) ) . '</td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="2" class="status-error">❌ No endpoints registered</td></tr>';
            }
            ?>
        </table>

        <h2>7. PHP Errors (if any)</h2>
        <div class="code">
            <?php
            $error_log = ini_get( 'error_log' );
            if ( $error_log && file_exists( $error_log ) ) {
                $errors = file_get_contents( $error_log );
                $recent_errors = array_slice( explode( "\n", $errors ), -20 );
                echo '<pre>' . esc_html( implode( "\n", $recent_errors ) ) . '</pre>';
            } else {
                echo 'No error log found or configured.';
            }
            ?>
        </div>

        <h2>8. Quick Actions</h2>
        <p>
            <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=advanced&section=features' ); ?>" class="button">
                View WooCommerce Features
            </a>
            <a href="<?php echo admin_url( 'admin.php?page=absloja-protheus-connector' ); ?>" class="button">
                Plugin Settings
            </a>
            <a href="<?php echo admin_url( 'plugins.php' ); ?>" class="button">
                Plugins Page
            </a>
        </p>

        <p style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; color: #666;">
            Generated at: <?php echo date( 'Y-m-d H:i:s' ); ?>
        </p>
    </div>
</body>
</html>
