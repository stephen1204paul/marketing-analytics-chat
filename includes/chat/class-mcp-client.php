<?php
/**
 * MCP Client
 *
 * Client for communicating with the local WordPress MCP server.
 * Provides methods to list and call MCP tools.
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\Chat;

use WP_Error;
use Marketing_Analytics_MCP\Utils\Logger;

/**
 * MCP Client for calling local MCP server tools
 */
class MCP_Client {

	/**
	 * MCP server endpoint URL
	 *
	 * @var string
	 */
	private $server_url;

	/**
	 * WordPress user for MCP context
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * Constructor
	 *
	 * @param int $user_id WordPress user ID for MCP context.
	 */
	public function __construct( $user_id = null ) {
		$this->user_id = $user_id ?: get_current_user_id();
	}

	/**
	 * Get the MCP server URL
	 *
	 * @return string MCP server URL.
	 */
	private function get_server_url() {
		if ( ! $this->server_url ) {
			$this->server_url = rest_url( 'mcp/mcp-adapter-default-server' );
		}
		return $this->server_url;
	}

	/**
	 * List available MCP tools
	 *
	 * Uses direct WordPress Abilities API when available, falls back to HTTP.
	 *
	 * @return array|WP_Error Array of tool definitions or WP_Error on failure.
	 */
	public function list_tools() {
		// Try direct Abilities API first (bypasses HTTP authentication issues)
		if ( function_exists( 'wp_get_abilities' ) ) {
			$abilities = wp_get_abilities();
			$tools     = array();

			Logger::debug( 'MCP Client: list_tools: Found ' . count( $abilities ) . ' abilities' );

			foreach ( $abilities as $ability_key => $ability ) {
				// WP_Ability objects use getter methods for protected properties
				$name         = '';
				$description  = '';
				$input_schema = null;

				if ( is_object( $ability ) && $ability instanceof \WP_Ability ) {
					// Use getter methods for WP_Ability objects
					$name         = $ability->get_name();
					$description  = $ability->get_description();
					$input_schema = $ability->get_input_schema();
				} elseif ( is_object( $ability ) ) {
					// Fallback for other object types - try getter methods first, then properties
					if ( method_exists( $ability, 'get_name' ) ) {
						$name = $ability->get_name();
					} else {
						$name = $ability->id ?? $ability->name ?? $ability->slug ?? '';
					}

					if ( method_exists( $ability, 'get_description' ) ) {
						$description = $ability->get_description();
					} else {
						$description = $ability->description ?? '';
					}

					if ( method_exists( $ability, 'get_input_schema' ) ) {
						$input_schema = $ability->get_input_schema();
					} else {
						$input_schema = $ability->input_schema ?? null;
					}
				} elseif ( is_array( $ability ) ) {
					$name         = $ability['id'] ?? $ability['name'] ?? $ability['slug'] ?? '';
					$description  = $ability['description'] ?? '';
					$input_schema = $ability['input_schema'] ?? null;
				}

				// If still empty, use the array key (which is often the ability ID)
				if ( empty( $name ) && is_string( $ability_key ) && ! is_numeric( $ability_key ) ) {
					$name = $ability_key;
				}

				// Skip abilities without valid names
				if ( empty( $name ) ) {
					Logger::debug( 'MCP Client: SKIPPING ability with empty name. Key: ' . $ability_key );
					continue;
				}

				// Ensure input_schema has proper structure for empty schemas
				if ( empty( $input_schema ) || ! is_array( $input_schema ) ) {
					$input_schema = array(
						'type'       => 'object',
						'properties' => new \stdClass(),
					);
				}

				$tools[] = array(
					'name'        => $name,
					'description' => $description,
					'inputSchema' => $input_schema,
				);
			}

			Logger::debug( 'MCP Client: list_tools: Returning ' . count( $tools ) . ' valid tools' );

			if ( ! empty( $tools ) ) {
				return $tools;
			}
		}

		// Fallback to HTTP request
		$request_body = array(
			'jsonrpc' => '2.0',
			'id'      => $this->generate_request_id(),
			'method'  => 'tools/list',
			'params'  => new \stdClass(), // Empty object
		);

		$response = $this->make_request( $request_body );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( isset( $response['result']['tools'] ) ) {
			return $response['result']['tools'];
		}

		return new WP_Error(
			'mcp_invalid_response',
			__( 'Invalid response from MCP server', 'marketing-analytics-chat' )
		);
	}

