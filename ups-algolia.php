<?php
/**
 * Plugin Name:     Upstatement Algolia Admin
 * Description:     Search records serialization and index management for Algolia
 * Text Domain:     ups-algolia
 * Version:         1.0.0
 * Author:          Upstatement
 * Author URI:      https://www.upstatement.com
 *
 * @package         UpsAlgolia
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

  $algolia_app = Filters\get_algolia_application();

  $algolia = \Algolia\AlgoliaSearch\SearchClient::create(
    $algolia_app['application_id'],
    $algolia_app['admin_key'],
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
 * @return void
 */
function save_post($id, $post, $update) {
  global $algolia;

  // Attribute used to delete outdated records
  // that currently exist on Algolia
  $ATTRIBUTE_FOR_DISTINCT_KEY = 'distinct_key';

  $post_status = $post->post_status;

  // Serialize post
  $records = serialize_post($id, $post);

  // Bail if serializer filter returns no records
  if (!$records) {
    return;
  }

  // Create Algolia index
  $index_name = Filters\get_index_name($post);
  $algolia_index = $algolia->initIndex($index_name);

  // Get all unique $ATTRIBUTE_FOR_DISTINCT_KEY values
  $all_filter_key_values = Utils\get_unique_key_values($records, $ATTRIBUTE_FOR_DISTINCT_KEY);
  $filter_string = Utils\chain_filters($ATTRIBUTE_FOR_DISTINCT_KEY, $all_filter_key_values);

  // Delete posts with matching $ATTRIBUTE_FOR_DISTINCT_KEY
  $algolia_index->deleteBy(['filters' => $filter_string]);

  if ($post_status == 'publish') {
    $algolia_index->saveObjects($records);
  }
}

/**
 * Serialize the given post.
 *
 * @param string $id   id of post
 * @param object $post WP Post object
 *
 * @return array
 */
function serialize_post($id, $post) {
  // Reformat post_type
  $post_type = Utils\transform_type($post->post_type);

  // Bail if post is not indexable
  if (!Filters\is_indexable($id, $post)) {
    return null;
  }

  // Get serializer filter name
  $filter_name = Utils\get_serializer_filter($post_type);

  // Bail if post doesn't have serializer
  if (!has_filter($filter_name)) {
    return null;
  }

  // Serialize post
  $records = apply_filters($filter_name, $post);

  return $records;
}
