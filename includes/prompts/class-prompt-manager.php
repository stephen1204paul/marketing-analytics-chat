<?php
/**
 * Prompt Manager
 *
 * Manages custom user-created MCP prompts
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\Prompts;

/**
 * Handles CRUD operations for custom prompts
 */
class Prompt_Manager {

	/**
	 * Option name for storing custom prompts
	 */
	const OPTION_NAME = 'marketing_analytics_mcp_custom_prompts';

	/**
	 * Get all custom prompts
	 *
	 * @return array Array of prompt configurations.
	 */
	public function get_all_prompts() {
		$prompts = get_option( self::OPTION_NAME, array() );
		return is_array( $prompts ) ? $prompts : array();
	}

	/**
	 * Get a single prompt by ID
	 *
	 * @param string $prompt_id Prompt ID.
	 * @return array|null Prompt configuration or null if not found.
	 */
	public function get_prompt( $prompt_id ) {
		$prompts = $this->get_all_prompts();
		return isset( $prompts[ $prompt_id ] ) ? $prompts[ $prompt_id ] : null;
	}

	/**
	 * Create a new custom prompt
	 *
	 * @param array $prompt_data Prompt configuration.
	 * @return string|WP_Error Prompt ID on success, WP_Error on failure.
	 */
	public function create_prompt( $prompt_data ) {
		$validation = $this->validate_prompt_data( $prompt_data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$prompts   = $this->get_all_prompts();
		$prompt_id = $this->generate_prompt_id( $prompt_data['name'] );

		// Check for duplicate ID
		if ( isset( $prompts[ $prompt_id ] ) ) {
			$prompt_id = $prompt_id . '_' . time();
		}

		$prompts[ $prompt_id ] = array_merge(
			$prompt_data,
			array(
				'id'         => $prompt_id,
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			)
		);

		update_option( self::OPTION_NAME, $prompts );

		return $prompt_id;
	}

	/**
	 * Update an existing prompt
	 *
	 * @param string $prompt_id Prompt ID.
	 * @param array  $prompt_data Updated prompt configuration.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function update_prompt( $prompt_id, $prompt_data ) {
		$validation = $this->validate_prompt_data( $prompt_data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$prompts = $this->get_all_prompts();

		if ( ! isset( $prompts[ $prompt_id ] ) ) {
			return new \WP_Error( 'prompt_not_found', __( 'Prompt not found.', 'marketing-analytics-chat' ) );
		}

		$prompts[ $prompt_id ] = array_merge(
			$prompts[ $prompt_id ],
			$prompt_data,
			array(
				'updated_at' => current_time( 'mysql' ),
			)
		);

		update_option( self::OPTION_NAME, $prompts );

		return true;
	}

	/**
	 * Delete a prompt
	 *
	 * @param string $prompt_id Prompt ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete_prompt( $prompt_id ) {
		$prompts = $this->get_all_prompts();

		if ( ! isset( $prompts[ $prompt_id ] ) ) {
			return false;
		}

		unset( $prompts[ $prompt_id ] );
		update_option( self::OPTION_NAME, $prompts );

		return true;
	}

	/**
	 * Validate prompt data
	 *
	 * @param array $prompt_data Prompt data to validate.
	 * @return true|WP_Error True if valid, WP_Error otherwise.
	 */
	private function validate_prompt_data( $prompt_data ) {
		$required_fields = array( 'name', 'description', 'instructions' );

		foreach ( $required_fields as $field ) {
			if ( empty( $prompt_data[ $field ] ) ) {
				return new \WP_Error(
					'missing_field',
					/* translators: %s: field name */
					sprintf( __( 'Missing required field: %s', 'marketing-analytics-chat' ), $field )
				);
			}
		}

		// Validate name format
		if ( ! preg_match( '/^[a-z0-9-]+$/', $prompt_data['name'] ) ) {
			return new \WP_Error(
				'invalid_name',
				__( 'Prompt name must contain only lowercase letters, numbers, and hyphens.', 'marketing-analytics-chat' )
			);
		}

		// Validate arguments if provided
		if ( ! empty( $prompt_data['arguments'] ) && ! is_array( $prompt_data['arguments'] ) ) {
			return new \WP_Error(
				'invalid_arguments',
				__( 'Arguments must be an array.', 'marketing-analytics-chat' )
			);
		}

		return true;
	}

	/**
	 * Generate prompt ID from name
	 *
	 * @param string $name Prompt name.
	 * @return string Prompt ID.
	 */
	private function generate_prompt_id( $name ) {
		return 'marketing-analytics/' . sanitize_title( $name );
	}

	/**
	 * Get preset prompt templates
	 *
	 * @return array Array of preset templates.
	 */
	public function get_preset_templates() {
		return array(
			'traffic-drop-analysis'      => array(
				'name'         => 'analyze-traffic-drop',
				'label'        => __( 'Analyze Traffic Drop', 'marketing-analytics-chat' ),
				'description'  => __( 'Investigates sudden traffic decreases by comparing current metrics with historical data across all platforms', 'marketing-analytics-chat' ),
				'instructions' => "Follow these steps to investigate the traffic drop:\n\n1. Get GA4 metrics for the last 7 days:\n   - Call marketing-analytics/get-ga4-metrics\n   - metrics: ['activeUsers', 'sessions', 'screenPageViews', 'bounceRate']\n   - date_range: '7daysAgo'\n\n2. Get comparison data for previous 7 days:\n   - Call marketing-analytics/get-ga4-metrics\n   - Same metrics\n   - date_range: '14daysAgo' to '7daysAgo'\n\n3. Calculate percentage changes for each metric\n\n4. Get behavioral insights from Clarity:\n   - Call marketing-analytics/get-clarity-insights\n   - num_of_days: 3\n   - dimension1: 'Device'\n\n5. Check search performance:\n   - Call marketing-analytics/get-gsc-performance\n   - Check for ranking drops or indexing issues\n\n6. Analyze traffic sources:\n   - Call marketing-analytics/get-traffic-sources\n   - Identify which channels dropped\n\n7. Provide a summary with:\n   - Metrics comparison table\n   - Primary drop sources\n   - Behavioral changes observed\n   - Specific actionable recommendations",
				'arguments'    => array(
					array(
						'name'        => 'page_url',
						'type'        => 'string',
						'description' => 'Optional: specific page URL to analyze',
						'required'    => false,
					),
				),
				'category'     => 'marketing-analytics',
			),

			'weekly-report'              => array(
				'name'         => 'weekly-report',
				'label'        => __( 'Weekly Performance Report', 'marketing-analytics-chat' ),
				'description'  => __( 'Generates a comprehensive weekly summary combining GA4, Clarity, and GSC data with key insights', 'marketing-analytics-chat' ),
				'instructions' => "Generate a comprehensive weekly performance report:\n\n1. Get GA4 weekly metrics:\n   - Call marketing-analytics/get-ga4-metrics\n   - metrics: ['activeUsers', 'sessions', 'screenPageViews', 'bounceRate', 'averageSessionDuration']\n   - date_range: '7daysAgo'\n   - dimensions: ['date', 'deviceCategory']\n\n2. Get traffic sources breakdown:\n   - Call marketing-analytics/get-traffic-sources\n   - date_range: '7daysAgo'\n\n3. Get Clarity insights:\n   - Call marketing-analytics/get-clarity-insights\n   - num_of_days: 3\n   - dimension1: 'Device'\n   - dimension2: 'Country'\n\n4. Get top performing content:\n   - Call marketing-analytics/get-ga4-metrics\n   - metrics: ['screenPageViews', 'averageSessionDuration']\n   - dimensions: ['pageTitle', 'pagePath']\n   - limit: 10\n\n5. Get search console performance:\n   - Call marketing-analytics/get-gsc-performance\n   - date_range: '7daysAgo'\n\n6. Format the report with:\n   - Executive Summary (3-5 key findings)\n   - Traffic Overview (total sessions, users, change vs last week)\n   - Top Traffic Sources\n   - Device Breakdown\n   - Top 10 Pages\n   - Search Performance Summary\n   - Clarity Behavioral Insights\n   - Recommendations for next week",
				'arguments'    => array(),
				'category'     => 'marketing-analytics',
			),

			'seo-health-check'           => array(
				'name'         => 'seo-health-check',
				'label'        => __( 'SEO Health Check', 'marketing-analytics-chat' ),
				'description'  => __( 'Analyzes GSC data for indexing issues, search performance trends, and optimization opportunities', 'marketing-analytics-chat' ),
				'instructions' => "Perform comprehensive SEO health check:\n\n1. Get Search Console performance:\n   - Call marketing-analytics/get-gsc-performance\n   - date_range: '28daysAgo'\n   - dimensions: ['query', 'page']\n\n2. Check indexing status:\n   - Call marketing-analytics/get-gsc-index-status\n   - Identify crawl errors, coverage issues\n\n3. Analyze top queries:\n   - From GSC data, identify:\n     - Queries with high impressions but low CTR (opportunity)\n     - Queries ranking position 11-20 (quick win potential)\n     - Declining queries (action needed)\n\n4. Get page performance:\n   - Top performing pages\n   - Pages losing rankings\n   - New pages in index\n\n5. Cross-reference with GA4:\n   - Call marketing-analytics/get-ga4-metrics\n   - metrics: ['organicSearches', 'bounceRate', 'averageSessionDuration']\n   - dimensions: ['landingPage']\n\n6. Provide SEO health report:\n   - Overall health score (0-100)\n   - Critical issues requiring immediate attention\n   - Optimization opportunities\n   - Content gaps to fill\n   - Technical SEO recommendations",
				'arguments'    => array(),
				'category'     => 'marketing-analytics',
			),

			'content-performance-audit'  => array(
				'name'         => 'content-performance-audit',
				'label'        => __( 'Content Performance Audit', 'marketing-analytics-chat' ),
				'description'  => __( 'Identifies top and bottom performing content by combining pageviews, engagement, and search rankings', 'marketing-analytics-chat' ),
				'instructions' => "Conduct comprehensive content performance audit:\n\n1. Get all pages with traffic:\n   - Call marketing-analytics/get-ga4-metrics\n   - metrics: ['screenPageViews', 'averageSessionDuration', 'bounceRate']\n   - dimensions: ['pageTitle', 'pagePath']\n   - date_range: '28daysAgo'\n   - limit: 1000\n\n2. Get search performance per page:\n   - Call marketing-analytics/get-gsc-performance\n   - dimensions: ['page']\n   - date_range: '28daysAgo'\n\n3. Get engagement metrics from Clarity:\n   - Call marketing-analytics/get-clarity-insights\n   - num_of_days: 3\n\n4. Categorize content:\n   - Stars: High traffic + High engagement + Good SEO\n   - Opportunities: Low traffic + High engagement (needs SEO)\n   - Update Needed: High traffic + Low engagement\n   - Archive Candidates: Low traffic + Low engagement\n\n5. Provide audit report:\n   - Top 10 performing pages (keep doing what works)\n   - Top 10 underperforming pages (update or remove)\n   - Quick win opportunities (good content, needs SEO)\n   - Content refresh priorities\n   - New content suggestions based on gaps",
				'arguments'    => array(
					array(
						'name'        => 'min_pageviews',
						'type'        => 'integer',
						'description' => 'Minimum pageviews to include in audit',
						'required'    => false,
						'default'     => 100,
					),
				),
				'category'     => 'marketing-analytics',
			),

			'conversion-funnel-analysis' => array(
				'name'         => 'conversion-funnel-analysis',
				'label'        => __( 'Conversion Funnel Analysis', 'marketing-analytics-chat' ),
				'description'  => __( 'Analyzes user journey from traffic source through conversion using GA4 events and Clarity session data', 'marketing-analytics-chat' ),
				'instructions' => "Analyze conversion funnel and identify drop-off points:\n\n1. Get funnel events from GA4:\n   - Call marketing-analytics/get-ga4-events\n   - Get all events in the funnel sequence\n   - date_range: '7daysAgo'\n\n2. Calculate funnel metrics:\n   - Entry point: sessions or page views\n   - Mid-funnel events: add_to_cart, begin_checkout, etc.\n   - Conversion: purchase, sign_up, etc.\n   - Calculate drop-off rates between steps\n\n3. Get traffic source performance:\n   - Call marketing-analytics/get-traffic-sources\n   - Compare conversion rates by source\n\n4. Analyze user behavior at drop-off points:\n   - Call marketing-analytics/get-clarity-recordings\n   - filters: pages where users drop off\n   - Review session recordings for friction\n\n5. Get device/browser breakdown:\n   - Call marketing-analytics/get-ga4-metrics\n   - dimensions: ['deviceCategory', 'browser']\n   - metrics: conversion rate by device\n\n6. Provide funnel analysis:\n   - Funnel visualization with numbers\n   - Drop-off rates at each step\n   - Worst performing segments\n   - Behavioral insights from recordings\n   - Specific recommendations to improve conversion",
				'arguments'    => array(
					array(
						'name'        => 'conversion_event',
						'type'        => 'string',
						'description' => 'GA4 event name for conversion (e.g., purchase, sign_up)',
						'required'    => true,
					),
				),
				'category'     => 'marketing-analytics',
			),
		);
	}

	/**
	 * Import a preset template as a custom prompt
	 *
	 * @param string $preset_key Preset template key.
	 * @return string|WP_Error Prompt ID on success, WP_Error on failure.
	 */
	public function import_preset( $preset_key ) {
		$presets = $this->get_preset_templates();

		if ( ! isset( $presets[ $preset_key ] ) ) {
			return new \WP_Error( 'preset_not_found', __( 'Preset template not found.', 'marketing-analytics-chat' ) );
		}

		return $this->create_prompt( $presets[ $preset_key ] );
	}
}