	/**
	 * Call an MCP tool
	 *
	 * Uses direct WordPress Abilities API when available, falls back to HTTP.
	 *
	 * @param string $tool_name Tool name (e.g., 'marketing-analytics/get-clarity-insights').
	 * @param array  $arguments Tool arguments.
	 * @return array|WP_Error Tool result or WP_Error on failure.
	 */
	public function call_tool( $tool_name, $arguments = array() ) {
		// Try direct Abilities API first (bypasses HTTP authentication issues)
		if ( function_exists( 'wp_get_ability' ) ) {
			$ability = wp_get_ability( $tool_name );

			if ( $ability instanceof \WP_Ability ) {
				// Execute the ability directly
				$result = $ability->execute( $arguments );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				// Format result to match MCP response structure
				return array(
					'content' => array(
						array(
							'type' => 'text',
							'text' => is_string( $result ) ? $result : wp_json_encode( $result, JSON_PRETTY_PRINT ),
						),
					),
				);
			}

			// Ability not found, log and fall back to HTTP
			Logger::debug( 'MCP Client: Ability not found via wp_get_ability: ' . $tool_name );
		}

		// Fallback to HTTP request
		$request_body = array(
			'jsonrpc' => '2.0',
			'id'      => $this->generate_request_id(),
			'method'  => 'tools/call',
			'params'  => array(
				'name'      => $tool_name,
				'arguments' => (object) $arguments, // Convert to object for JSON
			),
		);

		$response = $this->make_request( $request_body );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( isset( $response['result'] ) ) {
			return $response['result'];
		}

		if ( isset( $response['error'] ) ) {
			return new WP_Error(
				'mcp_tool_error',
				$response['error']['message'] ?? __( 'Tool execution failed', 'marketing-analytics-chat' ),
				$response['error']
			);
		}

		return new WP_Error(
			'mcp_invalid_response',
			__( 'Invalid response from MCP server', 'marketing-analytics-chat' )
		);
	}

	/**
	 * List available MCP resources
	 *
	 * @return array|WP_Error Array of resource definitions or WP_Error on failure.
	 */
	public function list_resources() {
		$request_body = array(
			'jsonrpc' => '2.0',
			'id'      => $this->generate_request_id(),
			'method'  => 'resources/list',
			'params'  => new \stdClass(),
		);

		$response = $this->make_request( $request_body );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( isset( $response['result']['resources'] ) ) {
			return $response['result']['resources'];
		}

		return new WP_Error(
			'mcp_invalid_response',
			__( 'Invalid response from MCP server', 'marketing-analytics-chat' )
		);
	}

	/**
	 * Read an MCP resource
	 *
	 * @param string $resource_uri Resource URI.
	 * @return array|WP_Error Resource content or WP_Error on failure.
	 */
	public function read_resource( $resource_uri ) {
		$request_body = array(
			'jsonrpc' => '2.0',
			'id'      => $this->generate_request_id(),
			'method'  => 'resources/read',
			'params'  => array(
				'uri' => $resource_uri,
			),
		);

		$response = $this->make_request( $request_body );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( isset( $response['result'] ) ) {
			return $response['result'];
		}

		return new WP_Error(
			'mcp_invalid_response',
			__( 'Invalid response from MCP server', 'marketing-analytics-chat' )
		);
	}

	/**
	 * Make HTTP request to MCP server
	 *
	 * @param array $request_body JSON-RPC request body.
	 * @return array|WP_Error Decoded response or WP_Error on failure.
	 */
	private function make_request( $request_body ) {
		$args = array(
			'headers' => array(
				'Content-Type' => 'application/json',
				'X-WP-Nonce'   => wp_create_nonce( 'wp_rest' ), // Required for cookie auth
			),
			'body'    => wp_json_encode( $request_body ),
			'timeout' => 30,
		);

		// Add cookies to maintain user session for internal requests
		// This ensures permission checks work correctly in the MCP adapter
		if ( ! empty( $_COOKIE ) ) {
			$cookies = array();
			foreach ( $_COOKIE as $name => $value ) {
				// Only include WordPress cookies for security
				if ( strpos( $name, 'wordpress_' ) === 0 || strpos( $name, 'wp-' ) === 0 ) {
					$cookies[] = new \WP_Http_Cookie(
						array(
							'name'  => $name,
							'value' => $value,
						)
					);
				}
			}
			if ( ! empty( $cookies ) ) {
				$args['cookies'] = $cookies;
			}
		}

		// Use internal WordPress HTTP API
		$response = wp_remote_post( $this->get_server_url(), $args );

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'mcp_request_failed',
				sprintf(
					/* translators: %s: Error message */
					__( 'MCP request failed: %s', 'marketing-analytics-chat' ),
					$response->get_error_message()
				)
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code !== 200 ) {
			return new WP_Error(
				'mcp_request_failed',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'MCP server returned status code: %d', 'marketing-analytics-chat' ),
					$status_code
				)
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error(
				'mcp_invalid_json',
				__( 'Invalid JSON response from MCP server', 'marketing-analytics-chat' )
			);
		}

		return $data;
	}

	/**
	 * Generate a unique request ID
	 *
	 * @return int Unique request ID.
	 */
	private function generate_request_id() {
		return time() + wp_rand( 1000, 9999 );
	}

	/**
	 * Format tool result for AI context
	 *
	 * @param string $tool_name Tool name.
	 * @param array  $result Tool result.
	 * @return string Formatted result for AI.
	 */
	public function format_tool_result( $tool_name, $result ) {
		$formatted = "Tool: {$tool_name}\n\n";

		if ( isset( $result['content'] ) && is_array( $result['content'] ) ) {
			foreach ( $result['content'] as $content_item ) {
				if ( isset( $content_item['type'] ) && $content_item['type'] === 'text' ) {
					$formatted .= $content_item['text'] . "\n\n";
				}
			}
		} elseif ( is_string( $result ) ) {
			$formatted .= $result . "\n";
		} else {
			$formatted .= wp_json_encode( $result, JSON_PRETTY_PRINT ) . "\n";
		}

		return $formatted;
	}
}
