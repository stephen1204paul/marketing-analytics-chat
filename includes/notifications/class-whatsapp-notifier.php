<?php
/**
 * WhatsApp Notifier
 *
 * @package Marketing_Analytics_MCP
 * @subpackage Notifications
 */

namespace Marketing_Analytics_MCP\Notifications;

/**
 * Class for sending notifications via WhatsApp using Twilio
 */
class WhatsApp_Notifier {

	/**
	 * Twilio Account SID
	 *
	 * @var string
	 */
	private $account_sid;

	/**
	 * Twilio Auth Token
	 *
	 * @var string
	 */
	private $auth_token;

	/**
	 * Twilio WhatsApp number
	 *
	 * @var string
	 */
	private $from_number;

	/**
	 * Default recipient numbers
	 *
	 * @var array
	 */
	private $recipients = array();

	/**
	 * Twilio API base URL
	 *
	 * @var string
	 */
	private const TWILIO_API_URL = 'https://api.twilio.com/2010-04-01';

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->account_sid = get_option( 'marketing_analytics_twilio_account_sid' );
		$this->auth_token  = get_option( 'marketing_analytics_twilio_auth_token' );
		$this->from_number = get_option( 'marketing_analytics_twilio_whatsapp_number' );
		$this->recipients  = get_option( 'marketing_analytics_whatsapp_recipients', array() );

