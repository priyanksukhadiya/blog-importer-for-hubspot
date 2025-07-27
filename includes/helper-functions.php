<?php
if (!defined('ABSPATH')) exit;

/**
 * HubSpot Blog Importer Helper Functions
 * Utility functions for the plugin
 */

/**
 * Helper: Print admin error notice
 */
function bifh_admin_error($msg) {
    add_action('admin_notices', function () use ($msg) {
        echo '<div class="notice notice-error"><p>' . esc_html($msg) . '</p></div>';
    });
}

/**
 * Helper: Print admin success notice
 */
function bifh_admin_success($msg) {
    add_action('admin_notices', function () use ($msg) {
        echo '<div class="notice notice-success"><p>' . esc_html($msg) . '</p></div>';
    });
}

/**
 * Helper: Sanitize array deeply
 */
function bifh_recursive_sanitize_text($array) {
    if (!is_array($array)) return sanitize_text_field($array);

    foreach ($array as $key => $val) {
        $array[$key] = bifh_recursive_sanitize_text($val);
    }
    return $array;
}

/**
 * Helper: Validate API Key Format (basic)
 */
function bifh_validate_api_key($key) {
    return preg_match('/^pat-[a-z0-9]{2,3}-[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $key);
}

/**
 * Get import statistics
 * @return array Import statistics
 */
function bifh_get_import_stats() {
    global $wpdb;

    $post_type = get_option(BIFH_OPTION_POST_TYPE, 'post');
    $cache_key = 'bifh_import_stats_' . $post_type;
    $total_imported = wp_cache_get($cache_key);

    if (false === $total_imported) {
        // Using direct query for JOIN - no WordPress function supports this
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $total_imported = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm 
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
             WHERE pm.meta_key = %s AND p.post_type = %s",
            BIFH_META_HUBSPOT_ID,
            $post_type
        ));
        wp_cache_set($cache_key, $total_imported, '', 300);
    }

    $last_manual_import = get_option('bifh_last_import');
    $last_cron_import = get_option('bifh_last_cron_import');

    return array(
        'total_imported' => intval($total_imported),
        'last_manual_import' => $last_manual_import ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_manual_import) : false,
        'last_cron_import' => $last_cron_import ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_cron_import) : false,
        'next_scheduled_import' => bifh_get_next_scheduled_import()
    );
}

/**
 * Check if HubSpot API key is valid by making a test request
 * @param string $api_key HubSpot API key
 * @return array Result with success status and message
 */
function bifh_test_api_connection($api_key) {
    if (empty($api_key)) {
        return array(
            'success' => false,
            'message' => __('API key is required.', 'blog-importer-for-hubspot')
        );
    }

    $url = 'https://api.hubapi.com/cms/v3/blogs/posts?limit=1';

    $response = wp_remote_get($url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ),
        'timeout' => 15
    ));

    if (is_wp_error($response)) {
        return array(
            'success' => false,
            'message' => __('Connection failed: ', 'blog-importer-for-hubspot') . $response->get_error_message()
        );
    }

    $response_code = wp_remote_retrieve_response_code($response);

    if ($response_code === 200) {
        return array(
            'success' => true,
            'message' => __('API connection successful!', 'blog-importer-for-hubspot')
        );
    } elseif ($response_code === 401) {
        return array(
            'success' => false,
            'message' => __('Invalid API key. Please check your HubSpot Private App Access Token.', 'blog-importer-for-hubspot')
        );
    } else {
        $body = wp_remote_retrieve_body($response);
        $error_data = json_decode($body, true);
        $error_message = isset($error_data['message']) ? $error_data['message'] : 'Unknown error';

        return array(
            'success' => false,
            'message' => __('API Error: ', 'blog-importer-for-hubspot') . $error_message . ' (Code: ' . $response_code . ')'
        );
    }
}

/**
 * Get plugin status information
 * @return array Plugin status data
 */
function bifh_get_plugin_status() {
    $api_key = get_option(BIFH_OPTION_API_KEY);
    $sync_enabled = get_option(BIFH_OPTION_SYNC_ENABLED, '0');
    $sync_interval = get_option(BIFH_OPTION_SYNC_INTERVAL, 'daily');

    return array(
        'api_configured' => !empty($api_key),
        'sync_enabled' => $sync_enabled === '1',
        'sync_interval' => $sync_interval,
        'cron_scheduled' => wp_next_scheduled('bifh_cron_import_hook') !== false
    );
}

/**
 * Create logs database table
 */
