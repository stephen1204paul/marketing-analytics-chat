<?php
/**
 * Google Search Console Abilities
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\Abilities;

use Marketing_Analytics_MCP\API_Clients\GSC_Client;
use Marketing_Analytics_MCP\Credentials\Credential_Manager;

/**
 * Registers Google Search Console MCP abilities
 */
class GSC_Abilities {

	/**
	 * Register GSC abilities
	 */
	public function register() {
		// Only register abilities if credentials are configured
		$credential_manager = new Credential_Manager();
		if ( ! $credential_manager->has_credentials( 'gsc' ) ) {
			return;
		}

		$this->register_get_search_performance();
		$this->register_get_top_queries();
		$this->register_get_indexing_status();
		$this->register_gsc_overview_resource();
	}

	/**
	 * Register get-search-performance tool
	 */
	private function register_get_search_performance() {
		wp_register_ability(
			'marketing-analytics/get-search-performance',
			array(
				'label'               => __( 'Get Search Performance', 'marketing-analytics-chat' ),
				'description'         => __( 'Retrieve search performance data from Google Search Console including clicks, impressions, CTR, and position.', 'marketing-analytics-chat' ),
				'category'            => 'marketing-analytics',

				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'date_range' => array(
							'type'        => 'string',
							'description' => 'Date range (e.g., "7daysAgo", "30daysAgo")',
							'default'     => '7daysAgo',
						),
						'dimensions' => array(
							'type'        => 'array',
							'description' => 'Dimensions to group by (query, page, country, device, searchAppearance)',
							'items'       => array(
								'type' => 'string',
								'enum' => array( 'query', 'page', 'country', 'device', 'searchAppearance' ),
							),
						),
						'filters'    => array(
							'type'        => 'object',
							'description' => 'Filters to apply to the query',
						),
						'limit'      => array(
							'type'        => 'integer',
							'description' => 'Maximum number of rows to return',
							'default'     => 100,
							'minimum'     => 1,
							'maximum'     => 25000,
						),
					),
				),

				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'rows'      => array(
							'type'        => 'array',
							'description' => 'Search performance data rows',
						),
						'row_count' => array(
							'type'        => 'integer',
							'description' => 'Number of rows returned',
						),
					),
				),

				'execute_callback'    => array( $this, 'execute_get_search_performance' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Register get-top-queries tool
	 */
	private function register_get_top_queries() {
		wp_register_ability(
			'marketing-analytics/get-top-queries',
			array(
				'label'               => __( 'Get Top Queries', 'marketing-analytics-chat' ),
				'description'         => __( 'Get top-performing search queries from Google Search Console.', 'marketing-analytics-chat' ),
				'category'            => 'marketing-analytics',

				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'date_range'      => array(
							'type'        => 'string',
							'description' => 'Date range (e.g., "7daysAgo", "30daysAgo")',
							'default'     => '7daysAgo',
						),
						'limit'           => array(
							'type'        => 'integer',
							'description' => 'Maximum number of queries to return',
							'default'     => 100,
							'minimum'     => 1,
							'maximum'     => 1000,
						),
						'min_impressions' => array(
							'type'        => 'integer',
							'description' => 'Minimum number of impressions to include',
							'default'     => 10,
							'minimum'     => 0,
						),
					),
				),

				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'rows'      => array(
							'type'        => 'array',
							'description' => 'Top queries with metrics',
						),
						'row_count' => array(
							'type'        => 'integer',
							'description' => 'Number of queries returned',
						),
					),
				),

				'execute_callback'    => array( $this, 'execute_get_top_queries' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Register get-indexing-status tool
	 */
	private function register_get_indexing_status() {
		wp_register_ability(
			'marketing-analytics/get-indexing-status',
			array(
				'label'               => __( 'Get Indexing Status', 'marketing-analytics-chat' ),
				'description'         => __( 'Check page indexing status and coverage issues in Google Search Console.', 'marketing-analytics-chat' ),
				'category'            => 'marketing-analytics',

				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'page_url' => array(
							'type'        => 'string',
							'description' => 'Specific page URL to inspect (optional)',
						),
					),
				),

				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'coverage' => array(
							'type'        => 'object',
							'description' => 'Indexing coverage information',
						),
						'errors'   => array(
							'type'        => 'array',
							'description' => 'Indexing errors',
						),
						'warnings' => array(
							'type'        => 'array',
							'description' => 'Indexing warnings',
						),
					),
				),

				'execute_callback'    => array( $this, 'execute_get_indexing_status' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Register gsc-overview resource
	 */
	private function register_gsc_overview_resource() {
		wp_register_ability(
			'marketing-analytics/gsc-overview',
			array(
				'label'               => __( 'Search Console Overview', 'marketing-analytics-chat' ),
				'description'         => __( 'Get Google Search Console site summary with verification status, indexed pages, and top queries.', 'marketing-analytics-chat' ),
				'category'            => 'marketing-analytics',

				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'site_url'            => array( 'type' => 'string' ),
						'verification_status' => array( 'type' => 'string' ),
						'summary'             => array( 'type' => 'object' ),
					),
				),

				'execute_callback'    => array( $this, 'execute_gsc_overview' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Execute get-search-performance tool
	 *
	 * @param array $args Tool arguments.
	 * @return array Tool result.
	 */
	public function execute_get_search_performance( $args ) {
		try {
			$client = new GSC_Client();

			$data = $client->query_search_analytics(
				$args['date_range'] ?? '7daysAgo',
				$args['dimensions'] ?? array(),
				$args['filters'] ?? array(),
				array( 'row_limit' => $args['limit'] ?? 100 )
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
	 * Execute get-top-queries tool
	 *
	 * @param array $args Tool arguments.
	 * @return array Tool result.
	 */
	public function execute_get_top_queries( $args ) {
		try {
			$client = new GSC_Client();

			$data = $client->get_top_queries(
				$args['date_range'] ?? '7daysAgo',
				$args['limit'] ?? 100,
				$args['min_impressions'] ?? 10
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
	 * Execute get-indexing-status tool
	 *
	 * @param array $args Tool arguments.
	 * @return array Tool result.
	 */
	public function execute_get_indexing_status( $args ) {
		try {
			$client = new GSC_Client();

			if ( ! empty( $args['page_url'] ) ) {
				// Get URL inspection data
				$data = $client->get_url_inspection( $args['page_url'] );
			} else {
				// Get sitemap status
				$data = $client->get_sitemap_status();
			}

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
	 * Execute gsc-overview resource
	 *
	 * @param array $args Resource arguments.
	 * @return array Resource result.
	 */
	public function execute_gsc_overview( $args ) {
		try {
			$client = new GSC_Client();

			// Get top queries for overview
			$top_queries = $client->get_top_queries( '7daysAgo', 10, 5 );

			// Get search performance summary
			$performance = $client->query_search_analytics( '7daysAgo', array(), array(), array( 'row_limit' => 1 ) );

			$summary = array(
				'site_url'    => $client->get_site_url(),
				'period'      => 'Last 7 days',
				'top_queries' => $top_queries,
				'performance' => $performance,
			);

			return array(
				'contents' => array(
					array(
						'uri'      => 'gsc://overview',
						'mimeType' => 'application/json',
						'text'     => wp_json_encode( $summary, JSON_PRETTY_PRINT ),
					),
				),
			);
		} catch ( \Exception $e ) {
			return array(
				'contents' => array(
					array(
						'uri'      => 'gsc://overview',
						'mimeType' => 'text/plain',
						'text'     => 'Error: ' . $e->getMessage(),
					),
				),
			);
		}
	}

	/**
	 * Permission callback for all GSC abilities
	 *
	 * @return bool True if user has permission, false otherwise.
	 */
	public function check_permissions() {
		return current_user_can( 'manage_options' );
	}
}
