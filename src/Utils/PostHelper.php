<?php
/**
 * Collection ot utilities for WordPress posts.
 *
 * @package AlgoliaConnector
 */

namespace Upstatement\AlgoliaConnector\Utils;

use WP_Post;

/** Class */
class PostHelper {
	/**
	 * Get a distinct ID for a post object.
	 *
	 * @param WP_Post|int|string $post Post ID or object.
	 *
	 * @return string
	 */
	public static function distinct_key( WP_Post|int|string $post ): string {
		$post = $post instanceof WP_Post ? $post : get_post( $post );

		if ( ! $post instanceof WP_Post ) {
			return '';
		}

		return implode( '#', array( $post->post_type, $post->ID ) );
	}
}
