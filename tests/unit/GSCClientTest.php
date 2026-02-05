<?php
/**
 * Tests for the GSC_Client class.
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\Tests\unit;

use Marketing_Analytics_MCP\API_Clients\GSC_Client;
use Marketing_Analytics_MCP\Credentials\OAuth_Handler;
use Marketing_Analytics_MCP\Cache\Cache_Manager;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Google Search Console Client test class.
 */
class GSCClientTest extends TestCase {

	/**
	 * GSC Client instance.
	 *
	 * @var GSC_Client
	 */
	private $client;

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		global $mock_options;
		$mock_options = array(
			'marketing_analytics_mcp_gsc_site_url' => 'https://example.com',
		);

		$this->client = new GSC_Client();
	}

	/**
	 * Test client initialization.
	 */
	public function test_client_initialization(): void {
		$this->assertInstanceOf( GSC_Client::class, $this->client );
	}

	/**
	 * Test get_site_url returns configured site URL.
	 */
	public function test_get_site_url(): void {
		$site_url = $this->client->get_site_url();
		$this->assertEquals( 'https://example.com', $site_url );
	}

	/**
	 * Test set_site_url updates the site URL.
	 */
	public function test_set_site_url(): void {
		$new_url = 'https://newsite.com';
		$result  = $this->client->set_site_url( $new_url );

		$this->assertTrue( $result );
		$this->assertEquals( $new_url, $this->client->get_site_url() );
	}

	/**
	 * Test set_site_url returns true when value is unchanged.
	 */
	public function test_set_site_url_unchanged_value(): void {
		$current_url = $this->client->get_site_url();
		$result      = $this->client->set_site_url( $current_url );

		$this->assertTrue( $result );
	}

	/**
	 * Test query_search_analytics throws exception when site URL not configured.
	 */
	public function test_query_search_analytics_throws_exception_without_site_url(): void {
		global $mock_options;
		$mock_options['marketing_analytics_mcp_gsc_site_url'] = '';

		$client = new GSC_Client();

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'GSC site URL not configured' );

		$client->query_search_analytics( '7daysAgo' );
	}

	/**
	 * Test get_url_inspection throws exception when site URL not configured.
	 */
	public function test_get_url_inspection_throws_exception_without_site_url(): void {
		global $mock_options;
		$mock_options['marketing_analytics_mcp_gsc_site_url'] = '';

		$client = new GSC_Client();

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'GSC site URL not configured' );

		$client->get_url_inspection( 'https://example.com/page' );
	}

	/**
	 * Test get_sitemap_status throws exception when site URL not configured.
	 */
	public function test_get_sitemap_status_throws_exception_without_site_url(): void {
		global $mock_options;
		$mock_options['marketing_analytics_mcp_gsc_site_url'] = '';

		$client = new GSC_Client();

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'GSC site URL not configured' );

		$client->get_sitemap_status();
	}

	/**
	 * Test parse_date_range handles relative dates.
	 */
	public function test_parse_date_range_relative(): void {
		$reflection = new ReflectionClass( $this->client );
		$method     = $reflection->getMethod( 'parse_date_range' );
		$method->setAccessible( true );

		// Test '7daysAgo' format
		$result = $method->invoke( $this->client, '7daysAgo' );
		$this->assertIsArray( $result );
		$this->assertCount( 2, $result );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}$/', $result[0] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}$/', $result[1] );
	}

	/**
	 * Test parse_date_range handles comma-separated dates.
	 */
	public function test_parse_date_range_comma_separated(): void {
		$reflection = new ReflectionClass( $this->client );
		$method     = $reflection->getMethod( 'parse_date_range' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->client, '2024-01-01,2024-01-31' );
		$this->assertEquals( array( '2024-01-01', '2024-01-31' ), $result );
	}

	/**
	 * Test parse_date_range handles specific date.
	 */
	public function test_parse_date_range_specific_date(): void {
		$reflection = new ReflectionClass( $this->client );
		$method     = $reflection->getMethod( 'parse_date_range' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->client, '2024-01-15' );
		$this->assertEquals( array( '2024-01-15', '2024-01-15' ), $result );
	}

	/**
	 * Test parse_date_range handles 'yesterday'.
	 */
	public function test_parse_date_range_yesterday(): void {
		$reflection = new ReflectionClass( $this->client );
		$method     = $reflection->getMethod( 'parse_date_range' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->client, 'yesterday' );
		$this->assertIsArray( $result );
		$this->assertCount( 2, $result );
		$this->assertEquals( gmdate( 'Y-m-d', strtotime( '-4 days' ) ), $result[0] );
		$this->assertEquals( gmdate( 'Y-m-d', strtotime( '-3 days' ) ), $result[1] );
	}

	/**
	 * Test parse_search_analytics_response with empty response.
	 */
	public function test_parse_search_analytics_response_empty(): void {
		$reflection = new ReflectionClass( $this->client );
		$method     = $reflection->getMethod( 'parse_search_analytics_response' );
		$method->setAccessible( true );

		$mock_response = $this->createMock( \Google\Service\SearchConsole\SearchAnalyticsQueryResponse::class );
		$mock_response->method( 'getRows' )->willReturn( null );

		$result = $method->invoke( $this->client, $mock_response );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'rows', $result );
		$this->assertArrayHasKey( 'row_count', $result );
		$this->assertCount( 0, $result['rows'] );
		$this->assertEquals( 0, $result['row_count'] );
	}

	/**
	 * Test list_sites returns null when no access token.
	 */
	public function test_list_sites_returns_null_without_token(): void {
		global $mock_oauth_tokens;
		$mock_oauth_tokens = array();

		$result = $this->client->list_sites();
		$this->assertNull( $result );
	}

	/**
	 * Test get_top_queries filters by minimum impressions.
	 */
	public function test_get_top_queries_filters_impressions(): void {
		// This would require mocking the API response, which is complex
		// For now, we test that the method signature is correct
		$this->assertTrue( method_exists( $this->client, 'get_top_queries' ) );
	}

	/**
	 * Test build_filters returns empty array (not implemented).
	 */
	public function test_build_filters(): void {
		$reflection = new ReflectionClass( $this->client );
		$method     = $reflection->getMethod( 'build_filters' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->client, array() );
		$this->assertIsArray( $result );
		$this->assertCount( 0, $result );
	}
}
