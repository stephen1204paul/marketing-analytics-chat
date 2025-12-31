<?php
/**
 * MCP Prompts
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\Abilities;

use Marketing_Analytics_MCP\Credentials\Credential_Manager;
use Marketing_Analytics_MCP\Prompts\Prompt_Manager;
use Marketing_Analytics_MCP\Utils\Permission_Manager;

/**
 * Registers MCP prompts for common analysis workflows
 */
class Prompts {

	/**
	 * Prompt Manager instance
	 *
	 * @var Prompt_Manager
	 */
	private $prompt_manager;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->prompt_manager = new Prompt_Manager();
	}

	/**
	 * Register prompts
	 *
	 * Dynamically registers all user-created custom prompts
	 */
	public function register() {
		// Only register prompts if at least one platform has credentials configured
		$credential_manager  = new Credential_Manager();
		$has_any_credentials = $credential_manager->has_credentials( 'clarity' )
			|| $credential_manager->has_credentials( 'ga4' )
			|| $credential_manager->has_credentials( 'gsc' );

		if ( ! $has_any_credentials ) {
			return;
		}

		// Get all custom prompts and register them
		$custom_prompts = $this->prompt_manager->get_all_prompts();

		foreach ( $custom_prompts as $prompt_id => $prompt_data ) {
			$this->register_custom_prompt( $prompt_id, $prompt_data );
		}
	}

	/**
	 * Register a single custom prompt
	 *
	 * @param string $prompt_id Prompt ID.
	 * @param array  $prompt_data Prompt configuration.
	 */
	private function register_custom_prompt( $prompt_id, $prompt_data ) {
		// Build input schema from arguments
		$input_schema = array(
			'type'       => 'object',
			'properties' => array(),
			'required'   => array(),
		);

		if ( ! empty( $prompt_data['arguments'] ) ) {
			foreach ( $prompt_data['arguments'] as $arg ) {
				$input_schema['properties'][ $arg['name'] ] = array(
					'type'        => $arg['type'] ?? 'string',
					'description' => $arg['description'] ?? '',
				);

				if ( isset( $arg['default'] ) ) {
					$input_schema['properties'][ $arg['name'] ]['default'] = $arg['default'];
				}

				if ( ! empty( $arg['required'] ) ) {
					$input_schema['required'][] = $arg['name'];
				}
			}
		}

		wp_register_ability(
			$prompt_id,
			array(
				'label'               => $prompt_data['label'] ?? $prompt_data['name'],
				'description'         => $prompt_data['description'] ?? '',
				'category'            => $prompt_data['category'] ?? 'marketing-analytics',
				'input_schema'        => $input_schema,
				'execute_callback'    => function ( $args ) use ( $prompt_data ) {
					return $this->execute_prompt( $prompt_data, $args );
				},
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Execute a custom prompt
	 *
	 * @param array $prompt_data Prompt configuration.
	 * @param array $args Runtime arguments.
	 * @return array Prompt result.
	 */
	private function execute_prompt( $prompt_data, $args ) {
		// Replace placeholders in instructions with actual argument values
		$instructions = $prompt_data['instructions'];

		if ( ! empty( $args ) ) {
			foreach ( $args as $key => $value ) {
				$instructions = str_replace( '{{' . $key . '}}', $value, $instructions );
			}
		}

		// Add context about available arguments
		if ( ! empty( $args ) ) {
			$context = "\n\n## Context\nYou have been provided with the following arguments:\n";
			foreach ( $args as $key => $value ) {
				$context .= "- {$key}: {$value}\n";
			}
			$instructions = $context . "\n" . $instructions;
		}

		return array(
			'content' => array(
				array(
					'type' => 'text',
					'text' => $instructions,
				),
			),
		);
	}

	/**
	 * Permission callback for all prompts
	 *
	 * @return bool True if user has permission, false otherwise.
	 */
	public function check_permissions() {
		return Permission_Manager::can_access_plugin();
	}
}
