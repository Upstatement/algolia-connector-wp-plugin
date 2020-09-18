<?php
namespace UpsAlgolia\CLI;

use UpsAlgolia;
use UpsAlgolia\Filters;
use UpsAlgolia\Utils;
use \Exception as Exception;

// Ensure that WP_CLI exists
if (! (defined('WP_CLI') && \WP_CLI)) {
  return;
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
      $cum_records = [];

      // Serialize each post to be saved as Algolia records
      foreach ($posts->posts as $post) {

        \WP_CLI::line("Serializing [$post->post_type] $post->post_title");

        // Serialize current post
        $records = UpsAlgolia\serialize_post(\get_the_id(), $post);

        if ($records) {
          $cum_records = array_merge($cum_records, $records);
          $count++;
        }
      }

    } catch (Exception $e) {
      \WP_CLI::error($e->getMessage());
    }

    // Save the records in Algolia!
    // https://www.algolia.com/doc/api-reference/api-methods/save-objects/
    try {
      $index->saveObjects($cum_records);

      \WP_CLI::success("$count $post_type records indexed in Algolia");

    } catch (Exception $e) {
      \WP_CLI::error($e->getMessage());
    }

    $paged++;

  } while (true);
}



class Algolia_Command {
  /**
   * Reindex the records of a post type from given index.
   *
   * @param string  index_name  name of Algolia index
   * @param string  post_type   post type to reindex
   * @param integer blog_id     blog id to pull posts from
   *
   * `wp algolia reindex <index_name> --type=<post_type> --blog_id=<blog_id>`
   */
  public function reindex($args, $assoc_args) {
    global $algolia;

    // Get Algolia index and post type arguments
    $index_name = $args[0] ?? null;
    $type = $assoc_args['type'] ?? null;

    $searchable_post_types = Utils\get_searchable_post_types();

    // Bail if type arg is not valid
    if ($type && !in_array($type, $searchable_post_types)) {
      \WP_CLI::error("$type is not a valid post type!");
      return;
    }

    $index = $algolia->initIndex($index_name);

    // Get all blog IDs in multisite network
    $blog_ids = $assoc_args['blog_id'] ? [$assoc_args['blog_id']] : get_sites([ 'fields' => 'ids' ]);

    // Index posts for each site in the multisite network
    foreach ($blog_ids as $blog_id) {
      switch_to_blog($blog_id);

      \WP_CLI::line("\n".'Indexing posts from '. \WP_CLI::colorize("%bBlog $blog_id%n")."\n");

      // Reindex post type only if argument was not specified OR type matches
      // specified argument
      foreach ($searchable_post_types as $post_type) {
        if (is_null($type) || $type === $post_type) {
          serialize_records($index, $post_type, $assoc_args);
        }
      }

      restore_current_blog();
    }
  }

  /**
   * Clear the records from given index and with given filters.
   *
   * @param string  index_name  name of Algolia index
   * @param key     attribute in Algolia records
   * @param value   value mapped to given attribute
   *
   * `wp algolia clear <index_name> [--<key>=<value>, ...]`
   */
  public function clear($args, $assoc_args) {
    global $algolia;

    $index_name = $args[0] ?? null;

    // Require an index to clear from
    if (!isset($index_name)) {
      \WP_CLI::error("Please provide an index to clear");
      return;
    }

    $index = $algolia->initIndex($index_name);

    // Delete records by filters if given, otherwise clear all
    if (count($assoc_args)) {
      $filters = Utils\map_into_filters($assoc_args, 'AND');

      \WP_CLI::line('Clearing records with filters: '
        . \WP_CLI::colorize("%b$filters%n")
        . ' from '
        . \WP_CLI::colorize("%p$index_name%n"));

      $index->deleteBy([ 'filters' => $filters ])->wait();

    } else {
      \WP_CLI::line('Clearing all records from '
        . \WP_CLI::colorize("%p$index_name%n"));

      $index->clearObjects()->wait();
    }
  }

  /**
   * Push Algolia config to index if provided, otherwise
   * send config to all available indices.
   *
   * @param string index_name  name of Algolia index
   * @param bool   settings    reconfigure settings
   * @param bool   synonyms    reconfigure synonyms
   * @param bool   rules       reconfigure rules
   *
   * `wp algolia push_config <index_name> --settings --synonyms --rules`
   */
  public function push_config($args, $assoc_args) {
    global $algolia;

    $index_name = $args[0] ?? null;

    $indices = [];

    if ($index_name) {
        $indices = [$index_name];
    } else {
        $list_indices = (array) $algolia->listIndices();
        $indices = array_column($list_indices['items'], 'name');
    }


    foreach ($indices as $idx) {
        $index = $algolia->initIndex($idx);

        // Bail early if index does not exist
        if (!$index->exists()) {
          \WP_CLI::error("Index $idx does not exist!");
          break;
        }

        // Set index settings if '--settings' flag exists
        if (isset($assoc_args['settings'])) {
            $settings = Filters\get_algolia_settings($idx);

            if ($settings) {
                $index->setSettings($settings);
                \WP_CLI::success('Pushed settings to '. \WP_CLI::colorize("%p$idx%n"));
            }
        }

        // Set index synonyms if '--synonyms' flag exists
        if (isset($assoc_args['synonyms'])) {
            $synonyms = Filters\get_algolia_synonyms($idx);

            if ($synonyms) {
                $index->replaceAllSynonyms($synonyms);
                \WP_CLI::success('Pushed synonyms to '. \WP_CLI::colorize("%p$idx%n"));
            }
        }

        // Set index rules if '--rules' flag exists
        if (isset($assoc_args['rules'])) {
            $rules = Filters\get_algolia_rules($idx);

            if ($rules) {
                $index->replaceAllRules($rules);
                \WP_CLI::success('Pushed rules to '. \WP_CLI::colorize("%p$idx%n"));
            }
        }
    }

  }
}

\WP_CLI::add_command('algolia', __NAMESPACE__ . '\\Algolia_Command');
