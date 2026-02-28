<?php
/**
 * Sync Schedule Tab Template
 *
 * @package ABSLoja\ProtheusConnector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$catalog_frequency = get_option( 'absloja_protheus_catalog_sync_frequency', '1hour' );
$stock_frequency   = get_option( 'absloja_protheus_stock_sync_frequency', '15min' );
$last_catalog_sync = get_option( 'absloja_protheus_last_catalog_sync', __( 'Never', 'absloja-protheus-connector' ) );
$last_stock_sync   = get_option( 'absloja_protheus_last_stock_sync', __( 'Never', 'absloja-protheus-connector' ) );
$products_synced   = get_option( 'absloja_protheus_products_synced', 0 );
?>

<form method="post" action="options.php">
	<?php
	settings_fields( 'absloja_protheus_schedule' );
	do_settings_sections( 'absloja_protheus_schedule' );
	?>

	<h3><?php esc_html_e( 'Automatic Sync Schedule', 'absloja-protheus-connector' ); ?></h3>

	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="absloja_protheus_catalog_sync_frequency"><?php esc_html_e( 'Catalog Sync Frequency', 'absloja-protheus-connector' ); ?></label>
			</th>
			<td>
				<select id="absloja_protheus_catalog_sync_frequency" name="absloja_protheus_catalog_sync_frequency">
					<option value="15min" <?php selected( $catalog_frequency, '15min' ); ?>><?php esc_html_e( 'Every 15 minutes', 'absloja-protheus-connector' ); ?></option>
					<option value="30min" <?php selected( $catalog_frequency, '30min' ); ?>><?php esc_html_e( 'Every 30 minutes', 'absloja-protheus-connector' ); ?></option>
					<option value="1hour" <?php selected( $catalog_frequency, '1hour' ); ?>><?php esc_html_e( 'Every hour', 'absloja-protheus-connector' ); ?></option>
					<option value="6hours" <?php selected( $catalog_frequency, '6hours' ); ?>><?php esc_html_e( 'Every 6 hours', 'absloja-protheus-connector' ); ?></option>
					<option value="12hours" <?php selected( $catalog_frequency, '12hours' ); ?>><?php esc_html_e( 'Every 12 hours', 'absloja-protheus-connector' ); ?></option>
					<option value="24hours" <?php selected( $catalog_frequency, '24hours' ); ?>><?php esc_html_e( 'Once daily', 'absloja-protheus-connector' ); ?></option>
				</select>
				<p class="description">
					<?php esc_html_e( 'How often to sync product catalog from Protheus', 'absloja-protheus-connector' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="absloja_protheus_stock_sync_frequency"><?php esc_html_e( 'Stock Sync Frequency', 'absloja-protheus-connector' ); ?></label>
			</th>
			<td>
				<select id="absloja_protheus_stock_sync_frequency" name="absloja_protheus_stock_sync_frequency">
					<option value="15min" <?php selected( $stock_frequency, '15min' ); ?>><?php esc_html_e( 'Every 15 minutes', 'absloja-protheus-connector' ); ?></option>
					<option value="30min" <?php selected( $stock_frequency, '30min' ); ?>><?php esc_html_e( 'Every 30 minutes', 'absloja-protheus-connector' ); ?></option>
					<option value="1hour" <?php selected( $stock_frequency, '1hour' ); ?>><?php esc_html_e( 'Every hour', 'absloja-protheus-connector' ); ?></option>
					<option value="6hours" <?php selected( $stock_frequency, '6hours' ); ?>><?php esc_html_e( 'Every 6 hours', 'absloja-protheus-connector' ); ?></option>
					<option value="12hours" <?php selected( $stock_frequency, '12hours' ); ?>><?php esc_html_e( 'Every 12 hours', 'absloja-protheus-connector' ); ?></option>
					<option value="24hours" <?php selected( $stock_frequency, '24hours' ); ?>><?php esc_html_e( 'Once daily', 'absloja-protheus-connector' ); ?></option>
				</select>
				<p class="description">
					<?php esc_html_e( 'How often to sync stock quantities from Protheus', 'absloja-protheus-connector' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<?php submit_button(); ?>
</form>

<hr>

<h3><?php esc_html_e( 'Sync Statistics', 'absloja-protheus-connector' ); ?></h3>
<table class="widefat">
	<tbody>
		<tr>
			<td><strong><?php esc_html_e( 'Last Catalog Sync', 'absloja-protheus-connector' ); ?></strong></td>
			<td><?php echo esc_html( $last_catalog_sync ); ?></td>
		</tr>
		<tr>
			<td><strong><?php esc_html_e( 'Last Stock Sync', 'absloja-protheus-connector' ); ?></strong></td>
			<td><?php echo esc_html( $last_stock_sync ); ?></td>
		</tr>
		<tr>
			<td><strong><?php esc_html_e( 'Products Synced', 'absloja-protheus-connector' ); ?></strong></td>
			<td><?php echo esc_html( number_format_i18n( $products_synced ) ); ?></td>
		</tr>
	</tbody>
</table>

<h3><?php esc_html_e( 'Manual Sync', 'absloja-protheus-connector' ); ?></h3>
<p class="description">
	<?php esc_html_e( 'Trigger immediate synchronization without waiting for the scheduled time', 'absloja-protheus-connector' ); ?>
</p>
<p>
	<button type="button" id="sync-catalog-now" class="button button-primary">
		<?php esc_html_e( 'Sync Catalog Now', 'absloja-protheus-connector' ); ?>
	</button>
	<button type="button" id="sync-stock-now" class="button button-primary">
		<?php esc_html_e( 'Sync Stock Now', 'absloja-protheus-connector' ); ?>
	</button>
</p>
<div id="sync-result"></div>
