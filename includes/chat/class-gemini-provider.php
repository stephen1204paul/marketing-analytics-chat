<?php
/**
 * Gemini API Provider
 *
 * LLM provider for Google's Gemini API with function calling support.
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\Chat;

use WP_Error;
use Marketing_Analytics_MCP\Utils\Logger;

/**
 * Gemini API provider
 */
class Gemini_Provider extends Abstract_LLM_Provider {

	/**
	 * Gemini API base URL
	 */
	const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models';

	/**
	 * Get provider name
	 *
	 * @return string Provider name.
	 */
	public function get_name() {
		return 'gemini';
	}

	/**
	 * Get provider display name
	 *
	 * @return string Provider display name.
	 */
	public function get_display_name() {
		return __( 'Google Gemini', 'marketing-analytics-chat' );
	}

	/**
	 * Get default model
	 *
	 * @return string Default Gemini model.
	 */
	protected function get_default_model() {
		return 'gemini-2.5-pro';
	}

	/**
	 * Send a message to Gemini and get a response
	 *
	 * @param array $messages Conversation history.
	 * @param array $tools Available MCP tools.
	 * @param array $options Additional options.
	 * @return array|WP_Error Response or WP_Error.
	 */
	public function send_message( $messages, $tools = array(), $options = array() ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error(
				'provider_not_configured',
				__( 'Gemini API is not configured', 'marketing-analytics-chat' )
			);
		}

		// Build API endpoint with model
		$model    = $options['model'] ?? $this->model;
		$endpoint = self::API_BASE . '/' . $model . ':generateContent';

		// Build request body
		$body = array(
			'contents' => $this->format_messages( $messages, $options ),
		);

		// Add generation config
		$generation_config = array();
		if ( isset( $options['temperature'] ) ) {
			$generation_config['temperature'] = $options['temperature'];
		} elseif ( $this->temperature ) {
			$generation_config['temperature'] = $this->temperature;
		}

		if ( isset( $options['max_tokens'] ) ) {
			$generation_config['maxOutputTokens'] = $options['max_tokens'];
		} elseif ( $this->max_tokens ) {
			$generation_config['maxOutputTokens'] = $this->max_tokens;
		}

		if ( ! empty( $generation_config ) ) {
			$body['generationConfig'] = $generation_config;
		}

		// Add tools if provided
		if ( ! empty( $tools ) ) {
			$converted_tools = $this->convert_tools_format( $tools );
			$body['tools']   = array(
				array(
					'functionDeclarations' => $converted_tools,
				),
			);

			// Log the tools being sent to Gemini API
			Logger::debug( 'Gemini: Sending ' . count( $converted_tools ) . ' tools to API' );
			Logger::debug( 'Gemini: First tool structure: ' . wp_json_encode( $converted_tools[0] ?? 'none' ) );
		}

		// Make API request with Gemini-specific header
		$headers = array(
			'x-goog-api-key' => $this->api_key,
		);

