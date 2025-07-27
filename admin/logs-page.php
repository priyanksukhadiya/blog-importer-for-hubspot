<?php
if (!defined('ABSPATH')) exit;

/**
 * HubSpot Blog Importer Logs Page
 * Display import history and activity logs
 */

function bifh_render_logs_page() {
    // Enqueue admin styles
    bifh_enqueue_admin_styles();
    
    // Handle log actions
    if (isset($_POST['bifh_clear_logs']) && isset($_POST['bifh_logs_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bifh_logs_nonce'])), 'bifh_clear_logs_action')) {
        bifh_clear_import_logs();
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Import logs cleared successfully.', 'blog-importer-for-hubspot') . '</p></div>';
    }
    
    // Get logs with pagination
    $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page = 20;
    $logs_data = bifh_get_import_logs($page, $per_page);
    $logs = $logs_data['logs'];
    $total_logs = $logs_data['total'];
    $total_pages = ceil($total_logs / $per_page);
    
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('HubSpot Import Logs', 'blog-importer-for-hubspot'); ?></h1>
        <p><?php esc_html_e('Track all import activities and monitor the status of your HubSpot blog imports.', 'blog-importer-for-hubspot'); ?></p>
        
        <!-- Log Statistics -->
        <div class="bifh-log-stats">
            <div class="bifh-stats-grid">
                <div class="bifh-stat-item">
                    <div class="bifh-stat-number"><?php echo esc_html($total_logs); ?></div>
                    <div class="bifh-stat-label"><?php esc_html_e('Total Log Entries', 'blog-importer-for-hubspot'); ?></div>
                </div>
                <div class="bifh-stat-item">
                    <div class="bifh-stat-number"><?php echo esc_html(bifh_get_successful_imports_count()); ?></div>
                    <div class="bifh-stat-label"><?php esc_html_e('Successful Imports', 'blog-importer-for-hubspot'); ?></div>
                </div>
                <div class="bifh-stat-item">
                    <div class="bifh-stat-number"><?php echo esc_html(bifh_get_failed_imports_count()); ?></div>
                    <div class="bifh-stat-label"><?php esc_html_e('Failed Imports', 'blog-importer-for-hubspot'); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Actions -->
        <div class="bifh-log-actions">
            <form method="post" action="" style="display: inline-block;">
                <?php wp_nonce_field('bifh_clear_logs_action', 'bifh_logs_nonce'); ?>
                <input type="submit" name="bifh_clear_logs" class="button button-secondary" value="<?php esc_attr_e('Clear All Logs', 'blog-importer-for-hubspot'); ?>" onclick="return confirm('<?php esc_attr_e('Are you sure you want to clear all import logs? This action cannot be undone.', 'blog-importer-for-hubspot'); ?>')">
            </form>
            
            <a href="<?php echo esc_url(admin_url('admin.php?page=blog-importer-for-hubspot')); ?>" class="button button-primary"><?php esc_html_e('Back to Settings', 'blog-importer-for-hubspot'); ?></a>
        </div>
        
        <?php if (empty($logs)): ?>
            <div class="bifh-no-logs">
                <div class="notice notice-info">
                    <p><?php esc_html_e('No import logs found. Logs will appear here after you run your first import.', 'blog-importer-for-hubspot'); ?></p>
                </div>
            </div>
        <?php else: ?>
            <!-- Logs Table -->
            <div class="bifh-logs-table-container">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col" class="manage-column column-date"><?php esc_html_e('Date/Time', 'blog-importer-for-hubspot'); ?></th>
                            <th scope="col" class="manage-column column-type"><?php esc_html_e('Type', 'blog-importer-for-hubspot'); ?></th>
                            <th scope="col" class="manage-column column-status"><?php esc_html_e('Status', 'blog-importer-for-hubspot'); ?></th>
                            <th scope="col" class="manage-column column-details"><?php esc_html_e('Details', 'blog-importer-for-hubspot'); ?></th>
                            <th scope="col" class="manage-column column-posts"><?php esc_html_e('Posts', 'blog-importer-for-hubspot'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr class="bifh-log-row bifh-log-<?php echo esc_attr($log->status); ?>">
                                <td class="column-date">
                                    <strong><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->created_at))); ?></strong>
                                </td>
                                <td class="column-type">
                                    <span class="bifh-log-type bifh-log-type-<?php echo esc_attr($log->import_type); ?>">
                                        <?php echo esc_html(ucfirst($log->import_type)); ?>
                                    </span>
                                </td>
                                <td class="column-status">
                                    <span class="bifh-status-badge bifh-status-<?php echo esc_attr($log->status); ?>">
                                        <?php echo esc_html(ucfirst($log->status)); ?>
                                    </span>
                                </td>
                                <td class="column-details">
                                    <div class="bifh-log-message"><?php echo esc_html($log->message); ?></div>
                                    <?php if (!empty($log->error_details)): ?>
                                        <details class="bifh-error-details">
                                            <summary><?php esc_html_e('Error Details', 'blog-importer-for-hubspot'); ?></summary>
                                            <pre><?php echo esc_html($log->error_details); ?></pre>
                                        </details>
                                    <?php endif; ?>
                                </td>
                                <td class="column-posts">
                                    <?php if ($log->status === 'success'): ?>
                                        <div class="bifh-post-counts">
                                            <?php if ($log->posts_imported > 0): ?>
                                                <span class="bifh-imported"><?php echo sprintf(
                                                    // translators: %d is the number of imported posts.
                                                    esc_html__('%d imported', 'blog-importer-for-hubspot'), absint($log->posts_imported)); ?></span>
                                            <?php endif; ?>
                                            <?php if ($log->posts_updated > 0): ?>
                                                <span class="bifh-updated"><?php echo sprintf(
                                                    // translators: %d is the number of updated posts.
                                                    esc_html__('%d updated', 'blog-importer-for-hubspot'), absint($log->posts_updated)); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="bifh-no-posts">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="bifh-pagination">
                    <?php
                    $pagination_args = array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo; Previous', 'blog-importer-for-hubspot'),
                        'next_text' => __('Next &raquo;', 'blog-importer-for-hubspot'),
                        'total' => $total_pages,
                        'current' => $page
                    );
                    echo wp_kses_post(paginate_links($pagination_args));
                    ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}
