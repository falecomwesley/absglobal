<?php
/**
 * Tests for Activator class.
 *
 * @package ABSLoja\ProtheusConnector\Tests
 */

namespace ABSLoja\ProtheusConnector\Tests;

use ABSLoja\ProtheusConnector\Activator;
use ABSLoja\ProtheusConnector\Database\Schema;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

/**
 * Activator test case.
 */
class ActivatorTest extends TestCase {

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * Tear down test environment.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		Mockery::close();
		parent::tearDown();
	}

	/**
	 * Test activate method calls Schema::create_all_tables.
	 *
	 * This test verifies that the Activator properly delegates
	 * table creation to the Schema class.
	 *
	 * @test
	 */
	public function test_activate_calls_schema_create_all_tables() {
		// Mock WordPress functions
		Functions\expect( 'version_compare' )
			->twice()
			->andReturn( true );

		Functions\expect( 'get_bloginfo' )
			->once()
			->with( 'version' )
			->andReturn( '6.4' );

		Functions\expect( 'class_exists' )
			->once()
			->with( 'WooCommerce' )
			->andReturn( true );

		Functions\expect( 'update_option' )
			->once()
			->with( 'absloja_protheus_version', Mockery::any() );

		Functions\expect( 'get_option' )
			->andReturn( false );

		Functions\expect( 'add_option' )
			->andReturn( true );

		Functions\expect( 'wp_generate_password' )
			->once()
			->andReturn( 'test_token_12345' );

		Functions\expect( 'wp_next_scheduled' )
			->andReturn( false );

		Functions\expect( 'wp_schedule_event' )
			->andReturn( true );

		Functions\expect( 'flush_rewrite_rules' );

		// Mock global $wpdb for Schema class
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$wpdb->shouldReceive( 'get_charset_collate' )
			->andReturn( 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' );

		// Mock dbDelta function
		Functions\expect( 'dbDelta' )
			->twice()
			->andReturn( array( 'table' => 'Created table' ) );

		// Mock ABSPATH constant
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/tmp/' );
		}

		// Mock ABSLOJA_PROTHEUS_CONNECTOR_VERSION constant
		if ( ! defined( 'ABSLOJA_PROTHEUS_CONNECTOR_VERSION' ) ) {
			define( 'ABSLOJA_PROTHEUS_CONNECTOR_VERSION', '1.0.0' );
		}

		// Execute activation
		Activator::activate();

		// If we reach here without exceptions, the test passes
		$this->assertTrue( true );
	}
}
