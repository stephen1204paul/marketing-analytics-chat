<?php
/**
 * DataForSEO API Client
 *
 * Handles interactions with DataForSEO API for SERP tracking, keyword research, backlinks, and domain metrics.
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\API_Clients;

use Marketing_Analytics_MCP\Credentials\Credential_Manager;
use Marketing_Analytics_MCP\Cache\Cache_Manager;
use Marketing_Analytics_MCP\Utils\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * DataForSEO API Client class
 */
class DataForSEO_Client {

	/**
	 * API Base URL
	 */
	const API_BASE_URL = 'https://api.dataforseo.com/v3/';

	/**
	 * Credential Manager instance
	 *
	 * @var Credential_Manager
	 */
	private $credential_manager;

	/**
	 * Cache Manager instance
	 *
	 * @var Cache_Manager
	 */
	private $cache_manager;

	/**
	 * Guzzle HTTP Client
	 *
	 * @var Client
	 */
	private $http_client;

	/**
	 * API credentials
	 *
	 * @var array
	 */
	private $credentials;

	/**
	 * Current credit balance
	 *
	 * @var float
	 */
	private $credit_balance;

	/**
	 * Credit warning threshold
	 *
	 * @var float
	 */
	private $credit_threshold = 10.0;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->credential_manager = new Credential_Manager();
		$this->cache_manager      = new Cache_Manager();

		// Get credentials
		$this->credentials = $this->credential_manager->get_credentials( 'dataforseo' );

