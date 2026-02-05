<?php
/**
 * PHPUnit bootstrap file for Marketing Analytics Chat plugin tests.
 *
 * @package Marketing_Analytics_MCP
 */

// Define WordPress constants for testing.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

if ( ! defined( 'WPINC' ) ) {
	define( 'WPINC', 'wp-includes' );
}

if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', true );
}

// WordPress database constants
if ( ! defined( 'DB_NAME' ) ) {
	define( 'DB_NAME', 'wordpress_test' );
}

if ( ! defined( 'DB_USER' ) ) {
	define( 'DB_USER', 'root' );
}

if ( ! defined( 'DB_PASSWORD' ) ) {
	define( 'DB_PASSWORD', '' );
}

if ( ! defined( 'DB_HOST' ) ) {
	define( 'DB_HOST', 'localhost' );
}

if ( ! defined( 'DB_CHARSET' ) ) {
	define( 'DB_CHARSET', 'utf8mb4' );
}

if ( ! defined( 'DB_COLLATE' ) ) {
	define( 'DB_COLLATE', '' );
}

// WordPress time constants
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! defined( 'WEEK_IN_SECONDS' ) ) {
	define( 'WEEK_IN_SECONDS', 604800 );
}

if ( ! defined( 'MONTH_IN_SECONDS' ) ) {
	define( 'MONTH_IN_SECONDS', 2592000 );
}

if ( ! defined( 'YEAR_IN_SECONDS' ) ) {
	define( 'YEAR_IN_SECONDS', 31536000 );
}

// Define plugin constants.
define( 'MARKETING_ANALYTICS_MCP_VERSION', '1.0.0' );
define( 'MARKETING_ANALYTICS_MCP_PATH', dirname( __DIR__ ) . '/' );
define( 'MARKETING_ANALYTICS_MCP_URL', 'http://localhost/wp-content/plugins/marketing-analytics-chat/' );
define( 'MARKETING_ANALYTICS_MCP_BASENAME', 'marketing-analytics-chat/marketing-analytics-chat.php' );

// Load Composer autoloader.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Mock WordPress role classes
if ( ! class_exists( 'WP_Role' ) ) {
	/**
	 * Mock WP_Role class.
	 */
	class WP_Role {
		/**
		 * Role name.
		 *
		 * @var string
		 */
		public $name;

		/**
		 * Role capabilities.
		 *
		 * @var array
		 */
		public $capabilities = array();

		/**
		 * Constructor.
		 *
		 * @param string $role Role name.
		 * @param array  $capabilities Role capabilities.
		 */
		public function __construct( $role, $capabilities = array() ) {
			$this->name         = $role;
			$this->capabilities = $capabilities;
		}

		/**
		 * Check if role has capability.
		 *
		 * @param string $cap Capability name.
		 * @return bool
		 */
		public function has_cap( $cap ) {
			return isset( $this->capabilities[ $cap ] ) && $this->capabilities[ $cap ];
		}

		/**
		 * Add capability to role.
		 *
		 * @param string $cap Capability name.
		 * @param bool   $grant Grant capability.
		 */
		public function add_cap( $cap, $grant = true ) {
			$this->capabilities[ $cap ] = $grant;
		}

		/**
		 * Remove capability from role.
		 *
		 * @param string $cap Capability name.
		 */
		public function remove_cap( $cap ) {
			unset( $this->capabilities[ $cap ] );
		}
	}
}

