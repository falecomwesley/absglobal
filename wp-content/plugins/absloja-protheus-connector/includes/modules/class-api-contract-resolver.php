<?php
/**
 * API Contract Resolver
 *
 * Resolves API endpoints and query conventions for different Protheus/TOTVS contracts.
 *
 * @package ABSLoja\ProtheusConnector\Modules
 * @since 1.0.0
 */

namespace ABSLoja\ProtheusConnector\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Api_Contract_Resolver
 *
 * Provides a single place to map endpoint names and pagination/query behaviors
 * according to selected integration profile.
 *
 * @since 1.0.0
 */
class Api_Contract_Resolver {

	/**
	 * WordPress option prefix.
	 *
	 * @var string
	 */
	private const OPTION_PREFIX = 'absloja_protheus_';

	/**
	 * TOTVS ecommerce endpoint profile.
	 * Order/customer write endpoints are kept configurable by override because
	 * each Protheus installation may expose specific custom routes.
	 *
	 * @var array<string,string>
	 */
	private const TOTVS_ECOMMERCE_ENDPOINTS = array(
		'orders_create'   => 'api/ecommerce/v1/retailSalesOrders',
		'orders_status'   => 'api/ecommerce/v1/orderChangeStatus',
		'orders_cancel'   => 'api/ecommerce/v1/orderChangeStatus',
		'orders_refund'   => 'api/ecommerce/v1/orderChangeStatus',
		'customers'       => 'api/v1/customers',
		'products'        => 'api/ecommerce/v1/retailItem',
		'product_by_sku'  => 'api/ecommerce/v1/retailItem/{sku}',
		'stock'           => 'api/ecommerce/v1/stock-product',
		'health'          => 'api/v1/health',
	);

	/**
	 * Get active integration profile.
	 *
	 * @return string
	 */
	public function get_profile(): string {
		$profile = get_option( self::OPTION_PREFIX . 'contract_profile', 'totvs_ecommerce_v1' );
		$valid   = array( 'totvs_ecommerce_v1', 'custom' );

		return in_array( $profile, $valid, true ) ? $profile : 'totvs_ecommerce_v1';
	}

	/**
	 * Resolve endpoint for a logical key.
	 *
	 * @param string               $key          Endpoint key.
	 * @param array<string,string> $replacements Placeholder replacements.
	 * @return string
	 */
	public function endpoint( string $key, array $replacements = array() ): string {
		$custom_value = trim( (string) get_option( self::OPTION_PREFIX . 'endpoint_' . $key, '' ) );
		$endpoint     = $custom_value;

		if ( '' === $endpoint ) {
			$endpoint_map = $this->get_default_endpoint_map();
			$endpoint     = $endpoint_map[ $key ] ?? '';
		}

		if ( ! empty( $replacements ) ) {
			foreach ( $replacements as $placeholder => $value ) {
				$endpoint = str_replace( '{' . $placeholder . '}', $value, $endpoint );
			}
		}

		return ltrim( $endpoint, '/' );
	}

	/**
	 * Build pagination params for current profile.
	 *
	 * @param int $page       Page number.
	 * @param int $batch_size Page size/batch size.
	 * @return array<string,mixed>
	 */
	public function pagination_params( int $page, int $batch_size ): array {
		$params  = array();

		$params['page']     = $page;
		$params['pageSize'] = $batch_size;

		return $this->add_context_query_params( $params );
	}

	/**
	 * Add context query params when configured.
	 *
	 * @param array<string,mixed> $params Existing params.
	 * @return array<string,mixed>
	 */
	public function add_context_query_params( array $params ): array {
		$company_param = trim( (string) get_option( self::OPTION_PREFIX . 'company_param', '' ) );
		$company_value = trim( (string) get_option( self::OPTION_PREFIX . 'company_value', '' ) );
		$branch_param  = trim( (string) get_option( self::OPTION_PREFIX . 'branch_param', '' ) );
		$branch_value  = trim( (string) get_option( self::OPTION_PREFIX . 'branch_value', '' ) );

		if ( '' !== $company_param && '' !== $company_value ) {
			$params[ $company_param ] = $company_value;
		}

		if ( '' !== $branch_param && '' !== $branch_value ) {
			$params[ $branch_param ] = $branch_value;
		}

		return $params;
	}

	/**
	 * Get query parameter name used to lookup customer by document.
	 *
	 * @return string
	 */
	public function customer_document_param(): string {
		$param = trim( (string) get_option( self::OPTION_PREFIX . 'customer_document_param', 'cgc' ) );
		return '' !== $param ? $param : 'cgc';
	}

	/**
	 * Determine if API response indicates a next page.
	 *
	 * @param mixed $data       Parsed response data.
	 * @param int   $count      Current item count.
	 * @param int   $batch_size Requested batch size.
	 * @return bool
	 */
	public function has_next_page( $data, int $count, int $batch_size ): bool {
		if ( is_array( $data ) ) {
			if ( isset( $data['hasNext'] ) ) {
				return (bool) $data['hasNext'];
			}
			if ( isset( $data['has_next'] ) ) {
				return (bool) $data['has_next'];
			}
		}

		return $count === $batch_size;
	}

	/**
	 * Get endpoint map for active profile.
	 *
	 * @return array<string,string>
	 */
	private function get_default_endpoint_map(): array {
		return self::TOTVS_ECOMMERCE_ENDPOINTS;
	}
}
