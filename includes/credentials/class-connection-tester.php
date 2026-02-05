<?php
/**
 * Connection Tester
 *
 * Tests API connections for all analytics platforms.
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\Credentials;

use Marketing_Analytics_MCP\API_Clients\Clarity_Client;

/**
 * Tests connections to analytics platforms
 */
class Connection_Tester {

	/**
	 * Credential Manager instance
	 *
	 * @var Credential_Manager
	 */
	private $credential_manager;

	/**
	 * OAuth Handler instance
	 *
	 * @var OAuth_Handler
	 */
	private $oauth_handler;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->credential_manager = new Credential_Manager();
		$this->oauth_handler      = new OAuth_Handler();
	}

	/**
	 * Test connection to Microsoft Clarity
	 *
	 * @return array Test result with success status and message.
	 */
	public function test_clarity_connection() {
		$credentials = $this->credential_manager->get_credentials( 'clarity' );

		if ( empty( $credentials ) ) {
			return array(
				'success' => false,
				'message' => __( 'No Clarity credentials found.', 'marketing-analytics-chat' ),
			);
		}

		if ( empty( $credentials['api_token'] ) || empty( $credentials['project_id'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Clarity API token or project ID is missing.', 'marketing-analytics-chat' ),
			);
		}

		try {
			$client = new Clarity_Client();

			// Try to fetch insights for 1 day (minimal request)
			$result = $client->get_dashboard_insights( 1 );

			if ( ! empty( $result ) ) {
				return array(
					'success' => true,
					'message' => __( 'Successfully connected to Microsoft Clarity.', 'marketing-analytics-chat' ),
					'data'    => array(
						'project_id' => $credentials['project_id'],
					),
				);
			}

			return array(
				'success' => false,
				'message' => __( 'Clarity API returned empty response.', 'marketing-analytics-chat' ),
			);
		} catch ( \Exception $e ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: error message */
					__( 'Clarity connection failed: %s', 'marketing-analytics-chat' ),
					$e->getMessage()
				),
			);
		}
	}

	/**
	 * Test connection to Google Analytics 4
	 *
	 * @return array Test result with success status and message.
	 */
	public function test_ga4_connection() {
		$access_token = $this->oauth_handler->get_access_token( 'ga4' );

		if ( empty( $access_token ) ) {
			return array(
				'success' => false,
				'message' => __( 'No GA4 access token found. Please authorize Google Analytics.', 'marketing-analytics-chat' ),
			);
		}

		try {
			$client = new \Google\Client();
			$client->setAccessToken( $access_token );

			// Initialize Analytics Admin API to list properties
			$analytics = new \Google\Service\GoogleAnalyticsAdmin( $client );

			// Try to list account summaries (lightweight test)
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- SDK property name.
			$account_summaries = $analytics->accountSummaries->listAccountSummaries();

			if ( ! empty( $account_summaries ) ) {
				$summary_list   = $account_summaries->getAccountSummaries();
				$property_count = 0;

				if ( ! empty( $summary_list ) ) {
					foreach ( $summary_list as $summary ) {
						$property_summaries = $summary->getPropertySummaries();
						if ( ! empty( $property_summaries ) ) {
							$property_count += count( $property_summaries );
						}
					}
				}

				return array(
					'success' => true,
					'message' => sprintf(
						/* translators: %d: number of properties */
						_n(
							'Successfully connected to Google Analytics. Found %d property.',
							'Successfully connected to Google Analytics. Found %d properties.',
							$property_count,
							'marketing-analytics-chat'
						),
						$property_count
					),
					'data'    => array(
						'property_count' => $property_count,
					),
				);
			}

			return array(
				'success' => false,
				'message' => __( 'GA4 API returned empty response.', 'marketing-analytics-chat' ),
			);
		} catch ( \Exception $e ) {
			// Check if token expired
			if ( strpos( $e->getMessage(), 'Invalid Credentials' ) !== false ) {
				// Try to refresh token
				if ( $this->oauth_handler->refresh_token( 'ga4' ) ) {
					return $this->test_ga4_connection(); // Retry once
				}
			}

			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: error message */
					__( 'GA4 connection failed: %s', 'marketing-analytics-chat' ),
					$e->getMessage()
				),
			);
		}
	}

	/**
	 * Test connection to Google Search Console
	 *
	 * @return array Test result with success status and message.
	 */
	public function test_gsc_connection() {
		$access_token = $this->oauth_handler->get_access_token( 'gsc' );

		if ( empty( $access_token ) ) {
			return array(
				'success' => false,
				'message' => __( 'No GSC access token found. Please authorize Google Search Console.', 'marketing-analytics-chat' ),
			);
		}

		try {
			$client = new \Google\Client();
			$client->setAccessToken( $access_token );

			// Initialize Search Console API
			$search_console = new \Google\Service\SearchConsole( $client );

			// Try to list sites (lightweight test)
			$sites_list = $search_console->sites->listSites();

			if ( ! empty( $sites_list ) ) {
				$sites      = $sites_list->getSiteEntry();
				$site_count = ! empty( $sites ) ? count( $sites ) : 0;

				return array(
					'success' => true,
					'message' => sprintf(
						/* translators: %d: number of sites */
						_n(
							'Successfully connected to Google Search Console. Found %d site.',
							'Successfully connected to Google Search Console. Found %d sites.',
							$site_count,
							'marketing-analytics-chat'
						),
						$site_count
					),
					'data'    => array(
						'site_count' => $site_count,
					),
				);
			}

			return array(
				'success' => false,
				'message' => __( 'GSC API returned empty response.', 'marketing-analytics-chat' ),
			);
		} catch ( \Exception $e ) {
			// Check if token expired
			if ( strpos( $e->getMessage(), 'Invalid Credentials' ) !== false ) {
				// Try to refresh token
				if ( $this->oauth_handler->refresh_token( 'gsc' ) ) {
					return $this->test_gsc_connection(); // Retry once
				}
			}

			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: error message */
					__( 'GSC connection failed: %s', 'marketing-analytics-chat' ),
					$e->getMessage()
				),
			);
		}
	}

	/**
	 * Test all platform connections
	 *
	 * @return array Array of platform => test_result.
	 */
	public function test_all_connections() {
		return array(
			'clarity' => $this->test_clarity_connection(),
			'ga4'     => $this->test_ga4_connection(),
			'gsc'     => $this->test_gsc_connection(),
		);
	}

	/**
	 * Get connection status for a platform
	 *
	 * @param string $platform Platform identifier.
	 * @return string Status: 'connected', 'not_configured', 'error'.
	 */
	public function get_connection_status( $platform ) {
		switch ( $platform ) {
			case 'clarity':
				if ( ! $this->credential_manager->has_credentials( 'clarity' ) ) {
					return 'not_configured';
				}
				$result = $this->test_clarity_connection();
				return $result['success'] ? 'connected' : 'error';

			case 'ga4':
				if ( ! $this->oauth_handler->get_access_token( 'ga4' ) ) {
					return 'not_configured';
				}
				$result = $this->test_ga4_connection();
				return $result['success'] ? 'connected' : 'error';

			case 'gsc':
				if ( ! $this->oauth_handler->get_access_token( 'gsc' ) ) {
					return 'not_configured';
				}
				$result = $this->test_gsc_connection();
				return $result['success'] ? 'connected' : 'error';

			default:
				return 'error';
		}
	}

	/**
	 * Get connection statuses for all platforms
	 *
	 * @return array Array of platform => status.
	 */
	public function get_all_statuses() {
		return array(
			'clarity' => $this->get_connection_status( 'clarity' ),
			'ga4'     => $this->get_connection_status( 'ga4' ),
			'gsc'     => $this->get_connection_status( 'gsc' ),
		);
	}
}
