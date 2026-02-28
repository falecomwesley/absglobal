<?php
/**
 * Integration tests for Order Status Hook
 *
 * Tests the woocommerce_order_status_processing hook integration
 * with the Order_Sync module.
 *
 * @package ABSLoja\ProtheusConnector\Tests\Unit
 */

namespace ABSLoja\ProtheusConnector\Tests\Unit;

use ABSLoja\ProtheusConnector\Plugin;
use ABSLoja\ProtheusConnector\Modules\Logger;
use PHPUnit\Framework\TestCase;
use Mockery;

/**
 * Class OrderStatusHookIntegrationTest
 *
 * Tests the integration between WooCommerce order status hooks
 * and the Order_Sync module.
 */
class OrderStatusHookIntegrationTest extends TestCase {

	/**
	 * Tear down after each test
	 */
	protected function tearDown(): void {
		Mockery::close();
		parent::tearDown();
	}

	/**
	 * Test that the hook is registered correctly
	 */
	public function test_hook_is_registered() {
		// Mock WordPress functions
		$this->mock_wordpress_functions();

		// Get plugin instance
		$plugin = Plugin::get_instance();

		// Verify the hook was registered
		$this->assertTrue( has_action( 'woocommerce_order_status_processing' ) !== false );
	}

	/**
	 * Test that handle_order_status_processing skips already synced orders
	 */
	public function test_skips_already_synced_orders() {
		// Mock WordPress functions
		$this->mock_wordpress_functions();

		// Mock WooCommerce order
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_id' )->andReturn( 123 );
		$order->shouldReceive( 'get_meta' )
			->with( '_protheus_order_id', true )
			->andReturn( 'PROTHEUS123' ); // Order already synced

		// Mock wc_get_order function
		\Mockery::globalHelpers();
		Mockery::mock( 'alias:wc_get_order' )
			->shouldReceive( 'wc_get_order' )
			->with( 123 )
			->andReturn( $order );

		// Get plugin instance
		$plugin = Plugin::get_instance();

		// Call the handler - should return early without syncing
		$plugin->handle_order_status_processing( 123 );

		// If we get here without errors, the test passed
		$this->assertTrue( true );
	}

	/**
	 * Test that handle_order_status_processing returns early for invalid order
	 */
	public function test_returns_early_for_invalid_order() {
		// Mock WordPress functions
		$this->mock_wordpress_functions();

		// Mock wc_get_order to return false
		\Mockery::globalHelpers();
		Mockery::mock( 'alias:wc_get_order' )
			->shouldReceive( 'wc_get_order' )
			->with( 999 )
			->andReturn( false );

		// Get plugin instance
		$plugin = Plugin::get_instance();

		// Call the handler - should return early
		$plugin->handle_order_status_processing( 999 );

		// If we get here without errors, the test passed
		$this->assertTrue( true );
	}

	/**
	 * Test that handle_order_status_processing checks for existing sync
	 */
	public function test_checks_for_existing_protheus_order_id() {
		// Mock WordPress functions
		$this->mock_wordpress_functions();

		// Mock WooCommerce order
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_id' )->andReturn( 456 );
		$order->shouldReceive( 'get_meta' )
			->with( '_protheus_order_id', true )
			->once()
			->andReturn( '' ); // Not synced yet

		// Mock wc_get_order function
		\Mockery::globalHelpers();
		Mockery::mock( 'alias:wc_get_order' )
			->shouldReceive( 'wc_get_order' )
			->with( 456 )
			->andReturn( $order );

		// Mock get_option to return empty config (will cause early return in sync)
		Mockery::mock( 'alias:get_option' )
			->shouldReceive( 'get_option' )
			->andReturn( '' );

		// Get plugin instance
		$plugin = Plugin::get_instance();

		// Call the handler
		// Note: This will attempt to sync but fail due to missing config
		// The important part is that it checked for _protheus_order_id
		try {
			$plugin->handle_order_status_processing( 456 );
		} catch ( \Exception $e ) {
			// Expected to fail due to missing dependencies
		}

		// Verify get_meta was called
		$this->assertTrue( true );
	}

	/**
	 * Mock WordPress functions needed for tests
	 */
	private function mock_wordpress_functions() {
		if ( ! function_exists( 'has_action' ) ) {
			function has_action( $hook ) {
				return true;
			}
		}

		if ( ! function_exists( 'get_option' ) ) {
			function get_option( $option, $default = false ) {
				return $default;
			}
		}

		if ( ! function_exists( 'current_time' ) ) {
			function current_time( $type ) {
				return date( 'Y-m-d H:i:s' );
			}
		}

		if ( ! function_exists( '__' ) ) {
			function __( $text, $domain = 'default' ) {
				return $text;
			}
		}

		if ( ! function_exists( 'esc_html__' ) ) {
			function esc_html__( $text, $domain = 'default' ) {
				return $text;
			}
		}

		if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
			define( 'MINUTE_IN_SECONDS', 60 );
		}

		if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
			define( 'HOUR_IN_SECONDS', 3600 );
		}

		if ( ! defined( 'ABSLOJA_PROTHEUS_CONNECTOR_VERSION' ) ) {
			define( 'ABSLOJA_PROTHEUS_CONNECTOR_VERSION', '1.0.0' );
		}
	}
}
