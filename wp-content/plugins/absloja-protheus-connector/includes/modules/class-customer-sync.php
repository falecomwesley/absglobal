<?php
/**
 * Customer Sync Module
 *
 * Manages customer verification and creation in Protheus before order sync.
 *
 * @package ABSLoja\ProtheusConnector\Modules
 * @since 1.0.0
 */

namespace ABSLoja\ProtheusConnector\Modules;

use ABSLoja\ProtheusConnector\API\Protheus_Client;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Customer_Sync
 *
 * Handles customer verification and creation in Protheus.
 * Ensures customers exist in Protheus SA1 table before order sync.
 *
 * @since 1.0.0
 */
class Customer_Sync {

	/**
	 * Protheus API client
	 *
	 * @var Protheus_Client
	 */
	private $client;

	/**
	 * Mapping Engine instance
	 *
	 * @var Mapping_Engine
	 */
	private $mapper;

	/**
	 * Logger instance
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Retry Manager instance
	 *
	 * @var Retry_Manager
	 */
	private $retry_manager;

	/**
	 * API contract resolver.
	 *
	 * @var Api_Contract_Resolver
	 */
	private $contract;

	/**
	 * Constructor
	 *
	 * @param Protheus_Client $client        Protheus API client instance.
	 * @param Mapping_Engine  $mapper        Mapping engine instance.
	 * @param Logger          $logger        Logger instance.
	 * @param Retry_Manager   $retry_manager Retry manager instance.
	 */
	public function __construct( Protheus_Client $client, Mapping_Engine $mapper, Logger $logger, Retry_Manager $retry_manager ) {
		$this->client        = $client;
		$this->mapper        = $mapper;
		$this->logger        = $logger;
		$this->retry_manager = $retry_manager;
		$this->contract      = new Api_Contract_Resolver();
	}

	/**
	 * Ensure customer exists in Protheus
	 *
	 * Main method that checks if customer exists in Protheus.
	 * If customer exists, returns customer code.
	 * If customer doesn't exist, creates customer and returns code.
	 * If creation fails, returns null.
	 *
	 * @param \WC_Order $order WooCommerce order object.
	 * @return string|null Customer code on success, null on failure.
	 */
	public function ensure_customer_exists( \WC_Order $order ): ?string {
		// Extract CPF/CNPJ from order billing fields
		$cpf_cnpj = $this->extract_document( $order );

		if ( empty( $cpf_cnpj ) ) {
			$this->logger->log_sync_operation(
				'customer_sync',
				array(
					'order_id' => $order->get_id(),
					'error'    => 'CPF/CNPJ not found in order',
				),
				false,
				'CPF/CNPJ not found in order billing fields'
			);
			return null;
		}

		// Clean document (remove formatting)
		$clean_document = $this->clean_document( $cpf_cnpj );

		// Check if customer exists in Protheus
		$customer_code = $this->check_customer_exists( $clean_document );

		if ( ! empty( $customer_code ) ) {
			// Customer exists, return code
			$this->logger->log_sync_operation(
				'customer_sync',
				array(
					'order_id'      => $order->get_id(),
					'customer_code' => $customer_code,
					'document'      => $clean_document,
					'action'        => 'found_existing',
				),
				true
			);
			return $customer_code;
		}

		// Customer doesn't exist, create new customer
		$customer_code = $this->create_customer( $order );

		if ( empty( $customer_code ) ) {
			// Creation failed - log error and schedule retry
			$error_message = 'Failed to create customer in Protheus';
			
			$this->logger->log_sync_operation(
				'customer_sync',
				array(
					'order_id' => $order->get_id(),
					'document' => $clean_document,
					'action'   => 'create_failed',
				),
				false,
				$error_message
			);

			// Schedule retry via Retry_Manager
			$this->retry_manager->schedule_retry(
				'customer_sync',
				array(
					'order_id' => $order->get_id(),
					'document' => $clean_document,
				),
				$error_message
			);

			return null;
		}

		// Customer created successfully
		$this->logger->log_sync_operation(
			'customer_sync',
			array(
				'order_id'      => $order->get_id(),
				'customer_code' => $customer_code,
				'document'      => $clean_document,
				'action'        => 'created_new',
			),
			true
		);

		return $customer_code;
	}

