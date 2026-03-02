<?php
/**
 * Property-based tests for Customer_Sync
 *
 * Tests correctness properties related to customer synchronization.
 *
 * @package ABSLoja\ProtheusConnector\Tests\Property
 */

namespace ABSLoja\ProtheusConnector\Tests\Property;

use ABSLoja\ProtheusConnector\Modules\Customer_Sync;
use ABSLoja\ProtheusConnector\Modules\Mapping_Engine;
use ABSLoja\ProtheusConnector\Modules\Logger;
use ABSLoja\ProtheusConnector\Modules\Retry_Manager;
use ABSLoja\ProtheusConnector\API\Protheus_Client;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use WC_Order;

/**
 * Customer_Sync property-based tests
 */
class CustomerSyncPropertiesTest extends TestCase {

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
	 * @test
	 * Feature: absloja-protheus-connector, Property 8: Customer Existence Check
	 *
	 * For any order being synced to Protheus, the Customer_Sync should verify
	 * if the customer exists in Protheus by querying with CPF or CNPJ before proceeding.
	 *
	 * Validates: Requirements 2.1
	 */
	public function test_customer_existence_check_queries_protheus() {
		$iterations = 100;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				// Generate random order with CPF or CNPJ
				$order_data = $this->generate_random_order();
				$document = $order_data['document'];
				$clean_document = preg_replace( '/\D/', '', $document );

				// Track API calls
				$api_calls = [];

				$client = $this->createMock( Protheus_Client::class );
				$client->method( 'get' )
					->willReturnCallback( function( $endpoint, $params ) use ( &$api_calls, $clean_document ) {
						$api_calls[] = [
							'endpoint' => $endpoint,
							'params' => $params,
						];

						// Randomly return existing or non-existing customer
						if ( rand( 0, 1 ) === 1 ) {
							return [
								'success' => true,
								'data' => [
									'A1_COD' => sprintf( '%06d', rand( 1, 999999 ) ),
									'A1_LOJA' => '01',
								],
							];
						} else {
							return [
								'success' => true,
								'data' => [],
							];
						}
					});

				$mapper = $this->createMock( Mapping_Engine::class );
				$logger = $this->createMock( Logger::class );
				$retry_manager = $this->createMock( Retry_Manager::class );

				$customer_sync = new Customer_Sync( $client, $mapper, $logger, $retry_manager );

				// Call check_customer_exists
				$customer_sync->check_customer_exists( $clean_document );

				// Verify that API was called with correct endpoint and document
				$this->assertNotEmpty(
					$api_calls,
					"API should be called to check customer existence (iteration $i)"
				);

				$this->assertEquals(
					'api/v1/customers',
					$api_calls[0]['endpoint'],
					"Should query customers endpoint (iteration $i)"
				);

				$this->assertEquals(
					$clean_document,
					$api_calls[0]['params']['cgc'],
					"Should query with cleaned CPF/CNPJ (iteration $i)"
				);

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
	 * Feature: absloja-protheus-connector, Property 9: Customer Creation on Non-Existence
	 *
	 * For any order where the customer does not exist in Protheus, the Customer_Sync
	 * should create a new customer record in SA1 table before sending the order.
	 *
	 * Validates: Requirements 2.2
	 */
	public function test_customer_creation_when_not_exists() {
		$iterations = 100;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				// Generate random order
				$order_data = $this->generate_random_order();
				$order = $this->create_mock_order( $order_data );

				// Track API calls
				$get_called = false;
				$post_called = false;

				$client = $this->createMock( Protheus_Client::class );
				
				// Customer does not exist
				$client->method( 'get' )
					->willReturnCallback( function() use ( &$get_called ) {
						$get_called = true;
						return [
							'success' => true,
							'data' => [],
						];
					});

				// Customer creation succeeds
				$client->method( 'post' )
					->willReturnCallback( function() use ( &$post_called ) {
						$post_called = true;
						return [
							'success' => true,
							'data' => [
								'A1_COD' => sprintf( '%06d', rand( 1, 999999 ) ),
							],
						];
					});

				$mapper = $this->createMock( Mapping_Engine::class );
				$mapper->method( 'get_customer_mapping' )->willReturn([
					'A1_FILIAL' => '01',
					'A1_LOJA' => '01',
				]);

				$logger = $this->createMock( Logger::class );
				$retry_manager = $this->createMock( Retry_Manager::class );

				$customer_sync = new Customer_Sync( $client, $mapper, $logger, $retry_manager );

				// Call ensure_customer_exists
				$result = $customer_sync->ensure_customer_exists( $order );

				// Verify that both check and create were called
				$this->assertTrue(
					$get_called,
					"Should check if customer exists (iteration $i)"
				);

				$this->assertTrue(
					$post_called,
					"Should create customer when not exists (iteration $i)"
				);

				$this->assertNotNull(
					$result,
					"Should return customer code after creation (iteration $i)"
				);

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
	 * Feature: absloja-protheus-connector, Property 10: Customer Field Mapping
	 *
	 * For any customer being created in Protheus, all WooCommerce billing fields
	 * should be mapped to Protheus SA1 fields according to the configured mapping.
	 *
	 * Validates: Requirements 2.3
	 */
	public function test_customer_field_mapping_completeness() {
		$iterations = 100;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				// Generate random order
				$order_data = $this->generate_random_order();
				$order = $this->create_mock_order( $order_data );

				// Track payload sent to API
				$sent_payload = null;

				$client = $this->createMock( Protheus_Client::class );
				$client->method( 'get' )->willReturn([
					'success' => true,
					'data' => [],
				]);

				$client->method( 'post' )
					->willReturnCallback( function( $endpoint, $payload ) use ( &$sent_payload ) {
						$sent_payload = $payload;
						return [
							'success' => true,
							'data' => [ 'A1_COD' => '000001' ],
						];
					});

				$mapper = $this->createMock( Mapping_Engine::class );
				$mapper->method( 'get_customer_mapping' )->willReturn([
					'A1_FILIAL' => '01',
					'A1_LOJA' => '01',
				]);

				$logger = $this->createMock( Logger::class );
				$retry_manager = $this->createMock( Retry_Manager::class );

				$customer_sync = new Customer_Sync( $client, $mapper, $logger, $retry_manager );
				$customer_sync->ensure_customer_exists( $order );

				// Verify all required SA1 fields are present
				$required_fields = [
					'A1_FILIAL',
					'A1_LOJA',
					'A1_NOME',
					'A1_CGC',
					'A1_TIPO',
					'A1_END',
					'A1_MUN',
					'A1_EST',
					'A1_CEP',
					'A1_EMAIL',
				];

				foreach ( $required_fields as $field ) {
					$this->assertArrayHasKey(
						$field,
						$sent_payload,
						"Field $field should be present in payload (iteration $i)"
					);
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
	 * Feature: absloja-protheus-connector, Property 11: CPF/CNPJ Extraction and Cleaning
	 *
	 * For any customer data, the CPF or CNPJ should be extracted from the appropriate
	 * billing field, cleaned of formatting characters (dots, dashes), and mapped to A1_CGC.
	 *
	 * Validates: Requirements 2.4
	 */
	public function test_cpf_cnpj_extraction_and_cleaning() {
		$iterations = 100;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				// Generate random formatted CPF or CNPJ
				$is_cpf = rand( 0, 1 ) === 1;
				
				if ( $is_cpf ) {
					$clean_document = $this->generate_random_cpf();
					$formatted_document = $this->format_cpf( $clean_document );
				} else {
					$clean_document = $this->generate_random_cnpj();
					$formatted_document = $this->format_cnpj( $clean_document );
				}

				$client = $this->createMock( Protheus_Client::class );
				$mapper = $this->createMock( Mapping_Engine::class );
				$logger = $this->createMock( Logger::class );
				$retry_manager = $this->createMock( Retry_Manager::class );

				$customer_sync = new Customer_Sync( $client, $mapper, $logger, $retry_manager );

				// Clean the document
				$result = $customer_sync->clean_document( $formatted_document );

				// Verify cleaning removes all formatting
				$this->assertMatchesRegularExpression(
					'/^\d+$/',
					$result,
					"Cleaned document should contain only digits (iteration $i)"
				);

				$this->assertEquals(
					$clean_document,
					$result,
					"Cleaned document should match original unformatted document (iteration $i)"
				);

				// Verify length is correct
				$expected_length = $is_cpf ? 11 : 14;
				$this->assertEquals(
					$expected_length,
					strlen( $result ),
					"Cleaned document should have correct length (iteration $i)"
				);

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
	 * Feature: absloja-protheus-connector, Property 12: Name Concatenation
	 *
	 * For any customer being created, the A1_NOME field should contain the
	 * concatenation of billing_first_name and billing_last_name with a space separator.
	 *
	 * Validates: Requirements 2.5
	 */
	public function test_name_concatenation() {
		$iterations = 100;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				// Generate random names
				$first_name = $this->generate_random_name();
				$last_name = $this->generate_random_name();
				$expected_full_name = $first_name . ' ' . $last_name;

				$order_data = $this->generate_random_order();
				$order_data['first_name'] = $first_name;
				$order_data['last_name'] = $last_name;
				$order = $this->create_mock_order( $order_data );

				// Track payload
				$sent_payload = null;

				$client = $this->createMock( Protheus_Client::class );
				$client->method( 'get' )->willReturn([
					'success' => true,
					'data' => [],
				]);

				$client->method( 'post' )
					->willReturnCallback( function( $endpoint, $payload ) use ( &$sent_payload ) {
						$sent_payload = $payload;
						return [
							'success' => true,
							'data' => [ 'A1_COD' => '000001' ],
						];
					});

				$mapper = $this->createMock( Mapping_Engine::class );
				$mapper->method( 'get_customer_mapping' )->willReturn([
					'A1_FILIAL' => '01',
					'A1_LOJA' => '01',
				]);

				$logger = $this->createMock( Logger::class );
				$retry_manager = $this->createMock( Retry_Manager::class );

				$customer_sync = new Customer_Sync( $client, $mapper, $logger, $retry_manager );
				$customer_sync->ensure_customer_exists( $order );

				// Verify name concatenation
				$this->assertEquals(
					$expected_full_name,
					$sent_payload['A1_NOME'],
					"A1_NOME should be first_name + space + last_name (iteration $i)"
				);

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
	 * Feature: absloja-protheus-connector, Property 13: Order Sync Abortion on Customer Creation Failure
	 *
	 * For any customer creation that fails, the order sync operation should be aborted,
	 * no order should be sent to Protheus, and an error should be logged.
	 *
	 * Validates: Requirements 2.6
	 */
	public function test_order_sync_abortion_on_customer_creation_failure() {
		$iterations = 100;
		$failures = [];

		for ( $i = 0; $i < $iterations; $i++ ) {
			try {
				// Generate random order
				$order_data = $this->generate_random_order();
				$order = $this->create_mock_order( $order_data );

				// Track calls
				$retry_scheduled = false;
				$error_logged = false;

				$client = $this->createMock( Protheus_Client::class );
				
				// Customer does not exist
				$client->method( 'get' )->willReturn([
					'success' => true,
					'data' => [],
				]);

				// Customer creation fails
				$client->method( 'post' )->willReturn([
					'success' => false,
					'error' => 'Server error',
				]);

				$mapper = $this->createMock( Mapping_Engine::class );
				$mapper->method( 'get_customer_mapping' )->willReturn([
					'A1_FILIAL' => '01',
					'A1_LOJA' => '01',
				]);

				$logger = $this->createMock( Logger::class );
				$logger->method( 'log_error' )
					->willReturnCallback( function() use ( &$error_logged ) {
						$error_logged = true;
					});

				$retry_manager = $this->createMock( Retry_Manager::class );
				$retry_manager->method( 'schedule_retry' )
					->willReturnCallback( function() use ( &$retry_scheduled ) {
						$retry_scheduled = true;
					});

				$customer_sync = new Customer_Sync( $client, $mapper, $logger, $retry_manager );
				$result = $customer_sync->ensure_customer_exists( $order );

				// Verify operation is aborted (returns null)
				$this->assertNull(
					$result,
					"Should return null when customer creation fails (iteration $i)"
				);

				// Verify error is logged
				$this->assertTrue(
					$error_logged,
					"Should log error when customer creation fails (iteration $i)"
				);

				// Verify retry is scheduled
				$this->assertTrue(
					$retry_scheduled,
					"Should schedule retry when customer creation fails (iteration $i)"
				);

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
	 * Generate random order data
	 *
	 * @return array
	 */
	private function generate_random_order(): array {
		$is_cpf = rand( 0, 1 ) === 1;
		
		if ( $is_cpf ) {
			$document = $this->format_cpf( $this->generate_random_cpf() );
			$document_field = '_billing_cpf';
		} else {
			$document = $this->format_cnpj( $this->generate_random_cnpj() );
			$document_field = '_billing_cnpj';
		}

		return [
			'id' => rand( 1, 999999 ),
			'first_name' => $this->generate_random_name(),
			'last_name' => $this->generate_random_name(),
			'address_1' => $this->generate_random_address(),
			'city' => $this->generate_random_city(),
			'state' => $this->generate_random_state(),
			'postcode' => $this->generate_random_postcode(),
			'phone' => $this->generate_random_phone(),
			'email' => $this->generate_random_email(),
			'document' => $document,
			'document_field' => $document_field,
			'neighborhood' => $this->generate_random_neighborhood(),
		];
	}

	/**
	 * Create mock WC_Order from order data
	 *
	 * @param array $data Order data
	 * @return WC_Order
	 */
	private function create_mock_order( array $data ): WC_Order {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_id' )->willReturn( $data['id'] );
		$order->method( 'get_billing_first_name' )->willReturn( $data['first_name'] );
		$order->method( 'get_billing_last_name' )->willReturn( $data['last_name'] );
		$order->method( 'get_billing_address_1' )->willReturn( $data['address_1'] );
		$order->method( 'get_billing_city' )->willReturn( $data['city'] );
		$order->method( 'get_billing_state' )->willReturn( $data['state'] );
		$order->method( 'get_billing_postcode' )->willReturn( $data['postcode'] );
		$order->method( 'get_billing_phone' )->willReturn( $data['phone'] );
		$order->method( 'get_billing_email' )->willReturn( $data['email'] );
		$order->method( 'get_meta' )
			->willReturnCallback( function( $key ) use ( $data ) {
				if ( $key === $data['document_field'] ) {
					return $data['document'];
				}
				if ( $key === '_billing_neighborhood' ) {
					return $data['neighborhood'];
				}
				return '';
			});

		return $order;
	}

	/**
	 * Generate random CPF (11 digits)
	 *
	 * @return string
	 */
	private function generate_random_cpf(): string {
		return sprintf( '%011d', rand( 10000000000, 99999999999 ) );
	}

	/**
	 * Generate random CNPJ (14 digits)
	 *
	 * @return string
	 */
	private function generate_random_cnpj(): string {
		return sprintf( '%014d', rand( 10000000000000, 99999999999999 ) );
	}

	/**
	 * Format CPF with dots and dash
	 *
	 * @param string $cpf Unformatted CPF
	 * @return string
	 */
	private function format_cpf( string $cpf ): string {
		return substr( $cpf, 0, 3 ) . '.' .
		       substr( $cpf, 3, 3 ) . '.' .
		       substr( $cpf, 6, 3 ) . '-' .
		       substr( $cpf, 9, 2 );
	}

	/**
	 * Format CNPJ with dots, slash and dash
	 *
	 * @param string $cnpj Unformatted CNPJ
	 * @return string
	 */
	private function format_cnpj( string $cnpj ): string {
		return substr( $cnpj, 0, 2 ) . '.' .
		       substr( $cnpj, 2, 3 ) . '.' .
		       substr( $cnpj, 5, 3 ) . '/' .
		       substr( $cnpj, 8, 4 ) . '-' .
		       substr( $cnpj, 12, 2 );
	}

	/**
	 * Generate random name
	 *
	 * @return string
	 */
	private function generate_random_name(): string {
		$names = [
			'João', 'Maria', 'José', 'Ana', 'Pedro', 'Paula', 'Carlos', 'Fernanda',
			'Ricardo', 'Juliana', 'Marcos', 'Beatriz', 'Lucas', 'Camila', 'Rafael',
			'Silva', 'Santos', 'Oliveira', 'Souza', 'Costa', 'Ferreira', 'Rodrigues',
			'Almeida', 'Nascimento', 'Lima', 'Araújo', 'Fernandes', 'Carvalho',
		];

		return $names[ array_rand( $names ) ];
	}

	/**
	 * Generate random address
	 *
	 * @return string
	 */
	private function generate_random_address(): string {
		$streets = [ 'Rua', 'Avenida', 'Travessa', 'Alameda' ];
		$names = [ 'das Flores', 'Principal', 'Central', 'do Comércio', 'Paulista' ];
		
		return $streets[ array_rand( $streets ) ] . ' ' .
		       $names[ array_rand( $names ) ] . ', ' .
		       rand( 1, 9999 );
	}

	/**
	 * Generate random city
	 *
	 * @return string
	 */
	private function generate_random_city(): string {
		$cities = [
			'São Paulo', 'Rio de Janeiro', 'Belo Horizonte', 'Curitiba',
			'Porto Alegre', 'Salvador', 'Brasília', 'Fortaleza', 'Recife',
		];

		return $cities[ array_rand( $cities ) ];
	}

	/**
	 * Generate random state
	 *
	 * @return string
	 */
	private function generate_random_state(): string {
		$states = [ 'SP', 'RJ', 'MG', 'PR', 'RS', 'BA', 'DF', 'CE', 'PE' ];
		return $states[ array_rand( $states ) ];
	}

	/**
	 * Generate random postcode
	 *
	 * @return string
	 */
	private function generate_random_postcode(): string {
		return sprintf( '%05d-%03d', rand( 10000, 99999 ), rand( 0, 999 ) );
	}

	/**
	 * Generate random phone
	 *
	 * @return string
	 */
	private function generate_random_phone(): string {
		return sprintf(
			'(%02d) %s-%04d',
			rand( 11, 99 ),
			rand( 0, 1 ) === 1 ? sprintf( '%05d', rand( 90000, 99999 ) ) : sprintf( '%04d', rand( 9000, 9999 ) ),
			rand( 0, 9999 )
		);
	}

	/**
	 * Generate random email
	 *
	 * @return string
	 */
	private function generate_random_email(): string {
		$names = [ 'joao', 'maria', 'jose', 'ana', 'pedro', 'paula', 'carlos' ];
		$domains = [ 'example.com', 'test.com', 'email.com', 'mail.com' ];
		
		return $names[ array_rand( $names ) ] . rand( 1, 999 ) . '@' . $domains[ array_rand( $domains ) ];
	}

	/**
	 * Generate random neighborhood
	 *
	 * @return string
	 */
	private function generate_random_neighborhood(): string {
		$neighborhoods = [
			'Centro', 'Jardim América', 'Vila Nova', 'Bela Vista',
			'Boa Vista', 'Jardim Paulista', 'Mooca', 'Ipanema',
		];

		return $neighborhoods[ array_rand( $neighborhoods ) ];
	}
}
