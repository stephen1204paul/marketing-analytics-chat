<?php
/**
 * Claude API Provider
 *
 * LLM provider for Anthropic's Claude API with tool use support.
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\Chat;

use WP_Error;
use Marketing_Analytics_MCP\Utils\Logger;

/**
 * Claude API provider
 */
class Claude_Provider extends Abstract_LLM_Provider {

	/**
	 * Claude API endpoint
	 */
	const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';

	/**
	 * API version
	 */
	const API_VERSION = '2023-06-01';

	/**
	 * Get provider name
	 *
	 * @return string Provider name.
	 */
	public function get_name() {
		return 'claude';
	}

	/**
	 * Get provider display name
	 *
	 * @return string Provider display name.
	 */
	public function get_display_name() {
		return __( 'Claude (Anthropic)', 'marketing-analytics-chat' );
	}

	/**
	 * Get default model
	 *
	 * @return string Default Claude model.
	 */
	protected function get_default_model() {
		return 'claude-sonnet-4-20250514';
	}

	/**
	 * Send a message to Claude and get a response
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
				__( 'Claude API is not configured', 'marketing-analytics-chat' )
			);
		}

		// Build request body
		$body = array(
			'model'      => $options['model'] ?? $this->model,
			'max_tokens' => $options['max_tokens'] ?? $this->max_tokens,
			'messages'   => $this->format_messages( $messages ),
		);

		// Add system message if provided
		if ( ! empty( $options['system'] ) ) {
			$body['system'] = $options['system'];
		} else {
			$body['system'] = $this->get_default_system_message();
		}

		// Add temperature if specified
		if ( isset( $options['temperature'] ) ) {
			$body['temperature'] = $options['temperature'];
		} elseif ( $this->temperature ) {
			$body['temperature'] = $this->temperature;
		}

		// Add tools if provided
		if ( ! empty( $tools ) ) {
			$converted_tools = $this->convert_tools_format( $tools );
			$body['tools']   = $converted_tools;

			// Log the tools being sent to Claude API
			Logger::debug( 'Claude: Sending ' . count( $converted_tools ) . ' tools to API' );
			Logger::debug( 'Claude: First tool structure: ' . wp_json_encode( $converted_tools[0] ?? 'none' ) );
			Logger::debug( 'Claude: Full tools array: ' . wp_json_encode( $converted_tools ) );
		}

		// Make API request
		$headers = array(
			'x-api-key'         => $this->api_key,
			'anthropic-version' => self::API_VERSION,
			'Content-Type'      => 'application/json',
		);

		$response = $this->make_api_request( self::API_ENDPOINT, $body, $headers );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->parse_response( $response );
	}

	/**
	 * Format messages for Claude API
	 *
	 * @param array $messages Raw messages.
	 * @return array Formatted messages.
	 */
	private function format_messages( $messages ) {
		$formatted = array();

		foreach ( $messages as $message ) {
			// Skip system messages (handled separately in Claude API)
			if ( $message['role'] === 'system' ) {
				continue;
			}

			// Handle tool results
			if ( $message['role'] === 'tool' ) {
				// Tool results should be included in the content of the next user message
				// or as a tool_result content block
				$formatted[] = array(
					'role'    => 'user',
					'content' => array(
						array(
							'type'        => 'tool_result',
							'tool_use_id' => $message['tool_call_id'] ?? 'unknown',
							'content'     => $message['content'],
						),
					),
				);
				continue;
			}

			// Format regular user/assistant messages
			$formatted_message = array(
				'role'    => $message['role'],
				'content' => $message['content'],
			);

			// Add tool calls if present
			if ( ! empty( $message['tool_calls'] ) ) {
				$formatted_message['content'] = array();

				// Add text content if exists
				if ( ! empty( $message['content'] ) ) {
					$formatted_message['content'][] = array(
						'type' => 'text',
						'text' => $message['content'],
					);
				}

				// Add tool use blocks
				foreach ( $message['tool_calls'] as $tool_call ) {
					// Get input/arguments and ensure it's an object, not an array
					$input = $tool_call['arguments'] ?? $tool_call['input'] ?? array();
					if ( empty( $input ) || ( is_array( $input ) && array_keys( $input ) === range( 0, count( $input ) - 1 ) ) ) {
						// Empty or sequential array - convert to empty object
						$input = new \stdClass();
					}

					// Convert tool name to Claude format (in case history has old format)
					$tool_name = str_replace( '/', '__', $tool_call['name'] );
					$tool_name = preg_replace( '/[^a-zA-Z0-9_-]/', '_', $tool_name );

					$formatted_message['content'][] = array(
						'type'  => 'tool_use',
						'id'    => $tool_call['id'],
						'name'  => $tool_name,
						'input' => $input,
					);
				}
			}

			$formatted[] = $formatted_message;
		}

		return $formatted;
	}

