<?php
/**
 * Cross-Platform Abilities
 *
 * Registers MCP abilities that combine data from multiple analytics platforms.
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\Abilities;

use Marketing_Analytics_MCP\API_Clients\Clarity_Client;
use Marketing_Analytics_MCP\API_Clients\GA4_Client;
use Marketing_Analytics_MCP\API_Clients\GSC_Client;
use Marketing_Analytics_MCP\Cache\Cache_Manager;
use Marketing_Analytics_MCP\Credentials\Credential_Manager;
use Marketing_Analytics_MCP\Utils\Logger;
use Marketing_Analytics_MCP\Utils\Permission_Manager;

/**
 * Registers cross-platform MCP abilities
 */
class Cross_Platform_Abilities {

	/**
	 * Cache Manager instance
	 *
	 * @var Cache_Manager
	 */
	private $cache_manager;

	/**
	 * Credential Manager instance
	 *
	 * @var Credential_Manager
	 */
	private $credential_manager;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->cache_manager      = new Cache_Manager();
		$this->credential_manager = new Credential_Manager();
	}

	/**
	 * Register cross-platform abilities
	 */
	public function register() {
		$platforms = $this->get_available_platforms();

		if ( empty( $platforms ) ) {
			return;
		}

		if ( function_exists( 'wp_register_ability' ) ) {
			$this->register_compare_periods();
			$this->register_get_top_content();
			$this->register_generate_summary_report();
		}
	}

	/**
	 * Register compare-periods tool
	 */
	private function register_compare_periods() {
		wp_register_ability(
			'marketing-analytics/compare-periods',
			array(
				'type'        => 'tool',
				'label'       => __( 'Compare Periods', 'marketing-analytics-chat' ),
				'description' => __( 'Compare analytics metrics between two time periods across connected platforms.', 'marketing-analytics-chat' ),
				'category'    => 'marketing-analytics',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'period_a'  => array(
							'type'        => 'string',
							'description' => 'First period start date (e.g., "14daysAgo", "2024-01-01")',
						),
						'period_b'  => array(
							'type'        => 'string',
							'description' => 'Second period start date (e.g., "7daysAgo", "2024-01-08")',
						),
						'platforms' => array(
							'type'        => 'array',
							'description' => 'Platforms to compare (defaults to all connected)',
							'items'       => array( 'type' => 'string' ),
						),
						'metrics'   => array(
							'type'        => 'array',
							'description' => 'Specific metrics to compare (optional)',
							'items'       => array( 'type' => 'string' ),
						),
					),
					'required'   => array( 'period_a', 'period_b' ),
				),
				'callback'    => array( $this, 'handle_compare_periods' ),
			)
		);
	}

	/**
	 * Register get-top-content tool
	 */
	private function register_get_top_content() {
		wp_register_ability(
			'marketing-analytics/get-top-content',
			array(
				'type'        => 'tool',
				'label'       => __( 'Get Top Content', 'marketing-analytics-chat' ),
				'description' => __( 'Get top performing content by combining GA4 engagement and GSC search data.', 'marketing-analytics-chat' ),
				'category'    => 'marketing-analytics',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'date_range' => array(
							'type'        => 'string',
							'description' => 'Date range (default: "7daysAgo")',
						),
						'limit'      => array(
							'type'        => 'integer',
							'description' => 'Maximum results to return (default: 20)',
						),
					),
				),
				'callback'    => array( $this, 'handle_get_top_content' ),
			)
		);
	}

	/**
	 * Register generate-summary-report tool
	 */
	private function register_generate_summary_report() {
		wp_register_ability(
			'marketing-analytics/generate-summary-report',
			array(
				'type'        => 'tool',
				'label'       => __( 'Generate Summary Report', 'marketing-analytics-chat' ),
				'description' => __( 'Generate a comprehensive analytics summary report from all connected platforms.', 'marketing-analytics-chat' ),
				'category'    => 'marketing-analytics',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'date_range' => array(
							'type'        => 'string',
							'description' => 'Date range (default: "7daysAgo")',
						),
						'format'     => array(
							'type'        => 'string',
							'description' => 'Output format: "markdown" or "json" (default: "markdown")',
							'enum'        => array( 'markdown', 'json' ),
						),
					),
				),
				'callback'    => array( $this, 'handle_generate_summary_report' ),
			)
		);
	}

	/**
	 * Handle compare-periods tool call
	 *
	 * @param array $args Tool arguments.
	 * @return array Result data.
	 */
	public function handle_compare_periods( $args ) {
		if ( ! $this->check_permissions() ) {
			return array( 'error' => __( 'Insufficient permissions.', 'marketing-analytics-chat' ) );
		}

		$period_a  = $args['period_a'];
		$period_b  = $args['period_b'];
		$platforms = isset( $args['platforms'] ) ? $args['platforms'] : $this->get_available_platforms();

		$cache_key = $this->cache_manager->generate_key(
			'cross_platform',
			'compare_periods',
			array(
				'period_a'  => $period_a,
				'period_b'  => $period_b,
				'platforms' => $platforms,
			)
		);

		$cached = $this->cache_manager->get( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$results = array();

		foreach ( $platforms as $platform ) {
			try {
				$data_a = $this->get_platform_period_data( $platform, $period_a );
				$data_b = $this->get_platform_period_data( $platform, $period_b );

				if ( ! empty( $data_a ) && ! empty( $data_b ) ) {
					$results[ $platform ] = $this->calculate_period_comparison( $data_a, $data_b, $platform );
				}
			} catch ( \Exception $e ) {
				Logger::debug( 'Compare periods error for ' . $platform . ': ' . $e->getMessage() );
				$results[ $platform ] = array( 'error' => $e->getMessage() );
			}
		}

		$result = array(
			'period_a'    => $period_a,
			'period_b'    => $period_b,
			'platforms'   => $platforms,
			'comparisons' => $results,
		);

		$this->cache_manager->set( $cache_key, $result, 30 * MINUTE_IN_SECONDS );

		return $result;
	}

	/**
	 * Handle get-top-content tool call
	 *
	 * @param array $args Tool arguments.
	 * @return array Result data.
	 */
	public function handle_get_top_content( $args ) {
		if ( ! $this->check_permissions() ) {
			return array( 'error' => __( 'Insufficient permissions.', 'marketing-analytics-chat' ) );
		}

		$date_range = isset( $args['date_range'] ) ? $args['date_range'] : '7daysAgo';
		$limit      = isset( $args['limit'] ) ? absint( $args['limit'] ) : 20;

		$cache_key = $this->cache_manager->generate_key(
			'cross_platform',
			'top_content',
			array(
				'date_range' => $date_range,
				'limit'      => $limit,
			)
		);

		$cached = $this->cache_manager->get( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$ga4_data = array();
		$gsc_data = array();

		// Pull GA4 page data.
		$platforms = $this->get_available_platforms();
		if ( in_array( 'ga4', $platforms, true ) ) {
			try {
				$ga4_client = $this->get_ga4_client();
				$ga4_data   = $ga4_client->run_report(
					array( 'screenPageViews', 'bounceRate', 'averageSessionDuration' ),
					array( 'pagePath', 'pageTitle' ),
					$date_range,
					array( 'limit' => $limit * 2 )
				);
			} catch ( \Exception $e ) {
				Logger::debug( 'Top content GA4 error: ' . $e->getMessage() );
			}
		}

		// Pull GSC page data.
		if ( in_array( 'gsc', $platforms, true ) ) {
			try {
				$gsc_client = $this->get_gsc_client();
				$gsc_data   = $gsc_client->query_search_analytics(
					$date_range,
					array( 'page' ),
					array(),
					array( 'row_limit' => $limit * 2 )
				);
			} catch ( \Exception $e ) {
				Logger::debug( 'Top content GSC error: ' . $e->getMessage() );
			}
		}

		// Merge data by URL path.
		$merged = $this->merge_content_data( $ga4_data, $gsc_data );

		// Sort by pageviews descending.
		usort(
			$merged,
			function ( $item_a, $item_b ) {
				$views_a = isset( $item_a['screenPageViews'] ) ? (int) $item_a['screenPageViews'] : 0;
				$views_b = isset( $item_b['screenPageViews'] ) ? (int) $item_b['screenPageViews'] : 0;
				return $views_b - $views_a;
			}
		);

		$result = array(
			'date_range' => $date_range,
			'pages'      => array_slice( $merged, 0, $limit ),
			'total'      => count( $merged ),
		);

		$this->cache_manager->set( $cache_key, $result, 30 * MINUTE_IN_SECONDS );

		return $result;
	}

	/**
	 * Handle generate-summary-report tool call
	 *
	 * @param array $args Tool arguments.
	 * @return array|string Result data.
	 */
	public function handle_generate_summary_report( $args ) {
		if ( ! $this->check_permissions() ) {
			return array( 'error' => __( 'Insufficient permissions.', 'marketing-analytics-chat' ) );
		}

		$date_range = isset( $args['date_range'] ) ? $args['date_range'] : '7daysAgo';
		$format     = isset( $args['format'] ) ? $args['format'] : 'markdown';

		$cache_key = $this->cache_manager->generate_key(
			'cross_platform',
			'summary_report',
			array(
				'date_range' => $date_range,
				'format'     => $format,
			)
		);

		$cached = $this->cache_manager->get( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$platforms = $this->get_available_platforms();
		$report   = array(
			'date_range'      => $date_range,
			'generated_at'    => current_time( 'mysql' ),
			'platforms'       => $platforms,
			'platform_data'   => array(),
			'key_takeaways'   => array(),
		);

		// Collect data from each platform.
		foreach ( $platforms as $platform ) {
			try {
				$report['platform_data'][ $platform ] = $this->get_platform_summary( $platform, $date_range );
			} catch ( \Exception $e ) {
				Logger::debug( 'Summary report error for ' . $platform . ': ' . $e->getMessage() );
				$report['platform_data'][ $platform ] = array( 'error' => $e->getMessage() );
			}
		}

		// Generate key takeaways.
		$report['key_takeaways'] = $this->generate_takeaways( $report['platform_data'] );

		if ( 'markdown' === $format ) {
			$result = $this->format_report_markdown( $report );
		} else {
			$result = $report;
		}

		$this->cache_manager->set( $cache_key, $result, 30 * MINUTE_IN_SECONDS );

		return $result;
	}

	/**
	 * Get available connected platforms
	 *
	 * @return array List of connected platform identifiers.
	 */
	public function get_available_platforms() {
		$platforms = array();

		if ( $this->credential_manager->has_credentials( 'clarity' ) ) {
			$platforms[] = 'clarity';
		}

		if ( $this->credential_manager->has_credentials( 'ga4' ) ) {
			$platforms[] = 'ga4';
		}

		if ( $this->credential_manager->has_credentials( 'gsc' ) ) {
			$platforms[] = 'gsc';
		}

		return $platforms;
	}

	/**
	 * Check user permissions
	 *
	 * @return bool True if user has permission.
	 */
	private function check_permissions() {
		return Permission_Manager::can_access_plugin();
	}

	/**
	 * Get Clarity client instance
	 *
	 * @return Clarity_Client Client instance.
	 * @throws \Exception If credentials not found.
	 */
	private function get_clarity_client() {
		$credentials = $this->credential_manager->get_credentials( 'clarity' );

		if ( empty( $credentials['api_token'] ) || empty( $credentials['project_id'] ) ) {
			throw new \Exception( 'Clarity credentials not configured' );
		}

		return new Clarity_Client( $credentials['api_token'], $credentials['project_id'] );
	}

	/**
	 * Get GA4 client instance
	 *
	 * @return GA4_Client Client instance.
	 */
	private function get_ga4_client() {
		return new GA4_Client();
	}

	/**
	 * Get GSC client instance
	 *
	 * @return GSC_Client Client instance.
	 */
	private function get_gsc_client() {
		return new GSC_Client();
	}

	/**
	 * Get platform data for a specific period
	 *
	 * @param string $platform Platform identifier.
	 * @param string $period   Date range string.
	 * @return array Platform data.
	 */
	private function get_platform_period_data( $platform, $period ) {
		switch ( $platform ) {
			case 'ga4':
				$client = $this->get_ga4_client();
				return $client->run_report(
					array( 'sessions', 'activeUsers', 'screenPageViews', 'bounceRate', 'averageSessionDuration' ),
					array(),
					$period
				);

			case 'gsc':
				$client = $this->get_gsc_client();
				return $client->query_search_analytics( $period );

			case 'clarity':
				$client  = $this->get_clarity_client();
				$num_days = $this->parse_clarity_days( $period );
				return $client->get_insights( $num_days );

			default:
				return array();
		}
	}

	/**
	 * Calculate percentage change between two periods
	 *
	 * @param array  $data_a   Period A data.
	 * @param array  $data_b   Period B data.
	 * @param string $platform Platform identifier.
	 * @return array Comparison results.
	 */
	private function calculate_period_comparison( $data_a, $data_b, $platform ) {
		$comparison = array();

		switch ( $platform ) {
			case 'ga4':
				$metrics = array( 'sessions', 'activeUsers', 'screenPageViews', 'bounceRate', 'averageSessionDuration' );
				foreach ( $metrics as $metric ) {
					$val_a = isset( $data_a['totals'][ $metric ] ) ? (float) $data_a['totals'][ $metric ] : 0;
					$val_b = isset( $data_b['totals'][ $metric ] ) ? (float) $data_b['totals'][ $metric ] : 0;

					$comparison[ $metric ] = array(
						'period_a'        => $val_a,
						'period_b'        => $val_b,
						'change'          => $val_b - $val_a,
						'percentage_change' => $this->calculate_percentage_change( $val_a, $val_b ),
					);
				}
				break;

			case 'gsc':
				if ( ! empty( $data_a['rows'] ) && ! empty( $data_b['rows'] ) ) {
					$totals_a = $this->aggregate_gsc_totals( $data_a['rows'] );
					$totals_b = $this->aggregate_gsc_totals( $data_b['rows'] );

					foreach ( array( 'clicks', 'impressions', 'ctr', 'position' ) as $metric ) {
						$val_a = $totals_a[ $metric ] ?? 0;
						$val_b = $totals_b[ $metric ] ?? 0;

						$comparison[ $metric ] = array(
							'period_a'        => $val_a,
							'period_b'        => $val_b,
							'change'          => $val_b - $val_a,
							'percentage_change' => $this->calculate_percentage_change( $val_a, $val_b ),
						);
					}
				}
				break;

			case 'clarity':
				if ( is_array( $data_a ) && is_array( $data_b ) ) {
					foreach ( array( 'totalSessions', 'pagesPerSession', 'scrollDepth' ) as $metric ) {
						$val_a = isset( $data_a[ $metric ] ) ? (float) $data_a[ $metric ] : 0;
						$val_b = isset( $data_b[ $metric ] ) ? (float) $data_b[ $metric ] : 0;

						$comparison[ $metric ] = array(
							'period_a'        => $val_a,
							'period_b'        => $val_b,
							'change'          => $val_b - $val_a,
							'percentage_change' => $this->calculate_percentage_change( $val_a, $val_b ),
						);
					}
				}
				break;
		}

		return $comparison;
	}

	/**
	 * Calculate percentage change between two values
	 *
	 * @param float $old_value Period A value.
	 * @param float $new_value Period B value.
	 * @return float Percentage change.
	 */
	public function calculate_percentage_change( $old_value, $new_value ) {
		if ( 0 === $old_value ) {
			return 0 === $new_value ? 0 : 100;
		}

		return round( ( ( $new_value - $old_value ) / $old_value ) * 100, 2 );
	}

	/**
	 * Aggregate GSC row totals
	 *
	 * @param array $rows GSC data rows.
	 * @return array Aggregated totals.
	 */
	private function aggregate_gsc_totals( $rows ) {
		$totals = array(
			'clicks'      => 0,
			'impressions' => 0,
			'ctr'         => 0,
			'position'    => 0,
		);

		$count = count( $rows );
		if ( 0 === $count ) {
			return $totals;
		}

		foreach ( $rows as $row ) {
			$totals['clicks']      += isset( $row['clicks'] ) ? (float) $row['clicks'] : 0;
			$totals['impressions'] += isset( $row['impressions'] ) ? (float) $row['impressions'] : 0;
			$totals['ctr']         += isset( $row['ctr'] ) ? (float) $row['ctr'] : 0;
			$totals['position']    += isset( $row['position'] ) ? (float) $row['position'] : 0;
		}

		$totals['ctr']      = $totals['ctr'] / $count;
		$totals['position'] = $totals['position'] / $count;

		return $totals;
	}

	/**
	 * Merge GA4 and GSC content data by URL path
	 *
	 * @param array $ga4_data GA4 report data.
	 * @param array $gsc_data GSC report data.
	 * @return array Merged content data.
	 */
	public function merge_content_data( $ga4_data, $gsc_data ) {
		$merged = array();

		// Index GA4 data by pagePath.
		if ( ! empty( $ga4_data['rows'] ) ) {
			foreach ( $ga4_data['rows'] as $row ) {
				$path = isset( $row['pagePath'] ) ? $row['pagePath'] : '';
				if ( empty( $path ) ) {
					continue;
				}

				$merged[ $path ] = array(
					'path'                   => $path,
					'title'                  => isset( $row['pageTitle'] ) ? $row['pageTitle'] : '',
					'screenPageViews'        => isset( $row['screenPageViews'] ) ? $row['screenPageViews'] : 0,
					'bounceRate'             => isset( $row['bounceRate'] ) ? $row['bounceRate'] : 0,
					'averageSessionDuration' => isset( $row['averageSessionDuration'] ) ? $row['averageSessionDuration'] : 0,
				);
			}
		}

		// Merge GSC data.
		if ( ! empty( $gsc_data['rows'] ) ) {
			foreach ( $gsc_data['rows'] as $row ) {
				$page_url = isset( $row['key'] ) ? $row['key'] : ( isset( $row['keys'][0] ) ? $row['keys'][0] : '' );
				if ( empty( $page_url ) ) {
					continue;
				}

				// Extract path from full URL.
				$path = wp_parse_url( $page_url, PHP_URL_PATH );
				if ( empty( $path ) ) {
					$path = $page_url;
				}

				if ( isset( $merged[ $path ] ) ) {
					$merged[ $path ]['clicks']      = isset( $row['clicks'] ) ? $row['clicks'] : 0;
					$merged[ $path ]['impressions']  = isset( $row['impressions'] ) ? $row['impressions'] : 0;
					$merged[ $path ]['ctr']          = isset( $row['ctr'] ) ? $row['ctr'] : 0;
					$merged[ $path ]['position']     = isset( $row['position'] ) ? $row['position'] : 0;
				} else {
					$merged[ $path ] = array(
						'path'        => $path,
						'title'       => '',
						'clicks'      => isset( $row['clicks'] ) ? $row['clicks'] : 0,
						'impressions' => isset( $row['impressions'] ) ? $row['impressions'] : 0,
						'ctr'         => isset( $row['ctr'] ) ? $row['ctr'] : 0,
						'position'    => isset( $row['position'] ) ? $row['position'] : 0,
					);
				}
			}
		}

		return array_values( $merged );
	}

	/**
	 * Get platform summary data for the report
	 *
	 * @param string $platform   Platform identifier.
	 * @param string $date_range Date range.
	 * @return array Summary data.
	 */
	private function get_platform_summary( $platform, $date_range ) {
		switch ( $platform ) {
			case 'ga4':
				$client = $this->get_ga4_client();
				return $client->run_report(
					array( 'sessions', 'activeUsers', 'screenPageViews', 'bounceRate', 'averageSessionDuration' ),
					array(),
					$date_range
				);

			case 'gsc':
				$client = $this->get_gsc_client();
				return $client->query_search_analytics( $date_range );

			case 'clarity':
				$client  = $this->get_clarity_client();
				$num_days = $this->parse_clarity_days( $date_range );
				return $client->get_insights( $num_days );

			default:
				return array();
		}
	}

	/**
	 * Generate key takeaways from platform data
	 *
	 * @param array $platform_data Data from all platforms.
	 * @return array Key takeaways.
	 */
	private function generate_takeaways( $platform_data ) {
		$takeaways = array();

		if ( isset( $platform_data['ga4']['totals'] ) ) {
			$totals = $platform_data['ga4']['totals'];
			if ( isset( $totals['sessions'] ) ) {
				$takeaways[] = sprintf(
					/* translators: %s: number of sessions */
					__( 'Total sessions: %s', 'marketing-analytics-chat' ),
					number_format( (float) $totals['sessions'] )
				);
			}
			if ( isset( $totals['activeUsers'] ) ) {
				$takeaways[] = sprintf(
					/* translators: %s: number of active users */
					__( 'Active users: %s', 'marketing-analytics-chat' ),
					number_format( (float) $totals['activeUsers'] )
				);
			}
		}

		if ( isset( $platform_data['gsc']['rows'] ) ) {
			$gsc_totals = $this->aggregate_gsc_totals( $platform_data['gsc']['rows'] );
			$takeaways[] = sprintf(
				/* translators: %s: number of search clicks */
				__( 'Search clicks: %s', 'marketing-analytics-chat' ),
				number_format( $gsc_totals['clicks'] )
			);
			$takeaways[] = sprintf(
				/* translators: %s: number of search impressions */
				__( 'Search impressions: %s', 'marketing-analytics-chat' ),
				number_format( $gsc_totals['impressions'] )
			);
		}

		return $takeaways;
	}

	/**
	 * Format report as markdown
	 *
	 * @param array $report Report data.
	 * @return string Markdown formatted report.
	 */
	public function format_report_markdown( $report ) {
		$md = "# Marketing Analytics Summary Report\n\n";
		$md .= sprintf( "**Date Range:** %s\n", $report['date_range'] );
		$md .= sprintf( "**Generated:** %s\n\n", $report['generated_at'] );

		// GA4 section.
		if ( isset( $report['platform_data']['ga4'] ) && ! isset( $report['platform_data']['ga4']['error'] ) ) {
			$md .= "## Google Analytics 4\n\n";
			$ga4 = $report['platform_data']['ga4'];

			if ( isset( $ga4['totals'] ) ) {
				$md .= "| Metric | Value |\n|--------|-------|\n";

				foreach ( $ga4['totals'] as $metric => $value ) {
					$md .= sprintf( "| %s | %s |\n", $metric, number_format( (float) $value, 2 ) );
				}

				$md .= "\n";
			}
		}

		// GSC section.
		if ( isset( $report['platform_data']['gsc'] ) && ! isset( $report['platform_data']['gsc']['error'] ) ) {
			$md .= "## Google Search Console\n\n";
			$gsc = $report['platform_data']['gsc'];

			if ( ! empty( $gsc['rows'] ) ) {
				$totals = $this->aggregate_gsc_totals( $gsc['rows'] );
				$md    .= "| Metric | Value |\n|--------|-------|\n";
				$md    .= sprintf( "| Clicks | %s |\n", number_format( $totals['clicks'] ) );
				$md    .= sprintf( "| Impressions | %s |\n", number_format( $totals['impressions'] ) );
				$md    .= sprintf( "| CTR | %s%% |\n", number_format( $totals['ctr'] * 100, 2 ) );
				$md    .= sprintf( "| Avg Position | %s |\n", number_format( $totals['position'], 1 ) );
				$md    .= "\n";
			}
		}

		// Clarity section.
		if ( isset( $report['platform_data']['clarity'] ) && ! isset( $report['platform_data']['clarity']['error'] ) && is_array( $report['platform_data']['clarity'] ) ) {
			$md      .= "## Microsoft Clarity\n\n";
			$clarity  = $report['platform_data']['clarity'];
			$md      .= "| Metric | Value |\n|--------|-------|\n";

			if ( isset( $clarity['totalSessions'] ) ) {
				$md .= sprintf( "| Sessions | %s |\n", number_format( (float) $clarity['totalSessions'] ) );
			}
			if ( isset( $clarity['pagesPerSession'] ) ) {
				$md .= sprintf( "| Pages/Session | %s |\n", number_format( (float) $clarity['pagesPerSession'], 2 ) );
			}
			if ( isset( $clarity['scrollDepth'] ) ) {
				$md .= sprintf( "| Scroll Depth | %s%% |\n", number_format( (float) $clarity['scrollDepth'], 1 ) );
			}

			$md .= "\n";
		}

		// Key takeaways.
		if ( ! empty( $report['key_takeaways'] ) ) {
			$md .= "## Key Takeaways\n\n";
			foreach ( $report['key_takeaways'] as $takeaway ) {
				$md .= sprintf( "- %s\n", $takeaway );
			}
		}

		return $md;
	}

	/**
	 * Parse date range to Clarity-compatible number of days (1, 2, or 3)
	 *
	 * @param string $date_range Date range string.
	 * @return int Number of days (1, 2, or 3).
	 */
	private function parse_clarity_days( $date_range ) {
		if ( preg_match( '/^(\d+)daysAgo$/', $date_range, $matches ) ) {
			$days = absint( $matches[1] );
			if ( $days <= 1 ) {
				return 1;
			} elseif ( $days <= 2 ) {
				return 2;
			}
			return 3;
		}

		return 1;
	}
}
