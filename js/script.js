jQuery(document).ready(function($) {
    const formContainer = $('.onboarding-form-container');
    const formSteps = $('.form-step');
    const progressBar = $('.progress-bar');
    const nextButton = $('.next-step');
    const prevButton = $('.prev-step');
    const submitButton = $('.submit-form');
    
    let currentStep = 0;
    let formData = {};
    
    // Initialize form data from localStorage if available
    if (localStorage.getItem('onboardingFormData')) {
        formData = JSON.parse(localStorage.getItem('onboardingFormData'));
    }
    
    // Update progress bar
    function updateProgress() {
        const progress = ((currentStep + 1) / formSteps.length) * 100;
        progressBar.css('width', progress + '%');
    }
    
    // Save form data to localStorage
    function saveFormData() {
        localStorage.setItem('onboardingFormData', JSON.stringify(formData));
    }
    
    // Load saved data for current step
    function loadStepData() {
        const currentStepElement = formSteps.eq(currentStep);
        const questionId = currentStepElement.find('[data-question-id]').data('question-id');
        
        if (formData[questionId]) {
            const input = currentStepElement.find('[data-question-id]');
            if (input.is('select')) {
                input.val(formData[questionId]);
            } else if (input.is('input[type="text"]')) {
                input.val(formData[questionId]);
            } else if (input.is('div.multiselect-container')) {
                formData[questionId].forEach(value => {
                    input.find(`input[value="${value}"]`).prop('checked', true);
                });
            }
        }
    }
    
    // Save current step data
    function saveStepData() {
        const currentStepElement = formSteps.eq(currentStep);
        const input = currentStepElement.find('[data-question-id]');
        const questionId = input.data('question-id');
        
        if (input.is('select') || input.is('input[type="text"]')) {
            formData[questionId] = input.val();
        } else if (input.is('div.multiselect-container')) {
            formData[questionId] = input.find('input:checked').map(function() {
                return $(this).val();
            }).get();
        }
        
        saveFormData();
    }
    
    // Show/hide navigation buttons
    function updateNavigation() {
        prevButton.toggle(currentStep > 0);
        nextButton.toggle(currentStep < formSteps.length - 1);
        submitButton.toggle(currentStep === formSteps.length - 1);
    }
    
    // Animate to next step
    function nextStep() {
        saveStepData();
        
        if (currentStep < formSteps.length - 1) {
            formSteps.eq(currentStep).removeClass('active').addClass('slide-out');
            currentStep++;
            formSteps.eq(currentStep).removeClass('slide-out').addClass('active');
            loadStepData();
            updateProgress();
            updateNavigation();
        }
    }
    
    // Animate to previous step
    function prevStep() {
        if (currentStep > 0) {
            formSteps.eq(currentStep).removeClass('active').addClass('slide-out');
            currentStep--;
            formSteps.eq(currentStep).removeClass('slide-out').addClass('active');
            loadStepData();
            updateProgress();
            updateNavigation();
        }
    }
    
    // Handle form submission
    function submitForm() {
        saveStepData();
        // Here you can add AJAX call to save the form data to the server
        alert('Formulier succesvol verzonden!');
        localStorage.removeItem('onboardingFormData');
    }
    
    // Event listeners
    nextButton.on('click', nextStep);
    prevButton.on('click', prevStep);
    submitButton.on('click', submitForm);
    
    // Initialize form
    updateProgress();
    updateNavigation();
    loadStepData();
}); 