<?php
/**
 * Abstract LLM Provider
 *
 * Base class for AI language model providers with common functionality.
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\Chat;

use WP_Error;
use Marketing_Analytics_MCP\Utils\Logger;

/**
 * Abstract base class for LLM providers
 */
abstract class Abstract_LLM_Provider implements LLM_Provider_Interface {

	/**
	 * API key
	 *
	 * @var string
	 */
	protected $api_key;

	/**
	 * Model name/identifier
	 *
	 * @var string
	 */
	protected $model;

	/**
	 * Maximum tokens for response
	 *
	 * @var int
	 */
	protected $max_tokens;

	/**
	 * Temperature for response generation
	 *
	 * @var float
	 */
	protected $temperature;

	/**
	 * Constructor
	 *
	 * @param array $config Configuration array with api_key, model, etc.
	 */
	public function __construct( $config = array() ) {
		$this->api_key     = $config['api_key'] ?? '';
		$this->model       = $config['model'] ?? $this->get_default_model();
		$this->max_tokens  = $config['max_tokens'] ?? 4096;
		$this->temperature = $config['temperature'] ?? 0.7;
	}

	/**
	 * Get default model for this provider
	 *
	 * @return string Default model identifier.
	 */
	abstract protected function get_default_model();

	/**
	 * Make API request
	 *
	 * @param string $endpoint API endpoint.
	 * @param array  $body Request body.
	 * @param array  $headers Additional headers.
	 * @return array|WP_Error Response or WP_Error.
	 */
	protected function make_api_request( $endpoint, $body, $headers = array() ) {
		$default_headers = array(
			'Content-Type' => 'application/json',
		);

		$headers = array_merge( $default_headers, $headers );

		$json_body = wp_json_encode( $body );

		// Log the request being sent
		Logger::debug( 'LLM Provider: API Request to: ' . $endpoint );
		Logger::debug( 'LLM Provider: Request body (first 2000 chars): ' . substr( $json_body, 0, 2000 ) );

		$args = array(
			'headers' => $headers,
			'body'    => $json_body,
			'timeout' => 60,
		);

		$response = wp_remote_post( $endpoint, $args );

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'api_request_failed',
				sprintf(
					/* translators: %s: Error message */
					__( 'API request failed: %s', 'marketing-analytics-chat' ),
					$response->get_error_message()
				)
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body_data   = wp_remote_retrieve_body( $response );
		$decoded     = json_decode( $body_data, true );

		// Log the response
		Logger::debug( 'LLM Provider: API Response status: ' . $status_code );
		if ( $status_code !== 200 ) {
			Logger::debug( 'LLM Provider: API Error response: ' . $body_data );
		}

		if ( $status_code !== 200 ) {
			$error_message = $decoded['error']['message'] ?? $decoded['message'] ?? __( 'Unknown error', 'marketing-analytics-chat' );
			return new WP_Error(
				'api_error',
				sprintf(
					/* translators: 1: HTTP status code, 2: Error message */
					__( 'API returned status %1$d: %2$s', 'marketing-analytics-chat' ),
					$status_code,
					$error_message
				),
				array(
					'status'   => $status_code,
					'response' => $decoded,
				)
			);
		}

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error(
				'invalid_json',
				__( 'Invalid JSON response from API', 'marketing-analytics-chat' )
			);
		}

		return $decoded;
	}

	/**
	 * Check if provider is configured
	 *
	 * @return bool True if configured.
	 */
	public function is_configured() {
		return ! empty( $this->api_key );
	}

	/**
	 * Get configuration errors
	 *
	 * @return array Array of error messages.
	 */
	public function get_configuration_errors() {
		$errors = array();

		if ( empty( $this->api_key ) ) {
			$errors[] = sprintf(
				/* translators: %s: Provider name */
				__( '%s API key is not configured', 'marketing-analytics-chat' ),
				$this->get_display_name()
			);
		}

		return $errors;
	}

	/**
	 * Convert MCP tools to provider-specific format
	 *
	 * @param array $mcp_tools MCP tool definitions.
	 * @return array Provider-specific tool definitions.
	 */
	abstract protected function convert_tools_format( $mcp_tools );

	/**
	 * Extract tool calls from provider response
	 *
	 * @param array $response Provider API response.
	 * @return array|null Tool calls if present, null otherwise.
	 */
	abstract protected function extract_tool_calls( $response );

	/**
	 * Extract text content from provider response
	 *
	 * @param array $response Provider API response.
	 * @return string Text content.
	 */
	abstract protected function extract_text_content( $response );
}
