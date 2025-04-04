<?php
/**
 * Plugin Name: IP Woo Attributes Converter
 * Description: Converts product custom attributes to global attributes
 * Version: 1.3.1
 * Author: InwebPress
 * Plugin URI: https://github.com/pekarskyi/ip-woo-attribute-converter
 * Author URI: https://inwebpress.com
 * Text Domain: ipwacg
 * Domain Path: /languages
 * Requires at least: 6.7.0
 * Tested up to: 6.7.2
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.7.1
 */

// For security - ensure the script is called from WordPress
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('IPWACG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IPWACG_PLUGIN_URL', plugin_dir_url(__FILE__));
define('IPWACG_PLUGIN_VERSION', get_file_data(__FILE__, array('Version' => 'Version'), 'plugin')['Version']);

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
  wp_enqueue_style('ipwacg-admin-css', IPWACG_PLUGIN_URL . 'assets/css/admin.css', '', IPWACG_PLUGIN_VERSION);
  wp_enqueue_script('ipwacg-admin-js', IPWACG_PLUGIN_URL .'assets/js/admin.js', array('jquery'), IPWACG_PLUGIN_VERSION, true);
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

// Adding update check via GitHub
require_once plugin_dir_path( __FILE__ ) . 'updates/github-updater.php';
if ( function_exists( 'ip_woo_attribute_converter_github_updater_init' ) ) {
    ip_woo_attribute_converter_github_updater_init(
        __FILE__,       // Plugin file path
        'pekarskyi',     // Your GitHub username
        '',              // Access token (empty)
        'ip-woo-attribute-converter' // Repository name (optional)
        // Other parameters are determined automatically
    );
}

// Adding update check via GitHub
require_once plugin_dir_path( __FILE__ ) . 'updates/github-updater.php';

$github_username = 'pekarskyi'; // Вказуємо ім'я користувача GitHub
$repo_name = 'ip-woo-attribute-converter'; // Вказуємо ім'я репозиторію GitHub, наприклад ip-wp-github-updater
$prefix = 'ip_woo_attribute_converter'; // Встановлюємо унікальний префікс плагіну, наприклад ip_wp_github_updater

// Ініціалізуємо систему оновлення плагіну з GitHub
if ( function_exists( 'ip_github_updater_load' ) ) {
    // Завантажуємо файл оновлювача з нашим префіксом
    ip_github_updater_load($prefix);
    
    // Формуємо назву функції оновлення з префіксу
    $updater_function = $prefix . '_github_updater_init';   
    
    // Після завантаження наша функція оновлення повинна бути доступна
    if ( function_exists( $updater_function ) ) {
        call_user_func(
            $updater_function,
            __FILE__,       // Plugin file path
            $github_username, // Your GitHub username
            '',              // Access token (empty)
            $repo_name       // Repository name (на основі префіксу)
        );
    }
} 