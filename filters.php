<?php
namespace UpsAlgolia\Filters;

/**
 * Does this post need to be indexed?
 *
 * @param string $id    id of post
 * @param object $post  post
 *
 * @returns bool
 */
function is_indexable($id, $post) {
  if (has_filter('UpsAlgolia\is_indexable')) {
    return apply_filters('UpsAlgolia\is_indexable', $id, $post);
  }

  $post_status = $post->post_status;

  // Only reindex posts that have been published or trashed
  $is_valid_status = $post_status === 'publish' || $post_status === 'trash';
  $revision_or_autosave = wp_is_post_revision($id) || wp_is_post_autosave($id);

  return $is_valid_status && !$revision_or_autosave;
}

/**
 * Get Algolia index name.
 *
 * @param object $post post
 *
 * @returns string
 */
function get_index_name($post) {
  if (has_filter('UpsAlgolia\get_index_name')) {
    return apply_filters('UpsAlgolia\get_index_name', $post);
  }

  return 'global_search';
}
