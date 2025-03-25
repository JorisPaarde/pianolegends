jQuery(document).ready(function($) {
    var currentStep = 0;
    var totalSteps = $('.form-step').length;
    
    // Update progress bar
    function updateProgress() {
        var progress = (currentStep / (totalSteps - 1)) * 100;
        $('.progress-bar').css('width', progress + '%');
    }
    
    // Show/hide navigation buttons
    function updateNavigation() {
        $('.prev-step').toggle(currentStep > 0);
        $('.next-step').toggle(currentStep < totalSteps - 1);
        $('.submit-form').toggle(currentStep === totalSteps - 1);
    }
    
    // Navigate to step
    function goToStep(step) {
        $('.form-step').removeClass('active');
        $('.form-step[data-step="' + step + '"]').addClass('active');
        currentStep = step;
        updateProgress();
        updateNavigation();
    }
    
    // Next button click
    $('.next-step').on('click', function() {
        if (currentStep < totalSteps - 1) {
            goToStep(currentStep + 1);
        }
    });
    
    // Previous button click
    $('.prev-step').on('click', function() {
        if (currentStep > 0) {
            goToStep(currentStep - 1);
        }
    });
    
    // Form submission
    $('.onboarding-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = {};
        var $form = $(this);
        var $submitButton = $form.find('.submit-form');
        
        // Disable submit button and show loading state
        $submitButton.prop('disabled', true).text('Verzenden...');
        
        // Collect form data
        $('.form-step').each(function() {
            var $step = $(this);
            var questionId = $step.find('[data-question-id]').data('question-id');
            
            // Handle different input types
            if ($step.find('select').length) {
                formData[questionId] = $step.find('select').val();
            } else if ($step.find('.multiselect-container').length) {
                var selectedValues = [];
                $step.find('input[type="checkbox"]:checked').each(function() {
                    selectedValues.push($(this).val());
                });
                formData[questionId] = selectedValues;
            } else {
                formData[questionId] = $step.find('input[type="text"]').val();
            }
        });
        
        // Log form data for debugging
        console.log('Form submission data:', formData);
        
        // Submit form data via AJAX
        $.ajax({
            url: onboardingFormSettings.ajaxurl,
            type: 'POST',
            data: {
                action: 'onboarding_form_submit',
                nonce: onboardingFormSettings.nonce,
                formData: formData
            },
            success: function(response) {
                if (response.success) {
                    console.log('Form submitted successfully');
                    console.log('Debug info:', response.data.debug);
                    
                    // Show success message
                    var $successMessage = $('<div class="form-success-message">Formulier succesvol verzonden!</div>');
                    $form.fadeOut(400, function() {
                        $(this).after($successMessage);
                        $successMessage.fadeIn();
                    });
                } else {
                    console.error('Form submission failed');
                    showFormError('Er is een fout opgetreden bij het verzenden van het formulier.');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                showFormError('Er is een fout opgetreden bij het verzenden van het formulier.');
            }
        });
    });
    
    // Helper function to show error message
    function showFormError(message) {
        var $errorMessage = $('<div class="form-error-message">' + message + '</div>');
        $('.form-error-message').remove(); // Remove any existing error messages
        $('.onboarding-form').before($errorMessage);
        $('.submit-form').prop('disabled', false).text('Versturen');
    }
    
    // Initialize
    updateProgress();
    updateNavigation();
}); 