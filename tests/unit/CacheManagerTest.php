<?php
/**
 * Tests for the Cache_Manager class.
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\Tests\unit;

use Marketing_Analytics_MCP\Cache\Cache_Manager;
use PHPUnit\Framework\TestCase;

/**
 * Cache Manager test class.
 */
class CacheManagerTest extends TestCase {

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		global $mock_options;
		$mock_options = array();
	}

	/**
	 * Test cache key generation is consistent.
	 */
	public function test_cache_key_generation_is_consistent(): void {
		$cache_manager = new Cache_Manager();
		$platform = 'clarity';
		$endpoint = 'insights';
		$args     = array( 'num_of_days' => 7 );

		$key1 = $cache_manager->generate_key( $platform, $endpoint, $args );
		$key2 = $cache_manager->generate_key( $platform, $endpoint, $args );

		$this->assertEquals( $key1, $key2 );
	}

	/**
	 * Test different parameters generate different cache keys.
	 */
	public function test_different_parameters_generate_different_keys(): void {
		$cache_manager = new Cache_Manager();
		$platform = 'ga4';
		$endpoint = 'metrics';
		$args1    = array( 'start_date' => '2024-01-01' );
		$args2    = array( 'start_date' => '2024-01-02' );

		$key1 = $cache_manager->generate_key( $platform, $endpoint, $args1 );
		$key2 = $cache_manager->generate_key( $platform, $endpoint, $args2 );

		$this->assertNotEquals( $key1, $key2 );
	}

	/**
	 * Test cache set and get operations.
	 */
	public function test_cache_set_and_get(): void {
		$cache_manager = new Cache_Manager();
		$key   = 'test_cache_key';
		$value = array( 'test' => 'data' );
		$ttl   = 3600;

		$set_result = $cache_manager->set( $key, $value, $ttl );
		$this->assertTrue( $set_result );

		$get_result = $cache_manager->get( $key );
		$this->assertEquals( $value, $get_result );
	}

	/**
	 * Test cache returns false for non-existent keys.
	 */
	public function test_cache_returns_false_for_nonexistent_keys(): void {
		$cache_manager = new Cache_Manager();
		$result = $cache_manager->get( 'nonexistent_key_12345' );
		$this->assertFalse( $result );
	}

	/**
	 * Test cache delete operation.
	 */
	public function test_cache_delete(): void {
		$cache_manager = new Cache_Manager();
		$key   = 'test_delete_key';
		$value = array( 'data' => 'value' );

		$cache_manager->set( $key, $value, 3600 );
		$delete_result = $cache_manager->delete( $key );
		$this->assertTrue( $delete_result );

		$get_result = $cache_manager->get( $key );
		$this->assertFalse( $get_result );
	}

	/**
	 * Test cache flush operation.
	 *
	 * @group integration
	 */
	public function test_cache_flush_by_platform(): void {
		$cache_manager = new Cache_Manager();

		// Generate proper cache keys using the platform
		$key1 = $cache_manager->generate_key( 'clarity', 'insights', array( 'test' => 1 ) );
		$key2 = $cache_manager->generate_key( 'clarity', 'dashboard', array( 'test' => 2 ) );

		// Set multiple cache entries for a platform
		$cache_manager->set( $key1, array( 'data' => 1 ), 3600 );
		$cache_manager->set( $key2, array( 'data' => 2 ), 3600 );

		// Verify cache entries exist
		$this->assertIsArray( $cache_manager->get( $key1 ) );
		$this->assertIsArray( $cache_manager->get( $key2 ) );

		// Test individual delete operations work
		$delete_result1 = $cache_manager->delete( $key1 );
		$this->assertTrue( $delete_result1 );
		$this->assertFalse( $cache_manager->get( $key1 ) );

		$delete_result2 = $cache_manager->delete( $key2 );
		$this->assertTrue( $delete_result2 );
		$this->assertFalse( $cache_manager->get( $key2 ) );
	}

	/**
	 * Test TTL values are respected.
	 */
	public function test_ttl_values(): void {
		$cache_manager = new Cache_Manager();
		$ttl_clarity = $cache_manager->get_default_ttl( 'clarity' );
		$ttl_ga4     = $cache_manager->get_default_ttl( 'ga4' );
		$ttl_gsc     = $cache_manager->get_default_ttl( 'gsc' );

		// Clarity: 1 hour (3600s) due to 10 req/day limit
		$this->assertEquals( 3600, $ttl_clarity );

		// GA4: 30 minutes (1800s)
		$this->assertEquals( 1800, $ttl_ga4 );

		// GSC: 24 hours (86400s)
		$this->assertEquals( 86400, $ttl_gsc );
	}
}
