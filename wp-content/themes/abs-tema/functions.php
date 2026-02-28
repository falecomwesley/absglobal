<?php
/**
 * ABS Tema - Functions
 * 
 * Tema filho do Hello Elementor
 * Desenvolvido por: Fale Agência Digital
 * https://faleagencia.digital
 *
 * @package ABS_Tema
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Define constantes do tema
 */
define( 'ABS_TEMA_VERSION', '1.0.0' );
define( 'ABS_TEMA_DIR', get_stylesheet_directory() );
define( 'ABS_TEMA_URI', get_stylesheet_directory_uri() );

/**
 * Enfileira os estilos do tema pai e filho
 */
function abs_tema_enqueue_styles() {
	// Estilo do tema pai
	wp_enqueue_style(
		'hello-elementor',
		get_template_directory_uri() . '/style.css',
		array(),
		wp_get_theme()->parent()->get( 'Version' )
	);

	// Estilo do tema filho
	wp_enqueue_style(
		'abs-tema',
		get_stylesheet_directory_uri() . '/style.css',
		array( 'hello-elementor' ),
		ABS_TEMA_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'abs_tema_enqueue_styles', 20 );

/**
 * Carrega o domínio de texto para traduções
 */
function abs_tema_load_textdomain() {
	load_child_theme_textdomain( 'abs-tema', get_stylesheet_directory() . '/languages' );
}
add_action( 'after_setup_theme', 'abs_tema_load_textdomain' );

/**
 * Adiciona suporte a recursos do tema
 */
function abs_tema_setup() {
	// Adiciona suporte a logo customizado
	add_theme_support( 'custom-logo', array(
		'height'      => 100,
		'width'       => 400,
		'flex-height' => true,
		'flex-width'  => true,
	) );

	// Adiciona suporte a imagem de cabeçalho
	add_theme_support( 'custom-header', array(
		'default-image' => '',
		'width'         => 1920,
		'height'        => 500,
		'flex-height'   => true,
		'flex-width'    => true,
	) );

	// Adiciona suporte a cores customizadas
	add_theme_support( 'custom-background', array(
		'default-color' => 'ffffff',
	) );
}
add_action( 'after_setup_theme', 'abs_tema_setup', 11 );

/**
 * Customiza o rodapé do tema
 */
function abs_tema_custom_footer_credits() {
	?>
	<div class="abs-footer-credits">
		<p>
			<?php
			printf(
				/* translators: 1: Site name, 2: Current year, 3: Agency name, 4: Agency URL */
				esc_html__( '© %2$s %1$s. Desenvolvido por %3$s', 'abs-tema' ),
				'<strong>' . get_bloginfo( 'name' ) . '</strong>',
				date( 'Y' ),
				'<a href="https://faleagencia.digital" target="_blank" rel="noopener">Fale Agência Digital</a>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Adiciona informações da agência no admin
 */
function abs_tema_admin_footer_text( $footer_text ) {
	$footer_text = sprintf(
		/* translators: 1: Agency name, 2: Agency URL */
		__( 'Tema desenvolvido por %s', 'abs-tema' ),
		'<a href="https://faleagencia.digital" target="_blank">Fale Agência Digital</a>'
	);
	return $footer_text;
}
add_filter( 'admin_footer_text', 'abs_tema_admin_footer_text' );

/**
 * Adiciona classes CSS customizadas ao body
 */
function abs_tema_body_classes( $classes ) {
	$classes[] = 'abs-tema';
	return $classes;
}
add_filter( 'body_class', 'abs_tema_body_classes' );

/**
 * Customizações adicionais podem ser adicionadas abaixo
 */

// Adicione suas funções customizadas aqui
