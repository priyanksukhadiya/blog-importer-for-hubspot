<?php
/**
 * HubSpot Blog Importer Uninstall
 * 
 * This file is executed when the plugin is uninstalled.
 * It cleans up all plugin data from the database.
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Define plugin constants if not already defined
if (!defined('BIFH_OPTION_API_KEY')) {
    define('BIFH_OPTION_API_KEY', 'bifh_hubspot_api_key');
    define('BIFH_OPTION_POST_TYPE', 'bifh_post_type');
    define('BIFH_OPTION_POST_STATUS', 'bifh_post_status');
    define('BIFH_OPTION_SYNC_ENABLED', 'bifh_enable_sync');
    define('BIFH_OPTION_SYNC_INTERVAL', 'bifh_sync_interval');
    define('BIFH_META_HUBSPOT_ID', '_hubspot_post_id');
}

// Remove all plugin options
delete_option(BIFH_OPTION_API_KEY);
delete_option(BIFH_OPTION_POST_TYPE);
delete_option(BIFH_OPTION_POST_STATUS);
delete_option(BIFH_OPTION_SYNC_ENABLED);
delete_option(BIFH_OPTION_SYNC_INTERVAL);
delete_option('bifh_last_import');
delete_option('bifh_last_cron_import');

// Clear scheduled cron jobs
wp_clear_scheduled_hook('bifh_cron_import_hook');