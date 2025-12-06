<?php
/**
 * Plugin Activation Handler
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP;

/**
 * Fired during plugin activation
 */
class Activator {

	/**
	 * Activate the plugin
	 *
	 * - Check WordPress and PHP version requirements
	 * - Create database tables if needed
	 * - Set default options
	 * - Generate encryption key
	 */
	public static function activate() {
		// Check minimum WordPress version
		if ( version_compare( get_bloginfo( 'version' ), '6.9', '<' ) ) {
			wp_die(
				esc_html__( 'Marketing Analytics MCP requires WordPress 6.90 or higher.', 'marketing-analytics-chat' ),
				esc_html__( 'Plugin Activation Error', 'marketing-analytics-chat' ),
				array( 'back_link' => true )
			);
		}

		// Check minimum PHP version
		if ( version_compare( PHP_VERSION, '8.3', '<' ) ) {
			wp_die(
				esc_html__( 'Marketing Analytics MCP requires PHP 8.3 or higher.', 'marketing-analytics-chat' ),
				esc_html__( 'Plugin Activation Error', 'marketing-analytics-chat' ),
				array( 'back_link' => true )
			);
		}

		// Check for required PHP extensions
		$required_extensions = array( 'json', 'curl', 'openssl', 'sodium' );
		$missing_extensions  = array();

		foreach ( $required_extensions as $extension ) {
			if ( ! extension_loaded( $extension ) ) {
				$missing_extensions[] = $extension;
			}
		}

		if ( ! empty( $missing_extensions ) ) {
			wp_die(
				sprintf(
					/* translators: %s: comma-separated list of PHP extensions */
					esc_html__( 'Marketing Analytics MCP requires the following PHP extensions: %s', 'marketing-analytics-chat' ),
					esc_html( implode( ', ', $missing_extensions ) )
				),
				esc_html__( 'Plugin Activation Error', 'marketing-analytics-chat' ),
				array( 'back_link' => true )
			);
		}

		// Create database tables
		self::create_chat_tables();
		self::create_quickwins_tables();

		// Set default options
		self::set_default_options();

		// Generate encryption key if it doesn't exist
		self::generate_encryption_key();

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Set default plugin options
	 */
	private static function set_default_options() {
		$defaults = array(
			'version'           => MARKETING_ANALYTICS_MCP_VERSION,
			'cache_ttl_clarity' => HOUR_IN_SECONDS,
			'cache_ttl_ga4'     => 30 * MINUTE_IN_SECONDS,
			'cache_ttl_gsc'     => DAY_IN_SECONDS,
			'debug_mode'        => false,
			'platforms'         => array(
				'clarity' => array(
					'enabled'   => false,
					'connected' => false,
				),
				'ga4'     => array(
					'enabled'   => false,
					'connected' => false,
				),
				'gsc'     => array(
					'enabled'   => false,
					'connected' => false,
				),
			),
		);

		// Only add if option doesn't exist
		add_option( 'marketing_analytics_mcp_settings', $defaults );
	}

	/**
	 * Generate encryption key for credentials
	 */
	private static function generate_encryption_key() {
		$key_option = 'marketing_analytics_mcp_encryption_key';

		// Only generate if key doesn't exist
		if ( ! get_option( $key_option ) ) {
			$key = base64_encode( random_bytes( SODIUM_CRYPTO_SECRETBOX_KEYBYTES ) );
			add_option( $key_option, $key, '', false ); // Don't autoload
		}
	}

	/**
	 * Create chat database tables
	 */
	private static function create_chat_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Conversations table
		$conversations_table = $wpdb->prefix . 'marketing_analytics_mcp_conversations';
		$conversations_sql   = "CREATE TABLE IF NOT EXISTS {$conversations_table} (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			user_id BIGINT UNSIGNED NOT NULL,
			title VARCHAR(255) DEFAULT 'New Conversation',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			INDEX user_created (user_id, created_at)
		) {$charset_collate};";

		// Messages table (without FOREIGN KEY as dbDelta doesn't handle them well)
		$messages_table = $wpdb->prefix . 'marketing_analytics_mcp_messages';
		$messages_sql   = "CREATE TABLE IF NOT EXISTS {$messages_table} (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			conversation_id BIGINT UNSIGNED NOT NULL,
			role ENUM('user', 'assistant', 'system', 'tool') NOT NULL,
			content LONGTEXT,
			tool_calls LONGTEXT NULL COMMENT 'JSON array of tool calls',
			tool_call_id VARCHAR(100) NULL,
			tool_name VARCHAR(100) NULL,
			metadata LONGTEXT NULL COMMENT 'JSON metadata (model, tokens, etc)',
			created_at DATETIME NOT NULL,
			INDEX conversation_created (conversation_id, created_at)
		) {$charset_collate};";

		// Execute table creation
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $conversations_sql );
		dbDelta( $messages_sql );

		// Add foreign key constraint separately (dbDelta doesn't handle these)
		// Check if foreign key already exists before adding
		$fk_check = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
				WHERE CONSTRAINT_SCHEMA = %s
				AND TABLE_NAME = %s
				AND CONSTRAINT_NAME LIKE %s
				AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
				DB_NAME,
				$messages_table,
				'%conversation_id%'
			)
		);

		if ( $fk_check == 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are safe, defined by plugin. ALTER TABLE cannot use prepare().
			$wpdb->query(
				"ALTER TABLE {$messages_table}
				ADD CONSTRAINT fk_conversation_id
				FOREIGN KEY (conversation_id)
				REFERENCES {$conversations_table}(id)
				ON DELETE CASCADE"
			);
		}

		// Store database version
		update_option( 'marketing_analytics_mcp_db_version', '1.0' );
	}

	/**
	 * Create Quick Wins feature database tables
	 */
	private static function create_quickwins_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Anomalies table
		$anomalies_table = $wpdb->prefix . 'marketing_analytics_anomalies';
		$anomalies_sql   = "CREATE TABLE IF NOT EXISTS {$anomalies_table} (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			platform VARCHAR(50) NOT NULL,
			metric VARCHAR(100) NOT NULL,
			value FLOAT NOT NULL,
			expected FLOAT NOT NULL,
			deviation FLOAT NOT NULL,
			type ENUM('spike', 'drop') NOT NULL,
			severity ENUM('low', 'medium', 'high', 'critical') NOT NULL,
			detected_at DATETIME NOT NULL,
			notified TINYINT(1) DEFAULT 0,
			INDEX platform_detected (platform, detected_at),
			INDEX severity_notified (severity, notified)
		) {$charset_collate};";

		// Network sites table
		$network_sites_table = $wpdb->prefix . 'marketing_analytics_network_sites';
		$network_sites_sql   = "CREATE TABLE IF NOT EXISTS {$network_sites_table} (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			site_url VARCHAR(255) NOT NULL,
			site_name VARCHAR(255) NOT NULL,
			auth_method VARCHAR(50) NOT NULL,
			auth_credentials TEXT NOT NULL,
			api_key VARCHAR(64) DEFAULT NULL,
			capabilities TEXT DEFAULT NULL,
			is_active TINYINT(1) DEFAULT 1,
			created_at DATETIME NOT NULL,
			last_sync DATETIME DEFAULT NULL,
			UNIQUE KEY site_url (site_url),
			INDEX is_active (is_active),
			INDEX api_key (api_key)
		) {$charset_collate};";

		// Execute table creation
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $anomalies_sql );
		dbDelta( $network_sites_sql );
	}
}
