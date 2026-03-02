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

// Manually load the Protheus_Client class
require_once dirname( __DIR__ ) . '/includes/api/class-protheus-client.php';

// Manually load the Customer_Sync class
require_once dirname( __DIR__ ) . '/includes/modules/class-customer-sync.php';

// Manually load the Mapping_Engine class
require_once dirname( __DIR__ ) . '/includes/modules/class-mapping-engine.php';

// Manually load the Logger class
require_once dirname( __DIR__ ) . '/includes/modules/class-logger.php';

// Manually load the Retry_Manager class
require_once dirname( __DIR__ ) . '/includes/modules/class-retry-manager.php';

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

// Mock WC_Order class if not available
if ( ! class_exists( 'WC_Order' ) ) {
	class WC_Order {
		// This is a placeholder for testing
		// Actual implementation will be mocked in tests
	}
}

// Mock wp_generate_password function if not available
if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( $length = 12, $special_chars = true, $extra_special_chars = false ) {
		$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		if ( $special_chars ) {
			$chars .= '!@#$%^&*()';
		}
		if ( $extra_special_chars ) {
			$chars .= '-_ []{}<>~`+=,.;:/?|';
		}

		$password = '';
		for ( $i = 0; $i < $length; $i++ ) {
			$password .= substr( $chars, rand( 0, strlen( $chars ) - 1 ), 1 );
		}

		return $password;
	}
}

echo "PHPUnit Bootstrap loaded successfully.\n";
