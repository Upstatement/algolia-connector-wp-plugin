<?php
/**
 * Watcher service to watch for post changes to trigger syncs with Algolia.
 *
 * @package AlgoliaConnector
 */

namespace Upstatement\AlgoliaConnector\Services;

use Upstatement\AlgoliaConnector\AlgoliaConnector;
use Upstatement\AlgoliaConnector\Services\Algolia;
use Upstatement\AlgoliaConnector\Services\Settings;
use Upstatement\AlgoliaConnector\Services\Theme;

/** Class */
class Watcher {

	/**
	 * Algolia service.
	 *
	 * @var Algolia
	 */
	private $algolia;

	/**
	 * Algolia settings service.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Theme service.
	 *
	 * @var Theme
	 */
	private $theme;


	/**
	 * Initialize.
	 *
	 * @param AlgoliaConnector $plugin Plugin instance.
	 *
	 * @return void
	 */
	public function __construct( AlgoliaConnector $plugin ) {
		$this->algolia  = $plugin->get_algolia();
		$this->settings = $plugin->get_settings();
		$this->theme    = $plugin->get_theme();
	}

	/**
	 * Adds actions to watch for changes.
	 *
	 * @return void
	 */
	public function watch(): void {
		add_action( 'wp_after_insert_post', array( $this, 'sync_item' ), 999 );
		add_action( 'before_delete_post', array( $this, 'delete_item' ) );
	}

	/**
	 * Sync items on save.
	 *
	 * @param int $post_id ID of the post that has been saved.
	 *
	 * @return void
	 */
	public function sync_item( int $post_id ): void {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Exit if the API is unreachable or if there's no index name.
		if ( ! $this->settings->get_api_is_reachable() || empty( $this->settings->get_index_name() ) ) {
			return;
		}

		$post                 = get_post( $post_id );
		$indexable_post_types = $this->theme->get_indexable_post_types();

		// Bail if there's no post object or the post type is not in the list of
		// indexable types.
		if ( empty( $post ) || ! in_array( $post->post_type, $indexable_post_types, true ) ) {
			return;
		}

		// Delete all records for this post since they may have been split.
		$this->delete_item( $post_id );

		// If the post is published, send it to Algolia.
		if ( 'publish' === $post->post_status ) {
			$records = $this->theme->post_to_records( $post );
			if ( $records ) {
				$this->algolia->save_objects( $records );
			}
		}
	}

	/**
	 * Delete an item.
	 *
	 * @param int $post_id ID of the post getting deleted.
	 *
	 * @return void
	 */
	public function delete_item( int $post_id ): void {
		// Exit if the API is unreachable or if there's no index name.
		if ( ! $this->settings->get_api_is_reachable() || empty( $this->settings->get_index_name() ) ) {
			return;
		}

		$this->algolia->delete_for_post( $post_id );
	}
}
