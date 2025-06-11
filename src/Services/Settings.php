<?php
/**
 * Store settings for the Algolia connection.
 *
 * @package AlgoliaConnector
 */

namespace Upstatement\AlgoliaConnector\Services;

/** Class */
class Settings {
	/**
	 * Scaffold settings.
	 */
	public function __construct() {
		add_option( 'algolia_application_id', '' );
		add_option( 'algolia_search_api_key', '' );
		add_option( 'algolia_admin_api_key', '' );
		add_option( 'algolia_index_name', '' );
		add_option( 'algolia_api_is_reachable', 'no' );
	}

	/**
	 * Gets the application ID.
	 *
	 * @return string
	 */
	public function get_application_id(): string {
		if (defined('ALGOLIA_APPLICATION_ID')) {
			return ALGOLIA_APPLICATION_ID;
		}
		return sanitize_text_field( $_ENV['ALGOLIA_APPLICATION_ID'] ?? (string) get_option( 'algolia_application_id', '' ) );
	}

	/**
	 * Gets the search API key.
	 *
	 * @return string
	 */
	public function get_search_api_key(): string {
		if (defined('ALGOLIA_SEARCH_API_KEY')) {
			return ALGOLIA_SEARCH_API_KEY;
		}
		return sanitize_text_field( $_ENV['ALGOLIA_SEARCH_API_KEY'] ?? (string) get_option( 'algolia_search_api_key', '' ) );
	}

	/**
	 * Gets the admin API key.
	 *
	 * @return string
	 */
	public function get_admin_api_key(): string {
		if (defined('ALGOLIA_ADMIN_API_KEY')) {
			return ALGOLIA_ADMIN_API_KEY;
		}
		return sanitize_text_field( $_ENV['ALGOLIA_ADMIN_API_KEY'] ?? (string) get_option( 'algolia_admin_api_key', '' ) );
	}

	/**
	 * Gets the search API key.
	 *
	 * @return string
	 */
	public function get_index_name(): string {
		if (defined('ALGOLIA_INDEX_NAME')) {
			return ALGOLIA_INDEX_NAME;
		}
		return sanitize_text_field( $_ENV['ALGOLIA_INDEX_NAME'] ?? (string) get_option( 'algolia_index_name', '' ) );
	}

	/**
	 * Checks if the API is reachable.
	 *
	 * @return bool
	 */
	public function get_api_is_reachable() {
		$enabled = get_option( 'algolia_api_is_reachable', 'no' );
		return 'yes' === $enabled;
	}

	/**
	 * Set the API is reachable option setting.
	 *
	 * @author  WebDevStudios <contact@webdevstudios.com>
	 * @since   1.0.0
	 *
	 * @param bool $flag If the API is reachable or not, 'yes' or 'no'.
	 */
	public function set_api_is_reachable( $flag ) {
		$value = (bool) true === $flag ? 'yes' : 'no';
		update_option( 'algolia_api_is_reachable', $value );
	}
}
