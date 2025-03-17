<?php
/**
 * Plugin Name: Woo Custom to Global Attributes Converter
 * Description: Конвертує індивідуальні атрибути товарів у глобальні атрибути
 * Version: 1.0
 * Author: Mykola Pekarskyi
 */

// Для безпеки - переконайтеся, що скрипт викликається з WordPress
if (!defined('ABSPATH')) {
    exit;
}

// Додаємо пункт меню в адмінпанель
add_action('admin_menu', 'custom_to_global_menu');

function custom_to_global_menu() {
    add_submenu_page(
        'woocommerce',
        'Attribute Converter',
        'Attribute Converter',
        'manage_woocommerce',
        'custom-to-global',
        'custom_to_global_page'
    );
}

// Функція для відображення сторінки
function custom_to_global_page() {
    // Перевіряємо, чи WooCommerce активний
    if (!class_exists('WooCommerce')) {
        echo '<div class="notice notice-error"><p>WooCommerce is not activated. Please activate WooCommerce before using this tool.</p></div>';
        return;
    }

    // Обробляємо форму
    if (isset($_POST['convert_attributes']) && isset($_POST['attribute_name']) && !empty($_POST['attribute_name'])) {
        $attribute_name = sanitize_text_field($_POST['attribute_name']);
        $result = convert_custom_to_global($attribute_name);
        
        if (is_wp_error($result)) {
            echo '<div class="notice notice-error"><p>' . $result->get_error_message() . '</p></div>';
        } else {
            echo '<div class="notice notice-success"><p>' . $result . '</p></div>';
        }
    }

    // Отримуємо всі унікальні імена індивідуальних атрибутів товарів
    $custom_attributes = get_unique_custom_attributes();
    ?>
    <div class="wrap">
        <h1>Convert Custom attributes to global</h1>
        
        <form method="post" action="">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="attribute_name">Select attribute to convert:</label></th>
                    <td>
                        <select name="attribute_name" id="attribute_name">
                            <option value="">Select attribute</option>
                            <?php foreach ($custom_attributes as $attr_name): ?>
                                <option value="<?php echo esc_attr($attr_name); ?>"><?php echo esc_html($attr_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="convert_attributes" class="button button-primary" value="Convert attribute">
            </p>
        </form>
    </div>
    <?php
}

// Функція для отримання всіх унікальних імен індивідуальних атрибутів
function get_unique_custom_attributes() {
    global $wpdb;
    
    $unique_attributes = array();
    
    // Отримуємо всі товари
    $products = wc_get_products(array(
        'limit' => -1,
        'status' => 'publish',
    ));
    
    foreach ($products as $product) {
        $product_id = $product->get_id();
        $product_attributes = get_post_meta($product_id, '_product_attributes', true);
        
        if (!empty($product_attributes)) {
            foreach ($product_attributes as $attribute_key => $attribute) {
                // Перевіряємо, чи це індивідуальний атрибут (не таксономія)
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

// Функція для конвертації індивідуальних атрибутів у глобальні
function convert_custom_to_global($attribute_name) {
    global $wpdb;
    
    // Перевірка, чи атрибут існує
    $attribute_taxonomy_name = wc_attribute_taxonomy_name($attribute_name);
    $attribute_taxonomy_id = wc_attribute_taxonomy_id_by_name($attribute_name);
    
    // Якщо глобальний атрибут уже існує, використовуємо його
    if ($attribute_taxonomy_id == 0) {
        // Створюємо новий глобальний атрибут
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
        
        // Реєструємо таксономію, щоб її можна було відразу використовувати
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
    
    // Лічильники для статистики
    $products_updated = 0;
    $values_created = 0;
    $values_collection = array();
    
    // Отримуємо всі товари
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
                // Перевіряємо, чи це індивідуальний атрибут з потрібним іменем
                if (isset($attribute['is_taxonomy']) && $attribute['is_taxonomy'] == 0 && $attribute['name'] == $attribute_name) {
                    // Зберігаємо властивості атрибута
                    $is_visible = $attribute['is_visible'];
                    $is_variation = $attribute['is_variation'];
                    $position = $attribute['position'];
                    
                    // Отримуємо значення атрибута
                    $values = explode(WC_DELIMITER, $attribute['value']);
                    $values = array_map('trim', $values);
                    
                    // Додаємо значення до колекції для створення термінів
                    foreach ($values as $value) {
                        if (!in_array($value, $values_collection)) {
                            $values_collection[] = $value;
                        }
                    }
                    
                    // Видаляємо індивідуальний атрибут
                    unset($product_attributes[$attribute_key]);
                    
                    // Створюємо новий глобальний атрибут
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
                // Оновлюємо атрибути товару
                update_post_meta($product_id, '_product_attributes', $product_attributes);
                
                // Оновлюємо значення атрибутів
                wp_set_object_terms($product_id, $values, $attribute_taxonomy_name);
                
                $products_updated++;
            }
        }
    }
    
    // Створюємо терміни для глобального атрибута
    foreach ($values_collection as $value) {
        $term = get_term_by('name', $value, $attribute_taxonomy_name);
        
        if (!$term) {
            wp_insert_term($value, $attribute_taxonomy_name);
            $values_created++;
        }
    }
    
    // Очищуємо кеш
    delete_transient('wc_attribute_taxonomies');
    
    return "Conversion completed! Products updated: $products_updated.";
}
