<?php
/**
 * Plugin Name:     Upstatement Algolia
 * Description:     Search records indexing for Algolia
 * Text Domain:     ups-algolia
 * Version:         1.0.0
 * Author:          Upstatement
 * Author URI:      https://www.upstatement.com*
 *
 * @package         Ups_Algolia
 */

namespace UpsAlgolia;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/filters.php';
require_once __DIR__ . '/wp-cli.php';

add_action('init', __NAMESPACE__ . '\\init');
add_action('save_post', __NAMESPACE__ . '\\save_post', 10, 3);

/**
 * Initialize a global Algolia PHP search client
 *
 * @return void
 */
function init() {
  global $algolia;

  $algolia = \Algolia\AlgoliaSearch\SearchClient::create(
    getenv('ALGOLIA_APPLICATION_ID'),
    getenv('ALGOLIA_ADMIN_KEY')
  );
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
function save_post($id, $post, $update) {
  // Don't save posts on development mode
  if (getenv('WP_ENV') === 'development') {
    return;
  }

  global $algolia;
  $FILTER_KEY = 'distinct_key';

  $post_type = Utils\transform_type($post->post_type);
  $post_status = $post->post_status;

  if (!Filters\is_indexable($id, $post)) {
    return;
  }

  // Get serializer filter name
  $filter_name = Utils\get_serializer_filter($post_type);

  if (!has_filter($filter_name)) {
    return;
  }

  // Serialize post
  $records = apply_filters($filter_name, $post);

  // Bail if no records given or filter does not exist
  if (!$records) {
    return;
  }

  $records = (array) $records;

  // Create algolia index
  $index_name = Filters\get_index_name($post);
  $algolia_index = $algolia->initIndex($index_name);

  // Get all unique $FILTER_KEY values
  $all_filter_key_values = Utils\get_unique_key_values($records, $FILTER_KEY);
  $filter_string = Utils\chain_filters($FILTER_KEY, $all_filter_key_values);

  // Delete posts with matching $FILTER_KEY
  $algolia_index->deleteBy(['filters' => $filter_string]);

  if ($post_status == 'publish') {
    $algolia_index->saveObjects($records);
  }
}
