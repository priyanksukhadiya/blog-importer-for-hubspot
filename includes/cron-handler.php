<?php
if (!defined('ABSPATH')) exit;

/**
 * HubSpot Blog Importer Cron Handler
 * Manages scheduled imports via WordPress Cron
 */

// Register custom intervals
add_filter('cron_schedules', 'bifh_custom_cron_intervals');
function bifh_custom_cron_intervals($schedules) {
    if (!isset($schedules['bifh_hourly'])) {
        $schedules['bifh_hourly'] = array(
            'interval' => HOUR_IN_SECONDS,
            'display' => __('Every Hour (HubSpot)', 'blog-importer-for-hubspot')
        );
    }
    if (!isset($schedules['bifh_twicedaily'])) {
        $schedules['bifh_twicedaily'] = array(
            'interval' => 12 * HOUR_IN_SECONDS,
            'display' => __('Twice Daily (HubSpot)', 'blog-importer-for-hubspot')
        );
    }
    if (!isset($schedules['bifh_weekly'])) {
        $schedules['bifh_weekly'] = array(
            'interval' => WEEK_IN_SECONDS,
            'display' => __('Weekly (HubSpot)', 'blog-importer-for-hubspot')
        );
    }
    return $schedules;
}

// Handle cron job
add_action('bifh_cron_import_hook', 'bifh_run_scheduled_import');
function bifh_run_scheduled_import() {
    // Check if sync is enabled
    $enabled = get_option(BIFH_OPTION_SYNC_ENABLED, '0');
    if ($enabled !== '1') {
        // Log that sync is disabled
        bifh_log_import_activity('cron', 'error', __('Automatic sync is disabled', 'blog-importer-for-hubspot'), array(
            'error_details' => 'Sync setting is disabled but cron job still executed'
        ));
        return;
    }
    
    // Get settings
    $api_key = get_option(BIFH_OPTION_API_KEY);
    $post_type = get_option(BIFH_OPTION_POST_TYPE, 'post');
    $post_status = get_option(BIFH_OPTION_POST_STATUS, 'draft');
    
    // Validate API key
    if (empty($api_key)) {
        $error_message = __('HubSpot API Key is missing for scheduled import', 'blog-importer-for-hubspot');
        
        // Log the error
        bifh_log_import_activity('cron', 'error', $error_message, array(
            'error_details' => 'API key not configured'
        ));
        
        // Debug logging removed for production
        return;
    }
    
    // Log the start of scheduled import
    bifh_log_import_activity('cron', 'pending', __('Scheduled import started', 'blog-importer-for-hubspot'));
    
    try {
        // Run the import
        $importer = new BIFH_Importer($api_key, $post_type, $post_status);
        $result = $importer->import_blogs();
        
        if ($result['success']) {
            // Update last import time
            update_option('bifh_last_cron_import', current_time('timestamp'));
            
            $success_message = sprintf(
                // translators: %1$d is the number of imported posts, %2$d is the number of updated posts.
                __('Scheduled import completed: %1$d imported, %2$d updated', 'blog-importer-for-hubspot'),
                $result['imported'],
                $result['updated']
            );
            
            // Log successful import
            bifh_log_import_activity('cron', 'success', $success_message, array(
                'imported' => $result['imported'],
                'updated' => $result['updated']
            ));
            
            // Success logged to plugin activity log
        } else {
            // Log failed import
            bifh_log_import_activity('cron', 'error', $result['message'], array(
                'error_details' => isset($result['error_details']) ? $result['error_details'] : $result['message']
            ));
            
            // Error logged to plugin activity log
        }
    } catch (Exception $e) {
        $error_message = sprintf(
            // translators: %s is the error message from the exception.
            __('Scheduled import failed with exception: %s', 'blog-importer-for-hubspot'),
            $e->getMessage()
        );
        
        // Log the exception
        bifh_log_import_activity('cron', 'error', $error_message, array(
            'error_details' => $e->getMessage() . "\n\nStack trace:\n" . $e->getTraceAsString()
        ));
        
        // Exception logged to plugin activity log
    }
}

// Schedule/reschedule cron when settings are updated
add_action('update_option_' . BIFH_OPTION_SYNC_ENABLED, 'bifh_handle_sync_setting_change', 10, 2);
add_action('update_option_' . BIFH_OPTION_SYNC_INTERVAL, 'bifh_handle_sync_setting_change', 10, 2);
function bifh_handle_sync_setting_change($old_value, $new_value) {
    // Clear existing scheduled event
    wp_clear_scheduled_hook('bifh_cron_import_hook');
    
    // If sync is enabled, schedule new event
    $sync_enabled = get_option(BIFH_OPTION_SYNC_ENABLED, '0');
    if ($sync_enabled === '1') {
        $interval = get_option(BIFH_OPTION_SYNC_INTERVAL, 'daily');
        
        // Map our intervals to WordPress cron intervals
        $wp_interval = $interval;
        if ($interval === 'hourly') {
            $wp_interval = 'bifh_hourly';
        } elseif ($interval === 'twicedaily') {
            $wp_interval = 'bifh_twicedaily';
        } elseif ($interval === 'weekly') {
            $wp_interval = 'bifh_weekly';
        }
        
        wp_schedule_event(time(), $wp_interval, 'bifh_cron_import_hook');
    }
}

// Plugin activation hook
register_activation_hook(BIFH_PLUGIN_DIR . 'blog-importer-for-hubspot.php', 'bifh_activate_cron');
function bifh_activate_cron() {
    // Schedule cron if sync is enabled
    $sync_enabled = get_option(BIFH_OPTION_SYNC_ENABLED, '0');
    if ($sync_enabled === '1') {
        $interval = get_option(BIFH_OPTION_SYNC_INTERVAL, 'daily');
        
        // Map our intervals to WordPress cron intervals
        $wp_interval = $interval;
        if ($interval === 'hourly') {
            $wp_interval = 'bifh_hourly';
        } elseif ($interval === 'twicedaily') {
            $wp_interval = 'bifh_twicedaily';
        } elseif ($interval === 'weekly') {
            $wp_interval = 'bifh_weekly';
        }
        
        if (!wp_next_scheduled('bifh_cron_import_hook')) {
            wp_schedule_event(time(), $wp_interval, 'bifh_cron_import_hook');
        }
    }
}

// Plugin deactivation hook
register_deactivation_hook(BIFH_PLUGIN_DIR . 'blog-importer-for-hubspot.php', 'bifh_deactivate_cron');
function bifh_deactivate_cron() {
    wp_clear_scheduled_hook('bifh_cron_import_hook');
}

// Get next scheduled import time
function bifh_get_next_scheduled_import() {
    $timestamp = wp_next_scheduled('bifh_cron_import_hook');
    if ($timestamp) {
        return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
    }
    return false;
}

// Get last import time
function bifh_get_last_import_time() {
    $timestamp = get_option('bifh_last_cron_import');
    if ($timestamp) {
        return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
    }
    return false;
}
