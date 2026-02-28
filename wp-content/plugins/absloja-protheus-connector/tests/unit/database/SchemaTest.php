<?php
/**
 * Tests for Schema class.
 *
 * @package ABSLoja\ProtheusConnector\Tests
 */

namespace ABSLoja\ProtheusConnector\Tests\Database;

use ABSLoja\ProtheusConnector\Database\Schema;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

/**
 * Schema test case.
 */
class SchemaTest extends TestCase {

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
	 * Test create_logs_table creates table with correct structure.
	 *
	 * @test
	 */
	public function test_create_logs_table_creates_table_with_correct_structure() {
		// Mock global $wpdb
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$wpdb->shouldReceive( 'get_charset_collate' )
			->once()
			->andReturn( 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' );

		// Mock dbDelta function
		Functions\expect( 'dbDelta' )
			->once()
			->with( Mockery::on( function ( $sql ) {
				// Verify SQL contains required fields
				$this->assertStringContainsString( 'CREATE TABLE IF NOT EXISTS wp_absloja_logs', $sql );
				$this->assertStringContainsString( 'id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY', $sql );
				$this->assertStringContainsString( 'timestamp DATETIME NOT NULL', $sql );
				$this->assertStringContainsString( 'type VARCHAR(50) NOT NULL', $sql );
				$this->assertStringContainsString( 'operation VARCHAR(100) NOT NULL', $sql );
				$this->assertStringContainsString( 'status VARCHAR(20) NOT NULL', $sql );
				$this->assertStringContainsString( 'message TEXT', $sql );
				$this->assertStringContainsString( 'payload LONGTEXT', $sql );
				$this->assertStringContainsString( 'response LONGTEXT', $sql );
				$this->assertStringContainsString( 'duration DECIMAL(10,4)', $sql );
				$this->assertStringContainsString( 'error_trace TEXT', $sql );
				$this->assertStringContainsString( 'context LONGTEXT', $sql );

				// Verify indexes
				$this->assertStringContainsString( 'INDEX idx_timestamp (timestamp)', $sql );
				$this->assertStringContainsString( 'INDEX idx_type (type)', $sql );
				$this->assertStringContainsString( 'INDEX idx_status (status)', $sql );
				$this->assertStringContainsString( 'INDEX idx_operation (operation)', $sql );

				return true;
			} ) )
			->andReturn( array( 'wp_absloja_logs' => 'Created table wp_absloja_logs' ) );

		// Mock ABSPATH constant
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/tmp/' );
		}

		$result = Schema::create_logs_table();

		$this->assertTrue( $result );
	}

	/**
	 * Test create_retry_queue_table creates table with correct structure.
	 *
	 * @test
	 */
	public function test_create_retry_queue_table_creates_table_with_correct_structure() {
		// Mock global $wpdb
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$wpdb->shouldReceive( 'get_charset_collate' )
			->once()
			->andReturn( 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' );

		// Mock dbDelta function
		Functions\expect( 'dbDelta' )
			->once()
			->with( Mockery::on( function ( $sql ) {
				// Verify SQL contains required fields
				$this->assertStringContainsString( 'CREATE TABLE IF NOT EXISTS wp_absloja_retry_queue', $sql );
				$this->assertStringContainsString( 'id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY', $sql );
				$this->assertStringContainsString( 'operation_type VARCHAR(100) NOT NULL', $sql );
				$this->assertStringContainsString( 'data LONGTEXT NOT NULL', $sql );
				$this->assertStringContainsString( 'attempts INT DEFAULT 0', $sql );
				$this->assertStringContainsString( 'max_attempts INT DEFAULT 5', $sql );
				$this->assertStringContainsString( 'next_attempt DATETIME NOT NULL', $sql );
				$this->assertStringContainsString( 'last_error TEXT', $sql );
				$this->assertStringContainsString( 'status VARCHAR(20) DEFAULT \'pending\'', $sql );
				$this->assertStringContainsString( 'created_at DATETIME NOT NULL', $sql );
				$this->assertStringContainsString( 'updated_at DATETIME NOT NULL', $sql );

				// Verify indexes
				$this->assertStringContainsString( 'INDEX idx_status (status)', $sql );
				$this->assertStringContainsString( 'INDEX idx_next_attempt (next_attempt)', $sql );
				$this->assertStringContainsString( 'INDEX idx_operation_type (operation_type)', $sql );

				return true;
			} ) )
			->andReturn( array( 'wp_absloja_retry_queue' => 'Created table wp_absloja_retry_queue' ) );

		// Mock ABSPATH constant
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/tmp/' );
		}

		$result = Schema::create_retry_queue_table();

		$this->assertTrue( $result );
	}

