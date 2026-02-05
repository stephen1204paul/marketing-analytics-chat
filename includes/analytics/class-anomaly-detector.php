<?php
/**
 * Anomaly Detection System
 *
 * @package Marketing_Analytics_MCP
 * @subpackage Analytics
 */

namespace Marketing_Analytics_MCP\Analytics;

use Marketing_Analytics_MCP\AI\Insights_Generator;
use Marketing_Analytics_MCP\Utils\Logger;

/**
 * Class for detecting anomalies in marketing analytics data
 */
class Anomaly_Detector {

	/**
	 * Number of days for rolling average
	 *
	 * @var int
	 */
	private const ROLLING_DAYS = 7;

	/**
	 * Default sensitivity (standard deviations)
	 *
	 * @var float
	 */
	private const DEFAULT_SENSITIVITY = 2.0;

	/**
	 * Database table name for anomalies
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'marketing_analytics_anomalies';
	}

	/**
	 * Initialize anomaly detection
	 */
	public function init() {
		// Schedule daily anomaly check
		if ( ! wp_next_scheduled( 'marketing_analytics_check_anomalies' ) ) {
			wp_schedule_event( time(), 'daily', 'marketing_analytics_check_anomalies' );
		}

		add_action( 'marketing_analytics_check_anomalies', array( $this, 'run_daily_check' ) );
	}

	/**
	 * Run daily anomaly check
	 */
	public function run_daily_check() {
		if ( ! get_option( 'marketing_analytics_mcp_anomaly_detection_enabled', false ) ) {
			return;
		}

		// Check each platform
		$platforms = array( 'clarity', 'ga4', 'gsc', 'meta', 'dataforseo' );

		foreach ( $platforms as $platform ) {
			if ( get_option( "marketing_analytics_mcp_anomaly_{$platform}_enabled", true ) ) {
				$this->check_platform_anomalies( $platform );
			}
		}
	}

	/**
	 * Check for anomalies in a specific platform
	 *
	 * @param string $platform The platform to check.
	 */
	private function check_platform_anomalies( $platform ) {
		// Get recent data for the platform
		$data = $this->get_platform_data( $platform );

		if ( empty( $data ) ) {
			return;
		}

		// Analyze each metric
		$metrics = $this->get_platform_metrics( $platform );

		foreach ( $metrics as $metric ) {
			$anomalies = $this->detect_metric_anomaly( $data, $metric, $platform );

			if ( ! empty( $anomalies ) ) {
				foreach ( $anomalies as $anomaly ) {
					$this->record_anomaly( $anomaly, $platform, $metric );
					$this->send_anomaly_notification( $anomaly, $platform, $metric );
				}
			}
		}
	}

