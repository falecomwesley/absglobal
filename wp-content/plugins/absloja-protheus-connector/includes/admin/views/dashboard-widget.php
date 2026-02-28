<?php
/**
 * Dashboard Widget Template
 *
 * @package ABSLoja\ProtheusConnector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="absloja-protheus-dashboard-widget">
	<h4><?php esc_html_e( 'Sync Statistics', 'absloja-protheus-connector' ); ?></h4>
	<table class="widefat">
		<tbody>
			<tr>
				<td><strong><?php esc_html_e( 'Last Catalog Sync:', 'absloja-protheus-connector' ); ?></strong></td>
				<td><?php echo esc_html( $stats['last_catalog_sync'] ); ?></td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'Last Stock Sync:', 'absloja-protheus-connector' ); ?></strong></td>
				<td><?php echo esc_html( $stats['last_stock_sync'] ); ?></td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'Products Synced:', 'absloja-protheus-connector' ); ?></strong></td>
				<td><?php echo esc_html( number_format_i18n( $stats['products_synced'] ) ); ?></td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'Orders Synced:', 'absloja-protheus-connector' ); ?></strong></td>
				<td><?php echo esc_html( number_format_i18n( $stats['orders_synced'] ) ); ?></td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'Recent Errors (24h):', 'absloja-protheus-connector' ); ?></strong></td>
				<td>
					<span class="<?php echo $stats['recent_errors'] > 0 ? 'error' : 'success'; ?>">
						<?php echo esc_html( number_format_i18n( $stats['recent_errors'] ) ); ?>
					</span>
				</td>
			</tr>
		</tbody>
	</table>

	<?php if ( ! empty( $pending_retries ) ) : ?>
		<h4><?php esc_html_e( 'Pending Retries', 'absloja-protheus-connector' ); ?></h4>
		<p>
			<?php
			printf(
				/* translators: %d: number of pending retries */
				esc_html( _n( '%d operation pending retry', '%d operations pending retry', count( $pending_retries ), 'absloja-protheus-connector' ) ),
				count( $pending_retries )
			);
			?>
		</p>
	<?php endif; ?>

	<?php if ( ! empty( $pending_orders ) ) : ?>
		<h4><?php esc_html_e( 'Orders Pending Manual Review', 'absloja-protheus-connector' ); ?></h4>
		<ul>
			<?php foreach ( array_slice( $pending_orders, 0, 5 ) as $order ) : ?>
				<li>
					<a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">
						<?php
						printf(
							/* translators: %s: order number */
							esc_html__( 'Order #%s', 'absloja-protheus-connector' ),
							$order->get_order_number()
						);
						?>
					</a>
					- <?php echo esc_html( $order->get_meta( '_protheus_sync_error' ) ); ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php if ( count( $pending_orders ) > 5 ) : ?>
			<p>
				<?php
				printf(
					/* translators: %d: number of additional orders */
					esc_html__( '...and %d more', 'absloja-protheus-connector' ),
					count( $pending_orders ) - 5
				);
				?>
			</p>
		<?php endif; ?>
	<?php endif; ?>

	<p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=absloja-protheus-connector' ) ); ?>" class="button button-primary">
			<?php esc_html_e( 'View Settings', 'absloja-protheus-connector' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=absloja-protheus-connector&tab=logs' ) ); ?>" class="button">
			<?php esc_html_e( 'View Logs', 'absloja-protheus-connector' ); ?>
		</a>
	</p>
</div>
