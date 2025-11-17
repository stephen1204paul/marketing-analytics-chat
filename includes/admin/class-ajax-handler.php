<?php
/**
 * AJAX Handler for Admin Operations
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\Admin;

use Marketing_Analytics_MCP\API_Clients\Clarity_Client;
use Marketing_Analytics_MCP\API_Clients\GA4_Client;
use Marketing_Analytics_MCP\API_Clients\GSC_Client;
use Marketing_Analytics_MCP\Credentials\Encryption;
use Marketing_Analytics_MCP\Credentials\Connection_Tester;

/**
 * Handles AJAX requests from admin interface
 */
class Ajax_Handler {

	/**
	 * Register AJAX hooks
	 */
	public function register_hooks() {
		error_log( '[Marketing Analytics MCP] Registering AJAX hooks' );

		add_action( 'wp_ajax_marketing_analytics_mcp_test_connection', array( $this, 'test_connection' ) );
		add_action( 'wp_ajax_marketing_analytics_mcp_save_credentials', array( $this, 'save_credentials' ) );
		add_action( 'wp_ajax_marketing_analytics_mcp_clear_caches', array( $this, 'clear_caches' ) );
		add_action( 'wp_ajax_marketing_analytics_mcp_list_ga4_properties', array( $this, 'list_ga4_properties' ) );
		add_action( 'wp_ajax_marketing_analytics_mcp_save_ga4_property', array( $this, 'save_ga4_property' ) );
		add_action( 'wp_ajax_marketing_analytics_mcp_list_gsc_sites', array( $this, 'list_gsc_sites' ) );
		add_action( 'wp_ajax_marketing_analytics_mcp_save_gsc_site', array( $this, 'save_gsc_site' ) );
	}

