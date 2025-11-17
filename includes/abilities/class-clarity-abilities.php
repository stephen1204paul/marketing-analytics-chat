<?php
/**
 * Microsoft Clarity Abilities
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\Abilities;

use Marketing_Analytics_MCP\API_Clients\Clarity_Client;
use Marketing_Analytics_MCP\Credentials\Credential_Manager;

/**
 * Registers Microsoft Clarity MCP abilities
 */
class Clarity_Abilities {

	/**
	 * Register Clarity abilities
	 */
	public function register() {
		// Only register abilities if credentials are configured
		$credential_manager = new Credential_Manager();
		if ( ! $credential_manager->has_credentials( 'clarity' ) ) {
			return;
		}

		$this->register_get_clarity_insights();
		$this->register_get_clarity_recordings();
		$this->register_analyze_clarity_heatmaps();
		$this->register_clarity_dashboard_resource();
	}

	/**
	 * Register get-clarity-insights tool
	 */
	private function register_get_clarity_insights() {
		wp_register_ability(
			'marketing-analytics/get-clarity-insights',
			array(
				'label'       => __( 'Get Microsoft Clarity Insights', 'marketing-analytics-mcp' ),
				'description' => __( 'Retrieve analytics dashboard data from Microsoft Clarity for a specified time period with optional dimension filters.', 'marketing-analytics-mcp' ),
				'category'    => 'marketing-analytics',

				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'num_of_days' => array(
							'type'        => 'integer',
							'description' => 'Number of days: 1 (last 24h), 2 (last 48h), or 3 (last 72h)',
							'enum'        => array( 1, 2, 3 ),
						),
						'dimension1'  => array(
							'type'        => 'string',
							'description' => 'First dimension to break down insights',
							'enum'        => array( 'OS', 'Browser', 'Device', 'Country' ),
						),
						'dimension2'  => array(
							'type'        => 'string',
							'description' => 'Second dimension (optional)',
							'enum'        => array( 'OS', 'Browser', 'Device', 'Country' ),
						),
						'dimension3'  => array(
							'type'        => 'string',
							'description' => 'Third dimension (optional)',
							'enum'        => array( 'OS', 'Browser', 'Device', 'Country' ),
						),
					),
					'required'   => array( 'num_of_days' ),
				),

				'output_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'metrics' => array(
							'type'        => 'object',
							'description' => 'Clarity dashboard metrics',
						),
						'insights' => array(
							'type'        => 'array',
							'description' => 'Array of insight objects',
						),
						'period'   => array(
							'type'        => 'string',
							'description' => 'Time period covered',
						),
					),
				),

				'execute_callback'    => array( $this, 'execute_get_clarity_insights' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Register get-clarity-recordings tool
	 */
	private function register_get_clarity_recordings() {
		wp_register_ability(
			'marketing-analytics/get-clarity-recordings',
			array(
				'label'       => __( 'Get Clarity Session Recordings', 'marketing-analytics-mcp' ),
				'description' => __( 'Fetch session recording URLs from Microsoft Clarity based on filters.', 'marketing-analytics-mcp' ),
				'category'    => 'marketing-analytics',

				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'filters' => array(
							'type'        => 'object',
							'description' => 'Filters for recordings (device, browser, country, etc.)',
						),
						'limit'   => array(
							'type'        => 'integer',
							'description' => 'Number of recordings to return (max 100)',
							'default'     => 10,
							'minimum'     => 1,
							'maximum'     => 100,
						),
						'sort_by' => array(
							'type'        => 'string',
							'description' => 'Sort recordings by criteria',
							'enum'        => array( 'date', 'duration', 'pages_viewed' ),
							'default'     => 'date',
						),
					),
				),

				'output_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'recordings'  => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'session_id' => array( 'type' => 'string' ),
									'url'        => array( 'type' => 'string' ),
									'duration'   => array( 'type' => 'integer' ),
									'device'     => array( 'type' => 'string' ),
								),
							),
						),
						'total_count' => array(
							'type'        => 'integer',
							'description' => 'Total recordings matching filters',
						),
					),
				),

				'execute_callback'    => array( $this, 'execute_get_clarity_recordings' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Register analyze-clarity-heatmaps tool
	 */
	private function register_analyze_clarity_heatmaps() {
		wp_register_ability(
			'marketing-analytics/analyze-clarity-heatmaps',
			array(
				'label'       => __( 'Analyze Clarity Heatmaps', 'marketing-analytics-mcp' ),
				'description' => __( 'Get heatmap data and AI-friendly insights from Microsoft Clarity.', 'marketing-analytics-mcp' ),
				'category'    => 'marketing-analytics',

				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'page_url'     => array(
							'type'        => 'string',
							'description' => 'URL of the page to analyze',
						),
						'heatmap_type' => array(
							'type'        => 'string',
							'description' => 'Type of heatmap to retrieve',
							'enum'        => array( 'click', 'scroll' ),
							'default'     => 'click',
						),
					),
					'required'   => array( 'page_url' ),
				),

				'output_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'heatmap_data' => array(
							'type'        => 'object',
							'description' => 'Heatmap data points',
						),
						'insights'     => array(
							'type'        => 'string',
							'description' => 'AI-friendly insights about user behavior',
						),
					),
				),

				'execute_callback'    => array( $this, 'execute_analyze_clarity_heatmaps' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Register clarity-dashboard resource
	 */
	private function register_clarity_dashboard_resource() {
		wp_register_ability(
			'marketing-analytics/clarity-dashboard',
			array(
				'label'       => __( 'Clarity Dashboard Summary', 'marketing-analytics-mcp' ),
				'description' => __( 'Get current Microsoft Clarity project summary with session counts and user metrics.', 'marketing-analytics-mcp' ),
				'category'    => 'marketing-analytics',

				'output_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'project_id'   => array( 'type' => 'string' ),
						'sessions'     => array( 'type' => 'integer' ),
						'active_users' => array( 'type' => 'integer' ),
						'insights'     => array( 'type' => 'array' ),
					),
				),

				'execute_callback'    => array( $this, 'execute_clarity_dashboard' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Get Clarity API client with credentials
	 *
	 * @return Clarity_Client|null Client instance or null if credentials not found.
	 * @throws \Exception If credentials are not configured.
	 */
	private function get_clarity_client() {
		$credential_manager = new Credential_Manager();
		$credentials        = $credential_manager->get_credentials( 'clarity' );

		if ( empty( $credentials ) || ! isset( $credentials['api_token'] ) || ! isset( $credentials['project_id'] ) ) {
			throw new \Exception( 'Clarity credentials not configured. Please configure your Microsoft Clarity API token and project ID in the plugin settings.' );
		}

		return new Clarity_Client( $credentials['api_token'], $credentials['project_id'] );
	}

	/**
	 * Execute get-clarity-insights tool
	 *
	 * @param array $args Tool arguments.
	 * @return array Tool result.
	 */
	public function execute_get_clarity_insights( $args ) {
		try {
			$client = $this->get_clarity_client();

			// Build dimensions array
			$dimensions = array();
			if ( ! empty( $args['dimension1'] ) ) {
				$dimensions[] = $args['dimension1'];
			}
			if ( ! empty( $args['dimension2'] ) ) {
				$dimensions[] = $args['dimension2'];
			}
			if ( ! empty( $args['dimension3'] ) ) {
				$dimensions[] = $args['dimension3'];
			}

			$data = $client->get_insights( $args['num_of_days'], $dimensions );

			return array(
				'content' => array(
					array(
						'type' => 'text',
						'text' => wp_json_encode( $data, JSON_PRETTY_PRINT ),
					),
				),
			);
		} catch ( \Exception $e ) {
			return array(
				'content' => array(
					array(
						'type' => 'text',
						'text' => 'Error: ' . $e->getMessage(),
					),
				),
				'isError' => true,
			);
		}
	}

	/**
	 * Execute get-clarity-recordings tool
	 *
	 * @param array $args Tool arguments.
	 * @return array Tool result.
	 */
	public function execute_get_clarity_recordings( $args ) {
		try {
			$client = $this->get_clarity_client();

			$recordings = $client->get_session_recordings(
				$args['filters'] ?? array(),
				$args['limit'] ?? 10,
				$args['sort_by'] ?? 'date'
			);

			return array(
				'content' => array(
					array(
						'type' => 'text',
						'text' => wp_json_encode( $recordings, JSON_PRETTY_PRINT ),
					),
				),
			);
		} catch ( \Exception $e ) {
			return array(
				'content' => array(
					array(
						'type' => 'text',
						'text' => 'Error: ' . $e->getMessage(),
					),
				),
				'isError' => true,
			);
		}
	}

	/**
	 * Execute analyze-clarity-heatmaps tool
	 *
	 * @param array $args Tool arguments.
	 * @return array Tool result.
	 */
	public function execute_analyze_clarity_heatmaps( $args ) {
		try {
			$client = $this->get_clarity_client();

			// Note: This is a placeholder - Clarity API may not directly support heatmap data export
			// You may need to use their web interface or alternative methods
			$heatmap_data = array(
				'page_url'     => $args['page_url'],
				'heatmap_type' => $args['heatmap_type'] ?? 'click',
				'note'         => 'Heatmap data retrieval may require Clarity web interface access',
			);

			return array(
				'content' => array(
					array(
						'type' => 'text',
						'text' => wp_json_encode( $heatmap_data, JSON_PRETTY_PRINT ),
					),
				),
			);
		} catch ( \Exception $e ) {
			return array(
				'content' => array(
					array(
						'type' => 'text',
						'text' => 'Error: ' . $e->getMessage(),
					),
				),
				'isError' => true,
			);
		}
	}

	/**
	 * Execute clarity-dashboard resource
	 *
	 * @param array $args Resource arguments (optional, not used for this resource).
	 * @return array Resource result.
	 */
	public function execute_clarity_dashboard( $args = array() ) {
		try {
			$client = $this->get_clarity_client();

			// Get 1-day insights for dashboard summary
			$data = $client->get_insights( 1 );

			$summary = array(
				'project_id'   => get_option( 'marketing_analytics_mcp_clarity_project_id', 'Not configured' ),
				'period'       => 'Last 24 hours',
				'data'         => $data,
			);

			return array(
				'contents' => array(
					array(
						'uri'      => 'clarity://dashboard',
						'mimeType' => 'application/json',
						'text'     => wp_json_encode( $summary, JSON_PRETTY_PRINT ),
					),
				),
			);
		} catch ( \Exception $e ) {
			return array(
				'contents' => array(
					array(
						'uri'      => 'clarity://dashboard',
						'mimeType' => 'text/plain',
						'text'     => 'Error: ' . $e->getMessage(),
					),
				),
			);
		}
	}

	/**
	 * Permission callback for all Clarity abilities
	 *
	 * @return bool True if user has permission, false otherwise.
	 */
	public function check_permissions() {
		return current_user_can( 'manage_options' );
	}
}
