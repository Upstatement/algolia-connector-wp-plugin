<?php
/**
 * Handle configuration that can be set at the theme level.
 *
 * @package AlgoliaConnector
 */

namespace Upstatement\AlgoliaConnector\Services;

use WP_Post;
use Upstatement\AlgoliaConnector\Utils\HtmlSplitter;
use Upstatement\AlgoliaConnector\Utils\PostHelper;

/** Class */
class Theme {

	/**
	 * The indexable post types.
	 *
	 * @var array
	 */
	private $indexable_post_types = array();

	/**
	 * Scaffold configuration.
	 */
	public function __construct() {
		$this->indexable_post_types = apply_filters( 'algolia_indexable_post_types', array() );
	}

	/**
	 * Get the list of indexable post types.
	 *
	 * @return array
	 */
	public function get_indexable_post_types(): array {
		return $this->indexable_post_types;
	}

	/**
	 * Converts a post object to an Algolia record, and returns an array of split
	 * records as necessary to represent the post.
	 *
	 * @param WP_Post $post The post being indexed.
	 *
	 * @return array[]|null
	 */
	public function post_to_records( WP_Post $post ): ?array {
		$post_type_label  = $post->post_type;
		$post_type_object = get_post_type_object( $post->post_type );

		if ( $post_type_object ) {
			$post_type_label = $post_type_object->labels->singular_name ?? $post->post_type;
		}

		$record = array(
			'distinct_key' => PostHelper::distinct_key( $post ),
			'post_id'      => $post->ID,
			'title'        => $post->post_title,
			'post_type'    => $post_type_label,
			'url'          => get_permalink( $post ),
			'boost'        => 10,
			'content'      => '',
		);

		$record = apply_filters( "algolia_{$post->post_type}_to_record", $record );

		if ( empty( $record ) ) {
			return null;
		}

		$splitter = new HtmlSplitter();
		$records  = $splitter->split( $record );

		// Merge the common attributes into each split and add a unique `objectID`.
		foreach ( $records as $key => $split ) {
			$records[ $key ] = array_merge(
				$record,
				$split,
				array(
					'objectID' => implode( '-', array( $post->post_type, $post->ID, $key ) ),
				)
			);
		}

		return $records;
	}
}
