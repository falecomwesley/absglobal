<?php
/**
 * Tests for Mapping_Engine persistence functionality
 *
 * @package ABSLoja\ProtheusConnector\Tests
 */

namespace ABSLoja\ProtheusConnector\Tests\Unit\Modules;

use ABSLoja\ProtheusConnector\Modules\Mapping_Engine;
use PHPUnit\Framework\TestCase;

/**
 * Test Mapping_Engine save_mapping method
 */
class MappingEnginePersistenceTest extends TestCase {

	/**
	 * Mapping Engine instance
	 *
	 * @var Mapping_Engine
	 */
	private $mapper;

	/**
	 * Set up test environment
	 */
	protected function setUp(): void {
		parent::setUp();
		
		// Mock WordPress functions
		if ( ! function_exists( 'update_option' ) ) {
			function update_option( $option, $value ) {
				global $wp_options;
				$wp_options[ $option ] = $value;
				return true;
			}
		}
		
		if ( ! function_exists( 'get_option' ) ) {
			function get_option( $option, $default = false ) {
				global $wp_options;
				return $wp_options[ $option ] ?? $default;
			}
		}
		
		if ( ! function_exists( 'add_option' ) ) {
			function add_option( $option, $value ) {
				global $wp_options;
				if ( ! isset( $wp_options[ $option ] ) ) {
					$wp_options[ $option ] = $value;
					return true;
				}
				return false;
			}
		}
		
		// Reset global options
		global $wp_options;
		$wp_options = array();
		
		$this->mapper = new Mapping_Engine();
	}

	/**
	 * Test save_mapping stores customer mapping with correct prefix
	 */
	public function test_save_customer_mapping_uses_correct_prefix() {
		$mapping = array(
			'A1_FILIAL' => '01',
			'A1_NOME'   => 'billing_first_name + billing_last_name',
			'A1_CGC'    => 'billing_cpf',
			'A1_END'    => 'billing_address_1',
			'A1_MUN'    => 'billing_city',
			'A1_EST'    => 'billing_state',
		);

		$result = $this->mapper->save_mapping( 'customer', $mapping );

		$this->assertTrue( $result, 'save_mapping should return true on success' );
		
		global $wp_options;
		$this->assertArrayHasKey( 'absloja_protheus_customer_mapping', $wp_options );
		$this->assertEquals( $mapping, $wp_options['absloja_protheus_customer_mapping'] );
	}

	/**
	 * Test save_mapping stores order mapping with correct prefix
	 */
	public function test_save_order_mapping_uses_correct_prefix() {
		$mapping = array(
			'SC5' => array(
				'C5_FILIAL' => '01',
				'C5_PEDWOO' => 'order_id',
			),
			'SC6' => array(
				'C6_PRODUTO' => 'product_sku',
				'C6_QTDVEN'  => 'quantity',
			),
		);

		$result = $this->mapper->save_mapping( 'order', $mapping );

		$this->assertTrue( $result, 'save_mapping should return true on success' );
		
		global $wp_options;
		$this->assertArrayHasKey( 'absloja_protheus_order_mapping', $wp_options );
		$this->assertEquals( $mapping, $wp_options['absloja_protheus_order_mapping'] );
	}

	/**
	 * Test save_mapping stores product mapping with correct prefix
	 */
	public function test_save_product_mapping_uses_correct_prefix() {
		$mapping = array(
			'sku'           => 'B1_COD',
			'name'          => 'B1_DESC',
			'regular_price' => 'B1_PRV1',
		);

		$result = $this->mapper->save_mapping( 'product', $mapping );

		$this->assertTrue( $result, 'save_mapping should return true on success' );
		
		global $wp_options;
		$this->assertArrayHasKey( 'absloja_protheus_product_mapping', $wp_options );
		$this->assertEquals( $mapping, $wp_options['absloja_protheus_product_mapping'] );
	}

	/**
	 * Test save_mapping stores payment mapping with correct prefix
	 */
	public function test_save_payment_mapping_uses_correct_prefix() {
		$mapping = array(
			'bacs' => '001',
			'pix'  => '005',
		);

		$result = $this->mapper->save_mapping( 'payment', $mapping );

		$this->assertTrue( $result, 'save_mapping should return true on success' );
		
		global $wp_options;
		$this->assertArrayHasKey( 'absloja_protheus_payment_mapping', $wp_options );
		$this->assertEquals( $mapping, $wp_options['absloja_protheus_payment_mapping'] );
	}

