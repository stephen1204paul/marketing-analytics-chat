<?php
/**
 * LLM Provider Interface
 *
 * Interface for AI language model providers (Claude, OpenAI, etc.).
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\Chat;

use WP_Error;

/**
 * Interface for LLM providers
 */
interface LLM_Provider_Interface {

	/**
	 * Send a message to the AI and get a response
	 *
	 * @param array $messages Conversation history in format:
	 *                        [
	 *                          ['role' => 'user', 'content' => '...'],
	 *                          ['role' => 'assistant', 'content' => '...'],
	 *                        ].
	 * @param array $tools Available MCP tools for the AI to use.
	 * @param array $options Additional options (temperature, max_tokens, etc.).
	 * @return array|WP_Error Response array or WP_Error on failure.
	 */
	public function send_message( $messages, $tools = array(), $options = array() );

	/**
	 * Get provider name
	 *
	 * @return string Provider name (e.g., 'claude', 'openai').
	 */
	public function get_name();

	/**
	 * Get provider display name
	 *
	 * @return string Provider display name (e.g., 'Claude (Anthropic)', 'OpenAI GPT-4').
	 */
	public function get_display_name();

	/**
	 * Check if provider is configured
	 *
	 * @return bool True if API credentials are configured.
	 */
	public function is_configured();

	/**
	 * Get configuration errors
	 *
	 * @return array Array of error messages if not configured.
	 */
	public function get_configuration_errors();
}
