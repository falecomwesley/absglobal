<?php
/**
 * Plugin Name: ABS Protheus Passive API
 * Description: Camada REST passiva para consumo pelo integrador do cliente/Protheus.
 * Version: 1.0.0
 * Author: ABS Loja
 * Text Domain: abs-protheus-passive-api
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ABS_Protheus_Passive_API {
	private const OPTION_KEY = 'abs_protheus_passive_api_key';
	private const OPTION_PASS = 'abs_protheus_passive_api_pass';
	private const NS = 'abs-protheus-passive/v1';

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_post_abs_protheus_passive_regenerate', array( $this, 'handle_regenerate_credentials' ) );
	}

	public static function activate() {
		self::ensure_credentials();
	}

	private static function ensure_credentials() {
		$key  = get_option( self::OPTION_KEY, '' );
		$pass = get_option( self::OPTION_PASS, '' );

		if ( empty( $key ) ) {
			update_option( self::OPTION_KEY, wp_generate_password( 24, false, false ) );
		}

		if ( empty( $pass ) ) {
			update_option( self::OPTION_PASS, wp_generate_password( 32, true, true ) );
		}
	}

	public function register_admin_page() {
		add_submenu_page(
			'woocommerce',
			'Protheus Passive API',
			'Protheus Passive API',
			'manage_woocommerce',
			'abs-protheus-passive-api',
			array( $this, 'render_admin_page' )
		);
	}

	public function render_admin_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'abs-protheus-passive-api' ) );
		}

		self::ensure_credentials();

		$key      = (string) get_option( self::OPTION_KEY, '' );
		$pass     = (string) get_option( self::OPTION_PASS, '' );
		$base_url = rtrim( get_site_url(), '/' ) . '/wp-json/' . self::NS;
		$query_auth = 'key=' . rawurlencode( $key ) . '&pass=' . rawurlencode( $pass );

			$endpoints = array(
				'Clientes' => array(
					array(
						'method'      => 'GET',
						'path'        => '/clients?page=1&limit=100',
						'title'       => 'Listar clientes',
						'description' => 'Retorna clientes com dados completos. Use updated_after para incremental.',
					),
					array(
						'method'      => 'GET',
						'path'        => '/clients/{id}',
						'title'       => 'Detalhe de cliente',
						'description' => 'Retorna cliente com dados completos de billing/shipping.',
						'path_real'   => '/clients/88',
					),
				),
				'Pedidos' => array(
					array(
						'method'      => 'GET',
						'path'        => '/orders?status=completed&page=1&limit=100',
						'title'       => 'Listar pedidos completos',
						'description' => 'Retorna pedidos completos para integração com Protheus.',
					),
					array(
						'method'      => 'GET',
						'path'        => '/orders/{id}',
						'title'       => 'Detalhe de pedido',
						'description' => 'Retorna um pedido específico com todos os campos.',
						'path_real'   => '/orders/1234',
					),
					array(
						'method'      => 'POST',
						'path'        => '/orders/status',
						'title'       => 'Atualizar status do pedido',
						'description' => 'Atualiza status no WooCommerce.',
						'payload'     => array(
							'woo_order_id' => 1234,
							'status'       => 'completed',
						),
					),
				),
				'Produtos' => array(
					array(
						'method'      => 'GET',
						'path'        => '/products?page=1&limit=100',
						'title'       => 'Listar produtos',
						'description' => 'Retorna catálogo completo. Pode filtrar incremental por updated_after.',
					),
					array(
						'method'      => 'GET',
						'path'        => '/products/{id}',
						'title'       => 'Detalhe de produto',
						'description' => 'Retorna produto completo para diagnóstico/validação.',
						'path_real'   => '/products/187',
					),
					array(
						'method'      => 'POST',
						'path'        => '/products/update',
						'title'       => 'Atualizar 1 produto (completo)',
						'description' => 'Atualiza todos os campos informados de um produto por SKU.',
						'payload'     => array(
							'sku'               => '605',
							'name'              => 'Produto Exemplo 1',
							'description'       => 'Descrição completa',
							'short_description' => 'Descrição curta',
							'weight'            => '0.85',
							'dimensions'        => array(
								'length' => '25',
								'width'  => '12',
								'height' => '8',
							),
							'regular_price'     => '79.90',
							'sale_price'        => '69.90',
							'stock_quantity'    => 15,
						),
					),
					array(
						'method'      => 'POST',
						'path'        => '/products/batch',
						'title'       => 'Atualizar/importar em massa',
						'description' => 'Atualiza um conjunto de produtos (3, 30 ou todos).',
						'payload'     => array(
							'create_if_missing' => true,
							'items'             => array(
								array(
									'sku'            => '605',
									'name'           => 'Produto Exemplo 1',
									'regular_price'  => '79.90',
									'stock_quantity' => 15,
								),
								array(
									'sku'            => '599',
									'price'          => '29.90',
									'stock_quantity' => 20,
								),
							),
						),
					),
				),
			);
		?>
			<div class="wrap">
				<style>
				.absppa-list { display:block; margin-top:16px; }
				.absppa-category-card { background:#fff; border:1px solid #d0d7de; border-radius:12px; overflow:hidden; box-shadow:0 1px 2px rgba(0,0,0,.04); margin-bottom:16px; }
				.absppa-category-head { padding:14px 16px; border-bottom:1px solid #eef2f5; background:#f8fafc; }
				.absppa-category-title { font-size:20px; font-weight:800; margin:0; }
				.absppa-endpoint { padding:14px 16px; border-bottom:1px solid #eef2f5; }
				.absppa-endpoint:last-child { border-bottom:none; }
				.absppa-endpoint-head { display:flex; gap:12px; align-items:center; justify-content:space-between; margin-bottom:8px; }
				.absppa-title { font-size:18px; font-weight:700; margin:0; }
				.absppa-method { font-weight:700; font-size:12px; padding:3px 10px; border-radius:999px; color:#fff; }
				.absppa-get { background:#0a4ea3; }
				.absppa-post { background:#188b4a; }
				.absppa-path { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size:15px; font-weight:600; color:#1f2937; margin:0 0 6px; }
				.absppa-desc { margin:0 0 10px; color:#4b5563; }
				.absppa-url { margin:0 0 10px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; color:#4b5563; white-space:pre-wrap; overflow-wrap:anywhere; word-break:break-word; }
				.absppa-actions { display:flex; gap:8px; margin-bottom:10px; }
				.absppa-code { background:#0f172a; color:#e5e7eb; border-radius:10px; padding:12px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size:13px; line-height:1.5; white-space:pre-wrap; overflow-wrap:anywhere; word-break:break-word; }
				.absppa-auth { background:#f8fafc; border:1px solid #d7dee5; border-radius:10px; padding:14px; margin-top:14px; }
				.absppa-auth code { background:#0f172a; color:#e5e7eb; padding:2px 6px; border-radius:6px; }
				</style>

			<h1>Protheus Passive API</h1>
			<p>Documentação de integração para o servidor do cliente (consumidor da API).</p>

			<div class="absppa-auth">
				<p><strong>API Key:</strong> <code><?php echo esc_html( $key ); ?></code></p>
				<p><strong>API Pass:</strong> <code><?php echo esc_html( $pass ); ?></code></p>
				<p><strong>Base URL:</strong> <code><?php echo esc_html( $base_url ); ?></code></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="abs_protheus_passive_regenerate" />
					<?php wp_nonce_field( 'abs_protheus_passive_regenerate' ); ?>
					<?php submit_button( 'Regenerar API Key/Pass', 'secondary', 'submit', false ); ?>
				</form>
			</div>

			<div class="absppa-list">
				<?php foreach ( $endpoints as $section_title => $rows ) : ?>
					<div class="absppa-category-card">
						<div class="absppa-category-head">
							<p class="absppa-category-title"><?php echo esc_html( $section_title ); ?></p>
						</div>
						<?php foreach ( $rows as $row ) : ?>
							<?php
							$path_for_url = isset( $row['path_real'] ) ? $row['path_real'] : $row['path'];
							$separator    = strpos( $path_for_url, '?' ) === false ? '?' : '&';
							$full_url     = $base_url . $path_for_url . $separator . $query_auth;
							$method       = strtoupper( $row['method'] );

							if ( 'GET' === $method ) {
								$curl_example = 'curl -X GET "' . $full_url . '"';
							} else {
								$payload      = isset( $row['payload'] ) ? $row['payload'] : array( 'sample' => 'value' );
								$payload_json = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES );
								$curl_example = 'curl -X POST "' . $base_url . $row['path'] . '?' . $query_auth . "\"\n"
									. '-H "Content-Type: application/json" ' . "\n"
									. "-d '" . $payload_json . "'";
							}
							?>
							<div class="absppa-endpoint">
								<div class="absppa-endpoint-head">
									<p class="absppa-title"><?php echo esc_html( $row['title'] ); ?></p>
									<span class="absppa-method <?php echo 'GET' === $method ? 'absppa-get' : 'absppa-post'; ?>"><?php echo esc_html( $method ); ?></span>
								</div>
								<p class="absppa-path"><?php echo esc_html( $row['path'] ); ?></p>
								<p class="absppa-desc"><?php echo esc_html( $row['description'] ); ?></p>
								<p class="absppa-url"><?php echo esc_html( $full_url ); ?></p>
								<div class="absppa-actions">
									<?php if ( 'GET' === $method ) : ?>
										<a class="button button-primary" target="_blank" href="<?php echo esc_url( $full_url ); ?>">Abrir endpoint</a>
									<?php else : ?>
										<span class="button disabled">Use cURL abaixo</span>
									<?php endif; ?>
								</div>
								<pre class="absppa-code"><code><?php echo esc_html( $curl_example ); ?></code></pre>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="absppa-auth">
				<h3>Autenticação</h3>
				<p>GET: query <code>?key=...&pass=...</code> ou headers <code>X-API-Key</code> e <code>X-API-Pass</code>.</p>
				<p>POST: JSON no body com <code>Content-Type: application/json</code>.</p>
			</div>
		</div>
		<?php
	}

		public function handle_regenerate_credentials() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'abs-protheus-passive-api' ) );
		}

		check_admin_referer( 'abs_protheus_passive_regenerate' );
		update_option( self::OPTION_KEY, wp_generate_password( 24, false, false ) );
		update_option( self::OPTION_PASS, wp_generate_password( 32, true, true ) );

		wp_safe_redirect( admin_url( 'admin.php?page=abs-protheus-passive-api' ) );
		exit;
	}

	public function register_routes() {
		register_rest_route(
			self::NS,
			'/clients',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_clients' ),
				'permission_callback' => array( $this, 'auth' ),
			)
		);

		register_rest_route(
			self::NS,
			'/clients/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_client' ),
				'permission_callback' => array( $this, 'auth' ),
			)
		);

		register_rest_route(
			self::NS,
			'/orders',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_orders' ),
				'permission_callback' => array( $this, 'auth' ),
			)
		);

		register_rest_route(
			self::NS,
			'/orders/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_order' ),
				'permission_callback' => array( $this, 'auth' ),
			)
		);

		register_rest_route(
			self::NS,
			'/orders/status',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'set_order_status' ),
				'permission_callback' => array( $this, 'auth' ),
			)
		);

		register_rest_route(
			self::NS,
			'/products',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_products' ),
				'permission_callback' => array( $this, 'auth' ),
			)
		);

		register_rest_route(
			self::NS,
			'/products/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_product' ),
				'permission_callback' => array( $this, 'auth' ),
			)
		);

		register_rest_route(
			self::NS,
			'/products/update',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'set_product_update' ),
				'permission_callback' => array( $this, 'auth' ),
			)
		);

		register_rest_route(
			self::NS,
			'/products/batch',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'set_products_batch' ),
				'permission_callback' => array( $this, 'auth' ),
			)
		);
	}

	public function auth( \WP_REST_Request $request ) {
		$stored_key  = (string) get_option( self::OPTION_KEY, '' );
		$stored_pass = (string) get_option( self::OPTION_PASS, '' );

		if ( empty( $stored_key ) || empty( $stored_pass ) ) {
			return false;
		}

		$key  = (string) $request->get_header( 'X-API-Key' );
		$pass = (string) $request->get_header( 'X-API-Pass' );

		if ( '' === $key ) {
			$key = (string) $request->get_param( 'key' );
		}
		if ( '' === $pass ) {
			$pass = (string) $request->get_param( 'pass' );
		}

		return hash_equals( $stored_key, $key ) && hash_equals( $stored_pass, $pass );
	}

	private function payload( \WP_REST_Request $request ) {
		$json = $request->get_json_params();
		if ( is_array( $json ) && ! empty( $json ) ) {
			return $json;
		}

		$params = $request->get_params();
		unset( $params['key'], $params['pass'] );
		if ( is_array( $params ) && ! empty( $params ) ) {
			return $params;
		}

		return array();
	}

		public function get_client( \WP_REST_Request $request ) {
			$user_id = absint( $request->get_param( 'id' ) );
			$user    = get_user_by( 'id', $user_id );

			if ( ! $user ) {
				return new \WP_REST_Response( array( 'success' => false, 'message' => 'Cliente não encontrado' ), 404 );
			}

			$data = $this->serialize_customer( $user );

			return new \WP_REST_Response( array( 'success' => true, 'data' => $data ), 200 );
		}

		public function get_clients( \WP_REST_Request $request ) {
			$page            = max( 1, absint( $request->get_param( 'page' ) ?: 1 ) );
			$limit           = min( 200, max( 1, absint( $request->get_param( 'limit' ) ?: 100 ) ) );
			$updated_after   = (string) $request->get_param( 'updated_after' );
			$registered_after = (string) $request->get_param( 'registered_after' );

			if ( '' === $registered_after ) {
				$registered_after = $updated_after;
			}

			$query_args = array(
				'number'  => $limit,
				'paged'   => $page,
				'orderby' => 'ID',
				'order'   => 'ASC',
				'fields'  => 'all',
				'count_total' => true,
				'role__in' => array( 'customer', 'subscriber' ),
			);

			if ( '' !== $registered_after ) {
				$ts = strtotime( $registered_after );
				if ( false === $ts ) {
					return new \WP_REST_Response( array( 'success' => false, 'message' => 'registered_after/updated_after inválido' ), 400 );
				}
				$query_args['date_query'] = array(
					array(
						'after'     => gmdate( 'Y-m-d H:i:s', $ts ),
						'inclusive' => false,
					),
				);
			}

			$query = new \WP_User_Query( $query_args );
			$users = is_array( $query->get_results() ) ? $query->get_results() : array();
			$data  = array_map( array( $this, 'serialize_customer' ), $users );

			$total       = (int) $query->get_total();
			$total_pages = (int) ceil( $total / max( 1, $limit ) );

			return new \WP_REST_Response(
				array(
					'success'    => true,
					'data'       => $data,
					'pagination' => array(
						'page'        => $page,
						'limit'       => $limit,
						'total'       => $total,
						'total_pages' => $total_pages,
					),
				),
				200
			);
		}

	public function get_order( \WP_REST_Request $request ) {
		$order = wc_get_order( absint( $request->get_param( 'id' ) ) );
		if ( ! $order ) {
			return new \WP_REST_Response( array( 'success' => false, 'message' => 'Pedido não encontrado' ), 404 );
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $this->serialize_order( $order ),
			),
			200
		);
	}

	public function get_orders( \WP_REST_Request $request ) {
		$page          = max( 1, absint( $request->get_param( 'page' ) ?: 1 ) );
		$limit         = min( 200, max( 1, absint( $request->get_param( 'limit' ) ?: 100 ) ) );
		$status        = sanitize_text_field( (string) $request->get_param( 'status' ) );
		$updated_after = (string) $request->get_param( 'updated_after' );

		$args = array(
			'return'   => 'objects',
			'paginate' => true,
			'limit'    => $limit,
			'page'     => $page,
			'orderby'  => 'date_modified',
			'order'    => 'ASC',
		);

		if ( '' !== $status ) {
			$args['status'] = array( preg_replace( '/^wc-/', '', strtolower( $status ) ) );
		}

		if ( '' !== $updated_after ) {
			$ts = strtotime( $updated_after );
			if ( false === $ts ) {
				return new \WP_REST_Response( array( 'success' => false, 'message' => 'updated_after inválido' ), 400 );
			}
			$args['date_modified'] = '>' . gmdate( 'Y-m-d H:i:s', $ts );
		}

		$result = wc_get_orders( $args );
		$orders = is_object( $result ) && isset( $result->orders ) ? $result->orders : array();
		$data   = array_map( array( $this, 'serialize_order' ), $orders );

		return new \WP_REST_Response(
			array(
				'success'    => true,
				'data'       => $data,
				'pagination' => array(
					'page'        => $page,
					'limit'       => $limit,
					'total'       => is_object( $result ) && isset( $result->total ) ? (int) $result->total : count( $data ),
					'total_pages' => is_object( $result ) && isset( $result->max_num_pages ) ? (int) $result->max_num_pages : 1,
				),
			),
			200
		);
	}


	public function set_order_status( \WP_REST_Request $request ) {
		$payload = $this->payload( $request );
		$order_id = absint( $payload['woo_order_id'] ?? $payload['idOrder'] ?? 0 );
		$status   = sanitize_text_field( (string) ( $payload['status'] ?? $payload['IdOrderStatus'] ?? '' ) );

		if ( ! $order_id || '' === $status ) {
			return new \WP_REST_Response( array( 'success' => false, 'message' => 'Campos obrigatórios: woo_order_id e status' ), 400 );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new \WP_REST_Response( array( 'success' => false, 'message' => 'Pedido não encontrado' ), 404 );
		}

		$woo_status = $this->map_status( $status );
		$order->update_status( $woo_status, 'Atualizado via Protheus Passive API' );
		$order->save();

		return new \WP_REST_Response( array( 'success' => true, 'data' => array( 'woo_order_id' => $order_id, 'status' => $woo_status ) ), 200 );
	}

	public function set_product_update( \WP_REST_Request $request ) {
		$payload = $this->payload( $request );
		$sku     = sanitize_text_field( (string) ( $payload['sku'] ?? $payload['IdProduct'] ?? '' ) );

		if ( '' === $sku ) {
			return new \WP_REST_Response( array( 'success' => false, 'message' => 'Campo obrigatório: sku' ), 400 );
		}

		$create_if_missing = ! isset( $payload['create_if_missing'] ) || (bool) $payload['create_if_missing'];
		$product_id        = wc_get_product_id_by_sku( $sku );
		$product           = $product_id ? wc_get_product( $product_id ) : null;

		if ( ! $product && ! $create_if_missing ) {
			return new \WP_REST_Response( array( 'success' => false, 'message' => 'Produto não encontrado e create_if_missing=false' ), 404 );
		}

		$created = false;
		if ( ! $product ) {
			$product = new \WC_Product_Simple();
			$product->set_sku( $sku );
			$created = true;
		}

		$this->apply_product_payload( $product, $payload );
		$product->save();

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'id'      => $product->get_id(),
					'sku'     => $product->get_sku(),
					'created' => $created,
				),
			),
			200
		);
	}


		public function get_products( \WP_REST_Request $request ) {
			$page            = max( 1, absint( $request->get_param( 'page' ) ?: 1 ) );
			$limit           = min( 200, max( 1, absint( $request->get_param( 'limit' ) ?: 100 ) ) );
			$updated_after   = (string) $request->get_param( 'updated_after' );
			$include_meta    = filter_var( $request->get_param( 'include_meta' ), FILTER_VALIDATE_BOOLEAN );
			$include_variations = filter_var( $request->get_param( 'include_variations' ), FILTER_VALIDATE_BOOLEAN );

			$args = array(
				'return'   => 'objects',
				'paginate' => true,
				'limit'    => $limit,
				'page'     => $page,
				'orderby'  => 'date_modified',
				'order'    => 'ASC',
				'status'   => array( 'publish', 'private', 'draft', 'pending' ),
			);

			if ( '' !== $updated_after ) {
				$ts = strtotime( $updated_after );
				if ( false === $ts ) {
					return new \WP_REST_Response( array( 'success' => false, 'message' => 'updated_after inválido' ), 400 );
				}
				$args['date_modified'] = '>' . gmdate( 'Y-m-d H:i:s', $ts );
			}

			$result   = wc_get_products( $args );
			$products = is_object( $result ) && isset( $result->products ) ? $result->products : array();
			$data     = array_map(
				function ( $product ) use ( $include_meta, $include_variations ) {
					return $this->serialize_product( $product, $include_meta, $include_variations );
				},
				$products
			);

			return new \WP_REST_Response(
				array(
					'success'    => true,
					'data'       => $data,
					'pagination' => array(
						'page'        => $page,
						'limit'       => $limit,
						'total'       => is_object( $result ) && isset( $result->total ) ? (int) $result->total : count( $data ),
						'total_pages' => is_object( $result ) && isset( $result->max_num_pages ) ? (int) $result->max_num_pages : 1,
					),
				),
				200
			);
		}

		public function get_product( \WP_REST_Request $request ) {
			$product = wc_get_product( absint( $request->get_param( 'id' ) ) );
			if ( ! $product ) {
				return new \WP_REST_Response( array( 'success' => false, 'message' => 'Produto não encontrado' ), 404 );
			}

			$include_meta       = filter_var( $request->get_param( 'include_meta' ), FILTER_VALIDATE_BOOLEAN );
			$include_variations = filter_var( $request->get_param( 'include_variations' ), FILTER_VALIDATE_BOOLEAN );

			return new \WP_REST_Response(
				array(
					'success' => true,
					'data'    => $this->serialize_product( $product, $include_meta, $include_variations ),
				),
				200
			);
		}

		public function set_products_batch( \WP_REST_Request $request ) {
			$payload           = $this->payload( $request );
			$items             = isset( $payload['items'] ) && is_array( $payload['items'] ) ? $payload['items'] : array();
			$create_if_missing = ! isset( $payload['create_if_missing'] ) || (bool) $payload['create_if_missing'];

			if ( empty( $items ) ) {
				return new \WP_REST_Response( array( 'success' => false, 'message' => 'Campo obrigatório: items[]' ), 400 );
			}

			$results = array(
				'processed' => 0,
				'updated'   => 0,
				'created'   => 0,
				'errors'    => array(),
			);

			foreach ( $items as $index => $item ) {
				$results['processed']++;

				if ( ! is_array( $item ) ) {
					$results['errors'][] = array( 'index' => $index, 'message' => 'Item inválido (esperado objeto)' );
					continue;
				}

				$sku = sanitize_text_field( (string) ( $item['sku'] ?? $item['IdProduct'] ?? '' ) );
				if ( '' === $sku ) {
					$results['errors'][] = array( 'index' => $index, 'message' => 'SKU obrigatório' );
					continue;
				}

				$product_id = wc_get_product_id_by_sku( $sku );
				$product    = $product_id ? wc_get_product( $product_id ) : null;

				if ( ! $product && ! $create_if_missing ) {
					$results['errors'][] = array( 'index' => $index, 'sku' => $sku, 'message' => 'Produto não encontrado e create_if_missing=false' );
					continue;
				}

				if ( ! $product ) {
					$product = new \WC_Product_Simple();
					$product->set_sku( $sku );
					$results['created']++;
				} else {
					$results['updated']++;
				}

				$this->apply_product_payload( $product, $item );
				$product->save();
			}

			return new \WP_REST_Response( array( 'success' => true, 'data' => $results ), 200 );
		}

	private function serialize_order( \WC_Order $order ) {
		$billing_name  = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		$shipping_name = trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() );
		$document      = (string) $order->get_meta( '_billing_cpfcnpj', true );
		if ( '' === $document ) {
			$document = (string) $order->get_meta( '_billing_cpf', true );
		}
		if ( '' === $document ) {
			$document = (string) $order->get_meta( '_billing_cnpj', true );
		}

			return array(
				'id'          => $order->get_id(),
				'number'      => $order->get_order_number(),
				'status'      => $order->get_status(),
				'payment_method' => $order->get_payment_method(),
				'payment_method_title' => $order->get_payment_method_title(),
				'transaction_id' => $order->get_transaction_id(),
				'customer_note' => $order->get_customer_note(),
				'created_at'  => $this->date_iso( $order->get_date_created() ),
				'updated_at'  => $this->date_iso( $order->get_date_modified() ),
				'total'       => (float) $order->get_total(),
				'subtotal'    => (float) $order->get_subtotal(),
				'discount_total' => (float) $order->get_discount_total(),
				'shipping_total' => (float) $order->get_shipping_total(),
				'total_tax'   => (float) $order->get_total_tax(),
				'total_refunded' => (float) $order->get_total_refunded(),
				'currency'    => $order->get_currency(),
				'customer_id' => $order->get_customer_id(),
				'billing'     => array(
				'name'       => $billing_name,
				'first_name' => $order->get_billing_first_name(),
				'last_name'  => $order->get_billing_last_name(),
				'email'      => $order->get_billing_email(),
				'phone'      => $order->get_billing_phone(),
				'document'   => $document,
				'company'    => $order->get_billing_company(),
				'address_1'  => $order->get_billing_address_1(),
				'address_2'  => $order->get_billing_address_2(),
				'city'       => $order->get_billing_city(),
				'state'      => $order->get_billing_state(),
				'postcode'   => $order->get_billing_postcode(),
				'country'    => $order->get_billing_country(),
			),
				'shipping'    => array(
				'name'       => '' !== $shipping_name ? $shipping_name : $billing_name,
				'first_name' => $order->get_shipping_first_name(),
				'last_name'  => $order->get_shipping_last_name(),
				'company'    => $order->get_shipping_company(),
				'address_1'  => $order->get_shipping_address_1(),
				'address_2'  => $order->get_shipping_address_2(),
				'city'       => $order->get_shipping_city(),
				'state'      => $order->get_shipping_state(),
				'postcode'   => $order->get_shipping_postcode(),
				'country'    => $order->get_shipping_country(),
				),
				'shipping_lines' => $this->serialize_order_shipping_lines( $order ),
				'fee_lines'      => $this->serialize_order_fee_lines( $order ),
				'coupon_lines'   => $this->serialize_order_coupon_lines( $order ),
				'items'       => $this->serialize_order_items( $order ),
			);
		}

		private function serialize_order_items( \WC_Order $order ) {
		$items = array();
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
				$items[] = array(
					'item_id'     => $item->get_id(),
					'product_id'  => $item->get_product_id(),
					'variation_id'=> $item->get_variation_id(),
					'sku'         => $product ? $product->get_sku() : '',
					'name'        => $item->get_name(),
					'quantity'    => (float) $item->get_quantity(),
					'subtotal'    => (float) $item->get_subtotal(),
					'unit_price'  => (float) $order->get_item_total( $item, false ),
					'total_price' => (float) $item->get_total(),
					'total_tax'   => (float) $item->get_total_tax(),
					'taxes'       => $item->get_taxes(),
				);
			}
			return $items;
		}

		private function serialize_order_shipping_lines( \WC_Order $order ) {
			$lines = array();
			foreach ( $order->get_items( 'shipping' ) as $item ) {
				$lines[] = array(
					'item_id'     => $item->get_id(),
					'method_id'   => $item->get_method_id(),
					'method_title'=> $item->get_method_title(),
					'total'       => (float) $item->get_total(),
					'total_tax'   => (float) $item->get_total_tax(),
				);
			}
			return $lines;
		}

		private function serialize_order_fee_lines( \WC_Order $order ) {
			$lines = array();
			foreach ( $order->get_items( 'fee' ) as $item ) {
				$lines[] = array(
					'item_id'   => $item->get_id(),
					'name'      => $item->get_name(),
					'total'     => (float) $item->get_total(),
					'total_tax' => (float) $item->get_total_tax(),
				);
			}
			return $lines;
		}

		private function serialize_order_coupon_lines( \WC_Order $order ) {
			$lines = array();
			foreach ( $order->get_items( 'coupon' ) as $item ) {
				$lines[] = array(
					'item_id'       => $item->get_id(),
					'code'          => $item->get_code(),
					'discount'      => (float) $item->get_discount(),
					'discount_tax'  => (float) $item->get_discount_tax(),
				);
			}
			return $lines;
		}

		private function serialize_customer( \WP_User $user ) {
			$customer_id = (int) $user->ID;
			$document    = (string) get_user_meta( $customer_id, 'billing_cpfcnpj', true );
			if ( '' === $document ) {
				$document = (string) get_user_meta( $customer_id, 'billing_cpf', true );
			}
			if ( '' === $document ) {
				$document = (string) get_user_meta( $customer_id, 'billing_cnpj', true );
			}

			return array(
				'id'            => $customer_id,
				'email'         => (string) $user->user_email,
				'username'      => (string) $user->user_login,
				'display_name'  => (string) $user->display_name,
				'first_name'    => (string) get_user_meta( $customer_id, 'first_name', true ),
				'last_name'     => (string) get_user_meta( $customer_id, 'last_name', true ),
				'document'      => $document,
				'registered_at' => gmdate( 'Y-m-d\TH:i:s\Z', strtotime( (string) $user->user_registered ) ),
				'billing'       => array(
					'first_name' => (string) get_user_meta( $customer_id, 'billing_first_name', true ),
					'last_name'  => (string) get_user_meta( $customer_id, 'billing_last_name', true ),
					'company'    => (string) get_user_meta( $customer_id, 'billing_company', true ),
					'address_1'  => (string) get_user_meta( $customer_id, 'billing_address_1', true ),
					'address_2'  => (string) get_user_meta( $customer_id, 'billing_address_2', true ),
					'city'       => (string) get_user_meta( $customer_id, 'billing_city', true ),
					'state'      => (string) get_user_meta( $customer_id, 'billing_state', true ),
					'postcode'   => (string) get_user_meta( $customer_id, 'billing_postcode', true ),
					'country'    => (string) get_user_meta( $customer_id, 'billing_country', true ),
					'phone'      => (string) get_user_meta( $customer_id, 'billing_phone', true ),
					'email'      => (string) get_user_meta( $customer_id, 'billing_email', true ),
				),
				'shipping'      => array(
					'first_name' => (string) get_user_meta( $customer_id, 'shipping_first_name', true ),
					'last_name'  => (string) get_user_meta( $customer_id, 'shipping_last_name', true ),
					'company'    => (string) get_user_meta( $customer_id, 'shipping_company', true ),
					'address_1'  => (string) get_user_meta( $customer_id, 'shipping_address_1', true ),
					'address_2'  => (string) get_user_meta( $customer_id, 'shipping_address_2', true ),
					'city'       => (string) get_user_meta( $customer_id, 'shipping_city', true ),
					'state'      => (string) get_user_meta( $customer_id, 'shipping_state', true ),
					'postcode'   => (string) get_user_meta( $customer_id, 'shipping_postcode', true ),
					'country'    => (string) get_user_meta( $customer_id, 'shipping_country', true ),
				),
			);
		}

		private function serialize_product( \WC_Product $product, $include_meta = false, $include_variations = false ) {
			$categories = array();
			foreach ( wp_get_post_terms( $product->get_id(), 'product_cat' ) as $term ) {
				$categories[] = array(
					'id'   => (int) $term->term_id,
					'name' => (string) $term->name,
					'slug' => (string) $term->slug,
				);
			}

			$tags = array();
			foreach ( wp_get_post_terms( $product->get_id(), 'product_tag' ) as $term ) {
				$tags[] = array(
					'id'   => (int) $term->term_id,
					'name' => (string) $term->name,
					'slug' => (string) $term->slug,
				);
			}

			$images = array();
			$image_ids = array_filter( array_merge( array( $product->get_image_id() ), $product->get_gallery_image_ids() ) );
			foreach ( $image_ids as $image_id ) {
				$url = wp_get_attachment_url( $image_id );
				if ( $url ) {
					$images[] = array(
						'id'  => (int) $image_id,
						'url' => (string) $url,
					);
				}
			}

			$attributes = array();
			foreach ( $product->get_attributes() as $attribute ) {
				if ( is_a( $attribute, 'WC_Product_Attribute' ) ) {
					$attributes[] = array(
						'name'      => $attribute->get_name(),
						'visible'   => $attribute->get_visible(),
						'variation' => $attribute->get_variation(),
						'options'   => $attribute->get_options(),
					);
				}
			}

			$data = array(
				'id'                => $product->get_id(),
				'type'              => $product->get_type(),
				'status'            => $product->get_status(),
				'name'              => $product->get_name(),
				'slug'              => $product->get_slug(),
				'sku'               => $product->get_sku(),
				'description'       => $product->get_description(),
				'short_description' => $product->get_short_description(),
				'weight'            => $product->get_weight(),
				'dimensions'        => array(
					'length' => $product->get_length(),
					'width'  => $product->get_width(),
					'height' => $product->get_height(),
				),
				'price'             => $product->get_price(),
				'regular_price'     => $product->get_regular_price(),
				'sale_price'        => $product->get_sale_price(),
				'date_on_sale_from' => $this->date_iso( $product->get_date_on_sale_from() ),
				'date_on_sale_to'   => $this->date_iso( $product->get_date_on_sale_to() ),
				'manage_stock'      => $product->get_manage_stock(),
				'stock_quantity'    => $product->get_stock_quantity(),
				'stock_status'      => $product->get_stock_status(),
				'backorders'        => $product->get_backorders(),
				'catalog_visibility'=> $product->get_catalog_visibility(),
				'featured'          => $product->get_featured(),
				'virtual'           => $product->is_virtual(),
				'downloadable'      => $product->is_downloadable(),
				'sold_individually' => $product->get_sold_individually(),
				'permalink'         => get_permalink( $product->get_id() ),
				'created_at'        => $this->date_iso( $product->get_date_created() ),
				'updated_at'        => $this->date_iso( $product->get_date_modified() ),
				'categories'        => $categories,
				'tags'              => $tags,
				'images'            => $images,
				'attributes'        => $attributes,
			);

			if ( $include_variations && $product->is_type( 'variable' ) ) {
				$variation_data = array();
				foreach ( $product->get_children() as $variation_id ) {
					$variation = wc_get_product( $variation_id );
					if ( $variation ) {
						$variation_data[] = array(
							'id'             => $variation->get_id(),
							'sku'            => $variation->get_sku(),
							'price'          => $variation->get_price(),
							'regular_price'  => $variation->get_regular_price(),
							'sale_price'     => $variation->get_sale_price(),
							'stock_quantity' => $variation->get_stock_quantity(),
							'stock_status'   => $variation->get_stock_status(),
							'attributes'     => $variation->get_attributes(),
						);
					}
				}
				$data['variations'] = $variation_data;
			}

			if ( $include_meta ) {
				$meta = array();
				foreach ( $product->get_meta_data() as $meta_item ) {
					$meta[] = array(
						'key'   => $meta_item->key,
						'value' => $meta_item->value,
					);
				}
				$data['meta'] = $meta;
			}

			return $data;
		}

		private function apply_product_payload( \WC_Product $product, array $payload ) {
			if ( isset( $payload['name'] ) ) {
				$product->set_name( sanitize_text_field( (string) $payload['name'] ) );
			}
			if ( isset( $payload['description'] ) ) {
				$product->set_description( wp_kses_post( (string) $payload['description'] ) );
			}
			if ( isset( $payload['short_description'] ) ) {
				$product->set_short_description( wp_kses_post( (string) $payload['short_description'] ) );
			}
			if ( isset( $payload['weight'] ) ) {
				$product->set_weight( (string) $payload['weight'] );
			}
			if ( isset( $payload['dimensions'] ) && is_array( $payload['dimensions'] ) ) {
				$dimensions = $payload['dimensions'];
				if ( isset( $dimensions['length'] ) ) {
					$product->set_length( (string) $dimensions['length'] );
				}
				if ( isset( $dimensions['width'] ) ) {
					$product->set_width( (string) $dimensions['width'] );
				}
				if ( isset( $dimensions['height'] ) ) {
					$product->set_height( (string) $dimensions['height'] );
				}
			}
			if ( isset( $payload['price'] ) ) {
				$product->set_price( (string) $payload['price'] );
			}
			if ( isset( $payload['regular_price'] ) ) {
				$product->set_regular_price( (string) $payload['regular_price'] );
			}
			if ( isset( $payload['sale_price'] ) ) {
				$product->set_sale_price( (string) $payload['sale_price'] );
			}
			if ( isset( $payload['stock_quantity'] ) ) {
				$product->set_manage_stock( true );
				$product->set_stock_quantity( (int) $payload['stock_quantity'] );
				$product->set_stock_status( (int) $payload['stock_quantity'] > 0 ? 'instock' : 'outofstock' );
			}
			if ( isset( $payload['status'] ) ) {
				$product->set_status( sanitize_text_field( (string) $payload['status'] ) );
			}
			if ( isset( $payload['catalog_visibility'] ) ) {
				$product->set_catalog_visibility( sanitize_text_field( (string) $payload['catalog_visibility'] ) );
			}
		}

	private function date_iso( $date ) {
		if ( ! $date || ! method_exists( $date, 'getTimestamp' ) ) {
			return null;
		}
		return gmdate( 'Y-m-d\TH:i:s\Z', $date->getTimestamp() );
	}

	private function map_status( $status ) {
		$status = strtolower( trim( (string) $status ) );
		$map    = array(
			'101'       => 'pending',
			'102'       => 'processing',
			'103'       => 'completed',
			'cancelled' => 'cancelled',
			'approved'  => 'processing',
			'invoiced'  => 'completed',
			'shipped'   => 'completed',
		);
		return $map[ $status ] ?? preg_replace( '/^wc-/', '', $status );
	}

}

register_activation_hook( __FILE__, array( 'ABS_Protheus_Passive_API', 'activate' ) );
new ABS_Protheus_Passive_API();
