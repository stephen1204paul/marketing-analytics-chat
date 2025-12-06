<?php
/**
 * Custom Prompts Management View
 *
 * @package Marketing_Analytics_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Marketing_Analytics_MCP\Prompts\Prompt_Manager;

$prompt_manager = new Prompt_Manager();
$custom_prompts = $prompt_manager->get_all_prompts();
$preset_templates = $prompt_manager->get_preset_templates();

// Handle form submissions
if ( isset( $_POST['marketing_analytics_mcp_prompts_nonce'] ) && wp_verify_nonce( $_POST['marketing_analytics_mcp_prompts_nonce'], 'marketing_analytics_mcp_prompts' ) ) {
	if ( isset( $_POST['action'] ) ) {
		switch ( $_POST['action'] ) {
			case 'create_prompt':
				$prompt_data = array(
					'name'         => sanitize_text_field( $_POST['prompt_name'] ),
					'label'        => sanitize_text_field( $_POST['prompt_label'] ),
					'description'  => sanitize_textarea_field( $_POST['prompt_description'] ),
					'instructions' => wp_kses_post( $_POST['prompt_instructions'] ),
					'category'     => sanitize_text_field( $_POST['prompt_category'] ?? 'marketing-analytics' ),
					'arguments'    => isset( $_POST['prompt_arguments'] ) ? json_decode( stripslashes( $_POST['prompt_arguments'] ), true ) : array(),
				);

				$result = $prompt_manager->create_prompt( $prompt_data );

				if ( is_wp_error( $result ) ) {
					echo '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
				} else {
					echo '<div class="notice notice-success"><p>' . esc_html__( 'Prompt created successfully!', 'marketing-analytics-chat' ) . '</p></div>';
					$custom_prompts = $prompt_manager->get_all_prompts(); // Refresh list
				}
				break;

			case 'delete_prompt':
				$prompt_id = sanitize_text_field( $_POST['prompt_id'] );
				if ( $prompt_manager->delete_prompt( $prompt_id ) ) {
					echo '<div class="notice notice-success"><p>' . esc_html__( 'Prompt deleted successfully!', 'marketing-analytics-chat' ) . '</p></div>';
					$custom_prompts = $prompt_manager->get_all_prompts(); // Refresh list
				} else {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Failed to delete prompt.', 'marketing-analytics-chat' ) . '</p></div>';
				}
				break;

			case 'import_preset':
				$preset_key = sanitize_text_field( $_POST['preset_key'] );
				$result = $prompt_manager->import_preset( $preset_key );

				if ( is_wp_error( $result ) ) {
					echo '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
				} else {
					echo '<div class="notice notice-success"><p>' . esc_html__( 'Preset imported successfully!', 'marketing-analytics-chat' ) . '</p></div>';
					$custom_prompts = $prompt_manager->get_all_prompts(); // Refresh list
				}
				break;
		}
	}
}
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Custom MCP Prompts', 'marketing-analytics-chat' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Create custom prompts to guide AI assistants through complex marketing analytics workflows. Prompts can combine multiple tools and provide step-by-step instructions.', 'marketing-analytics-chat' ); ?>
	</p>

	<div class="marketing-analytics-chat-prompts-container">
		<!-- Existing Prompts -->
		<div class="card">
			<h2><?php esc_html_e( 'Your Custom Prompts', 'marketing-analytics-chat' ); ?></h2>

			<?php if ( empty( $custom_prompts ) ) : ?>
				<p class="description">
					<?php esc_html_e( 'No custom prompts yet. Create one below or import a preset template.', 'marketing-analytics-chat' ); ?>
				</p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'marketing-analytics-chat' ); ?></th>
							<th><?php esc_html_e( 'Description', 'marketing-analytics-chat' ); ?></th>
							<th><?php esc_html_e( 'Arguments', 'marketing-analytics-chat' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'marketing-analytics-chat' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $custom_prompts as $prompt_id => $prompt ) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $prompt['label'] ?? $prompt['name'] ); ?></strong>
									<br>
									<code style="font-size: 11px; color: #666;"><?php echo esc_html( $prompt_id ); ?></code>
								</td>
								<td><?php echo esc_html( $prompt['description'] ); ?></td>
								<td>
									<?php if ( ! empty( $prompt['arguments'] ) ) : ?>
										<?php echo count( $prompt['arguments'] ); ?> argument(s)
									<?php else : ?>
										<span style="color: #999;">None</span>
									<?php endif; ?>
								</td>
								<td>
									<button type="button" class="button view-prompt" data-prompt-id="<?php echo esc_attr( $prompt_id ); ?>">
										<?php esc_html_e( 'View', 'marketing-analytics-chat' ); ?>
									</button>
									<form method="post" style="display: inline;">
										<?php wp_nonce_field( 'marketing_analytics_mcp_prompts', 'marketing_analytics_mcp_prompts_nonce' ); ?>
										<input type="hidden" name="action" value="delete_prompt">
										<input type="hidden" name="prompt_id" value="<?php echo esc_attr( $prompt_id ); ?>">
										<button type="submit" class="button button-link-delete" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this prompt?', 'marketing-analytics-chat' ); ?>');">
											<?php esc_html_e( 'Delete', 'marketing-analytics-chat' ); ?>
										</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<!-- Import Preset Templates -->
		<div class="card" style="margin-top: 20px;">
			<h2><?php esc_html_e( 'Import Preset Templates', 'marketing-analytics-chat' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Quick start with pre-built prompt templates for common marketing analytics workflows.', 'marketing-analytics-chat' ); ?>
			</p>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Template', 'marketing-analytics-chat' ); ?></th>
						<th><?php esc_html_e( 'Description', 'marketing-analytics-chat' ); ?></th>
						<th><?php esc_html_e( 'Action', 'marketing-analytics-chat' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $preset_templates as $preset_key => $preset ) : ?>
						<?php
						// Check if already imported
						$already_imported = isset( $custom_prompts[ 'marketing-analytics/' . $preset['name'] ] );
						?>
						<tr>
							<td><strong><?php echo esc_html( $preset['label'] ); ?></strong></td>
							<td><?php echo esc_html( $preset['description'] ); ?></td>
							<td>
								<?php if ( $already_imported ) : ?>
									<span style="color: #999;"><?php esc_html_e( 'Already imported', 'marketing-analytics-chat' ); ?></span>
								<?php else : ?>
									<form method="post" style="display: inline;">
										<?php wp_nonce_field( 'marketing_analytics_mcp_prompts', 'marketing_analytics_mcp_prompts_nonce' ); ?>
										<input type="hidden" name="action" value="import_preset">
										<input type="hidden" name="preset_key" value="<?php echo esc_attr( $preset_key ); ?>">
										<button type="submit" class="button button-primary">
											<?php esc_html_e( 'Import', 'marketing-analytics-chat' ); ?>
										</button>
									</form>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<!-- Create New Prompt -->
		<div class="card" style="margin-top: 20px;">
			<h2><?php esc_html_e( 'Create Custom Prompt', 'marketing-analytics-chat' ); ?></h2>

			<form method="post" id="create-prompt-form">
				<?php wp_nonce_field( 'marketing_analytics_mcp_prompts', 'marketing_analytics_mcp_prompts_nonce' ); ?>
				<input type="hidden" name="action" value="create_prompt">

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="prompt_name"><?php esc_html_e( 'Prompt Name', 'marketing-analytics-chat' ); ?> *</label>
						</th>
						<td>
							<input type="text" id="prompt_name" name="prompt_name" class="regular-text" required
								pattern="[a-z0-9-]+"
								placeholder="analyze-conversion-rate"
								title="<?php esc_attr_e( 'Use only lowercase letters, numbers, and hyphens', 'marketing-analytics-chat' ); ?>">
							<p class="description">
								<?php esc_html_e( 'Unique identifier (lowercase, hyphens only). Will be prefixed with "marketing-analytics/"', 'marketing-analytics-chat' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="prompt_label"><?php esc_html_e( 'Display Label', 'marketing-analytics-chat' ); ?> *</label>
						</th>
						<td>
							<input type="text" id="prompt_label" name="prompt_label" class="regular-text" required placeholder="Analyze Conversion Rate">
							<p class="description">
								<?php esc_html_e( 'Human-readable name shown in MCP clients', 'marketing-analytics-chat' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="prompt_description"><?php esc_html_e( 'Description', 'marketing-analytics-chat' ); ?> *</label>
						</th>
						<td>
							<textarea id="prompt_description" name="prompt_description" rows="3" class="large-text" required placeholder="Analyzes conversion rate trends and identifies optimization opportunities"></textarea>
							<p class="description">
								<?php esc_html_e( 'Brief description of what this prompt does', 'marketing-analytics-chat' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="prompt_instructions"><?php esc_html_e( 'Instructions', 'marketing-analytics-chat' ); ?> *</label>
						</th>
						<td>
							<textarea id="prompt_instructions" name="prompt_instructions" rows="15" class="large-text code" required placeholder="Step-by-step instructions for the AI assistant...&#10;&#10;1. Call marketing-analytics/get-ga4-metrics&#10;2. Analyze the data&#10;3. Provide recommendations"></textarea>
							<p class="description">
								<?php esc_html_e( 'Detailed step-by-step instructions for AI. Use {{argument_name}} placeholders for dynamic values.', 'marketing-analytics-chat' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="prompt_arguments"><?php esc_html_e( 'Arguments (JSON)', 'marketing-analytics-chat' ); ?></label>
						</th>
						<td>
							<textarea id="prompt_arguments" name="prompt_arguments" rows="8" class="large-text code" placeholder='[&#10;  {&#10;    "name": "date_range",&#10;    "type": "string",&#10;    "description": "Date range to analyze",&#10;    "required": false,&#10;    "default": "7daysAgo"&#10;  }&#10;]'></textarea>
							<p class="description">
								<?php esc_html_e( 'Optional: Define arguments as JSON array. Leave empty if no arguments needed.', 'marketing-analytics-chat' ); ?>
								<br>
								<a href="#" id="show-argument-example"><?php esc_html_e( 'Show example', 'marketing-analytics-chat' ); ?></a>
							</p>
							<pre id="argument-example" style="display: none; background: #f0f0f0; padding: 10px; margin-top: 10px; overflow-x: auto;">[
  {
    "name": "conversion_event",
    "type": "string",
    "description": "GA4 event name for conversion",
    "required": true
  },
  {
    "name": "min_sessions",
    "type": "integer",
    "description": "Minimum sessions to include",
    "required": false,
    "default": 100
  }
]</pre>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Create Prompt', 'marketing-analytics-chat' ); ?>
					</button>
				</p>
			</form>
		</div>
	</div>
</div>

<!-- Modal for viewing prompt details -->
<div id="prompt-details-modal" style="display: none;">
	<div class="prompt-modal-overlay" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 100000;"></div>
	<div class="prompt-modal-content" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; max-width: 800px; max-height: 80vh; overflow-y: auto; z-index: 100001; border-radius: 4px; box-shadow: 0 5px 15px rgba(0,0,0,0.3);">
		<h2 id="modal-prompt-label"></h2>
		<p><strong><?php esc_html_e( 'ID:', 'marketing-analytics-chat' ); ?></strong> <code id="modal-prompt-id"></code></p>
		<p><strong><?php esc_html_e( 'Description:', 'marketing-analytics-chat' ); ?></strong> <span id="modal-prompt-description"></span></p>

		<h3><?php esc_html_e( 'Instructions', 'marketing-analytics-chat' ); ?></h3>
		<pre id="modal-prompt-instructions" style="background: #f5f5f5; padding: 15px; overflow-x: auto; white-space: pre-wrap;"></pre>

		<div id="modal-prompt-arguments-section">
			<h3><?php esc_html_e( 'Arguments', 'marketing-analytics-chat' ); ?></h3>
			<div id="modal-prompt-arguments"></div>
		</div>

		<button type="button" class="button" id="close-modal"><?php esc_html_e( 'Close', 'marketing-analytics-chat' ); ?></button>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	// Show argument example
	$('#show-argument-example').on('click', function(e) {
		e.preventDefault();
		$('#argument-example').slideToggle();
	});

	// View prompt modal
	$('.view-prompt').on('click', function() {
		var promptId = $(this).data('prompt-id');
		var prompts = <?php echo wp_json_encode( $custom_prompts ); ?>;
		var prompt = prompts[promptId];

		if (prompt) {
			$('#modal-prompt-label').text(prompt.label || prompt.name);
			$('#modal-prompt-id').text(promptId);
			$('#modal-prompt-description').text(prompt.description);
			$('#modal-prompt-instructions').text(prompt.instructions);

			if (prompt.arguments && prompt.arguments.length > 0) {
				var argsHtml = '<ul>';
				prompt.arguments.forEach(function(arg) {
					argsHtml += '<li><strong>' + arg.name + '</strong> (' + arg.type + ')';
					if (arg.required) {
						argsHtml += ' <span style="color: red;">*required</span>';
					}
					argsHtml += '<br><em>' + arg.description + '</em>';
					if (arg.default !== undefined) {
						argsHtml += '<br>Default: <code>' + arg.default + '</code>';
					}
					argsHtml += '</li>';
				});
				argsHtml += '</ul>';
				$('#modal-prompt-arguments').html(argsHtml);
				$('#modal-prompt-arguments-section').show();
			} else {
				$('#modal-prompt-arguments-section').hide();
			}

			$('#prompt-details-modal').fadeIn();
		}
	});

	// Close modal
	$('#close-modal, .prompt-modal-overlay').on('click', function() {
		$('#prompt-details-modal').fadeOut();
	});

	// Validate JSON before submit
	$('#create-prompt-form').on('submit', function(e) {
		var argsJson = $('#prompt_arguments').val().trim();
		if (argsJson) {
			try {
				JSON.parse(argsJson);
			} catch (error) {
				e.preventDefault();
				alert('<?php esc_html_e( 'Invalid JSON in Arguments field. Please check your syntax.', 'marketing-analytics-chat' ); ?>');
				$('#prompt_arguments').focus();
				return false;
			}
		}
	});
});
</script>

<style>
.marketing-analytics-chat-prompts-container .card {
	padding: 20px;
}

.marketing-analytics-chat-prompts-container table th {
	font-weight: 600;
}

.marketing-analytics-chat-prompts-container .button-link-delete {
	color: #b32d2e;
}

.marketing-analytics-chat-prompts-container .button-link-delete:hover {
	color: #dc3232;
}

.marketing-analytics-chat-prompts-container textarea.code {
	font-family: Consolas, Monaco, monospace;
	font-size: 13px;
}
</style>
