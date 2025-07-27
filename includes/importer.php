<?php
if (!defined('ABSPATH')) exit;

/**
 * HubSpot Blog Importer Class
 * Handles importing blog posts from HubSpot CMS API
 */
class BIFH_Importer {
    
    private $api_key;
    private $post_type;
    private $post_status;
    private $api_base_url = 'https://api.hubapi.com';
    
    public function __construct($api_key, $post_type = 'post', $post_status = 'draft') {
        $this->api_key = $api_key;
        $this->post_type = $post_type;
        $this->post_status = $post_status;
    }
    
    /**
     * Import blog posts from HubSpot
     * @return array Result with success status and message
     */
    public function import_blogs() {
        try {
            // Get blog posts from HubSpot
            $blog_posts = $this->fetch_hubspot_blogs();
            
            if (empty($blog_posts)) {
                return array(
                    'success' => false,
                    'message' => __('No blog posts found in HubSpot.', 'blog-importer-for-hubspot')
                );
            }
            
            $imported = 0;
            $updated = 0;
            $errors = array();
            
            foreach ($blog_posts as $blog_post) {
                $result = $this->process_blog_post($blog_post);
                
                if ($result['success']) {
                    if ($result['action'] === 'imported') {
                        $imported++;
                    } else {
                        $updated++;
                    }
                } else {
                    $errors[] = $result['message'];
                }
            }
            
            // Log the import activity
            $details = array(
                'imported' => $imported,
                'updated' => $updated,
                'total_processed' => count($blog_posts)
            );
            
            if (!empty($errors)) {
                $details['errors'] = $errors;
                bifh_log_import_activity(
                    'manual', 
                    'error', 
                    // translators: %d is the number of errors that occurred during import
                    sprintf(__('Import completed with %d errors', 'blog-importer-for-hubspot'), count($errors)),
                    $details
                );
            } else {
                bifh_log_import_activity(
                    'manual', 
                    'success', 
                    // translators: "%1$d is the number of imported posts and %2$d is the number of updated posts during import
                    sprintf(__('Successfully imported %1$d posts and updated %2$d posts', 'blog-importer-for-hubspot'), $imported, $updated),
                    $details
                );
            }
            
            return array(
                'success' => true,
                'imported' => $imported,
                'updated' => $updated,
                'errors' => $errors
            );
            
        } catch (Exception $e) {
            // Log the exception
            bifh_log_import_activity(
                'manual', 
                'error', 
                $e->getMessage(),
                array('exception' => $e->getMessage())
            );
            
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    
    /**
     * Fetch blog posts from HubSpot API
     * @return array Array of blog posts
     */
    private function fetch_hubspot_blogs() {
        $all_posts = array();
        $offset = 0;
        $limit = 50; // HubSpot API limit
        
        do {
            $url = $this->api_base_url . '/cms/v3/blogs/posts';
            $url .= '?limit=' . $limit . '&offset=' . $offset;
            $url .= '&state=PUBLISHED'; // Only get published posts
            $url .= '&sort=-publish_date'; // Sort by newest first
            
            $response = wp_remote_get($url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type' => 'application/json'
                ),
                'timeout' => 30
            ));

            if (is_wp_error($response)) {
                throw new Exception(esc_html__('Failed to connect to HubSpot API: ', 'blog-importer-for-hubspot') . esc_html($response->get_error_message()));
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                $body = wp_remote_retrieve_body($response);
                $error_data = json_decode($body, true);
                $error_message = isset($error_data['message']) ? $error_data['message'] : 'Unknown API error';
                throw new Exception(esc_html__('HubSpot API Error: ', 'blog-importer-for-hubspot') . esc_html($error_message) . ' (Code: ' . absint($response_code) . ')');
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (!isset($data['results'])) {
                break;
            }
            
            $all_posts = array_merge($all_posts, $data['results']);
            $offset += $limit;
            
            // Prevent infinite loops
            if (count($all_posts) >= 500) {
                break;
            }
            
        } while (!empty($data['results']) && count($data['results']) === $limit);
        
        return $all_posts;
    }
    
    /**
     * Process a single blog post from HubSpot
     * @param array $blog_post HubSpot blog post data
     * @return array Result with success status and action taken
     */
    private function process_blog_post($blog_post) {
        try {
            $hubspot_id = $blog_post['id'];
            
            // Check if post already exists
            $existing_post = $this->find_existing_post($hubspot_id);
            
            // Prepare post data
            $post_data = $this->prepare_post_data($blog_post, $existing_post);
            
            if ($existing_post) {
                // Update existing post
                $post_data['ID'] = $existing_post->ID;
                $result = wp_update_post($post_data, true);
                $action = 'updated';
            } else {
                // Create new post
                $result = wp_insert_post($post_data, true);
                $action = 'imported';
            }
            
            if (is_wp_error($result)) {
                return array(
                    'success' => false,
                    'message' => $result->get_error_message()
                );
            }
            
            // Update post meta
            $this->update_post_meta($result, $blog_post);
            
            // Handle featured image
            $this->handle_featured_image($result, $blog_post);
            
            return array(
                'success' => true,
                'action' => $action,
                'post_id' => $result
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Find existing post by HubSpot ID
     * @param string $hubspot_id HubSpot post ID
     * @return WP_Post|null Existing post or null
     */
    private function find_existing_post($hubspot_id) {
        // Use the cached helper function to avoid slow meta queries
        $post_id = bifh_find_post_by_meta(BIFH_META_HUBSPOT_ID, $hubspot_id);
        
        if ($post_id) {
            $post = get_post($post_id);
            // Verify the post is of the correct type
            if ($post && $post->post_type === $this->post_type) {
                return $post;
            }
        }
        
        return null;
    }
    
    /**
     * Prepare WordPress post data from HubSpot blog post
     * @param array $blog_post HubSpot blog post data
     * @param WP_Post|null $existing_post Existing WordPress post
     * @return array WordPress post data
     */
    private function prepare_post_data($blog_post, $existing_post = null) {
        // Get publish date
        $publish_date = null;
        if (!empty($blog_post['publishDate'])) {
            $publish_date = gmdate('Y-m-d H:i:s', strtotime($blog_post['publishDate']));
        }
        
        // Prepare post content
        $content = '';
        if (!empty($blog_post['postBody'])) {
            $content = $blog_post['postBody'];
        } elseif (!empty($blog_post['postSummary'])) {
            $content = $blog_post['postSummary'];
        }
        
        // Clean up HubSpot-specific HTML/CSS
        $content = $this->clean_hubspot_content($content);
        
        $post_data = array(
            'post_title' => sanitize_text_field($blog_post['name'] ?? ''),
            'post_content' => $content,
            'post_excerpt' => sanitize_textarea_field($blog_post['postSummary'] ?? ''),
            'post_status' => $this->post_status,
            'post_type' => $this->post_type,
            'post_author' => get_current_user_id(),
        );
        
        // Set publish date if available
        if ($publish_date) {
            $post_data['post_date'] = $publish_date;
            $post_data['post_date_gmt'] = get_gmt_from_date($publish_date);
        }
        
        // Handle slug
        if (!empty($blog_post['slug'])) {
            $post_data['post_name'] = sanitize_title($blog_post['slug']);
        }
        
        return $post_data;
    }
    
    /**
     * Clean HubSpot-specific content
     * @param string $content Raw content from HubSpot
     * @return string Cleaned content
     */
    private function clean_hubspot_content($content) {
        if (empty($content)) {
            return '';
        }
        
        // Remove HubSpot-specific CSS classes and inline styles
        $content = preg_replace('/class="[^"]*hs-[^"]*"/i', '', $content);
        $content = preg_replace('/style="[^"]*"/i', '', $content);
        
        // Clean up extra whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);
        
        return wp_kses_post($content);
    }
    
    /**
     * Update post meta data
     * @param int $post_id WordPress post ID
     * @param array $blog_post HubSpot blog post data
     */
    private function update_post_meta($post_id, $blog_post) {
        // Store HubSpot ID
        update_post_meta($post_id, BIFH_META_HUBSPOT_ID, $blog_post['id']);
        
        // Store additional HubSpot data
        update_post_meta($post_id, '_hubspot_url', $blog_post['url'] ?? '');
        update_post_meta($post_id, '_hubspot_updated', $blog_post['updated'] ?? '');
        update_post_meta($post_id, '_hubspot_created', $blog_post['created'] ?? '');
        update_post_meta($post_id, '_hubspot_import_date', current_time('mysql'));
        
        // Store author information if available
        if (!empty($blog_post['blogAuthor'])) {
            update_post_meta($post_id, '_hubspot_author', sanitize_text_field($blog_post['blogAuthor']['displayName'] ?? ''));
        }
    }
    
    /**
     * Handle featured image from HubSpot
     * @param int $post_id WordPress post ID
     * @param array $blog_post HubSpot blog post data
     */
    private function handle_featured_image($post_id, $blog_post) {
        if (empty($blog_post['featuredImage'])) {
            return;
        }
        
        $image_url = $blog_post['featuredImage'];
        
        // Check if we already have this image
        $existing_attachment = get_post_meta($post_id, '_hubspot_featured_image_url', true);
        if ($existing_attachment === $image_url) {
            return; // Same image, no need to re-import
        }
        
        // Import the image
        $attachment_id = $this->import_image($image_url, $post_id);
        
        if ($attachment_id) {
            set_post_thumbnail($post_id, $attachment_id);
            update_post_meta($post_id, '_hubspot_featured_image_url', $image_url);
        }
    }
    
    /**
     * Import image from URL
     * @param string $image_url Image URL
     * @param int $post_id Parent post ID
     * @return int|false Attachment ID or false on failure
     */
    private function import_image($image_url, $post_id) {
        if (!function_exists('media_handle_sideload')) {
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }
        
        // Download the image
        $tmp = download_url($image_url);
        
        if (is_wp_error($tmp)) {
            return false;
        }
        
        // Get file info
        $file_array = array(
            'name' => basename($image_url),
            'tmp_name' => $tmp
        );
        
        // Import the image
        $attachment_id = media_handle_sideload($file_array, $post_id);
        
        // Clean up temp file
        if (file_exists($tmp)) {
            wp_delete_file($tmp);
        }
        
        return is_wp_error($attachment_id) ? false : $attachment_id;
    }
}

function bifh_find_post_by_meta($meta_key, $meta_value) {
    global $wpdb;
    
    // Use caching for post meta lookups
    $cache_key = 'bifh_post_meta_' . md5($meta_key . '_' . $meta_value);
    $post_id = wp_cache_get($cache_key);
    
    if (false === $post_id) {
        // Direct database query is necessary for performance reasons
        // get_posts() would be less efficient for this specific meta lookup
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
            $meta_key,
            $meta_value
        ));
        wp_cache_set($cache_key, $post_id, '', 300); // Cache for 5 minutes
    }
    
    return $post_id;
}