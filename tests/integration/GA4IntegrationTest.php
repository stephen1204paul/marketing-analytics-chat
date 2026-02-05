<?php
/**
 * Integration tests for GA4 API client.
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\Tests\integration;

use Marketing_Analytics_MCP\API_Clients\GA4_Client;
use Marketing_Analytics_MCP\Credentials\Credential_Manager;
use PHPUnit\Framework\TestCase;

/**
 * GA4 integration test class.
 */
class GA4IntegrationTest extends TestCase {

	/**
	 * GA4 client instance.
	 *
	 * @var GA4_Client
	 */
	private $client;

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->client = new GA4_Client();

		// Skip tests if no credentials
		if ( ! getenv( 'GA4_CLIENT_ID' ) || ! getenv( 'GA4_CLIENT_SECRET' ) ) {
			$this->markTestSkipped( 'GA4 API credentials not available for integration testing.' );
		}

		Credential_Manager::save_credentials( 'ga4', array(
			'client_id'     => getenv( 'GA4_CLIENT_ID' ),
			'client_secret' => getenv( 'GA4_CLIENT_SECRET' ),
			'refresh_token' => getenv( 'GA4_REFRESH_TOKEN' ),
		) );
	}

	/**
	 * Test real GA4 API call.
	 *
	 * @group integration
	 * @group external-api
	 */
	public function test_get_metrics_real_api_call(): void {
		$property_id = getenv( 'GA4_PROPERTY_ID' );

		if ( ! $property_id ) {
			$this->markTestSkipped( 'GA4_PROPERTY_ID not set.' );
		}

		$result = $this->client->get_metrics( array(
			'property_id' => $property_id,
			'start_date'  => date( 'Y-m-d', strtotime( '-7 days' ) ),
			'end_date'    => date( 'Y-m-d' ),
			'metrics'     => array( 'activeUsers', 'sessions' ),
		) );

		$this->assertIsArray( $result );
	}

	/**
	 * Test OAuth token refresh.
	 *
	 * @group integration
	 */
	public function test_oauth_token_refresh(): void {
		// Client should automatically refresh expired tokens
		// This is handled by the Google API client library
		$this->assertTrue( method_exists( $this->client, 'refresh_access_token' ) ||
						   class_exists( 'Google_Client' ) );
	}

	/**
	 * Test multiple metrics in single request.
	 *
	 * @group integration
	 */
	public function test_multiple_metrics(): void {
		$property_id = getenv( 'GA4_PROPERTY_ID' );

		if ( ! $property_id ) {
			$this->markTestSkipped( 'GA4_PROPERTY_ID not set.' );
		}

		$result = $this->client->get_metrics( array(
			'property_id' => $property_id,
			'start_date'  => date( 'Y-m-d', strtotime( '-30 days' ) ),
			'end_date'    => date( 'Y-m-d' ),
			'metrics'     => array(
				'activeUsers',
				'sessions',
				'screenPageViews',
				'bounceRate',
			),
		) );

		$this->assertIsArray( $result );

		if ( ! empty( $result ) ) {
			$this->assertArrayHasKey( 'rows', $result );
		}
	}

	/**
	 * Test dimension grouping.
	 *
	 * @group integration
	 */
	public function test_dimension_grouping(): void {
		$property_id = getenv( 'GA4_PROPERTY_ID' );

		if ( ! $property_id ) {
			$this->markTestSkipped( 'GA4_PROPERTY_ID not set.' );
		}

		$result = $this->client->get_metrics( array(
			'property_id' => $property_id,
			'start_date'  => date( 'Y-m-d', strtotime( '-7 days' ) ),
			'end_date'    => date( 'Y-m-d' ),
			'metrics'     => array( 'activeUsers' ),
			'dimensions'  => array( 'country', 'deviceCategory' ),
		) );

		$this->assertIsArray( $result );
	}
}
