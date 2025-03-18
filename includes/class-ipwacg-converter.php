<?php
/**
 * Main class for WooCommerce Custom to Global Attributes Converter
 *
 * @package IPWACG
 */

// For security - ensure the script is called from WordPress
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class IPWACG_Converter
 * Handles the conversion of custom attributes to global attributes
 */
class IPWACG_Converter {
    /**
     * Initialize the converter
     */
    public function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Add menu item to admin panel
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            esc_html__('Convert Attributes', 'ipwacg'),
            esc_html__('Convert Attributes', 'ipwacg'),
            'manage_woocommerce',
            'ipwacg',
            array($this, 'render_admin_page')
        );
    }

    /**
     * Enqueue admin scripts
     *
     * @param string $hook_suffix The current admin page.
     */
    public function enqueue_admin_scripts($hook_suffix) {
        if ($hook_suffix !== 'woocommerce_page_custom-to-global') {
            return;
        }

        wp_enqueue_script(
            'ipwacg-admin',
            IPWACG_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            IPWACG_PLUGIN_VERSION,
            true
        );
    }

    /**
     * Function to display the admin page
     */
    public function render_admin_page() {
        // Process the form
        if (isset($_POST['convert_attributes']) && isset($_POST['attribute_name']) && !empty($_POST['attribute_name'])) {
            check_admin_referer('ipwacg_convert_attributes', 'ipwacg_nonce');
            
            $attribute_name = sanitize_text_field($_POST['attribute_name']);
            $result = $this->convert_custom_to_global($attribute_name);
            
            if (is_wp_error($result)) {
                echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
            } else {
                echo '<div class="notice notice-success"><p>' . esc_html($result) . '</p></div>';
            }
        }

        // Get all unique custom attribute names
        $custom_attributes = $this->get_unique_custom_attributes();
        $has_custom_attributes = !empty($custom_attributes);
        ?>
        <div class="wrap">
            <div class="ipwacg-container">
                <div class="ipwacg-main-content">

                    <h1><?php esc_html_e('Convert Custom Attributes to Global', 'ipwacg'); ?></h1>
                    
                    <?php if (!$has_custom_attributes): ?>
                        <div class="notice notice-warning">
                            <p><?php esc_html_e('No custom attributes found in your products. The conversion tool is currently inactive.', 'ipwacg'); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" action="">
                        <?php wp_nonce_field('ipwacg_convert_attributes', 'ipwacg_nonce'); ?>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="attribute_name"><?php esc_html_e('Select attribute to convert:', 'ipwacg'); ?></label></th>
                                <td>
                                    <select name="attribute_name" id="attribute_name" <?php echo $has_custom_attributes ? '' : 'disabled'; ?>>
                                        <option value=""><?php esc_html_e('Select an attribute', 'ipwacg'); ?></option>
                                        <?php foreach ($custom_attributes as $attr_name): ?>
                                            <option value="<?php echo esc_attr($attr_name); ?>"><?php echo esc_html($attr_name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <input type="submit" name="convert_attributes" class="button button-primary" value="<?php esc_attr_e('Convert Attribute', 'ipwacg'); ?>" <?php echo $has_custom_attributes ? '' : 'disabled'; ?>>
                        </p>
                    </form>
                    
                    <?php if (!$has_custom_attributes): ?>
                        <div class="notice notice-info">
                            <p><strong><?php esc_html_e('What to do next:', 'ipwacg'); ?></strong></p>
                            <ul>
                                <li><?php esc_html_e('Check if you already have all attributes set as global.', 'ipwacg'); ?></li>
                                <li><?php esc_html_e('If you need to create new attributes, go to Products > Attributes in the WooCommerce menu', 'ipwacg'); ?></li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>

                 <div class="ipwacg-sidebar">
                    <?php include IPWACG_PLUGIN_DIR . 'includes/sidebar.php'; ?>
                </div>

            </div>

        </div>


        <?php
    }

    /**
     * Function to get all unique custom attribute names
     *
     * @return array Array of unique custom attribute names
     */
    public function get_unique_custom_attributes() {
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

    /**
     * Function to convert custom attributes to global
     *
     * @param string $attribute_name The name of the attribute to convert
     * @return string|WP_Error Success message or error
     */
    public function convert_custom_to_global($attribute_name) {
        global $wpdb;
        
        // Check if attribute exists
        $attribute_taxonomy_name = wc_attribute_taxonomy_name($attribute_name);
        $attribute_taxonomy_id = wc_attribute_taxonomy_id_by_name($attribute_name);
        
        // If global attribute doesn't exist, create it
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
        
        return sprintf(
            /* translators: %d: number of updated products */
            esc_html__('Conversion completed! Updated products: %d.', 'ipwacg'),
            $products_updated
        );
    }
}
