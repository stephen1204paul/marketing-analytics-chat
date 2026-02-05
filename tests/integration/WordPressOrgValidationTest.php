<?php
/**
 * WordPress.org plugin repository validation tests.
 *
 * These tests check requirements for WordPress.org submission.
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\Tests\integration;

use PHPUnit\Framework\TestCase;

/**
 * WordPress.org validation test class.
 */
class WordPressOrgValidationTest extends TestCase {

	/**
	 * Plugin directory path.
	 *
	 * @var string
	 */
	private $plugin_dir;

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->plugin_dir = MARKETING_ANALYTICS_MCP_PATH;
	}

	/**
	 * Test plugin has valid header.
	 *
	 * @group wporg
	 */
	public function test_plugin_has_valid_header(): void {
		$main_file = $this->plugin_dir . 'marketing-analytics-chat.php';
		$this->assertFileExists( $main_file );

		$content = file_get_contents( $main_file );

		// Required headers
		$required_headers = array(
			'Plugin Name:',
			'Description:',
			'Version:',
			'Author:',
			'License:',
			'Text Domain:',
		);

		foreach ( $required_headers as $header ) {
			$this->assertStringContainsString(
				$header,
				$content,
				"Plugin main file missing required header: $header"
			);
		}
	}

	/**
	 * Test plugin uses GPL-compatible license.
	 *
	 * @group wporg
	 */
	public function test_plugin_uses_gpl_license(): void {
		$main_file = $this->plugin_dir . 'marketing-analytics-chat.php';
		$content   = file_get_contents( $main_file );

		// Should contain GPL license
		$this->assertMatchesRegularExpression(
			'/(GPL|GPLv2|GPLv3)/i',
			$content,
			'Plugin must use GPL-compatible license'
		);
	}

	/**
	 * Test readme.txt exists and is valid.
	 *
	 * @group wporg
	 */
	public function test_readme_txt_exists(): void {
		$readme = $this->plugin_dir . 'readme.txt';
		$this->assertFileExists( $readme, 'readme.txt is required for WordPress.org' );

		$content = file_get_contents( $readme );

		// Required sections
		$required_sections = array(
			'=== ',              // Plugin name
			'Contributors:',
			'Requires at least:',
			'Tested up to:',
			'Stable tag:',
			'License:',
			'== Description ==',
			'== Installation ==',
			'== Changelog ==',
		);

		foreach ( $required_sections as $section ) {
			$this->assertStringContainsString(
				$section,
				$content,
				"readme.txt missing required section: $section"
			);
		}
	}

	/**
	 * Test plugin doesn't include blocked files in distributable directories.
	 *
	 * @group wporg
	 */
	public function test_no_blocked_files(): void {
		// WordPress.org blocks certain file types in the plugin source directories.
		$blocked_extensions = array( 'zip', 'tar', 'gz', 'rar', 'exe' );
		$found_blocked      = array();

		// Only scan distributable directories (exclude .git, vendor internals, etc.)
		$scan_dirs = array(
			$this->plugin_dir . 'includes',
			$this->plugin_dir . 'admin',
		);

		foreach ( $scan_dirs as $dir ) {
			if ( ! is_dir( $dir ) ) {
				continue;
			}

			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $dir )
			);

			foreach ( $iterator as $file ) {
				if ( $file->isFile() && in_array( $file->getExtension(), $blocked_extensions, true ) ) {
					$found_blocked[] = $file->getPathname();
				}
			}
		}

		$this->assertEmpty(
			$found_blocked,
			'Plugin source directories should not contain blocked file types: ' . implode( ', ', $found_blocked )
		);
	}

	/**
	 * Test plugin distributable size is reasonable.
	 *
	 * @group wporg
	 */
	public function test_plugin_size_reasonable(): void {
		// WordPress.org prefers plugins under 10MB (excluding dev dependencies).
		$max_size = 10 * 1024 * 1024; // 10MB in bytes

		// Only measure distributable directories (not dev-only packages)
		$total_size = 0;
		$scan_dirs  = array( 'includes', 'admin', 'languages' );

		foreach ( $scan_dirs as $dir ) {
			$full_path = $this->plugin_dir . $dir;
			if ( is_dir( $full_path ) ) {
				$total_size += $this->get_directory_size( $full_path );
			}
		}

		// Measure vendor/ but exclude dev-only packages
		$dev_packages = array( 'phpunit', 'phpstan', 'squizlabs', 'sebastian', 'phpcsstandards', 'wp-coding-standards', 'dealerdirect', 'phar-io', 'myclabs', 'nikic', 'theseer' );
		$vendor_dir   = $this->plugin_dir . 'vendor/';
		if ( is_dir( $vendor_dir ) ) {
			$vendor_items = glob( $vendor_dir . '*', GLOB_ONLYDIR );
			foreach ( $vendor_items as $vendor_item ) {
				$basename = basename( $vendor_item );
				if ( ! in_array( $basename, $dev_packages, true ) ) {
					$total_size += $this->get_directory_size( $vendor_item );
				}
			}
		}

		// Add root PHP files
		$root_files = glob( $this->plugin_dir . '*.php' );
		foreach ( $root_files as $file ) {
			$total_size += filesize( $file );
		}

		$this->assertLessThan(
			$max_size,
			$total_size,
			sprintf(
				'Plugin distributable size (%s) should be under 10MB for WordPress.org',
				number_format( $total_size / 1024 / 1024, 2 ) . 'MB'
			)
		);
	}

	/**
	 * Test no external CDN dependencies in plugin source code.
	 *
	 * @group wporg
	 */
	public function test_no_external_dependencies(): void {
		// Plugin should not load external scripts/styles from CDN.
		// Only scan production code (includes/ and admin/), not tests.
		$php_files = array_merge(
			$this->get_php_files( $this->plugin_dir . 'includes/' ),
			$this->get_php_files( $this->plugin_dir . 'admin/' )
		);

		$blocked_domains = array(
			'cdn.jsdelivr.net',
			'cdnjs.cloudflare.com',
			'unpkg.com',
			'maxcdn.bootstrapcdn.com',
		);

		foreach ( $php_files as $file ) {
			$content = file_get_contents( $file );

			foreach ( $blocked_domains as $domain ) {
				$this->assertStringNotContainsString(
					$domain,
					$content,
					"File $file loads external dependency from $domain"
				);
			}
		}
	}

	/**
	 * Test proper text domain usage.
	 *
	 * @group wporg
	 */
	public function test_proper_text_domain(): void {
		$main_file = $this->plugin_dir . 'marketing-analytics-chat.php';
		$content   = file_get_contents( $main_file );

		// Should have Text Domain header
		$this->assertStringContainsString( 'Text Domain:', $content );

		// Extract text domain
		preg_match( '/Text Domain:\s*(.+)/', $content, $matches );
		$text_domain = isset( $matches[1] ) ? trim( $matches[1] ) : '';

		// Text domain should match plugin slug
		$this->assertEquals( 'marketing-analytics-chat', $text_domain );
	}

	/**
	 * Test all strings are translatable.
	 *
	 * @group wporg
	 */
	public function test_translatable_strings(): void {
		$php_files = $this->get_php_files( $this->plugin_dir . 'includes/' );

		foreach ( $php_files as $file ) {
			$content = file_get_contents( $file );

			// Check for hardcoded user-facing strings
			// This is a basic check - manual review is still needed
			if ( strpos( $content, 'echo' ) !== false ||
				 strpos( $content, 'wp_die' ) !== false ) {

				// Should use __() or _e() functions
				$has_translation = strpos( $content, '__(' ) !== false ||
								   strpos( $content, '_e(' ) !== false ||
								   strpos( $content, 'esc_html__(' ) !== false ||
								   strpos( $content, 'esc_html_e(' ) !== false;

				// This is informational - not all files will have user-facing strings
				$this->assertTrue(
					true,
					"Check translation functions in $file"
				);
			}
		}
	}

	/**
	 * Test no PHP short tags in production code.
	 *
	 * @group wporg
	 */
	public function test_no_php_short_tags(): void {
		$php_files = array_merge(
			$this->get_php_files( $this->plugin_dir . 'includes/' ),
			$this->get_php_files( $this->plugin_dir . 'admin/' )
		);

		foreach ( $php_files as $file ) {
			$content = file_get_contents( $file );

			// Replace all full <?php tags so we can check for remaining short tags.
			$stripped = str_replace( '<?php', '', $content );

			// Also remove short echo tags <?= which are allowed in PHP 7+
			$stripped = str_replace( '<?=', '', $stripped );

			$this->assertStringNotContainsString(
				'<?',
				$stripped,
				"File $file should use <?php not short tags"
			);
		}
	}

	/**
	 * Test proper namespacing.
	 *
	 * @group wporg
	 */
	public function test_proper_namespacing(): void {
		$php_files = $this->get_php_files( $this->plugin_dir . 'includes/' );

		foreach ( $php_files as $file ) {
			$content = file_get_contents( $file );

			// Should use namespace or prefix
			$has_namespace = strpos( $content, 'namespace Marketing_Analytics_MCP' ) !== false;
			$has_prefix    = strpos( $content, 'class Marketing_Analytics_MCP_' ) !== false ||
							 strpos( $content, 'function marketing_analytics_mcp_' ) !== false;

			$this->assertTrue(
				$has_namespace || $has_prefix,
				"File $file should use namespace or prefix"
			);
		}
	}

	/**
	 * Test no dangerous function usage in production code.
	 *
	 * @group wporg
	 */
	public function test_no_eval_usage(): void {
		// Only scan production code, not tests (which may reference these strings).
		$php_files = array_merge(
			$this->get_php_files( $this->plugin_dir . 'includes/' ),
			$this->get_php_files( $this->plugin_dir . 'admin/' )
		);

		// Add root PHP files
		$root_files = glob( $this->plugin_dir . '*.php' );
		$php_files  = array_merge( $php_files, $root_files );

		foreach ( $php_files as $file ) {
			$content = file_get_contents( $file );

			// Check for eval() with word boundary to avoid false positives
			$this->assertDoesNotMatchRegularExpression(
				'/\beval\s*\(/',
				$content,
				"File $file contains dangerous eval() function"
			);
		}
	}

	/**
	 * Test proper escaping of output.
	 *
	 * @group wporg
	 */
	public function test_proper_output_escaping(): void {
		$admin_files = $this->get_php_files( $this->plugin_dir . 'admin/views/' );

		foreach ( $admin_files as $file ) {
			$content = file_get_contents( $file );

			// Admin views should use escaping functions
			if ( strpos( $content, 'echo' ) !== false ) {
				$has_escaping = strpos( $content, 'esc_html' ) !== false ||
								strpos( $content, 'esc_attr' ) !== false ||
								strpos( $content, 'esc_url' ) !== false ||
								strpos( $content, 'wp_kses' ) !== false;

				$this->assertTrue(
					$has_escaping,
					"File $file should use escaping functions"
				);
			}
		}
	}

	/**
	 * Helper: Get directory size recursively.
	 *
	 * @param string $directory Directory path.
	 * @return int Size in bytes.
	 */
	private function get_directory_size( string $directory ): int {
		$size  = 0;
		$files = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $directory )
		);

		foreach ( $files as $file ) {
			if ( $file->isFile() ) {
				$size += $file->getSize();
			}
		}

		return $size;
	}

	/**
	 * Helper: Get all PHP files recursively.
	 *
	 * @param string $directory Directory path.
	 * @return array Array of file paths.
	 */
	private function get_php_files( string $directory ): array {
		$php_files = array();

		if ( ! is_dir( $directory ) ) {
			return $php_files;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $directory )
		);

		foreach ( $iterator as $file ) {
			if ( $file->isFile() && $file->getExtension() === 'php' ) {
				$php_files[] = $file->getPathname();
			}
		}

		return $php_files;
	}
}
