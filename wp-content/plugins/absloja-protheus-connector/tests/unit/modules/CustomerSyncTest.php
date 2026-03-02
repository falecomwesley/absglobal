<?php
/**
 * Customer Sync Unit Tests
 *
 * Comprehensive unit tests for Customer_Sync module (Task 7.4).
 * Tests customer existence verification, creation, document cleaning,
 * name concatenation, and customer type determination.
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
 * Class CustomerSyncTest
 *
 * Comprehensive unit tests for Customer_Sync module.
 *
 * @since 1.0.0
 */
class CustomerSyncTest extends TestCase {

	/**
	 * Set up test environment
	 */
	protected function setUp(): void {
		parent::setUp();
	}

	/**
	 * Tear down test environment
	 */
	protected function tearDown(): void {
		parent::tearDown();
	}

	/**
	 * Test customer existence check returns customer code when customer exists
	 */
	public function test_check_customer_exists_returns_code_when_found() {
		$client = $this->createMock( Protheus_Client::class );
		$client->method( 'get' )
			->with( 'api/v1/customers', [ 'cgc' => '12345678900' ] )
			->willReturn([
				'success' => true,
				'data' => [
					'A1_COD' => '000123',
					'A1_LOJA' => '01',
					'A1_NOME' => 'João Silva',
				],
			]);

		$mapper = $this->createMock( Mapping_Engine::class );
		$logger = $this->createMock( Logger::class );
		$retry_manager = $this->createMock( Retry_Manager::class );

		$customer_sync = new Customer_Sync( $client, $mapper, $logger, $retry_manager );
		$result = $customer_sync->check_customer_exists( '12345678900' );

		$this->assertEquals( '000123', $result );
	}

	/**
	 * Test customer existence check returns null when customer not found
	 */
	public function test_check_customer_exists_returns_null_when_not_found() {
		$client = $this->createMock( Protheus_Client::class );
		$client->method( 'get' )
			->with( 'api/v1/customers', [ 'cgc' => '12345678900' ] )
			->willReturn([
				'success' => true,
				'data' => [],
			]);

		$mapper = $this->createMock( Mapping_Engine::class );
		$logger = $this->createMock( Logger::class );
		$retry_manager = $this->createMock( Retry_Manager::class );

		$customer_sync = new Customer_Sync( $client, $mapper, $logger, $retry_manager );
		$result = $customer_sync->check_customer_exists( '12345678900' );

		$this->assertNull( $result );
	}

	/**
	 * Test customer existence check handles array response format
	 */
	public function test_check_customer_exists_handles_array_response() {
		$client = $this->createMock( Protheus_Client::class );
		$client->method( 'get' )
			->with( 'api/v1/customers', [ 'cgc' => '12345678900' ] )
			->willReturn([
				'success' => true,
				'data' => [
					[
						'A1_COD' => '000456',
						'A1_LOJA' => '01',
					],
				],
			]);

		$mapper = $this->createMock( Mapping_Engine::class );
		$logger = $this->createMock( Logger::class );
		$retry_manager = $this->createMock( Retry_Manager::class );

		$customer_sync = new Customer_Sync( $client, $mapper, $logger, $retry_manager );
		$result = $customer_sync->check_customer_exists( '12345678900' );

		$this->assertEquals( '000456', $result );
	}

	/**
	 * Test customer existence check returns null on API failure
	 */
	public function test_check_customer_exists_returns_null_on_api_failure() {
		$client = $this->createMock( Protheus_Client::class );
		$client->method( 'get' )
			->willReturn([
				'success' => false,
				'error' => 'Connection timeout',
			]);

		$mapper = $this->createMock( Mapping_Engine::class );
		$logger = $this->createMock( Logger::class );
		$retry_manager = $this->createMock( Retry_Manager::class );

		$customer_sync = new Customer_Sync( $client, $mapper, $logger, $retry_manager );
		$result = $customer_sync->check_customer_exists( '12345678900' );

		$this->assertNull( $result );
	}

