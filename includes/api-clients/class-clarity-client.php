<?php
/**
 * Microsoft Clarity API Client
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\API_Clients;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Client for Microsoft Clarity Data Export API
 *
 * API Documentation: https://learn.microsoft.com/en-us/clarity/setup-and-installation/clarity-data-export-api
 * Rate Limit: 10 requests per day per project
 */
class Clarity_Client {

	/**
	 * API Base URL
	 */
	const API_BASE_URL = 'https://www.clarity.ms/export-data/api/v1';

	/**
	 * HTTP client
	 *
	 * @var Client
	 */
	private $http_client;

	/**
	 * API Token
	 *
	 * @var string
	 */
	private $api_token;

	/**
	 * Project ID
	 *
	 * @var string
	 */
	private $project_id;

	/**
	 * Constructor
	 *
	 * @param string $api_token  The API token.
	 * @param string $project_id The project ID.
	 */
	public function __construct( $api_token, $project_id ) {
		error_log( '[Marketing Analytics MCP - Clarity] Initializing Clarity client' );
		error_log( sprintf( '[Marketing Analytics MCP - Clarity] Project ID: %s', $project_id ) );
		error_log( sprintf( '[Marketing Analytics MCP - Clarity] API Token provided: %s', $api_token ? 'yes (length: ' . strlen( $api_token ) . ')' : 'NO' ) );

		$this->api_token = $api_token;
		$this->project_id = $project_id;

		$this->http_client = new Client(
			array(
				'base_uri' => self::API_BASE_URL . '/',
				'timeout'  => 30,
				'headers'  => array(
					'Accept'        => 'application/json',
					'Authorization' => 'Bearer ' . $api_token,
				),
			)
		);

		error_log( '[Marketing Analytics MCP - Clarity] HTTP client configured with base URL: ' . self::API_BASE_URL );
	}

	/**
	 * Test the API connection
	 *
	 * @return array Response with success status and message
	 */
	public function test_connection() {
		error_log( '[Marketing Analytics MCP - Clarity] ===== STARTING CONNECTION TEST =====' );
		error_log( sprintf( '[Marketing Analytics MCP - Clarity] Testing connection for project: %s', $this->project_id ) );
		error_log( sprintf( '[Marketing Analytics MCP - Clarity] Note: Project ID is stored for reference but not used in API URL (token is project-specific)' ) );

		try {
			// Test connection by fetching 1 day of insights data
			$endpoint = 'project-live-insights';
			$params = array( 'numOfDays' => 1 );

			error_log( sprintf( '[Marketing Analytics MCP - Clarity] Making GET request to: %s/%s', self::API_BASE_URL, $endpoint ) );
			error_log( sprintf( '[Marketing Analytics MCP - Clarity] Query params: %s', wp_json_encode( $params ) ) );

			$start_time = microtime( true );
			$response = $this->http_client->get( $endpoint, array( 'query' => $params ) );
			$elapsed = round( ( microtime( true ) - $start_time ) * 1000, 2 );

			$status_code = $response->getStatusCode();
			$body = (string) $response->getBody();

			error_log( sprintf( '[Marketing Analytics MCP - Clarity] Response received in %sms', $elapsed ) );
			error_log( sprintf( '[Marketing Analytics MCP - Clarity] Status code: %d', $status_code ) );
			error_log( sprintf( '[Marketing Analytics MCP - Clarity] Response body length: %d bytes', strlen( $body ) ) );
			error_log( sprintf( '[Marketing Analytics MCP - Clarity] Response body preview: %s', substr( $body, 0, 500 ) ) );

			if ( $status_code === 200 ) {
				$data = json_decode( $body, true );

				if ( $data === null ) {
					error_log( sprintf( '[Marketing Analytics MCP - Clarity] ERROR: Failed to decode JSON response. JSON error: %s', json_last_error_msg() ) );
					error_log( sprintf( '[Marketing Analytics MCP - Clarity] Full response body: %s', $body ) );

					return array(
						'success' => false,
						'message' => 'Invalid JSON response from Clarity API: ' . json_last_error_msg(),
					);
				}

				error_log( '[Marketing Analytics MCP - Clarity] ===== CONNECTION TEST SUCCESSFUL =====' );
				error_log( sprintf( '[Marketing Analytics MCP - Clarity] Data summary: %s', wp_json_encode( array_keys( $data ) ) ) );

				// Extract some basic info for the success message
				$message = 'Connection successful! ';
				if ( isset( $data['totalSessions'] ) ) {
					$message .= sprintf( 'Retrieved data with %d sessions.', $data['totalSessions'] );
				}

				return array(
					'success' => true,
					'message' => $message,
					'data'    => $data,
				);
			} else {
				error_log( sprintf( '[Marketing Analytics MCP - Clarity] ERROR: Unexpected status code: %d', $status_code ) );
				error_log( sprintf( '[Marketing Analytics MCP - Clarity] Response body: %s', $body ) );

				return array(
					'success' => false,
					'message' => sprintf( 'Unexpected response from Clarity API (Status: %d)', $status_code ),
				);
			}
		} catch ( GuzzleException $e ) {
			error_log( '[Marketing Analytics MCP - Clarity] ===== CONNECTION TEST FAILED (GuzzleException) =====' );
			error_log( sprintf( '[Marketing Analytics MCP - Clarity] Exception class: %s', get_class( $e ) ) );
			error_log( sprintf( '[Marketing Analytics MCP - Clarity] Error message: %s', $e->getMessage() ) );
			error_log( sprintf( '[Marketing Analytics MCP - Clarity] Error code: %s', $e->getCode() ) );

			// Check if there's a response (for HTTP errors)
			if ( method_exists( $e, 'hasResponse' ) && $e->hasResponse() ) {
				$response = $e->getResponse();
				$status_code = $response->getStatusCode();
				$body = (string) $response->getBody();

				error_log( sprintf( '[Marketing Analytics MCP - Clarity] HTTP Status: %d', $status_code ) );
				error_log( sprintf( '[Marketing Analytics MCP - Clarity] Response body: %s', $body ) );

				// Parse error message from response
				$data = json_decode( $body, true );
				if ( $data && isset( $data['error'] ) ) {
					$error_msg = is_string( $data['error'] ) ? $data['error'] : ( $data['error']['message'] ?? 'Unknown error' );
					error_log( sprintf( '[Marketing Analytics MCP - Clarity] API error message: %s', $error_msg ) );

					return array(
						'success' => false,
						'message' => sprintf( 'Clarity API Error (%d): %s', $status_code, $error_msg ),
					);
				}

				return array(
					'success' => false,
					'message' => sprintf( 'HTTP Error %d: %s', $status_code, $this->get_http_error_description( $status_code ) ),
				);
			}

			// Network or other error without response
			error_log( sprintf( '[Marketing Analytics MCP - Clarity] Full exception trace: %s', $e->getTraceAsString() ) );

			return array(
				'success' => false,
				'message' => 'Connection failed: ' . $e->getMessage(),
			);
		} catch ( \Exception $e ) {
			error_log( '[Marketing Analytics MCP - Clarity] ===== CONNECTION TEST FAILED (Exception) =====' );
			error_log( sprintf( '[Marketing Analytics MCP - Clarity] Exception class: %s', get_class( $e ) ) );
			error_log( sprintf( '[Marketing Analytics MCP - Clarity] Error message: %s', $e->getMessage() ) );
			error_log( sprintf( '[Marketing Analytics MCP - Clarity] Error trace: %s', $e->getTraceAsString() ) );

			return array(
				'success' => false,
				'message' => 'Unexpected error: ' . $e->getMessage(),
			);
		}
	}

