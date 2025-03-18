/**
 * Admin JavaScript for WooCommerce Custom to Global Attributes Converter
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        var $select = $('#attribute_name');
        var $button = $('input[name="convert_attributes"]');
        
        $select.on('change', function() {
            if ($(this).val()) {
                $button.prop('disabled', false);
            } else {
                $button.prop('disabled', true);
            }
        });
        
        // Initial check
        if (!$select.val()) {
            $button.prop('disabled', true);
        }
    });
})(jQuery);
