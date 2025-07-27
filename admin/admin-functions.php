<?php
if (!defined('ABSPATH')) exit;

// Enqueue admin styles
function bifh_enqueue_admin_styles() {
    wp_enqueue_style(
        'bifh-admin-styles',
        plugin_dir_url(__FILE__) . '../assets/css/admin-styles.css',
        array(),
        '1.0.0'
    );
}

// Register plugin settings
add_action('admin_init', 'bifh_register_settings');
function bifh_register_settings() {
    register_setting('bifh_settings_group', BIFH_OPTION_API_KEY, 'bifh_sanitize_api_key');
    register_setting('bifh_settings_group', BIFH_OPTION_POST_TYPE, 'bifh_sanitize_post_type');
    register_setting('bifh_settings_group', BIFH_OPTION_POST_STATUS, 'bifh_sanitize_post_status');
    register_setting('bifh_settings_group', BIFH_OPTION_SYNC_ENABLED, 'bifh_sanitize_checkbox');
    register_setting('bifh_settings_group', BIFH_OPTION_SYNC_INTERVAL, 'bifh_sanitize_sync_interval');
}

// Sanitization functions
function bifh_sanitize_api_key($value) {
    $value = sanitize_text_field($value);
    // Basic validation for HubSpot API key format
    if (!empty($value) && !preg_match('/^pat-[a-z0-9]{2,3}-[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $value)) {
        add_settings_error('bifh_settings_group', 'invalid_api_key', __('Invalid HubSpot API Key format. Please check your API key.', 'blog-importer-for-hubspot'));
    }
    return $value;
}

function bifh_sanitize_post_type($value) {
    $value = sanitize_text_field($value);
    $post_types = get_post_types(array('public' => true));
    return in_array($value, $post_types) ? $value : 'post';
}

function bifh_sanitize_post_status($value) {
    $value = sanitize_text_field($value);
    $allowed_statuses = array('publish', 'draft', 'pending', 'private');
    return in_array($value, $allowed_statuses) ? $value : 'draft';
}

function bifh_sanitize_checkbox($value) {
    return $value === '1' ? '1' : '0';
}

function bifh_sanitize_sync_interval($value) {
    $value = sanitize_text_field($value);
    $allowed_intervals = array('hourly', 'twicedaily', 'daily', 'weekly');
    return in_array($value, $allowed_intervals) ? $value : 'daily';
}

// Manual import function
function bifh_run_manual_import() {
    // Check if user has proper capabilities
    if (!current_user_can('manage_options')) {
        $error_message = __('You do not have permission to run imports.', 'blog-importer-for-hubspot');
        
        // Log the failed attempt
        bifh_log_import_activity('manual', 'error', $error_message, array(
            'error_details' => 'User lacks manage_options capability'
        ));
        
        return array(
            'success' => false,
            'message' => $error_message
        );
    }
    
    // Get settings
    $api_key = get_option(BIFH_OPTION_API_KEY);
    $post_type = get_option(BIFH_OPTION_POST_TYPE, 'post');
    $post_status = get_option(BIFH_OPTION_POST_STATUS, 'draft');
    
    // Validate API key
    if (empty($api_key)) {
        $error_message = __('HubSpot API Key is required. Please configure it in the settings.', 'blog-importer-for-hubspot');
        
        // Log the failed attempt
        bifh_log_import_activity('manual', 'error', $error_message, array(
            'error_details' => 'API key not configured'
        ));
        
        return array(
            'success' => false,
            'message' => $error_message
        );
    }
    
    // Log the start of import
    bifh_log_import_activity('manual', 'pending', __('Manual import started', 'blog-importer-for-hubspot'));
    
    // Run the import
    try {
        $importer = new BIFH_Importer($api_key, $post_type, $post_status);
        $result = $importer->import_blogs();
        
        if ($result['success']) {
            // Update last import time
            update_option('bifh_last_import', current_time('timestamp'));
            
            $success_message = sprintf(
                // translators: %1$d is the number of imported posts, %2$d is the number of updated posts.
                __('Import completed successfully! Imported %1$d new posts and updated %2$d existing posts.', 'blog-importer-for-hubspot'),
                $result['imported'],
                $result['updated']
            );
            
            // Log successful import
            bifh_log_import_activity('manual', 'success', $success_message, array(
                'imported' => $result['imported'],
                'updated' => $result['updated']
            ));
            
            return array(
                'success' => true,
                'message' => $success_message
            );
        } else {
            // Log failed import
            bifh_log_import_activity('manual', 'error', $result['message'], array(
                'error_details' => isset($result['error_details']) ? $result['error_details'] : $result['message']
            ));
            
            return array(
                'success' => false,
                'message' => $result['message']
            );
        }
    } catch (Exception $e) {
        $error_message = __('Import failed. Please check your API key and try again. Error: ', 'blog-importer-for-hubspot') . $e->getMessage();
        
        // Log the exception
        bifh_log_import_activity('manual', 'error', $error_message, array(
            'error_details' => $e->getMessage() . "\n\nStack trace:\n" . $e->getTraceAsString()
        ));
        
        // Error logging handled through bifh_log_import_activity function
        
        return array(
            'success' => false,
            'message' => $error_message
        );
    }
}

// Add admin notices for settings errors
add_action('admin_notices', 'bifh_admin_notices');
function bifh_admin_notices() {
    settings_errors('bifh_settings_group');
}

// Add settings link to plugin page
add_filter('plugin_action_links_' . plugin_basename(BIFH_PLUGIN_DIR . 'blog-importer-for-hubspot.php'), 'bifh_add_settings_link');
function bifh_add_settings_link($links) {
    $settings_link = '<a href="admin.php?page=blog-importer-for-hubspot">' . __('Settings', 'blog-importer-for-hubspot') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
