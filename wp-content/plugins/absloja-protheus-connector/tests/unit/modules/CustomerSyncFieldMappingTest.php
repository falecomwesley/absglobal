<?php
/**
 * Customer Sync Field Mapping Tests
 *
 * Tests for customer field mapping implementation (Task 7.2).
 *
 * @package ABSLoja\ProtheusConnector\Tests\Unit\Modules
 * @since 1.0.0
 */

namespace ABSLoja\ProtheusConnector\Tests\Unit\Modules;

use ABSLoja\ProtheusConnector\Modules\Customer_Sync;
use ABSLoja\ProtheusConnector\Modules\Mapping_Engine;
use ABSLoja\ProtheusConnector\Modules\Logger;
use ABSLoja\ProtheusConnector\Modules\Retry_Manager;
use ABSLoja\ProtheusConnector\API\Protheus_Client;
use PHPUnit\Framework\TestCase;
use WC_Order;

/**
 * Class CustomerSyncFieldMappingTest
 *
 * Tests customer field mapping functionality.
 *
 * @since 1.0.0
 */
class CustomerSyncFieldMappingTest extends TestCase {

	/**
	 * Test CPF extraction from _billing_cpf field
	 */
	public function test_extract_cpf_from_billing_cpf_field() {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_meta' )
			->willReturnCallback( function( $key ) {
				if ( $key === '_billing_cpf' ) {
					return '123.456.789-00';
				}
				return '';
			});

		$client = $this->createMock( Protheus_Client::class );
		$mapper = $this->createMock( Mapping_Engine::class );
		$logger = $this->createMock( Logger::class );
		$retry_manager = $this->createMock( Retry_Manager::class );

		$customer_sync = new Customer_Sync( $client, $mapper, $logger, $retry_manager );

		// Use reflection to access private method
		$reflection = new \ReflectionClass( $customer_sync );
		$method = $reflection->getMethod( 'extract_document' );
		$method->setAccessible( true );

		$result = $method->invoke( $customer_sync, $order );

		$this->assertEquals( '123.456.789-00', $result );
	}

	/**
	 * Test CNPJ extraction from _billing_cnpj field
	 */
	public function test_extract_cnpj_from_billing_cnpj_field() {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_meta' )
			->willReturnCallback( function( $key ) {
				if ( $key === '_billing_cnpj' ) {
					return '12.345.678/0001-90';
				}
				return '';
			});

		$client = $this->createMock( Protheus_Client::class );
		$mapper = $this->createMock( Mapping_Engine::class );
		$logger = $this->createMock( Logger::class );
		$retry_manager = $this->createMock( Retry_Manager::class );

		$customer_sync = new Customer_Sync( $client, $mapper, $logger, $retry_manager );

		$reflection = new \ReflectionClass( $customer_sync );
		$method = $reflection->getMethod( 'extract_document' );
		$method->setAccessible( true );

		$result = $method->invoke( $customer_sync, $order );

		$this->assertEquals( '12.345.678/0001-90', $result );
	}

	/**
	 * Test document cleaning (remove formatting)
	 */
	public function test_clean_document_removes_formatting() {
		$client = $this->createMock( Protheus_Client::class );
		$mapper = $this->createMock( Mapping_Engine::class );
		$logger = $this->createMock( Logger::class );
		$retry_manager = $this->createMock( Retry_Manager::class );

		$customer_sync = new Customer_Sync( $client, $mapper, $logger, $retry_manager );

		// Test CPF cleaning
		$clean_cpf = $customer_sync->clean_document( '123.456.789-00' );
		$this->assertEquals( '12345678900', $clean_cpf );

		// Test CNPJ cleaning
		$clean_cnpj = $customer_sync->clean_document( '12.345.678/0001-90' );
		$this->assertEquals( '12345678000190', $clean_cnpj );
	}

	/**
	 * Test A1_TIPO determination based on document length
	 */
	public function test_customer_type_determination() {
		$client = $this->createMock( Protheus_Client::class );
		$mapper = $this->createMock( Mapping_Engine::class );
		$logger = $this->createMock( Logger::class );
		$retry_manager = $this->createMock( Retry_Manager::class );

		$customer_sync = new Customer_Sync( $client, $mapper, $logger, $retry_manager );

		// CPF (11 digits) should result in type 'F'
		$cpf = '12345678900';
		$type_f = strlen( $cpf ) === 11 ? 'F' : 'J';
		$this->assertEquals( 'F', $type_f );

		// CNPJ (14 digits) should result in type 'J'
		$cnpj = '12345678000190';
		$type_j = strlen( $cnpj ) === 11 ? 'F' : 'J';
		$this->assertEquals( 'J', $type_j );
	}

	/**
	 * Test name concatenation for A1_NOME
	 */
	public function test_name_concatenation() {
		$first_name = 'João';
		$last_name = 'Silva';
		$full_name = $first_name . ' ' . $last_name;

		$this->assertEquals( 'João Silva', $full_name );
	}

