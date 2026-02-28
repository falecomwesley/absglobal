<?php
/**
 * Manual validation test script
 * 
 * This script demonstrates the validate_mapping() functionality
 * Run this from command line: php tests/manual-validation-test.php
 * 
 * @package ABSLoja\ProtheusConnector\Tests
 */

// Simulate WordPress environment
define( 'ABSPATH', dirname( __DIR__ ) . '/' );

// Mock WordPress functions
function get_option( $option, $default = false ) {
	return $default;
}

function add_option( $option, $value ) {
	return true;
}

function update_option( $option, $value ) {
	return true;
}

// Load the Mapping_Engine class
require_once dirname( __DIR__ ) . '/includes/modules/class-mapping-engine.php';

use ABSLoja\ProtheusConnector\Modules\Mapping_Engine;

echo "=== Mapping Engine Validation Tests ===\n\n";

$mapper = new Mapping_Engine();

// Test 1: Valid customer mapping
echo "Test 1: Valid customer mapping\n";
$valid_customer = array(
	'A1_FILIAL' => '01',
	'A1_NOME'   => 'billing_first_name + billing_last_name',
	'A1_CGC'    => 'billing_cpf',
	'A1_END'    => 'billing_address_1',
	'A1_MUN'    => 'billing_city',
	'A1_EST'    => 'billing_state',
);
$errors = $mapper->validate_mapping( 'customer', $valid_customer );
echo "Errors: " . ( empty( $errors ) ? "None (PASS)" : implode( ', ', $errors ) ) . "\n\n";

// Test 2: Invalid customer mapping (missing fields)
echo "Test 2: Invalid customer mapping (missing required fields)\n";
$invalid_customer = array(
	'A1_FILIAL' => '01',
	'A1_NOME'   => 'billing_first_name',
	// Missing A1_CGC, A1_END, A1_MUN, A1_EST
);
$errors = $mapper->validate_mapping( 'customer', $invalid_customer );
echo "Errors: " . ( ! empty( $errors ) ? implode( ', ', $errors ) . " (PASS)" : "None (FAIL)" ) . "\n\n";

// Test 3: Valid order mapping
echo "Test 3: Valid order mapping\n";
$valid_order = array(
	'SC5' => array(
		'C5_FILIAL' => '01',
		'C5_PEDWOO' => 'order_id',
	),
	'SC6' => array(
		'C6_PRODUTO' => 'product_sku',
		'C6_QTDVEN'  => 'quantity',
	),
);
$errors = $mapper->validate_mapping( 'order', $valid_order );
echo "Errors: " . ( empty( $errors ) ? "None (PASS)" : implode( ', ', $errors ) ) . "\n\n";

// Test 4: Invalid order mapping (missing SC6)
echo "Test 4: Invalid order mapping (missing SC6)\n";
$invalid_order = array(
	'SC5' => array(
		'C5_FILIAL' => '01',
	),
);
$errors = $mapper->validate_mapping( 'order', $invalid_order );
echo "Errors: " . ( ! empty( $errors ) ? implode( ', ', $errors ) . " (PASS)" : "None (FAIL)" ) . "\n\n";

// Test 5: Valid product mapping
echo "Test 5: Valid product mapping\n";
$valid_product = array(
	'sku'           => 'B1_COD',
	'name'          => 'B1_DESC',
	'regular_price' => 'B1_PRV1',
);
$errors = $mapper->validate_mapping( 'product', $valid_product );
echo "Errors: " . ( empty( $errors ) ? "None (PASS)" : implode( ', ', $errors ) ) . "\n\n";

// Test 6: Invalid product mapping (missing fields)
echo "Test 6: Invalid product mapping (missing required fields)\n";
$invalid_product = array(
	'sku' => 'B1_COD',
	// Missing name and regular_price
);
$errors = $mapper->validate_mapping( 'product', $invalid_product );
echo "Errors: " . ( ! empty( $errors ) ? implode( ', ', $errors ) . " (PASS)" : "None (FAIL)" ) . "\n\n";

// Test 7: Valid payment mapping
echo "Test 7: Valid payment mapping\n";
$valid_payment = array(
	'bacs' => '001',
	'pix'  => '005',
);
$errors = $mapper->validate_mapping( 'payment', $valid_payment );
echo "Errors: " . ( empty( $errors ) ? "None (PASS)" : implode( ', ', $errors ) ) . "\n\n";

// Test 8: Invalid payment mapping (not an array)
echo "Test 8: Invalid payment mapping (not an array)\n";
$invalid_payment = 'not_an_array';
$errors = $mapper->validate_mapping( 'payment', $invalid_payment );
echo "Errors: " . ( ! empty( $errors ) ? implode( ', ', $errors ) . " (PASS)" : "None (FAIL)" ) . "\n\n";

// Test 9: Valid TES mapping
echo "Test 9: Valid TES mapping\n";
$valid_tes = array(
	'SP'      => '501',
	'RJ'      => '502',
	'default' => '502',
);
$errors = $mapper->validate_mapping( 'tes', $valid_tes );
echo "Errors: " . ( empty( $errors ) ? "None (PASS)" : implode( ', ', $errors ) ) . "\n\n";

// Test 10: Invalid mapping type
echo "Test 10: Invalid mapping type\n";
$mapping = array( 'test' => 'value' );
$errors = $mapper->validate_mapping( 'invalid_type', $mapping );
echo "Errors: " . ( ! empty( $errors ) ? implode( ', ', $errors ) . " (PASS)" : "None (FAIL)" ) . "\n\n";

echo "=== All Tests Completed ===\n";
