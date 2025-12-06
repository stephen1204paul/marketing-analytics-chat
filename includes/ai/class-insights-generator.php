<?php
/**
 * AI-Powered Insights Generator
 *
 * @package Marketing_Analytics_MCP
 * @subpackage AI
 */

namespace Marketing_Analytics_MCP\AI;

/**
 * Class for generating AI-powered insights from analytics data
 */
class Insights_Generator {

	/**
	 * AI model to use for generating insights
	 *
	 * @var string
	 */
	private $model = 'claude-3-sonnet';

	/**
	 * Cache group for AI insights
	 *
	 * @var string
	 */
	private const CACHE_GROUP = 'marketing_analytics_ai_insights';

	/**
	 * Cache TTL (1 hour)
	 *
	 * @var int
	 */
	private const CACHE_TTL = 3600;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Get AI model from settings
		$this->model = get_option( 'marketing_analytics_mcp_ai_model', 'claude-3-sonnet' );
	}

	/**
	 * Generate insights from analytics data
	 *
	 * @param array  $data          The analytics data to analyze.
	 * @param string $platform      The analytics platform (ga4, clarity, gsc, meta, dataforseo).
	 * @param string $analysis_type The type of analysis (traffic, seo, social, general).
	 * @return array|WP_Error Array of insights or error
	 */
	public function generate_insights( $data, $platform, $analysis_type = 'general' ) {
		// Check if AI insights are enabled
		if ( ! get_option( 'marketing_analytics_mcp_ai_insights_enabled', false ) ) {
			return array();
		}

		// Check cache first
		$cache_key = $this->get_cache_key( $data, $platform, $analysis_type );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		// Generate prompt based on analysis type
		$prompt = $this->generate_prompt( $data, $platform, $analysis_type );

		// Call AI API
		$insights = $this->call_ai_api( $prompt );

		if ( is_wp_error( $insights ) ) {
			return $insights;
		}

		// Structure the insights
		$structured_insights = $this->structure_insights( $insights, $platform );

		// Cache the results
		set_transient( $cache_key, $structured_insights, self::CACHE_TTL );

		// Track API usage for cost monitoring
		$this->track_api_usage( $platform, $analysis_type );

		return $structured_insights;
	}

	/**
	 * Generate prompt for AI analysis
	 *
	 * @param array  $data          The analytics data.
	 * @param string $platform      The analytics platform.
	 * @param string $analysis_type The type of analysis.
	 * @return string The generated prompt
	 */
	private function generate_prompt( $data, $platform, $analysis_type ) {
		$prompts = $this->get_prompt_templates();

		if ( ! isset( $prompts[ $analysis_type ] ) ) {
			$analysis_type = 'general';
		}

		$template = $prompts[ $analysis_type ];

		// Replace placeholders with actual data
		$prompt = str_replace( '{platform}', $platform, $template );
		$prompt = str_replace( '{data}', wp_json_encode( $data, JSON_PRETTY_PRINT ), $prompt );
		$prompt = str_replace( '{date}', current_time( 'Y-m-d' ), $prompt );

		return $prompt;
	}

	/**
	 * Get prompt templates for different analysis types
	 *
	 * @return array Array of prompt templates
	 */
	private function get_prompt_templates() {
		return array(
			'general' => 'Analyze this {platform} analytics data and provide 3-5 actionable insights and recommendations:

Data:
{data}

Please provide:
1. Key trends and patterns
2. Potential issues or concerns
3. Specific actionable recommendations
4. Priority levels for each recommendation (High/Medium/Low)

Format your response as a structured list of insights.',

			'traffic' => 'Analyze this website traffic data from {platform} and provide insights:

Data:
{data}

Focus on:
1. Traffic trends and patterns
2. Traffic source performance
3. User behavior insights
4. Conversion opportunities
5. Recommendations for traffic growth

Provide 3-5 specific, actionable recommendations with priority levels.',

			'seo'     => 'Analyze this SEO data from {platform} and provide optimization insights:

Data:
{data}

Analyze:
1. Search performance trends
2. Keyword opportunities
3. Content gaps
4. Technical SEO issues
5. Competitor insights

Provide 3-5 specific SEO improvement recommendations with estimated impact.',

			'social'  => 'Analyze this social media data from {platform} and provide engagement insights:

Data:
{data}

Evaluate:
1. Engagement trends
2. Content performance
3. Audience insights
4. Optimal posting times
5. Growth opportunities

Provide 3-5 actionable recommendations for improving social media performance.',

			'anomaly' => 'Analyze this {platform} data for anomalies and unusual patterns:

Data:
{data}

Identify:
1. Significant deviations from normal patterns
2. Potential causes for anomalies
3. Impact assessment
4. Recommended actions
5. Monitoring suggestions

Prioritize findings by urgency and potential impact.',
		);
	}

	/**
	 * Call AI API to generate insights
	 *
	 * @param string $prompt The prompt for AI analysis.
	 * @return string|WP_Error The AI response or error
	 */
	private function call_ai_api( $prompt ) {
		$model   = $this->model;
		$api_key = get_option( 'marketing_analytics_mcp_ai_api_key' );

		if ( empty( $api_key ) ) {
			return new \WP_Error(
				'no_api_key',
				__( 'AI API key not configured', 'marketing-analytics-chat' )
			);
		}

		// Determine which API to use based on model
		if ( strpos( $model, 'claude' ) !== false ) {
			return $this->call_claude_api( $prompt, $api_key, $model );
		} elseif ( strpos( $model, 'gpt' ) !== false ) {
			return $this->call_openai_api( $prompt, $api_key, $model );
		} else {
			// Default to WordPress AI if available
			return $this->call_wordpress_ai( $prompt );
		}
	}

	/**
	 * Call Claude API
	 *
	 * @param string $prompt  The prompt.
	 * @param string $api_key The API key.
	 * @param string $model   The model to use.
	 * @return string|WP_Error The response or error
	 */
	private function call_claude_api( $prompt, $api_key, $model ) {
		$url = 'https://api.anthropic.com/v1/messages';

		$body = array(
			'model'      => $model,
			'max_tokens' => 1024,
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			),
		);

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Content-Type'      => 'application/json',
					'x-api-key'         => $api_key,
					'anthropic-version' => '2023-06-01',
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( isset( $data['content'][0]['text'] ) ) {
			return $data['content'][0]['text'];
		}

		return new \WP_Error(
			'api_error',
			__( 'Failed to generate AI insights', 'marketing-analytics-chat' )
		);
	}

	/**
	 * Call OpenAI API
	 *
	 * @param string $prompt  The prompt.
	 * @param string $api_key The API key.
	 * @param string $model   The model to use.
	 * @return string|WP_Error The response or error
	 */
	private function call_openai_api( $prompt, $api_key, $model ) {
		$url = 'https://api.openai.com/v1/chat/completions';

		$body = array(
			'model'       => $model,
			'messages'    => array(
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			),
			'max_tokens'  => 1024,
			'temperature' => 0.7,
		);

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( isset( $data['choices'][0]['message']['content'] ) ) {
			return $data['choices'][0]['message']['content'];
		}

		return new \WP_Error(
			'api_error',
			__( 'Failed to generate AI insights', 'marketing-analytics-chat' )
		);
	}

	/**
	 * Call WordPress AI (fallback)
	 *
	 * @param string $prompt The prompt.
	 * @return string|WP_Error The response or error
	 */
	private function call_wordpress_ai( $prompt ) {
		// Check if WordPress AI is available
		if ( function_exists( 'wp_ai_generate_text' ) ) {
			return wp_ai_generate_text( $prompt );
		}

		// Generate basic insights without AI
		return $this->generate_basic_insights( $prompt );
	}

	/**
	 * Generate basic insights without AI
	 *
	 * @param string $prompt The original prompt.
	 * @return string Basic insights
	 */
	private function generate_basic_insights( $prompt ) {
		// Parse data from prompt
		preg_match( '/Data:\s*(\{.*?\})/s', $prompt, $matches );

		if ( empty( $matches[1] ) ) {
			return __( 'Unable to analyze data', 'marketing-analytics-chat' );
		}

		$data = json_decode( $matches[1], true );

		if ( empty( $data ) ) {
			return __( 'Invalid data format', 'marketing-analytics-chat' );
		}

		// Generate basic statistical insights
		$insights = array(
			__( 'Data analysis completed', 'marketing-analytics-chat' ),
			/* translators: %d: number of data points analyzed */
			sprintf( __( 'Total data points analyzed: %d', 'marketing-analytics-chat' ), count( $data ) ),
		);

		// Add basic trend analysis
		if ( isset( $data['metrics'] ) ) {
			/* translators: %s: comma-separated list of metric names */
			$insights[] = sprintf(
				__( 'Metrics tracked: %s', 'marketing-analytics-chat' ),
				implode( ', ', array_keys( $data['metrics'] ) )
			);
		}

		return implode( "\n", $insights );
	}

	/**
	 * Structure AI insights into a consistent format
	 *
	 * @param string $raw_insights Raw AI response.
	 * @param string $platform     The platform.
	 * @return array Structured insights
	 */
	private function structure_insights( $raw_insights, $platform ) {
		// Parse insights into structured format
		$lines           = explode( "\n", $raw_insights );
		$insights        = array();
		$current_insight = null;

		foreach ( $lines as $line ) {
			$line = trim( $line );

			if ( empty( $line ) ) {
				continue;
			}

			// Check for priority indicators
			$priority = 'medium';
			if ( stripos( $line, 'high' ) !== false || stripos( $line, 'urgent' ) !== false ) {
				$priority = 'high';
			} elseif ( stripos( $line, 'low' ) !== false ) {
				$priority = 'low';
			}

			// Check if this is a new insight (starts with number or bullet)
			if ( preg_match( '/^(\d+\.|\*|-|•)/', $line ) ) {
				if ( $current_insight ) {
					$insights[] = $current_insight;
				}
				$current_insight = array(
					'text'     => preg_replace( '/^(\d+\.|\*|-|•)\s*/', '', $line ),
					'priority' => $priority,
					'platform' => $platform,
					'type'     => $this->determine_insight_type( $line ),
				);
			} elseif ( $current_insight ) {
				// Continue previous insight
				$current_insight['text'] .= ' ' . $line;
			} else {
				// Standalone insight
				$insights[] = array(
					'text'     => $line,
					'priority' => $priority,
					'platform' => $platform,
					'type'     => $this->determine_insight_type( $line ),
				);
			}
		}

		// Add last insight
		if ( $current_insight ) {
			$insights[] = $current_insight;
		}

		return array(
			'insights'     => $insights,
			'generated_at' => current_time( 'mysql' ),
			'model'        => $this->model,
			'platform'     => $platform,
		);
	}

	/**
	 * Determine the type of insight
	 *
	 * @param string $text The insight text.
	 * @return string The insight type
	 */
	private function determine_insight_type( $text ) {
		$text = strtolower( $text );

		if ( strpos( $text, 'trend' ) !== false || strpos( $text, 'pattern' ) !== false ) {
			return 'trend';
		} elseif ( strpos( $text, 'issue' ) !== false || strpos( $text, 'problem' ) !== false ) {
			return 'issue';
		} elseif ( strpos( $text, 'recommend' ) !== false || strpos( $text, 'suggest' ) !== false ) {
			return 'recommendation';
		} elseif ( strpos( $text, 'opportunit' ) !== false ) {
			return 'opportunity';
		} else {
			return 'observation';
		}
	}

	/**
	 * Get cache key for insights
	 *
	 * @param array  $data          The data.
	 * @param string $platform      The platform.
	 * @param string $analysis_type The analysis type.
	 * @return string Cache key
	 */
	private function get_cache_key( $data, $platform, $analysis_type ) {
		return self::CACHE_GROUP . '_' . md5( wp_json_encode( $data ) . $platform . $analysis_type );
	}

	/**
	 * Track API usage for cost monitoring
	 *
	 * @param string $platform      The platform.
	 * @param string $analysis_type The analysis type.
	 */
	private function track_api_usage( $platform, $analysis_type ) {
		$usage = get_option( 'marketing_analytics_mcp_ai_usage', array() );

		$month = current_time( 'Y-m' );

		if ( ! isset( $usage[ $month ] ) ) {
			$usage[ $month ] = array(
				'total_calls' => 0,
				'by_platform' => array(),
				'by_type'     => array(),
			);
		}

		++$usage[ $month ]['total_calls'];

		if ( ! isset( $usage[ $month ]['by_platform'][ $platform ] ) ) {
			$usage[ $month ]['by_platform'][ $platform ] = 0;
		}
		++$usage[ $month ]['by_platform'][ $platform ];

		if ( ! isset( $usage[ $month ]['by_type'][ $analysis_type ] ) ) {
			$usage[ $month ]['by_type'][ $analysis_type ] = 0;
		}
		++$usage[ $month ]['by_type'][ $analysis_type ];

		update_option( 'marketing_analytics_mcp_ai_usage', $usage );
	}

	/**
	 * Get AI usage statistics
	 *
	 * @param string $month Optional month (Y-m format).
	 * @return array Usage statistics
	 */
	public function get_usage_stats( $month = null ) {
		if ( ! $month ) {
			$month = current_time( 'Y-m' );
		}

		$usage = get_option( 'marketing_analytics_mcp_ai_usage', array() );

		if ( isset( $usage[ $month ] ) ) {
			return $usage[ $month ];
		}

		return array(
			'total_calls' => 0,
			'by_platform' => array(),
			'by_type'     => array(),
		);
	}

	/**
	 * Estimate monthly cost based on usage
	 *
	 * @param string $month Optional month (Y-m format).
	 * @return float Estimated cost in USD
	 */
	public function estimate_monthly_cost( $month = null ) {
		$stats = $this->get_usage_stats( $month );

		// Cost per 1000 tokens (approximate)
		$cost_per_call = 0.003; // $0.003 per API call average

		if ( strpos( $this->model, 'gpt-4' ) !== false ) {
			$cost_per_call = 0.01; // GPT-4 is more expensive
		} elseif ( strpos( $this->model, 'claude-3-opus' ) !== false ) {
			$cost_per_call = 0.015; // Claude Opus is premium
		}

		return $stats['total_calls'] * $cost_per_call;
	}
}