	/**
	 * Test platform connection
	 */
	public function test_connection() {
		error_log( '[Marketing Analytics MCP] ===== AJAX TEST CONNECTION REQUEST =====' );
		error_log( sprintf( '[Marketing Analytics MCP] Request data: %s', wp_json_encode( $_POST ) ) );

		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'marketing-analytics-mcp-admin' ) ) {
			error_log( '[Marketing Analytics MCP] ERROR: Nonce verification failed' );
			wp_send_json_error(
				array(
					'message' => 'Security check failed. Please refresh the page and try again.',
				)
			);
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			error_log( '[Marketing Analytics MCP] ERROR: User lacks manage_options capability' );
			wp_send_json_error(
				array(
					'message' => 'You do not have permission to perform this action.',
				)
			);
		}

		$platform = isset( $_POST['platform'] ) ? sanitize_text_field( wp_unslash( $_POST['platform'] ) ) : '';
		error_log( sprintf( '[Marketing Analytics MCP] Testing connection for platform: %s', $platform ) );

		// Use Connection_Tester for OAuth-based platforms (GA4, GSC)
		if ( in_array( $platform, array( 'ga4', 'gsc' ), true ) ) {
			$this->test_oauth_platform_connection( $platform );
		} elseif ( $platform === 'clarity' ) {
			$this->test_clarity_connection();
		} else {
			error_log( sprintf( '[Marketing Analytics MCP] ERROR: Unsupported platform: %s', $platform ) );
			wp_send_json_error(
				array(
					'message' => 'Unsupported platform: ' . $platform,
				)
			);
		}
	}

	/**
	 * Test Clarity connection
	 */
	private function test_clarity_connection() {
		error_log( '[Marketing Analytics MCP] Testing Clarity connection' );

		// Get credentials from POST
		$api_token = isset( $_POST['api_token'] ) ? sanitize_text_field( wp_unslash( $_POST['api_token'] ) ) : '';
		$project_id = isset( $_POST['project_id'] ) ? sanitize_text_field( wp_unslash( $_POST['project_id'] ) ) : '';

		error_log( sprintf( '[Marketing Analytics MCP] API Token provided: %s', $api_token ? 'yes (length: ' . strlen( $api_token ) . ')' : 'NO' ) );
		error_log( sprintf( '[Marketing Analytics MCP] Project ID: %s', $project_id ? $project_id : 'EMPTY' ) );

		// Validate inputs
		if ( empty( $api_token ) ) {
			error_log( '[Marketing Analytics MCP] ERROR: API token is empty' );
			wp_send_json_error(
				array(
					'message' => 'API Token is required. Please enter your Clarity API token.',
				)
			);
		}

		if ( empty( $project_id ) ) {
			error_log( '[Marketing Analytics MCP] ERROR: Project ID is empty' );
			wp_send_json_error(
				array(
					'message' => 'Project ID is required. Please enter your Clarity project ID.',
				)
			);
		}

		// Validate token format (should be a non-empty string)
		if ( strlen( $api_token ) < 10 ) {
			error_log( sprintf( '[Marketing Analytics MCP] ERROR: API token too short (length: %d)', strlen( $api_token ) ) );
			wp_send_json_error(
				array(
					'message' => 'API Token appears to be invalid (too short). Please check your token.',
				)
			);
		}

		// Create client and test connection
		try {
			error_log( '[Marketing Analytics MCP] Creating Clarity client instance' );
			$client = new Clarity_Client( $api_token, $project_id );

			error_log( '[Marketing Analytics MCP] Calling test_connection()' );
			$result = $client->test_connection();

			error_log( sprintf( '[Marketing Analytics MCP] Connection test result: %s', wp_json_encode( $result ) ) );

			if ( $result['success'] ) {
				error_log( '[Marketing Analytics MCP] ===== CONNECTION TEST SUCCESSFUL =====' );
				wp_send_json_success(
					array(
						'message' => $result['message'],
						'data'    => $result['data'] ?? null,
					)
				);
			} else {
				error_log( sprintf( '[Marketing Analytics MCP] ===== CONNECTION TEST FAILED: %s =====', $result['message'] ) );
				wp_send_json_error(
					array(
						'message' => $result['message'],
					)
				);
			}
		} catch ( \Exception $e ) {
			error_log( '[Marketing Analytics MCP] ===== CONNECTION TEST EXCEPTION =====' );
			error_log( sprintf( '[Marketing Analytics MCP] Exception class: %s', get_class( $e ) ) );
			error_log( sprintf( '[Marketing Analytics MCP] Exception message: %s', $e->getMessage() ) );
			error_log( sprintf( '[Marketing Analytics MCP] Exception trace: %s', $e->getTraceAsString() ) );

			wp_send_json_error(
				array(
					'message' => 'Connection test failed: ' . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Test OAuth platform connection (GA4 or GSC)
	 */
	private function test_oauth_platform_connection( $platform ) {
		error_log( sprintf( '[Marketing Analytics MCP] Testing OAuth connection for: %s', $platform ) );

		try {
			$connection_tester = new Connection_Tester();

			if ( $platform === 'ga4' ) {
				$result = $connection_tester->test_ga4_connection();
			} elseif ( $platform === 'gsc' ) {
				$result = $connection_tester->test_gsc_connection();
			} else {
				wp_send_json_error(
					array(
						'message' => 'Invalid platform for OAuth testing',
					)
				);
				return;
			}

			error_log( sprintf( '[Marketing Analytics MCP] OAuth connection test result: %s', wp_json_encode( $result ) ) );

			if ( $result['success'] ) {
				error_log( '[Marketing Analytics MCP] ===== OAUTH CONNECTION TEST SUCCESSFUL =====' );
				wp_send_json_success(
					array(
						'message' => $result['message'],
						'data'    => $result['data'] ?? null,
					)
				);
			} else {
				error_log( sprintf( '[Marketing Analytics MCP] ===== OAUTH CONNECTION TEST FAILED: %s =====', $result['message'] ) );
				wp_send_json_error(
					array(
						'message' => $result['message'],
					)
				);
			}
		} catch ( \Exception $e ) {
			error_log( '[Marketing Analytics MCP] ===== OAUTH CONNECTION TEST EXCEPTION =====' );
			error_log( sprintf( '[Marketing Analytics MCP] Exception: %s', $e->getMessage() ) );

			wp_send_json_error(
				array(
					'message' => 'Connection test failed: ' . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Save platform credentials
	 */
	public function save_credentials() {
		error_log( '[Marketing Analytics MCP] ===== AJAX SAVE CREDENTIALS REQUEST =====' );

		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'marketing-analytics-mcp-admin' ) ) {
			error_log( '[Marketing Analytics MCP] ERROR: Nonce verification failed' );
			wp_send_json_error(
				array(
					'message' => 'Security check failed.',
				)
			);
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			error_log( '[Marketing Analytics MCP] ERROR: User lacks permissions' );
			wp_send_json_error(
				array(
					'message' => 'Insufficient permissions.',
				)
			);
		}

		$platform = isset( $_POST['platform'] ) ? sanitize_text_field( wp_unslash( $_POST['platform'] ) ) : '';
		error_log( sprintf( '[Marketing Analytics MCP] Saving credentials for platform: %s', $platform ) );

		if ( $platform === 'clarity' ) {
			$api_token = isset( $_POST['api_token'] ) ? sanitize_text_field( wp_unslash( $_POST['api_token'] ) ) : '';
			$project_id = isset( $_POST['project_id'] ) ? sanitize_text_field( wp_unslash( $_POST['project_id'] ) ) : '';

			$credentials = array(
				'api_token'  => $api_token,
				'project_id' => $project_id,
			);

			$result = Encryption::save_credentials( $platform, $credentials );

			if ( $result ) {
				error_log( '[Marketing Analytics MCP] Credentials saved successfully' );
				wp_send_json_success(
					array(
						'message' => 'Credentials saved successfully!',
					)
				);
			} else {
				error_log( '[Marketing Analytics MCP] ERROR: Failed to save credentials' );
				wp_send_json_error(
					array(
						'message' => 'Failed to save credentials.',
					)
				);
			}
		} else {
			error_log( sprintf( '[Marketing Analytics MCP] ERROR: Unsupported platform: %s', $platform ) );
			wp_send_json_error(
				array(
					'message' => 'Unsupported platform.',
				)
			);
		}
	}

	/**
	 * Clear all caches
	 */
	public function clear_caches() {
		error_log( '[Marketing Analytics MCP] ===== AJAX CLEAR CACHES REQUEST =====' );

		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'marketing-analytics-mcp-admin' ) ) {
			error_log( '[Marketing Analytics MCP] ERROR: Nonce verification failed' );
			wp_send_json_error(
				array(
					'message' => 'Security check failed.',
				)
			);
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			error_log( '[Marketing Analytics MCP] ERROR: User lacks permissions' );
			wp_send_json_error(
				array(
					'message' => 'Insufficient permissions.',
				)
			);
		}

		global $wpdb;
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_marketing_analytics_mcp_%'
			)
		);

		error_log( sprintf( '[Marketing Analytics MCP] Cleared %d cache entries', $deleted ) );

		wp_send_json_success(
			array(
				'message' => sprintf( 'Cleared %d cache entries', $deleted ),
			)
		);
	}

	/**
	 * List GA4 properties
	 */
	public function list_ga4_properties() {
		error_log( '[Marketing Analytics MCP] ===== AJAX LIST GA4 PROPERTIES REQUEST =====' );

		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'marketing-analytics-mcp-admin' ) ) {
			error_log( '[Marketing Analytics MCP] ERROR: Nonce verification failed' );
			wp_send_json_error(
				array(
					'message' => 'Security check failed.',
				)
			);
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			error_log( '[Marketing Analytics MCP] ERROR: User lacks permissions' );
			wp_send_json_error(
				array(
					'message' => 'Insufficient permissions.',
				)
			);
		}

		try {
			$client = new GA4_Client();
			$properties = $client->list_properties();

			if ( $properties === null ) {
				error_log( '[Marketing Analytics MCP] ERROR: Failed to retrieve properties' );
				wp_send_json_error(
					array(
						'message' => 'Failed to retrieve properties. Please ensure you are connected to Google Analytics.',
					)
				);
			}

			if ( empty( $properties ) ) {
				error_log( '[Marketing Analytics MCP] No properties found' );
				wp_send_json_error(
					array(
						'message' => 'No GA4 properties found for your account.',
					)
				);
			}

			error_log( sprintf( '[Marketing Analytics MCP] Found %d properties', count( $properties ) ) );
			wp_send_json_success(
				array(
					'properties' => $properties,
				)
			);
		} catch ( \Exception $e ) {
			error_log( '[Marketing Analytics MCP] ===== LIST PROPERTIES EXCEPTION =====' );
			error_log( sprintf( '[Marketing Analytics MCP] Exception: %s', $e->getMessage() ) );

			wp_send_json_error(
				array(
					'message' => 'Error fetching properties: ' . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Save GA4 property ID
	 */
	public function save_ga4_property() {
		error_log( '[Marketing Analytics MCP] ===== AJAX SAVE GA4 PROPERTY REQUEST =====' );

		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'marketing-analytics-mcp-admin' ) ) {
			error_log( '[Marketing Analytics MCP] ERROR: Nonce verification failed' );
			wp_send_json_error(
				array(
					'message' => 'Security check failed.',
				)
			);
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			error_log( '[Marketing Analytics MCP] ERROR: User lacks permissions' );
			wp_send_json_error(
				array(
					'message' => 'Insufficient permissions.',
				)
			);
		}

		$property_id = isset( $_POST['property_id'] ) ? sanitize_text_field( wp_unslash( $_POST['property_id'] ) ) : '';

		if ( empty( $property_id ) ) {
			error_log( '[Marketing Analytics MCP] ERROR: Property ID is empty' );
			wp_send_json_error(
				array(
					'message' => 'Please select a property.',
				)
			);
		}

		try {
			$client = new GA4_Client();
			$result = $client->set_property_id( $property_id );

			if ( $result ) {
				error_log( sprintf( '[Marketing Analytics MCP] Property ID saved: %s', $property_id ) );
				wp_send_json_success(
					array(
						'message'     => 'Property saved successfully!',
						'property_id' => $property_id,
					)
				);
			} else {
				error_log( '[Marketing Analytics MCP] ERROR: Failed to save property ID' );
				wp_send_json_error(
					array(
						'message' => 'Failed to save property.',
					)
				);
			}
		} catch ( \Exception $e ) {
			error_log( '[Marketing Analytics MCP] ===== SAVE PROPERTY EXCEPTION =====' );
			error_log( sprintf( '[Marketing Analytics MCP] Exception: %s', $e->getMessage() ) );

			wp_send_json_error(
				array(
					'message' => 'Error saving property: ' . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * List GSC sites
	 */
	public function list_gsc_sites() {
		error_log( '[Marketing Analytics MCP] ===== AJAX LIST GSC SITES REQUEST =====' );

		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'marketing-analytics-mcp-admin' ) ) {
			error_log( '[Marketing Analytics MCP] ERROR: Nonce verification failed' );
			wp_send_json_error(
				array(
					'message' => 'Security check failed.',
				)
			);
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			error_log( '[Marketing Analytics MCP] ERROR: User lacks permissions' );
			wp_send_json_error(
				array(
					'message' => 'Insufficient permissions.',
				)
			);
		}

		try {
			$client = new GSC_Client();
			$sites = $client->list_sites();

			if ( $sites === null ) {
				error_log( '[Marketing Analytics MCP] ERROR: Failed to retrieve sites' );
				wp_send_json_error(
					array(
						'message' => 'Failed to retrieve sites. Please ensure you are connected to Google Search Console.',
					)
				);
			}

			if ( empty( $sites ) ) {
				error_log( '[Marketing Analytics MCP] No sites found' );
				wp_send_json_error(
					array(
						'message' => 'No Search Console sites found for your account.',
					)
				);
			}

			error_log( sprintf( '[Marketing Analytics MCP] Found %d sites', count( $sites ) ) );
			wp_send_json_success(
				array(
					'sites' => $sites,
				)
			);
		} catch ( \Exception $e ) {
			error_log( '[Marketing Analytics MCP] ===== LIST SITES EXCEPTION =====' );
			error_log( sprintf( '[Marketing Analytics MCP] Exception: %s', $e->getMessage() ) );

			wp_send_json_error(
				array(
					'message' => 'Error fetching sites: ' . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Save GSC site URL
	 */
	public function save_gsc_site() {
		error_log( '[Marketing Analytics MCP] ===== AJAX SAVE GSC SITE REQUEST =====' );

		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'marketing-analytics-mcp-admin' ) ) {
			error_log( '[Marketing Analytics MCP] ERROR: Nonce verification failed' );
			wp_send_json_error(
				array(
					'message' => 'Security check failed.',
				)
			);
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			error_log( '[Marketing Analytics MCP] ERROR: User lacks permissions' );
			wp_send_json_error(
				array(
					'message' => 'Insufficient permissions.',
				)
			);
		}

		$site_url = isset( $_POST['site_url'] ) ? sanitize_text_field( wp_unslash( $_POST['site_url'] ) ) : '';

		if ( empty( $site_url ) ) {
			error_log( '[Marketing Analytics MCP] ERROR: Site URL is empty' );
			wp_send_json_error(
				array(
					'message' => 'Please select a site.',
				)
			);
		}

		try {
			$client = new GSC_Client();
			$result = $client->set_site_url( $site_url );

			if ( $result ) {
				error_log( sprintf( '[Marketing Analytics MCP] Site URL saved: %s', $site_url ) );
				wp_send_json_success(
					array(
						'message'  => 'Site saved successfully!',
						'site_url' => $site_url,
					)
				);
			} else {
				error_log( '[Marketing Analytics MCP] ERROR: Failed to save site URL' );
				wp_send_json_error(
					array(
						'message' => 'Failed to save site.',
					)
				);
			}
		} catch ( \Exception $e ) {
			error_log( '[Marketing Analytics MCP] ===== SAVE SITE EXCEPTION =====' );
			error_log( sprintf( '[Marketing Analytics MCP] Exception: %s', $e->getMessage() ) );

			wp_send_json_error(
				array(
					'message' => 'Error saving site: ' . $e->getMessage(),
				)
			);
		}
	}
}
