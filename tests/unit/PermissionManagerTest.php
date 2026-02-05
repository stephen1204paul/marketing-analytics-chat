<?php
/**
 * Tests for the Permission_Manager class.
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\Tests\unit;

use Marketing_Analytics_MCP\Utils\Permission_Manager;
use PHPUnit\Framework\TestCase;

/**
 * Permission Manager test class.
 */
class PermissionManagerTest extends TestCase {

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		global $mock_options;
		$mock_options = array();
	}

	/**
	 * Test get_available_roles method.
	 */
	public function test_get_available_roles(): void {
		$roles = Permission_Manager::get_available_roles();

		$this->assertIsArray( $roles );
		$this->assertArrayHasKey( 'administrator', $roles );
	}

	/**
	 * Test get_allowed_roles and set_allowed_roles.
	 */
	public function test_allowed_roles(): void {
		// Default should be administrator
		$allowed = Permission_Manager::get_allowed_roles();
		$this->assertIsArray( $allowed );
		$this->assertContains( 'administrator', $allowed );

		// Set new allowed roles
		$new_roles = array( 'administrator', 'editor' );
		$result    = Permission_Manager::set_allowed_roles( $new_roles );
		$this->assertTrue( $result );

		// Verify they were saved
		$allowed = Permission_Manager::get_allowed_roles();
		$this->assertCount( 2, $allowed );
		$this->assertContains( 'administrator', $allowed );
		$this->assertContains( 'editor', $allowed );
	}

	/**
	 * Test can_access_plugin method.
	 */
	public function test_can_access_plugin(): void {
		// Test with user ID
		$result = Permission_Manager::can_access_plugin( 1 );

		// Without WordPress environment, this will use mock functions
		// Just ensure the method exists and returns boolean
		$this->assertIsBool( $result );
	}

	/**
	 * Test that invalid roles are filtered out.
	 */
	public function test_invalid_roles_filtered(): void {
		// Try to set invalid roles
		$invalid_roles = array( 'administrator', 'invalid_role_xyz' );
		Permission_Manager::set_allowed_roles( $invalid_roles );

		// Should only keep valid roles
		$allowed = Permission_Manager::get_allowed_roles();
		$this->assertContains( 'administrator', $allowed );
		$this->assertNotContains( 'invalid_role_xyz', $allowed );
	}

	/**
	 * Test that empty roles default to administrator.
	 */
	public function test_empty_roles_default_to_admin(): void {
		// Try to set empty array
		Permission_Manager::set_allowed_roles( array() );

		// Should default to administrator
		$allowed = Permission_Manager::get_allowed_roles();
		$this->assertContains( 'administrator', $allowed );
	}
}
