<?php
/**
 * Chat AJAX Handler
 *
 * Handles AJAX requests for chat interface operations.
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\Chat;

/**
 * Handles chat-related AJAX requests
 */
class Chat_Ajax_Handler {

	/**
	 * Chat manager instance
	 *
	 * @var Chat_Manager
	 */
	private $chat_manager;

	/**
	 * MCP client instance
	 *
	 * @var MCP_Client
	 */
	private $mcp_client;

	/**
	 * LLM provider instance
	 *
	 * @var LLM_Provider_Interface
	 */
	private $llm_provider;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->chat_manager = new Chat_Manager();
		$this->mcp_client   = new MCP_Client();
		$this->llm_provider = $this->get_llm_provider();
	}

	/**
	 * Register AJAX handlers
	 */
	public function register_handlers() {
		add_action( 'wp_ajax_marketing_analytics_mcp_create_conversation', array( $this, 'create_conversation' ) );
		add_action( 'wp_ajax_marketing_analytics_mcp_send_message', array( $this, 'send_message' ) );
		add_action( 'wp_ajax_marketing_analytics_mcp_retry_tool', array( $this, 'retry_tool_call' ) );
	}

	/**
	 * Create a new conversation
	 */
	public function create_conversation() {
		// Verify nonce
		check_ajax_referer( 'marketing-analytics-chat-admin', 'nonce' );

		// Check user permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Insufficient permissions', 'marketing-analytics-chat' ) ),
				403
			);
		}

		// Get user ID
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : get_current_user_id();

		// Create conversation
		$conversation_id = $this->chat_manager->create_conversation( $user_id, 'New Conversation' );

		if ( $conversation_id ) {
			wp_send_json_success(
				array(
					'conversation_id' => $conversation_id,
					'message'         => __( 'Conversation created successfully', 'marketing-analytics-chat' ),
				)
			);
		} else {
			wp_send_json_error(
				array( 'message' => __( 'Failed to create conversation', 'marketing-analytics-chat' ) ),
				500
			);
		}
	}

	/**
	 * Send a message and get AI response
	 */
	public function send_message() {
		// Verify nonce
		check_ajax_referer( 'marketing-analytics-chat-admin', 'nonce' );

		// Check user permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Insufficient permissions', 'marketing-analytics-chat' ) ),
				403
			);
		}

		// Get parameters
		$conversation_id = isset( $_POST['conversation_id'] ) ? absint( $_POST['conversation_id'] ) : 0;
		$message         = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

		// Validate
		if ( ! $conversation_id ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid conversation ID', 'marketing-analytics-chat' ) ),
				400
			);
		}

		if ( empty( $message ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Message cannot be empty', 'marketing-analytics-chat' ) ),
				400
			);
		}

		// Verify conversation belongs to user
		$conversation = $this->chat_manager->get_conversation( $conversation_id );
		if ( ! $conversation || (int) $conversation->user_id !== get_current_user_id() ) {
			wp_send_json_error(
				array( 'message' => __( 'Conversation not found', 'marketing-analytics-chat' ) ),
				404
			);
		}

		// Add user message to database
		$user_message_id = $this->chat_manager->add_message(
			$conversation_id,
			'user',
			$message
		);

		if ( ! $user_message_id ) {
			wp_send_json_error(
				array( 'message' => __( 'Failed to save message', 'marketing-analytics-chat' ) ),
				500
			);
		}

		// Generate conversation title from first message if needed
		$message_count = $this->chat_manager->get_message_count( $conversation_id );
		$new_title     = null;
		if ( $message_count === 1 ) {
			$new_title = $this->chat_manager->generate_title_from_message( $message );
			$this->chat_manager->update_conversation_title( $conversation_id, $new_title );
		}

		// Get AI response
		$ai_response_result = $this->get_ai_response( $conversation_id, $message );

		if ( is_wp_error( $ai_response_result ) ) {
			wp_send_json_error(
				array( 'message' => $ai_response_result->get_error_message() ),
				500
			);
		}

		$ai_response = $ai_response_result['content'];
		$tool_calls  = $ai_response_result['tool_calls'] ?? null;
		$usage       = $ai_response_result['usage'] ?? null;

		// Add assistant response to database
		if ( $tool_calls ) {
			$assistant_message_id = $this->chat_manager->add_tool_message(
				$conversation_id,
				$tool_calls,
				$ai_response
			);
		} else {
			$assistant_message_id = $this->chat_manager->add_message(
				$conversation_id,
				'assistant',
				$ai_response,
				$usage ? array( 'usage' => $usage ) : array()
			);
		}

		if ( ! $assistant_message_id ) {
			wp_send_json_error(
				array( 'message' => __( 'Failed to save AI response', 'marketing-analytics-chat' ) ),
				500
			);
		}

		// Collect all assistant messages to return to frontend
		$all_messages = array();

		// Add initial AI response if it has content
		if ( ! empty( $ai_response ) ) {
			$all_messages[] = array(
				'role'       => 'assistant',
				'content'    => $ai_response,
				'usage'      => $usage,
				'tool_calls' => $tool_calls,
			);
		}

		// Track failed tools for retry functionality
		$failed_tools = array();

		// If there were tool calls, execute them and get a final response
		if ( $tool_calls ) {
			$final_response = $this->handle_tool_calls( $conversation_id, $tool_calls, $usage );
			if ( ! is_wp_error( $final_response ) ) {
				// Add the final response as a separate message
				$all_messages[] = array(
					'role'    => 'assistant',
					'content' => $final_response['content'],
					'usage'   => $final_response['usage'],
				);
				// Update main response vars for backward compatibility
				$ai_response = $final_response['content'];
				$usage       = $final_response['usage'];
				// Track any tools that failed during execution
				$failed_tools = $final_response['failed_tools'] ?? array();
			} else {
				// Tool execution failed - add error message so frontend can display it
				$error_content = sprintf(
					/* translators: %s: Error message */
					__( 'I tried to use tools to answer your question, but encountered an error: %s', 'marketing-analytics-chat' ),
					$final_response->get_error_message()
				);
				$all_messages[] = array(
					'role'     => 'assistant',
					'content'  => $error_content,
					'is_error' => true,
				);
				$ai_response    = $error_content;
			}
		}

		// Build failed tools list for the error message (if any tools failed)
		$failed_tools_for_response = array();
		if ( ! empty( $failed_tools ) ) {
			foreach ( $failed_tools as $ft ) {
				$failed_tools_for_response[] = array(
					'name'      => $ft['name'],
					'error'     => $ft['error'],
					'arguments' => $ft['arguments'],
				);
			}
		}

		// Return success with AI response(s)
		wp_send_json_success(
			array(
				'content'       => $ai_response, // Final/main content for backward compat
				'messages'      => $all_messages, // All messages for proper UI update
				'new_title'     => $new_title,
				'usage'         => $usage,
				'tool_metadata' => $ai_response_result['tool_metadata'] ?? null,
				'failed_tools'  => $failed_tools_for_response, // Tools that failed for retry
				'message'       => __( 'Message sent successfully', 'marketing-analytics-chat' ),
			)
		);
	}

	/**
	 * Get LLM provider instance
	 *
	 * @return LLM_Provider_Interface|null LLM provider or null if not configured.
	 */
	private function get_llm_provider() {
		$settings = get_option( 'marketing_analytics_mcp_settings', array() );
		$provider = $settings['ai_provider'] ?? 'claude';

		if ( $provider === 'claude' ) {
			$config = array(
				'api_key'     => $settings['claude_api_key'] ?? '',
				'model'       => $settings['claude_model'] ?? 'claude-sonnet-4-20250514',
				'temperature' => $settings['ai_temperature'] ?? 0.7,
				'max_tokens'  => $settings['ai_max_tokens'] ?? 4096,
			);
			return new Claude_Provider( $config );
		}

		if ( $provider === 'openai' ) {
			$config = array(
				'api_key'     => $settings['openai_api_key'] ?? '',
				'model'       => $settings['openai_model'] ?? 'gpt-5.1',
				'temperature' => $settings['ai_temperature'] ?? 0.7,
				'max_tokens'  => $settings['ai_max_tokens'] ?? 4096,
			);
			return new OpenAI_Provider( $config );
		}

		if ( $provider === 'gemini' ) {
			$config = array(
				'api_key'     => $settings['gemini_api_key'] ?? '',
				'model'       => $settings['gemini_model'] ?? 'gemini-2.5-pro',
				'temperature' => $settings['ai_temperature'] ?? 0.7,
				'max_tokens'  => $settings['ai_max_tokens'] ?? 4096,
			);
			return new Gemini_Provider( $config );
		}

		return null;
	}

	/**
	 * Filter MCP tools based on settings
	 *
	 * @param array $tools All available MCP tools.
	 * @return array Filtered tools.
	 */
	private function filter_tools( $tools ) {
		$settings = get_option( 'marketing_analytics_mcp_settings', array() );

		// Get enabled tool categories (default: all enabled)
		$enabled_categories = $settings['enabled_tool_categories'] ?? array( 'all' );

		// If "all" is selected, return all tools
		if ( in_array( 'all', $enabled_categories, true ) ) {
			return $tools;
		}

		// Filter tools by category
		$filtered = array();
		foreach ( $tools as $tool ) {
			$tool_name = $tool['name'];

			// Categorize tools by prefix
			if ( strpos( $tool_name, 'clarity_' ) === 0 && in_array( 'clarity', $enabled_categories, true ) ) {
				$filtered[] = $tool;
			} elseif ( strpos( $tool_name, 'ga4_' ) === 0 && in_array( 'ga4', $enabled_categories, true ) ) {
				$filtered[] = $tool;
			} elseif ( strpos( $tool_name, 'gsc_' ) === 0 && in_array( 'gsc', $enabled_categories, true ) ) {
				$filtered[] = $tool;
			}
		}

		return $filtered;
	}

	/**
	 * Get AI response for a message
	 *
	 * @param int    $conversation_id Conversation ID.
	 * @param string $message User message.
	 * @return array|WP_Error Response array or WP_Error.
	 */
	private function get_ai_response( $conversation_id, $message ) {
		// Check if provider is configured
		if ( ! $this->llm_provider || ! $this->llm_provider->is_configured() ) {
			return new \WP_Error(
				'provider_not_configured',
				__( 'AI provider is not configured. Please configure your API key in Settings.', 'marketing-analytics-chat' )
			);
		}

		// Get conversation history
		$history_messages   = $this->chat_manager->get_messages( $conversation_id );
		$formatted_messages = array();

		foreach ( $history_messages as $msg ) {
			$formatted_messages[] = array(
				'role'         => $msg->role,
				'content'      => $msg->content,
				'tool_calls'   => ! empty( $msg->tool_calls ) ? $msg->tool_calls : null,
				'tool_call_id' => $msg->tool_call_id ?? null,
			);
		}

		// Get available MCP tools
		$mcp_tools = $this->mcp_client->list_tools();
		if ( is_wp_error( $mcp_tools ) ) {
			$mcp_tools = array(); // Fallback to no tools if MCP server is unavailable
		}

		// Filter tools based on settings
		$filtered_tools = $this->filter_tools( $mcp_tools );

		// Send message to LLM
		$response = $this->llm_provider->send_message( $formatted_messages, $filtered_tools );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Add tool metadata to response
		$response['tool_metadata'] = array(
			'total_available' => count( $mcp_tools ),
			'tools_sent'      => count( $filtered_tools ),
			'filtered'        => count( $mcp_tools ) !== count( $filtered_tools ),
		);

		return $response;
	}

	/**
	 * Handle tool calls from AI
	 *
	 * @param int   $conversation_id Conversation ID.
	 * @param array $tool_calls Tool calls from AI.
	 * @param array $initial_usage Initial token usage from tool_use request.
	 * @return array|WP_Error Array with 'content', 'usage', and 'failed_tools', or WP_Error.
	 */
	private function handle_tool_calls( $conversation_id, $tool_calls, $initial_usage = array() ) {
		$tool_results = array();
		$failed_tools = array();

		// Execute each tool call
		foreach ( $tool_calls as $tool_call ) {
			$tool_name = $tool_call['name'];
			$arguments = $tool_call['arguments'] ?? array();

			// Call the MCP tool
			$result = $this->mcp_client->call_tool( $tool_name, $arguments );

			if ( is_wp_error( $result ) ) {
				$result_content = 'Error: ' . $result->get_error_message();
				// Track failed tools for reporting
				$failed_tools[] = array(
					'name'      => $tool_name,
					'id'        => $tool_call['id'],
					'error'     => $result->get_error_message(),
					'arguments' => $arguments,
				);
			} else {
				$result_content = $this->mcp_client->format_tool_result( $tool_name, $result );
			}

			// Save tool result to database
			$this->chat_manager->add_tool_result(
				$conversation_id,
				$tool_call['id'],
				$tool_name,
				$result_content
			);

			$tool_results[] = array(
				'role'         => 'tool',
				'content'      => $result_content,
				'tool_call_id' => $tool_call['id'],
			);
		}

		// Get updated conversation history with tool results
		$history_messages   = $this->chat_manager->get_messages( $conversation_id );
		$formatted_messages = array();

		foreach ( $history_messages as $msg ) {
			$formatted_messages[] = array(
				'role'         => $msg->role,
				'content'      => $msg->content,
				'tool_calls'   => ! empty( $msg->tool_calls ) ? $msg->tool_calls : null,
				'tool_call_id' => $msg->tool_call_id ?? null,
			);
		}

		// Get final response from AI with tool results
		$final_response = $this->llm_provider->send_message( $formatted_messages, array() );

		if ( is_wp_error( $final_response ) ) {
			return $final_response;
		}

		// Accumulate token usage from both API calls
		$cumulative_usage = array();
		if ( ! empty( $initial_usage ) && ! empty( $final_response['usage'] ) ) {
			$cumulative_usage = array(
				'input_tokens'  => ( $initial_usage['input_tokens'] ?? 0 ) + ( $final_response['usage']['input_tokens'] ?? 0 ),
				'output_tokens' => ( $initial_usage['output_tokens'] ?? 0 ) + ( $final_response['usage']['output_tokens'] ?? 0 ),
			);
		} elseif ( ! empty( $final_response['usage'] ) ) {
			$cumulative_usage = $final_response['usage'];
		} elseif ( ! empty( $initial_usage ) ) {
			$cumulative_usage = $initial_usage;
		}

		// Save final response with cumulative usage
		$this->chat_manager->add_message(
			$conversation_id,
			'assistant',
			$final_response['content'],
			$cumulative_usage ? array( 'usage' => $cumulative_usage ) : array()
		);

		return array(
			'content'      => $final_response['content'],
			'usage'        => $cumulative_usage,
			'failed_tools' => $failed_tools,
		);
	}

	/**
	 * Retry failed tool calls
	 */
	public function retry_tool_call() {
		// Verify nonce
		check_ajax_referer( 'marketing-analytics-chat-admin', 'nonce' );

		// Check user permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Insufficient permissions', 'marketing-analytics-chat' ) ),
				403
			);
		}

		// Get parameters
		$conversation_id = isset( $_POST['conversation_id'] ) ? absint( $_POST['conversation_id'] ) : 0;
		$tool_name       = isset( $_POST['tool_name'] ) ? sanitize_text_field( wp_unslash( $_POST['tool_name'] ) ) : '';
		$tool_arguments  = isset( $_POST['tool_arguments'] ) ? json_decode( wp_unslash( $_POST['tool_arguments'] ), true ) : array();

		// Validate
		if ( ! $conversation_id || empty( $tool_name ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid parameters', 'marketing-analytics-chat' ) ),
				400
			);
		}

		// Execute the tool
		$result = $this->mcp_client->call_tool( $tool_name, $tool_arguments );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'tool'    => $tool_name,
				)
			);
		}

		// Format the result
		$formatted_result = $this->mcp_client->format_tool_result( $tool_name, $result );

		// Get AI to interpret the result
		$interpretation = $this->get_tool_result_interpretation( $formatted_result, $tool_name );

		wp_send_json_success(
			array(
				'content'    => $interpretation,
				'raw_result' => $formatted_result,
				'tool'       => $tool_name,
			)
		);
	}

	/**
	 * Get AI interpretation of tool result
	 *
	 * @param string $result Tool result.
	 * @param string $tool_name Tool name.
	 * @return string AI interpretation.
	 */
	private function get_tool_result_interpretation( $result, $tool_name ) {
		if ( ! $this->llm_provider || ! $this->llm_provider->is_configured() ) {
			// Return raw result if no AI available
			return $result;
		}

		$messages = array(
			array(
				'role'    => 'user',
				'content' => sprintf(
					"Here's the result from the %s tool. Please provide a brief, helpful summary:\n\n%s",
					$tool_name,
					$result
				),
			),
		);

		$response = $this->llm_provider->send_message( $messages, array() );

		if ( is_wp_error( $response ) ) {
			return $result;
		}

		return $response['content'] ?? $result;
	}
}
