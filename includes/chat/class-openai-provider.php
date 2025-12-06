<?php
/**
 * OpenAI API Provider
 *
 * LLM provider for OpenAI's Chat Completions API with function calling support.
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\Chat;

use WP_Error;
use Marketing_Analytics_MCP\Utils\Logger;

/**
 * OpenAI API provider
 */
class OpenAI_Provider extends Abstract_LLM_Provider {

	/**
	 * OpenAI API endpoint
	 */
	const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

	/**
	 * Get provider name
	 *
	 * @return string Provider name.
	 */
	public function get_name() {
		return 'openai';
	}

	/**
	 * Get provider display name
	 *
	 * @return string Provider display name.
	 */
	public function get_display_name() {
		return __( 'OpenAI GPT', 'marketing-analytics-chat' );
	}

	/**
	 * Get default model
	 *
	 * @return string Default OpenAI model.
	 */
	protected function get_default_model() {
		return 'gpt-5.1';
	}

	/**
	 * Send a message to OpenAI and get a response
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
				__( 'OpenAI API is not configured', 'marketing-analytics-chat' )
			);
		}

		// Build request body
		$model = $options['model'] ?? $this->model;
		$body  = array(
			'model'    => $model,
			'messages' => $this->format_messages( $messages, $options ),
		);

		// Check if this model supports custom parameters
		$supports_temperature = $this->model_supports_temperature( $model );

		// Add temperature if specified and supported by model
		if ( $supports_temperature ) {
			if ( isset( $options['temperature'] ) ) {
				$body['temperature'] = $options['temperature'];
			} elseif ( $this->temperature ) {
				$body['temperature'] = $this->temperature;
			}
		}

		// Add max_completion_tokens if specified (newer OpenAI API parameter)
		if ( isset( $options['max_tokens'] ) ) {
			$body['max_completion_tokens'] = $options['max_tokens'];
		} elseif ( $this->max_tokens ) {
			$body['max_completion_tokens'] = $this->max_tokens;
		}

		// Add tools if provided
		if ( ! empty( $tools ) ) {
			$converted_tools = $this->convert_tools_format( $tools );
			$body['tools']   = $converted_tools;

			// Log the tools being sent to OpenAI API
			Logger::debug( 'OpenAI: Sending ' . count( $converted_tools ) . ' tools to API' );
			Logger::debug( 'OpenAI: First tool structure: ' . wp_json_encode( $converted_tools[0] ?? 'none' ) );
		}

		// Make API request
		$headers = array(
			'Authorization' => 'Bearer ' . $this->api_key,
			'Content-Type'  => 'application/json',
		);

		$response = $this->make_api_request( self::API_ENDPOINT, $body, $headers );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->parse_response( $response );
	}

	/**
	 * Format messages for OpenAI API
	 *
	 * @param array $messages Raw messages.
	 * @param array $options Additional options.
	 * @return array Formatted messages.
	 */
	private function format_messages( $messages, $options = array() ) {
		$formatted = array();

		// Add system message if provided
		if ( ! empty( $options['system'] ) ) {
			$formatted[] = array(
				'role'    => 'system',
				'content' => $options['system'],
			);
		} else {
			// Use default system message
			$formatted[] = array(
				'role'    => 'system',
				'content' => $this->get_default_system_message(),
			);
		}

		foreach ( $messages as $message ) {
			// Handle tool results
			if ( $message['role'] === 'tool' ) {
				$formatted[] = array(
					'role'         => 'tool',
					'tool_call_id' => $message['tool_call_id'] ?? 'unknown',
					'content'      => $message['content'],
				);
				continue;
			}

			// Format regular user/assistant messages
			$formatted_message = array(
				'role'    => $message['role'] === 'system' ? 'system' : $message['role'],
				'content' => $message['content'],
			);

			// Add tool calls if present (for assistant messages)
			if ( ! empty( $message['tool_calls'] ) ) {
				$formatted_message['tool_calls'] = array();

				foreach ( $message['tool_calls'] as $tool_call ) {
					$arguments = $tool_call['arguments'] ?? $tool_call['input'] ?? array();

					// Ensure arguments is a JSON string for OpenAI
					if ( is_array( $arguments ) || is_object( $arguments ) ) {
						$arguments = wp_json_encode( $arguments );
					}

					// Sanitize tool name: convert forward slashes to double underscores
					// OpenAI requires function names to match ^[a-zA-Z0-9_-]+$
					$sanitized_name = str_replace( '/', '__', $tool_call['name'] );

					$formatted_message['tool_calls'][] = array(
						'id'       => $tool_call['id'],
						'type'     => 'function',
						'function' => array(
							'name'      => $sanitized_name,
							'arguments' => $arguments,
						),
					);
				}
			}

			$formatted[] = $formatted_message;
		}

		return $formatted;
	}

