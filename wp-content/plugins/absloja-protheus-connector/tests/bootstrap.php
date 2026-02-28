<?php
/**
 * PHPUnit Bootstrap File
 *
 * @package ABSLoja\ProtheusConnector\Tests
 */

// Load Composer autoloader
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Manually load the Auth_Manager class since PSR-4 autoloading may not work with WordPress naming conventions
require_once dirname( __DIR__ ) . '/includes/modules/class-auth-manager.php';

// Define WordPress constants for testing
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
}

if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
	define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
}

// Define authentication key for encryption tests
if ( ! defined( 'AUTH_KEY' ) ) {
	define( 'AUTH_KEY', 'test-auth-key-for-encryption-in-phpunit-tests' );
}

// Mock WP_Error class if not available
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public $errors = array();
		public $error_data = array();

		public function __construct( $code = '', $message = '', $data = '' ) {
			if ( empty( $code ) ) {
				return;
			}

			$this->errors[ $code ][] = $message;

			if ( ! empty( $data ) ) {
				$this->error_data[ $code ] = $data;
			}
		}

		public function get_error_code() {
			$codes = array_keys( $this->errors );

			if ( empty( $codes ) ) {
				return '';
			}

			return $codes[0];
		}

		public function get_error_message( $code = '' ) {
			if ( empty( $code ) ) {
				$code = $this->get_error_code();
			}

			if ( isset( $this->errors[ $code ] ) ) {
				return $this->errors[ $code ][0];
			}

			return '';
		}
	}
}

echo "PHPUnit Bootstrap loaded successfully.\n";
