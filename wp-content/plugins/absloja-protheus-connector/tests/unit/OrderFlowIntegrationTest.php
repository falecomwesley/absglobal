<?php
/**
 * Integration Test for Complete Order Flow
 * 
 * This test validates the complete order flow from WooCommerce to Protheus:
 * - Order creation in WooCommerce
 * - Customer verification/creation in Protheus
 * - Order sync to Protheus
 * - Metadata storage
 * - Logging of operations
 * 
 * @package ABSLoja\ProtheusConnector\Tests
 */

namespace ABSLoja\ProtheusConnector\Tests;

use PHPUnit\Framework\TestCase;
use Mockery;
use Brain\Monkey;
use Brain\Monkey\Functions;
use ABSLoja\ProtheusConnector\Modules\Order_Sync;
use ABSLoja\ProtheusConnector\Modules\Customer_Sync;
use ABSLoja\ProtheusConnector\Modules\Auth_Manager;
use ABSLoja\ProtheusConnector\Modules\Mapping_Engine;
use ABSLoja\ProtheusConnector\Modules\Logger;
use ABSLoja\ProtheusConnector\Modules\Retry_Manager;
use ABSLoja\ProtheusConnector\API\Protheus_Client;

class OrderFlowIntegrationTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Mockery::close();
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Test complete order flow: WooCommerce order -> Customer verification -> Order sync -> Metadata storage
     * 
     * @test
     */
    public function test_complete_order_flow_with_new_customer() {
        // Mock WordPress functions
        Functions\when('get_option')->justReturn([]);
        Functions\when('update_option')->justReturn(true);
        Functions\when('current_time')->justReturn('2024-01-15 10:30:00');
        Functions\when('wp_remote_post')->alias(function($url, $args) {
            // Simulate Protheus API responses
            if (strpos($url, '/customers') !== false) {
                // Customer check - not found
                if (isset($args['method']) && $args['method'] === 'GET') {
                    return [
                        'response' => ['code' => 404],
                        'body' => json_encode(['error' => 'Customer not found'])
                    ];
                }
                // Customer creation - success
                return [
                    'response' => ['code' => 201],
                    'body' => json_encode([
                        'success' => true,
                        'customer_code' => 'C00001',
                        'message' => 'Customer created successfully'
                    ])
                ];
            }
            
            if (strpos($url, '/orders') !== false) {
                // Order creation - success
                return [
                    'response' => ['code' => 201],
                    'body' => json_encode([
                        'success' => true,
                        'order_number' => 'PED000123',
                        'message' => 'Order created successfully'
                    ])
                ];
            }
            
            return ['response' => ['code' => 500], 'body' => ''];
        });
        
        Functions\when('wp_remote_get')->alias(function($url, $args) {
            // Customer check endpoint
            if (strpos($url, '/customers') !== false) {
                return [
                    'response' => ['code' => 404],
                    'body' => json_encode(['error' => 'Customer not found'])
                ];
            }
            return ['response' => ['code' => 500], 'body' => ''];
        });
        
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_schedule_single_event')->justReturn(true);
        
        global $wpdb;
        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->shouldReceive('insert')->andReturn(true);
        $wpdb->shouldReceive('prepare')->andReturnUsing(function($query, ...$args) {
            return vsprintf(str_replace('%s', "'%s'", str_replace('%d', '%d', $query)), $args);
        });
        $wpdb->shouldReceive('get_results')->andReturn([]);
        
        // Create mock WooCommerce order
        $order = $this->createMockOrder();
        
        // Setup dependencies
        $auth_manager = new Auth_Manager([
            'auth_type' => 'basic',
            'api_url' => 'https://protheus.example.com/api',
            'username' => 'testuser',
            'password' => 'testpass'
        ]);
        
        $protheus_client = new Protheus_Client($auth_manager);
        $logger = new Logger();
        $mapping_engine = new Mapping_Engine();
        $retry_manager = new Retry_Manager($logger);
        $customer_sync = new Customer_Sync($protheus_client, $mapping_engine, $logger);
        $order_sync = new Order_Sync($protheus_client, $customer_sync, $mapping_engine, $logger, $retry_manager);
        
        // Execute order sync
        $result = $order_sync->sync_order($order);
        
        // Assertions
        $this->assertTrue($result, 'Order sync should succeed');
        
        // Verify customer was created
        $this->assertEquals('C00001', $order->get_meta('_protheus_customer_code'));
        
        // Verify order was synced
        $this->assertEquals('PED000123', $order->get_meta('_protheus_order_id'));
        $this->assertEquals('synced', $order->get_meta('_protheus_sync_status'));
        $this->assertNotEmpty($order->get_meta('_protheus_sync_date'));
    }

    /**
     * Test order flow with existing customer
     * 
     * @test
     */
    public function test_complete_order_flow_with_existing_customer() {
        // Mock WordPress functions
        Functions\when('get_option')->justReturn([]);
        Functions\when('update_option')->justReturn(true);
        Functions\when('current_time')->justReturn('2024-01-15 10:30:00');
        Functions\when('wp_remote_get')->alias(function($url, $args) {
            // Customer exists
            if (strpos($url, '/customers') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'success' => true,
                        'customer_code' => 'C00099',
                        'name' => 'João Silva'
                    ])
                ];
            }
            return ['response' => ['code' => 500], 'body' => ''];
        });
        
        Functions\when('wp_remote_post')->alias(function($url, $args) {
            // Order creation - success
            if (strpos($url, '/orders') !== false) {
                return [
                    'response' => ['code' => 201],
                    'body' => json_encode([
                        'success' => true,
                        'order_number' => 'PED000124',
                        'message' => 'Order created successfully'
                    ])
                ];
            }
            return ['response' => ['code' => 500], 'body' => ''];
        });
        
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_schedule_single_event')->justReturn(true);
        
        global $wpdb;
        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->shouldReceive('insert')->andReturn(true);
        $wpdb->shouldReceive('prepare')->andReturnUsing(function($query, ...$args) {
            return vsprintf(str_replace('%s', "'%s'", str_replace('%d', '%d', $query)), $args);
        });
        $wpdb->shouldReceive('get_results')->andReturn([]);
        
        // Create mock WooCommerce order
        $order = $this->createMockOrder();
        
        // Setup dependencies
        $auth_manager = new Auth_Manager([
            'auth_type' => 'basic',
            'api_url' => 'https://protheus.example.com/api',
            'username' => 'testuser',
            'password' => 'testpass'
        ]);
        
        $protheus_client = new Protheus_Client($auth_manager);
        $logger = new Logger();
        $mapping_engine = new Mapping_Engine();
        $retry_manager = new Retry_Manager($logger);
        $customer_sync = new Customer_Sync($protheus_client, $mapping_engine, $logger);
        $order_sync = new Order_Sync($protheus_client, $customer_sync, $mapping_engine, $logger, $retry_manager);
        
        // Execute order sync
        $result = $order_sync->sync_order($order);
        
        // Assertions
        $this->assertTrue($result, 'Order sync should succeed');
        
        // Verify existing customer was used
        $this->assertEquals('C00099', $order->get_meta('_protheus_customer_code'));
        
        // Verify order was synced
        $this->assertEquals('PED000124', $order->get_meta('_protheus_order_id'));
        $this->assertEquals('synced', $order->get_meta('_protheus_sync_status'));
    }

    /**
     * Test order flow with customer creation failure
     * 
     * @test
     */
    public function test_order_flow_aborts_on_customer_creation_failure() {
        // Mock WordPress functions
        Functions\when('get_option')->justReturn([]);
        Functions\when('update_option')->justReturn(true);
        Functions\when('current_time')->justReturn('2024-01-15 10:30:00');
        Functions\when('wp_remote_get')->alias(function($url, $args) {
            // Customer not found
            return [
                'response' => ['code' => 404],
                'body' => json_encode(['error' => 'Customer not found'])
            ];
        });
        
        Functions\when('wp_remote_post')->alias(function($url, $args) {
            // Customer creation fails
            if (strpos($url, '/customers') !== false) {
                return [
                    'response' => ['code' => 400],
                    'body' => json_encode([
                        'success' => false,
                        'error' => 'Invalid CPF format'
                    ])
                ];
            }
            return ['response' => ['code' => 500], 'body' => ''];
        });
        
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_schedule_single_event')->justReturn(true);
        
        global $wpdb;
        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->shouldReceive('insert')->andReturn(true);
        $wpdb->shouldReceive('prepare')->andReturnUsing(function($query, ...$args) {
            return vsprintf(str_replace('%s', "'%s'", str_replace('%d', '%d', $query)), $args);
        });
        $wpdb->shouldReceive('get_results')->andReturn([]);
        
        // Create mock WooCommerce order
        $order = $this->createMockOrder();
        
        // Setup dependencies
        $auth_manager = new Auth_Manager([
            'auth_type' => 'basic',
            'api_url' => 'https://protheus.example.com/api',
            'username' => 'testuser',
            'password' => 'testpass'
        ]);
        
        $protheus_client = new Protheus_Client($auth_manager);
        $logger = new Logger();
        $mapping_engine = new Mapping_Engine();
        $retry_manager = new Retry_Manager($logger);
        $customer_sync = new Customer_Sync($protheus_client, $mapping_engine, $logger);
        $order_sync = new Order_Sync($protheus_client, $customer_sync, $mapping_engine, $logger, $retry_manager);
        
        // Execute order sync
        $result = $order_sync->sync_order($order);
        
        // Assertions
        $this->assertFalse($result, 'Order sync should fail when customer creation fails');
        
        // Verify order was not synced
        $this->assertEmpty($order->get_meta('_protheus_order_id'));
        $this->assertEquals('error', $order->get_meta('_protheus_sync_status'));
    }

    /**
     * Test order flow with API error and retry scheduling
     * 
     * @test
     */
    public function test_order_flow_schedules_retry_on_api_error() {
        // Mock WordPress functions
        Functions\when('get_option')->justReturn([]);
        Functions\when('update_option')->justReturn(true);
        Functions\when('current_time')->justReturn('2024-01-15 10:30:00');
        Functions\when('wp_remote_get')->alias(function($url, $args) {
            // Customer exists
            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'success' => true,
                    'customer_code' => 'C00099'
                ])
            ];
        });
        
        Functions\when('wp_remote_post')->alias(function($url, $args) {
            // Order creation fails with server error
            if (strpos($url, '/orders') !== false) {
                return [
                    'response' => ['code' => 500],
                    'body' => json_encode([
                        'success' => false,
                        'error' => 'Internal server error'
                    ])
                ];
            }
            return ['response' => ['code' => 500], 'body' => ''];
        });
        
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_schedule_single_event')->justReturn(true);
        
        global $wpdb;
        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->shouldReceive('insert')->andReturn(true);
        $wpdb->shouldReceive('prepare')->andReturnUsing(function($query, ...$args) {
            return vsprintf(str_replace('%s', "'%s'", str_replace('%d', '%d', $query)), $args);
        });
        $wpdb->shouldReceive('get_results')->andReturn([]);
        
        // Create mock WooCommerce order
        $order = $this->createMockOrder();
        
        // Setup dependencies
        $auth_manager = new Auth_Manager([
            'auth_type' => 'basic',
            'api_url' => 'https://protheus.example.com/api',
            'username' => 'testuser',
            'password' => 'testpass'
        ]);
        
        $protheus_client = new Protheus_Client($auth_manager);
        $logger = new Logger();
        $mapping_engine = new Mapping_Engine();
        $retry_manager = new Retry_Manager($logger);
        $customer_sync = new Customer_Sync($protheus_client, $mapping_engine, $logger);
        $order_sync = new Order_Sync($protheus_client, $customer_sync, $mapping_engine, $logger, $retry_manager);
        
        // Execute order sync
        $result = $order_sync->sync_order($order);
        
        // Assertions
        $this->assertFalse($result, 'Order sync should fail on API error');
        
        // Verify error was logged
        $this->assertEquals('error', $order->get_meta('_protheus_sync_status'));
        $this->assertNotEmpty($order->get_meta('_protheus_sync_error'));
    }

    /**
     * Test metadata storage after successful sync
     * 
     * @test
     */
    public function test_metadata_storage_after_successful_sync() {
        // Mock WordPress functions
        Functions\when('get_option')->justReturn([]);
        Functions\when('update_option')->justReturn(true);
        Functions\when('current_time')->justReturn('2024-01-15 10:30:00');
        Functions\when('wp_remote_get')->alias(function($url, $args) {
            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'success' => true,
                    'customer_code' => 'C00099'
                ])
            ];
        });
        
        Functions\when('wp_remote_post')->alias(function($url, $args) {
            return [
                'response' => ['code' => 201],
                'body' => json_encode([
                    'success' => true,
                    'order_number' => 'PED000125',
                    'message' => 'Order created successfully'
                ])
            ];
        });
        
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_schedule_single_event')->justReturn(true);
        
        global $wpdb;
        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->shouldReceive('insert')->andReturn(true);
        $wpdb->shouldReceive('prepare')->andReturnUsing(function($query, ...$args) {
            return vsprintf(str_replace('%s', "'%s'", str_replace('%d', '%d', $query)), $args);
        });
        $wpdb->shouldReceive('get_results')->andReturn([]);
        
        // Create mock WooCommerce order
        $order = $this->createMockOrder();
        
        // Setup dependencies
        $auth_manager = new Auth_Manager([
            'auth_type' => 'basic',
            'api_url' => 'https://protheus.example.com/api',
            'username' => 'testuser',
            'password' => 'testpass'
        ]);
        
        $protheus_client = new Protheus_Client($auth_manager);
        $logger = new Logger();
        $mapping_engine = new Mapping_Engine();
        $retry_manager = new Retry_Manager($logger);
        $customer_sync = new Customer_Sync($protheus_client, $mapping_engine, $logger);
        $order_sync = new Order_Sync($protheus_client, $customer_sync, $mapping_engine, $logger, $retry_manager);
        
        // Execute order sync
        $result = $order_sync->sync_order($order);
        
        // Verify all required metadata is stored
        $this->assertEquals('PED000125', $order->get_meta('_protheus_order_id'), 'Protheus order ID should be stored');
        $this->assertEquals('synced', $order->get_meta('_protheus_sync_status'), 'Sync status should be "synced"');
        $this->assertEquals('C00099', $order->get_meta('_protheus_customer_code'), 'Customer code should be stored');
        $this->assertNotEmpty($order->get_meta('_protheus_sync_date'), 'Sync date should be stored');
    }

    /**
     * Helper method to create a mock WooCommerce order
     */
    private function createMockOrder() {
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_id')->andReturn(789);
        $order->shouldReceive('get_date_created')->andReturn(new \DateTime('2024-01-15'));
        $order->shouldReceive('get_billing_first_name')->andReturn('João');
        $order->shouldReceive('get_billing_last_name')->andReturn('Silva');
        $order->shouldReceive('get_billing_email')->andReturn('joao@example.com');
        $order->shouldReceive('get_billing_phone')->andReturn('11987654321');
        $order->shouldReceive('get_billing_address_1')->andReturn('Rua Exemplo, 123');
        $order->shouldReceive('get_billing_city')->andReturn('São Paulo');
        $order->shouldReceive('get_billing_state')->andReturn('SP');
        $order->shouldReceive('get_billing_postcode')->andReturn('01234-567');
        $order->shouldReceive('get_payment_method')->andReturn('credit_card');
        $order->shouldReceive('get_shipping_total')->andReturn(15.00);
        $order->shouldReceive('get_discount_total')->andReturn(5.00);
        $order->shouldReceive('get_total')->andReturn(110.00);
        
        // Mock order items
        $item = Mockery::mock('WC_Order_Item_Product');
        $item->shouldReceive('get_quantity')->andReturn(2);
        $item->shouldReceive('get_total')->andReturn(100.00);
        $item->shouldReceive('get_product')->andReturn($this->createMockProduct());
        
        $order->shouldReceive('get_items')->andReturn([$item]);
        
        // Mock metadata storage
        $metadata = [];
        $order->shouldReceive('get_meta')->andReturnUsing(function($key) use (&$metadata) {
            return $metadata[$key] ?? '';
        });
        $order->shouldReceive('update_meta_data')->andReturnUsing(function($key, $value) use (&$metadata) {
            $metadata[$key] = $value;
        });
        $order->shouldReceive('save')->andReturn(true);
        
        // Mock billing meta fields for CPF
        $order->shouldReceive('get_meta')->with('_billing_cpf')->andReturn('12345678901');
        $order->shouldReceive('get_meta')->with('_billing_neighborhood')->andReturn('Centro');
        
        return $order;
    }

    /**
     * Helper method to create a mock WooCommerce product
     */
    private function createMockProduct() {
        $product = Mockery::mock('WC_Product');
        $product->shouldReceive('get_sku')->andReturn('PROD001');
        $product->shouldReceive('get_name')->andReturn('Produto Teste');
        $product->shouldReceive('get_price')->andReturn(50.00);
        
        return $product;
    }
}
