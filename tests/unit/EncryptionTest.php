<?php
/**
 * Tests for the Encryption class.
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\Tests\unit;

use Marketing_Analytics_MCP\Credentials\Encryption;
use PHPUnit\Framework\TestCase;

/**
 * Encryption test class.
 */
class EncryptionTest extends TestCase {

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		global $mock_options;
		$mock_options = array();
	}

	/**
	 * Test that encryption works and returns a string.
	 */
	public function test_encrypt_returns_string(): void {
		if ( ! extension_loaded( 'sodium' ) ) {
			$this->markTestSkipped( 'Sodium extension not available.' );
		}

		$credentials = array(
			'api_key' => 'test_key_123',
			'secret'  => 'test_secret_456',
		);

		$encrypted = Encryption::encrypt( $credentials, 'test_platform' );

		$this->assertIsString( $encrypted );
		$this->assertNotEmpty( $encrypted );
	}

	/**
	 * Test that encryption and decryption are reversible.
	 */
	public function test_encrypt_decrypt_reversible(): void {
		if ( ! extension_loaded( 'sodium' ) ) {
			$this->markTestSkipped( 'Sodium extension not available.' );
		}

		$credentials = array(
			'api_key' => 'test_key_123',
			'secret'  => 'test_secret_456',
		);

		$encrypted  = Encryption::encrypt( $credentials, 'test_platform' );
		$decrypted  = Encryption::decrypt( $encrypted, 'test_platform' );

		$this->assertEquals( $credentials, $decrypted );
	}

	/**
	 * Test that invalid encrypted data returns false.
	 */
	public function test_decrypt_invalid_data_returns_false(): void {
		if ( ! extension_loaded( 'sodium' ) ) {
			$this->markTestSkipped( 'Sodium extension not available.' );
		}

		$result = Encryption::decrypt( 'invalid_encrypted_data', 'test_platform' );

		$this->assertFalse( $result );
	}

	/**
	 * Test save and get credentials.
	 */
	public function test_save_and_get_credentials(): void {
		if ( ! extension_loaded( 'sodium' ) ) {
			$this->markTestSkipped( 'Sodium extension not available.' );
		}

		$credentials = array(
			'client_id'     => 'test_client_id',
			'client_secret' => 'test_client_secret',
		);

		$saved = Encryption::save_credentials( 'test_platform', $credentials );
		$this->assertTrue( $saved );

		$retrieved = Encryption::get_credentials( 'test_platform' );
		$this->assertEquals( $credentials, $retrieved );
	}

	/**
	 * Test get credentials returns false for non-existent platform.
	 */
	public function test_get_credentials_returns_false_for_nonexistent(): void {
		$result = Encryption::get_credentials( 'nonexistent_platform' );
		$this->assertFalse( $result );
	}

	/**
	 * Test delete credentials.
	 */
	public function test_delete_credentials(): void {
		if ( ! extension_loaded( 'sodium' ) ) {
			$this->markTestSkipped( 'Sodium extension not available.' );
		}

		$credentials = array( 'key' => 'value' );
		Encryption::save_credentials( 'delete_test', $credentials );

		$deleted = Encryption::delete_credentials( 'delete_test' );
		$this->assertTrue( $deleted );

		$result = Encryption::get_credentials( 'delete_test' );
		$this->assertFalse( $result );
	}
}
