<?php
/**
 * Tests for the Credential_Manager class.
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\Tests\unit;

use Marketing_Analytics_MCP\Credentials\Credential_Manager;
use PHPUnit\Framework\TestCase;

/**
 * Credential Manager test class.
 */
class CredentialManagerTest extends TestCase {

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		global $mock_options;
		$mock_options = array();
	}

	/**
	 * Test saving and retrieving credentials.
	 */
	public function test_save_and_get_credentials(): void {
		$manager = new Credential_Manager();

		$credentials = array(
			'project_id' => 'test_project_123',
			'api_key'    => 'test_api_key_456',
		);

		// Save credentials
		$result = $manager->save_credentials( 'clarity', $credentials );
		$this->assertTrue( $result );

		// Retrieve credentials
		$retrieved = $manager->get_credentials( 'clarity' );
		$this->assertIsArray( $retrieved );
		$this->assertEquals( 'test_project_123', $retrieved['project_id'] );
		$this->assertEquals( 'test_api_key_456', $retrieved['api_key'] );
	}

	/**
	 * Test has_credentials method.
	 */
	public function test_has_credentials(): void {
		$manager = new Credential_Manager();

		// Initially should return false
		$this->assertFalse( $manager->has_credentials( 'clarity' ) );

		// After saving credentials, should return true
		$credentials = array(
			'project_id' => 'test_project',
			'api_key'    => 'test_key',
		);

		$manager->save_credentials( 'clarity', $credentials );
		$this->assertTrue( $manager->has_credentials( 'clarity' ) );
	}

	/**
	 * Test get_all_statuses method.
	 */
	public function test_get_all_statuses(): void {
		$manager = new Credential_Manager();

		// Save credentials for multiple platforms
		$manager->save_credentials( 'clarity', array(
			'project_id' => 'test',
			'api_key'    => 'test',
		) );

		$manager->save_credentials( 'ga4', array(
			'client_id'     => 'test',
			'client_secret' => 'test',
			'refresh_token' => 'test',
		) );

		$statuses = $manager->get_all_statuses();
		$this->assertIsArray( $statuses );
		$this->assertTrue( $statuses['clarity'] );
		$this->assertTrue( $statuses['ga4'] );
		$this->assertFalse( $statuses['gsc'] );
	}

	/**
	 * Test delete credentials.
	 */
	public function test_delete_credentials(): void {
		$manager = new Credential_Manager();

		$credentials = array(
			'project_id' => 'test_project',
			'api_key'    => 'test_key',
		);

		// Save and verify
		$manager->save_credentials( 'clarity', $credentials );
		$this->assertTrue( $manager->has_credentials( 'clarity' ) );

		// Delete and verify
		$result = $manager->delete_credentials( 'clarity' );
		$this->assertTrue( $result );
		$this->assertFalse( $manager->has_credentials( 'clarity' ) );
	}

	/**
	 * Test invalid platform handling.
	 */
	public function test_invalid_platform(): void {
		$manager = new Credential_Manager();

		$credentials = array(
			'some_key' => 'some_value',
		);

		// Should return false for invalid platform
		$result = $manager->save_credentials( 'invalid_platform', $credentials );
		$this->assertFalse( $result );
	}

	/**
	 * Test empty credentials handling.
	 */
	public function test_empty_credentials(): void {
		$manager = new Credential_Manager();

		// Should return false for empty credentials
		$result = $manager->save_credentials( 'clarity', array() );
		$this->assertFalse( $result );

		$result = $manager->save_credentials( 'clarity', null );
		$this->assertFalse( $result );
	}
}
