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

			// Delete conversation buttons
			$(document).on('click', '.delete-conversation', this.deleteConversation.bind(this));

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
						window.location.href = marketingAnalyticsMCPChat.chatPageUrl + '&conversation_id=' + response.data.conversation_id;
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
	 * Delete a conversation
	 */
	deleteConversation: function(e) {
		e.preventDefault();
		e.stopPropagation();

		var $button = $(e.currentTarget);
		var conversationId = $button.data('conversation-id');
		var conversationTitle = $button.data('conversation-title');

		// Confirm deletion
		if (!confirm('Are you sure you want to delete "' + conversationTitle + '"? This action cannot be undone.')) {
			return;
		}

		// Show loading state
		$button.prop('disabled', true).html('<span class="dashicons dashicons-update-alt"></span>');

		// Delete conversation via AJAX
		$.ajax({
			url: marketingAnalyticsMCPChat.ajaxUrl,
			type: 'POST',
			data: {
				action: 'marketing_analytics_mcp_delete_conversation',
				nonce: marketingAnalyticsMCPChat.nonce,
				conversation_id: conversationId
			},
			success: function(response) {
				if (response.success) {
					// If this was the active conversation, redirect to chat page without conversation
					if (parseInt(marketingAnalyticsMCPChat.conversationId) === parseInt(conversationId)) {
						window.location.href = marketingAnalyticsMCPChat.chatPageUrl;
					} else {
						// Just remove from sidebar
						$button.closest('.conversation-item').fadeOut(300, function() {
							$(this).remove();
						});
					}
				} else {
					alert(response.data.message || 'Failed to delete conversation. Please try again.');
					$button.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span>');
				}
			},
			error: function() {
				alert('Failed to delete conversation. Please try again.');
				$button.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span>');
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
						// Handle multiple messages if available (tool calls generate multiple responses)
						if (response.data.messages && response.data.messages.length > 0) {
							var self = this;
							response.data.messages.forEach(function(msg, index) {
								// Add each message to UI
								// For tool_calls message, show a brief indicator
								if (msg.tool_calls && msg.tool_calls.length > 0) {
									var toolNames = msg.tool_calls.map(function(tc) { return tc.name; }).join(', ');
									var toolIndicator = '🔧 *Using tools: ' + toolNames + '*';
									if (msg.content) {
										self.addMessageToUI('assistant', msg.content + '\n\n' + toolIndicator, null, response.data.tool_metadata);
									} else {
										self.addMessageToUI('assistant', toolIndicator, null, response.data.tool_metadata);
									}
								} else if (msg.content) {
									// Regular message or final response after tool calls
									// Use 'error' role if is_error flag is set for distinct styling
									var msgRole = msg.is_error ? 'error' : 'assistant';
									// Pass failed_tools for retry buttons on error messages
									var failedTools = msg.is_error ? response.data.failed_tools : null;
									self.addMessageToUI(msgRole, msg.content, msg.usage, index === 0 ? response.data.tool_metadata : null, failedTools);
								}
							});
						} else if (response.data.content) {
							// Fallback to single content (backward compatibility)
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
		addMessageToUI: function(role, content, usage, toolMetadata, failedTools) {
			var $messages = $('#chat-messages');
			var self = this;

			// Remove welcome message if present
			$messages.find('.welcome-message').remove();

			var avatarIcon, roleName;
			switch (role) {
				case 'user':
					avatarIcon = 'admin-users';
					roleName = 'You';
					break;
				case 'assistant':
					avatarIcon = 'superhero';
					roleName = 'AI Assistant';
					break;
				case 'error':
					avatarIcon = 'warning';
					roleName = 'AI Assistant';
					break;
				default:
					avatarIcon = 'warning';
					roleName = 'System';
			}

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

			// Build failed tools HTML with retry buttons
			var failedToolsHTML = '';
			if (failedTools && failedTools.length > 0) {
				failedToolsHTML = '<div class="failed-tools">' +
					'<div class="failed-tools-header"><span class="dashicons dashicons-warning"></span> Failed Tools:</div>' +
					'<ul class="failed-tools-list">';
				failedTools.forEach(function(tool, index) {
					var toolShortName = tool.name.split('/').pop();
					failedToolsHTML += '<li class="failed-tool-item">' +
						'<span class="tool-name">' + toolShortName + '</span>' +
						'<span class="tool-error">' + tool.error + '</span>' +
						'<button class="retry-tool-btn" data-tool-index="' + index + '" ' +
							'data-tool-name="' + tool.name + '" ' +
							'data-tool-args=\'' + JSON.stringify(tool.arguments) + '\'>' +
							'<span class="dashicons dashicons-update"></span> Retry' +
						'</button>' +
					'</li>';
				});
				failedToolsHTML += '</ul></div>';
			}

			var messageHTML = '<div class="message message-' + role + '">' +
				'<div class="message-avatar">' +
					'<span class="dashicons dashicons-' + avatarIcon + '"></span>' +
				'</div>' +
				'<div class="message-content">' +
					'<div class="message-role">' + roleName + '</div>' +
					'<div class="message-text">' + this.formatMessage(content) + '</div>' +
					failedToolsHTML +
					usageHTML +
					'<div class="message-time">Just now</div>' +
				'</div>' +
			'</div>';

			var $message = $(messageHTML);
			$messages.append($message);

			// Bind retry button click handlers
			$message.find('.retry-tool-btn').on('click', function() {
				var $btn = $(this);
				var toolName = $btn.data('tool-name');
				var toolArgs = $btn.data('tool-args');
				self.retryToolCall(toolName, toolArgs, $btn);
			});

			this.scrollToBottom();
		},

		/**
		 * Retry a failed tool call
		 */
		retryToolCall: function(toolName, toolArgs, $button) {
			var self = this;
			var originalHTML = $button.html();

			// Show loading state
			$button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Retrying...');

			$.ajax({
				url: marketingAnalyticsMCPChat.ajaxUrl,
				type: 'POST',
				data: {
					action: 'marketing_analytics_mcp_retry_tool',
					nonce: marketingAnalyticsMCPChat.nonce,
					conversation_id: marketingAnalyticsMCPChat.conversationId,
					tool_name: toolName,
					tool_arguments: JSON.stringify(toolArgs)
				},
				success: function(response) {
					if (response.success && response.data) {
						// Replace the failed tool item with success message
						var $failedItem = $button.closest('.failed-tool-item');
						$failedItem.removeClass('failed-tool-item').addClass('retry-success-item');
						$failedItem.html(
							'<span class="dashicons dashicons-yes-alt"></span> ' +
							'<span class="tool-name">' + toolName.split('/').pop() + '</span> - Success!'
						);

						// Add the result as a new message
						self.addMessageToUI('assistant', response.data.content, null, null);
					} else {
						// Show error but keep retry button
						$button.prop('disabled', false).html(originalHTML);
						alert('Retry failed: ' + (response.data ? response.data.message : 'Unknown error'));
					}
				},
				error: function() {
					$button.prop('disabled', false).html(originalHTML);
					alert('Retry failed. Please check your connection and try again.');
				}
			});
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
		 * Show typing indicator with optional status message
		 *
		 * @param {string} status Optional status message (e.g., 'thinking', 'executing_tools')
		 */
		showTypingIndicator: function(status) {
			var $messages = $('#chat-messages');
			var statusText = '';
			var statusClass = '';

			switch (status) {
				case 'executing_tools':
					statusText = 'Executing tools...';
					statusClass = 'status-tools';
					break;
				case 'processing':
					statusText = 'Processing results...';
					statusClass = 'status-processing';
					break;
				default:
					statusText = 'Thinking...';
					statusClass = 'status-thinking';
			}

			var typingHTML = '<div class="message message-assistant typing-indicator">' +
				'<div class="message-avatar">' +
					'<span class="dashicons dashicons-superhero"></span>' +
				'</div>' +
				'<div class="message-content">' +
					'<div class="message-role">AI Assistant</div>' +
					'<div class="message-loading">' +
						'<span></span><span></span><span></span>' +
					'</div>' +
					'<div class="message-status ' + statusClass + '">' + statusText + '</div>' +
				'</div>' +
			'</div>';

			$messages.append(typingHTML);
			this.scrollToBottom();
		},

		/**
		 * Update typing indicator status
		 *
		 * @param {string} status New status message
		 */
		updateTypingStatus: function(status) {
			var $indicator = $('#chat-messages').find('.typing-indicator');
			if ($indicator.length) {
				var statusText = '';
				var statusClass = '';

				switch (status) {
					case 'executing_tools':
						statusText = 'Executing tools...';
						statusClass = 'status-tools';
						break;
					case 'processing':
						statusText = 'Processing results...';
						statusClass = 'status-processing';
						break;
					default:
						statusText = 'Thinking...';
						statusClass = 'status-thinking';
				}

				$indicator.find('.message-status')
					.removeClass('status-thinking status-tools status-processing')
					.addClass(statusClass)
					.text(statusText);
			}
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
		if ($('.marketing-analytics-chat').length) {
			ChatInterface.init();
		}
	});

})(jQuery);
