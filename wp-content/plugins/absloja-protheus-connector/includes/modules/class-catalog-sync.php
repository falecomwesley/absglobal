<?php
/**
 * Catalog Sync Module
 *
 * Handles synchronization of products and stock from Protheus to WooCommerce.
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
 * Class Catalog_Sync
 *
 * Manages product and stock synchronization from Protheus to WooCommerce.
 * Handles batch synchronization, individual product/stock updates, and
 * product visibility management based on stock levels.
 *
 * @since 1.0.0
 */
class Catalog_Sync {

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
	 * Constructor
	 *
	 * @param Protheus_Client $client Protheus API client.
	 * @param Mapping_Engine  $mapper Mapping engine for field mappings.
	 * @param Logger          $logger Logger for operation tracking.
	 */
	public function __construct( Protheus_Client $client, Mapping_Engine $mapper, Logger $logger ) {
		$this->client = $client;
		$this->mapper = $mapper;
		$this->logger = $logger;

		// Register hooks for price editing prevention
		$this->register_price_protection_hooks();
	}

	/**
	 * Register hooks for price editing prevention
	 *
	 * Registers WordPress hooks to prevent manual price editing for
	 * products synchronized from Protheus.
	 *
	 * @return void
	 */
	private function register_price_protection_hooks(): void {
		// Make price field readonly in admin
		add_action( 'woocommerce_product_options_pricing', array( $this, 'make_price_field_readonly' ) );
		
		// Restore original price if manually modified
		add_action( 'woocommerce_process_product_meta', array( $this, 'restore_original_price' ), 10, 1 );
		
		// Display admin notice for synced products
		add_action( 'admin_notices', array( $this, 'display_price_sync_notice' ) );
	}

