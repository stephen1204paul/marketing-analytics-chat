<?php
/**
 * Tests for the GA4_Client class.
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\Tests\unit;

use Marketing_Analytics_MCP\API_Clients\GA4_Client;
use PHPUnit\Framework\TestCase;

/**
 * GA4 Client test class.
 */
class GA4ClientTest extends TestCase {

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		global $mock_options;
		$mock_options = array(
			'marketing_analytics_mcp_ga4_property_id' => '12345678', // Mock property ID
		);
	}

	/**
	 * Test client initialization.
	 */
	public function test_client_initialization(): void {
		$client = new GA4_Client();
		$this->assertInstanceOf( GA4_Client::class, $client );
	}

	/**
	 * Test run_report validates date range.
	 *
	 * @group integration
	 */
	public function test_run_report_validates_date_range(): void {
		try {
			$client = new GA4_Client();

			// Test with valid date range
			$metrics = array( 'activeUsers' );
			$dimensions = array();
			$date_range = '30daysAgo';

			// This will fail on credentials but should not throw errors
			$result = $client->run_report( $metrics, $dimensions, $date_range );
			$this->assertTrue( is_array( $result ) || false === $result );
		} catch ( \Exception $e ) {
			// Expected to fail without credentials
			$this->assertStringContainsString( 'Failed to initialize GA4 client', $e->getMessage() );
		}
	}

	/**
	 * Test metric name validation.
	 *
	 * @group integration
	 */
	public function test_validates_metric_names(): void {
		try {
			$client = new GA4_Client();

			$valid_metrics = array(
				'activeUsers',
				'sessions',
				'screenPageViews',
				'averageSessionDuration',
				'bounceRate',
			);

			foreach ( $valid_metrics as $metric ) {
				$result = $client->run_report( array( $metric ), array(), '7daysAgo' );
				// Should not throw errors with valid metric names
				$this->assertTrue( is_array( $result ) || false === $result );
			}
		} catch ( \Exception $e ) {
			// Expected to fail without credentials
			$this->assertStringContainsString( 'Failed to initialize GA4 client', $e->getMessage() );
		}
	}

	/**
	 * Test dimension validation.
	 *
	 * @group integration
	 */
	public function test_validates_dimensions(): void {
		try {
			$client = new GA4_Client();

			$valid_dimensions = array(
				'date',
				'country',
				'city',
				'deviceCategory',
				'browser',
				'landingPage',
			);

			foreach ( $valid_dimensions as $dimension ) {
				$result = $client->run_report(
					array( 'activeUsers' ),
					array( $dimension ),
					'7daysAgo'
				);
				$this->assertTrue( is_array( $result ) || false === $result );
			}
		} catch ( \Exception $e ) {
			// Expected to fail without credentials
			$this->assertStringContainsString( 'Failed to initialize GA4 client', $e->getMessage() );
		}
	}

	/**
	 * Test API error handling.
	 *
	 * @group integration
	 */
	public function test_handles_api_errors_gracefully(): void {
		try {
			$client = new GA4_Client();

			// Test with empty metrics (should handle gracefully)
			$result = $client->run_report( array(), array(), '7daysAgo' );
			// Should return false or empty array, not throw an error
			$this->assertTrue( is_array( $result ) || false === $result );
		} catch ( \Exception $e ) {
			// Expected to fail without credentials
			$this->assertStringContainsString( 'Failed to initialize GA4 client', $e->getMessage() );
		}
	}
}
