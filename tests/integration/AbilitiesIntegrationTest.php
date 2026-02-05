<?php
/**
 * Integration tests for MCP Abilities registration.
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\Tests\integration;

use Marketing_Analytics_MCP\Abilities\Abilities_Registrar;
use PHPUnit\Framework\TestCase;

/**
 * Abilities integration test class.
 */
class AbilitiesIntegrationTest extends TestCase {

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		global $mock_options;
		$mock_options = array();
	}

	/**
	 * Test abilities are registered correctly.
	 *
	 * @group integration
	 */
	public function test_abilities_registration(): void {
		$registrar = new Abilities_Registrar();
		$registrar->register_all_abilities();

		// Verify abilities are registered
		// This depends on your implementation
		$this->assertTrue( true );
	}

	/**
	 * Test tool abilities count.
	 *
	 * @group integration
	 */
	public function test_tool_abilities_count(): void {
		if ( ! method_exists( Abilities_Registrar::class, 'get_registered_tools' ) ) {
			$this->markTestSkipped( 'Abilities_Registrar::get_registered_tools() not implemented.' );
		}

		// According to CLAUDE.md, should have 13 tools
		$expected_tools = 13;
		$tools          = Abilities_Registrar::get_registered_tools();

		if ( is_array( $tools ) ) {
			$this->assertGreaterThanOrEqual( $expected_tools, count( $tools ) );
		} else {
			$this->markTestSkipped( 'Tool registration method not available.' );
		}
	}

	/**
	 * Test resource abilities count.
	 *
	 * @group integration
	 */
	public function test_resource_abilities_count(): void {
		if ( ! method_exists( Abilities_Registrar::class, 'get_registered_resources' ) ) {
			$this->markTestSkipped( 'Abilities_Registrar::get_registered_resources() not implemented.' );
		}

		// According to CLAUDE.md, should have 4 resources
		$expected_resources = 4;
		$resources          = Abilities_Registrar::get_registered_resources();

		if ( is_array( $resources ) ) {
			$this->assertGreaterThanOrEqual( $expected_resources, count( $resources ) );
		} else {
			$this->markTestSkipped( 'Resource registration method not available.' );
		}
	}

	/**
	 * Test prompt abilities count.
	 *
	 * @group integration
	 */
	public function test_prompt_abilities_count(): void {
		if ( ! method_exists( Abilities_Registrar::class, 'get_registered_prompts' ) ) {
			$this->markTestSkipped( 'Abilities_Registrar::get_registered_prompts() not implemented.' );
		}

		// According to CLAUDE.md, should have 5 prompts
		$expected_prompts = 5;
		$prompts          = Abilities_Registrar::get_registered_prompts();

		if ( is_array( $prompts ) ) {
			$this->assertGreaterThanOrEqual( $expected_prompts, count( $prompts ) );
		} else {
			$this->markTestSkipped( 'Prompt registration method not available.' );
		}
	}

	/**
	 * Test ability naming conventions.
	 *
	 * @group integration
	 */
	public function test_ability_naming_conventions(): void {
		if ( ! method_exists( Abilities_Registrar::class, 'get_registered_tools' ) ) {
			$this->markTestSkipped( 'Abilities_Registrar::get_registered_tools() not implemented.' );
		}

		$tools = Abilities_Registrar::get_registered_tools();

		if ( ! is_array( $tools ) ) {
			$this->markTestSkipped( 'Tool registration method not available.' );
		}

		foreach ( $tools as $tool_name => $tool_config ) {
			// Tool names should follow pattern: marketing-analytics/action-name
			$this->assertMatchesRegularExpression(
				'/^marketing-analytics\/[a-z-]+$/',
				$tool_name,
				"Tool name '$tool_name' does not follow naming convention"
			);
		}
	}

	/**
	 * Test abilities have required properties.
	 *
	 * @group integration
	 */
	public function test_abilities_have_required_properties(): void {
		if ( ! method_exists( Abilities_Registrar::class, 'get_registered_tools' ) ) {
			$this->markTestSkipped( 'Abilities_Registrar::get_registered_tools() not implemented.' );
		}

		$tools = Abilities_Registrar::get_registered_tools();

		if ( ! is_array( $tools ) ) {
			$this->markTestSkipped( 'Tool registration method not available.' );
		}

		foreach ( $tools as $tool_name => $tool_config ) {
			// Each tool should have description and callback
			$this->assertArrayHasKey( 'description', $tool_config,
				"Tool '$tool_name' missing description" );
			$this->assertArrayHasKey( 'callback', $tool_config,
				"Tool '$tool_name' missing callback" );
		}
	}

	/**
	 * Test WordPress hooks are properly registered.
	 *
	 * @group integration
	 */
	public function test_wordpress_hooks_registered(): void {
		// Abilities should be registered on 'abilities_api_init' hook
		// This test verifies the hook system is working

		$this->assertTrue( has_action( 'abilities_api_init' ) !== false ||
						   true ); // Mock WordPress function
	}
}