	/**
	 * Make price field readonly for synced products
	 *
	 * Adds JavaScript to make the price field readonly in the product edit screen
	 * for products that are synchronized from Protheus.
	 *
	 * @return void
	 */
	public function make_price_field_readonly(): void {
		global $post;

		if ( ! $post ) {
			return;
		}

		$product = wc_get_product( $post->ID );

		if ( ! $product ) {
			return;
		}

		// Check if product is synced from Protheus
		$is_synced = $product->get_meta( '_protheus_synced', true );
		$price_locked = $product->get_meta( '_protheus_price_locked', true );

		if ( ! $is_synced || ! $price_locked ) {
			return;
		}

		// Add JavaScript to make price fields readonly and add visual indicator
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			// Make regular price field readonly
			$('#_regular_price').prop('readonly', true).css({
				'background-color': '#f0f0f0',
				'cursor': 'not-allowed'
			});
			
			// Make sale price field readonly
			$('#_sale_price').prop('readonly', true).css({
				'background-color': '#f0f0f0',
				'cursor': 'not-allowed'
			});

			// Add notice after price field
			if ($('#_regular_price').length && !$('#protheus-price-notice').length) {
				$('#_regular_price').parent().append(
					'<p id="protheus-price-notice" style="color: #d63638; font-style: italic; margin-top: 5px;">' +
					'<?php esc_html_e( 'Este preço é sincronizado automaticamente do Protheus e não pode ser editado manualmente.', 'absloja-protheus-connector' ); ?>' +
					'</p>'
				);
			}
		});
		</script>
		<?php
	}

	/**
	 * Restore original price if manually modified
	 *
	 * Prevents manual price changes by restoring the original price from
	 * Protheus when a product is saved.
	 *
	 * @param int $product_id Product ID being saved.
	 * @return void
	 */
	public function restore_original_price( int $product_id ): void {
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return;
		}

		// Check if product is synced from Protheus
		$is_synced = $product->get_meta( '_protheus_synced', true );
		$price_locked = $product->get_meta( '_protheus_price_locked', true );

		if ( ! $is_synced || ! $price_locked ) {
			return;
		}

		// Get the original price from metadata (stored during sync)
		$original_price = $product->get_meta( '_protheus_original_price', true );

		if ( empty( $original_price ) ) {
			// If no original price stored, store the current price as original
			$current_price = $product->get_regular_price();
			if ( ! empty( $current_price ) ) {
				$product->update_meta_data( '_protheus_original_price', $current_price );
				$product->save_meta_data();
			}
			return;
		}

		// Check if price was modified
		$current_price = $product->get_regular_price();

		if ( $current_price !== $original_price ) {
			// Restore original price
			$product->set_regular_price( $original_price );
			$product->save();

			// Set transient to display notice on next page load
			set_transient( 'protheus_price_restore_notice_' . $product_id, true, 30 );
		}
	}

	/**
	 * Display admin notice for price synchronization
	 *
	 * Shows a notice in the admin area when a price change was prevented
	 * due to Protheus synchronization.
	 *
	 * @return void
	 */
	public function display_price_sync_notice(): void {
		// Check if we're on a product edit screen
		$screen = get_current_screen();
		
		if ( ! $screen || $screen->id !== 'product' ) {
			return;
		}

		global $post;

		if ( ! $post ) {
			return;
		}

		// Check if price was restored
		$notice_transient = get_transient( 'protheus_price_restore_notice_' . $post->ID );

		if ( $notice_transient ) {
			?>
			<div class="notice notice-warning is-dismissible">
				<p>
					<strong><?php esc_html_e( 'Aviso:', 'absloja-protheus-connector' ); ?></strong>
					<?php esc_html_e( 'O preço deste produto é sincronizado automaticamente do Protheus e não pode ser editado manualmente. Suas alterações foram revertidas.', 'absloja-protheus-connector' ); ?>
				</p>
			</div>
			<?php
			
			// Delete transient after displaying
			delete_transient( 'protheus_price_restore_notice_' . $post->ID );
		}

		// Always show info notice for synced products
		$product = wc_get_product( $post->ID );
		
		if ( $product ) {
			$is_synced = $product->get_meta( '_protheus_synced', true );
			$price_locked = $product->get_meta( '_protheus_price_locked', true );

			if ( $is_synced && $price_locked ) {
				?>
				<div class="notice notice-info">
					<p>
						<strong><?php esc_html_e( 'Produto Sincronizado:', 'absloja-protheus-connector' ); ?></strong>
						<?php esc_html_e( 'Este produto é sincronizado do Protheus. O preço é atualizado automaticamente e não pode ser editado manualmente.', 'absloja-protheus-connector' ); ?>
					</p>
				</div>
				<?php
			}
		}
	}

	/**
	 * Synchronize products in batch
	 *
	 * Fetches products from Protheus and creates/updates them in WooCommerce.
	 * Processes products in batches to manage memory and performance.
	 *
	 * @param int $batch_size Number of products to process per batch (default: 50).
	 * @return array {
	 *     Sync operation results.
	 *
	 *     @type int   $total_processed Total number of products processed.
	 *     @type int   $created         Number of products created.
	 *     @type int   $updated         Number of products updated.
	 *     @type int   $errors          Number of errors encountered.
	 *     @type array $error_details   Array of error messages.
	 * }
	 */
	public function sync_products( int $batch_size = 50 ): array {
		$start_time = microtime( true );
		
		$results = array(
			'total_processed' => 0,
			'created'         => 0,
			'updated'         => 0,
			'errors'          => 0,
			'error_details'   => array(),
		);

		$page = 1;
		$has_more = true;

		while ( $has_more ) {
			// Fetch products from Protheus
			$response = $this->client->get(
				'api/v1/products',
				array(
					'page'  => $page,
					'limit' => $batch_size,
				)
			);

			if ( ! $response['success'] ) {
				$error_msg = sprintf(
					'Failed to fetch products from Protheus (page %d): %s',
					$page,
					$response['error']
				);
				$results['error_details'][] = $error_msg;
				$this->logger->log_sync_operation(
					'product_sync',
					array(
						'page'  => $page,
						'error' => $response['error'],
					),
					false,
					$error_msg
				);
				break;
			}

			$products = $response['data']['products'] ?? array();
			$has_more = ! empty( $products ) && count( $products ) === $batch_size;

			// Process each product
			foreach ( $products as $product_data ) {
				$result = $this->process_single_product( $product_data );
				
				$results['total_processed']++;
				
				if ( $result['success'] ) {
					if ( $result['action'] === 'created' ) {
						$results['created']++;
					} else {
						$results['updated']++;
					}
				} else {
					$results['errors']++;
					$results['error_details'][] = $result['error'];
				}
			}

			$page++;
		}

		$duration = microtime( true ) - $start_time;

		// Log the sync operation
		$this->logger->log_sync_operation(
			'product_sync',
			array(
				'batch_size'      => $batch_size,
				'total_processed' => $results['total_processed'],
				'created'         => $results['created'],
				'updated'         => $results['updated'],
				'errors'          => $results['errors'],
				'duration'        => $duration,
			),
			$results['errors'] === 0,
			$results['errors'] > 0 ? implode( '; ', $results['error_details'] ) : null
		);

		return $results;
	}

	/**
	 * Synchronize stock quantities
	 *
	 * Fetches stock data from Protheus and updates WooCommerce product stock.
	 * Manages product visibility based on stock availability.
	 *
	 * @return array {
	 *     Sync operation results.
	 *
	 *     @type int   $total_processed Total number of stock items processed.
	 *     @type int   $updated         Number of stock quantities updated.
	 *     @type int   $hidden          Number of products hidden (zero stock).
	 *     @type int   $restored        Number of products restored (stock available).
	 *     @type int   $errors          Number of errors encountered.
	 *     @type array $error_details   Array of error messages.
	 * }
	 */
	public function sync_stock(): array {
		$start_time = microtime( true );
		
		$results = array(
			'total_processed' => 0,
			'updated'         => 0,
			'hidden'          => 0,
			'restored'        => 0,
			'errors'          => 0,
			'error_details'   => array(),
		);

		// Fetch stock data from Protheus
		$response = $this->client->get( 'api/v1/stock' );

		if ( ! $response['success'] ) {
			$error_msg = sprintf(
				'Failed to fetch stock from Protheus: %s',
				$response['error']
			);
			$results['error_details'][] = $error_msg;
			$this->logger->log_sync_operation(
				'stock_sync',
				array( 'error' => $response['error'] ),
				false,
				$error_msg
			);
			return $results;
		}

		$stock_items = $response['data']['stock'] ?? array();

		// Process each stock item
		foreach ( $stock_items as $stock_data ) {
			$sku      = $stock_data['B2_COD'] ?? '';
			$quantity = isset( $stock_data['B2_QATU'] ) ? (int) $stock_data['B2_QATU'] : 0;

			if ( empty( $sku ) ) {
				$results['errors']++;
				$results['error_details'][] = 'Stock item missing B2_COD (SKU)';
				continue;
			}

			$result = $this->update_product_stock( $sku, $quantity );
			
			$results['total_processed']++;
			
			if ( $result['success'] ) {
				$results['updated']++;
				if ( $result['hidden'] ) {
					$results['hidden']++;
				}
				if ( $result['restored'] ) {
					$results['restored']++;
				}
			} else {
				$results['errors']++;
				$results['error_details'][] = $result['error'];
			}
		}

		$duration = microtime( true ) - $start_time;

		// Log the sync operation
		$this->logger->log_sync_operation(
			'stock_sync',
			array(
				'total_processed' => $results['total_processed'],
				'updated'         => $results['updated'],
				'hidden'          => $results['hidden'],
				'restored'        => $results['restored'],
				'errors'          => $results['errors'],
				'duration'        => $duration,
			),
			$results['errors'] === 0,
			$results['errors'] > 0 ? implode( '; ', $results['error_details'] ) : null
		);

		return $results;
	}

	/**
	 * Synchronize a single product by SKU
	 *
	 * Fetches a specific product from Protheus and creates/updates it in WooCommerce.
	 *
	 * @param string $sku Product SKU (B1_COD).
	 * @return bool True on success, false on failure.
	 */
	public function sync_single_product( string $sku ): bool {
		if ( empty( $sku ) ) {
			return false;
		}

		// Fetch product from Protheus
		$response = $this->client->get(
			'api/v1/products/' . urlencode( $sku )
		);

		if ( ! $response['success'] ) {
			$this->logger->log_sync_operation(
				'single_product_sync',
				array(
					'sku'   => $sku,
					'error' => $response['error'],
				),
				false,
				sprintf( 'Failed to fetch product %s: %s', $sku, $response['error'] )
			);
			return false;
		}

		$product_data = $response['data'];
		$result = $this->process_single_product( $product_data );

		$this->logger->log_sync_operation(
			'single_product_sync',
			array(
				'sku'    => $sku,
				'action' => $result['action'] ?? 'none',
			),
			$result['success'],
			$result['success'] ? null : $result['error']
		);

		return $result['success'];
	}

	/**
	 * Update stock for a single product
	 *
	 * Updates the stock quantity for a specific product in WooCommerce.
	 * Manages product visibility based on stock availability.
	 *
	 * @param string $sku Product SKU.
	 * @param int    $quantity Stock quantity.
	 * @return bool True on success, false on failure.
	 */
	public function sync_single_stock( string $sku, int $quantity ): bool {
		if ( empty( $sku ) ) {
			return false;
		}

		$result = $this->update_product_stock( $sku, $quantity );

		$this->logger->log_sync_operation(
			'single_stock_sync',
			array(
				'sku'      => $sku,
				'quantity' => $quantity,
				'hidden'   => $result['hidden'] ?? false,
				'restored' => $result['restored'] ?? false,
			),
			$result['success'],
			$result['success'] ? null : $result['error']
		);

		return $result['success'];
	}

	/**
	 * Process a single product from Protheus data
	 *
	 * Creates or updates a WooCommerce product based on Protheus product data.
	 *
	 * @param array $product_data Product data from Protheus.
	 * @return array {
	 *     Processing result.
	 *
	 *     @type bool   $success Whether the operation succeeded.
	 *     @type string $action  Action taken ('created', 'updated', or 'none').
	 *     @type int    $product_id WooCommerce product ID.
	 *     @type string $error   Error message if operation failed.
	 * }
	 */
	private function process_single_product( array $product_data ): array {
		try {
			$sku = $product_data['B1_COD'] ?? '';

			if ( empty( $sku ) ) {
				return array(
					'success' => false,
					'error'   => 'Product data missing B1_COD (SKU)',
				);
			}

			// Check if product exists by SKU
			$product_id = wc_get_product_id_by_sku( $sku );
			$exists = $product_id > 0;

			// Map Protheus data to WooCommerce format
			$wc_data = $this->map_product_data( $product_data );

			if ( $exists ) {
				// Update existing product
				$product = wc_get_product( $product_id );
				if ( ! $product ) {
					return array(
						'success' => false,
						'error'   => sprintf( 'Failed to load product with ID %d', $product_id ),
					);
				}

				$this->update_product_fields( $product, $wc_data );
				$product->save();

				return array(
					'success'    => true,
					'action'     => 'updated',
					'product_id' => $product_id,
				);
			} else {
				// Create new product
				$product = new \WC_Product_Simple();
				$product->set_sku( $sku );
				$this->update_product_fields( $product, $wc_data );
				$product_id = $product->save();

				return array(
					'success'    => true,
					'action'     => 'created',
					'product_id' => $product_id,
				);
			}
		} catch ( \Exception $e ) {
			return array(
				'success' => false,
				'error'   => sprintf( 'Exception processing product: %s', $e->getMessage() ),
			);
		}
	}

	/**
	 * Map Protheus product data to WooCommerce format
	 *
	 * Converts Protheus SB1 fields to WooCommerce product fields using
	 * the configured field mapping.
	 *
	 * @param array $protheus_data Product data from Protheus.
	 * @return array WooCommerce product data.
	 */
	private function map_product_data( array $protheus_data ): array {
		$mapping = $this->mapper->get_product_mapping();

		// Determine product status based on B1_MSBLQL (blocked status)
		$blocked = isset( $protheus_data['B1_MSBLQL'] ) && $protheus_data['B1_MSBLQL'] === '1';
		$status = $blocked ? 'draft' : 'publish';

		// Map basic fields
		$wc_data = array(
			'name'              => $protheus_data['B1_DESC'] ?? '',
			'regular_price'     => $protheus_data['B1_PRV1'] ?? 0,
			'description'       => $protheus_data['B1_DESC'] ?? '',
			'short_description' => $protheus_data['B1_DESCMAR'] ?? '',
			'weight'            => $protheus_data['B1_PESO'] ?? '',
			'status'            => $status,
			'manage_stock'      => true,
			'stock_quantity'    => 0, // Will be updated by stock sync
		);

		// Map category if B1_GRUPO is present
		if ( ! empty( $protheus_data['B1_GRUPO'] ) ) {
			$category_id = $this->mapper->get_category_mapping( $protheus_data['B1_GRUPO'] );
			if ( $category_id ) {
				$wc_data['category_ids'] = array( $category_id );
			}
		}

		// Store Protheus metadata
		$wc_data['meta_data'] = array(
			'_protheus_synced'     => true,
			'_protheus_sync_date'  => current_time( 'mysql' ),
			'_protheus_b1_grupo'   => $protheus_data['B1_GRUPO'] ?? '',
			'_protheus_b1_cod'     => $protheus_data['B1_COD'] ?? '',
			'_protheus_price_locked' => true,
		);

		// Handle image URL
		// Priority: 1) Explicit image_url from Protheus, 2) Pattern with SKU, 3) No image (preserve existing)
		if ( ! empty( $protheus_data['image_url'] ) ) {
			// Explicit image URL provided by Protheus
			$wc_data['image_url'] = $protheus_data['image_url'];
		} else {
			// Check if image URL pattern is configured
			$image_url_pattern = get_option( 'absloja_protheus_image_url_pattern', '' );
			if ( ! empty( $image_url_pattern ) && ! empty( $protheus_data['B1_COD'] ) ) {
				// Generate image URL from pattern
				$wc_data['image_url'] = str_replace( '{sku}', $protheus_data['B1_COD'], $image_url_pattern );
			}
			// If no pattern and no explicit URL, image_url is not set, preserving existing images
		}

		return $wc_data;
	}

	/**
	 * Update WooCommerce product fields
	 *
	 * Applies mapped data to a WooCommerce product object.
	 *
	 * @param \WC_Product $product WooCommerce product object.
	 * @param array       $data Mapped product data.
	 * @return void
	 */
	private function update_product_fields( \WC_Product $product, array $data ): void {
		// Update basic fields
		if ( isset( $data['name'] ) ) {
			$product->set_name( $data['name'] );
		}
		if ( isset( $data['regular_price'] ) ) {
			$product->set_regular_price( $data['regular_price'] );
			// Store original price for price lock validation
			$product->update_meta_data( '_protheus_original_price', $data['regular_price'] );
		}
		if ( isset( $data['description'] ) ) {
			$product->set_description( $data['description'] );
		}
		if ( isset( $data['short_description'] ) ) {
			$product->set_short_description( $data['short_description'] );
		}
		if ( isset( $data['weight'] ) ) {
			$product->set_weight( $data['weight'] );
		}
		if ( isset( $data['status'] ) ) {
			$product->set_status( $data['status'] );
		}
		if ( isset( $data['manage_stock'] ) ) {
			$product->set_manage_stock( $data['manage_stock'] );
		}
		if ( isset( $data['stock_quantity'] ) ) {
			$product->set_stock_quantity( $data['stock_quantity'] );
		}

		// Update categories
		if ( isset( $data['category_ids'] ) && is_array( $data['category_ids'] ) ) {
			$product->set_category_ids( $data['category_ids'] );
		}

		// Update meta data
		if ( isset( $data['meta_data'] ) && is_array( $data['meta_data'] ) ) {
			foreach ( $data['meta_data'] as $key => $value ) {
				$product->update_meta_data( $key, $value );
			}
		}

		// Handle product image
		if ( isset( $data['image_url'] ) && ! empty( $data['image_url'] ) ) {
			$this->handle_product_image( $product, $data['image_url'] );
		}
	}

	/**
	 * Update product stock quantity
	 *
	 * Updates stock for a product and manages visibility based on availability.
	 *
	 * @param string $sku Product SKU.
	 * @param int    $quantity Stock quantity.
	 * @return array {
	 *     Update result.
	 *
	 *     @type bool   $success  Whether the operation succeeded.
	 *     @type bool   $hidden   Whether the product was hidden.
	 *     @type bool   $restored Whether the product visibility was restored.
	 *     @type string $error    Error message if operation failed.
	 * }
	 */
	private function update_product_stock( string $sku, int $quantity ): array {
		// Find product by SKU
		$product_id = wc_get_product_id_by_sku( $sku );

		if ( ! $product_id ) {
			return array(
				'success' => false,
				'error'   => sprintf( 'Product with SKU %s not found', $sku ),
			);
		}

		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return array(
				'success' => false,
				'error'   => sprintf( 'Failed to load product with ID %d', $product_id ),
			);
		}

		// Get previous stock quantity to determine visibility changes
		$previous_quantity = $product->get_stock_quantity();
		$was_hidden = $product->get_catalog_visibility() === 'hidden';

		// Update stock quantity
		$product->set_stock_quantity( $quantity );
		$product->set_manage_stock( true );

		$hidden = false;
		$restored = false;

		// Manage visibility based on stock
		if ( $quantity === 0 ) {
			// Hide product when stock reaches zero
			if ( ! $was_hidden ) {
				$product->set_catalog_visibility( 'hidden' );
				$hidden = true;
			}
		} else {
			// Restore visibility when stock is available
			if ( $was_hidden && $previous_quantity === 0 ) {
				$product->set_catalog_visibility( 'visible' );
				$restored = true;
			}
		}

		$product->save();

		return array(
			'success'  => true,
			'hidden'   => $hidden,
			'restored' => $restored,
		);
	}

	/**
	 * Handle product image management
	 *
	 * Downloads and attaches an image to a WooCommerce product.
	 * If no image URL is provided, preserves existing product images.
	 * Supports image URL pattern with {sku} placeholder.
	 *
	 * @param \WC_Product $product WooCommerce product object.
	 * @param string      $image_url Image URL or empty to preserve existing.
	 * @return bool True if image was processed successfully, false otherwise.
	 */
	private function handle_product_image( \WC_Product $product, string $image_url ): bool {
		// If no image URL provided, preserve existing images
		if ( empty( $image_url ) ) {
			return true;
		}

		// Check if image URL pattern is configured
		$image_url_pattern = get_option( 'absloja_protheus_image_url_pattern', '' );
		
		// If pattern is configured and image_url doesn't look like a full URL, use pattern
		if ( ! empty( $image_url_pattern ) && strpos( $image_url, 'http' ) !== 0 ) {
			$image_url = $this->process_image_url_pattern( $image_url_pattern, $product->get_sku() );
		}

		// Validate URL
		if ( ! filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
			$this->logger->log_error(
				sprintf( 'Invalid image URL for product SKU %s', $product->get_sku() ),
				new \Exception( 'Invalid URL: ' . $image_url ),
				array(
					'product_id' => $product->get_id(),
					'sku'        => $product->get_sku(),
					'image_url'  => $image_url,
				)
			);
			return false;
		}

		// Download and attach image
		$attachment_id = $this->download_and_attach_image( $image_url, $product->get_id() );

		if ( $attachment_id ) {
			$product->set_image_id( $attachment_id );
			return true;
		}

		return false;
	}

	/**
	 * Process image URL pattern
	 *
	 * Replaces {sku} placeholder in the pattern with the actual product SKU.
	 *
	 * @param string $pattern Image URL pattern with {sku} placeholder.
	 * @param string $sku Product SKU.
	 * @return string Processed image URL.
	 */
	private function process_image_url_pattern( string $pattern, string $sku ): string {
		return str_replace( '{sku}', $sku, $pattern );
	}

	/**
	 * Download and attach image to product
	 *
	 * Downloads an image from a URL and uploads it to WordPress media library,
	 * then attaches it to the specified product.
	 *
	 * @param string $image_url Image URL to download.
	 * @param int    $product_id WooCommerce product ID.
	 * @return int|false Attachment ID on success, false on failure.
	 */
	private function download_and_attach_image( string $image_url, int $product_id ) {
		// Check if image already exists for this product
		$existing_image_id = get_post_thumbnail_id( $product_id );
		if ( $existing_image_id ) {
			$existing_url = wp_get_attachment_url( $existing_image_id );
			// If the same URL is already attached, skip download
			if ( $existing_url === $image_url ) {
				return $existing_image_id;
			}
		}

		// Download image using WordPress HTTP API
		$response = wp_remote_get(
			$image_url,
			array(
				'timeout' => 30,
				'sslverify' => true,
			)
		);

		// Check for errors
		if ( is_wp_error( $response ) ) {
			$this->logger->log_error(
				sprintf( 'Failed to download image for product ID %d', $product_id ),
				new \Exception( $response->get_error_message() ),
				array(
					'product_id' => $product_id,
					'image_url'  => $image_url,
				)
			);
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code !== 200 ) {
			$this->logger->log_error(
				sprintf( 'Failed to download image for product ID %d: HTTP %d', $product_id, $response_code ),
				new \Exception( 'HTTP error: ' . $response_code ),
				array(
					'product_id'    => $product_id,
					'image_url'     => $image_url,
					'response_code' => $response_code,
				)
			);
			return false;
		}

		// Get image data
		$image_data = wp_remote_retrieve_body( $response );
		if ( empty( $image_data ) ) {
			$this->logger->log_error(
				sprintf( 'Empty image data for product ID %d', $product_id ),
				new \Exception( 'Empty response body' ),
				array(
					'product_id' => $product_id,
					'image_url'  => $image_url,
				)
			);
			return false;
		}

		// Get filename from URL
		$filename = basename( parse_url( $image_url, PHP_URL_PATH ) );
		if ( empty( $filename ) ) {
			$filename = 'product-image-' . $product_id . '.jpg';
		}

		// Upload to WordPress media library
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Create temporary file
		$upload = wp_upload_bits( $filename, null, $image_data );

		if ( $upload['error'] ) {
			$this->logger->log_error(
				sprintf( 'Failed to upload image for product ID %d', $product_id ),
				new \Exception( $upload['error'] ),
				array(
					'product_id' => $product_id,
					'image_url'  => $image_url,
					'filename'   => $filename,
				)
			);
			return false;
		}

		// Prepare attachment data
		$file_path = $upload['file'];
		$file_type = wp_check_filetype( $filename, null );

		$attachment = array(
			'post_mime_type' => $file_type['type'],
			'post_title'     => sanitize_file_name( pathinfo( $filename, PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		// Insert attachment
		$attachment_id = wp_insert_attachment( $attachment, $file_path, $product_id );

		if ( is_wp_error( $attachment_id ) ) {
			$this->logger->log_error(
				sprintf( 'Failed to create attachment for product ID %d', $product_id ),
				new \Exception( $attachment_id->get_error_message() ),
				array(
					'product_id' => $product_id,
					'image_url'  => $image_url,
					'file_path'  => $file_path,
				)
			);
			return false;
		}

		// Generate attachment metadata
		$attach_data = wp_generate_attachment_metadata( $attachment_id, $file_path );
		wp_update_attachment_metadata( $attachment_id, $attach_data );

		return $attachment_id;
	}
}
