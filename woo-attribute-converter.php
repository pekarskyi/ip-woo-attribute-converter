<?php
/**
 * Plugin Name: WooCommerce Custom to Global Attributes Converter
 * Description: Converts product custom attributes to global attributes
 * Version: 1.0
 * Author: Mykola Pekarskyi
 */

// For security - ensure the script is called from WordPress
if (!defined('ABSPATH')) {
    exit;
}

// Add menu item to admin panel
add_action('admin_menu', 'custom_to_global_menu');

function custom_to_global_menu() {
    add_submenu_page(
        'woocommerce',
        'Convert Attributes',
        'Convert Attributes',
        'manage_woocommerce',
        'custom-to-global',
        'custom_to_global_page'
    );
}

// Function to display the page
function custom_to_global_page() {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        echo '<div class="notice notice-error"><p>WooCommerce is not activated. Please activate WooCommerce before using this tool.</p></div>';
        return;
    }

    // Process the form
    if (isset($_POST['convert_attributes']) && isset($_POST['attribute_name']) && !empty($_POST['attribute_name'])) {
        $attribute_name = sanitize_text_field($_POST['attribute_name']);
        $result = convert_custom_to_global($attribute_name);
        
        if (is_wp_error($result)) {
            echo '<div class="notice notice-error"><p>' . $result->get_error_message() . '</p></div>';
        } else {
            echo '<div class="notice notice-success"><p>' . $result . '</p></div>';
        }
    }

    // Get all unique custom attribute names
    $custom_attributes = get_unique_custom_attributes();
    $has_custom_attributes = !empty($custom_attributes);
    ?>
    <div class="wrap">
        <h1>Convert Custom Attributes to Global</h1>
        
        <?php if (!$has_custom_attributes): ?>
            <div class="notice notice-warning">
                <p>No custom attributes found in your products. The conversion tool is currently inactive.</p>
            </div>
        <?php endif; ?>
        
        <form method="post" action="">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="attribute_name">Select attribute to convert:</label></th>
                    <td>
                        <select name="attribute_name" id="attribute_name" <?php echo $has_custom_attributes ? '' : 'disabled'; ?>>
                            <option value="">Select an attribute</option>
                            <?php foreach ($custom_attributes as $attr_name): ?>
                                <option value="<?php echo esc_attr($attr_name); ?>"><?php echo esc_html($attr_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="convert_attributes" class="button button-primary" value="Convert Attribute" <?php echo $has_custom_attributes ? '' : 'disabled'; ?>>
            </p>
        </form>
        
        <?php if (!$has_custom_attributes): ?>
            <div class="notice notice-info">
                <p>What to do next:</p>
                <ul>
                    <li>Check if you already have all attributes set as global</li>
                    <li>If you need to create new attributes, go to Products > Attributes in the WooCommerce menu</li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

// Function to get all unique custom attribute names
function get_unique_custom_attributes() {
    global $wpdb;
    
    $unique_attributes = array();
    
    // Get all products
    $products = wc_get_products(array(
        'limit' => -1,
        'status' => 'publish',
    ));
    
    foreach ($products as $product) {
        $product_id = $product->get_id();
        $product_attributes = get_post_meta($product_id, '_product_attributes', true);
        
        if (!empty($product_attributes)) {
            foreach ($product_attributes as $attribute_key => $attribute) {
                // Check if this is a custom attribute (not taxonomy)
                if (isset($attribute['is_taxonomy']) && $attribute['is_taxonomy'] == 0) {
                    if (!in_array($attribute['name'], $unique_attributes)) {
                        $unique_attributes[] = $attribute['name'];
                    }
                }
            }
        }
    }
    
    return $unique_attributes;
}

// Function to convert custom attributes to global
function convert_custom_to_global($attribute_name) {
    global $wpdb;
    
    // Check if attribute exists
    $attribute_taxonomy_name = wc_attribute_taxonomy_name($attribute_name);
    $attribute_taxonomy_id = wc_attribute_taxonomy_id_by_name($attribute_name);
    
    // If global attribute already exists, use it
    if ($attribute_taxonomy_id == 0) {
        // Create new global attribute
        $args = array(
            'name' => $attribute_name,
            'slug' => wc_sanitize_taxonomy_name($attribute_name),
            'type' => 'select',
            'order_by' => 'menu_order',
            'has_archives' => false,
        );
        
        $result = wc_create_attribute($args);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        $attribute_taxonomy_id = $result;
        $attribute_taxonomy_name = wc_attribute_taxonomy_name($attribute_name);
        
        // Register taxonomy so it can be used immediately
        register_taxonomy(
            $attribute_taxonomy_name,
            array('product'),
            array(
                'labels' => array(
                    'name' => $attribute_name,
                ),
                'hierarchical' => true,
                'show_ui' => true,
                'query_var' => true,
            )
        );
    }
    
    // Counters for statistics
    $products_updated = 0;
    $values_created = 0;
    $values_collection = array();
    
    // Get all products
    $products = wc_get_products(array(
        'limit' => -1,
        'status' => 'publish',
    ));
    
    foreach ($products as $product) {
        $product_id = $product->get_id();
        $product_attributes = get_post_meta($product_id, '_product_attributes', true);
        
        if (!empty($product_attributes)) {
            $updated = false;
            
            foreach ($product_attributes as $attribute_key => $attribute) {
                // Check if this is a custom attribute with the desired name
                if (isset($attribute['is_taxonomy']) && $attribute['is_taxonomy'] == 0 && $attribute['name'] == $attribute_name) {
                    // Save attribute properties
                    $is_visible = $attribute['is_visible'];
                    $is_variation = $attribute['is_variation'];
                    $position = $attribute['position'];
                    
                    // Get attribute values
                    $values = explode(WC_DELIMITER, $attribute['value']);
                    $values = array_map('trim', $values);
                    
                    // Add values to collection for term creation
                    foreach ($values as $value) {
                        if (!in_array($value, $values_collection)) {
                            $values_collection[] = $value;
                        }
                    }
                    
                    // Remove custom attribute
                    unset($product_attributes[$attribute_key]);
                    
                    // Create new global attribute
                    $new_attribute_key = sanitize_title($attribute_taxonomy_name);
                    $product_attributes[$new_attribute_key] = array(
                        'name' => $attribute_taxonomy_name,
                        'value' => '',
                        'position' => $position,
                        'is_visible' => $is_visible,
                        'is_variation' => $is_variation,
                        'is_taxonomy' => 1
                    );
                    
                    $updated = true;
                }
            }
            
            if ($updated) {
                // Update product attributes
                update_post_meta($product_id, '_product_attributes', $product_attributes);
                
                // Update attribute values
                wp_set_object_terms($product_id, $values, $attribute_taxonomy_name);
                
                $products_updated++;
            }
        }
    }
    
    // Create terms for global attribute
    foreach ($values_collection as $value) {
        $term = get_term_by('name', $value, $attribute_taxonomy_name);
        
        if (!$term) {
            wp_insert_term($value, $attribute_taxonomy_name);
            $values_created++;
        }
    }
    
    // Clear cache
    delete_transient('wc_attribute_taxonomies');
    
    return "Conversion completed! Updated products: $products_updated.";
}

// Add JavaScript to disable the form if no attributes are selected
add_action('admin_footer', 'custom_to_global_script');

function custom_to_global_script() {
    $screen = get_current_screen();
    
    if ($screen->id !== 'woocommerce_page_custom-to-global') {
        return;
    }
    
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
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
    </script>
    <?php
}