	/**
	 * Test save_mapping stores category mapping with correct prefix
	 */
	public function test_save_category_mapping_uses_correct_prefix() {
		$mapping = array(
			'01' => 15,
			'02' => 16,
		);

		$result = $this->mapper->save_mapping( 'category', $mapping );

		$this->assertTrue( $result, 'save_mapping should return true on success' );
		
		global $wp_options;
		$this->assertArrayHasKey( 'absloja_protheus_category_mapping', $wp_options );
		$this->assertEquals( $mapping, $wp_options['absloja_protheus_category_mapping'] );
	}

	/**
	 * Test save_mapping uses 'tes_rules' for TES type (special case)
	 */
	public function test_save_tes_mapping_uses_tes_rules_option_name() {
		$mapping = array(
			'SP'      => '501',
			'RJ'      => '502',
			'default' => '502',
		);

		$result = $this->mapper->save_mapping( 'tes', $mapping );

		$this->assertTrue( $result, 'save_mapping should return true on success' );
		
		global $wp_options;
		// Should use 'tes_rules' instead of 'tes_mapping'
		$this->assertArrayHasKey( 'absloja_protheus_tes_rules', $wp_options );
		$this->assertArrayNotHasKey( 'absloja_protheus_tes_mapping', $wp_options );
		$this->assertEquals( $mapping, $wp_options['absloja_protheus_tes_rules'] );
	}

	/**
	 * Test save_mapping stores status mapping with correct prefix
	 */
	public function test_save_status_mapping_uses_correct_prefix() {
		$mapping = array(
			'pending'  => 'pending',
			'approved' => 'processing',
		);

		$result = $this->mapper->save_mapping( 'status', $mapping );

		$this->assertTrue( $result, 'save_mapping should return true on success' );
		
		global $wp_options;
		$this->assertArrayHasKey( 'absloja_protheus_status_mapping', $wp_options );
		$this->assertEquals( $mapping, $wp_options['absloja_protheus_status_mapping'] );
	}

	/**
	 * Test save_mapping validates before saving
	 */
	public function test_save_mapping_validates_before_saving() {
		$invalid_mapping = array(
			'A1_FILIAL' => '01',
			// Missing required fields
		);

		$result = $this->mapper->save_mapping( 'customer', $invalid_mapping );

		$this->assertFalse( $result, 'save_mapping should return false for invalid mapping' );
		
		global $wp_options;
		// Should not save invalid mapping
		$this->assertArrayNotHasKey( 'absloja_protheus_customer_mapping', $wp_options );
	}

	/**
	 * Test save_mapping serializes arrays correctly
	 */
	public function test_save_mapping_serializes_arrays() {
		$mapping = array(
			'SC5' => array(
				'C5_FILIAL'  => '01',
				'C5_PEDWOO'  => 'order_id',
				'C5_EMISSAO' => 'date_created',
			),
			'SC6' => array(
				'C6_PRODUTO' => 'product_sku',
				'C6_QTDVEN'  => 'quantity',
				'C6_PRCVEN'  => 'unit_price',
			),
		);

		$result = $this->mapper->save_mapping( 'order', $mapping );

		$this->assertTrue( $result, 'save_mapping should return true on success' );
		
		global $wp_options;
		$stored = $wp_options['absloja_protheus_order_mapping'];
		
		// Verify nested arrays are preserved
		$this->assertIsArray( $stored );
		$this->assertArrayHasKey( 'SC5', $stored );
		$this->assertArrayHasKey( 'SC6', $stored );
		$this->assertIsArray( $stored['SC5'] );
		$this->assertIsArray( $stored['SC6'] );
		$this->assertEquals( $mapping['SC5'], $stored['SC5'] );
		$this->assertEquals( $mapping['SC6'], $stored['SC6'] );
	}

	/**
	 * Test save_mapping returns false for invalid type
	 */
	public function test_save_mapping_returns_false_for_invalid_type() {
		$mapping = array( 'test' => 'value' );

		$result = $this->mapper->save_mapping( 'invalid_type', $mapping );

		$this->assertFalse( $result, 'save_mapping should return false for invalid type' );
	}

	/**
	 * Test save_mapping overwrites existing mapping
	 */
	public function test_save_mapping_overwrites_existing() {
		$original_mapping = array(
			'bacs' => '001',
			'pix'  => '005',
		);

		$updated_mapping = array(
			'bacs'        => '001',
			'pix'         => '005',
			'credit_card' => '004',
		);

		// Save original
		$this->mapper->save_mapping( 'payment', $original_mapping );
		
		// Save updated
		$result = $this->mapper->save_mapping( 'payment', $updated_mapping );

		$this->assertTrue( $result, 'save_mapping should return true on success' );
		
		global $wp_options;
		$stored = $wp_options['absloja_protheus_payment_mapping'];
		
		// Should have updated mapping
		$this->assertEquals( $updated_mapping, $stored );
		$this->assertArrayHasKey( 'credit_card', $stored );
	}
}
