<?php
/**
 * Connection Tab Template
 *
 * @package ABSLoja\ProtheusConnector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$auth_type       = get_option( 'absloja_protheus_auth_type', 'basic' );
$api_url         = get_option( 'absloja_protheus_api_url', '' );
$username        = get_option( 'absloja_protheus_username', '' );
$password        = get_option( 'absloja_protheus_password', '' );
$client_id       = get_option( 'absloja_protheus_client_id', '' );
$client_secret   = get_option( 'absloja_protheus_client_secret', '' );
$token_endpoint  = get_option( 'absloja_protheus_token_endpoint', '/oauth2/token' );
$contract_profile = get_option( 'absloja_protheus_contract_profile', 'totvs_ecommerce_v1' );
?>

<form method="post" action="options.php">
	<?php
	settings_fields( 'absloja_protheus_connection' );
	do_settings_sections( 'absloja_protheus_connection' );
	?>

	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="absloja_protheus_api_url"><?php esc_html_e( 'API URL', 'absloja-protheus-connector' ); ?></label>
			</th>
			<td>
				<input type="url" 
					   id="absloja_protheus_api_url" 
					   name="absloja_protheus_api_url" 
					   value="<?php echo esc_attr( $api_url ); ?>" 
					   class="regular-text" 
					   required>
				<p class="description">
					<?php esc_html_e( 'Base URL of your Protheus REST API (e.g., https://protheus.example.com)', 'absloja-protheus-connector' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="absloja_protheus_auth_type"><?php esc_html_e( 'Authentication Type', 'absloja-protheus-connector' ); ?></label>
			</th>
			<td>
				<select id="absloja_protheus_auth_type" name="absloja_protheus_auth_type">
					<option value="basic" <?php selected( $auth_type, 'basic' ); ?>><?php esc_html_e( 'Basic Authentication', 'absloja-protheus-connector' ); ?></option>
					<option value="oauth2" <?php selected( $auth_type, 'oauth2' ); ?>><?php esc_html_e( 'OAuth2', 'absloja-protheus-connector' ); ?></option>
				</select>
				<p class="description">
					<?php esc_html_e( 'Select the authentication method used by your Protheus API', 'absloja-protheus-connector' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="absloja_protheus_contract_profile"><?php esc_html_e( 'API Contract Profile', 'absloja-protheus-connector' ); ?></label>
			</th>
			<td>
				<select id="absloja_protheus_contract_profile" name="absloja_protheus_contract_profile">
					<option value="totvs_ecommerce_v1" <?php selected( $contract_profile, 'totvs_ecommerce_v1' ); ?>><?php esc_html_e( 'TOTVS E-commerce v1 (Recommended)', 'absloja-protheus-connector' ); ?></option>
					<option value="custom" <?php selected( $contract_profile, 'custom' ); ?>><?php esc_html_e( 'Custom (use endpoint overrides)', 'absloja-protheus-connector' ); ?></option>
				</select>
				<p class="description">
					<?php esc_html_e( 'Select which endpoint contract the plugin should follow.', 'absloja-protheus-connector' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<div id="basic-auth-fields" style="<?php echo $auth_type === 'oauth2' ? 'display:none;' : ''; ?>">
		<h3><?php esc_html_e( 'Basic Authentication', 'absloja-protheus-connector' ); ?></h3>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="absloja_protheus_username"><?php esc_html_e( 'Username', 'absloja-protheus-connector' ); ?></label>
				</th>
				<td>
					<input type="text" 
						   id="absloja_protheus_username" 
						   name="absloja_protheus_username" 
						   value="<?php echo esc_attr( $username ); ?>" 
						   class="regular-text">
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="absloja_protheus_password"><?php esc_html_e( 'Password', 'absloja-protheus-connector' ); ?></label>
				</th>
				<td>
					<input type="password" 
						   id="absloja_protheus_password" 
						   name="absloja_protheus_password" 
						   value="<?php echo ! empty( $password ) ? '••••••••' : ''; ?>" 
						   class="regular-text"
						   placeholder="<?php esc_attr_e( 'Enter new password to change', 'absloja-protheus-connector' ); ?>">
					<p class="description">
						<?php esc_html_e( 'Password is encrypted before storage', 'absloja-protheus-connector' ); ?>
					</p>
				</td>
			</tr>
		</table>
	</div>

	<div id="oauth2-fields" style="<?php echo $auth_type === 'basic' ? 'display:none;' : ''; ?>">
		<h3><?php esc_html_e( 'OAuth2 Authentication', 'absloja-protheus-connector' ); ?></h3>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="absloja_protheus_client_id"><?php esc_html_e( 'Client ID', 'absloja-protheus-connector' ); ?></label>
				</th>
				<td>
					<input type="text" 
						   id="absloja_protheus_client_id" 
						   name="absloja_protheus_client_id" 
						   value="<?php echo esc_attr( $client_id ); ?>" 
						   class="regular-text">
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="absloja_protheus_client_secret"><?php esc_html_e( 'Client Secret', 'absloja-protheus-connector' ); ?></label>
				</th>
				<td>
					<input type="password" 
						   id="absloja_protheus_client_secret" 
						   name="absloja_protheus_client_secret" 
						   value="<?php echo ! empty( $client_secret ) ? '••••••••' : ''; ?>" 
						   class="regular-text"
						   placeholder="<?php esc_attr_e( 'Enter new secret to change', 'absloja-protheus-connector' ); ?>">
					<p class="description">
						<?php esc_html_e( 'Client secret is encrypted before storage', 'absloja-protheus-connector' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="absloja_protheus_token_endpoint"><?php esc_html_e( 'Token Endpoint', 'absloja-protheus-connector' ); ?></label>
				</th>
				<td>
					<input type="text" 
						   id="absloja_protheus_token_endpoint" 
						   name="absloja_protheus_token_endpoint" 
						   value="<?php echo esc_attr( $token_endpoint ); ?>" 
						   class="regular-text">
					<p class="description">
						<?php esc_html_e( 'OAuth2 token endpoint path (e.g., /oauth2/token)', 'absloja-protheus-connector' ); ?>
					</p>
				</td>
			</tr>
		</table>
	</div>

	<?php submit_button(); ?>
</form>

<hr>

<h3><?php esc_html_e( 'Connection Status', 'absloja-protheus-connector' ); ?></h3>
<p>
	<button type="button" id="test-connection" class="button button-secondary">
		<?php esc_html_e( 'Test Connection', 'absloja-protheus-connector' ); ?>
	</button>
	<span id="connection-status"></span>
</p>

<script>
jQuery(document).ready(function($) {
	// Toggle auth fields based on selection
	$('#absloja_protheus_auth_type').on('change', function() {
		if ($(this).val() === 'basic') {
			$('#basic-auth-fields').show();
			$('#oauth2-fields').hide();
		} else {
			$('#basic-auth-fields').hide();
			$('#oauth2-fields').show();
		}
	});
});
</script>
