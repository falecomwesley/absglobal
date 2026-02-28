<?php
/**
 * Tests for WP-Cron integration with Retry_Manager.
 *
 * @package ABSLoja\ProtheusConnector\Tests
 */

namespace ABSLoja\ProtheusConnector\Tests;

use PHPUnit\Framework\TestCase;
use ABSLoja\ProtheusConnector\Plugin;
use ABSLoja\ProtheusConnector\Loader;

/**
 * Test WP-Cron integration.
 */
class WPCronIntegrationTest extends TestCase {

	/**
	 * Test that the retry processing hook is registered.
	 */
	public function test_retry_processing_hook_is_registered() {
		// Create a mock Plugin instance
		$plugin = $this->getMockBuilder( Plugin::class )
			->disableOriginalConstructor()
			->getMock();

		// Create a real Loader instance
		$loader = new Loader();

		// Use reflection to access the private define_cron_hooks method
		$reflection = new \ReflectionClass( Plugin::class );
		$method = $reflection->getMethod( 'define_cron_hooks' );
		$method->setAccessible( true );

		// Set the loader property
		$loaderProperty = $reflection->getProperty( 'loader' );
		$loaderProperty->setAccessible( true );
		$loaderProperty->setValue( $plugin, $loader );

		// Call the method
		$method->invoke( $plugin );

		// Get the registered actions
		$actionsProperty = new \ReflectionProperty( Loader::class, 'actions' );
		$actionsProperty->setAccessible( true );
		$actions = $actionsProperty->getValue( $loader );

		// Verify that the retry processing hook is registered
		$found = false;
		foreach ( $actions as $action ) {
			if ( $action['hook'] === 'absloja_protheus_process_retries' &&
			     $action['callback'] === 'process_retries_callback' ) {
				$found = true;
				break;
			}
		}

		$this->assertTrue( $found, 'The absloja_protheus_process_retries hook should be registered' );
	}

	/**
	 * Test that the process_retries_callback method exists.
	 */
	public function test_process_retries_callback_method_exists() {
		$this->assertTrue(
			method_exists( Plugin::class, 'process_retries_callback' ),
			'Plugin class should have a process_retries_callback method'
		);
	}

	/**
	 * Test that the callback method is public.
	 */
	public function test_process_retries_callback_is_public() {
		$reflection = new \ReflectionMethod( Plugin::class, 'process_retries_callback' );
		$this->assertTrue(
			$reflection->isPublic(),
			'process_retries_callback method should be public'
		);
	}
}
