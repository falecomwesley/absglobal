<?php
/**
 * Advanced Tab Template
 *
 * @package ABSLoja\ProtheusConnector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$batch_size      = get_option( 'absloja_protheus_batch_size', 50 );
$retry_interval  = get_option( 'absloja_protheus_retry_interval', 3600 );
$max_retries     = get_option( 'absloja_protheus_max_retries', 5 );
$log_retention   = get_option( 'absloja_protheus_log_retention', 30 );
$webhook_token   = get_option( 'absloja_protheus_webhook_token', '' );
$webhook_secret  = get_option( 'absloja_protheus_webhook_secret', '' );
$image_url_pattern = get_option( 'absloja_protheus_image_url_pattern', '' );
$endpoint_orders_create  = get_option( 'absloja_protheus_endpoint_orders_create', '' );
$endpoint_orders_status  = get_option( 'absloja_protheus_endpoint_orders_status', '' );
$endpoint_orders_cancel  = get_option( 'absloja_protheus_endpoint_orders_cancel', '' );
$endpoint_orders_refund  = get_option( 'absloja_protheus_endpoint_orders_refund', '' );
$endpoint_customers      = get_option( 'absloja_protheus_endpoint_customers', '' );
$endpoint_products       = get_option( 'absloja_protheus_endpoint_products', '' );
$endpoint_product_by_sku = get_option( 'absloja_protheus_endpoint_product_by_sku', '' );
$endpoint_stock          = get_option( 'absloja_protheus_endpoint_stock', '' );
$endpoint_health         = get_option( 'absloja_protheus_endpoint_health', '' );
$customer_document_param = get_option( 'absloja_protheus_customer_document_param', 'cgc' );
$company_param           = get_option( 'absloja_protheus_company_param', '' );
$company_value           = get_option( 'absloja_protheus_company_value', '' );
$branch_param            = get_option( 'absloja_protheus_branch_param', '' );
$branch_value            = get_option( 'absloja_protheus_branch_value', '' );
?>

<form method="post" action="options.php">
	<?php
	settings_fields( 'absloja_protheus_advanced' );
	do_settings_sections( 'absloja_protheus_advanced' );
	?>

	<h3><?php esc_html_e( 'Performance Settings', 'absloja-protheus-connector' ); ?></h3>

	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="absloja_protheus_batch_size"><?php esc_html_e( 'Batch Size', 'absloja-protheus-connector' ); ?></label>
			</th>
			<td>
				<input type="number" 
					   id="absloja_protheus_batch_size" 
					   name="absloja_protheus_batch_size" 
					   value="<?php echo esc_attr( $batch_size ); ?>" 
					   min="10" 
					   max="200" 
					   class="small-text">
				<p class="description">
					<?php esc_html_e( 'Number of products to sync per batch (10-200)', 'absloja-protheus-connector' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<h3><?php esc_html_e( 'Retry Settings', 'absloja-protheus-connector' ); ?></h3>

	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="absloja_protheus_retry_interval"><?php esc_html_e( 'Retry Interval', 'absloja-protheus-connector' ); ?></label>
			</th>
			<td>
				<input type="number" 
					   id="absloja_protheus_retry_interval" 
					   name="absloja_protheus_retry_interval" 
					   value="<?php echo esc_attr( $retry_interval ); ?>" 
					   min="300" 
					   max="86400" 
					   class="small-text">
				<p class="description">
					<?php esc_html_e( 'Seconds between retry attempts (300-86400)', 'absloja-protheus-connector' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="absloja_protheus_max_retries"><?php esc_html_e( 'Maximum Retries', 'absloja-protheus-connector' ); ?></label>
			</th>
			<td>
				<input type="number" 
					   id="absloja_protheus_max_retries" 
					   name="absloja_protheus_max_retries" 
					   value="<?php echo esc_attr( $max_retries ); ?>" 
					   min="1" 
					   max="10" 
					   class="small-text">
				<p class="description">
					<?php esc_html_e( 'Maximum number of retry attempts (1-10)', 'absloja-protheus-connector' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<h3><?php esc_html_e( 'Log Settings', 'absloja-protheus-connector' ); ?></h3>

	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="absloja_protheus_log_retention"><?php esc_html_e( 'Log Retention', 'absloja-protheus-connector' ); ?></label>
			</th>
			<td>
				<input type="number" 
					   id="absloja_protheus_log_retention" 
					   name="absloja_protheus_log_retention" 
					   value="<?php echo esc_attr( $log_retention ); ?>" 
					   min="7" 
					   max="365" 
					   class="small-text">
				<p class="description">
					<?php esc_html_e( 'Days to keep logs before automatic cleanup (7-365)', 'absloja-protheus-connector' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<h3><?php esc_html_e( 'Webhook Settings', 'absloja-protheus-connector' ); ?></h3>

	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="absloja_protheus_webhook_token"><?php esc_html_e( 'Webhook Token', 'absloja-protheus-connector' ); ?></label>
			</th>
			<td>
				<input type="text" 
					   id="absloja_protheus_webhook_token" 
					   name="absloja_protheus_webhook_token" 
					   value="<?php echo esc_attr( $webhook_token ); ?>" 
					   class="regular-text">
				<button type="button" id="generate-webhook-token" class="button">
					<?php esc_html_e( 'Generate', 'absloja-protheus-connector' ); ?>
				</button>
				<p class="description">
					<?php esc_html_e( 'Token for webhook authentication (X-Protheus-Token header)', 'absloja-protheus-connector' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="absloja_protheus_webhook_secret"><?php esc_html_e( 'Webhook Secret', 'absloja-protheus-connector' ); ?></label>
			</th>
			<td>
				<input type="text" 
					   id="absloja_protheus_webhook_secret" 
					   name="absloja_protheus_webhook_secret" 
					   value="<?php echo esc_attr( $webhook_secret ); ?>" 
					   class="regular-text">
				<button type="button" id="generate-webhook-secret" class="button">
					<?php esc_html_e( 'Generate', 'absloja-protheus-connector' ); ?>
				</button>
				<p class="description">
					<?php esc_html_e( 'Secret for HMAC signature authentication (X-Protheus-Signature header)', 'absloja-protheus-connector' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<?php esc_html_e( 'Webhook Endpoints', 'absloja-protheus-connector' ); ?>
			</th>
			<td>
				<p>
					<strong><?php esc_html_e( 'Order Status:', 'absloja-protheus-connector' ); ?></strong><br>
					<code><?php echo esc_html( rest_url( 'absloja-protheus/v1/webhook/order-status' ) ); ?></code>
				</p>
				<p>
					<strong><?php esc_html_e( 'Stock Update:', 'absloja-protheus-connector' ); ?></strong><br>
					<code><?php echo esc_html( rest_url( 'absloja-protheus/v1/webhook/stock' ) ); ?></code>
				</p>
			</td>
		</tr>
	</table>

	<h3><?php esc_html_e( 'Image Settings', 'absloja-protheus-connector' ); ?></h3>

	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="absloja_protheus_image_url_pattern"><?php esc_html_e( 'Image URL Pattern', 'absloja-protheus-connector' ); ?></label>
			</th>
			<td>
				<input type="text" 
					   id="absloja_protheus_image_url_pattern" 
					   name="absloja_protheus_image_url_pattern" 
					   value="<?php echo esc_attr( $image_url_pattern ); ?>" 
					   class="regular-text"
					   placeholder="https://cdn.example.com/products/{sku}.jpg">
				<p class="description">
					<?php esc_html_e( 'URL pattern for product images. Use {sku} as placeholder for product SKU.', 'absloja-protheus-connector' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<h3><?php esc_html_e( 'TOTVS Contract Overrides', 'absloja-protheus-connector' ); ?></h3>

	<table class="form-table">
		<tr>
			<th scope="row"><label for="absloja_protheus_endpoint_orders_create"><?php esc_html_e( 'Order Create Endpoint', 'absloja-protheus-connector' ); ?></label></th>
			<td><input type="text" id="absloja_protheus_endpoint_orders_create" name="absloja_protheus_endpoint_orders_create" value="<?php echo esc_attr( $endpoint_orders_create ); ?>" class="regular-text" placeholder="api/ecommerce/v1/retailSalesOrders"></td>
		</tr>
		<tr>
			<th scope="row"><label for="absloja_protheus_endpoint_orders_status"><?php esc_html_e( 'Order Status Endpoint', 'absloja-protheus-connector' ); ?></label></th>
			<td><input type="text" id="absloja_protheus_endpoint_orders_status" name="absloja_protheus_endpoint_orders_status" value="<?php echo esc_attr( $endpoint_orders_status ); ?>" class="regular-text" placeholder="api/ecommerce/v1/orderChangeStatus"></td>
		</tr>
		<tr>
			<th scope="row"><label for="absloja_protheus_endpoint_orders_cancel"><?php esc_html_e( 'Order Cancel Endpoint', 'absloja-protheus-connector' ); ?></label></th>
			<td><input type="text" id="absloja_protheus_endpoint_orders_cancel" name="absloja_protheus_endpoint_orders_cancel" value="<?php echo esc_attr( $endpoint_orders_cancel ); ?>" class="regular-text" placeholder="api/ecommerce/v1/orderChangeStatus"></td>
		</tr>
		<tr>
			<th scope="row"><label for="absloja_protheus_endpoint_orders_refund"><?php esc_html_e( 'Order Refund Endpoint', 'absloja-protheus-connector' ); ?></label></th>
			<td><input type="text" id="absloja_protheus_endpoint_orders_refund" name="absloja_protheus_endpoint_orders_refund" value="<?php echo esc_attr( $endpoint_orders_refund ); ?>" class="regular-text" placeholder="api/ecommerce/v1/orderChangeStatus"></td>
		</tr>
		<tr>
			<th scope="row"><label for="absloja_protheus_endpoint_customers"><?php esc_html_e( 'Customers Endpoint', 'absloja-protheus-connector' ); ?></label></th>
			<td><input type="text" id="absloja_protheus_endpoint_customers" name="absloja_protheus_endpoint_customers" value="<?php echo esc_attr( $endpoint_customers ); ?>" class="regular-text" placeholder="api/v1/customers"></td>
		</tr>
		<tr>
			<th scope="row"><label for="absloja_protheus_endpoint_products"><?php esc_html_e( 'Products Endpoint', 'absloja-protheus-connector' ); ?></label></th>
			<td><input type="text" id="absloja_protheus_endpoint_products" name="absloja_protheus_endpoint_products" value="<?php echo esc_attr( $endpoint_products ); ?>" class="regular-text" placeholder="api/ecommerce/v1/retailItem"></td>
		</tr>
		<tr>
			<th scope="row"><label for="absloja_protheus_endpoint_product_by_sku"><?php esc_html_e( 'Product By SKU Endpoint', 'absloja-protheus-connector' ); ?></label></th>
			<td><input type="text" id="absloja_protheus_endpoint_product_by_sku" name="absloja_protheus_endpoint_product_by_sku" value="<?php echo esc_attr( $endpoint_product_by_sku ); ?>" class="regular-text" placeholder="api/ecommerce/v1/retailItem/{sku}"></td>
		</tr>
		<tr>
			<th scope="row"><label for="absloja_protheus_endpoint_stock"><?php esc_html_e( 'Stock Endpoint', 'absloja-protheus-connector' ); ?></label></th>
			<td><input type="text" id="absloja_protheus_endpoint_stock" name="absloja_protheus_endpoint_stock" value="<?php echo esc_attr( $endpoint_stock ); ?>" class="regular-text" placeholder="api/ecommerce/v1/stock-product"></td>
		</tr>
		<tr>
			<th scope="row"><label for="absloja_protheus_endpoint_health"><?php esc_html_e( 'Health Endpoint', 'absloja-protheus-connector' ); ?></label></th>
			<td><input type="text" id="absloja_protheus_endpoint_health" name="absloja_protheus_endpoint_health" value="<?php echo esc_attr( $endpoint_health ); ?>" class="regular-text" placeholder="api/v1/health"></td>
		</tr>
		<tr>
			<th scope="row"><label for="absloja_protheus_customer_document_param"><?php esc_html_e( 'Customer Document Param', 'absloja-protheus-connector' ); ?></label></th>
			<td><input type="text" id="absloja_protheus_customer_document_param" name="absloja_protheus_customer_document_param" value="<?php echo esc_attr( $customer_document_param ); ?>" class="regular-text" placeholder="cgc"></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Context Params', 'absloja-protheus-connector' ); ?></th>
			<td>
				<p><input type="text" name="absloja_protheus_company_param" value="<?php echo esc_attr( $company_param ); ?>" class="regular-text" placeholder="companyId"> <input type="text" name="absloja_protheus_company_value" value="<?php echo esc_attr( $company_value ); ?>" class="regular-text" placeholder="01"></p>
				<p><input type="text" name="absloja_protheus_branch_param" value="<?php echo esc_attr( $branch_param ); ?>" class="regular-text" placeholder="branchId"> <input type="text" name="absloja_protheus_branch_value" value="<?php echo esc_attr( $branch_value ); ?>" class="regular-text" placeholder="01"></p>
				<p class="description"><?php esc_html_e( 'Optional query parameters appended to list/search endpoints.', 'absloja-protheus-connector' ); ?></p>
			</td>
		</tr>
	</table>

	<?php submit_button(); ?>
</form>

<hr>

<h3><?php esc_html_e( 'Retry Queue', 'absloja-protheus-connector' ); ?></h3>
<p class="description">
	<?php esc_html_e( 'Operations that failed and are scheduled for retry', 'absloja-protheus-connector' ); ?>
</p>

<?php if ( ! empty( $pending_retries ) ) : ?>
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Operation', 'absloja-protheus-connector' ); ?></th>
				<th><?php esc_html_e( 'Attempts', 'absloja-protheus-connector' ); ?></th>
				<th><?php esc_html_e( 'Next Attempt', 'absloja-protheus-connector' ); ?></th>
				<th><?php esc_html_e( 'Last Error', 'absloja-protheus-connector' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'absloja-protheus-connector' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $pending_retries as $retry ) : ?>
				<tr>
					<td><?php echo esc_html( $retry['operation_type'] ); ?></td>
					<td><?php echo esc_html( $retry['attempts'] . '/' . $retry['max_attempts'] ); ?></td>
					<td><?php echo esc_html( $retry['next_attempt'] ); ?></td>
					<td><?php echo esc_html( wp_trim_words( $retry['last_error'], 10 ) ); ?></td>
					<td>
						<button type="button" 
								class="button button-small retry-now" 
								data-retry-id="<?php echo esc_attr( $retry['id'] ); ?>">
							<?php esc_html_e( 'Retry Now', 'absloja-protheus-connector' ); ?>
						</button>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php else : ?>
	<p><?php esc_html_e( 'No pending retries', 'absloja-protheus-connector' ); ?></p>
<?php endif; ?>

<script>
jQuery(document).ready(function($) {
	// Generate webhook token
	$('#generate-webhook-token').on('click', function() {
		var token = generateRandomString(32);
		$('#absloja_protheus_webhook_token').val(token);
	});

	// Generate webhook secret
	$('#generate-webhook-secret').on('click', function() {
		var secret = generateRandomString(64);
		$('#absloja_protheus_webhook_secret').val(secret);
	});

	function generateRandomString(length) {
		var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
		var result = '';
		for (var i = 0; i < length; i++) {
			result += chars.charAt(Math.floor(Math.random() * chars.length));
		}
		return result;
	}
});
</script>
