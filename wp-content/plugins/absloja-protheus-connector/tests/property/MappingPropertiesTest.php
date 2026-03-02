<?php
/**
 * Property-based tests for Mapping_Engine
 *
 * Tests correctness properties related to mapping validation.
 *
 * @package ABSLoja\ProtheusConnector\Tests\Property
 */

namespace ABSLoja\ProtheusConnector\Tests\Property;

use ABSLoja\ProtheusConnector\Modules\Mapping_Engine;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

/**
 * Mapping_Engine property-based tests
 */
class MappingPropertiesTest extends TestCase {

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
		Mockery::close();
		parent::tearDown();
	}

	/**
	 * @test
	 * Feature: absloja-protheus-connector, Property 46: Mapping Validation
	 *
	 * For any mapping configuration submitted, the Mapping_Engine should validate
	 * the configuration and return errors for invalid mappings.
	 *
	 * **Validates: Requirements 10.8**
	 */
	public function test_mapping_validation_returns_errors_for_invalid_mappings() {
		$iterations = 100;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				$mapper = new Mapping_Engine();

				// Test all mapping types
				$mapping_type = $this->generate_random_mapping_type();
				
				// Generate invalid mapping (missing required fields)
				$invalid_mapping = $this->generate_invalid_mapping( $mapping_type );

				$errors = $mapper->validate_mapping( $mapping_type, $invalid_mapping );

				// Property: Invalid mappings should return non-empty error array
				$this->assertIsArray( $errors, "validate_mapping should return an array (iteration $i, type: $mapping_type)" );
				$this->assertNotEmpty( $errors, "Invalid mapping should return errors (iteration $i, type: $mapping_type)" );

				// Verify error messages are strings
				foreach ( $errors as $error ) {
					$this->assertIsString( $error, "Each error should be a string (iteration $i, type: $mapping_type)" );
					$this->assertNotEmpty( $error, "Error message should not be empty (iteration $i, type: $mapping_type)" );
				}

			} catch ( \Exception $e ) {
				$failures[] = "Iteration $i failed: " . $e->getMessage();
			}
		}

		if ( ! empty( $failures ) ) {
			$this->fail( "Property test failed:\n" . implode( "\n", $failures ) );
		}

		$this->assertTrue( true, "All $iterations iterations passed" );
	}

	/**
	 * @test
	 * Feature: absloja-protheus-connector, Property 46: Mapping Validation
	 *
	 * For any valid mapping configuration submitted, the Mapping_Engine should
	 * validate the configuration and return an empty error array.
	 *
	 * **Validates: Requirements 10.8**
	 */
	public function test_mapping_validation_returns_empty_array_for_valid_mappings() {
		$iterations = 100;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				$mapper = new Mapping_Engine();

				// Test all mapping types
				$mapping_type = $this->generate_random_mapping_type();
				
				// Generate valid mapping (all required fields present)
				$valid_mapping = $this->generate_valid_mapping( $mapping_type );

				$errors = $mapper->validate_mapping( $mapping_type, $valid_mapping );

				// Property: Valid mappings should return empty error array
				$this->assertIsArray( $errors, "validate_mapping should return an array (iteration $i, type: $mapping_type)" );
				$this->assertEmpty( $errors, "Valid mapping should return no errors (iteration $i, type: $mapping_type)" );

			} catch ( \Exception $e ) {
				$failures[] = "Iteration $i failed: " . $e->getMessage();
			}
		}

		if ( ! empty( $failures ) ) {
			$this->fail( "Property test failed:\n" . implode( "\n", $failures ) );
		}

		$this->assertTrue( true, "All $iterations iterations passed" );
	}

	/**
	 * @test
	 * Feature: absloja-protheus-connector, Property 46: Mapping Validation
	 *
	 * For any customer mapping missing required fields, the Mapping_Engine should
	 * return specific error messages identifying the missing fields.
	 *
	 * **Validates: Requirements 10.8**
	 */
	public function test_customer_mapping_validation_identifies_missing_required_fields() {
		$iterations = 100;
		$failures = [];

		$required_fields = [ 'A1_FILIAL', 'A1_NOME', 'A1_CGC', 'A1_END', 'A1_MUN', 'A1_EST' ];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				$mapper = new Mapping_Engine();

				// Generate customer mapping with random missing required fields
				$num_missing = rand( 1, count( $required_fields ) );
				$fields_to_include = array_rand( array_flip( $required_fields ), count( $required_fields ) - $num_missing );
				if ( ! is_array( $fields_to_include ) ) {
					$fields_to_include = [ $fields_to_include ];
				}

				$mapping = [];
				foreach ( $fields_to_include as $field ) {
					$mapping[ $field ] = $this->generate_random_field_value();
				}

				$errors = $mapper->validate_mapping( 'customer', $mapping );

				// Property: Should return errors for missing required fields
				$this->assertIsArray( $errors, "Should return array (iteration $i)" );
				$this->assertNotEmpty( $errors, "Should return errors for missing fields (iteration $i)" );
				$this->assertGreaterThanOrEqual( $num_missing, count( $errors ), "Should have at least $num_missing errors (iteration $i)" );

				// Verify error messages mention the missing fields
				$missing_fields = array_diff( $required_fields, $fields_to_include );
				foreach ( $missing_fields as $missing_field ) {
					$found_error = false;
					foreach ( $errors as $error ) {
						if ( strpos( $error, $missing_field ) !== false ) {
							$found_error = true;
							break;
						}
					}
					$this->assertTrue( $found_error, "Error should mention missing field $missing_field (iteration $i)" );
				}

			} catch ( \Exception $e ) {
				$failures[] = "Iteration $i failed: " . $e->getMessage();
			}
		}

		if ( ! empty( $failures ) ) {
			$this->fail( "Property test failed:\n" . implode( "\n", $failures ) );
		}

		$this->assertTrue( true, "All $iterations iterations passed" );
	}

	/**
	 * @test
	 * Feature: absloja-protheus-connector, Property 46: Mapping Validation
	 *
	 * For any order mapping missing SC5 or SC6 sections, the Mapping_Engine should
	 * return specific error messages identifying the missing sections.
	 *
	 * **Validates: Requirements 10.8**
	 */
	public function test_order_mapping_validation_requires_sc5_and_sc6_sections() {
		$iterations = 100;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				$mapper = new Mapping_Engine();

				// Generate order mapping with randomly missing SC5 or SC6 or both
				$include_sc5 = (bool) rand( 0, 1 );
				$include_sc6 = (bool) rand( 0, 1 );

				// Ensure at least one is missing for this test
				if ( $include_sc5 && $include_sc6 ) {
					if ( rand( 0, 1 ) ) {
						$include_sc5 = false;
					} else {
						$include_sc6 = false;
					}
				}

				$mapping = [];
				if ( $include_sc5 ) {
					$mapping['SC5'] = $this->generate_random_sc5_mapping();
				}
				if ( $include_sc6 ) {
					$mapping['SC6'] = $this->generate_random_sc6_mapping();
				}

				$errors = $mapper->validate_mapping( 'order', $mapping );

				// Property: Should return errors for missing SC5 or SC6
				$this->assertIsArray( $errors, "Should return array (iteration $i)" );
				$this->assertNotEmpty( $errors, "Should return errors for missing sections (iteration $i)" );

				// Verify error messages mention the missing sections
				if ( ! $include_sc5 ) {
					$found_sc5_error = false;
					foreach ( $errors as $error ) {
						if ( stripos( $error, 'SC5' ) !== false ) {
							$found_sc5_error = true;
							break;
						}
					}
					$this->assertTrue( $found_sc5_error, "Error should mention missing SC5 (iteration $i)" );
				}

				if ( ! $include_sc6 ) {
					$found_sc6_error = false;
					foreach ( $errors as $error ) {
						if ( stripos( $error, 'SC6' ) !== false ) {
							$found_sc6_error = true;
							break;
						}
					}
					$this->assertTrue( $found_sc6_error, "Error should mention missing SC6 (iteration $i)" );
				}

			} catch ( \Exception $e ) {
				$failures[] = "Iteration $i failed: " . $e->getMessage();
			}
		}

		if ( ! empty( $failures ) ) {
			$this->fail( "Property test failed:\n" . implode( "\n", $failures ) );
		}

		$this->assertTrue( true, "All $iterations iterations passed" );
	}

	/**
	 * @test
	 * Feature: absloja-protheus-connector, Property 46: Mapping Validation
	 *
	 * For any product mapping missing required fields, the Mapping_Engine should
	 * return specific error messages identifying the missing fields.
	 *
	 * **Validates: Requirements 10.8**
	 */
	public function test_product_mapping_validation_identifies_missing_required_fields() {
		$iterations = 100;
		$failures = [];

		$required_fields = [ 'sku', 'name', 'regular_price' ];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				$mapper = new Mapping_Engine();

				// Generate product mapping with random missing required fields
				$num_missing = rand( 1, count( $required_fields ) );
				$fields_to_include = array_rand( array_flip( $required_fields ), count( $required_fields ) - $num_missing );
				if ( ! is_array( $fields_to_include ) ) {
					$fields_to_include = [ $fields_to_include ];
				}

				$mapping = [];
				foreach ( $fields_to_include as $field ) {
					$mapping[ $field ] = $this->generate_random_field_value();
				}

				$errors = $mapper->validate_mapping( 'product', $mapping );

				// Property: Should return errors for missing required fields
				$this->assertIsArray( $errors, "Should return array (iteration $i)" );
				$this->assertNotEmpty( $errors, "Should return errors for missing fields (iteration $i)" );
				$this->assertGreaterThanOrEqual( $num_missing, count( $errors ), "Should have at least $num_missing errors (iteration $i)" );

				// Verify error messages mention the missing fields
				$missing_fields = array_diff( $required_fields, $fields_to_include );
				foreach ( $missing_fields as $missing_field ) {
					$found_error = false;
					foreach ( $errors as $error ) {
						if ( strpos( $error, $missing_field ) !== false ) {
							$found_error = true;
							break;
						}
					}
					$this->assertTrue( $found_error, "Error should mention missing field $missing_field (iteration $i)" );
				}

			} catch ( \Exception $e ) {
				$failures[] = "Iteration $i failed: " . $e->getMessage();
			}
		}

		if ( ! empty( $failures ) ) {
			$this->fail( "Property test failed:\n" . implode( "\n", $failures ) );
		}

		$this->assertTrue( true, "All $iterations iterations passed" );
	}

	/**
	 * @test
	 * Feature: absloja-protheus-connector, Property 46: Mapping Validation
	 *
	 * For any invalid mapping type, the Mapping_Engine should return an error
	 * indicating the mapping type is invalid.
	 *
	 * **Validates: Requirements 10.8**
	 */
	public function test_mapping_validation_rejects_invalid_mapping_types() {
		$iterations = 100;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				$mapper = new Mapping_Engine();

				// Generate random invalid mapping type
				$invalid_type = $this->generate_invalid_mapping_type();
				$mapping = $this->generate_random_array();

				$errors = $mapper->validate_mapping( $invalid_type, $mapping );

				// Property: Should return error for invalid mapping type
				$this->assertIsArray( $errors, "Should return array (iteration $i)" );
				$this->assertNotEmpty( $errors, "Should return errors for invalid type (iteration $i)" );

				// Verify error message mentions invalid type
				$found_type_error = false;
				foreach ( $errors as $error ) {
					if ( stripos( $error, 'invalid' ) !== false && stripos( $error, 'type' ) !== false ) {
						$found_type_error = true;
						break;
					}
				}
				$this->assertTrue( $found_type_error, "Error should mention invalid mapping type (iteration $i)" );

			} catch ( \Exception $e ) {
				$failures[] = "Iteration $i failed: " . $e->getMessage();
			}
		}

		if ( ! empty( $failures ) ) {
			$this->fail( "Property test failed:\n" . implode( "\n", $failures ) );
		}

		$this->assertTrue( true, "All $iterations iterations passed" );
	}

	/**
	 * @test
	 * Feature: absloja-protheus-connector, Property 46: Mapping Validation
	 *
	 * For any simple mapping types (payment, category, tes, status) that are not arrays,
	 * the Mapping_Engine should return an error indicating the mapping must be an array.
	 *
	 * **Validates: Requirements 10.8**
	 */
	public function test_simple_mapping_validation_requires_array_type() {
		$iterations = 100;
		$failures = [];

		$simple_types = [ 'payment', 'category', 'tes', 'status' ];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				$mapper = new Mapping_Engine();

				// Pick random simple mapping type
				$mapping_type = $simple_types[ array_rand( $simple_types ) ];

				// Generate non-array value
				$non_array_value = $this->generate_non_array_value();

				$errors = $mapper->validate_mapping( $mapping_type, $non_array_value );

				// Property: Should return error for non-array mapping
				$this->assertIsArray( $errors, "Should return array (iteration $i, type: $mapping_type)" );
				$this->assertNotEmpty( $errors, "Should return errors for non-array mapping (iteration $i, type: $mapping_type)" );

				// Verify error message mentions array requirement
				$found_array_error = false;
				foreach ( $errors as $error ) {
					if ( stripos( $error, 'array' ) !== false ) {
						$found_array_error = true;
						break;
					}
				}
				$this->assertTrue( $found_array_error, "Error should mention array requirement (iteration $i, type: $mapping_type)" );

			} catch ( \Exception $e ) {
				$failures[] = "Iteration $i failed: " . $e->getMessage();
			}
		}

		if ( ! empty( $failures ) ) {
			$this->fail( "Property test failed:\n" . implode( "\n", $failures ) );
		}

		$this->assertTrue( true, "All $iterations iterations passed" );
	}

	// ========== Helper Methods for Generating Random Test Data ==========

	/**
	 * Generate random mapping type
	 *
	 * @return string
	 */
	private function generate_random_mapping_type(): string {
		$types = [ 'customer', 'order', 'product', 'payment', 'category', 'tes', 'status' ];
		return $types[ array_rand( $types ) ];
	}

	/**
	 * Generate invalid mapping type
	 *
	 * @return string
	 */
	private function generate_invalid_mapping_type(): string {
		$invalid_types = [
			'invalid',
			'unknown',
			'test',
			'random_' . rand( 1, 1000 ),
			'',
			'123',
			'customer_invalid',
			'order_test',
		];
		return $invalid_types[ array_rand( $invalid_types ) ];
	}

	/**
	 * Generate invalid mapping for given type
	 *
	 * @param string $type Mapping type
	 * @return array
	 */
	private function generate_invalid_mapping( string $type ): array {
		switch ( $type ) {
			case 'customer':
				// Missing required fields
				$possible_fields = [ 'A1_FILIAL', 'A1_NOME', 'A1_CGC', 'A1_END', 'A1_MUN', 'A1_EST' ];
				$num_fields = rand( 0, count( $possible_fields ) - 1 );
				$fields = array_rand( array_flip( $possible_fields ), $num_fields );
				if ( ! is_array( $fields ) ) {
					$fields = [ $fields ];
				}
				$mapping = [];
				foreach ( $fields as $field ) {
					$mapping[ $field ] = $this->generate_random_field_value();
				}
				return $mapping;

			case 'order':
				// Missing SC5 or SC6 or both
				$include_sc5 = (bool) rand( 0, 1 );
				$include_sc6 = (bool) rand( 0, 1 );
				if ( $include_sc5 && $include_sc6 ) {
					// Make sure at least one is missing
					if ( rand( 0, 1 ) ) {
						$include_sc5 = false;
					} else {
						$include_sc6 = false;
					}
				}
				$mapping = [];
				if ( $include_sc5 ) {
					$mapping['SC5'] = $this->generate_random_sc5_mapping();
				}
				if ( $include_sc6 ) {
					$mapping['SC6'] = $this->generate_random_sc6_mapping();
				}
				return $mapping;

			case 'product':
				// Missing required fields
				$possible_fields = [ 'sku', 'name', 'regular_price' ];
				$num_fields = rand( 0, count( $possible_fields ) - 1 );
				$fields = array_rand( array_flip( $possible_fields ), $num_fields );
				if ( ! is_array( $fields ) ) {
					$fields = [ $fields ];
				}
				$mapping = [];
				foreach ( $fields as $field ) {
					$mapping[ $field ] = $this->generate_random_field_value();
				}
				return $mapping;

			case 'payment':
			case 'category':
			case 'tes':
			case 'status':
				// Return empty array (valid structure but could be considered incomplete)
				return [];

			default:
				return [];
		}
	}

	/**
	 * Generate valid mapping for given type
	 *
	 * @param string $type Mapping type
	 * @return array
	 */
	private function generate_valid_mapping( string $type ): array {
		switch ( $type ) {
			case 'customer':
				return [
					'A1_FILIAL' => '0' . rand( 1, 9 ),
					'A1_NOME'   => $this->generate_random_field_value(),
					'A1_CGC'    => $this->generate_random_field_value(),
					'A1_END'    => $this->generate_random_field_value(),
					'A1_MUN'    => $this->generate_random_field_value(),
					'A1_EST'    => $this->generate_random_state(),
					'A1_CEP'    => $this->generate_random_field_value(),
					'A1_EMAIL'  => $this->generate_random_field_value(),
				];

			case 'order':
				return [
					'SC5' => $this->generate_random_sc5_mapping(),
					'SC6' => $this->generate_random_sc6_mapping(),
				];

			case 'product':
				return [
					'sku'           => $this->generate_random_field_value(),
					'name'          => $this->generate_random_field_value(),
					'regular_price' => $this->generate_random_field_value(),
					'description'   => $this->generate_random_field_value(),
					'weight'        => $this->generate_random_field_value(),
				];

			case 'payment':
				return [
					'bacs'        => '00' . rand( 1, 9 ),
					'credit_card' => '00' . rand( 1, 9 ),
					'pix'         => '00' . rand( 1, 9 ),
				];

			case 'category':
				return [
					'0' . rand( 1, 9 ) => rand( 10, 100 ),
					'0' . rand( 1, 9 ) => rand( 10, 100 ),
				];

			case 'tes':
				return [
					$this->generate_random_state() => '50' . rand( 1, 9 ),
					$this->generate_random_state() => '50' . rand( 1, 9 ),
					'default'                      => '502',
				];

			case 'status':
				return [
					'pending'   => 'pending',
					'approved'  => 'processing',
					'invoiced'  => 'completed',
					'cancelled' => 'cancelled',
				];

			default:
				return [];
		}
	}

	/**
	 * Generate random SC5 mapping
	 *
	 * @return array
	 */
	private function generate_random_sc5_mapping(): array {
		return [
			'C5_FILIAL'  => '0' . rand( 1, 9 ),
			'C5_PEDWOO'  => 'order_id',
			'C5_CLIENTE' => 'customer_code',
			'C5_CONDPAG' => 'payment_method',
		];
	}

	/**
	 * Generate random SC6 mapping
	 *
	 * @return array
	 */
	private function generate_random_sc6_mapping(): array {
		return [
			'C6_FILIAL'  => '0' . rand( 1, 9 ),
			'C6_PRODUTO' => 'product_sku',
			'C6_QTDVEN'  => 'quantity',
			'C6_PRCVEN'  => 'unit_price',
		];
	}

	/**
	 * Generate random field value
	 *
	 * @return string
	 */
	private function generate_random_field_value(): string {
		$values = [
			'billing_first_name',
			'billing_last_name',
			'billing_address_1',
			'billing_city',
			'billing_state',
			'billing_postcode',
			'billing_email',
			'billing_phone',
			'billing_cpf',
			'billing_cnpj',
			'order_id',
			'customer_code',
			'product_sku',
			'B1_COD',
			'B1_DESC',
			'B1_PRV1',
		];
		return $values[ array_rand( $values ) ];
	}

	/**
	 * Generate random Brazilian state code
	 *
	 * @return string
	 */
	private function generate_random_state(): string {
		$states = [
			'SP', 'RJ', 'MG', 'ES', 'PR', 'SC', 'RS',
			'BA', 'PE', 'CE', 'PA', 'MA', 'GO', 'MT',
			'MS', 'DF', 'RO', 'AC', 'AM', 'RR', 'AP',
			'TO', 'PI', 'RN', 'PB', 'AL', 'SE',
		];
		return $states[ array_rand( $states ) ];
	}

	/**
	 * Generate random array
	 *
	 * @return array
	 */
	private function generate_random_array(): array {
		$size = rand( 0, 5 );
		$array = [];
		for ( $i = 0; $i < $size; $i++ ) {
			$array[ 'key_' . $i ] = 'value_' . rand( 1, 100 );
		}
		return $array;
	}

	/**
	 * Generate non-array value
	 *
	 * @return mixed
	 */
	private function generate_non_array_value() {
		$types = [
			'string',
			'integer',
			'boolean',
			'null',
		];

		$type = $types[ array_rand( $types ) ];

		switch ( $type ) {
			case 'string':
				return 'not_an_array_' . rand( 1, 1000 );
			case 'integer':
				return rand( 1, 10000 );
			case 'boolean':
				return (bool) rand( 0, 1 );
			case 'null':
				return null;
			default:
				return 'string';
		}
	}
}
