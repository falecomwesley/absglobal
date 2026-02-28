<?php
/**
 * Mappings Tab Template
 *
 * @package ABSLoja\ProtheusConnector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$payment_mapping  = get_option( 'absloja_protheus_payment_mapping', array() );
$category_mapping = get_option( 'absloja_protheus_category_mapping', array() );
$tes_rules        = get_option( 'absloja_protheus_tes_rules', array() );
$status_mapping   = get_option( 'absloja_protheus_status_mapping', array() );

// Get WooCommerce payment gateways.
$payment_gateways = WC()->payment_gateways->payment_gateways();

// Get WooCommerce categories.
$categories = get_terms(
	array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => false,
	)
);

// Brazilian states.
$states = array(
	'AC' => 'Acre',
	'AL' => 'Alagoas',
	'AP' => 'Amapá',
	'AM' => 'Amazonas',
	'BA' => 'Bahia',
	'CE' => 'Ceará',
	'DF' => 'Distrito Federal',
	'ES' => 'Espírito Santo',
	'GO' => 'Goiás',
	'MA' => 'Maranhão',
	'MT' => 'Mato Grosso',
	'MS' => 'Mato Grosso do Sul',
	'MG' => 'Minas Gerais',
	'PA' => 'Pará',
	'PB' => 'Paraíba',
	'PR' => 'Paraná',
	'PE' => 'Pernambuco',
	'PI' => 'Piauí',
	'RJ' => 'Rio de Janeiro',
	'RN' => 'Rio Grande do Norte',
	'RS' => 'Rio Grande do Sul',
	'RO' => 'Rondônia',
	'RR' => 'Roraima',
	'SC' => 'Santa Catarina',
	'SP' => 'São Paulo',
	'SE' => 'Sergipe',
	'TO' => 'Tocantins',
);
?>

<form method="post" action="options.php">
	<?php
	settings_fields( 'absloja_protheus_mappings' );
	do_settings_sections( 'absloja_protheus_mappings' );
	?>

	<h3><?php esc_html_e( 'Payment Method Mapping', 'absloja-protheus-connector' ); ?></h3>
	<p class="description">
		<?php esc_html_e( 'Map WooCommerce payment methods to Protheus payment condition codes', 'absloja-protheus-connector' ); ?>
	</p>
	<table class="widefat">
		<thead>
			<tr>
				<th><?php esc_html_e( 'WooCommerce Payment Method', 'absloja-protheus-connector' ); ?></th>
				<th><?php esc_html_e( 'Protheus Condition Code', 'absloja-protheus-connector' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $payment_gateways as $gateway ) : ?>
				<?php if ( $gateway->enabled === 'yes' ) : ?>
					<tr>
						<td><?php echo esc_html( $gateway->get_title() ); ?></td>
						<td>
							<input type="text" 
								   name="absloja_protheus_payment_mapping[<?php echo esc_attr( $gateway->id ); ?>]" 
								   value="<?php echo esc_attr( $payment_mapping[ $gateway->id ] ?? '' ); ?>" 
								   class="regular-text"
								   placeholder="<?php esc_attr_e( 'e.g., 001', 'absloja-protheus-connector' ); ?>">
						</td>
					</tr>
				<?php endif; ?>
			<?php endforeach; ?>
		</tbody>
	</table>

	<h3><?php esc_html_e( 'Category Mapping', 'absloja-protheus-connector' ); ?></h3>
	<p class="description">
		<?php esc_html_e( 'Map Protheus product groups (B1_GRUPO) to WooCommerce categories', 'absloja-protheus-connector' ); ?>
	</p>
	<div id="category-mappings">
		<?php
		if ( ! empty( $category_mapping ) ) {
			foreach ( $category_mapping as $protheus_group => $woo_category_id ) {
				?>
				<div class="category-mapping-row">
					<input type="text" 
						   name="absloja_protheus_category_mapping_keys[]" 
						   value="<?php echo esc_attr( $protheus_group ); ?>" 
						   placeholder="<?php esc_attr_e( 'Protheus Group (e.g., 01)', 'absloja-protheus-connector' ); ?>">
					<select name="absloja_protheus_category_mapping_values[]">
						<option value=""><?php esc_html_e( 'Select Category', 'absloja-protheus-connector' ); ?></option>
						<?php foreach ( $categories as $category ) : ?>
							<option value="<?php echo esc_attr( $category->term_id ); ?>" <?php selected( $woo_category_id, $category->term_id ); ?>>
								<?php echo esc_html( $category->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<button type="button" class="button remove-mapping"><?php esc_html_e( 'Remove', 'absloja-protheus-connector' ); ?></button>
				</div>
				<?php
			}
		}
		?>
	</div>
	<button type="button" id="add-category-mapping" class="button">
		<?php esc_html_e( 'Add Category Mapping', 'absloja-protheus-connector' ); ?>
	</button>

	<h3><?php esc_html_e( 'TES Rules by State', 'absloja-protheus-connector' ); ?></h3>
	<p class="description">
		<?php esc_html_e( 'Define TES (Tipo de Entrada/Saída) codes based on customer billing state', 'absloja-protheus-connector' ); ?>
	</p>
	<table class="widefat">
		<thead>
			<tr>
				<th><?php esc_html_e( 'State', 'absloja-protheus-connector' ); ?></th>
				<th><?php esc_html_e( 'TES Code', 'absloja-protheus-connector' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $states as $state_code => $state_name ) : ?>
				<tr>
					<td><?php echo esc_html( $state_name . ' (' . $state_code . ')' ); ?></td>
					<td>
						<input type="text" 
							   name="absloja_protheus_tes_rules[<?php echo esc_attr( $state_code ); ?>]" 
							   value="<?php echo esc_attr( $tes_rules[ $state_code ] ?? '' ); ?>" 
							   class="regular-text"
							   placeholder="<?php esc_attr_e( 'e.g., 501', 'absloja-protheus-connector' ); ?>">
					</td>
				</tr>
			<?php endforeach; ?>
			<tr>
				<td><strong><?php esc_html_e( 'Default (fallback)', 'absloja-protheus-connector' ); ?></strong></td>
				<td>
					<input type="text" 
						   name="absloja_protheus_tes_rules[default]" 
						   value="<?php echo esc_attr( $tes_rules['default'] ?? '' ); ?>" 
						   class="regular-text"
						   placeholder="<?php esc_attr_e( 'e.g., 502', 'absloja-protheus-connector' ); ?>">
				</td>
			</tr>
		</tbody>
	</table>

	<h3><?php esc_html_e( 'Status Mapping', 'absloja-protheus-connector' ); ?></h3>
	<p class="description">
		<?php esc_html_e( 'Map Protheus order statuses to WooCommerce order statuses', 'absloja-protheus-connector' ); ?>
	</p>
	<table class="widefat">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Protheus Status', 'absloja-protheus-connector' ); ?></th>
				<th><?php esc_html_e( 'WooCommerce Status', 'absloja-protheus-connector' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			$protheus_statuses = array(
				'pending'   => __( 'Pending', 'absloja-protheus-connector' ),
				'approved'  => __( 'Approved', 'absloja-protheus-connector' ),
				'invoiced'  => __( 'Invoiced', 'absloja-protheus-connector' ),
				'shipped'   => __( 'Shipped', 'absloja-protheus-connector' ),
				'cancelled' => __( 'Cancelled', 'absloja-protheus-connector' ),
				'rejected'  => __( 'Rejected', 'absloja-protheus-connector' ),
			);

			$wc_statuses = wc_get_order_statuses();

			foreach ( $protheus_statuses as $protheus_status => $protheus_label ) :
				?>
				<tr>
					<td><?php echo esc_html( $protheus_label ); ?></td>
					<td>
						<select name="absloja_protheus_status_mapping[<?php echo esc_attr( $protheus_status ); ?>]">
							<?php foreach ( $wc_statuses as $wc_status => $wc_label ) : ?>
								<option value="<?php echo esc_attr( str_replace( 'wc-', '', $wc_status ) ); ?>" 
										<?php selected( $status_mapping[ $protheus_status ] ?? '', str_replace( 'wc-', '', $wc_status ) ); ?>>
									<?php echo esc_html( $wc_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php submit_button(); ?>
</form>

<script>
jQuery(document).ready(function($) {
	// Add category mapping row
	$('#add-category-mapping').on('click', function() {
		var row = '<div class="category-mapping-row">' +
			'<input type="text" name="absloja_protheus_category_mapping_keys[]" placeholder="<?php esc_attr_e( 'Protheus Group (e.g., 01)', 'absloja-protheus-connector' ); ?>">' +
			'<select name="absloja_protheus_category_mapping_values[]">' +
			'<option value=""><?php esc_html_e( 'Select Category', 'absloja-protheus-connector' ); ?></option>' +
			<?php foreach ( $categories as $category ) : ?>
				'<option value="<?php echo esc_attr( $category->term_id ); ?>"><?php echo esc_js( $category->name ); ?></option>' +
			<?php endforeach; ?>
			'</select>' +
			'<button type="button" class="button remove-mapping"><?php esc_html_e( 'Remove', 'absloja-protheus-connector' ); ?></button>' +
			'</div>';
		$('#category-mappings').append(row);
	});

	// Remove category mapping row
	$(document).on('click', '.remove-mapping', function() {
		$(this).closest('.category-mapping-row').remove();
	});
});
</script>
