<?php
/**
 * Google Analytics 4 Abilities
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\Abilities;

use Marketing_Analytics_MCP\API_Clients\GA4_Client;
use Marketing_Analytics_MCP\Credentials\Credential_Manager;

/**
 * Registers Google Analytics 4 MCP abilities
 */
class GA4_Abilities {

	/**
	 * Register GA4 abilities
	 */
	public function register() {
		// Only register abilities if credentials are configured
		$credential_manager = new Credential_Manager();
		if ( ! $credential_manager->has_credentials( 'ga4' ) ) {
			return;
		}

		$this->register_get_ga4_metrics();
		$this->register_get_ga4_events();
		$this->register_get_ga4_realtime();
		$this->register_get_traffic_sources();
		$this->register_ga4_overview_resource();
	}

	/**
	 * Register get-ga4-metrics tool
	 */
	private function register_get_ga4_metrics() {
		wp_register_ability(
			'marketing-analytics/get-ga4-metrics',
			array(
				'label'               => __( 'Get GA4 Metrics', 'marketing-analytics-chat' ),
				'description'         => __( 'Retrieve Google Analytics 4 metrics for a specified date range with optional dimensions.', 'marketing-analytics-chat' ),
				'category'            => 'marketing-analytics',

				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'metrics'    => array(
							'type'        => 'array',
							'description' => 'Array of metric names (e.g., activeUsers, sessions, screenPageViews)',
							'items'       => array( 'type' => 'string' ),
						),
						'date_range' => array(
							'type'        => 'string',
							'description' => 'Date range (e.g., "7daysAgo", "yesterday", "2024-01-01")',
							'default'     => '7daysAgo',
						),
						'dimensions' => array(
							'type'        => 'array',
							'description' => 'Array of dimension names (e.g., date, country, deviceCategory)',
							'items'       => array( 'type' => 'string' ),
						),
						'limit'      => array(
							'type'        => 'integer',
							'description' => 'Maximum number of rows to return',
							'default'     => 100,
							'minimum'     => 1,
							'maximum'     => 10000,
						),
					),
					'required'   => array( 'metrics' ),
				),

				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'rows'      => array(
							'type'        => 'array',
							'description' => 'Data rows',
						),
						'totals'    => array(
							'type'        => 'object',
							'description' => 'Total values for metrics',
						),
						'row_count' => array(
							'type'        => 'integer',
							'description' => 'Number of rows returned',
						),
					),
				),

				'execute_callback'    => array( $this, 'execute_get_ga4_metrics' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Register get-ga4-events tool
	 */
	private function register_get_ga4_events() {
		wp_register_ability(
			'marketing-analytics/get-ga4-events',
			array(
				'label'               => __( 'Get GA4 Events', 'marketing-analytics-chat' ),
				'description'         => __( 'Query custom event data from Google Analytics 4.', 'marketing-analytics-chat' ),
				'category'            => 'marketing-analytics',

				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'event_name' => array(
							'type'        => 'string',
							'description' => 'Specific event name to filter (optional)',
						),
						'date_range' => array(
							'type'        => 'string',
							'description' => 'Date range (e.g., "7daysAgo", "yesterday")',
							'default'     => '7daysAgo',
						),
						'limit'      => array(
							'type'        => 'integer',
							'description' => 'Maximum number of events to return',
							'default'     => 100,
							'minimum'     => 1,
							'maximum'     => 1000,
						),
					),
				),

				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'rows'        => array( 'type' => 'array' ),
						'event_count' => array( 'type' => 'integer' ),
					),
				),

				'execute_callback'    => array( $this, 'execute_get_ga4_events' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Register get-ga4-realtime tool
	 */
	private function register_get_ga4_realtime() {
		wp_register_ability(
			'marketing-analytics/get-ga4-realtime',
			array(
				'label'               => __( 'Get GA4 Real-time Data', 'marketing-analytics-chat' ),
				'description'         => __( 'Get real-time user activity from Google Analytics 4.', 'marketing-analytics-chat' ),
				'category'            => 'marketing-analytics',

				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'metrics' => array(
							'type'        => 'array',
							'description' => 'Array of real-time metric names (e.g., activeUsers)',
							'items'       => array( 'type' => 'string' ),
							'default'     => array( 'activeUsers' ),
						),
					),
				),

				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'active_users' => array( 'type' => 'integer' ),
						'data'         => array( 'type' => 'object' ),
					),
				),

				'execute_callback'    => array( $this, 'execute_get_ga4_realtime' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Register get-traffic-sources tool
	 */
	private function register_get_traffic_sources() {
		wp_register_ability(
			'marketing-analytics/get-traffic-sources',
			array(
				'label'               => __( 'Get Traffic Sources', 'marketing-analytics-chat' ),
				'description'         => __( 'Analyze traffic sources and acquisition channels from GA4.', 'marketing-analytics-chat' ),
				'category'            => 'marketing-analytics',

				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'date_range' => array(
							'type'        => 'string',
							'description' => 'Date range (e.g., "7daysAgo", "30daysAgo")',
							'default'     => '7daysAgo',
						),
						'limit'      => array(
							'type'        => 'integer',
							'description' => 'Maximum number of sources to return',
							'default'     => 100,
							'minimum'     => 1,
							'maximum'     => 1000,
						),
					),
				),

				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'sources'  => array( 'type' => 'array' ),
						'channels' => array( 'type' => 'object' ),
					),
				),

				'execute_callback'    => array( $this, 'execute_get_traffic_sources' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Register ga4-overview resource
	 */
	private function register_ga4_overview_resource() {
		wp_register_ability(
			'marketing-analytics/ga4-overview',
			array(
				'label'               => __( 'GA4 Overview', 'marketing-analytics-chat' ),
				'description'         => __( 'Get Google Analytics 4 property summary with key metrics snapshot.', 'marketing-analytics-chat' ),
				'category'            => 'marketing-analytics',

				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'property_id' => array( 'type' => 'string' ),
						'key_metrics' => array( 'type' => 'object' ),
						'period'      => array( 'type' => 'string' ),
					),
				),

				'execute_callback'    => array( $this, 'execute_ga4_overview' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Execute get-ga4-metrics tool
	 *
	 * @param array $args Tool arguments.
	 * @return array Tool result.
	 */
	public function execute_get_ga4_metrics( $args ) {
		try {
			$client = new GA4_Client();

			$data = $client->run_report(
				$args['metrics'],
				$args['dimensions'] ?? array(),
				$args['date_range'] ?? '7daysAgo',
				array( 'limit' => $args['limit'] ?? 100 )
			);

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
	 * Execute get-ga4-events tool
	 *
	 * @param array $args Tool arguments.
	 * @return array Tool result.
	 */
	public function execute_get_ga4_events( $args ) {
		try {
			$client = new GA4_Client();

			$data = $client->get_event_data(
				$args['event_name'] ?? null,
				$args['date_range'] ?? '7daysAgo',
				$args['limit'] ?? 100
			);

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
	 * Execute get-ga4-realtime tool
	 *
	 * @param array $args Tool arguments.
	 * @return array Tool result.
	 */
	public function execute_get_ga4_realtime( $args ) {
		try {
			$client = new GA4_Client();

			$data = $client->get_realtime_data(
				$args['metrics'] ?? array( 'activeUsers' )
			);

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
	 * Execute get-traffic-sources tool
	 *
	 * @param array $args Tool arguments.
	 * @return array Tool result.
	 */
	public function execute_get_traffic_sources( $args ) {
		try {
			$client = new GA4_Client();

			$data = $client->get_traffic_sources(
				$args['date_range'] ?? '7daysAgo',
				$args['limit'] ?? 100
			);

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
	 * Execute ga4-overview resource
	 *
	 * @param array $args Resource arguments.
	 * @return array Resource result.
	 */
	public function execute_ga4_overview( $args ) {
		try {
			$client = new GA4_Client();

			// Get key metrics for last 7 days
			$metrics = array( 'activeUsers', 'sessions', 'screenPageViews', 'bounceRate' );
			$data    = $client->run_report( $metrics, array(), '7daysAgo' );

			$summary = array(
				'property_id' => $client->get_property_id(),
				'period'      => 'Last 7 days',
				'key_metrics' => $data['totals'] ?? array(),
				'row_count'   => $data['row_count'] ?? 0,
			);

			return array(
				'contents' => array(
					array(
						'uri'      => 'ga4://overview',
						'mimeType' => 'application/json',
						'text'     => wp_json_encode( $summary, JSON_PRETTY_PRINT ),
					),
				),
			);
		} catch ( \Exception $e ) {
			return array(
				'contents' => array(
					array(
						'uri'      => 'ga4://overview',
						'mimeType' => 'text/plain',
						'text'     => 'Error: ' . $e->getMessage(),
					),
				),
			);
		}
	}

	/**
	 * Permission callback for all GA4 abilities
	 *
	 * @return bool True if user has permission, false otherwise.
	 */
	public function check_permissions() {
		return current_user_can( 'manage_options' );
	}
}
