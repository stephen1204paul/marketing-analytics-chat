<?php
/**
 * Abilities Registrar
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\Abilities;

/**
 * Registers all MCP abilities with WordPress Abilities API
 */
class Abilities_Registrar {

	/**
	 * Register ability category
	 *
	 * Called on the 'wp_abilities_api_categories_init' hook
	 */
	public function register_category() {
		// Register the marketing-analytics category
		if ( function_exists( 'wp_register_ability_category' ) ) {
			wp_register_ability_category(
				'marketing-analytics',
				array(
					'label'       => __( 'Marketing Analytics', 'marketing-analytics-mcp' ),
					'description' => __( 'Tools for accessing marketing analytics data from Microsoft Clarity, Google Analytics 4, and Google Search Console.', 'marketing-analytics-mcp' ),
				)
			);
		}
	}

	/**
	 * Register all abilities
	 *
	 * Called on the 'wp_abilities_api_init' hook
	 */
	public function register_all_abilities() {
		// Check if Abilities API is available
		if ( ! function_exists( 'wp_register_ability' ) ) {
			// Log warning
			error_log( 'Marketing Analytics MCP: Abilities API not available. Please install wordpress/abilities-api.' );
			return;
		}

		// Register Clarity abilities
		$clarity_abilities = new Clarity_Abilities();
		$clarity_abilities->register();

		// Register GA4 abilities
		$ga4_abilities = new GA4_Abilities();
		$ga4_abilities->register();

		// Register GSC abilities
		$gsc_abilities = new GSC_Abilities();
		$gsc_abilities->register();

		// Register cross-platform abilities
		$cross_platform_abilities = new Cross_Platform_Abilities();
		$cross_platform_abilities->register();

		// Register prompts
		$prompts = new Prompts();
		$prompts->register();
	}
}
