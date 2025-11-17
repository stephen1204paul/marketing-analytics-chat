<?php
/**
 * MCP Server Initialization
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP;

use WP\MCP\Core\McpAdapter;
use WP\MCP\Transport\Http\RestTransport;
use WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler;
use WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler;

/**
 * Initializes and configures the MCP server
 */
class MCP_Server {

	/**
	 * Register hooks for MCP server initialization
	 */
	public function register_hooks() {
		add_action( 'mcp_adapter_init', [ $this, 'create_mcp_server' ] );
	}

	/**
	 * Create and configure the MCP server
	 *
	 * @param McpAdapter $adapter The MCP adapter instance.
	 */
	public function create_mcp_server( $adapter ) {
		// Get all registered abilities
		$tools      = $this->get_ability_names( 'tool' );
		$resources  = $this->get_ability_names( 'resource' );
		$prompts    = $this->get_ability_names( 'prompt' );

		// Build server description with current date context
		$server_description = $this->build_server_description();

		// Create the MCP server
		$adapter->create_server(
			'marketing-analytics-mcp',           // Unique server identifier
			'marketing-analytics',               // REST API namespace
			'mcp',                              // REST API route
			'Marketing Analytics MCP',          // Server name
			$server_description,                // Description with current date context
			MARKETING_ANALYTICS_MCP_VERSION,    // Server version
			[                                   // Transport methods
				RestTransport::class,
			],
			ErrorLogMcpErrorHandler::class,     // Error handler
			NullMcpObservabilityHandler::class, // Observability handler
			$tools,                             // Abilities to expose as tools
			$resources,                         // Resources
			$prompts                            // Prompts
		);
	}

	/**
	 * Build server description with current date context
	 *
	 * @return string Server description with system context.
	 */
	private function build_server_description() {
		// Get current date/time information
		$current_date      = current_time( 'Y-m-d' );
		$current_datetime  = current_time( 'Y-m-d H:i:s' );
		$timezone_string   = wp_timezone_string();
		$current_timestamp = current_time( 'timestamp' );

		// Calculate useful date references
		$yesterday    = gmdate( 'Y-m-d', strtotime( '-1 day', $current_timestamp ) );
		$last_week    = gmdate( 'Y-m-d', strtotime( '-7 days', $current_timestamp ) );
		$last_month   = gmdate( 'Y-m-d', strtotime( '-30 days', $current_timestamp ) );
		$day_of_week  = gmdate( 'l', $current_timestamp );

		$description = "Marketing Analytics MCP - Exposes marketing analytics data (Microsoft Clarity, Google Analytics 4, Google Search Console) via Model Context Protocol for AI assistants.\n\n";

		$description .= "## Current Date & Time Context\n";
		$description .= "- **Today's Date:** {$current_date}\n";
		$description .= "- **Current Time:** {$current_datetime}\n";
		$description .= "- **Timezone:** {$timezone_string}\n";
		$description .= "- **Day of Week:** {$day_of_week}\n\n";

		$description .= "## Date References for Queries\n";
		$description .= "- Yesterday: {$yesterday}\n";
		$description .= "- 7 days ago: {$last_week}\n";
		$description .= "- 30 days ago: {$last_month}\n\n";

		$description .= "## Data Freshness & Important Notes\n";
		$description .= "- **Google Analytics 4:** Data available up to today ({$current_date})\n";
		$description .= "- **Google Search Console:** Has 2-3 day delay, latest data from " . gmdate( 'Y-m-d', strtotime( '-3 days', $current_timestamp ) ) . "\n";
		$description .= "- **Microsoft Clarity:** Rate limited to 10 API requests per day\n\n";

		$description .= "When users request data for relative dates (e.g., 'last week', 'yesterday', 'last month'), use the date references above to calculate the correct date ranges.";

		return $description;
	}

	/**
	 * Get ability names by type
	 *
	 * @param string $type The ability type (tool, resource, or prompt).
	 * @return array Array of ability names.
	 */
	private function get_ability_names( $type ) {
		$abilities = [];

		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return $abilities;
		}

		$all_abilities = wp_get_abilities();

		foreach ( $all_abilities as $ability_name => $ability ) {
			// Filter abilities by our plugin prefix
			if ( strpos( $ability_name, 'marketing-analytics/' ) !== 0 ) {
				continue;
			}

			// Determine type based on ability properties
			$ability_type = $this->determine_ability_type( $ability );

			if ( $ability_type === $type ) {
				$abilities[] = $ability_name;
			}
		}

		return $abilities;
	}

	/**
	 * Determine ability type based on its properties
	 *
	 * @param object $ability The WP_Ability object.
	 * @return string The ability type: 'tool', 'resource', or 'prompt'.
	 */
	private function determine_ability_type( $ability ) {
		// Tools have input_schema (callable with parameters)
		if ( isset( $ability->input_schema ) ) {
			return 'tool';
		}

		// Resources have output_schema but no input_schema (static data)
		if ( isset( $ability->output_schema ) ) {
			return 'resource';
		}

		// Prompts have prompt-specific properties
		if ( isset( $ability->arguments ) || isset( $ability->template ) ) {
			return 'prompt';
		}

		// Default to tool
		return 'tool';
	}
}
