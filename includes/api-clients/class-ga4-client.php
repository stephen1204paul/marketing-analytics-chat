<?php
/**
 * Google Analytics 4 API Client
 *
 * Handles interactions with Google Analytics Data API v1 for GA4 properties.
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\API_Clients;

use Marketing_Analytics_MCP\Credentials\OAuth_Handler;
use Marketing_Analytics_MCP\Cache\Cache_Manager;
use Marketing_Analytics_MCP\Utils\Logger;

/**
 * GA4 API Client class
 */
class GA4_Client {

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
	 * GA4 Property ID
	 *
	 * @var string
	 */
	private $property_id;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->oauth_handler = new OAuth_Handler();
		$this->cache_manager = new Cache_Manager();

		// Get configured property ID from options
		$this->property_id = get_option( 'marketing_analytics_mcp_ga4_property_id' );
	}

	/**
	 * Initialize Google Analytics Data API client
	 *
	 * @return \Google\Service\AnalyticsData|null Analytics Data service or null on failure.
	 */
	private function init_analytics_client() {
		$access_token = $this->oauth_handler->get_access_token( 'ga4' );

		if ( empty( $access_token ) ) {
			return null;
		}

		try {
			$client = new \Google\Client();
			$client->setAccessToken( $access_token );

			return new \Google\Service\AnalyticsData( $client );
		} catch ( \Exception $e ) {
			Logger::debug( 'Failed to initialize GA4 client: ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Run a GA4 report
	 *
	 * @param array  $metrics Array of metric names (e.g., ['activeUsers', 'sessions']).
	 * @param array  $dimensions Array of dimension names (e.g., ['date', 'country']).
	 * @param string $date_range Date range string (e.g., '7daysAgo', 'yesterday').
	 * @param array  $options Additional options (limit, offset, filters, etc.).
	 * @return array|null Report data or null on failure.
	 */
	public function run_report( $metrics, $dimensions = array(), $date_range = '7daysAgo', $options = array() ) {
		if ( empty( $this->property_id ) ) {
			throw new \Exception( 'GA4 property ID not configured' );
		}

		// Check cache first
		$cache_key = $this->cache_manager->generate_key(
			'ga4',
			'run_report',
			array(
				'metrics'    => $metrics,
				'dimensions' => $dimensions,
				'date_range' => $date_range,
				'options'    => $options,
			)
		);

		$cached = $this->cache_manager->get( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$analytics = $this->init_analytics_client();

		if ( null === $analytics ) {
			throw new \Exception( 'Failed to initialize GA4 client' );
		}

		try {
			// Build date range
			$date_ranges = array(
				new \Google\Service\AnalyticsData\DateRange(
					array(
						'startDate' => $this->parse_date_range( $date_range, 'start' ),
						'endDate'   => $this->parse_date_range( $date_range, 'end' ),
					)
				),
			);

			// Build metrics
			$metric_objects = array();
			foreach ( $metrics as $metric ) {
				$metric_objects[] = new \Google\Service\AnalyticsData\Metric( array( 'name' => $metric ) );
			}

			// Build dimensions
			$dimension_objects = array();
			foreach ( $dimensions as $dimension ) {
				$dimension_objects[] = new \Google\Service\AnalyticsData\Dimension( array( 'name' => $dimension ) );
			}

			// Build request
			$request = new \Google\Service\AnalyticsData\RunReportRequest(
				array(
					'dateRanges' => $date_ranges,
					'metrics'    => $metric_objects,
					'dimensions' => $dimension_objects,
				)
			);

			// Add optional parameters
			if ( isset( $options['limit'] ) ) {
				$request->setLimit( absint( $options['limit'] ) );
			}

			if ( isset( $options['offset'] ) ) {
				$request->setOffset( absint( $options['offset'] ) );
			}

			// Run report
			$response = $analytics->properties->runReport( 'properties/' . $this->property_id, $request );

			// Parse response
			$data = $this->parse_report_response( $response );

			// Cache for 30 minutes
			$this->cache_manager->set( $cache_key, $data, $this->cache_manager->get_default_ttl( 'ga4' ) );

			return $data;
		} catch ( \Exception $e ) {
			throw new \Exception( 'GA4 API error: ' . $e->getMessage() );
		}
	}

	/**
	 * Get real-time data
	 *
	 * @param array $metrics Array of metric names (optional).
	 * @return array|null Real-time data or null on failure.
	 */
	public function get_realtime_data( $metrics = array() ) {
		if ( empty( $this->property_id ) ) {
			throw new \Exception( 'GA4 property ID not configured' );
		}

		if ( empty( $metrics ) ) {
			$metrics = array( 'activeUsers' );
		}

		$analytics = $this->init_analytics_client();

		if ( null === $analytics ) {
			throw new \Exception( 'Failed to initialize GA4 client' );
		}

		try {
			// Build metrics
			$metric_objects = array();
			foreach ( $metrics as $metric ) {
				$metric_objects[] = new \Google\Service\AnalyticsData\Metric( array( 'name' => $metric ) );
			}

			// Build request
			$request = new \Google\Service\AnalyticsData\RunRealtimeReportRequest(
				array(
					'metrics' => $metric_objects,
				)
			);

			// Run realtime report
			$response = $analytics->properties->runRealtimeReport( 'properties/' . $this->property_id, $request );

			return $this->parse_report_response( $response );
		} catch ( \Exception $e ) {
			throw new \Exception( 'GA4 realtime API error: ' . $e->getMessage() );
		}
	}

	/**
	 * Get event data
	 *
	 * @param string $event_name Event name to filter (optional).
	 * @param string $date_range Date range string.
	 * @param int    $limit Result limit.
	 * @return array|null Event data or null on failure.
	 */
	public function get_event_data( $event_name = null, $date_range = '7daysAgo', $limit = 100 ) {
		$dimensions = array( 'eventName' );
		$metrics    = array( 'eventCount' );

		$options = array( 'limit' => $limit );

		// TODO: Add dimension filter for specific event name if provided

		return $this->run_report( $metrics, $dimensions, $date_range, $options );
	}

	/**
	 * Get traffic sources
	 *
	 * @param string $date_range Date range string.
	 * @param int    $limit Result limit.
	 * @return array|null Traffic source data or null on failure.
	 */
	public function get_traffic_sources( $date_range = '7daysAgo', $limit = 100 ) {
		$dimensions = array( 'sessionSource', 'sessionMedium', 'sessionCampaign' );
		$metrics    = array( 'sessions', 'activeUsers' );

		$options = array( 'limit' => $limit );

		return $this->run_report( $metrics, $dimensions, $date_range, $options );
	}

	/**
	 * List available GA4 properties for the connected account
	 *
	 * @return array|null Array of properties or null on failure.
	 */
	public function list_properties() {
		$access_token = $this->oauth_handler->get_access_token( 'ga4' );

		if ( empty( $access_token ) ) {
			return null;
		}

		try {
			$client = new \Google\Client();
			$client->setAccessToken( $access_token );

			$analytics_admin = new \Google\Service\GoogleAnalyticsAdmin( $client );

			// Get account summaries
			$account_summaries = $analytics_admin->accountSummaries->listAccountSummaries();

			$properties = array();

			foreach ( $account_summaries->getAccountSummaries() as $account_summary ) {
				$property_summaries = $account_summary->getPropertySummaries();

				if ( ! empty( $property_summaries ) ) {
					foreach ( $property_summaries as $property_summary ) {
						$properties[] = array(
							'property_id'  => str_replace( 'properties/', '', $property_summary->getProperty() ),
							'display_name' => $property_summary->getDisplayName(),
							'account_name' => $account_summary->getDisplayName(),
						);
					}
				}
			}

			return $properties;
		} catch ( \Exception $e ) {
			$error_message = $e->getMessage();
			Logger::error( 'Failed to list GA4 properties: ' . $error_message );

			// Check if the error is about the Admin API not being enabled
			if ( strpos( $error_message, 'analyticsadmin.googleapis.com' ) !== false ||
				 strpos( $error_message, 'Google Analytics Admin API' ) !== false ) {
				throw new \Exception( 'Google Analytics Admin API is not enabled. Please enable it in your Google Cloud Console: https://console.cloud.google.com/apis/library/analyticsadmin.googleapis.com' );
			}

			throw $e;
		}
	}

	/**
	 * Set GA4 property ID
	 *
	 * @param string $property_id Property ID.
	 * @return bool True on success, false on failure.
	 */
	public function set_property_id( $property_id ) {
		$this->property_id = $property_id;
		return update_option( 'marketing_analytics_mcp_ga4_property_id', $property_id, false );
	}

	/**
	 * Get configured property ID
	 *
	 * @return string|null Property ID or null if not set.
	 */
	public function get_property_id() {
		return $this->property_id;
	}

	/**
	 * Parse date range string
	 *
	 * @param string $date_range Date range string (e.g., '7daysAgo', 'yesterday').
	 * @param string $boundary 'start' or 'end'.
	 * @return string Formatted date string.
	 */
	private function parse_date_range( $date_range, $boundary = 'start' ) {
		// If it's already a date, return it
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_range ) ) {
			return $date_range;
		}

		// Handle relative dates
		if ( 'start' === $boundary ) {
			return $date_range;
		}

		// For end boundary, return 'today' if not specified
		return 'today';
	}

	/**
	 * Parse GA4 report response
	 *
	 * @param mixed $response API response object.
	 * @return array Parsed data.
	 */
	private function parse_report_response( $response ) {
		$data = array(
			'rows'   => array(),
			'totals' => array(),
		);

		// Parse dimension headers
		$dimension_headers = array();
		if ( $response->getDimensionHeaders() ) {
			foreach ( $response->getDimensionHeaders() as $header ) {
				$dimension_headers[] = $header->getName();
			}
		}

		// Parse metric headers
		$metric_headers = array();
		if ( $response->getMetricHeaders() ) {
			foreach ( $response->getMetricHeaders() as $header ) {
				$metric_headers[] = $header->getName();
			}
		}

		// Parse rows
		if ( $response->getRows() ) {
			foreach ( $response->getRows() as $row ) {
				$row_data = array();

				// Add dimensions
				$dimension_values = $row->getDimensionValues();
				foreach ( $dimension_values as $index => $value ) {
					$row_data[ $dimension_headers[ $index ] ] = $value->getValue();
				}

				// Add metrics
				$metric_values = $row->getMetricValues();
				foreach ( $metric_values as $index => $value ) {
					$row_data[ $metric_headers[ $index ] ] = $value->getValue();
				}

				$data['rows'][] = $row_data;
			}
		}

		// Parse totals
		if ( $response->getTotals() ) {
			foreach ( $response->getTotals() as $total ) {
				$total_values = $total->getMetricValues();
				foreach ( $total_values as $index => $value ) {
					$data['totals'][ $metric_headers[ $index ] ] = $value->getValue();
				}
			}
		}

		// Add row count
		$data['row_count'] = $response->getRowCount();

		return $data;
	}
}
