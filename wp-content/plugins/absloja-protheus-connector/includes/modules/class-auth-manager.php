<?php
/**
 * Auth Manager Class
 *
 * Manages authentication with Protheus REST API supporting both Basic Auth and OAuth2.
 *
 * @package ABSLoja\ProtheusConnector\Modules
 * @since 1.0.0
 */

namespace ABSLoja\ProtheusConnector\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Auth_Manager
 *
 * Handles authentication with Protheus API including credential storage,
 * header generation, connection testing, and OAuth2 token management.
 *
 * @since 1.0.0
 */
class Auth_Manager {

	/**
	 * Authentication configuration
	 *
	 * @var array
	 */
	private $config;

	/**
	 * WordPress options prefix
	 *
	 * @var string
	 */
	private const OPTION_PREFIX = 'absloja_protheus_';

	/**
	 * Constructor
	 *
	 * @param array $config Authentication configuration.
	 *                      - auth_type: 'basic' or 'oauth2'
	 *                      - api_url: Base URL of Protheus API
	 *                      - username: Username for Basic Auth
	 *                      - password: Password for Basic Auth
	 *                      - client_id: Client ID for OAuth2
	 *                      - client_secret: Client Secret for OAuth2
	 *                      - token_endpoint: Token endpoint for OAuth2
	 */
	public function __construct( array $config ) {
		$this->config = $config;
	}

	/**
	 * Get authentication headers for API requests
	 *
	 * Returns appropriate headers based on configured auth type.
	 * For Basic Auth: Authorization header with base64 encoded credentials.
	 * For OAuth2: Authorization header with Bearer token.
	 *
	 * @return array Authentication headers
	 */
	public function get_auth_headers(): array {
		$auth_type = $this->config['auth_type'] ?? 'basic';

		if ( 'oauth2' === $auth_type ) {
			return $this->get_oauth2_headers();
		}

		return $this->get_basic_auth_headers();
	}

	/**
	 * Get Basic Authentication headers
	 *
	 * @return array Headers with Basic Auth
	 */
	private function get_basic_auth_headers(): array {
		$username = $this->config['username'] ?? '';
		$password = $this->config['password'] ?? '';

		if ( empty( $username ) || empty( $password ) ) {
			return array();
		}

		$credentials = base64_encode( $username . ':' . $password );

		return array(
			'Authorization' => 'Basic ' . $credentials,
			'Content-Type'  => 'application/json',
		);
	}

	/**
	 * Get OAuth2 Bearer token headers
	 *
	 * Retrieves stored access token or refreshes if expired.
	 *
	 * @return array Headers with Bearer token
	 */
	private function get_oauth2_headers(): array {
		$access_token = $this->get_valid_access_token();

		if ( empty( $access_token ) ) {
			return array();
		}

		return array(
			'Authorization' => 'Bearer ' . $access_token,
			'Content-Type'  => 'application/json',
		);
	}

	/**
	 * Get valid access token
	 *
	 * Returns stored token if valid, or refreshes if expired.
	 *
	 * @return string|null Access token or null if unavailable
	 */
	private function get_valid_access_token(): ?string {
		$access_token = $this->get_stored_option( 'access_token' );
		$token_expires = get_option( self::OPTION_PREFIX . 'token_expires', 0 );

		// Check if token exists and is not expired
		if ( ! empty( $access_token ) && time() < $token_expires ) {
			return $access_token;
		}

		// Token expired or doesn't exist, try to refresh
		if ( $this->refresh_token() ) {
			return $this->get_stored_option( 'access_token' );
		}

		return null;
	}

