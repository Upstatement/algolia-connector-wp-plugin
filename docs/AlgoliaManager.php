<?php
namespace HarvardDCE\Managers;

use function Ups\Blocks\get_post_bylines;

class AlgoliaManager
{
  /**
   * Add UpsAlgolia filters and Algolia environment variables
   * to Timber context
   *
   * @return void
   */
  public function run()
  {
    // UpsAlgolia credentials and configuration
    add_filter('UpsAlgolia\get_algolia_application', [$this, 'get_algolia_application']);
    add_filter('UpsAlgolia\get_algolia_settings', [$this, 'get_algolia_settings']);
    add_filter('UpsAlgolia\get_index_name', [$this, 'get_index_name']);
    add_filter('UpsAlgolia\is_indexable', [$this, 'is_indexable'], 10, 2);

    // UpsAlgolia serializer functions
    add_filter('UpsAlgolia\post_to_record', [$this, 'post_to_record']);
    add_filter('UpsAlgolia\page_to_record', [$this, 'page_to_record']);

    // Provide Algolia environment variables to Timber context
    add_filter(
        'timber/context',
        function ($context) {
          $context['ALGOLIA_APPLICATION_ID'] = getenv('ALGOLIA_APPLICATION_ID');
          $context['ALGOLIA_SEARCH_ONLY_API_KEY'] = getenv('ALGOLIA_SEARCH_ONLY_API_KEY');
          $context['ALGOLIA_INDEX_NAME'] = $this->get_index_name();
          $context['BLOG_ID'] = get_current_blog_id();

          return $context;
        }
    );
  }

  /**
   * Get Algolia application id and admin key.
   *
   * @return object
   */
  public function get_algolia_application()
  {
    return [
        "application_id" => getenv('ALGOLIA_APPLICATION_ID'),
        "admin_key" => getenv('ALGOLIA_ADMIN_KEY')
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
  public function get_algolia_settings()
  {
    return [
      "attributesForFaceting" => [
        'type',
        'sub_type',
        'distinct_key',
        'blogID',
        'blog_name',
        'program_months',
        'subject_areas',
        'modes'
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
  public function get_index_name($post = null)
  {
    $algolia_env = getenv('ALGOLIA_ENVIRONMENT');
    return "${algolia_env}_global";
  }

  /**
   * Does this post need to be indexed?
   *
   * A post is indexable if
   * 1. not on local development mode
   * 2. post status is `publish` or `trash`
   * 3. post is not on revision or autosave
   *
   * @param $id   id of post
   * @param $post post
   *
   * @return bool
   */
  public function is_indexable($id, $post)
  {
    if (getenv('WP_ENV') === 'development') {
      return false;
    }

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
  public function post_to_record($post)
  {
    $post_record_attrs = [
      'excerpt' => $get_the_excerpt($post),
    ];

    return $this->serialize_record($post, $post_record_attrs);
  }

  /**
   * Converts a Page to an Algolia record
   *
   * @param object $post Post to get record of
   *
   * @return array
   */
  public function page_to_record($post)
  {
    return $this->serialize_record($post);
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
  private function generate_id($blog_id, $post, $order = '')
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
  private function get_default_record_attributes($post, $blog_id, $order)
  {
    return [
      'objectID' => $this->generate_id($blog_id, $post, $order),
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
  private function split($text)
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
  private function serialize_record($post, $post_attrs = [])
  {
    $blog_id = get_current_blog_id();
    $split_content = $this->split($post->post_content);

    $records = array_map(
        function ($content, $index) use ($post, $post_attrs, $blog_id) {
            return array_merge(
                $this->get_default_record_attributes($post, $blog_id, $index + 1),
                $post_attrs,
                [ 'content' => $content, 'distinct_key' => $this->generate_id($blog_id, $post) ]
            );
        },
        $split_content,
        array_keys($split_content)
    );

    return $records;
  }

}
