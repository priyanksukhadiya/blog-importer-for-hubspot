<?php
if (!defined('ABSPATH')) exit;

function bifh_render_settings_page() {
    // Enqueue admin styles
    bifh_enqueue_admin_styles();
    
    // Handle import action
    if (isset($_POST['bifh_run_import']) && isset($_POST['bifh_import_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bifh_import_nonce'])), 'bifh_run_import_action')) {
        $result = bifh_run_manual_import();
        if ($result['success']) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';
        }
    }
    
    // Get available post types
    $post_types = get_post_types(array('public' => true), 'objects');
    $current_post_type = get_option(BIFH_OPTION_POST_TYPE, 'post');
    $current_post_status = get_option(BIFH_OPTION_POST_STATUS, 'draft');
    $sync_enabled = get_option(BIFH_OPTION_SYNC_ENABLED, '0');
    $sync_interval = get_option(BIFH_OPTION_SYNC_INTERVAL, 'daily');
    $api_key = get_option(BIFH_OPTION_API_KEY, '');
    
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('HubSpot Blog Importer Settings', 'blog-importer-for-hubspot'); ?></h1>
        
        <!-- Settings Form -->
        <form method="post" action="options.php">
            <?php settings_fields('bifh_settings_group'); ?>
            <?php do_settings_sections('bifh_settings_group'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="<?php echo esc_attr(BIFH_OPTION_API_KEY); ?>"><?php esc_html_e('HubSpot API Key', 'blog-importer-for-hubspot'); ?></label>
                    </th>
                    <td>
                        <input type="password" name="<?php echo esc_attr(BIFH_OPTION_API_KEY); ?>" id="<?php echo esc_attr(BIFH_OPTION_API_KEY); ?>" value="<?php echo esc_attr($api_key); ?>" class="regular-text" placeholder="pat-na1-xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                        <p class="description"><?php esc_html_e('Enter your HubSpot Private App Access Token. You can create one in your HubSpot account under Settings > Integrations > Private Apps.', 'blog-importer-for-hubspot'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="<?php echo esc_attr(BIFH_OPTION_POST_TYPE); ?>"><?php esc_html_e('Import To Post Type', 'blog-importer-for-hubspot'); ?></label>
                    </th>
                    <td>
                        <select name="<?php echo esc_attr(BIFH_OPTION_POST_TYPE); ?>" id="<?php echo esc_attr(BIFH_OPTION_POST_TYPE); ?>">
                            <?php foreach ($post_types as $post_type): ?>
                                <option value="<?php echo esc_attr($post_type->name); ?>" <?php selected($current_post_type, $post_type->name); ?>>
                                    <?php echo esc_html($post_type->labels->singular_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e('Select the post type where HubSpot blog posts will be imported.', 'blog-importer-for-hubspot'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="<?php echo esc_attr(BIFH_OPTION_POST_STATUS); ?>"><?php esc_html_e('Post Status on Import', 'blog-importer-for-hubspot'); ?></label>
                    </th>
                    <td>
                        <select name="<?php echo esc_attr(BIFH_OPTION_POST_STATUS); ?>" id="<?php echo esc_attr(BIFH_OPTION_POST_STATUS); ?>">
                            <option value="publish" <?php selected($current_post_status, 'publish'); ?>><?php esc_html_e('Published', 'blog-importer-for-hubspot'); ?></option>
                            <option value="draft" <?php selected($current_post_status, 'draft'); ?>><?php esc_html_e('Draft', 'blog-importer-for-hubspot'); ?></option>
                            <option value="pending" <?php selected($current_post_status, 'pending'); ?>><?php esc_html_e('Pending Review', 'blog-importer-for-hubspot'); ?></option>
                            <option value="private" <?php selected($current_post_status, 'private'); ?>><?php esc_html_e('Private', 'blog-importer-for-hubspot'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('Choose the status for imported posts.', 'blog-importer-for-hubspot'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="<?php echo esc_attr(BIFH_OPTION_SYNC_ENABLED); ?>">
                            <?php esc_html_e('Enable Sync (WP-Cron)', 'blog-importer-for-hubspot'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="checkbox" name="<?php echo esc_attr(BIFH_OPTION_SYNC_ENABLED); ?>" id="<?php echo esc_attr(BIFH_OPTION_SYNC_ENABLED); ?>" value="1" <?php checked($sync_enabled, '1'); ?>>
                        <label for="<?php echo esc_attr(BIFH_OPTION_SYNC_ENABLED); ?>"><?php esc_html_e('Automatically sync HubSpot blogs at regular intervals', 'blog-importer-for-hubspot'); ?></label>
                        <p class="description"><?php esc_html_e('When enabled, the plugin will automatically check for new or updated blog posts from HubSpot.', 'blog-importer-for-hubspot'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="<?php echo esc_attr(BIFH_OPTION_SYNC_INTERVAL); ?>"><?php esc_html_e('Sync Interval', 'blog-importer-for-hubspot'); ?></label>
                    </th>
                    <td>
                        <select name="<?php echo esc_attr(BIFH_OPTION_SYNC_INTERVAL); ?>" id="<?php echo esc_attr(BIFH_OPTION_SYNC_INTERVAL); ?>">
                            <option value="hourly" <?php selected($sync_interval, 'hourly'); ?>><?php esc_html_e('Hourly', 'blog-importer-for-hubspot'); ?></option>
                            <option value="twicedaily" <?php selected($sync_interval, 'twicedaily'); ?>><?php esc_html_e('Twice Daily', 'blog-importer-for-hubspot'); ?></option>
                            <option value="daily" <?php selected($sync_interval, 'daily'); ?>><?php esc_html_e('Daily', 'blog-importer-for-hubspot'); ?></option>
                            <option value="weekly" <?php selected($sync_interval, 'weekly'); ?>><?php esc_html_e('Weekly', 'blog-importer-for-hubspot'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('How often should the plugin check for updates from HubSpot?', 'blog-importer-for-hubspot'); ?></p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(__('Save Settings', 'blog-importer-for-hubspot')); ?>
        </form>
        
        <hr>
        
        <!-- Import Statistics Section -->
        <?php 
        $stats = bifh_get_import_stats();
        $status = bifh_get_plugin_status();
        ?>
        <div class="bifh-stats-section">
            <h2><?php esc_html_e('Import Statistics', 'blog-importer-for-hubspot'); ?></h2>
            <div class="bifh-stats-grid">
                <div class="bifh-stat-item">
                    <div class="bifh-stat-number"><?php echo esc_html($stats['total_imported']); ?></div>
                    <div class="bifh-stat-label"><?php esc_html_e('Total Imported Posts', 'blog-importer-for-hubspot'); ?></div>
                </div>
                <div class="bifh-stat-item">
                    <div class="bifh-stat-number"><?php echo $status['sync_enabled'] ? '✓' : '✗'; ?></div>
                    <div class="bifh-stat-label"><?php esc_html_e('Auto Sync Status', 'blog-importer-for-hubspot'); ?></div>
                </div>
                <div class="bifh-stat-item">
                    <div class="bifh-stat-number"><?php echo esc_html(ucfirst($status['sync_interval'])); ?></div>
                    <div class="bifh-stat-label"><?php esc_html_e('Sync Interval', 'blog-importer-for-hubspot'); ?></div>
                </div>
            </div>
            
            <div class="bifh-import-times">
                <?php if ($stats['last_manual_import']): ?>
                    <p><strong><?php esc_html_e('Last Manual Import:', 'blog-importer-for-hubspot'); ?></strong> <?php echo esc_html($stats['last_manual_import']); ?></p>
                <?php endif; ?>
                <?php if ($stats['last_cron_import']): ?>
                    <p><strong><?php esc_html_e('Last Automatic Import:', 'blog-importer-for-hubspot'); ?></strong> <?php echo esc_html($stats['last_cron_import']); ?></p>
                <?php endif; ?>
                <?php if ($stats['next_scheduled_import'] && $status['sync_enabled']): ?>
                    <p><strong><?php esc_html_e('Next Scheduled Import:', 'blog-importer-for-hubspot'); ?></strong> <?php echo esc_html($stats['next_scheduled_import']); ?></p>
                <?php endif; ?>
            </div>
            
            <div class="bifh-logs-link">
                <a href="<?php echo esc_url(admin_url('admin.php?page=blog-importer-for-hubspot-logs')); ?>" class="button button-secondary">
                    <span class="dashicons dashicons-list-view"></span>
                    <?php esc_html_e('View Import Logs', 'blog-importer-for-hubspot'); ?>
                </a>
            </div>
        </div>
        
        <hr>
        
        <!-- Manual Import Section -->
        <div class="bifh-import-section">
            <h2><?php esc_html_e('Manual Import', 'blog-importer-for-hubspot'); ?></h2>
            <p><?php esc_html_e('Click the button below to manually import blog posts from HubSpot right now.', 'blog-importer-for-hubspot'); ?></p>
            
            <?php if (empty($api_key)): ?>
                <div class="notice notice-warning inline">
                    <p><?php esc_html_e('Please save your HubSpot API Key above before running an import.', 'blog-importer-for-hubspot'); ?></p>
                </div>
            <?php else: ?>
                <form method="post" action="" style="display: inline-block;">
                    <?php wp_nonce_field('bifh_run_import_action', 'bifh_import_nonce'); ?>
                    <input type="submit" name="bifh_run_import" class="button button-primary button-large" value="<?php esc_attr_e('Run Import Now', 'blog-importer-for-hubspot'); ?>" onclick="return confirm('<?php esc_attr_e('This will import blog posts from HubSpot. Continue?', 'blog-importer-for-hubspot'); ?>')">
                </form>
                
                <div id="bifh-import-status" style="margin-top: 15px;"></div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

// Add AJAX handler for API key testing
add_action('wp_ajax_bifh_test_api_key', 'bifh_ajax_test_api_key');
function bifh_ajax_test_api_key() {
    // Check nonce and permissions
    if (!check_ajax_referer('bifh_test_api_nonce', 'nonce', false) || !current_user_can('manage_options')) {
        wp_die(esc_html__('Security check failed.', 'blog-importer-for-hubspot'));
    }
    
    $api_key = sanitize_text_field(wp_unslash($_POST['api_key'] ?? ''));
    $result = bifh_test_api_connection($api_key);
    
    wp_send_json($result);
}
