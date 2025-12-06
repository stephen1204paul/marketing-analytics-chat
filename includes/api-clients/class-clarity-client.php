<?php
/**
 * Microsoft Clarity API Client
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\API_Clients;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Marketing_Analytics_MCP\Utils\Logger;

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
		Logger::debug( 'Clarity: Initializing Clarity client' );
		Logger::debug( sprintf( 'Clarity: Project ID: %s', $project_id ) );
		Logger::debug( sprintf( 'Clarity: API Token provided: %s', $api_token ? 'yes (length: ' . strlen( $api_token ) . ')' : 'NO' ) );

		$this->api_token  = $api_token;
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

		Logger::debug( 'Clarity: HTTP client configured with base URL: ' . self::API_BASE_URL );
	}

	/**
	 * Test the API connection
	 *
	 * @return array Response with success status and message
	 */
	public function test_connection() {
		Logger::debug( 'Clarity: ===== STARTING CONNECTION TEST =====' );
		Logger::debug( sprintf( 'Clarity: Testing connection for project: %s', $this->project_id ) );
		Logger::debug( sprintf( 'Clarity: Note: Project ID is stored for reference but not used in API URL (token is project-specific)' ) );

		try {
			// Test connection by fetching 1 day of insights data
			$endpoint = 'project-live-insights';
			$params   = array( 'numOfDays' => 1 );

			Logger::debug( sprintf( 'Clarity: Making GET request to: %s/%s', self::API_BASE_URL, $endpoint ) );
			Logger::debug( sprintf( 'Clarity: Query params: %s', wp_json_encode( $params ) ) );

			$start_time = microtime( true );
			$response   = $this->http_client->get( $endpoint, array( 'query' => $params ) );
			$elapsed    = round( ( microtime( true ) - $start_time ) * 1000, 2 );

			$status_code = $response->getStatusCode();
			$body        = (string) $response->getBody();

			Logger::debug( sprintf( 'Clarity: Response received in %sms', $elapsed ) );
			Logger::debug( sprintf( 'Clarity: Status code: %d', $status_code ) );
			Logger::debug( sprintf( 'Clarity: Response body length: %d bytes', strlen( $body ) ) );
			Logger::debug( sprintf( 'Clarity: Response body preview: %s', substr( $body, 0, 500 ) ) );

			if ( $status_code === 200 ) {
				$data = json_decode( $body, true );

				if ( $data === null ) {
					Logger::debug( sprintf( 'Clarity: ERROR: Failed to decode JSON response. JSON error: %s', json_last_error_msg() ) );
					Logger::debug( sprintf( 'Clarity: Full response body: %s', $body ) );

					return array(
						'success' => false,
						'message' => 'Invalid JSON response from Clarity API: ' . json_last_error_msg(),
					);
				}

				Logger::debug( 'Clarity: ===== CONNECTION TEST SUCCESSFUL =====' );
				Logger::debug( sprintf( 'Clarity: Data summary: %s', wp_json_encode( array_keys( $data ) ) ) );

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
				Logger::debug( sprintf( 'Clarity: ERROR: Unexpected status code: %d', $status_code ) );
				Logger::debug( sprintf( 'Clarity: Response body: %s', $body ) );

				return array(
					'success' => false,
					'message' => sprintf( 'Unexpected response from Clarity API (Status: %d)', $status_code ),
				);
			}
		} catch ( GuzzleException $e ) {
			Logger::debug( 'Clarity: ===== CONNECTION TEST FAILED (GuzzleException) =====' );
			Logger::debug( sprintf( 'Clarity: Exception class: %s', get_class( $e ) ) );
			Logger::debug( sprintf( 'Clarity: Error message: %s', $e->getMessage() ) );
			Logger::debug( sprintf( 'Clarity: Error code: %s', $e->getCode() ) );

			// Check if there's a response (for HTTP errors)
			if ( method_exists( $e, 'hasResponse' ) && $e->hasResponse() ) {
				$response    = $e->getResponse();
				$status_code = $response->getStatusCode();
				$body        = (string) $response->getBody();

				Logger::debug( sprintf( 'Clarity: HTTP Status: %d', $status_code ) );
				Logger::debug( sprintf( 'Clarity: Response body: %s', $body ) );

				// Parse error message from response
				$data = json_decode( $body, true );
				if ( $data && isset( $data['error'] ) ) {
					$error_msg = is_string( $data['error'] ) ? $data['error'] : ( $data['error']['message'] ?? 'Unknown error' );
					Logger::debug( sprintf( 'Clarity: API error message: %s', $error_msg ) );

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
			Logger::debug( sprintf( 'Clarity: Full exception trace: %s', $e->getTraceAsString() ) );

			return array(
				'success' => false,
				'message' => 'Connection failed: ' . $e->getMessage(),
			);
		} catch ( \Exception $e ) {
			Logger::debug( 'Clarity: ===== CONNECTION TEST FAILED (Exception) =====' );
			Logger::debug( sprintf( 'Clarity: Exception class: %s', get_class( $e ) ) );
			Logger::debug( sprintf( 'Clarity: Error message: %s', $e->getMessage() ) );
			Logger::debug( sprintf( 'Clarity: Error trace: %s', $e->getTraceAsString() ) );

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
		Logger::debug( sprintf( 'Clarity: Getting insights for %d days', $num_of_days ) );

		// Validate numOfDays (Clarity only supports 1, 2, or 3)
		if ( ! in_array( $num_of_days, array( 1, 2, 3 ), true ) ) {
			Logger::debug( sprintf( 'Clarity: ERROR: Invalid numOfDays value: %d (must be 1, 2, or 3)', $num_of_days ) );
			return false;
		}

		try {
			$endpoint = 'project-live-insights';
			$params   = array( 'numOfDays' => $num_of_days );

			// Add dimensions if provided (dimension1, dimension2, dimension3)
			$dimension_keys = array( 'dimension1', 'dimension2', 'dimension3' );
			foreach ( $dimensions as $index => $dimension ) {
				if ( isset( $dimension_keys[ $index ] ) && ! empty( $dimension ) ) {
					$params[ $dimension_keys[ $index ] ] = $dimension;
				}
			}

			Logger::debug( sprintf( 'Clarity: Request params: %s', wp_json_encode( $params ) ) );

			$response = $this->http_client->get( $endpoint, array( 'query' => $params ) );
			$data     = json_decode( (string) $response->getBody(), true );

			Logger::debug( 'Clarity: Insights retrieved successfully' );

			return $data;

		} catch ( \Exception $e ) {
			Logger::debug( sprintf( 'Clarity: Failed to get insights: %s', $e->getMessage() ) );
			return false;
		}
	}

	/**
	 * Get session recordings list
	 *
	 * Note: The Clarity Data Export API primarily provides dashboard insights.
	 * Session recording retrieval may have limited support via the public API.
	 * For full session recording access, use the Clarity dashboard or consider
	 * the Microsoft Clarity MCP Server.
	 *
	 * @param array  $filters Array of filters (device, browser, os, country, etc.).
	 * @param int    $limit   Number of recordings to retrieve.
	 * @param string $sort_by Sort parameter (date, duration, pages).
	 * @return array|false Response data or false on failure.
	 */
	public function get_session_recordings( $filters = array(), $limit = 10, $sort_by = 'date' ) {
		Logger::debug( sprintf( 'Clarity: Getting session recordings (limit: %d, sort: %s)', $limit, $sort_by ) );

		try {
			// The Clarity Data Export API endpoint for recordings
			$endpoint = 'project-recordings';
			$params   = array(
				'limit'  => min( $limit, 100 ),
				'sortBy' => $sort_by,
			);

			// Add filters if provided
			if ( ! empty( $filters['device'] ) ) {
				$params['device'] = $filters['device'];
			}
			if ( ! empty( $filters['browser'] ) ) {
				$params['browser'] = $filters['browser'];
			}
			if ( ! empty( $filters['os'] ) ) {
				$params['os'] = $filters['os'];
			}
			if ( ! empty( $filters['country'] ) ) {
				$params['country'] = $filters['country'];
			}
			if ( ! empty( $filters['url'] ) ) {
				$params['url'] = $filters['url'];
			}

			Logger::debug( sprintf( 'Clarity: Request params: %s', wp_json_encode( $params ) ) );

			$response = $this->http_client->get( $endpoint, array( 'query' => $params ) );
			$data     = json_decode( (string) $response->getBody(), true );

			Logger::debug( 'Clarity: Session recordings retrieved successfully' );

			return $data;

		} catch ( \Exception $e ) {
			$error_message = $e->getMessage();
			Logger::debug( sprintf( 'Clarity: Failed to get session recordings: %s', $error_message ) );

			// If the endpoint doesn't exist, return helpful message
			if ( strpos( $error_message, '404' ) !== false || strpos( $error_message, 'Not Found' ) !== false ) {
				return array(
					'error'   => true,
					'message' => 'Session recordings endpoint not available. Access recordings via the Clarity dashboard at https://clarity.microsoft.com/',
					'note'    => 'The Clarity Data Export API primarily supports dashboard insights. For session recordings, use the Clarity web interface.',
				);
			}

			return false;
		}
	}
}
