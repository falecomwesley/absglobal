<?php
/**
 * Logs Tab Template
 *
 * @package ABSLoja\ProtheusConnector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<h3><?php esc_html_e( 'Transaction Logs', 'absloja-protheus-connector' ); ?></h3>

<div class="tablenav top">
	<form method="get" action="">
		<input type="hidden" name="page" value="absloja-protheus-connector">
		<input type="hidden" name="tab" value="logs">

		<label for="filter-date-from"><?php esc_html_e( 'From:', 'absloja-protheus-connector' ); ?></label>
		<input type="date" 
			   id="filter-date-from" 
			   name="date_from" 
			   value="<?php echo esc_attr( $filters['date_from'] ?? '' ); ?>">

		<label for="filter-date-to"><?php esc_html_e( 'To:', 'absloja-protheus-connector' ); ?></label>
		<input type="date" 
			   id="filter-date-to" 
			   name="date_to" 
			   value="<?php echo esc_attr( $filters['date_to'] ?? '' ); ?>">

		<label for="filter-type"><?php esc_html_e( 'Type:', 'absloja-protheus-connector' ); ?></label>
		<select id="filter-type" name="type">
			<option value=""><?php esc_html_e( 'All Types', 'absloja-protheus-connector' ); ?></option>
			<option value="api_request" <?php selected( $filters['type'] ?? '', 'api_request' ); ?>><?php esc_html_e( 'API Request', 'absloja-protheus-connector' ); ?></option>
			<option value="webhook" <?php selected( $filters['type'] ?? '', 'webhook' ); ?>><?php esc_html_e( 'Webhook', 'absloja-protheus-connector' ); ?></option>
			<option value="sync" <?php selected( $filters['type'] ?? '', 'sync' ); ?>><?php esc_html_e( 'Sync', 'absloja-protheus-connector' ); ?></option>
			<option value="error" <?php selected( $filters['type'] ?? '', 'error' ); ?>><?php esc_html_e( 'Error', 'absloja-protheus-connector' ); ?></option>
		</select>

		<label for="filter-status"><?php esc_html_e( 'Status:', 'absloja-protheus-connector' ); ?></label>
		<select id="filter-status" name="status">
			<option value=""><?php esc_html_e( 'All Statuses', 'absloja-protheus-connector' ); ?></option>
			<option value="success" <?php selected( $filters['status'] ?? '', 'success' ); ?>><?php esc_html_e( 'Success', 'absloja-protheus-connector' ); ?></option>
			<option value="error" <?php selected( $filters['status'] ?? '', 'error' ); ?>><?php esc_html_e( 'Error', 'absloja-protheus-connector' ); ?></option>
			<option value="retry" <?php selected( $filters['status'] ?? '', 'retry' ); ?>><?php esc_html_e( 'Retry', 'absloja-protheus-connector' ); ?></option>
		</select>

		<button type="submit" class="button"><?php esc_html_e( 'Filter', 'absloja-protheus-connector' ); ?></button>
		<a href="?page=absloja-protheus-connector&tab=logs" class="button"><?php esc_html_e( 'Clear', 'absloja-protheus-connector' ); ?></a>
	</form>

	<button type="button" id="export-logs" class="button button-secondary" style="float:right;">
		<?php esc_html_e( 'Export to CSV', 'absloja-protheus-connector' ); ?>
	</button>
</div>

<table class="wp-list-table widefat fixed striped">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Timestamp', 'absloja-protheus-connector' ); ?></th>
			<th><?php esc_html_e( 'Type', 'absloja-protheus-connector' ); ?></th>
			<th><?php esc_html_e( 'Operation', 'absloja-protheus-connector' ); ?></th>
			<th><?php esc_html_e( 'Status', 'absloja-protheus-connector' ); ?></th>
			<th><?php esc_html_e( 'Message', 'absloja-protheus-connector' ); ?></th>
			<th><?php esc_html_e( 'Duration', 'absloja-protheus-connector' ); ?></th>
			<th><?php esc_html_e( 'Actions', 'absloja-protheus-connector' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php if ( ! empty( $logs ) ) : ?>
			<?php foreach ( $logs as $log ) : ?>
				<tr>
					<td><?php echo esc_html( $log['timestamp'] ); ?></td>
					<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $log['type'] ) ) ); ?></td>
					<td><?php echo esc_html( $log['operation'] ); ?></td>
					<td>
						<span class="status-badge status-<?php echo esc_attr( $log['status'] ); ?>">
							<?php echo esc_html( ucfirst( $log['status'] ) ); ?>
						</span>
					</td>
					<td><?php echo esc_html( wp_trim_words( $log['message'], 10 ) ); ?></td>
					<td><?php echo isset( $log['duration'] ) ? esc_html( number_format( $log['duration'], 3 ) . 's' ) : '-'; ?></td>
					<td>
						<button type="button" 
								class="button button-small view-log-details" 
								data-log-id="<?php echo esc_attr( $log['id'] ); ?>">
							<?php esc_html_e( 'View', 'absloja-protheus-connector' ); ?>
						</button>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php else : ?>
			<tr>
				<td colspan="7" style="text-align:center;">
					<?php esc_html_e( 'No logs found', 'absloja-protheus-connector' ); ?>
				</td>
			</tr>
		<?php endif; ?>
	</tbody>
</table>

<?php if ( $total_pages > 1 ) : ?>
	<div class="tablenav bottom">
		<div class="tablenav-pages">
			<?php
			echo paginate_links(
				array(
					'base'      => add_query_arg( 'paged', '%#%' ),
					'format'    => '',
					'prev_text' => __( '&laquo;', 'absloja-protheus-connector' ),
					'next_text' => __( '&raquo;', 'absloja-protheus-connector' ),
					'total'     => $total_pages,
					'current'   => $page,
				)
			);
			?>
		</div>
	</div>
<?php endif; ?>

<!-- Log Details Modal -->
<div id="log-details-modal" style="display:none;">
	<div class="log-details-content">
		<span class="close-modal">&times;</span>
		<h2><?php esc_html_e( 'Log Details', 'absloja-protheus-connector' ); ?></h2>
		<div id="log-details-body"></div>
	</div>
</div>
