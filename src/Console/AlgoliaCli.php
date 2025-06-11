<?php
/**
 * Adds CLI functionality.
 *
 * @package AlgoliaConnector
 */

namespace Upstatement\AlgoliaConnector\Console;

use WP_CLI;
use WP_Post;
use Upstatement\AlgoliaConnector\AlgoliaConnector;
use Upstatement\AlgoliaConnector\Services\Algolia;
use Upstatement\AlgoliaConnector\Services\Settings;
use Upstatement\AlgoliaConnector\Services\Theme;

/**
 * Push and re-index records into Algolia indices.
 */
class AlgoliaCli {
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
	 * Algolia_CLI constructor.
	 *
	 * @param AlgoliaConnector $plugin Instance of the plugin.
	 */
	public function __construct( AlgoliaConnector $plugin ) {
		$this->algolia  = $plugin->get_algolia();
		$this->settings = $plugin->get_settings();
		$this->theme    = $plugin->get_theme();
	}

	/**
	 * Push all records to Algolia for a given index.
	 *
	 * ## OPTIONS
	 *
	 * [--index=<value>]
	 * : The name of the index.
	 *
	 * [--types=<value>]
	 * : Post type(s) to reindex, separated by commas.
	 *
	 * [--clear]
	 * : Clear all existing records prior to pushing the records.
	 *
	 * ## EXAMPLES
	 *
	 *     wp algolia reindex
	 *     wp algolia reindex --type=post,page
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function reindex( $args, $assoc_args ) {

		if ( ! $this->settings->get_api_is_reachable() ) {
			WP_CLI::warning( 'Your application is not hooked up to an Algolia application.' );
			die;
		}

		$index_flag = WP_CLI\Utils\get_flag_value( $assoc_args, 'index' );
		$post_types = WP_CLI\Utils\get_flag_value( $assoc_args, 'types' );
		$clear      = WP_CLI\Utils\get_flag_value( $assoc_args, 'clear' );
		$index_name = $index_flag ?? $this->settings->get_index_name();

		if ( ! $index_name ) {
			WP_CLI::error( 'You need to either provide an index name or have one specified in your settings.' );
		}

		if ( $clear ) {
			WP_CLI::log( 'Clearing the index first...' );
			$this->algolia->clear_obejcts( $index_name );
		}

		$indexable_post_types = ! empty( $post_types ) ? explode( ',', $post_types ) : $this->theme->get_indexable_post_types();

		if ( empty( $indexable_post_types ) ) {
			WP_CLI::warning( 'No post types have been identified as indexable.' );
			die;
		}

		WP_CLI::log( 'Indexing posts from the following types: ' . join( ', ', $indexable_post_types ) );

		$this->algolia->reindex_all( $indexable_post_types );

		WP_CLI::success( 'Done' );
	}

	/**
	 * Reindex a single post.
	 *
	 * ## Options
	 *
	 * <post_id>
	 * : The ID of the post.
	 *
	 * ## Examples
	 *
	 *     wp algolia reindex_single 123
	 *
	 * @param array $args Positional arguments.
	 */
	public function reindex_single( $args ) {
		if ( ! $this->settings->get_api_is_reachable() ) {
			WP_CLI::warning( 'Your application is not hooked up to an Algolia application.' );
			die;
		}

		$post_id = $args[0] ?? null;

		if ( empty( $post_id ) ) {
			WP_CLI::error( 'A post ID is required.' );
		}

		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			WP_CLI::error( 'Invalid post.' );
		}

		$this->algolia->delete_for_post( $post_id );

		// If the post is anything but published, delete it from Algolia.
		if ( 'publish' === $post->post_status ) {
			$records = $this->theme->post_to_records( $post );
			if ( $records ) {
				$this->algolia->save_objects( $records );
			}
		}
	}

	/**
	 * Delete all records in an index.
	 *
	 * ## OPTIONS
	 *
	 * [--index=<value>]
	 * : The name of the index.
	 *
	 * ## EXAMPLES
	 *
	 *     wp algolia clear
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function clear( $args, $assoc_args ) {

		if ( ! $this->settings->get_api_is_reachable() ) {
			WP_CLI::warning( 'Your application is not hooked up to an Algolia application.' );
			die;
		}

		$index_flag = WP_CLI\Utils\get_flag_value( $assoc_args, 'index' );
		$index_name = $index_flag ?? $this->settings->get_index_name();

		if ( ! $index_name ) {
			WP_CLI::error( 'You need to either provide an index name or have one specified in your settings.' );
		}

		$this->algolia->clear_obejcts( $index_name );

		WP_CLI::success( 'Done' );
	}

	/**
	 * Test the connection.
	 *
	 * ## EXAMPLES
	 *
	 *     wp algolia test_connection
	 */
	public function test_connection() {
		if ( $this->settings->get_api_is_reachable() ) {
			WP_CLI::success( 'Algolia application is connected.' );
		} else {
			WP_CLI::warning( 'Algolia is not connected. Be sure that you have valid credentials set up.' );
		}
	}
}
