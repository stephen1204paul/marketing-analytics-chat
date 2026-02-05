<?php
/**
 * Slack Notifier
 *
 * @package Marketing_Analytics_MCP
 * @subpackage Notifications
 */

namespace Marketing_Analytics_MCP\Notifications;

/**
 * Class for sending notifications to Slack
 */
class Slack_Notifier {

	/**
	 * Slack webhook URL
	 *
	 * @var string
	 */
	private $webhook_url;

	/**
	 * Default channel
	 *
	 * @var string
	 */
	private $default_channel;

	/**
	 * Bot username
	 *
	 * @var string
	 */
	private $bot_name = 'Marketing Analytics Bot';

	/**
	 * Bot icon
	 *
	 * @var string
	 */
	private $bot_icon = ':chart_with_upwards_trend:';

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->webhook_url     = get_option( 'marketing_analytics_slack_webhook_url' );
		$this->default_channel = get_option( 'marketing_analytics_slack_channel', '#marketing' );
		$this->bot_name        = get_option( 'marketing_analytics_slack_bot_name', $this->bot_name );
		$this->bot_icon        = get_option( 'marketing_analytics_slack_bot_icon', $this->bot_icon );
	}

	/**
	 * Send message to Slack
	 *
	 * @param string $message The message to send.
	 * @param array  $options Additional options.
	 * @return bool|WP_Error True on success, error on failure
	 */
	public function send( $message, $options = array() ) {
		if ( empty( $this->webhook_url ) ) {
			return new \WP_Error(
				'no_webhook',
				__( 'Slack webhook URL not configured', 'marketing-analytics-chat' )
			);
		}

		$defaults = array(
			'channel'     => $this->default_channel,
			'username'    => $this->bot_name,
			'icon_emoji'  => $this->bot_icon,
			'attachments' => array(),
			'blocks'      => array(),
			'mrkdwn'      => true,
		);

		$options = wp_parse_args( $options, $defaults );

		// Build payload
		$payload = $this->build_payload( $message, $options );

		// Send to Slack
		$response = wp_remote_post(
			$this->webhook_url,
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code !== 200 ) {
			return new \WP_Error(
				'slack_error',
				sprintf(
					/* translators: %s: Error message */
					__( 'Slack API error: %d', 'marketing-analytics-chat' ),
					$status_code
				)
			);
		}

		return true;
	}

	/**
	 * Build Slack payload
	 *
	 * @param string $message The message.
	 * @param array  $options Options.
	 * @return array Slack payload
	 */
	private function build_payload( $message, $options ) {
		$payload = array(
			'text'       => $message,
			'channel'    => $options['channel'],
			'username'   => $options['username'],
			'icon_emoji' => $options['icon_emoji'],
			'mrkdwn'     => $options['mrkdwn'],
		);

		// Add blocks if provided (for rich formatting)
		if ( ! empty( $options['blocks'] ) ) {
			$payload['blocks'] = $options['blocks'];
		} elseif ( isset( $options['type'] ) ) {
			// Auto-generate blocks based on notification type
			$payload['blocks'] = $this->generate_blocks( $message, $options['type'] );
		}

		// Add attachments if provided
		if ( ! empty( $options['attachments'] ) ) {
			$payload['attachments'] = $this->format_attachments( $options['attachments'] );
		}

		return $payload;
	}

	/**
	 * Generate Slack blocks for rich formatting
	 *
	 * @param string $message The message.
	 * @param string $type    Notification type.
	 * @return array Slack blocks
	 */
	private function generate_blocks( $message, $type ) {
		$blocks = array();

		switch ( $type ) {
			case 'daily_summary':
				$blocks[] = array(
					'type' => 'header',
					'text' => array(
						'type'  => 'plain_text',
						'text'  => '📊 Daily Analytics Summary',
						'emoji' => true,
					),
				);

				$blocks[] = array(
					'type' => 'section',
					'text' => array(
						'type' => 'mrkdwn',
						'text' => $message,
					),
				);

				$blocks[] = array(
					'type'     => 'actions',
					'elements' => array(
						array(
							'type'  => 'button',
							'text'  => array(
								'type'  => 'plain_text',
								'text'  => 'View Dashboard',
								'emoji' => true,
							),
							'url'   => admin_url( 'admin.php?page=marketing-analytics-chat' ),
							'style' => 'primary',
						),
						array(
							'type' => 'button',
							'text' => array(
								'type'  => 'plain_text',
								'text'  => 'Export Report',
								'emoji' => true,
							),
							'url'  => admin_url( 'admin.php?page=marketing-analytics-chat&action=export' ),
						),
					),
				);
				break;

			case 'weekly_report':
				$blocks[] = array(
					'type' => 'header',
					'text' => array(
						'type'  => 'plain_text',
						'text'  => '📈 Weekly Analytics Report',
						'emoji' => true,
					),
				);

				// Parse sections from message
				$sections = explode( "\n\n", $message );
				foreach ( $sections as $section ) {
					if ( ! empty( trim( $section ) ) ) {
						$blocks[] = array(
							'type' => 'section',
							'text' => array(
								'type' => 'mrkdwn',
								'text' => $section,
							),
						);

						$blocks[] = array(
							'type' => 'divider',
						);
					}
				}
				break;

			case 'anomaly_alert':
				$blocks[] = array(
					'type' => 'header',
					'text' => array(
						'type'  => 'plain_text',
						'text'  => '⚠️ Anomaly Detected',
						'emoji' => true,
					),
				);

				$blocks[] = array(
					'type' => 'section',
					'text' => array(
						'type' => 'mrkdwn',
						'text' => $message,
					),
				);

				$blocks[] = array(
					'type'     => 'actions',
					'elements' => array(
						array(
							'type'  => 'button',
							'text'  => array(
								'type'  => 'plain_text',
								'text'  => 'Investigate',
								'emoji' => true,
							),
							'url'   => admin_url( 'admin.php?page=marketing-analytics-chat-anomalies' ),
							'style' => 'danger',
						),
					),
				);
				break;

			default:
				// Default section block
				$blocks[] = array(
					'type' => 'section',
					'text' => array(
						'type' => 'mrkdwn',
						'text' => $message,
					),
				);
		}

		// Add footer
		$blocks[] = array(
			'type'     => 'context',
			'elements' => array(
				array(
					'type' => 'mrkdwn',
					'text' => 'Sent from Marketing Analytics Chat | ' . current_time( 'g:i A' ),
				),
			),
		);

		return $blocks;
	}

	/**
	 * Format attachments for Slack
	 *
	 * @param array $attachments Raw attachments.
	 * @return array Formatted attachments
	 */
	private function format_attachments( $attachments ) {
		$formatted = array();

		foreach ( $attachments as $attachment ) {
			$slack_attachment = array(
				'fallback' => $attachment['title'] ?? 'Attachment',
				'color'    => $attachment['color'] ?? '#36a64f',
			);

			if ( isset( $attachment['title'] ) ) {
				$slack_attachment['title'] = $attachment['title'];
			}

			if ( isset( $attachment['text'] ) ) {
				$slack_attachment['text'] = $attachment['text'];
			}

			if ( isset( $attachment['fields'] ) ) {
				$slack_attachment['fields'] = array();
				foreach ( $attachment['fields'] as $key => $value ) {
					$slack_attachment['fields'][] = array(
						'title' => ucfirst( str_replace( '_', ' ', $key ) ),
						'value' => $value,
						'short' => strlen( $value ) < 20,
					);
				}
			}

			if ( isset( $attachment['image_url'] ) ) {
				$slack_attachment['image_url'] = $attachment['image_url'];
			}

			$slack_attachment['footer'] = 'Marketing Analytics';
			$slack_attachment['ts']     = time();

			$formatted[] = $slack_attachment;
		}

		return $formatted;
	}

	/**
	 * Send rich analytics report to Slack
	 *
	 * @param array $analytics_data The analytics data.
	 * @param array $options        Additional options.
	 * @return bool|WP_Error Send result
	 */
	public function send_analytics_report( $analytics_data, $options = array() ) {
		// Build rich blocks for analytics data
		$blocks = array(
			array(
				'type' => 'header',
				'text' => array(
					'type'  => 'plain_text',
					'text'  => $options['title'] ?? 'Analytics Report',
					'emoji' => true,
				),
			),
		);

		// Add metrics sections
		foreach ( $analytics_data as $platform => $data ) {
			$blocks[] = array(
				'type'   => 'section',
				'text'   => array(
					'type' => 'mrkdwn',
					'text' => "*{$platform}*",
				),
				'fields' => $this->format_metrics_as_fields( $data ),
			);

			$blocks[] = array(
				'type' => 'divider',
			);
		}

		// Add action buttons
		$blocks[] = array(
			'type'     => 'actions',
			'elements' => array(
				array(
					'type' => 'button',
					'text' => array(
						'type'  => 'plain_text',
						'text'  => 'View Full Report',
						'emoji' => true,
					),
					'url'  => admin_url( 'admin.php?page=marketing-analytics-chat' ),
				),
			),
		);

		$options['blocks'] = $blocks;
		$options['text']   = 'New analytics report available';

		return $this->send( '', $options );
	}

	/**
	 * Format metrics as Slack fields
	 *
	 * @param array $metrics The metrics.
	 * @return array Formatted fields
	 */
	private function format_metrics_as_fields( $metrics ) {
		$fields = array();

		foreach ( $metrics as $key => $value ) {
			if ( is_numeric( $value ) ) {
				$fields[] = array(
					'type' => 'mrkdwn',
					'text' => sprintf(
						'*%s*\n%s',
						ucfirst( str_replace( '_', ' ', $key ) ),
						number_format( $value )
					),
				);
			}
		}

		return array_slice( $fields, 0, 10 ); // Slack limits to 10 fields
	}

	/**
	 * Test Slack connection
	 *
	 * @return bool|WP_Error Test result
	 */
	public function test_connection() {
		return $this->send(
			':white_check_mark: Test successful! Your Slack integration is working correctly.',
			array(
				'test' => true,
			)
		);
	}

	/**
	 * Get available Slack channels
	 *
	 * Note: This requires OAuth token, not webhook
	 *
	 * @return array|WP_Error Channels list or error
	 */
	public function get_channels() {
		// This would require OAuth token and different API endpoint
		// For webhook-only setup, return default channels
		return array(
			'#general',
			'#marketing',
			'#analytics',
			'#alerts',
		);
	}
}