function bifh_create_logs_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bifh_import_logs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        import_type varchar(20) NOT NULL DEFAULT 'manual',
        status varchar(20) NOT NULL DEFAULT 'pending',
        message text NOT NULL,
        error_details longtext,
        posts_imported int(11) DEFAULT 0,
        posts_updated int(11) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY status (status),
        KEY import_type (import_type),
        KEY created_at (created_at)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
    dbDelta($sql);
}

/**
 * Log import activity
 * @param string $import_type Type of import (manual, cron)
 * @param string $status Status (success, error)
 * @param string $message Log message
 * @param array $details Additional details
 */
function bifh_log_import_activity($import_type, $status, $message, $details = array()) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bifh_import_logs';

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
    $wpdb->insert(
        $table_name,
        array(
            'import_type' => sanitize_text_field($import_type),
            'status' => sanitize_text_field($status),
            'message' => sanitize_text_field($message),
            'error_details' => wp_json_encode(bifh_recursive_sanitize_text($details)),
            'posts_imported' => isset($details['imported']) ? intval($details['imported']) : 0,
            'posts_updated' => isset($details['updated']) ? intval($details['updated']) : 0,
            'created_at' => current_time('mysql')
        ),
        array('%s', '%s', '%s', '%s', '%d', '%d', '%s')
    );

    wp_cache_delete('bifh_logs_total_count');
    wp_cache_delete('bifh_success_imports_count');
    wp_cache_delete('bifh_failed_imports_count');
    wp_cache_flush();
}


/**
 * Get import logs with pagination
 * @param int $page Page number
 * @param int $per_page Items per page
 * @return array Logs data with total count
 */
function bifh_get_import_logs($page = 1, $per_page = 20) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bifh_import_logs';
    $offset = ($page - 1) * $per_page;

    $cache_key = 'bifh_logs_total_count';
    $total = wp_cache_get($cache_key);
    if (false === $total) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $total = $wpdb->get_var("SELECT COUNT(*) FROM `" . esc_sql($table_name) . "`");
        wp_cache_set($cache_key, $total, '', 300);
    }

    $cache_key = "bifh_logs_page_{$page}_{$per_page}";
    $logs = wp_cache_get($cache_key);
    if (false === $logs) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM `" . esc_sql($table_name) . "` ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));
        wp_cache_set($cache_key, $logs, '', 300);
    }

    return array(
        'logs' => $logs,
        'total' => intval($total)
    );
}

/**
 * Get successful imports count
 * @return int Number of successful imports
 */
function bifh_get_successful_imports_count() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bifh_import_logs';
    $cache_key = 'bifh_success_imports_count';
    $count = wp_cache_get($cache_key);

    if (false === $count) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $count = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `" . esc_sql($table_name) . "` WHERE status = %s",
            'success'
        )));
        wp_cache_set($cache_key, $count, '', 300);
    }
    return $count;
}

/**
 * Get failed imports count
 * @return int Number of failed imports
 */
function bifh_get_failed_imports_count() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bifh_import_logs';
    $cache_key = 'bifh_failed_imports_count';
    $count = wp_cache_get($cache_key);

    if (false === $count) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $count = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `" . esc_sql($table_name) . "` WHERE status = %s",
            'error'
        )));
        wp_cache_set($cache_key, $count, '', 300);
    }
    return $count;
}

/**
 * Clear all import logs
 */
function bifh_clear_import_logs() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bifh_import_logs';

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query("DELETE FROM `" . esc_sql($table_name) . "`");

    wp_cache_delete('bifh_logs_total_count');
    wp_cache_delete('bifh_success_imports_count');
    wp_cache_delete('bifh_failed_imports_count');
    wp_cache_flush();
}

/**
 * Clean up plugin data on uninstall
 */
function bifh_cleanup_plugin_data() {
    global $wpdb;

    delete_option(BIFH_OPTION_API_KEY);
    delete_option(BIFH_OPTION_POST_TYPE);
    delete_option(BIFH_OPTION_POST_STATUS);
    delete_option(BIFH_OPTION_SYNC_ENABLED);
    delete_option(BIFH_OPTION_SYNC_INTERVAL);
    delete_option('bifh_last_import');
    delete_option('bifh_last_cron_import');

    wp_clear_scheduled_hook('bifh_cron_import_hook');

    $table_name = $wpdb->prefix . 'bifh_import_logs';

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $table_exists = $wpdb->get_var($wpdb->prepare(
        "SHOW TABLES LIKE %s",
        $table_name
    ));

    if ($table_exists) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $wpdb->query("DROP TABLE `" . esc_sql($table_name) . "`");
    }

    wp_cache_flush();
}