	/**
	 * Get human-readable description for HTTP status codes
	 *
	 * @param int $status_code HTTP status code.
	 * @return string Description
	 */
	private function get_http_error_description( $status_code ) {
		$descriptions = array(
			400 => 'Bad Request - Check your project ID format',
			401 => 'Unauthorized - Invalid API token',
			403 => 'Forbidden - API token does not have access to this project',
			404 => 'Not Found - Project ID does not exist',
			429 => 'Rate limit exceeded (10 requests per day)',
			500 => 'Clarity server error',
			503 => 'Clarity service unavailable',
		);

		return $descriptions[ $status_code ] ?? 'Unknown error';
	}

	/**
	 * Get insights data
	 *
	 * @param int   $num_of_days Number of days of data to retrieve (1, 2, or 3).
	 * @param array $dimensions  Optional dimensions for grouping (dimension1, dimension2, dimension3).
	 * @return array|false Response data or false on failure
	 */
	public function get_insights( $num_of_days = 1, $dimensions = array() ) {
		error_log( sprintf( '[Marketing Analytics MCP - Clarity] Getting insights for %d days', $num_of_days ) );

		// Validate numOfDays (Clarity only supports 1, 2, or 3)
		if ( ! in_array( $num_of_days, array( 1, 2, 3 ), true ) ) {
			error_log( sprintf( '[Marketing Analytics MCP - Clarity] ERROR: Invalid numOfDays value: %d (must be 1, 2, or 3)', $num_of_days ) );
			return false;
		}

		try {
			$endpoint = 'project-live-insights';
			$params = array( 'numOfDays' => $num_of_days );

			// Add dimensions if provided (dimension1, dimension2, dimension3)
			$dimension_keys = array( 'dimension1', 'dimension2', 'dimension3' );
			foreach ( $dimensions as $index => $dimension ) {
				if ( isset( $dimension_keys[ $index ] ) && ! empty( $dimension ) ) {
					$params[ $dimension_keys[ $index ] ] = $dimension;
				}
			}

			error_log( sprintf( '[Marketing Analytics MCP - Clarity] Request params: %s', wp_json_encode( $params ) ) );

			$response = $this->http_client->get( $endpoint, array( 'query' => $params ) );
			$data = json_decode( (string) $response->getBody(), true );

			error_log( '[Marketing Analytics MCP - Clarity] Insights retrieved successfully' );

			return $data;

		} catch ( \Exception $e ) {
			error_log( sprintf( '[Marketing Analytics MCP - Clarity] Failed to get insights: %s', $e->getMessage() ) );
			return false;
		}
	}
}