	/**
	 * Get platform data for analysis
	 *
	 * @param string $platform The platform.
	 * @return array Platform data
	 */
	private function get_platform_data( $platform ) {
		// Get data from the appropriate API client
		$api_client_class = $this->get_api_client_class( $platform );

		if ( ! class_exists( $api_client_class ) ) {
			return array();
		}

		try {
			$client = new $api_client_class();

			// Get last 14 days of data for rolling average calculation
			$end_date   = current_time( 'Y-m-d' );
			$start_date = gmdate( 'Y-m-d', strtotime( '-14 days' ) );

			switch ( $platform ) {
				case 'ga4':
					return $client->get_metrics(
						array( 'sessions', 'pageviews', 'users', 'conversions' ),
						array(),
						$start_date,
						$end_date
					);

				case 'clarity':
					return $client->get_insights( 14 );

				case 'gsc':
					return $client->get_performance_data(
						$start_date,
						$end_date,
						array()
					);

				case 'meta':
					return $client->get_insights( $start_date, $end_date );

				case 'dataforseo':
					return $client->get_serp_data( '', 100 );

				default:
					return array();
			}
		} catch ( \Exception $e ) {
			Logger::debug( 'Anomaly detection error for ' . $platform . ': ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Get API client class name
	 *
	 * @param string $platform The platform.
	 * @return string Class name
	 */
	private function get_api_client_class( $platform ) {
		$namespace = 'Marketing_Analytics_MCP\\API_Clients\\';

		switch ( $platform ) {
			case 'ga4':
				return $namespace . 'GA4_Client';
			case 'clarity':
				return $namespace . 'Clarity_Client';
			case 'gsc':
				return $namespace . 'Search_Console_Client';
			case 'meta':
				return $namespace . 'Meta_Client';
			case 'dataforseo':
				return $namespace . 'DataForSEO_Client';
			default:
				return '';
		}
	}

	/**
	 * Get metrics to track for a platform
	 *
	 * @param string $platform The platform.
	 * @return array Metrics to track
	 */
	private function get_platform_metrics( $platform ) {
		switch ( $platform ) {
			case 'ga4':
				return array( 'sessions', 'pageviews', 'users', 'bounce_rate', 'conversion_rate' );

			case 'clarity':
				return array( 'sessions', 'pages_per_session', 'scroll_depth', 'engagement_time' );

			case 'gsc':
				return array( 'clicks', 'impressions', 'ctr', 'average_position' );

			case 'meta':
				return array( 'reach', 'impressions', 'engagement_rate', 'clicks', 'conversions' );

			case 'dataforseo':
				return array( 'rankings', 'visibility_score', 'featured_snippets', 'rich_results' );

			default:
				return array();
		}
	}

	/**
	 * Detect anomalies in a metric
	 *
	 * @param array  $data     The data to analyze.
	 * @param string $metric   The metric to check.
	 * @param string $platform The platform.
	 * @return array Detected anomalies
	 */
	private function detect_metric_anomaly( $data, $metric, $platform ) {
		if ( empty( $data ) ) {
			return array();
		}

		// Extract metric values from data
		$values = $this->extract_metric_values( $data, $metric );

		if ( count( $values ) < self::ROLLING_DAYS + 1 ) {
			return array(); // Not enough data
		}

		// Calculate rolling statistics
		$anomalies   = array();
		$sensitivity = get_option( 'marketing_analytics_mcp_anomaly_sensitivity', self::DEFAULT_SENSITIVITY );

		// Check the most recent value against historical data
		$recent_value = end( $values );
		$historical   = array_slice( $values, -( self::ROLLING_DAYS + 1 ), self::ROLLING_DAYS );

		$mean   = $this->calculate_mean( $historical );
		$stddev = $this->calculate_stddev( $historical, $mean );

		if ( $stddev === 0 ) {
			return array(); // No variation in data
		}

		$z_score = ( $recent_value - $mean ) / $stddev;

		// Check if value is an anomaly
		if ( abs( $z_score ) > $sensitivity ) {
			$anomalies[] = array(
				'value'             => $recent_value,
				'expected'          => $mean,
				'deviation'         => $z_score,
				'type'              => $z_score > 0 ? 'spike' : 'drop',
				'severity'          => $this->calculate_severity( abs( $z_score ) ),
				'date'              => current_time( 'Y-m-d' ),
				'metric'            => $metric,
				'platform'          => $platform,
				'percentage_change' => round( ( ( $recent_value - $mean ) / $mean ) * 100, 2 ),
			);
		}

		return $anomalies;
	}

	/**
	 * Extract metric values from data
	 *
	 * @param array  $data   The data.
	 * @param string $metric The metric.
	 * @return array Metric values
	 */
	private function extract_metric_values( $data, $metric ) {
		$values = array();

		if ( isset( $data['rows'] ) ) {
			// GA4/GSC format
			foreach ( $data['rows'] as $row ) {
				if ( isset( $row['metricValues'] ) ) {
					foreach ( $row['metricValues'] as $metric_value ) {
						if ( isset( $metric_value['name'] ) && $metric_value['name'] === $metric ) {
							$values[] = floatval( $metric_value['value'] );
						}
					}
				} elseif ( isset( $row[ $metric ] ) ) {
					$values[] = floatval( $row[ $metric ] );
				}
			}
		} elseif ( isset( $data[ $metric ] ) ) {
			// Direct metric array
			if ( is_array( $data[ $metric ] ) ) {
				$values = array_map( 'floatval', $data[ $metric ] );
			} else {
				$values[] = floatval( $data[ $metric ] );
			}
		}

		return $values;
	}

	/**
	 * Calculate mean of values
	 *
	 * @param array $values The values.
	 * @return float Mean value
	 */
	private function calculate_mean( $values ) {
		if ( empty( $values ) ) {
			return 0;
		}
		return array_sum( $values ) / count( $values );
	}

	/**
	 * Calculate standard deviation
	 *
	 * @param array $values The values.
	 * @param float $mean   Pre-calculated mean.
	 * @return float Standard deviation
	 */
	private function calculate_stddev( $values, $mean ) {
		if ( count( $values ) < 2 ) {
			return 0;
		}

		$variance = 0;
		foreach ( $values as $value ) {
			$variance += pow( $value - $mean, 2 );
		}
		$variance = $variance / ( count( $values ) - 1 );

		return sqrt( $variance );
	}

	/**
	 * Calculate severity of anomaly
	 *
	 * @param float $z_score Absolute z-score.
	 * @return string Severity level
	 */
	private function calculate_severity( $z_score ) {
		if ( $z_score > 4 ) {
			return 'critical';
		} elseif ( $z_score > 3 ) {
			return 'high';
		} elseif ( $z_score > 2 ) {
			return 'medium';
		} else {
			return 'low';
		}
	}

	/**
	 * Record anomaly in database
	 *
	 * @param array  $anomaly  The anomaly data.
	 * @param string $platform The platform.
	 * @param string $metric   The metric.
	 */
	private function record_anomaly( $anomaly, $platform, $metric ) {
		global $wpdb;

		$wpdb->insert(
			$this->table_name,
			array(
				'platform'    => $platform,
				'metric'      => $metric,
				'value'       => $anomaly['value'],
				'expected'    => $anomaly['expected'],
				'deviation'   => $anomaly['deviation'],
				'type'        => $anomaly['type'],
				'severity'    => $anomaly['severity'],
				'detected_at' => current_time( 'mysql' ),
				'notified'    => 0,
			),
			array( '%s', '%s', '%f', '%f', '%f', '%s', '%s', '%s', '%d' )
		);

		// Store in option for quick access
		$recent_anomalies = get_option( 'marketing_analytics_recent_anomalies', array() );
		array_unshift( $recent_anomalies, $anomaly );
		$recent_anomalies = array_slice( $recent_anomalies, 0, 10 ); // Keep only last 10
		update_option( 'marketing_analytics_recent_anomalies', $recent_anomalies );
	}

	/**
	 * Send anomaly notification
	 *
	 * @param array  $anomaly  The anomaly data.
	 * @param string $platform The platform.
	 * @param string $metric   The metric.
	 */
	private function send_anomaly_notification( $anomaly, $platform, $metric ) {
		// Only notify for medium+ severity
		if ( in_array( $anomaly['severity'], array( 'low' ), true ) ) {
			return;
		}

		// Check if email notifications are enabled
		if ( get_option( 'marketing_analytics_mcp_anomaly_email_enabled', true ) ) {
			$this->send_email_notification( $anomaly, $platform, $metric );
		}

		// Trigger action for other notification methods
		do_action( 'marketing_analytics_mcp_anomaly_detected', $anomaly, $platform, $metric );
	}

	/**
	 * Send email notification
	 *
	 * @param array  $anomaly  The anomaly data.
	 * @param string $platform The platform.
	 * @param string $metric   The metric.
	 */
	private function send_email_notification( $anomaly, $platform, $metric ) {
		$to = get_option( 'admin_email' );
		$subject = sprintf(
			/* translators: 1: severity level, 2: anomaly type (spike/drop), 3: platform name */
			__( '[%1$s Alert] %2$s anomaly detected in %3$s', 'marketing-analytics-chat' ),
			$anomaly['severity'],
			ucfirst( $anomaly['type'] ),
			$platform
		);

		$message = sprintf(
			/* translators: %s: platform name */
			__( "An anomaly has been detected in your %s analytics:\n\n", 'marketing-analytics-chat' ),
			$platform
		);

		$message .= sprintf(
			/* translators: %s: metric name */
			__( "Metric: %s\n", 'marketing-analytics-chat' ),
			$metric
		);

		$message .= sprintf(
			/* translators: %s: current metric value */
			__( "Current Value: %s\n", 'marketing-analytics-chat' ),
			number_format( $anomaly['value'], 2 )
		);

		$message .= sprintf(
			/* translators: %s: expected metric value */
			__( "Expected Value: %s\n", 'marketing-analytics-chat' ),
			number_format( $anomaly['expected'], 2 )
		);

		$message .= sprintf(
			/* translators: %s: percentage change with sign */
			__( "Change: %s%%\n", 'marketing-analytics-chat' ),
			$anomaly['percentage_change'] > 0 ? '+' . $anomaly['percentage_change'] : $anomaly['percentage_change']
		);

		$message .= sprintf(
			/* translators: %s: severity level */
			__( "Severity: %s\n\n", 'marketing-analytics-chat' ),
			ucfirst( $anomaly['severity'] )
		);

		$message .= sprintf(
			/* translators: %s: URL to view anomaly details */
			__( "View details: %s\n", 'marketing-analytics-chat' ),
			admin_url( 'admin.php?page=marketing-analytics-chat-anomalies' )
		);

		// Generate AI insights if enabled
		if ( class_exists( 'Marketing_Analytics_MCP\AI\Insights_Generator' ) ) {
			$insights_generator = new Insights_Generator();
			$insights           = $insights_generator->generate_insights( $anomaly, $platform, 'anomaly' );

			if ( ! is_wp_error( $insights ) && ! empty( $insights['insights'] ) ) {
				$message .= "\n" . __( 'AI Analysis:', 'marketing-analytics-chat' ) . "\n";
				foreach ( $insights['insights'] as $insight ) {
					$message .= '- ' . $insight['text'] . "\n";
				}
			}
		}

		wp_mail( $to, $subject, $message );
	}

	/**
	 * Get anomaly history
	 *
	 * @param int    $limit    Number of anomalies to retrieve.
	 * @param string $platform Optional platform filter.
	 * @param string $severity Optional severity filter.
	 * @return array Anomaly history
	 */
	public function get_anomaly_history( $limit = 50, $platform = '', $severity = '' ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, defined by plugin.
		$query  = "SELECT * FROM {$this->table_name} WHERE 1=1";
		$params = array();

		if ( ! empty( $platform ) ) {
			$query   .= ' AND platform = %s';
			$params[] = $platform;
		}

		if ( ! empty( $severity ) ) {
			$query   .= ' AND severity = %s';
			$params[] = $severity;
		}

		$query   .= ' ORDER BY detected_at DESC LIMIT %d';
		$params[] = $limit;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query contains table name which cannot be parameterized.
		return $wpdb->get_results( $wpdb->prepare( $query, $params ), ARRAY_A );
	}

	/**
	 * Get anomaly statistics
	 *
	 * @param string $period Period for stats (day, week, month).
	 * @return array Statistics
	 */
	public function get_anomaly_stats( $period = 'week' ) {
		global $wpdb;

		$date_filter = $this->get_date_filter( $period );

		// Get counts by severity
		$severity_stats = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, defined by plugin.
				"SELECT severity, COUNT(*) as count
				FROM {$this->table_name}
				WHERE detected_at > %s
				GROUP BY severity",
				$date_filter
			),
			ARRAY_A
		);

		// Get counts by platform
		$platform_stats = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, defined by plugin.
				"SELECT platform, COUNT(*) as count
				FROM {$this->table_name}
				WHERE detected_at > %s
				GROUP BY platform",
				$date_filter
			),
			ARRAY_A
		);

