<?php
/**
 * Service to interact with Algolia.
 *
 * @package AlgoliaConnector
 */

namespace Upstatement\AlgoliaConnector\Services;

use WP_Query;
use WP_CLI;
use Algolia\AlgoliaSearch\Api\SearchClient;
use Upstatement\AlgoliaConnector\AlgoliaConnector;
use Upstatement\AlgoliaConnector\Services\Settings;
use Upstatement\AlgoliaConnector\Services\Theme;
use Upstatement\AlgoliaConnector\Utils\PostHelper;

/** Class */
class Algolia {

	/**
	 * Algolia client.
	 *
	 * @var SearchClient|null
	 */
	private $client;

	/**
	 * Algolia settings.
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
	 * Name of the configured index.
	 *
	 * @var string
	 */
	private $index_name;

	/**
	 * Constructor.
	 *
	 * @param AlgoliaConnector $plugin Plugin instance.
	 *
	 * @return void
	 */
	public function __construct( AlgoliaConnector $plugin ) {
		$this->client   = $plugin->get_client();
		$this->settings = $plugin->get_settings();
		$this->theme    = $plugin->get_theme();

		$this->index_name = $this->settings->get_index_name();
	}

	/**
	 * Clear all objects.
	 *
	 * @param string $index_name The name of the index.
	 *
	 * @return void
	 */
	public function clear_obejcts( ?string $index_name = null ): void {
		if ( ! $this->client ) {
			return;
		}

		$index_name = $index_name ?? $this->index_name;
		$this->client->clearObjects( $index_name );
	}

	/**
	 * Save multiple objects.
	 *
	 * @param array[] $records A list of object records to save.
	 *
	 * @return void
	 */
	public function save_objects( array $records ): void {
		try {
			$this->client->saveObjects( $this->index_name, $records );
		} catch ( \Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[Algolia error] There was a problem saving records: ' . $e->getMessage() );
		}
	}

	/**
	 * Delete all records with a specific post ID.
	 *
	 * @param int $post_id Remove records with this post ID.
	 *
	 * @return void
	 */
	public function delete_for_post( int $post_id ): void {
		try {
			$this->client->deleteBy( $this->index_name, array( 'filters' => 'post_id=' . $post_id ) );
		} catch ( \Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( "[Algolia error] There was a problem deleting records for post $post_id: " . $e->getMessage() );
		}
	}

	/**
	 * Reindex all content.
	 *
	 * @param array $post_types If passed, only reindex the given post type(s).
	 *
	 * @return void
	 */
	public function reindex_all( ?array $post_types = null ): void {
		if ( is_null( $post_types ) ) {
			$post_types = $this->theme->get_indexable_post_types();
		}

		$paged    = 1;
		$count    = 0;
		$progress = null;

		$query_args = array(
			'posts_per_page' => -1,
			'post_type'      => $post_types,
			'post_status'    => 'publish',
		);

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$total_posts = ( new WP_Query( $query_args ) )->found_posts;
			WP_CLI::log( "Reindexing $total_posts posts..." );
			$progress = WP_CLI\Utils\make_progress_bar( 'Reindexing', $total_posts );
		}

		do {
			$posts = new WP_Query(
				array_merge(
					$query_args,
					array(
						'posts_per_page' => 100,
						'paged'          => $paged,
					)
				)
			);

			if ( ! $posts->have_posts() ) {
				break;
			}

			$records = array();
			foreach ( $posts->posts as $post ) {
				$new_records = $this->theme->post_to_records( $post );

				if ( $new_records ) {
					$records = array_merge( $records, $new_records );
				}

				++$count;

				if ( $progress ) {
					// phpcs:ignore
					/** @phpstan-ignore class.notFound */
					$progress->tick();
				}
			}

			try {
				$this->save_objects( $records );
			} catch ( \Exception $e ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[Algolia error]: There was a problem saving a batch: ' . $e->getMessage() );
			}

			++$paged;
		} while ( true );

		if ( $progress ) {
			// phpcs:ignore
			/** @phpstan-ignore class.notFound */
			$progress->finish();
		}
	}
}
