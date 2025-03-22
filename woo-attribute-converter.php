<?php
/**
 * Plugin Name: IP WACG
 * Description: Converts product custom attributes to global attributes
 * Version: 1.2
 * Author: Mykola Pekarskyi
 * Text Domain: ipwacg
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 */

// For security - ensure the script is called from WordPress
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('IPWACG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IPWACG_PLUGIN_URL', plugin_dir_url(__FILE__));
define('IPWACG_PLUGIN_VERSION', '1.2');

/**
 * Add settings link to plugin page
 *
 * @param array $links Plugin action links
 * @return array Modified plugin action links
 */
function ipwacg_plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=ipwacg') . '">' . esc_html__('Settings', 'ipwacg') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'ipwacg_plugin_action_links');

/**
 * Check if WooCommerce is active
 */
function ipwacg_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p><?php esc_html_e('Plugin IP-WACG requires WooCommerce to be installed and activated.', 'ipwacg'); ?></p>
            </div>
            <?php
        });
        return false;
    }
    return true;
}

/**
 * Initialize the plugin
 */
function ipwacg_init() {
    if (ipwacg_check_woocommerce()) {
        // Include core functionality
        require_once IPWACG_PLUGIN_DIR . 'includes/class-ipwacg-converter.php';
        
        // Initialize the converter class
        $converter = new IPWACG_Converter();
        $converter->init();
    }
}
add_action('plugins_loaded', 'ipwacg_init', 20);

//CSS:Admin CSS
function ipwacg_admin_assets() {
  wp_enqueue_style('ipwacg-admin-css', IPWACG_PLUGIN_URL . 'assets/css/admin.css', '', time());
  wp_enqueue_script('ipwacg-admin-js', IPWACG_PLUGIN_URL .'assets/js/admin.js', array('jquery'), '1.0', true);
  }
  add_action('admin_init', 'ipwacg_admin_assets');

  /**
 * Load plugin text domain for translations
 */
function ipwacg_load_textdomain() {
    load_plugin_textdomain('ipwacg', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'ipwacg_load_textdomain');

/**
 * Register activation hook
 */
register_activation_hook(__FILE__, 'ipwacg_activate');

function ipwacg_activate() {
    flush_rewrite_rules();
}

/**
 * Register deactivation hook
 */
register_deactivation_hook(__FILE__, 'ipwacg_deactivate');

function ipwacg_deactivate() {
    flush_rewrite_rules();
}
