<?php
/**
 * API Docs Tab
 *
 * @package ABSLoja\ProtheusConnector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$order_status_payload = array(
	'order_id'       => 'PTH-2026-000123',
	'woo_order_id'   => 1234,
	'status'         => 'approved',
	'tracking_code'  => 'BR123456789',
	'invoice_number' => 'NF-998877',
	'invoice_date'   => '2026-03-02',
);

$stock_payload = array(
	'sku'      => 'SKU-ABC-001',
	'quantity' => 48,
	'warehouse'=> '01',
);

$spec_order = array(
	'id'           => 1234,
	'number'       => '1234',
	'status'       => 'processing',
	'created_at'   => '2026-03-02T10:00:00Z',
	'updated_at'   => '2026-03-02T10:30:00Z',
	'customer'     => array(
		'id'    => 88,
		'name'  => 'Cliente Exemplo',
		'email' => 'cliente@exemplo.com',
		'document' => '12345678909',
	),
	'totals'       => array(
		'subtotal' => 199.90,
		'discount' => 0,
		'shipping' => 25,
		'total'    => 224.90,
	),
	'items'        => array(
		array(
			'sku'        => 'SKU-ABC-001',
			'name'       => 'Produto A',
			'qty'        => 2,
			'unit_price' => 99.95,
			'total'      => 199.90,
		),
	),
);

$spec_inventory_batch = array(
	'items' => array(
		array(
			'sku'      => 'SKU-ABC-001',
			'quantity' => 100,
			'warehouse'=> '01',
		),
		array(
			'sku'      => 'SKU-XYZ-010',
			'quantity' => 0,
			'warehouse'=> '01',
		),
	),
);
?>

<div class="absloja-api-docs">
	<div class="absloja-notice notice-info">
		<p><strong><?php esc_html_e( 'Internal API documentation', 'absloja-protheus-connector' ); ?></strong></p>
		<p><?php esc_html_e( 'Share this page with the client integration team. It contains active endpoints for the passive integration model.', 'absloja-protheus-connector' ); ?></p>
	</div>

	<h2><?php esc_html_e( 'Base URL', 'absloja-protheus-connector' ); ?></h2>
	<p><code><?php echo esc_html( $rest_base ); ?></code></p>

	<h2><?php esc_html_e( 'Security', 'absloja-protheus-connector' ); ?></h2>
	<ul>
		<li><?php esc_html_e( 'Recommended header: Content-Type: application/json', 'absloja-protheus-connector' ); ?></li>
		<li><?php esc_html_e( 'Webhook token header: X-Protheus-Token', 'absloja-protheus-connector' ); ?></li>
		<li><?php esc_html_e( 'Webhook signature header: X-Protheus-Signature (HMAC SHA256)', 'absloja-protheus-connector' ); ?></li>
		<li><?php echo esc_html( sprintf( __( 'Token configured: %s', 'absloja-protheus-connector' ), empty( $webhook_token ) ? 'no' : 'yes' ) ); ?></li>
		<li><?php echo esc_html( sprintf( __( 'Secret configured: %s', 'absloja-protheus-connector' ), empty( $webhook_secret ) ? 'no' : 'yes' ) ); ?></li>
	</ul>

	<h2><?php esc_html_e( 'Active Endpoints', 'absloja-protheus-connector' ); ?></h2>
	<div class="absloja-api-endpoint">
		<h3><span class="absloja-method get">GET</span> <code>/orders?updated_after=ISO8601&page=1&limit=100&status=processing</code></h3>
		<p><?php esc_html_e( 'Exports orders incrementally for client-side consumption.', 'absloja-protheus-connector' ); ?></p>
	</div>

	<div class="absloja-api-endpoint">
		<h3><span class="absloja-method get">GET</span> <code>/orders/{id}</code></h3>
		<p><?php esc_html_e( 'Returns full order details (customer, addresses, items, totals).', 'absloja-protheus-connector' ); ?></p>
	</div>

	<div class="absloja-api-endpoint">
		<h3><span class="absloja-method get">GET</span> <code>/customers/{id}</code></h3>
		<p><?php esc_html_e( 'Returns WooCommerce customer data for ERP matching.', 'absloja-protheus-connector' ); ?></p>
	</div>

	<div class="absloja-api-endpoint">
		<h3><span class="absloja-method get">GET</span> <code>/products?updated_after=ISO8601&page=1&limit=100</code></h3>
		<p><?php esc_html_e( 'Exports products incrementally (sku, stock, prices).', 'absloja-protheus-connector' ); ?></p>
	</div>

	<div class="absloja-api-endpoint">
		<h3><span class="absloja-method post">POST</span> <code>/webhook/order-status</code></h3>
		<p><?php esc_html_e( 'Updates WooCommerce order status from client/Protheus side.', 'absloja-protheus-connector' ); ?></p>
		<h4><?php esc_html_e( 'Example JSON body', 'absloja-protheus-connector' ); ?></h4>
		<pre><?php echo esc_html( wp_json_encode( $order_status_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
	</div>

	<div class="absloja-api-endpoint">
		<h3><span class="absloja-method post">POST</span> <code>/webhook/stock</code></h3>
		<p><?php esc_html_e( 'Updates WooCommerce stock by SKU from client/Protheus side.', 'absloja-protheus-connector' ); ?></p>
		<h4><?php esc_html_e( 'Example JSON body', 'absloja-protheus-connector' ); ?></h4>
		<pre><?php echo esc_html( wp_json_encode( $stock_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
	</div>

	<table class="widefat striped absloja-api-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Method', 'absloja-protheus-connector' ); ?></th>
				<th><?php esc_html_e( 'Endpoint', 'absloja-protheus-connector' ); ?></th>
					<th><?php esc_html_e( 'Purpose', 'absloja-protheus-connector' ); ?></th>
					<th><?php esc_html_e( 'Status', 'absloja-protheus-connector' ); ?></th>
				</tr>
		</thead>
		<tbody>
			<tr>
					<td><span class="absloja-method get">GET</span></td>
					<td><code>/orders?updated_after=ISO8601&page=1&limit=100</code></td>
					<td><?php esc_html_e( 'Incremental order export.', 'absloja-protheus-connector' ); ?></td>
					<td><?php esc_html_e( 'Active', 'absloja-protheus-connector' ); ?></td>
				</tr>
				<tr>
					<td><span class="absloja-method get">GET</span></td>
					<td><code>/orders/{id}</code></td>
					<td><?php esc_html_e( 'Order detail with customer + items.', 'absloja-protheus-connector' ); ?></td>
					<td><?php esc_html_e( 'Active', 'absloja-protheus-connector' ); ?></td>
				</tr>
				<tr>
					<td><span class="absloja-method get">GET</span></td>
					<td><code>/customers/{id}</code></td>
					<td><?php esc_html_e( 'Customer detail.', 'absloja-protheus-connector' ); ?></td>
					<td><?php esc_html_e( 'Active', 'absloja-protheus-connector' ); ?></td>
				</tr>
				<tr>
					<td><span class="absloja-method get">GET</span></td>
					<td><code>/products?updated_after=ISO8601&page=1&limit=100</code></td>
					<td><?php esc_html_e( 'Product export for sync.', 'absloja-protheus-connector' ); ?></td>
					<td><?php esc_html_e( 'Active', 'absloja-protheus-connector' ); ?></td>
				</tr>
				<tr>
					<td><span class="absloja-method post">POST</span></td>
					<td><code>/inventory/batch</code></td>
					<td><?php esc_html_e( 'Batch stock update.', 'absloja-protheus-connector' ); ?></td>
					<td><?php esc_html_e( 'Planned', 'absloja-protheus-connector' ); ?></td>
				</tr>
				<tr>
					<td><span class="absloja-method post">POST</span></td>
					<td><code>/webhook/order-status</code></td>
					<td><?php esc_html_e( 'Inbound order status update.', 'absloja-protheus-connector' ); ?></td>
					<td><?php esc_html_e( 'Active', 'absloja-protheus-connector' ); ?></td>
				</tr>
				<tr>
					<td><span class="absloja-method post">POST</span></td>
					<td><code>/webhook/stock</code></td>
					<td><?php esc_html_e( 'Inbound stock update by SKU.', 'absloja-protheus-connector' ); ?></td>
					<td><?php esc_html_e( 'Active', 'absloja-protheus-connector' ); ?></td>
				</tr>
			</tbody>
		</table>

	<h3><?php esc_html_e( 'Example: GET /orders/{id} response', 'absloja-protheus-connector' ); ?></h3>
	<pre><?php echo esc_html( wp_json_encode( $spec_order, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>

	<h3><?php esc_html_e( 'Example: POST /inventory/batch body', 'absloja-protheus-connector' ); ?></h3>
	<pre><?php echo esc_html( wp_json_encode( $spec_inventory_batch, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>

	<p class="description">
		<?php echo esc_html( sprintf( __( 'Generated at (UTC): %s', 'absloja-protheus-connector' ), $current_date ) ); ?>
	</p>
</div>
