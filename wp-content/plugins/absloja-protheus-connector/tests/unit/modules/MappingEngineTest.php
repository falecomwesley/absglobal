<?php
/**
 * Mapping Engine Unit Tests
 *
 * @package ABSLoja\ProtheusConnector\Tests\Unit\Modules
 */

namespace ABSLoja\ProtheusConnector\Tests\Unit\Modules;

use ABSLoja\ProtheusConnector\Modules\Mapping_Engine;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

/**
 * Class MappingEngineTest
 *
 * Unit tests for Mapping_Engine class.
 */
class MappingEngineTest extends TestCase {

	/**
	 * Set up test environment
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * Tear down test environment
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test get_customer_mapping returns default mapping when option not set
	 */
	public function test_get_customer_mapping_returns_default_when_option_not_set() {
		Functions\expect( 'get_option' )
			->once()
			->with( 'absloja_protheus_customer_mapping', \Mockery::type( 'array' ) )
			->andReturnUsing( function( $option, $default ) {
				return $default;
			} );

		$mapper = new Mapping_Engine();
		$result = $mapper->get_customer_mapping();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'A1_FILIAL', $result );
		$this->assertArrayHasKey( 'A1_NOME', $result );
		$this->assertArrayHasKey( 'A1_CGC', $result );
		$this->assertEquals( '01', $result['A1_FILIAL'] );
	}

	/**
	 * Test get_customer_mapping returns saved mapping when option exists
	 */
	public function test_get_customer_mapping_returns_saved_mapping_when_option_exists() {
		$custom_mapping = array(
			'A1_FILIAL' => '02',
			'A1_NOME'   => 'custom_name_field',
			'A1_CGC'    => 'custom_document_field',
		);

		Functions\expect( 'get_option' )
			->once()
			->with( 'absloja_protheus_customer_mapping', \Mockery::type( 'array' ) )
			->andReturn( $custom_mapping );

		$mapper = new Mapping_Engine();
		$result = $mapper->get_customer_mapping();

		$this->assertEquals( $custom_mapping, $result );
		$this->assertEquals( '02', $result['A1_FILIAL'] );
	}

	/**
	 * Test get_order_mapping returns default mapping with SC5 and SC6
	 */
	public function test_get_order_mapping_returns_default_with_sc5_and_sc6() {
		Functions\expect( 'get_option' )
			->once()
			->with( 'absloja_protheus_order_mapping', \Mockery::type( 'array' ) )
			->andReturnUsing( function( $option, $default ) {
				return $default;
			} );

		$mapper = new Mapping_Engine();
		$result = $mapper->get_order_mapping();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'SC5', $result );
		$this->assertArrayHasKey( 'SC6', $result );
		$this->assertIsArray( $result['SC5'] );
		$this->assertIsArray( $result['SC6'] );
		$this->assertArrayHasKey( 'C5_PEDWOO', $result['SC5'] );
		$this->assertArrayHasKey( 'C6_PRODUTO', $result['SC6'] );
	}

	/**
	 * Test get_product_mapping returns default mapping
	 */
	public function test_get_product_mapping_returns_default_mapping() {
		Functions\expect( 'get_option' )
			->once()
			->with( 'absloja_protheus_product_mapping', \Mockery::type( 'array' ) )
			->andReturnUsing( function( $option, $default ) {
				return $default;
			} );

		$mapper = new Mapping_Engine();
		$result = $mapper->get_product_mapping();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'sku', $result );
		$this->assertArrayHasKey( 'name', $result );
		$this->assertArrayHasKey( 'regular_price', $result );
		$this->assertEquals( 'B1_COD', $result['sku'] );
		$this->assertEquals( 'B1_DESC', $result['name'] );
	}

	/**
	 * Test get_payment_mapping returns mapped payment condition
	 */
	public function test_get_payment_mapping_returns_mapped_payment_condition() {
		$payment_mapping = array(
			'bacs'        => '001',
			'credit_card' => '004',
			'pix'         => '005',
			'cod'         => '003',
		);

		Functions\expect( 'get_option' )
			->once()
			->with( 'absloja_protheus_payment_mapping', \Mockery::type( 'array' ) )
			->andReturn( $payment_mapping );

		$mapper = new Mapping_Engine();
		$result = $mapper->get_payment_mapping( 'credit_card' );

		$this->assertEquals( '004', $result );
	}

	/**
	 * Test get_payment_mapping returns fallback for unmapped method
	 */
	public function test_get_payment_mapping_returns_fallback_for_unmapped_method() {
		$payment_mapping = array(
			'bacs' => '001',
			'cod'  => '003',
		);

		Functions\expect( 'get_option' )
			->once()
			->with( 'absloja_protheus_payment_mapping', \Mockery::type( 'array' ) )
			->andReturn( $payment_mapping );

		$mapper = new Mapping_Engine();
		$result = $mapper->get_payment_mapping( 'unknown_method' );

		// Should fallback to 'cod' or default '003'
		$this->assertEquals( '003', $result );
	}

	/**
	 * Test get_category_mapping returns mapped category ID
	 */
	public function test_get_category_mapping_returns_mapped_category_id() {
		$category_mapping = array(
			'01' => 15,
			'02' => 16,
			'03' => 17,
		);

		Functions\expect( 'get_option' )
			->once()
			->with( 'absloja_protheus_category_mapping', \Mockery::type( 'array' ) )
			->andReturn( $category_mapping );

		$mapper = new Mapping_Engine();
		$result = $mapper->get_category_mapping( '02' );

		$this->assertEquals( 16, $result );
	}

	/**
	 * Test get_category_mapping returns null for unmapped group
	 */
	public function test_get_category_mapping_returns_null_for_unmapped_group() {
		$category_mapping = array(
			'01' => 15,
			'02' => 16,
		);

		Functions\expect( 'get_option' )
			->once()
			->with( 'absloja_protheus_category_mapping', \Mockery::type( 'array' ) )
			->andReturn( $category_mapping );

		$mapper = new Mapping_Engine();
		$result = $mapper->get_category_mapping( '99' );

		$this->assertNull( $result );
	}

	/**
	 * Test get_tes_by_state returns correct TES for SP
	 */
	public function test_get_tes_by_state_returns_correct_tes_for_sp() {
		$tes_rules = array(
			'SP'      => '501',
			'RJ'      => '502',
			'default' => '502',
		);

		Functions\expect( 'get_option' )
			->once()
			->with( 'absloja_protheus_tes_rules', \Mockery::type( 'array' ) )
			->andReturn( $tes_rules );

		$mapper = new Mapping_Engine();
		$result = $mapper->get_tes_by_state( 'SP' );

		$this->assertEquals( '501', $result );
	}

	/**
	 * Test get_tes_by_state returns correct TES for other states
	 */
	public function test_get_tes_by_state_returns_correct_tes_for_other_states() {
		$tes_rules = array(
			'SP'      => '501',
			'RJ'      => '502',
			'MG'      => '502',
			'default' => '502',
		);

		Functions\expect( 'get_option' )
			->once()
			->with( 'absloja_protheus_tes_rules', \Mockery::type( 'array' ) )
			->andReturn( $tes_rules );

		$mapper = new Mapping_Engine();
		$result = $mapper->get_tes_by_state( 'RJ' );

		$this->assertEquals( '502', $result );
	}

	/**
	 * Test get_tes_by_state returns default for unmapped state
	 */
	public function test_get_tes_by_state_returns_default_for_unmapped_state() {
		$tes_rules = array(
			'SP'      => '501',
			'RJ'      => '502',
			'default' => '502',
		);

		Functions\expect( 'get_option' )
			->once()
			->with( 'absloja_protheus_tes_rules', \Mockery::type( 'array' ) )
			->andReturn( $tes_rules );

		$mapper = new Mapping_Engine();
		$result = $mapper->get_tes_by_state( 'XX' );

		$this->assertEquals( '502', $result );
	}

	/**
	 * Test get_status_mapping returns correct WooCommerce status
	 */
	public function test_get_status_mapping_returns_correct_woocommerce_status() {
		$status_mapping = array(
			'pending'   => 'pending',
			'approved'  => 'processing',
			'invoiced'  => 'completed',
			'cancelled' => 'cancelled',
		);

		Functions\expect( 'get_option' )
			->once()
			->with( 'absloja_protheus_status_mapping', \Mockery::type( 'array' ) )
			->andReturn( $status_mapping );

		$mapper = new Mapping_Engine();
		$result = $mapper->get_status_mapping( 'approved' );

		$this->assertEquals( 'processing', $result );
	}

	/**
	 * Test get_status_mapping returns pending for unmapped status
	 */
	public function test_get_status_mapping_returns_pending_for_unmapped_status() {
		$status_mapping = array(
			'approved' => 'processing',
			'invoiced' => 'completed',
		);

		Functions\expect( 'get_option' )
			->once()
			->with( 'absloja_protheus_status_mapping', \Mockery::type( 'array' ) )
			->andReturn( $status_mapping );

		$mapper = new Mapping_Engine();
		$result = $mapper->get_status_mapping( 'unknown_status' );

		$this->assertEquals( 'pending', $result );
	}

	/**
	 * Test validate_mapping returns empty array for valid customer mapping
	 */
	public function test_validate_mapping_returns_empty_array_for_valid_customer_mapping() {
		$valid_mapping = array(
			'A1_FILIAL' => '01',
			'A1_NOME'   => 'billing_first_name + billing_last_name',
			'A1_CGC'    => 'billing_cpf',
			'A1_END'    => 'billing_address_1',
			'A1_MUN'    => 'billing_city',
			'A1_EST'    => 'billing_state',
		);

		$mapper = new Mapping_Engine();
		$errors = $mapper->validate_mapping( 'customer', $valid_mapping );

		$this->assertIsArray( $errors );
		$this->assertEmpty( $errors );
	}

	/**
	 * Test validate_mapping returns errors for invalid customer mapping
	 */
	public function test_validate_mapping_returns_errors_for_invalid_customer_mapping() {
		$invalid_mapping = array(
			'A1_FILIAL' => '01',
			'A1_NOME'   => 'billing_name',
			// Missing required fields: A1_CGC, A1_END, A1_MUN, A1_EST
		);

		$mapper = new Mapping_Engine();
		$errors = $mapper->validate_mapping( 'customer', $invalid_mapping );

		$this->assertIsArray( $errors );
		$this->assertNotEmpty( $errors );
		$this->assertGreaterThanOrEqual( 4, count( $errors ) );
		$this->assertStringContainsString( 'A1_CGC', $errors[0] );
	}

	/**
	 * Test validate_mapping returns errors for missing SC5 in order mapping
	 */
	public function test_validate_mapping_returns_errors_for_missing_sc5_in_order_mapping() {
		$invalid_mapping = array(
			'SC6' => array(
				'C6_PRODUTO' => 'product_sku',
			),
		);

		$mapper = new Mapping_Engine();
		$errors = $mapper->validate_mapping( 'order', $invalid_mapping );

		$this->assertIsArray( $errors );
		$this->assertNotEmpty( $errors );
		$this->assertStringContainsString( 'SC5', $errors[0] );
	}

	/**
	 * Test validate_mapping returns errors for missing SC6 in order mapping
	 */
	public function test_validate_mapping_returns_errors_for_missing_sc6_in_order_mapping() {
		$invalid_mapping = array(
			'SC5' => array(
				'C5_PEDWOO' => 'order_id',
			),
		);

		$mapper = new Mapping_Engine();
		$errors = $mapper->validate_mapping( 'order', $invalid_mapping );

		$this->assertIsArray( $errors );
		$this->assertNotEmpty( $errors );
		$this->assertStringContainsString( 'SC6', $errors[0] );
	}

	/**
	 * Test validate_mapping returns empty array for valid order mapping
	 */
	public function test_validate_mapping_returns_empty_array_for_valid_order_mapping() {
		$valid_mapping = array(
			'SC5' => array(
				'C5_PEDWOO' => 'order_id',
			),
			'SC6' => array(
				'C6_PRODUTO' => 'product_sku',
			),
		);

		$mapper = new Mapping_Engine();
		$errors = $mapper->validate_mapping( 'order', $valid_mapping );

		$this->assertIsArray( $errors );
		$this->assertEmpty( $errors );
	}

	/**
	 * Test validate_mapping returns errors for invalid product mapping
	 */
	public function test_validate_mapping_returns_errors_for_invalid_product_mapping() {
		$invalid_mapping = array(
			'sku' => 'B1_COD',
			// Missing required fields: name, regular_price
		);

		$mapper = new Mapping_Engine();
		$errors = $mapper->validate_mapping( 'product', $invalid_mapping );

		$this->assertIsArray( $errors );
		$this->assertNotEmpty( $errors );
		$this->assertGreaterThanOrEqual( 2, count( $errors ) );
	}

	/**
	 * Test validate_mapping returns empty array for valid product mapping
	 */
	public function test_validate_mapping_returns_empty_array_for_valid_product_mapping() {
		$valid_mapping = array(
			'sku'           => 'B1_COD',
			'name'          => 'B1_DESC',
			'regular_price' => 'B1_PRV1',
		);

		$mapper = new Mapping_Engine();
		$errors = $mapper->validate_mapping( 'product', $valid_mapping );

		$this->assertIsArray( $errors );
		$this->assertEmpty( $errors );
	}

	/**
	 * Test validate_mapping returns error for invalid mapping type
	 */
	public function test_validate_mapping_returns_error_for_invalid_mapping_type() {
		$mapper = new Mapping_Engine();
		$errors = $mapper->validate_mapping( 'invalid_type', array() );

		$this->assertIsArray( $errors );
		$this->assertNotEmpty( $errors );
		$this->assertStringContainsString( 'Invalid mapping type', $errors[0] );
	}

	/**
	 * Test save_mapping saves valid customer mapping
	 */
	public function test_save_mapping_saves_valid_customer_mapping() {
		$valid_mapping = array(
			'A1_FILIAL' => '01',
			'A1_NOME'   => 'billing_first_name + billing_last_name',
			'A1_CGC'    => 'billing_cpf',
			'A1_END'    => 'billing_address_1',
			'A1_MUN'    => 'billing_city',
			'A1_EST'    => 'billing_state',
		);

		Functions\expect( 'update_option' )
			->once()
			->with( 'absloja_protheus_customer_mapping', $valid_mapping )
			->andReturn( true );

		$mapper = new Mapping_Engine();
		$result = $mapper->save_mapping( 'customer', $valid_mapping );

		$this->assertTrue( $result );
	}

	/**
	 * Test save_mapping returns false for invalid mapping
	 */
	public function test_save_mapping_returns_false_for_invalid_mapping() {
		$invalid_mapping = array(
			'A1_FILIAL' => '01',
			// Missing required fields
		);

		$mapper = new Mapping_Engine();
		$result = $mapper->save_mapping( 'customer', $invalid_mapping );

		$this->assertFalse( $result );
	}

	/**
	 * Test save_mapping saves payment mapping
	 */
	public function test_save_mapping_saves_payment_mapping() {
		$payment_mapping = array(
			'bacs' => '001',
			'pix'  => '005',
		);

		Functions\expect( 'update_option' )
			->once()
			->with( 'absloja_protheus_payment_mapping', $payment_mapping )
			->andReturn( true );

		$mapper = new Mapping_Engine();
		$result = $mapper->save_mapping( 'payment', $payment_mapping );

		$this->assertTrue( $result );
	}

	/**
	 * Test save_mapping saves TES rules with correct option name
	 */
	public function test_save_mapping_saves_tes_rules_with_correct_option_name() {
		$tes_rules = array(
			'SP'      => '501',
			'RJ'      => '502',
			'default' => '502',
		);

		Functions\expect( 'update_option' )
			->once()
			->with( 'absloja_protheus_tes_rules', $tes_rules )
			->andReturn( true );

		$mapper = new Mapping_Engine();
		$result = $mapper->save_mapping( 'tes', $tes_rules );

		$this->assertTrue( $result );
	}

	/**
	 * Test save_mapping saves status mapping
	 */
	public function test_save_mapping_saves_status_mapping() {
		$status_mapping = array(
			'approved' => 'processing',
			'invoiced' => 'completed',
		);

		Functions\expect( 'update_option' )
			->once()
			->with( 'absloja_protheus_status_mapping', $status_mapping )
			->andReturn( true );

		$mapper = new Mapping_Engine();
		$result = $mapper->save_mapping( 'status', $status_mapping );

		$this->assertTrue( $result );
	}

	/**
	 * Test save_mapping saves category mapping
	 */
	public function test_save_mapping_saves_category_mapping() {
		$category_mapping = array(
			'01' => 15,
			'02' => 16,
		);

		Functions\expect( 'update_option' )
			->once()
			->with( 'absloja_protheus_category_mapping', $category_mapping )
			->andReturn( true );

		$mapper = new Mapping_Engine();
		$result = $mapper->save_mapping( 'category', $category_mapping );

		$this->assertTrue( $result );
	}

	/**
	 * Test constructor initializes defaults when not already initialized
	 */
	public function test_constructor_initializes_defaults_when_not_initialized() {
		Functions\expect( 'get_option' )
			->once()
			->with( 'absloja_protheus_mappings_initialized' )
			->andReturn( false );

		Functions\expect( 'add_option' )
			->times( 7 )
			->andReturn( true );

		Functions\expect( 'update_option' )
			->once()
			->with( 'absloja_protheus_mappings_initialized', true )
			->andReturn( true );

		new Mapping_Engine();

		// If we reach here without errors, initialization worked
		$this->assertTrue( true );
	}

	/**
	 * Test constructor skips initialization when already initialized
	 */
	public function test_constructor_skips_initialization_when_already_initialized() {
		Functions\expect( 'get_option' )
			->once()
			->with( 'absloja_protheus_mappings_initialized' )
			->andReturn( true );

		Functions\expect( 'add_option' )
			->never();

		Functions\expect( 'update_option' )
			->never();

		new Mapping_Engine();

		// If we reach here without errors, initialization was skipped correctly
		$this->assertTrue( true );
	}
}
