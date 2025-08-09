<?php
/**
 * Plugin Name: Blog Importer for HubSpot
 * Plugin URI: https://wordpress.org/plugins/blog-importer-for-hubspot/
 * Description: Seamlessly import and manage HubSpot blog posts in WordPress.
 * Version: 1.0.0
 * Author: Priyank Sukhadiya
 * Author URI: https://profiles.wordpress.org/priyanksukhadiya/
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: blog-importer-for-hubspot
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit;

// Constants
define('BIFH_VERSION', '1.0.0');
define('BIFH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BIFH_PLUGIN_URL', plugin_dir_url(__FILE__));

define('BIFH_OPTION_API_KEY', 'bifh_hubspot_api_key');
define('BIFH_OPTION_SELECTED_BLOG_ID', 'bifh_selected_blog_id');
define('BIFH_OPTION_POST_TYPE', 'bifh_post_type');
define('BIFH_OPTION_POST_STATUS', 'bifh_post_status');
define('BIFH_OPTION_SYNC_ENABLED', 'bifh_enable_sync');
define('BIFH_OPTION_SYNC_INTERVAL', 'bifh_sync_interval');

define('BIFH_META_HUBSPOT_ID', '_hubspot_post_id');

// Includes
require_once BIFH_PLUGIN_DIR . 'admin/settings-page.php';
require_once BIFH_PLUGIN_DIR . 'admin/logs-page.php';
require_once BIFH_PLUGIN_DIR . 'admin/admin-functions.php';
require_once BIFH_PLUGIN_DIR . 'includes/auth-handler.php';
require_once BIFH_PLUGIN_DIR . 'includes/cron-handler.php';
require_once BIFH_PLUGIN_DIR . 'includes/importer.php';
require_once BIFH_PLUGIN_DIR . 'includes/helper-functions.php';

// Admin Menu
add_action('admin_menu', 'bifh_register_admin_menu');
function bifh_register_admin_menu() {
    // Main menu page
    add_menu_page(
        __('Blog Importer for HubSpot', 'blog-importer-for-hubspot'),
        __('Blog Importer for HubSpot', 'blog-importer-for-hubspot'),
        'manage_options',
        'blog-importer-for-hubspot',
        'bifh_render_settings_page',
        'dashicons-cloud',
        26
    );
    
    // Settings submenu (rename main page)
    add_submenu_page(
        'blog-importer-for-hubspot',
        __('Settings', 'blog-importer-for-hubspot'),
        __('Settings', 'blog-importer-for-hubspot'),
        'manage_options',
        'blog-importer-for-hubspot',
        'bifh_render_settings_page'
    );
    
    // Logs submenu
    add_submenu_page(
        'blog-importer-for-hubspot',
        __('Import Logs', 'blog-importer-for-hubspot'),
        __('Import Logs', 'blog-importer-for-hubspot'),
        'manage_options',
        'blog-importer-for-hubspot-logs',
        'bifh_render_logs_page'
    );
}

// Plugin activation hook
register_activation_hook(__FILE__, 'bifh_plugin_activate');
function bifh_plugin_activate() {
    // Create logs table
    bifh_create_logs_table();
    
    // Schedule cron if sync is enabled
    bifh_activate_cron();
}