	/**
	 * Convert MCP tools to Claude format
	 *
	 * Claude API requires tool names to match pattern: ^[a-zA-Z0-9_-]{1,128}
	 * WordPress abilities use names like "marketing-analytics/get-ga4-metrics"
	 * We need to convert slashes to underscores.
	 *
	 * @param array $mcp_tools MCP tool definitions.
	 * @return array Claude tool definitions.
	 */
	protected function convert_tools_format( $mcp_tools ) {
		$claude_tools = array();

		foreach ( $mcp_tools as $tool ) {
			// Skip tools without valid names
			$name = $tool['name'] ?? '';
			if ( empty( $name ) ) {
				continue;
			}

			// Convert tool name to Claude-compatible format
			// Replace slashes with double underscores (to allow reverse conversion)
			$claude_name = str_replace( '/', '__', $name );

			// Ensure name only contains valid characters (alphanumeric, underscore, hyphen)
			$claude_name = preg_replace( '/[^a-zA-Z0-9_-]/', '_', $claude_name );

			// Ensure name is not longer than 128 characters
			$claude_name = substr( $claude_name, 0, 128 );

			$claude_tools[] = array(
				'name'         => $claude_name,
				'description'  => $tool['description'] ?? '',
				'input_schema' => $tool['inputSchema'] ?? array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			);
		}

		return $claude_tools;
	}

	/**
	 * Convert Claude tool name back to MCP format
	 *
	 * @param string $claude_name Claude tool name.
	 * @return string MCP tool name.
	 */
	public function convert_tool_name_to_mcp( $claude_name ) {
		// Convert double underscores back to slashes
		return str_replace( '__', '/', $claude_name );
	}

	/**
	 * Parse Claude API response
	 *
	 * @param array $response Raw API response.
	 * @return array Parsed response.
	 */
	private function parse_response( $response ) {
		$result = array(
			'content'     => $this->extract_text_content( $response ),
			'tool_calls'  => $this->extract_tool_calls( $response ),
			'stop_reason' => $response['stop_reason'] ?? null,
			'usage'       => $response['usage'] ?? array(),
			'raw'         => $response,
		);

		return $result;
	}

	/**
	 * Extract text content from Claude response
	 *
	 * @param array $response Claude API response.
	 * @return string Text content.
	 */
	protected function extract_text_content( $response ) {
		if ( empty( $response['content'] ) ) {
			return '';
		}

		$text_parts = array();

		foreach ( $response['content'] as $content_block ) {
			if ( $content_block['type'] === 'text' ) {
				$text_parts[] = $content_block['text'];
			}
		}

		return implode( "\n\n", $text_parts );
	}

	/**
	 * Extract tool calls from Claude response
	 *
	 * @param array $response Claude API response.
	 * @return array|null Tool calls if present.
	 */
	protected function extract_tool_calls( $response ) {
		if ( empty( $response['content'] ) ) {
			return null;
		}

		$tool_calls = array();

		foreach ( $response['content'] as $content_block ) {
			if ( $content_block['type'] === 'tool_use' ) {
				// Convert Claude tool name back to MCP format
				$mcp_name = $this->convert_tool_name_to_mcp( $content_block['name'] );

				$tool_calls[] = array(
					'id'        => $content_block['id'],
					'name'      => $mcp_name,
					'arguments' => $content_block['input'] ?? array(),
				);
			}
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
}
