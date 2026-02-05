<?php
/**
 * Notification Manager
 *
 * @package Marketing_Analytics_MCP
 * @subpackage Notifications
 */

namespace Marketing_Analytics_MCP\Notifications;

use Marketing_Analytics_MCP\Utils\Logger;

/**
 * Class for managing notifications across different platforms
 */
class Notification_Manager {

	/**
	 * Available notification channels
	 *
	 * @var array
	 */
	private $channels = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->register_channels();
		$this->init_schedules();
	}

	/**
	 * Register notification channels
	 */
	private function register_channels() {
		// Register Slack
		if ( class_exists( 'Marketing_Analytics_MCP\\Notifications\\Slack_Notifier' ) ) {
			$this->channels['slack'] = new Slack_Notifier();
		}

		// Register WhatsApp
		if ( class_exists( 'Marketing_Analytics_MCP\\Notifications\\WhatsApp_Notifier' ) ) {
			$this->channels['whatsapp'] = new WhatsApp_Notifier();
		}

		// Allow plugins to register additional channels
		$this->channels = apply_filters( 'marketing_analytics_mcp_notification_channels', $this->channels );
	}

	/**
	 * Initialize scheduled notifications
	 */
	private function init_schedules() {
		// Add custom cron schedules
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );

		// Schedule daily summary
		if ( ! wp_next_scheduled( 'marketing_analytics_daily_summary' ) ) {
			$time      = get_option( 'marketing_analytics_daily_summary_time', '09:00' );
			$timestamp = strtotime( 'today ' . $time );
			if ( $timestamp < time() ) {
				$timestamp = strtotime( 'tomorrow ' . $time );
			}
			wp_schedule_event( $timestamp, 'daily', 'marketing_analytics_daily_summary' );
		}

		// Schedule weekly report
		if ( ! wp_next_scheduled( 'marketing_analytics_weekly_report' ) ) {
			$day       = get_option( 'marketing_analytics_weekly_report_day', 'monday' );
			$time      = get_option( 'marketing_analytics_weekly_report_time', '09:00' );
			$timestamp = strtotime( 'next ' . $day . ' ' . $time );
			wp_schedule_event( $timestamp, 'weekly', 'marketing_analytics_weekly_report' );
		}

		// Hook to scheduled events
		add_action( 'marketing_analytics_daily_summary', array( $this, 'send_daily_summary' ) );
		add_action( 'marketing_analytics_weekly_report', array( $this, 'send_weekly_report' ) );
	}

	/**
	 * Add custom cron schedules
	 *
	 * @param array $schedules Existing schedules.
	 * @return array Modified schedules
	 */
	public function add_cron_schedules( $schedules ) {
		$schedules['weekly'] = array(
			'interval' => 604800,
			'display'  => __( 'Once Weekly', 'marketing-analytics-chat' ),
		);

		$schedules['monthly'] = array(
			'interval' => 2635200,
			'display'  => __( 'Once Monthly', 'marketing-analytics-chat' ),
		);

		return $schedules;
	}

	/**
	 * Send notification to specified channels
	 *
	 * @param string $message  The message to send.
	 * @param array  $channels Channels to send to.
	 * @param array  $options  Additional options.
	 * @return array Results by channel
	 */
	public function send_notification( $message, $channels = array(), $options = array() ) {
		$results = array();

		// Use all enabled channels if none specified
		if ( empty( $channels ) ) {
			$channels = $this->get_enabled_channels();
		}

		foreach ( $channels as $channel_name ) {
			if ( isset( $this->channels[ $channel_name ] ) ) {
				$result                   = $this->channels[ $channel_name ]->send( $message, $options );
				$results[ $channel_name ] = $result;

				// Log notification
				$this->log_notification( $channel_name, $message, $result );
			}
		}

		return $results;
	}

	/**
	 * Get enabled notification channels
	 *
	 * @return array Enabled channel names
	 */
	private function get_enabled_channels() {
		$enabled = array();

		foreach ( $this->channels as $name => $channel ) {
			if ( get_option( "marketing_analytics_{$name}_enabled", false ) ) {
				$enabled[] = $name;
			}
		}

		return $enabled;
	}

	/**
	 * Send daily summary notification
	 */
	public function send_daily_summary() {
		if ( ! get_option( 'marketing_analytics_daily_summary_enabled', false ) ) {
			return;
		}

		// Generate summary
		$summary = $this->generate_daily_summary();

		if ( empty( $summary ) ) {
			return;
		}

		// Send to enabled channels
		$this->send_notification(
			$summary,
			array(),
			array(
				'type'   => 'daily_summary',
				'format' => 'markdown',
			)
		);
	}

	/**
	 * Send weekly report notification
	 */
	public function send_weekly_report() {
		if ( ! get_option( 'marketing_analytics_weekly_report_enabled', false ) ) {
			return;
		}

		// Generate report
		$report = $this->generate_weekly_report();

		if ( empty( $report ) ) {
			return;
		}

		// Send to enabled channels
		$this->send_notification(
			$report,
			array(),
			array(
				'type'        => 'weekly_report',
				'format'      => 'markdown',
				'attachments' => $this->generate_report_attachments(),
			)
		);
	}

	/**
	 * Generate daily summary
	 *
	 * @return string Summary message
	 */
	private function generate_daily_summary() {
		$summary = '📊 *Daily Analytics Summary - ' . current_time( 'F j, Y' ) . "*\n\n";

		// Get data from each platform
		$platforms = array( 'ga4', 'clarity', 'gsc', 'meta', 'dataforseo' );

		foreach ( $platforms as $platform ) {
			$data = $this->get_platform_summary( $platform, 'day' );

			if ( ! empty( $data ) ) {
				$summary .= $this->format_platform_summary( $platform, $data );
				$summary .= "\n";
			}
		}

		// Add anomalies if any
		$anomalies       = get_option( 'marketing_analytics_recent_anomalies', array() );
		$today_anomalies = array_filter(
			$anomalies,
			function ( $a ) {
				return gmdate( 'Y-m-d', strtotime( $a['date'] ) ) === gmdate( 'Y-m-d' );
			}
		);

		if ( ! empty( $today_anomalies ) ) {
			$summary .= "\n⚠️ *Anomalies Detected:*\n";
			foreach ( $today_anomalies as $anomaly ) {
				$summary .= sprintf(
					"• %s %s in %s (%+.1f%%)\n",
					ucfirst( $anomaly['type'] ),
					$anomaly['metric'],
					$anomaly['platform'],
					$anomaly['percentage_change']
				);
			}
		}

		// Add AI insights if enabled
		if ( class_exists( 'Marketing_Analytics_MCP\\AI\\Insights_Generator' ) ) {
			$insights_generator = new \Marketing_Analytics_MCP\AI\Insights_Generator();
			$insights           = $insights_generator->generate_insights(
				array( 'summary' => $summary ),
				'all',
				'general'
			);

			if ( ! is_wp_error( $insights ) && ! empty( $insights['insights'] ) ) {
				$summary .= "\n💡 *AI Insights:*\n";
				foreach ( array_slice( $insights['insights'], 0, 3 ) as $insight ) {
					$summary .= '• ' . $insight['text'] . "\n";
				}
			}
		}

		// Add dashboard link
		$summary .= "\n🔗 " . admin_url( 'admin.php?page=marketing-analytics-chat' );

		return $summary;
	}

	/**
	 * Generate weekly report
	 *
	 * @return string Report message
	 */
	private function generate_weekly_report() {
		$report  = "📈 *Weekly Analytics Report*\n";
		$report .= '*Week of ' . gmdate( 'F j', strtotime( '-7 days' ) ) . ' - ' . current_time( 'F j, Y' ) . "*\n\n";

		// Get week-over-week comparisons
		$platforms = array( 'ga4', 'clarity', 'gsc', 'meta', 'dataforseo' );

		foreach ( $platforms as $platform ) {
			$data       = $this->get_platform_summary( $platform, 'week' );
			$comparison = $this->get_week_over_week_comparison( $platform );

			if ( ! empty( $data ) ) {
				$report .= '*' . strtoupper( $platform ) . " Performance:*\n";
				$report .= $this->format_weekly_data( $data, $comparison );
				$report .= "\n";
			}
		}

		// Top performing content
		$top_content = $this->get_top_content();
		if ( ! empty( $top_content ) ) {
			$report .= "*🏆 Top Performing Content:*\n";
			foreach ( array_slice( $top_content, 0, 5 ) as $content ) {
				$report .= sprintf(
					"• %s - %d views\n",
					$content['title'],
					$content['views']
				);
			}
			$report .= "\n";
		}

		// Improvement opportunities
		$report      .= "*📋 Action Items:*\n";
		$action_items = $this->generate_action_items();
		foreach ( $action_items as $item ) {
			$report .= '• ' . $item . "\n";
		}

		// Add dashboard link
		$report .= "\n🔗 Full Report: " . admin_url( 'admin.php?page=marketing-analytics-chat' );

		return $report;
	}

	/**
	 * Get platform summary data
	 *
	 * @param string $platform The platform.
	 * @param string $period   The period (day, week, month).
	 * @return array Summary data
	 */
	private function get_platform_summary( $platform, $period = 'day' ) {
		// Try to get cached summary
		$cache_key = "marketing_analytics_{$platform}_{$period}_summary";
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		// Get fresh data from API client
		$api_client_class = $this->get_api_client_class( $platform );

		if ( ! class_exists( $api_client_class ) ) {
			return array();
		}

		try {
			$client = new $api_client_class();
			$data   = array();

			switch ( $platform ) {
				case 'ga4':
					$metrics    = array( 'sessions', 'users', 'pageviews', 'conversions' );
					$end_date   = current_time( 'Y-m-d' );
					$start_date = $period === 'day' ? $end_date : gmdate( 'Y-m-d', strtotime( '-7 days' ) );
					$data       = $client->get_metrics( $metrics, array(), $start_date, $end_date );
					break;

				case 'clarity':
					$days = $period === 'day' ? 1 : 7;
					$data = $client->get_insights( $days );
					break;

				case 'gsc':
					$end_date   = gmdate( 'Y-m-d', strtotime( '-3 days' ) ); // GSC has 3-day delay
					$start_date = $period === 'day' ? $end_date : gmdate( 'Y-m-d', strtotime( '-10 days' ) );
					$data       = $client->get_performance_data( $start_date, $end_date );
					break;

				case 'meta':
					$end_date   = current_time( 'Y-m-d' );
					$start_date = $period === 'day' ? $end_date : gmdate( 'Y-m-d', strtotime( '-7 days' ) );
					$data       = $client->get_insights( $start_date, $end_date );
					break;

				case 'dataforseo':
					$data = $client->get_overview_data();
					break;
			}

			// Cache for 30 minutes
			set_transient( $cache_key, $data, 1800 );

			return $data;
		} catch ( \Exception $e ) {
			Logger::debug( 'Failed to get ' . $platform . ' summary: ' . $e->getMessage() );
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
	 * Format platform summary for notification
	 *
	 * @param string $platform The platform.
	 * @param array  $data     The data.
	 * @return string Formatted summary
	 */
	private function format_platform_summary( $platform, $data ) {
		$summary = '*' . strtoupper( $platform ) . "*\n";

		// Extract key metrics based on platform
		switch ( $platform ) {
			case 'ga4':
				if ( isset( $data['rows'][0]['metricValues'] ) ) {
					$metrics  = $data['rows'][0]['metricValues'];
					$summary .= '• Sessions: ' . number_format( $metrics[0]['value'] ?? 0 ) . "\n";
					$summary .= '• Users: ' . number_format( $metrics[1]['value'] ?? 0 ) . "\n";
					$summary .= '• Pageviews: ' . number_format( $metrics[2]['value'] ?? 0 ) . "\n";
				}
				break;

			case 'clarity':
				if ( isset( $data['totalSessions'] ) ) {
					$summary .= '• Sessions: ' . number_format( $data['totalSessions'] ) . "\n";
					$summary .= '• Avg Pages/Session: ' . number_format( $data['pagesPerSession'] ?? 0, 1 ) . "\n";
				}
				break;

			case 'gsc':
				if ( isset( $data['rows'] ) ) {
					$totals   = $this->calculate_totals( $data['rows'] );
					$summary .= '• Clicks: ' . number_format( $totals['clicks'] ?? 0 ) . "\n";
					$summary .= '• Impressions: ' . number_format( $totals['impressions'] ?? 0 ) . "\n";
					$summary .= '• Avg Position: ' . number_format( $totals['position'] ?? 0, 1 ) . "\n";
				}
				break;

			case 'meta':
				if ( isset( $data['data'] ) ) {
					$summary .= '• Reach: ' . number_format( $data['reach'] ?? 0 ) . "\n";
					$summary .= '• Engagement: ' . number_format( $data['engagement'] ?? 0 ) . "\n";
				}
				break;

			case 'dataforseo':
				if ( isset( $data['visibility_score'] ) ) {
					$summary .= '• Visibility: ' . number_format( $data['visibility_score'], 2 ) . "%\n";
					$summary .= '• Keywords: ' . number_format( $data['total_keywords'] ?? 0 ) . "\n";
				}
				break;
		}

		return $summary;
	}

	/**
	 * Calculate totals from rows
	 *
	 * @param array $rows Data rows.
	 * @return array Totals
	 */
	private function calculate_totals( $rows ) {
		$totals = array(
			'clicks'      => 0,
			'impressions' => 0,
			'ctr'         => 0,
			'position'    => 0,
		);

		foreach ( $rows as $row ) {
			$totals['clicks']      += $row['clicks'] ?? 0;
			$totals['impressions'] += $row['impressions'] ?? 0;
		}

		if ( $totals['impressions'] > 0 ) {
			$totals['ctr'] = ( $totals['clicks'] / $totals['impressions'] ) * 100;
		}

		if ( count( $rows ) > 0 ) {
			$total_position     = array_sum( array_column( $rows, 'position' ) );
			$totals['position'] = $total_position / count( $rows );
		}

		return $totals;
	}

	/**
	 * Get week-over-week comparison
	 *
	 * @param string $platform The platform.
	 * @return array Comparison data
	 */
	private function get_week_over_week_comparison( $platform ) {
		// This would compare this week's data with last week's
		// Implementation depends on specific platform APIs
		return array();
	}

	/**
	 * Format weekly data
	 *
	 * @param array $data       The data.
	 * @param array $comparison Week-over-week comparison.
	 * @return string Formatted data
	 */
	private function format_weekly_data( $data, $comparison ) {
		$formatted = '';

		// Format based on data structure
		if ( is_array( $data ) ) {
			foreach ( $data as $key => $value ) {
				if ( is_numeric( $value ) ) {
					$formatted .= '• ' . ucfirst( str_replace( '_', ' ', $key ) ) . ': ';
					$formatted .= number_format( $value );

					// Add comparison if available
					if ( isset( $comparison[ $key ] ) ) {
						$change     = ( ( $value - $comparison[ $key ] ) / $comparison[ $key ] ) * 100;
						$formatted .= sprintf( ' (%+.1f%%)', $change );
					}

					$formatted .= "\n";
				}
			}
		}

		return $formatted;
	}

	/**
	 * Get top performing content
	 *
	 * @return array Top content
	 */
	private function get_top_content() {
		// This would get top pages/posts from GA4 or other sources
		// Placeholder implementation
		return array();
	}

	/**
	 * Generate action items based on data
	 *
	 * @return array Action items
	 */
	private function generate_action_items() {
		$items = array();

		// Check for recent anomalies
		$anomalies = get_option( 'marketing_analytics_recent_anomalies', array() );
		foreach ( array_slice( $anomalies, 0, 3 ) as $anomaly ) {
			if ( $anomaly['type'] === 'drop' && $anomaly['severity'] !== 'low' ) {
				$items[] = sprintf(
					'Investigate %s drop in %s',
					$anomaly['metric'],
					$anomaly['platform']
				);
			}
		}

		// Add generic items if needed
		if ( empty( $items ) ) {
			$items[] = 'Review top performing content for optimization opportunities';
			$items[] = 'Check for any technical SEO issues';
			$items[] = 'Update social media posting schedule based on engagement data';
		}

		return array_slice( $items, 0, 3 );
	}

	/**
	 * Generate report attachments
	 *
	 * @return array Attachment data
	 */
	private function generate_report_attachments() {
		// Could generate charts, export to PDF, etc.
		return array();
	}

	/**
	 * Log notification
	 *
	 * @param string     $channel The channel.
	 * @param string     $message The message.
	 * @param bool|array $result  Send result.
	 */
	private function log_notification( $channel, $message, $result ) {
		$log = get_option( 'marketing_analytics_notification_log', array() );

		array_unshift(
			$log,
			array(
				'channel' => $channel,
				'message' => substr( $message, 0, 100 ) . '...',
				'success' => ! is_wp_error( $result ),
				'sent_at' => current_time( 'mysql' ),
			)
		);

		// Keep only last 100 entries
		$log = array_slice( $log, 0, 100 );

		update_option( 'marketing_analytics_notification_log', $log );
	}

	/**
	 * Get notification history
	 *
	 * @param int $limit Number of entries to retrieve.
	 * @return array Notification history
	 */
	public function get_notification_history( $limit = 20 ) {
		$log = get_option( 'marketing_analytics_notification_log', array() );
		return array_slice( $log, 0, $limit );
	}

	/**
	 * Test notification channel
	 *
	 * @param string $channel Channel to test.
	 * @return bool|WP_Error Test result
	 */
	public function test_channel( $channel ) {
		if ( ! isset( $this->channels[ $channel ] ) ) {
			return new \WP_Error(
				'invalid_channel',
				__( 'Invalid notification channel', 'marketing-analytics-chat' )
			);
		}

		$test_message = sprintf(
			/* translators: %s: current timestamp */
			__( 'Test notification from Marketing Analytics Chat - %s', 'marketing-analytics-chat' ),
			current_time( 'Y-m-d H:i:s' )
		);

		return $this->channels[ $channel ]->send( $test_message, array( 'test' => true ) );
	}
}
