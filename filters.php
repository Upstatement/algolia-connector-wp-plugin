<?php
/**
 * Description:     Exposes filters
 *
 * @package         UpsAlgolia
 */

namespace UpsAlgolia\Filters;

use UpsAlgolia\Utils;
use \Exception as Exception;

/**
 * Does this post need to be indexed?
 *
 * @param string $id    ID of post.
 * @param object $post  WP post.
 *
 * @return bool
 */
function is_indexable( $id, $post ) {
	if ( has_filter( 'UpsAlgolia\is_indexable' ) ) {
		return apply_filters( 'UpsAlgolia\is_indexable', $id, $post );
	}

	$post_status = $post->post_status;

	// Only reindex posts that have been published or trashed.
	$is_valid_status      = 'publish' === $post_status || 'trash' === $post_status;
	$revision_or_autosave = wp_is_post_revision( $id ) || wp_is_post_autosave( $id );

	return $is_valid_status && ! $revision_or_autosave;
}

/**
 * Get credentials to Algolia application.
 *
 * @return object { "application_id": <Algolia application id>, "admin_key": <Algolia admin key> }
 */
function get_algolia_application() {
	return apply( 'UpsAlgolia\get_algolia_application', null );
}


/**
 * Get Algolia index name.
 *
 * @param object $post WP post.
 *
 * @return string
 */
function get_index_name( $post ) {
	return apply( 'UpsAlgolia\get_index_name', $post );
}


/**
 * Get Algolia settings.
 *
 * @param string $index Index name.
 *
 * @return object
 */
function get_algolia_settings( $index ) {
	return apply( 'UpsAlgolia\get_algolia_settings', $index );
}

/**
 * Get Algolia rules.
 *
 * @param string $index Index name.
 *
 * @return object
 */
function get_algolia_rules( $index ) {
	return apply( 'UpsAlgolia\get_algolia_rules', $index );
}

/**
 * Get Algolia synonyms.
 *
 * @param string $index Index name.
 *
 * @return object
 */
function get_algolia_synonyms( $index ) {
	return apply( 'UpsAlgolia\get_algolia_synonyms', $index );
}

/**
 * Apply the given filter with the arguments
 *
 * @param string $filter Filter name.
 * @param array  ...$args Arguments to be passed to filter.
 *
 * @return mixed
 */
function apply( $filter, ...$args ) {
	if ( has_filter( $filter ) ) {
		return apply_filters( $filter, ...$args );
	}

	Utils\throw_undefined_filter_exception( $filter );
}
