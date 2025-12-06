<?php
/**
 * Multi-Site Network Manager
 *
 * @package Marketing_Analytics_MCP
 * @subpackage Multisite
 */

namespace Marketing_Analytics_MCP\Multisite;

/**
 * Class for managing multiple WordPress sites in a network
 */
class Network_Manager {

	/**
	 * Database table name for network sites
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'marketing_analytics_network_sites';
	}

	/**
	 * Initialize network management
	 */
	public function init() {
		// Register REST API endpoints for remote sites
		add_action( 'rest_api_init', array( $this, 'register_rest_endpoints' ) );

		// Register cron job for syncing data
		if ( ! wp_next_scheduled( 'marketing_analytics_sync_network_data' ) ) {
			wp_schedule_event( time(), 'hourly', 'marketing_analytics_sync_network_data' );
		}

		add_action( 'marketing_analytics_sync_network_data', array( $this, 'sync_network_data' ) );
	}

	/**
	 * Register REST API endpoints
	 */
	public function register_rest_endpoints() {
		// Endpoint for receiving data from network sites
		register_rest_route(
			'marketing-analytics/v1',
			'/network/report',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_network_report' ),
				'permission_callback' => array( $this, 'verify_network_site' ),
			)
		);

		// Endpoint for site registration
		register_rest_route(
			'marketing-analytics/v1',
			'/network/register',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_site_registration' ),
				'permission_callback' => array( $this, 'verify_admin_request' ),
			)
		);
	}

	/**
	 * Add a site to the network
	 *
	 * @param array $site_data Site data.
	 * @return int|WP_Error Site ID or error
	 */
	public function add_site( $site_data ) {
		global $wpdb;

		$defaults = array(
			'site_url'         => '',
			'site_name'        => '',
			'auth_method'      => 'api_key',
			'auth_credentials' => '',
			'is_active'        => 1,
			'capabilities'     => array( 'read', 'report' ),
		);

		$site_data = wp_parse_args( $site_data, $defaults );

		// Validate URL
		if ( ! filter_var( $site_data['site_url'], FILTER_VALIDATE_URL ) ) {
			return new \WP_Error(
				'invalid_url',
				__( 'Invalid site URL', 'marketing-analytics-chat' )
			);
		}

		// Check if site already exists
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, defined by plugin.
				"SELECT id FROM {$this->table_name} WHERE site_url = %s",
				$site_data['site_url']
			)
		);

		if ( $existing ) {
			return new \WP_Error(
				'site_exists',
				__( 'Site already exists in network', 'marketing-analytics-chat' )
			);
		}

		// Encrypt credentials
		if ( class_exists( 'Marketing_Analytics_MCP\\Credentials\\Encryptor' ) ) {
			$encryptor                     = new \Marketing_Analytics_MCP\Credentials\Encryptor();
			$site_data['auth_credentials'] = $encryptor->encrypt( $site_data['auth_credentials'] );
		}

		// Generate API key for the site
		$api_key = $this->generate_api_key();

		// Insert site
		$result = $wpdb->insert(
			$this->table_name,
			array(
				'site_url'         => $site_data['site_url'],
				'site_name'        => $site_data['site_name'],
				'auth_method'      => $site_data['auth_method'],
				'auth_credentials' => $site_data['auth_credentials'],
				'api_key'          => $api_key,
				'capabilities'     => wp_json_encode( $site_data['capabilities'] ),
				'is_active'        => $site_data['is_active'],
				'created_at'       => current_time( 'mysql' ),
				'last_sync'        => null,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( ! $result ) {
			return new \WP_Error(
				'insert_failed',
				__( 'Failed to add site to network', 'marketing-analytics-chat' )
			);
		}

		$site_id = $wpdb->insert_id;

		// Test connection to the site
		$connection_test = $this->test_site_connection( $site_id );

		if ( is_wp_error( $connection_test ) ) {
			// Mark site as inactive if connection fails
			$this->update_site( $site_id, array( 'is_active' => 0 ) );
		}

		// Log the addition
		do_action( 'marketing_analytics_network_site_added', $site_id, $site_data );

		return $site_id;
	}

	/**
	 * Remove a site from the network
	 *
	 * @param int $site_id Site ID.
	 * @return bool|WP_Error Success or error
	 */
	public function remove_site( $site_id ) {
		global $wpdb;

		// Get site data before deletion
		$site = $this->get_site( $site_id );

		if ( ! $site ) {
			return new \WP_Error(
				'site_not_found',
				__( 'Site not found', 'marketing-analytics-chat' )
			);
		}

		// Delete site data
		$result = $wpdb->delete(
			$this->table_name,
			array( 'id' => $site_id ),
			array( '%d' )
		);

		if ( ! $result ) {
			return new \WP_Error(
				'delete_failed',
				__( 'Failed to remove site from network', 'marketing-analytics-chat' )
			);
		}

		// Delete cached data for this site
		delete_transient( 'marketing_analytics_site_data_' . $site_id );

		// Log the removal
		do_action( 'marketing_analytics_network_site_removed', $site_id, $site );

		return true;
	}

	/**
	 * Update site information
	 *
	 * @param int   $site_id   Site ID.
	 * @param array $site_data Updated data.
	 * @return bool|WP_Error Success or error
	 */
	public function update_site( $site_id, $site_data ) {
		global $wpdb;

		// Encrypt credentials if being updated
		if ( isset( $site_data['auth_credentials'] ) ) {
			if ( class_exists( 'Marketing_Analytics_MCP\\Credentials\\Encryptor' ) ) {
				$encryptor                     = new \Marketing_Analytics_MCP\Credentials\Encryptor();
				$site_data['auth_credentials'] = $encryptor->encrypt( $site_data['auth_credentials'] );
			}
		}

		// Update site
		$result = $wpdb->update(
			$this->table_name,
			$site_data,
			array( 'id' => $site_id )
		);

		if ( false === $result ) {
			return new \WP_Error(
				'update_failed',
				__( 'Failed to update site', 'marketing-analytics-chat' )
			);
		}

		// Clear cached data
		delete_transient( 'marketing_analytics_site_data_' . $site_id );

		return true;
	}

	/**
	 * Get site information
	 *
	 * @param int $site_id Site ID.
	 * @return object|null Site data or null
	 */
	public function get_site( $site_id ) {
		global $wpdb;

		$site = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, defined by plugin.
				"SELECT * FROM {$this->table_name} WHERE id = %d",
				$site_id
			)
		);

		if ( $site && isset( $site->capabilities ) ) {
			$site->capabilities = json_decode( $site->capabilities, true );
		}

		return $site;
	}

	/**
	 * Get all network sites
	 *
	 * @param array $args Query arguments.
	 * @return array Sites
	 */
	public function get_sites( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'is_active' => null,
			'orderby'   => 'site_name',
			'order'     => 'ASC',
			'limit'     => 100,
			'offset'    => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, defined by plugin.
		$query  = "SELECT * FROM {$this->table_name} WHERE 1=1";
		$params = array();

		if ( $args['is_active'] !== null ) {
			$query   .= ' AND is_active = %d';
			$params[] = $args['is_active'];
		}

		$query .= sprintf(
			' ORDER BY %s %s LIMIT %d OFFSET %d',
			esc_sql( $args['orderby'] ),
			esc_sql( $args['order'] ),
			intval( $args['limit'] ),
			intval( $args['offset'] )
		);

		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query contains table name which cannot be parameterized.
			$sites = $wpdb->get_results( $wpdb->prepare( $query, $params ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No user input, query is safe.
			$sites = $wpdb->get_results( $query );
		}

		// Decode capabilities
		foreach ( $sites as &$site ) {
			if ( isset( $site->capabilities ) ) {
				$site->capabilities = json_decode( $site->capabilities, true );
			}
		}

		return $sites;
	}

	/**
	 * Test connection to a network site
	 *
	 * @param int $site_id Site ID.
	 * @return bool|WP_Error Success or error
	 */
	public function test_site_connection( $site_id ) {
		$site = $this->get_site( $site_id );

		if ( ! $site ) {
			return new \WP_Error(
				'site_not_found',
				__( 'Site not found', 'marketing-analytics-chat' )
			);
		}

		// Test based on auth method
		switch ( $site->auth_method ) {
			case 'api_key':
				return $this->test_api_key_connection( $site );

			case 'oauth':
				return $this->test_oauth_connection( $site );

			case 'basic_auth':
				return $this->test_basic_auth_connection( $site );

			default:
				return new \WP_Error(
					'unknown_auth_method',
					__( 'Unknown authentication method', 'marketing-analytics-chat' )
				);
		}
	}

	/**
	 * Test API key connection
	 *
	 * @param object $site Site object.
	 * @return bool|WP_Error Success or error
	 */
	private function test_api_key_connection( $site ) {
		// Decrypt credentials
		$api_key = $site->auth_credentials;
		if ( class_exists( 'Marketing_Analytics_MCP\\Credentials\\Encryptor' ) ) {
			$encryptor = new \Marketing_Analytics_MCP\Credentials\Encryptor();
			$api_key   = $encryptor->decrypt( $api_key );
		}

		$response = wp_remote_get(
			trailingslashit( $site->site_url ) . 'wp-json/marketing-analytics/v1/test',
			array(
				'headers' => array(
					'X-API-Key' => $api_key,
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code !== 200 ) {
			return new \WP_Error(
				'connection_failed',
				sprintf(
					__( 'Connection failed with status %d', 'marketing-analytics-chat' ),
					$status_code
				)
			);
		}

		return true;
	}

	/**
	 * Test OAuth connection
	 *
	 * @param object $site Site object.
	 * @return bool|WP_Error Success or error
	 */
	private function test_oauth_connection( $site ) {
		// OAuth implementation would go here
		return new \WP_Error(
			'not_implemented',
			__( 'OAuth authentication not yet implemented', 'marketing-analytics-chat' )
		);
	}

	/**
	 * Test basic auth connection
	 *
	 * @param object $site Site object.
	 * @return bool|WP_Error Success or error
	 */
	private function test_basic_auth_connection( $site ) {
		// Decrypt credentials
		$credentials = $site->auth_credentials;
		if ( class_exists( 'Marketing_Analytics_MCP\\Credentials\\Encryptor' ) ) {
			$encryptor   = new \Marketing_Analytics_MCP\Credentials\Encryptor();
			$credentials = $encryptor->decrypt( $credentials );
		}

		$creds = json_decode( $credentials, true );

		if ( ! isset( $creds['username'] ) || ! isset( $creds['password'] ) ) {
			return new \WP_Error(
				'invalid_credentials',
				__( 'Invalid basic auth credentials', 'marketing-analytics-chat' )
			);
		}

		$response = wp_remote_get(
			trailingslashit( $site->site_url ) . 'wp-json/marketing-analytics/v1/test',
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $creds['username'] . ':' . $creds['password'] ),
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code !== 200 ) {
			return new \WP_Error(
				'connection_failed',
				sprintf(
					__( 'Connection failed with status %d', 'marketing-analytics-chat' ),
					$status_code
				)
			);
		}

		return true;
	}

	/**
	 * Sync data from all network sites
	 */
	public function sync_network_data() {
		$sites = $this->get_sites( array( 'is_active' => 1 ) );

		foreach ( $sites as $site ) {
			$this->sync_site_data( $site->id );
		}
	}

	/**
	 * Sync data from a specific site
	 *
	 * @param int $site_id Site ID.
	 * @return bool|WP_Error Success or error
	 */
	public function sync_site_data( $site_id ) {
		$site = $this->get_site( $site_id );

		if ( ! $site || ! $site->is_active ) {
			return new \WP_Error(
				'site_not_active',
				__( 'Site not found or not active', 'marketing-analytics-chat' )
			);
		}

		// Request data from the site
		$data = $this->request_site_data( $site );

		if ( is_wp_error( $data ) ) {
			// Log sync error
			$this->log_sync_error( $site_id, $data );
			return $data;
		}

		// Cache the data
		set_transient(
			'marketing_analytics_site_data_' . $site_id,
			$data,
			HOUR_IN_SECONDS
		);

		// Update last sync time
		$this->update_site( $site_id, array( 'last_sync' => current_time( 'mysql' ) ) );

		// Process data for anomalies if enabled
		if ( get_option( 'marketing_analytics_network_anomaly_detection', false ) ) {
			$this->check_site_anomalies( $site_id, $data );
		}

		return true;
	}

	/**
	 * Request data from a network site
	 *
	 * @param object $site Site object.
	 * @return array|WP_Error Data or error
	 */
	private function request_site_data( $site ) {
		// Build request headers based on auth method
		$headers = $this->build_auth_headers( $site );

		if ( is_wp_error( $headers ) ) {
			return $headers;
		}

		$response = wp_remote_get(
			trailingslashit( $site->site_url ) . 'wp-json/marketing-analytics/v1/data',
			array(
				'headers' => $headers,
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code !== 200 ) {
			return new \WP_Error(
				'data_request_failed',
				sprintf(
					__( 'Data request failed with status %d', 'marketing-analytics-chat' ),
					$status_code
				)
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! $data ) {
			return new \WP_Error(
				'invalid_data',
				__( 'Invalid data received from site', 'marketing-analytics-chat' )
			);
		}

		return $data;
	}

	/**
	 * Build authentication headers
	 *
	 * @param object $site Site object.
	 * @return array|WP_Error Headers or error
	 */
	private function build_auth_headers( $site ) {
		// Decrypt credentials
		$credentials = $site->auth_credentials;
		if ( class_exists( 'Marketing_Analytics_MCP\\Credentials\\Encryptor' ) ) {
			$encryptor   = new \Marketing_Analytics_MCP\Credentials\Encryptor();
			$credentials = $encryptor->decrypt( $credentials );
		}

		switch ( $site->auth_method ) {
			case 'api_key':
				return array( 'X-API-Key' => $credentials );

			case 'basic_auth':
				$creds = json_decode( $credentials, true );
				return array(
					'Authorization' => 'Basic ' . base64_encode( $creds['username'] . ':' . $creds['password'] ),
				);

			case 'oauth':
				// OAuth headers would go here
				return new \WP_Error(
					'oauth_not_implemented',
					__( 'OAuth not yet implemented', 'marketing-analytics-chat' )
				);

			default:
				return new \WP_Error(
					'unknown_auth_method',
					__( 'Unknown authentication method', 'marketing-analytics-chat' )
				);
		}
	}

	/**
	 * Check for anomalies in site data
	 *
	 * @param int   $site_id Site ID.
	 * @param array $data    Site data.
	 */
	private function check_site_anomalies( $site_id, $data ) {
		if ( class_exists( 'Marketing_Analytics_MCP\\Analytics\\Anomaly_Detector' ) ) {
			$detector = new \Marketing_Analytics_MCP\Analytics\Anomaly_Detector();

			// Check each platform's data
			foreach ( $data as $platform => $platform_data ) {
				// Detector will handle the anomaly detection
				// This is a simplified call
			}
		}
	}

	/**
	 * Get aggregated data from all sites
	 *
	 * @param string $metric Metric to aggregate.
	 * @param array  $args   Query arguments.
	 * @return array Aggregated data
	 */
	public function get_network_summary( $metric = 'all', $args = array() ) {
		$sites   = $this->get_sites( array( 'is_active' => 1 ) );
		$summary = array();

		foreach ( $sites as $site ) {
			$site_data = get_transient( 'marketing_analytics_site_data_' . $site->id );

			if ( ! $site_data ) {
				// Try to sync if no cached data
				$this->sync_site_data( $site->id );
				$site_data = get_transient( 'marketing_analytics_site_data_' . $site->id );
			}

			if ( $site_data ) {
				$summary[ $site->site_name ] = $this->extract_metrics( $site_data, $metric );
			}
		}

		return $summary;
	}

	/**
	 * Extract specific metrics from data
	 *
	 * @param array  $data   The data.
	 * @param string $metric Metric to extract.
	 * @return mixed Extracted metrics
	 */
	private function extract_metrics( $data, $metric ) {
		if ( $metric === 'all' ) {
			return $data;
		}

		// Extract specific metric from data structure
		$extracted = array();

		foreach ( $data as $platform => $platform_data ) {
			if ( isset( $platform_data[ $metric ] ) ) {
				$extracted[ $platform ] = $platform_data[ $metric ];
			}
		}

		return $extracted;
	}

	/**
	 * Compare metrics between sites
	 *
	 * @param array  $site_ids Sites to compare.
	 * @param string $metric   Metric to compare.
	 * @param array  $args     Additional arguments.
	 * @return array Comparison data
	 */
	public function compare_sites( $site_ids, $metric, $args = array() ) {
		$comparison = array();

		foreach ( $site_ids as $site_id ) {
			$site = $this->get_site( $site_id );

			if ( ! $site ) {
				continue;
			}

			$site_data = get_transient( 'marketing_analytics_site_data_' . $site_id );

			if ( $site_data ) {
				$comparison[ $site->site_name ] = array(
					'site_id'      => $site_id,
					'site_url'     => $site->site_url,
					'metric_value' => $this->extract_metrics( $site_data, $metric ),
					'last_sync'    => $site->last_sync,
				);
			}
		}

		// Calculate rankings
		if ( ! empty( $comparison ) ) {
			$comparison = $this->calculate_rankings( $comparison, $metric );
		}

		return $comparison;
	}

	/**
	 * Calculate rankings for comparison
	 *
	 * @param array  $comparison Comparison data.
	 * @param string $metric     Metric being compared.
	 * @return array Comparison with rankings
	 */
	private function calculate_rankings( $comparison, $metric ) {
		// This would calculate rankings based on metric values
		// Implementation depends on metric type
		return $comparison;
	}

	/**
	 * Generate API key
	 *
	 * @return string API key
	 */
	private function generate_api_key() {
		return wp_generate_password( 32, false );
	}

	/**
	 * Log sync error
	 *
	 * @param int      $site_id Site ID.
	 * @param WP_Error $error   The error.
	 */
	private function log_sync_error( $site_id, $error ) {
		$log = get_option( 'marketing_analytics_network_sync_errors', array() );

		array_unshift(
			$log,
			array(
				'site_id'       => $site_id,
				'error_code'    => $error->get_error_code(),
				'error_message' => $error->get_error_message(),
				'occurred_at'   => current_time( 'mysql' ),
			)
		);

		// Keep only last 50 errors
		$log = array_slice( $log, 0, 50 );

		update_option( 'marketing_analytics_network_sync_errors', $log );
	}

	/**
	 * Handle network report from remote site
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response Response
	 */
	public function handle_network_report( $request ) {
		$site_id = $request->get_param( 'site_id' );
		$data    = $request->get_json_params();

		// Validate and process the report
		// This would be called by remote sites to push data

		return new \WP_REST_Response(
			array( 'success' => true ),
			200
		);
	}

	/**
	 * Handle site registration request
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response Response
	 */
	public function handle_site_registration( $request ) {
		$site_data = $request->get_json_params();

		$site_id = $this->add_site( $site_data );

		if ( is_wp_error( $site_id ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'error'   => $site_id->get_error_message(),
				),
				400
			);
		}

		$site = $this->get_site( $site_id );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'site_id' => $site_id,
				'api_key' => $site->api_key,
			),
			201
		);
	}

	/**
	 * Verify network site request
	 *
	 * @param WP_REST_Request $request The request.
	 * @return bool|WP_Error Permission result
	 */
	public function verify_network_site( $request ) {
		$api_key = $request->get_header( 'X-API-Key' );

		if ( empty( $api_key ) ) {
			return new \WP_Error(
				'missing_api_key',
				__( 'API key required', 'marketing-analytics-chat' ),
				array( 'status' => 401 )
			);
		}

		global $wpdb;

		$site = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, defined by plugin.
				"SELECT * FROM {$this->table_name} WHERE api_key = %s AND is_active = 1",
				$api_key
			)
		);

		if ( ! $site ) {
			return new \WP_Error(
				'invalid_api_key',
				__( 'Invalid API key', 'marketing-analytics-chat' ),
				array( 'status' => 401 )
			);
		}

		// Store site ID for use in callback
		$request->set_param( 'verified_site_id', $site->id );

		return true;
	}

	/**
	 * Verify admin request
	 *
	 * @return bool Permission result
	 */
	public function verify_admin_request() {
		return current_user_can( 'manage_options' );
	}
}
