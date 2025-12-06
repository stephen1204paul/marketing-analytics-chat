<?php
/**
 * Meta Business Suite Abilities
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\Abilities;

use Marketing_Analytics_MCP\API_Clients\Meta_Client;
use Marketing_Analytics_MCP\Credentials\Credential_Manager;

/**
 * Registers Meta Business Suite MCP abilities
 */
class Meta_Abilities {

	/**
	 * Register Meta abilities
	 */
	public function register() {
		// Only register abilities if credentials are configured
		$credential_manager = new Credential_Manager();
		if ( ! $credential_manager->has_credentials( 'meta' ) ) {
			return;
		}

		$this->register_get_facebook_insights();
		$this->register_get_instagram_insights();
		$this->register_get_social_demographics();
		$this->register_get_post_performance();
		$this->register_get_social_engagement();
		$this->register_social_overview_resource();
	}

	/**
	 * Register get-facebook-insights tool
	 */
	private function register_get_facebook_insights() {
		wp_register_ability(
			'marketing-analytics/get-facebook-insights',
			array(
				'label'               => __( 'Get Facebook Insights', 'marketing-analytics-chat' ),
				'description'         => __( 'Retrieve Facebook Page insights including reach, engagement, and follower metrics.', 'marketing-analytics-chat' ),
				'category'            => 'marketing-analytics',

				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'metrics'     => array(
							'type'        => 'array',
							'description' => 'Array of metric names (e.g., page_impressions, page_engaged_users)',
							'items'       => array( 'type' => 'string' ),
							'default'     => array(
								'page_impressions',
								'page_engaged_users',
								'page_post_engagements',
								'page_fan_adds',
								'page_views_total',
							),
						),
						'period'      => array(
							'type'        => 'string',
							'description' => 'Period for metrics (day, week, days_28)',
							'enum'        => array( 'day', 'week', 'days_28' ),
							'default'     => 'day',
						),
						'date_preset' => array(
							'type'        => 'string',
							'description' => 'Date range preset (today, yesterday, this_week, last_week, last_7d, last_14d, last_30d)',
							'default'     => 'last_7d',
						),
					),
				),

				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'data'   => array(
							'type'        => 'array',
							'description' => 'Insights data with metrics and values',
						),
						'paging' => array(
							'type'        => 'object',
							'description' => 'Pagination information',
						),
					),
				),

				'execute_callback'    => array( $this, 'execute_get_facebook_insights' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Register get-instagram-insights tool
	 */
	private function register_get_instagram_insights() {
		wp_register_ability(
			'marketing-analytics/get-instagram-insights',
			array(
				'label'               => __( 'Get Instagram Insights', 'marketing-analytics-chat' ),
				'description'         => __( 'Retrieve Instagram Business account insights including impressions, reach, and profile views.', 'marketing-analytics-chat' ),
				'category'            => 'marketing-analytics',

				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'metrics'     => array(
							'type'        => 'array',
							'description' => 'Array of metric names (e.g., impressions, reach, profile_views)',
							'items'       => array( 'type' => 'string' ),
							'default'     => array(
								'impressions',
								'reach',
								'profile_views',
								'follower_count',
								'website_clicks',
							),
						),
						'period'      => array(
							'type'        => 'string',
							'description' => 'Period for metrics (day, week, days_28, lifetime)',
							'enum'        => array( 'day', 'week', 'days_28', 'lifetime' ),
							'default'     => 'day',
						),
						'date_preset' => array(
							'type'        => 'string',
							'description' => 'Date range preset',
							'default'     => 'last_7d',
						),
					),
				),

				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'data'   => array(
							'type'        => 'array',
							'description' => 'Instagram insights data',
						),
						'paging' => array(
							'type'        => 'object',
							'description' => 'Pagination information',
						),
					),
				),

				'execute_callback'    => array( $this, 'execute_get_instagram_insights' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Register get-social-demographics tool
	 */
	private function register_get_social_demographics() {
		wp_register_ability(
			'marketing-analytics/get-social-demographics',
			array(
				'label'               => __( 'Get Social Demographics', 'marketing-analytics-chat' ),
				'description'         => __( 'Retrieve audience demographics data for Facebook Page or Instagram Business account.', 'marketing-analytics-chat' ),
				'category'            => 'marketing-analytics',

				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'platform' => array(
							'type'        => 'string',
							'description' => 'Platform to get demographics for',
							'enum'        => array( 'facebook', 'instagram' ),
							'default'     => 'facebook',
						),
					),
				),

				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'gender_age' => array(
							'type'        => 'object',
							'description' => 'Gender and age distribution',
						),
						'country'    => array(
							'type'        => 'object',
							'description' => 'Country distribution',
						),
						'city'       => array(
							'type'        => 'object',
							'description' => 'City distribution',
						),
					),
				),

				'execute_callback'    => array( $this, 'execute_get_social_demographics' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Register get-post-performance tool
	 */
	private function register_get_post_performance() {
		wp_register_ability(
			'marketing-analytics/get-post-performance',
			array(
				'label'               => __( 'Get Post Performance', 'marketing-analytics-chat' ),
				'description'         => __( 'Retrieve performance metrics for recent posts on Facebook or Instagram.', 'marketing-analytics-chat' ),
				'category'            => 'marketing-analytics',

				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'platform' => array(
							'type'        => 'string',
							'description' => 'Platform to get posts from',
							'enum'        => array( 'facebook', 'instagram' ),
							'default'     => 'facebook',
						),
						'limit'    => array(
							'type'        => 'integer',
							'description' => 'Number of posts to retrieve',
							'default'     => 10,
							'minimum'     => 1,
							'maximum'     => 50,
						),
					),
				),

				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'data' => array(
							'type'        => 'array',
							'description' => 'Array of posts with performance metrics',
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'id'           => array( 'type' => 'string' ),
									'message'      => array( 'type' => 'string' ),
									'created_time' => array( 'type' => 'string' ),
									'permalink'    => array( 'type' => 'string' ),
									'insights'     => array( 'type' => 'object' ),
								),
							),
						),
					),
				),

				'execute_callback'    => array( $this, 'execute_get_post_performance' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Register get-social-engagement tool
	 */
	private function register_get_social_engagement() {
		wp_register_ability(
			'marketing-analytics/get-social-engagement',
			array(
				'label'               => __( 'Get Social Engagement', 'marketing-analytics-chat' ),
				'description'         => __( 'Retrieve engagement metrics for Facebook or Instagram including likes, comments, shares, and saves.', 'marketing-analytics-chat' ),
				'category'            => 'marketing-analytics',

				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'platform'   => array(
							'type'        => 'string',
							'description' => 'Platform to get engagement metrics for',
							'enum'        => array( 'facebook', 'instagram' ),
							'default'     => 'facebook',
						),
						'date_range' => array(
							'type'        => 'string',
							'description' => 'Date range for metrics',
							'default'     => 'last_7d',
						),
					),
				),

				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'engagement_rate' => array(
							'type'        => 'number',
							'description' => 'Overall engagement rate',
						),
						'metrics'         => array(
							'type'        => 'object',
							'description' => 'Detailed engagement metrics',
						),
					),
				),

				'execute_callback'    => array( $this, 'execute_get_social_engagement' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Register social-overview resource
	 */
	private function register_social_overview_resource() {
		wp_register_ability(
			'marketing-analytics/social-overview',
			array(
				'label'               => __( 'Social Media Overview', 'marketing-analytics-chat' ),
				'description'         => __( 'Get a comprehensive overview of Facebook and Instagram performance metrics.', 'marketing-analytics-chat' ),
				'category'            => 'marketing-analytics',

				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'facebook'     => array(
							'type'        => 'object',
							'description' => 'Facebook metrics overview',
						),
						'instagram'    => array(
							'type'        => 'object',
							'description' => 'Instagram metrics overview',
						),
						'combined'     => array(
							'type'        => 'object',
							'description' => 'Combined social metrics',
						),
						'last_updated' => array(
							'type'        => 'string',
							'description' => 'Timestamp of last data update',
						),
					),
				),

				'execute_callback'    => array( $this, 'read_social_overview' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Execute get-facebook-insights
	 *
	 * @param array $arguments Tool arguments.
	 * @return array Response data.
	 */
	public function execute_get_facebook_insights( $arguments ) {
		$client = new Meta_Client();

		$metrics     = isset( $arguments['metrics'] ) ? $arguments['metrics'] : array();
		$period      = isset( $arguments['period'] ) ? $arguments['period'] : 'day';
		$date_preset = isset( $arguments['date_preset'] ) ? $arguments['date_preset'] : 'last_7d';

		$data = $client->get_facebook_insights( $metrics, $period, $date_preset );

		if ( null === $data ) {
			return array(
				'error' => __( 'Failed to retrieve Facebook insights. Please check your connection settings.', 'marketing-analytics-chat' ),
			);
		}

		return $data;
	}

	/**
	 * Execute get-instagram-insights
	 *
	 * @param array $arguments Tool arguments.
	 * @return array Response data.
	 */
	public function execute_get_instagram_insights( $arguments ) {
		$client = new Meta_Client();

		$metrics     = isset( $arguments['metrics'] ) ? $arguments['metrics'] : array();
		$period      = isset( $arguments['period'] ) ? $arguments['period'] : 'day';
		$date_preset = isset( $arguments['date_preset'] ) ? $arguments['date_preset'] : 'last_7d';

		$data = $client->get_instagram_insights( $metrics, $period, $date_preset );

		if ( null === $data ) {
			return array(
				'error' => __( 'Failed to retrieve Instagram insights. Please check your connection settings.', 'marketing-analytics-chat' ),
			);
		}

		return $data;
	}

	/**
	 * Execute get-social-demographics
	 *
	 * @param array $arguments Tool arguments.
	 * @return array Response data.
	 */
	public function execute_get_social_demographics( $arguments ) {
		$client = new Meta_Client();

		$platform = isset( $arguments['platform'] ) ? $arguments['platform'] : 'facebook';

		$data = $client->get_audience_demographics( $platform );

		if ( null === $data ) {
			return array(
				'error' => __( 'Failed to retrieve demographics data. Please check your connection settings.', 'marketing-analytics-chat' ),
			);
		}

		// Process the data for better readability
		$processed = array();
		if ( isset( $data['data'] ) ) {
			foreach ( $data['data'] as $metric ) {
				$name               = $metric['name'];
				$values             = $metric['values'][0]['value'] ?? array();
				$processed[ $name ] = $values;
			}
		}

		return $processed;
	}

	/**
	 * Execute get-post-performance
	 *
	 * @param array $arguments Tool arguments.
	 * @return array Response data.
	 */
	public function execute_get_post_performance( $arguments ) {
		$client = new Meta_Client();

		$platform = isset( $arguments['platform'] ) ? $arguments['platform'] : 'facebook';
		$limit    = isset( $arguments['limit'] ) ? intval( $arguments['limit'] ) : 10;

		$data = $client->get_post_performance( $limit, $platform );

		if ( null === $data ) {
			return array(
				'error' => __( 'Failed to retrieve post performance data. Please check your connection settings.', 'marketing-analytics-chat' ),
			);
		}

		return $data;
	}

	/**
	 * Execute get-social-engagement
	 *
	 * @param array $arguments Tool arguments.
	 * @return array Response data.
	 */
	public function execute_get_social_engagement( $arguments ) {
		$client = new Meta_Client();

		$platform   = isset( $arguments['platform'] ) ? $arguments['platform'] : 'facebook';
		$date_range = isset( $arguments['date_range'] ) ? $arguments['date_range'] : 'last_7d';

		$data = $client->get_engagement_metrics( $platform, $date_range );

		if ( null === $data ) {
			return array(
				'error' => __( 'Failed to retrieve engagement metrics. Please check your connection settings.', 'marketing-analytics-chat' ),
			);
		}

		// Calculate engagement rate if we have the data
		$engagement_rate = 0;
		if ( isset( $data['data'] ) ) {
			$total_engagement = 0;
			$total_reach      = 0;

			foreach ( $data['data'] as $metric ) {
				if ( strpos( $metric['name'], 'engagement' ) !== false || strpos( $metric['name'], 'consumptions' ) !== false ) {
					foreach ( $metric['values'] as $value ) {
						$total_engagement += intval( $value['value'] );
					}
				}
				if ( strpos( $metric['name'], 'reach' ) !== false || strpos( $metric['name'], 'impressions' ) !== false ) {
					foreach ( $metric['values'] as $value ) {
						$total_reach += intval( $value['value'] );
					}
				}
			}

			if ( $total_reach > 0 ) {
				$engagement_rate = ( $total_engagement / $total_reach ) * 100;
			}
		}

		return array(
			'engagement_rate' => round( $engagement_rate, 2 ),
			'metrics'         => $data,
		);
	}

	/**
	 * Read social-overview resource
	 *
	 * @return array Resource data.
	 */
	public function read_social_overview() {
		$client = new Meta_Client();

		// Get Facebook overview
		$fb_data     = $client->get_facebook_insights( array(), 'day', 'last_7d' );
		$fb_overview = array(
			'connected' => ( null !== $fb_data ),
			'metrics'   => array(),
		);

		if ( $fb_data && isset( $fb_data['data'] ) ) {
			foreach ( $fb_data['data'] as $metric ) {
				$fb_overview['metrics'][ $metric['name'] ] = array(
					'title'       => $metric['title'] ?? $metric['name'],
					'description' => $metric['description'] ?? '',
					'value'       => $metric['values'][0]['value'] ?? 0,
				);
			}
		}

		// Get Instagram overview
		$ig_data     = $client->get_instagram_insights( array(), 'day', 'last_7d' );
		$ig_overview = array(
			'connected' => ( null !== $ig_data ),
			'metrics'   => array(),
		);

		if ( $ig_data && isset( $ig_data['data'] ) ) {
			foreach ( $ig_data['data'] as $metric ) {
				$ig_overview['metrics'][ $metric['name'] ] = array(
					'title'       => $metric['title'] ?? $metric['name'],
					'description' => $metric['description'] ?? '',
					'value'       => $metric['values'][0]['value'] ?? 0,
				);
			}
		}

		// Combined metrics
		$combined = array(
			'total_reach'      => 0,
			'total_engagement' => 0,
			'total_followers'  => 0,
		);

		if ( isset( $fb_overview['metrics']['page_impressions'] ) ) {
			$combined['total_reach'] += intval( $fb_overview['metrics']['page_impressions']['value'] );
		}
		if ( isset( $ig_overview['metrics']['reach'] ) ) {
			$combined['total_reach'] += intval( $ig_overview['metrics']['reach']['value'] );
		}

		return array(
			'facebook'     => $fb_overview,
			'instagram'    => $ig_overview,
			'combined'     => $combined,
			'last_updated' => current_time( 'mysql' ),
		);
	}

	/**
	 * Check permissions for abilities
	 *
	 * @return bool True if user has permission.
	 */
	public function check_permissions() {
		return current_user_can( 'manage_options' );
	}
}
