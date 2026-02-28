<?php
/**
 * Protheus Client Class
 *
 * HTTP client wrapper for Protheus REST API communication.
 * Wraps WordPress wp_remote_post() and wp_remote_get() functions with
 * authentication, error handling, and JSON parsing.
 *
 * @package ABSLoja\ProtheusConnector\API
 * @since 1.0.0
 */

namespace ABSLoja\ProtheusConnector\API;

use ABSLoja\ProtheusConnector\Modules\Auth_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Protheus_Client
 *
 * Handles HTTP communication with Protheus REST API including
 * authentication, timeout management, and response parsing.
 *
 * @since 1.0.0
 */
class Protheus_Client {

	/**
	 * Auth Manager instance
	 *
	 * @var Auth_Manager
	 */
	private $auth_manager;

	/**
	 * Default timeout in seconds
	 *
	 * @var int
	 */
	private $timeout;

	/**
	 * Base API URL
	 *
	 * @var string
	 */
	private $api_url;

	/**
	 * Constructor
	 *
	 * @param Auth_Manager $auth_manager Authentication manager instance.
	 * @param string       $api_url Base URL of Protheus API.
	 * @param int          $timeout Request timeout in seconds (default: 30).
	 */
	public function __construct( Auth_Manager $auth_manager, string $api_url, int $timeout = 30 ) {
		$this->auth_manager = $auth_manager;
		$this->api_url      = trailingslashit( $api_url );
		$this->timeout      = $timeout;
	}

	/**
	 * Send POST request to Protheus API
	 *
	 * @param string $endpoint API endpoint (relative to base URL).
	 * @param array  $data Request payload data.
	 * @param int    $timeout Optional custom timeout for this request.
	 * @return array Response array with 'success', 'data', 'error', 'status_code'.
	 */
	public function post( string $endpoint, array $data, int $timeout = null ): array {
		$url = $this->api_url . ltrim( $endpoint, '/' );
		$timeout = $timeout ?? $this->timeout;

		$headers = $this->auth_manager->get_auth_headers();

		if ( empty( $headers ) ) {
			return $this->error_response( 'Authentication headers not available', 0 );
		}

		$response = wp_remote_post(
			$url,
			array(
				'headers' => $headers,
				'body'    => wp_json_encode( $data ),
				'timeout' => $timeout,
			)
		);

		return $this->process_response( $response );
	}

	/**
	 * Send GET request to Protheus API
	 *
	 * @param string $endpoint API endpoint (relative to base URL).
	 * @param array  $query_params Optional query parameters.
	 * @param int    $timeout Optional custom timeout for this request.
	 * @return array Response array with 'success', 'data', 'error', 'status_code'.
	 */
	public function get( string $endpoint, array $query_params = array(), int $timeout = null ): array {
		$url = $this->api_url . ltrim( $endpoint, '/' );
		$timeout = $timeout ?? $this->timeout;

		// Add query parameters to URL
		if ( ! empty( $query_params ) ) {
			$url = add_query_arg( $query_params, $url );
		}

		$headers = $this->auth_manager->get_auth_headers();

		if ( empty( $headers ) ) {
			return $this->error_response( 'Authentication headers not available', 0 );
		}

		$response = wp_remote_get(
			$url,
			array(
				'headers' => $headers,
				'timeout' => $timeout,
			)
		);

		return $this->process_response( $response );
	}

