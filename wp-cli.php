<?php
namespace UpsAlgolia\CLI;

use UpsAlgolia\Utils;

if (! (defined('WP_CLI') && \WP_CLI)) {
  return;
}

/**
 * Gets an array of searchable post types
 *
 * @return array
 */
function get_searchable_post_types() {
  return get_post_types(
    array(
      'public' => true,
      'exclude_from_search' => false
    )
  );
}

/**
 * Query for posts and and serialize them to be saved as records in Algolia
 *
 * @return void
 */
function serialize_records($index, $post_type, $assoc_args) {
  $paged = 1;
  $count = 0;

  do {
    $posts = new \WP_Query([
      'posts_per_page' => 100,
      'paged' => $paged,
      'post_type' => $post_type,
      'post_status' => 'publish',
    ]);

    if (!$posts->have_posts()) {
      break;
    }

    try {
      $all_records = [];

      // Serialize each post to be saved as Algolia records
      foreach ($posts->posts as $post) {
        \WP_CLI::line("Serializing [$post->post_type] $post->post_title");

        // Use post type to get corresponding serializer function
        $post_type = Utils\transform_type($post->post_type);
        $filter_name = Utils\get_serializer_filter($post_type);

        $records = apply_filters($filter_name, $post);

        // Bail early if no records given or filter does not exist
        if (!$records) {
          throw new Exception("No filter called $filter_name");
        }

        // The serialize function will take care of splitting large records
        $records = (array) $records;
        $all_records = array_merge($all_records, $records);

        $count++;
      }

      if (isset($assoc_args['verbose'])) {
        \WP_CLI::line('Sending batch...');
      }

    } catch (Exception $e) {
      \WP_CLI::error($e->getMessage());
    }

    // Save the records in Algolia!
    // https://www.algolia.com/doc/api-reference/api-methods/save-objects/
    try {
      $index->saveObjects($all_records);

      \WP_CLI::success("$count $post_type records indexed in Algolia");

    } catch (Exception $e) {
      \WP_CLI::error($e->getMessage());
    }

    $paged++;

  } while (true);
}



class Algolia_Command {
  /**
   * Reindex the records of a certain type from given index.
   *
   * `wp algolia reindex --index=<index_name> --type=<record_type>`
   */
  public function reindex($args, $assoc_args) {
    global $algolia;

    $this->clear($args, $assoc_args);

    // Get Algolia index and post type arguments
    $index_name = $assoc_args['index'] ?? null;
    $type = $assoc_args['type'] ?? null;

    $searchable_post_types = get_searchable_post_types();

    // Bail early if type arg is not valid
    if ($type && !in_array($type, $searchable_post_types)) {
      \WP_CLI::error("$type is not a valid post type!");
      return;
    }

    $index = $algolia->initIndex($index_name);

    // Reindex post type only if argument was not specified OR type matches
    // specified argument
    foreach ($searchable_post_types as $post_type) {
      if (is_null($type) || $type === $post_type) {
        serialize_records($index, $post_type, $assoc_args);
      }
    }
  }

  /**
   * Clear the records of a certain type from given index.
   *
   * `wp algolia clear --index=<index_name> --type=<record_type>`
   */
  public function clear($args, $assoc_args) {
    global $algolia;

    $index_name = $assoc_args['index'] ?? null;
    $type = $assoc_args['type'] ?? null;

    // Require an index to clear from
    if (!isset($index_name)) {
      \WP_CLI::error("Please provide an index to clear from");
      return;
    }

    $index = $algolia->initIndex($index_name);

    // Delete records by type if given, otherwise clear all from index
    if (isset($type)) {
      \WP_CLI::line('Deleting all records of type ' . \WP_CLI::colorize("%b$type%n") . ' from ' . \WP_CLI::colorize("%p$index_name%n"));
      $index->deleteBy([ 'filters' => "type:\"${type}\"",])->wait();

    } else {
      \WP_CLI::line('Clearing all records from ' . \WP_CLI::colorize("%p$index_name%n"));
      $index->clearObjects()->wait();
    }
  }

  /**
   * Push Algolia settings to provided index.
   *
   * `wp algolia push_settings --index=<index_name>`
   */
  public function push_settings($args, $assoc_args) {
    global $algolia;

    $index_name = $assoc_args['index'] ?? null;
    $settings = apply_filters('UpsAlgolia\get_algolia_settings', null);

    if (!$settings) {
      \WP_CLI::error('Please provide valid settings from your theme by hooking into the UpsAlgolia\get_algolia_settings filter.');
      return;
    }

    $indices;

    if ($index_name) {
        $indices = [$index_name];
    } else {
        $list_indices = (array) $algolia->listIndices();
        $indices = array_map(
          function ($index) {
            return $index['name'];
          },
          $list_indices['items']
        );
    }


    foreach ($indices as $idx) {
        $index = $algolia->initIndex($idx);
        $index->setSettings($settings);
        \WP_CLI::line("Configured settings for ${idx}");
    }

  }
}

\WP_CLI::add_command('algolia', __NAMESPACE__ . '\\Algolia_Command');