	/**
	 * Test create customer successfully creates and returns customer code
	 */
	public function test_create_customer_returns_code_on_success() {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_id' )->willReturn( 123 );
		$order->method( 'get_billing_first_name' )->willReturn( 'Maria' );
		$order->method( 'get_billing_last_name' )->willReturn( 'Santos' );
		$order->method( 'get_billing_address_1' )->willReturn( 'Av. Paulista, 1000' );
		$order->method( 'get_billing_city' )->willReturn( 'São Paulo' );
		$order->method( 'get_billing_state' )->willReturn( 'SP' );
		$order->method( 'get_billing_postcode' )->willReturn( '01310-100' );
		$order->method( 'get_billing_phone' )->willReturn( '(11) 99999-8888' );
		$order->method( 'get_billing_email' )->willReturn( 'maria@example.com' );
		$order->method( 'get_meta' )
			->willReturnCallback( function( $key ) {
				if ( $key === '_billing_cpf' ) {
					return '987.654.321-00';
				}
				if ( $key === '_billing_neighborhood' ) {
					return 'Bela Vista';
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
			->willReturn([
				'success' => true,
				'data' => [
					'A1_COD' => '000789',
				],
			]);

		$customer_sync = new Customer_Sync( $client, $mapper, $logger, $retry_manager );
		$result = $customer_sync->create_customer( $order );

		$this->assertEquals( '000789', $result );
	}

	/**
	 * Test create customer returns null on API failure
	 */
	public function test_create_customer_returns_null_on_api_failure() {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_id' )->willReturn( 123 );
		$order->method( 'get_billing_first_name' )->willReturn( 'Pedro' );
		$order->method( 'get_billing_last_name' )->willReturn( 'Oliveira' );
		$order->method( 'get_billing_address_1' )->willReturn( 'Rua A, 100' );
		$order->method( 'get_billing_city' )->willReturn( 'Rio de Janeiro' );
		$order->method( 'get_billing_state' )->willReturn( 'RJ' );
		$order->method( 'get_billing_postcode' )->willReturn( '20000-000' );
		$order->method( 'get_billing_phone' )->willReturn( '(21) 98888-7777' );
		$order->method( 'get_billing_email' )->willReturn( 'pedro@example.com' );
		$order->method( 'get_meta' )
			->willReturnCallback( function( $key ) {
				if ( $key === '_billing_cpf' ) {
					return '111.222.333-44';
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
			->willReturn([
				'success' => false,
				'error' => 'Invalid data',
			]);

		$customer_sync = new Customer_Sync( $client, $mapper, $logger, $retry_manager );
		$result = $customer_sync->create_customer( $order );

		$this->assertNull( $result );
	}

	/**
	 * Test clean_document removes all formatting from CPF
	 */
	public function test_clean_document_removes_cpf_formatting() {
		$client = $this->createMock( Protheus_Client::class );
		$mapper = $this->createMock( Mapping_Engine::class );
		$logger = $this->createMock( Logger::class );
		$retry_manager = $this->createMock( Retry_Manager::class );

		$customer_sync = new Customer_Sync( $client, $mapper, $logger, $retry_manager );

		$formatted_cpf = '123.456.789-00';
		$clean_cpf = $customer_sync->clean_document( $formatted_cpf );

		$this->assertEquals( '12345678900', $clean_cpf );
		$this->assertEquals( 11, strlen( $clean_cpf ) );
	}

	/**
	 * Test clean_document removes all formatting from CNPJ
	 */
	public function test_clean_document_removes_cnpj_formatting() {
		$client = $this->createMock( Protheus_Client::class );
		$mapper = $this->createMock( Mapping_Engine::class );
		$logger = $this->createMock( Logger::class );
		$retry_manager = $this->createMock( Retry_Manager::class );

		$customer_sync = new Customer_Sync( $client, $mapper, $logger, $retry_manager );

		$formatted_cnpj = '12.345.678/0001-90';
		$clean_cnpj = $customer_sync->clean_document( $formatted_cnpj );

		$this->assertEquals( '12345678000190', $clean_cnpj );
		$this->assertEquals( 14, strlen( $clean_cnpj ) );
	}

	/**
	 * Test clean_document handles already clean documents
	 */
	public function test_clean_document_handles_already_clean_document() {
		$client = $this->createMock( Protheus_Client::class );
		$mapper = $this->createMock( Mapping_Engine::class );
		$logger = $this->createMock( Logger::class );
		$retry_manager = $this->createMock( Retry_Manager::class );

		$customer_sync = new Customer_Sync( $client, $mapper, $logger, $retry_manager );

		$clean_cpf = '12345678900';
		$result = $customer_sync->clean_document( $clean_cpf );

		$this->assertEquals( '12345678900', $result );
	}

	/**
	 * Test clean_document removes various special characters
	 */
	public function test_clean_document_removes_various_special_characters() {
		$client = $this->createMock( Protheus_Client::class );
		$mapper = $this->createMock( Mapping_Engine::class );
		$logger = $this->createMock( Logger::class );
		$retry_manager = $this->createMock( Retry_Manager::class );

		$customer_sync = new Customer_Sync( $client, $mapper, $logger, $retry_manager );

		$document_with_spaces = '123 456 789 00';
		$this->assertEquals( '12345678900', $customer_sync->clean_document( $document_with_spaces ) );

		$document_with_mixed = '12.345.678/0001-90 ';
		$this->assertEquals( '12345678000190', $customer_sync->clean_document( $document_with_mixed ) );
	}

	/**
	 * Test name concatenation with space separator
	 */
	public function test_name_concatenation_with_space() {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_id' )->willReturn( 123 );
		$order->method( 'get_billing_first_name' )->willReturn( 'Ana' );
		$order->method( 'get_billing_last_name' )->willReturn( 'Costa' );
		$order->method( 'get_billing_address_1' )->willReturn( 'Rua B, 200' );
		$order->method( 'get_billing_city' )->willReturn( 'Curitiba' );
		$order->method( 'get_billing_state' )->willReturn( 'PR' );
		$order->method( 'get_billing_postcode' )->willReturn( '80000-000' );
		$order->method( 'get_billing_phone' )->willReturn( '(41) 97777-6666' );
		$order->method( 'get_billing_email' )->willReturn( 'ana@example.com' );
		$order->method( 'get_meta' )
			->willReturnCallback( function( $key ) {
				if ( $key === '_billing_cpf' ) {
					return '555.666.777-88';
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
				$this->assertEquals( 'Ana Costa', $payload['A1_NOME'] );
				return [
					'success' => true,
					'data' => [ 'A1_COD' => '000999' ],
				];
			});

		$customer_sync = new Customer_Sync( $client, $mapper, $logger, $retry_manager );
		$customer_sync->create_customer( $order );
	}

	/**
	 * Test customer type determination for CPF (11 digits = F)
	 */
	public function test_customer_type_determination_cpf_returns_f() {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_id' )->willReturn( 123 );
		$order->method( 'get_billing_first_name' )->willReturn( 'Carlos' );
		$order->method( 'get_billing_last_name' )->willReturn( 'Ferreira' );
		$order->method( 'get_billing_address_1' )->willReturn( 'Rua C, 300' );
		$order->method( 'get_billing_city' )->willReturn( 'Belo Horizonte' );
		$order->method( 'get_billing_state' )->willReturn( 'MG' );
		$order->method( 'get_billing_postcode' )->willReturn( '30000-000' );
		$order->method( 'get_billing_phone' )->willReturn( '(31) 96666-5555' );
		$order->method( 'get_billing_email' )->willReturn( 'carlos@example.com' );
		$order->method( 'get_meta' )
			->willReturnCallback( function( $key ) {
				if ( $key === '_billing_cpf' ) {
					return '999.888.777-66';
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
				$this->assertEquals( 'F', $payload['A1_TIPO'] );
				$this->assertEquals( 11, strlen( $payload['A1_CGC'] ) );
				return [
					'success' => true,
					'data' => [ 'A1_COD' => '001000' ],
				];
			});

		$customer_sync = new Customer_Sync( $client, $mapper, $logger, $retry_manager );
		$customer_sync->create_customer( $order );
	}

	/**
	 * Test customer type determination for CNPJ (14 digits = J)
	 */
	public function test_customer_type_determination_cnpj_returns_j() {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_id' )->willReturn( 456 );
		$order->method( 'get_billing_first_name' )->willReturn( 'Empresa' );
		$order->method( 'get_billing_last_name' )->willReturn( 'LTDA' );
		$order->method( 'get_billing_address_1' )->willReturn( 'Av. Comercial, 500' );
		$order->method( 'get_billing_city' )->willReturn( 'Porto Alegre' );
		$order->method( 'get_billing_state' )->willReturn( 'RS' );
		$order->method( 'get_billing_postcode' )->willReturn( '90000-000' );
		$order->method( 'get_billing_phone' )->willReturn( '(51) 95555-4444' );
		$order->method( 'get_billing_email' )->willReturn( 'contato@empresa.com' );
		$order->method( 'get_meta' )
			->willReturnCallback( function( $key ) {
				if ( $key === '_billing_cnpj' ) {
					return '11.222.333/0001-44';
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
				$this->assertEquals( 'J', $payload['A1_TIPO'] );
				$this->assertEquals( 14, strlen( $payload['A1_CGC'] ) );
				return [
					'success' => true,
					'data' => [ 'A1_COD' => '001001' ],
				];
			});

		$customer_sync = new Customer_Sync( $client, $mapper, $logger, $retry_manager );
		$customer_sync->create_customer( $order );
	}

	/**
	 * Test ensure_customer_exists returns existing customer code
	 */
	public function test_ensure_customer_exists_returns_existing_code() {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_id' )->willReturn( 789 );
		$order->method( 'get_meta' )
			->willReturnCallback( function( $key ) {
				if ( $key === '_billing_cpf' ) {
					return '123.456.789-00';
				}
				return '';
			});

		$client = $this->createMock( Protheus_Client::class );
		$client->method( 'get' )
			->willReturn([
				'success' => true,
				'data' => [
					'A1_COD' => '000555',
				],
			]);

		$mapper = $this->createMock( Mapping_Engine::class );
		$logger = $this->createMock( Logger::class );
		$retry_manager = $this->createMock( Retry_Manager::class );

		$customer_sync = new Customer_Sync( $client, $mapper, $logger, $retry_manager );
		$result = $customer_sync->ensure_customer_exists( $order );

		$this->assertEquals( '000555', $result );
	}

	/**
	 * Test ensure_customer_exists creates new customer when not found
	 */
	public function test_ensure_customer_exists_creates_new_customer() {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_id' )->willReturn( 890 );
		$order->method( 'get_billing_first_name' )->willReturn( 'Novo' );
		$order->method( 'get_billing_last_name' )->willReturn( 'Cliente' );
		$order->method( 'get_billing_address_1' )->willReturn( 'Rua Nova, 100' );
		$order->method( 'get_billing_city' )->willReturn( 'Salvador' );
		$order->method( 'get_billing_state' )->willReturn( 'BA' );
		$order->method( 'get_billing_postcode' )->willReturn( '40000-000' );
		$order->method( 'get_billing_phone' )->willReturn( '(71) 94444-3333' );
		$order->method( 'get_billing_email' )->willReturn( 'novo@example.com' );
		$order->method( 'get_meta' )
			->willReturnCallback( function( $key ) {
				if ( $key === '_billing_cpf' ) {
					return '777.888.999-00';
				}
				return '';
			});

		$client = $this->createMock( Protheus_Client::class );
		$client->method( 'get' )
			->willReturn([
				'success' => true,
				'data' => [],
			]);
		$client->method( 'post' )
			->willReturn([
				'success' => true,
				'data' => [
					'A1_COD' => '001234',
				],
			]);

		$mapper = $this->createMock( Mapping_Engine::class );
		$mapper->method( 'get_customer_mapping' )->willReturn([
			'A1_FILIAL' => '01',
			'A1_LOJA' => '01',
		]);

		$logger = $this->createMock( Logger::class );
		$retry_manager = $this->createMock( Retry_Manager::class );

		$customer_sync = new Customer_Sync( $client, $mapper, $logger, $retry_manager );
		$result = $customer_sync->ensure_customer_exists( $order );

		$this->assertEquals( '001234', $result );
	}

	/**
	 * Test ensure_customer_exists returns null when CPF/CNPJ not found
	 */
	public function test_ensure_customer_exists_returns_null_when_no_document() {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_id' )->willReturn( 999 );
		$order->method( 'get_meta' )->willReturn( '' );

		$client = $this->createMock( Protheus_Client::class );
		$mapper = $this->createMock( Mapping_Engine::class );
		$logger = $this->createMock( Logger::class );
		$retry_manager = $this->createMock( Retry_Manager::class );

		$customer_sync = new Customer_Sync( $client, $mapper, $logger, $retry_manager );
		$result = $customer_sync->ensure_customer_exists( $order );

		$this->assertNull( $result );
	}

	/**
	 * Test ensure_customer_exists schedules retry on creation failure
	 */
	public function test_ensure_customer_exists_schedules_retry_on_failure() {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_id' )->willReturn( 1000 );
		$order->method( 'get_billing_first_name' )->willReturn( 'Teste' );
		$order->method( 'get_billing_last_name' )->willReturn( 'Falha' );
		$order->method( 'get_billing_address_1' )->willReturn( 'Rua Teste' );
		$order->method( 'get_billing_city' )->willReturn( 'Recife' );
		$order->method( 'get_billing_state' )->willReturn( 'PE' );
		$order->method( 'get_billing_postcode' )->willReturn( '50000-000' );
		$order->method( 'get_billing_phone' )->willReturn( '(81) 93333-2222' );
		$order->method( 'get_billing_email' )->willReturn( 'teste@example.com' );
		$order->method( 'get_meta' )
			->willReturnCallback( function( $key ) {
				if ( $key === '_billing_cpf' ) {
					return '444.555.666-77';
				}
				return '';
			});

		$client = $this->createMock( Protheus_Client::class );
		$client->method( 'get' )
			->willReturn([
				'success' => true,
				'data' => [],
			]);
		$client->method( 'post' )
			->willReturn([
				'success' => false,
				'error' => 'Server error',
			]);

		$mapper = $this->createMock( Mapping_Engine::class );
		$mapper->method( 'get_customer_mapping' )->willReturn([
			'A1_FILIAL' => '01',
			'A1_LOJA' => '01',
		]);

		$logger = $this->createMock( Logger::class );
		
		$retry_manager = $this->createMock( Retry_Manager::class );
		$retry_manager->expects( $this->once() )
			->method( 'schedule_retry' )
			->with(
				'customer_sync',
				$this->callback( function( $data ) {
					return $data['order_id'] === 1000 && $data['document'] === '44455566677';
				}),
				'Failed to create customer in Protheus'
			);

		$customer_sync = new Customer_Sync( $client, $mapper, $logger, $retry_manager );
		$result = $customer_sync->ensure_customer_exists( $order );

		$this->assertNull( $result );
	}
}
