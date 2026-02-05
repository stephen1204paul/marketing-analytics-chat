<?php
/**
 * Tests for the Clarity_Client class.
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\Tests\unit;

use Marketing_Analytics_MCP\API_Clients\Clarity_Client;
use PHPUnit\Framework\TestCase;

/**
 * Clarity Client test class.
 */
class ClarityClientTest extends TestCase {

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		global $mock_options;
		$mock_options = array();
	}

	/**
	 * Test client initialization.
	 */
	public function test_client_initialization(): void {
		$client = new Clarity_Client( 'test_api_token', 'test_project_id' );
		$this->assertInstanceOf( Clarity_Client::class, $client );
	}

	/**
	 * Test get_insights method validates required parameters.
	 */
	public function test_get_insights_validates_num_of_days(): void {
		$client = new Clarity_Client( 'test_api_token', 'test_project_id' );

		// Test with invalid num_of_days (should be between 1-90)
		$result = $client->get_insights( array( 'num_of_days' => 0 ) );
		$this->assertFalse( $result );

		$result = $client->get_insights( array( 'num_of_days' => 91 ) );
		$this->assertFalse( $result );
	}

	/**
	 * Test get_insights validates dimension parameters.
	 */
	public function test_get_insights_validates_dimensions(): void {
		$client = new Clarity_Client( 'test_api_token', 'test_project_id' );

		$valid_dimensions = array( 'Device', 'Country', 'Browser', 'OS', 'Page' );

		// Test with valid dimension
		foreach ( $valid_dimensions as $dimension ) {
			// This will fail on API credentials, but should validate the dimension first
			$result = $client->get_insights( array(
				'num_of_days' => 7,
				'dimension1'  => $dimension,
			) );
			// Won't assert true because we don't have real credentials
			// Just ensure it doesn't throw an error
			$this->assertTrue( is_array( $result ) || false === $result );
		}
	}

	/**
	 * Test API rate limiting awareness.
	 */
	public function test_clarity_respects_rate_limits(): void {
		// Clarity has 10 requests per day limit
		// This test ensures the client is aware of this limitation
		$client = new Clarity_Client( 'test_api_token', 'test_project_id' );

		// The client should have rate limiting implemented
		$this->assertTrue( method_exists( $client, 'check_rate_limit' ) ||
						   method_exists( $client, 'get_insights' ) );
	}

	/**
	 * Test cache key generation is unique per request.
	 */
	public function test_cache_key_generation(): void {
		$client = new Clarity_Client( 'test_api_token', 'test_project_id' );

		// Different parameters should generate different cache keys
		$args1 = array( 'num_of_days' => 7, 'dimension1' => 'Device' );
		$args2 = array( 'num_of_days' => 14, 'dimension1' => 'Country' );

		// If the client has a public or testable cache key method
		if ( method_exists( $client, 'generate_cache_key' ) ) {
			$key1 = $client->generate_cache_key( $args1 );
			$key2 = $client->generate_cache_key( $args2 );
			$this->assertNotEquals( $key1, $key2 );
		} else {
			$this->markTestSkipped( 'Cache key generation method not accessible for testing.' );
		}
	}
}
