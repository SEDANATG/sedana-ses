/**
 * SEDANA SES Admin JavaScript
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Show/hide fields based on connection type
        function toggleConnectionFields() {
            var connectionType = $('#connection_type').val();
            
            if (connectionType === 'api') {
                $('.api-field').show();
                $('.smtp-field').hide();
            } else {
                $('.api-field').hide();
                $('.smtp-field').show();
            }
        }
        
        // Initialize field visibility
        toggleConnectionFields();
        
        // Listen for connection type changes
        $('#connection_type').on('change', toggleConnectionFields);
        
        // Test connection button
        $('#test-connection').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $result = $('#test-result');
            
            // Get current values from the form
            var connectionType = $('#connection_type').val();
            var formData = {
                action: 'sedana_ses_test_connection',
                nonce: sedana_ses.nonce,
                connection_type: connectionType
            };
            
            // Add the appropriate fields based on connection type
            if (connectionType === 'api') {
                formData.aws_region = $('#aws_region').val();
                formData.aws_access_key = $('#aws_access_key').val();
                formData.aws_secret_key = $('#aws_secret_key').val();
            } else {
                formData.smtp_host = $('#smtp_host').val();
                formData.smtp_port = $('#smtp_port').val();
                formData.smtp_secure = $('#smtp_secure').val();
                formData.smtp_username = $('#smtp_username').val();
                formData.smtp_password = $('#smtp_password').val();
            }
            
            // Disable button and show testing message
            $button.prop('disabled', true);
            $result.removeClass('success error').text(sedana_ses.testing);
            
            // Send AJAX request
            $.post(sedana_ses.ajax_url, formData, function(response) {
                if (response.success) {
                    $result.addClass('success').removeClass('error').text(sedana_ses.success);
                } else {
                    $result.addClass('error').removeClass('success').text(sedana_ses.error + response.data);
                }
                
                // Re-enable button
                $button.prop('disabled', false);
            }).fail(function() {
                $result.addClass('error').removeClass('success').text(sedana_ses.error + 'AJAX request failed.');
                $button.prop('disabled', false);
            });
        });
    });
})(jQuery);