if ( ! class_exists( 'WP_Roles' ) ) {
	/**
	 * Mock WP_Roles class.
	 */
	class WP_Roles {
		/**
		 * List of roles.
		 *
		 * @var array
		 */
		public $roles = array();

		/**
		 * Role objects.
		 *
		 * @var array
		 */
		public $role_objects = array();

		/**
		 * Constructor.
		 */
		public function __construct() {
			// Initialize default WordPress roles
			$this->roles = array(
				'administrator' => array(
					'name'         => 'Administrator',
					'capabilities' => array(
						'manage_options' => true,
					),
				),
				'editor'        => array(
					'name'         => 'Editor',
					'capabilities' => array(
						'edit_posts' => true,
					),
				),
				'author'        => array(
					'name'         => 'Author',
					'capabilities' => array(
						'edit_posts' => true,
					),
				),
				'contributor'   => array(
					'name'         => 'Contributor',
					'capabilities' => array(
						'edit_posts' => true,
					),
				),
				'subscriber'    => array(
					'name'         => 'Subscriber',
					'capabilities' => array(
						'read' => true,
					),
				),
			);

			// Create role objects
			foreach ( $this->roles as $role_slug => $role_data ) {
				$this->role_objects[ $role_slug ] = new WP_Role( $role_slug, $role_data['capabilities'] );
			}
		}
	}
}

