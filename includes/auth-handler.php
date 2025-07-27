<?php
if (!defined('ABSPATH')) exit;

/**
 * Fetch list of HubSpot blogs using API Key
 * @param string $api_key
 * @return array|WP_Error
 */
function bifh_fetch_hubspot_blogs($api_key) {
    $endpoint = 'https://api.hubapi.com/cms/v3/blogs/blogs';

    $response = wp_remote_get($endpoint, [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json'
        ],
        'timeout' => 15
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    if ($code !== 200) {
        return new WP_Error('hubspot_api_error', 'Error fetching blogs: ' . $body);
    }

    $data = json_decode($body, true);
    return !empty($data['results']) ? $data['results'] : [];
}