	/**
	 * Test connection to Protheus API
	 *
	 * Attempts to connect to the API and validate credentials.
	 *
	 * @return bool True if connection successful, false otherwise
	 */
	public function test_connection(): bool {
		$api_url = $this->config['api_url'] ?? '';

		if ( empty( $api_url ) ) {
			return false;
		}

		$headers = $this->get_auth_headers();

		if ( empty( $headers ) ) {
			return false;
		}

		// Test endpoint - typically a health check or simple GET endpoint
		$test_url = trailingslashit( $api_url ) . 'api/v1/health';

		$response = wp_remote_get(
			$test_url,
			array(
				'headers' => $headers,
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		// Consider 200-299 as successful
		return $status_code >= 200 && $status_code < 300;
	}

	/**
	 * Refresh OAuth2 access token
	 *
	 * Requests a new access token from the token endpoint.
	 *
	 * @return bool True if token refreshed successfully, false otherwise
	 */
	public function refresh_token(): bool {
		$auth_type = $this->config['auth_type'] ?? 'basic';

		// Only applicable for OAuth2
		if ( 'oauth2' !== $auth_type ) {
			return false;
		}

		$client_id     = $this->config['client_id'] ?? '';
		$client_secret = $this->config['client_secret'] ?? '';
		$token_endpoint = $this->config['token_endpoint'] ?? '';
		$api_url       = $this->config['api_url'] ?? '';

		if ( empty( $client_id ) || empty( $client_secret ) || empty( $token_endpoint ) ) {
			return false;
		}

		$token_url = trailingslashit( $api_url ) . ltrim( $token_endpoint, '/' );

		$response = wp_remote_post(
			$token_url,
			array(
				'headers' => array(
					'Content-Type' => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'grant_type'    => 'client_credentials',
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code !== 200 ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( empty( $data['access_token'] ) ) {
			return false;
		}

		// Store access token securely
		$this->store_option( 'access_token', $data['access_token'] );

		// Calculate and store expiration time (default 3600 seconds if not provided)
		$expires_in = $data['expires_in'] ?? 3600;
		$expires_at = time() + $expires_in - 60; // Subtract 60 seconds as buffer

		update_option( self::OPTION_PREFIX . 'token_expires', $expires_at );

		return true;
	}

	/**
	 * Check if currently authenticated
	 *
	 * Verifies if valid credentials/token are available.
	 *
	 * @return bool True if authenticated, false otherwise
	 */
	public function is_authenticated(): bool {
		$auth_type = $this->config['auth_type'] ?? 'basic';

		if ( 'oauth2' === $auth_type ) {
			$access_token = $this->get_valid_access_token();
			return ! empty( $access_token );
		}

		// For Basic Auth, check if credentials are configured
		$username = $this->config['username'] ?? '';
		$password = $this->config['password'] ?? '';

		return ! empty( $username ) && ! empty( $password );
	}

	/**
	 * Save credentials securely
	 *
	 * Stores authentication credentials in WordPress options with encryption.
	 *
	 * @param array $credentials Credentials to save.
	 *                          For Basic Auth: username, password
	 *                          For OAuth2: client_id, client_secret
	 * @return bool True on success, false on failure
	 */
	public function save_credentials( array $credentials ): bool {
		$auth_type = $this->config['auth_type'] ?? 'basic';
		$success   = true;

		if ( 'oauth2' === $auth_type ) {
			// Save OAuth2 credentials
			if ( isset( $credentials['client_id'] ) ) {
				$success = $this->store_option( 'client_id', $credentials['client_id'] ) && $success;
			}
			if ( isset( $credentials['client_secret'] ) ) {
				$success = $this->store_option( 'client_secret', $credentials['client_secret'] ) && $success;
			}
		} else {
			// Save Basic Auth credentials
			if ( isset( $credentials['username'] ) ) {
				$success = $this->store_option( 'username', $credentials['username'] ) && $success;
			}
			if ( isset( $credentials['password'] ) ) {
				$success = $this->store_option( 'password', $credentials['password'] ) && $success;
			}
		}

		// Save common settings
		if ( isset( $credentials['api_url'] ) ) {
			$success = update_option( self::OPTION_PREFIX . 'api_url', $credentials['api_url'] ) && $success;
		}
		if ( isset( $credentials['auth_type'] ) ) {
			$success = update_option( self::OPTION_PREFIX . 'auth_type', $credentials['auth_type'] ) && $success;
		}
		if ( isset( $credentials['token_endpoint'] ) ) {
			$success = update_option( self::OPTION_PREFIX . 'token_endpoint', $credentials['token_endpoint'] ) && $success;
		}

		return $success;
	}

	/**
	 * Load credentials from storage
	 *
	 * Retrieves and decrypts stored credentials from WordPress options.
	 *
	 * @return array Decrypted credentials
	 */
	public function load_credentials(): array {
		$auth_type = get_option( self::OPTION_PREFIX . 'auth_type', 'basic' );
		$credentials = array(
			'auth_type' => $auth_type,
			'api_url'   => get_option( self::OPTION_PREFIX . 'api_url', '' ),
		);

		if ( 'oauth2' === $auth_type ) {
			// Load OAuth2 credentials
			$credentials['client_id']     = $this->get_stored_option( 'client_id' );
			$credentials['client_secret'] = $this->get_stored_option( 'client_secret' );
			$credentials['token_endpoint'] = get_option( self::OPTION_PREFIX . 'token_endpoint', '' );
		} else {
			// Load Basic Auth credentials
			$credentials['username'] = $this->get_stored_option( 'username' );
			$credentials['password'] = $this->get_stored_option( 'password' );
		}

		return $credentials;
	}

	/**
	 * Delete stored credentials
	 *
	 * Removes all stored credentials from WordPress options.
	 *
	 * @return bool True on success, false on failure
	 */
	public function delete_credentials(): bool {
		$success = true;

		// Delete all credential-related options
		$success = delete_option( self::OPTION_PREFIX . 'username' ) && $success;
		$success = delete_option( self::OPTION_PREFIX . 'password' ) && $success;
		$success = delete_option( self::OPTION_PREFIX . 'client_id' ) && $success;
		$success = delete_option( self::OPTION_PREFIX . 'client_secret' ) && $success;
		$success = delete_option( self::OPTION_PREFIX . 'access_token' ) && $success;
		$success = delete_option( self::OPTION_PREFIX . 'token_expires' ) && $success;
		$success = delete_option( self::OPTION_PREFIX . 'api_url' ) && $success;
		$success = delete_option( self::OPTION_PREFIX . 'auth_type' ) && $success;
		$success = delete_option( self::OPTION_PREFIX . 'token_endpoint' ) && $success;

		return $success;
	}


	/**
	 * Store option securely
	 *
	 * Encrypts sensitive data before storing in WordPress options.
	 *
	 * @param string $key Option key (without prefix)
	 * @param string $value Value to store
	 * @return bool True on success, false on failure
	 */
	private function store_option( string $key, string $value ): bool {
		$encrypted_value = $this->encrypt( $value );
		return update_option( self::OPTION_PREFIX . $key, $encrypted_value );
	}

	/**
	 * Get stored option securely
	 *
	 * Retrieves and decrypts stored option value.
	 *
	 * @param string $key Option key (without prefix)
	 * @return string|null Decrypted value or null if not found
	 */
	private function get_stored_option( string $key ): ?string {
		$encrypted_value = get_option( self::OPTION_PREFIX . $key, '' );

		if ( empty( $encrypted_value ) ) {
			return null;
		}

		return $this->decrypt( $encrypted_value );
	}

	/**
	 * Encrypt sensitive data
	 *
	 * Uses OpenSSL encryption with key derived from WordPress AUTH_KEY.
	 *
	 * @param string $data Data to encrypt
	 * @return string Encrypted data (base64 encoded)
	 */
	private function encrypt( string $data ): string {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			// Fallback: return base64 encoded if OpenSSL not available
			return base64_encode( $data );
		}

		$key = $this->get_encryption_key();
		$iv  = openssl_random_pseudo_bytes( 16 );

		$encrypted = openssl_encrypt( $data, 'AES-256-CBC', $key, 0, $iv );

		// Combine IV and encrypted data
		return base64_encode( $iv . $encrypted );
	}

	/**
	 * Decrypt sensitive data
	 *
	 * Decrypts data encrypted with encrypt() method.
	 *
	 * @param string $encrypted_data Encrypted data (base64 encoded)
	 * @return string Decrypted data
	 */
	private function decrypt( string $encrypted_data ): string {
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			// Fallback: return base64 decoded if OpenSSL not available
			return base64_decode( $encrypted_data );
		}

		$key  = $this->get_encryption_key();
		$data = base64_decode( $encrypted_data );

		// Extract IV (first 16 bytes) and encrypted content
		$iv        = substr( $data, 0, 16 );
		$encrypted = substr( $data, 16 );

		$decrypted = openssl_decrypt( $encrypted, 'AES-256-CBC', $key, 0, $iv );

		return $decrypted !== false ? $decrypted : '';
	}

	/**
	 * Get encryption key
	 *
	 * Derives encryption key from WordPress AUTH_KEY constant.
	 *
	 * @return string Encryption key
	 */
	private function get_encryption_key(): string {
		if ( defined( 'AUTH_KEY' ) && ! empty( AUTH_KEY ) ) {
			return hash( 'sha256', AUTH_KEY, true );
		}

		// Fallback key (not recommended for production)
		return hash( 'sha256', 'absloja-protheus-connector-fallback-key', true );
	}
}
