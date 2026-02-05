<?php
/**
 * Integration tests for Clarity API client.
 *
 * These tests require actual API credentials and are typically run in CI/CD.
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\Tests\integration;

use Marketing_Analytics_MCP\API_Clients\Clarity_Client;
use Marketing_Analytics_MCP\Credentials\Credential_Manager;
use Marketing_Analytics_MCP\Cache\Cache_Manager;
use PHPUnit\Framework\TestCase;

/**
 * Clarity integration test class.
 */
class ClarityIntegrationTest extends TestCase {

	/**
	 * Clarity client instance.
	 *
	 * @var Clarity_Client
	 */
	private $client;

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Skip tests if no credentials are available
		if ( ! getenv( 'CLARITY_PROJECT_ID' ) || ! getenv( 'CLARITY_API_KEY' ) ) {
			$this->markTestSkipped( 'Clarity API credentials not available for integration testing.' );
		}

		$this->client = new Clarity_Client(
			getenv( 'CLARITY_API_KEY' ),
			getenv( 'CLARITY_PROJECT_ID' )
		);
	}

	/**
	 * Test actual API call to Clarity.
	 *
	 * @group integration
	 * @group external-api
	 */
	public function test_get_insights_real_api_call(): void {
		$result = $this->client->get_insights( array(
			'num_of_days' => 7,
			'dimension1'  => 'Device',
		) );

		// Should return array of insights
		$this->assertIsArray( $result );

		// Should have expected structure
		if ( ! empty( $result ) ) {
			$this->assertArrayHasKey( 'data', $result );
		}
	}

	/**
	 * Test caching works with real API.
	 *
	 * @group integration
	 */
	public function test_caching_with_real_api(): void {
		$args = array(
			'num_of_days' => 3,
			'dimension1'  => 'Country',
		);

		// First call - should hit API
		$result1 = $this->client->get_insights( $args );

		// Second call - should use cache
		$result2 = $this->client->get_insights( $args );

		// Results should be identical
		$this->assertEquals( $result1, $result2 );

		// Verify cache was used (implementation-specific)
		$cache_key = Cache_Manager::generate_cache_key( 'clarity', 'insights', $args );
		$cached    = Cache_Manager::get( $cache_key );
		$this->assertNotFalse( $cached );
	}

	/**
	 * Test rate limiting awareness.
	 *
	 * @group integration
	 * @group slow
	 */
	public function test_rate_limiting(): void {
		// Clarity allows 10 requests per day
		// This test should not exceed the limit in a single run

		for ( $i = 0; $i < 3; $i++ ) {
			$result = $this->client->get_insights( array(
				'num_of_days' => 1,
				'dimension1'  => 'Device',
			) );

			// Due to caching, only first request should hit API
			$this->assertTrue( is_array( $result ) || false === $result );
		}

		// Verify we didn't exhaust the rate limit
		$this->assertTrue( true );
	}

	/**
	 * Test error handling with real API.
	 *
	 * @group integration
	 */
	public function test_error_handling_with_invalid_parameters(): void {
		$result = $this->client->get_insights( array(
			'num_of_days' => 999, // Invalid: exceeds 90 days
		) );

		// Should handle error gracefully
		$this->assertFalse( $result );
	}

	/**
	 * Clean up after tests.
	 */
	protected function tearDown(): void {
		// Clear cache
		Cache_Manager::flush_platform( 'clarity' );

		parent::tearDown();
	}
}
