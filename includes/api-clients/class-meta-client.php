<?php
/**
 * Meta Business Suite API Client
 *
 * Handles interactions with Facebook Graph API for Facebook Pages and Instagram Business accounts.
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\API_Clients;

use Marketing_Analytics_MCP\Credentials\OAuth_Handler;
use Marketing_Analytics_MCP\Cache\Cache_Manager;
use Marketing_Analytics_MCP\Utils\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Meta API Client class
 */
class Meta_Client {

	/**
	 * OAuth Handler instance
	 *
	 * @var OAuth_Handler
	 */
	private $oauth_handler;

	/**
	 * Cache Manager instance
	 *
	 * @var Cache_Manager
	 */
	private $cache_manager;

	/**
	 * Guzzle HTTP Client
	 *
	 * @var Client
	 */
	private $http_client;

	/**
	 * Facebook Page ID
	 *
	 * @var string
	 */
	private $page_id;

	/**
	 * Instagram Business Account ID
	 *
	 * @var string
	 */
	private $instagram_id;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->oauth_handler = new OAuth_Handler();
		$this->cache_manager = new Cache_Manager();

		// Get configured IDs from options
		$this->page_id      = get_option( 'marketing_analytics_mcp_meta_page_id' );
		$this->instagram_id = get_option( 'marketing_analytics_mcp_meta_instagram_id' );

