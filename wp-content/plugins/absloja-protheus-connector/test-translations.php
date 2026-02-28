<?php
/**
 * Translation Test Script
 * 
 * This script tests if translations are working correctly.
 * 
 * Access at: http://localhost:8888/absglobal/wp-content/plugins/absloja-protheus-connector/test-translations.php
 */

// Load WordPress
require_once '../../../wp-load.php';

// Check if user is admin
if ( ! current_user_can( 'manage_options' ) ) {
	die( 'You must be logged in as an administrator to run this test.' );
}

// Load plugin text domain
load_plugin_textdomain(
	'absloja-protheus-connector',
	false,
	dirname( plugin_basename( __FILE__ ) ) . '/languages/'
);

echo '<h1>Teste de Traduções - Protheus Connector</h1>';
echo '<style>
	body { font-family: Arial, sans-serif; padding: 20px; }
	.success { color: green; }
	.error { color: red; }
	table { border-collapse: collapse; width: 100%; margin: 20px 0; }
	th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
	th { background-color: #f5f5f5; font-weight: bold; }
	tr:nth-child(even) { background-color: #f9f9f9; }
</style>';

// Test translations
$translations = array(
	// Tab Names
	'Connection' => 'Conexão',
	'Mappings' => 'Mapeamentos',
	'Sync Schedule' => 'Agendamento',
	'Logs' => 'Logs',
	'Advanced' => 'Avançado',
	
	// Connection Tab
	'Test Connection' => 'Testar Conexão',
	'Connection successful!' => 'Conexão bem-sucedida!',
	'API URL' => 'URL da API',
	'Username' => 'Usuário',
	'Password' => 'Senha',
	
	// Mappings Tab
	'Payment Method Mapping' => 'Mapeamento de Formas de Pagamento',
	'Category Mapping' => 'Mapeamento de Categorias',
	'Status Mapping' => 'Mapeamento de Status',
	
	// Schedule Tab
	'Catalog Sync Frequency' => 'Frequência de Sincronização do Catálogo',
	'Stock Sync Frequency' => 'Frequência de Sincronização de Estoque',
	'Manual Sync' => 'Sincronização Manual',
	'Sync Catalog Now' => 'Sincronizar Catálogo Agora',
	'Sync Stock Now' => 'Sincronizar Estoque Agora',
	
	// Advanced Tab
	'Performance Settings' => 'Configurações de Performance',
	'Retry Settings' => 'Configurações de Retry',
	'Log Settings' => 'Configurações de Log',
	'Webhook Settings' => 'Configurações de Webhook',
	
	// General
	'Save Changes' => 'Salvar Alterações',
	'Success!' => 'Sucesso!',
	'Error:' => 'Erro:',
	'Loading...' => 'Carregando...',
);

echo '<h2>Resultados dos Testes</h2>';
echo '<table>';
echo '<tr><th>Texto Original (Inglês)</th><th>Tradução Esperada</th><th>Tradução Obtida</th><th>Status</th></tr>';

$passed = 0;
$failed = 0;

foreach ( $translations as $original => $expected ) {
	$translated = __( $original, 'absloja-protheus-connector' );
	$status = ( $translated === $expected ) ? 'success' : 'error';
	$status_text = ( $translated === $expected ) ? '✓ OK' : '✗ FALHOU';
	
	if ( $translated === $expected ) {
		$passed++;
	} else {
		$failed++;
	}
	
	echo '<tr>';
	echo '<td>' . esc_html( $original ) . '</td>';
	echo '<td>' . esc_html( $expected ) . '</td>';
	echo '<td>' . esc_html( $translated ) . '</td>';
	echo '<td class="' . $status . '">' . $status_text . '</td>';
	echo '</tr>';
}

echo '</table>';

echo '<h2>Resumo</h2>';
echo '<p class="success">✓ Testes Passados: ' . $passed . '</p>';
if ( $failed > 0 ) {
	echo '<p class="error">✗ Testes Falhados: ' . $failed . '</p>';
}

echo '<h2>Informações do Sistema</h2>';
echo '<ul>';
echo '<li><strong>Locale do WordPress:</strong> ' . get_locale() . '</li>';
echo '<li><strong>Idioma do Site:</strong> ' . get_bloginfo( 'language' ) . '</li>';
echo '<li><strong>Diretório de Traduções:</strong> ' . dirname( plugin_basename( __FILE__ ) ) . '/languages/</li>';
echo '<li><strong>Arquivo .po existe:</strong> ' . ( file_exists( __DIR__ . '/languages/absloja-protheus-connector-pt_BR.po' ) ? 'Sim' : 'Não' ) . '</li>';
echo '<li><strong>Arquivo .mo existe:</strong> ' . ( file_exists( __DIR__ . '/languages/absloja-protheus-connector-pt_BR.mo' ) ? 'Sim' : 'Não' ) . '</li>';
echo '</ul>';

echo '<hr>';
echo '<p><a href="' . admin_url( 'admin.php?page=absloja-protheus-connector' ) . '">Ir para Configurações do Plugin</a></p>';
