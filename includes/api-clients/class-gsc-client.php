<?php
/**
 * Google Search Console API Client
 *
 * Handles interactions with Google Search Console API.
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\API_Clients;

use Marketing_Analytics_MCP\Credentials\OAuth_Handler;
use Marketing_Analytics_MCP\Cache\Cache_Manager;

/**
 * Google Search Console API Client class
 */
class GSC_Client {

	/**
	 * OAuth Handler instance
	 *
	 * @var OAuth_Handler
	 */
	private $oauth_handler;

	/**
	 * Cache Manager instance
	 *
	 * @var Cache_Manager
	 */
	private $cache_manager;

	/**
	 * Site URL
	 *
	 * @var string
	 */
	private $site_url;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->oauth_handler = new OAuth_Handler();
		$this->cache_manager = new Cache_Manager();

		// Get configured site URL from options
		$this->site_url = get_option( 'marketing_analytics_mcp_gsc_site_url' );
	}

	/**
	 * Initialize Google Search Console API client
	 *
	 * @return \Google\Service\SearchConsole|null Search Console service or null on failure.
	 */
	private function init_search_console_client() {
		$access_token = $this->oauth_handler->get_access_token( 'gsc' );

		if ( empty( $access_token ) ) {
			return null;
		}

		try {
			$client = new \Google\Client();
			$client->setAccessToken( $access_token );

			return new \Google\Service\SearchConsole( $client );
		} catch ( \Exception $e ) {
			error_log( 'Marketing Analytics MCP: Failed to initialize GSC client: ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Query search analytics data
	 *
	 * @param string $date_range Date range (e.g., '7daysAgo' or specific dates).
	 * @param array  $dimensions Dimensions to group by (query, page, country, device, searchAppearance).
	 * @param array  $filters Filters to apply.
	 * @param array  $options Additional options (row_limit, start_row).
	 * @return array|null Search analytics data or null on failure.
	 */
	public function query_search_analytics( $date_range = '7daysAgo', $dimensions = array(), $filters = array(), $options = array() ) {
		if ( empty( $this->site_url ) ) {
			throw new \Exception( 'GSC site URL not configured' );
		}

		// Check cache first
		$cache_key = $this->cache_manager->generate_key( 'gsc', 'search_analytics', array(
			'date_range' => $date_range,
			'dimensions' => $dimensions,
			'filters'    => $filters,
			'options'    => $options,
		) );

		$cached = $this->cache_manager->get( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$search_console = $this->init_search_console_client();

		if ( null === $search_console ) {
			throw new \Exception( 'Failed to initialize GSC client' );
		}

		try {
			// Parse date range
			list( $start_date, $end_date ) = $this->parse_date_range( $date_range );

			// Build request
			$request = new \Google\Service\SearchConsole\SearchAnalyticsQueryRequest();
			$request->setStartDate( $start_date );
			$request->setEndDate( $end_date );

			// Set dimensions
			if ( ! empty( $dimensions ) ) {
				$request->setDimensions( $dimensions );
			}

			// Set filters
			if ( ! empty( $filters ) ) {
				$request->setDimensionFilterGroups( $this->build_filters( $filters ) );
			}

			// Set row limit
			if ( isset( $options['row_limit'] ) ) {
				$request->setRowLimit( absint( $options['row_limit'] ) );
			} else {
				$request->setRowLimit( 100 ); // Default limit
			}

			// Set start row
			if ( isset( $options['start_row'] ) ) {
				$request->setStartRow( absint( $options['start_row'] ) );
			}

			// Execute query
			$response = $search_console->searchanalytics->query( $this->site_url, $request );

			// Parse response
			$data = $this->parse_search_analytics_response( $response );

			// Cache for 24 hours (GSC data has 2-3 day delay)
			$this->cache_manager->set( $cache_key, $data, $this->cache_manager->get_default_ttl( 'gsc' ) );

			return $data;
		} catch ( \Exception $e ) {
			throw new \Exception( 'GSC API error: ' . $e->getMessage() );
		}
	}

	/**
	 * Get top queries
	 *
	 * @param string $date_range Date range.
	 * @param int    $limit Result limit.
	 * @param int    $min_impressions Minimum impressions filter.
	 * @return array|null Top queries data or null on failure.
	 */
	public function get_top_queries( $date_range = '7daysAgo', $limit = 100, $min_impressions = 10 ) {
		$dimensions = array( 'query' );
		$options    = array( 'row_limit' => $limit );

		$data = $this->query_search_analytics( $date_range, $dimensions, array(), $options );

		if ( null === $data || empty( $data['rows'] ) ) {
			return $data;
		}

		// Filter by minimum impressions
		if ( $min_impressions > 0 ) {
			$data['rows'] = array_filter( $data['rows'], function( $row ) use ( $min_impressions ) {
				return isset( $row['impressions'] ) && $row['impressions'] >= $min_impressions;
			} );

			// Re-index array
			$data['rows'] = array_values( $data['rows'] );
		}

		return $data;
	}

	/**
	 * Get URL inspection data
	 *
	 * @param string $url URL to inspect.
	 * @return array|null Inspection data or null on failure.
	 */
	public function get_url_inspection( $url ) {
		if ( empty( $this->site_url ) ) {
			throw new \Exception( 'GSC site URL not configured' );
		}

		$search_console = $this->init_search_console_client();

		if ( null === $search_console ) {
			throw new \Exception( 'Failed to initialize GSC client' );
		}

		try {
			$request = new \Google\Service\SearchConsole\InspectUrlIndexRequest();
			$request->setInspectionUrl( $url );
			$request->setSiteUrl( $this->site_url );

			$response = $search_console->urlInspection_index->inspect( $request );

			return array(
				'coverage_state'    => $response->getInspectionResult()->getIndexStatusResult()->getCoverageState(),
				'crawled_as'        => $response->getInspectionResult()->getIndexStatusResult()->getCrawledAs(),
				'indexing_state'    => $response->getInspectionResult()->getIndexStatusResult()->getIndexingState(),
				'last_crawl_time'   => $response->getInspectionResult()->getIndexStatusResult()->getLastCrawlTime(),
				'page_fetch_state'  => $response->getInspectionResult()->getIndexStatusResult()->getPageFetchState(),
			);
		} catch ( \Exception $e ) {
			throw new \Exception( 'GSC URL Inspection error: ' . $e->getMessage() );
		}
	}

	/**
	 * Get sitemap status
	 *
	 * @return array|null Sitemap data or null on failure.
	 */
	public function get_sitemap_status() {
		if ( empty( $this->site_url ) ) {
			throw new \Exception( 'GSC site URL not configured' );
		}

		$search_console = $this->init_search_console_client();

		if ( null === $search_console ) {
			throw new \Exception( 'Failed to initialize GSC client' );
		}

		try {
			$sitemaps = $search_console->sitemaps->listSitemaps( $this->site_url );

			$sitemap_data = array();

			if ( $sitemaps->getSitemap() ) {
				foreach ( $sitemaps->getSitemap() as $sitemap ) {
					$sitemap_data[] = array(
						'path'          => $sitemap->getPath(),
						'last_submitted' => $sitemap->getLastSubmitted(),
						'is_pending'    => $sitemap->getIsPending(),
						'is_sitemaps_index' => $sitemap->getIsSitemapsIndex(),
						'warnings'      => $sitemap->getWarnings(),
						'errors'        => $sitemap->getErrors(),
					);
				}
			}

			return $sitemap_data;
		} catch ( \Exception $e ) {
			throw new \Exception( 'GSC Sitemap error: ' . $e->getMessage() );
		}
	}

	/**
	 * List available sites for the connected account
	 *
	 * @return array|null Array of sites or null on failure.
	 */
	public function list_sites() {
		$access_token = $this->oauth_handler->get_access_token( 'gsc' );

		if ( empty( $access_token ) ) {
			return null;
		}

		try {
			$client = new \Google\Client();
			$client->setAccessToken( $access_token );

			$search_console = new \Google\Service\SearchConsole( $client );

			$sites_list = $search_console->sites->listSites();

			$sites = array();

			if ( $sites_list->getSiteEntry() ) {
				foreach ( $sites_list->getSiteEntry() as $site ) {
					$sites[] = array(
						'site_url'           => $site->getSiteUrl(),
						'permission_level'   => $site->getPermissionLevel(),
					);
				}
			}

			return $sites;
		} catch ( \Exception $e ) {
			error_log( 'Marketing Analytics MCP: Failed to list GSC sites: ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Set GSC site URL
	 *
	 * @param string $site_url Site URL.
	 * @return bool True on success, false on failure.
	 */
	public function set_site_url( $site_url ) {
		$this->site_url = $site_url;
		return update_option( 'marketing_analytics_mcp_gsc_site_url', $site_url, false );
	}

	/**
	 * Get configured site URL
	 *
	 * @return string|null Site URL or null if not set.
	 */
	public function get_site_url() {
		return $this->site_url;
	}

	/**
	 * Parse date range string
	 *
	 * @param string $date_range Date range string (e.g., '7daysAgo', 'yesterday').
	 * @return array Array with start_date and end_date.
	 */
	private function parse_date_range( $date_range ) {
		// If it's already a comma-separated date range
		if ( strpos( $date_range, ',' ) !== false ) {
			list( $start, $end ) = explode( ',', $date_range );
			return array( trim( $start ), trim( $end ) );
		}

		// Handle relative dates
		$end_date = gmdate( 'Y-m-d', strtotime( '-3 days' ) ); // GSC has 2-3 day delay

		// Parse start date
		if ( preg_match( '/^(\d+)daysAgo$/', $date_range, $matches ) ) {
			$days = absint( $matches[1] );
			$start_date = gmdate( 'Y-m-d', strtotime( '-' . ( $days + 3 ) . ' days' ) );
		} elseif ( 'yesterday' === $date_range ) {
			$start_date = gmdate( 'Y-m-d', strtotime( '-4 days' ) );
			$end_date   = gmdate( 'Y-m-d', strtotime( '-3 days' ) );
		} elseif ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_range ) ) {
			// Specific date
			$start_date = $date_range;
			$end_date   = $date_range;
		} else {
			// Default to last 7 days
			$start_date = gmdate( 'Y-m-d', strtotime( '-10 days' ) );
		}

		return array( $start_date, $end_date );
	}

	/**
	 * Build filters for search analytics query
	 *
	 * @param array $filters Filter array.
	 * @return array Filter groups for API.
	 */
	private function build_filters( $filters ) {
		$filter_groups = array();

		// TODO: Implement dimension filters
		// Example: filter by country, device, etc.

		return $filter_groups;
	}

	/**
	 * Parse search analytics response
	 *
	 * @param mixed $response API response object.
	 * @return array Parsed data.
	 */
	private function parse_search_analytics_response( $response ) {
		$data = array(
			'rows' => array(),
		);

		if ( $response->getRows() ) {
			foreach ( $response->getRows() as $row ) {
				$row_data = array(
					'clicks'      => $row->getClicks(),
					'impressions' => $row->getImpressions(),
					'ctr'         => $row->getCtr(),
					'position'    => $row->getPosition(),
				);

				// Add dimensions
				if ( $row->getKeys() ) {
					$keys = $row->getKeys();
					$dimensions = array();

					// Map keys to dimension names
					// Note: Order matters and depends on requested dimensions
					foreach ( $keys as $key ) {
						$dimensions[] = $key;
					}

					$row_data['keys'] = $dimensions;

					// For convenience, if only one dimension, add it directly
					if ( count( $dimensions ) === 1 ) {
						$row_data['key'] = $dimensions[0];
					}
				}

				$data['rows'][] = $row_data;
			}
		}

		// Add total row count
		$data['row_count'] = count( $data['rows'] );

		return $data;
	}
}
