<?php
/**
 * Export to Google Sheets
 *
 * @package Marketing_Analytics_MCP
 * @subpackage Export
 */

namespace Marketing_Analytics_MCP\Export;

use Marketing_Analytics_MCP\Utils\Logger;
use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\ValueRange;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest;
use Google\Service\Sheets\Request as SheetsRequest;

/**
 * Class for exporting analytics data to Google Sheets
 */
class Sheets_Exporter {

	/**
	 * Google Sheets service
	 *
	 * @var Sheets
	 */
	private $service;

	/**
	 * Google Client
	 *
	 * @var Client
	 */
	private $client;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->initialize_client();
	}

	/**
	 * Initialize Google Client and Sheets service
	 */
	private function initialize_client() {
		try {
			// Get credentials from WordPress options
			$credentials = get_option( 'marketing_analytics_mcp_google_credentials' );

			if ( empty( $credentials ) ) {
				throw new \Exception( 'Google credentials not configured' );
			}

			// Decrypt credentials if encrypted
			if ( class_exists( 'Marketing_Analytics_MCP\\Credentials\\Encryptor' ) ) {
				$encryptor   = new \Marketing_Analytics_MCP\Credentials\Encryptor();
				$credentials = $encryptor->decrypt( $credentials );
			}

			$this->client = new Client();
			$this->client->setAuthConfig( json_decode( $credentials, true ) );
			$this->client->addScope( Sheets::SPREADSHEETS );
			$this->client->setAccessType( 'offline' );

			// Get access token
			$access_token = get_transient( 'marketing_analytics_google_access_token' );

			if ( ! $access_token ) {
				$access_token = $this->refresh_access_token();
			}

			if ( $access_token ) {
				$this->client->setAccessToken( $access_token );
				$this->service = new Sheets( $this->client );
			}
		} catch ( \Exception $e ) {
			Logger::debug( 'Google Sheets initialization error: ' . $e->getMessage() );
			$this->service = null;
		}
	}

	/**
	 * Refresh Google access token
	 *
	 * @return array|null Access token
	 */
	private function refresh_access_token() {
		try {
			$refresh_token = get_option( 'marketing_analytics_mcp_google_refresh_token' );

			if ( empty( $refresh_token ) ) {
				return null;
			}

			$this->client->refreshToken( $refresh_token );
			$access_token = $this->client->getAccessToken();

			// Cache for 50 minutes (tokens expire in 60 minutes)
			set_transient( 'marketing_analytics_google_access_token', $access_token, 3000 );

			return $access_token;
		} catch ( \Exception $e ) {
			Logger::debug( 'Token refresh error: ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Export data to Google Sheets
	 *
	 * @param array  $data          The data to export.
	 * @param string $platform      The analytics platform.
	 * @param array  $options       Export options.
	 * @return array|WP_Error Export result with sheet URL or error
	 */
	public function export_to_sheets( $data, $platform, $options = array() ) {
		if ( ! $this->service ) {
			return new \WP_Error(
				'service_not_initialized',
				__( 'Google Sheets service not initialized', 'marketing-analytics-chat' )
			);
		}

		$defaults = array(
			'create_new'      => true,
			'spreadsheet_id'  => '',
			'sheet_name'      => ucfirst( $platform ) . ' Export',
			'include_charts'  => true,
			'format_as_table' => true,
		);

		$options = wp_parse_args( $options, $defaults );

		try {
			// Create or get spreadsheet
			if ( $options['create_new'] || empty( $options['spreadsheet_id'] ) ) {
				$spreadsheet_id = $this->create_spreadsheet( $options['sheet_name'] );
			} else {
				$spreadsheet_id = $options['spreadsheet_id'];
			}

			// Format data for sheets
			$formatted_data = $this->format_data_for_sheets( $data, $platform );

			// Write data to sheet
			$range = $options['sheet_name'] . '!A1';
			$this->write_data_to_sheet( $spreadsheet_id, $range, $formatted_data );

			// Format the sheet
			if ( $options['format_as_table'] ) {
				$this->format_as_table( $spreadsheet_id, $options['sheet_name'], $formatted_data );
			}

			// Add charts if requested
			if ( $options['include_charts'] && $this->has_chartable_data( $data ) ) {
				$this->add_charts( $spreadsheet_id, $options['sheet_name'], $data );
			}

			// Get shareable link
			$share_link = $this->get_shareable_link( $spreadsheet_id );

			// Log export
			$this->log_export( $platform, $spreadsheet_id );

			return array(
				'success'        => true,
				'spreadsheet_id' => $spreadsheet_id,
				'url'            => $share_link,
				'sheet_name'     => $options['sheet_name'],
				'rows_exported'  => count( $formatted_data ),
			);
		} catch ( \Exception $e ) {
			return new \WP_Error(
				'export_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'Failed to export to Google Sheets: %s', 'marketing-analytics-chat' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Create a new spreadsheet
	 *
	 * @param string $title The spreadsheet title.
	 * @return string Spreadsheet ID
	 * @throws \Exception On creation failure.
	 */
	private function create_spreadsheet( $title ) {
		$spreadsheet = new \Google\Service\Sheets\Spreadsheet(
			array(
				'properties' => array(
					'title' => $title . ' - ' . current_time( 'Y-m-d H:i' ),
				),
			)
		);

		$spreadsheet = $this->service->spreadsheets->create( $spreadsheet );
		return $spreadsheet->getSpreadsheetId();
	}

	/**
	 * Format data for Google Sheets
	 *
	 * @param array  $data     The raw data.
	 * @param string $platform The platform.
	 * @return array Formatted data array
	 */
	private function format_data_for_sheets( $data, $platform ) {
		$formatted = array();

		// Add metadata header
		$formatted[] = array( 'Marketing Analytics Export' );
		$formatted[] = array( 'Platform:', ucfirst( $platform ) );
		$formatted[] = array( 'Export Date:', current_time( 'Y-m-d H:i:s' ) );
		$formatted[] = array(); // Empty row

		// Handle different data structures
		if ( isset( $data['rows'] ) ) {
			// GA4/GSC format
			$headers     = $this->extract_headers_from_rows( $data );
			$formatted[] = $headers;

			foreach ( $data['rows'] as $row ) {
				$formatted[] = $this->extract_row_values( $row, $headers );
			}
		} elseif ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
			// Meta/DataForSEO format
			$formatted = array_merge( $formatted, $this->format_nested_data( $data['data'] ) );
		} else {
			// Generic format
			$formatted = array_merge( $formatted, $this->format_generic_data( $data ) );
		}

		return $formatted;
	}

	/**
	 * Extract headers from data rows
	 *
	 * @param array $data The data with rows.
	 * @return array Headers
	 */
	private function extract_headers_from_rows( $data ) {
		$headers = array();

		if ( isset( $data['dimensionHeaders'] ) ) {
			foreach ( $data['dimensionHeaders'] as $header ) {
				$headers[] = $header['name'] ?? 'Dimension';
			}
		}

		if ( isset( $data['metricHeaders'] ) ) {
			foreach ( $data['metricHeaders'] as $header ) {
				$headers[] = $header['name'] ?? 'Metric';
			}
		}

		// If no headers found, try to extract from first row
		if ( empty( $headers ) && ! empty( $data['rows'][0] ) ) {
			$headers = array_keys( $data['rows'][0] );
		}

		return ! empty( $headers ) ? $headers : array( 'Value' );
	}

	/**
	 * Extract values from a data row
	 *
	 * @param array $row     The data row.
	 * @param array $headers The headers.
	 * @return array Row values
	 */
	private function extract_row_values( $row, $headers ) {
		$values = array();

		if ( isset( $row['dimensionValues'] ) ) {
			foreach ( $row['dimensionValues'] as $value ) {
				$values[] = $value['value'] ?? '';
			}
		}

		if ( isset( $row['metricValues'] ) ) {
			foreach ( $row['metricValues'] as $value ) {
				$values[] = $value['value'] ?? 0;
			}
		}

		// If structured differently, just get values
		if ( empty( $values ) ) {
			foreach ( $headers as $header ) {
				$values[] = $row[ $header ] ?? '';
			}
		}

		return $values;
	}

	/**
	 * Format nested data structure
	 *
	 * @param array $data The nested data.
	 * @return array Formatted data
	 */
	private function format_nested_data( $data ) {
		$formatted = array();

		// Get headers from first item
		if ( ! empty( $data ) && is_array( $data ) ) {
			$first_item = reset( $data );

			if ( is_array( $first_item ) ) {
				$headers     = array_keys( $first_item );
				$formatted[] = $headers;

				foreach ( $data as $item ) {
					$row = array();
					foreach ( $headers as $header ) {
						$row[] = $item[ $header ] ?? '';
					}
					$formatted[] = $row;
				}
			}
		}

		return $formatted;
	}

	/**
	 * Format generic data
	 *
	 * @param mixed $data The data to format.
	 * @return array Formatted data
	 */
	private function format_generic_data( $data ) {
		$formatted = array();

		if ( is_array( $data ) ) {
			foreach ( $data as $key => $value ) {
				if ( is_array( $value ) ) {
					$formatted[] = array( $key );
					$formatted   = array_merge( $formatted, $this->format_generic_data( $value ) );
					$formatted[] = array(); // Empty row
				} else {
					$formatted[] = array( $key, $value );
				}
			}
		} else {
			$formatted[] = array( 'Data', $data );
		}

		return $formatted;
	}

	/**
	 * Write data to sheet
	 *
	 * @param string $spreadsheet_id The spreadsheet ID.
	 * @param string $range         The range to write to.
	 * @param array  $data          The data to write.
	 * @throws \Exception On write failure.
	 */
	private function write_data_to_sheet( $spreadsheet_id, $range, $data ) {
		$body = new ValueRange(
			array(
				'values' => $data,
			)
		);

		$params = array(
			'valueInputOption' => 'USER_ENTERED',
		);

		$this->service->spreadsheets_values->update(
			$spreadsheet_id,
			$range,
			$body,
			$params
		);
	}

	/**
	 * Format sheet as table
	 *
	 * @param string $spreadsheet_id The spreadsheet ID.
	 * @param string $sheet_name    The sheet name.
	 * @param array  $data          The data.
	 */
	private function format_as_table( $spreadsheet_id, $sheet_name, $data ) {
		try {
			$requests = array();

			// Auto-resize columns
			$requests[] = array(
				'autoResizeDimensions' => array(
					'dimensions' => array(
						'sheetId'    => 0,
						'dimension'  => 'COLUMNS',
						'startIndex' => 0,
						'endIndex'   => count( $data[0] ?? array() ),
					),
				),
			);

			// Bold header row
			$requests[] = array(
				'repeatCell' => array(
					'range'  => array(
						'sheetId'          => 0,
						'startRowIndex'    => 4, // After metadata
						'endRowIndex'      => 5,
						'startColumnIndex' => 0,
						'endColumnIndex'   => count( $data[0] ?? array() ),
					),
					'cell'   => array(
						'userEnteredFormat' => array(
							'textFormat' => array(
								'bold' => true,
							),
						),
					),
					'fields' => 'userEnteredFormat.textFormat.bold',
				),
			);

			// Apply conditional formatting for numbers
			if ( $this->has_numeric_data( $data ) ) {
				$requests[] = $this->create_conditional_formatting_request();
			}

			$batch_request = new BatchUpdateSpreadsheetRequest(
				array(
					'requests' => $requests,
				)
			);

			$this->service->spreadsheets->batchUpdate(
				$spreadsheet_id,
				$batch_request
			);
		} catch ( \Exception $e ) {
			// Formatting is optional, don't fail the export
			Logger::debug( 'Sheet formatting error: ' . $e->getMessage() );
		}
	}

	/**
	 * Check if data has chartable content
	 *
	 * @param array $data The data.
	 * @return bool True if chartable
	 */
	private function has_chartable_data( $data ) {
		// Check for time series or numeric data
		return isset( $data['rows'] ) && count( $data['rows'] ) > 1;
	}

	/**
	 * Check if data has numeric content
	 *
	 * @param array $data The data.
	 * @return bool True if has numbers
	 */
	private function has_numeric_data( $data ) {
		foreach ( $data as $row ) {
			if ( is_array( $row ) ) {
				foreach ( $row as $value ) {
					if ( is_numeric( $value ) ) {
						return true;
					}
				}
			}
		}
		return false;
	}

	/**
	 * Create conditional formatting request
	 *
	 * @return array Formatting request
	 */
	private function create_conditional_formatting_request() {
		return array(
			'addConditionalFormatRule' => array(
				'rule' => array(
					'ranges'       => array(
						array(
							'sheetId' => 0,
						),
					),
					'gradientRule' => array(
						'minpoint' => array(
							'color' => array(
								'red'   => 1,
								'green' => 0,
								'blue'  => 0,
							),
							'type'  => 'MIN',
						),
						'midpoint' => array(
							'color' => array(
								'red'   => 1,
								'green' => 1,
								'blue'  => 0,
							),
							'type'  => 'PERCENTILE',
							'value' => '50',
						),
						'maxpoint' => array(
							'color' => array(
								'red'   => 0,
								'green' => 1,
								'blue'  => 0,
							),
							'type'  => 'MAX',
						),
					),
				),
			),
		);
	}

	/**
	 * Add charts to the sheet
	 *
	 * @param string $spreadsheet_id The spreadsheet ID.
	 * @param string $sheet_name    The sheet name.
	 * @param array  $data          The data.
	 */
	private function add_charts( $spreadsheet_id, $sheet_name, $data ) {
		try {
			$requests = array();

			// Determine chart type based on data
			if ( $this->is_time_series_data( $data ) ) {
				$requests[] = $this->create_line_chart_request( $data );
			} else {
				$requests[] = $this->create_column_chart_request( $data );
			}

			if ( ! empty( $requests ) ) {
				$batch_request = new BatchUpdateSpreadsheetRequest(
					array(
						'requests' => $requests,
					)
				);

				$this->service->spreadsheets->batchUpdate(
					$spreadsheet_id,
					$batch_request
				);
			}
		} catch ( \Exception $e ) {
			// Charts are optional, don't fail the export
			Logger::debug( 'Chart creation error: ' . $e->getMessage() );
		}
	}

	/**
	 * Check if data is time series
	 *
	 * @param array $data The data.
	 * @return bool True if time series
	 */
	private function is_time_series_data( $data ) {
		// Check for date/time dimensions
		if ( isset( $data['dimensionHeaders'] ) ) {
			foreach ( $data['dimensionHeaders'] as $header ) {
				if ( stripos( $header['name'], 'date' ) !== false ||
					stripos( $header['name'], 'time' ) !== false ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Create line chart request
	 *
	 * @param array $data The data.
	 * @return array Chart request
	 */
	private function create_line_chart_request( $data ) {
		return array(
			'addChart' => array(
				'chart' => array(
					'spec'     => array(
						'title'      => 'Analytics Trend',
						'basicChart' => array(
							'chartType'      => 'LINE',
							'legendPosition' => 'BOTTOM_LEGEND',
							'axis'           => array(
								array(
									'position' => 'BOTTOM_AXIS',
									'title'    => 'Date',
								),
								array(
									'position' => 'LEFT_AXIS',
									'title'    => 'Value',
								),
							),
							'domains'        => array(
								array(
									'domain' => array(
										'sourceRange' => array(
											'sources' => array(
												array(
													'sheetId' => 0,
													'startRowIndex' => 4,
													'endRowIndex' => 50,
													'startColumnIndex' => 0,
													'endColumnIndex' => 1,
												),
											),
										),
									),
								),
							),
							'series'         => array(
								array(
									'series' => array(
										'sourceRange' => array(
											'sources' => array(
												array(
													'sheetId' => 0,
													'startRowIndex' => 4,
													'endRowIndex' => 50,
													'startColumnIndex' => 1,
													'endColumnIndex' => 2,
												),
											),
										),
									),
								),
							),
						),
					),
					'position' => array(
						'overlayPosition' => array(
							'anchorCell' => array(
								'sheetId'     => 0,
								'rowIndex'    => 2,
								'columnIndex' => 5,
							),
						),
					),
				),
			),
		);
	}

	/**
	 * Create column chart request
	 *
	 * @param array $data The data.
	 * @return array Chart request
	 */
	private function create_column_chart_request( $data ) {
		return array(
			'addChart' => array(
				'chart' => array(
					'spec'     => array(
						'title'      => 'Analytics Summary',
						'basicChart' => array(
							'chartType'      => 'COLUMN',
							'legendPosition' => 'BOTTOM_LEGEND',
							'axis'           => array(
								array(
									'position' => 'BOTTOM_AXIS',
									'title'    => 'Category',
								),
								array(
									'position' => 'LEFT_AXIS',
									'title'    => 'Value',
								),
							),
						),
					),
					'position' => array(
						'overlayPosition' => array(
							'anchorCell' => array(
								'sheetId'     => 0,
								'rowIndex'    => 2,
								'columnIndex' => 5,
							),
						),
					),
				),
			),
		);
	}

	/**
	 * Get shareable link for spreadsheet
	 *
	 * @param string $spreadsheet_id The spreadsheet ID.
	 * @return string Shareable URL
	 */
	private function get_shareable_link( $spreadsheet_id ) {
		// Make spreadsheet publicly viewable
		try {
			$drive = new \Google\Service\Drive( $this->client );

			$permission = new \Google\Service\Drive\Permission();
			$permission->setType( 'anyone' );
			$permission->setRole( 'reader' );

			$drive->permissions->create( $spreadsheet_id, $permission );
		} catch ( \Exception $e ) {
			// If sharing fails, still return the URL
			Logger::debug( 'Failed to set sheet permissions: ' . $e->getMessage() );
		}

		return "https://docs.google.com/spreadsheets/d/{$spreadsheet_id}/edit?usp=sharing";
	}

	/**
	 * Log export activity
	 *
	 * @param string $platform       The platform.
	 * @param string $spreadsheet_id The spreadsheet ID.
	 */
	private function log_export( $platform, $spreadsheet_id ) {
		$export_log = get_option( 'marketing_analytics_export_log', array() );

		array_unshift(
			$export_log,
			array(
				'platform'       => $platform,
				'spreadsheet_id' => $spreadsheet_id,
				'exported_at'    => current_time( 'mysql' ),
				'exported_by'    => wp_get_current_user()->user_login,
			)
		);

		// Keep only last 50 exports
		$export_log = array_slice( $export_log, 0, 50 );

		update_option( 'marketing_analytics_export_log', $export_log );
	}

	/**
	 * Get export history
	 *
	 * @param int $limit Number of exports to retrieve.
	 * @return array Export history
	 */
	public function get_export_history( $limit = 10 ) {
		$export_log = get_option( 'marketing_analytics_export_log', array() );
		return array_slice( $export_log, 0, $limit );
	}
}
