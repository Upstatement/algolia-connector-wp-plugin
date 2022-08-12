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

add_action( 'init', __NAMESPACE__ . '\\init' );
add_action( 'save_post', __NAMESPACE__ . '\\save_post', 10, 3 );

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
 * @param integer $id     Post ID.
 * @param object  $post   Post object.
 * @param bool    $update Whether this is an existing post getting updated.
 *
 * @return void
 */
function save_post( $id, $post, $update ) {
	global $algolia;

	// Attribute used to delete outdated records that currently exist on Algolia.
	$attribute_for_distinct_key = 'distinct_key';

	$post_status = $post->post_status;

	// Serialize post.
	$records = serialize_post( $id, $post );

	// Bail if serializer filter returns no records.
	if ( ! $records ) {
		return;
	}

	// Create Algolia index.
	$index_name    = Filters\get_index_name( $post );
	$algolia_index = $algolia->initIndex( $index_name );

	// Get all unique $attribute_for_distinct_key values.
	$all_filter_key_values = Utils\get_unique_key_values(
		$records,
		$attribute_for_distinct_key
	);
	$filter_string         = Utils\chain_filters(
		$attribute_for_distinct_key,
		$all_filter_key_values
	);

	// Delete posts with matching $attribute_for_distinct_key.
	$algolia_index->deleteBy( array( 'filters' => $filter_string ) );

	if ( ! Filters\is_indexable( $id, $post ) ) {
		// Delete post if not indexable anymore.
		$algolia_index->deleteObjects(
			array_map(
				function ( $record ) {
					return $record['objectID'];
				},
				$records
			)
		);
	} elseif ( 'publish' === $post_status ) {
		// Otherwise, save on publish!
		$algolia_index->saveObjects( $records );
	}
}

/**
 * Serialize the given post.
 *
 * @param string $id   ID of post.
 * @param object $post WP Post object.
 *
 * @return array
 */
function serialize_post( $id, $post ) {
	// Reformat post_type.
	$post_type = Utils\transform_type( $post->post_type );

	// Get serializer filter name.
	$filter_name = Utils\get_serializer_filter( $post_type );

	// Bail if post doesn't have serializer.
	if ( ! has_filter( $filter_name ) ) {
		return null;
	}

	// Serialize post.
	$records = apply_filters( $filter_name, $post );

	return $records;
}