// Mock WordPress functions for unit tests.
if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Mock get_option function.
	 *
	 * @param string $option  Option name.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	function get_option( $option, $default = false ) {
		global $mock_options;
		return isset( $mock_options[ $option ] ) ? $mock_options[ $option ] : $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * Mock update_option function.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Option value.
	 * @param bool   $autoload Whether to autoload.
	 * @return bool
	 */
	function update_option( $option, $value, $autoload = null ) {
		global $mock_options;
		$mock_options[ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	/**
	 * Mock add_option function.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Option value.
	 * @param string $deprecated Deprecated.
	 * @param bool   $autoload Whether to autoload.
	 * @return bool
	 */
	function add_option( $option, $value = '', $deprecated = '', $autoload = 'yes' ) {
		global $mock_options;
		if ( isset( $mock_options[ $option ] ) ) {
			return false;
		}
		$mock_options[ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	/**
	 * Mock delete_option function.
	 *
	 * @param string $option Option name.
	 * @return bool
	 */
	function delete_option( $option ) {
		global $mock_options;
		if ( isset( $mock_options[ $option ] ) ) {
			unset( $mock_options[ $option ] );
			return true;
		}
		return false;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * Mock wp_json_encode function.
	 *
	 * @param mixed $data Data to encode.
	 * @param int   $options JSON options.
	 * @param int   $depth Max depth.
	 * @return string|false
	 */
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Mock sanitize_text_field function.
	 *
	 * @param string $str String to sanitize.
	 * @return string
	 */
	function sanitize_text_field( $str ) {
		return trim( strip_tags( $str ) );
	}
}

if ( ! function_exists( 'set_transient' ) ) {
    function set_transient( $transient, $value, $expiration = 0 ) {
        global $mock_transients;
        $mock_transients[ $transient ] = array(
            'value'      => $value,
            'expiration' => time() + $expiration,
        );
        return true;
    }
}

if ( ! function_exists( 'get_transient' ) ) {
    function get_transient( $transient ) {
        global $mock_transients;
        if ( isset( $mock_transients[ $transient ] ) ) {
            $data = $mock_transients[ $transient ];
            if ( $data['expiration'] > time() ) {
                return $data['value'];
            }
        }
        return false;
    }
}

if ( ! function_exists( 'delete_transient' ) ) {
    function delete_transient( $transient ) {
        global $mock_transients;
        unset( $mock_transients[ $transient ] );
        return true;
    }
}

if ( ! function_exists( 'get_bloginfo' ) ) {
    function get_bloginfo( $show = '', $filter = 'raw' ) {
        $info = array(
            'version' => '6.9',
            'name'    => 'Test Blog',
            'url'     => 'https://example.com',
        );
        return isset( $info[ $show ] ) ? $info[ $show ] : '';
    }
}

if ( ! function_exists( 'has_action' ) ) {
    function has_action( $hook_name, $callback = false ) {
        return false;
    }
}

if ( ! function_exists( 'has_filter' ) ) {
    function has_filter( $hook_name, $callback = false ) {
        return false;
    }
}

if ( ! function_exists( 'current_user_can' ) ) {
    function current_user_can( $capability ) {
        return true; // Mock as admin for tests
    }
}

if ( ! function_exists( 'user_can' ) ) {
    function user_can( $user_id, $capability ) {
        return true; // Mock as admin for tests
    }
}

if ( ! function_exists( 'get_current_user_id' ) ) {
    function get_current_user_id() {
        return 1; // Mock admin user
    }
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
    function wp_verify_nonce( $nonce, $action = -1 ) {
        return true; // Mock nonce verification
    }
}

if ( ! function_exists( 'sanitize_email' ) ) {
    function sanitize_email( $email ) {
        return filter_var( $email, FILTER_SANITIZE_EMAIL );
    }
}

if ( ! function_exists( 'wp_die' ) ) {
	/**
	 * Mock wp_die function.
	 *
	 * @param string $message Error message.
	 * @param string $title   Error title.
	 * @param array  $args    Additional arguments.
	 */
	function wp_die( $message = '', $title = '', $args = array() ) {
		if ( is_wp_error( $message ) ) {
			$message = $message->get_error_message();
		}
		throw new \Exception( $message );
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * Check if variable is a WordPress Error.
	 *
	 * @param mixed $thing Variable to check.
	 * @return bool
	 */
	function is_wp_error( $thing ) {
		return ( $thing instanceof \WP_Error );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * Mock esc_html function.
	 *
	 * @param string $text Text to escape.
	 * @return string
	 */
	function esc_html( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * Mock esc_attr function.
	 *
	 * @param string $text Text to escape.
	 * @return string
	 */
	function esc_attr( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	/**
	 * Mock esc_url function.
	 *
	 * @param string $url URL to escape.
	 * @return string
	 */
	function esc_url( $url ) {
		return filter_var( $url, FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Mock __ function (translation).
	 *
	 * @param string $text   Text to translate.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( '_e' ) ) {
	/**
	 * Mock _e function (translation echo).
	 *
	 * @param string $text   Text to translate.
	 * @param string $domain Text domain.
	 */
	function _e( $text, $domain = 'default' ) {
		echo $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * Mock esc_html__ function (translation with escaping).
	 *
	 * @param string $text   Text to translate.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function esc_html__( $text, $domain = 'default' ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	/**
	 * Mock esc_html_e function (translation with escaping, echo).
	 *
	 * @param string $text   Text to translate.
	 * @param string $domain Text domain.
	 */
	function esc_html_e( $text, $domain = 'default' ) {
		echo htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	/**
	 * Mock esc_attr__ function.
	 *
	 * @param string $text   Text to translate.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function esc_attr__( $text, $domain = 'default' ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Mock sanitize_key function.
	 *
	 * @param string $key Key to sanitize.
	 * @return string
	 */
	function sanitize_key( $key ) {
		$key = strtolower( $key );
		$key = preg_replace( '/[^a-z0-9_\-]/', '', $key );
		return $key;
	}
}

if ( ! function_exists( 'absint' ) ) {
	/**
	 * Mock absint function (absolute integer).
	 *
	 * @param mixed $maybeint Value to convert.
	 * @return int
	 */
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Mock apply_filters function.
	 *
	 * @param string $hook_name Hook name.
	 * @param mixed  $value     Value to filter.
	 * @param mixed  ...$args   Additional arguments.
	 * @return mixed
	 */
	function apply_filters( $hook_name, $value, ...$args ) {
		// In tests, just return the value unfiltered
		return $value;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	/**
	 * Mock do_action function.
	 *
	 * @param string $hook_name Hook name.
	 * @param mixed  ...$args   Additional arguments.
	 */
	function do_action( $hook_name, ...$args ) {
		// In tests, do nothing
	}
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	/**
	 * Mock wp_next_scheduled function.
	 *
	 * @param string $hook Hook name.
	 * @param array  $args Hook arguments.
	 * @return false|int
	 */
	function wp_next_scheduled( $hook, $args = array() ) {
		// In tests, return false (no scheduled events)
		return false;
	}
}

if ( ! function_exists( 'wp_schedule_event' ) ) {
	/**
	 * Mock wp_schedule_event function.
	 *
	 * @param int    $timestamp Timestamp.
	 * @param string $recurrence Recurrence.
	 * @param string $hook Hook name.
	 * @param array  $args Hook arguments.
	 * @return bool
	 */
	function wp_schedule_event( $timestamp, $recurrence, $hook, $args = array() ) {
		// In tests, return true (success)
		return true;
	}
}

if ( ! function_exists( 'wp_roles' ) ) {
	/**
	 * Mock wp_roles function.
	 *
	 * @return WP_Roles
	 */
	function wp_roles() {
		static $wp_roles = null;
		if ( null === $wp_roles ) {
			$wp_roles = new WP_Roles();
		}
		return $wp_roles;
	}
}

if ( ! function_exists( 'get_role' ) ) {
	/**
	 * Mock get_role function.
	 *
	 * @param string $role Role name.
	 * @return WP_Role|null
	 */
	function get_role( $role ) {
		$wp_roles = wp_roles();
		if ( isset( $wp_roles->role_objects[ $role ] ) ) {
			return $wp_roles->role_objects[ $role ];
		}
		return null;
	}
}

if ( ! function_exists( 'flush_rewrite_rules' ) ) {
	/**
	 * Mock flush_rewrite_rules function.
	 *
	 * @param bool $hard Whether to flush hard.
	 */
	function flush_rewrite_rules( $hard = true ) {
		// In tests, do nothing
	}
}

if ( ! function_exists( 'dbDelta' ) ) {
	/**
	 * Mock dbDelta function.
	 *
	 * @param string|array $queries SQL queries.
	 * @return array
	 */
	function dbDelta( $queries ) {
		// In tests, return empty array
		return array();
	}
}

// Mock global $wpdb
global $wpdb;
if ( ! isset( $wpdb ) ) {
    $wpdb = new class {
        public $prefix = 'wp_';
        public $options = 'wp_options';

        public function query( $query ) {
            return true;
        }

        public function get_results( $query, $output = OBJECT ) {
            return array();
        }

        public function get_var( $query, $x = 0, $y = 0 ) {
            return null;
        }

        public function prepare( $query, ...$args ) {
            return $query;
        }

        public function get_row( $query, $output = OBJECT, $y = 0 ) {
            return null;
        }

        public function insert( $table, $data, $format = null ) {
            return true;
        }

        public function update( $table, $data, $where, $format = null, $where_format = null ) {
            return true;
        }

        public function delete( $table, $where, $where_format = null ) {
            return true;
        }

        public function esc_like( $text ) {
            return addcslashes( $text, '_%\\' );
        }

        public function get_charset_collate() {
            return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        }
    };
}

// Initialize mock transients array
$mock_transients = array();

// Mock WordPress object cache functions
if ( ! function_exists( 'wp_cache_get' ) ) {
	function wp_cache_get( $key, $group = '', $force = false, &$found = null ) {
		global $mock_cache;
		$cache_key = $group . ':' . $key;
		if ( isset( $mock_cache[ $cache_key ] ) ) {
			$found = true;
			return $mock_cache[ $cache_key ];
		}
		$found = false;
		return false;
	}
}

if ( ! function_exists( 'wp_cache_set' ) ) {
	function wp_cache_set( $key, $data, $group = '', $expire = 0 ) {
		global $mock_cache;
		$mock_cache[ $group . ':' . $key ] = $data;
		return true;
	}
}

if ( ! function_exists( 'wp_cache_delete' ) ) {
	function wp_cache_delete( $key, $group = '' ) {
		global $mock_cache;
		unset( $mock_cache[ $group . ':' . $key ] );
		return true;
	}
}

$mock_cache = array();

if ( ! function_exists( 'esc_sql' ) ) {
	function esc_sql( $data ) {
		if ( is_array( $data ) ) {
			return array_map( 'esc_sql', $data );
		}
		return addslashes( $data );
	}
}