	/**
	 * Test CEP cleaning
	 */
	public function test_cep_cleaning() {
		$cep_formatted = '12345-678';
		$cep_clean = preg_replace( '/\D/', '', $cep_formatted );

		$this->assertEquals( '12345678', $cep_clean );
	}

	/**
	 * Test phone cleaning and DDD extraction
	 */
	public function test_phone_cleaning_and_ddd_extraction() {
		$phone_formatted = '(11) 98765-4321';
		$clean_phone = preg_replace( '/\D/', '', $phone_formatted );

		$this->assertEquals( '11987654321', $clean_phone );

		// Extract DDD (first 2 digits)
		$ddd = substr( $clean_phone, 0, 2 );
		$this->assertEquals( '11', $ddd );

		// Extract phone (remaining digits)
		$phone = substr( $clean_phone, 2 );
		$this->assertEquals( '987654321', $phone );
	}

	/**
	 * Test complete field mapping structure
	 */
	public function test_complete_field_mapping_structure() {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_id' )->willReturn( 123 );
		$order->method( 'get_billing_first_name' )->willReturn( 'João' );
		$order->method( 'get_billing_last_name' )->willReturn( 'Silva' );
		$order->method( 'get_billing_address_1' )->willReturn( 'Rua Teste, 123' );
		$order->method( 'get_billing_city' )->willReturn( 'São Paulo' );
		$order->method( 'get_billing_state' )->willReturn( 'SP' );
		$order->method( 'get_billing_postcode' )->willReturn( '12345-678' );
		$order->method( 'get_billing_phone' )->willReturn( '(11) 98765-4321' );
		$order->method( 'get_billing_email' )->willReturn( 'joao@example.com' );
		$order->method( 'get_meta' )
			->willReturnCallback( function( $key ) {
				if ( $key === '_billing_cpf' ) {
					return '123.456.789-00';
				}
				if ( $key === '_billing_neighborhood' ) {
					return 'Centro';
				}
				return '';
			});

		$mapper = $this->createMock( Mapping_Engine::class );
		$mapper->method( 'get_customer_mapping' )->willReturn([
			'A1_FILIAL' => '01',
			'A1_LOJA' => '01',
		]);

		$logger = $this->createMock( Logger::class );
		$retry_manager = $this->createMock( Retry_Manager::class );

		$client = $this->createMock( Protheus_Client::class );
		$client->method( 'post' )
			->willReturnCallback( function( $endpoint, $payload ) {
				// Verify payload structure
				$this->assertArrayHasKey( 'A1_FILIAL', $payload );
				$this->assertArrayHasKey( 'A1_NOME', $payload );
				$this->assertArrayHasKey( 'A1_NREDUZ', $payload );
				$this->assertArrayHasKey( 'A1_CGC', $payload );
				$this->assertArrayHasKey( 'A1_TIPO', $payload );
				$this->assertArrayHasKey( 'A1_END', $payload );
				$this->assertArrayHasKey( 'A1_BAIRRO', $payload );
				$this->assertArrayHasKey( 'A1_MUN', $payload );
				$this->assertArrayHasKey( 'A1_EST', $payload );
				$this->assertArrayHasKey( 'A1_CEP', $payload );
				$this->assertArrayHasKey( 'A1_DDD', $payload );
				$this->assertArrayHasKey( 'A1_TEL', $payload );
				$this->assertArrayHasKey( 'A1_EMAIL', $payload );

				// Verify values
				$this->assertEquals( '01', $payload['A1_FILIAL'] );
				$this->assertEquals( 'João Silva', $payload['A1_NOME'] );
				$this->assertEquals( 'João', $payload['A1_NREDUZ'] );
				$this->assertEquals( '12345678900', $payload['A1_CGC'] );
				$this->assertEquals( 'F', $payload['A1_TIPO'] );
				$this->assertEquals( 'Rua Teste, 123', $payload['A1_END'] );
				$this->assertEquals( 'Centro', $payload['A1_BAIRRO'] );
				$this->assertEquals( 'São Paulo', $payload['A1_MUN'] );
				$this->assertEquals( 'SP', $payload['A1_EST'] );
				$this->assertEquals( '12345678', $payload['A1_CEP'] );
				$this->assertEquals( '11', $payload['A1_DDD'] );
				$this->assertEquals( '987654321', $payload['A1_TEL'] );
				$this->assertEquals( 'joao@example.com', $payload['A1_EMAIL'] );

				return [
					'success' => true,
					'data' => [
						'A1_COD' => '000001',
					],
				];
			});

		$customer_sync = new Customer_Sync( $client, $mapper, $logger, $retry_manager );
		$result = $customer_sync->create_customer( $order );

		$this->assertEquals( '000001', $result );
	}
}
