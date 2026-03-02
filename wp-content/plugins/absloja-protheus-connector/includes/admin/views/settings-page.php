<?php
/**
 * Settings Page Template
 *
 * @package ABSLoja\ProtheusConnector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap absloja-protheus-settings">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<nav class="nav-tab-wrapper">
		<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
			<a href="?page=absloja-protheus-connector&tab=<?php echo esc_attr( $tab_key ); ?>" 
			   class="nav-tab <?php echo $current_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $tab_label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="tab-content">
		<?php
		switch ( $current_tab ) {
			case 'connection':
				$this->render_connection_tab();
				break;
			case 'mappings':
				$this->render_mappings_tab();
				break;
			case 'schedule':
				$this->render_schedule_tab();
				break;
			case 'logs':
				$this->render_logs_tab();
				break;
			case 'advanced':
				$this->render_advanced_tab();
				break;
			case 'api-docs':
				$this->render_api_docs_tab();
				break;
			default:
				$this->render_connection_tab();
		}
		?>
	</div>
</div>