	/**
	 * Test create_all_tables creates both tables.
	 *
	 * @test
	 */
	public function test_create_all_tables_creates_both_tables() {
		// Mock global $wpdb
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$wpdb->shouldReceive( 'get_charset_collate' )
			->twice()
			->andReturn( 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' );

		// Mock dbDelta function for both tables
		Functions\expect( 'dbDelta' )
			->twice()
			->andReturn( array( 'table' => 'Created table' ) );

		// Mock ABSPATH constant
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/tmp/' );
		}

		$results = Schema::create_all_tables();

		$this->assertIsArray( $results );
		$this->assertArrayHasKey( 'logs', $results );
		$this->assertArrayHasKey( 'retry_queue', $results );
		$this->assertTrue( $results['logs'] );
		$this->assertTrue( $results['retry_queue'] );
	}

	/**
	 * Test drop_logs_table removes the table.
	 *
	 * @test
	 */
	public function test_drop_logs_table_removes_table() {
		// Mock global $wpdb
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$wpdb->shouldReceive( 'query' )
			->once()
			->with( 'DROP TABLE IF EXISTS wp_absloja_logs' )
			->andReturn( 1 );

		$result = Schema::drop_logs_table();

		$this->assertTrue( $result );
	}

	/**
	 * Test drop_retry_queue_table removes the table.
	 *
	 * @test
	 */
	public function test_drop_retry_queue_table_removes_table() {
		// Mock global $wpdb
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$wpdb->shouldReceive( 'query' )
			->once()
			->with( 'DROP TABLE IF EXISTS wp_absloja_retry_queue' )
			->andReturn( 1 );

		$result = Schema::drop_retry_queue_table();

		$this->assertTrue( $result );
	}

	/**
	 * Test drop_all_tables removes both tables.
	 *
	 * @test
	 */
	public function test_drop_all_tables_removes_both_tables() {
		// Mock global $wpdb
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$wpdb->shouldReceive( 'query' )
			->twice()
			->andReturn( 1 );

		$results = Schema::drop_all_tables();

		$this->assertIsArray( $results );
		$this->assertArrayHasKey( 'logs', $results );
		$this->assertArrayHasKey( 'retry_queue', $results );
		$this->assertTrue( $results['logs'] );
		$this->assertTrue( $results['retry_queue'] );
	}

	/**
	 * Test create_logs_table returns false when dbDelta returns empty array.
	 *
	 * @test
	 */
	public function test_create_logs_table_returns_false_on_failure() {
		// Mock global $wpdb
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$wpdb->shouldReceive( 'get_charset_collate' )
			->once()
			->andReturn( 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' );

		// Mock dbDelta returning empty array (failure)
		Functions\expect( 'dbDelta' )
			->once()
			->andReturn( array() );

		// Mock ABSPATH constant
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/tmp/' );
		}

		$result = Schema::create_logs_table();

		$this->assertFalse( $result );
	}

	/**
	 * Test drop_logs_table returns false when query fails.
	 *
	 * @test
	 */
	public function test_drop_logs_table_returns_false_on_failure() {
		// Mock global $wpdb
		global $wpdb;
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$wpdb->shouldReceive( 'query' )
			->once()
			->andReturn( false );

		$result = Schema::drop_logs_table();

		$this->assertFalse( $result );
	}
}
