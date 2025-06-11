<?php
/**
 * Baseline functionality for the Algolia Connector plugin.
 *
 * @package AlgoliaConnector
 */

namespace Upstatement\AlgoliaConnector;

use Upstatement\AlgoliaConnector\Admin\SettingsPage;
use Upstatement\AlgoliaConnector\Services\Algolia;
use Upstatement\AlgoliaConnector\Services\Settings;
use Upstatement\AlgoliaConnector\Services\Theme;
use Upstatement\AlgoliaConnector\Services\Watcher;
use Algolia\AlgoliaSearch\Api\SearchClient;
use Algolia\AlgoliaSearch\Exceptions\UnreachableException;
use Exception;

/** Class */
class AlgoliaConnector {
	/**
	 * Instance of this plugin.
	 *
	 * @var AlgoliaConnector|null
	 */
	private static $instance = null;

	/**
	 * Manages Algolia service.
	 *
	 * @var Algolia
	 */
	private $algolia;

	/**
	 * Manages settings saved.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Manages theme-level configuration.
	 *
	 * @var Theme
	 */
	private $theme;

	/**
	 * Algolia client.
	 *
	 * @var SearchClient|null
	 */
	private $client;

	/**
	 * Private constructor prevents multiple instances.
	 */
	private function __construct() {
		$this->settings = new Settings();
		$this->theme    = new Theme();

		$this->set_algolia_client();

		$this->algolia = new Algolia( $this );

		$watcher = new Watcher( $this );
		$watcher->watch();

		new SettingsPage( $this );
	}

	/**
	 * Class instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Set the Algolia client.
	 *
	 * @return void
	 */
	private function set_algolia_client(): void {
		if ( $this->settings->get_application_id() && $this->settings->get_admin_api_key() && $this->settings->get_index_name() ) {
			$this->client = SearchClient::create( $this->settings->get_application_id(), $this->settings->get_admin_api_key() );
		}
	}

	/**
	 * Get the settings.
	 *
	 * @return Settings
	 */
	public function get_settings(): Settings {
		return $this->settings;
	}

	/**
	 * Get the theme configuration.
	 *
	 * @return Theme
	 */
	public function get_theme(): Theme {
		return $this->theme;
	}

	/**
	 * Get the Algolia client.
	 *
	 * @return SearchClient|null
	 */
	public function get_client(): ?SearchClient {
		return $this->client;
	}

	/**
	 * Get the Algolia service.
	 *
	 * @return Algolia|null
	 */
	public function get_algolia(): ?Algolia {
		return $this->algolia;
	}

	/**
	 * Assert that the credentials are valid.
	 *
	 * @param string $admin_api_key The Algolia Admin API key to check.
	 * @param string $app_id Application ID, which will create a new instance of the
	 *                       Algolia client to test against.
	 *
	 * @return bool
	 *
	 * @throws Exception If the connection fails.
	 */
	public function valid_credentials( string $admin_api_key, $app_id = null ): bool {
		$client = ! is_null( $app_id )
			? SearchClient::create( $app_id, $admin_api_key )
			: $this->client;

		if ( is_null( $client ) ) {
			$this->get_settings()->set_api_is_reachable( false );
			throw new Exception( 'The Algolia client can\'t be initialized.' );
		}

		try {
			// If this call does not succeed, then the credentials are invalid.
			$key = $client->getApiKey( (string) $admin_api_key );
		} catch ( UnreachableException | \Exception $exception ) {
			$this->get_settings()->set_api_is_reachable( false );
			throw new Exception( esc_html( $exception->getMessage() ) );
		}

		$required_acls = array(
			'addObject',
			'deleteObject',
			'listIndexes',
			'deleteIndex',
			'settings',
			'editSettings',
		);

		$missing_acls = array();
		foreach ( $required_acls as $required_acl ) {
			if ( ! in_array( $required_acl, $key['acl'], true ) ) {
				$missing_acls[] = $required_acl;
			}
		}

		if ( ! empty( $missing_acls ) ) {
			$this->get_settings()->set_api_is_reachable( false );
			throw new Exception(
				'Your admin API key is missing the following ACLs: ' . esc_html( implode( ', ', $missing_acls ) )
			);
		}

		$this->get_settings()->set_api_is_reachable( true );

		return true;
	}
}
