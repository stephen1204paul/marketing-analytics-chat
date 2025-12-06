<?php
/**
 * DataForSEO connection settings view
 *
 * @package Marketing_Analytics_MCP
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Marketing_Analytics_MCP\Credentials\Credential_Manager;
use Marketing_Analytics_MCP\API_Clients\DataForSEO_Client;

$credential_manager = new Credential_Manager();
$has_credentials    = $credential_manager->has_credentials( 'dataforseo' );
$is_connected       = false;
$credit_balance     = 0;
$usage_stats        = array();

if ( $has_credentials ) {
	$dataforseo_client = new DataForSEO_Client();
	$is_connected      = $dataforseo_client->test_connection();

	if ( $is_connected ) {
		$credit_balance = $dataforseo_client->get_credit_balance();
		$usage_stats    = $dataforseo_client->get_usage_statistics();
	}
}

?>

<div class="connection-panel dataforseo-panel">
	<h3><?php esc_html_e( 'DataForSEO', 'marketing-analytics-chat' ); ?></h3>

	<?php if ( $is_connected ) : ?>
		<div class="notice notice-success inline">
			<p>
				<span class="dashicons dashicons-yes"></span>
				<?php esc_html_e( 'Connected to DataForSEO', 'marketing-analytics-chat' ); ?>
			</p>
		</div>

		<!-- Credit Balance Display -->
		<div class="credit-balance-display">
			<h4><?php esc_html_e( 'Account Balance', 'marketing-analytics-chat' ); ?></h4>
			<div class="balance-info <?php echo $credit_balance < 10 ? 'low-balance' : ''; ?>">
				<span class="balance-amount">$<?php echo number_format( $credit_balance, 2 ); ?></span>
				<?php if ( $credit_balance < 10 ) : ?>
					<span class="low-balance-warning">
						<span class="dashicons dashicons-warning"></span>
						<?php esc_html_e( 'Low balance', 'marketing-analytics-chat' ); ?>
					</span>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $usage_stats ) ) : ?>
				<div class="usage-stats">
					<p>
						<strong><?php esc_html_e( 'API Usage:', 'marketing-analytics-chat' ); ?></strong><br>
						<?php
						/* translators: %d: number of API requests made today */
						echo esc_html( sprintf( __( 'Today: %d requests', 'marketing-analytics-chat' ), $usage_stats['api_calls_today'] ?? 0 ) );
						?>
						<br>
						<?php
						/* translators: %s: total amount spent in dollars */
						echo esc_html( sprintf( __( 'Total Spent: $%s', 'marketing-analytics-chat' ), number_format( $usage_stats['total_spent'] ?? 0, 2 ) ) );
						?>
					</p>
				</div>
			<?php endif; ?>

			<p>
				<a href="https://app.dataforseo.com/api-dashboard/payment" target="_blank" class="button button-secondary">
					<?php esc_html_e( 'Add Credits', 'marketing-analytics-chat' ); ?>
					<span class="dashicons dashicons-external"></span>
				</a>
			</p>
		</div>

	<?php elseif ( $has_credentials ) : ?>
		<div class="notice notice-warning inline">
			<p>
				<span class="dashicons dashicons-warning"></span>
				<?php esc_html_e( 'Connection failed. Please check your credentials.', 'marketing-analytics-chat' ); ?>
			</p>
		</div>
	<?php else : ?>
		<div class="notice notice-info inline">
			<p>
				<span class="dashicons dashicons-info"></span>
				<?php esc_html_e( 'Connect DataForSEO to access SERP tracking, keyword research, and backlink analysis.', 'marketing-analytics-chat' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<div class="connection-form">
		<h4><?php esc_html_e( 'API Credentials', 'marketing-analytics-chat' ); ?></h4>
		<p>
			<?php esc_html_e( 'Enter your DataForSEO API credentials. You can find these in your', 'marketing-analytics-chat' ); ?>
			<a href="https://app.dataforseo.com/api-dashboard" target="_blank">DataForSEO dashboard</a>.
		</p>

		<form id="dataforseo-credentials-form">
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="dataforseo-login"><?php esc_html_e( 'API Login', 'marketing-analytics-chat' ); ?></label>
					</th>
					<td>
						<input type="text" id="dataforseo-login" name="login" class="regular-text"
							placeholder="<?php esc_attr_e( 'your-email@example.com', 'marketing-analytics-chat' ); ?>" />
						<p class="description"><?php esc_html_e( 'Your DataForSEO account email', 'marketing-analytics-chat' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="dataforseo-password"><?php esc_html_e( 'API Password', 'marketing-analytics-chat' ); ?></label>
					</th>
					<td>
						<input type="password" id="dataforseo-password" name="password" class="regular-text" />
						<p class="description"><?php esc_html_e( 'Your DataForSEO API password', 'marketing-analytics-chat' ); ?></p>
					</td>
				</tr>
			</table>

			<div class="api-info">
				<h5><?php esc_html_e( 'DataForSEO Features:', 'marketing-analytics-chat' ); ?></h5>
				<ul>
					<li><?php esc_html_e( 'SERP Rankings - Track keyword positions in search results', 'marketing-analytics-chat' ); ?></li>
					<li><?php esc_html_e( 'Keyword Research - Get search volume, CPC, and competition data', 'marketing-analytics-chat' ); ?></li>
					<li><?php esc_html_e( 'Backlink Analysis - Monitor your backlink profile', 'marketing-analytics-chat' ); ?></li>
					<li><?php esc_html_e( 'Competitor Analysis - Analyze competitor domains', 'marketing-analytics-chat' ); ?></li>
					<li><?php esc_html_e( 'Domain Metrics - Get comprehensive domain statistics', 'marketing-analytics-chat' ); ?></li>
				</ul>

				<h5><?php esc_html_e( 'Pricing:', 'marketing-analytics-chat' ); ?></h5>
				<ul>
					<li><?php esc_html_e( 'SERP API: ~$0.003 per keyword', 'marketing-analytics-chat' ); ?></li>
					<li><?php esc_html_e( 'Keywords API: ~$0.002 per keyword', 'marketing-analytics-chat' ); ?></li>
					<li><?php esc_html_e( 'Backlinks API: ~$0.02 per request', 'marketing-analytics-chat' ); ?></li>
					<li><?php esc_html_e( 'Domain Metrics: ~$0.01 per domain', 'marketing-analytics-chat' ); ?></li>
				</ul>
				<p>
					<a href="https://dataforseo.com/apis/pricing" target="_blank">
						<?php esc_html_e( 'View full pricing', 'marketing-analytics-chat' ); ?>
						<span class="dashicons dashicons-external"></span>
					</a>
				</p>
			</div>

			<p class="submit">
				<button type="button" class="button button-primary" id="dataforseo-save-credentials">
					<?php esc_html_e( 'Save & Test Connection', 'marketing-analytics-chat' ); ?>
				</button>
				<?php if ( $has_credentials ) : ?>
					<button type="button" class="button button-secondary" id="dataforseo-test-connection">
						<?php esc_html_e( 'Test Connection', 'marketing-analytics-chat' ); ?>
					</button>
				<?php endif; ?>
			</p>
		</form>
	</div>

	<?php if ( $is_connected ) : ?>
		<div class="connection-actions">
			<button type="button" class="button" id="dataforseo-refresh-balance">
				<?php esc_html_e( 'Refresh Balance', 'marketing-analytics-chat' ); ?>
			</button>
			<button type="button" class="button button-link-delete" id="dataforseo-disconnect">
				<?php esc_html_e( 'Disconnect', 'marketing-analytics-chat' ); ?>
			</button>
		</div>
	<?php endif; ?>

	<div id="dataforseo-test-results" style="display: none;"></div>
</div>

<style>
.credit-balance-display {
	background: #f6f7f7;
	border: 1px solid #c3c4c7;
	border-radius: 4px;
	padding: 15px;
	margin: 20px 0;
}

.balance-info {
	font-size: 24px;
	margin: 10px 0;
}

.balance-amount {
	font-weight: bold;
	color: #135e96;
}

.balance-info.low-balance .balance-amount {
	color: #d63638;
}

.low-balance-warning {
	color: #d63638;
	font-size: 14px;
	margin-left: 10px;
}

.usage-stats {
	margin-top: 10px;
	font-size: 14px;
}

.api-info {
	background: #f0f0f1;
	border-left: 4px solid #2271b1;
	padding: 12px;
	margin: 20px 0;
}

.api-info h5 {
	margin-top: 0;
	margin-bottom: 10px;
}

.api-info ul {
	margin-left: 20px;
	list-style-type: disc;
}

.api-info ul li {
	margin: 5px 0;
}
</style>

<script>
jQuery(document).ready(function($) {
	// Save credentials
	$('#dataforseo-save-credentials').on('click', function() {
		const login = $('#dataforseo-login').val();
		const password = $('#dataforseo-password').val();

		if (!login || !password) {
			alert('<?php echo esc_js( __( 'Please enter both login and password', 'marketing-analytics-chat' ) ); ?>');
			return;
		}

		const $button = $(this);
		const $results = $('#dataforseo-test-results');

		$button.prop('disabled', true).text('<?php echo esc_js( __( 'Saving and testing...', 'marketing-analytics-chat' ) ); ?>');

		$.post(ajaxurl, {
			action: 'marketing_analytics_save_credentials',
			platform: 'dataforseo',
			credentials: {
				login: login,
				password: password
			},
			nonce: '<?php echo esc_js( wp_create_nonce( 'marketing_analytics_ajax' ) ); ?>'
		}, function(response) {
			if (response.success) {
				// Test the connection
				$.post(ajaxurl, {
					action: 'marketing_analytics_test_dataforseo_connection',
					nonce: '<?php echo esc_js( wp_create_nonce( 'marketing_analytics_ajax' ) ); ?>'
				}, function(testResponse) {
					if (testResponse.success) {
						$results.html('<div class="notice notice-success"><p><?php echo esc_js( __( 'Connection successful!', 'marketing-analytics-chat' ) ); ?> ' +
							'<?php echo esc_js( __( 'Balance:', 'marketing-analytics-chat' ) ); ?> $' + testResponse.data.balance + '</p></div>').show();
						setTimeout(function() {
							location.reload();
						}, 2000);
					} else {
						$results.html('<div class="notice notice-error"><p>' +
							(testResponse.data.message || '<?php echo esc_js( __( 'Connection failed', 'marketing-analytics-chat' ) ); ?>') +
							'</p></div>').show();
					}
				});
			} else {
				$results.html('<div class="notice notice-error"><p>' +
					(response.data.message || '<?php echo esc_js( __( 'Failed to save credentials', 'marketing-analytics-chat' ) ); ?>') +
					'</p></div>').show();
			}
		}).always(function() {
			$button.prop('disabled', false).text('<?php echo esc_js( __( 'Save & Test Connection', 'marketing-analytics-chat' ) ); ?>');
		});
	});

	// Test connection
	$('#dataforseo-test-connection').on('click', function() {
		const $button = $(this);
		const $results = $('#dataforseo-test-results');

		$button.prop('disabled', true).text('<?php echo esc_js( __( 'Testing...', 'marketing-analytics-chat' ) ); ?>');

		$.post(ajaxurl, {
			action: 'marketing_analytics_test_dataforseo_connection',
			nonce: '<?php echo esc_js( wp_create_nonce( 'marketing_analytics_ajax' ) ); ?>'
		}, function(response) {
			if (response.success) {
				$results.html('<div class="notice notice-success"><p><?php echo esc_js( __( 'Connection successful!', 'marketing-analytics-chat' ) ); ?> ' +
					'<?php echo esc_js( __( 'Balance:', 'marketing-analytics-chat' ) ); ?> $' + response.data.balance + '</p></div>').show();
			} else {
				$results.html('<div class="notice notice-error"><p>' +
					(response.data.message || '<?php echo esc_js( __( 'Connection failed', 'marketing-analytics-chat' ) ); ?>') +
					'</p></div>').show();
			}
		}).always(function() {
			$button.prop('disabled', false).text('<?php echo esc_js( __( 'Test Connection', 'marketing-analytics-chat' ) ); ?>');
		});
	});

	// Refresh balance
	$('#dataforseo-refresh-balance').on('click', function() {
		const $button = $(this);

		$button.prop('disabled', true).text('<?php echo esc_js( __( 'Refreshing...', 'marketing-analytics-chat' ) ); ?>');

		$.post(ajaxurl, {
			action: 'marketing_analytics_get_dataforseo_balance',
			nonce: '<?php echo esc_js( wp_create_nonce( 'marketing_analytics_ajax' ) ); ?>'
		}, function(response) {
			if (response.success) {
				location.reload();
			} else {
				alert(response.data.message || '<?php echo esc_js( __( 'Failed to refresh balance', 'marketing-analytics-chat' ) ); ?>');
			}
		}).always(function() {
			$button.prop('disabled', false).text('<?php echo esc_js( __( 'Refresh Balance', 'marketing-analytics-chat' ) ); ?>');
		});
	});

	// Disconnect
	$('#dataforseo-disconnect').on('click', function() {
		if (!confirm('<?php echo esc_js( __( 'Are you sure you want to disconnect DataForSEO?', 'marketing-analytics-chat' ) ); ?>')) {
			return;
		}

		$.post(ajaxurl, {
			action: 'marketing_analytics_disconnect',
			platform: 'dataforseo',
			nonce: '<?php echo esc_js( wp_create_nonce( 'marketing_analytics_ajax' ) ); ?>'
		}, function(response) {
			if (response.success) {
				location.reload();
			} else {
				alert(response.data.message || '<?php echo esc_js( __( 'Failed to disconnect', 'marketing-analytics-chat' ) ); ?>');
			}
		});
	});
});
</script>