	/**
	 * Check if customer exists in Protheus
	 *
	 * Queries Protheus API to check if customer exists by CPF/CNPJ.
	 *
	 * @param string $cpf_cnpj Cleaned CPF or CNPJ (digits only).
	 * @return string|null Customer code if exists, null otherwise.
	 */
	public function check_customer_exists( string $cpf_cnpj ): ?string {
		$start_time = microtime( true );

		// Query Protheus API for customer by CGC (CPF/CNPJ)
		$query_param = $this->contract->customer_document_param();
		$query       = array( $query_param => $cpf_cnpj );
		$query       = $this->contract->add_context_query_params( $query );
		$endpoint    = $this->contract->endpoint( 'customers' );

		$response = $this->client->get( $endpoint, $query );

		$duration = microtime( true ) - $start_time;

		// Log API request
		$this->logger->log_api_request(
			'GET /' . $endpoint,
			$query,
			$response,
			$duration
		);

		if ( ! $response['success'] ) {
			// Log API failure but continue to create customer
			$this->logger->log_error(
				'Customer existence check API call failed - will attempt to create customer',
				new \Exception( $response['error'] ?? 'Unknown API error' ),
				array(
					'document' => $cpf_cnpj,
					'error'    => $response['error'] ?? 'Unknown error',
					'code'     => $response['code'] ?? null,
				)
			);
			return null;
		}

		// Check if customer data is present in response
		$data = $response['data'];

		if ( empty( $data ) || ! is_array( $data ) ) {
			return null;
		}

		// Extract customer code from response
		// Assuming response structure: { "A1_COD": "000001", "A1_LOJA": "01", ... }
		if ( isset( $data['A1_COD'] ) && ! empty( $data['A1_COD'] ) ) {
			return $data['A1_COD'];
		}

		// Alternative: response might be an array of customers
		if ( isset( $data[0]['A1_COD'] ) && ! empty( $data[0]['A1_COD'] ) ) {
			return $data[0]['A1_COD'];
		}

		return null;
	}