		// Decrypt auth token if encrypted
		if ( class_exists( 'Marketing_Analytics_MCP\\Credentials\\Encryption' ) ) {
			if ( ! empty( $this->auth_token ) ) {
				$this->auth_token = \Marketing_Analytics_MCP\Credentials\Encryption::decrypt( $this->auth_token );
			}
		}
	}

	/**
	 * Send message via WhatsApp
	 *
	 * @param string $message The message to send.
	 * @param array  $options Additional options.
	 * @return bool|WP_Error True on success, error on failure
	 */
	public function send( $message, $options = array() ) {
		if ( empty( $this->account_sid ) || empty( $this->auth_token ) || empty( $this->from_number ) ) {
			return new \WP_Error(
				'not_configured',
				__( 'WhatsApp/Twilio credentials not configured', 'marketing-analytics-chat' )
			);
		}

		$defaults = array(
			'recipients' => $this->recipients,
			'media_url'  => '',
			'template'   => false,
		);

		$options = wp_parse_args( $options, $defaults );

		// Ensure recipients is an array
		if ( ! is_array( $options['recipients'] ) ) {
			$options['recipients'] = array( $options['recipients'] );
		}

		if ( empty( $options['recipients'] ) ) {
			return new \WP_Error(
				'no_recipients',
				__( 'No WhatsApp recipients configured', 'marketing-analytics-chat' )
			);
		}

		// Format message
		$formatted_message = $this->format_message( $message, $options );

		// Send to each recipient
		$results = array();
		foreach ( $options['recipients'] as $recipient ) {
			$result                = $this->send_to_number( $recipient, $formatted_message, $options );
			$results[ $recipient ] = $result;
		}

		// Check if any sends succeeded
		$successes = array_filter(
			$results,
			function ( $r ) {
				return ! is_wp_error( $r );
			}
		);

		if ( empty( $successes ) ) {
			return new \WP_Error(
				'all_failed',
				__( 'Failed to send to all recipients', 'marketing-analytics-chat' )
			);
		}

		return true;
	}

	/**
	 * Send message to a specific number
	 *
	 * @param string $to_number The recipient number.
	 * @param string $message   The message.
	 * @param array  $options   Options.
	 * @return bool|WP_Error Send result
	 */
	private function send_to_number( $to_number, $message, $options ) {
		// Format numbers for WhatsApp (must include 'whatsapp:' prefix)
		$from = strpos( $this->from_number, 'whatsapp:' ) === 0
			? $this->from_number
			: 'whatsapp:' . $this->from_number;

		$to = strpos( $to_number, 'whatsapp:' ) === 0
			? $to_number
			: 'whatsapp:' . $to_number;

		// Build request body
		$body = array(
			'From' => $from,
			'To'   => $to,
			'Body' => $message,
		);

		// Add media if provided
		if ( ! empty( $options['media_url'] ) ) {
			$body['MediaUrl'] = $options['media_url'];
		}

		// Send via Twilio API
		$url = sprintf(
			'%s/Accounts/%s/Messages.json',
			self::TWILIO_API_URL,
			$this->account_sid
		);

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $this->account_sid . ':' . $this->auth_token ),
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => $body,
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code >= 400 ) {
			return new \WP_Error(
				'twilio_error',
				sprintf(
					/* translators: %s: error message from Twilio API */
					__( 'Twilio error: %s', 'marketing-analytics-chat' ),
					$data['message'] ?? 'Unknown error'
				)
			);
		}

		// Log successful send
		$this->log_message( $to_number, $message, $data['sid'] ?? '' );

		return true;
	}

	/**
	 * Format message for WhatsApp
	 *
	 * @param string $message The original message.
	 * @param array  $options Options.
	 * @return string Formatted message
	 */
	private function format_message( $message, $options ) {
		// Convert markdown to WhatsApp formatting
		$formatted = $message;

		// Bold: **text** or *text* -> *text*
		$formatted = preg_replace( '/\*\*(.*?)\*\*/', '*$1*', $formatted );

		// Italic: _text_ -> _text_
		// Already in WhatsApp format

		// Strikethrough: ~~text~~ -> ~text~
		$formatted = preg_replace( '/~~(.*?)~~/', '~$1~', $formatted );

		// Code: `text` -> ```text```
		$formatted = preg_replace( '/`([^`]+)`/', '```$1```', $formatted );

		// Add header if this is a report
		if ( isset( $options['type'] ) ) {
			$header = $this->get_message_header( $options['type'] );
			if ( $header ) {
				$formatted = $header . "\n\n" . $formatted;
			}
		}

		// Add footer
		$formatted .= "\n\n_Sent from Marketing Analytics MCP_";

		// Truncate if too long (WhatsApp has a 4096 character limit)
		if ( strlen( $formatted ) > 4000 ) {
			$formatted = substr( $formatted, 0, 3900 ) . "...\n\n📱 View full report in dashboard";
		}

		return $formatted;
	}

	/**
	 * Get message header based on type
	 *
	 * @param string $type Message type.
	 * @return string Header text
	 */
	private function get_message_header( $type ) {
		switch ( $type ) {
			case 'daily_summary':
				return "📊 *Daily Analytics Summary*\n" . gmdate( 'F j, Y' );

			case 'weekly_report':
				return "📈 *Weekly Analytics Report*\nWeek of " . gmdate( 'F j', strtotime( '-7 days' ) );

			case 'anomaly_alert':
				return '⚠️ *Anomaly Alert*';

			case 'test':
				return '✅ *Test Message*';

			default:
				return '';
		}
	}

	/**
	 * Send analytics report with chart image
	 *
	 * @param array $analytics_data The analytics data.
	 * @param array $options        Additional options.
	 * @return bool|WP_Error Send result
	 */
	public function send_analytics_report( $analytics_data, $options = array() ) {
		// Generate summary text
		$message = $this->generate_report_summary( $analytics_data );

		// Generate and upload chart image if data is suitable
		$chart_url = $this->generate_chart_image( $analytics_data );

		if ( $chart_url ) {
			$options['media_url'] = $chart_url;
		}

		$options['type'] = 'analytics_report';

		return $this->send( $message, $options );
	}

	/**
	 * Generate report summary text
	 *
	 * @param array $analytics_data The analytics data.
	 * @return string Summary text
	 */
	private function generate_report_summary( $analytics_data ) {
		$summary = '';

		foreach ( $analytics_data as $platform => $data ) {
			$summary .= '*' . strtoupper( $platform ) . "*\n";

			// Extract key metrics
			if ( is_array( $data ) ) {
				foreach ( array_slice( $data, 0, 5 ) as $key => $value ) {
					if ( is_numeric( $value ) ) {
						$summary .= '• ' . ucfirst( str_replace( '_', ' ', $key ) ) . ': ';
						$summary .= number_format( $value ) . "\n";
					}
				}
			}

			$summary .= "\n";
		}

		return $summary;
	}

	/**
	 * Generate chart image from data
	 *
	 * @param array $data The data.
	 * @return string|null Chart URL or null
	 */
	private function generate_chart_image( $data ) {
		// This would integrate with a chart generation service
		// like QuickChart.io or Google Charts API
		// For now, return null
		return null;

		// Example implementation:
		/*
		$chart_data = $this->prepare_chart_data( $data );
		$chart_config = array(
			'type' => 'line',
			'data' => $chart_data,
			'options' => array(
				'title' => array(
					'display' => true,
					'text' => 'Analytics Trend',
				),
			),
		);

		$url = 'https://quickchart.io/chart';
		$response = wp_remote_post( $url, array(
			'body' => wp_json_encode( array( 'chart' => wp_json_encode( $chart_config ) ) ),
		) );

		if ( ! is_wp_error( $response ) ) {
			$body = wp_remote_retrieve_body( $response );
			$result = json_decode( $body, true );
			return $result['url'] ?? null;
		}

		return null;
		*/
	}

	/**
	 * Log sent message
	 *
	 * @param string $recipient   The recipient.
	 * @param string $message     The message.
	 * @param string $message_sid Twilio message SID.
	 */
	private function log_message( $recipient, $message, $message_sid ) {
		$log = get_option( 'marketing_analytics_whatsapp_log', array() );

		array_unshift(
			$log,
			array(
				'recipient'   => $recipient,
				'message'     => substr( $message, 0, 100 ) . '...',
				'message_sid' => $message_sid,
				'sent_at'     => current_time( 'mysql' ),
			)
		);

		// Keep only last 50 messages
		$log = array_slice( $log, 0, 50 );

		update_option( 'marketing_analytics_whatsapp_log', $log );
	}

	/**
	 * Get message status from Twilio
	 *
	 * @param string $message_sid The message SID.
	 * @return array|WP_Error Message status or error
	 */
	public function get_message_status( $message_sid ) {
		if ( empty( $this->account_sid ) || empty( $this->auth_token ) ) {
			return new \WP_Error(
				'not_configured',
				__( 'Twilio credentials not configured', 'marketing-analytics-chat' )
			);
		}

		$url = sprintf(
			'%s/Accounts/%s/Messages/%s.json',
			self::TWILIO_API_URL,
			$this->account_sid,
			$message_sid
		);

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $this->account_sid . ':' . $this->auth_token ),
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		return json_decode( $body, true );
	}

	/**
	 * Test WhatsApp connection
	 *
	 * @param string $test_number Optional test number.
	 * @return bool|WP_Error Test result
	 */
	public function test_connection( $test_number = null ) {
		$recipients = $test_number ? array( $test_number ) : $this->recipients;

		if ( empty( $recipients ) ) {
			return new \WP_Error(
				'no_test_number',
				__( 'No test number provided', 'marketing-analytics-chat' )
			);
		}

		return $this->send(
			'✅ Test successful! Your WhatsApp integration is working correctly.',
			array(
				'recipients' => array( $recipients[0] ),
				'test'       => true,
			)
		);
	}

	/**
	 * Register WhatsApp template (for business accounts)
	 *
	 * @param string $template_name Template name.
	 * @param string $template_text Template text.
	 * @return bool|WP_Error Registration result
	 */
	public function register_template( $template_name, $template_text ) {
		// This would register a message template with WhatsApp Business API
		// Templates allow for sending messages outside the 24-hour window
		// Implementation depends on WhatsApp Business API setup
		return new \WP_Error(
			'not_implemented',
			__( 'Template registration requires WhatsApp Business API', 'marketing-analytics-chat' )
		);
	}

	/**
	 * Send template message
	 *
	 * @param string $template_name Template name.
	 * @param array  $parameters    Template parameters.
	 * @param array  $options       Send options.
	 * @return bool|WP_Error Send result
	 */
	public function send_template( $template_name, $parameters, $options = array() ) {
		// This would send a pre-approved template message
		// Useful for notifications outside the 24-hour window
		return new \WP_Error(
			'not_implemented',
			__( 'Template messaging requires WhatsApp Business API', 'marketing-analytics-chat' )
		);
	}
}