		// Initialize HTTP client if credentials exist
		if ( ! empty( $this->credentials ) ) {
			$this->init_http_client();
			$this->update_credit_balance();
		}
	}

	/**
	 * Initialize HTTP client with authentication
	 *
	 * @return void
	 */
	private function init_http_client() {
		if ( empty( $this->credentials['login'] ) || empty( $this->credentials['password'] ) ) {
			return;
		}

		$this->http_client = new Client(
			array(
				'base_uri' => self::API_BASE_URL,
				'timeout'  => 30,
				'auth'     => array( $this->credentials['login'], $this->credentials['password'] ),
				'headers'  => array(
					'Content-Type' => 'application/json',
				),
			)
		);
	}

	/**
	 * Update credit balance from API
	 *
	 * @return void
	 */
	private function update_credit_balance() {
		if ( ! $this->http_client ) {
			return;
		}

		try {
			$response = $this->http_client->get( 'appendix/user_data' );
			$data     = json_decode( $response->getBody()->getContents(), true );

			if ( isset( $data['tasks'][0]['result'][0]['money']['balance'] ) ) {
				$this->credit_balance = floatval( $data['tasks'][0]['result'][0]['money']['balance'] );
				update_option( 'marketing_analytics_mcp_dataforseo_balance', $this->credit_balance );

				// Check if balance is below threshold
				if ( $this->credit_balance < $this->credit_threshold ) {
					$this->trigger_low_credit_warning();
				}
			}
		} catch ( RequestException $e ) {
			Logger::debug( 'Failed to get DataForSEO balance: ' . $e->getMessage() );
		}
	}

	/**
	 * Trigger low credit warning
	 *
	 * @return void
	 */
	private function trigger_low_credit_warning() {
		$notice = sprintf(
			/* translators: %s: credit balance */
			__( 'DataForSEO credit balance is low: $%s. Please add credits to continue using the service.', 'marketing-analytics-chat' ),
			number_format( $this->credit_balance, 2 )
		);

		// Store admin notice
		$notices                          = get_option( 'marketing_analytics_mcp_admin_notices', array() );
		$notices['dataforseo_low_credit'] = array(
			'type'        => 'warning',
			'message'     => $notice,
			'dismissible' => true,
		);
		update_option( 'marketing_analytics_mcp_admin_notices', $notices );
	}

	/**
	 * Check if we have sufficient credits for an operation
	 *
	 * @param float $estimated_cost Estimated cost of the operation.
	 *
	 * @return bool True if sufficient credits, false otherwise.
	 */
	private function has_sufficient_credits( $estimated_cost = 0.01 ) {
		if ( null === $this->credit_balance ) {
			$this->update_credit_balance();
		}

		return $this->credit_balance >= $estimated_cost;
	}

	/**
	 * Get SERP rankings for keywords
	 *
	 * @param string $domain Domain to track.
	 * @param array  $keywords Array of keywords to track.
	 * @param string $location Location for search (e.g., "United States").
	 * @param string $language Language code (e.g., "en").
	 *
	 * @return array|null SERP data or null on failure.
	 */
	public function get_serp_rankings( $domain, $keywords = array(), $location = 'United States', $language = 'en' ) {
		if ( ! $this->http_client || empty( $keywords ) ) {
			return null;
		}

		// Check credits (estimated cost: $0.003 per keyword)
		$estimated_cost = count( $keywords ) * 0.003;
		if ( ! $this->has_sufficient_credits( $estimated_cost ) ) {
			Logger::debug( 'Insufficient DataForSEO credits for SERP tracking' );
			return null;
		}

		$cache_key = 'dataforseo_serp_' . md5( $domain . serialize( $keywords ) . $location );
		$cached    = $this->cache_manager->get( $cache_key );

		if ( $cached !== false ) {
			return $cached;
		}

		try {
			$tasks = array();
			foreach ( $keywords as $keyword ) {
				$tasks[] = array(
					'keyword'       => $keyword,
					'location_name' => $location,
					'language_name' => $language,
					'device'        => 'desktop',
					'os'            => 'windows',
					'depth'         => 100,
				);
			}

			$response = $this->http_client->post(
				'serp/google/organic/live/regular',
				array(
					'json' => $tasks,
				)
			);

			$data = json_decode( $response->getBody()->getContents(), true );

			// Process results to find domain rankings
			$rankings = array();
			if ( isset( $data['tasks'] ) ) {
				foreach ( $data['tasks'] as $task ) {
					if ( isset( $task['result'][0]['items'] ) ) {
						$keyword  = $task['data']['keyword'];
						$position = null;

						foreach ( $task['result'][0]['items'] as $index => $item ) {
							if ( isset( $item['url'] ) && strpos( $item['url'], $domain ) !== false ) {
								$position = $index + 1;
								break;
							}
						}

						$rankings[] = array(
							'keyword'       => $keyword,
							'position'      => $position ?: 'Not in top 100',
							'search_volume' => $task['result'][0]['keyword_info']['search_volume'] ?? null,
							'competition'   => $task['result'][0]['keyword_info']['competition'] ?? null,
						);
					}
				}
			}

			// Cache for 1 hour
			$this->cache_manager->set( $cache_key, $rankings, 3600 );

			// Update credit balance after operation
			$this->update_credit_balance();

			return $rankings;

		} catch ( RequestException $e ) {
			Logger::debug( 'SERP API error: ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Get keyword research data
	 *
	 * @param array  $keywords Array of keywords to research.
	 * @param string $location Location for search data.
	 * @param string $language Language code.
	 *
	 * @return array|null Keyword data or null on failure.
	 */
	public function get_keyword_data( $keywords = array(), $location = 'United States', $language = 'en' ) {
		if ( ! $this->http_client || empty( $keywords ) ) {
			return null;
		}

		// Check credits (estimated cost: $0.002 per keyword)
		$estimated_cost = count( $keywords ) * 0.002;
		if ( ! $this->has_sufficient_credits( $estimated_cost ) ) {
			Logger::debug( 'Insufficient DataForSEO credits for keyword research' );
			return null;
		}

		$cache_key = 'dataforseo_keywords_' . md5( serialize( $keywords ) . $location );
		$cached    = $this->cache_manager->get( $cache_key );

		if ( $cached !== false ) {
			return $cached;
		}

		try {
			$task = array(
				array(
					'keywords'      => $keywords,
					'location_name' => $location,
					'language_name' => $language,
				),
			);

			$response = $this->http_client->post(
				'keywords_data/google/search_volume/live',
				array(
					'json' => $task,
				)
			);

			$data = json_decode( $response->getBody()->getContents(), true );

			$keyword_data = array();
			if ( isset( $data['tasks'][0]['result'] ) ) {
				foreach ( $data['tasks'][0]['result'] as $result ) {
					$keyword_data[] = array(
						'keyword'           => $result['keyword'],
						'search_volume'     => $result['search_volume'],
						'cpc'               => $result['cpc'],
						'competition'       => $result['competition'],
						'competition_level' => $result['competition_level'],
						'trend'             => $result['monthly_searches'] ?? array(),
					);
				}
			}

			// Cache for 6 hours
			$this->cache_manager->set( $cache_key, $keyword_data, 21600 );

			// Update credit balance
			$this->update_credit_balance();

			return $keyword_data;

		} catch ( RequestException $e ) {
			Logger::debug( 'Keyword API error: ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Get backlink analysis
	 *
	 * @param string $domain Domain to analyze.
	 * @param int    $limit Number of backlinks to retrieve.
	 * @param string $sort_by Sort parameter (rank, domain_rank, backlinks).
	 *
	 * @return array|null Backlink data or null on failure.
	 */
	public function get_backlinks( $domain, $limit = 100, $sort_by = 'rank' ) {
		if ( ! $this->http_client ) {
			return null;
		}

		// Check credits (estimated cost: $0.02)
		if ( ! $this->has_sufficient_credits( 0.02 ) ) {
			Logger::debug( 'Insufficient DataForSEO credits for backlink analysis' );
			return null;
		}

		$cache_key = 'dataforseo_backlinks_' . md5( $domain . $limit . $sort_by );
		$cached    = $this->cache_manager->get( $cache_key );

		if ( $cached !== false ) {
			return $cached;
		}

		try {
			$task = array(
				array(
					'target'   => $domain,
					'mode'     => 'as_is',
					'filters'  => array( array( 'dofollow', '=', true ) ),
					'order_by' => array( $sort_by . ':desc' ),
					'limit'    => $limit,
				),
			);

			$response = $this->http_client->post(
				'backlinks/backlinks/live',
				array(
					'json' => $task,
				)
			);

			$data = json_decode( $response->getBody()->getContents(), true );

			$backlinks = array();
			if ( isset( $data['tasks'][0]['result'][0]['items'] ) ) {
				foreach ( $data['tasks'][0]['result'][0]['items'] as $item ) {
					$backlinks[] = array(
						'domain'      => $item['domain_from'],
						'url_from'    => $item['url_from'],
						'url_to'      => $item['url_to'],
						'anchor'      => $item['anchor'],
						'dofollow'    => $item['dofollow'],
						'domain_rank' => $item['domain_from_rank'] ?? null,
						'page_rank'   => $item['page_from_rank'] ?? null,
						'first_seen'  => $item['first_seen'] ?? null,
						'last_seen'   => $item['last_seen'] ?? null,
					);
				}
			}

			// Also get summary metrics
			$summary = array(
				'total_backlinks'   => $data['tasks'][0]['result'][0]['total_count'] ?? 0,
				'referring_domains' => $data['tasks'][0]['result'][0]['items_count'] ?? 0,
			);

			$result = array(
				'summary'   => $summary,
				'backlinks' => $backlinks,
			);

			// Cache for 12 hours
			$this->cache_manager->set( $cache_key, $result, 43200 );

			// Update credit balance
			$this->update_credit_balance();

			return $result;

		} catch ( RequestException $e ) {
			Logger::debug( 'Backlinks API error: ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Analyze competitors
	 *
	 * @param string $domain Domain to analyze competitors for.
	 * @param int    $limit Number of competitors to retrieve.
	 *
	 * @return array|null Competitor data or null on failure.
	 */
	public function analyze_competitors( $domain, $limit = 10 ) {
		if ( ! $this->http_client ) {
			return null;
		}

		// Check credits (estimated cost: $0.02)
		if ( ! $this->has_sufficient_credits( 0.02 ) ) {
			Logger::debug( 'Insufficient DataForSEO credits for competitor analysis' );
			return null;
		}

		$cache_key = 'dataforseo_competitors_' . md5( $domain . $limit );
		$cached    = $this->cache_manager->get( $cache_key );

		if ( $cached !== false ) {
			return $cached;
		}

		try {
			$task = array(
				array(
					'target' => $domain,
					'limit'  => $limit,
				),
			);

			$response = $this->http_client->post(
				'dataforseo_labs/google/competitors_domain/live',
				array(
					'json' => $task,
				)
			);

			$data = json_decode( $response->getBody()->getContents(), true );

			$competitors = array();
			if ( isset( $data['tasks'][0]['result'][0]['items'] ) ) {
				foreach ( $data['tasks'][0]['result'][0]['items'] as $item ) {
					$competitors[] = array(
						'domain'              => $item['domain'],
						'avg_position'        => $item['avg_position'] ?? null,
						'sum_position'        => $item['sum_position'] ?? null,
						'intersections'       => $item['intersections'] ?? null,
						'full_domain_metrics' => array(
							'organic_traffic'  => $item['full_domain_metrics']['organic']['etv'] ?? null,
							'organic_keywords' => $item['full_domain_metrics']['organic']['count'] ?? null,
							'paid_traffic'     => $item['full_domain_metrics']['paid']['etv'] ?? null,
							'paid_keywords'    => $item['full_domain_metrics']['paid']['count'] ?? null,
						),
					);
				}
			}

			// Cache for 6 hours
			$this->cache_manager->set( $cache_key, $competitors, 21600 );

			// Update credit balance
			$this->update_credit_balance();

			return $competitors;

		} catch ( RequestException $e ) {
			Logger::debug( 'Competitors API error: ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Get domain metrics
	 *
	 * @param string $domain Domain to get metrics for.
	 *
	 * @return array|null Domain metrics or null on failure.
	 */
	public function get_domain_metrics( $domain ) {
		if ( ! $this->http_client ) {
			return null;
		}

		// Check credits (estimated cost: $0.01)
		if ( ! $this->has_sufficient_credits( 0.01 ) ) {
			Logger::debug( 'Insufficient DataForSEO credits for domain metrics' );
			return null;
		}

		$cache_key = 'dataforseo_domain_' . md5( $domain );
		$cached    = $this->cache_manager->get( $cache_key );

		if ( $cached !== false ) {
			return $cached;
		}

		try {
			$task = array(
				array(
					'target' => $domain,
				),
			);

			// Get domain metrics
			$response = $this->http_client->post(
				'backlinks/domain_metrics/live',
				array(
					'json' => $task,
				)
			);

			$data = json_decode( $response->getBody()->getContents(), true );

			$metrics = array();
			if ( isset( $data['tasks'][0]['result'][0] ) ) {
				$result  = $data['tasks'][0]['result'][0];
				$metrics = array(
					'domain_rank'        => $result['rank'] ?? null,
					'backlinks'          => $result['backlinks'] ?? 0,
					'referring_domains'  => $result['referring_domains'] ?? 0,
					'referring_ips'      => $result['referring_ips'] ?? 0,
					'referring_subnets'  => $result['referring_subnets'] ?? 0,
					'dofollow_backlinks' => $result['dofollow'] ?? 0,
					'nofollow_backlinks' => $result['nofollow'] ?? 0,
					'gov_backlinks'      => $result['gov'] ?? 0,
					'edu_backlinks'      => $result['edu'] ?? 0,
					'broken_backlinks'   => $result['broken_backlinks'] ?? 0,
					'broken_pages'       => $result['broken_pages'] ?? 0,
					'spam_score'         => $result['spam_score'] ?? null,
				);
			}

			// Get organic traffic metrics
			$traffic_response = $this->http_client->post(
				'dataforseo_labs/google/historical_rank_overview/live',
				array(
					'json' => array(
						array(
							'target'        => $domain,
							'location_code' => 2840,  // United States
							'language_code' => 'en',
						),
					),
				)
			);

			$traffic_data = json_decode( $traffic_response->getBody()->getContents(), true );

			if ( isset( $traffic_data['tasks'][0]['result'][0]['items'] ) ) {
				$latest                     = end( $traffic_data['tasks'][0]['result'][0]['items'] );
				$metrics['organic_metrics'] = array(
					'organic_traffic'  => $latest['metrics']['organic']['etv'] ?? 0,
					'organic_keywords' => $latest['metrics']['organic']['count'] ?? 0,
					'organic_cost'     => $latest['metrics']['organic']['estimated_paid_traffic_cost'] ?? 0,
					'visibility'       => $latest['metrics']['organic']['impressions_etv'] ?? 0,
				);
			}

			// Cache for 6 hours
			$this->cache_manager->set( $cache_key, $metrics, 21600 );

			// Update credit balance
			$this->update_credit_balance();

			return $metrics;

		} catch ( RequestException $e ) {
			Logger::debug( 'Domain metrics API error: ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Test connection to DataForSEO API
	 *
	 * @return bool True if connection successful, false otherwise.
	 */
	public function test_connection() {
		if ( ! $this->http_client ) {
			return false;
		}

		try {
			$response = $this->http_client->get( 'appendix/user_data' );
			$data     = json_decode( $response->getBody()->getContents(), true );

			return isset( $data['status_code'] ) && $data['status_code'] === 20000;

		} catch ( RequestException $e ) {
			Logger::debug( 'DataForSEO connection test failed: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Get current credit balance
	 *
	 * @return float Credit balance.
	 */
	public function get_credit_balance() {
		if ( null === $this->credit_balance ) {
			$this->update_credit_balance();
		}

		return $this->credit_balance ?: 0.0;
	}

	/**
	 * Get API usage statistics
	 *
	 * @return array|null Usage statistics or null on failure.
	 */
	public function get_usage_statistics() {
		if ( ! $this->http_client ) {
			return null;
		}

		try {
			$response = $this->http_client->get( 'appendix/user_data' );
			$data     = json_decode( $response->getBody()->getContents(), true );

			if ( isset( $data['tasks'][0]['result'][0] ) ) {
				$result = $data['tasks'][0]['result'][0];
				return array(
					'balance'          => $result['money']['balance'] ?? 0,
					'total_spent'      => $result['money']['total'] ?? 0,
					'api_calls_today'  => $result['rates']['requests']['day'] ?? 0,
					'api_calls_minute' => $result['rates']['requests']['minute'] ?? 0,
					'limits'           => array(
						'day_limit'    => $result['rates']['limits']['day']['requests'] ?? null,
						'minute_limit' => $result['rates']['limits']['minute']['requests'] ?? null,
					),
				);
			}

			return null;

		} catch ( RequestException $e ) {
			Logger::debug( 'Failed to get usage statistics: ' . $e->getMessage() );
			return null;
		}
	}
}