	/**
	 * Create customer in Protheus
	 *
	 * Creates new customer record in Protheus SA1 table.
	 *
	 * @param \WC_Order $order WooCommerce order object.
	 * @return string|null Customer code on success, null on failure.
	 */
	public function create_customer( \WC_Order $order ): ?string {
		// Get customer mapping configuration
		$mapping = $this->mapper->get_customer_mapping();

		// Extract billing data from order
		$billing_first_name = $order->get_billing_first_name();
		$billing_last_name  = $order->get_billing_last_name();
		$billing_address_1  = $order->get_billing_address_1();
		$billing_city       = $order->get_billing_city();
		$billing_state      = $order->get_billing_state();
		$billing_postcode   = $order->get_billing_postcode();
		$billing_phone      = $order->get_billing_phone();
		$billing_email      = $order->get_billing_email();

		// Extract CPF/CNPJ
		$cpf_cnpj = $this->extract_document( $order );
		$clean_document = $this->clean_document( $cpf_cnpj );

		// Determine customer type (F=Física/CPF, J=Jurídica/CNPJ)
		$customer_type = strlen( $clean_document ) === 11 ? 'F' : 'J';

		// Get neighborhood from custom field (common in Brazilian WooCommerce)
		$billing_neighborhood = $order->get_meta( '_billing_neighborhood', true );
		if ( empty( $billing_neighborhood ) ) {
			$billing_neighborhood = $order->get_meta( '_billing_bairro', true );
		}

		// Clean phone number (extract DDD and phone)
		$clean_phone = preg_replace( '/\D/', '', $billing_phone );
		$ddd = strlen( $clean_phone ) >= 2 ? substr( $clean_phone, 0, 2 ) : '';
		$phone = strlen( $clean_phone ) > 2 ? substr( $clean_phone, 2 ) : $clean_phone;

		// Clean postcode
		$clean_postcode = preg_replace( '/\D/', '', $billing_postcode );

		// Build customer payload for Protheus SA1 table
		$payload = array(
			'A1_FILIAL'  => $mapping['A1_FILIAL'] ?? '01',
			'A1_COD'     => '', // Generated by Protheus
			'A1_LOJA'    => $mapping['A1_LOJA'] ?? '01',
			'A1_NOME'    => $billing_first_name . ' ' . $billing_last_name,
			'A1_NREDUZ'  => substr( $billing_first_name, 0, 20 ),
			'A1_CGC'     => $clean_document,
			'A1_TIPO'    => $customer_type,
			'A1_END'     => $billing_address_1,
			'A1_BAIRRO'  => $billing_neighborhood,
			'A1_MUN'     => $billing_city,
			'A1_EST'     => $billing_state,
			'A1_CEP'     => $clean_postcode,
			'A1_DDD'     => $ddd,
			'A1_TEL'     => $phone,
			'A1_EMAIL'   => $billing_email,
		);

		$start_time = microtime( true );

		// Send POST request to create customer
		$endpoint = $this->contract->endpoint( 'customers' );
		$response = $this->client->post( $endpoint, $payload );

		$duration = microtime( true ) - $start_time;

		// Log API request
		$this->logger->log_api_request(
			'POST /' . $endpoint,
			$payload,
			$response,
			$duration
		);

		if ( ! $response['success'] ) {
			// Log detailed error information
			$error_details = array(
				'order_id' => $order->get_id(),
				'document' => $clean_document,
				'error'    => $response['error'] ?? 'Unknown error',
				'code'     => $response['code'] ?? null,
			);

			$this->logger->log_error(
				'Customer creation API call failed',
				new \Exception( $response['error'] ?? 'Unknown API error' ),
				$error_details
			);

			return null;
		}

		// Extract customer code from response
		$data = $response['data'];

		if ( isset( $data['A1_COD'] ) && ! empty( $data['A1_COD'] ) ) {
			return $data['A1_COD'];
		}

		// Alternative response structures
		if ( isset( $data['customer_code'] ) ) {
			return $data['customer_code'];
		}

		if ( isset( $data['code'] ) ) {
			return $data['code'];
		}

		return null;
	}

	/**
	 * Extract document (CPF or CNPJ) from order
	 *
	 * Tries to extract CPF or CNPJ from order billing meta fields.
	 *
	 * @param \WC_Order $order WooCommerce order object.
	 * @return string|null Document number or null if not found.
	 */
	private function extract_document( \WC_Order $order ): ?string {
		// Try common CPF field names
		$cpf = $order->get_meta( '_billing_cpf', true );
		if ( ! empty( $cpf ) ) {
			return $cpf;
		}

		// Try common CNPJ field names
		$cnpj = $order->get_meta( '_billing_cnpj', true );
		if ( ! empty( $cnpj ) ) {
			return $cnpj;
		}

		// Try alternative field names
		$document = $order->get_meta( '_billing_document', true );
		if ( ! empty( $document ) ) {
			return $document;
		}

		$document = $order->get_meta( '_billing_persontype', true );
		if ( ! empty( $document ) ) {
			return $document;
		}

		return null;
	}

	/**
	 * Clean document number
	 *
	 * Removes formatting characters (dots, dashes, slashes) from CPF/CNPJ.
	 *
	 * @param string $document Document number with formatting.
	 * @return string Cleaned document (digits only).
	 */
	public function clean_document( string $document ): string {
		// Remove all non-digit characters
		return preg_replace( '/\D/', '', $document );
	}
}
