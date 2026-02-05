(function($) {
	'use strict';

	let currentStep = 1;
	const totalSteps = 6;

	// Initialize wizard on document ready
	$(document).ready(function() {
		// Only initialize if wizard container exists
		if ($('.wizard-container').length === 0) {
			return;
		}

		initWizard();
		setupEventListeners();
		loadSavedProgress();
	});

	function initWizard() {
		// Show only first step
		$('.wizard-step').not('[data-step="1"]').hide();

		// Update progress bar
		updateProgressBar();

		// Check if credentials already exist
		if ($('#google_client_id').val()) {
			// Jump to step 6 if credentials exist
			goToStep(6);
		}
	}

	function setupEventListeners() {
		// Navigation buttons
		$('.wizard-next').on('click', handleNextStep);
		$('.wizard-prev').on('click', handlePrevStep);

		// Step confirmations
		$('input[type="checkbox"][id^="step"]').on('change', handleStepConfirmation);

		// API enable checkboxes
		$('.api-enabled-checkbox').on('change', checkAllAPIsEnabled);

		// Credential validation
		$('#google_client_id, #google_client_secret').on('blur', validateCredentialField);

		// Form submission
		$('#credentials-form').on('submit', handleCredentialSave);

		// Copy buttons
		$('.copy-btn').on('click', handleCopyToClipboard);
		$('.copy-redirect-uri').on('click', copyRedirectURI);

		// Password toggle
		$('.toggle-password').on('click', togglePasswordVisibility);

		// Video toggle
		$('.toggle-video').on('click', toggleVideo);

		// Progress step clicks (allow jumping to completed steps)
		$('.progress-step').on('click', handleProgressStepClick);
	}

	function goToStep(stepNumber) {
		if (stepNumber < 1 || stepNumber > totalSteps) return;

		// Hide current step
		$(`.wizard-step[data-step="${currentStep}"]`).slideUp(300);

		// Show target step
		setTimeout(function() {
			$(`.wizard-step[data-step="${stepNumber}"]`).slideDown(300);

			// Update active states
			$('.wizard-step').removeClass('active');
			$(`.wizard-step[data-step="${stepNumber}"]`).addClass('active');

			$('.progress-step').removeClass('active');
			$(`.progress-step[data-step="${stepNumber}"]`).addClass('active');

			currentStep = stepNumber;

			// Update navigation buttons
			updateNavigationButtons();

			// Update progress bar
			updateProgressBar();

			// Save progress
			saveProgress();

			// Scroll to wizard
			$('html, body').animate({
				scrollTop: $('.wizard-container').offset().top - 32
			}, 300);
		}, 300);
	}

	function handleNextStep() {
		// Validate current step before proceeding
		if (!validateStep(currentStep)) {
			showValidationError(currentStep);
			return;
		}

		// Mark current step as complete
		markStepComplete(currentStep);

		// Go to next step
		goToStep(currentStep + 1);
	}

	function handlePrevStep() {
		goToStep(currentStep - 1);
	}

	function validateStep(stepNumber) {
		switch(stepNumber) {
			case 1:
				return $('#step1-complete').is(':checked');
			case 2:
				return $('.api-enabled-checkbox:checked').length === 3;
			case 3:
				return $('#step3-complete').is(':checked');
			case 4:
				return $('#step4-complete').is(':checked');
			case 5:
				// Validate credential format
				const clientId = $('#google_client_id').val();
				const clientSecret = $('#google_client_secret').val();
				const hasExisting = $('#google_client_secret').attr('placeholder').includes('new secret');
				return validateClientIdFormat(clientId) &&
					   (validateClientSecretFormat(clientSecret) || hasExisting);
			default:
				return true;
		}
	}

	function showValidationError(stepNumber) {
		let message = 'Please complete this step before continuing.';

		if (stepNumber === 2) {
			message = 'Please enable all 3 APIs before continuing.';
			$('.apis-remaining').slideDown();
		}

		// Show error notice
		const $notice = $('<div class="notice notice-error inline"><p>' + message + '</p></div>');
		$(`.wizard-step[data-step="${stepNumber}"] .step-content`).prepend($notice);

		setTimeout(function() {
			$notice.slideUp(300, function() { $(this).remove(); });
		}, 3000);
	}

	function markStepComplete(stepNumber) {
		$(`.progress-step[data-step="${stepNumber}"]`).addClass('completed');
		$(`.wizard-step[data-step="${stepNumber}"]`).addClass('completed');

		// Add checkmark to step status
		const $status = $(`.wizard-step[data-step="${stepNumber}"] .step-status`);
		$status.html('<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>');
	}

	function updateProgressBar() {
		const progress = (currentStep / totalSteps) * 100;
		$('.progress-fill').css('width', progress + '%');
	}

	function updateNavigationButtons() {
		// Previous button
		if (currentStep === 1) {
			$('.wizard-prev').prop('disabled', true);
		} else {
			$('.wizard-prev').prop('disabled', false);
		}

		// Next button
		if (currentStep === totalSteps) {
			$('.wizard-next').hide();
		} else {
			$('.wizard-next').show();
		}
	}

	function handleStepConfirmation() {
		const stepNumber = parseInt($(this).attr('id').match(/\d+/)[0]);

		if ($(this).is(':checked')) {
			// Enable next button
			$('.wizard-next').prop('disabled', false);
		}
	}

	function checkAllAPIsEnabled() {
		const enabledCount = $('.api-enabled-checkbox:checked').length;

		if (enabledCount === 3) {
			$('.apis-remaining').slideUp();
			$('.wizard-next').prop('disabled', false);
		} else {
			$('.apis-remaining').slideDown();
		}
	}

	function validateCredentialField() {
		const $field = $(this);
		const value = $field.val().trim();
		const $feedback = $field.closest('td').find('.validation-feedback');

		if (!value) {
			$feedback.empty();
			return;
		}

		let isValid = false;
		let message = '';

		if ($field.attr('id') === 'google_client_id') {
			isValid = validateClientIdFormat(value);
			message = isValid ?
				'<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> Valid Client ID format' :
				'<span class="dashicons dashicons-dismiss" style="color: #dc3232;"></span> Invalid format (must end with .apps.googleusercontent.com)';
		} else if ($field.attr('id') === 'google_client_secret') {
			isValid = validateClientSecretFormat(value);
			message = isValid ?
				'<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> Valid Client Secret format' :
				'<span class="dashicons dashicons-dismiss" style="color: #dc3232;"></span> Invalid format (must start with GOCSPX-)';
		}

		$feedback.html(message).css('color', isValid ? '#46b450' : '#dc3232');
	}

	function validateClientIdFormat(clientId) {
		return /^[0-9]+-[a-z0-9]+\.apps\.googleusercontent\.com$/.test(clientId);
	}

	function validateClientSecretFormat(clientSecret) {
		return clientSecret.startsWith('GOCSPX-');
	}

	function handleCredentialSave(e) {
		e.preventDefault();

		const clientId = $('#google_client_id').val().trim();
		const clientSecret = $('#google_client_secret').val().trim();

		// Validate
		if (!validateClientIdFormat(clientId)) {
			alert('Invalid Client ID format');
			return;
		}

		// Client secret optional if already saved
		const hasExisting = $('#google_client_secret').attr('placeholder').includes('new secret');
		if (clientSecret && !validateClientSecretFormat(clientSecret)) {
			alert('Invalid Client Secret format');
			return;
		}

		if (!clientSecret && !hasExisting) {
			alert('Client Secret is required for initial setup');
			return;
		}

		// Show spinner
		$('#save-credentials-btn').prop('disabled', true);
		$('.spinner').addClass('is-active');

		// AJAX validate credentials (optional - can skip for faster UX)
		// For now, just submit the form directly
		$('#credentials-form')[0].submit();
	}

	function handleCopyToClipboard() {
		const textToCopy = $(this).data('copy');
		copyToClipboard(textToCopy, $(this));
	}

	function copyRedirectURI() {
		const uri = $('#redirect-uri-display').text().trim();
		copyToClipboard(uri, $(this));
	}

	function copyToClipboard(text, $button) {
		navigator.clipboard.writeText(text).then(function() {
			const originalHTML = $button.html();
			$button.html('<span class="dashicons dashicons-yes"></span> Copied!');
			$button.css('color', '#46b450');

			setTimeout(function() {
				$button.html(originalHTML);
				$button.css('color', '');
			}, 2000);
		}).catch(function() {
			alert('Failed to copy. Please copy manually.');
		});
	}

	function togglePasswordVisibility() {
		const $input = $('#google_client_secret');
		const $icon = $(this).find('.dashicons');

		if ($input.attr('type') === 'password') {
			$input.attr('type', 'text');
			$icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
		} else {
			$input.attr('type', 'password');
			$icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
		}
	}

	function toggleVideo() {
		$('.video-embed').slideToggle(300);
		const $btn = $(this);
		const isVisible = $('.video-embed').is(':visible');
		$btn.text(isVisible ? 'Hide Video Tutorial' : 'Show Video Tutorial');
	}

	function handleProgressStepClick() {
		const stepNumber = parseInt($(this).data('step'));

		// Only allow jumping to completed steps or next step
		if ($(this).hasClass('completed') || stepNumber === currentStep + 1) {
			goToStep(stepNumber);
		}
	}

	function saveProgress() {
		if (!window.localStorage) return;

		const progress = {
			currentStep: currentStep,
			completedSteps: [],
			apiCheckboxes: {}
		};

		// Save completed steps
		$('.wizard-step.completed').each(function() {
			progress.completedSteps.push(parseInt($(this).data('step')));
		});

		// Save API checkboxes
		$('.api-enabled-checkbox').each(function() {
			progress.apiCheckboxes[$(this).data('api')] = $(this).is(':checked');
		});

		localStorage.setItem('wizard_progress_google_oauth', JSON.stringify(progress));
	}

	function loadSavedProgress() {
		if (!window.localStorage) return;

		const saved = localStorage.getItem('wizard_progress_google_oauth');
		if (!saved) return;

		try {
			const progress = JSON.parse(saved);

			// Restore API checkboxes
			if (progress.apiCheckboxes) {
				$.each(progress.apiCheckboxes, function(api, checked) {
					$(`.api-enabled-checkbox[data-api="${api}"]`).prop('checked', checked);
				});
			}

			// Restore completed steps
			if (progress.completedSteps) {
				progress.completedSteps.forEach(function(stepNum) {
					markStepComplete(stepNum);
				});
			}

			// Don't auto-restore current step - let user decide
		} catch (e) {
			console.error('Failed to load wizard progress', e);
		}
	}

})(jQuery);
