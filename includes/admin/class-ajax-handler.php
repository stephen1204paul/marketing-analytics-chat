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
use Marketing_Analytics_MCP\API_Clients\Meta_Client;
use Marketing_Analytics_MCP\API_Clients\DataForSEO_Client;
use Marketing_Analytics_MCP\Credentials\Encryption;
use Marketing_Analytics_MCP\Credentials\Connection_Tester;
use Marketing_Analytics_MCP\Credentials\Credential_Manager;
use Marketing_Analytics_MCP\Credentials\OAuth_Handler;
use Marketing_Analytics_MCP\Notifications\Notification_Manager;
use Marketing_Analytics_MCP\Utils\Logger;
use Marketing_Analytics_MCP\Utils\Permission_Manager;

/**
 * Handles AJAX requests from admin interface
 */
class Ajax_Handler {

	/**
	 * Register AJAX hooks
	 */
	public function register_hooks() {
		Logger::debug( 'Registering AJAX hooks' );

		add_action( 'wp_ajax_marketing_analytics_mcp_test_connection', array( $this, 'test_connection' ) );
		add_action( 'wp_ajax_marketing_analytics_mcp_save_credentials', array( $this, 'save_credentials' ) );
		add_action( 'wp_ajax_marketing_analytics_mcp_clear_caches', array( $this, 'clear_caches' ) );
		add_action( 'wp_ajax_marketing_analytics_mcp_list_ga4_properties', array( $this, 'list_ga4_properties' ) );
		add_action( 'wp_ajax_marketing_analytics_mcp_save_ga4_property', array( $this, 'save_ga4_property' ) );
		add_action( 'wp_ajax_marketing_analytics_mcp_list_gsc_sites', array( $this, 'list_gsc_sites' ) );
		add_action( 'wp_ajax_marketing_analytics_mcp_save_gsc_site', array( $this, 'save_gsc_site' ) );

		// Meta Business Suite actions
		add_action( 'wp_ajax_marketing_analytics_save_meta_app_config', array( $this, 'save_meta_app_config' ) );
		add_action( 'wp_ajax_marketing_analytics_check_meta_auth', array( $this, 'check_meta_auth' ) );
		add_action( 'wp_ajax_marketing_analytics_list_facebook_pages', array( $this, 'list_facebook_pages' ) );
		add_action( 'wp_ajax_marketing_analytics_get_instagram_account', array( $this, 'get_instagram_account' ) );
		add_action( 'wp_ajax_marketing_analytics_save_meta_accounts', array( $this, 'save_meta_accounts' ) );
		add_action( 'wp_ajax_marketing_analytics_test_connection', array( $this, 'test_connection' ) );
		add_action( 'wp_ajax_marketing_analytics_disconnect', array( $this, 'disconnect_platform' ) );

		// DataForSEO actions
		add_action( 'wp_ajax_marketing_analytics_save_credentials', array( $this, 'save_credentials' ) );
		add_action( 'wp_ajax_marketing_analytics_test_dataforseo_connection', array( $this, 'test_dataforseo_connection' ) );
		add_action( 'wp_ajax_marketing_analytics_get_dataforseo_balance', array( $this, 'get_dataforseo_balance' ) );

		// Notification actions
		add_action( 'wp_ajax_marketing_analytics_test_slack', array( $this, 'test_slack' ) );
		add_action( 'wp_ajax_marketing_analytics_test_whatsapp', array( $this, 'test_whatsapp' ) );
		add_action( 'admin_post_marketing_analytics_save_notification_settings', array( $this, 'save_notification_settings' ) );
	}

