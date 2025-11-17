<?php
/**
 * AI Chat Interface Template
 *
 * @package Marketing_Analytics_MCP
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Marketing_Analytics_MCP\Chat\Chat_Manager;

$chat_manager = new Chat_Manager();
$user_id      = get_current_user_id();

// Get conversations
$conversations = $chat_manager->get_conversations( $user_id, 20 );

// Get active conversation
$active_conversation_id = isset( $_GET['conversation_id'] ) ? absint( $_GET['conversation_id'] ) : null;
$active_conversation    = $active_conversation_id ? $chat_manager->get_conversation( $active_conversation_id ) : null;
$messages               = $active_conversation_id ? $chat_manager->get_messages( $active_conversation_id ) : array();

?>

<div class="wrap marketing-analytics-mcp-chat">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'AI Assistant', 'marketing-analytics-mcp' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Chat with an AI assistant about your marketing analytics data. The assistant can access Clarity, Google Analytics, and Search Console data.', 'marketing-analytics-mcp' ); ?>
	</p>

	<div class="chat-container">
		<!-- Conversation Sidebar -->
		<div class="chat-sidebar">
			<div class="sidebar-header">
				<button type="button" id="new-conversation" class="button button-primary button-block">
					<span class="dashicons dashicons-plus-alt2"></span>
					<?php esc_html_e( 'New Conversation', 'marketing-analytics-mcp' ); ?>
				</button>
			</div>

			<div class="conversation-list">
				<?php if ( empty( $conversations ) ) : ?>
					<div class="no-conversations">
						<p><?php esc_html_e( 'No conversations yet. Start a new conversation!', 'marketing-analytics-mcp' ); ?></p>
					</div>
				<?php else : ?>
					<?php foreach ( $conversations as $conversation ) : ?>
						<?php
						$is_active = $active_conversation_id === (int) $conversation->id;
						$class     = $is_active ? 'conversation-item active' : 'conversation-item';
						?>
						<a href="?page=marketing-analytics-mcp-chat&conversation_id=<?php echo esc_attr( $conversation->id ); ?>" class="<?php echo esc_attr( $class ); ?>">
							<div class="conversation-title"><?php echo esc_html( $conversation->title ); ?></div>
							<div class="conversation-date"><?php echo esc_html( human_time_diff( strtotime( $conversation->updated_at ), current_time( 'timestamp' ) ) . ' ago' ); ?></div>
						</a>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>

		<!-- Chat Area -->
		<div class="chat-main">
			<?php if ( $active_conversation ) : ?>
				<!-- Message Area -->
				<div class="chat-messages" id="chat-messages">
					<?php if ( empty( $messages ) ) : ?>
						<div class="welcome-message">
							<h2><?php esc_html_e( 'Welcome to your AI Analytics Assistant!', 'marketing-analytics-mcp' ); ?></h2>
							<p><?php esc_html_e( 'Ask me anything about your marketing analytics data. I can help with:', 'marketing-analytics-mcp' ); ?></p>
							<ul>
								<li><?php esc_html_e( 'Traffic trends and performance metrics', 'marketing-analytics-mcp' ); ?></li>
								<li><?php esc_html_e( 'Search Console rankings and queries', 'marketing-analytics-mcp' ); ?></li>
								<li><?php esc_html_e( 'User behavior insights from Clarity', 'marketing-analytics-mcp' ); ?></li>
								<li><?php esc_html_e( 'Period comparisons and analysis', 'marketing-analytics-mcp' ); ?></li>
							</ul>
						</div>
					<?php else : ?>
						<?php foreach ( $messages as $message ) : ?>
							<div class="message message-<?php echo esc_attr( $message->role ); ?>">
								<div class="message-avatar">
									<?php if ( $message->role === 'user' ) : ?>
										<span class="dashicons dashicons-admin-users"></span>
									<?php elseif ( $message->role === 'assistant' ) : ?>
										<span class="dashicons dashicons-superhero"></span>
									<?php elseif ( $message->role === 'tool' ) : ?>
										<span class="dashicons dashicons-admin-tools"></span>
									<?php endif; ?>
								</div>
								<div class="message-content">
									<div class="message-role">
										<?php
										if ( $message->role === 'user' ) {
											esc_html_e( 'You', 'marketing-analytics-mcp' );
										} elseif ( $message->role === 'assistant' ) {
											esc_html_e( 'AI Assistant', 'marketing-analytics-mcp' );
										} elseif ( $message->role === 'tool' ) {
											echo esc_html( sprintf( __( 'Tool: %s', 'marketing-analytics-mcp' ), $message->tool_name ) );
										}
										?>
									</div>
									<div class="message-text">
										<?php
										if ( $message->role === 'tool' ) {
											echo '<pre>' . esc_html( $message->content ) . '</pre>';
										} else {
											echo wp_kses_post( wpautop( $message->content ) );
										}
										?>
									</div>
									<?php if ( ! empty( $message->tool_calls ) ) : ?>
										<div class="tool-calls">
											<strong><?php esc_html_e( 'Using tools:', 'marketing-analytics-mcp' ); ?></strong>
											<ul>
												<?php foreach ( $message->tool_calls as $tool_call ) : ?>
													<li>
														<span class="dashicons dashicons-admin-tools"></span>
														<?php echo esc_html( $tool_call['name'] ?? 'Unknown tool' ); ?>
													</li>
												<?php endforeach; ?>
											</ul>
										</div>
									<?php endif; ?>
									<div class="message-time">
										<?php echo esc_html( human_time_diff( strtotime( $message->created_at ), current_time( 'timestamp' ) ) . ' ago' ); ?>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>

				<!-- Message Input -->
				<div class="chat-input-container">
					<form id="chat-form" method="post">
						<?php wp_nonce_field( 'marketing-analytics-mcp-admin', 'chat_nonce' ); ?>
						<input type="hidden" name="conversation_id" value="<?php echo esc_attr( $active_conversation_id ); ?>">

						<div class="suggested-prompts" id="suggested-prompts">
							<button type="button" class="suggested-prompt" data-prompt="<?php esc_attr_e( 'Show me traffic trends for the last 30 days', 'marketing-analytics-mcp' ); ?>">
								<?php esc_html_e( 'Show me traffic trends for the last 30 days', 'marketing-analytics-mcp' ); ?>
							</button>
							<button type="button" class="suggested-prompt" data-prompt="<?php esc_attr_e( 'What are my top performing pages?', 'marketing-analytics-mcp' ); ?>">
								<?php esc_html_e( 'What are my top performing pages?', 'marketing-analytics-mcp' ); ?>
							</button>
							<button type="button" class="suggested-prompt" data-prompt="<?php esc_attr_e( 'Compare this week vs last week', 'marketing-analytics-mcp' ); ?>">
								<?php esc_html_e( 'Compare this week vs last week', 'marketing-analytics-mcp' ); ?>
							</button>
						</div>

						<div class="input-wrapper">
							<textarea
								id="message-input"
								name="message"
								placeholder="<?php esc_attr_e( 'Ask about your analytics data...', 'marketing-analytics-mcp' ); ?>"
								rows="3"
								required
							></textarea>
							<button type="submit" id="send-button" class="button button-primary">
								<span class="dashicons dashicons-arrow-up-alt2"></span>
								<?php esc_html_e( 'Send', 'marketing-analytics-mcp' ); ?>
							</button>
						</div>
					</form>
				</div>
			<?php else : ?>
				<!-- No Conversation Selected -->
				<div class="no-conversation-selected">
					<div class="empty-state">
						<span class="dashicons dashicons-format-chat"></span>
						<h2><?php esc_html_e( 'No Conversation Selected', 'marketing-analytics-mcp' ); ?></h2>
						<p><?php esc_html_e( 'Select a conversation from the sidebar or start a new one.', 'marketing-analytics-mcp' ); ?></p>
						<button type="button" id="new-conversation-main" class="button button-primary button-large">
							<span class="dashicons dashicons-plus-alt2"></span>
							<?php esc_html_e( 'Start New Conversation', 'marketing-analytics-mcp' ); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<script type="text/javascript">
	var marketingAnalyticsMCPChat = {
		ajaxUrl: '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
		nonce: '<?php echo esc_js( wp_create_nonce( 'marketing-analytics-mcp-admin' ) ); ?>',
		conversationId: <?php echo $active_conversation_id ? absint( $active_conversation_id ) : 'null'; ?>,
		userId: <?php echo absint( $user_id ); ?>
	};
</script>
