<?php
/**
 * Mock Protheus Client for testing
 * 
 * @package ABSLoja\ProtheusConnector\Tests
 */

namespace ABSLoja\ProtheusConnector\Tests\Fixtures;

use ABSLoja\ProtheusConnector\API\Protheus_Client;

class ProtheusClientMock extends Protheus_Client {
    
    /**
     * Mock responses
     * 
     * @var array
     */
    private $mock_responses = [];
    
    /**
     * Request history
     * 
     * @var array
     */
    private $request_history = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        // Don't call parent constructor to avoid dependencies
    }
    
    /**
     * Set mock response for next request
     * 
     * @param array $response Response data
     */
    public function set_mock_response( $response ) {
        $this->mock_responses[] = $response;
    }
    
    /**
     * Set multiple mock responses
     * 
     * @param array $responses Array of responses
     */
    public function set_mock_responses( array $responses ) {
        $this->mock_responses = $responses;
    }
    
    /**
     * Get request history
     * 
     * @return array
     */
    public function get_request_history() {
        return $this->request_history;
    }
    
    /**
     * Get last request
     * 
     * @return array|null
     */
    public function get_last_request() {
        return end( $this->request_history ) ?: null;
    }
    
    /**
     * Clear request history
     */
    public function clear_history() {
        $this->request_history = [];
    }
    
    /**
     * Mock POST request
     * 
     * @param string $endpoint API endpoint
     * @param array  $data Request data
     * @return array
     */
    public function post( $endpoint, $data = [] ) {
        $this->request_history[] = [
            'method' => 'POST',
            'endpoint' => $endpoint,
            'data' => $data,
            'timestamp' => time(),
        ];
        
        return $this->get_next_response();
    }
    
    /**
     * Mock GET request
     * 
     * @param string $endpoint API endpoint
     * @param array  $params Query parameters
     * @return array
     */
    public function get( $endpoint, $params = [] ) {
        $this->request_history[] = [
            'method' => 'GET',
            'endpoint' => $endpoint,
            'params' => $params,
            'timestamp' => time(),
        ];
        
        return $this->get_next_response();
    }
    
    /**
     * Get next mock response
     * 
     * @return array
     */
    private function get_next_response() {
        if ( empty( $this->mock_responses ) ) {
            return [
                'success' => false,
                'status' => 500,
                'message' => 'No mock response configured',
            ];
        }
        
        $response = array_shift( $this->mock_responses );
        
        // Parse response body if it's JSON
        if ( isset( $response['body'] ) && is_string( $response['body'] ) ) {
            $body = json_decode( $response['body'], true );
            if ( json_last_error() === JSON_ERROR_NONE ) {
                $response['data'] = $body;
            }
        }
        
        return $response;
    }
    
    /**
     * Simulate successful response
     * 
     * @param array $data Response data
     * @return array
     */
    public static function success_response( $data = [] ) {
        return [
            'success' => true,
            'status' => 200,
            'data' => $data,
        ];
    }
    
    /**
     * Simulate error response
     * 
     * @param string $message Error message
     * @param int    $status HTTP status code
     * @return array
     */
    public static function error_response( $message = 'Error', $status = 400 ) {
        return [
            'success' => false,
            'status' => $status,
            'message' => $message,
        ];
    }
}