		$response = $this->make_api_request( $endpoint, $body, $headers );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->parse_response( $response );
	}

	/**
	 * Format messages for Gemini API
	 *
	 * @param array $messages Raw messages.
	 * @param array $options Additional options.
	 * @return array Formatted messages.
	 */
	private function format_messages( $messages, $options = array() ) {
		$formatted          = array();
		$system_instruction = $options['system'] ?? $this->get_default_system_message();

		// Gemini uses a different format: role can be 'user' or 'model'
		foreach ( $messages as $message ) {
			// Skip system messages (handled separately)
			if ( $message['role'] === 'system' ) {
				continue;
			}

			// Handle tool results
			if ( $message['role'] === 'tool' ) {
				// Tool results are part of function responses
				$tool_call_id = $message['tool_call_id'] ?? 'unknown';
				$formatted[]  = array(
					'role'  => 'function',
					'parts' => array(
						array(
							'functionResponse' => array(
								'name'     => $message['tool_name'] ?? 'unknown',
								'response' => array(
									'result' => $message['content'],
								),
							),
						),
					),
				);
				continue;
			}

			// Convert role: 'assistant' -> 'model'
			$role = $message['role'] === 'assistant' ? 'model' : $message['role'];

			// Format content
			$parts = array();

			// Handle tool calls (function calls in Gemini)
			if ( ! empty( $message['tool_calls'] ) ) {
				foreach ( $message['tool_calls'] as $tool_call ) {
					$arguments = $tool_call['arguments'] ?? $tool_call['input'] ?? array();

					$parts[] = array(
						'functionCall' => array(
							'name' => $tool_call['name'],
							'args' => $arguments,
						),
					);
				}

				// Add text content if exists
				if ( ! empty( $message['content'] ) ) {
					array_unshift(
						$parts,
						array(
							'text' => $message['content'],
						)
					);
				}
			} else {
				// Regular text message
				$parts[] = array(
					'text' => $message['content'],
				);
			}

			$formatted[] = array(
				'role'  => $role,
				'parts' => $parts,
			);
		}

		return $formatted;
	}

	/**
	 * Convert MCP tools to Gemini function calling format
	 *
	 * @param array $mcp_tools MCP tool definitions.
	 * @return array Gemini function declarations.
	 */
	protected function convert_tools_format( $mcp_tools ) {
		$gemini_tools = array();

		foreach ( $mcp_tools as $tool ) {
			// Skip tools without valid names
			$name = $tool['name'] ?? '';
			if ( empty( $name ) ) {
				continue;
			}

			// Get input schema
			$input_schema = $tool['inputSchema'] ?? array(
				'type'       => 'object',
				'properties' => new \stdClass(),
			);

			// Convert schema to Gemini format
			$parameters = $this->convert_schema_to_gemini( $input_schema );

			$gemini_tools[] = array(
				'name'        => $name,
				'description' => $tool['description'] ?? '',
				'parameters'  => $parameters,
			);
		}

		return $gemini_tools;
	}

	/**
	 * Convert JSON schema to Gemini parameter format
	 *
	 * @param array $schema JSON schema.
	 * @return array Gemini parameter schema.
	 */
	private function convert_schema_to_gemini( $schema ) {
		// Gemini uses a similar schema format but may need adjustments
		// Remove additionalProperties if present as it's not supported
		if ( isset( $schema['additionalProperties'] ) ) {
			unset( $schema['additionalProperties'] );
		}

		return $schema;
	}

	/**
	 * Parse Gemini API response
	 *
	 * @param array $response Raw API response.
	 * @return array Parsed response.
	 */
	private function parse_response( $response ) {
		$candidate = $response['candidates'][0] ?? array();
		$content   = $candidate['content'] ?? array();

		$result = array(
			'content'     => $this->extract_text_content( $response ),
			'tool_calls'  => $this->extract_tool_calls( $response ),
			'stop_reason' => $candidate['finishReason'] ?? null,
			'usage'       => $this->extract_usage( $response ),
			'raw'         => $response,
		);

		return $result;
	}

	/**
	 * Extract text content from Gemini response
	 *
	 * @param array $response Gemini API response.
	 * @return string Text content.
	 */
	protected function extract_text_content( $response ) {
		$candidate = $response['candidates'][0] ?? array();
		$content   = $candidate['content'] ?? array();
		$parts     = $content['parts'] ?? array();

		$text_parts = array();

		foreach ( $parts as $part ) {
			if ( isset( $part['text'] ) ) {
				$text_parts[] = $part['text'];
			}
		}

		return implode( "\n\n", $text_parts );
	}

	/**
	 * Extract tool calls from Gemini response
	 *
	 * @param array $response Gemini API response.
	 * @return array|null Tool calls if present.
	 */
	protected function extract_tool_calls( $response ) {
		$candidate = $response['candidates'][0] ?? array();
		$content   = $candidate['content'] ?? array();
		$parts     = $content['parts'] ?? array();

		$tool_calls = array();

		foreach ( $parts as $index => $part ) {
			if ( isset( $part['functionCall'] ) ) {
				$function_call = $part['functionCall'];

				$tool_calls[] = array(
					'id'        => 'call_' . uniqid(), // Gemini doesn't provide IDs, so we generate one
					'name'      => $function_call['name'],
					'arguments' => $function_call['args'] ?? array(),
				);
			}
		}

		return ! empty( $tool_calls ) ? $tool_calls : null;
	}

	/**
	 * Extract usage information from Gemini response
	 *
	 * @param array $response Gemini API response.
	 * @return array Usage information.
	 */
	private function extract_usage( $response ) {
		$usage_metadata = $response['usageMetadata'] ?? array();

		if ( empty( $usage_metadata ) ) {
			return array();
		}

		// Convert Gemini format to standard format
		return array(
			'input_tokens'  => $usage_metadata['promptTokenCount'] ?? 0,
			'output_tokens' => $usage_metadata['candidatesTokenCount'] ?? 0,
			'total_tokens'  => $usage_metadata['totalTokenCount'] ?? 0,
		);
	}

	/**
	 * Get default system message
	 *
	 * @return string System message.
	 */
	private function get_default_system_message() {
		return __( 'You are a helpful AI assistant with access to marketing analytics data from Google Analytics 4, Google Search Console, and Microsoft Clarity. Use the available tools to answer questions about website performance, user behavior, and marketing metrics. Provide clear, actionable insights based on the data.', 'marketing-analytics-chat' );
	}

	/**
	 * Override make_api_request to handle Gemini's API format
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
		Logger::debug( 'Gemini Provider: API Request to: ' . $endpoint );
		Logger::debug( 'Gemini Provider: Request body (first 2000 chars): ' . substr( $json_body, 0, 2000 ) );

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
		Logger::debug( 'Gemini Provider: API Response status: ' . $status_code );
		if ( $status_code !== 200 ) {
			Logger::debug( 'Gemini Provider: API Error response: ' . $body_data );
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
}
