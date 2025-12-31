<?php
/**
 * Quick Wins MCP Abilities
 *
 * @package Marketing_Analytics_MCP
 * @subpackage Abilities
 */

namespace Marketing_Analytics_MCP\Abilities;

use Marketing_Analytics_MCP\AI\Insights_Generator;
use Marketing_Analytics_MCP\Analytics\Anomaly_Detector;
use Marketing_Analytics_MCP\Export\Sheets_Exporter;
use Marketing_Analytics_MCP\Notifications\Notification_Manager;
use Marketing_Analytics_MCP\Multisite\Network_Manager;
use Marketing_Analytics_MCP\Utils\Permission_Manager;

/**
 * Class for registering Quick Wins MCP abilities
 */
class QuickWins_Abilities {

	/**
	 * Register all Quick Wins abilities
	 */
	public function register_abilities() {
		// Register AI insights tool
		$this->register_ai_insights_tool();

		// Register anomaly detection tools
		$this->register_anomaly_tools();

		// Register export tools
		$this->register_export_tools();

		// Register notification tools
		$this->register_notification_tools();

		// Register network management tools
		$this->register_network_tools();

		// Register resources
		$this->register_quickwins_resources();
	}

	/**
	 * Register AI insights generation tool
	 */
	private function register_ai_insights_tool() {
		wp_register_ability(
			'marketing-analytics/generate-ai-insights',
			array(
				'label'               => __( 'Generate AI Insights', 'marketing-analytics-chat' ),
				'description'         => __( 'Generate AI-powered insights from analytics data', 'marketing-analytics-chat' ),
				'category'            => 'marketing-analytics',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'platform'      => array(
							'type'        => 'string',
							'description' => __( 'Analytics platform (ga4, clarity, gsc, meta, dataforseo)', 'marketing-analytics-chat' ),
							'enum'        => array( 'ga4', 'clarity', 'gsc', 'meta', 'dataforseo', 'all' ),
						),
						'data'          => array(
							'type'        => 'object',
							'description' => __( 'Analytics data to analyze', 'marketing-analytics-chat' ),
						),
						'analysis_type' => array(
							'type'        => 'string',
							'description' => __( 'Type of analysis (general, traffic, seo, social, anomaly)', 'marketing-analytics-chat' ),
							'enum'        => array( 'general', 'traffic', 'seo', 'social', 'anomaly' ),
							'default'     => 'general',
						),
					),
					'required'   => array( 'platform', 'data' ),
				),
				'execute_callback'    => array( $this, 'handle_generate_ai_insights' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Register anomaly detection tools
	 */
	private function register_anomaly_tools() {
		// Check for anomalies
		wp_register_ability(
			'marketing-analytics/check-anomalies',
			array(
				'label'               => __( 'Check Anomalies', 'marketing-analytics-chat' ),
				'description'         => __( 'Check for anomalies in analytics data', 'marketing-analytics-chat' ),
				'category'            => 'marketing-analytics',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'platform'    => array(
							'type'        => 'string',
							'description' => __( 'Platform to check (or "all" for all platforms)', 'marketing-analytics-chat' ),
							'enum'        => array( 'ga4', 'clarity', 'gsc', 'meta', 'dataforseo', 'all' ),
							'default'     => 'all',
						),
						'sensitivity' => array(
							'type'        => 'number',
							'description' => __( 'Sensitivity level (standard deviations)', 'marketing-analytics-chat' ),
							'minimum'     => 1,
							'maximum'     => 4,
							'default'     => 2,
						),
					),
				),
				'execute_callback'    => array( $this, 'handle_check_anomalies' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// Get anomaly history
		wp_register_ability(
			'marketing-analytics/get-anomaly-history',
			array(
				'label'               => __( 'Get Anomaly History', 'marketing-analytics-chat' ),
				'description'         => __( 'Get history of detected anomalies', 'marketing-analytics-chat' ),
				'category'            => 'marketing-analytics',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'limit'    => array(
							'type'        => 'integer',
							'description' => __( 'Number of anomalies to retrieve', 'marketing-analytics-chat' ),
							'default'     => 20,
						),
						'platform' => array(
							'type'        => 'string',
							'description' => __( 'Filter by platform', 'marketing-analytics-chat' ),
							'enum'        => array( 'ga4', 'clarity', 'gsc', 'meta', 'dataforseo', '' ),
						),
						'severity' => array(
							'type'        => 'string',
							'description' => __( 'Filter by severity', 'marketing-analytics-chat' ),
							'enum'        => array( 'low', 'medium', 'high', 'critical', '' ),
						),
					),
				),
				'execute_callback'    => array( $this, 'handle_get_anomaly_history' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Register export tools
	 */
	private function register_export_tools() {
		// Export to Google Sheets
		wp_register_ability(
			'marketing-analytics/export-to-sheets',
			array(
				'label'               => __( 'Export to Sheets', 'marketing-analytics-chat' ),
				'description'         => __( 'Export analytics data to Google Sheets', 'marketing-analytics-chat' ),
				'category'            => 'marketing-analytics',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'data_source'    => array(
							'type'        => 'string',
							'description' => __( 'Data source platform', 'marketing-analytics-chat' ),
							'enum'        => array( 'ga4', 'clarity', 'gsc', 'meta', 'dataforseo' ),
						),
						'date_range'     => array(
							'type'        => 'object',
							'description' => __( 'Date range for data', 'marketing-analytics-chat' ),
							'properties'  => array(
								'start_date' => array(
									'type'   => 'string',
									'format' => 'date',
								),
								'end_date'   => array(
									'type'   => 'string',
									'format' => 'date',
								),
							),
						),
						'metrics'        => array(
							'type'        => 'array',
							'description' => __( 'Metrics to export', 'marketing-analytics-chat' ),
							'items'       => array(
								'type' => 'string',
							),
						),
						'create_new'     => array(
							'type'        => 'boolean',
							'description' => __( 'Create new spreadsheet or use existing', 'marketing-analytics-chat' ),
							'default'     => true,
						),
						'include_charts' => array(
							'type'        => 'boolean',
							'description' => __( 'Include charts in export', 'marketing-analytics-chat' ),
							'default'     => true,
						),
					),
					'required'   => array( 'data_source' ),
				),
				'execute_callback'    => array( $this, 'handle_export_to_sheets' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Register notification tools
	 */
	private function register_notification_tools() {
		// Send Slack notification
		wp_register_ability(
			'marketing-analytics/send-slack-notification',
			array(
				'label'               => __( 'Send Slack Notification', 'marketing-analytics-chat' ),
				'description'         => __( 'Send notification to Slack', 'marketing-analytics-chat' ),
				'category'            => 'marketing-analytics',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'message'     => array(
							'type'        => 'string',
							'description' => __( 'Message to send', 'marketing-analytics-chat' ),
						),
						'channel'     => array(
							'type'        => 'string',
							'description' => __( 'Slack channel', 'marketing-analytics-chat' ),
							'default'     => '#marketing',
						),
						'attachments' => array(
							'type'        => 'array',
							'description' => __( 'Message attachments', 'marketing-analytics-chat' ),
							'items'       => array(
								'type' => 'object',
							),
						),
					),
					'required'   => array( 'message' ),
				),
				'execute_callback'    => array( $this, 'handle_send_slack_notification' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// Send WhatsApp notification
		wp_register_ability(
			'marketing-analytics/send-whatsapp-notification',
			array(
				'label'               => __( 'Send WhatsApp Notification', 'marketing-analytics-chat' ),
				'description'         => __( 'Send notification via WhatsApp', 'marketing-analytics-chat' ),
				'category'            => 'marketing-analytics',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'message'    => array(
							'type'        => 'string',
							'description' => __( 'Message to send', 'marketing-analytics-chat' ),
						),
						'recipients' => array(
							'type'        => 'array',
							'description' => __( 'Recipient phone numbers', 'marketing-analytics-chat' ),
							'items'       => array(
								'type' => 'string',
							),
						),
						'media_url'  => array(
							'type'        => 'string',
							'description' => __( 'Media URL to attach', 'marketing-analytics-chat' ),
							'format'      => 'uri',
						),
					),
					'required'   => array( 'message' ),
				),
				'execute_callback'    => array( $this, 'handle_send_whatsapp_notification' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// Schedule report
		wp_register_ability(
			'marketing-analytics/schedule-report',
			array(
				'label'               => __( 'Schedule Report', 'marketing-analytics-chat' ),
				'description'         => __( 'Schedule recurring analytics report', 'marketing-analytics-chat' ),
				'category'            => 'marketing-analytics',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'frequency' => array(
							'type'        => 'string',
							'description' => __( 'Report frequency', 'marketing-analytics-chat' ),
							'enum'        => array( 'daily', 'weekly', 'monthly' ),
						),
						'channels'  => array(
							'type'        => 'array',
							'description' => __( 'Notification channels', 'marketing-analytics-chat' ),
							'items'       => array(
								'type' => 'string',
								'enum' => array( 'email', 'slack', 'whatsapp' ),
							),
						),
						'time'      => array(
							'type'        => 'string',
							'description' => __( 'Time to send (HH:MM)', 'marketing-analytics-chat' ),
							'pattern'     => '^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$',
						),
					),
					'required'   => array( 'frequency', 'channels' ),
				),
				'execute_callback'    => array( $this, 'handle_schedule_report' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Register network management tools
	 */
	private function register_network_tools() {
		// Add site to network
		wp_register_ability(
			'marketing-analytics/add-site',
			array(
				'label'               => __( 'Add Site', 'marketing-analytics-chat' ),
				'description'         => __( 'Add WordPress site to analytics network', 'marketing-analytics-chat' ),
				'category'            => 'marketing-analytics',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'site_url'         => array(
							'type'        => 'string',
							'description' => __( 'Site URL', 'marketing-analytics-chat' ),
							'format'      => 'uri',
						),
						'site_name'        => array(
							'type'        => 'string',
							'description' => __( 'Site name', 'marketing-analytics-chat' ),
						),
						'auth_method'      => array(
							'type'        => 'string',
							'description' => __( 'Authentication method', 'marketing-analytics-chat' ),
							'enum'        => array( 'api_key', 'oauth', 'basic_auth' ),
							'default'     => 'api_key',
						),
						'auth_credentials' => array(
							'type'        => 'string',
							'description' => __( 'Authentication credentials', 'marketing-analytics-chat' ),
						),
					),
					'required'   => array( 'site_url', 'site_name' ),
				),
				'execute_callback'    => array( $this, 'handle_add_site' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// Remove site from network
		wp_register_ability(
			'marketing-analytics/remove-site',
			array(
				'label'               => __( 'Remove Site', 'marketing-analytics-chat' ),
				'description'         => __( 'Remove site from analytics network', 'marketing-analytics-chat' ),
				'category'            => 'marketing-analytics',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'site_id' => array(
							'type'        => 'integer',
							'description' => __( 'Site ID', 'marketing-analytics-chat' ),
						),
					),
					'required'   => array( 'site_id' ),
				),
				'execute_callback'    => array( $this, 'handle_remove_site' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// List network sites
		wp_register_ability(
			'marketing-analytics/list-sites',
			array(
				'label'               => __( 'List Sites', 'marketing-analytics-chat' ),
				'description'         => __( 'List all sites in analytics network', 'marketing-analytics-chat' ),
				'category'            => 'marketing-analytics',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'is_active' => array(
							'type'        => 'boolean',
							'description' => __( 'Filter by active status', 'marketing-analytics-chat' ),
						),
					),
				),
				'execute_callback'    => array( $this, 'handle_list_sites' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// Get network summary
		wp_register_ability(
			'marketing-analytics/get-network-summary',
			array(
				'label'               => __( 'Get Network Summary', 'marketing-analytics-chat' ),
				'description'         => __( 'Get aggregated metrics across all network sites', 'marketing-analytics-chat' ),
				'category'            => 'marketing-analytics',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'metric' => array(
							'type'        => 'string',
							'description' => __( 'Metric to aggregate', 'marketing-analytics-chat' ),
							'default'     => 'all',
						),
					),
				),
				'execute_callback'    => array( $this, 'handle_get_network_summary' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// Compare sites
		wp_register_ability(
			'marketing-analytics/compare-sites',
			array(
				'label'               => __( 'Compare Sites', 'marketing-analytics-chat' ),
				'description'         => __( 'Compare metrics between network sites', 'marketing-analytics-chat' ),
				'category'            => 'marketing-analytics',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'site_ids' => array(
							'type'        => 'array',
							'description' => __( 'Site IDs to compare', 'marketing-analytics-chat' ),
							'items'       => array(
								'type' => 'integer',
							),
						),
						'metric'   => array(
							'type'        => 'string',
							'description' => __( 'Metric to compare', 'marketing-analytics-chat' ),
						),
					),
					'required'   => array( 'site_ids', 'metric' ),
				),
				'execute_callback'    => array( $this, 'handle_compare_sites' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Register Quick Wins resources
	 */
	private function register_quickwins_resources() {
		// AI insights usage stats
		wp_register_ability(
			'marketing-analytics/ai-usage-stats',
			array(
				'label'               => __( 'AI Usage Stats', 'marketing-analytics-chat' ),
				'description'         => __( 'AI insights usage statistics and costs', 'marketing-analytics-chat' ),
				'category'            => 'marketing-analytics',
				'execute_callback'    => array( $this, 'handle_ai_usage_stats_resource' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// Anomaly detection status
		wp_register_ability(
			'marketing-analytics/anomaly-status',
			array(
				'label'               => __( 'Anomaly Status', 'marketing-analytics-chat' ),
				'description'         => __( 'Current anomaly detection status and recent alerts', 'marketing-analytics-chat' ),
				'category'            => 'marketing-analytics',
				'execute_callback'    => array( $this, 'handle_anomaly_status_resource' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// Network sites overview
		wp_register_ability(
			'marketing-analytics/network-overview',
			array(
				'label'               => __( 'Network Overview', 'marketing-analytics-chat' ),
				'description'         => __( 'Overview of all network sites and their status', 'marketing-analytics-chat' ),
				'category'            => 'marketing-analytics',
				'execute_callback'    => array( $this, 'handle_network_overview_resource' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	// Handler methods for tools

	/**
	 * Handle generate AI insights tool
	 *
	 * @param array $arguments Tool arguments.
	 * @return array Tool response
	 */
	public function handle_generate_ai_insights( $arguments ) {
		$insights_generator = new Insights_Generator();

		$insights = $insights_generator->generate_insights(
			$arguments['data'],
			$arguments['platform'],
			$arguments['analysis_type'] ?? 'general'
		);

		if ( is_wp_error( $insights ) ) {
			return array(
				'error' => $insights->get_error_message(),
			);
		}

		return $insights;
	}

	/**
	 * Handle check anomalies tool
	 *
	 * @param array $arguments Tool arguments.
	 * @return array Tool response
	 */
	public function handle_check_anomalies( $arguments ) {
		$detector = new Anomaly_Detector();

		// Set sensitivity if provided
		if ( isset( $arguments['sensitivity'] ) ) {
			update_option( 'marketing_analytics_mcp_anomaly_sensitivity', $arguments['sensitivity'] );
		}

		// Run check
		if ( $arguments['platform'] === 'all' ) {
			$detector->run_daily_check();
			$anomalies = get_option( 'marketing_analytics_recent_anomalies', array() );
		} else {
			// Check specific platform
			// This would need to be implemented in the detector
			$anomalies = array();
		}

		return array(
			'anomalies' => $anomalies,
			'count'     => count( $anomalies ),
		);
	}

	/**
	 * Handle get anomaly history tool
	 *
	 * @param array $arguments Tool arguments.
	 * @return array Tool response
	 */
	public function handle_get_anomaly_history( $arguments ) {
		$detector = new Anomaly_Detector();

		$history = $detector->get_anomaly_history(
			$arguments['limit'] ?? 20,
			$arguments['platform'] ?? '',
			$arguments['severity'] ?? ''
		);

		return array(
			'history' => $history,
			'count'   => count( $history ),
		);
	}

	/**
	 * Handle export to sheets tool
	 *
	 * @param array $arguments Tool arguments.
	 * @return array Tool response
	 */
	public function handle_export_to_sheets( $arguments ) {
		$exporter = new Sheets_Exporter();

		// Get data from the specified source
		$data = $this->get_platform_data_for_export( $arguments );

		if ( is_wp_error( $data ) ) {
			return array(
				'error' => $data->get_error_message(),
			);
		}

		$result = $exporter->export_to_sheets(
			$data,
			$arguments['data_source'],
			array(
				'create_new'     => $arguments['create_new'] ?? true,
				'include_charts' => $arguments['include_charts'] ?? true,
			)
		);

		if ( is_wp_error( $result ) ) {
			return array(
				'error' => $result->get_error_message(),
			);
		}

		return $result;
	}

	/**
	 * Handle send Slack notification tool
	 *
	 * @param array $arguments Tool arguments.
	 * @return array Tool response
	 */
	public function handle_send_slack_notification( $arguments ) {
		$manager = new Notification_Manager();

		$result = $manager->send_notification(
			$arguments['message'],
			array( 'slack' ),
			array(
				'channel'     => $arguments['channel'] ?? '#marketing',
				'attachments' => $arguments['attachments'] ?? array(),
			)
		);

		return array(
			'success' => ! is_wp_error( $result['slack'] ?? false ),
			'result'  => $result,
		);
	}

	/**
	 * Handle send WhatsApp notification tool
	 *
	 * @param array $arguments Tool arguments.
	 * @return array Tool response
	 */
	public function handle_send_whatsapp_notification( $arguments ) {
		$manager = new Notification_Manager();

		$result = $manager->send_notification(
			$arguments['message'],
			array( 'whatsapp' ),
			array(
				'recipients' => $arguments['recipients'] ?? array(),
				'media_url'  => $arguments['media_url'] ?? '',
			)
		);

		return array(
			'success' => ! is_wp_error( $result['whatsapp'] ?? false ),
			'result'  => $result,
		);
	}

	/**
	 * Handle schedule report tool
	 *
	 * @param array $arguments Tool arguments.
	 * @return array Tool response
	 */
	public function handle_schedule_report( $arguments ) {
		// Update schedule settings
		$schedule_key = 'marketing_analytics_' . $arguments['frequency'] . '_report';

		update_option( $schedule_key . '_enabled', true );
		update_option( $schedule_key . '_channels', $arguments['channels'] );

		if ( isset( $arguments['time'] ) ) {
			update_option( $schedule_key . '_time', $arguments['time'] );
		}

		// Reschedule cron job
		wp_clear_scheduled_hook( $schedule_key );

		$timestamp = $this->calculate_next_run( $arguments['frequency'], $arguments['time'] ?? '09:00' );

		wp_schedule_event( $timestamp, $arguments['frequency'], $schedule_key );

		return array(
			'success'  => true,
			'next_run' => gmdate( 'Y-m-d H:i:s', $timestamp ),
		);
	}

	/**
	 * Handle add site tool
	 *
	 * @param array $arguments Tool arguments.
	 * @return array Tool response
	 */
	public function handle_add_site( $arguments ) {
		$network_manager = new Network_Manager();

		$site_id = $network_manager->add_site( $arguments );

		if ( is_wp_error( $site_id ) ) {
			return array(
				'error' => $site_id->get_error_message(),
			);
		}

		return array(
			'success' => true,
			'site_id' => $site_id,
		);
	}

	/**
	 * Handle remove site tool
	 *
	 * @param array $arguments Tool arguments.
	 * @return array Tool response
	 */
	public function handle_remove_site( $arguments ) {
		$network_manager = new Network_Manager();

		$result = $network_manager->remove_site( $arguments['site_id'] );

		if ( is_wp_error( $result ) ) {
			return array(
				'error' => $result->get_error_message(),
			);
		}

		return array(
			'success' => true,
		);
	}

	/**
	 * Handle list sites tool
	 *
	 * @param array $arguments Tool arguments.
	 * @return array Tool response
	 */
	public function handle_list_sites( $arguments ) {
		$network_manager = new Network_Manager();

		$args = array();
		if ( isset( $arguments['is_active'] ) ) {
			$args['is_active'] = $arguments['is_active'] ? 1 : 0;
		}

		$sites = $network_manager->get_sites( $args );

		return array(
			'sites' => $sites,
			'count' => count( $sites ),
		);
	}

	/**
	 * Handle get network summary tool
	 *
	 * @param array $arguments Tool arguments.
	 * @return array Tool response
	 */
	public function handle_get_network_summary( $arguments ) {
		$network_manager = new Network_Manager();

		$summary = $network_manager->get_network_summary(
			$arguments['metric'] ?? 'all'
		);

		return array(
			'summary'     => $summary,
			'sites_count' => count( $summary ),
		);
	}

	/**
	 * Handle compare sites tool
	 *
	 * @param array $arguments Tool arguments.
	 * @return array Tool response
	 */
	public function handle_compare_sites( $arguments ) {
		$network_manager = new Network_Manager();

		$comparison = $network_manager->compare_sites(
			$arguments['site_ids'],
			$arguments['metric']
		);

		return array(
			'comparison' => $comparison,
		);
	}

	// Handler methods for resources

	/**
	 * Handle AI usage stats resource
	 *
	 * @return array Resource data
	 */
	public function handle_ai_usage_stats_resource() {
		$insights_generator = new Insights_Generator();

		$current_month = current_time( 'Y-m' );
		$stats         = $insights_generator->get_usage_stats( $current_month );
		$cost          = $insights_generator->estimate_monthly_cost( $current_month );

		return array(
			'month'          => $current_month,
			'total_calls'    => $stats['total_calls'],
			'by_platform'    => $stats['by_platform'],
			'by_type'        => $stats['by_type'],
			'estimated_cost' => $cost,
			'currency'       => 'USD',
		);
	}

	/**
	 * Handle anomaly status resource
	 *
	 * @return array Resource data
	 */
	public function handle_anomaly_status_resource() {
		$detector = new Anomaly_Detector();

		$recent_anomalies = get_option( 'marketing_analytics_recent_anomalies', array() );
		$stats            = $detector->get_anomaly_stats( 'week' );

		return array(
			'enabled'          => get_option( 'marketing_analytics_mcp_anomaly_detection_enabled', false ),
			'sensitivity'      => get_option( 'marketing_analytics_mcp_anomaly_sensitivity', 2.0 ),
			'recent_anomalies' => array_slice( $recent_anomalies, 0, 5 ),
			'weekly_stats'     => $stats,
		);
	}

	/**
	 * Handle network overview resource
	 *
	 * @return array Resource data
	 */
	public function handle_network_overview_resource() {
		$network_manager = new Network_Manager();

		$all_sites    = $network_manager->get_sites();
		$active_sites = array_filter(
			$all_sites,
			function ( $s ) {
				return $s->is_active;
			}
		);

		return array(
			'total_sites'  => count( $all_sites ),
			'active_sites' => count( $active_sites ),
			'sites'        => array_map(
				function ( $site ) {
					return array(
						'id'        => $site->id,
						'name'      => $site->site_name,
						'url'       => $site->site_url,
						'is_active' => $site->is_active,
						'last_sync' => $site->last_sync,
					);
				},
				$all_sites
			),
		);
	}

	// Helper methods

	/**
	 * Get platform data for export
	 *
	 * @param array $arguments Export arguments.
	 * @return array|WP_Error Data or error
	 */
	private function get_platform_data_for_export( $arguments ) {
		// This would fetch data from the appropriate API client
		// Based on data_source and date_range
		// Placeholder implementation
		return array(
			'platform' => $arguments['data_source'],
			'data'     => array(),
		);
	}

	/**
	 * Calculate next run time for scheduled report
	 *
	 * @param string $frequency Report frequency.
	 * @param string $time      Time of day.
	 * @return int Timestamp
	 */
	private function calculate_next_run( $frequency, $time ) {
		$base_time = strtotime( 'today ' . $time );

		switch ( $frequency ) {
			case 'daily':
				if ( $base_time < time() ) {
					$base_time = strtotime( 'tomorrow ' . $time );
				}
				break;

			case 'weekly':
				$day       = get_option( 'marketing_analytics_weekly_report_day', 'monday' );
				$base_time = strtotime( 'next ' . $day . ' ' . $time );
				break;

			case 'monthly':
				$day       = get_option( 'marketing_analytics_monthly_report_day', 1 );
				$base_time = strtotime( gmdate( 'Y-m-' . $day . ' ' . $time ) );
				if ( $base_time < time() ) {
					$base_time = strtotime( '+1 month', $base_time );
				}
				break;
		}

		return $base_time;
	}

	/**
	 * Check permissions for Quick Wins abilities
	 *
	 * @return bool True if user has permission.
	 */
	public function check_permissions() {
		return Permission_Manager::can_access_plugin();
	}
}
