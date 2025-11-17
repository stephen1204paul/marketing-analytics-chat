/**
 * AI Chat Interface JavaScript
 *
 * @package Marketing_Analytics_MCP
 */

(function($) {
	'use strict';

	/**
	 * Chat Interface Object
	 */
	var ChatInterface = {

		/**
		 * Initialize chat interface
		 */
		init: function() {
			this.bindEvents();
			this.scrollToBottom();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			// New conversation buttons
			$('#new-conversation, #new-conversation-main').on('click', this.createNewConversation.bind(this));

			// Suggested prompts
			$('.suggested-prompt').on('click', this.fillSuggestedPrompt.bind(this));

			// Form submission
			$('#chat-form').on('submit', this.sendMessage.bind(this));

			// Auto-resize textarea
			$('#message-input').on('input', this.autoResizeTextarea);

			// Keyboard shortcuts
			$('#message-input').on('keydown', function(e) {
				// Cmd/Ctrl + Enter to send
				if ((e.metaKey || e.ctrlKey) && e.keyCode === 13) {
					e.preventDefault();
					$('#chat-form').submit();
				}
			});
		},

		/**
		 * Create a new conversation
		 */
		createNewConversation: function(e) {
			e.preventDefault();

			// Show loading state
			var $button = $(e.currentTarget);
			var originalText = $button.html();
			$button.prop('disabled', true).html('<span class="dashicons dashicons-update-alt"></span> Creating...');

			// Create conversation via AJAX
			$.ajax({
				url: marketingAnalyticsMCPChat.ajaxUrl,
				type: 'POST',
				data: {
					action: 'marketing_analytics_mcp_create_conversation',
					nonce: marketingAnalyticsMCPChat.nonce,
					user_id: marketingAnalyticsMCPChat.userId
				},
				success: function(response) {
					if (response.success && response.data.conversation_id) {
						// Redirect to new conversation
						window.location.href = '?page=marketing-analytics-mcp-chat&conversation_id=' + response.data.conversation_id;
					} else {
						alert('Failed to create conversation. Please try again.');
						$button.prop('disabled', false).html(originalText);
					}
				},
				error: function() {
					alert('Failed to create conversation. Please try again.');
					$button.prop('disabled', false).html(originalText);
				}
			});
		},

		/**
		 * Fill suggested prompt into input
		 */
		fillSuggestedPrompt: function(e) {
			e.preventDefault();
			var prompt = $(e.currentTarget).data('prompt');
			$('#message-input').val(prompt).focus();
		},

		/**
		 * Send message
		 */
		sendMessage: function(e) {
			e.preventDefault();

			var $form = $(e.currentTarget);
			var $input = $('#message-input');
			var $sendButton = $('#send-button');
			var message = $input.val().trim();

			if (!message) {
				return;
			}

			// Disable form
			$input.prop('disabled', true);
			$sendButton.prop('disabled', true).html('<span class="dashicons dashicons-update-alt"></span> Sending...');

			// Hide suggested prompts after first message
			$('#suggested-prompts').fadeOut();

			// Add user message to UI immediately
			this.addMessageToUI('user', message);
			$input.val('');

			// Show typing indicator
			this.showTypingIndicator();

			// Send message via AJAX
			$.ajax({
				url: marketingAnalyticsMCPChat.ajaxUrl,
				type: 'POST',
				data: {
					action: 'marketing_analytics_mcp_send_message',
					nonce: marketingAnalyticsMCPChat.nonce,
					conversation_id: marketingAnalyticsMCPChat.conversationId,
					message: message
				},
				success: function(response) {
					this.hideTypingIndicator();

					if (response.success && response.data) {
						// Add assistant response to UI
						if (response.data.content) {
							this.addMessageToUI('assistant', response.data.content, response.data.usage, response.data.tool_metadata);
						}

						// Update conversation title if this was the first message
						if (response.data.new_title) {
							this.updateConversationTitle(response.data.new_title);
						}
					} else {
						this.addMessageToUI('system', 'Sorry, I encountered an error. Please try again.');
					}

					// Re-enable form
					$input.prop('disabled', false).focus();
					$sendButton.prop('disabled', false).html('<span class="dashicons dashicons-arrow-up-alt2"></span> Send');
				}.bind(this),
				error: function() {
					this.hideTypingIndicator();
					this.addMessageToUI('system', 'Sorry, I couldn\'t send your message. Please check your connection and try again.');

					// Re-enable form
					$input.prop('disabled', false).focus();
					$sendButton.prop('disabled', false).html('<span class="dashicons dashicons-arrow-up-alt2"></span> Send');
				}.bind(this)
			});
		},

		/**
		 * Add message to UI
		 */
		addMessageToUI: function(role, content, usage, toolMetadata) {
			var $messages = $('#chat-messages');

			// Remove welcome message if present
			$messages.find('.welcome-message').remove();

			var avatarIcon = role === 'user' ? 'admin-users' : (role === 'assistant' ? 'superhero' : 'warning');
			var roleName = role === 'user' ? 'You' : (role === 'assistant' ? 'AI Assistant' : 'System');

			var usageHTML = '';
			if (usage && role === 'assistant') {
				usageHTML = '<div class="message-usage">' +
					'<span class="dashicons dashicons-chart-bar"></span> ' +
					usage.input_tokens + ' in / ' + usage.output_tokens + ' out';

				// Add tool metadata if available
				if (toolMetadata) {
					var toolsText = toolMetadata.tools_sent + ' tool' + (toolMetadata.tools_sent !== 1 ? 's' : '');
					if (toolMetadata.filtered) {
						usageHTML += ' <span class="tools-filtered" title="Filtered from ' + toolMetadata.total_available + ' total tools">' +
							'<span class="dashicons dashicons-filter"></span> ' + toolsText +
							'</span>';
					} else {
						usageHTML += ' <span class="tools-all" title="All available tools sent">' +
							'<span class="dashicons dashicons-admin-tools"></span> ' + toolsText +
							'</span>';
					}
				}

				usageHTML += '</div>';
			}

			var messageHTML = '<div class="message message-' + role + '">' +
				'<div class="message-avatar">' +
					'<span class="dashicons dashicons-' + avatarIcon + '"></span>' +
				'</div>' +
				'<div class="message-content">' +
					'<div class="message-role">' + roleName + '</div>' +
					'<div class="message-text">' + this.formatMessage(content) + '</div>' +
					usageHTML +
					'<div class="message-time">Just now</div>' +
				'</div>' +
			'</div>';

			$messages.append(messageHTML);
			this.scrollToBottom();
		},

		/**
		 * Format message content with markdown-like formatting
		 */
		formatMessage: function(content) {
			// Escape HTML first
			var formatted = $('<div>').text(content).html();

			// Headers (### text)
			formatted = formatted.replace(/^### (.+)$/gm, '<h4>$1</h4>');
			formatted = formatted.replace(/^## (.+)$/gm, '<h3>$1</h3>');
			formatted = formatted.replace(/^# (.+)$/gm, '<h2>$1</h2>');

			// Bold (**text** or __text__)
			formatted = formatted.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
			formatted = formatted.replace(/__(.+?)__/g, '<strong>$1</strong>');

			// Italic (*text* or _text_) - but not inside already bolded text
			formatted = formatted.replace(/(?<!\*)\*([^\*]+)\*(?!\*)/g, '<em>$1</em>');

			// Code blocks (```code```)
			formatted = formatted.replace(/```([^`]+)```/g, '<pre><code>$1</code></pre>');

			// Inline code (`code`)
			formatted = formatted.replace(/`([^`]+)`/g, '<code>$1</code>');

			// Unordered lists (lines starting with - or *)
			formatted = formatted.replace(/^[\-\*] (.+)$/gm, '<li>$1</li>');
			formatted = formatted.replace(/(<li>.*<\/li>)/s, '<ul>$1</ul>');

			// Checkmarks and warnings
			formatted = formatted.replace(/✓/g, '<span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>');
			formatted = formatted.replace(/⚠️|⚠/g, '<span class="dashicons dashicons-warning" style="color: #dba617;"></span>');

			// Line breaks and paragraphs
			formatted = formatted.replace(/\n\n/g, '</p><p>');
			formatted = formatted.replace(/\n/g, '<br>');

			return '<div class="formatted-content">' + formatted + '</div>';
		},

		/**
		 * Show typing indicator
		 */
		showTypingIndicator: function() {
			var $messages = $('#chat-messages');
			var typingHTML = '<div class="message message-assistant typing-indicator">' +
				'<div class="message-avatar">' +
					'<span class="dashicons dashicons-superhero"></span>' +
				'</div>' +
				'<div class="message-content">' +
					'<div class="message-role">AI Assistant</div>' +
					'<div class="message-loading">' +
						'<span></span><span></span><span></span>' +
					'</div>' +
				'</div>' +
			'</div>';

			$messages.append(typingHTML);
			this.scrollToBottom();
		},

		/**
		 * Hide typing indicator
		 */
		hideTypingIndicator: function() {
			$('#chat-messages').find('.typing-indicator').remove();
		},

		/**
		 * Scroll chat to bottom
		 */
		scrollToBottom: function() {
			var $messages = $('#chat-messages');
			if ($messages.length) {
				$messages.animate({
					scrollTop: $messages[0].scrollHeight
				}, 300);
			}
		},

		/**
		 * Auto-resize textarea
		 */
		autoResizeTextarea: function() {
			var $this = $(this);
			$this.css('height', 'auto');
			var newHeight = Math.min($this[0].scrollHeight, 200);
			$this.css('height', newHeight + 'px');
		},

		/**
		 * Update conversation title in sidebar
		 */
		updateConversationTitle: function(newTitle) {
			$('.conversation-item.active .conversation-title').text(newTitle);
		}
	};

	/**
	 * Initialize on document ready
	 */
	$(document).ready(function() {
		if ($('.marketing-analytics-mcp-chat').length) {
			ChatInterface.init();
		}
	});

})(jQuery);