	/**
	 * Process HTTP response
	 *
	 * Handles error detection, status code checking, and JSON parsing.
	 *
	 * @param mixed $response WordPress HTTP API response.
	 * @return array Standardized response array.
	 */
	private function process_response( $response ): array {
		// Check for WP_Error (network errors, timeouts, etc.)
		if ( is_wp_error( $response ) ) {
			$error_code = $response->get_error_code();
			$error_type = $this->classify_network_error( $error_code );
			
			return $this->error_response(
				$response->get_error_message(),
				0,
				$error_type
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		// Parse JSON response
		$data = json_decode( $body, true );

		// Check for JSON parsing errors
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return $this->error_response(
				'Invalid JSON response: ' . json_last_error_msg(),
				$status_code,
				'json_error'
			);
		}

		// Check HTTP status code
		if ( $status_code >= 200 && $status_code < 300 ) {
			return $this->success_response( $data, $status_code );
		}

		// Handle error responses
		$error_message = $this->extract_error_message( $data, $status_code );
		$error_type    = $this->determine_error_type( $status_code );

		return $this->error_response( $error_message, $status_code, $error_type );
	}

	/**
	 * Extract error message from response data
	 *
	 * @param mixed $data Parsed response data.
	 * @param int   $status_code HTTP status code.
	 * @return string Error message.
	 */
	private function extract_error_message( $data, int $status_code ): string {
		// Try common error message fields
		if ( is_array( $data ) ) {
			if ( isset( $data['error'] ) ) {
				return is_string( $data['error'] ) ? $data['error'] : wp_json_encode( $data['error'] );
			}
			if ( isset( $data['message'] ) ) {
				return $data['message'];
			}
			if ( isset( $data['errorMessage'] ) ) {
				return $data['errorMessage'];
			}
		}

		// Fallback to generic message based on status code
		return $this->get_generic_error_message( $status_code );
	}

	/**
	 * Get generic error message based on HTTP status code
	 *
	 * @param int $status_code HTTP status code.
	 * @return string Generic error message.
	 */
	private function get_generic_error_message( int $status_code ): string {
		$messages = array(
			400 => 'Bad Request',
			401 => 'Unauthorized - Invalid credentials',
			403 => 'Forbidden - Insufficient permissions',
			404 => 'Not Found',
			408 => 'Request Timeout',
			422 => 'Unprocessable Entity - Validation error',
			429 => 'Too Many Requests',
			500 => 'Internal Server Error',
			502 => 'Bad Gateway',
			503 => 'Service Unavailable',
			504 => 'Gateway Timeout',
		);

		return $messages[ $status_code ] ?? 'HTTP Error ' . $status_code;
	}

	/**
	 * Classify network error based on WP_Error code
	 *
	 * Provides more specific error classification for network-level errors.
	 *
	 * @param string $error_code WordPress error code.
	 * @return string Classified error type.
	 */
	private function classify_network_error( string $error_code ): string {
		// Timeout errors
		if ( strpos( $error_code, 'timeout' ) !== false || 
		     strpos( $error_code, 'timed_out' ) !== false ) {
			return 'timeout_error';
		}

		// DNS resolution errors
		if ( strpos( $error_code, 'dns' ) !== false || 
		     strpos( $error_code, 'resolve' ) !== false ||
		     strpos( $error_code, 'getaddrinfo' ) !== false ) {
			return 'dns_error';
		}

		// SSL/TLS errors
		if ( strpos( $error_code, 'ssl' ) !== false || 
		     strpos( $error_code, 'tls' ) !== false ||
		     strpos( $error_code, 'certificate' ) !== false ||
		     strpos( $error_code, 'https' ) !== false ) {
			return 'ssl_error';
		}

		// Connection errors
		if ( strpos( $error_code, 'connect' ) !== false || 
		     strpos( $error_code, 'connection' ) !== false ||
		     strpos( $error_code, 'unreachable' ) !== false ) {
			return 'connection_error';
		}

		// Generic network error for unclassified cases
		return 'network_error';
	}

	/**
	 * Determine error type based on status code
	 *
	 * @param int $status_code HTTP status code.
	 * @return string Error type.
	 */
	private function determine_error_type( int $status_code ): string {
		if ( $status_code === 401 || $status_code === 403 ) {
			return 'auth_error';
		}

		if ( $status_code >= 400 && $status_code < 500 ) {
			return 'client_error';
		}

		if ( $status_code >= 500 ) {
			return 'server_error';
		}

		return 'unknown_error';
	}

	/**
	 * Create success response array
	 *
	 * @param mixed $data Response data.
	 * @param int   $status_code HTTP status code.
	 * @return array Success response.
	 */
	private function success_response( $data, int $status_code ): array {
		return array(
			'success'     => true,
			'data'        => $data,
			'error'       => null,
			'status_code' => $status_code,
			'error_type'  => null,
		);
	}

	/**
	 * Create error response array
	 *
	 * @param string $error_message Error message.
	 * @param int    $status_code HTTP status code.
	 * @param string $error_type Error type classification.
	 * @return array Error response.
	 */
	private function error_response( string $error_message, int $status_code, string $error_type = 'unknown_error' ): array {
		return array(
			'success'     => false,
			'data'        => null,
			'error'       => $error_message,
			'status_code' => $status_code,
			'error_type'  => $error_type,
		);
	}

	/**
	 * Set custom timeout for subsequent requests
	 *
	 * @param int $timeout Timeout in seconds.
	 * @return void
	 */
	public function set_timeout( int $timeout ): void {
		$this->timeout = $timeout;
	}

	/**
	 * Get current timeout setting
	 *
	 * @return int Timeout in seconds.
	 */
	public function get_timeout(): int {
		return $this->timeout;
	}

	/**
	 * Get API base URL
	 *
	 * @return string API base URL.
	 */
	public function get_api_url(): string {
		return $this->api_url;
	}
}