	/**
	 * Convert MCP tools to OpenAI function calling format
	 *
	 * @param array $mcp_tools MCP tool definitions.
	 * @return array OpenAI function definitions.
	 */
	protected function convert_tools_format( $mcp_tools ) {
		$openai_tools = array();

		foreach ( $mcp_tools as $tool ) {
			// Skip tools without valid names
			$name = $tool['name'] ?? '';
			if ( empty( $name ) ) {
				continue;
			}

			// OpenAI requires function names to match ^[a-zA-Z0-9_-]+$
			// Convert forward slashes to double underscores (e.g., core/get-site-info -> core__get_site_info)
			$sanitized_name = str_replace( '/', '__', $name );

			$openai_tools[] = array(
				'type'     => 'function',
				'function' => array(
					'name'        => $sanitized_name,
					'description' => $tool['description'] ?? '',
					'parameters'  => $tool['inputSchema'] ?? array(
						'type'       => 'object',
						'properties' => new \stdClass(),
					),
				),
			);
		}

		return $openai_tools;
	}

	/**
	 * Parse OpenAI API response
	 *
	 * @param array $response Raw API response.
	 * @return array Parsed response.
	 */
	private function parse_response( $response ) {
		$choice  = $response['choices'][0] ?? array();
		$message = $choice['message'] ?? array();

		// Normalize OpenAI usage format to match Claude's format
		// OpenAI: prompt_tokens, completion_tokens
		// Claude: input_tokens, output_tokens
		$normalized_usage = array();
		if ( ! empty( $response['usage'] ) ) {
			$usage            = $response['usage'];
			$normalized_usage = array(
				'input_tokens'  => $usage['prompt_tokens'] ?? 0,
				'output_tokens' => $usage['completion_tokens'] ?? 0,
				'total_tokens'  => $usage['total_tokens'] ?? 0,
			);
		}

		$result = array(
			'content'     => $this->extract_text_content( $response ),
			'tool_calls'  => $this->extract_tool_calls( $response ),
			'stop_reason' => $choice['finish_reason'] ?? null,
			'usage'       => $normalized_usage,
			'raw'         => $response,
		);

		return $result;
	}

	/**
	 * Extract text content from OpenAI response
	 *
	 * @param array $response OpenAI API response.
	 * @return string Text content.
	 */
	protected function extract_text_content( $response ) {
		$choice  = $response['choices'][0] ?? array();
		$message = $choice['message'] ?? array();

		return $message['content'] ?? '';
	}

	/**
	 * Extract tool calls from OpenAI response
	 *
	 * @param array $response OpenAI API response.
	 * @return array|null Tool calls if present.
	 */
	protected function extract_tool_calls( $response ) {
		$choice  = $response['choices'][0] ?? array();
		$message = $choice['message'] ?? array();

		if ( empty( $message['tool_calls'] ) ) {
			return null;
		}

		$tool_calls = array();

		foreach ( $message['tool_calls'] as $tool_call ) {
			// Parse arguments from JSON string
			$arguments = $tool_call['function']['arguments'] ?? '{}';
			if ( is_string( $arguments ) ) {
				$arguments = json_decode( $arguments, true );
			}

			// Convert sanitized tool name back to MCP format
			// (e.g., core__get_site_info -> core/get-site-info)
			$original_name = str_replace( '__', '/', $tool_call['function']['name'] );

			$tool_calls[] = array(
				'id'        => $tool_call['id'],
				'name'      => $original_name,
				'arguments' => $arguments ?? array(),
			);
		}

		return ! empty( $tool_calls ) ? $tool_calls : null;
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
	 * Check if model supports temperature parameter
	 *
	 * Some OpenAI models (like o1, o1-mini, o3-mini, gpt-5-mini) don't support custom temperature.
	 *
	 * @param string $model Model name.
	 * @return bool True if model supports temperature.
	 */
	private function model_supports_temperature( $model ) {
		// Models that don't support custom temperature (reasoning models)
		$no_temperature_models = array(
			'o1',
			'o1-mini',
			'o1-preview',
			'o3-mini',
			'gpt-5-mini',
		);

		// Check if the model starts with any of the non-supporting prefixes
		foreach ( $no_temperature_models as $prefix ) {
			if ( strpos( $model, $prefix ) === 0 ) {
				return false;
			}
		}

		return true;
	}
}
