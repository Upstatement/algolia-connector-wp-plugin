<?php

/**
 * Plugin Name:     Algolia WordPress Integration
 * Description:     Index WordPress content in Algolia
 * Text Domain:     algolia-wordpress-integration
 * Version:         0.1.0
 * Author:          Upstatement
 * Author URI:      https://www.upstatement.com
 *
 * @package Algolia_WordPress_Integration
 */

// https://www.algolia.com/doc/integration/wordpress/indexing/setting-up-algolia/?language=php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/wp-cli.php';
require_once __DIR__ . '/serializer.php';

add_action('init', 'algolia_init');
add_filter('get_algolia_index_name', 'get_algolia_index_name');
add_action('save_post', 'algolia_save_post', 10, 3);

/**
 * Initialize Algolia PHP search client
 */
function algolia_init() {
    global $algolia;

    $algolia = \Algolia\AlgoliaSearch\SearchClient::create(
        getenv('ALGOLIA_APPLICATION_ID'),
        getenv('ALGOLIA_ADMIN_API_KEY')
    );

    $algoliaSerializer = new AlgoliaSerializer();
    $algoliaSerializer->run();
}

/**
 * Returns a prefixed Algolia index name for the given name
 * https://www.algolia.com/doc/integration/wordpress/indexing/importing-content/?language=php#customizing-algolia-index-name
 *
 * @param string $name index name without prefix
 *
 * @return string example: local_wp_ (with no $name) or local_wp_global_search
 */
function get_algolia_index_name($name = '') {
    global $wpdb;

    $env_prefix = getenv('ALGOLIA_INDEX_PREFIX') ?: ''; // local, dev, stage, prod, etc.
    $base_prefix = $wpdb->base_prefix; // wp_

    return "${env_prefix}_${base_prefix}${name}";
}

/**
 * Automatically reindexes records in Algolia when a post is saved
 * https://www.algolia.com/doc/integration/wordpress/indexing/automatic-updates/?language=php
 *
 * @param integer $id     Post ID
 * @param object  $post   Post object
 * @param bool    $update Whether this is an existing post getting updated
 *
 * @return array
 */
function algolia_save_post($id, $post, $update) {
    global $algolia;

    $post_type = $post->post_type;
    $post_status = $post->post_status;

    $searchable_post_types = getSearchablePostTypes();

    if (in_array($post_type, $searchable_post_types)) {
        // Only reindex posts that have been published or trashed
        $is_invalid_status = $post_status != 'publish' && $post_status != 'trash';

        if (wp_is_post_revision($id) || wp_is_post_autosave($id) || $is_invalid_status) {
            return $post;
        }

        $filter_name = $post_type.'_to_record';

        // Bail early if filter does not exist
        if (!has_filter($filter_name)) {
            return;
        }

        // Serialize post
        $records = apply_filters($filter_name, $post);
        if (!$records) {
            return $post;
        }

        $records = (array) $records;

        // Get index
        $canonical_index_name = apply_filters('get_algolia_index_name', 'global_search');
        $global_index = $algolia->initIndex($canonical_index_name);

        // Delete all records using the distinct_key attribute
        $filter_to_delete = 'distinct_key:'.$records[0]['distinct_key'];

        // Make sure to delete split records if they exist
        $global_index->deleteBy(['filters' => $filter_to_delete]);

        if ($post_status == 'publish') {
            $global_index->saveObjects($records);
        }
    }

    return $post;
}
