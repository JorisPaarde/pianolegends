jQuery(document).ready(function($) {
    // Show/hide options based on field type
    function toggleOptions() {
        var fieldType = $('#field_type').val();
        if (fieldType === 'dropdown' || fieldType === 'multiselect') {
            $('.options-row').show();
        } else {
            $('.options-row').hide();
        }
    }

    $('#field_type').on('change', toggleOptions);
    toggleOptions(); // Run on page load

    // Add new option
    $('.add-option').on('click', function() {
        var newRow = $('<div class="option-row">' +
            '<input type="text" name="options[]" class="regular-text" placeholder="Voer een optie in">' +
            '<button type="button" class="button remove-option">-</button>' +
            '</div>');
        $('.options-container').append(newRow);
    });

    // Remove option
    $(document).on('click', '.remove-option', function() {
        var optionRows = $('.option-row').length;
        if (optionRows > 1) {
            $(this).closest('.option-row').remove();
        } else {
            $(this).closest('.option-row').find('input').val('');
        }
    });

    // Make questions sortable
    $('.sortable-questions').sortable({
        handle: '.dashicons-menu',
        update: function(event, ui) {
            var order = [];
            $('.sortable-questions tr').each(function() {
                order.push($(this).data('id'));
            });

            $.ajax({
                url: onboardingAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'update_question_order',
                    order: order,
                    nonce: onboardingAjax.nonce // Use the nonce provided from PHP
                },
                success: function(response) {
                    if (response.success) {
                        // Optional: Show success message
                        console.log('Order updated successfully');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error updating order:', error);
                }
            });
        }
    });

    // Form submission handling
    $('form').on('submit', function(e) {
        var fieldType = $('#field_type').val();
        if ((fieldType === 'dropdown' || fieldType === 'multiselect')) {
            var options = [];
            $('input[name="options[]"]').each(function() {
                var value = $(this).val().trim();
                if (value) {
                    options.push(value);
                }
            });
            
            if (options.length === 0) {
                e.preventDefault();
                alert('Voeg ten minste één optie toe voor dropdown/multiselect velden.');
                return false;
            }
        }
    });
}); 