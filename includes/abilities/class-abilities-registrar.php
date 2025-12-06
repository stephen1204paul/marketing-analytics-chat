<?php
/**
 * Abilities Registrar
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\Abilities;

use Marketing_Analytics_MCP\Utils\Logger;

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
					'label'       => __( 'Marketing Analytics', 'marketing-analytics-chat' ),
					'description' => __( 'Tools for accessing marketing analytics data from Microsoft Clarity, Google Analytics 4, and Google Search Console.', 'marketing-analytics-chat' ),
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
			Logger::debug( 'Abilities API not available. Please install wordpress/abilities-api.' );
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

		// Register Meta Business Suite abilities
		$meta_abilities = new Meta_Abilities();
		$meta_abilities->register();

		// Register DataForSEO abilities
		$dataforseo_abilities = new DataForSEO_Abilities();
		$dataforseo_abilities->register();

		// Register cross-platform abilities
		$cross_platform_abilities = new Cross_Platform_Abilities();
		$cross_platform_abilities->register();

		// Register prompts
		$prompts = new Prompts();
		$prompts->register();

		// Register Quick Wins abilities (AI insights, anomaly detection, exports, notifications, network)
		if ( class_exists( 'Marketing_Analytics_MCP\\Abilities\\QuickWins_Abilities' ) ) {
			$quickwins_abilities = new QuickWins_Abilities();
			$quickwins_abilities->register_abilities();
		}
	}
}
