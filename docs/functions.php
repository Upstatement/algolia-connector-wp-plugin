/**
 * This is an example of hooking into UpsAlgolia's filters
 * in WordPress' functions.php file.
 */

<?php

// UpsAlgolia credentials and configuration
add_filter('UpsAlgolia\get_algolia_application', 'get_algolia_application');
add_filter('UpsAlgolia\get_algolia_settings', 'get_algolia_settings');
add_filter('UpsAlgolia\get_index_name', 'get_index_name');
add_filter('UpsAlgolia\is_indexable', 'is_indexable', 10, 2);


// Post type serializer functions.
add_filter('UpsAlgolia\post_to_record', 'post_to_record');
add_filter('UpsAlgolia\page_to_record', 'page_to_record');

/**
 * Get Algolia application id and admin key.
 *
 * @return object
 */
function get_algolia_application()
{
  return [
    "application_id" => 'ALGOLIA_APPLICATION_ID',
    "admin_key" => 'ALGOLIA_ADMIN_KEY'
  ];
}

/**
 * Get Algolia settings
 *
 * ObjectID is unique to each individual record, and `distinct_key` is used for
 * large records (> 10kb) that need to be split into multiple parts
 *
 * @return object
 */
function get_algolia_settings()
{
  return [
    "attributesForFaceting" => [
      'type',
      'distinct_key',
    ],

    "attributeForDistinct" => 'distinct_key'
  ];
}

/**
 * Get Algolia index name. All posts
 * use the same global index.
 *
 * @param object $post post
 *
 * @return string
 */
function get_index_name($post)
{
  return "global_search";
}

/**
 * Does this post need to be indexed?
 *
 * A post is indexable if
 * 1. post status is `publish` or `trash`
 * 2. post is not on revision or autosave
 *
 * @param $id   id of post
 * @param $post post
 *
 * @return bool
 */
function is_indexable($id, $post)
{
  $post_status = $post->post_status;

  // Only reindex posts that have been published or trashed
  $is_valid_status = $post_status === 'publish' || $post_status === 'trash';
  $revision_or_autosave = wp_is_post_revision($id) || wp_is_post_autosave($id);

  return $is_valid_status && !$revision_or_autosave;
}

/**
 * Converts a Post to an Algolia record
 *
 * @param object $post Post to get record of
 *
 * @return array
 */
function post_to_record($post)
{
  $post_record_attrs = [
    'excerpt' => $get_the_excerpt($post),
  ];

  return serialize_record($post, $post_record_attrs);
}

/**
 * Converts a Page to an Algolia record
 *
 * @param object $post Post to get record of
 *
 * @return array
 */
function page_to_record($post)
{
  return serialize_record($post);
}

/**
 * Generate a unique id for given post.
 *
 * @param string $blog_id ID of current blog
 * @param object $post    Post to get record of
 * @param string $order   Order number for record
 *
 * @return string
 */
function generate_id($blog_id, $post, $order = '')
{
  $attributes = array_filter([$blog_id, $post->post_type, $post->ID, $order]);
  return implode('-', $attributes);
}

/**
 * Get default attributes for each Algolia record
 *
 * @param object $post    Post to get record of
 * @param string $blog_id ID of current blog
 * @param string $order   Order number for record
 *
 * @return array
 */
function get_default_record_attributes($post, $blog_id, $order)
{
  return [
    'objectID' => generate_id($blog_id, $post, $order),
    'blogID' => get_current_blog_id(),
    'type' => $post->post_type,
    'title' => $post->post_title,
    'url' => get_permalink($post->ID),
  ];
}

/**
 * Split given text into chunks of 1000 characters
 *
 * @param string $text text to split
 *
 * @return array
 */
function split($text)
{
  $char_limit = 1000;
  return str_split(strip_tags($text), $char_limit);
}

/**
 * Get records for the given post
 *
 * @param object $post       Post to get records for
 * @param array  $post_attrs Post-specific record attributes
 *
 * @return array
 */
function serialize_record($post, $post_attrs = [])
{
  $blog_id = get_current_blog_id();
  $split_content = split($post->post_content);

  $records = array_map(
    function ($content, $index) use ($post, $post_attrs, $blog_id) {
      return array_merge(
        get_default_record_attributes($post, $blog_id, $index + 1),
        $post_attrs,
        [ 'content' => $content, 'distinct_key' => generate_id($blog_id, $post) ]
      );
    },
    $split_content,
    array_keys($split_content)
  );

  return $records;
}