		// Get counts by type
		$type_stats = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, defined by plugin.
				"SELECT type, COUNT(*) as count
				FROM {$this->table_name}
				WHERE detected_at > %s
				GROUP BY type",
				$date_filter
			),
			ARRAY_A
		);

		// Get total count
		$total = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, defined by plugin.
				"SELECT COUNT(*) FROM {$this->table_name} WHERE detected_at > %s",
				$date_filter
			)
		);

		return array(
			'total'       => intval( $total ),
			'by_severity' => $severity_stats,
			'by_platform' => $platform_stats,
			'by_type'     => $type_stats,
			'period'      => $period,
		);
	}

	/**
	 * Get date filter for period
	 *
	 * @param string $period The period.
	 * @return string Date filter
	 */
	private function get_date_filter( $period ) {
		switch ( $period ) {
			case 'day':
				return gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) );
			case 'week':
				return gmdate( 'Y-m-d H:i:s', strtotime( '-1 week' ) );
			case 'month':
				return gmdate( 'Y-m-d H:i:s', strtotime( '-1 month' ) );
			default:
				return gmdate( 'Y-m-d H:i:s', strtotime( '-1 week' ) );
		}
	}

	/**
	 * Clear old anomaly data
	 *
	 * @param int $days_to_keep Number of days to keep.
	 */
	public function cleanup_old_anomalies( $days_to_keep = 90 ) {
		global $wpdb;

		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days_to_keep} days" ) );

		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, defined by plugin.
				"DELETE FROM {$this->table_name} WHERE detected_at < %s",
				$cutoff_date
			)
		);
	}
}
