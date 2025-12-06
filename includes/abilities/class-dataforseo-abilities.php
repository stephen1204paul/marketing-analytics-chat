<?php
/**
 * DataForSEO Abilities
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\Abilities;

use Marketing_Analytics_MCP\API_Clients\DataForSEO_Client;
use Marketing_Analytics_MCP\Credentials\Credential_Manager;

/**
 * Registers DataForSEO MCP abilities
 */
class DataForSEO_Abilities {

	/**
	 * Register DataForSEO abilities
	 */
	public function register() {
		// Only register abilities if credentials are configured
		$credential_manager = new Credential_Manager();
		if ( ! $credential_manager->has_credentials( 'dataforseo' ) ) {
			return;
		}

		$this->register_get_serp_rankings();
		$this->register_get_keyword_data();
		$this->register_get_backlinks();
		$this->register_analyze_competitors();
		$this->register_get_domain_metrics();
		$this->register_seo_overview_resource();
	}

	/**
	 * Register get-serp-rankings tool
	 */
	private function register_get_serp_rankings() {
		wp_register_ability(
			'marketing-analytics/get-serp-rankings',
			array(
				'label'               => __( 'Get SERP Rankings', 'marketing-analytics-chat' ),
				'description'         => __( 'Track keyword rankings in Google search results for a specified domain.', 'marketing-analytics-chat' ),
				'category'            => 'marketing-analytics',

				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'domain'   => array(
							'type'        => 'string',
							'description' => 'Domain to track rankings for (e.g., example.com)',
						),
						'keywords' => array(
							'type'        => 'array',
							'description' => 'Array of keywords to track',
							'items'       => array( 'type' => 'string' ),
						),
						'location' => array(
							'type'        => 'string',
							'description' => 'Location for search results',
							'default'     => 'United States',
						),
						'language' => array(
							'type'        => 'string',
							'description' => 'Language code',
							'default'     => 'en',
						),
					),
					'required'   => array( 'domain', 'keywords' ),
				),

				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'rankings'     => array(
							'type'        => 'array',
							'description' => 'Array of keyword rankings',
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'keyword'       => array( 'type' => 'string' ),
									'position'      => array( 'type' => array( 'integer', 'string' ) ),
									'search_volume' => array( 'type' => 'integer' ),
									'competition'   => array( 'type' => 'number' ),
								),
							),
						),
						'credits_used' => array(
							'type'        => 'number',
							'description' => 'DataForSEO credits consumed',
						),
					),
				),

				'execute_callback'    => array( $this, 'execute_get_serp_rankings' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Register get-keyword-data tool
	 */
	private function register_get_keyword_data() {
		wp_register_ability(
			'marketing-analytics/get-keyword-data',
			array(
				'label'               => __( 'Get Keyword Data', 'marketing-analytics-chat' ),
				'description'         => __( 'Retrieve search volume, CPC, competition, and trend data for keywords.', 'marketing-analytics-chat' ),
				'category'            => 'marketing-analytics',

				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'keywords' => array(
							'type'        => 'array',
							'description' => 'Array of keywords to research',
							'items'       => array( 'type' => 'string' ),
						),
						'location' => array(
							'type'        => 'string',
							'description' => 'Location for search data',
							'default'     => 'United States',
						),
						'language' => array(
							'type'        => 'string',
							'description' => 'Language code',
							'default'     => 'en',
						),
					),
					'required'   => array( 'keywords' ),
				),

				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'keywords'     => array(
							'type'        => 'array',
							'description' => 'Array of keyword data',
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'keyword'           => array( 'type' => 'string' ),
									'search_volume'     => array( 'type' => 'integer' ),
									'cpc'               => array( 'type' => 'number' ),
									'competition'       => array( 'type' => 'number' ),
									'competition_level' => array( 'type' => 'string' ),
									'trend'             => array( 'type' => 'array' ),
								),
							),
						),
						'credits_used' => array(
							'type'        => 'number',
							'description' => 'DataForSEO credits consumed',
						),
					),
				),

				'execute_callback'    => array( $this, 'execute_get_keyword_data' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Register get-backlinks tool
	 */
	private function register_get_backlinks() {
		wp_register_ability(
			'marketing-analytics/get-backlinks',
			array(
				'label'               => __( 'Get Backlinks', 'marketing-analytics-chat' ),
				'description'         => __( 'Analyze backlinks pointing to a domain including referring domains, anchor text, and authority metrics.', 'marketing-analytics-chat' ),
				'category'            => 'marketing-analytics',

				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'domain'  => array(
							'type'        => 'string',
							'description' => 'Domain to analyze backlinks for',
						),
						'limit'   => array(
							'type'        => 'integer',
							'description' => 'Number of backlinks to retrieve',
							'default'     => 100,
							'minimum'     => 1,
							'maximum'     => 1000,
						),
						'sort_by' => array(
							'type'        => 'string',
							'description' => 'Sort parameter',
							'enum'        => array( 'rank', 'domain_rank', 'backlinks' ),
							'default'     => 'rank',
						),
					),
					'required'   => array( 'domain' ),
				),

				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'summary'      => array(
							'type'        => 'object',
							'description' => 'Backlink summary metrics',
							'properties'  => array(
								'total_backlinks'   => array( 'type' => 'integer' ),
								'referring_domains' => array( 'type' => 'integer' ),
							),
						),
						'backlinks'    => array(
							'type'        => 'array',
							'description' => 'Array of backlink details',
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'domain'      => array( 'type' => 'string' ),
									'url_from'    => array( 'type' => 'string' ),
									'url_to'      => array( 'type' => 'string' ),
									'anchor'      => array( 'type' => 'string' ),
									'dofollow'    => array( 'type' => 'boolean' ),
									'domain_rank' => array( 'type' => 'integer' ),
									'page_rank'   => array( 'type' => 'integer' ),
								),
							),
						),
						'credits_used' => array(
							'type'        => 'number',
							'description' => 'DataForSEO credits consumed',
						),
					),
				),

				'execute_callback'    => array( $this, 'execute_get_backlinks' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Register analyze-competitors tool
	 */
	private function register_analyze_competitors() {
		wp_register_ability(
			'marketing-analytics/analyze-competitors',
			array(
				'label'               => __( 'Analyze Competitors', 'marketing-analytics-chat' ),
				'description'         => __( 'Identify and analyze competitor domains with their organic and paid traffic metrics.', 'marketing-analytics-chat' ),
				'category'            => 'marketing-analytics',

				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'domain' => array(
							'type'        => 'string',
							'description' => 'Domain to find competitors for',
						),
						'limit'  => array(
							'type'        => 'integer',
							'description' => 'Number of competitors to retrieve',
							'default'     => 10,
							'minimum'     => 1,
							'maximum'     => 50,
						),
					),
					'required'   => array( 'domain' ),
				),

				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'competitors'  => array(
							'type'        => 'array',
							'description' => 'Array of competitor domains',
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'domain'              => array( 'type' => 'string' ),
									'avg_position'        => array( 'type' => 'number' ),
									'sum_position'        => array( 'type' => 'number' ),
									'intersections'       => array( 'type' => 'integer' ),
									'full_domain_metrics' => array(
										'type'       => 'object',
										'properties' => array(
											'organic_traffic' => array( 'type' => 'number' ),
											'organic_keywords' => array( 'type' => 'integer' ),
											'paid_traffic' => array( 'type' => 'number' ),
											'paid_keywords' => array( 'type' => 'integer' ),
										),
									),
								),
							),
						),
						'credits_used' => array(
							'type'        => 'number',
							'description' => 'DataForSEO credits consumed',
						),
					),
				),

				'execute_callback'    => array( $this, 'execute_analyze_competitors' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Register get-domain-metrics tool
	 */
	private function register_get_domain_metrics() {
		wp_register_ability(
			'marketing-analytics/get-domain-metrics',
			array(
				'label'               => __( 'Get Domain Metrics', 'marketing-analytics-chat' ),
				'description'         => __( 'Retrieve comprehensive domain authority metrics including rank, backlinks, and organic traffic.', 'marketing-analytics-chat' ),
				'category'            => 'marketing-analytics',

				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'domain' => array(
							'type'        => 'string',
							'description' => 'Domain to get metrics for',
						),
					),
					'required'   => array( 'domain' ),
				),

				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'domain_rank'        => array( 'type' => 'integer' ),
						'backlinks'          => array( 'type' => 'integer' ),
						'referring_domains'  => array( 'type' => 'integer' ),
						'referring_ips'      => array( 'type' => 'integer' ),
						'dofollow_backlinks' => array( 'type' => 'integer' ),
						'nofollow_backlinks' => array( 'type' => 'integer' ),
						'gov_backlinks'      => array( 'type' => 'integer' ),
						'edu_backlinks'      => array( 'type' => 'integer' ),
						'spam_score'         => array( 'type' => 'number' ),
						'organic_metrics'    => array(
							'type'       => 'object',
							'properties' => array(
								'organic_traffic'  => array( 'type' => 'number' ),
								'organic_keywords' => array( 'type' => 'integer' ),
								'organic_cost'     => array( 'type' => 'number' ),
								'visibility'       => array( 'type' => 'number' ),
							),
						),
						'credits_used'       => array(
							'type'        => 'number',
							'description' => 'DataForSEO credits consumed',
						),
					),
				),

				'execute_callback'    => array( $this, 'execute_get_domain_metrics' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Register seo-overview resource
	 */
	private function register_seo_overview_resource() {
		wp_register_ability(
			'marketing-analytics/seo-overview',
			array(
				'label'               => __( 'SEO Overview', 'marketing-analytics-chat' ),
				'description'         => __( 'Get a comprehensive SEO overview including rankings, backlinks, and domain metrics.', 'marketing-analytics-chat' ),
				'category'            => 'marketing-analytics',

				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'domain'          => array( 'type' => 'string' ),
						'domain_metrics'  => array( 'type' => 'object' ),
						'recent_rankings' => array( 'type' => 'array' ),
						'top_backlinks'   => array( 'type' => 'array' ),
						'credit_balance'  => array( 'type' => 'number' ),
						'last_updated'    => array( 'type' => 'string' ),
					),
				),

				'execute_callback'    => array( $this, 'read_seo_overview' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Execute get-serp-rankings
	 *
	 * @param array $arguments Tool arguments.
	 * @return array Response data.
	 */
	public function execute_get_serp_rankings( $arguments ) {
		$client = new DataForSEO_Client();

		$domain   = sanitize_text_field( $arguments['domain'] );
		$keywords = isset( $arguments['keywords'] ) ? array_map( 'sanitize_text_field', $arguments['keywords'] ) : array();
		$location = isset( $arguments['location'] ) ? sanitize_text_field( $arguments['location'] ) : 'United States';
		$language = isset( $arguments['language'] ) ? sanitize_text_field( $arguments['language'] ) : 'en';

		if ( empty( $domain ) || empty( $keywords ) ) {
			return array(
				'error' => __( 'Domain and keywords are required.', 'marketing-analytics-chat' ),
			);
		}

		$data = $client->get_serp_rankings( $domain, $keywords, $location, $language );

		if ( null === $data ) {
			return array(
				'error' => __( 'Failed to retrieve SERP rankings. Please check your connection settings and credit balance.', 'marketing-analytics-chat' ),
			);
		}

		return array(
			'rankings'     => $data,
			'credits_used' => count( $keywords ) * 0.003,
		);
	}

	/**
	 * Execute get-keyword-data
	 *
	 * @param array $arguments Tool arguments.
	 * @return array Response data.
	 */
	public function execute_get_keyword_data( $arguments ) {
		$client = new DataForSEO_Client();

		$keywords = isset( $arguments['keywords'] ) ? array_map( 'sanitize_text_field', $arguments['keywords'] ) : array();
		$location = isset( $arguments['location'] ) ? sanitize_text_field( $arguments['location'] ) : 'United States';
		$language = isset( $arguments['language'] ) ? sanitize_text_field( $arguments['language'] ) : 'en';

		if ( empty( $keywords ) ) {
			return array(
				'error' => __( 'Keywords are required.', 'marketing-analytics-chat' ),
			);
		}

		$data = $client->get_keyword_data( $keywords, $location, $language );

		if ( null === $data ) {
			return array(
				'error' => __( 'Failed to retrieve keyword data. Please check your connection settings and credit balance.', 'marketing-analytics-chat' ),
			);
		}

		return array(
			'keywords'     => $data,
			'credits_used' => count( $keywords ) * 0.002,
		);
	}

	/**
	 * Execute get-backlinks
	 *
	 * @param array $arguments Tool arguments.
	 * @return array Response data.
	 */
	public function execute_get_backlinks( $arguments ) {
		$client = new DataForSEO_Client();

		$domain  = sanitize_text_field( $arguments['domain'] );
		$limit   = isset( $arguments['limit'] ) ? intval( $arguments['limit'] ) : 100;
		$sort_by = isset( $arguments['sort_by'] ) ? sanitize_text_field( $arguments['sort_by'] ) : 'rank';

		if ( empty( $domain ) ) {
			return array(
				'error' => __( 'Domain is required.', 'marketing-analytics-chat' ),
			);
		}

		$data = $client->get_backlinks( $domain, $limit, $sort_by );

		if ( null === $data ) {
			return array(
				'error' => __( 'Failed to retrieve backlinks. Please check your connection settings and credit balance.', 'marketing-analytics-chat' ),
			);
		}

		$data['credits_used'] = 0.02;
		return $data;
	}

	/**
	 * Execute analyze-competitors
	 *
	 * @param array $arguments Tool arguments.
	 * @return array Response data.
	 */
	public function execute_analyze_competitors( $arguments ) {
		$client = new DataForSEO_Client();

		$domain = sanitize_text_field( $arguments['domain'] );
		$limit  = isset( $arguments['limit'] ) ? intval( $arguments['limit'] ) : 10;

		if ( empty( $domain ) ) {
			return array(
				'error' => __( 'Domain is required.', 'marketing-analytics-chat' ),
			);
		}

		$data = $client->analyze_competitors( $domain, $limit );

		if ( null === $data ) {
			return array(
				'error' => __( 'Failed to analyze competitors. Please check your connection settings and credit balance.', 'marketing-analytics-chat' ),
			);
		}

		return array(
			'competitors'  => $data,
			'credits_used' => 0.02,
		);
	}

	/**
	 * Execute get-domain-metrics
	 *
	 * @param array $arguments Tool arguments.
	 * @return array Response data.
	 */
	public function execute_get_domain_metrics( $arguments ) {
		$client = new DataForSEO_Client();

		$domain = sanitize_text_field( $arguments['domain'] );

		if ( empty( $domain ) ) {
			return array(
				'error' => __( 'Domain is required.', 'marketing-analytics-chat' ),
			);
		}

		$data = $client->get_domain_metrics( $domain );

		if ( null === $data ) {
			return array(
				'error' => __( 'Failed to retrieve domain metrics. Please check your connection settings and credit balance.', 'marketing-analytics-chat' ),
			);
		}

		$data['credits_used'] = 0.01;
		return $data;
	}

	/**
	 * Read seo-overview resource
	 *
	 * @return array Resource data.
	 */
	public function read_seo_overview() {
		$client = new DataForSEO_Client();

		// Get the site's domain
		$domain = wp_parse_url( home_url(), PHP_URL_HOST );

		// Get domain metrics
		$metrics = $client->get_domain_metrics( $domain );

		// Get some recent tracked keywords from options (if any)
		$tracked_keywords = get_option( 'marketing_analytics_mcp_tracked_keywords', array() );
		$recent_rankings  = array();

		if ( ! empty( $tracked_keywords ) ) {
			$sample_keywords = array_slice( $tracked_keywords, 0, 5 );
			$rankings_data   = $client->get_serp_rankings( $domain, $sample_keywords );
			if ( $rankings_data ) {
				$recent_rankings = $rankings_data;
			}
		}

		// Get top backlinks
		$backlinks_data = $client->get_backlinks( $domain, 10, 'rank' );
		$top_backlinks  = array();
		if ( $backlinks_data && isset( $backlinks_data['backlinks'] ) ) {
			$top_backlinks = $backlinks_data['backlinks'];
		}

		// Get credit balance
		$credit_balance = $client->get_credit_balance();

		return array(
			'domain'          => $domain,
			'domain_metrics'  => $metrics ?: array(),
			'recent_rankings' => $recent_rankings,
			'top_backlinks'   => $top_backlinks,
			'credit_balance'  => $credit_balance,
			'last_updated'    => current_time( 'mysql' ),
		);
	}

	/**
	 * Check permissions for abilities
	 *
	 * @return bool True if user has permission.
	 */
	public function check_permissions() {
		return current_user_can( 'manage_options' );
	}
}