	/**
	 * Test platform connection
	 */
	public function test_connection() {
		Logger::debug( '===== AJAX TEST CONNECTION REQUEST =====' );
		Logger::debug( sprintf( 'Request data: %s', wp_json_encode( $_POST ) ) );

		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'marketing-analytics-chat-admin' ) ) {
			Logger::error( 'Nonce verification failed' );
			wp_send_json_error(
				array(
					'message' => 'Security check failed. Please refresh the page and try again.',
				)
			);
			return;
		}

		// Check permissions
		if ( ! Permission_Manager::can_access_plugin() ) {
			Logger::error( 'User lacks manage_options capability' );
			wp_send_json_error(
				array(
					'message' => 'You do not have permission to perform this action.',
				)
			);
			return;
		}

		$platform = isset( $_POST['platform'] ) ? sanitize_text_field( wp_unslash( $_POST['platform'] ) ) : '';
		Logger::debug( sprintf( 'Testing connection for platform: %s', $platform ) );

		// Use Connection_Tester for OAuth-based platforms (GA4, GSC)
		if ( in_array( $platform, array( 'ga4', 'gsc' ), true ) ) {
			$this->test_oauth_platform_connection( $platform );
		} elseif ( $platform === 'clarity' ) {
			$this->test_clarity_connection();
		} else {
			Logger::debug( sprintf( 'ERROR: Unsupported platform: %s', $platform ) );
			wp_send_json_error(
				array(
					'message' => 'Unsupported platform: ' . $platform,
				)
			);
			return;
		}
	}

	/**
	 * Test Clarity connection
	 */
	private function test_clarity_connection() {
		Logger::debug( 'Testing Clarity connection' );

		// Get credentials from POST
		$api_token  = isset( $_POST['api_token'] ) ? sanitize_text_field( wp_unslash( $_POST['api_token'] ) ) : '';
		$project_id = isset( $_POST['project_id'] ) ? sanitize_text_field( wp_unslash( $_POST['project_id'] ) ) : '';

		Logger::debug( sprintf( 'API Token provided: %s', $api_token ? 'yes (length: ' . strlen( $api_token ) . ')' : 'NO' ) );
		Logger::debug( sprintf( 'Project ID: %s', $project_id ? $project_id : 'EMPTY' ) );

		// Validate inputs
		if ( empty( $api_token ) ) {
			Logger::error( 'API token is empty' );
			wp_send_json_error(
				array(
					'message' => 'API Token is required. Please enter your Clarity API token.',
				)
			);
			return;
		}

		if ( empty( $project_id ) ) {
			Logger::error( 'Project ID is empty' );
			wp_send_json_error(
				array(
					'message' => 'Project ID is required. Please enter your Clarity project ID.',
				)
			);
			return;
		}

		// Validate token format (should be a non-empty string)
		if ( strlen( $api_token ) < 10 ) {
			Logger::debug( sprintf( 'ERROR: API token too short (length: %d)', strlen( $api_token ) ) );
			wp_send_json_error(
				array(
					'message' => 'API Token appears to be invalid (too short). Please check your token.',
				)
			);
			return;
		}

		// Create client and test connection
		try {
			Logger::debug( 'Creating Clarity client instance' );
			$client = new Clarity_Client( $api_token, $project_id );

			Logger::debug( 'Calling test_connection()' );
			$result = $client->test_connection();

			Logger::debug( sprintf( 'Connection test result: %s', wp_json_encode( $result ) ) );

			if ( $result['success'] ) {
				Logger::debug( '===== CONNECTION TEST SUCCESSFUL =====' );
				wp_send_json_success(
					array(
						'message' => $result['message'],
						'data'    => $result['data'] ?? null,
					)
				);
			} else {
				Logger::debug( sprintf( '===== CONNECTION TEST FAILED: %s =====', $result['message'] ) );
				wp_send_json_error(
					array(
						'message' => $result['message'],
					)
				);
			}
		} catch ( \Exception $e ) {
			Logger::debug( '===== CONNECTION TEST EXCEPTION =====' );
			Logger::debug( sprintf( 'Exception class: %s', get_class( $e ) ) );
			Logger::debug( sprintf( 'Exception message: %s', $e->getMessage() ) );
			Logger::debug( sprintf( 'Exception trace: %s', $e->getTraceAsString() ) );

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
		Logger::debug( sprintf( 'Testing OAuth connection for: %s', $platform ) );

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

			Logger::debug( sprintf( 'OAuth connection test result: %s', wp_json_encode( $result ) ) );

			if ( $result['success'] ) {
				Logger::debug( '===== OAUTH CONNECTION TEST SUCCESSFUL =====' );
				wp_send_json_success(
					array(
						'message' => $result['message'],
						'data'    => $result['data'] ?? null,
					)
				);
			} else {
				Logger::debug( sprintf( '===== OAUTH CONNECTION TEST FAILED: %s =====', $result['message'] ) );
				wp_send_json_error(
					array(
						'message' => $result['message'],
					)
				);
			}
		} catch ( \Exception $e ) {
			Logger::debug( '===== OAUTH CONNECTION TEST EXCEPTION =====' );
			Logger::debug( sprintf( 'Exception: %s', $e->getMessage() ) );

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
		Logger::debug( '===== AJAX SAVE CREDENTIALS REQUEST =====' );

		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'marketing-analytics-chat-admin' ) ) {
			Logger::error( 'Nonce verification failed' );
			wp_send_json_error(
				array(
					'message' => 'Security check failed.',
				)
			);
		}

		// Check permissions
		if ( ! Permission_Manager::can_access_plugin() ) {
			Logger::error( 'User lacks permissions' );
			wp_send_json_error(
				array(
					'message' => 'Insufficient permissions.',
				)
			);
		}

		$platform = isset( $_POST['platform'] ) ? sanitize_text_field( wp_unslash( $_POST['platform'] ) ) : '';
		Logger::debug( sprintf( 'Saving credentials for platform: %s', $platform ) );

		if ( $platform === 'clarity' ) {
			$api_token  = isset( $_POST['api_token'] ) ? sanitize_text_field( wp_unslash( $_POST['api_token'] ) ) : '';
			$project_id = isset( $_POST['project_id'] ) ? sanitize_text_field( wp_unslash( $_POST['project_id'] ) ) : '';

			$credentials = array(
				'api_token'  => $api_token,
				'project_id' => $project_id,
			);

			$result = Encryption::save_credentials( $platform, $credentials );

			if ( $result ) {
				Logger::debug( 'Credentials saved successfully' );
				wp_send_json_success(
					array(
						'message' => 'Credentials saved successfully!',
					)
				);
			} else {
				Logger::error( 'Failed to save credentials' );
				wp_send_json_error(
					array(
						'message' => 'Failed to save credentials.',
					)
				);
			}
		} else {
			Logger::debug( sprintf( 'ERROR: Unsupported platform: %s', $platform ) );
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
		Logger::debug( '===== AJAX CLEAR CACHES REQUEST =====' );

		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'marketing-analytics-chat-admin' ) ) {
			Logger::error( 'Nonce verification failed' );
			wp_send_json_error(
				array(
					'message' => 'Security check failed.',
				)
			);
		}

		// Check permissions
		if ( ! Permission_Manager::can_access_plugin() ) {
			Logger::error( 'User lacks permissions' );
			wp_send_json_error(
				array(
					'message' => 'Insufficient permissions.',
				)
			);
		}

		global $wpdb;
		// Use proper escaping for LIKE patterns with wpdb
		$pattern = $wpdb->esc_like( '_transient_marketing_analytics_mcp_' ) . '%';
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$pattern
			)
		);

		Logger::debug( sprintf( 'Cleared %d cache entries', $deleted ) );

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
		Logger::debug( '===== AJAX LIST GA4 PROPERTIES REQUEST =====' );

		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'marketing-analytics-chat-admin' ) ) {
			Logger::error( 'Nonce verification failed' );
			wp_send_json_error(
				array(
					'message' => 'Security check failed.',
				)
			);
		}

		// Check permissions
		if ( ! Permission_Manager::can_access_plugin() ) {
			Logger::error( 'User lacks permissions' );
			wp_send_json_error(
				array(
					'message' => 'Insufficient permissions.',
				)
			);
		}

		try {
			$client     = new GA4_Client();
			$properties = $client->list_properties();

			if ( $properties === null ) {
				Logger::error( 'Failed to retrieve properties' );
				wp_send_json_error(
					array(
						'message' => 'Failed to retrieve properties. Please ensure you are connected to Google Analytics.',
					)
				);
			}

			if ( empty( $properties ) ) {
				Logger::debug( 'No properties found' );
				wp_send_json_error(
					array(
						'message' => 'No GA4 properties found for your account.',
					)
				);
			}

			Logger::debug( sprintf( 'Found %d properties', count( $properties ) ) );
			wp_send_json_success(
				array(
					'properties' => $properties,
				)
			);
		} catch ( \Exception $e ) {
			Logger::error( '===== LIST PROPERTIES EXCEPTION =====' );
			Logger::error( sprintf( 'Exception: %s', $e->getMessage() ) );

			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Save GA4 property ID
	 */
	public function save_ga4_property() {
		Logger::debug( '===== AJAX SAVE GA4 PROPERTY REQUEST =====' );

		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'marketing-analytics-chat-admin' ) ) {
			Logger::error( 'Nonce verification failed' );
			wp_send_json_error(
				array(
					'message' => 'Security check failed.',
				)
			);
		}

		// Check permissions
		if ( ! Permission_Manager::can_access_plugin() ) {
			Logger::error( 'User lacks permissions' );
			wp_send_json_error(
				array(
					'message' => 'Insufficient permissions.',
				)
			);
		}

		$property_id = isset( $_POST['property_id'] ) ? sanitize_text_field( wp_unslash( $_POST['property_id'] ) ) : '';

		if ( empty( $property_id ) ) {
			Logger::error( 'Property ID is empty' );
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
				Logger::debug( sprintf( 'Property ID saved: %s', $property_id ) );
				wp_send_json_success(
					array(
						'message'     => 'Property saved successfully!',
						'property_id' => $property_id,
					)
				);
			} else {
				Logger::error( 'Failed to save property ID' );
				wp_send_json_error(
					array(
						'message' => 'Failed to save property.',
					)
				);
			}
		} catch ( \Exception $e ) {
			Logger::debug( '===== SAVE PROPERTY EXCEPTION =====' );
			Logger::debug( sprintf( 'Exception: %s', $e->getMessage() ) );

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
		Logger::debug( '===== AJAX LIST GSC SITES REQUEST =====' );

		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'marketing-analytics-chat-admin' ) ) {
			Logger::error( 'Nonce verification failed' );
			wp_send_json_error(
				array(
					'message' => 'Security check failed.',
				)
			);
		}

		// Check permissions
		if ( ! Permission_Manager::can_access_plugin() ) {
			Logger::error( 'User lacks permissions' );
			wp_send_json_error(
				array(
					'message' => 'Insufficient permissions.',
				)
			);
		}

		try {
			$client = new GSC_Client();
			$sites  = $client->list_sites();

			if ( $sites === null ) {
				Logger::error( 'Failed to retrieve sites' );
				wp_send_json_error(
					array(
						'message' => 'Failed to retrieve sites. Please ensure you are connected to Google Search Console.',
					)
				);
			}

			if ( empty( $sites ) ) {
				Logger::debug( 'No sites found' );
				wp_send_json_error(
					array(
						'message' => 'No Search Console sites found for your account.',
					)
				);
			}

			Logger::debug( sprintf( 'Found %d sites', count( $sites ) ) );
			wp_send_json_success(
				array(
					'sites' => $sites,
				)
			);
		} catch ( \Exception $e ) {
			Logger::debug( '===== LIST SITES EXCEPTION =====' );
			Logger::debug( sprintf( 'Exception: %s', $e->getMessage() ) );

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
		Logger::debug( '===== AJAX SAVE GSC SITE REQUEST =====' );

		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'marketing-analytics-chat-admin' ) ) {
			Logger::error( 'Nonce verification failed' );
			wp_send_json_error(
				array(
					'message' => 'Security check failed.',
				)
			);
		}

		// Check permissions
		if ( ! Permission_Manager::can_access_plugin() ) {
			Logger::error( 'User lacks permissions' );
			wp_send_json_error(
				array(
					'message' => 'Insufficient permissions.',
				)
			);
		}

		$site_url = isset( $_POST['site_url'] ) ? sanitize_text_field( wp_unslash( $_POST['site_url'] ) ) : '';

		if ( empty( $site_url ) ) {
			Logger::error( 'Site URL is empty' );
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
				Logger::debug( sprintf( 'Site URL saved: %s', $site_url ) );
				wp_send_json_success(
					array(
						'message'  => 'Site saved successfully!',
						'site_url' => $site_url,
					)
				);
			} else {
				Logger::error( 'Failed to save site URL' );
				wp_send_json_error(
					array(
						'message' => 'Failed to save site.',
					)
				);
			}
		} catch ( \Exception $e ) {
			Logger::debug( '===== SAVE SITE EXCEPTION =====' );
			Logger::debug( sprintf( 'Exception: %s', $e->getMessage() ) );

			wp_send_json_error(
				array(
					'message' => 'Error saving site: ' . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Save Meta app configuration (App ID and App Secret)
	 */
	public function save_meta_app_config() {
		Logger::debug( '===== AJAX SAVE META APP CONFIG REQUEST =====' );

		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'marketing-analytics-chat-admin' ) ) {
			Logger::error( 'Nonce verification failed' );
			wp_send_json_error(
				array(
					'message' => 'Security check failed.',
				)
			);
		}

		// Check permissions
		if ( ! Permission_Manager::can_access_plugin() ) {
			Logger::error( 'User lacks permissions' );
			wp_send_json_error(
				array(
					'message' => 'Insufficient permissions.',
				)
			);
		}

		$app_id     = isset( $_POST['app_id'] ) ? sanitize_text_field( wp_unslash( $_POST['app_id'] ) ) : '';
		$app_secret = isset( $_POST['app_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['app_secret'] ) ) : '';

		if ( empty( $app_id ) || empty( $app_secret ) ) {
			Logger::error( 'App ID or App Secret is empty' );
			wp_send_json_error(
				array(
					'message' => 'Both App ID and App Secret are required.',
				)
			);
		}

		// Save to options
		update_option( 'marketing_analytics_mcp_meta_app_id', $app_id );
		update_option( 'marketing_analytics_mcp_meta_app_secret', $app_secret );

		Logger::debug( 'Meta app config saved successfully' );
		wp_send_json_success(
			array(
				'message' => 'Meta app configuration saved successfully!',
			)
		);
	}

	/**
	 * Check Meta authorization status
	 */
	public function check_meta_auth() {
		Logger::debug( '===== AJAX CHECK META AUTH REQUEST =====' );

		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'marketing-analytics-chat-admin' ) ) {
			Logger::error( 'Nonce verification failed' );
			wp_send_json_error(
				array(
					'message' => 'Security check failed.',
				)
			);
		}

		// Check permissions
		if ( ! Permission_Manager::can_access_plugin() ) {
			Logger::error( 'User lacks permissions' );
			wp_send_json_error(
				array(
					'message' => 'Insufficient permissions.',
				)
			);
		}

		$oauth_handler = new OAuth_Handler();
		$has_token     = $oauth_handler->has_access_token( 'meta' );

		if ( $has_token ) {
			Logger::debug( 'Meta has valid token' );
			wp_send_json_success(
				array(
					'authenticated' => true,
					'message'       => 'Meta account is connected.',
				)
			);
		} else {
			$app_id     = get_option( 'marketing_analytics_mcp_meta_app_id' );
			$app_secret = get_option( 'marketing_analytics_mcp_meta_app_secret' );

			if ( empty( $app_id ) || empty( $app_secret ) ) {
				Logger::debug( 'Meta app configuration missing' );
				wp_send_json_error(
					array(
						'authenticated' => false,
						'message'       => 'Please configure Meta app credentials first.',
					)
				);
			}

			// Generate OAuth URL
			$auth_url = $oauth_handler->get_meta_auth_url();

			Logger::debug( 'Meta not authenticated, returning auth URL' );
			wp_send_json_success(
				array(
					'authenticated' => false,
					'auth_url'      => $auth_url,
					'message'       => 'Meta account not connected.',
				)
			);
		}
	}

	/**
	 * List Facebook pages for the authenticated user
	 */
	public function list_facebook_pages() {
		Logger::debug( '===== AJAX LIST FACEBOOK PAGES REQUEST =====' );

		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'marketing-analytics-chat-admin' ) ) {
			Logger::error( 'Nonce verification failed' );
			wp_send_json_error(
				array(
					'message' => 'Security check failed.',
				)
			);
		}

		// Check permissions
		if ( ! Permission_Manager::can_access_plugin() ) {
			Logger::error( 'User lacks permissions' );
			wp_send_json_error(
				array(
					'message' => 'Insufficient permissions.',
				)
			);
		}

		try {
			$meta_client = new Meta_Client();
			$pages       = $meta_client->get_user_pages();

			if ( empty( $pages ) ) {
				Logger::debug( 'No Facebook pages found' );
				wp_send_json_error(
					array(
						'message' => 'No Facebook pages found for your account.',
					)
				);
			}

			Logger::debug( sprintf( 'Found %d Facebook pages', count( $pages ) ) );
			wp_send_json_success(
				array(
					'pages' => $pages,
				)
			);
		} catch ( \Exception $e ) {
			Logger::debug( '===== LIST PAGES EXCEPTION =====' );
			Logger::debug( sprintf( 'Exception: %s', $e->getMessage() ) );

			wp_send_json_error(
				array(
					'message' => 'Error fetching Facebook pages: ' . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Get Instagram account for a Facebook page
	 */
	public function get_instagram_account() {
		Logger::debug( '===== AJAX GET INSTAGRAM ACCOUNT REQUEST =====' );

		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'marketing-analytics-chat-admin' ) ) {
			Logger::error( 'Nonce verification failed' );
			wp_send_json_error(
				array(
					'message' => 'Security check failed.',
				)
			);
		}

		// Check permissions
		if ( ! Permission_Manager::can_access_plugin() ) {
			Logger::error( 'User lacks permissions' );
			wp_send_json_error(
				array(
					'message' => 'Insufficient permissions.',
				)
			);
		}

		$page_id = isset( $_POST['page_id'] ) ? sanitize_text_field( wp_unslash( $_POST['page_id'] ) ) : '';

		if ( empty( $page_id ) ) {
			Logger::error( 'Page ID is empty' );
			wp_send_json_error(
				array(
					'message' => 'Page ID is required.',
				)
			);
		}

		try {
			$meta_client       = new Meta_Client();
			$instagram_account = $meta_client->get_instagram_account( $page_id );

			if ( empty( $instagram_account ) ) {
				Logger::debug( 'No Instagram account found for this page' );
				wp_send_json_error(
					array(
						'message' => 'No Instagram Business account found for this page.',
					)
				);
			}

			Logger::debug( 'Instagram account found' );
			wp_send_json_success(
				array(
					'instagram_account' => $instagram_account,
				)
			);
		} catch ( \Exception $e ) {
			Logger::debug( '===== GET INSTAGRAM EXCEPTION =====' );
			Logger::debug( sprintf( 'Exception: %s', $e->getMessage() ) );

			wp_send_json_error(
				array(
					'message' => 'Error fetching Instagram account: ' . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Save selected Meta accounts (Facebook Page and Instagram)
	 */
	public function save_meta_accounts() {
		Logger::debug( '===== AJAX SAVE META ACCOUNTS REQUEST =====' );

		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'marketing-analytics-chat-admin' ) ) {
			Logger::error( 'Nonce verification failed' );
			wp_send_json_error(
				array(
					'message' => 'Security check failed.',
				)
			);
		}

		// Check permissions
		if ( ! Permission_Manager::can_access_plugin() ) {
			Logger::error( 'User lacks permissions' );
			wp_send_json_error(
				array(
					'message' => 'Insufficient permissions.',
				)
			);
		}

		$page_id        = isset( $_POST['page_id'] ) ? sanitize_text_field( wp_unslash( $_POST['page_id'] ) ) : '';
		$page_name      = isset( $_POST['page_name'] ) ? sanitize_text_field( wp_unslash( $_POST['page_name'] ) ) : '';
		$instagram_id   = isset( $_POST['instagram_id'] ) ? sanitize_text_field( wp_unslash( $_POST['instagram_id'] ) ) : '';
		$instagram_name = isset( $_POST['instagram_name'] ) ? sanitize_text_field( wp_unslash( $_POST['instagram_name'] ) ) : '';

		if ( empty( $page_id ) ) {
			Logger::error( 'Page ID is empty' );
			wp_send_json_error(
				array(
					'message' => 'Facebook Page ID is required.',
				)
			);
		}

		// Save to options
		update_option( 'marketing_analytics_mcp_meta_page_id', $page_id );
		update_option( 'marketing_analytics_mcp_meta_page_name', $page_name );

		if ( ! empty( $instagram_id ) ) {
			update_option( 'marketing_analytics_mcp_meta_instagram_id', $instagram_id );
			update_option( 'marketing_analytics_mcp_meta_instagram_name', $instagram_name );
		}

		Logger::debug( 'Meta accounts saved successfully' );
		wp_send_json_success(
			array(
				'message' => 'Meta accounts saved successfully!',
			)
		);
	}

	/**
	 * Disconnect platform
	 */
	public function disconnect_platform() {
		Logger::debug( '===== AJAX DISCONNECT PLATFORM REQUEST =====' );

		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'marketing-analytics-chat-admin' ) ) {
			Logger::error( 'Nonce verification failed' );
			wp_send_json_error(
				array(
					'message' => 'Security check failed.',
				)
			);
		}

		// Check permissions
		if ( ! Permission_Manager::can_access_plugin() ) {
			Logger::error( 'User lacks permissions' );
			wp_send_json_error(
				array(
					'message' => 'Insufficient permissions.',
				)
			);
		}

		$platform = isset( $_POST['platform'] ) ? sanitize_text_field( wp_unslash( $_POST['platform'] ) ) : '';

		if ( empty( $platform ) ) {
			Logger::error( 'Platform is empty' );
			wp_send_json_error(
				array(
					'message' => 'Platform is required.',
				)
			);
		}

		try {
			$credential_manager = new Credential_Manager();
			$result             = $credential_manager->delete_credentials( $platform );

			if ( $platform === 'meta' ) {
				// Clear Meta-specific options
				delete_option( 'marketing_analytics_mcp_meta_page_id' );
				delete_option( 'marketing_analytics_mcp_meta_page_name' );
				delete_option( 'marketing_analytics_mcp_meta_instagram_id' );
				delete_option( 'marketing_analytics_mcp_meta_instagram_name' );
			}

			if ( $result ) {
				Logger::debug( sprintf( 'Platform %s disconnected successfully', $platform ) );
				wp_send_json_success(
					array(
						'message' => 'Platform disconnected successfully!',
					)
				);
			} else {
				Logger::debug( sprintf( 'ERROR: Failed to disconnect platform %s', $platform ) );
				wp_send_json_error(
					array(
						'message' => 'Failed to disconnect platform.',
					)
				);
			}
		} catch ( \Exception $e ) {
			Logger::debug( '===== DISCONNECT EXCEPTION =====' );
			Logger::debug( sprintf( 'Exception: %s', $e->getMessage() ) );

			wp_send_json_error(
				array(
					'message' => 'Error disconnecting platform: ' . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Test DataForSEO connection
	 */
	public function test_dataforseo_connection() {
		Logger::debug( '===== AJAX TEST DATAFORSEO CONNECTION REQUEST =====' );

		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'marketing-analytics-chat-admin' ) ) {
			Logger::error( 'Nonce verification failed' );
			wp_send_json_error(
				array(
					'message' => 'Security check failed.',
				)
			);
		}

		// Check permissions
		if ( ! Permission_Manager::can_access_plugin() ) {
			Logger::error( 'User lacks permissions' );
			wp_send_json_error(
				array(
					'message' => 'Insufficient permissions.',
				)
			);
		}

		$login    = isset( $_POST['login'] ) ? sanitize_text_field( wp_unslash( $_POST['login'] ) ) : '';
		$password = isset( $_POST['password'] ) ? sanitize_text_field( wp_unslash( $_POST['password'] ) ) : '';

		if ( empty( $login ) || empty( $password ) ) {
			Logger::error( 'Login or password is empty' );
			wp_send_json_error(
				array(
					'message' => 'Both login and password are required.',
				)
			);
		}

		try {
			// Save credentials temporarily for testing
			$credential_manager = new Credential_Manager();
			$credential_manager->save_credentials(
				'dataforseo',
				array(
					'login'    => $login,
					'password' => $password,
				)
			);

			// Test connection
			$dataforseo_client = new DataForSEO_Client();
			$is_connected      = $dataforseo_client->test_connection();

			if ( $is_connected ) {
				$balance = $dataforseo_client->get_credit_balance();

				Logger::debug( sprintf( 'DataForSEO connected successfully. Balance: $%s', $balance ) );
				wp_send_json_success(
					array(
						'message' => 'Connection successful!',
						'balance' => $balance,
					)
				);
			} else {
				// Remove invalid credentials
				$credential_manager->delete_credentials( 'dataforseo' );

				Logger::debug( 'DataForSEO connection failed' );
				wp_send_json_error(
					array(
						'message' => 'Connection failed. Please check your credentials.',
					)
				);
			}
		} catch ( \Exception $e ) {
			Logger::debug( '===== TEST DATAFORSEO EXCEPTION =====' );
			Logger::debug( sprintf( 'Exception: %s', $e->getMessage() ) );

			// Remove invalid credentials
			$credential_manager = new Credential_Manager();
			$credential_manager->delete_credentials( 'dataforseo' );

			wp_send_json_error(
				array(
					'message' => 'Connection test failed: ' . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Get DataForSEO credit balance
	 */
	public function get_dataforseo_balance() {
		Logger::debug( '===== AJAX GET DATAFORSEO BALANCE REQUEST =====' );

		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'marketing-analytics-chat-admin' ) ) {
			Logger::error( 'Nonce verification failed' );
			wp_send_json_error(
				array(
					'message' => 'Security check failed.',
				)
			);
		}

		// Check permissions
		if ( ! Permission_Manager::can_access_plugin() ) {
			Logger::error( 'User lacks permissions' );
			wp_send_json_error(
				array(
					'message' => 'Insufficient permissions.',
				)
			);
		}

		try {
			$dataforseo_client = new DataForSEO_Client();
			$balance           = $dataforseo_client->get_credit_balance();
			$usage_stats       = $dataforseo_client->get_usage_statistics();

			Logger::debug( sprintf( 'DataForSEO balance: $%s', $balance ) );
			wp_send_json_success(
				array(
					'balance'     => $balance,
					'usage_stats' => $usage_stats,
				)
			);
		} catch ( \Exception $e ) {
			Logger::debug( '===== GET BALANCE EXCEPTION =====' );
			Logger::debug( sprintf( 'Exception: %s', $e->getMessage() ) );

			wp_send_json_error(
				array(
					'message' => 'Error fetching balance: ' . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Test Slack notification
	 */
	public function test_slack() {
		Logger::debug( '===== AJAX TEST SLACK REQUEST =====' );

		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'marketing_analytics_test_notification' ) ) {
			Logger::error( 'Nonce verification failed' );
			wp_send_json_error( 'Security check failed.' );
			return;
		}

		// Check permissions
		if ( ! Permission_Manager::can_access_plugin() ) {
			Logger::error( 'User lacks permissions' );
			wp_send_json_error( 'Insufficient permissions.' );
			return;
		}

		try {
			$notification_manager = new Notification_Manager();
			$result               = $notification_manager->test_channel( 'slack' );

			if ( is_wp_error( $result ) ) {
				Logger::debug( sprintf( 'Slack test failed: %s', $result->get_error_message() ) );
				wp_send_json_error( $result->get_error_message() );
			} else {
				Logger::debug( 'Slack test successful' );
				wp_send_json_success( 'Test message sent successfully!' );
			}
		} catch ( \Exception $e ) {
			Logger::debug( sprintf( 'Slack test exception: %s', $e->getMessage() ) );
			wp_send_json_error( 'Error sending test: ' . $e->getMessage() );
		}
	}

	/**
	 * Test WhatsApp notification
	 */
	public function test_whatsapp() {
		Logger::debug( '===== AJAX TEST WHATSAPP REQUEST =====' );

		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'marketing_analytics_test_notification' ) ) {
			Logger::error( 'Nonce verification failed' );
			wp_send_json_error( 'Security check failed.' );
			return;
		}

		// Check permissions
		if ( ! Permission_Manager::can_access_plugin() ) {
			Logger::error( 'User lacks permissions' );
			wp_send_json_error( 'Insufficient permissions.' );
			return;
		}

		try {
			$notification_manager = new Notification_Manager();
			$result               = $notification_manager->test_channel( 'whatsapp' );

			if ( is_wp_error( $result ) ) {
				Logger::debug( sprintf( 'WhatsApp test failed: %s', $result->get_error_message() ) );
				wp_send_json_error( $result->get_error_message() );
			} else {
				Logger::debug( 'WhatsApp test successful' );
				wp_send_json_success( 'Test message sent successfully!' );
			}
		} catch ( \Exception $e ) {
			Logger::debug( sprintf( 'WhatsApp test exception: %s', $e->getMessage() ) );
			wp_send_json_error( 'Error sending test: ' . $e->getMessage() );
		}
	}

	/**
	 * Save notification settings
	 */
	public function save_notification_settings() {
		Logger::debug( '===== SAVE NOTIFICATION SETTINGS REQUEST =====' );

		// Check nonce
		if ( ! isset( $_POST['notification_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['notification_settings_nonce'] ) ), 'marketing_analytics_notification_settings' ) ) {
			Logger::error( 'Nonce verification failed' );
			wp_die( esc_html__( 'Security check failed.', 'marketing-analytics-chat' ) );
		}

		// Check permissions
		if ( ! Permission_Manager::can_access_plugin() ) {
			Logger::error( 'User lacks permissions' );
			wp_die( esc_html__( 'Insufficient permissions.', 'marketing-analytics-chat' ) );
		}

		// Save Slack settings
		$slack_enabled  = isset( $_POST['slack_enabled'] ) ? 1 : 0;
		$slack_webhook  = isset( $_POST['slack_webhook'] ) ? sanitize_url( wp_unslash( $_POST['slack_webhook'] ) ) : '';
		$slack_channel  = isset( $_POST['slack_channel'] ) ? sanitize_text_field( wp_unslash( $_POST['slack_channel'] ) ) : '#marketing';
		$slack_bot_name = isset( $_POST['slack_bot_name'] ) ? sanitize_text_field( wp_unslash( $_POST['slack_bot_name'] ) ) : 'Marketing Analytics Bot';

		update_option( 'marketing_analytics_slack_enabled', $slack_enabled );
		update_option( 'marketing_analytics_slack_webhook_url', $slack_webhook );
		update_option( 'marketing_analytics_slack_channel', $slack_channel );
		update_option( 'marketing_analytics_slack_bot_name', $slack_bot_name );

		// Save WhatsApp settings
		$whatsapp_enabled = isset( $_POST['whatsapp_enabled'] ) ? 1 : 0;
		$twilio_sid       = isset( $_POST['twilio_sid'] ) ? sanitize_text_field( wp_unslash( $_POST['twilio_sid'] ) ) : '';
		$twilio_token     = isset( $_POST['twilio_token'] ) ? sanitize_text_field( wp_unslash( $_POST['twilio_token'] ) ) : '';
		$twilio_number    = isset( $_POST['twilio_number'] ) ? sanitize_text_field( wp_unslash( $_POST['twilio_number'] ) ) : '';
		$whatsapp_recip   = isset( $_POST['whatsapp_recipients'] ) ? sanitize_textarea_field( wp_unslash( $_POST['whatsapp_recipients'] ) ) : '';

		// Convert recipients to array
		$recipients = array_filter( array_map( 'trim', explode( "\n", $whatsapp_recip ) ) );

		update_option( 'marketing_analytics_whatsapp_enabled', $whatsapp_enabled );
		update_option( 'marketing_analytics_twilio_account_sid', $twilio_sid );

		// Encrypt auth token if provided and not placeholder
		if ( ! empty( $twilio_token ) && $twilio_token !== '********' ) {
			if ( class_exists( 'Marketing_Analytics_MCP\\Credentials\\Encryption' ) ) {
				$encrypted_token = \Marketing_Analytics_MCP\Credentials\Encryption::encrypt( $twilio_token, 'twilio' );
				update_option( 'marketing_analytics_twilio_auth_token', $encrypted_token );
			} else {
				update_option( 'marketing_analytics_twilio_auth_token', $twilio_token );
			}
		}

		update_option( 'marketing_analytics_twilio_whatsapp_number', $twilio_number );
		update_option( 'marketing_analytics_whatsapp_recipients', $recipients );

		// Save schedule settings
		$daily_enabled  = isset( $_POST['daily_enabled'] ) ? 1 : 0;
		$daily_time     = isset( $_POST['daily_time'] ) ? sanitize_text_field( wp_unslash( $_POST['daily_time'] ) ) : '09:00';
		$weekly_enabled = isset( $_POST['weekly_enabled'] ) ? 1 : 0;
		$weekly_day     = isset( $_POST['weekly_day'] ) ? sanitize_text_field( wp_unslash( $_POST['weekly_day'] ) ) : 'monday';
		$weekly_time    = isset( $_POST['weekly_time'] ) ? sanitize_text_field( wp_unslash( $_POST['weekly_time'] ) ) : '09:00';

		update_option( 'marketing_analytics_daily_summary_enabled', $daily_enabled );
		update_option( 'marketing_analytics_daily_summary_time', $daily_time );
		update_option( 'marketing_analytics_weekly_report_enabled', $weekly_enabled );
		update_option( 'marketing_analytics_weekly_report_day', $weekly_day );
		update_option( 'marketing_analytics_weekly_report_time', $weekly_time );

		// Reschedule cron jobs
		wp_clear_scheduled_hook( 'marketing_analytics_daily_summary' );
		wp_clear_scheduled_hook( 'marketing_analytics_weekly_report' );

		if ( $daily_enabled ) {
			$timestamp = strtotime( 'today ' . $daily_time );
			if ( $timestamp < time() ) {
				$timestamp = strtotime( 'tomorrow ' . $daily_time );
			}
			wp_schedule_event( $timestamp, 'daily', 'marketing_analytics_daily_summary' );
		}

		if ( $weekly_enabled ) {
			$timestamp = strtotime( 'next ' . $weekly_day . ' ' . $weekly_time );
			wp_schedule_event( $timestamp, 'weekly', 'marketing_analytics_weekly_report' );
		}

		Logger::debug( 'Notification settings saved successfully' );

		// Redirect back to settings page
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'marketing-analytics-chat',
					'tab'     => 'settings',
					'section' => 'notifications',
					'saved'   => 'true',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
