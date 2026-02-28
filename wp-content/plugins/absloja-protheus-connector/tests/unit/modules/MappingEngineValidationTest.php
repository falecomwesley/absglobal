<?php
/**
 * Tests for Mapping_Engine validation functionality
 *
 * @package ABSLoja\ProtheusConnector\Tests
 */

namespace ABSLoja\ProtheusConnector\Tests\Unit\Modules;

use ABSLoja\ProtheusConnector\Modules\Mapping_Engine;
use PHPUnit\Framework\TestCase;

/**
 * Test Mapping_Engine validation methods
 */
class MappingEngineValidationTest extends TestCase {

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
		$this->mapper = new Mapping_Engine();
	}

	/**
	 * Test customer mapping validation with valid data
	 */
	public function test_validate_customer_mapping_valid() {
		$mapping = array(
			'A1_FILIAL' => '01',
			'A1_NOME'   => 'billing_first_name + billing_last_name',
			'A1_CGC'    => 'billing_cpf',
			'A1_END'    => 'billing_address_1',
			'A1_MUN'    => 'billing_city',
			'A1_EST'    => 'billing_state',
		);

		$errors = $this->mapper->validate_mapping( 'customer', $mapping );

		$this->assertEmpty( $errors, 'Valid customer mapping should have no errors' );
	}

	/**
	 * Test customer mapping validation with missing required fields
	 */
	public function test_validate_customer_mapping_missing_fields() {
		$mapping = array(
			'A1_FILIAL' => '01',
			'A1_NOME'   => 'billing_first_name',
			// Missing A1_CGC, A1_END, A1_MUN, A1_EST
		);

		$errors = $this->mapper->validate_mapping( 'customer', $mapping );

		$this->assertNotEmpty( $errors, 'Customer mapping with missing fields should have errors' );
		$this->assertCount( 4, $errors, 'Should have 4 errors for missing fields' );
		$this->assertStringContainsString( 'A1_CGC', $errors[0] );
		$this->assertStringContainsString( 'A1_END', $errors[1] );
		$this->assertStringContainsString( 'A1_MUN', $errors[2] );
		$this->assertStringContainsString( 'A1_EST', $errors[3] );
	}

	/**
	 * Test customer mapping validation with empty required fields
	 */
	public function test_validate_customer_mapping_empty_fields() {
		$mapping = array(
			'A1_FILIAL' => '01',
			'A1_NOME'   => '',
			'A1_CGC'    => '',
			'A1_END'    => 'billing_address_1',
			'A1_MUN'    => 'billing_city',
			'A1_EST'    => 'billing_state',
		);

		$errors = $this->mapper->validate_mapping( 'customer', $mapping );

		$this->assertNotEmpty( $errors, 'Customer mapping with empty fields should have errors' );
		$this->assertCount( 2, $errors, 'Should have 2 errors for empty fields' );
	}

	/**
	 * Test order mapping validation with valid data
	 */
	public function test_validate_order_mapping_valid() {
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

		$errors = $this->mapper->validate_mapping( 'order', $mapping );

		$this->assertEmpty( $errors, 'Valid order mapping should have no errors' );
	}

	/**
	 * Test order mapping validation with missing SC5
	 */
	public function test_validate_order_mapping_missing_sc5() {
		$mapping = array(
			'SC6' => array(
				'C6_PRODUTO' => 'product_sku',
			),
		);

		$errors = $this->mapper->validate_mapping( 'order', $mapping );

		$this->assertNotEmpty( $errors, 'Order mapping without SC5 should have errors' );
		$this->assertStringContainsString( 'SC5', $errors[0] );
	}

	/**
	 * Test order mapping validation with missing SC6
	 */
	public function test_validate_order_mapping_missing_sc6() {
		$mapping = array(
			'SC5' => array(
				'C5_FILIAL' => '01',
			),
		);

		$errors = $this->mapper->validate_mapping( 'order', $mapping );

		$this->assertNotEmpty( $errors, 'Order mapping without SC6 should have errors' );
		$this->assertStringContainsString( 'SC6', $errors[0] );
	}

	/**
	 * Test order mapping validation with non-array SC5
	 */
	public function test_validate_order_mapping_invalid_sc5_type() {
		$mapping = array(
			'SC5' => 'not_an_array',
			'SC6' => array(
				'C6_PRODUTO' => 'product_sku',
			),
		);

		$errors = $this->mapper->validate_mapping( 'order', $mapping );

		$this->assertNotEmpty( $errors, 'Order mapping with non-array SC5 should have errors' );
		$this->assertStringContainsString( 'SC5', $errors[0] );
	}

	/**
	 * Test product mapping validation with valid data
	 */
	public function test_validate_product_mapping_valid() {
		$mapping = array(
			'sku'           => 'B1_COD',
			'name'          => 'B1_DESC',
			'regular_price' => 'B1_PRV1',
		);

		$errors = $this->mapper->validate_mapping( 'product', $mapping );

		$this->assertEmpty( $errors, 'Valid product mapping should have no errors' );
	}

	/**
	 * Test product mapping validation with missing required fields
	 */
	public function test_validate_product_mapping_missing_fields() {
		$mapping = array(
			'sku' => 'B1_COD',
			// Missing name and regular_price
		);

		$errors = $this->mapper->validate_mapping( 'product', $mapping );

		$this->assertNotEmpty( $errors, 'Product mapping with missing fields should have errors' );
		$this->assertCount( 2, $errors, 'Should have 2 errors for missing fields' );
		$this->assertStringContainsString( 'name', $errors[0] );
		$this->assertStringContainsString( 'regular_price', $errors[1] );
	}

	/**
	 * Test payment mapping validation with valid array
	 */
	public function test_validate_payment_mapping_valid() {
		$mapping = array(
			'bacs' => '001',
			'pix'  => '005',
		);

		$errors = $this->mapper->validate_mapping( 'payment', $mapping );

		$this->assertEmpty( $errors, 'Valid payment mapping should have no errors' );
	}

	/**
	 * Test payment mapping validation with non-array
	 */
	public function test_validate_payment_mapping_invalid_type() {
		$mapping = 'not_an_array';

		$errors = $this->mapper->validate_mapping( 'payment', $mapping );

		$this->assertNotEmpty( $errors, 'Payment mapping with non-array should have errors' );
		$this->assertStringContainsString( 'array', $errors[0] );
	}

	/**
	 * Test category mapping validation with valid array
	 */
	public function test_validate_category_mapping_valid() {
		$mapping = array(
			'01' => 15,
			'02' => 16,
		);

		$errors = $this->mapper->validate_mapping( 'category', $mapping );

		$this->assertEmpty( $errors, 'Valid category mapping should have no errors' );
	}

	/**
	 * Test TES mapping validation with valid array
	 */
	public function test_validate_tes_mapping_valid() {
		$mapping = array(
			'SP'      => '501',
			'RJ'      => '502',
			'default' => '502',
		);

		$errors = $this->mapper->validate_mapping( 'tes', $mapping );

		$this->assertEmpty( $errors, 'Valid TES mapping should have no errors' );
	}

	/**
	 * Test status mapping validation with valid array
	 */
	public function test_validate_status_mapping_valid() {
		$mapping = array(
			'pending'  => 'pending',
			'approved' => 'processing',
		);

		$errors = $this->mapper->validate_mapping( 'status', $mapping );

		$this->assertEmpty( $errors, 'Valid status mapping should have no errors' );
	}

	/**
	 * Test validation with invalid mapping type
	 */
	public function test_validate_mapping_invalid_type() {
		$mapping = array( 'test' => 'value' );

		$errors = $this->mapper->validate_mapping( 'invalid_type', $mapping );

		$this->assertNotEmpty( $errors, 'Invalid mapping type should have errors' );
		$this->assertStringContainsString( 'Invalid mapping type', $errors[0] );
	}
}
