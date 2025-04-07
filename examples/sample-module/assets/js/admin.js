/**
 * Admin JavaScript for YModules Sample Module
 */
jQuery(document).ready(function($) {
    // Handle form submission
    $('#ymodules-sample-form').on('submit', function(e) {
        // Form validation can be added here
        console.log('Form submitted');
    });

    // Handle select option changes
    $('#sample_select').on('change', function() {
        console.log('Selected option: ' + $(this).val());
    });
}); 