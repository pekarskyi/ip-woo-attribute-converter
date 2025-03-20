/**
 * Admin JavaScript for WooCommerce Custom to Global Attributes Converter
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        var $attributeCheckboxes = $('.attribute-checkbox');
        var $selectAllCheckbox = $('#select_all_attributes');
        var $convertButton = $('input[name="convert_attributes"]');
        
        // Function to update the convert button state
        function updateConvertButtonState() {
            var atLeastOneChecked = false;
            $attributeCheckboxes.each(function() {
                if ($(this).prop('checked')) {
                    atLeastOneChecked = true;
                    return false; // Break the loop
                }
            });
            
            $convertButton.prop('disabled', !atLeastOneChecked);
        }
        
        // Handle selecting/deselecting all attributes
        $selectAllCheckbox.on('change', function() {
            var isChecked = $(this).prop('checked');
            $attributeCheckboxes.prop('checked', isChecked);
            updateConvertButtonState();
        });
        
        // Handle individual attribute checkbox changes
        $attributeCheckboxes.on('change', function() {
            updateConvertButtonState();
            
            // Update "Select All" checkbox
            var allChecked = true;
            $attributeCheckboxes.each(function() {
                if (!$(this).prop('checked')) {
                    allChecked = false;
                    return false; // Break the loop
                }
            });
            
            $selectAllCheckbox.prop('checked', allChecked);
        });
        
        // Initial check
        updateConvertButtonState();
    });
})(jQuery);