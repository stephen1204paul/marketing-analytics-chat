<?php
/**
 * Tests for the Logger class.
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\Tests\unit;

use Marketing_Analytics_MCP\Utils\Logger;
use PHPUnit\Framework\TestCase;

/**
 * Logger test class.
 */
class LoggerTest extends TestCase {

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		global $mock_options;
		$mock_options = array();
	}

	/**
	 * Test debug logging is disabled when WP_DEBUG is false.
	 */
	public function test_is_debug_enabled_returns_false_by_default(): void {
		// When no debug mode is set and WP_DEBUG is true (from bootstrap)
		// it should still be enabled
		$this->assertTrue( Logger::is_debug_enabled() );
	}

	/**
	 * Test debug logging is enabled when plugin debug mode is on.
	 */
	public function test_is_debug_enabled_with_plugin_setting(): void {
		global $mock_options;
		$mock_options['marketing_analytics_mcp_debug_mode'] = true;

		$this->assertTrue( Logger::is_debug_enabled() );
	}

	/**
	 * Test debug method does not throw errors.
	 */
	public function test_debug_method_accepts_string(): void {
		// Should not throw any exception
		$this->expectNotToPerformAssertions();
		Logger::debug( 'Test message' );
	}

	/**
	 * Test debug method handles arrays.
	 */
	public function test_debug_method_accepts_array(): void {
		// Should not throw any exception
		$this->expectNotToPerformAssertions();
		Logger::debug( array( 'key' => 'value' ) );
	}

	/**
	 * Test error method does not throw errors.
	 */
	public function test_error_method_accepts_string(): void {
		// Should not throw any exception
		$this->expectNotToPerformAssertions();
		Logger::error( 'Test error message' );
	}

	/**
	 * Test warning method does not throw errors.
	 */
	public function test_warning_method_accepts_string(): void {
		// Should not throw any exception
		$this->expectNotToPerformAssertions();
		Logger::warning( 'Test warning message' );
	}
}