		$this->init_http_client();
	}

	/**
	 * Initialize HTTP client
	 *
	 * @return void
	 */
	private function init_http_client() {
		$this->http_client = new Client(
			array(
				'base_uri' => 'https://graph.facebook.com/v21.0/',
				'timeout'  => 30,
				'headers'  => array(
					'Accept' => 'application/json',
				),
			)
		);
	}

	/**
	 * Make API request to Facebook Graph API
	 *
	 * @param string $endpoint API endpoint.
	 * @param array  $params Query parameters.
	 *
	 * @return array|null Response data or null on failure.
	 */
	private function make_api_request( $endpoint, $params = array() ) {
		$access_token = $this->oauth_handler->get_access_token( 'meta' );
		if ( empty( $access_token ) ) {
			return null;
		}

		// Add access token to params
		$params['access_token'] = $access_token;

		try {
			$response = $this->http_client->get(
				$endpoint,
				array(
					'query' => $params,
				)
			);

			$body = $response->getBody()->getContents();
			return json_decode( $body, true );

		} catch ( RequestException $e ) {
			Logger::debug( 'Meta API error: ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Get Facebook Page insights
	 *
	 * @param array  $metrics Array of metric names.
	 * @param string $period Period for the metrics (day, week, days_28).
	 * @param string $date_preset Date range preset (today, yesterday, this_week, last_week, etc).
	 *
	 * @return array|null Insights data or null on failure.
	 */
	public function get_facebook_insights( $metrics = array(), $period = 'day', $date_preset = 'last_7d' ) {
		if ( empty( $this->page_id ) ) {
			return null;
		}

		$cache_key = 'meta_fb_insights_' . md5( serialize( array( $metrics, $period, $date_preset ) ) );
		$cached    = $this->cache_manager->get( $cache_key );

		if ( $cached !== false ) {
			return $cached;
		}

		// Default metrics if none specified
		if ( empty( $metrics ) ) {
			$metrics = array(
				'page_impressions',
				'page_engaged_users',
				'page_post_engagements',
				'page_fan_adds',
				'page_views_total',
			);
		}

		$endpoint = $this->page_id . '/insights';
		$params   = array(
			'metric'      => implode( ',', $metrics ),
			'period'      => $period,
			'date_preset' => $date_preset,
		);

		$data = $this->make_api_request( $endpoint, $params );

		if ( $data !== null ) {
			// Cache for 30 minutes
			$this->cache_manager->set( $cache_key, $data, 1800 );
		}

		return $data;
	}

	/**
	 * Get Instagram Business insights
	 *
	 * @param array  $metrics Array of metric names.
	 * @param string $period Period for the metrics (day, week, days_28, lifetime).
	 * @param string $date_preset Date range preset.
	 *
	 * @return array|null Insights data or null on failure.
	 */
	public function get_instagram_insights( $metrics = array(), $period = 'day', $date_preset = 'last_7d' ) {
		if ( empty( $this->instagram_id ) ) {
			return null;
		}

		$cache_key = 'meta_ig_insights_' . md5( serialize( array( $metrics, $period, $date_preset ) ) );
		$cached    = $this->cache_manager->get( $cache_key );

		if ( $cached !== false ) {
			return $cached;
		}

		// Default metrics if none specified
		if ( empty( $metrics ) ) {
			$metrics = array(
				'impressions',
				'reach',
				'profile_views',
				'follower_count',
				'website_clicks',
			);
		}

		$endpoint = $this->instagram_id . '/insights';
		$params   = array(
			'metric'      => implode( ',', $metrics ),
			'period'      => $period,
			'date_preset' => $date_preset,
		);

		$data = $this->make_api_request( $endpoint, $params );

		if ( $data !== null ) {
			// Cache for 30 minutes
			$this->cache_manager->set( $cache_key, $data, 1800 );
		}

		return $data;
	}

	/**
	 * Get audience demographics
	 *
	 * @param string $platform Platform to get demographics for ('facebook' or 'instagram').
	 *
	 * @return array|null Demographics data or null on failure.
	 */
	public function get_audience_demographics( $platform = 'facebook' ) {
		$account_id = ( 'instagram' === $platform ) ? $this->instagram_id : $this->page_id;

		if ( empty( $account_id ) ) {
			return null;
		}

		$cache_key = 'meta_demographics_' . $platform;
		$cached    = $this->cache_manager->get( $cache_key );

		if ( $cached !== false ) {
			return $cached;
		}

		$endpoint = $account_id . '/insights';
		$params   = array(
			'metric' => 'page_fans_gender_age,page_fans_country,page_fans_city',
			'period' => 'lifetime',
		);

		if ( 'instagram' === $platform ) {
			$params['metric'] = 'audience_gender_age,audience_country,audience_city';
		}

		$data = $this->make_api_request( $endpoint, $params );

		if ( $data !== null ) {
			// Cache for 1 hour
			$this->cache_manager->set( $cache_key, $data, 3600 );
		}

		return $data;
	}

	/**
	 * Get Facebook posts performance
	 *
	 * @param int    $limit Number of posts to retrieve.
	 * @param string $metric_type Type of metrics ('engagement', 'impressions', 'reactions').
	 *
	 * @return array|null Posts data or null on failure.
	 */
	public function get_posts_performance( $limit = 10, $metric_type = 'engagement' ) {
		if ( empty( $this->page_id ) ) {
			return null;
		}

		$cache_key = 'meta_posts_' . md5( serialize( array( $limit, $metric_type ) ) );
		$cached    = $this->cache_manager->get( $cache_key );

		if ( $cached !== false ) {
			return $cached;
		}

		$endpoint = $this->page_id . '/posts';
		$params   = array(
			'fields' => 'message,created_time,permalink_url,insights.metric(post_impressions,post_engaged_users,post_reactions_by_type_total)',
			'limit'  => $limit,
		);

		$data = $this->make_api_request( $endpoint, $params );

		if ( $data !== null ) {
			// Cache for 30 minutes
			$this->cache_manager->set( $cache_key, $data, 1800 );
		}

		return $data;
	}

	/**
	 * Get Instagram media performance
	 *
	 * @param int    $limit Number of media items to retrieve.
	 * @param string $media_type Type of media ('all', 'image', 'video', 'carousel').
	 *
	 * @return array|null Media data or null on failure.
	 */
	public function get_media_performance( $limit = 10, $media_type = 'all' ) {
		if ( empty( $this->instagram_id ) ) {
			return null;
		}

		$cache_key = 'meta_media_' . md5( serialize( array( $limit, $media_type ) ) );
		$cached    = $this->cache_manager->get( $cache_key );

		if ( $cached !== false ) {
			return $cached;
		}

		$endpoint = $this->instagram_id . '/media';
		$params   = array(
			'fields' => 'caption,media_type,media_url,permalink,timestamp,like_count,comments_count,insights.metric(impressions,reach,saved)',
			'limit'  => $limit,
		);

		$data = $this->make_api_request( $endpoint, $params );

		if ( $data !== null ) {
			// Filter by media type if specified
			if ( 'all' !== $media_type && isset( $data['data'] ) ) {
				$data['data'] = array_filter(
					$data['data'],
					function ( $item ) use ( $media_type ) {
						$type_map = array(
							'image'    => 'IMAGE',
							'video'    => 'VIDEO',
							'carousel' => 'CAROUSEL_ALBUM',
						);
						return isset( $item['media_type'] ) && $item['media_type'] === $type_map[ $media_type ];
					}
				);
			}

			// Cache for 30 minutes
			$this->cache_manager->set( $cache_key, $data, 1800 );
		}

		return $data;
	}

	/**
	 * Get engagement metrics for Facebook or Instagram
	 *
	 * @param string $platform Platform to get metrics for ('facebook' or 'instagram').
	 * @param string $date_range Date range preset (e.g., 'last_7d', 'last_30d').
	 *
	 * @return array|null Engagement metrics data or null on failure.
	 */
	public function get_engagement_metrics( $platform = 'facebook', $date_range = 'last_7d' ) {
		$account_id = ( 'instagram' === $platform ) ? $this->instagram_id : $this->page_id;

		if ( empty( $account_id ) ) {
			return null;
		}

		$cache_key = 'meta_engagement_' . $platform . '_' . $date_range;
		$cached    = $this->cache_manager->get( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$endpoint = $account_id . '/insights';

		if ( 'instagram' === $platform ) {
			$params = array(
				'metric'      => 'impressions,reach,profile_views',
				'period'      => 'day',
				'date_preset' => $date_range,
			);
		} else {
			$params = array(
				'metric'      => 'page_post_engagements,page_consumptions,page_engaged_users,page_impressions',
				'period'      => 'day',
				'date_preset' => $date_range,
			);
		}

		$data = $this->make_api_request( $endpoint, $params );

		if ( null !== $data ) {
			// Cache for 30 minutes.
			$this->cache_manager->set( $cache_key, $data, 1800 );
		}

		return $data;
	}

	/**
	 * Test connection to Meta APIs
	 *
	 * @return bool True if connection successful, false otherwise.
	 */
	public function test_connection() {
		$data = $this->make_api_request( 'me', array( 'fields' => 'id,name' ) );
		return ! empty( $data['id'] );
	}

	/**
	 * Get available Facebook Pages for the user
	 *
	 * @return array|null Array of pages or null on failure.
	 */
	public function get_available_pages() {
		$data = $this->make_api_request( 'me/accounts' );
		return isset( $data['data'] ) ? $data['data'] : null;
	}

	/**
	 * Get user's Facebook Pages (alias for get_available_pages)
	 *
	 * @return array|null Array of pages or null on failure.
	 */
	public function get_user_pages() {
		return $this->get_available_pages();
	}

	/**
	 * Get Instagram Business Account ID for a Facebook Page
	 *
	 * @param string $page_id Facebook Page ID.
	 *
	 * @return array|null Instagram account data or null if not found.
	 */
	public function get_instagram_account( $page_id ) {
		$data = $this->make_api_request(
			$page_id,
			array( 'fields' => 'instagram_business_account{id,username,profile_picture_url}' )
		);

		if ( isset( $data['instagram_business_account'] ) ) {
			return $data['instagram_business_account'];
		}

		return null;
	}
}
