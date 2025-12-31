<?php
/**
 * Permission Manager
 *
 * Manages plugin access permissions based on WordPress roles.
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\Utils;

/**
 * Permission Manager Class
 *
 * Handles role-based access control for the plugin using WordPress capabilities.
 */
class Permission_Manager {

	/**
	 * Option key for storing allowed roles
	 *
	 * @var string
	 */
	private static $option_key = 'marketing_analytics_mcp_allowed_roles';

	/**
	 * Custom capability name
	 *
	 * @var string
	 */
	private static $capability = 'access_marketing_analytics';

	/**
	 * Check if current user can access plugin
	 *
	 * @param int|null $user_id User ID (default: current user).
	 * @return bool True if user has access, false otherwise.
	 */
	public static function can_access_plugin( $user_id = null ) {
		if ( $user_id === null ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return false;
		}

		return user_can( $user_id, self::$capability );
	}

	/**
	 * Get allowed roles
	 *
	 * Returns array of role slugs that are allowed to access the plugin.
	 *
	 * @return array Array of role slugs.
	 */
	public static function get_allowed_roles() {
		$roles = get_option( self::$option_key, array() );

		// Default to administrator if not configured
		if ( empty( $roles ) || ! is_array( $roles ) ) {
			return array( 'administrator' );
		}

		return $roles;
	}

	/**
	 * Set allowed roles
	 *
	 * Saves the allowed roles and syncs capabilities across all WordPress roles.
	 *
	 * @param array $roles Array of role slugs.
	 * @return bool True on success, false on failure.
	 */
	public static function set_allowed_roles( $roles ) {
		if ( ! is_array( $roles ) || empty( $roles ) ) {
			$roles = array( 'administrator' );
		}

		// Validate roles exist in WordPress
		$valid_roles = array_keys( wp_roles()->roles );
		$roles       = array_intersect( $roles, $valid_roles );

		// Fallback to administrator if no valid roles
		if ( empty( $roles ) ) {
			$roles = array( 'administrator' );
		}

		$result = update_option( self::$option_key, $roles );

		// Re-sync capabilities after saving
		if ( $result ) {
			self::sync_capabilities();
		}

		return $result;
	}

	/**
	 * Get available WordPress roles
	 *
	 * Returns all WordPress roles that can be selected for plugin access.
	 *
	 * @return array Array of roles [slug => name].
	 */
	public static function get_available_roles() {
		$wp_roles = wp_roles();
		$roles    = array();

		foreach ( $wp_roles->roles as $slug => $role ) {
			$roles[ $slug ] = $role['name'];
		}

		return $roles;
	}

	/**
	 * Register custom capability to allowed roles
	 *
	 * Called during plugin activation to set up capabilities.
	 */
	public static function register_capabilities() {
		$allowed_roles = self::get_allowed_roles();

		foreach ( $allowed_roles as $role_slug ) {
			$role = get_role( $role_slug );
			if ( $role && ! $role->has_cap( self::$capability ) ) {
				$role->add_cap( self::$capability );
			}
		}
	}

	/**
	 * Remove custom capability from all roles
	 *
	 * Called during plugin deactivation to clean up capabilities.
	 */
	public static function remove_capabilities() {
		$wp_roles = wp_roles();

		foreach ( array_keys( $wp_roles->roles ) as $role_slug ) {
			$role = get_role( $role_slug );
			if ( $role && $role->has_cap( self::$capability ) ) {
				$role->remove_cap( self::$capability );
			}
		}
	}

	/**
	 * Sync capabilities across all roles
	 *
	 * Adds capability to allowed roles and removes from disallowed roles.
	 * Called automatically when allowed roles are updated.
	 *
	 * @access private
	 */
	private static function sync_capabilities() {
		$allowed_roles = self::get_allowed_roles();
		$wp_roles      = wp_roles();

		foreach ( array_keys( $wp_roles->roles ) as $role_slug ) {
			$role = get_role( $role_slug );
			if ( ! $role ) {
				continue;
			}

			if ( in_array( $role_slug, $allowed_roles, true ) ) {
				// Add capability if not present
				if ( ! $role->has_cap( self::$capability ) ) {
					$role->add_cap( self::$capability );
				}
			} else {
				// Remove capability if present
				if ( $role->has_cap( self::$capability ) ) {
					$role->remove_cap( self::$capability );
				}
			}
		}
	}
}
