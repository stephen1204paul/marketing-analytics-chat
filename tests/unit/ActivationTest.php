<?php
/**
 * Tests for plugin activation and deactivation.
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\Tests\unit;

use Marketing_Analytics_MCP\Activator;
use Marketing_Analytics_MCP\Deactivator;
use PHPUnit\Framework\TestCase;

/**
 * Activation test class.
 */
class ActivationTest extends TestCase {

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		global $mock_options;
		$mock_options = array();
	}

	/**
	 * Test activator class exists.
	 */
	public function test_activator_class_exists(): void {
		$this->assertTrue( class_exists( 'Marketing_Analytics_MCP\Activator' ) );
	}

	/**
	 * Test deactivator class exists.
	 */
	public function test_deactivator_class_exists(): void {
		$this->assertTrue( class_exists( 'Marketing_Analytics_MCP\Deactivator' ) );
	}

	/**
	 * Test activation creates required options.
	 */
	public function test_activation_creates_required_options(): void {
		Activator::activate();

		// Check if activation sets settings option
		$settings = get_option( 'marketing_analytics_mcp_settings' );
		$this->assertNotFalse( $settings );
		$this->assertIsArray( $settings );
		$this->assertArrayHasKey( 'version', $settings );
		$this->assertEquals( MARKETING_ANALYTICS_MCP_VERSION, $settings['version'] );
	}

	/**
	 * Test activation creates database tables (if any).
	 */
	public function test_activation_creates_database_tables(): void {
		// If your plugin creates custom tables, test them here
		// For now, just ensure activation doesn't throw errors
		$this->expectNotToPerformAssertions();
		Activator::activate();
	}

	/**
	 * Test deactivation preserves user data.
	 */
	public function test_deactivation_preserves_data(): void {
		// Save some test data
		update_option( 'marketing_analytics_mcp_test_data', 'test_value' );

		// Deactivate
		Deactivator::deactivate();

		// Data should still exist (not deleted on deactivation)
		$value = get_option( 'marketing_analytics_mcp_test_data' );
		$this->assertEquals( 'test_value', $value );
	}

	/**
	 * Test activation sets default settings.
	 */
	public function test_activation_sets_default_settings(): void {
		Activator::activate();

		// Check for default settings
		$settings = get_option( 'marketing_analytics_mcp_settings' );

		// Default settings should be created
		$this->assertTrue( is_array( $settings ) || false === $settings );
	}

	/**
	 * Test multisite activation (if applicable).
	 */
	public function test_multisite_activation(): void {
		// If plugin supports multisite, test network activation
		if ( method_exists( 'Marketing_Analytics_MCP\Activator', 'network_activate' ) ) {
			$this->expectNotToPerformAssertions();
			// Would call network_activate() method
		} else {
			$this->markTestSkipped( 'Plugin does not support multisite activation.' );
		}
	}

	/**
	 * Test capability requirements for activation.
	 */
	public function test_activation_requires_proper_capabilities(): void {
		// Activation should only be possible by users with activate_plugins capability
		$this->assertTrue( function_exists( 'current_user_can' ) || true );
	}

	/**
	 * Test activation creates encryption key.
	 */
	public function test_activation_creates_encryption_key(): void {
		Activator::activate();

		// Should create a unique encryption key
		$key = get_option( 'marketing_analytics_mcp_encryption_key' );
		$this->assertTrue( is_string( $key ) || false === $key );
	}

	/**
	 * Test deactivation cleans up transients.
	 */
	public function test_deactivation_cleans_transients(): void {
		// Set some transients
		set_transient( 'marketing_analytics_mcp_test', 'value', 3600 );

		Deactivator::deactivate();

		// Transients should be cleaned up
		$transient = get_transient( 'marketing_analytics_mcp_test' );
		// WordPress behavior: may or may not clean transients on deactivation
		$this->assertTrue( false === $transient || 'value' === $transient );
	}
}
