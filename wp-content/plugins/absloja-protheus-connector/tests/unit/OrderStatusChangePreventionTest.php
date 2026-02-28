<?php
/**
 * Unit tests for Order Status Change Prevention
 *
 * Tests the functionality that prevents status changes on orders
 * that failed to sync to Protheus.
 *
 * @package ABSLoja\ProtheusConnector\Tests\Unit
 */

namespace ABSLoja\ProtheusConnector\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ABSLoja\ProtheusConnector\Modules\Order_Sync;
use ABSLoja\ProtheusConnector\Modules\Customer_Sync;
use ABSLoja\ProtheusConnector\Modules\Mapping_Engine;
use ABSLoja\ProtheusConnector\Modules\Logger;
use ABSLoja\ProtheusConnector\Modules\Retry_Manager;
use ABSLoja\ProtheusConnector\API\Protheus_Client;

/**
 * Class OrderStatusChangePreventionTest
 *
 * Tests order status change prevention when sync fails.
 */
class OrderStatusChangePreventionTest extends TestCase {

	/**
	 * Test that status change is blocked when sync status is error
	 */
	public function test_should_block_status_change_when_sync_failed() {
		// Create mock order with error sync status
		$order = $this->createMock( \WC_Order::class );
		$order->method( 'get_meta' )
			->with( '_protheus_sync_status', true )
			->willReturn( 'error' );

		// Create Order_Sync instance
		$client = $this->createMock( Protheus_Client::class );
		$customer_sync = $this->createMock( Customer_Sync::class );
		$mapper = $this->createMock( Mapping_Engine::class );
		$logger = $this->createMock( Logger::class );
		$retry_manager = $this->createMock( Retry_Manager::class );

		$order_sync = new Order_Sync(
			$client,
			$customer_sync,
			$mapper,
			$logger,
			$retry_manager
		);

		// Assert that status change should be blocked
		$this->assertTrue( $order_sync->should_block_status_change( $order ) );
	}

	/**
	 * Test that status change is not blocked when sync status is synced
	 */
	public function test_should_not_block_status_change_when_sync_succeeded() {
		// Create mock order with synced status
		$order = $this->createMock( \WC_Order::class );
		$order->method( 'get_meta' )
			->with( '_protheus_sync_status', true )
			->willReturn( 'synced' );

		// Create Order_Sync instance
		$client = $this->createMock( Protheus_Client::class );
		$customer_sync = $this->createMock( Customer_Sync::class );
		$mapper = $this->createMock( Mapping_Engine::class );
		$logger = $this->createMock( Logger::class );
		$retry_manager = $this->createMock( Retry_Manager::class );

		$order_sync = new Order_Sync(
			$client,
			$customer_sync,
			$mapper,
			$logger,
			$retry_manager
		);

		// Assert that status change should not be blocked
		$this->assertFalse( $order_sync->should_block_status_change( $order ) );
	}

	/**
	 * Test that status change is not blocked when sync status is empty
	 */
	public function test_should_not_block_status_change_when_sync_status_empty() {
		// Create mock order with no sync status
		$order = $this->createMock( \WC_Order::class );
		$order->method( 'get_meta' )
			->with( '_protheus_sync_status', true )
			->willReturn( '' );

		// Create Order_Sync instance
		$client = $this->createMock( Protheus_Client::class );
		$customer_sync = $this->createMock( Customer_Sync::class );
		$mapper = $this->createMock( Mapping_Engine::class );
		$logger = $this->createMock( Logger::class );
		$retry_manager = $this->createMock( Retry_Manager::class );

		$order_sync = new Order_Sync(
			$client,
			$customer_sync,
			$mapper,
			$logger,
			$retry_manager
		);

		// Assert that status change should not be blocked
		$this->assertFalse( $order_sync->should_block_status_change( $order ) );
	}

	/**
	 * Test that block message includes error details
	 */
	public function test_get_status_block_message_includes_error_details() {
		// Create mock order with error details
		$order = $this->createMock( \WC_Order::class );
		$order->method( 'get_meta' )
			->willReturnMap( [
				[ '_protheus_sync_error', true, 'TES not found for state SP' ],
				[ '_protheus_business_error', true, 'tes_error' ],
			] );

		// Create Order_Sync instance
		$client = $this->createMock( Protheus_Client::class );
		$customer_sync = $this->createMock( Customer_Sync::class );
		$mapper = $this->createMock( Mapping_Engine::class );
		$logger = $this->createMock( Logger::class );
		$retry_manager = $this->createMock( Retry_Manager::class );

		$order_sync = new Order_Sync(
			$client,
			$customer_sync,
			$mapper,
			$logger,
			$retry_manager
		);

		// Get block message
		$message = $order_sync->get_status_block_message( $order );

		// Assert message contains key information
		$this->assertStringContainsString( 'Status change blocked', $message );
		$this->assertStringContainsString( 'tes_error', $message );
		$this->assertStringContainsString( 'TES not found for state SP', $message );
		$this->assertStringContainsString( 'resolve the sync issue', $message );
	}

	/**
	 * Test that block message works without business error
	 */
	public function test_get_status_block_message_without_business_error() {
		// Create mock order with only sync error
		$order = $this->createMock( \WC_Order::class );
		$order->method( 'get_meta' )
			->willReturnMap( [
				[ '_protheus_sync_error', true, 'Connection timeout' ],
				[ '_protheus_business_error', true, '' ],
			] );

		// Create Order_Sync instance
		$client = $this->createMock( Protheus_Client::class );
		$customer_sync = $this->createMock( Customer_Sync::class );
		$mapper = $this->createMock( Mapping_Engine::class );
		$logger = $this->createMock( Logger::class );
		$retry_manager = $this->createMock( Retry_Manager::class );

		$order_sync = new Order_Sync(
			$client,
			$customer_sync,
			$mapper,
			$logger,
			$retry_manager
		);

		// Get block message
		$message = $order_sync->get_status_block_message( $order );

		// Assert message contains key information
		$this->assertStringContainsString( 'Status change blocked', $message );
		$this->assertStringContainsString( 'Connection timeout', $message );
		$this->assertStringNotContainsString( 'Error type:', $message );
	}

	/**
	 * Test that block message works with minimal information
	 */
	public function test_get_status_block_message_minimal() {
		// Create mock order with no error details
		$order = $this->createMock( \WC_Order::class );
		$order->method( 'get_meta' )
			->willReturn( '' );

		// Create Order_Sync instance
		$client = $this->createMock( Protheus_Client::class );
		$customer_sync = $this->createMock( Customer_Sync::class );
		$mapper = $this->createMock( Mapping_Engine::class );
		$logger = $this->createMock( Logger::class );
		$retry_manager = $this->createMock( Retry_Manager::class );

		$order_sync = new Order_Sync(
			$client,
			$customer_sync,
			$mapper,
			$logger,
			$retry_manager
		);

		// Get block message
		$message = $order_sync->get_status_block_message( $order );

		// Assert message contains basic information
		$this->assertStringContainsString( 'Status change blocked', $message );
		$this->assertStringContainsString( 'resolve the sync issue', $message );
	}
}
